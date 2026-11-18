import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { date, displayName, money, percent } from '../lib/format'
import type { Dashboard as DashboardData } from '../lib/types'
import { EmptyState, ErrorBanner, Loading, Notice, PageHeader, RecommendationBadge, StatusBadge } from '../components/ui'

export default function Dashboard() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get<DashboardData>('/dashboard')).data,
  })

  if (isLoading) return <Loading />
  if (error || !data) return <ErrorBanner>Não foi possível carregar o painel.</ErrorBanner>

  const r = data.recommendations

  return (
    <>
      <PageHeader
        title="Painel"
        description="Onde o IBS/CBS pelo regime regular compensa entre os seus clientes do Simples Nacional."
        actions={<>
          <Link to="/lotes" className="btn-secondary">Simular em lote</Link>
          <Link to="/simulacoes/nova" className="btn-primary">Nova simulação</Link>
        </>}
      />

      {!data.plan.is_active && (
        <div className="mb-6"><Notice tone="warn">Sua assinatura está inativa. Novas simulações e importações estão bloqueadas até atualizar o plano em <Link to="/configuracoes" className="underline">Configurações</Link>.</Notice></div>
      )}

      {data.clients.total === 0 ? (
        <EmptyState
          title="Cadastre o primeiro cliente"
          action={<Link to="/clientes/novo" className="btn-primary">Cadastrar cliente</Link>}
        >
          Com o CNPJ, o município, o anexo do Simples e o faturamento você já consegue simular. Para um resultado mais preciso, importe os XMLs de notas fiscais do cliente.
        </EmptyState>
      ) : (
        <>
          <section aria-label="Resumo" className="sheet grid grid-cols-2 divide-rule sm:grid-cols-4 sm:divide-x">
            <div className="col-span-2 border-b border-rule p-5 sm:col-span-1 sm:border-b-0">
              <div className="text-sm text-ink-soft">Compensa migrar</div>
              <div className="figure mt-1 text-5xl font-bold text-migrate">{r.migrar_regular}</div>
              <div className="mt-1 text-sm text-muted">de {data.clients.simulated} clientes simulados</div>
            </div>
            <Stat label="Economia potencial por ano" value={money(data.potential_savings)} note="soma dos clientes que compensam migrar" />
            <Stat label="Manter no Simples" value={String(r.manter_simples)} note={`${r.indiferente} indiferentes`} />
            <Stat label="Clientes ativos" value={String(data.clients.active)} note={`${data.clients.total - data.clients.simulated} ainda sem simulação`} />
          </section>

          <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_1.4fr]">
            <section>
              <h2 className="mb-3 text-lg font-bold">Maiores oportunidades</h2>
              {data.top_migrate.length === 0 ? (
                <p className="text-ink-soft">Nenhum cliente simulado tem vantagem no regime regular até agora.</p>
              ) : (
                <ol className="sheet divide-y divide-rule-soft">
                  {data.top_migrate.map((t, i) => (
                    <li key={t.comparison_id}>
                      <Link to={`/simulacoes/${t.comparison_id}`} className="flex items-center gap-4 px-4 py-3 hover:bg-paper">
                        <span className="figure w-6 text-right text-muted">{i + 1}</span>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate font-medium">{displayName(t.client)}</span>
                          <span className="text-xs text-muted">{t.reference_year}</span>
                        </span>
                        <span className="text-right">
                          <span className="figure block font-semibold text-migrate">{money(Math.abs(+t.difference))}</span>
                          <span className="text-xs text-muted">{percent(Math.abs(t.difference_pct))} da receita</span>
                        </span>
                      </Link>
                    </li>
                  ))}
                </ol>
              )}
            </section>

            <section>
              <div className="mb-3 flex items-baseline justify-between">
                <h2 className="text-lg font-bold">Simulações recentes</h2>
                <Link to="/simulacoes" className="text-sm text-brand hover:underline">Ver todas</Link>
              </div>
              {data.recent.length === 0 ? (
                <p className="text-ink-soft">Nenhuma simulação ainda. <Link to="/simulacoes/nova" className="text-brand underline">Rode a primeira</Link>.</p>
              ) : (
                <div className="sheet overflow-x-auto">
                  <table className="table-ledger">
                    <thead><tr><th>Cliente</th><th>Ano</th><th>Resultado</th><th className="text-right">Diferença</th><th>Data</th></tr></thead>
                    <tbody>
                      {data.recent.map((c) => (
                        <tr key={c.id}>
                          <td className="max-w-[220px] truncate"><Link to={`/simulacoes/${c.id}`} className="font-medium hover:text-brand">{displayName(c.client)}</Link></td>
                          <td className="figure">{c.reference_year}</td>
                          <td>{c.status === 'completed' ? <RecommendationBadge value={c.recommendation} /> : <StatusBadge status={c.status} />}</td>
                          <td className="figure text-right">{c.difference !== null ? money(c.difference) : '—'}</td>
                          <td className="text-muted">{date(c.created_at)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </div>
        </>
      )}
    </>
  )
}

function Stat({ label, value, note }: { label: string; value: string; note: string }) {
  return (
    <div className="p-5">
      <div className="text-sm text-ink-soft">{label}</div>
      <div className="figure mt-1 text-2xl font-bold">{value}</div>
      <div className="mt-1 text-sm text-muted">{note}</div>
    </div>
  )
}
