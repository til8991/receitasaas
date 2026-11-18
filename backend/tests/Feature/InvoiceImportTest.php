<?php

use App\Models\Client;
use App\Models\InvoiceImport;
use App\Models\InvoiceItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = actingAsOfficeUser();
    $this->client = Client::factory()->create(['office_id' => $this->user->office_id, 'cnpj' => '11222333000181']);
});

function xmlUpload(string $file): UploadedFile
{
    return UploadedFile::fake()->createWithContent($file, file_get_contents(__DIR__.'/../Fixtures/xml/'.$file));
}

it('imports XML files and a ZIP, classifies sales/purchases and B2B/B2C, and skips duplicates', function () {
    $zipPath = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    foreach (['nfse-nacional.xml', 'nfse-abrasf.xml', 'nfe-compra.xml', 'xxe.xml'] as $f) {
        $zip->addFile(__DIR__.'/../Fixtures/xml/'.$f, "notas/{$f}");
    }
    $zip->addFromString('leia-me.txt', 'ignorar');
    $zip->close();

    $response = $this->post("/api/clients/{$this->client->id}/imports", [
        'files' => [
            xmlUpload('nfe-venda-pj.xml'),
            xmlUpload('nfce-venda-pf.xml'),
            UploadedFile::fake()->createWithContent('lote.zip', file_get_contents($zipPath)),
        ],
    ], ['Accept' => 'application/json']);

    $response->assertAccepted();
    $import = InvoiceImport::first();

    expect($import->status)->toBe('completed')
        ->and($import->total_files)->toBe(6)
        ->and($import->processed_files)->toBe(6)
        ->and($import->failed_files)->toBe(1)                 // xxe.xml
        ->and($import->imported_items)->toBe(6)
        ->and($import->errors[0]['erro'])->toContain('DOCTYPE');

    expect(InvoiceItem::where('direction', 'saida')->count())->toBe(5)
        ->and(InvoiceItem::where('direction', 'entrada')->count())->toBe(1)
        ->and(InvoiceItem::where('recipient_type', 'pj')->where('direction', 'saida')->count())->toBe(3)
        ->and(InvoiceItem::where('nbs', '115011000')->exists())->toBeTrue();

    // Same NF-e again -> duplicated, nothing inserted.
    $this->post("/api/clients/{$this->client->id}/imports", ['files' => [xmlUpload('nfe-venda-pj.xml')]], ['Accept' => 'application/json'])
        ->assertAccepted();
    $second = InvoiceImport::latest('id')->first();
    expect($second->duplicated_documents)->toBe(1)->and($second->imported_items)->toBe(0);

    $this->getJson("/api/clients/{$this->client->id}/invoice-items")
        ->assertOk()->assertJsonPath('summary.saida.documents', 4);
});

it('rejects documents that do not belong to the client', function () {
    $this->client->update(['cnpj' => '06990590000123']); // neither issuer nor recipient of the NF-e

    $this->post("/api/clients/{$this->client->id}/imports", ['files' => [xmlUpload('nfe-venda-pj.xml')]], ['Accept' => 'application/json'])
        ->assertAccepted();

    $import = InvoiceImport::first();
    expect($import->failed_files)->toBe(1)->and($import->errors[0]['erro'])->toContain('não pertence ao cliente');
});

it('validates file types', function () {
    $this->post("/api/clients/{$this->client->id}/imports", [
        'files' => [UploadedFile::fake()->create('foto.png', 10, 'image/png')],
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

it('does not allow importing into another office client', function () {
    $foreign = Client::factory()->create();

    $this->post("/api/clients/{$foreign->id}/imports", ['files' => [xmlUpload('nfe-venda-pj.xml')]], ['Accept' => 'application/json'])
        ->assertNotFound();
});
