import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { money } from '../lib/format'
import type { ComparisonResult } from '../lib/types'

/** Grouped bars: each measure compared between the two scenarios. */
export function ScenarioChart({ result }: { result: ComparisonResult }) {
  const a = result.cenarios.simples_integral
  const b = result.cenarios.ibs_cbs_regular
  const data = [
    { name: 'IBS/CBS devido', simples: +a.total_ibs_cbs, regular: +b.total_ibs_cbs },
    { name: 'Custo líquido', simples: +a.custo_liquido, regular: +b.custo_liquido },
    { name: 'Crédito aos clientes B2B', simples: +a.credito_clientes_b2b, regular: +b.credito_clientes_b2b },
    { name: 'Custo efetivo', simples: +a.custo_efetivo, regular: +b.custo_efetivo },
  ]

  return (
    <figure className="sheet p-4 sm:p-6">
      <figcaption className="mb-4 font-semibold">Comparação por medida (R$ por ano)</figcaption>
      <div className="h-72">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 4, right: 8, left: 8, bottom: 4 }} barGap={4}>
            <CartesianGrid vertical={false} stroke="var(--color-rule-soft)" />
            <XAxis dataKey="name" tick={{ fontSize: 12, fill: 'var(--color-ink-soft)' }} axisLine={{ stroke: 'var(--color-rule)' }} tickLine={false} interval={0} />
            <YAxis tickFormatter={(v: number) => new Intl.NumberFormat('pt-BR', { notation: 'compact' }).format(v)}
              tick={{ fontSize: 12, fill: 'var(--color-muted)' }} axisLine={false} tickLine={false} width={56} />
            <Tooltip formatter={(v) => money(Number(v))} cursor={{ fill: 'var(--color-rule-soft)' }}
              contentStyle={{ borderRadius: 8, borderColor: 'var(--color-rule)', fontSize: 13 }} />
            <Legend wrapperStyle={{ fontSize: 13 }} />
            <Bar dataKey="simples" name="Simples integral" fill="var(--color-accent)" radius={[3, 3, 0, 0]} maxBarSize={48} />
            <Bar dataKey="regular" name="IBS/CBS regular" fill="var(--color-brand)" radius={[3, 3, 0, 0]} maxBarSize={48} />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </figure>
  )
}
