import { api } from '@/lib/api'
import type { ChangeRequest, ChangeRequestStatus } from '@/types/changeRequests'
import type { Paginated } from '@/types/clients'

export async function fetchChangeRequests(params: {
  status?: ChangeRequestStatus | ''
  page?: number
  subject_kind?: 'client' | 'recruiter'
  subject_id?: number
}): Promise<Paginated<ChangeRequest>> {
  const { data } = await api.get<Paginated<ChangeRequest>>('/change-requests', {
    params: { ...params, status: params.status || undefined },
  })

  return data
}

export interface SubmitChangeRequestPayload {
  subject_kind: 'client' | 'recruiter'
  subject_id: number
  reason: string
  changes: Record<string, string>
}

export async function submitChangeRequest(
  payload: SubmitChangeRequestPayload,
): Promise<ChangeRequest> {
  const { data } = await api.post<{ data: ChangeRequest }>('/change-requests', payload)

  return data.data
}

export async function approveChangeRequest(id: number, note?: string): Promise<ChangeRequest> {
  const { data } = await api.post<{ data: ChangeRequest }>(`/change-requests/${id}/approval`, {
    note,
  })

  return data.data
}

export async function rejectChangeRequest(id: number, note: string): Promise<ChangeRequest> {
  const { data } = await api.post<{ data: ChangeRequest }>(`/change-requests/${id}/rejection`, {
    note,
  })

  return data.data
}
