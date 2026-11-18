import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api, tokenStore } from '../lib/api'
import type { Office, User } from '../lib/types'

interface AuthState {
  user: User | null
  office: Office | null
  loading: boolean
  isAdmin: boolean
  login: (email: string, password: string) => Promise<void>
  register: (payload: Record<string, string>) => Promise<void>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

/** Applies the office brand colour to the whole app (white label). */
function applyBrand(office: Office | null) {
  const root = document.documentElement
  if (!office) {
    root.style.removeProperty('--brand')
    root.style.removeProperty('--accent')
    root.style.removeProperty('--brand-ink')
    return
  }
  root.style.setProperty('--brand', office.primary_color)
  root.style.setProperty('--accent', office.secondary_color)
  root.style.setProperty('--brand-ink', readableInk(office.primary_color))
}

function readableInk(hex: string): string {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex)
  if (!m) return '#ffffff'
  const n = parseInt(m[1], 16)
  const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((c) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
  })
  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b
  return luminance > 0.45 ? '#16232f' : '#ffffff'
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const queryClient = useQueryClient()

  const refresh = useCallback(async () => {
    if (!tokenStore.get()) {
      setUser(null)
      setLoading(false)
      return
    }
    try {
      const { data } = await api.get<{ user: User }>('/auth/me')
      setUser(data.user)
    } catch {
      tokenStore.set(null)
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { void refresh() }, [refresh])
  useEffect(() => { applyBrand(user?.office ?? null) }, [user?.office])

  const login = useCallback(async (email: string, password: string) => {
    const { data } = await api.post<{ token: string; user: User }>('/auth/login', { email, password })
    tokenStore.set(data.token)
    setUser(data.user)
  }, [])

  const register = useCallback(async (payload: Record<string, string>) => {
    const { data } = await api.post<{ token: string; user: User }>('/auth/register', payload)
    tokenStore.set(data.token)
    setUser(data.user)
  }, [])

  const logout = useCallback(async () => {
    try { await api.post('/auth/logout') } catch { /* token may already be invalid */ }
    tokenStore.set(null)
    setUser(null)
    queryClient.clear()
  }, [queryClient])

  const value = useMemo<AuthState>(() => ({
    user,
    office: user?.office ?? null,
    loading,
    isAdmin: user?.role === 'admin',
    login,
    register,
    logout,
    refresh,
  }), [user, loading, login, register, logout, refresh])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
