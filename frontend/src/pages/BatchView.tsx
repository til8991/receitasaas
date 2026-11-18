import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api, download } from '../lib/api'
import { cnpj, dateTime, displayName, money, percent } from '../lib/format'
import type { Batch } from '../lib/types'
import { ErrorBanner, Loading, Notice, PageHeader, ProgressBar, RecommendationBadge, StatusBadge } from '../components/ui'

export default function BatchView() {
  const { id } = useParams()
  const [busy, setBusy] = useState<number | null>(null)

  const { data: b, isLoading, error } = useQuery({
    queryKey: ['batch', id],
    queryFn: async () => (await api.get<{ data: Batch }>(`/batches/${id}`)).data.data,
    refetchInterval: (q) => (q.state.data && !['completed', 'failed'].includes(q.state.data.status) ? 1500 : false),
  })

  const reports = useMutation({ mutationFn: () => api.post<{ queued: number }>(`/batches/${id}/reports`) })

  if (isLoading) return <Loading />
  if (error || !b) return <ErrorBanner>Lote não encontrado.</ErrorBanner>

  const done = b.processed + b.failed
  const migrate = b.comparisons?.filter((c) => c.recommendation === 'migrar_regular').length ?? 0

  return (
    <>
      <PageHeader title={`Lote de ${b.reference_year}`} back={{ to: '/lotes', label: 'Lotes' }}
        description={`Iniciado em ${dateTime(b.created_at)}.`}
        actions={b.status === 'completed' && b.processed > 0 ? (
          <button className="btn-secondary" onClick={() => reports.mutate()} disabled={reports.isPending || reports.isSuccess}>
            {reports.isSuccess ? 'PDFs em geração' : 'Gerar PDFs de todos'}
          </button>
        ) : undefined} />

      <section className="sheet mb-6 p-5">
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-3">
          <div className="text-lg font-semibold"><span className="figure">{done}</span> de <span className="figure">{b.total}</span> clientes processados</div>
          <StatusBadge status={b.status} />
        </div>
        <ProgressBar value={b.progress} />
        <div className="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-ink-soft">
          <span><strong className="text-migrate">{migrate}</strong> compensa migrar</span>
          <span><strong>{b.processed}</strong> concluídos</span>
          {b.failed > 0 && <span className="text-danger"><strong>{b.failed}</strong> com falha</span>}
        </div>
      </section>

      {reports.isSuccess && <div className="mb-4"><Notice>{reports.data.data.queued} PDFs entraram na fila. Eles ficam disponíveis em Relatórios.</Notice></div>}

      <div className="sheet overflow-x-auto">
        <table className="table-ledger">
          <thead><tr><th>Cliente</th><th>CNPJ</th><th>Resultado</th><th className="text-right">Diferença</th><th className="text-right">% receita</th><th /></tr></thead>
          <tbody>
            {b.comparisons?.map((c) => (
              <tr key={c.id}>
                <td className="max-w-[240px] truncate"><Link to={`/simulacoes/${c.id}`} className="font-medium hover:text-brand">{displayName(c.client)}</Link></td>
                <td className="figure whitespace-nowrap">{cnpj(c.client.cnpj)}</td>
                <td>
                  {c.status === 'completed' ? <RecommendationBadge value={c.recommendation} /> : <StatusBadge status={c.status} />}
                  {c.status === 'failed' && c.error_message && <span className="mt-1 block max-w-[260px] text-xs text-danger">{c.error_message}</span>}
                </td>
                <td className="figure text-right">{c.difference !== null ? money(c.difference) : '—'}</td>
                <td className="figure text-right">{c.difference_pct !== null ? percent(c.difference_pct) : '—'}</td>
                <td className="text-right">
                  {c.status === 'completed' && (
                    <button className="btn-ghost text-sm" disabled={busy === c.id}
                      onClick={async () => { setBusy(c.id); try { await download(`/comparisons/${c.id}/report`, `simulacao-${c.id}.pdf`) } finally { setBusy(null) } }}>
                      PDF
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}
