<?php

/*
|--------------------------------------------------------------------------
| Simples Nacional — tabelas da LC 123/2006 (Anexos I a V, redação da LC 155/2016)
|--------------------------------------------------------------------------
| PREMISSA P-01 (docs/premissas.md): a calculadora oficial do Simples recebe pIBSSN/pCBSSN
| prontos. Calculamos a alíquota efetiva pela fórmula do art. 18 da LC 123:
|     aliq_efetiva = (RBT12 × aliq_nominal − parcela_deduzir) / RBT12
| e a parcela de CBS/IBS pela repartição do anexo:
|     pCBSSN = aliq_efetiva × (PIS + COFINS)%                     (a CBS substitui PIS/COFINS)
|     pIBSSN = aliq_efetiva × (ICMS ou ISS)% × fator_transição_IBS (o IBS substitui ICMS/ISS)
|
| ATENÇÃO: valores transcritos da LC 123 e sujeitos a conferência pelo contador. A LC 214/2025
| alterou os anexos para incluir CBS/IBS; se a repartição oficial pós-reforma divergir, ajuste aqui
| ou informe pIBSSN/pCBSSN manualmente por cliente (premissas p_ibs_sn / p_cbs_sn).
|
| Cada faixa: [limite RBT12, alíquota nominal %, parcela a deduzir R$, repartição %]
*/

