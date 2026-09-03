import type { Client } from '@/types/clients'
import type { LoanAccountSummary } from '@/types/repayments'

export interface ProfileApplication {
  id: number
  application_number: string
  amount: number
  term_months: number
  interest_rate: number
  monthly_instalment: number
  total_repayable: number
  status: string
  status_label: string
  submitted_at: string | null
  decided_at: string | null
  decline_reason: string | null
  disbursed_on: string | null
  account: LoanAccountSummary | null
}

export interface ProfileRepayment {
  id: number
  application_number: string | null
  amount: number
  received_on: string | null
  method_label: string
  reference: string | null
  note: string | null
  is_reversal: boolean
  recorded_by: string | null
}

export interface ProfileDocument {
  id: number
  loan_application_id: number
  application_number: string
  type_label: string
  original_name: string
  mime_type: string
  size_bytes: number
  uploaded_at: string | null
}

export interface ClientProfile {
  client: Client
  photo: {
    agreement_id: number
    loan_application_id: number
    captured_at: string | null
  } | null
  applications: ProfileApplication[]
  repayments: ProfileRepayment[]
  documents: ProfileDocument[]
  totals: {
    applications: number
    borrowed: number
    outstanding: number
    arrears: number
    is_in_arrears: boolean
  }
}
