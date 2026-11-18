<?php

namespace App\Services\Calculadora\Dto\RegimeGeral;

use DateTimeInterface;

/**
 * Body of POST /calculadora/observabilidade/regime-geral (api/model/input/OperacaoInput.java).
 * Required: id, versao, municipio (IBGE), itens. dhFatoGerador is ISO-8601 with offset.
 */
final readonly class OperacaoInput
{
    public const VERSAO = '1.0.0';

    /** @param list<ItemInput> $itens */
    public function __construct(
        public string $id,
        public DateTimeInterface $dhFatoGerador,
        public int $municipio,
        public string $uf,
        public array $itens,
        public ?int $tpDoc = null,
        public string $versao = self::VERSAO,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'versao' => $this->versao,
            'dhFatoGerador' => $this->dhFatoGerador->format('Y-m-d\TH:i:sP'),
            'municipio' => $this->municipio,
            'uf' => $this->uf,
            'tpDoc' => $this->tpDoc,
            'itens' => array_map(fn (ItemInput $i) => $i->toArray(), $this->itens),
        ], fn ($v) => $v !== null);
    }
}
