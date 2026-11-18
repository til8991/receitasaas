import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { errorMessage, fieldErrors } from '../lib/api'
import { maskCnpj } from '../lib/format'
import { ErrorBanner, Field, Spinner } from '../components/ui'
import { AuthShell } from './AuthShell'

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ office_name: '', office_cnpj: '', name: '', email: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const set = (k: keyof typeof form) => (v: string) => setForm((f) => ({ ...f, [k]: v }))

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setErrors({})
    setError(null)
    try {
      await register(form)
      navigate('/')
    } catch (err) {
      setErrors(fieldErrors(err))
      setError(errorMessage(err, 'Não foi possível concluir o cadastro.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell title="Cadastre seu escritório" subtitle="14 dias de teste com até 10 CNPJs ativos. Sem cartão.">
      <form onSubmit={submit} className="space-y-4" noValidate>
        {error && Object.keys(errors).length === 0 && <ErrorBanner>{error}</ErrorBanner>}
        <Field label="Nome do escritório" htmlFor="office_name" error={errors.office_name}>
          <input id="office_name" className="input" value={form.office_name} onChange={(e) => set('office_name')(e.target.value)} />
        </Field>
        <Field label="CNPJ do escritório" htmlFor="office_cnpj" error={errors.office_cnpj}>
          <input id="office_cnpj" className="input" inputMode="text" value={form.office_cnpj} onChange={(e) => set('office_cnpj')(maskCnpj(e.target.value))} placeholder="00.000.000/0000-00" />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Seu nome" htmlFor="name" error={errors.name}>
            <input id="name" autoComplete="name" className="input" value={form.name} onChange={(e) => set('name')(e.target.value)} />
          </Field>
          <Field label="E-mail" htmlFor="email" error={errors.email}>
            <input id="email" type="email" autoComplete="email" className="input" value={form.email} onChange={(e) => set('email')(e.target.value)} />
          </Field>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Senha" htmlFor="password" error={errors.password} hint="Mínimo de 8 caracteres, com letras e números.">
            <input id="password" type="password" autoComplete="new-password" className="input" value={form.password} onChange={(e) => set('password')(e.target.value)} />
          </Field>
          <Field label="Confirme a senha" htmlFor="password_confirmation">
            <input id="password_confirmation" type="password" autoComplete="new-password" className="input" value={form.password_confirmation} onChange={(e) => set('password_confirmation')(e.target.value)} />
          </Field>
        </div>
        <button type="submit" className="btn-primary w-full py-2.5" disabled={busy}>
          {busy && <Spinner small />} Criar conta do escritório
        </button>
      </form>
      <p className="mt-6 text-sm text-ink-soft">
        Já tem conta? <Link to="/entrar" className="font-semibold text-brand underline-offset-2 hover:underline">Entrar</Link>
      </p>
    </AuthShell>
  )
}
