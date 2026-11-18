<?php

namespace App\Services\Comparador;

use App\Models\Client;
use App\Models\Comparison;
use App\Models\Simulation;
use App\Services\Calculadora\CalculadoraException;
use App\Services\Calculadora\Dto\RegimeGeral\AliquotasNominais;
use App\Services\Calculadora\Dto\RegimeGeral\ItemInput as RgItem;
use App\Services\Calculadora\Dto\RegimeGeral\OperacaoInput as RgOperacao;
use App\Services\Calculadora\Dto\RegimeGeralResult;
use App\Services\Calculadora\Dto\SimplesNacional\ItemInput as SnItem;
use App\Services\Calculadora\Dto\SimplesNacional\OperacaoInput as SnOperacao;
use App\Services\Calculadora\Dto\SimplesNacionalResult;
use App\Services\Calculadora\RegimeGeralClient;
use App\Services\Calculadora\SimplesNacionalClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Compares, for one Simples Nacional client and reference year:
 *   A) simples_integral — IBS/CBS collected inside the Simples (official Simples calculator);
 *   B) ibs_cbs_regular  — IBS/CBS collected by the regular regime (official regime regular calculator),
 *                         which generates full credit to B2B customers and allows credits on purchases.
 * The calculators compute the taxes; the business rules on top (credit pass-through, supplier mix,
 * thresholds) are parametrized assumptions (docs/premissas.md).
 *
 * PREMISSA P-02: the other taxes of the DAS (IRPJ, CSLL, CPP, ...) are the same in both scenarios,
 * so the comparison is restricted to IBS/CBS.
 */
class ComparadorService
{
    public const MIN_YEAR = 2027;
    public const MAX_YEAR = 2040;

    public function __construct(
        private readonly RegimeGeralClient $regimeGeral,
        private readonly SimplesNacionalClient $simples,
        private readonly SimplesAliquotaService $aliquotas,
        private readonly OperationLinesBuilder $linesBuilder,
    ) {}

    public function run(Comparison $comparison): Comparison
    {
        $comparison->update(['status' => 'running', 'error_message' => null]);
        $client = Client::withoutGlobalScopes()->with('office')->findOrFail($comparison->client_id);

        try {
            $result = $this->compute($client, $comparison);
        } catch (InvalidArgumentException $e) {
            // Business validation messages written by us, safe to show.
            $comparison->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return $comparison;
        } catch (CalculadoraException $e) {
            report($e);
            $comparison->update([
                'status' => 'failed',
                'error_message' => 'Calculadora oficial indisponível no momento ('.$e->calculadora.'). Tente novamente em instantes.',
            ]);

            return $comparison;
        } catch (Throwable $e) {
            $comparison->update(['status' => 'failed', 'error_message' => 'Erro inesperado ao calcular a simulação.']);
            throw $e;
        }

        DB::transaction(function () use ($comparison, $client, $result) {
            foreach ($result['simulations'] as $scenario => $sim) {
                Simulation::updateOrCreate(
                    ['comparison_id' => $comparison->id, 'scenario' => $scenario],
                    [
                        'office_id' => $comparison->office_id,
                        'client_id' => $client->id,
                        'reference_year' => $comparison->reference_year,
                        'status' => 'completed',
                        'input' => $sim['input'],
                        'request_payload' => $sim['request_payload'],
                        'response_raw' => $sim['response_raw'],
                        'calculator_version' => $sim['calculator_version'],
                        'totals' => $sim['totals'],
                        'item_errors' => $sim['item_errors'],
                        'calculated_at' => now(),
                    ],
                );
            }

            $comparison->update([
                'status' => 'completed',
                'assumptions' => $result['assumptions'],
                'result' => $result['result'],
                'recommendation' => $result['result']['recomendacao'],
                'calculated_at' => now(),
                'report_path' => null,
                'report_generated_at' => null,
            ]);
        });

        return $comparison->fresh('simulations');
    }

