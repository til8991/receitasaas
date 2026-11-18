<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Scopes every tenant-aware model to the authenticated user's office. */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenant::set($request->user()?->office_id);

        try {
            return $next($request);
        } finally {
            Tenant::set(null);
        }
    }
}
