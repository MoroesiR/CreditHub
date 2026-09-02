import axios, { AxiosError } from 'axios'

import { tokenStore } from '@/lib/token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = tokenStore.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

/**
 * A 401 means the token is gone or revoked - on the server's word, not ours.
 * Drop it locally so the app stops presenting a session that no longer exists.
 */
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      tokenStore.clear()
    }

    return Promise.reject(error)
  },
)

interface LaravelErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Turns an axios failure into something displayable, preferring Laravel's
 * first field-level validation message over the generic summary.
 */
export function errorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (!axios.isAxiosError(error)) {
    return fallback
  }

  const body = error.response?.data as LaravelErrorBody | undefined
  const firstFieldError = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined

  return firstFieldError ?? body?.message ?? error.message ?? fallback
}
