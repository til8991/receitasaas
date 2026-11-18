import { money, percent, recommendationLong } from '../lib/format'
import type { ComparisonResult } from '../lib/types'

/**
 * The two scenarios as an accounting T-account (razonete): Simples integral on the left,
 * IBS/CBS regular on the right, with the verdict written above the horizontal rule.
 */
export function Razonete({ result }: { result: ComparisonResult }) {
  const a = result.cenarios.simples_integral
  const b = result.cenarios.ibs_cbs_regular
  const diff = Number(result.diferenca.valor)
  const tone = result.recomendacao === 'migrar_regular' ? 'text-migrate' : result.recomendacao === 'manter_simples' ? 'text-keep' : 'text-ink-soft'
  const saving = Math.abs(diff)

  const rows: { label: string; a: string | null; b: string | null; strong?: boolean; sign?: '−' }[] = [
    { label: 'IBS', a: a.ibs, b: b.ibs },
    { label: 'CBS', a: a.cbs, b: b.cbs },
    { label: 'Crédito de compras aproveitado', a: null, b: b.credito_compras_aproveitado, sign: '−' },
    { label: 'Custo líquido de IBS/CBS', a: a.custo_liquido, b: b.custo_liquido, strong: true },
    { label: 'Crédito gerado aos clientes B2B', a: a.credito_clientes_b2b, b: b.credito_clientes_b2b },
    { label: 'Benefício comercial do crédito adicional', a: null, b: b.beneficio_comercial, sign: '−' },
    { label: 'Custo efetivo', a: a.custo_efetivo, b: b.custo_efetivo, strong: true },
  ]

  return (
    <section aria-labelledby="veredito" className="sheet animate-settle overflow-hidden">
      <div className="px-5 pb-4 pt-6 sm:px-8">
        <p className="text-sm text-ink-soft">Recomendação para {result.ano_referencia}</p>
        <h2 id="veredito" className={`mt-1 text-2xl font-bold sm:text-3xl ${tone}`}>{recommendationLong[result.recomendacao]}</h2>
        <p className="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
          <span className={`figure text-4xl font-bold sm:text-5xl ${tone}`}>{money(saving)}</span>
          <span className="text-ink-soft">
            {diff < 0 ? 'a menos por ano no regime regular' : diff > 0 ? 'a menos por ano mantendo o Simples' : 'de diferença'}
            {', '}{percent(Math.abs(result.diferenca.percentual_receita))} da receita bruta
          </span>
        </p>
      </div>

      {/* The T: top rule spans both columns, vertical rule splits them */}
      <div className="border-t-[3px] border-ink">
        <div className="grid grid-cols-[1fr_auto_1fr] text-sm sm:text-[15px]">
          <ColumnTitle side="left">Simples integral</ColumnTitle>
          <div className="w-[3px] bg-ink" aria-hidden />
          <ColumnTitle side="right">IBS/CBS no regime regular</ColumnTitle>

          {rows.map((row) => (
            <Row key={row.label} {...row} />
          ))}
        </div>
      </div>
    </section>
  )
}

function ColumnTitle({ children, side }: { children: string; side: 'left' | 'right' }) {
  return (
    <div className={`px-4 pb-2 pt-3 font-semibold sm:px-8 ${side === 'left' ? 'text-right' : ''}`} style={{ fontStretch: '112%' }}>
      {children}
    </div>
  )
}

function Row({ label, a, b, strong, sign }: { label: string; a: string | null; b: string | null; strong?: boolean; sign?: '−' }) {
  const cell = (v: string | null, side: 'left' | 'right') => (
    <div className={`border-t border-rule-soft px-4 py-2 sm:px-8 ${side === 'left' ? 'text-right' : ''} ${strong ? 'bg-paper font-semibold' : ''}`}>
      <div className="text-xs text-muted">{label}</div>
      <div className="figure">{v === null ? '—' : `${sign ? `${sign} ` : ''}${money(v)}`}</div>
    </div>
  )
  return (
    <>
      {cell(a, 'left')}
      <div className="w-[3px] bg-ink" aria-hidden />
      {cell(b, 'right')}
    </>
  )
}
