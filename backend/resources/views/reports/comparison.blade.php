@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $pct = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.').'%';
    $cnpj = fn ($c) => strlen($c) === 14 ? substr($c,0,2).'.'.substr($c,2,3).'.'.substr($c,5,3).'/'.substr($c,8,4).'-'.substr($c,12,2) : $c;
    $a = $result['cenarios']['simples_integral'];
    $b = $result['cenarios']['ibs_cbs_regular'];
    $max = max(1, abs((float) $a['custo_efetivo']), abs((float) $b['custo_efetivo']), (float) $a['total_ibs_cbs'], (float) $b['total_ibs_cbs']);
    $bar = fn ($v) => max(0.5, min(100, abs((float) $v) / $max * 100));
    $labels = [
        'migrar_regular' => 'Optar pelo IBS/CBS no regime regular',
        'manter_simples' => 'Manter o IBS/CBS no Simples Nacional',
        'indiferente' => 'Indiferente (diferença abaixo do limite)',
    ];
    $primary = $office->primary_color ?: '#0F4C81';
    $secondary = $office->secondary_color ?: '#F2A900';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Simulação Reforma Tributária — {{ $client->legal_name }}</title>
<style>
    @page { margin: 28mm 16mm 22mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2933; }
    header { position: fixed; top: -22mm; left: 0; right: 0; height: 18mm; border-bottom: 2px solid {{ $primary }}; }
    footer { position: fixed; bottom: -16mm; left: 0; right: 0; height: 12mm; font-size: 8px; color: #52606d; border-top: 1px solid #d9e2ec; padding-top: 4px; }
    .brand td { vertical-align: middle; }
    .brand .name { font-size: 14px; font-weight: bold; color: {{ $primary }}; }
    h1 { font-size: 16px; margin: 0 0 4px; color: {{ $primary }}; }
    h2 { font-size: 12px; margin: 16px 0 6px; color: {{ $primary }}; border-bottom: 1px solid #d9e2ec; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; }
    .grid td { padding: 4px 6px; border-bottom: 1px solid #eef2f6; }
    .grid th { text-align: left; padding: 5px 6px; background: #f0f4f8; font-size: 9px; text-transform: uppercase; color: #52606d; }
    .num { text-align: right; white-space: nowrap; }
    .reco { padding: 10px 12px; border-left: 4px solid {{ $secondary }}; background: #fffaf0; margin: 8px 0; }
    .reco .title { font-size: 13px; font-weight: bold; color: {{ $primary }}; }
    .bar-wrap { background: #f0f4f8; height: 12px; width: 100%; }
    .bar { height: 12px; }
    .muted { color: #7b8794; }
    .small { font-size: 8.5px; }
    .disclaimer { font-weight: bold; color: #9b2c2c; }
    .pagenum:before { content: counter(page); }
</style>
</head>
<body>
<header>
    <table class="brand"><tr>
        <td style="width: 60%">
            @if ($logo)<img src="{{ $logo }}" style="max-height: 14mm; max-width: 60mm;">@else<span class="name">{{ $office->name }}</span>@endif
        </td>
        <td style="text-align: right" class="small muted">
            {{ $office->name }} · CNPJ {{ $cnpj($office->cnpj) }}<br>
            @if ($office->email){{ $office->email }}@endif @if ($office->phone) · {{ $office->phone }}@endif
        </td>
    </tr></table>
</header>

<footer>
    <span class="disclaimer">{{ $disclaimer }}</span><br>
    Gerado em {{ now()->format('d/m/Y H:i') }} · Simulação #{{ $comparison->id }} · Página <span class="pagenum"></span>
</footer>

<h1>Reforma Tributária — IBS/CBS: Simples Nacional × Regime Regular</h1>
<div class="muted">Ano de referência <strong>{{ $result['ano_referencia'] }}</strong> ·
    Base: {{ $result['periodo']['origem'] === 'manual' ? 'valores informados' : 'notas fiscais de '.\Carbon\Carbon::parse($result['periodo']['inicio'])->format('d/m/Y').' a '.\Carbon\Carbon::parse($result['periodo']['fim'])->format('d/m/Y') }}
</div>

<h2>Cliente</h2>
<table class="grid">
    <tr><td style="width: 30%" class="muted">Razão social</td><td>{{ $client->legal_name }}</td></tr>
    <tr><td class="muted">CNPJ</td><td>{{ $cnpj($client->cnpj) }}</td></tr>
    <tr><td class="muted">Município / UF</td><td>{{ $client->municipality_ibge }} / {{ $client->uf }}</td></tr>
    <tr><td class="muted">Anexo do Simples / Atividade</td><td>{{ $client->simples_annex ?? '—' }} / {{ implode(', ', $client->activities ?? []) ?: '—' }}</td></tr>
    <tr><td class="muted">Receita bruta considerada</td><td>{{ $brl($result['receita_bruta']) }} (B2B: {{ $brl($result['receita_b2b']) }})</td></tr>
    <tr><td class="muted">Compras consideradas</td><td>{{ $brl($result['compras']) }}</td></tr>
</table>

<div class="reco">
    <div class="title">Recomendação: {{ $labels[$result['recomendacao']] ?? $result['recomendacao'] }}</div>
    Diferença de custo efetivo: <strong>{{ $brl($result['diferenca']['valor']) }}</strong>
    ({{ $pct(abs($result['diferenca']['percentual_receita'])) }} da receita; valor negativo indica economia no regime regular).
</div>

<h2>Cenários lado a lado</h2>
<table class="grid">
    <tr><th></th><th class="num">Simples integral</th><th class="num">IBS/CBS regular</th></tr>
    <tr><td>IBS</td><td class="num">{{ $brl($a['ibs']) }}</td><td class="num">{{ $brl($b['ibs']) }}</td></tr>
    <tr><td>CBS</td><td class="num">{{ $brl($a['cbs']) }}</td><td class="num">{{ $brl($b['cbs']) }}</td></tr>
    <tr><td>Total IBS/CBS (débito)</td><td class="num">{{ $brl($a['total_ibs_cbs']) }}</td><td class="num">{{ $brl($b['total_ibs_cbs']) }}</td></tr>
    <tr><td>(−) Crédito de compras aproveitado</td><td class="num">—</td><td class="num">{{ $brl($b['credito_compras_aproveitado']) }}</td></tr>
    <tr><td><strong>Custo líquido de IBS/CBS</strong></td><td class="num"><strong>{{ $brl($a['custo_liquido']) }}</strong></td><td class="num"><strong>{{ $brl($b['custo_liquido']) }}</strong></td></tr>
    <tr><td>Crédito gerado aos clientes B2B</td><td class="num">{{ $brl($a['credito_clientes_b2b']) }}</td><td class="num">{{ $brl($b['credito_clientes_b2b']) }}</td></tr>
    <tr><td>(−) Benefício comercial do crédito adicional</td><td class="num">—</td><td class="num">{{ $brl($b['beneficio_comercial']) }}</td></tr>
    <tr><td><strong>Custo efetivo</strong></td><td class="num"><strong>{{ $brl($a['custo_efetivo']) }}</strong></td><td class="num"><strong>{{ $brl($b['custo_efetivo']) }}</strong></td></tr>
</table>

<table style="margin-top: 10px">
    @foreach ([['Simples integral — custo efetivo', $a['custo_efetivo'], $secondary], ['IBS/CBS regular — custo efetivo', $b['custo_efetivo'], $primary]] as [$label, $value, $color])
        <tr>
            <td style="width: 32%; padding: 3px 6px 3px 0">{{ $label }}</td>
            <td style="padding: 3px 0"><div class="bar-wrap"><div class="bar" style="width: {{ $bar($value) }}%; background: {{ $color }}"></div></div></td>
            <td class="num" style="width: 22%; padding-left: 6px">{{ $brl($value) }}</td>
        </tr>
    @endforeach
</table>

<h2>Justificativa</h2>
<ul style="margin: 0; padding-left: 14px">
    @foreach ($result['justificativa'] as $line)<li style="margin-bottom: 3px">{{ $line }}</li>@endforeach
</ul>

@if (! empty($result['avisos']))
<h2>Avisos</h2>
<ul style="margin: 0; padding-left: 14px">@foreach ($result['avisos'] as $w)<li>{{ $w }}</li>@endforeach</ul>
@endif

<h2>Premissas utilizadas</h2>
<table class="grid small">
    <tr><th style="width: 8%">ID</th><th style="width: 42%">Premissa</th><th>Valor</th></tr>
    @foreach ($assumptions as $p)
        <tr><td>{{ $p['id'] }}</td><td>{{ $p['descricao'] }}</td><td>{{ $p['valor'] }}</td></tr>
    @endforeach
</table>

@if ($itemErrors->isNotEmpty())
<h2>Itens com erro na calculadora oficial ({{ $itemErrors->count() }})</h2>
<table class="grid small">
    <tr><th>Cenário</th><th>Item</th><th>Código</th><th>Detalhe</th></tr>
    @foreach ($itemErrors->take(30) as $e)
        @foreach ($e['erros'] ?: [['code' => $e['estadoItem'], 'detail' => '']] as $err)
            <tr><td>{{ $e['cenario'] === 'simples_integral' ? 'Simples' : 'Regular' }}{{ isset($e['origem']) ? ' (compras)' : '' }}</td><td>{{ $e['nObj'] }}</td><td>{{ $err['code'] ?? '' }}</td><td>{{ $err['detail'] ?? ($err['title'] ?? '') }}</td></tr>
        @endforeach
    @endforeach
</table>
@endif

<h2>Auditoria</h2>
<table class="grid small">
    @foreach ($versions as $scenario => $calcs)
        @foreach ((array) $calcs as $calc => $v)
            <tr>
                <td style="width: 30%">{{ $calc === 'simples_nacional' ? 'Calculadora Simples Nacional' : 'Calculadora Regime Regular' }}</td>
                <td>app {{ $v['versaoApp'] ?? '—' }} · base de dados {{ $v['versaoDb'] ?? '—' }} {{ isset($v['dataVersaoDb']) ? '('.$v['dataVersaoDb'].')' : '' }} · {{ $v['ambiente'] ?? '' }}</td>
            </tr>
        @endforeach
    @endforeach
    <tr><td>Calculado em</td><td>{{ optional($comparison->calculated_at)->format('d/m/Y H:i') }}</td></tr>
</table>
<p class="small muted">Os valores de IBS, CBS e IS foram calculados pelas calculadoras oficiais da Receita Federal (código aberto,
    github.com/ReceitaFederal). Payloads enviados e respostas brutas ficam armazenados para auditoria.</p>
</body>
</html>
