<?php

namespace App\Services\Comparador;

use App\Models\Client;
use App\Models\InvoiceItem;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Builds the operation lines from imported invoices or from manually informed values.
 *
 * PREMISSA P-08: with invoices, the 12 months ending at the most recent imported sale are used as
 * the representative revenue mix of the reference year (values are not inflated/projected).
 * PREMISSA P-09: items without IBSCBS group (CST/cClassTrib) use the client's default classification.
 */
class OperationLinesBuilder
{
    public const MAX_LINES = 400;

    public function fromInvoices(Client $client): OperationLines
    {
        $last = InvoiceItem::where('client_id', $client->id)
            ->where('direction', InvoiceItem::DIRECTION_SALE)->max('issued_at');
        if (! $last) {
            throw new InvalidArgumentException('O cliente não tem notas de saída importadas. Importe XMLs ou use valores manuais.');
        }

        $end = CarbonImmutable::parse($last)->endOfDay();
        $start = $end->subYear()->addSecond();
        $warnings = [];

        $sales = $this->aggregate($client, InvoiceItem::DIRECTION_SALE, $start, $end, $warnings);
        $purchases = $this->aggregate($client, InvoiceItem::DIRECTION_PURCHASE, $start, $end, $warnings);

        if ($purchases === []) {
            $warnings[] = 'Nenhuma nota de entrada no período: créditos de compras no regime regular considerados zero.';
        }

        return new OperationLines($sales, $purchases, 'invoices', $start->toDateString(), $end->toDateString(), array_values(array_unique($warnings)));
    }

    /**
     * @param array{revenue?: float|string|null, b2b_sales_pct?: float|string|null, purchases?: float|string|null} $manual
     */
    public function fromManual(Client $client, array $manual): OperationLines
    {
        $revenue = (string) ($manual['revenue'] ?? $client->revenue_12m);
        $b2bPct = (string) ($manual['b2b_sales_pct'] ?? $client->b2b_sales_pct);
        $purchases = (string) ($manual['purchases'] ?? $client->purchases_12m);

        if (bccomp($revenue, '0', 2) <= 0) {
            throw new InvalidArgumentException('Informe o faturamento (receita bruta) para a simulação manual.');
        }
        if (! $client->default_ncm && ! $client->default_nbs) {
            throw new InvalidArgumentException('Cadastre a NCM ou a NBS padrão do cliente para simular sem notas.');
        }

        $b2b = bcdiv(bcmul($revenue, $b2bPct, 4), '100', 2);
        $b2c = bcsub($revenue, $b2b, 2);
        $classification = [
            'ncm' => $client->default_ncm,
            'nbs' => $client->default_ncm ? null : $client->default_nbs,
            'cst' => $client->default_cst,
            'cClassTrib' => $client->default_c_class_trib,
        ];

        $sales = [];
        if (bccomp($b2b, '0', 2) > 0) {
            $sales[] = ['descricao' => 'Vendas a empresas (B2B)', ...$classification, 'base' => $b2b, 'b2b' => true, 'documentos' => 0];
        }
        if (bccomp($b2c, '0', 2) > 0) {
            $sales[] = ['descricao' => 'Vendas a consumidores (B2C)', ...$classification, 'base' => $b2c, 'b2b' => false, 'documentos' => 0];
        }

        $purchaseLines = bccomp($purchases, '0', 2) > 0
            ? [['descricao' => 'Compras e insumos', ...$classification, 'base' => number_format((float) $purchases, 2, '.', ''), 'documentos' => 0]]
            : [];

        return new OperationLines($sales, $purchaseLines, 'manual', null, null, [
            'Simulação com valores informados manualmente e a classificação fiscal padrão do cliente.',
        ]);
    }

