import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'

import { NavDropdown } from '@/components/NavDropdown'
import { useAuth } from '@/features/auth/useAuth'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { MODULES } from '@/navigation'

export function AppLayout() {
  const { user, signOut, can } = useAuth()
  const navigate = useNavigate()
  const [isSigningOut, setIsSigningOut] = useState(false)

  async function handleSignOut() {
    setIsSigningOut(true)
    await signOut()
    void navigate('/login', { replace: true })
  }

  const roles = user?.roles.map((role) => role.slug) ?? []

  // Two filters, and they answer different questions. Permission decides what
  // a role may open at all; primaryFor decides what it works in often enough
  // to deserve a place in the header. A payouts officer can still open a
  // client from a link on a payout, without carrying a Clients menu item.
  const modules = MODULES.filter(
    (module) =>
      can(module.permission) &&
      (module.primaryFor === undefined || module.primaryFor.some((slug) => roles.includes(slug))),
  ).map((module) => ({
    ...module,
    children:
      module.children?.filter(
        (child) =>
          can(child.permission) &&
          (child.primaryFor === undefined || child.primaryFor.some((slug) => roles.includes(slug))),
      ) ?? [],
  }))

  return (
    <div className="flex min-h-full flex-col">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center gap-6 px-6 py-4">
          <span className="text-lg font-semibold tracking-tight text-brand-700">CreditHub</span>

          <nav className="flex flex-1 flex-wrap items-center gap-1">
            <NavLink
              to="/"
              end
              className={({ isActive }) =>
                `rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-brand-50 text-brand-700'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`
              }
            >
              Dashboard
            </NavLink>

            {modules.map((module) =>
              module.children.length > 0 ? (
                <NavDropdown key={module.path} label={module.label} items={module.children} />
              ) : (
                <NavLink
                  key={module.path}
                  to={module.path}
                  className={({ isActive }) =>
                    `rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                      isActive
                        ? 'bg-brand-50 text-brand-700'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    }`
                  }
                >
                  {module.label}
                </NavLink>
              ),
            )}
          </nav>

          <div className="flex items-center gap-3">
            <NotificationBell />

            <div className="text-right leading-tight">
              <p className="text-sm font-medium text-slate-900">{user?.full_name}</p>
              <p className="text-xs text-slate-500">{user?.job_title}</p>
            </div>
            <button
              type="button"
              onClick={() => void handleSignOut()}
              disabled={isSigningOut}
              className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 disabled:opacity-60"
            >
              {isSigningOut ? 'Signing out…' : 'Sign out'}
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-6 py-8">
        <Outlet />
      </main>
    </div>
  )
}
