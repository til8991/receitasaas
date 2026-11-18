<?php

/*
|--------------------------------------------------------------------------
| Premissas padrão do comparador (docs/premissas.md)
|--------------------------------------------------------------------------
| Tudo aqui é regra de negócio que NÃO vem das calculadoras oficiais. O escritório
| sobrescreve em Configurações (offices.assumptions) e cada cliente pode sobrescrever
| de novo (clients.assumptions). Os valores efetivamente usados ficam gravados em cada comparação.
*/

return [
    // P-03 está no cadastro do cliente (clients.b2b_sales_pct).

    // P-04 — quanto do crédito ADICIONAL gerado aos clientes B2B no regime regular vira benefício
    // para a empresa (preço/competitividade). 0 = ignora o efeito comercial; 100 = crédito integral.
    'credit_pass_through_pct' => 50.0,

    // P-07 — % das compras feitas de fornecedores do regime regular (geram crédito integral de IBS/CBS
    // no cenário regular). Compras de fornecedores do Simples geram crédito limitado ao pago no DAS.
    'regular_suppliers_pct' => 70.0,

    // P-10 — diferença mínima (em % da receita bruta do período) para a recomendação sair de "indiferente".
    'recommendation_threshold_pct' => 0.5,

    // P-01 — sobrescrita manual das alíquotas efetivas do Simples (p.p.). null = calcular pela LC 123.
    'p_ibs_sn' => null,
    'p_cbs_sn' => null,

    // P-06 — alíquotas nominais (p.p.) enviadas à calculadora do regime regular em aliquotasNominais
    // (obrigatórias a partir de 2027). As alíquotas de referência definitivas serão fixadas pelo Senado;
    // os valores abaixo são ESTIMATIVAS (CBS 8,8% como no exemplo oficial; IBS de referência total
    // estimado em 17,7%, 2/3 estadual e 1/3 municipal, seguindo a transição da LC 214).
    'nominal_rates' => [
        '2027' => ['cbs' => 8.8, 'ibs_estadual' => 0.05, 'ibs_municipal' => 0.05],
        '2028' => ['cbs' => 8.8, 'ibs_estadual' => 0.05, 'ibs_municipal' => 0.05],
        '2029' => ['cbs' => 8.8, 'ibs_estadual' => 1.18, 'ibs_municipal' => 0.59],
        '2030' => ['cbs' => 8.8, 'ibs_estadual' => 2.36, 'ibs_municipal' => 1.18],
        '2031' => ['cbs' => 8.8, 'ibs_estadual' => 3.54, 'ibs_municipal' => 1.77],
        '2032' => ['cbs' => 8.8, 'ibs_estadual' => 4.72, 'ibs_municipal' => 2.36],
        '2033' => ['cbs' => 8.8, 'ibs_estadual' => 11.80, 'ibs_municipal' => 5.90],
    ],

    // P-05 — fração da parcela de ICMS/ISS do DAS que vira IBS em cada ano (transição da LC 214:
    // 2029 10%, 2030 20%, 2031 30%, 2032 40%, 2033 100%). Antes de 2029 = 0.
    'simples_ibs_transition' => [
        '2026' => 0.0, '2027' => 0.0, '2028' => 0.0, '2029' => 0.1,
        '2030' => 0.2, '2031' => 0.3, '2032' => 0.4, '2033' => 1.0,
    ],

    // P-05b — a CBS substitui PIS/COFINS no DAS a partir deste ano.
    'simples_cbs_start_year' => 2027,
];
