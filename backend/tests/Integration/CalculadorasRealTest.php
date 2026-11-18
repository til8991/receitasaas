<?php

/*
| Optional integration tests against the REAL calculators (docker compose up calc-regular calc-simples).
| Enable with CALC_INTEGRATION_TESTS=true and point CALC_REGULAR_URL / CALC_SIMPLES_URL to them, e.g.
| from the php container (internal network) or through docker-compose.dev.example.yml on localhost.
|   CALC_INTEGRATION_TESTS=true CALC_REGULAR_URL=http://localhost:18080/api \
|   CALC_SIMPLES_URL=http://localhost:18082/api php artisan test --testsuite=Integration
*/

use App\Services\Calculadora\Dto\EstadoItem;
use App\Services\Calculadora\RegimeGeralClient;
use App\Services\Calculadora\SimplesNacionalClient;

beforeEach(function () {
    if (! filter_var(env('CALC_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Defina CALC_INTEGRATION_TESTS=true para rodar contra as calculadoras reais.');
    }
    config(['calculadoras.cache_store' => 'array', 'calculadoras.log_channel' => 'stderr']);
});

it('calculates the official docs/exemplos.md operation on the real regime regular calculator', function () {
    $result = app(RegimeGeralClient::class)->calcular(fixture('calculadoras/regime-geral-request.json'));

    expect($result->item(3)->estado)->toBe(EstadoItem::CalculoSimulado)
        ->and($result->item(4)->estado)->toBe(EstadoItem::InconsistenciaEntrada)
        ->and($result->item(4)->erros[0]['code'])->toBe('REG-011')
        ->and(data_get($result->item(3)->tribCalc, 'IBSCBS.gIBSCBS.gCBS.vCBS'))->toBe('42.24');
});

it('calculates on the real Simples calculator', function () {
    $result = app(SimplesNacionalClient::class)->calcular(fixture('calculadoras/simples-nacional-request.json'));

    expect($result->item(1)->estado)->toBe(EstadoItem::Calculado)
        ->and((float) $result->totalTributos())->toBeGreaterThan(0);
});

it('reads both versions and they share the same database version', function () {
    $rg = app(RegimeGeralClient::class)->versao();
    $sn = app(SimplesNacionalClient::class)->versao();

    expect($rg->versaoDb)->not->toBeNull()->toBe($sn->versaoDb);
});
