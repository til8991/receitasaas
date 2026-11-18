<?php

namespace App\Services\Invoices;

use App\Jobs\ImportInvoicesJob;
use App\Models\Client;
use App\Models\InvoiceImport;
use App\Models\InvoiceItem;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class InvoiceImportService
{
    public const MAX_ZIP_ENTRIES = 20000;
    public const MAX_XML_BYTES = 5 * 1024 * 1024;
    private const DISK = 'local';

    public function __construct(private readonly InvoiceXmlParser $parser) {}

    /** @param list<UploadedFile> $files */
    public function start(Client $client, array $files, ?int $userId): InvoiceImport
    {
        $import = InvoiceImport::create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'user_id' => $userId,
            'status' => 'pending',
            'files' => [],
        ]);

        $stored = [];
        foreach ($files as $file) {
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->storeAs("imports/{$import->id}", uniqid().'_'.$name, self::DISK),
            ];
        }
        $import->update(['files' => $stored]);

        ImportInvoicesJob::dispatch($import->id);

        return $import;
    }

    public function process(InvoiceImport $import): void
    {
        $client = Client::withoutGlobalScopes()->findOrFail($import->client_id);
        $errors = [];

        $total = 0;
        foreach ($import->files as $file) {
            $total += $this->countXml($file['path'], $file['name']);
        }
        $import->update(['status' => 'processing', 'started_at' => now(), 'total_files' => $total]);

        $processed = $failed = $duplicated = $items = 0;

        foreach ($import->files as $file) {
            foreach ($this->xmlEntries($file['path'], $file['name']) as [$entryName, $content, $error]) {
                $processed++;
                if ($error !== null) {
                    $failed++;
                    $errors[] = ['arquivo' => $entryName, 'erro' => $error];
                } else {
                    try {
                        $doc = $this->parser->parse($content);
                        $result = $this->store($import, $client, $doc);
                        $items += $result['items'];
                        $duplicated += $result['duplicated'] ? 1 : 0;
                    } catch (InvoiceParseException $e) {
                        $failed++;
                        $errors[] = ['arquivo' => $entryName, 'erro' => $e->getMessage()];
                    } catch (Throwable $e) {
                        $failed++;
                        $errors[] = ['arquivo' => $entryName, 'erro' => 'Falha ao processar: '.$e->getMessage()];
                        report($e);
                    }
                }

                if ($processed % 25 === 0 || $processed === $total) {
                    $import->update([
                        'processed_files' => $processed, 'failed_files' => $failed,
                        'duplicated_documents' => $duplicated, 'imported_items' => $items,
                        'errors' => array_slice($errors, 0, 500),
                    ]);
                }
            }
        }

        $import->update([
            'status' => 'completed',
            'processed_files' => $processed,
            'failed_files' => $failed,
            'duplicated_documents' => $duplicated,
            'imported_items' => $items,
            'errors' => array_slice($errors, 0, 500),
            'finished_at' => now(),
        ]);

        foreach ($import->files as $file) {
            Storage::disk(self::DISK)->delete($file['path']);
        }
    }

    /** @return array{items: int, duplicated: bool} */
    private function store(InvoiceImport $import, Client $client, ParsedDocument $doc): array
    {
        $direction = match (true) {
            $doc->issuerDocument === $client->cnpj => InvoiceItem::DIRECTION_SALE,
            $doc->recipientDocument === $client->cnpj => InvoiceItem::DIRECTION_PURCHASE,
            default => throw new InvoiceParseException(
                "Documento {$doc->number} não pertence ao cliente (emitente {$doc->issuerDocument}, destinatário {$doc->recipientDocument})."
            ),
        };

        $exists = InvoiceItem::withoutGlobalScopes()
            ->where('client_id', $client->id)->where('document_key', $doc->key)->exists();
        if ($exists) {
            return ['items' => 0, 'duplicated' => true];
        }

        $now = now();
        $rows = array_map(fn (array $item) => $item + [
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'invoice_import_id' => $import->id,
            'document_type' => $doc->type,
            'document_key' => $doc->key,
            'document_number' => $doc->number ? mb_substr($doc->number, 0, 20) : null,
            'issued_at' => $doc->issuedAt->format('Y-m-d H:i:s'),
            'direction' => $direction,
            'issuer_document' => $doc->issuerDocument,
            'recipient_document' => $doc->recipientDocument,
            'recipient_type' => $doc->recipientType,
            'recipient_uf' => $doc->recipientUf,
            'created_at' => $now,
            'updated_at' => $now,
        ], $doc->items);

        DB::transaction(fn () => collect($rows)->chunk(200)->each(fn ($chunk) => InvoiceItem::insert($chunk->all())));

        return ['items' => count($rows), 'duplicated' => false];
    }

    private function countXml(string $path, string $name): int
    {
        if (! $this->isZip($path, $name)) {
            return 1;
        }
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk(self::DISK)->path($path)) !== true) {
            return 1;
        }
        $count = 0;
        for ($i = 0; $i < min($zip->numFiles, self::MAX_ZIP_ENTRIES); $i++) {
            if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.xml')) {
                $count++;
            }
        }
        $zip->close();

        return max($count, 1);
    }

    /** @return Generator<array{0: string, 1: ?string, 2: ?string}> [name, content, error] */
    private function xmlEntries(string $path, string $name): Generator
    {
        $absolute = Storage::disk(self::DISK)->path($path);

        if (! $this->isZip($path, $name)) {
            if (filesize($absolute) > self::MAX_XML_BYTES) {
                yield [$name, null, 'Arquivo XML maior que 5 MB.'];

                return;
            }
            yield [$name, file_get_contents($absolute), null];

            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($absolute) !== true) {
            yield [$name, null, 'Arquivo ZIP inválido ou corrompido.'];

            return;
        }

        $found = false;
        for ($i = 0; $i < min($zip->numFiles, self::MAX_ZIP_ENTRIES); $i++) {
            $stat = $zip->statIndex($i);
            $entry = (string) $stat['name'];
            if (! str_ends_with(strtolower($entry), '.xml') || str_contains($entry, '__MACOSX')) {
                continue;
            }
            $found = true;
            $label = $name.' › '.$entry;
            if ($stat['size'] > self::MAX_XML_BYTES) {
                yield [$label, null, 'Arquivo XML maior que 5 MB.'];

                continue;
            }
            $content = $zip->getFromIndex($i, self::MAX_XML_BYTES + 1);
            yield $content === false ? [$label, null, 'Não foi possível ler o arquivo do ZIP.'] : [$label, $content, null];
        }
        $zip->close();

        if (! $found) {
            yield [$name, null, 'O ZIP não contém arquivos .xml.'];
        }
    }

    private function isZip(string $path, string $name): bool
    {
        if (str_ends_with(strtolower($name), '.zip')) {
            return true;
        }
        $handle = fopen(Storage::disk(self::DISK)->path($path), 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        return $magic === "PK\x03\x04";
    }
}
