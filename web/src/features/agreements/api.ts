import { api } from '@/lib/api'
import type { LoanAgreement } from '@/types/agreements'

export async function fetchAgreement(applicationId: number): Promise<LoanAgreement | null> {
  const { data } = await api.get<{ data: LoanAgreement | null }>(
    `/applications/${applicationId}/agreement`,
  )

  return data.data
}

/** Idempotent: returns the existing agreement if one has already been drawn. */
export async function generateAgreement(applicationId: number): Promise<LoanAgreement> {
  const { data } = await api.post<{ data: LoanAgreement }>(
    `/applications/${applicationId}/agreement`,
  )

  return data.data
}

export interface SignAgreementPayload {
  signed_name: string
  /** Data URL of the drawn signature. */
  signature: string
  /** Data URL of the webcam frame, when one was captured. */
  photo?: string | null
}

export async function signAgreement(
  applicationId: number,
  payload: SignAgreementPayload,
): Promise<LoanAgreement> {
  const { data } = await api.post<{ data: LoanAgreement }>(
    `/applications/${applicationId}/agreement/signature`,
    payload,
  )

  return data.data
}

/** Signing evidence is private, so it is fetched as an authenticated blob. */
export async function fetchAgreementImage(
  applicationId: number,
  agreementId: number,
  kind: 'signature' | 'photo',
): Promise<string> {
  const response = await api.get<Blob>(
    `/applications/${applicationId}/agreement/${agreementId}/${kind}`,
    { responseType: 'blob' },
  )

  return URL.createObjectURL(response.data)
}
