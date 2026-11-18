import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { errorMessage } from '../lib/api'
import { ErrorBanner, Field, Spinner } from '../components/ui'
import { AuthShell } from './AuthShell'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(errorMessage(err, 'Não foi possível entrar.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell title="Entrar" subtitle="Acesse o painel do seu escritório.">
      <form onSubmit={submit} className="space-y-4" noValidate>
        {error && <ErrorBanner>{error}</ErrorBanner>}
        <Field label="E-mail" htmlFor="email">
          <input id="email" type="email" autoComplete="email" required className="input" value={email} onChange={(e) => setEmail(e.target.value)} />
        </Field>
        <Field label="Senha" htmlFor="password">
          <input id="password" type="password" autoComplete="current-password" required className="input" value={password} onChange={(e) => setPassword(e.target.value)} />
        </Field>
        <button type="submit" className="btn-primary w-full py-2.5" disabled={busy}>
          {busy && <Spinner small />} Entrar
        </button>
      </form>
      <p className="mt-6 text-sm text-ink-soft">
        Escritório novo? <Link to="/cadastro" className="font-semibold text-brand underline-offset-2 hover:underline">Cadastre seu escritório</Link>
      </p>
    </AuthShell>
  )
}
