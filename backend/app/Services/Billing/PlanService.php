<?php

namespace App\Services\Billing;

use App\Models\Office;

class PlanService
{
    public function plan(Office $office): array
    {
        return config("plans.plans.{$office->plan}") ?? config('plans.plans.'.config('plans.default'));
    }

    public function maxActiveClients(Office $office): ?int
    {
        return $this->plan($office)['max_active_clients'];
    }

    public function canActivateAnotherClient(Office $office): bool
    {
        $max = $this->maxActiveClients($office);

        return $max === null || $office->activeClientsCount() < $max;
    }

    public function isActive(Office $office): bool
    {
        if (! in_array($office->plan_status, config('plans.active_statuses'), true)) {
            return false;
        }

        return $office->plan_status !== 'trialing' || $office->trial_ends_at === null || $office->trial_ends_at->isFuture();
    }

    public function summary(Office $office): array
    {
        $plan = $this->plan($office);

        return [
            'plan' => $office->plan,
            'name' => $plan['name'],
            'status' => $office->plan_status,
            'trial_ends_at' => $office->trial_ends_at?->toIso8601String(),
            'max_active_clients' => $plan['max_active_clients'],
            'active_clients' => $office->activeClientsCount(),
            'is_active' => $this->isActive($office),
            'available_plans' => collect(config('plans.plans'))->map(fn ($p, $key) => [
                'key' => $key,
                'name' => $p['name'],
                'max_active_clients' => $p['max_active_clients'],
                'price_cents' => $p['price_cents'],
            ])->values(),
        ];
    }
}
