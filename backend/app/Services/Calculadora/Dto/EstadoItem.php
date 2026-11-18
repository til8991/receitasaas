<?php

namespace App\Services\Calculadora\Dto;

/**
 * estadoItem of the observability endpoints.
 * Regime regular: CALCULADO, CALCULO_SIMULADO, INCONSISTENCIA_ENTRADA, NAO_IMPLEMENTADO, FALHA_TECNICA.
 * Simples Nacional adds NAO_CALCULADO (EstadoItemSimplesEnum).
 */
enum EstadoItem: string
{
    case Calculado = 'CALCULADO';
    case CalculoSimulado = 'CALCULO_SIMULADO';
    case NaoCalculado = 'NAO_CALCULADO';
    case InconsistenciaEntrada = 'INCONSISTENCIA_ENTRADA';
    case NaoImplementado = 'NAO_IMPLEMENTADO';
    case FalhaTecnica = 'FALHA_TECNICA';
    case Desconhecido = 'DESCONHECIDO';

    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Desconhecido;
    }

    /** Item has tax values that are summed in the totals. */
    public function hasValues(): bool
    {
        return in_array($this, [self::Calculado, self::CalculoSimulado], true);
    }

    public function isError(): bool
    {
        return in_array($this, [self::InconsistenciaEntrada, self::NaoImplementado, self::FalhaTecnica, self::Desconhecido], true);
    }
}
