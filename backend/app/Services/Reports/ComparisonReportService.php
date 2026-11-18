<?php

namespace App\Services\Reports;

use App\Models\Comparison;
use App\Models\Office;
use App\Support\Disclaimer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** PDF report of one comparison with the office branding. */
class ComparisonReportService
{
    private const DISK = 'local';

    public function generate(Comparison $comparison): string
    {
        if ($comparison->status !== 'completed') {
            throw new RuntimeException('A simulação ainda não foi concluída.');
        }

        $comparison->loadMissing(['client', 'simulations']);
        $office = Office::findOrFail($comparison->office_id);

        $pdf = Pdf::loadView('reports.comparison', [
            'comparison' => $comparison,
            'client' => $comparison->client,
            'office' => $office,
            'logo' => $this->logoDataUri($office),
            'result' => $comparison->result,
            'assumptions' => $comparison->assumptions['lista'] ?? [],
            'versions' => $comparison->simulations->mapWithKeys(fn ($s) => [$s->scenario => $s->calculator_version]),
            'itemErrors' => $comparison->simulations->flatMap(fn ($s) => collect($s->item_errors ?? [])->map(fn ($e) => $e + ['cenario' => $s->scenario])),
            'disclaimer' => Disclaimer::TEXT,
        ])->setPaper('a4')->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);

        $path = "reports/{$office->id}/comparison-{$comparison->id}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf->output());

        $comparison->update(['report_path' => $path, 'report_generated_at' => now()]);

        return $path;
    }

    public function path(Comparison $comparison): ?string
    {
        return $comparison->report_path && Storage::disk(self::DISK)->exists($comparison->report_path)
            ? Storage::disk(self::DISK)->path($comparison->report_path)
            : null;
    }

    public function fileName(Comparison $comparison): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $comparison->client->legal_name ?? 'cliente'));

        return strtolower(trim($name, '-'))."-reforma-{$comparison->reference_year}.pdf";
    }

    private function logoDataUri(Office $office): ?string
    {
        if (! $office->logo_path || ! Storage::disk('public')->exists($office->logo_path)) {
            return null;
        }
        $mime = Storage::disk('public')->mimeType($office->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($office->logo_path));
    }
}
