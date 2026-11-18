<?php

namespace App\Services\Invoices;

use DateTimeImmutable;

final class ParsedDocument
{
    /** @param list<array> $items */
    public function __construct(
        public readonly string $type,             // nfe|nfce|nfse
        public readonly string $key,              // access key or deterministic hash
        public readonly ?string $number,
        public readonly DateTimeImmutable $issuedAt,
        public readonly ?string $issuerDocument,
        public readonly ?string $recipientDocument,
        public readonly string $recipientType,    // pj|pf|exterior|unknown
        public readonly ?string $recipientUf,
        public readonly array $items,
    ) {}
}
