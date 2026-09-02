/**
 * Mirrors the JSON returned by the API's UserResource. Kept in one file so a
 * change to the contract surfaces as a type error everywhere it is consumed.
 */
export interface Role {
  slug: string
  name: string
}

export interface User {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  phone: string | null
  job_title: string | null
  is_active: boolean
  last_login_at: string | null
  roles: Role[]
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
  device_name?: string
}

export interface LoginResponse {
  token: string
  user: User
}
