<?php

use App\Models\Client;
use App\Models\Comparison;
use App\Models\InvoiceImport;
use App\Models\InvoiceItem;
use App\Models\Simulation;
use Tests\Support\FakeCalculadoras;

beforeEach(function () {
    FakeCalculadoras::install();
    $this->user = actingAsOfficeUser();
    $this->client = Client::factory()->create([
        'office_id' => $this->user->office_id,
        'cnpj' => '11222333000181',
        'simples_annex' => 'I',
        'activities' => ['01'],
        'revenue_12m' => 1200000,     // Annex I bracket 4 -> effective 8,825%; CBS share 15,5% -> pCBSSN 1,3679
        'purchases_12m' => 400000,
        'b2b_sales_pct' => 80,
        'default_ncm' => '22030000',
    ]);
});

it('runs a manual comparison and recommends migrating when most sales are B2B', function () {
    $response = $this->postJson("/api/clients/{$this->client->id}/comparisons", [
        'reference_year' => 2027,
        'source' => 'manual',
        'manual' => ['revenue' => 100000, 'b2b_sales_pct' => 80, 'purchases' => 40000],
    ])->assertAccepted();

    $comparison = Comparison::findOrFail($response->json('data.id'));
    $r = $comparison->result;

    expect($comparison->status)->toBe('completed')
        ->and($r['receita_bruta'])->toBe('100000.00')
        ->and($r['receita_b2b'])->toBe('80000.00');

    // Scenario A: pCBSSN = 8,825 × 15,5% = 1,3679 p.p.; IBS 0 in 2027
    $a = $r['cenarios']['simples_integral'];
    expect($a['cbs'])->toBe('1367.90')            // 1094,32 (B2B) + 273,58 (B2C)
        ->and($a['ibs'])->toBe('0.00')
        ->and($a['credito_clientes_b2b'])->toBe('1094.32');

    // Scenario B: CBS 8,8% + IBS 0,1% on 100.000 = 8.900; purchases 40.000 × 8,9% = 3.560 × 70% = 2.492
    $b = $r['cenarios']['ibs_cbs_regular'];
    expect($b['total_ibs_cbs'])->toBe('8900.00')
        ->and($b['credito_compras_bruto'])->toBe('3560.00')
        ->and($b['credito_compras_aproveitado'])->toBe('2492.00')
        ->and($b['custo_liquido'])->toBe('6408.00')
        ->and($b['credito_clientes_b2b'])->toBe('7120.00');

    // Extra credit 7120 − 1094,32 = 6025,68 × 50% = 3012,84 -> effective 3395,16 vs 1367,90 -> keep Simples
    expect($r['diferenca']['credito_adicional_clientes'])->toBe('6025.68')
        ->and($b['beneficio_comercial'])->toBe('3012.84')
        ->and($b['custo_efetivo'])->toBe('3395.16')
        ->and($r['diferenca']['valor'])->toBe('2027.26')
        ->and($comparison->recommendation)->toBe(Comparison::RECOMMEND_KEEP)
        ->and($r['justificativa'])->toHaveCount(5);

    // With 100% pass-through and all suppliers in the regular regime, migrating wins.
    $this->client->update(['assumptions' => ['credit_pass_through_pct' => 100, 'regular_suppliers_pct' => 100]]);
    $id = $this->postJson("/api/clients/{$this->client->id}/comparisons", [
        'reference_year' => 2027, 'source' => 'manual',
        'manual' => ['revenue' => 100000, 'b2b_sales_pct' => 80, 'purchases' => 40000],
    ])->json('data.id');
    $second = Comparison::find($id);
    // 8900 − 3560 − 6025,68 = −685,68 vs 1367,90 -> diff −2053,58 (−2,05% of revenue)
    expect($second->result['cenarios']['ibs_cbs_regular']['custo_efetivo'])->toBe('-685.68')
        ->and($second->recommendation)->toBe(Comparison::RECOMMEND_MIGRATE);
});

it('stores the audit trail: payloads sent, raw responses and calculator versions', function () {
    $id = $this->postJson("/api/clients/{$this->client->id}/comparisons", [
        'reference_year' => 2027, 'source' => 'manual', 'manual' => ['revenue' => 50000],
    ])->json('data.id');

    $simples = Simulation::where('comparison_id', $id)->where('scenario', 'simples_integral')->first();
    $regular = Simulation::where('comparison_id', $id)->where('scenario', 'ibs_cbs_regular')->first();

    expect($simples->request_payload['simples_nacional']['CRT'])->toBe('1')
        ->and($simples->request_payload['simples_nacional']['itens'][0]['cAtivSN'])->toBe('01')
        ->and($simples->request_payload['simples_nacional']['itens'][0]['pCBSSN'])->toBe('1.3679')
        ->and($simples->response_raw['simples_nacional']['total'])->toHaveKey('totalTributos')
        ->and($simples->calculator_version['simples_nacional']['versaoApp'])->toBe('0.0.6')
        ->and($regular->request_payload['regime_geral_vendas']['municipio'])->toBe(4314902)
        ->and($regular->request_payload['regime_geral_vendas']['itens'][0]['aliquotasNominais'])->toBe(['cbs' => 8.8, 'ibsEstadual' => 0.05, 'ibsMunicipal' => 0.05])
        ->and($regular->request_payload['regime_geral_vendas']['dhFatoGerador'])->toBe('2027-07-01T12:00:00-03:00')
        ->and($regular->calculator_version['regime_geral']['versaoApp'])->toBe('1.6.1');

    $this->getJson("/api/comparisons/{$id}/audit/ibs_cbs_regular")
        ->assertOk()->assertJsonPath('calculator_version.regime_geral.versaoDb', 'v0059')
        ->assertJsonStructure(['request_payload' => ['regime_geral_vendas', 'regime_geral_compras'], 'response_raw']);

    $this->getJson("/api/comparisons/{$id}")
        ->assertOk()
        ->assertJsonPath('data.disclaimer', 'Simulação baseada na Calculadora oficial da Receita Federal. Não substitui análise profissional.')
        ->assertJsonStructure(['data' => ['result', 'assumptions' => ['lista'], 'simulations' => ['simples_integral' => ['totals'], 'ibs_cbs_regular' => ['totals']]]]);
});

