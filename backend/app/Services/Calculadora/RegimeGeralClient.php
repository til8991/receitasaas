<?php

namespace App\Services\Calculadora;

use App\Services\Calculadora\Dto\RegimeGeral\OperacaoInput;
use App\Services\Calculadora\Dto\RegimeGeralResult;

/**
 * Client for ReceitaFederal/calculadora-rtc-regime-regular (base /api).
 * Uses the observability endpoint so item errors do not abort the whole operation.
 */
class RegimeGeralClient extends CalculadoraClient
{
    public function name(): string
    {
        return 'regime_geral';
    }

    protected function versaoPath(): string
    {
        return 'calculadora/dados-abertos/versao';
    }

    /** POST /calculadora/observabilidade/regime-geral — returns the parsed result; ->raw keeps the full JSON. */
    public function calcular(OperacaoInput|array $operacao): RegimeGeralResult
    {
        $payload = $operacao instanceof OperacaoInput ? $operacao->toArray() : $operacao;

        return RegimeGeralResult::fromArray($this->post('calculadora/observabilidade/regime-geral', $payload));
    }

    /** GET /calculadora/observabilidade/erros?codigo=REG-011 */
    public function descricaoErro(string $codigo): ?array
    {
        return $this->cachedGet('calculadora/observabilidade/erros', ['codigo' => $codigo], nullOn404: true);
    }

    // ---- Open data (cached) -------------------------------------------------------------

    /** GET /calculadora/dados-abertos/ncm?ncm=&data= */
    public function ncm(string $ncm, string $data): ?array
    {
        return $this->cachedGet('calculadora/dados-abertos/ncm', ['ncm' => $ncm, 'data' => $data], nullOn404: true);
    }

    /** GET /calculadora/dados-abertos/nbs?nbs=&data= */
    public function nbs(string $nbs, string $data): ?array
    {
        return $this->cachedGet('calculadora/dados-abertos/nbs', ['nbs' => $nbs, 'data' => $data], nullOn404: true);
    }

    /** GET /calculadora/dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/{cClassTrib}?data= */
    public function classificacaoTributaria(string $cClassTrib, string $data): ?array
    {
        return $this->cachedGet(
            "calculadora/dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/{$cClassTrib}",
            ['data' => $data],
            nullOn404: true,
        );
    }

    /** GET /calculadora/dados-abertos/situacoes-tributarias/cbs-ibs?data= */
    public function situacoesTributarias(string $data): ?array
    {
        return $this->cachedGet('calculadora/dados-abertos/situacoes-tributarias/cbs-ibs', ['data' => $data], nullOn404: true);
    }

    /** GET /calculadora/dados-abertos/aliquota-uniao?data= (reference rates exist only for 2026; 404 from 2027) */
    public function aliquotaUniao(string $data): ?array
    {
        return $this->cachedGet('calculadora/dados-abertos/aliquota-uniao', ['data' => $data], nullOn404: true);
    }

    /** GET /calculadora/dados-abertos/aliquota-uf?codigoUf=&data= */
    public function aliquotaUf(int $codigoUf, string $data): ?array
    {
        return $this->cachedGet('calculadora/dados-abertos/aliquota-uf', ['codigoUf' => $codigoUf, 'data' => $data], nullOn404: true);
    }

    /** GET /calculadora/dados-abertos/aliquota-municipio?codigoMunicipio=&data= */
    public function aliquotaMunicipio(int $codigoMunicipio, string $data): ?array
    {
        return $this->cachedGet(
            'calculadora/dados-abertos/aliquota-municipio',
            ['codigoMunicipio' => $codigoMunicipio, 'data' => $data],
            nullOn404: true,
        );
    }
}
