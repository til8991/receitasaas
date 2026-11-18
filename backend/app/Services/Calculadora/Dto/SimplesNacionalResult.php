<?php

namespace App\Services\Calculadora\Dto;

/**
 * Response of POST /calculadora/observabilidade/simples-nacional
 * (ObservabilidadeSimplesDomain): oper, objetos[].tribCalc{vRBSNitem, tpRBSN, vBC, pIBSSN, vIBSSN,
 * vIBSPendSusp, pCBSSN, vCBSSN, vCBSPendSusp, IS{valor}, totalItem}, total{vRBSNtot, vBC, vIBSSNtot,
 * vIBSSNtotPendSusp, vCBSSNtot, vCBSSNtotPendSusp, totalIs, totalTributos}.
 * Absent fields mean "not applicable" (NON_NULL serialization), treated as 0.
 */
final class SimplesNacionalResult extends ObservabilidadeResult
{
    public static function fromArray(array $raw): self
    {
        return new self(self::parseItens($raw), $raw);
    }

    public function totalReceitaBruta(): string
    {
        return self::dec(data_get($this->raw, 'total.vRBSNtot'));
    }

    public function totalBaseCalculo(): string
    {
        return self::dec(data_get($this->raw, 'total.vBC'));
    }

    public function totalIbs(): string
    {
        return self::dec(data_get($this->raw, 'total.vIBSSNtot'));
    }

    public function totalCbs(): string
    {
        return self::dec(data_get($this->raw, 'total.vCBSSNtot'));
    }

    public function totalIbsPendenteSuspenso(): string
    {
        return self::dec(data_get($this->raw, 'total.vIBSSNtotPendSusp'));
    }

    public function totalCbsPendenteSuspenso(): string
    {
        return self::dec(data_get($this->raw, 'total.vCBSSNtotPendSusp'));
    }

    public function totalIbsCbs(): string
    {
        return bcadd($this->totalIbs(), $this->totalCbs(), 2);
    }

    public function totalTributos(): string
    {
        return self::dec(data_get($this->raw, 'total.totalTributos'));
    }

    /** IBS + CBS due for a single item (0 when not calculated). */
    public function itemIbsCbs(int $nObj): string
    {
        $item = $this->item($nObj);
        if (! $item || $item->estado !== EstadoItem::Calculado) {
            return '0.00';
        }

        return bcadd(self::dec($item->tribCalc['vIBSSN'] ?? null), self::dec($item->tribCalc['vCBSSN'] ?? null), 2);
    }

    public function totals(): array
    {
        return [
            'vRBSN' => $this->totalReceitaBruta(),
            'vBC' => $this->totalBaseCalculo(),
            'vIBS' => $this->totalIbs(),
            'vCBS' => $this->totalCbs(),
            'vIBSPendSusp' => $this->totalIbsPendenteSuspenso(),
            'vCBSPendSusp' => $this->totalCbsPendenteSuspenso(),
            'vIBSCBS' => $this->totalIbsCbs(),
            'totalTributos' => $this->totalTributos(),
            'estados' => $this->contagemPorEstado(),
        ];
    }
}
