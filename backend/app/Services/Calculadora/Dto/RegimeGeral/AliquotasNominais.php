<?php

namespace App\Services\Calculadora\Dto\RegimeGeral;

/** aliquotasNominais{cbs, ibsEstadual, ibsMunicipal} in percentage points (docs/exemplos.md). */
final readonly class AliquotasNominais
{
    public function __construct(
        public string $cbs,
        public string $ibsEstadual,
        public string $ibsMunicipal,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self((string) $data['cbs'], (string) $data['ibs_estadual'], (string) $data['ibs_municipal']);
    }

    public function toArray(): array
    {
        return [
            'cbs' => (float) $this->cbs,
            'ibsEstadual' => (float) $this->ibsEstadual,
            'ibsMunicipal' => (float) $this->ibsMunicipal,
        ];
    }
}
