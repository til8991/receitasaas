import { useState } from 'react'
import { Link } from 'react-router-dom'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api, download, errorMessage } from '../lib/api'
import { cnpj, dateTime, displayName, money } from '../lib/format'
import type { Comparison, Paginated } from '../lib/types'
import { EmptyState, ErrorBanner, Loading, PageHeader, Pagination, RecommendationBadge, Spinner } from '../components/ui'

export default function Reports() {
  const [page, setPage] = useState(1)
  const [busy, setBusy] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['comparisons', 'reports', page],
    queryFn: async () => (await api.get<Paginated<Comparison>>('/comparisons', { params: { page, per_page: 25 } })).data,
    placeholderData: keepPreviousData,
  })
  const rows = data?.data.filter((c) => c.status === 'completed') ?? []

  const get = async (c: Comparison) => {
    setBusy(c.id)
    setError(null)
    try { await download(`/comparisons/${c.id}/report`, `simulacao-${c.id}.pdf`) } catch (err) { setError(errorMessage(err, 'Não foi possível gerar o PDF.')) } finally { setBusy(null) }
  }

  return (
    <>
      <PageHeader title="Relatórios" description="PDF por cliente com a marca do escritório, os dois cenários, a recomendação e as premissas usadas." />
      {error && <div className="mb-4"><ErrorBanner>{error}</ErrorBanner></div>}
      {isLoading ? <Loading /> : rows.length === 0 ? (
        <EmptyState title="Nenhum relatório disponível" action={<Link to="/simulacoes/nova" className="btn-primary">Nova simulação</Link>}>
          Os relatórios ficam disponíveis assim que uma simulação é concluída.
        </EmptyState>
      ) : (
        <>
          <div className="sheet overflow-x-auto">
            <table className="table-ledger">
              <thead><tr><th>Cliente</th><th>CNPJ</th><th>Ano</th><th>Resultado</th><th className="text-right">Diferença</th><th>Calculado em</th><th /></tr></thead>
              <tbody>
                {rows.map((c) => (
                  <tr key={c.id}>
                    <td className="max-w-[240px] truncate"><Link to={`/simulacoes/${c.id}`} className="font-medium hover:text-brand">{displayName(c.client)}</Link></td>
                    <td className="figure whitespace-nowrap">{cnpj(c.client?.cnpj)}</td>
                    <td className="figure">{c.reference_year}</td>
                    <td><RecommendationBadge value={c.recommendation} /></td>
                    <td className="figure text-right">{c.result ? money(c.result.diferenca.valor) : '—'}</td>
                    <td className="whitespace-nowrap text-muted">{dateTime(c.calculated_at)}</td>
                    <td className="text-right">
                      <button className="btn-secondary py-1.5 text-sm" onClick={() => void get(c)} disabled={busy === c.id}>
                        {busy === c.id && <Spinner small />} Baixar PDF
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data!.current_page} last={data!.last_page} onChange={setPage} />
        </>
      )}
    </>
  )
}
