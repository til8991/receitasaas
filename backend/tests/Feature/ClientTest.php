<?php

use App\Models\Client;
use App\Models\Office;

function clientPayload(array $overrides = []): array
{
    return array_merge([
        'cnpj' => '11.222.333/0001-81',
        'legal_name' => 'Mercado Bom Preço LTDA',
        'uf' => 'rs',
        'municipality_ibge' => 4314902,
        'current_regime' => 'simples_nacional',
        'simples_annex' => 'I',
        'activities' => ['01'],
        'revenue_12m' => 1500000,
        'b2b_sales_pct' => 40,
        'default_ncm' => '2203.00.00',
    ], $overrides);
}

it('creates, lists, updates and deletes clients of the office', function () {
    actingAsOfficeUser();

    $id = $this->postJson('/api/clients', clientPayload())
        ->assertCreated()
        ->assertJsonPath('data.cnpj', '11222333000181')
        ->assertJsonPath('data.uf', 'RS')
        ->assertJsonPath('data.default_ncm', '22030000')
        ->json('data.id');

    $this->getJson('/api/clients?search=bom')->assertOk()->assertJsonPath('total', 1);
    $this->getJson('/api/clients?search=11222333')->assertOk()->assertJsonPath('total', 1);

    $this->putJson("/api/clients/{$id}", ['b2b_sales_pct' => 80])
        ->assertOk()->assertJsonPath('data.b2b_sales_pct', '80.00');

    $this->deleteJson("/api/clients/{$id}")->assertNoContent();
    expect(Client::count())->toBe(0);
});

it('validates client fields', function () {
    actingAsOfficeUser();

    $this->postJson('/api/clients', clientPayload([
        'cnpj' => '123', 'uf' => 'XX', 'municipality_ibge' => 12, 'b2b_sales_pct' => 120, 'simples_annex' => 'VI',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['cnpj', 'uf', 'municipality_ibge', 'b2b_sales_pct', 'simples_annex']);
});

it('rejects a duplicated CNPJ in the same office but allows it in another office', function () {
    $other = Office::factory()->create();
    Client::factory()->create(['office_id' => $other->id, 'cnpj' => '11222333000181']);
    actingAsOfficeUser();

    $this->postJson('/api/clients', clientPayload())->assertCreated();
    $this->postJson('/api/clients', clientPayload())->assertUnprocessable()->assertJsonValidationErrors('cnpj');
});

it('isolates clients between offices (tenant scope)', function () {
    $foreign = Client::factory()->create();
    actingAsOfficeUser();
    Client::factory()->count(2)->create(['office_id' => auth()->user()->office_id]);

    $this->getJson('/api/clients')->assertOk()->assertJsonPath('total', 2);
    $this->getJson("/api/clients/{$foreign->id}")->assertNotFound();
    $this->putJson("/api/clients/{$foreign->id}", ['legal_name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/clients/{$foreign->id}")->assertNotFound();
});

it('enforces the active CNPJ limit of the plan', function () {
    $user = actingAsOfficeUser(['plan' => 'starter']);
    Client::factory()->count(10)->create(['office_id' => $user->office_id]);

    $this->postJson('/api/clients', clientPayload())
        ->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'até 10 CNPJs'));

    // Inactive clients do not count.
    $this->postJson('/api/clients', clientPayload(['active' => false]))->assertCreated();
    $inactive = Client::where('active', false)->first();
    $this->putJson("/api/clients/{$inactive->id}", ['active' => true])->assertForbidden();
});

it('validates per-client assumption overrides', function () {
    actingAsOfficeUser();

    $this->postJson('/api/clients', clientPayload(['assumptions' => ['credit_pass_through_pct' => 150]]))
        ->assertUnprocessable()->assertJsonValidationErrors('assumptions.credit_pass_through_pct');

    $this->postJson('/api/clients', clientPayload(['assumptions' => ['credit_pass_through_pct' => 30, 'unknown' => 1]]))
        ->assertCreated()->assertJsonPath('data.assumptions', ['credit_pass_through_pct' => 30]);
});
