<?php

use App\Services\Invoices\InvoiceParseException;
use App\Services\Invoices\InvoiceXmlParser;

function parseXml(string $file)
{
    return (new InvoiceXmlParser)->parse(file_get_contents(__DIR__.'/../Fixtures/xml/'.$file));
}

it('parses an NF-e sale to a company with the IBSCBS group', function () {
    $doc = parseXml('nfe-venda-pj.xml');

    expect($doc->type)->toBe('nfe')
        ->and($doc->key)->toBe('43270311222333000181550010000012341000012345')
        ->and($doc->number)->toBe('1234')
        ->and($doc->issuedAt->format('Y-m-d'))->toBe('2027-03-10')
        ->and($doc->issuerDocument)->toBe('11222333000181')
        ->and($doc->recipientDocument)->toBe('19131243000197')
        ->and($doc->recipientType)->toBe('pj')
        ->and($doc->recipientUf)->toBe('SC')
        ->and($doc->items)->toHaveCount(2);

    expect($doc->items[0])->toMatchArray([
        'item_number' => 1,
        'ncm' => '22030000',
        'cfop' => '5102',
        'cst' => '000',
        'c_class_trib' => '000001',
        'product_amount' => '1000.00',
        'freight' => '50.00',
        'discount' => '30.00',
        'base_amount' => '1020.00',
        'quantity' => '100.0000',
    ]);
    expect($doc->items[1]['c_class_trib'])->toBe('200003');
});

it('parses an NFC-e to an individual without IBSCBS group', function () {
    $doc = parseXml('nfce-venda-pf.xml');

    expect($doc->type)->toBe('nfce')
        ->and($doc->recipientType)->toBe('pf')
        ->and($doc->recipientDocument)->toBe('52998224725')
        ->and($doc->items[0]['cst'])->toBeNull()
        ->and($doc->items[0]['base_amount'])->toBe('100.00');
});

it('parses the national NFS-e with NBS and IBSCBS', function () {
    $doc = parseXml('nfse-nacional.xml');

    expect($doc->type)->toBe('nfse')
        ->and($doc->issuerDocument)->toBe('11222333000181')
        ->and($doc->recipientType)->toBe('pj')
        ->and($doc->number)->toBe('1')
        ->and($doc->items[0])->toMatchArray([
            'nbs' => '115011000',
            'ncm' => null,
            'cst' => '000',
            'c_class_trib' => '000001',
            'base_amount' => '5000.00',
        ]);
});

it('parses the ABRASF municipal NFS-e', function () {
    $doc = parseXml('nfse-abrasf.xml');

    expect($doc->type)->toBe('nfse')
        ->and($doc->number)->toBe('777')
        ->and($doc->issuerDocument)->toBe('11222333000181')
        ->and($doc->recipientType)->toBe('pf')
        ->and($doc->recipientUf)->toBe('RS')
        ->and($doc->items[0]['nbs'])->toBeNull()
        ->and($doc->items[0]['base_amount'])->toBe('1500.00');
});

it('rejects XML with DOCTYPE (XXE)', function () {
    parseXml('xxe.xml');
})->throws(InvoiceParseException::class, 'DOCTYPE');

it('rejects a DOCTYPE hidden by UTF-16 encoding', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/xml/xxe.xml');
    $utf16 = "\xFF\xFE".mb_convert_encoding(str_replace('<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-16"?>', $xml), 'UTF-16LE', 'UTF-8');

    (new InvoiceXmlParser)->parse($utf16);
})->throws(InvoiceParseException::class);

it('rejects unknown layouts and invalid XML', function () {
    expect(fn () => (new InvoiceXmlParser)->parse('<foo/>'))->toThrow(InvoiceParseException::class, 'Layout')
        ->and(fn () => (new InvoiceXmlParser)->parse('<foo'))->toThrow(InvoiceParseException::class, 'inválido');
});
