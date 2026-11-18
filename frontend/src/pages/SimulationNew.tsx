import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api, errorMessage } from '../lib/api'
import { cnpj, displayName, money } from '../lib/format'
import type { Client, Comparison, Paginated } from '../lib/types'
import { ErrorBanner, Field, Notice, PageHeader, Spinner } from '../components/ui'

const YEARS = Array.from({ length: 7 }, (_, i) => 2027 + i)

export default function SimulationNew() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [clientId, setClientId] = useState(params.get('cliente') ?? '')
  const [year, setYear] = useState(String(Math.max(2027, new Date().getFullYear() + 1)))
  const [source, setSource] = useState<'auto' | 'invoices' | 'manual'>('auto')
  const [manual, setManual] = useState({ revenue: '', b2b_sales_pct: '', purchases: '' })

  const { data: clients } = useQuery({
    queryKey: ['clients', 'all-active'],
    queryFn: async () => (await api.get<Paginated<Client>>('/clients', { params: { active: 1, per_page: 200 } })).data.data,
  })
  const client = clients?.find((c) => String(c.id) === clientId)

  useEffect(() => {
    if (client) setManual({ revenue: client.revenue_12m, b2b_sales_pct: client.b2b_sales_pct, purchases: client.purchases_12m })
  }, [client])

  const run = useMutation({
    mutationFn: async () => {
      const body: Record<string, unknown> = { reference_year: Number(year), source }
      if (source === 'manual') {
        body.manual = {
          revenue: manual.revenue === '' ? null : Number(manual.revenue),
          b2b_sales_pct: manual.b2b_sales_pct === '' ? null : Number(manual.b2b_sales_pct),
          purchases: manual.purchases === '' ? null : Number(manual.purchases),
        }
      }
      return (await api.post<{ data: Comparison }>(`/clients/${clientId}/comparisons`, body)).data.data
    },
    onSuccess: (c) => navigate(`/simulacoes/${c.id}`),
  })

  const submit = (e: FormEvent) => { e.preventDefault(); run.mutate() }
  const hasInvoices = (client?.invoice_items_count ?? 0) > 0

  return (
    <>
      <PageHeader title="Nova simulação" back={{ to: '/simulacoes', label: 'Simulações' }}
        description="Compara o IBS/CBS recolhido dentro do Simples com a opção de recolher pelo regime regular, usando as duas calculadoras oficiais." />

      <form onSubmit={submit} className="max-w-2xl space-y-6" noValidate>
        {run.isError && <ErrorBanner>{errorMessage(run.error)}</ErrorBanner>}

        <div className="grid gap-4 sm:grid-cols-[1fr_160px]">
          <Field label="Cliente" htmlFor="client">
            <select id="client" className="input" value={clientId} onChange={(e) => setClientId(e.target.value)} required>
              <option value="">Escolha um cliente</option>
              {clients?.map((c) => <option key={c.id} value={c.id}>{displayName(c)} — {cnpj(c.cnpj)}</option>)}
            </select>
          </Field>
          <Field label="Ano de referência" htmlFor="year">
            <select id="year" className="input" value={year} onChange={(e) => setYear(e.target.value)}>
              {YEARS.map((y) => <option key={y}>{y}</option>)}
            </select>
          </Field>
        </div>

        <fieldset>
          <legend className="label">Base da simulação</legend>
          <div className="grid gap-2 sm:grid-cols-3">
            {([
              ['auto', 'Automática', 'Notas, se houver; senão valores informados'],
              ['invoices', 'Notas importadas', '12 meses até a última nota de saída'],
              ['manual', 'Valores informados', 'Faturamento, % B2B e compras'],
            ] as const).map(([value, title, help]) => (
              <label key={value} className={`cursor-pointer rounded-md border px-3 py-2.5 ${source === value ? 'border-brand bg-brand/5' : 'border-rule bg-sheet'}`}>
                <input type="radio" name="source" value={value} checked={source === value} onChange={() => setSource(value)} className="sr-only" />
                <span className="block text-sm font-semibold">{title}</span>
                <span className="block text-xs text-muted">{help}</span>
              </label>
            ))}
          </div>
          {client && source !== 'manual' && (
            <p className="mt-2 text-sm text-ink-soft">
              {hasInvoices ? `${client.invoice_items_count?.toLocaleString('pt-BR')} itens de nota importados.` : 'Este cliente ainda não tem notas importadas; será usada a simulação com valores informados.'}
            </p>
          )}
        </fieldset>

        {source === 'manual' && (
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Faturamento anual (R$)" htmlFor="m-rev">
              <input id="m-rev" className="input figure" inputMode="decimal" value={manual.revenue} onChange={(e) => setManual((m) => ({ ...m, revenue: e.target.value }))} />
            </Field>
            <Field label="Vendas B2B (%)" htmlFor="m-b2b">
              <input id="m-b2b" className="input figure" inputMode="decimal" value={manual.b2b_sales_pct} onChange={(e) => setManual((m) => ({ ...m, b2b_sales_pct: e.target.value }))} />
            </Field>
            <Field label="Compras anuais (R$)" htmlFor="m-pur">
              <input id="m-pur" className="input figure" inputMode="decimal" value={manual.purchases} onChange={(e) => setManual((m) => ({ ...m, purchases: e.target.value }))} />
            </Field>
          </div>
        )}

        {client && !client.simples_annex && (
          <Notice tone="warn">Este cliente não tem anexo do Simples cadastrado. Cadastre o anexo ou informe as alíquotas efetivas nas premissas do cliente.</Notice>
        )}
        {client && (
          <p className="text-sm text-muted">RBT12 usado para a alíquota efetiva do Simples: {money(client.revenue_12m)}.</p>
        )}

        <button type="submit" className="btn-primary" disabled={!clientId || run.isPending}>
          {run.isPending && <Spinner small />} Rodar simulação
        </button>
      </form>
    </>
  )
}
