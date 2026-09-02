import { createContext } from 'react'

import type { User } from '@/types/auth'

export interface AuthContextValue {
  user: User | null
  /** True until the stored token has been checked against the API. */
  isLoading: boolean
  signIn: (email: string, password: string) => Promise<void>
  signOut: () => Promise<void>
  can: (permission: string) => boolean
}

export const AuthContext = createContext<AuthContextValue | undefined>(undefined)
