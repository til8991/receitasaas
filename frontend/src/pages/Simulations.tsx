import { useState } from 'react'
import { Link } from 'react-router-dom'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { dateTime, displayName, money, percent } from '../lib/format'
import type { Comparison, Paginated, Recommendation } from '../lib/types'
import { EmptyState, Loading, PageHeader, Pagination, RecommendationBadge, StatusBadge } from '../components/ui'

export default function Simulations() {
  const [page, setPage] = useState(1)
  const [reco, setReco] = useState<Recommendation | ''>('')

  const { data, isLoading } = useQuery({
    queryKey: ['comparisons', { page, reco }],
    queryFn: async () => (await api.get<Paginated<Comparison>>('/comparisons', { params: { page, recommendation: reco || undefined } })).data,
    placeholderData: keepPreviousData,
    refetchInterval: (q) => (q.state.data?.data.some((c) => c.status === 'pending' || c.status === 'running') ? 2000 : false),
  })

  return (
    <>
      <PageHeader title="Simulações" description="Todas as comparações feitas pelo escritório."
        actions={<Link to="/simulacoes/nova" className="btn-primary">Nova simulação</Link>} />

      <div className="mb-4">
        <label className="sr-only" htmlFor="reco">Resultado</label>
        <select id="reco" className="input w-auto" value={reco} onChange={(e) => { setReco(e.target.value as Recommendation | ''); setPage(1) }}>
          <option value="">Todos os resultados</option>
          <option value="migrar_regular">Compensa migrar</option>
          <option value="manter_simples">Manter no Simples</option>
          <option value="indiferente">Indiferente</option>
        </select>
      </div>

      {isLoading ? <Loading /> : !data?.data.length ? (
        <EmptyState title="Nenhuma simulação" action={<Link to="/simulacoes/nova" className="btn-primary">Nova simulação</Link>}>
          Escolha um cliente e o ano para comparar os dois cenários de IBS/CBS.
        </EmptyState>
      ) : (
        <>
          <div className="sheet overflow-x-auto">
            <table className="table-ledger">
              <thead><tr><th>Cliente</th><th>Ano</th><th>Base</th><th>Resultado</th><th className="text-right">Diferença</th><th className="text-right">% receita</th><th>Data</th></tr></thead>
              <tbody>
                {data.data.map((c) => (
                  <tr key={c.id}>
                    <td className="max-w-[240px] truncate"><Link to={`/simulacoes/${c.id}`} className="font-medium hover:text-brand">{displayName(c.client)}</Link></td>
                    <td className="figure">{c.reference_year}</td>
                    <td className="text-sm">{c.source === 'manual' ? 'Informada' : 'Notas'}</td>
                    <td>{c.status === 'completed' ? <RecommendationBadge value={c.recommendation} /> : <StatusBadge status={c.status} />}</td>
                    <td className="figure text-right">{c.result ? money(c.result.diferenca.valor) : '—'}</td>
                    <td className="figure text-right">{c.result ? percent(c.result.diferenca.percentual_receita) : '—'}</td>
                    <td className="whitespace-nowrap text-muted">{dateTime(c.created_at)}</td>
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
