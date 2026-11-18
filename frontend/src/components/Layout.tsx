import { useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../auth/AuthContext'
import { api } from '../lib/api'
import { date } from '../lib/format'
import type { PlanSummary } from '../lib/types'
import { DISCLAIMER } from './ui'

const nav = [
  { to: '/', label: 'Painel', end: true },
  { to: '/clientes', label: 'Clientes' },
  { to: '/simulacoes', label: 'Simulações' },
  { to: '/lotes', label: 'Lotes' },
  { to: '/relatorios', label: 'Relatórios' },
  { to: '/configuracoes', label: 'Configurações' },
]

export function Layout() {
  const { user, office, logout } = useAuth()
  const [open, setOpen] = useState(false)
  const location = useLocation()
  const { data: plan } = useQuery({
    queryKey: ['billing'],
    queryFn: async () => (await api.get<{ data: PlanSummary }>('/billing')).data.data,
    staleTime: 60_000,
  })

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[248px_1fr]">
      <a href="#conteudo" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-sheet focus:px-3 focus:py-2">
        Pular para o conteúdo
      </a>

      {/* Mobile top bar */}
      <div className="flex items-center justify-between bg-ink px-4 py-3 text-white lg:hidden">
        <Brand name={office?.name} logo={office?.logo_url} />
        <button className="rounded px-2 py-1 text-sm ring-1 ring-white/30" onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-controls="menu">
          {open ? 'Fechar' : 'Menu'}
        </button>
      </div>

      <aside
        id="menu"
        className={`${open ? 'block' : 'hidden'} bg-ink text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col`}
      >
        <div className="hidden px-5 pb-6 pt-6 lg:block">
          <Brand name={office?.name} logo={office?.logo_url} />
        </div>
        <nav className="flex flex-col gap-0.5 px-3 py-3 lg:py-0" aria-label="Principal">
          {nav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `rounded-md px-3 py-2 text-[15px] transition-colors ${isActive
                  ? 'bg-white/10 font-semibold text-white shadow-[inset_3px_0_0_var(--color-brand)]'
                  : 'text-white/70 hover:bg-white/5 hover:text-white'}`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="mt-auto space-y-3 border-t border-white/10 px-5 py-5 text-sm">
          {plan && (
            <div className="text-white/70">
              <div className="font-semibold text-white">Plano {plan.name}</div>
              <div>
                {plan.active_clients}
                {plan.max_active_clients ? ` de ${plan.max_active_clients}` : ''} CNPJs ativos
              </div>
              {plan.status === 'trialing' && plan.trial_ends_at && <div>Teste até {date(plan.trial_ends_at)}</div>}
              {!plan.is_active && <div className="mt-1 font-semibold text-amber-300">Assinatura inativa</div>}
            </div>
          )}
          <div className="flex items-center justify-between gap-2">
            <span className="truncate text-white/80" title={user?.email}>{user?.name}</span>
            <button className="text-white/70 underline-offset-2 hover:text-white hover:underline" onClick={() => void logout()}>Sair</button>
          </div>
        </div>
      </aside>

      <main id="conteudo" className="min-w-0 px-4 py-6 sm:px-8 sm:py-8" key={location.pathname}>
        <div className="mx-auto max-w-6xl">
          <Outlet />
          <footer className="mt-12 border-t border-rule pt-4 text-xs text-muted">{DISCLAIMER}</footer>
        </div>
      </main>
    </div>
  )
}

function Brand({ name, logo }: { name?: string; logo?: string | null }) {
  return (
    <div className="flex items-center gap-3">
      {logo ? (
        <img src={logo} alt="" className="h-8 w-8 rounded bg-white object-contain p-0.5" />
      ) : (
        <svg viewBox="0 0 32 32" className="h-8 w-8 shrink-0" aria-hidden>
          <rect width="32" height="32" rx="6" fill="var(--color-brand)" />
          <path d="M6 10h20M16 10v15" stroke="#F5F7F6" strokeWidth="3" strokeLinecap="round" />
        </svg>
      )}
      <div className="min-w-0 leading-tight">
        <div className="truncate font-semibold" style={{ fontStretch: '112%' }}>{name ?? 'Razonete'}</div>
        <div className="text-xs text-white/60">Reforma Tributária</div>
      </div>
    </div>
  )
}
