<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Valid CNPJ, numeric or alphanumeric (IN RFB 2.229/2024), with or without mask. */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Documento::cnpjValido($value)) {
            $fail('O :attribute informado não é um CNPJ válido.');
        }
    }
}
