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
  documents: Record<string, File>
}

export async function submitChangeRequest(
  payload: SubmitChangeRequestPayload,
): Promise<ChangeRequest> {
  // Multipart because the proof travels with the request: the API will not
  // record a name or a bank change without it.
  const form = new FormData()
  form.append('subject_kind', payload.subject_kind)
  form.append('subject_id', String(payload.subject_id))
  form.append('reason', payload.reason)

  for (const [field, value] of Object.entries(payload.changes)) {
    form.append(`changes[${field}]`, value)
  }

  for (const [type, file] of Object.entries(payload.documents)) {
    form.append(`documents[${type}]`, file)
  }

  const { data } = await api.post<{ data: ChangeRequest }>('/change-requests', form)

  return data.data
}

/** Attached proof is private, so it is fetched as an authenticated blob. */
export async function downloadChangeRequestDocument(
  requestId: number,
  documentId: number,
  filename: string,
): Promise<void> {
  const response = await api.get<Blob>(
    `/change-requests/${requestId}/documents/${documentId}`,
    { responseType: 'blob' },
  )

  const url = URL.createObjectURL(response.data)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
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
