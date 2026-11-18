<?php

namespace App\Services\Calculadora;

use RuntimeException;
use Throwable;

/**
 * The calculator could not be reached or answered with a non-2xx status.
 * Per-item problems are NOT exceptions: the observability endpoints return them inside the 200 body.
 */
class CalculadoraException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $calculadora,
        public readonly ?int $status = null,
        public readonly mixed $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function context(): array
    {
        return ['calculadora' => $this->calculadora, 'status' => $this->status, 'body' => $this->body];
    }
}
