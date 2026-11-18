<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\InvoiceImport;
use App\Models\InvoiceItem;
use App\Services\Invoices\InvoiceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceImportController extends Controller
{
    public function __construct(private readonly InvoiceImportService $service) {}

    public function index(Client $client): JsonResponse
    {
        return response()->json([
            'data' => $client->invoiceImports()->latest()->limit(50)->get()->map(fn ($i) => $this->present($i)),
        ]);
    }

    /** Upload of XML files and/or ZIP files. Processing runs on the queue. */
    public function store(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:200'],
            'files.*' => ['file', 'max:51200', 'mimes:xml,zip', 'mimetypes:text/xml,application/xml,application/zip,application/x-zip-compressed,text/plain,application/octet-stream'],
        ], [
            'files.*.mimes' => 'Envie apenas arquivos .xml ou .zip.',
            'files.*.max' => 'Cada arquivo pode ter no máximo 50 MB.',
        ]);

        $import = $this->service->start($client, $request->file('files'), $request->user()->id);

        return response()->json(['data' => $this->present($import->fresh())], 202);
    }

    public function show(InvoiceImport $import): JsonResponse
    {
        return response()->json(['data' => $this->present($import)]);
    }

    public function items(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'direction' => ['nullable', 'in:saida,entrada'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $items = InvoiceItem::where('client_id', $client->id)
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->input('direction')))
            ->latest('issued_at')
            ->paginate($request->integer('per_page', 50));

        $summary = InvoiceItem::where('client_id', $client->id)
            ->selectRaw('direction, COUNT(DISTINCT document_key) as documents, COUNT(*) as items, SUM(base_amount) as total, MIN(issued_at) as first, MAX(issued_at) as last')
            ->groupBy('direction')->get()->keyBy('direction');

        return response()->json(['summary' => $summary, 'items' => $items]);
    }

    public function destroyItems(Client $client): JsonResponse
    {
        InvoiceItem::where('client_id', $client->id)->delete();

        return response()->json(status: 204);
    }

    private function present(InvoiceImport $import): array
    {
        return $import->toArray() + ['progress' => $import->progress()];
    }
}
