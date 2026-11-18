<?php

namespace App\Services\Billing;

use App\Models\Office;
use Illuminate\Http\Request;

/**
 * Payment gateway abstraction (Asaas or Stripe, to be decided).
 * Quantity = number of active CNPJs when the plan is metered.
 */
interface BillingGateway
{
    /** Creates (or returns) the customer id in the gateway. */
    public function ensureCustomer(Office $office): ?string;

    /** Starts or changes the subscription. Returns a checkout URL when the gateway needs one. */
    public function subscribe(Office $office, string $plan): ?string;

    public function cancel(Office $office): void;

    /** Reports the number of active CNPJs (for metered plans). */
    public function syncQuantity(Office $office, int $activeClients): void;

    /** Validates and applies a webhook; returns false when the signature is invalid. */
    public function handleWebhook(Request $request): bool;
}
