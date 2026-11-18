<?php

use App\Models\Client;
use App\Models\Comparison;
use App\Models\SimulationBatch;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeCalculadoras;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    FakeCalculadoras::install();
    $this->user = actingAsOfficeUser(['plan' => 'pro']);
    $this->clients = Client::factory()->count(3)->sequence(
        ['b2b_sales_pct' => 95, 'purchases_12m' => 600000, 'assumptions' => ['credit_pass_through_pct' => 100, 'regular_suppliers_pct' => 100]],
        ['b2b_sales_pct' => 0],
        ['simples_annex' => null], // fails: no annex
    )->create(['office_id' => $this->user->office_id]);
});

it('runs a batch for selected clients and tracks progress', function () {
    $response = $this->postJson('/api/batches', [
        'reference_year' => 2027,
        'client_ids' => $this->clients->pluck('id')->all(),
    ])->assertAccepted();

    $batch = SimulationBatch::findOrFail($response->json('data.id'));
    expect($batch->status)->toBe('completed')
        ->and($batch->total)->toBe(3)
        ->and($batch->processed)->toBe(2)
        ->and($batch->failed)->toBe(1)
        ->and($batch->progress())->toBe(100);

    $show = $this->getJson("/api/batches/{$batch->id}")->assertOk();
    expect(collect($show->json('data.comparisons'))->pluck('recommendation')->filter()->values()->all())
        ->toBe(['migrar_regular', 'manter_simples']);
});

it('runs a batch for all active clients and ignores clients of other offices', function () {
    Client::factory()->create(); // other office
    $this->clients[1]->update(['active' => false]);

    $this->postJson('/api/batches', ['reference_year' => 2027, 'all_active' => true])
        ->assertAccepted()->assertJsonPath('data.total', 2);

    $this->postJson('/api/batches', ['reference_year' => 2027, 'client_ids' => [999999]])
        ->assertUnprocessable();
});

it('generates and downloads the PDF report with the office branding', function () {
    $this->user->office->update(['name' => 'Contábil Alfa', 'primary_color' => '#123456']);
    $id = $this->postJson("/api/clients/{$this->clients[0]->id}/comparisons", ['reference_year' => 2027, 'source' => 'manual'])->json('data.id');

    $response = $this->get("/api/comparisons/{$id}/report");
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getFile()->getSize())->toBeGreaterThan(5000);

    $comparison = Comparison::find($id);
    expect($comparison->report_path)->not->toBeNull();
    Storage::disk('local')->assertExists($comparison->report_path);

    // HTML of the report contains disclaimer, branding and assumptions
    $html = view('reports.comparison', [
        'comparison' => $comparison, 'client' => $comparison->client, 'office' => $this->user->office->fresh(), 'logo' => null,
        'result' => $comparison->result, 'assumptions' => $comparison->assumptions['lista'],
        'versions' => $comparison->simulations->mapWithKeys(fn ($s) => [$s->scenario => $s->calculator_version]),
        'itemErrors' => collect(), 'disclaimer' => App\Support\Disclaimer::TEXT,
    ])->render();
    expect($html)->toContain('Não substitui análise profissional')
        ->toContain('Contábil Alfa')
        ->toContain('#123456')
        ->toContain('P-04')
        ->toContain('Optar pelo IBS/CBS no regime regular');
});

it('returns 409 for the report of an unfinished comparison', function () {
    $c = Comparison::create(['office_id' => $this->user->office_id, 'client_id' => $this->clients[0]->id, 'reference_year' => 2027, 'source' => 'manual', 'status' => 'pending']);

    $this->getJson("/api/comparisons/{$c->id}/report")->assertStatus(409);
});

it('summarizes the dashboard with the latest comparison per client', function () {
    $this->postJson('/api/batches', ['reference_year' => 2027, 'client_ids' => $this->clients->pluck('id')->all()]);
    // A second, newer comparison for client 0 also recommending migration must not double count.
    $this->postJson("/api/clients/{$this->clients[0]->id}/comparisons", ['reference_year' => 2028, 'source' => 'manual']);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('clients.total', 3)
        ->assertJsonPath('clients.simulated', 2)
        ->assertJsonPath('recommendations.migrar_regular', 1)
        ->assertJsonPath('recommendations.manter_simples', 1)
        ->assertJsonPath('plan.max_active_clients', 50)
        ->assertJsonCount(1, 'top_migrate')
        ->assertJsonCount(4, 'recent');
});

it('changes plan through the billing gateway and blocks downgrades below the active CNPJs', function () {
    Client::factory()->count(8)->create(['office_id' => $this->user->office_id]); // 11 active

    $this->postJson('/api/billing/subscribe', ['plan' => 'starter'])
        ->assertUnprocessable()->assertJsonValidationErrors('plan');

    $this->postJson('/api/billing/subscribe', ['plan' => 'unlimited'])
        ->assertOk()->assertJsonPath('data.plan', 'unlimited')->assertJsonPath('data.max_active_clients', null);

    $this->getJson('/api/billing')->assertOk()->assertJsonPath('data.active_clients', 11);
});

it('expires the trial', function () {
    $this->user->office->update(['plan_status' => 'trialing', 'trial_ends_at' => now()->subDay()]);

    $this->postJson('/api/batches', ['reference_year' => 2027, 'all_active' => true])->assertStatus(402);
    $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('plan.is_active', false);
});
