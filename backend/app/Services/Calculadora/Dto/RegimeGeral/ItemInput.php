<?php

namespace App\Services\Calculadora\Dto\RegimeGeral;

/**
 * Item of the regime regular operation (api/model/input/ItemOperacaoInput.java).
 * Required: numero, cst, cClassTrib; ncm or nbs; baseCalculo when impostoSeletivo/gComponentesBC are absent.
 * aliquotasNominais is mandatory from 2027-01-01 and forbidden before (docs/exemplos.md).
 * Monetary values are sent as JSON numbers, as in docs/exemplos.md.
 */
final readonly class ItemInput
{
    public function __construct(
        public int $numero,
        public string $cst,
        public string $cClassTrib,
        public string $baseCalculo,
        public ?string $ncm = null,
        public ?string $nbs = null,
        public ?string $quantidade = null,
        public ?string $unidade = null,
        public ?AliquotasNominais $aliquotasNominais = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'numero' => $this->numero,
            'ncm' => $this->ncm,
            'nbs' => $this->nbs,
            'cst' => $this->cst,
            'cClassTrib' => $this->cClassTrib,
            'baseCalculo' => (float) $this->baseCalculo,
            'quantidade' => $this->quantidade !== null ? (float) $this->quantidade : null,
            'unidade' => $this->unidade,
            'aliquotasNominais' => $this->aliquotasNominais?->toArray(),
        ], fn ($v) => $v !== null);
    }
}
