import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { recommendationLabel, statusLabel } from '../lib/format'
import type { Recommendation, RunStatus } from '../lib/types'

export const DISCLAIMER = 'Simulação baseada na Calculadora oficial da Receita Federal. Não substitui análise profissional.'

export function PageHeader({ title, description, actions, back }: {
  title: string
  description?: ReactNode
  actions?: ReactNode
  back?: { to: string; label: string }
}) {
  return (
    <header className="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-rule pb-5">
      <div className="min-w-0">
        {back && (
          <Link to={back.to} className="mb-2 inline-block text-sm text-muted hover:text-ink">‹ {back.label}</Link>
        )}
        <h1 className="text-2xl font-bold text-ink sm:text-[28px]">{title}</h1>
        {description && <p className="mt-1 max-w-[70ch] text-[15px] text-ink-soft">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </header>
  )
}

export function Field({ label, error, hint, children, htmlFor }: {
  label: string
  error?: string
  hint?: ReactNode
  children: ReactNode
  htmlFor?: string
}) {
  return (
    <div>
      <label className="label" htmlFor={htmlFor}>{label}</label>
      {children}
      {error ? <p className="mt-1 text-sm text-danger">{error}</p> : hint ? <p className="mt-1 text-xs text-muted">{hint}</p> : null}
    </div>
  )
}

const recoStyles: Record<Recommendation, string> = {
  migrar_regular: 'bg-migrate/10 text-migrate border-migrate/30',
  manter_simples: 'bg-keep/10 text-keep border-keep/30',
  indiferente: 'bg-neutral/10 text-ink-soft border-neutral/30',
}

export function RecommendationBadge({ value }: { value: Recommendation | null | undefined }) {
  if (!value) return <span className="text-muted">—</span>
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${recoStyles[value]}`}>
      <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-current" />
      {recommendationLabel[value]}
    </span>
  )
}

export function StatusBadge({ status }: { status: RunStatus }) {
  const tone = status === 'completed' ? 'text-migrate' : status === 'failed' ? 'text-danger' : 'text-warn'
  return (
    <span className={`inline-flex items-center gap-1.5 text-xs font-semibold ${tone}`}>
      {(status === 'pending' || status === 'running' || status === 'processing') && <Spinner small />}
      {statusLabel[status]}
    </span>
  )
}

export function Spinner({ small = false }: { small?: boolean }) {
  return (
    <span
      role="status"
      aria-label="Carregando"
      className={`inline-block animate-spin rounded-full border-2 border-current border-r-transparent ${small ? 'h-3 w-3' : 'h-5 w-5'}`}
    />
  )
}

export function Loading({ label = 'Carregando…' }: { label?: string }) {
  return <div className="flex items-center gap-3 py-16 text-muted"><Spinner /> {label}</div>
}

export function EmptyState({ title, children, action }: { title: string; children?: ReactNode; action?: ReactNode }) {
  return (
    <div className="sheet px-6 py-12 text-center">
      <h3 className="text-lg font-semibold">{title}</h3>
      {children && <div className="mx-auto mt-2 max-w-[56ch] text-ink-soft">{children}</div>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}

export function ErrorBanner({ children }: { children: ReactNode }) {
  return <div role="alert" className="rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{children}</div>
}

export function Notice({ children, tone = 'info' }: { children: ReactNode; tone?: 'info' | 'warn' }) {
  const cls = tone === 'warn' ? 'border-warn/30 bg-warn/5 text-warn' : 'border-rule bg-sheet text-ink-soft'
  return <div className={`rounded-md border px-4 py-3 text-sm ${cls}`}>{children}</div>
}

export function ProgressBar({ value, label }: { value: number; label?: string }) {
  const v = Math.max(0, Math.min(100, value))
  return (
    <div>
      <div className="h-2 overflow-hidden rounded-full bg-rule-soft" role="progressbar" aria-valuenow={v} aria-valuemin={0} aria-valuemax={100} aria-label={label}>
        <div className="h-full bg-brand transition-[width] duration-500" style={{ width: `${v}%` }} />
      </div>
      {label && <p className="mt-1 text-xs text-muted">{label}</p>}
    </div>
  )
}

export function Disclaimer({ className = '' }: { className?: string }) {
  return <p className={`text-xs text-muted ${className}`}>{DISCLAIMER}</p>
}

export function Pagination({ page, last, onChange }: { page: number; last: number; onChange: (p: number) => void }) {
  if (last <= 1) return null
  return (
    <nav className="mt-4 flex items-center justify-end gap-2 text-sm" aria-label="Paginação">
      <button className="btn-ghost" disabled={page <= 1} onClick={() => onChange(page - 1)}>Anterior</button>
      <span className="text-muted">Página {page} de {last}</span>
      <button className="btn-ghost" disabled={page >= last} onClick={() => onChange(page + 1)}>Próxima</button>
    </nav>
  )
}
