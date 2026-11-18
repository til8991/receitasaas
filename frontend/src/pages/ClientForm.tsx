import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { maskCnpj } from '../lib/format'
import type { Client } from '../lib/types'
import { ErrorBanner, Field, Loading, PageHeader, Spinner } from '../components/ui'

const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO']
const ANNEXES = [
  ['I', 'I — Comércio'], ['II', 'II — Indústria'], ['III', 'III — Serviços'], ['IV', 'IV — Serviços (CPP fora)'], ['V', 'V — Serviços (fator R < 28%)'],
]

/** GET /calculadora/simples-nacional/atividades (AtividadeSimplesDomain) */
type Atividade = { codigo: string; descricao: string; anexoSemFatorR?: string; anexoComFatorR?: string; rotuloCurtoAnexo?: string }
const ANNEX_KEYS = ['I', 'II', 'III', 'IV', 'V']

type FormState = {
  cnpj: string; legal_name: string; trade_name: string; uf: string; municipality_ibge: string
  current_regime: string; simples_annex: string; activity: string
  revenue_12m: string; purchases_12m: string; b2b_sales_pct: string
  default_ncm: string; default_nbs: string; default_cst: string; default_c_class_trib: string
  active: boolean; notes: string
  credit_pass_through_pct: string; regular_suppliers_pct: string; p_ibs_sn: string; p_cbs_sn: string
}

const empty: FormState = {
  cnpj: '', legal_name: '', trade_name: '', uf: '', municipality_ibge: '', current_regime: 'simples_nacional',
  simples_annex: '', activity: '', revenue_12m: '', purchases_12m: '', b2b_sales_pct: '0',
  default_ncm: '', default_nbs: '', default_cst: '000', default_c_class_trib: '000001', active: true, notes: '',
  credit_pass_through_pct: '', regular_suppliers_pct: '', p_ibs_sn: '', p_cbs_sn: '',
}

