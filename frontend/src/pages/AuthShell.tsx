import type { ReactNode } from 'react'
import { DISCLAIMER } from '../components/ui'

/** Split layout for login/sign-up: a razonete illustration on the left explains the product at a glance. */
export function AuthShell({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <div className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
      <section className="hidden flex-col justify-between bg-ink px-12 py-12 text-white lg:flex">
        <div className="flex items-center gap-3">
          <svg viewBox="0 0 32 32" className="h-9 w-9" aria-hidden>
            <rect width="32" height="32" rx="6" fill="#0B5D4B" />
            <path d="M6 10h20M16 10v15" stroke="#F5F7F6" strokeWidth="3" strokeLinecap="round" />
          </svg>
          <span className="text-xl font-bold" style={{ fontStretch: '115%' }}>Razonete</span>
        </div>

        <div>
          <p className="max-w-[30ch] text-[34px] font-bold leading-tight" style={{ fontStretch: '115%' }}>
            Simples integral ou IBS/CBS por fora? Responda para cada cliente.
          </p>
          <div className="mt-10 max-w-md text-[15px]" aria-hidden>
            <div className="grid grid-cols-[1fr_3px_1fr] border-t-[3px] border-white/90">
              <div className="px-4 py-2 text-right text-white/70">Simples integral</div>
              <div className="bg-white/90" />
              <div className="px-4 py-2 text-white/70">Regime regular</div>
              <div className="px-4 py-1.5 text-right figure">R$ 13.679,00</div>
              <div className="bg-white/90" />
              <div className="px-4 py-1.5 figure">R$ 64.080,00</div>
              <div className="px-4 py-1.5 text-right text-sm text-white/60">crédito B2B 10.943</div>
              <div className="bg-white/90" />
              <div className="px-4 py-1.5 text-sm text-white/60">crédito B2B 71.200</div>
            </div>
          </div>
          <p className="mt-8 max-w-[52ch] text-white/70">
            Os tributos são calculados pelas calculadoras oficiais da Receita Federal. Você importa os XMLs,
            ajusta as premissas e entrega o relatório com a marca do seu escritório.
          </p>
        </div>

        <p className="text-xs text-white/50">{DISCLAIMER}</p>
      </section>

      <section className="flex items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-md">
          <h1 className="text-2xl font-bold">{title}</h1>
          <p className="mt-1 text-ink-soft">{subtitle}</p>
          <div className="mt-8">{children}</div>
          <p className="mt-10 text-xs text-muted lg:hidden">{DISCLAIMER}</p>
        </div>
      </section>
    </div>
  )
}
