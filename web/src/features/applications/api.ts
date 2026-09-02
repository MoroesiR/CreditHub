import { api } from '@/lib/api'
import type { AuditEvent } from '@/types/audit'
import type {
  ApplicationStatus,
  DocumentType,
  LoanApplication,
  LoanQuote,
} from '@/types/applications'
import type { Paginated } from '@/types/clients'

export interface ApplicationSearchParams {
  search?: string
  status?: ApplicationStatus | ''
  page?: number
}

export async function searchApplications(
  params: ApplicationSearchParams,
): Promise<Paginated<LoanApplication>> {
  const { data } = await api.get<Paginated<LoanApplication>>('/applications', {
    params: { ...params, status: params.status || undefined },
  })

  return data
}

export async function fetchApplication(id: number): Promise<LoanApplication> {
  const { data } = await api.get<{ data: LoanApplication }>(`/applications/${id}`)

  return data.data
}

export async function fetchApplicationAuditTrail(id: number): Promise<AuditEvent[]> {
  const { data } = await api.get<{ data: AuditEvent[] }>(`/applications/${id}/audit-trail`)

  return data.data
}

/** Prices a loan without capturing one. */
export async function quoteLoan(amount: number, termMonths: number): Promise<LoanQuote> {
  const { data } = await api.post<{ data: LoanQuote }>('/applications/quote', {
    amount,
    term_months: termMonths,
  })

  return data.data
}

export interface CreateApplicationPayload {
  client_id: number
  amount: number
  term_months: number
  purpose?: string | null
  documents: Partial<Record<DocumentType, File>>
}

export async function createApplication(
  payload: CreateApplicationPayload,
): Promise<LoanApplication> {
  // Sent as multipart because the supporting documents travel with the
  // application: the API will not create one without them.
  const form = new FormData()
  form.append('client_id', String(payload.client_id))
  form.append('amount', String(payload.amount))
  form.append('term_months', String(payload.term_months))

  if (payload.purpose) {
    form.append('purpose', payload.purpose)
  }

  for (const [type, file] of Object.entries(payload.documents)) {
    if (file) {
      form.append(`documents[${type}]`, file)
    }
  }

  const { data } = await api.post<{ data: LoanApplication }>('/applications', form)

  return data.data
}

/**
 * Documents are not public URLs - they are streamed through an authenticated
 * request, so the browser is handed a blob rather than a link.
 */
export async function downloadDocument(
  applicationId: number,
  documentId: number,
  filename: string,
): Promise<void> {
  const response = await api.get<Blob>(
    `/applications/${applicationId}/documents/${documentId}`,
    { responseType: 'blob' },
  )

  const url = URL.createObjectURL(response.data)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}

export async function decideApplication(
  id: number,
  approved: boolean,
  declineReason?: string,
): Promise<LoanApplication> {
  const { data } = await api.post<{ data: LoanApplication }>(`/applications/${id}/decision`, {
    approved,
    decline_reason: approved ? undefined : declineReason,
  })

  return data.data
}
