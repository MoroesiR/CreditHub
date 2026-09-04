import type { ReactNode } from 'react'

import { useAuth } from '@/features/auth/useAuth'

/**
 * Hiding a link is presentation, not authorisation - anyone can type the URL.
 * Every guarded route is wrapped in this so the check happens on the route
 * itself. The API enforces the same permission again on every request; this
 * only saves the user from a screen they may not use.
 */
export function RequirePermission({
  permission,
  children,
}: {
  permission: string
  children: ReactNode
}) {
  const { can } = useAuth()

  if (!can(permission)) {
    return (
      <div className="rounded-lg border border-warn-200 bg-warn-50 p-6">
        <h1 className="text-lg font-semibold text-warn-900">Not available to your role</h1>
        <p className="mt-1 text-sm text-warn-800">
          This screen requires the <code className="font-mono">{permission}</code> permission.
        </p>
      </div>
    )
  }

  return <>{children}</>
}
