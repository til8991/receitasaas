import axios, { AxiosError } from 'axios'

const TOKEN_KEY = 'razonete.token'

export const tokenStore = {
  get: (): string | null => {
    try { return localStorage.getItem(TOKEN_KEY) } catch { return null }
  },
  set: (token: string | null) => {
    try {
      if (token) localStorage.setItem(TOKEN_KEY, token)
      else localStorage.removeItem(TOKEN_KEY)
    } catch { /* storage unavailable */ }
  },
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (r) => r,
  (error: AxiosError) => {
    if (error.response?.status === 401 && !error.config?.url?.includes('/auth/login')) {
      tokenStore.set(null)
      if (!location.pathname.startsWith('/entrar')) location.assign('/entrar')
    }
    return Promise.reject(error)
  },
)

/** Human message from a Laravel error response. */
export function errorMessage(error: unknown, fallback = 'Não foi possível concluir a operação.'): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    if (data?.errors) {
      const first = Object.values(data.errors)[0]?.[0]
      if (first) return first
    }
    if (data?.message) return data.message
    if (!error.response) return 'Sem conexão com o servidor. Verifique sua internet.'
  }
  return fallback
}

/** Field errors keyed by field name (first message only). */
export function fieldErrors(error: unknown): Record<string, string> {
  if (axios.isAxiosError(error)) {
    const errors = (error.response?.data as { errors?: Record<string, string[]> })?.errors ?? {}
    return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
  }
  return {}
}

/** Downloads a binary response (PDF) keeping the server file name. */
export async function download(url: string, fallbackName: string) {
  const res = await api.get(url, { responseType: 'blob' })
  const disposition = String(res.headers['content-disposition'] ?? '')
  const match = /filename="?([^";]+)"?/i.exec(disposition)
  const href = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = href
  a.download = match?.[1] ?? fallbackName
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(href)
}
