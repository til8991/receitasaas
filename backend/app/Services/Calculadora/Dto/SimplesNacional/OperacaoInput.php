<?php

namespace App\Services\Calculadora\Dto\SimplesNacional;

use DateTimeInterface;

/**
 * Body of POST /calculadora/observabilidade/simples-nacional
 * (api/simplesnacional/model/input/OperacaoSimplesInput.java). All scalars are strings.
 *   tpDoc: 55 (NF-e) | 65 (NFC-e)
 *   CRT: 1 SN | 2 SN excesso de sublimite | 3 regime normal (only with tpRegimeIBSCBS=2) | 4 MEI (out of scope)
 *   tpRegimeIBSCBS: 2 = apuração por fora (only with CRT=3)
 */
final readonly class OperacaoInput
{
    public const VERSAO = '2.0.0';

    /** @param list<ItemInput> $itens */
    public function __construct(
        public string $id,
        public DateTimeInterface $data,
        public array $itens,
        public string $crt = '1',
        public string $tpDoc = '55',
        public ?string $tpRegimeIBSCBS = null,
        public ?string $tpEnteGov = null,
        public string $versao = self::VERSAO,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'versao' => $this->versao,
            'data' => $this->data->format('Y-m-d'),
            'tpDoc' => $this->tpDoc,
            'CRT' => $this->crt,
            'tpRegimeIBSCBS' => $this->tpRegimeIBSCBS,
            'tpEnteGov' => $this->tpEnteGov,
            'itens' => array_map(fn (ItemInput $i) => $i->toArray(), $this->itens),
        ], fn ($v) => $v !== null);
    }
}
