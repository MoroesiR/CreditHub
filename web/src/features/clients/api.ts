import { api } from '@/lib/api'
import type { ClientProfile } from '@/types/profile'
import type {
  Bank,
  Client,
  ClientSearchParams,
  Locations,
  Paginated,
  Recruiter,
} from '@/types/clients'

export async function searchClients(params: ClientSearchParams): Promise<Paginated<Client>> {
  const { data } = await api.get<Paginated<Client>>('/clients', { params })

  return data
}

export async function fetchRecruiters(): Promise<Recruiter[]> {
  const { data } = await api.get<Paginated<Recruiter>>('/recruiters', {
    params: { active_only: true, per_page: 100 },
  })

  return data.data
}

export async function fetchBanks(): Promise<Bank[]> {
  const { data } = await api.get<{ data: Bank[] }>('/reference/banks')

  return data.data
}

export async function fetchLocations(): Promise<Locations> {
  const { data } = await api.get<{ data: Locations }>('/reference/provinces')

  return data.data
}

export async function fetchClientProfile(id: number): Promise<ClientProfile> {
  const { data } = await api.get<{ data: ClientProfile }>(`/clients/${id}/profile`)

  return data.data
}

export interface RegisterClientPayload {
  first_name: string
  last_name: string
  id_number: string
  phone: string
  email?: string | null
  city?: string | null
  province?: string | null
  employer_name?: string | null
  employment_status: string
  bank_name?: string | null
  bank_account_number?: string | null
  recruiter_id?: number | null
  new_recruiter?: {
    first_name: string
    last_name: string
    id_number: string
    phone: string
    bank_name?: string | null
    bank_account_number?: string | null
  } | null
  affordability: {
    gross_monthly_income: number
    net_monthly_income: number
    monthly_living_expenses: number
    monthly_debt_repayments: number
  }
}

export async function registerClient(payload: RegisterClientPayload): Promise<Client> {
  const { data } = await api.post<{ data: Client }>('/clients', payload)

  return data.data
}