it('builds the scenarios from imported invoices (B2B = PJ recipient) and reports item errors', function () {
    $import = InvoiceImport::create(['office_id' => $this->user->office_id, 'client_id' => $this->client->id, 'status' => 'completed', 'files' => []]);
    $base = ['office_id' => $this->user->office_id, 'client_id' => $this->client->id, 'invoice_import_id' => $import->id, 'document_type' => 'nfe', 'item_number' => 1,
        'cst' => '000', 'c_class_trib' => '000001', 'ncm' => '22030000', 'issuer_document' => '11222333000181'];

    InvoiceItem::create(array_merge($base, ['document_key' => 'k1', 'issued_at' => '2026-10-10', 'direction' => 'saida', 'recipient_type' => 'pj', 'base_amount' => 6000]));
    InvoiceItem::create(array_merge($base, ['document_key' => 'k2', 'issued_at' => '2026-11-10', 'direction' => 'saida', 'recipient_type' => 'pf', 'base_amount' => 4000]));
    InvoiceItem::create(array_merge($base, ['document_key' => 'k3', 'issued_at' => '2026-11-11', 'direction' => 'saida', 'recipient_type' => 'pj', 'base_amount' => 500, 'c_class_trib' => '999999']));
    InvoiceItem::create(array_merge($base, ['document_key' => 'k4', 'issued_at' => '2026-09-01', 'direction' => 'entrada', 'recipient_type' => 'pj', 'base_amount' => 2000]));
    // Outside the 12-month window -> ignored
    InvoiceItem::create(array_merge($base, ['document_key' => 'k5', 'issued_at' => '2025-01-01', 'direction' => 'saida', 'recipient_type' => 'pj', 'base_amount' => 99999]));

    $id = $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2028])
        ->assertAccepted()->assertJsonPath('data.source', 'invoices')->json('data.id');

    $c = Comparison::find($id);
    expect($c->status)->toBe('completed')
        ->and($c->result['receita_bruta'])->toBe('10500.00')
        ->and($c->result['receita_b2b'])->toBe('6500.00')
        ->and($c->result['periodo']['fim'])->toBe('2026-11-11')
        ->and($c->result['erros_itens'])->toBe(1)
        ->and($c->result['cenarios']['ibs_cbs_regular']['total_ibs_cbs'])->toBe('890.00') // 10.000 × 8,9% (item with error excluded)
        ->and($c->result['cenarios']['ibs_cbs_regular']['credito_compras_bruto'])->toBe('178.00');

    $regular = $c->simulations()->where('scenario', 'ibs_cbs_regular')->first();
    expect($regular->item_errors[0]['erros'][0]['code'])->toBe('REG-011')
        ->and(collect($regular->totals['items'])->firstWhere('estado', 'INCONSISTENCIA_ENTRADA')['ibs_cbs'])->toBe('0.00');
});

it('fails gracefully with a clear message', function () {
    $this->client->update(['simples_annex' => null]);

    $id = $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2027, 'source' => 'manual'])->json('data.id');
    expect(Comparison::find($id)->status)->toBe('failed')
        ->and(Comparison::find($id)->error_message)->toContain('anexo');

    $id = $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2027, 'source' => 'invoices'])->json('data.id');
    expect(Comparison::find($id)->error_message)->toContain('notas de saída');

    $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2026])->assertUnprocessable();
});

it('reports an unavailable calculator as a failed comparison', function () {
    Illuminate\Support\Facades\Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('refused'));

    $id = $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2027, 'source' => 'manual'])->json('data.id');

    expect(Comparison::find($id)->status)->toBe('failed')
        ->and(Comparison::find($id)->error_message)->toContain('indisponível');
});

it('blocks simulations when the subscription is inactive', function () {
    $this->user->office->update(['plan_status' => 'canceled']);

    $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2027])
        ->assertStatus(402)->assertJsonPath('code', 'subscription_inactive');
});

it('uses CRT=2 above the sublimit', function () {
    $this->client->update(['revenue_12m' => 4000000]);
    $this->postJson("/api/clients/{$this->client->id}/comparisons", ['reference_year' => 2027, 'source' => 'manual']);

    $sent = collect(FakeCalculadoras::$requests)->first(fn ($r) => str_ends_with($r['url'], '/simples-nacional'));
    expect($sent['body']['CRT'])->toBe('2');
});
