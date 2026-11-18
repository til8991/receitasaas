import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthContext'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { cnpj, date } from '../lib/format'
import type { AssumptionDefinition, Office, PlanSummary, User } from '../lib/types'
import { ErrorBanner, Field, Loading, Notice, PageHeader, Spinner } from '../components/ui'

type Tab = 'escritorio' | 'premissas' | 'usuarios' | 'plano'

interface OfficeResponse {
  office: Office
  assumptions: Record<string, unknown>
  assumption_definitions: Record<string, AssumptionDefinition>
}

export default function Settings() {
  const { isAdmin } = useAuth()
  const [tab, setTab] = useState<Tab>('escritorio')
  const tabs: [Tab, string][] = [['escritorio', 'Escritório'], ['premissas', 'Premissas padrão'], ['plano', 'Plano']]
  if (isAdmin) tabs.splice(2, 0, ['usuarios', 'Usuários'])

  return (
    <>
      <PageHeader title="Configurações" />
      {!isAdmin && <div className="mb-4"><Notice>Somente administradores podem alterar as configurações.</Notice></div>}
      <div role="tablist" className="mb-6 flex flex-wrap gap-1 border-b border-rule">
        {tabs.map(([k, l]) => (
          <button key={k} role="tab" aria-selected={tab === k} onClick={() => setTab(k)}
            className={`-mb-px border-b-2 px-4 py-2 text-sm font-semibold ${tab === k ? 'border-brand' : 'border-transparent text-muted hover:text-ink'}`}>{l}</button>
        ))}
      </div>
      {tab === 'escritorio' && <OfficeTab />}
      {tab === 'premissas' && <AssumptionsTab />}
      {tab === 'usuarios' && isAdmin && <UsersTab />}
      {tab === 'plano' && <PlanTab />}
    </>
  )
}

function useOffice() {
  return useQuery({ queryKey: ['office'], queryFn: async () => (await api.get<OfficeResponse>('/office')).data })
}

function OfficeTab() {
  const { isAdmin, refresh } = useAuth()
  const { data, isLoading } = useOffice()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ name: '', email: '', phone: '', primary_color: '#0B5D4B', secondary_color: '#F2A900' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    if (data) setForm({ name: data.office.name, email: data.office.email ?? '', phone: data.office.phone ?? '', primary_color: data.office.primary_color, secondary_color: data.office.secondary_color })
  }, [data])

  const save = useMutation({
    mutationFn: () => api.put('/office', form),
    onSuccess: async () => { setSaved(true); await refresh(); void queryClient.invalidateQueries({ queryKey: ['office'] }) },
    onError: (e) => setErrors(fieldErrors(e)),
  })
  const logo = useMutation({
    mutationFn: (file: File) => { const b = new FormData(); b.append('logo', file); return api.post('/office/logo', b) },
    onSuccess: async () => { await refresh(); void queryClient.invalidateQueries({ queryKey: ['office'] }) },
  })

  if (isLoading || !data) return <Loading />
  const submit = (e: FormEvent) => { e.preventDefault(); setErrors({}); setSaved(false); save.mutate() }

  return (
    <div className="grid max-w-4xl gap-10 lg:grid-cols-[1fr_260px]">
      <form onSubmit={submit} className="space-y-4" noValidate>
        <Field label="Nome do escritório" htmlFor="o-name" error={errors.name}>
          <input id="o-name" className="input" disabled={!isAdmin} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </Field>
        <Field label="CNPJ" htmlFor="o-cnpj"><input id="o-cnpj" className="input figure" disabled value={cnpj(data.office.cnpj)} /></Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="E-mail no relatório" htmlFor="o-email" error={errors.email}>
            <input id="o-email" type="email" className="input" disabled={!isAdmin} value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </Field>
          <Field label="Telefone no relatório" htmlFor="o-phone" error={errors.phone}>
            <input id="o-phone" className="input" disabled={!isAdmin} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          {(['primary_color', 'secondary_color'] as const).map((k) => (
            <Field key={k} label={k === 'primary_color' ? 'Cor principal' : 'Cor de destaque'} htmlFor={k} error={errors[k]}
              hint={k === 'primary_color' ? 'Usada no app e no PDF.' : 'Usada no gráfico e no destaque do PDF.'}>
              <div className="flex gap-2">
                <input type="color" aria-label="Escolher cor" className="h-10 w-12 cursor-pointer rounded border border-rule bg-sheet" disabled={!isAdmin} value={form[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value.toUpperCase() })} />
                <input id={k} className="input figure" disabled={!isAdmin} value={form[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} />
              </div>
            </Field>
          ))}
        </div>
        {isAdmin && (
          <div className="flex items-center gap-3 pt-2">
            <button className="btn-primary" disabled={save.isPending}>{save.isPending && <Spinner small />} Salvar escritório</button>
            {saved && <span className="text-sm text-migrate">Escritório salvo.</span>}
          </div>
        )}
      </form>

      <div>
        <div className="label">Logo</div>
        <div className="sheet flex h-32 items-center justify-center p-4">
          {data.office.logo_url ? <img src={data.office.logo_url} alt="Logo do escritório" className="max-h-full max-w-full object-contain" /> : <span className="text-sm text-muted">Sem logo</span>}
        </div>
        {isAdmin && (
          <label className="btn-secondary mt-3 w-full cursor-pointer">
            {logo.isPending ? <Spinner small /> : null} Enviar logo (PNG ou JPG, até 2 MB)
            <input type="file" accept="image/png,image/jpeg" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) logo.mutate(f); e.target.value = '' }} />
          </label>
        )}
        {logo.isError && <p className="mt-2 text-sm text-danger">{errorMessage(logo.error)}</p>}
      </div>
    </div>
  )
}

