<?php

namespace App\Services\Invoices;

use DateTimeImmutable;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Throwable;

/**
 * Extracts what the comparator needs from NF-e/NFC-e (models 55/65) and NFS-e XML:
 * NCM/NBS, CFOP, CST/cClassTrib (IBSCBS group, when present), values, date and recipient (PJ/PF).
 * Namespace-agnostic (local-name()) and safe against XXE (no network, no entity expansion).
 */
class InvoiceXmlParser
{
    public function parse(string $xml): ParsedDocument
    {
        $xml = trim($xml);
        // Strip a UTF-8 BOM if present.
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            $xml = substr($xml, 3);
        }
        if ($xml === '' || stripos($xml, '<!DOCTYPE') !== false) {
            throw new InvoiceParseException('Arquivo vazio ou com DOCTYPE (não suportado).');
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $ok) {
            throw new InvoiceParseException('XML inválido.');
        }
        // Checked again after decoding: the textual check above can be bypassed with other encodings (e.g. UTF-16).
        if ($dom->doctype !== null) {
            throw new InvoiceParseException('Arquivo com DOCTYPE (não suportado).');
        }

        $xp = new DOMXPath($dom);

        if ($this->node($xp, '//*[local-name()="infNFe"]')) {
            return $this->parseNfe($xp);
        }
        if ($this->node($xp, '//*[local-name()="infNFSe"]') || $this->node($xp, '//*[local-name()="infDPS"]')) {
            return $this->parseNfseNacional($xp);
        }
        if ($this->node($xp, '//*[local-name()="InfNfse"]')) {
            return $this->parseNfseAbrasf($xp);
        }
        if ($this->node($xp, '//*[local-name()="infEvento"]')) {
            throw new InvoiceParseException('Arquivo de evento (cancelamento/CC-e) ignorado.');
        }

