<?php

namespace App\Services\Comparador;

use App\Models\Client;
use App\Models\Office;
use Illuminate\Validation\ValidationException;

/**
 * Resolved assumptions: config/premissas.php <- office overrides <- client overrides.
 * The resolved set is stored with every comparison for audit.
 */
final class Premissas
{
    private function __construct(private readonly array $values) {}

    public static function forOffice(Office $office): self
    {
        return new self(self::merge(config('premissas'), $office->assumptions ?? []));
    }

    public static function forClient(Client $client): self
    {
        $office = $client->office ?? Office::find($client->office_id);

        return new self(self::merge(self::forOffice($office)->values, $client->assumptions ?? []));
    }

    public static function fromArray(array $values): self
    {
        return new self(self::merge(config('premissas'), $values));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->values, $key, $default);
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function creditPassThroughPct(): float
    {
        return (float) $this->values['credit_pass_through_pct'];
    }

    public function regularSuppliersPct(): float
    {
        return (float) $this->values['regular_suppliers_pct'];
    }

    public function recommendationThresholdPct(): float
    {
        return (float) $this->values['recommendation_threshold_pct'];
    }

    /** @return array{cbs: float, ibs_estadual: float, ibs_municipal: float}|null */
    public function nominalRates(int $year): ?array
    {
        $rates = $this->values['nominal_rates'] ?? [];
        if (isset($rates[(string) $year])) {
            return array_map('floatval', $rates[(string) $year]);
        }
        // After the last configured year the last known rates apply (full regime from 2033).
        $years = array_map('intval', array_keys($rates));
        sort($years);
        $last = end($years);

        return $last !== false && $year > $last ? array_map('floatval', $rates[(string) $last]) : null;
    }

    public function simplesIbsTransition(int $year): float
    {
        $table = $this->values['simples_ibs_transition'] ?? [];
        if (isset($table[(string) $year])) {
            return (float) $table[(string) $year];
        }

        return $year >= 2033 ? 1.0 : 0.0;
    }

    public function simplesCbsActive(int $year): bool
    {
        return $year >= (int) $this->values['simples_cbs_start_year'];
    }

    public function manualSimplesRates(): ?array
    {
        $ibs = $this->values['p_ibs_sn'] ?? null;
        $cbs = $this->values['p_cbs_sn'] ?? null;

        return ($ibs === null && $cbs === null) ? null : ['ibs' => (float) ($ibs ?? 0), 'cbs' => (float) ($cbs ?? 0)];
    }

    /** Metadata shown in Settings and in the PDF. */
    public static function definitions(): array
    {
        return [
            'credit_pass_through_pct' => ['id' => 'P-04', 'label' => 'Repasse do crédito ao preço (%)', 'type' => 'percent',
                'help' => 'Quanto do crédito adicional de IBS/CBS que seus clientes B2B passam a receber se converte em benefício (preço/competitividade) para a empresa.'],
            'regular_suppliers_pct' => ['id' => 'P-07', 'label' => 'Compras de fornecedores do regime regular (%)', 'type' => 'percent',
                'help' => 'Parcela das compras que gera crédito integral de IBS/CBS no cenário regular.'],
            'recommendation_threshold_pct' => ['id' => 'P-10', 'label' => 'Diferença mínima para recomendar (% da receita)', 'type' => 'percent',
                'help' => 'Abaixo desse percentual da receita bruta a recomendação é "indiferente".'],
            'p_ibs_sn' => ['id' => 'P-01', 'label' => 'Alíquota efetiva IBS no Simples (p.p.) — manual', 'type' => 'rate_nullable',
                'help' => 'Deixe em branco para calcular pela LC 123 (alíquota efetiva × repartição do anexo).'],
            'p_cbs_sn' => ['id' => 'P-01', 'label' => 'Alíquota efetiva CBS no Simples (p.p.) — manual', 'type' => 'rate_nullable',
                'help' => 'Deixe em branco para calcular pela LC 123 (alíquota efetiva × repartição do anexo).'],
            'nominal_rates' => ['id' => 'P-06', 'label' => 'Alíquotas nominais do regime regular por ano (p.p.)', 'type' => 'rates_by_year',
                'help' => 'Enviadas à calculadora oficial em aliquotasNominais (obrigatórias a partir de 2027). Estimativas até a fixação das alíquotas de referência.'],
            'simples_ibs_transition' => ['id' => 'P-05', 'label' => 'Fração do ICMS/ISS do DAS convertida em IBS por ano', 'type' => 'factor_by_year',
                'help' => 'Transição da LC 214: 10% (2029) a 100% (2033).'],
            'simples_cbs_start_year' => ['id' => 'P-05', 'label' => 'Ano em que a CBS substitui PIS/COFINS no DAS', 'type' => 'year',
                'help' => 'Padrão 2027.'],
        ];
    }

    /** Keeps only known keys and validates their types. */
    public static function validateOverrides(array $input): array
    {
        $errors = [];
        $out = [];

        foreach ($input as $key => $value) {
            $def = self::definitions()[$key] ?? null;
            if ($def === null) {
                continue;
            }
            switch ($def['type']) {
                case 'percent':
                    if ($value === null || $value === '') {
                        break;
                    }
                    if (! is_numeric($value) || $value < 0 || $value > 100) {
                        $errors["assumptions.$key"] = 'Informe um percentual entre 0 e 100.';
                    } else {
                        $out[$key] = (float) $value;
                    }
                    break;
                case 'rate_nullable':
                    if ($value === null || $value === '') {
                        $out[$key] = null;
                    } elseif (! is_numeric($value) || $value < 0 || $value > 50) {
                        $errors["assumptions.$key"] = 'Informe uma alíquota entre 0 e 50 p.p.';
                    } else {
                        $out[$key] = (float) $value;
                    }
                    break;
                case 'year':
                    if (! is_numeric($value) || $value < 2026 || $value > 2040) {
                        $errors["assumptions.$key"] = 'Ano inválido.';
                    } else {
                        $out[$key] = (int) $value;
                    }
                    break;
                case 'rates_by_year':
                    if (! is_array($value)) {
                        $errors["assumptions.$key"] = 'Formato inválido.';
                        break;
                    }
                    foreach ($value as $year => $rates) {
                        if (! preg_match('/^20\d{2}$/', (string) $year) || ! is_array($rates)) {
                            $errors["assumptions.$key.$year"] = 'Ano inválido.';
                            continue;
                        }
                        foreach (['cbs', 'ibs_estadual', 'ibs_municipal'] as $r) {
                            if (! isset($rates[$r]) || ! is_numeric($rates[$r]) || $rates[$r] < 0 || $rates[$r] > 50) {
                                $errors["assumptions.$key.$year.$r"] = 'Alíquota inválida.';
                            } else {
                                $out[$key][(string) $year][$r] = (float) $rates[$r];
                            }
                        }
                    }
                    break;
                case 'factor_by_year':
                    if (! is_array($value)) {
                        $errors["assumptions.$key"] = 'Formato inválido.';
                        break;
                    }
                    foreach ($value as $year => $factor) {
                        if (! preg_match('/^20\d{2}$/', (string) $year) || ! is_numeric($factor) || $factor < 0 || $factor > 1) {
                            $errors["assumptions.$key.$year"] = 'Fator deve estar entre 0 e 1.';
                        } else {
                            $out[$key][(string) $year] = (float) $factor;
                        }
                    }
                    break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Recursive merge where overrides replace scalars and year maps are merged by year. */
    private static function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
