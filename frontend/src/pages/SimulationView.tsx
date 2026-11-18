import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, download, errorMessage } from '../lib/api'
import { date, dateTime, displayName, money } from '../lib/format'
import type { Comparison, Scenario, SimulationDetail } from '../lib/types'
import { Razonete } from '../components/Razonete'
import { ScenarioChart } from '../components/ScenarioChart'
import { ErrorBanner, Loading, Notice, PageHeader, Spinner } from '../components/ui'

type Tab = 'itens' | 'erros' | 'premissas' | 'auditoria'
const scenarioName: Record<Scenario, string> = { simples_integral: 'Simples integral', ibs_cbs_regular: 'IBS/CBS regular' }

export default function SimulationView() {
  const { id } = useParams()
  const queryClient = useQueryClient()
  const [tab, setTab] = useState<Tab>('itens')
  const [pdfBusy, setPdfBusy] = useState(false)
  const [pdfError, setPdfError] = useState<string | null>(null)

  const { data: c, isLoading, error } = useQuery({
    queryKey: ['comparison', id],
    queryFn: async () => (await api.get<{ data: Comparison }>(`/comparisons/${id}`)).data.data,
    refetchInterval: (q) => (q.state.data && ['pending', 'running'].includes(q.state.data.status) ? 1500 : false),
  })

  const retry = useMutation({
    mutationFn: () => api.post(`/comparisons/${id}/retry`),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['comparison', id] }),
  })

  if (isLoading) return <Loading />
  if (error || !c) return <ErrorBanner>Simulação não encontrada.</ErrorBanner>

  const pdf = async () => {
    setPdfBusy(true)
    setPdfError(null)
    try {
      await download(`/comparisons/${c.id}/report`, `simulacao-${c.id}.pdf`)
    } catch (err) {
      setPdfError(errorMessage(err, 'Não foi possível gerar o PDF.'))
    } finally {
      setPdfBusy(false)
    }
  }

  const r = c.result
  const sims = c.simulations ?? {}
  const errorsCount = Object.values(sims).reduce((n, s) => n + (s?.item_errors?.length ?? 0), 0)

  return (
    <>
      <PageHeader
        title={`${displayName(c.client)} — ${c.reference_year}`}
        back={{ to: `/clientes/${c.client_id}`, label: 'Voltar ao cliente' }}
        description={r ? (r.periodo.origem === 'manual'
          ? 'Base: valores informados e classificação padrão do cliente.'
          : `Base: notas fiscais de ${date(r.periodo.inicio)} a ${date(r.periodo.fim)}.`) : undefined}
        actions={c.status === 'completed' ? (
          <>
            <Link to={`/simulacoes/nova?cliente=${c.client_id}`} className="btn-secondary">Simular de novo</Link>
            <button className="btn-primary" onClick={() => void pdf()} disabled={pdfBusy}>{pdfBusy && <Spinner small />} Baixar PDF</button>
          </>
        ) : undefined}
      />

      {pdfError && <div className="mb-4"><ErrorBanner>{pdfError}</ErrorBanner></div>}

      {(c.status === 'pending' || c.status === 'running') && (
        <div className="sheet flex items-center gap-4 p-6">
          <Spinner />
          <div>
            <div className="font-semibold">Calculando nas calculadoras oficiais…</div>
            <div className="text-sm text-muted">Os dois cenários são enviados à calculadora do Simples Nacional e à do regime regular.</div>
          </div>
        </div>
      )}

      {c.status === 'failed' && (
        <div className="space-y-3">
          <ErrorBanner>{c.error_message ?? 'A simulação falhou.'}</ErrorBanner>
          <div className="flex gap-2">
            <button className="btn-primary" onClick={() => retry.mutate()} disabled={retry.isPending}>Tentar de novo</button>
            <Link to={`/clientes/${c.client_id}/editar`} className="btn-secondary">Revisar cadastro do cliente</Link>
          </div>
        </div>
      )}

      {c.status === 'completed' && r && (
        <div className="space-y-8">
          <Razonete result={r} />

          {r.avisos.length > 0 && (
            <Notice tone="warn">
              <ul className="list-disc space-y-1 pl-4">{r.avisos.map((a) => <li key={a}>{a}</li>)}</ul>
            </Notice>
          )}

          <div className="grid gap-8 lg:grid-cols-[1.1fr_1fr]">
            <ScenarioChart result={r} />
            <section>
              <h2 className="mb-3 text-lg font-bold">Por que essa recomendação</h2>
              <ul className="space-y-2.5 text-[15px] leading-relaxed text-ink-soft">
                {r.justificativa.map((j) => <li key={j} className="border-l-2 border-rule pl-3">{j}</li>)}
              </ul>
            </section>
          </div>

          <section>
            <div role="tablist" className="mb-4 flex flex-wrap gap-1 border-b border-rule">
              {([['itens', 'Itens'], ['erros', `Erros da calculadora (${errorsCount})`], ['premissas', 'Premissas'], ['auditoria', 'Auditoria']] as const).map(([k, l]) => (
                <button key={k} role="tab" aria-selected={tab === k} onClick={() => setTab(k)}
                  className={`-mb-px border-b-2 px-4 py-2 text-sm font-semibold ${tab === k ? 'border-brand' : 'border-transparent text-muted hover:text-ink'}`}>{l}</button>
              ))}
            </div>
            {tab === 'itens' && <ItemsTable sims={sims} />}
            {tab === 'erros' && <ErrorsTable sims={sims} />}
            {tab === 'premissas' && (
              <div className="sheet overflow-x-auto">
                <table className="table-ledger">
                  <thead><tr><th>ID</th><th>Premissa</th><th>Valor usado</th></tr></thead>
                  <tbody>{c.assumptions?.lista.map((p) => (
                    <tr key={p.id + p.descricao}><td className="font-semibold">{p.id}</td><td>{p.descricao}</td><td className="text-ink-soft">{p.valor}</td></tr>
                  ))}</tbody>
                </table>
                <p className="px-3 py-3 text-xs text-muted">Altere os padrões em Configurações ou no cadastro do cliente e rode a simulação de novo.</p>
              </div>
            )}
            {tab === 'auditoria' && <Audit comparison={c} />}
          </section>
        </div>
      )}
    </>
  )
}

