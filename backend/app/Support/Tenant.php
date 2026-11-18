<?php

namespace App\Support;

use Closure;

/**
 * Holds the current tenant (office). Set by the SetTenant middleware for HTTP requests
 * and by Tenant::run() inside queued jobs.
 */
final class Tenant
{
    private static ?int $officeId = null;

    public static function id(): ?int
    {
        return self::$officeId;
    }

    public static function set(?int $officeId): void
    {
        self::$officeId = $officeId;
    }

    /**
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(int $officeId, Closure $callback): mixed
    {
        $previous = self::$officeId;
        self::$officeId = $officeId;

        try {
            return $callback();
        } finally {
            self::$officeId = $previous;
        }
    }
}
