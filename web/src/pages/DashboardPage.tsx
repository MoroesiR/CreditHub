import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { fetchDashboardSummary } from '@/features/dashboard/api'
import { useAuth } from '@/features/auth/useAuth'
import { formatMoney, formatNumber } from '@/lib/format'
import type { DashboardTile } from '@/types/dashboard'

function TileCard({ tile }: { tile: DashboardTile }) {
  const value = tile.format === 'money' ? formatMoney(tile.value) : formatNumber(tile.value)

  return (
    <Link
      to={tile.href}
      className="group rounded-lg border border-slate-200 bg-white p-5 transition-colors hover:border-brand-300 hover:bg-brand-50/40"
    >
      <p className="text-sm font-medium text-slate-500">{tile.label}</p>
      <p className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{value}</p>
      <p className="mt-1 text-xs text-slate-500">{tile.caption}</p>
    </Link>
  )
}

export function DashboardPage() {
  const { user } = useAuth()

  const {
    data: tiles,
    isPending,
    isError,
  } = useQuery({
    queryKey: ['dashboard', 'summary'],
    queryFn: fetchDashboardSummary,
  })

  if (!user) {
    return null
  }

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Good day, {user.first_name}</h1>
        <p className="mt-1 text-sm text-slate-500">
          Signed in as {user.roles.map((role) => role.name).join(', ') || 'no role assigned'}.
        </p>
      </header>

      <section>
        {isPending ? (
          <div className="flex justify-center py-10">
            <Spinner />
          </div>
        ) : isError ? (
          <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            The summary could not be loaded.
          </p>
        ) : tiles.length === 0 ? (
          <p className="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500">
            Your role has no modules to summarise.
          </p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {tiles.map((tile) => (
              <TileCard key={tile.key} tile={tile} />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
