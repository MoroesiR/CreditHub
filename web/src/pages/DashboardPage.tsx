import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { fetchDashboardSummary } from '@/features/dashboard/api'
import { useAuth } from '@/features/auth/useAuth'
import { formatMoney, formatNumber } from '@/lib/format'
import type { DashboardTile } from '@/types/dashboard'

/*
  Colour here says what the number means, not which tile it is. Anything
  waiting on a person is amber, anything owed or overdue is red, money settled
  is green, and a plain count stays neutral so it does not compete with the
  figures that need acting on.
*/
const TONE: Record<string, { rail: string; value: string }> = {
  applications_pending: { rail: 'bg-warn-500', value: 'text-warn-800' },
  awaiting_payout: { rail: 'bg-warn-500', value: 'text-warn-800' },
  arrears: { rail: 'bg-bad-500', value: 'text-bad-700' },
  commissions_pending: { rail: 'bg-bad-500', value: 'text-bad-700' },
  clients: { rail: 'bg-brand-500', value: 'text-ink-900' },
  recruiters: { rail: 'bg-brand-500', value: 'text-ink-900' },
  applications: { rail: 'bg-info-500', value: 'text-ink-900' },
}

const NEUTRAL = { rail: 'bg-ink-300', value: 'text-ink-900' }

function TileCard({ tile }: { tile: DashboardTile }) {
  const value = tile.format === 'money' ? formatMoney(tile.value) : formatNumber(tile.value)
  const tone = TONE[tile.key] ?? NEUTRAL

  // A zero is good news on an arrears tile, so it drops back to neutral rather
  // than sitting there in red for a book with nothing wrong with it.
  const isQuiet = tile.value === 0
  const valueTone = isQuiet ? NEUTRAL.value : tone.value
  const railTone = isQuiet ? NEUTRAL.rail : tone.rail

  return (
    <Link
      to={tile.href}
      className="group relative overflow-hidden rounded-lg border border-ink-200 bg-white p-5 pl-6 shadow-sm transition-all hover:border-ink-300 hover:shadow-md"
    >
      <span className={`absolute inset-y-0 left-0 w-1 ${railTone}`} aria-hidden="true" />
      <p className="text-sm font-medium text-ink-500">{tile.label}</p>
      <p className={`tabular mt-2 text-3xl font-semibold tracking-tight ${valueTone}`}>{value}</p>
      <p className="mt-1 text-xs text-ink-500">{tile.caption}</p>
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
        <p className="mt-1 text-sm text-ink-500">
          Signed in as {user.roles.map((role) => role.name).join(', ') || 'no role assigned'}.
        </p>
      </header>

      <section>
        {isPending ? (
          <div className="flex justify-center py-10">
            <Spinner />
          </div>
        ) : isError ? (
          <p className="rounded-lg border border-bad-200 bg-bad-50 p-4 text-sm text-bad-700">
            The summary could not be loaded.
          </p>
        ) : tiles.length === 0 ? (
          <p className="rounded-lg border border-ink-200 bg-white p-6 text-sm text-ink-500">
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
