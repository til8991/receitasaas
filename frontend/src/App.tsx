import { Navigate, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from './auth/AuthContext'
import { Layout } from './components/Layout'
import { Loading } from './components/ui'
import Login from './pages/Login'
import Register from './pages/Register'
import Dashboard from './pages/Dashboard'
import Clients from './pages/Clients'
import ClientForm from './pages/ClientForm'
import ClientDetail from './pages/ClientDetail'
import Simulations from './pages/Simulations'
import SimulationNew from './pages/SimulationNew'
import SimulationView from './pages/SimulationView'
import Batches from './pages/Batches'
import BatchView from './pages/BatchView'
import Reports from './pages/Reports'
import Settings from './pages/Settings'

function Protected({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="px-8"><Loading /></div>
  if (!user) return <Navigate to="/entrar" replace />
  return <>{children}</>
}

function GuestOnly({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return null
  if (user) return <Navigate to="/" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/entrar" element={<GuestOnly><Login /></GuestOnly>} />
      <Route path="/cadastro" element={<GuestOnly><Register /></GuestOnly>} />
      <Route element={<Protected><Layout /></Protected>}>
        <Route index element={<Dashboard />} />
        <Route path="clientes" element={<Clients />} />
        <Route path="clientes/novo" element={<ClientForm />} />
        <Route path="clientes/:id" element={<ClientDetail />} />
        <Route path="clientes/:id/editar" element={<ClientForm />} />
        <Route path="simulacoes" element={<Simulations />} />
        <Route path="simulacoes/nova" element={<SimulationNew />} />
        <Route path="simulacoes/:id" element={<SimulationView />} />
        <Route path="lotes" element={<Batches />} />
        <Route path="lotes/:id" element={<BatchView />} />
        <Route path="relatorios" element={<Reports />} />
        <Route path="configuracoes" element={<Settings />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
