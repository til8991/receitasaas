<?php

use App\Services\Calculadora\CalculadoraException;
use App\Services\Calculadora\Dto\EstadoItem;
use App\Services\Calculadora\Dto\RegimeGeral\AliquotasNominais;
use App\Services\Calculadora\Dto\RegimeGeral\ItemInput as RgItem;
use App\Services\Calculadora\Dto\RegimeGeral\OperacaoInput as RgOperacao;
use App\Services\Calculadora\Dto\SimplesNacional\ItemInput as SnItem;
use App\Services\Calculadora\Dto\SimplesNacional\OperacaoInput as SnOperacao;
use App\Services\Calculadora\RegimeGeralClient;
use App\Services\Calculadora\SimplesNacionalClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'calculadoras.regime_geral.base_url' => 'http://calc-regular:8080/api',
        'calculadoras.simples_nacional.base_url' => 'http://calc-simples:8082/api',
        'calculadoras.regime_geral.retry_sleep_ms' => 1,
        'calculadoras.simples_nacional.retry_sleep_ms' => 1,
        'calculadoras.cache_store' => 'array',
        'calculadoras.log_channel' => 'null',
    ]);
    app()->forgetInstance(RegimeGeralClient::class);
    app()->forgetInstance(SimplesNacionalClient::class);
});

it('posts the regime geral operation to the observability endpoint and parses totals and item errors', function () {
    Http::fake([
        'calc-regular:8080/api/calculadora/observabilidade/regime-geral' => Http::response(fixture('calculadoras/regime-geral-response.json')),
    ]);

    $result = app(RegimeGeralClient::class)->calcular(fixture('calculadoras/regime-geral-request.json'));

    expect($result->totalIbs())->toBe('0.64')
        ->and($result->totalCbs())->toBe('56.76')
        ->and($result->totalIbsCbs())->toBe('57.40')
        ->and($result->totalBaseCalculo())->toBe('1465.00')
        ->and($result->totalIs())->toBe('15.00')
        ->and($result->item(3)->estado)->toBe(EstadoItem::CalculoSimulado)
        ->and($result->itemIbsCbs(3))->toBe('42.72')
        ->and($result->itemIbsCbs(4))->toBe('0.00')
        ->and($result->erros())->toHaveCount(1)
        ->and($result->erros()[0]['erros'][0]['code'])->toBe('REG-011')
        ->and($result->contagemPorEstado())->toBe(['CALCULO_SIMULADO' => 3, 'INCONSISTENCIA_ENTRADA' => 1]);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === 'http://calc-regular:8080/api/calculadora/observabilidade/regime-geral'
        && $r['municipio'] === 4314902
        && count($r['itens']) === 4);
});

it('serializes the regime geral DTO following docs/exemplos.md', function () {
    $op = new RgOperacao(
        id: 'abc',
        dhFatoGerador: new DateTimeImmutable('2027-03-10T10:00:00-03:00'),
        municipio: 4314902,
        uf: 'RS',
        itens: [new RgItem(1, '200', '200028', '1200.00', nbs: '122011200',
            aliquotasNominais: new AliquotasNominais('8.8', '0.05', '0.05'))],
    );

    expect($op->toArray())->toBe([
        'id' => 'abc',
        'versao' => '1.0.0',
        'dhFatoGerador' => '2027-03-10T10:00:00-03:00',
        'municipio' => 4314902,
        'uf' => 'RS',
        'itens' => [[
            'numero' => 1,
            'nbs' => '122011200',
            'cst' => '200',
            'cClassTrib' => '200028',
            'baseCalculo' => 1200.0,
            'aliquotasNominais' => ['cbs' => 8.8, 'ibsEstadual' => 0.05, 'ibsMunicipal' => 0.05],
        ]],
    ]);
});

it('serializes the simples DTO with string decimals and CRT in upper case', function () {
    $op = new SnOperacao('id1', new DateTimeImmutable('2027-03-15'), [
        new SnItem(1, '01', '000001', '950', '1.25', '2.75'),
    ]);

    expect($op->toArray())->toBe([
        'id' => 'id1',
        'versao' => '2.0.0',
        'data' => '2027-03-15',
        'tpDoc' => '55',
        'CRT' => '1',
        'itens' => [[
            'numero' => 1,
            'cAtivSN' => '01',
            'cClassTrib' => '000001',
            'baseCalculo' => '950.00',
            'valorImpostoSeletivo' => '0.00',
            'pIBSSN' => '1.2500',
            'pCBSSN' => '2.7500',
        ]],
    ]);
});

