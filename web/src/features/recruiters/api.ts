import { api } from '@/lib/api'
import type { AuditEvent } from '@/types/audit'
import type { Client, Paginated, Recruiter } from '@/types/clients'

export interface RecruiterSearchParams {
  search?: string
  page?: number
}

export async function searchRecruiters(
  params: RecruiterSearchParams,
): Promise<Paginated<Recruiter>> {
  const { data } = await api.get<Paginated<Recruiter>>('/recruiters', { params })

  return data
}

export async function fetchRecruiter(id: number): Promise<Recruiter> {
  const { data } = await api.get<{ data: Recruiter }>(`/recruiters/${id}`)

  return data.data
}

export async function fetchRecruiterClients(id: number): Promise<Client[]> {
  const { data } = await api.get<{ data: Client[] }>(`/recruiters/${id}/clients`)

  return data.data
}

export async function fetchRecruiterAuditTrail(id: number): Promise<AuditEvent[]> {
  const { data } = await api.get<{ data: AuditEvent[] }>(`/recruiters/${id}/audit-trail`)

  return data.data
}

/** Clients captured as walk-ins, available to be credited to a recruiter. */
export async function fetchUnlinkedClients(): Promise<Client[]> {
  const { data } = await api.get<{ data: Client[] }>('/recruiters/unlinked-clients')

  return data.data
}

export async function linkClientToRecruiter(
  recruiterId: number,
  clientId: number,
): Promise<Client> {
  const { data } = await api.post<{ data: Client }>(`/recruiters/${recruiterId}/clients`, {
    client_id: clientId,
  })

  return data.data
}

export interface RegisterRecruiterPayload {
  first_name: string
  last_name: string
  id_number: string
  phone: string
  email?: string | null
  bank_name?: string | null
  bank_account_number?: string | null
}

export async function registerRecruiter(payload: RegisterRecruiterPayload): Promise<Recruiter> {
  const { data } = await api.post<{ data: Recruiter }>('/recruiters', payload)

  return data.data
}