export default function ClientForm() {
  const { id } = useParams()
  const editing = Boolean(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [advanced, setAdvanced] = useState(false)

  const { data: client, isLoading } = useQuery({
    queryKey: ['client', id],
    enabled: editing,
    queryFn: async () => (await api.get<{ data: Client }>(`/clients/${id}`)).data.data,
  })

  const { data: atividades } = useQuery({
    queryKey: ['atividades'],
    queryFn: async () => (await api.get<{ data: Atividade[] }>('/calculadoras/atividades')).data.data,
    staleTime: 3_600_000,
    retry: false,
  })

  useEffect(() => {
    if (!client) return
    const a = (client.assumptions ?? {}) as Record<string, unknown>
    const s = (v: unknown) => (v === null || v === undefined ? '' : String(v))
    setForm({
      cnpj: maskCnpj(client.cnpj), legal_name: client.legal_name, trade_name: client.trade_name ?? '', uf: client.uf,
      municipality_ibge: String(client.municipality_ibge), current_regime: client.current_regime,
      simples_annex: client.simples_annex ?? '', activity: client.activities?.[0] ?? '',
      revenue_12m: client.revenue_12m, purchases_12m: client.purchases_12m, b2b_sales_pct: client.b2b_sales_pct,
      default_ncm: client.default_ncm ?? '', default_nbs: client.default_nbs ?? '', default_cst: client.default_cst,
      default_c_class_trib: client.default_c_class_trib, active: client.active, notes: client.notes ?? '',
      credit_pass_through_pct: s(a.credit_pass_through_pct), regular_suppliers_pct: s(a.regular_suppliers_pct),
      p_ibs_sn: s(a.p_ibs_sn), p_cbs_sn: s(a.p_cbs_sn),
    })
    setAdvanced(Object.keys(a).length > 0)
  }, [client])

  const mutation = useMutation({
    mutationFn: async () => {
      const assumptions: Record<string, number | null> = {}
      if (form.credit_pass_through_pct !== '') assumptions.credit_pass_through_pct = Number(form.credit_pass_through_pct)
      if (form.regular_suppliers_pct !== '') assumptions.regular_suppliers_pct = Number(form.regular_suppliers_pct)
      if (form.p_ibs_sn !== '' || form.p_cbs_sn !== '') {
        assumptions.p_ibs_sn = form.p_ibs_sn === '' ? null : Number(form.p_ibs_sn)
        assumptions.p_cbs_sn = form.p_cbs_sn === '' ? null : Number(form.p_cbs_sn)
      }
      const payload = {
        cnpj: form.cnpj, legal_name: form.legal_name, trade_name: form.trade_name || null, uf: form.uf,
        municipality_ibge: Number(form.municipality_ibge), current_regime: form.current_regime,
        simples_annex: form.simples_annex || null, activities: form.activity ? [form.activity.padStart(2, '0')] : [],
        revenue_12m: Number(form.revenue_12m || 0), purchases_12m: Number(form.purchases_12m || 0),
        b2b_sales_pct: Number(form.b2b_sales_pct || 0), default_ncm: form.default_ncm || null,
        default_nbs: form.default_nbs || null, default_cst: form.default_cst, default_c_class_trib: form.default_c_class_trib,
        active: form.active, notes: form.notes || null, assumptions,
      }
      const res = editing ? await api.put<{ data: Client }>(`/clients/${id}`, payload) : await api.post<{ data: Client }>('/clients', payload)
      return res.data.data
    },
    onSuccess: (saved) => {
      void queryClient.invalidateQueries({ queryKey: ['clients'] })
      void queryClient.invalidateQueries({ queryKey: ['client', String(saved.id)] })
      void queryClient.invalidateQueries({ queryKey: ['billing'] })
      navigate(`/clientes/${saved.id}`)
    },
    onError: (err) => setErrors(fieldErrors(err)),
  })

  const set = <K extends keyof FormState>(k: K) => (v: FormState[K]) => setForm((f) => ({ ...f, [k]: v }))
  const submit = (e: FormEvent) => { e.preventDefault(); setErrors({}); mutation.mutate() }

  if (editing && isLoading) return <Loading />

  return (
    <>
      <PageHeader
        title={editing ? 'Editar cliente' : 'Cadastrar cliente'}
        back={editing ? { to: `/clientes/${id}`, label: 'Voltar ao cliente' } : { to: '/clientes', label: 'Clientes' }}
      />
      <form onSubmit={submit} className="max-w-3xl space-y-8" noValidate>
        {mutation.isError && Object.keys(errors).length === 0 && <ErrorBanner>{errorMessage(mutation.error)}</ErrorBanner>}

        <fieldset className="space-y-4">
          <legend className="mb-2 text-lg font-bold">Empresa</legend>
          <div className="grid gap-4 sm:grid-cols-[220px_1fr]">
            <Field label="CNPJ" htmlFor="cnpj" error={errors.cnpj}>
              <input id="cnpj" className="input" value={form.cnpj} onChange={(e) => set('cnpj')(maskCnpj(e.target.value))} placeholder="00.000.000/0000-00" />
            </Field>
            <Field label="Razão social" htmlFor="legal_name" error={errors.legal_name}>
              <input id="legal_name" className="input" value={form.legal_name} onChange={(e) => set('legal_name')(e.target.value)} />
            </Field>
          </div>
          <Field label="Nome fantasia (opcional)" htmlFor="trade_name" error={errors.trade_name}>
            <input id="trade_name" className="input" value={form.trade_name} onChange={(e) => set('trade_name')(e.target.value)} />
          </Field>
          <div className="grid gap-4 sm:grid-cols-[120px_1fr]">
            <Field label="UF" htmlFor="uf" error={errors.uf}>
              <select id="uf" className="input" value={form.uf} onChange={(e) => set('uf')(e.target.value)}>
                <option value="">—</option>
                {UFS.map((u) => <option key={u}>{u}</option>)}
              </select>
            </Field>
            <Field label="Município (código IBGE de 7 dígitos)" htmlFor="ibge" error={errors.municipality_ibge}
              hint={<>Ex.: Porto Alegre 4314902, São Paulo 3550308. Consulte em <a className="underline" href="https://www.ibge.gov.br/explica/codigos-dos-municipios.php" target="_blank" rel="noreferrer">ibge.gov.br</a>.</>}>
              <input id="ibge" className="input" inputMode="numeric" maxLength={7} value={form.municipality_ibge}
                onChange={(e) => set('municipality_ibge')(e.target.value.replace(/\D/g, ''))} />
            </Field>
          </div>
        </fieldset>

        <fieldset className="space-y-4">
          <legend className="mb-2 text-lg font-bold">Simples Nacional</legend>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Regime atual" htmlFor="regime" error={errors.current_regime}>
              <select id="regime" className="input" value={form.current_regime} onChange={(e) => set('current_regime')(e.target.value)}>
                <option value="simples_nacional">Simples Nacional</option>
                <option value="mei">MEI</option>
                <option value="lucro_presumido">Lucro Presumido</option>
                <option value="lucro_real">Lucro Real</option>
              </select>
            </Field>
            <Field label="Anexo" htmlFor="annex" error={errors.simples_annex}>
              <select id="annex" className="input" value={form.simples_annex} onChange={(e) => set('simples_annex')(e.target.value)}>
                <option value="">—</option>
                {ANNEXES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
              </select>
            </Field>
            <Field label="Atividade do Simples (cAtivSN)" htmlFor="activity" error={errors['activities.0'] ?? errors.activities}
              hint={atividades ? 'Lista oficial da calculadora do Simples.' : 'Código de 2 dígitos (NT 2026.005).'}>
              {atividades && atividades.length > 0 ? (
                <select id="activity" className="input" value={form.activity} onChange={(e) => {
                  const chosen = atividades.find((a) => a.codigo === e.target.value)
                  set('activity')(e.target.value)
                  // Suggest the annex of the official catalogue (without fator R) when none is selected yet.
                  if (chosen?.anexoSemFatorR && ANNEX_KEYS.includes(chosen.anexoSemFatorR) && !form.simples_annex) set('simples_annex')(chosen.anexoSemFatorR)
                }}>
                  <option value="">—</option>
                  {atividades.map((a) => (
                    <option key={a.codigo} value={a.codigo}>{a.codigo} — {a.descricao}{a.rotuloCurtoAnexo ? ` (${a.rotuloCurtoAnexo})` : ''}</option>
                  ))}
                </select>
              ) : (
                <input id="activity" className="input" maxLength={2} value={form.activity} onChange={(e) => set('activity')(e.target.value.replace(/\D/g, ''))} />
              )}
            </Field>
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Faturamento 12 meses (RBT12)" htmlFor="rev" error={errors.revenue_12m}>
              <input id="rev" className="input figure" inputMode="decimal" value={form.revenue_12m} onChange={(e) => set('revenue_12m')(e.target.value)} placeholder="0,00" />
            </Field>
            <Field label="Compras 12 meses" htmlFor="pur" error={errors.purchases_12m} hint="Usado quando não há notas de entrada.">
              <input id="pur" className="input figure" inputMode="decimal" value={form.purchases_12m} onChange={(e) => set('purchases_12m')(e.target.value)} placeholder="0,00" />
            </Field>
            <Field label="Vendas a empresas (B2B) %" htmlFor="b2b" error={errors.b2b_sales_pct}>
              <input id="b2b" className="input figure" inputMode="decimal" value={form.b2b_sales_pct} onChange={(e) => set('b2b_sales_pct')(e.target.value)} />
            </Field>
          </div>
        </fieldset>

        <fieldset className="space-y-4">
          <legend className="mb-1 text-lg font-bold">Classificação padrão</legend>
          <p className="text-sm text-ink-soft">Usada na simulação manual e nos itens de notas que não trazem NCM/NBS ou o grupo IBSCBS.</p>
          <div className="grid gap-4 sm:grid-cols-4">
            <Field label="NCM padrão" htmlFor="ncm" error={errors.default_ncm}>
              <input id="ncm" className="input figure" maxLength={10} value={form.default_ncm} onChange={(e) => set('default_ncm')(e.target.value)} />
            </Field>
            <Field label="NBS padrão" htmlFor="nbs" error={errors.default_nbs}>
              <input id="nbs" className="input figure" maxLength={12} value={form.default_nbs} onChange={(e) => set('default_nbs')(e.target.value)} />
            </Field>
            <Field label="CST" htmlFor="cst" error={errors.default_cst}>
              <input id="cst" className="input figure" maxLength={3} value={form.default_cst} onChange={(e) => set('default_cst')(e.target.value)} />
            </Field>
            <Field label="cClassTrib" htmlFor="cct" error={errors.default_c_class_trib}>
              <input id="cct" className="input figure" maxLength={6} value={form.default_c_class_trib} onChange={(e) => set('default_c_class_trib')(e.target.value)} />
            </Field>
          </div>
        </fieldset>

        <fieldset>
          <button type="button" className="text-left font-bold text-brand" onClick={() => setAdvanced((a) => !a)} aria-expanded={advanced}>
            {advanced ? '▾' : '▸'} Premissas específicas deste cliente
          </button>
          {advanced && (
            <div className="mt-4 space-y-4">
              <p className="text-sm text-ink-soft">Deixe em branco para usar o padrão do escritório.</p>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Repasse do crédito ao preço (%)" htmlFor="pass" error={errors['assumptions.credit_pass_through_pct']}>
                  <input id="pass" className="input figure" inputMode="decimal" value={form.credit_pass_through_pct} onChange={(e) => set('credit_pass_through_pct')(e.target.value)} />
                </Field>
                <Field label="Compras de fornecedores do regime regular (%)" htmlFor="supp" error={errors['assumptions.regular_suppliers_pct']}>
                  <input id="supp" className="input figure" inputMode="decimal" value={form.regular_suppliers_pct} onChange={(e) => set('regular_suppliers_pct')(e.target.value)} />
                </Field>
                <Field label="Alíquota efetiva IBS no Simples (p.p.)" htmlFor="pibs" error={errors['assumptions.p_ibs_sn']} hint="Em branco: calcula pela LC 123.">
                  <input id="pibs" className="input figure" inputMode="decimal" value={form.p_ibs_sn} onChange={(e) => set('p_ibs_sn')(e.target.value)} />
                </Field>
                <Field label="Alíquota efetiva CBS no Simples (p.p.)" htmlFor="pcbs" error={errors['assumptions.p_cbs_sn']} hint="Em branco: calcula pela LC 123.">
                  <input id="pcbs" className="input figure" inputMode="decimal" value={form.p_cbs_sn} onChange={(e) => set('p_cbs_sn')(e.target.value)} />
                </Field>
              </div>
            </div>
          )}
        </fieldset>

        <fieldset className="space-y-4">
          <Field label="Observações" htmlFor="notes" error={errors.notes}>
            <textarea id="notes" rows={3} className="input" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={form.active} onChange={(e) => set('active')(e.target.checked)} className="h-4 w-4 accent-[var(--color-brand)]" />
            Cliente ativo (conta no limite de CNPJs do plano)
          </label>
        </fieldset>

        <div className="flex gap-3 border-t border-rule pt-6">
          <button type="submit" className="btn-primary" disabled={mutation.isPending}>
            {mutation.isPending && <Spinner small />} {editing ? 'Salvar alterações' : 'Cadastrar cliente'}
          </button>
          <Link to={editing ? `/clientes/${id}` : '/clientes'} className="btn-ghost">Cancelar</Link>
        </div>
      </form>
    </>
  )
}
