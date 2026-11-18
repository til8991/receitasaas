<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use App\Services\Billing\BillingGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function __construct(private readonly BillingGateway $billing) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $clients = Client::query()
            ->with('latestComparison')
            ->withCount('invoiceItems')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';
                $digits = preg_replace('/\D/', '', (string) $request->input('search'));
                $q->where(fn ($w) => $w->where('legal_name', 'like', $term)
                    ->orWhere('trade_name', 'like', $term)
                    ->when($digits !== '', fn ($w) => $w->orWhere('cnpj', 'like', "%{$digits}%")));
            })
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('legal_name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($clients);
    }

    public function store(ClientRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Lock the office row so concurrent requests cannot exceed the plan's CNPJ limit.
        $client = DB::transaction(function () use ($request, $data) {
            Office::whereKey($request->user()->office_id)->lockForUpdate()->first();
            Gate::authorize('create', [Client::class, $data['active'] ?? true]);

            return Client::create($data);
        });
        $this->syncBilling($request);

        return response()->json(['data' => $client], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->loadCount('invoiceItems')->load(['invoiceImports' => fn ($q) => $q->latest()->limit(10)]);

        return response()->json(['data' => $client]);
    }

    public function update(ClientRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $client, $data) {
            Office::whereKey($request->user()->office_id)->lockForUpdate()->first();
            Gate::authorize('update', [$client, (bool) ($data['active'] ?? false)]);
            $client->update($data);
        });
        $this->syncBilling($request);

        return response()->json(['data' => $client]);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        Gate::authorize('delete', $client);
        $client->delete();
        $this->syncBilling($request);

        return response()->json(status: 204);
    }

    private function syncBilling(Request $request): void
    {
        $office = $request->user()->office;
        $this->billing->syncQuantity($office, $office->activeClientsCount());
    }
}
