<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunBatchJob;
use App\Models\Client;
use App\Models\SimulationBatch;
use App\Services\Comparador\ComparadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SimulationBatch::latest()->paginate(20)->through(fn ($b) => $this->present($b)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference_year' => ['required', 'integer', 'between:'.ComparadorService::MIN_YEAR.','.ComparadorService::MAX_YEAR],
            'source' => ['nullable', Rule::in(['auto', 'invoices', 'manual'])],
            'client_ids' => ['nullable', 'array', 'max:1000'],
            'client_ids.*' => ['integer'],
            'all_active' => ['nullable', 'boolean'],
        ]);

        $query = Client::query()->where('active', true);
        if (! ($data['all_active'] ?? false)) {
            $query->whereIn('id', $data['client_ids'] ?? []);
        }
        $ids = $query->pluck('id')->all();

        if ($ids === []) {
            throw ValidationException::withMessages(['client_ids' => 'Selecione ao menos um cliente ativo.']);
        }

        $batch = SimulationBatch::create([
            'user_id' => $request->user()->id,
            'reference_year' => $data['reference_year'],
            'source' => $data['source'] ?? 'auto',
            'status' => 'pending',
            'client_ids' => $ids,
            'total' => count($ids),
        ]);

        RunBatchJob::dispatch($batch->id);

        return response()->json(['data' => $this->present($batch->fresh())], 202);
    }

    public function show(SimulationBatch $batch): JsonResponse
    {
        $comparisons = $batch->comparisons()->with('client:id,legal_name,trade_name,cnpj')
            ->orderBy('id')->get(['id', 'client_id', 'status', 'recommendation', 'result', 'error_message', 'report_path', 'simulation_batch_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'client' => $c->client,
                'status' => $c->status,
                'recommendation' => $c->recommendation,
                'difference' => $c->result['diferenca']['valor'] ?? null,
                'difference_pct' => $c->result['diferenca']['percentual_receita'] ?? null,
                'error_message' => $c->error_message,
                'has_report' => $c->report_path !== null,
            ]);

        return response()->json(['data' => $this->present($batch) + ['comparisons' => $comparisons]]);
    }

    private function present(SimulationBatch $batch): array
    {
        return $batch->toArray() + ['progress' => $batch->progress()];
    }
}
