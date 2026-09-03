export interface PipelineRow {
  status: string
  label: string
  count: number
  total: number
}

export interface MonthlyRow {
  month: string
  label: string
  count: number
  total: number
}

export interface PortfolioReport {
  pipeline: PipelineRow[]
  cash_out: {
    loans_paid_count: number
    loans_paid_total: number
    commission_paid_total: number
    total: number
    awaiting_payout_count: number
    awaiting_payout_total: number
  }
  commission: {
    owing_count: number
    owing_total: number
    paid_count: number
    paid_total: number
  }
  repayments: {
    live_loans: number
    collected: number
    outstanding: number
    arrears: number
    accounts_in_arrears: number
    accounts_settled: number
  }
  monthly: MonthlyRow[]
}
