<?php

namespace App\Support;

final class Documento
{
    /** Removes the mask. Keeps letters because of the alphanumeric CNPJ. */
    public static function limpar(?string $value): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $value));
    }

    /**
     * Numeric and alphanumeric CNPJ. Each character is valued as ASCII - 48
     * (digits 0-9, letters A=17 ... Z=42); check digits are numeric, modulus 11.
     */
    public static function cnpjValido(string $value): bool
    {
        $cnpj = self::limpar($value);
        if (! preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        $values = array_map(fn ($c) => ord($c) - 48, str_split($cnpj));

        foreach ([12, 13] as $length) {
            $weights = $length === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += $values[$i] * $weights[$i];
            }
            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;
            if ($values[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function cpfValido(string $value): bool
    {
        $cpf = preg_replace('/\D/', '', $value);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            if ((int) $cpf[$t] !== ((10 * $sum) % 11) % 10) {
                return false;
            }
        }

        return true;
    }
}
