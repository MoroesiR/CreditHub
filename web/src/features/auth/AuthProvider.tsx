import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'

import { AuthContext, type AuthContextValue } from '@/features/auth/AuthContext'
import * as authApi from '@/features/auth/api'
import { tokenStore } from '@/lib/token'
import type { User } from '@/types/auth'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  // With no stored token there is nothing to resolve, so the app is not
  // loading - it is simply signed out, and can render the login screen at once.
  const [isLoading, setIsLoading] = useState(() => tokenStore.get() !== null)

  // A stored token is a claim, not proof. Resolve it against the API once on
  // boot so a revoked token never renders a signed-in shell.
  useEffect(() => {
    if (!tokenStore.get()) {
      return
    }

    let cancelled = false

    authApi
      .fetchCurrentUser()
      .then((current) => {
        if (!cancelled) setUser(current)
      })
      .catch(() => {
        tokenStore.clear()
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const signIn = useCallback(async (email: string, password: string) => {
    const { token, user: signedIn } = await authApi.login({ email, password })

    tokenStore.set(token)
    setUser(signedIn)
  }, [])

  const signOut = useCallback(async () => {
    try {
      await authApi.logout()
    } finally {
      // Whether or not the server acknowledged, this browser is signed out.
      tokenStore.clear()
      setUser(null)
    }
  }, [])

  const can = useCallback(
    (permission: string) => user?.permissions.includes(permission) ?? false,
    [user],
  )

  const value = useMemo<AuthContextValue>(
    () => ({ user, isLoading, signIn, signOut, can }),
    [user, isLoading, signIn, signOut, can],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
