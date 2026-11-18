<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportPdfJob;
use App\Models\Comparison;
use App\Models\SimulationBatch;
use App\Services\Reports\ComparisonReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ComparisonReportService $reports) {}

    /** Downloads the PDF; generates it on the fly when it does not exist yet. */
    public function download(Comparison $comparison): BinaryFileResponse|JsonResponse
    {
        if ($comparison->status !== 'completed') {
            return response()->json(['message' => 'A simulação ainda não foi concluída.'], 409);
        }

        $path = $this->reports->path($comparison);
        if (! $path) {
            $this->reports->generate($comparison);
            $path = $this->reports->path($comparison->fresh());
        }

        return response()->download($path, $this->reports->fileName($comparison->loadMissing('client')), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Queues the PDFs of every completed comparison of a batch. */
    public function generateBatch(SimulationBatch $batch): JsonResponse
    {
        $ids = $batch->comparisons()->where('status', 'completed')->pluck('id');
        $ids->each(fn ($id) => GenerateReportPdfJob::dispatch($id));

        return response()->json(['queued' => $ids->count()], 202);
    }
}