function AssumptionsTab() {
  const { isAdmin } = useAuth()
  const { data, isLoading } = useOffice()
  const queryClient = useQueryClient()
  const [values, setValues] = useState<Record<string, unknown>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saved, setSaved] = useState(false)

  useEffect(() => { if (data) setValues(structuredClone(data.assumptions)) }, [data])

  const save = useMutation({
    mutationFn: () => api.put('/office', { assumptions: values }),
    onSuccess: () => { setSaved(true); void queryClient.invalidateQueries({ queryKey: ['office'] }) },
    onError: (e) => setErrors(fieldErrors(e)),
  })

  if (isLoading || !data) return <Loading />
  const defs = data.assumption_definitions
  const set = (key: string, v: unknown) => { setSaved(false); setValues((s) => ({ ...s, [key]: v })) }

  return (
    <form onSubmit={(e) => { e.preventDefault(); setErrors({}); save.mutate() }} className="max-w-4xl space-y-8" noValidate>
      <Notice>Estas premissas não vêm das calculadoras oficiais. Valem para todos os clientes, exceto quando o cliente tem premissa própria, e ficam registradas em cada simulação e no PDF. Detalhes em docs/premissas.md.</Notice>
      {save.isError && Object.keys(errors).length === 0 && <ErrorBanner>{errorMessage(save.error)}</ErrorBanner>}

      <div className="grid gap-5 sm:grid-cols-2">
        {Object.entries(defs).filter(([, d]) => d.type === 'percent' || d.type === 'rate_nullable' || d.type === 'year').map(([key, d]) => (
          <Field key={key} label={`${d.id} — ${d.label}`} htmlFor={key} error={errors[`assumptions.${key}`]} hint={d.help}>
            <input id={key} className="input figure" inputMode="decimal" disabled={!isAdmin}
              value={values[key] === null || values[key] === undefined ? '' : String(values[key])}
              onChange={(e) => set(key, e.target.value === '' ? null : e.target.value)} />
          </Field>
        ))}
      </div>

      <YearTable title={`${defs.nominal_rates?.id} — ${defs.nominal_rates?.label}`} help={defs.nominal_rates?.help}
        rows={(values.nominal_rates ?? {}) as Record<string, Record<string, number>>} columns={[['cbs', 'CBS'], ['ibs_estadual', 'IBS UF'], ['ibs_municipal', 'IBS município']]}
        disabled={!isAdmin} onChange={(rows) => set('nominal_rates', rows)} errors={errors} errorKey="assumptions.nominal_rates" />

      <YearTable title={`${defs.simples_ibs_transition?.id} — ${defs.simples_ibs_transition?.label}`} help={defs.simples_ibs_transition?.help}
        rows={Object.fromEntries(Object.entries((values.simples_ibs_transition ?? {}) as Record<string, number>).map(([y, f]) => [y, { fator: f }]))}
        columns={[['fator', 'Fator (0 a 1)']]} disabled={!isAdmin}
        onChange={(rows) => set('simples_ibs_transition', Object.fromEntries(Object.entries(rows).map(([y, r]) => [y, r.fator])))}
        errors={errors} errorKey="assumptions.simples_ibs_transition" />

      {isAdmin && (
        <div className="flex items-center gap-3">
          <button className="btn-primary" disabled={save.isPending}>{save.isPending && <Spinner small />} Salvar premissas</button>
          {saved && <span className="text-sm text-migrate">Premissas salvas. Rode as simulações de novo para aplicar.</span>}
        </div>
      )}
    </form>
  )
}

