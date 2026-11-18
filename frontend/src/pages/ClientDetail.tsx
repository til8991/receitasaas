import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errorMessage } from '../lib/api'
import { cnpj, date, dateTime, displayName, money, regimeLabel } from '../lib/format'
import type { Client, Comparison, InvoiceImport, Paginated } from '../lib/types'
import { Dropzone } from '../components/Dropzone'
import { ErrorBanner, Loading, Notice, PageHeader, ProgressBar, RecommendationBadge, Spinner, StatusBadge } from '../components/ui'

type Tab = 'notas' | 'simulacoes' | 'dados'

interface ItemsSummary {
  summary: Record<'saida' | 'entrada', { documents: number; items: number; total: string; first: string; last: string } | undefined>
}

export default function ClientDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [tab, setTab] = useState<Tab>('notas')

  const { data: client, isLoading, error } = useQuery({
    queryKey: ['client', id],
    queryFn: async () => (await api.get<{ data: Client }>(`/clients/${id}`)).data.data,
  })

  const remove = useMutation({
    mutationFn: () => api.delete(`/clients/${id}`),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['clients'] })
      void queryClient.invalidateQueries({ queryKey: ['billing'] })
      navigate('/clientes')
    },
  })

  if (isLoading) return <Loading />
  if (error || !client) return <ErrorBanner>Cliente não encontrado.</ErrorBanner>

  return (
    <>
      <PageHeader
        title={displayName(client)}
        back={{ to: '/clientes', label: 'Clientes' }}
        description={<>{client.legal_name} — CNPJ {cnpj(client.cnpj)} — {regimeLabel[client.current_regime]}{client.simples_annex ? `, Anexo ${client.simples_annex}` : ''}{client.active ? '' : ' (inativo)'}</>}
        actions={<>
          <Link to={`/clientes/${client.id}/editar`} className="btn-secondary">Editar</Link>
          <Link to={`/simulacoes/nova?cliente=${client.id}`} className="btn-primary">Simular</Link>
        </>}
      />

      <div role="tablist" aria-label="Seções do cliente" className="mb-6 flex gap-1 border-b border-rule">
        {([['notas', 'Notas fiscais'], ['simulacoes', 'Simulações'], ['dados', 'Dados cadastrais']] as const).map(([key, label]) => (
          <button key={key} role="tab" aria-selected={tab === key} onClick={() => setTab(key)}
            className={`-mb-px border-b-2 px-4 py-2 text-sm font-semibold ${tab === key ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink'}`}>
            {label}
          </button>
        ))}
      </div>

      {tab === 'notas' && <InvoicesTab client={client} />}
      {tab === 'simulacoes' && <ComparisonsTab clientId={client.id} />}
      {tab === 'dados' && (
        <div className="space-y-6">
          <dl className="sheet grid gap-x-8 gap-y-3 p-5 sm:grid-cols-2">
            <Item label="UF / Município (IBGE)" value={`${client.uf} / ${client.municipality_ibge}`} />
            <Item label="Atividade do Simples" value={client.activities?.join(', ') || '—'} />
            <Item label="Faturamento 12 meses" value={money(client.revenue_12m)} />
            <Item label="Compras 12 meses" value={money(client.purchases_12m)} />
            <Item label="Vendas B2B" value={`${Number(client.b2b_sales_pct).toLocaleString('pt-BR')}%`} />
            <Item label="NCM / NBS padrão" value={`${client.default_ncm ?? '—'} / ${client.default_nbs ?? '—'}`} />
            <Item label="CST / cClassTrib padrão" value={`${client.default_cst} / ${client.default_c_class_trib}`} />
            <Item label="Premissas próprias" value={client.assumptions && Object.keys(client.assumptions).length ? Object.entries(client.assumptions).map(([k, v]) => `${k}: ${v}`).join('; ') : 'Usa o padrão do escritório'} />
          </dl>
          {client.notes && <p className="max-w-[70ch] whitespace-pre-line text-ink-soft">{client.notes}</p>}
          <div className="border-t border-rule pt-6">
            <button className="btn-danger" disabled={remove.isPending}
              onClick={() => { if (window.confirm(`Excluir ${client.legal_name}? Notas importadas e simulações também serão excluídas.`)) remove.mutate() }}>
              Excluir cliente
            </button>
            {remove.isError && <p className="mt-2 text-sm text-danger">{errorMessage(remove.error)}</p>}
          </div>
        </div>
      )}
    </>
  )
}

