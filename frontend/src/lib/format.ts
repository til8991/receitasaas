import type { Recommendation, RunStatus } from './types'

const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const num = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 })

export const money = (v: string | number | null | undefined) => brl.format(Number(v ?? 0))
export const number = (v: string | number | null | undefined, digits = 2) =>
  new Intl.NumberFormat('pt-BR', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(v ?? 0))
export const percent = (v: string | number | null | undefined, digits = 2) => `${number(v, digits)}%`
export const plain = (v: string | number) => num.format(Number(v))

export function cnpj(value: string | null | undefined): string {
  const v = (value ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '')
  if (v.length !== 14) return value ?? ''
  return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8, 12)}-${v.slice(12)}`
}

export function maskCnpj(input: string): string {
  const v = input.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14)
  return v
    .replace(/^(\w{2})(\w)/, '$1.$2')
    .replace(/^(\w{2})\.(\w{3})(\w)/, '$1.$2.$3')
    .replace(/\.(\w{3})(\w)/, '.$1/$2')
    .replace(/(\w{4})(\d)/, '$1-$2')
}

export const date = (v: string | null | undefined) => (v ? new Date(v).toLocaleDateString('pt-BR') : '—')
export const dateTime = (v: string | null | undefined) =>
  v ? new Date(v).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—'

export const recommendationLabel: Record<Recommendation, string> = {
  migrar_regular: 'Compensa migrar',
  manter_simples: 'Manter no Simples',
  indiferente: 'Indiferente',
}

export const recommendationLong: Record<Recommendation, string> = {
  migrar_regular: 'Optar pelo IBS/CBS no regime regular',
  manter_simples: 'Manter o IBS/CBS no Simples Nacional',
  indiferente: 'Diferença abaixo do limite definido nas premissas',
}

export const statusLabel: Record<RunStatus, string> = {
  pending: 'Na fila',
  running: 'Calculando',
  processing: 'Processando',
  completed: 'Concluída',
  failed: 'Falhou',
}

export const regimeLabel: Record<string, string> = {
  simples_nacional: 'Simples Nacional',
  mei: 'MEI',
  lucro_presumido: 'Lucro Presumido',
  lucro_real: 'Lucro Real',
}

export const displayName = (c?: { legal_name: string; trade_name?: string | null } | null) =>
  c ? c.trade_name || c.legal_name : ''
