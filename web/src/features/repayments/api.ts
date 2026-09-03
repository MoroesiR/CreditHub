import { api } from '@/lib/api'
import type { LoanAccountSummary, LoanBook, Repayment } from '@/types/repayments'

export async function fetchLoanBook(search?: string): Promise<LoanBook> {
  const { data } = await api.get<LoanBook>('/loans', {
    params: { search: search || undefined },
  })

  return data
}

export async function fetchRepayments(
  loanId: number,
): Promise<{ data: Repayment[]; meta: { account: LoanAccountSummary } }> {
  const { data } = await api.get<{ data: Repayment[]; meta: { account: LoanAccountSummary } }>(
    `/loans/${loanId}/repayments`,
  )

  return data
}

export interface RecordRepaymentPayload {
  amount: number
  received_on: string
  method: string
  reference?: string | null
  note?: string | null
}

export async function recordRepayment(
  loanId: number,
  payload: RecordRepaymentPayload,
): Promise<Repayment> {
  const { data } = await api.post<{ data: Repayment }>(`/loans/${loanId}/repayments`, payload)

  return data.data
}

export async function reverseRepayment(
  loanId: number,
  repaymentId: number,
  reason: string,
): Promise<Repayment> {
  const { data } = await api.post<{ data: Repayment }>(
    `/loans/${loanId}/repayments/${repaymentId}/reversal`,
    { reason },
  )

  return data.data
}
