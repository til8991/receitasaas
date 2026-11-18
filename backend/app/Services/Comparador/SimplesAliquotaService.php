<?php

namespace App\Services\Comparador;

use InvalidArgumentException;

/**
 * PREMISSA P-01 / P-05 (docs/premissas.md).
 * The official Simples calculator receives pIBSSN/pCBSSN as input. We derive them from LC 123:
 *   aliq_efetiva = (RBT12 × nominal − parcela_deduzir) / RBT12
 *   pCBSSN = aliq_efetiva × (PIS + COFINS)% (from simples_cbs_start_year)
 *   pIBSSN = aliq_efetiva × (ICMS or ISS)% × transition factor of the year (ISS share capped at 5 p.p.)
 * Manual overrides (premissas p_ibs_sn / p_cbs_sn) take precedence.
 */
class SimplesAliquotaService
{
    public function calcular(?string $annex, float $rbt12, int $year, Premissas $premissas): array
    {
        $manual = $premissas->manualSimplesRates();
        if ($manual !== null) {
            return [
                'origem' => 'manual',
                'pIBSSN' => round($manual['ibs'], 4),
                'pCBSSN' => round($manual['cbs'], 4),
                'memoria' => 'Alíquotas efetivas de IBS/CBS no Simples informadas manualmente (premissa P-01).',
            ];
        }

        $table = config("simples_nacional.annexes.{$annex}");
        if (! $table) {
            throw new InvalidArgumentException('Informe o anexo do Simples Nacional do cliente (I a V) ou as alíquotas manuais.');
        }

        $limit = (float) config('simples_nacional.limite');
        $rbt12Used = $rbt12 > 0 ? min($rbt12, $limit) : 0.0;

        [$faixa, $row] = $this->faixa($table, $rbt12Used);
        [$teto, $nominal, $deduzir, $reparticao] = $row;

        // Without revenue history (start of activity) the first-bracket nominal rate is used (premissa P-01).
        $efetiva = $rbt12Used > 0 ? (($rbt12Used * $nominal / 100) - $deduzir) / $rbt12Used * 100 : $nominal;
        $efetiva = max(0.0, $efetiva);

        $cbsShare = ($reparticao['pis'] ?? 0) + ($reparticao['cofins'] ?? 0);
        $pCbs = $premissas->simplesCbsActive($year) ? $efetiva * $cbsShare / 100 : 0.0;

        $localTax = isset($reparticao['iss']) ? 'iss' : 'icms';
        $localEffective = $efetiva * ($reparticao[$localTax] ?? 0) / 100;
        if ($localTax === 'iss') {
            $localEffective = min($localEffective, (float) config('simples_nacional.iss_max_effective_pct'));
        }
        $transition = $premissas->simplesIbsTransition($year);
        $pIbs = $localEffective * $transition;

        $acimaSublimite = $rbt12Used > (float) config('simples_nacional.sublimite');

        return [
            'origem' => 'lc123',
            'anexo' => $annex,
            'faixa' => $faixa,
            'rbt12' => round($rbt12Used, 2),
            'aliquota_nominal' => $nominal,
            'parcela_deduzir' => $deduzir,
            'aliquota_efetiva' => round($efetiva, 4),
            'reparticao_cbs_pct' => $cbsShare,
            'reparticao_ibs_pct' => $reparticao[$localTax] ?? 0,
            'tributo_substituido_ibs' => strtoupper($localTax),
            'fator_transicao_ibs' => $transition,
            'acima_sublimite' => $acimaSublimite,
            'pIBSSN' => round($pIbs, 4),
            'pCBSSN' => round($pCbs, 4),
            'memoria' => sprintf(
                'Anexo %s, faixa %d: alíquota efetiva = (%s × %s%% − %s) / %s = %s%%. CBS = %s%% × %s%% (PIS+COFINS) = %s p.p.; IBS = %s%% × %s%% (%s) × fator %s = %s p.p.',
                $annex, $faixa, self::money($rbt12Used), self::num($nominal), self::money($deduzir), self::money($rbt12Used),
                self::num($efetiva, 4), self::num($efetiva, 4), self::num($cbsShare), self::num($pCbs, 4),
                self::num($efetiva, 4), self::num($reparticao[$localTax] ?? 0), strtoupper($localTax), self::num($transition),
                self::num($pIbs, 4),
            ),
        ];
    }

    private function faixa(array $table, float $rbt12): array
    {
        foreach ($table as $i => $row) {
            if ($rbt12 <= $row[0]) {
                return [$i + 1, $row];
            }
        }

        return [count($table), end($table)];
    }

    private static function num(float $v, int $d = 2): string
    {
        return number_format($v, $d, ',', '.');
    }

    private static function money(float $v): string
    {
        return 'R$ '.number_format($v, 2, ',', '.');
    }
}
