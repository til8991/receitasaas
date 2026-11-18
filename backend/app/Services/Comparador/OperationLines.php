<?php

namespace App\Services\Comparador;

/**
 * Normalized input for both scenarios. Each line becomes one item (nObj = index + 1)
 * in the calculator payloads, so responses can be joined back to the lines.
 */
final class OperationLines
{
    /**
     * @param list<array{descricao:string, ncm:?string, nbs:?string, cst:string, cClassTrib:string, base:string, b2b:bool, documentos:int}> $sales
     * @param list<array{descricao:string, ncm:?string, nbs:?string, cst:string, cClassTrib:string, base:string, documentos:int}> $purchases
     */
    public function __construct(
        public readonly array $sales,
        public readonly array $purchases,
        public readonly string $source,          // invoices|manual
        public readonly ?string $periodStart,
        public readonly ?string $periodEnd,
        public readonly array $warnings = [],
    ) {}

    public function salesTotal(): string
    {
        return array_reduce($this->sales, fn ($c, $l) => bcadd($c, $l['base'], 2), '0.00');
    }

    public function salesB2bTotal(): string
    {
        return array_reduce(array_filter($this->sales, fn ($l) => $l['b2b']), fn ($c, $l) => bcadd($c, $l['base'], 2), '0.00');
    }

    public function purchasesTotal(): string
    {
        return array_reduce($this->purchases, fn ($c, $l) => bcadd($c, $l['base'], 2), '0.00');
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'sales_total' => $this->salesTotal(),
            'sales_b2b_total' => $this->salesB2bTotal(),
            'purchases_total' => $this->purchasesTotal(),
            'sales' => $this->sales,
            'purchases' => $this->purchases,
            'warnings' => $this->warnings,
        ];
    }
}
