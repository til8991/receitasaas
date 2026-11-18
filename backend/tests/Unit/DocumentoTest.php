<?php

use App\Support\Documento;
use Database\Factories\CnpjGenerator;

it('validates numeric CNPJ with and without mask', function () {
    expect(Documento::cnpjValido('11.222.333/0001-81'))->toBeTrue()
        ->and(Documento::cnpjValido('11222333000181'))->toBeTrue()
        ->and(Documento::cnpjValido('11222333000182'))->toBeFalse()
        ->and(Documento::cnpjValido('11111111111111'))->toBeFalse()
        ->and(Documento::cnpjValido('123'))->toBeFalse();
});

it('validates the alphanumeric CNPJ (Receita Federal example 12.ABC.345/01DE-35)', function () {
    expect(Documento::cnpjValido('12.ABC.345/01DE-35'))->toBeTrue()
        ->and(Documento::cnpjValido('12ABC34501DE36'))->toBeFalse();
});

it('generates valid CNPJs in factories', function () {
    foreach (range(1, 20) as $_) {
        expect(Documento::cnpjValido(CnpjGenerator::make()))->toBeTrue();
    }
});

it('validates CPF', function () {
    expect(Documento::cpfValido('529.982.247-25'))->toBeTrue()
        ->and(Documento::cpfValido('52998224724'))->toBeFalse();
});