return [
    'annexes' => [
        'I' => [ // Comércio
            [180000.00,   4.00,      0.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 12.74, 'pis' => 2.76, 'cpp' => 41.50, 'icms' => 34.00]],
            [360000.00,   7.30,   5940.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 12.74, 'pis' => 2.76, 'cpp' => 41.50, 'icms' => 34.00]],
            [720000.00,   9.50,  13860.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 12.74, 'pis' => 2.76, 'cpp' => 42.00, 'icms' => 33.50]],
            [1800000.00, 10.70,  22500.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 12.74, 'pis' => 2.76, 'cpp' => 42.00, 'icms' => 33.50]],
            [3600000.00, 14.30,  87300.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 12.74, 'pis' => 2.76, 'cpp' => 42.00, 'icms' => 33.50]],
            [4800000.00, 19.00, 378000.00, ['irpj' => 13.50, 'csll' => 10.00, 'cofins' => 28.27, 'pis' => 6.13, 'cpp' => 42.10, 'icms' => 0.00]],
        ],
        'II' => [ // Indústria
            [180000.00,   4.50,      0.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 11.51, 'pis' => 2.49, 'cpp' => 37.50, 'ipi' => 7.50, 'icms' => 32.00]],
            [360000.00,   7.80,   5940.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 11.51, 'pis' => 2.49, 'cpp' => 37.50, 'ipi' => 7.50, 'icms' => 32.00]],
            [720000.00,  10.00,  13860.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 11.51, 'pis' => 2.49, 'cpp' => 37.50, 'ipi' => 7.50, 'icms' => 32.00]],
            [1800000.00, 11.20,  22500.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 11.51, 'pis' => 2.49, 'cpp' => 37.50, 'ipi' => 7.50, 'icms' => 32.00]],
            [3600000.00, 14.70,  85500.00, ['irpj' => 5.50, 'csll' => 3.50, 'cofins' => 11.51, 'pis' => 2.49, 'cpp' => 37.50, 'ipi' => 7.50, 'icms' => 32.00]],
            [4800000.00, 30.00, 720000.00, ['irpj' => 8.50, 'csll' => 7.50, 'cofins' => 20.96, 'pis' => 4.54, 'cpp' => 23.50, 'ipi' => 35.00, 'icms' => 0.00]],
        ],
        'III' => [ // Serviços (com CPP)
            [180000.00,   6.00,      0.00, ['irpj' => 4.00, 'csll' => 3.50, 'cofins' => 12.82, 'pis' => 2.78, 'cpp' => 43.40, 'iss' => 33.50]],
            [360000.00,  11.20,   9360.00, ['irpj' => 4.00, 'csll' => 3.50, 'cofins' => 14.05, 'pis' => 3.05, 'cpp' => 43.40, 'iss' => 32.00]],
            [720000.00,  13.50,  17640.00, ['irpj' => 4.00, 'csll' => 3.50, 'cofins' => 13.64, 'pis' => 2.96, 'cpp' => 43.40, 'iss' => 32.50]],
            [1800000.00, 16.00,  35640.00, ['irpj' => 4.00, 'csll' => 3.50, 'cofins' => 13.64, 'pis' => 2.96, 'cpp' => 43.40, 'iss' => 32.50]],
            [3600000.00, 21.00, 125640.00, ['irpj' => 4.00, 'csll' => 3.50, 'cofins' => 12.82, 'pis' => 2.78, 'cpp' => 43.40, 'iss' => 33.50]],
            [4800000.00, 33.00, 648000.00, ['irpj' => 35.00, 'csll' => 15.00, 'cofins' => 16.03, 'pis' => 3.47, 'cpp' => 30.50, 'iss' => 0.00]],
        ],
        'IV' => [ // Serviços (CPP fora do DAS)
            [180000.00,   4.50,      0.00, ['irpj' => 18.80, 'csll' => 15.20, 'cofins' => 17.67, 'pis' => 3.83, 'iss' => 44.50]],
            [360000.00,   9.00,   8100.00, ['irpj' => 19.80, 'csll' => 15.20, 'cofins' => 20.55, 'pis' => 4.45, 'iss' => 40.00]],
            [720000.00,  10.20,  12420.00, ['irpj' => 20.80, 'csll' => 15.20, 'cofins' => 19.73, 'pis' => 4.27, 'iss' => 40.00]],
            [1800000.00, 14.00,  39780.00, ['irpj' => 17.80, 'csll' => 19.20, 'cofins' => 18.90, 'pis' => 4.10, 'iss' => 40.00]],
            [3600000.00, 22.00, 183780.00, ['irpj' => 18.80, 'csll' => 19.20, 'cofins' => 18.08, 'pis' => 3.92, 'iss' => 40.00]],
            [4800000.00, 33.00, 828000.00, ['irpj' => 53.50, 'csll' => 21.50, 'cofins' => 20.55, 'pis' => 4.45, 'iss' => 0.00]],
        ],
        'V' => [ // Serviços (fator R < 28%)
            [180000.00,  15.50,      0.00, ['irpj' => 25.00, 'csll' => 15.00, 'cofins' => 14.10, 'pis' => 3.05, 'cpp' => 28.85, 'iss' => 14.00]],
            [360000.00,  18.00,   4500.00, ['irpj' => 23.00, 'csll' => 15.00, 'cofins' => 14.10, 'pis' => 3.05, 'cpp' => 27.85, 'iss' => 17.00]],
            [720000.00,  19.50,   9900.00, ['irpj' => 24.00, 'csll' => 15.00, 'cofins' => 14.92, 'pis' => 3.23, 'cpp' => 23.85, 'iss' => 19.00]],
            [1800000.00, 20.50,  17100.00, ['irpj' => 21.00, 'csll' => 15.00, 'cofins' => 15.74, 'pis' => 3.41, 'cpp' => 23.85, 'iss' => 21.00]],
            [3600000.00, 23.00,  62100.00, ['irpj' => 23.00, 'csll' => 12.50, 'cofins' => 14.10, 'pis' => 3.05, 'cpp' => 23.85, 'iss' => 23.50]],
            [4800000.00, 30.50, 540000.00, ['irpj' => 35.00, 'csll' => 15.50, 'cofins' => 16.44, 'pis' => 3.56, 'cpp' => 29.50, 'iss' => 0.00]],
        ],
    ],

    // Sublimite estadual/municipal: acima dele ICMS/ISS (e, por premissa, o IBS) ficam fora do DAS → CRT=2.
    'sublimite' => 3600000.00,
    'limite' => 4800000.00,

    // LC 123, art. 18, §14 / Anexo IV nota: percentual efetivo máximo do ISS no DAS.
    'iss_max_effective_pct' => 5.00,
];
