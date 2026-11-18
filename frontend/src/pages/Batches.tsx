import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api, errorMessage } from '../lib/api'
import { cnpj, dateTime, displayName } from '../lib/format'
import type { Batch, Client, Paginated } from '../lib/types'
import { ErrorBanner, Loading, PageHeader, ProgressBar, Spinner, StatusBadge } from '../components/ui'

const YEARS = Array.from({ length: 7 }, (_, i) => 2027 + i)

export default function Batches() {
  const navigate = useNavigate()
  const [year, setYear] = useState(String(Math.max(2027, new Date().getFullYear() + 1)))
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [filter, setFilter] = useState('')

  const { data: clients, isLoading } = useQuery({
    queryKey: ['clients', 'all-active'],
    queryFn: async () => (await api.get<Paginated<Client>>('/clients', { params: { active: 1, per_page: 200 } })).data.data,
  })
  const { data: batches } = useQuery({
    queryKey: ['batches'],
    queryFn: async () => (await api.get<Paginated<Batch>>('/batches')).data.data,
    refetchInterval: (q) => (q.state.data?.some((b) => b.status !== 'completed' && b.status !== 'failed') ? 2000 : false),
  })

  const visible = useMemo(() => {
    const f = filter.trim().toLowerCase()
    return (clients ?? []).filter((c) => !f || displayName(c).toLowerCase().includes(f) || c.cnpj.includes(f.replace(/\D/g, '')))
  }, [clients, filter])

  const run = useMutation({
    mutationFn: async () => (await api.post<{ data: Batch }>('/batches', { reference_year: Number(year), client_ids: [...selected] })).data.data,
    onSuccess: (b) => navigate(`/lotes/${b.id}`),
  })

  const toggle = (id: number) => setSelected((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n })
  const allVisibleSelected = visible.length > 0 && visible.every((c) => selected.has(c.id))
  const toggleAll = () => setSelected((s) => {
    const n = new Set(s)
    visible.forEach((c) => (allVisibleSelected ? n.delete(c.id) : n.add(c.id)))
    return n
  })

  return (
    <>
      <PageHeader title="Simulação em lote" description="Rode a comparação para vários clientes de uma vez. Cada cliente usa as notas importadas, se houver, ou os valores do cadastro." />

      <div className="grid gap-8 lg:grid-cols-[1.4fr_1fr]">
        <section>
          <div className="mb-3 flex flex-wrap items-end gap-3">
            <div>
              <label className="label" htmlFor="year">Ano de referência</label>
              <select id="year" className="input w-32" value={year} onChange={(e) => setYear(e.target.value)}>{YEARS.map((y) => <option key={y}>{y}</option>)}</select>
            </div>
            <div className="min-w-[200px] flex-1">
              <label className="label" htmlFor="filter">Filtrar clientes</label>
              <input id="filter" className="input" value={filter} onChange={(e) => setFilter(e.target.value)} placeholder="Nome ou CNPJ" />
            </div>
            <button className="btn-primary" disabled={selected.size === 0 || run.isPending} onClick={() => run.mutate()}>
              {run.isPending && <Spinner small />} Rodar para {selected.size} cliente{selected.size === 1 ? '' : 's'}
            </button>
          </div>
          {run.isError && <div className="mb-3"><ErrorBanner>{errorMessage(run.error)}</ErrorBanner></div>}

          {isLoading ? <Loading /> : (
            <div className="sheet max-h-[560px] overflow-auto">
              <table className="table-ledger">
                <thead className="sticky top-0 bg-sheet">
                  <tr>
                    <th className="w-10"><input type="checkbox" aria-label="Selecionar todos" checked={allVisibleSelected} onChange={toggleAll} className="h-4 w-4 accent-[var(--color-brand)]" /></th>
                    <th>Cliente</th><th>CNPJ</th><th>Notas</th>
                  </tr>
                </thead>
                <tbody>
                  {visible.map((c) => (
                    <tr key={c.id} className="cursor-pointer hover:bg-paper" onClick={() => toggle(c.id)}>
                      <td><input type="checkbox" aria-label={`Selecionar ${displayName(c)}`} checked={selected.has(c.id)} onChange={() => toggle(c.id)} onClick={(e) => e.stopPropagation()} className="h-4 w-4 accent-[var(--color-brand)]" /></td>
                      <td className="max-w-[260px] truncate font-medium">{displayName(c)}</td>
                      <td className="figure whitespace-nowrap">{cnpj(c.cnpj)}</td>
                      <td className="text-sm text-muted">{c.invoice_items_count ? 'Sim' : 'Cadastro'}</td>
                    </tr>
                  ))}
                  {visible.length === 0 && <tr><td colSpan={4} className="py-8 text-center text-muted">Nenhum cliente ativo encontrado.</td></tr>}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <aside>
          <h2 className="mb-3 text-lg font-bold">Lotes anteriores</h2>
          {!batches?.length ? <p className="text-ink-soft">Nenhum lote ainda.</p> : (
            <ul className="space-y-3">
              {batches.map((b) => (
                <li key={b.id}>
                  <Link to={`/lotes/${b.id}`} className="sheet block p-4 hover:border-brand/50">
                    <div className="flex items-center justify-between gap-3">
                      <span className="font-semibold">{b.total} clientes, {b.reference_year}</span>
                      <StatusBadge status={b.status} />
                    </div>
                    <div className="mt-2"><ProgressBar value={b.progress} /></div>
                    <div className="mt-1 text-xs text-muted">{dateTime(b.created_at)}{b.failed ? `, ${b.failed} com falha` : ''}</div>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </aside>
      </div>
    </>
  )
}
