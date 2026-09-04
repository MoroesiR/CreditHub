import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'

import { useAuth } from '@/features/auth/useAuth'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { MODULES } from '@/navigation'

function initials(name: string | undefined): string {
  if (!name) {
    return '?'
  }

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

export function AppLayout() {
  const { user, signOut, can } = useAuth()
  const navigate = useNavigate()
  const [isSigningOut, setIsSigningOut] = useState(false)
  const [isNavOpen, setIsNavOpen] = useState(false)

  async function handleSignOut() {
    setIsSigningOut(true)
    await signOut()
    void navigate('/login', { replace: true })
  }

  const roles = user?.roles.map((role) => role.slug) ?? []

  // Two filters, and they answer different questions. Permission decides what
  // a role may open at all; primaryFor decides what it works in often enough
  // to deserve a place in the sidebar. A payouts officer can still open a
  // client from a link on a payout without carrying a Clients entry.
  const visible = (primaryFor: string[] | undefined, permission: string) =>
    can(permission) && (primaryFor === undefined || primaryFor.some((slug) => roles.includes(slug)))

  const modules = MODULES.filter((module) => visible(module.primaryFor, module.permission))

  const linkClass = ({ isActive }: { isActive: boolean }) =>
    `block rounded-md px-3 py-2 text-sm transition-colors ${
      isActive
        ? 'bg-ink-800 font-medium text-white'
        : 'text-ink-300 hover:bg-ink-800/60 hover:text-white'
    }`

  return (
    <div className="flex min-h-full">
      <aside
        className={`fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-ink-900 transition-transform lg:static lg:translate-x-0 ${
          isNavOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center gap-2.5 px-5 py-5">
          <span className="flex h-8 w-8 items-center justify-center rounded bg-brand-500 text-xs font-bold text-white">
            CH
          </span>
          <span className="text-base font-semibold tracking-tight text-white">CreditHub</span>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
          <NavLink to="/" end className={linkClass} onClick={() => setIsNavOpen(false)}>
            Dashboard
          </NavLink>

          {modules.map((module) => (
            <NavLink
              key={module.path}
              to={module.path}
              className={linkClass}
              onClick={() => setIsNavOpen(false)}
            >
              {module.label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-ink-800 px-3 py-3">
          <div className="flex items-center gap-3 px-2 py-1">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink-700 text-xs font-semibold text-ink-100">
              {initials(user?.full_name)}
            </span>
            <div className="min-w-0 leading-tight">
              <p className="truncate text-sm font-medium text-white">{user?.full_name}</p>
              <p className="truncate text-xs text-ink-400">{user?.job_title}</p>
            </div>
          </div>

          <button
            type="button"
            onClick={() => void handleSignOut()}
            disabled={isSigningOut}
            className="mt-2 w-full rounded-md px-3 py-2 text-left text-sm text-ink-300 transition-colors hover:bg-ink-800 hover:text-white disabled:opacity-60"
          >
            {isSigningOut ? 'Signing out...' : 'Sign out'}
          </button>
        </div>
      </aside>

      {isNavOpen && (
        <button
          type="button"
          aria-label="Close navigation"
          onClick={() => setIsNavOpen(false)}
          className="fixed inset-0 z-30 bg-ink-950/50 lg:hidden"
        />
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-ink-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
          <button
            type="button"
            onClick={() => setIsNavOpen(true)}
            aria-label="Open navigation"
            className="rounded-md border border-ink-200 px-2.5 py-1.5 text-sm text-ink-600 lg:hidden"
          >
            Menu
          </button>

          <div className="flex-1" />

          <NotificationBell />
        </header>

        <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