function ItemsTable({ sims }: { sims: Partial<Record<Scenario, SimulationDetail>> }) {
  const a = sims.simples_integral?.totals.items ?? []
  const b = new Map((sims.ibs_cbs_regular?.totals.items ?? []).map((i) => [i.nObj, i]))
  if (!a.length) return <p className="text-ink-soft">Sem itens.</p>
  return (
    <div className="sheet overflow-x-auto">
      <table className="table-ledger">
        <thead>
          <tr><th>#</th><th>Item (NCM/NBS)</th><th>Venda</th><th className="text-right">Base</th><th className="text-right">IBS/CBS Simples</th><th className="text-right">IBS/CBS regular</th></tr>
        </thead>
        <tbody>
          {a.map((i) => {
            const reg = b.get(i.nObj)
            return (
              <tr key={i.nObj}>
                <td className="figure text-muted">{i.nObj}</td>
                <td className="max-w-[320px] truncate" title={i.descricao}>{i.descricao}</td>
                <td>{i.b2b ? 'B2B' : 'B2C'}</td>
                <td className="figure text-right">{money(i.base)}</td>
                <td className="figure text-right">{i.estado === 'CALCULADO' ? money(i.ibs_cbs) : <span className="text-xs text-warn">{i.estado}</span>}</td>
                <td className="figure text-right">{reg && (reg.estado === 'CALCULADO' || reg.estado === 'CALCULO_SIMULADO') ? money(reg.ibs_cbs) : <span className="text-xs text-warn">{reg?.estado ?? '—'}</span>}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

function ErrorsTable({ sims }: { sims: Partial<Record<Scenario, SimulationDetail>> }) {
  const rows = (Object.entries(sims) as [Scenario, SimulationDetail][]).flatMap(([scenario, s]) =>
    (s.item_errors ?? []).flatMap((e) => (e.erros.length ? e.erros : [{ code: e.estadoItem }]).map((err) => ({ scenario, e, err }))))
  if (!rows.length) return <p className="text-ink-soft">Nenhum item com erro: todos foram calculados pelas calculadoras oficiais.</p>
  return (
    <div className="sheet overflow-x-auto">
      <table className="table-ledger">
        <thead><tr><th>Cenário</th><th>Item</th><th>Situação</th><th>Código</th><th>Detalhe</th></tr></thead>
        <tbody>
          {rows.map(({ scenario, e, err }, n) => (
            <tr key={n}>
              <td>{scenarioName[scenario]}{e.origem === 'compras' ? ' (compras)' : ''}</td>
              <td className="figure">{e.nObj}</td>
              <td className="text-xs">{e.estadoItem}</td>
              <td className="font-semibold">{err.code}</td>
              <td className="text-ink-soft">{err.detail ?? err.title}{err.pointer ? <span className="block text-xs text-muted">{err.pointer}</span> : null}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <p className="px-3 py-3 text-xs text-muted">Itens com erro não entram nos totais. Ajuste a NCM/NBS ou o cClassTrib e rode a simulação de novo.</p>
    </div>
  )
}

function Audit({ comparison }: { comparison: Comparison }) {
  const [busy, setBusy] = useState<Scenario | null>(null)
  const save = async (scenario: Scenario) => {
    setBusy(scenario)
    try {
      const { data } = await api.get(`/comparisons/${comparison.id}/audit/${scenario}`)
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      const a = document.createElement('a')
      a.href = URL.createObjectURL(blob)
      a.download = `auditoria-${comparison.id}-${scenario}.json`
      a.click()
      URL.revokeObjectURL(a.href)
    } finally {
      setBusy(null)
    }
  }
  const sims = comparison.simulations ?? {}

  return (
    <div className="space-y-4">
      <div className="sheet overflow-x-auto">
        <table className="table-ledger">
          <thead><tr><th>Cenário</th><th>Calculadora</th><th>Versão do app</th><th>Base de dados</th><th>Calculado em</th><th /></tr></thead>
          <tbody>
            {(Object.entries(sims) as [Scenario, SimulationDetail][]).flatMap(([scenario, s]) =>
              Object.entries(s.calculator_version ?? {}).map(([calc, v]) => (
                <tr key={scenario + calc}>
                  <td>{scenarioName[scenario]}</td>
                  <td>{calc === 'simples_nacional' ? 'Simples Nacional' : 'Regime regular'}</td>
                  <td className="figure">{v.versaoApp ?? '—'}</td>
                  <td className="figure">{v.versaoDb ?? '—'} {v.dataVersaoDb ? `(${v.dataVersaoDb})` : ''}</td>
                  <td>{dateTime(s.calculated_at)}</td>
                  <td className="text-right">
                    <button className="btn-ghost text-sm" onClick={() => void save(scenario)} disabled={busy === scenario}>Baixar JSON</button>
                  </td>
                </tr>
              )))}
          </tbody>
        </table>
      </div>
      <p className="text-sm text-muted">O JSON traz o payload enviado e a resposta bruta de cada calculadora oficial, para conferência.</p>
    </div>
  )
}