function YearTable({ title, help, rows, columns, disabled, onChange, errors, errorKey }: {
  title: string; help?: string; rows: Record<string, Record<string, number | string>>; columns: [string, string][]
  disabled: boolean; onChange: (rows: Record<string, Record<string, number | string>>) => void; errors: Record<string, string>; errorKey: string
}) {
  const years = Object.keys(rows).sort()
  const fieldError = Object.entries(errors).find(([k]) => k.startsWith(errorKey))?.[1]
  return (
    <fieldset>
      <legend className="font-semibold">{title}</legend>
      {help && <p className="mb-2 text-sm text-muted">{help}</p>}
      <div className="sheet overflow-x-auto">
        <table className="table-ledger">
          <thead><tr><th>Ano</th>{columns.map(([, l]) => <th key={l} className="text-right">{l}</th>)}</tr></thead>
          <tbody>
            {years.map((y) => (
              <tr key={y}>
                <td className="figure font-semibold">{y}</td>
                {columns.map(([k]) => (
                  <td key={k} className="text-right">
                    <input aria-label={`${k} ${y}`} className="input figure w-28 py-1 text-right" inputMode="decimal" disabled={disabled}
                      value={String(rows[y]?.[k] ?? '')}
                      onChange={(e) => onChange({ ...rows, [y]: { ...rows[y], [k]: e.target.value } })} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {fieldError && <p className="mt-1 text-sm text-danger">{fieldError}</p>}
    </fieldset>
  )
}

function UsersTab() {
  const { user: me } = useAuth()
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['users'], queryFn: async () => (await api.get<{ data: User[] }>('/users')).data.data })
  const [form, setForm] = useState({ name: '', email: '', role: 'collaborator', password: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const create = useMutation({
    mutationFn: () => api.post('/users', form),
    onSuccess: () => { setForm({ name: '', email: '', role: 'collaborator', password: '' }); void queryClient.invalidateQueries({ queryKey: ['users'] }) },
    onError: (e) => setErrors(fieldErrors(e)),
  })
  const update = useMutation({
    mutationFn: ({ id, role }: { id: number; role: string }) => api.put(`/users/${id}`, { role }),
    onSettled: () => void queryClient.invalidateQueries({ queryKey: ['users'] }),
  })
  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/users/${id}`),
    onSettled: () => void queryClient.invalidateQueries({ queryKey: ['users'] }),
  })

  if (isLoading) return <Loading />
  const mutationError = update.error ?? remove.error

  return (
    <div className="grid max-w-5xl gap-10 lg:grid-cols-[1.3fr_1fr]">
      <section>
        {mutationError && <div className="mb-3"><ErrorBanner>{errorMessage(mutationError)}</ErrorBanner></div>}
        <div className="sheet overflow-x-auto">
          <table className="table-ledger">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th /></tr></thead>
            <tbody>
              {data?.map((u) => (
                <tr key={u.id}>
                  <td className="font-medium">{u.name}{u.id === me?.id ? ' (você)' : ''}</td>
                  <td className="text-ink-soft">{u.email}</td>
                  <td>
                    <select aria-label={`Papel de ${u.name}`} className="input w-auto py-1" value={u.role} onChange={(e) => update.mutate({ id: u.id, role: e.target.value })}>
                      <option value="admin">Administrador</option>
                      <option value="collaborator">Colaborador</option>
                    </select>
                  </td>
                  <td className="text-right">
                    {u.id !== me?.id && <button className="btn-ghost text-sm text-danger" onClick={() => { if (window.confirm(`Remover ${u.name}?`)) remove.mutate(u.id) }}>Remover</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); setErrors({}); create.mutate() }} noValidate>
        <h2 className="text-lg font-bold">Adicionar usuário</h2>
        <Field label="Nome" htmlFor="u-name" error={errors.name}><input id="u-name" className="input" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
        <Field label="E-mail" htmlFor="u-email" error={errors.email}><input id="u-email" type="email" className="input" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></Field>
        <Field label="Papel" htmlFor="u-role" error={errors.role}>
          <select id="u-role" className="input" value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            <option value="collaborator">Colaborador: cadastra clientes e simula</option>
            <option value="admin">Administrador: também gerencia usuários, premissas e plano</option>
          </select>
        </Field>
        <Field label="Senha inicial" htmlFor="u-pass" error={errors.password} hint="Mínimo de 8 caracteres, com letras e números.">
          <input id="u-pass" type="password" autoComplete="new-password" className="input" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
        </Field>
        <button className="btn-primary" disabled={create.isPending}>{create.isPending && <Spinner small />} Adicionar usuário</button>
      </form>
    </div>
  )
}

function PlanTab() {
  const { isAdmin } = useAuth()
  const queryClient = useQueryClient()
  const { data: plan, isLoading } = useQuery({ queryKey: ['billing'], queryFn: async () => (await api.get<{ data: PlanSummary }>('/billing')).data.data })
  const change = useMutation({
    mutationFn: (key: string) => api.post<{ checkout_url: string | null }>('/billing/subscribe', { plan: key }),
    onSuccess: (res) => {
      if (res.data.checkout_url) window.location.assign(res.data.checkout_url)
      void queryClient.invalidateQueries({ queryKey: ['billing'] })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })

  if (isLoading || !plan) return <Loading />

  return (
    <div className="max-w-4xl space-y-6">
      <p className="text-ink-soft">
        Plano atual: <strong className="text-ink">{plan.name}</strong>, {plan.active_clients}
        {plan.max_active_clients ? ` de ${plan.max_active_clients}` : ''} CNPJs ativos.
        {plan.status === 'trialing' && plan.trial_ends_at && <> Período de teste até {date(plan.trial_ends_at)}.</>}
      </p>
      {change.isError && <ErrorBanner>{errorMessage(change.error)}</ErrorBanner>}
      <div className="grid gap-4 sm:grid-cols-3">
        {plan.available_plans.map((p) => {
          const current = p.key === plan.plan
          return (
            <div key={p.key} className={`sheet flex flex-col p-5 ${current ? 'border-brand ring-1 ring-brand' : ''}`}>
              <div className="text-lg font-bold">{p.name}</div>
              <div className="mt-1 text-ink-soft">{p.max_active_clients ? `Até ${p.max_active_clients} CNPJs ativos` : 'CNPJs ilimitados'}</div>
              <div className="figure mt-4 text-2xl font-bold">{(p.price_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}<span className="text-sm font-normal text-muted">/mês</span></div>
              <div className="mt-auto pt-5">
                {current ? <span className="text-sm font-semibold text-brand">Plano atual</span> : isAdmin && (
                  <button className="btn-secondary w-full" disabled={change.isPending} onClick={() => change.mutate(p.key)}>Mudar para {p.name}</button>
                )}
              </div>
            </div>
          )
        })}
      </div>
      <p className="text-sm text-muted">A cobrança online será habilitada em breve. Por enquanto a troca de plano é aplicada imediatamente, sem cobrança.</p>
    </div>
  )
}