    private function aggregate(Client $client, string $direction, CarbonImmutable $start, CarbonImmutable $end, array &$warnings): array
    {
        $rows = InvoiceItem::query()
            ->where('client_id', $client->id)
            ->where('direction', $direction)
            ->whereBetween('issued_at', [$start, $end])
            ->get(['ncm', 'nbs', 'cst', 'c_class_trib', 'recipient_type', 'base_amount', 'document_key', 'description', 'cfop']);

        $groups = [];
        foreach ($rows as $row) {
            // Returns of purchases issued by the client (CFOP x2xx, e.g. 5202/6202) are not revenue.
            if ($direction === InvoiceItem::DIRECTION_SALE && $row->cfop && in_array($row->cfop[1] ?? '', ['2'], true)) {
                continue;
            }

            $ncm = $row->ncm;
            $nbs = $ncm ? null : ($row->nbs ?: $client->default_nbs);
            if (! $ncm && ! $nbs) {
                $ncm = $client->default_ncm;
                $warnings[] = 'Itens sem NCM/NBS usaram a classificação padrão do cliente.';
            }
            if (! $row->cst || ! $row->c_class_trib) {
                $warnings[] = 'Itens sem grupo IBSCBS no XML usaram CST/cClassTrib padrão do cliente (premissa P-09).';
            }
            $cst = $row->cst ?: $client->default_cst;
            $cClassTrib = $row->c_class_trib ?: $client->default_c_class_trib;
            $b2b = $row->recipient_type === 'pj';

            $key = implode('|', [$ncm, $nbs, $cst, $cClassTrib, $direction === InvoiceItem::DIRECTION_SALE ? (int) $b2b : 0]);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'descricao' => $this->label($ncm, $nbs, $row->description),
                    'ncm' => $ncm,
                    'nbs' => $nbs,
                    'cst' => $cst,
                    'cClassTrib' => $cClassTrib,
                    'base' => '0.00',
                    'documentos' => [],
                ];
                if ($direction === InvoiceItem::DIRECTION_SALE) {
                    $groups[$key]['b2b'] = $b2b;
                }
            }
            $groups[$key]['base'] = bcadd($groups[$key]['base'], (string) $row->base_amount, 2);
            $groups[$key]['documentos'][$row->document_key] = true;
        }

        $lines = array_map(function ($g) {
            $g['documentos'] = count($g['documentos']);

            return $g;
        }, array_values(array_filter($groups, fn ($g) => bccomp($g['base'], '0', 2) > 0)));

        usort($lines, fn ($a, $b) => bccomp($b['base'], $a['base'], 2));

        if (count($lines) > self::MAX_LINES) {
            $warnings[] = 'Mais de '.self::MAX_LINES.' combinações de NCM/NBS: as menores foram agrupadas na classificação padrão.';
            $lines = $this->collapseTail($lines, $client, $direction);
        }

        return $lines;
    }

    private function collapseTail(array $lines, Client $client, string $direction): array
    {
        $head = array_slice($lines, 0, self::MAX_LINES - 2);
        $tail = array_slice($lines, self::MAX_LINES - 2);
        $sum = fn (array $items) => array_reduce($items, fn ($c, $l) => bcadd($c, $l['base'], 2), '0.00');
        $base = [
            'ncm' => $client->default_ncm, 'nbs' => $client->default_ncm ? null : $client->default_nbs,
            'cst' => $client->default_cst, 'cClassTrib' => $client->default_c_class_trib,
        ];

        if ($direction !== InvoiceItem::DIRECTION_SALE) {
            $head[] = ['descricao' => 'Demais itens (agrupados)', ...$base, 'base' => $sum($tail), 'documentos' => array_sum(array_column($tail, 'documentos'))];

            return $head;
        }

        foreach ([true, false] as $b2b) {
            $part = array_filter($tail, fn ($l) => $l['b2b'] === $b2b);
            if ($part !== []) {
                $head[] = ['descricao' => 'Demais itens (agrupados) '.($b2b ? 'B2B' : 'B2C'), ...$base, 'base' => $sum($part), 'b2b' => $b2b,
                    'documentos' => array_sum(array_column($part, 'documentos'))];
            }
        }

        return $head;
    }

    private function label(?string $ncm, ?string $nbs, ?string $description): string
    {
        $code = $ncm ? "NCM {$ncm}" : ($nbs ? "NBS {$nbs}" : 'Sem classificação');

        return $description ? mb_substr("{$code} — {$description}", 0, 120) : $code;
    }
}
