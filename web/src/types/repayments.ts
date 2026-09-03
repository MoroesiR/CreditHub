export interface LoanAccountSummary {
  total_repayable: number
  paid: number
  balance: number
  instalments_due: number
  expected_to_date: number
  arrears: number
  is_settled: boolean
  is_in_arrears: boolean
  months_behind: number
}

export interface LoanBookRow {
  id: number
  application_number: string
  client_name: string | null
  client_number: string | null
  amount: number
  monthly_instalment: number
  term_months: number
  disbursed_on: string | null
  account: LoanAccountSummary
}

export interface RepaymentMethodOption {
  value: string
  label: string
}

export interface LoanBook {
  data: LoanBookRow[]
  meta: {
    total_outstanding: number
    total_arrears: number
    accounts_in_arrears: number
    methods: RepaymentMethodOption[]
  }
}

export interface Repayment {
  id: number
  amount: number
  received_on: string | null
  method: string
  method_label: string
  reference: string | null
  note: string | null
  is_reversal: boolean
  reverses_id: number | null
  recorded_by?: string | null
  recorded_at: string | null
}
