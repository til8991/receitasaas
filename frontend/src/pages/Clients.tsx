import { useState } from 'react'
import { Link } from 'react-router-dom'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { cnpj, displayName, money } from '../lib/format'
import type { Client, Paginated } from '../lib/types'
import { EmptyState, ErrorBanner, Loading, PageHeader, Pagination, RecommendationBadge } from '../components/ui'

export default function Clients() {
  const [search, setSearch] = useState('')
  const [active, setActive] = useState<'all' | '1' | '0'>('1')
  const [page, setPage] = useState(1)

  const { data, isLoading, error } = useQuery({
    queryKey: ['clients', { search, active, page }],
    queryFn: async () => (await api.get<Paginated<Client>>('/clients', {
      params: { search: search || undefined, active: active === 'all' ? undefined : active, page },
    })).data,
    placeholderData: keepPreviousData,
  })

  return (
    <>
      <PageHeader
        title="Clientes"
        description="Empresas atendidas pelo escritório. Cada CNPJ ativo conta no limite do plano."
        actions={<Link to="/clientes/novo" className="btn-primary">Cadastrar cliente</Link>}
      />

      <div className="mb-4 flex flex-wrap gap-3">
        <label className="sr-only" htmlFor="busca">Buscar</label>
        <input id="busca" className="input max-w-sm" placeholder="Buscar por nome ou CNPJ" value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1) }} />
        <label className="sr-only" htmlFor="situacao">Situação</label>
        <select id="situacao" className="input w-auto" value={active} onChange={(e) => { setActive(e.target.value as typeof active); setPage(1) }}>
          <option value="1">Ativos</option>
          <option value="0">Inativos</option>
          <option value="all">Todos</option>
        </select>
      </div>

      {isLoading ? <Loading /> : error ? <ErrorBanner>Não foi possível carregar os clientes.</ErrorBanner> : !data || data.total === 0 ? (
        search ? <p className="text-ink-soft">Nenhum cliente encontrado para “{search}”.</p> : (
          <EmptyState title="Nenhum cliente cadastrado" action={<Link to="/clientes/novo" className="btn-primary">Cadastrar cliente</Link>}>
            Cadastre os clientes do Simples Nacional para comparar os dois regimes de IBS/CBS.
          </EmptyState>
        )
      ) : (
        <>
          <div className="sheet overflow-x-auto">
            <table className="table-ledger">
              <thead>
                <tr><th>Cliente</th><th>CNPJ</th><th>UF</th><th>Anexo</th><th className="text-right">Faturamento 12m</th><th className="text-right">B2B</th><th>Notas</th><th>Última simulação</th></tr>
              </thead>
              <tbody>
                {data.data.map((c) => (
                  <tr key={c.id} className={c.active ? '' : 'text-muted'}>
                    <td className="max-w-[260px]">
                      <Link to={`/clientes/${c.id}`} className="block truncate font-medium hover:text-brand">{displayName(c)}</Link>
                      {c.trade_name && <span className="block truncate text-xs text-muted">{c.legal_name}</span>}
                    </td>
                    <td className="figure whitespace-nowrap">{cnpj(c.cnpj)}</td>
                    <td>{c.uf}</td>
                    <td>{c.simples_annex ?? '—'}</td>
                    <td className="figure text-right">{money(c.revenue_12m)}</td>
                    <td className="figure text-right">{Number(c.b2b_sales_pct).toLocaleString('pt-BR')}%</td>
                    <td className="figure">{c.invoice_items_count ? c.invoice_items_count.toLocaleString('pt-BR') : '—'}</td>
                    <td>
                      {c.latest_comparison ? (
                        <Link to={`/simulacoes/${c.latest_comparison.id}`}><RecommendationBadge value={c.latest_comparison.recommendation} /></Link>
                      ) : <span className="text-muted">—</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data.current_page} last={data.last_page} onChange={setPage} />
        </>
      )}
    </>
  )
}
