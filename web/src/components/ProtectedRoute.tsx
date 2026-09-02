import { Navigate, Outlet, useLocation } from 'react-router-dom'

import { FullPageSpinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'

/**
 * Route guard. This is a convenience for the person using the app, not a
 * security boundary - the API authorises every request on its own.
 */
export function ProtectedRoute() {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <Outlet />
}
