<?php

namespace App\Services\Calculadora\Dto\SimplesNacional;

/**
 * Item of the Simples operation (api/simplesnacional/model/input/ItemSimplesInput.java).
 *   cAtivSN: official Simples activity code (2 chars). Missing/invalid -> item NAO_CALCULADO, no error.
 *   cClassTrib: required, resolves tpRBSN.
 *   baseCalculo: vProd + vFrete + vSeg + vOutro - vDesc, WITHOUT Imposto Seletivo (decimal string).
 *   pIBSSN / pCBSSN: effective Simples rates in percentage points, supplied by the caller (docs/premissas.md P-01).
 */
final readonly class ItemInput
{
    public function __construct(
        public int $numero,
        public ?string $cAtivSN,
        public string $cClassTrib,
        public string $baseCalculo,
        public string $pIBSSN,
        public string $pCBSSN,
        public string $valorImpostoSeletivo = '0.00',
    ) {}

    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'cAtivSN' => $this->cAtivSN,
            'cClassTrib' => $this->cClassTrib,
            'baseCalculo' => self::dec($this->baseCalculo, 2),
            'valorImpostoSeletivo' => self::dec($this->valorImpostoSeletivo, 2),
            'pIBSSN' => self::dec($this->pIBSSN, 4),
            'pCBSSN' => self::dec($this->pCBSSN, 4),
        ];
    }

    private static function dec(string $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
