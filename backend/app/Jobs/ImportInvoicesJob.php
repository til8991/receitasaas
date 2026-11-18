<?php

namespace App\Jobs;

use App\Models\InvoiceImport;
use App\Services\Invoices\InvoiceImportService;
use App\Support\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('imports');
    }

    public function handle(InvoiceImportService $service): void
    {
        $import = InvoiceImport::withoutGlobalScopes()->find($this->importId);
        if (! $import) {
            return;
        }

        Tenant::run($import->office_id, fn () => $service->process($import));
    }

    public function failed(?Throwable $e): void
    {
        InvoiceImport::withoutGlobalScopes()->whereKey($this->importId)->update([
            'status' => 'failed',
            'errors' => json_encode([['arquivo' => '-', 'erro' => $e?->getMessage() ?? 'Falha desconhecida']]),
            'finished_at' => now(),
        ]);
    }
}
