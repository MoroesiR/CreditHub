import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { useAuth } from '@/features/auth/useAuth'
import {
  approveChangeRequest,
  fetchChangeRequests,
  rejectChangeRequest,
} from '@/features/change-requests/api'
import { errorMessage } from '@/lib/api'
import { formatDateTime } from '@/lib/format'

/**
 * The change requests raised against the record being looked at.
 *
 * This sits on the client and recruiter pages so that a notification can lead
 * to the record itself rather than to a list. Deciding whether a surname or a
 * bank account should change means looking at the person first, and an
 * administrator arriving from the bell can now do both in one place.
 */
export function RecordChangeRequests({
  subjectKind,
  subjectId,
}: {
  subjectKind: 'client' | 'recruiter'
  subjectId: number
}) {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  const highlighted = Number(searchParams.get('request')) || null
  const anchorRef = useRef<HTMLDivElement>(null)

  const [error, setError] = useState<string | null>(null)
  const [rejecting, setRejecting] = useState<number | null>(null)
  const [note, setNote] = useState('')

  const mayReview = can('change-requests.review')

  const { data } = useQuery({
    queryKey: ['change-requests', { subjectKind, subjectId }],
    queryFn: () => fetchChangeRequests({ subject_kind: subjectKind, subject_id: subjectId }),
    enabled: can('change-requests.view'),
  })

  const requests = data?.data ?? []
  const pending = requests.filter((request) => request.status === 'pending')

  // Arriving from a notification, put the request in view rather than leaving
  // it below the fold on a long profile.
  useEffect(() => {
    if (highlighted && requests.length > 0) {
      anchorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }, [highlighted, requests.length])

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

  // Nothing outstanding and nothing being pointed at: the panel would only be
  // noise on a record whose history is settled.
  const recentlyDecided = requests.filter((request) => request.id === highlighted)
  const shown = pending.length > 0 ? pending : recentlyDecided

  if (shown.length === 0) {
    return null
  }

  return (
    <section
      ref={anchorRef}
      className={`rounded-lg border p-6 ${
        pending.length > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white'
      }`}
    >
      <h2
        className={`text-sm font-semibold uppercase tracking-wide ${
          pending.length > 0 ? 'text-amber-900' : 'text-slate-500'
        }`}
      >
        {pending.length > 0 ? 'Change requested' : 'Recent change request'}
      </h2>

      {error && (
        <p className="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}
        </p>
      )}

      <ul className="mt-4 space-y-4">
        {shown.map((request) => (
          <li
            key={request.id}
            className={`rounded-md bg-white p-4 ${
              request.id === highlighted ? 'ring-2 ring-amber-300' : 'border border-slate-200'
            }`}
          >
            <p className="text-sm text-slate-700">{request.reason}</p>

            <dl className="mt-3 space-y-1">
              {Object.entries(request.changes).map(([field, value]) => (
                <div key={field} className="flex flex-wrap items-baseline gap-2 text-sm">
                  <dt className="font-medium capitalize text-slate-700">
                    {field.replace(/_/g, ' ')}
                  </dt>
                  <dd className="text-slate-900">
                    {request.replaced_values?.[field] && (
                      <span className="mr-2 text-slate-400 line-through">
                        {request.replaced_values[field]}
                      </span>
                    )}
                    {value}
                  </dd>
                </div>
              ))}
            </dl>

            <p className="mt-3 text-xs text-slate-500">
              Asked by {request.requested_by ?? 'unknown'} · {formatDateTime(request.created_at)}
              {request.reviewed_at
                ? ` · ${request.status_label} by ${request.reviewed_by} · ${formatDateTime(request.reviewed_at)}`
                : ''}
            </p>

            {request.review_note && (
              <p className="mt-1 text-xs text-slate-600">Note: {request.review_note}</p>
            )}

            {mayReview && request.status === 'pending' && (
              <>
                <div className="mt-4 flex gap-2">
                  <button
                    type="button"
                    onClick={() => approve.mutate(request.id)}
                    disabled={approve.isPending}
                    className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                  >
                    Approve and apply
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

                {rejecting === request.id && (
                  <div className="mt-3 rounded-md border border-red-200 bg-red-50 p-3">
                    <label className="block">
                      <span className="text-sm font-medium text-red-900">
                        Why is this being rejected?
                      </span>
                      <input
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        placeholder="The officer is told this, so they know what to correct."
                        className="mt-1 w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm outline-none focus:border-red-500"
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
              </>
            )}
          </li>
        ))}
      </ul>
    </section>
  )
}
