<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Services\Billing\PlanService;
use Illuminate\Auth\Access\Response;

class ClientPolicy
{
    public function __construct(private readonly PlanService $plans) {}

    public function view(User $user, Client $client): bool
    {
        return $user->office_id === $client->office_id;
    }

    /** Creating an active client counts against the plan's CNPJ limit. */
    public function create(User $user, bool $active = true): Response
    {
        if ($active && ! $this->plans->canActivateAnotherClient($user->office)) {
            return Response::deny($this->limitMessage($user));
        }

        return Response::allow();
    }

    public function update(User $user, Client $client, bool $activating = false): Response
    {
        if ($user->office_id !== $client->office_id) {
            return Response::denyAsNotFound();
        }
        if ($activating && ! $client->active && ! $this->plans->canActivateAnotherClient($user->office)) {
            return Response::deny($this->limitMessage($user));
        }

        return Response::allow();
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->office_id === $client->office_id;
    }

    private function limitMessage(User $user): string
    {
        $max = $this->plans->maxActiveClients($user->office);

        return "Seu plano permite até {$max} CNPJs ativos. Desative um cliente ou faça upgrade do plano.";
    }
}
