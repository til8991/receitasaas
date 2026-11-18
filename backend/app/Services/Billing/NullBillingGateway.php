<?php

namespace App\Services\Billing;

use App\Models\Office;
use Illuminate\Http\Request;

/** Billing disabled: plan changes are applied locally, nothing is charged. */
class NullBillingGateway implements BillingGateway
{
    public function ensureCustomer(Office $office): ?string
    {
        return null;
    }

    public function subscribe(Office $office, string $plan): ?string
    {
        $office->update(['plan' => $plan, 'plan_status' => 'active']);

        return null;
    }

    public function cancel(Office $office): void
    {
        $office->update(['plan_status' => 'canceled']);
    }

    public function syncQuantity(Office $office, int $activeClients): void {}

    public function handleWebhook(Request $request): bool
    {
        return false;
    }
}
