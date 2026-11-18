<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\SetTenant;
use App\Services\Calculadora\CalculadoraException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenant::class,
            'subscription' => EnsureActiveSubscription::class,
        ]);
        // The tenant must be known before route model binding so {client} etc. are tenant-scoped.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenant::class);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (CalculadoraException $e, Request $request) {
            return response()->json([
                'message' => 'Não foi possível consultar a calculadora oficial. Tente novamente em instantes.',
                'calculadora' => $e->calculadora,
                'detail' => $e->getMessage(),
            ], 503);
        });
    })->create();