    private function compute(Client $client, Comparison $comparison): array
    {
        $year = (int) $comparison->reference_year;
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw new InvalidArgumentException('Ano de referência deve estar entre '.self::MIN_YEAR.' e '.self::MAX_YEAR.'.');
        }

        $premissas = Premissas::forClient($client);
        $lines = $comparison->source === 'manual'
            ? $this->linesBuilder->fromManual($client, $comparison->input['manual'] ?? [])
            : $this->linesBuilder->fromInvoices($client);

        if ($lines->sales === []) {
            throw new InvalidArgumentException('Não há receitas no período para simular.');
        }

        $warnings = $lines->warnings;
        $activity = $client->activities[0] ?? null;
        if (! $activity) {
            $warnings[] = 'Cliente sem código de atividade do Simples (cAtivSN): a calculadora do Simples não calcula os itens.';
        } elseif (count($client->activities) > 1) {
            $warnings[] = "Cliente com mais de uma atividade: usada a atividade {$activity} para todas as receitas.";
        }

        $rbt12 = (float) $client->revenue_12m > 0 ? (float) $client->revenue_12m : (float) $lines->salesTotal();
        $rates = $this->aliquotas->calcular($client->simples_annex, $rbt12, $year, $premissas);
        $nominal = $premissas->nominalRates($year)
            ?? throw new InvalidArgumentException("Não há alíquotas nominais configuradas para {$year} (premissa P-06).");

        $date = new DateTimeImmutable("{$year}-07-01T12:00:00-03:00");
        $versions = [
            'simples_nacional' => $this->simples->versao()->toArray(),
            'regime_geral' => $this->regimeGeral->versao()->toArray(),
        ];

        $a = $this->scenarioSimples($client, $lines, $rates, $activity, $date, $versions['simples_nacional']);
        $b = $this->scenarioRegular($client, $lines, $nominal, $date, $premissas, $versions['regime_geral']);

