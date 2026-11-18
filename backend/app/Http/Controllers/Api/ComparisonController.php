<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunSimulationJob;
use App\Models\Client;
use App\Models\Comparison;
use App\Models\InvoiceItem;
use App\Models\Simulation;
use App\Services\Comparador\ComparadorService;
use App\Support\Disclaimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComparisonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['nullable', 'integer'],
            'recommendation' => ['nullable', Rule::in([Comparison::RECOMMEND_KEEP, Comparison::RECOMMEND_MIGRATE, Comparison::RECOMMEND_NEUTRAL])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $list = Comparison::with('client:id,legal_name,trade_name,cnpj')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('recommendation'), fn ($q) => $q->where('recommendation', $request->input('recommendation')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($list);
    }

    /** Starts a comparison for one client (queued). */
    public function store(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'reference_year' => ['required', 'integer', 'between:'.ComparadorService::MIN_YEAR.','.ComparadorService::MAX_YEAR],
            'source' => ['nullable', Rule::in(['auto', 'invoices', 'manual'])],
            'manual' => ['nullable', 'array'],
            'manual.revenue' => ['nullable', 'numeric', 'min:0'],
            'manual.b2b_sales_pct' => ['nullable', 'numeric', 'between:0,100'],
            'manual.purchases' => ['nullable', 'numeric', 'min:0'],
        ]);

        $comparison = Comparison::create([
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'reference_year' => $data['reference_year'],
            'source' => self::resolveSource($client, $data['source'] ?? 'auto'),
            'status' => 'pending',
            'input' => ['manual' => $data['manual'] ?? null],
        ]);

        RunSimulationJob::dispatch($comparison->id);

        return response()->json(['data' => $this->present($comparison->fresh())], 202);
    }

    public function show(Comparison $comparison): JsonResponse
    {
        return response()->json(['data' => $this->present($comparison->load(['client', 'simulations']), withDetails: true)]);
    }

    /** Raw audit data of one scenario: payloads sent, raw responses and calculator versions. */
    public function audit(Comparison $comparison, string $scenario): JsonResponse
    {
        abort_unless(in_array($scenario, Simulation::SCENARIOS, true), 404);
        $simulation = $comparison->simulations()->where('scenario', $scenario)->firstOrFail();

        return response()->json([
            'scenario' => $scenario,
            'calculated_at' => $simulation->calculated_at,
            'calculator_version' => $simulation->calculator_version,
            'request_payload' => $simulation->request_payload,
            'response_raw' => $simulation->response_raw,
        ]);
    }

    public function retry(Comparison $comparison): JsonResponse
    {
        abort_if($comparison->status === 'running', 409, 'A simulação já está em execução.');
        $comparison->update(['status' => 'pending', 'error_message' => null]);
        RunSimulationJob::dispatch($comparison->id);

        return response()->json(['data' => $this->present($comparison)], 202);
    }

    public function destroy(Comparison $comparison): JsonResponse
    {
        $comparison->delete();

        return response()->json(status: 204);
    }

    public static function resolveSource(Client $client, string $source): string
    {
        if ($source !== 'auto') {
            return $source;
        }

        return InvoiceItem::where('client_id', $client->id)->where('direction', InvoiceItem::DIRECTION_SALE)->exists()
            ? 'invoices'
            : 'manual';
    }

    private function present(Comparison $comparison, bool $withDetails = false): array
    {
        $data = $comparison->toArray();
        $data['has_report'] = $comparison->report_path !== null;
        $data['disclaimer'] = Disclaimer::TEXT;

        if ($withDetails) {
            $data['simulations'] = $comparison->simulations->mapWithKeys(fn (Simulation $s) => [$s->scenario => [
                'id' => $s->id,
                'status' => $s->status,
                'totals' => $s->totals,
                'item_errors' => $s->item_errors,
                'calculator_version' => $s->calculator_version,
                'input' => $s->input,
                'calculated_at' => $s->calculated_at,
            ]]);
        } else {
            unset($data['assumptions']);
        }

        return $data;
    }
}
