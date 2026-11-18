<?php

namespace App\Http\Middleware;

use App\Services\Billing\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks write operations that consume the product (simulations, imports) when the plan is not active. */
class EnsureActiveSubscription
{
    public function __construct(private readonly PlanService $plans) {}

    public function handle(Request $request, Closure $next): Response
    {
        $office = $request->user()?->office;

        if ($office && ! $this->plans->isActive($office)) {
            return response()->json([
                'message' => 'Assinatura inativa ou período de teste encerrado. Atualize o plano em Configurações.',
                'code' => 'subscription_inactive',
            ], 402);
        }

        return $next($request);
    }
}
