import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import {
  approveChangeRequest,
  fetchChangeRequests,
  rejectChangeRequest,
} from '@/features/change-requests/api'
import { errorMessage } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { ChangeRequest, ChangeRequestStatus } from '@/types/changeRequests'

const FILTERS: { value: ChangeRequestStatus | ''; label: string }[] = [
  { value: 'pending', label: 'Awaiting a decision' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: '', label: 'All' },
]

const STATUS_STYLES: Record<ChangeRequestStatus, string> = {
  pending: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
  approved: 'bg-emerald-600 text-white',
  rejected: 'bg-red-50 text-red-700 ring-1 ring-red-200',
}

function FieldChange({ request }: { request: ChangeRequest }) {
  return (
    <dl className="mt-3 space-y-1">
      {Object.entries(request.changes).map(([field, value]) => (
        <div key={field} className="flex flex-wrap items-baseline gap-2 text-sm">
          <dt className="font-medium capitalize text-slate-700">{field.replace(/_/g, ' ')}</dt>
          <dd className="text-slate-500">
            {request.replaced_values?.[field] ? (
              <>
                <span className="line-through">{request.replaced_values[field]}</span>{' '}
                <span className="text-slate-900">{value}</span>
              </>
            ) : (
              <span className="text-slate-900">{value}</span>
            )}
          </dd>
        </div>
      ))}
    </dl>
  )
}

export function ChangeRequestsPage() {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<ChangeRequestStatus | ''>('pending')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)
  const [rejecting, setRejecting] = useState<number | null>(null)
  const [note, setNote] = useState('')

  const mayReview = can('change-requests.review')

  const { data, isPending, isError } = useQuery({
    queryKey: ['change-requests', { status, page }],
    queryFn: () => fetchChangeRequests({ status, page }),
    placeholderData: keepPreviousData,
  })

  async function refresh() {
    setError(null)
    await queryClient.invalidateQueries({ queryKey: ['change-requests'] })
    await queryClient.invalidateQueries({ queryKey: ['clients'] })
    await queryClient.invalidateQueries({ queryKey: ['recruiters'] })
    await queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }

  const approve = useMutation({
    mutationFn: (id: number) => approveChangeRequest(id),
    onSuccess: refresh,
    onError: (cause) => setError(errorMessage(cause, 'That request could not be approved.')),
  })

  const reject = useMutation({
    mutationFn: ({ id, why }: { id: number; why: string }) => rejectChangeRequest(id, why),
    onSuccess: async () => {
      setRejecting(null)
      setNote('')
      await refresh()
    },
    onError: (cause) => setError(errorMessage(cause, 'That request could not be rejected.')),
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Change requests</h1>
        <p className="mt-1 text-sm text-slate-500">
          {mayReview
            ? 'Corrections to a client or recruiter, waiting on an administrator. Nothing on the record moves until one is approved.'
            : 'Corrections you have asked an administrator to make. Nothing changes until one is approved.'}
        </p>
      </header>

      {error && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </p>
      )}

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

      {isPending ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : isError ? (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          Change requests could not be loaded.
        </p>
      ) : data.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
          Nothing in this view.
        </p>
      ) : (
        <ul className="space-y-3">
          {data.data.map((request) => (
            <li key={request.id} className="rounded-lg border border-slate-200 bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-3">
                    <Link
                      to={
                        request.subject_kind === 'client'
                          ? `/clients/${request.subject_id}`
                          : `/recruiters/${request.subject_id}`
                      }
                      className="font-medium text-brand-700 hover:text-brand-800"
                    >
                      {request.subject_label ?? 'Record removed'}
                    </Link>
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_STYLES[request.status]}`}
                    >
                      {request.status_label}
                    </span>
                    <span className="text-xs uppercase tracking-wide text-slate-400">
                      {request.subject_kind}
                    </span>
                  </div>

                  <p className="mt-2 text-sm text-slate-600">{request.reason}</p>

                  <FieldChange request={request} />

                  <p className="mt-3 text-xs text-slate-500">
                    Asked by {request.requested_by ?? 'unknown'} ·{' '}
                    {formatDateTime(request.created_at)}
                    {request.reviewed_at
                      ? ` · decided by ${request.reviewed_by} · ${formatDateTime(request.reviewed_at)}`
                      : ''}
                  </p>

                  {request.review_note && (
                    <p className="mt-1 text-xs text-slate-600">Note: {request.review_note}</p>
                  )}
                </div>

                {mayReview && request.status === 'pending' && (
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => approve.mutate(request.id)}
                      disabled={approve.isPending}
                      className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                    >
                      Approve
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setRejecting(request.id)
                        setError(null)
                      }}
                      className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50"
                    >
                      Reject
                    </button>
                  </div>
                )}
              </div>

              {rejecting === request.id && (
                <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-4">
                  <label className="block">
                    <span className="text-sm font-medium text-red-900">
                      Why is this being rejected?
                    </span>
                    <input
                      value={note}
                      onChange={(event) => setNote(event.target.value)}
                      placeholder="The officer is told this, so they know what to correct."
                      className="mt-1 w-full max-w-lg rounded-md border border-red-300 bg-white px-3 py-2 text-sm outline-none focus:border-red-500"
                    />
                  </label>
                  <div className="mt-3 flex gap-2">
                    <button
                      type="button"
                      onClick={() => reject.mutate({ id: request.id, why: note })}
                      disabled={note.trim().length === 0 || reject.isPending}
                      className="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
                    >
                      Confirm rejection
                    </button>
                    <button
                      type="button"
                      onClick={() => setRejecting(null)}
                      className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-900 hover:bg-red-100"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-slate-500">
          <p>
            Showing {data.meta.from ?? 0}-{data.meta.to ?? 0} of {data.meta.total}
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setPage((current) => Math.max(1, current - 1))}
              disabled={data.meta.current_page <= 1}
              className="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
            >
              Previous
            </button>
            <button
              type="button"
              onClick={() => setPage((current) => current + 1)}
              disabled={data.meta.current_page >= data.meta.last_page}
              className="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
