import type { LoanApplication } from '@/types/applications'
import type { Recruiter } from '@/types/clients'

export type DisbursementStatus = 'pending' | 'verified' | 'paid' | 'on_hold'

export interface Disbursement {
  id: number
  amount: number
  status: DisbursementStatus
  status_label: string
  verified_at: string | null
  verified_by?: string | null
  paid_at: string | null
  paid_by?: string | null
  payment_reference: string | null
  paid_to_bank_name: string | null
  paid_to_account_number: string | null
  paid_to_branch_code: string | null
  hold_reason: string | null
  application?: LoanApplication
  /** Present when the paid loan earned a recruiter a commission. */
  commission?: Commission
  created_at: string | null
}

export interface Commission {
  id: number
  loan_amount: number
  rate_applied: number
  amount: number
  status: 'pending' | 'paid' | 'cancelled'
  status_label: string
  calculated_at: string | null
  paid_at: string | null
  recruiter?: Recruiter
  application?: LoanApplication
}
