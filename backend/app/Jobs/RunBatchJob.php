<?php

namespace App\Jobs;

use App\Http\Controllers\Api\ComparisonController;
use App\Models\Client;
use App\Models\Comparison;
use App\Models\SimulationBatch;
use App\Support\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Fans out one RunSimulationJob per client; progress is updated by each RunSimulationJob. */
class RunBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly int $batchId)
    {
        $this->onQueue('simulations');
    }

    public function handle(): void
    {
        $batch = SimulationBatch::withoutGlobalScopes()->find($this->batchId);
        if (! $batch) {
            return;
        }

        Tenant::run($batch->office_id, function () use ($batch) {
            $batch->update(['status' => 'running', 'started_at' => now()]);

            $clients = Client::whereIn('id', $batch->client_ids)->get();
            $batch->update(['total' => $clients->count()]);

            if ($clients->isEmpty()) {
                $batch->update(['status' => 'completed', 'finished_at' => now()]);

                return;
            }

            foreach ($clients as $client) {
                $comparison = Comparison::create([
                    'office_id' => $batch->office_id,
                    'client_id' => $client->id,
                    'user_id' => $batch->user_id,
                    'simulation_batch_id' => $batch->id,
                    'reference_year' => $batch->reference_year,
                    'source' => ComparisonController::resolveSource($client, $batch->source),
                    'status' => 'pending',
                    'input' => ['manual' => null],
                ]);
                RunSimulationJob::dispatch($comparison->id);
            }
        });
    }
}
