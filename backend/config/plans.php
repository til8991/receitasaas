<?php

/*
| Billing per ACTIVE CNPJ (active clients). Limits are enforced by App\Services\Billing\PlanService
| (ClientPolicy + EnsureActiveSubscription middleware). The gateway (Asaas or Stripe) is pending:
| it sits behind App\Services\Billing\BillingGateway; "null" keeps billing disabled.
*/

return [
    'gateway' => env('BILLING_GATEWAY', 'null'),

    'trial_days' => (int) env('PLAN_TRIAL_DAYS', 14),

    'default' => 'starter',

    'plans' => [
        'starter' => [
            'name' => 'Essencial',
            'max_active_clients' => 10,
            'price_cents' => (int) env('PLAN_STARTER_PRICE_CENTS', 9900),
            'gateway_price_id' => env('PLAN_STARTER_GATEWAY_ID'),
        ],
        'pro' => [
            'name' => 'Profissional',
            'max_active_clients' => 50,
            'price_cents' => (int) env('PLAN_PRO_PRICE_CENTS', 29900),
            'gateway_price_id' => env('PLAN_PRO_GATEWAY_ID'),
        ],
        'unlimited' => [
            'name' => 'Ilimitado',
            'max_active_clients' => null,
            'price_cents' => (int) env('PLAN_UNLIMITED_PRICE_CENTS', 59900),
            'gateway_price_id' => env('PLAN_UNLIMITED_GATEWAY_ID'),
        ],
    ],

    // Statuses that allow creating/running simulations.
    'active_statuses' => ['trialing', 'active'],
];
