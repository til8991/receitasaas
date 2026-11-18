<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Comparison;
use App\Services\Billing\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Latest completed comparison per client.
        $latestIds = Comparison::query()
            ->where('status', 'completed')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('client_id')
            ->pluck('id');

        $latest = Comparison::whereIn('id', $latestIds)
            ->with('client:id,legal_name,trade_name,cnpj')
            ->get(['id', 'client_id', 'reference_year', 'recommendation', 'result', 'calculated_at']);

        $byRecommendation = $latest->countBy('recommendation');
        $migrate = $latest->where('recommendation', Comparison::RECOMMEND_MIGRATE);

        $savings = $migrate->sum(fn ($c) => abs((float) ($c->result['diferenca']['valor'] ?? 0)));

        $recent = Comparison::with('client:id,legal_name,trade_name,cnpj')
            ->latest()->limit(10)
            ->get(['id', 'client_id', 'reference_year', 'status', 'recommendation', 'result', 'created_at', 'error_message'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'client' => $c->client,
                'reference_year' => $c->reference_year,
                'status' => $c->status,
                'recommendation' => $c->recommendation,
                'difference' => $c->result['diferenca']['valor'] ?? null,
                'created_at' => $c->created_at,
            ]);

        return response()->json([
            'clients' => [
                'total' => Client::count(),
                'active' => Client::where('active', true)->count(),
                'simulated' => $latest->count(),
            ],
            'recommendations' => [
                'migrar_regular' => $byRecommendation[Comparison::RECOMMEND_MIGRATE] ?? 0,
                'manter_simples' => $byRecommendation[Comparison::RECOMMEND_KEEP] ?? 0,
                'indiferente' => $byRecommendation[Comparison::RECOMMEND_NEUTRAL] ?? 0,
            ],
            'potential_savings' => number_format($savings, 2, '.', ''),
            'top_migrate' => $migrate->sortBy(fn ($c) => (float) ($c->result['diferenca']['valor'] ?? 0))->take(5)->values()->map(fn ($c) => [
                'comparison_id' => $c->id,
                'client' => $c->client,
                'reference_year' => $c->reference_year,
                'difference' => $c->result['diferenca']['valor'] ?? null,
                'difference_pct' => $c->result['diferenca']['percentual_receita'] ?? null,
            ]),
            'recent' => $recent,
            'comparisons_total' => Comparison::count(),
            'plan' => $this->plans->summary($request->user()->office),
        ]);
    }
}
