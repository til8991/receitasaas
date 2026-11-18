<?php

namespace App\Services\Calculadora\Dto;

/**
 * One element of "objetos" in an observability response:
 * {nObj, estadoItem, tribCalc, erros[{code, title, pointer, detail}]}.
 */
final readonly class ItemResult
{
    /** @param list<array{code?:string,title?:string,pointer?:string,detail?:string}> $erros */
    public function __construct(
        public int $nObj,
        public EstadoItem $estado,
        public array $tribCalc,
        public array $erros,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['nObj'] ?? 0),
            EstadoItem::parse($data['estadoItem'] ?? null),
            is_array($data['tribCalc'] ?? null) ? $data['tribCalc'] : [],
            is_array($data['erros'] ?? null) ? array_values($data['erros']) : [],
        );
    }

    public function toErrorArray(): array
    {
        return [
            'nObj' => $this->nObj,
            'estadoItem' => $this->estado->value,
            'erros' => $this->erros,
        ];
    }
}
