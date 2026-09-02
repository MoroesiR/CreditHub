export interface LoanAgreement {
  id: number
  agreement_number: string
  loan_application_id: number
  amount: number
  term_months: number
  interest_rate: number
  monthly_instalment: number
  total_repayable: number
  generated_at: string | null
  signed_name: string | null
  signed_at: string | null
  signed_ip: string | null
  witnessed_by?: string | null
  is_signed: boolean
  has_signature: boolean
  has_photo: boolean
}
