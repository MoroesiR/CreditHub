import { api } from '@/lib/api'
import type { Paginated } from '@/types/clients'
import type { Disbursement, DisbursementStatus } from '@/types/disbursements'

export async function fetchDisbursements(params: {
  status?: DisbursementStatus | ''
  page?: number
}): Promise<Paginated<Disbursement>> {
  const { data } = await api.get<Paginated<Disbursement>>('/disbursements', {
    params: { ...params, status: params.status || undefined },
  })

  return data
}

export async function fetchDisbursement(id: number): Promise<Disbursement> {
  const { data } = await api.get<{ data: Disbursement }>(`/disbursements/${id}`)

  return data.data
}

export async function verifyDisbursement(id: number): Promise<Disbursement> {
  const { data } = await api.post<{ data: Disbursement }>(`/disbursements/${id}/verification`)

  return data.data
}

export async function holdDisbursement(id: number, reason: string): Promise<Disbursement> {
  const { data } = await api.post<{ data: Disbursement }>(`/disbursements/${id}/hold`, { reason })

  return data.data
}

export async function payDisbursement(
  id: number,
  paymentReference: string,
): Promise<Disbursement> {
  const { data } = await api.post<{ data: Disbursement }>(`/disbursements/${id}/payment`, {
    payment_reference: paymentReference,
  })

  return data.data
}
