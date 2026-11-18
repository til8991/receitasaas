<?php

use App\Services\Comparador\Premissas;
use App\Services\Comparador\SimplesAliquotaService;

it('computes the effective rate and the CBS share for Annex I in 2027 (no IBS yet)', function () {
    // Annex I, bracket 4: (1.200.000 × 10,70% − 22.500) / 1.200.000 = 8,825%
    $r = (new SimplesAliquotaService)->calcular('I', 1200000, 2027, Premissas::fromArray([]));

    expect($r['faixa'])->toBe(4)
        ->and($r['aliquota_efetiva'])->toBe(8.825)
        ->and($r['reparticao_cbs_pct'])->toBe(15.5)          // PIS 2,76 + COFINS 12,74
        ->and($r['pCBSSN'])->toBe(round(8.825 * 0.155, 4))  // 1,3679
        ->and($r['pIBSSN'])->toBe(0.0)                      // transition factor 0 in 2027
        ->and($r['acima_sublimite'])->toBeFalse();
});

it('applies the IBS transition factor over the ICMS share', function () {
    $r = (new SimplesAliquotaService)->calcular('I', 1200000, 2029, Premissas::fromArray([]));

    // ICMS share 33,5% × 8,825% × 0,1
    expect($r['pIBSSN'])->toBe(round(8.825 * 0.335 * 0.1, 4));

    $r2033 = (new SimplesAliquotaService)->calcular('I', 1200000, 2033, Premissas::fromArray([]));
    expect($r2033['pIBSSN'])->toBe(round(8.825 * 0.335, 4));
});

it('caps the ISS share at 5 p.p. for service annexes', function () {
    // Annex IV bracket 5: (3.000.000 × 22% − 183.780) / 3.000.000 = 15,874%; ISS 40% = 6,3496 -> capped at 5
    $r = (new SimplesAliquotaService)->calcular('IV', 3000000, 2033, Premissas::fromArray([]));

    expect($r['tributo_substituido_ibs'])->toBe('ISS')
        ->and($r['pIBSSN'])->toBe(5.0);
});

it('uses the first bracket nominal rate without revenue history and flags the sublimit', function () {
    $service = new SimplesAliquotaService;

    expect($service->calcular('III', 0, 2027, Premissas::fromArray([]))['aliquota_efetiva'])->toBe(6.0)
        ->and($service->calcular('I', 4000000, 2027, Premissas::fromArray([]))['acima_sublimite'])->toBeTrue();
});

it('prefers manual rates when informed', function () {
    $r = (new SimplesAliquotaService)->calcular(null, 0, 2027, Premissas::fromArray(['p_ibs_sn' => 1.25, 'p_cbs_sn' => 2.75]));

    expect($r['origem'])->toBe('manual')->and($r['pIBSSN'])->toBe(1.25)->and($r['pCBSSN'])->toBe(2.75);
});

it('requires the annex when rates are not manual', function () {
    (new SimplesAliquotaService)->calcular(null, 100000, 2027, Premissas::fromArray([]));
})->throws(InvalidArgumentException::class, 'anexo');
