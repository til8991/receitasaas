<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingGateway;
use App\Services\Billing\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly BillingGateway $gateway,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->plans->summary($request->user()->office)]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        Gate::authorize('admin');
        $data = $request->validate(['plan' => ['required', Rule::in(array_keys(config('plans.plans')))]]);
        $office = $request->user()->office;

        $max = config("plans.plans.{$data['plan']}.max_active_clients");
        if ($max !== null && $office->activeClientsCount() > $max) {
            throw ValidationException::withMessages([
                'plan' => "Você tem {$office->activeClientsCount()} CNPJs ativos; o plano escolhido permite {$max}. Desative clientes antes de mudar.",
            ]);
        }

        $checkoutUrl = $this->gateway->subscribe($office, $data['plan']);

        return response()->json(['data' => $this->plans->summary($office->fresh()), 'checkout_url' => $checkoutUrl]);
    }

    /** Public endpoint for the payment gateway (signature validated by the gateway implementation). */
    public function webhook(Request $request): JsonResponse
    {
        return $this->gateway->handleWebhook($request)
            ? response()->json(['ok' => true])
            : response()->json(['ok' => false], 400);
    }
}
