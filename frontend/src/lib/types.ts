export type Role = 'admin' | 'collaborator'
export type Recommendation = 'migrar_regular' | 'manter_simples' | 'indiferente'
export type RunStatus = 'pending' | 'running' | 'processing' | 'completed' | 'failed'
export type Scenario = 'simples_integral' | 'ibs_cbs_regular'

export interface Office {
  id: number
  name: string
  cnpj: string
  email: string | null
  phone: string | null
  logo_url: string | null
  primary_color: string
  secondary_color: string
  plan: string
  plan_status: string
  trial_ends_at: string | null
  assumptions: Record<string, unknown> | null
}

export interface User {
  id: number
  name: string
  email: string
  role: Role
  office_id: number
  office?: Office
}

export interface Client {
  id: number
  cnpj: string
  legal_name: string
  trade_name: string | null
  uf: string
  municipality_ibge: number
  current_regime: string
  simples_annex: string | null
  activities: string[] | null
  revenue_12m: string
  purchases_12m: string
  b2b_sales_pct: string
  default_ncm: string | null
  default_nbs: string | null
  default_cst: string
  default_c_class_trib: string
  assumptions: Record<string, unknown> | null
  active: boolean
  notes: string | null
  invoice_items_count?: number
  latest_comparison?: Comparison | null
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

export interface InvoiceImport {
  id: number
  client_id: number
  status: RunStatus
  total_files: number
  processed_files: number
  failed_files: number
  duplicated_documents: number
  imported_items: number
  errors: { arquivo: string; erro: string }[] | null
  progress: number
  created_at: string
  finished_at: string | null
}

export interface ScenarioResult {
  ibs: string
  cbs: string
  total_ibs_cbs: string
  credito_compras_bruto?: string
  credito_compras_aproveitado: string
  custo_liquido: string
  credito_clientes_b2b: string
  beneficio_comercial: string
  custo_efetivo: string
}

export interface ComparisonResult {
  ano_referencia: number
  receita_bruta: string
  receita_b2b: string
  compras: string
  periodo: { inicio: string | null; fim: string | null; origem: 'invoices' | 'manual' }
  cenarios: Record<Scenario, ScenarioResult>
  diferenca: { valor: string; percentual_receita: number; credito_adicional_clientes: string }
  recomendacao: Recommendation
  justificativa: string[]
  avisos: string[]
  erros_itens: number
}

export interface ItemError {
  nObj: number
  estadoItem: string
  origem?: string
  erros: { code?: string; title?: string; pointer?: string; detail?: string }[]
}

export interface SimulationDetail {
  id: number
  status: RunStatus
  totals: Record<string, unknown> & {
    items?: { nObj: number; descricao: string; base: string; b2b: boolean; estado: string | null; ibs_cbs: string }[]
  }
  item_errors: ItemError[] | null
  calculator_version: Record<string, { versaoApp?: string; versaoDb?: string; dataVersaoDb?: string; ambiente?: string }>
  input: Record<string, unknown>
  calculated_at: string | null
}

export interface Comparison {
  id: number
  client_id: number
  client?: Pick<Client, 'id' | 'legal_name' | 'trade_name' | 'cnpj'>
  simulation_batch_id: number | null
  reference_year: number
  source: 'invoices' | 'manual'
  status: RunStatus
  result: ComparisonResult | null
  recommendation: Recommendation | null
  error_message: string | null
  has_report?: boolean
  disclaimer?: string
  assumptions?: { lista: { id: string; descricao: string; valor: string }[]; valores: Record<string, unknown> }
  simulations?: Partial<Record<Scenario, SimulationDetail>>
  calculated_at: string | null
  created_at: string
}

export interface Batch {
  id: number
  reference_year: number
  source: string
  status: RunStatus
  total: number
  processed: number
  failed: number
  progress: number
  created_at: string
  finished_at: string | null
  comparisons?: {
    id: number
    client: Pick<Client, 'id' | 'legal_name' | 'trade_name' | 'cnpj'>
    status: RunStatus
    recommendation: Recommendation | null
    difference: string | null
    difference_pct: number | null
    error_message: string | null
    has_report: boolean
  }[]
}

export interface PlanSummary {
  plan: string
  name: string
  status: string
  trial_ends_at: string | null
  max_active_clients: number | null
  active_clients: number
  is_active: boolean
  available_plans: { key: string; name: string; max_active_clients: number | null; price_cents: number }[]
}

export interface Dashboard {
  clients: { total: number; active: number; simulated: number }
  recommendations: Record<Recommendation, number>
  potential_savings: string
  top_migrate: { comparison_id: number; client: Client; reference_year: number; difference: string; difference_pct: number }[]
  recent: { id: number; client: Client; reference_year: number; status: RunStatus; recommendation: Recommendation | null; difference: string | null; created_at: string }[]
  comparisons_total: number
  plan: PlanSummary
}

export interface AssumptionDefinition {
  id: string
  label: string
  type: 'percent' | 'rate_nullable' | 'year' | 'rates_by_year' | 'factor_by_year'
  help: string
}
