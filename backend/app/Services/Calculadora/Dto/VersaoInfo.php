<?php

namespace App\Services\Calculadora\Dto;

/**
 * Response of GET /calculadora/dados-abertos/versao (regime regular) and GET /versao (Simples).
 * Both expose versaoApp, versaoDb, descricaoVersaoDb, dataVersaoDb and ambiente.
 */
final readonly class VersaoInfo
{
    public function __construct(
        public ?string $versaoApp,
        public ?string $versaoDb,
        public ?string $descricaoVersaoDb,
        public ?string $dataVersaoDb,
        public ?string $ambiente,
        public array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['versaoApp'] ?? null,
            isset($data['versaoDb']) ? (string) $data['versaoDb'] : null,
            $data['descricaoVersaoDb'] ?? null,
            $data['dataVersaoDb'] ?? null,
            $data['ambiente'] ?? null,
            $data,
        );
    }

    public function toArray(): array
    {
        return [
            'versaoApp' => $this->versaoApp,
            'versaoDb' => $this->versaoDb,
            'descricaoVersaoDb' => $this->descricaoVersaoDb,
            'dataVersaoDb' => $this->dataVersaoDb,
            'ambiente' => $this->ambiente,
        ];
    }
}