        return [
            'assumptions' => $this->assumptionsUsed($premissas, $client, $year, $rates, $nominal, $lines),
            'result' => $this->compare($lines, $a, $b, $premissas, $rates, $warnings, $year),
            'simulations' => [
                Simulation::SCENARIO_SIMPLES => $a['simulation'],
                Simulation::SCENARIO_REGULAR => $b['simulation'],
            ],
        ];
    }

    // ---------------------------------------------------------------- Scenario A

    private function scenarioSimples(Client $client, OperationLines $lines, array $rates, ?string $activity, DateTimeImmutable $date, array $version): array
    {
        $itens = [];
        foreach ($lines->sales as $i => $line) {
            $itens[] = new SnItem($i + 1, $activity, $line['cClassTrib'], $line['base'], (string) $rates['pIBSSN'], (string) $rates['pCBSSN']);
        }

        $operation = new SnOperacao(
            id: Str::lower(Str::random(32)),
            data: $date,
            itens: $itens,
            crt: ($rates['acima_sublimite'] ?? false) ? '2' : '1',
        );
        $payload = $operation->toArray();
        $result = $this->simples->calcular($payload);

        $creditToCustomers = '0.00';
        $items = [];
        foreach ($lines->sales as $i => $line) {
            $tax = $result->itemIbsCbs($i + 1);
            if ($line['b2b']) {
                $creditToCustomers = bcadd($creditToCustomers, $tax, 2);
            }
            $item = $result->item($i + 1);
            $items[] = [
                'nObj' => $i + 1,
                'descricao' => $line['descricao'],
                'base' => $line['base'],
                'b2b' => $line['b2b'],
                'estado' => $item?->estado->value,
                'ibs_cbs' => $tax,
            ];
        }

        $totals = $result->totals() + [
            'credito_clientes_b2b' => $creditToCustomers,
            'custo_liquido' => $result->totalIbsCbs(),
            'items' => $items,
        ];

        return [
            'result' => $result,
            'tax' => $result->totalIbsCbs(),
            'credit_customers' => $creditToCustomers,
            'simulation' => [
                'input' => ['lines' => $lines->sales, 'aliquotas_simples' => $rates],
                'request_payload' => ['simples_nacional' => $payload],
                'response_raw' => ['simples_nacional' => $result->raw],
                'calculator_version' => ['simples_nacional' => $version],
                'totals' => $totals,
                'item_errors' => $result->erros(),
            ],
        ];
    }

    // ---------------------------------------------------------------- Scenario B

    private function scenarioRegular(Client $client, OperationLines $lines, array $nominal, DateTimeImmutable $date, Premissas $premissas, array $version): array
    {
        $aliquotas = new AliquotasNominais((string) $nominal['cbs'], (string) $nominal['ibs_estadual'], (string) $nominal['ibs_municipal']);
        $build = fn (array $source) => new RgOperacao(
            id: Str::lower(Str::random(32)),
            dhFatoGerador: $date,
            municipio: (int) $client->municipality_ibge,
            uf: $client->uf,
            itens: array_map(fn ($line, $i) => new RgItem(
                numero: $i + 1,
                cst: $line['cst'],
                cClassTrib: $line['cClassTrib'],
                baseCalculo: $line['base'],
                ncm: $line['ncm'],
                nbs: $line['ncm'] ? null : $line['nbs'],
                aliquotasNominais: $aliquotas,
            ), $source, array_keys($source)),
        );

        $salesPayload = $build($lines->sales)->toArray();
        $sales = $this->regimeGeral->calcular($salesPayload);

        $purchasesPayload = null;
        $purchases = null;
        if ($lines->purchases !== []) {
            $purchasesPayload = $build($lines->purchases)->toArray();
            $purchases = $this->regimeGeral->calcular($purchasesPayload);
        }

        $debit = $sales->totalIbsCbs();
        $creditGross = $purchases?->totalIbsCbs() ?? '0.00';
        $creditUsed = bcdiv(bcmul($creditGross, (string) $premissas->regularSuppliersPct(), 4), '100', 2);
        $net = bcsub($debit, $creditUsed, 2);

        $creditToCustomers = '0.00';
        $items = [];
        foreach ($lines->sales as $i => $line) {
            $tax = $sales->itemIbsCbs($i + 1);
            if ($line['b2b']) {
                $creditToCustomers = bcadd($creditToCustomers, $tax, 2);
            }
            $items[] = [
                'nObj' => $i + 1,
                'descricao' => $line['descricao'],
                'base' => $line['base'],
                'b2b' => $line['b2b'],
                'estado' => $sales->item($i + 1)?->estado->value,
                'ibs_cbs' => $tax,
            ];
        }

        $errors = $sales->erros();
        foreach ($purchases?->erros() ?? [] as $error) {
            $errors[] = $error + ['origem' => 'compras'];
        }

        return [
            'tax' => $net,
            'debit' => $debit,
            'credit_gross' => $creditGross,
            'credit_used' => $creditUsed,
            'credit_customers' => $creditToCustomers,
            'sales' => $sales,
            'simulation' => [
                'input' => ['lines' => $lines->sales, 'purchases' => $lines->purchases, 'aliquotas_nominais' => $nominal],
                'request_payload' => array_filter([
                    'regime_geral_vendas' => $salesPayload,
                    'regime_geral_compras' => $purchasesPayload,
                ]),
                'response_raw' => array_filter([
                    'regime_geral_vendas' => $sales->raw,
                    'regime_geral_compras' => $purchases?->raw,
                ]),
                'calculator_version' => ['regime_geral' => $version],
                'totals' => [
                    'vendas' => $sales->totals(),
                    'compras' => $purchases?->totals(),
                    'debito_ibs_cbs' => $debit,
                    'credito_compras_bruto' => $creditGross,
                    'credito_compras_aproveitado' => $creditUsed,
                    'custo_liquido' => $net,
                    'credito_clientes_b2b' => $creditToCustomers,
                    'items' => $items,
                ],
                'item_errors' => $errors,
            ],
        ];
    }

    // ---------------------------------------------------------------- Comparison

    private function compare(OperationLines $lines, array $a, array $b, Premissas $premissas, array $rates, array $warnings, int $year): array
    {
        $revenue = $lines->salesTotal();
        $extraCredit = bcsub($b['credit_customers'], $a['credit_customers'], 2);
        $passThrough = (string) $premissas->creditPassThroughPct();
        $benefit = bccomp($extraCredit, '0', 2) > 0 ? bcdiv(bcmul($extraCredit, $passThrough, 4), '100', 2) : '0.00';
        $effectiveRegular = bcsub($b['tax'], $benefit, 2);
        $diff = bcsub($effectiveRegular, $a['tax'], 2);
        $diffPct = bccomp($revenue, '0', 2) > 0 ? round((float) $diff / (float) $revenue * 100, 4) : 0.0;
        $threshold = $premissas->recommendationThresholdPct();

        $recommendation = match (true) {
            abs($diffPct) < $threshold => Comparison::RECOMMEND_NEUTRAL,
            $diffPct < 0 => Comparison::RECOMMEND_MIGRATE,
            default => Comparison::RECOMMEND_KEEP,
        };

        $fmt = fn (string $v) => 'R$ '.number_format((float) $v, 2, ',', '.');
        $pct = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.').'%';

        $justification = [
            "Receita bruta considerada: {$fmt($revenue)}, sendo {$fmt($lines->salesB2bTotal())} em vendas a empresas (B2B).",
            "Simples integral: IBS/CBS recolhidos no DAS de {$fmt($a['tax'])} (alíquotas efetivas IBS {$pct($rates['pIBSSN'], 4)} e CBS {$pct($rates['pCBSSN'], 4)}). Crédito transferido aos clientes B2B: {$fmt($a['credit_customers'])}.",
            "IBS/CBS pelo regime regular: débito de {$fmt($b['debit'])} menos crédito de compras aproveitado de {$fmt($b['credit_used'])} ({$pct($premissas->regularSuppliersPct(), 0)} das compras de fornecedores do regime regular) = {$fmt($b['tax'])}. Crédito transferido aos clientes B2B: {$fmt($b['credit_customers'])}.",
            "Crédito adicional aos clientes B2B no regime regular: {$fmt($extraCredit)}; com repasse de {$pct($premissas->creditPassThroughPct(), 0)}, benefício comercial estimado de {$fmt($benefit)}.",
            match ($recommendation) {
                Comparison::RECOMMEND_MIGRATE => "Optar pelo IBS/CBS no regime regular reduz o custo efetivo em {$fmt(ltrim($diff, '-'))} ({$pct(abs($diffPct))} da receita).",
                Comparison::RECOMMEND_KEEP => "Manter o IBS/CBS no Simples custa {$fmt($diff)} a menos ({$pct(abs($diffPct))} da receita).",
                default => "A diferença ({$fmt($diff)}; {$pct(abs($diffPct))} da receita) é menor que o limite de {$pct($threshold)} definido nas premissas.",
            },
        ];

        $itemErrors = count($a['simulation']['item_errors']) + count($b['simulation']['item_errors']);
        if ($itemErrors > 0) {
            $warnings[] = "{$itemErrors} item(ns) com erro na calculadora oficial ficaram fora dos totais. Veja a aba de erros.";
        }

        return [
            'ano_referencia' => $year,
            'receita_bruta' => $revenue,
            'receita_b2b' => $lines->salesB2bTotal(),
            'compras' => $lines->purchasesTotal(),
            'periodo' => ['inicio' => $lines->periodStart, 'fim' => $lines->periodEnd, 'origem' => $lines->source],
            'cenarios' => [
                Simulation::SCENARIO_SIMPLES => [
                    'ibs' => $a['result']->totalIbs(),
                    'cbs' => $a['result']->totalCbs(),
                    'total_ibs_cbs' => $a['tax'],
                    'credito_compras_aproveitado' => '0.00',
                    'custo_liquido' => $a['tax'],
                    'credito_clientes_b2b' => $a['credit_customers'],
                    'beneficio_comercial' => '0.00',
                    'custo_efetivo' => $a['tax'],
                ],
                Simulation::SCENARIO_REGULAR => [
                    'ibs' => $b['sales']->totalIbs(),
                    'cbs' => $b['sales']->totalCbs(),
                    'total_ibs_cbs' => $b['debit'],
                    'credito_compras_bruto' => $b['credit_gross'],
                    'credito_compras_aproveitado' => $b['credit_used'],
                    'custo_liquido' => $b['tax'],
                    'credito_clientes_b2b' => $b['credit_customers'],
                    'beneficio_comercial' => $benefit,
                    'custo_efetivo' => $effectiveRegular,
                ],
            ],
            'diferenca' => [
                'valor' => $diff,
                'percentual_receita' => $diffPct,
                'credito_adicional_clientes' => $extraCredit,
            ],
            'recomendacao' => $recommendation,
            'justificativa' => $justification,
            'avisos' => array_values(array_unique($warnings)),
            'erros_itens' => $itemErrors,
        ];
    }

    private function assumptionsUsed(Premissas $p, Client $client, int $year, array $rates, array $nominal, OperationLines $lines): array
    {
        $defs = Premissas::definitions();

        return [
            'valores' => $p->toArray(),
            'lista' => [
                ['id' => 'P-01', 'descricao' => 'Alíquotas efetivas de IBS/CBS no Simples', 'valor' => $rates['memoria']],
                ['id' => 'P-02', 'descricao' => 'Comparação restrita a IBS/CBS (demais tributos do DAS iguais nos dois cenários)', 'valor' => 'Sim'],
                ['id' => 'P-03', 'descricao' => 'Vendas B2B', 'valor' => $lines->source === 'manual'
                    ? number_format((float) ($client->b2b_sales_pct), 2, ',', '.').'% (cadastro/entrada manual)'
                    : 'Destinatário PJ nas notas importadas'],
                ['id' => 'P-04', 'descricao' => $defs['credit_pass_through_pct']['label'], 'valor' => self::pct($p->creditPassThroughPct())],
                ['id' => 'P-05', 'descricao' => 'Transição do IBS no Simples (fração do ICMS/ISS)', 'valor' => self::pct($p->simplesIbsTransition($year) * 100).' do ICMS/ISS do DAS'],
                ['id' => 'P-06', 'descricao' => "Alíquotas nominais {$year} (CBS / IBS UF / IBS Mun)", 'valor' => implode(' / ', array_map(fn ($v) => self::pct((float) $v), [$nominal['cbs'], $nominal['ibs_estadual'], $nominal['ibs_municipal']]))],
                ['id' => 'P-07', 'descricao' => $defs['regular_suppliers_pct']['label'], 'valor' => self::pct($p->regularSuppliersPct())],
                ['id' => 'P-08', 'descricao' => 'Período de receitas usado como base do ano de referência', 'valor' => $lines->source === 'manual'
                    ? 'Valores informados'
                    : "{$lines->periodStart} a {$lines->periodEnd} (12 meses até a última nota de saída)"],
                ['id' => 'P-09', 'descricao' => 'Classificação padrão para itens sem grupo IBSCBS', 'valor' => "CST {$client->default_cst} / cClassTrib {$client->default_c_class_trib}"],
                ['id' => 'P-10', 'descricao' => $defs['recommendation_threshold_pct']['label'], 'valor' => self::pct($p->recommendationThresholdPct())],
                ['id' => 'P-11', 'descricao' => 'Crédito do adquirente de empresa do Simples = IBS/CBS recolhido no DAS', 'valor' => 'Sim'],
            ],
        ];
    }

    /** pt-BR percentage without useless trailing zeros: 8,8% / 0,05% / 50%. */
    private static function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',').'%';
    }
}