        throw new InvoiceParseException('Layout não reconhecido (esperado NF-e, NFC-e ou NFS-e).');
    }

    // ---------------------------------------------------------------- NF-e / NFC-e

    private function parseNfe(DOMXPath $xp): ParsedDocument
    {
        $inf = $this->node($xp, '//*[local-name()="infNFe"]');
        $key = preg_replace('/\D/', '', (string) $inf->attributes?->getNamedItem('Id')?->nodeValue);
        $model = $this->text($xp, '*[local-name()="ide"]/*[local-name()="mod"]', $inf);
        $type = $model === '65' ? 'nfce' : 'nfe';

        $issued = $this->text($xp, '*[local-name()="ide"]/*[local-name()="dhEmi"]', $inf)
            ?? $this->text($xp, '*[local-name()="ide"]/*[local-name()="dEmi"]', $inf);

        [$recipientDoc, $recipientType] = $this->recipient($xp, $inf, 'dest');
        $items = [];

        foreach ($xp->query('*[local-name()="det"]', $inf) as $det) {
            $prod = $this->node($xp, '*[local-name()="prod"]', $det);
            if (! $prod) {
                continue;
            }
            $vProd = $this->dec($this->text($xp, '*[local-name()="vProd"]', $prod));
            $vFrete = $this->dec($this->text($xp, '*[local-name()="vFrete"]', $prod));
            $vSeg = $this->dec($this->text($xp, '*[local-name()="vSeg"]', $prod));
            $vOutro = $this->dec($this->text($xp, '*[local-name()="vOutro"]', $prod));
            $vDesc = $this->dec($this->text($xp, '*[local-name()="vDesc"]', $prod));
            $ibscbs = $this->node($xp, '*[local-name()="imposto"]/*[local-name()="IBSCBS"]', $det);

            $items[] = [
                'item_number' => (int) ($det->attributes?->getNamedItem('nItem')?->nodeValue ?? count($items) + 1),
                'description' => mb_substr((string) $this->text($xp, '*[local-name()="xProd"]', $prod), 0, 255),
                'ncm' => $this->digits($this->text($xp, '*[local-name()="NCM"]', $prod), 8),
                'nbs' => null,
                'cfop' => $this->digits($this->text($xp, '*[local-name()="CFOP"]', $prod), 4),
                'cst' => $ibscbs ? $this->digits($this->text($xp, '*[local-name()="CST"]', $ibscbs), 3) : null,
                'c_class_trib' => $ibscbs ? $this->digits($this->text($xp, '*[local-name()="cClassTrib"]', $ibscbs), 6) : null,
                'quantity' => $this->dec($this->text($xp, '*[local-name()="qCom"]', $prod), 4),
                'unit' => mb_substr((string) $this->text($xp, '*[local-name()="uCom"]', $prod), 0, 10) ?: null,
                'product_amount' => $vProd,
                'freight' => $vFrete,
                'insurance' => $vSeg,
                'other' => $vOutro,
                'discount' => $vDesc,
                'base_amount' => $this->base($vProd, $vFrete, $vSeg, $vOutro, $vDesc),
            ];
        }

        return new ParsedDocument(
            $type,
            $key ?: hash('sha256', $xp->document->saveXML()),
            $this->text($xp, '*[local-name()="ide"]/*[local-name()="nNF"]', $inf),
            $this->date($issued),
            $this->docOf($xp, $this->node($xp, '*[local-name()="emit"]', $inf)),
            $recipientDoc,
            $recipientType,
            $this->text($xp, '*[local-name()="dest"]/*[local-name()="enderDest"]/*[local-name()="UF"]', $inf),
            $items,
        );
    }

    // ---------------------------------------------------------------- NFS-e Nacional (ADN)

    private function parseNfseNacional(DOMXPath $xp): ParsedDocument
    {
        $infNfse = $this->node($xp, '//*[local-name()="infNFSe"]');
        $dps = $this->node($xp, '//*[local-name()="infDPS"]');
        $key = $infNfse ? preg_replace('/[^0-9A-Za-z]/', '', (string) $infNfse->attributes?->getNamedItem('Id')?->nodeValue) : null;

        $issued = $this->text($xp, '*[local-name()="dhEmi"]', $dps) ?? $this->text($xp, '//*[local-name()="dhProc"]');
        $serv = $this->node($xp, '*[local-name()="serv"]', $dps);
        $vServ = $this->dec($this->text($xp, '//*[local-name()="vServPrest"]/*[local-name()="vServ"]')
            ?? $this->text($xp, '//*[local-name()="vServ"]'));
        $vDesc = $this->dec($this->text($xp, '//*[local-name()="vDescCondIncond"]/*[local-name()="vDescIncond"]'));
        $ibscbs = $this->node($xp, '//*[local-name()="IBSCBS"]');

        [$recipientDoc, $recipientType] = $this->recipient($xp, $dps, 'toma');

        $item = [
            'item_number' => 1,
            'description' => mb_substr((string) $this->text($xp, '*[local-name()="cServ"]/*[local-name()="xDescServ"]', $serv), 0, 255),
            'ncm' => null,
            'nbs' => $this->digits($this->text($xp, '*[local-name()="cServ"]/*[local-name()="cNBS"]', $serv), 9),
            'cfop' => null,
            'cst' => $ibscbs ? $this->digits($this->text($xp, './/*[local-name()="CST"]', $ibscbs), 3) : null,
            'c_class_trib' => $ibscbs ? $this->digits($this->text($xp, './/*[local-name()="cClassTrib"]', $ibscbs), 6) : null,
            'quantity' => '1.0000',
            'unit' => null,
            'product_amount' => $vServ,
            'freight' => '0.00',
            'insurance' => '0.00',
            'other' => '0.00',
            'discount' => $vDesc,
            'base_amount' => $this->base($vServ, '0', '0', '0', $vDesc),
        ];

        $issuer = $this->docOf($xp, $this->node($xp, '*[local-name()="prest"]', $dps))
            ?? $this->docOf($xp, $this->node($xp, '//*[local-name()="emit"]'));
        $number = $this->text($xp, '//*[local-name()="nNFSe"]') ?? $this->text($xp, '*[local-name()="nDPS"]', $dps);

        return new ParsedDocument(
            'nfse',
            $key ?: hash('sha256', 'nfse|'.$issuer.'|'.$number.'|'.$issued),
            $number,
            $this->date($issued),
            $issuer,
            $recipientDoc,
            $recipientType,
            null,
            [$item],
        );
    }

    // ---------------------------------------------------------------- NFS-e ABRASF (municipal)

    private function parseNfseAbrasf(DOMXPath $xp): ParsedDocument
    {
        $inf = $this->node($xp, '//*[local-name()="InfNfse"]');
        $number = $this->text($xp, '*[local-name()="Numero"]', $inf);
        $issued = $this->text($xp, '*[local-name()="DataEmissao"]', $inf)
            ?? $this->text($xp, './/*[local-name()="DataEmissao"]', $inf);
        $issuer = $this->digits($this->text($xp, './/*[local-name()="PrestadorServico" or local-name()="Prestador"]//*[local-name()="Cnpj"]', $inf));

        $tomador = $this->node($xp, './/*[local-name()="TomadorServico" or local-name()="Tomador"]', $inf);
        $cnpj = $tomador ? $this->digits($this->text($xp, './/*[local-name()="Cnpj"]', $tomador)) : null;
        $cpf = $tomador ? $this->digits($this->text($xp, './/*[local-name()="Cpf"]', $tomador)) : null;
        $uf = $tomador ? $this->text($xp, './/*[local-name()="Uf"]', $tomador) : null;

        $vServ = $this->dec($this->text($xp, './/*[local-name()="Valores"]/*[local-name()="ValorServicos"]', $inf));
        $vDesc = $this->dec($this->text($xp, './/*[local-name()="Valores"]/*[local-name()="DescontoIncondicionado"]', $inf));

        return new ParsedDocument(
            'nfse',
            hash('sha256', 'nfse|'.$issuer.'|'.$number.'|'.$this->text($xp, './/*[local-name()="CodigoVerificacao"]', $inf)),
            $number,
            $this->date($issued),
            $issuer,
            $cnpj ?: $cpf,
            $cnpj ? 'pj' : ($cpf ? 'pf' : 'unknown'),
            $uf ? strtoupper($uf) : null,
            [[
                'item_number' => 1,
                'description' => mb_substr((string) $this->text($xp, './/*[local-name()="Discriminacao"]', $inf), 0, 255),
                'ncm' => null,
                'nbs' => null, // ABRASF has no NBS: the client's default NBS is used in the simulation
                'cfop' => null,
                'cst' => null,
                'c_class_trib' => null,
                'quantity' => '1.0000',
                'unit' => null,
                'product_amount' => $vServ,
                'freight' => '0.00',
                'insurance' => '0.00',
                'other' => '0.00',
                'discount' => $vDesc,
                'base_amount' => $this->base($vServ, '0', '0', '0', $vDesc),
            ]],
        );
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: ?string, 1: string} */
    private function recipient(DOMXPath $xp, ?DOMNode $context, string $tag): array
    {
        $node = $context ? $this->node($xp, '*[local-name()="'.$tag.'"]', $context) : null;
        if (! $node) {
            return [null, 'unknown'];
        }
        if ($cnpj = $this->digits($this->text($xp, '*[local-name()="CNPJ"]', $node))) {
            return [$cnpj, 'pj'];
        }
        if ($cpf = $this->digits($this->text($xp, '*[local-name()="CPF"]', $node))) {
            return [$cpf, 'pf'];
        }
        if ($this->text($xp, '*[local-name()="idEstrangeiro"]', $node) !== null || $this->text($xp, '*[local-name()="NIF"]', $node) !== null) {
            return [null, 'exterior'];
        }

        return [null, 'unknown'];
    }

    private function docOf(DOMXPath $xp, ?DOMNode $node): ?string
    {
        if (! $node) {
            return null;
        }

        return $this->digits($this->text($xp, '*[local-name()="CNPJ"]', $node))
            ?: $this->digits($this->text($xp, '*[local-name()="CPF"]', $node));
    }

    private function node(DOMXPath $xp, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $list = $context ? $xp->query($query, $context) : $xp->query($query);

        return $list !== false && $list->length > 0 ? $list->item(0) : null;
    }

    private function text(DOMXPath $xp, string $query, ?DOMNode $context = null): ?string
    {
        $node = $this->node($xp, $query, $context);
        $value = $node ? trim($node->textContent) : null;

        return $value === '' ? null : $value;
    }

    private function digits(?string $value, ?int $length = null): ?string
    {
        if ($value === null) {
            return null;
        }
        $d = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $value));
        if ($d === '') {
            return null;
        }

        return $length ? substr($d, 0, $length) : $d;
    }

    private function dec(?string $value, int $scale = 2): string
    {
        return number_format((float) ($value ?? 0), $scale, '.', '');
    }

    private function base(string $vProd, string $frete, string $seg, string $outro, string $desc): string
    {
        $sum = bcadd(bcadd(bcadd($vProd, $frete, 2), $seg, 2), $outro, 2);

        return bcsub($sum, $desc, 2);
    }

    private function date(?string $value): DateTimeImmutable
    {
        if (! $value) {
            throw new InvoiceParseException('Data de emissão não encontrada.');
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new InvoiceParseException("Data de emissão inválida: {$value}");
        }
    }
}
