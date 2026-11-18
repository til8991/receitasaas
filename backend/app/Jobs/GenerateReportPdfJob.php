<?php

namespace App\Jobs;

use App\Models\Comparison;
use App\Services\Reports\ComparisonReportService;
use App\Support\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportPdfJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public readonly int $comparisonId)
    {
        $this->onQueue('reports');
    }

    public function handle(ComparisonReportService $reports): void
    {
        $comparison = Comparison::withoutGlobalScopes()->find($this->comparisonId);
        if (! $comparison || $comparison->status !== 'completed') {
            return;
        }

        Tenant::run($comparison->office_id, fn () => $reports->generate($comparison));
    }
}
