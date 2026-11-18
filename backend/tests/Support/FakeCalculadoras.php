<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Http::fake for both calculators that answers in the real response shape and computes
 * base × rate, so the comparator math can be asserted end to end without Docker.
 * Items whose cClassTrib is "999999" come back as INCONSISTENCIA_ENTRADA.
 */
final class FakeCalculadoras
{
    public static array $requests = [];

    public static function install(): void
    {
        self::$requests = [];
        config([
            'calculadoras.regime_geral.base_url' => 'http://calc-regular:8080/api',
            'calculadoras.simples_nacional.base_url' => 'http://calc-simples:8082/api',
            'calculadoras.cache_store' => 'array',
            'calculadoras.log_channel' => 'null',
        ]);
        app()->forgetInstance(\App\Services\Calculadora\RegimeGeralClient::class);
        app()->forgetInstance(\App\Services\Calculadora\SimplesNacionalClient::class);

        Http::fake(function (Request $request) {
            $url = $request->url();
            self::$requests[] = ['url' => $url, 'body' => $request->data()];

            return match (true) {
                str_ends_with($url, '/calculadora/dados-abertos/versao') => Http::response(['versaoApp' => '1.6.1', 'versaoDb' => 'v0059', 'ambiente' => 'offline']),
                str_ends_with($url, '/api/versao') => Http::response(['versaoApp' => '0.0.6', 'versaoDb' => 'v0059', 'ambiente' => 'offline']),
                str_ends_with($url, '/observabilidade/regime-geral') => Http::response(self::regimeGeral($request->data())),
                str_ends_with($url, '/observabilidade/simples-nacional') => Http::response(self::simples($request->data())),
                default => Http::response(['message' => 'not found'], 404),
            };
        });
    }

    private static function regimeGeral(array $op): array
    {
        $objetos = [];
        $tot = ['vBC' => 0.0, 'vIBS' => 0.0, 'vCBS' => 0.0];
        foreach ($op['itens'] as $item) {
            if ($item['cClassTrib'] === '999999') {
                $objetos[] = ['nObj' => $item['numero'], 'estadoItem' => 'INCONSISTENCIA_ENTRADA',
                    'erros' => [['code' => 'REG-011', 'title' => 'NCM não vinculada', 'pointer' => '#/itens/'.($item['numero'] - 1).'/ncm', 'detail' => 'teste']]];

                continue;
            }
            $a = $item['aliquotasNominais'];
            $base = (float) $item['baseCalculo'];
            $uf = round($base * $a['ibsEstadual'] / 100, 2);
            $mun = round($base * $a['ibsMunicipal'] / 100, 2);
            $cbs = round($base * $a['cbs'] / 100, 2);
            $tot['vBC'] += $base;
            $tot['vIBS'] += $uf + $mun;
            $tot['vCBS'] += $cbs;
            $objetos[] = ['nObj' => $item['numero'], 'estadoItem' => 'CALCULO_SIMULADO', 'tribCalc' => ['IBSCBS' => [
                'CST' => $item['cst'], 'cClassTrib' => $item['cClassTrib'], 'gIBSCBS' => [
                    'vBC' => self::f($base),
                    'gIBSUF' => ['pIBSUF' => self::f($a['ibsEstadual']), 'vIBSUF' => self::f($uf)],
                    'gIBSMun' => ['pIBSMun' => self::f($a['ibsMunicipal']), 'vIBSMun' => self::f($mun)],
                    'vIBS' => self::f($uf + $mun),
                    'gCBS' => ['pCBS' => self::f($a['cbs']), 'vCBS' => self::f($cbs)],
                ]]]];
        }

        return ['objetos' => $objetos, 'total' => ['tribCalc' => ['IBSCBSTot' => [
            'vBCIBSCBS' => self::f($tot['vBC']), 'gIBS' => ['vIBS' => self::f($tot['vIBS'])], 'gCBS' => ['vCBS' => self::f($tot['vCBS'])],
        ]]]];
    }

    private static function simples(array $op): array
    {
        $objetos = [];
        $tot = ['rb' => 0.0, 'bc' => 0.0, 'ibs' => 0.0, 'cbs' => 0.0];
        foreach ($op['itens'] as $item) {
            $base = (float) $item['baseCalculo'];
            if (! $item['cAtivSN']) {
                $objetos[] = ['nObj' => $item['numero'], 'estadoItem' => 'NAO_CALCULADO', 'tribCalc' => ['vRBSNitem' => self::f($base), 'tpRBSN' => 1]];
                $tot['rb'] += $base;

                continue;
            }
            $ibs = $op['CRT'] === '2' ? null : round($base * (float) $item['pIBSSN'] / 100, 2);
            $cbs = round($base * (float) $item['pCBSSN'] / 100, 2);
            $tot['rb'] += $base;
            $tot['bc'] += $base;
            $tot['ibs'] += $ibs ?? 0;
            $tot['cbs'] += $cbs;
            $objetos[] = ['nObj' => $item['numero'], 'estadoItem' => 'CALCULADO', 'tribCalc' => array_filter([
                'vRBSNitem' => self::f($base), 'tpRBSN' => 1, 'vBC' => self::f($base),
                'pIBSSN' => $item['pIBSSN'], 'vIBSSN' => $ibs === null ? null : self::f($ibs),
                'pCBSSN' => $item['pCBSSN'], 'vCBSSN' => self::f($cbs), 'totalItem' => self::f(($ibs ?? 0) + $cbs),
            ], fn ($v) => $v !== null)];
        }

        return ['oper' => ['data' => $op['data'], 'tpDoc' => $op['tpDoc'], 'CRT' => $op['CRT']], 'objetos' => $objetos, 'total' => [
            'vRBSNtot' => self::f($tot['rb']), 'vBC' => self::f($tot['bc']), 'vIBSSNtot' => self::f($tot['ibs']),
            'vCBSSNtot' => self::f($tot['cbs']), 'totalTributos' => self::f($tot['ibs'] + $tot['cbs']),
        ]];
    }

    private static function f(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