it('parses the simples observability response including NAO_CALCULADO and errors', function () {
    Http::fake([
        'calc-simples:8082/api/calculadora/observabilidade/simples-nacional' => Http::response(fixture('calculadoras/simples-nacional-response.json')),
    ]);

    $result = app(SimplesNacionalClient::class)->calcular(fixture('calculadoras/simples-nacional-request.json'));

    expect($result->totalIbsCbs())->toBe('38.01')
        ->and($result->totalReceitaBruta())->toBe('1050.00')
        ->and($result->itemIbsCbs(1))->toBe('38.01')
        ->and($result->itemIbsCbs(2))->toBe('0.00')
        ->and($result->item(2)->estado)->toBe(EstadoItem::NaoCalculado)
        ->and($result->item(2)->estado->isError())->toBeFalse()
        ->and($result->erros())->toHaveCount(1)
        ->and($result->erros()[0]['erros'][0]['code'])->toBe('VSN-001');
});

it('reads the version from the different endpoint of each calculator', function () {
    Http::fake([
        'calc-regular:8080/api/calculadora/dados-abertos/versao' => Http::response(['versaoApp' => '1.6.1', 'versaoDb' => 'v0059', 'ambiente' => 'offline']),
        'calc-simples:8082/api/versao' => Http::response(['versaoApp' => '0.0.6', 'versaoDb' => 'v0059', 'ambiente' => 'offline']),
    ]);

    expect(app(RegimeGeralClient::class)->versao()->versaoApp)->toBe('1.6.1')
        ->and(app(SimplesNacionalClient::class)->versao()->versaoApp)->toBe('0.0.6');
});

it('retries on server errors and then succeeds', function () {
    Http::fake([
        'calc-simples:8082/api/versao' => Http::sequence()
            ->push('erro', 503)
            ->push(['versaoApp' => '0.0.6', 'versaoDb' => 'v0059']),
    ]);

    expect(app(SimplesNacionalClient::class)->versao()->versaoDb)->toBe('v0059');
    Http::assertSentCount(2);
});

it('throws CalculadoraException with status and body on 4xx without retrying', function () {
    Http::fake([
        'calc-regular:8080/api/calculadora/observabilidade/regime-geral' => Http::response(['title' => 'Bad Request'], 400),
    ]);

    try {
        app(RegimeGeralClient::class)->calcular(['id' => 'x']);
        $this->fail('exception expected');
    } catch (CalculadoraException $e) {
        expect($e->status)->toBe(400)
            ->and($e->calculadora)->toBe('regime_geral')
            ->and($e->body)->toBe(['title' => 'Bad Request']);
    }
    Http::assertSentCount(1);
});

it('throws CalculadoraException when the calculator is unreachable', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('Connection refused'));

    app(SimplesNacionalClient::class)->versao();
})->throws(CalculadoraException::class, 'indisponível');

it('caches open data and returns null on 404', function () {
    Http::fake([
        'calc-regular:8080/api/calculadora/dados-abertos/ncm*' => Http::response(['ncm' => '22030000', 'descricao' => 'Cervejas de malte']),
        'calc-regular:8080/api/calculadora/dados-abertos/aliquota-uniao*' => Http::response(['message' => 'not found'], 404),
    ]);
    $client = app(RegimeGeralClient::class);

    expect($client->ncm('22030000', '2027-01-01')['descricao'])->toBe('Cervejas de malte')
        ->and($client->ncm('22030000', '2027-01-01')['descricao'])->toBe('Cervejas de malte')
        ->and($client->aliquotaUniao('2027-01-01'))->toBeNull()
        ->and($client->aliquotaUniao('2027-01-01'))->toBeNull();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'ncm=22030000') && str_contains($r->url(), 'data=2027-01-01'));
});

it('caches the Simples activities list', function () {
    Http::fake([
        'calc-simples:8082/api/calculadora/simples-nacional/atividades*' => Http::response([['codigo' => '01', 'descricao' => 'Comércio']]),
    ]);
    $client = app(SimplesNacionalClient::class);

    $client->atividades('2027-01-01');
    expect($client->atividades('2027-01-01'))->toHaveCount(1);
    Http::assertSentCount(1);
});
