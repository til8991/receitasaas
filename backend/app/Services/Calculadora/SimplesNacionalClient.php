<?php

namespace App\Services\Calculadora;

use App\Services\Calculadora\Dto\SimplesNacional\OperacaoInput;
use App\Services\Calculadora\Dto\SimplesNacionalResult;

/** Client for ReceitaFederal/calculadora-rtc-simples-nacional (base /api). */
class SimplesNacionalClient extends CalculadoraClient
{
    public function name(): string
    {
        return 'simples_nacional';
    }

    protected function versaoPath(): string
    {
        return 'versao';
    }

    /** POST /calculadora/observabilidade/simples-nacional */
    public function calcular(OperacaoInput|array $operacao): SimplesNacionalResult
    {
        $payload = $operacao instanceof OperacaoInput ? $operacao->toArray() : $operacao;

        return SimplesNacionalResult::fromArray($this->post('calculadora/observabilidade/simples-nacional', $payload));
    }

    /** GET /calculadora/observabilidade/simples-nacional/erros?codigo=VSN-001 */
    public function descricaoErro(string $codigo): ?array
    {
        return $this->cachedGet('calculadora/observabilidade/simples-nacional/erros', ['codigo' => $codigo], nullOn404: true);
    }

    /**
     * GET /calculadora/simples-nacional/atividades?data=AAAA-MM-DD (cached).
     *
     * @return list<array>
     */
    public function atividades(string $data, array $filtros = []): array
    {
        return $this->cachedGet('calculadora/simples-nacional/atividades', ['data' => $data] + $filtros) ?? [];
    }

    /** GET /calculadora/simples-nacional/atividades/{codigo}?data= (cached) */
    public function atividade(string $codigo, string $data): ?array
    {
        return $this->cachedGet("calculadora/simples-nacional/atividades/{$codigo}", ['data' => $data], nullOn404: true);
    }
}
