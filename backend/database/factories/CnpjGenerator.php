<?php

namespace Database\Factories;

/** Generates valid numeric CNPJs for tests and seeders. */
final class CnpjGenerator
{
    public static function make(): string
    {
        $base = '';
        for ($i = 0; $i < 8; $i++) {
            $base .= random_int(0, 9);
        }
        $base .= '0001';

        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;
            foreach ($weights as $i => $w) {
                $sum += (int) $base[$i] * $w;
            }
            $rest = $sum % 11;
            $base .= $rest < 2 ? 0 : 11 - $rest;
        }

        return $base;
    }
}
