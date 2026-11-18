<?php

namespace App\Services\Calculadora\Dto;

/**
 * Response of POST /calculadora/observabilidade/regime-geral (docs/exemplos.md):
 * objetos[].tribCalc.IBSCBS.gIBSCBS{vBC, gIBSUF{vIBSUF}, gIBSMun{vIBSMun}, vIBS, gCBS{vCBS}}
 * total.tribCalc{ISTot{vIS}, IBSCBSTot{vBCIBSCBS, gIBS{vIBS}, gCBS{vCBS}}}
 * Totals only include calculated items.
 */
final class RegimeGeralResult extends ObservabilidadeResult
{
    public static function fromArray(array $raw): self
    {
        return new self(self::parseItens($raw), $raw);
    }

    public function totalBaseCalculo(): string
    {
        return self::dec(data_get($this->raw, 'total.tribCalc.IBSCBSTot.vBCIBSCBS'));
    }

    public function totalIbs(): string
    {
        return self::dec(data_get($this->raw, 'total.tribCalc.IBSCBSTot.gIBS.vIBS'));
    }

    public function totalCbs(): string
    {
        return self::dec(data_get($this->raw, 'total.tribCalc.IBSCBSTot.gCBS.vCBS'));
    }

    public function totalIs(): string
    {
        return self::dec(data_get($this->raw, 'total.tribCalc.ISTot.vIS'));
    }

    public function totalIbsCbs(): string
    {
        return bcadd($this->totalIbs(), $this->totalCbs(), 2);
    }

    /** IBS + CBS of a single item (0 when not calculated). */
    public function itemIbsCbs(int $nObj): string
    {
        $item = $this->item($nObj);
        if (! $item || ! $item->estado->hasValues()) {
            return '0.00';
        }
        $g = data_get($item->tribCalc, 'IBSCBS.gIBSCBS', []);

        return bcadd(self::dec(data_get($g, 'vIBS')), self::dec(data_get($g, 'gCBS.vCBS')), 2);
    }

    public function totals(): array
    {
        return [
            'vBC' => $this->totalBaseCalculo(),
            'vIBS' => $this->totalIbs(),
            'vCBS' => $this->totalCbs(),
            'vIS' => $this->totalIs(),
            'vIBSCBS' => $this->totalIbsCbs(),
            'estados' => $this->contagemPorEstado(),
        ];
    }
}
