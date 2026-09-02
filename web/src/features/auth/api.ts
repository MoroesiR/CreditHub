import { api } from '@/lib/api'
import type { LoginCredentials, LoginResponse, User } from '@/types/auth'

export async function login(credentials: LoginCredentials): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/auth/login', {
    device_name: 'web',
    ...credentials,
  })

  return data
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await api.get<{ data: User }>('/auth/me')

  return data.data
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}
