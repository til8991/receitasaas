<?php

namespace App\Jobs;

use App\Models\Comparison;
use App\Models\SimulationBatch;
use App\Services\Comparador\ComparadorService;
use App\Support\Tenant;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Runs one comparison (both scenarios) for one client. Used alone or inside a RunBatchJob. */
class RunSimulationJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 300;
    public int $tries = 2;
    public array $backoff = [30];

    public function __construct(public readonly int $comparisonId)
    {
        $this->onQueue('simulations');
    }

    public function handle(ComparadorService $comparador): void
    {
        $comparison = Comparison::withoutGlobalScopes()->find($this->comparisonId);
        if (! $comparison) {
            return;
        }

        Tenant::run($comparison->office_id, fn () => $comparador->run($comparison));

        $this->updateBatchProgress($comparison->fresh());
    }

    public function failed(?Throwable $e): void
    {
        $comparison = Comparison::withoutGlobalScopes()->find($this->comparisonId);
        if (! $comparison) {
            return;
        }
        $comparison->update(['status' => 'failed', 'error_message' => 'Falha ao executar a simulação. Tente novamente.']);
        $this->updateBatchProgress($comparison);
    }

    private function updateBatchProgress(Comparison $comparison): void
    {
        if (! $comparison->simulation_batch_id) {
            return;
        }

        $batch = SimulationBatch::withoutGlobalScopes()->find($comparison->simulation_batch_id);
        if (! $batch) {
            return;
        }

        $counts = Comparison::withoutGlobalScopes()
            ->where('simulation_batch_id', $batch->id)
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as done, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->first();

        $done = (int) $counts->done;
        $failed = (int) $counts->failed;
        $finished = $done + $failed >= $batch->total;

        $batch->update([
            'processed' => $done,
            'failed' => $failed,
            'status' => $finished ? 'completed' : 'running',
            'finished_at' => $finished ? now() : null,
        ]);
    }
}
