import type { Client, Recruiter } from '@/types/clients'

export type ApplicationStatus =
  | 'draft'
  | 'submitted'
  | 'approved'
  | 'declined'
  | 'agreement_signed'
  | 'disbursed'
  | 'cancelled'

export type DocumentType = 'id_copy' | 'bank_statement' | 'payslip'

export interface ApplicationDocument {
  id: number
  type: DocumentType
  type_label: string
  original_name: string
  mime_type: string
  size_bytes: number
  uploaded_at: string | null
}

export interface JourneyStage {
  key: string
  label: string
  actor: string | null
  at: string | null
  done: boolean
  detail: string | null
}

export interface LoanApplication {
  id: number
  application_number: string
  amount: number
  term_months: number
  interest_rate: number
  monthly_instalment: number
  total_repayable: number
  disposable_income_at_capture: number | null
  purpose: string | null
  status: ApplicationStatus
  status_label: string
  submitted_at: string | null
  submitted_by?: string | null
  decided_at: string | null
  decided_by?: string | null
  decline_reason: string | null
  documents?: ApplicationDocument[]
  journey?: JourneyStage[]
  client?: Client
  recruiter?: Recruiter
  created_at: string | null
}

export interface LoanQuote {
  monthly_instalment: number
  total_repayable: number
  total_interest: number
  interest_rate: number
}