function Item({ label, value }: { label: string; value: string }) {
  return <div><dt className="text-sm text-muted">{label}</dt><dd className="figure font-medium">{value}</dd></div>
}

function InvoicesTab({ client }: { client: Client }) {
  const queryClient = useQueryClient()
  const [uploadPct, setUploadPct] = useState<number | null>(null)
  const [uploadError, setUploadError] = useState<string | null>(null)

  const imports = useQuery({
    queryKey: ['imports', client.id],
    queryFn: async () => (await api.get<{ data: InvoiceImport[] }>(`/clients/${client.id}/imports`)).data.data,
    refetchInterval: (q) => (q.state.data?.some((i) => i.status === 'pending' || i.status === 'processing') ? 1500 : false),
  })

  const summary = useQuery({
    queryKey: ['invoice-summary', client.id],
    queryFn: async () => (await api.get<ItemsSummary>(`/clients/${client.id}/invoice-items`, { params: { per_page: 1 } })).data.summary,
  })

  // Refresh the imported base once a running import finishes.
  const wasRunning = useRef(false)
  const isRunning = Boolean(imports.data?.some((i) => i.status === 'pending' || i.status === 'processing'))
  useEffect(() => {
    if (wasRunning.current && !isRunning) {
      void queryClient.invalidateQueries({ queryKey: ['invoice-summary', client.id] })
      void queryClient.invalidateQueries({ queryKey: ['client', String(client.id)] })
    }
    wasRunning.current = isRunning
  }, [isRunning, client.id, queryClient])

  const upload = async (files: File[]) => {
    setUploadError(null)
    setUploadPct(0)
    const body = new FormData()
    files.forEach((f) => body.append('files[]', f))
    try {
      await api.post(`/clients/${client.id}/imports`, body, {
        onUploadProgress: (e) => setUploadPct(e.total ? Math.round((e.loaded * 100) / e.total) : 0),
      })
      void queryClient.invalidateQueries({ queryKey: ['imports', client.id] })
      void queryClient.invalidateQueries({ queryKey: ['invoice-summary', client.id] })
      void queryClient.invalidateQueries({ queryKey: ['client', String(client.id)] })
    } catch (err) {
      setUploadError(errorMessage(err, 'Não foi possível enviar os arquivos.'))
    } finally {
      setUploadPct(null)
    }
  }

  const clear = useMutation({
    mutationFn: () => api.delete(`/clients/${client.id}/invoice-items`),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['invoice-summary', client.id] }),
  })

  const s = summary.data
  const running = imports.data?.find((i) => i.status === 'pending' || i.status === 'processing')

  return (
    <div className="grid gap-8 lg:grid-cols-[1.2fr_1fr]">
      <section className="space-y-4">
        <Dropzone onFiles={(f) => void upload(f)} disabled={uploadPct !== null} />
        {uploadPct !== null && <ProgressBar value={uploadPct} label={`Enviando arquivos: ${uploadPct}%`} />}
        {running && <ProgressBar value={running.progress} label={`Processando ${running.processed_files} de ${running.total_files || '…'} arquivos`} />}
        {uploadError && <ErrorBanner>{uploadError}</ErrorBanner>}
        <Notice>
          Notas emitidas pelo cliente entram como <strong>vendas</strong>; notas em que ele é destinatário entram como <strong>compras</strong>.
          Destinatário com CNPJ conta como venda B2B. Notas repetidas são ignoradas.
        </Notice>

        <h3 className="pt-2 font-bold">Importações</h3>
        {imports.isLoading ? <Loading /> : !imports.data?.length ? (
          <p className="text-ink-soft">Nenhuma importação ainda.</p>
        ) : (
          <div className="sheet overflow-x-auto">
            <table className="table-ledger">
              <thead><tr><th>Data</th><th>Situação</th><th className="text-right">Arquivos</th><th className="text-right">Itens</th><th>Ocorrências</th></tr></thead>
              <tbody>
                {imports.data.map((i) => (
                  <tr key={i.id}>
                    <td className="whitespace-nowrap">{dateTime(i.created_at)}</td>
                    <td><StatusBadge status={i.status} /></td>
                    <td className="figure text-right">{i.processed_files}/{i.total_files}</td>
                    <td className="figure text-right">{i.imported_items.toLocaleString('pt-BR')}</td>
                    <td className="text-sm">
                      {i.duplicated_documents > 0 && <span className="block text-muted">{i.duplicated_documents} repetida(s)</span>}
                      {i.failed_files > 0 && (
                        <details>
                          <summary className="cursor-pointer text-danger">{i.failed_files} com erro</summary>
                          <ul className="mt-1 max-h-48 space-y-1 overflow-auto text-xs text-ink-soft">
                            {i.errors?.map((e, n) => <li key={n}><strong>{e.arquivo}</strong>: {e.erro}</li>)}
                          </ul>
                        </details>
                      )}
                      {i.failed_files === 0 && i.duplicated_documents === 0 && <span className="text-muted">—</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <aside className="space-y-4">
        <h3 className="font-bold">Base importada</h3>
        {(['saida', 'entrada'] as const).map((dir) => {
          const d = s?.[dir]
          return (
            <div key={dir} className="sheet p-4">
              <div className="text-sm text-ink-soft">{dir === 'saida' ? 'Vendas (notas emitidas)' : 'Compras (notas recebidas)'}</div>
              {summary.isLoading ? <div className="mt-2"><Spinner small /></div> : d ? (
                <>
                  <div className="figure mt-1 text-2xl font-bold">{money(d.total)}</div>
                  <div className="mt-1 text-sm text-muted">{d.documents.toLocaleString('pt-BR')} notas, {d.items.toLocaleString('pt-BR')} itens, de {date(d.first)} a {date(d.last)}</div>
                </>
              ) : <div className="mt-1 text-muted">Nenhuma nota.</div>}
            </div>
          )
        })}
        {(s?.saida || s?.entrada) && (
          <button className="btn-ghost text-sm text-danger" disabled={clear.isPending}
            onClick={() => { if (window.confirm('Apagar todas as notas importadas deste cliente?')) clear.mutate() }}>
            Apagar notas importadas
          </button>
        )}
      </aside>
    </div>
  )
}

function ComparisonsTab({ clientId }: { clientId: number }) {
  const { data, isLoading } = useQuery({
    queryKey: ['comparisons', { client: clientId }],
    queryFn: async () => (await api.get<Paginated<Comparison>>('/comparisons', { params: { client_id: clientId } })).data,
  })
  if (isLoading) return <Loading />
  if (!data?.data.length) {
    return <p className="text-ink-soft">Nenhuma simulação para este cliente. <Link className="text-brand underline" to={`/simulacoes/nova?cliente=${clientId}`}>Simular agora</Link>.</p>
  }
  return (
    <div className="sheet overflow-x-auto">
      <table className="table-ledger">
        <thead><tr><th>Ano</th><th>Base</th><th>Resultado</th><th className="text-right">Diferença</th><th>Data</th></tr></thead>
        <tbody>
          {data.data.map((c) => (
            <tr key={c.id}>
              <td className="figure"><Link className="font-medium hover:text-brand" to={`/simulacoes/${c.id}`}>{c.reference_year}</Link></td>
              <td>{c.source === 'manual' ? 'Valores informados' : 'Notas fiscais'}</td>
              <td>{c.status === 'completed' ? <RecommendationBadge value={c.recommendation} /> : <StatusBadge status={c.status} />}</td>
              <td className="figure text-right">{c.result ? money(c.result.diferenca.valor) : '—'}</td>
              <td className="text-muted">{dateTime(c.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
