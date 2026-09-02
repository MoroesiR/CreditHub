import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { StatusBadge } from '@/features/applications/StatusBadge'
import { searchApplications } from '@/features/applications/api'
import { useAuth } from '@/features/auth/useAuth'
import { formatDate, formatMoney } from '@/lib/format'
import type { ApplicationStatus } from '@/types/applications'

const FILTERS: { value: ApplicationStatus | ''; label: string }[] = [
  { value: '', label: 'All' },
  { value: 'submitted', label: 'Awaiting decision' },
  { value: 'approved', label: 'Approved' },
  { value: 'declined', label: 'Declined' },
  { value: 'agreement_signed', label: 'Agreement signed' },
  { value: 'disbursed', label: 'Disbursed' },
]

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
                  <th className="px-4 py-3 font-medium">Submitted</th>
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
                    <td className="px-4 py-3 text-slate-600">
                      {formatDate(application.submitted_at)}
                    </td>
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
