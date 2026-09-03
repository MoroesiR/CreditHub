import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { StatusBadge } from '@/features/applications/StatusBadge'
import { searchApplications } from '@/features/applications/api'
import { useAuth } from '@/features/auth/useAuth'
import { formatDateTime, formatMoney } from '@/lib/format'
import type { ApplicationStatus, JourneyStage } from '@/types/applications'

const FILTERS: { value: ApplicationStatus | ''; label: string }[] = [
  { value: '', label: 'All' },
  { value: 'submitted', label: 'Awaiting decision' },
  { value: 'approved', label: 'Approved' },
  { value: 'declined', label: 'Declined' },
  { value: 'agreement_signed', label: 'Agreement signed' },
  { value: 'disbursed', label: 'Disbursed' },
]

const CUSTODY_COLUMNS: {
  key: 'captured' | 'decision' | 'agreement' | 'paid'
  role: string
  step: string
}[] = [
  { key: 'captured', role: 'Loan Officer', step: 'Captured' },
  { key: 'decision', role: 'Credit Manager', step: 'Decision' },
  { key: 'agreement', role: 'Loan Officer', step: 'Agreement signed' },
  { key: 'paid', role: 'Disbursement Officer', step: 'Paid out' },
]

/**
 * One custody point: who did it and when, or why it has not happened yet.
 *
 * Read off the same journey the detail page uses, so a name in this table and
 * a name on the file can never disagree.
 */
function StageCell({
  journey,
  stageKey,
}: {
  journey?: JourneyStage[]
  stageKey: 'captured' | 'decision' | 'agreement' | 'paid'
}) {
  const stage = journey?.find((entry) => entry.key === stageKey)

  if (!stage) {
    // Declined files stop after the decision, and walk-ins never reach a
    // commission stage, so a missing stage means "not on this file's route".
    return (
      <td className="px-4 py-3 text-slate-300" title="Not part of this file's route">
        n/a
      </td>
    )
  }

  if (!stage.done) {
    return (
      <td className="px-4 py-3">
        <span className="text-xs text-amber-700">Pending</span>
      </td>
    )
  }

  return (
    <td className="px-4 py-3">
      <p className="text-slate-900">{stage.actor ?? 'Unrecorded'}</p>
      <p className="text-xs text-slate-500">{stage.at ? formatDateTime(stage.at) : ''}</p>
      {stage.detail && <p className="text-xs text-slate-400">{stage.detail}</p>}
    </td>
  )
}

export function TrackApplicationsPage() {
  const { can } = useAuth()
  const [term, setTerm] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<ApplicationStatus | ''>('')
  const [page, setPage] = useState(1)

  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(term)
      setPage(1)
    }, 300)

    return () => clearTimeout(timer)
  }, [term])

  const { data, isPending, isError } = useQuery({
    queryKey: ['applications', { search, status, page }],
    queryFn: () => searchApplications({ search: search || undefined, status, page }),
    placeholderData: keepPreviousData,
  })

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Track loan applications</h1>
          <p className="mt-1 text-sm text-slate-500">
            Every application and where it has reached. Search by application number, client name,
            client number or ID number.
          </p>
        </div>

        {can('applications.create') && (
          <Link
            to="/applications/create"
            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
          >
            Create application
          </Link>
        )}
      </header>

      <div className="flex flex-wrap items-center gap-3">
        <input
          type="search"
          value={term}
          onChange={(event) => setTerm(event.target.value)}
          placeholder="Search applications…"
          className="w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
        />

        <div className="flex flex-wrap gap-1">
          {FILTERS.map((filter) => (
            <button
              key={filter.value}
              type="button"
              onClick={() => {
                setStatus(filter.value)
                setPage(1)
              }}
              className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                status === filter.value
                  ? 'bg-brand-50 text-brand-700'
                  : 'text-slate-600 hover:bg-slate-100'
              }`}
            >
              {filter.label}
            </button>
          ))}
        </div>
      </div>

      {isPending ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : isError ? (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          Applications could not be loaded.
        </p>
      ) : data.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
          No applications match this view.
        </p>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-medium">Application</th>
                  <th className="px-4 py-3 font-medium">Client</th>
                  <th className="px-4 py-3 text-right font-medium">Amount</th>
                  <th className="px-4 py-3 text-right font-medium">Instalment</th>
                  {CUSTODY_COLUMNS.map((column) => (
                    <th key={column.key} className="px-4 py-3 font-medium">
                      {column.role}
                      <br />
                      <small className="font-normal normal-case text-slate-400">
                        {column.step}
                      </small>
                    </th>
                  ))}
                  <th className="px-4 py-3 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((application) => (
                  <tr key={application.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3">
                      <Link
                        to={`/applications/${application.id}`}
                        className="font-mono text-sm font-medium text-brand-700 hover:text-brand-800"
                      >
                        {application.application_number}
                      </Link>
                      <p className="text-xs text-slate-500">
                        {application.term_months} months @ {application.interest_rate}%
                      </p>
                    </td>
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-900">
                        {application.client?.full_name ?? '-'}
                      </p>
                      <p className="font-mono text-xs text-slate-500">
                        {application.client?.client_number}
                      </p>
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-slate-900">
                      {formatMoney(application.amount)}
                    </td>
                    <td className="px-4 py-3 text-right text-slate-700">
                      {formatMoney(application.monthly_instalment)}
                    </td>
                    {CUSTODY_COLUMNS.map((column) => (
                      <StageCell
                        key={column.key}
                        journey={application.journey}
                        stageKey={column.key}
                      />
                    ))}
                    <td className="px-4 py-3">
                      <StatusBadge
                        status={application.status}
                        label={application.status_label}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between text-sm text-slate-500">
            <p>
              Showing {data.meta.from ?? 0}–{data.meta.to ?? 0} of {data.meta.total}
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={data.meta.current_page <= 1}
                className="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition-colors hover:bg-slate-100 disabled:opacity-50"
              >
                Previous
              </button>
              <button
                type="button"
                onClick={() => setPage((current) => current + 1)}
                disabled={data.meta.current_page >= data.meta.last_page}
                className="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 transition-colors hover:bg-slate-100 disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
