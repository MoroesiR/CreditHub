/** Mirrors the API's ClientResource, RecruiterResource and paginator envelope. */

export interface Recruiter {
  id: number
  recruiter_number: string
  first_name: string
  last_name: string
  full_name: string
  id_number: string
  phone: string
  email: string | null
  is_active: boolean
  bank_name: string | null
  bank_account_number: string | null
  bank_branch_code: string | null
  clients_count?: number
  created_at: string | null
}

export interface AffordabilityAssessment {
  id: number
  gross_monthly_income: number
  net_monthly_income: number
  monthly_living_expenses: number
  monthly_debt_repayments: number
  disposable_income: number
  assessed_at: string | null
}

export interface Client {
  id: number
  client_number: string
  first_name: string
  last_name: string
  full_name: string
  id_number: string
  date_of_birth: string | null
  gender: string | null
  age: number | null
  phone: string
  email: string | null
  city: string | null
  province: string | null
  country: string | null
  employer_name: string | null
  employment_status: string
  bank_name: string | null
  bank_account_number: string | null
  bank_branch_code: string | null
  recruiter?: Recruiter
  affordability?: AffordabilityAssessment
  created_at: string | null
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

export interface ClientSearchParams {
  search?: string
  page?: number
  per_page?: number
}

export interface Bank {
  name: string
  branch_code: string
}

export interface Locations {
  provinces: string[]
  country: string
}
