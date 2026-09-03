import { api } from '@/lib/api'
import type { User } from '@/types/auth'

export interface AssignableRole {
  slug: string
  name: string
  description: string | null
  permission_count: number
  permissions: string[]
}

export async function fetchUsers(): Promise<User[]> {
  const { data } = await api.get<{ data: User[] }>('/users')

  return data.data
}

export async function fetchAssignableRoles(): Promise<AssignableRole[]> {
  const { data } = await api.get<{ data: AssignableRole[] }>('/users/roles')

  return data.data
}

export interface CreateUserPayload {
  first_name: string
  last_name: string
  email: string
  phone?: string | null
  job_title?: string | null
  password: string
  roles: string[]
}

export async function createUser(payload: CreateUserPayload): Promise<User> {
  const { data } = await api.post<{ data: User }>('/users', payload)

  return data.data
}

export async function updateUser(id: number, changes: Record<string, unknown>): Promise<User> {
  const { data } = await api.patch<{ data: User }>(`/users/${id}`, changes)

  return data.data
}
