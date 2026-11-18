<?php

namespace App\Services\Calculadora\Dto;

/** Common part of both observability responses. */
abstract class ObservabilidadeResult
{
    /** @param list<ItemResult> $itens */
    public function __construct(
        public readonly array $itens,
        public readonly array $raw,
    ) {}

    /** @return list<ItemResult> */
    public static function parseItens(array $raw): array
    {
        return array_map(
            fn ($o) => ItemResult::fromArray(is_array($o) ? $o : []),
            array_values($raw['objetos'] ?? []),
        );
    }

    public function item(int $nObj): ?ItemResult
    {
        foreach ($this->itens as $item) {
            if ($item->nObj === $nObj) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<array> items in error state with their error list */
    public function erros(): array
    {
        return array_values(array_map(
            fn (ItemResult $i) => $i->toErrorArray(),
            array_filter($this->itens, fn (ItemResult $i) => $i->estado->isError()),
        ));
    }

    public function hasErrors(): bool
    {
        return $this->erros() !== [];
    }

    /** @return array<string,int> count by estadoItem */
    public function contagemPorEstado(): array
    {
        $count = [];
        foreach ($this->itens as $item) {
            $count[$item->estado->value] = ($count[$item->estado->value] ?? 0) + 1;
        }

        return $count;
    }

    protected static function dec(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
