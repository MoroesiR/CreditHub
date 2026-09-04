import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { DocumentModal } from '@/components/DocumentModal'
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
  const [viewing, setViewing] = useState<{ path: string; title: string; subtitle: string } | null>(
    null,
  )
  const onView = setViewing


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
        pending.length > 0 ? 'border-warn-200 bg-warn-50' : 'border-ink-200 bg-white'
      }`}
    >
      {viewing && (
        <DocumentModal
          path={viewing.path}
          title={viewing.title}
          subtitle={viewing.subtitle}
          onClose={() => setViewing(null)}
        />
      )}

      <h2
        className={`text-sm font-semibold uppercase tracking-wide ${
          pending.length > 0 ? 'text-warn-900' : 'text-ink-500'
        }`}
      >
        {pending.length > 0 ? 'Change requested' : 'Recent change request'}
      </h2>

      {error && (
        <p className="mt-3 rounded-md border border-bad-200 bg-bad-50 p-3 text-sm text-bad-700">
          {error}
        </p>
      )}

      <ul className="mt-4 space-y-4">
        {shown.map((request) => (
          <li
            key={request.id}
            className={`rounded-md bg-white p-4 ${
              request.id === highlighted ? 'ring-2 ring-warn-300' : 'border border-ink-200'
            }`}
          >
            <p className="text-sm text-ink-700">{request.reason}</p>

            <dl className="mt-3 space-y-1">
              {Object.entries(request.changes).map(([field, value]) => (
                <div key={field} className="flex flex-wrap items-baseline gap-2 text-sm">
                  <dt className="font-medium capitalize text-ink-700">
                    {field.replace(/_/g, ' ')}
                  </dt>
                  <dd className="text-ink-900">
                    {request.replaced_values?.[field] && (
                      <span className="mr-2 text-ink-400 line-through">
                        {request.replaced_values[field]}
                      </span>
                    )}
                    {value}
                  </dd>
                </div>
              ))}
            </dl>

{request.documents && request.documents.length > 0 && (
              <div className="mt-3 rounded-md border border-ink-200 p-3">
                <p className="text-xs font-medium uppercase tracking-wide text-ink-500">
                  Proof attached
                </p>
                <ul className="mt-2 space-y-1">
                  {request.documents.map((document) => (
                    <li key={document.id} className="flex items-center justify-between gap-3">
                      <span className="min-w-0 text-sm text-ink-700">
                        {document.type_label}
                        <span className="ml-2 truncate text-xs text-ink-500">
                          {document.original_name}
                        </span>
                      </span>
                      <button
                        type="button"
                        onClick={() =>
                          onView({
                            path: `/change-requests/${request.id}/documents/${document.id}`,
                            title: document.type_label,
                            subtitle: document.original_name,
                          })
                        }
                        className="whitespace-nowrap rounded-md border border-ink-300 px-2.5 py-1 text-xs font-medium text-ink-700 hover:bg-ink-100"
                      >
                        View
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <p className="mt-3 text-xs text-ink-500">
              Asked by {request.requested_by ?? 'unknown'} · {formatDateTime(request.created_at)}
              {request.reviewed_at
                ? ` · ${request.status_label} by ${request.reviewed_by} · ${formatDateTime(request.reviewed_at)}`
                : ''}
            </p>

            {request.review_note && (
              <p className="mt-1 text-xs text-ink-600">Note: {request.review_note}</p>
            )}

            {mayReview && request.status === 'pending' && (
              <>
                <div className="mt-4 flex gap-2">
                  <button
                    type="button"
                    onClick={() => approve.mutate(request.id)}
                    disabled={approve.isPending}
                    className="rounded-md bg-good-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-good-700 disabled:opacity-60"
                  >
                    Approve and apply
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setRejecting(request.id)
                      setError(null)
                    }}
                    className="rounded-md border border-bad-300 px-3 py-1.5 text-sm font-medium text-bad-700 hover:bg-bad-50"
                  >
                    Reject
                  </button>
                </div>

                {rejecting === request.id && (
                  <div className="mt-3 rounded-md border border-bad-200 bg-bad-50 p-3">
                    <label className="block">
                      <span className="text-sm font-medium text-bad-900">
                        Why is this being rejected?
                      </span>
                      <input
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        placeholder="The officer is told this, so they know what to correct."
                        className="mt-1 w-full rounded-md border border-bad-300 bg-white px-3 py-2 text-sm outline-none focus:border-bad-500"
                      />
                    </label>
                    <div className="mt-3 flex gap-2">
                      <button
                        type="button"
                        onClick={() => reject.mutate({ id: request.id, why: note })}
                        disabled={note.trim().length === 0 || reject.isPending}
                        className="rounded-md bg-bad-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-bad-700 disabled:opacity-60"
                      >
                        Confirm rejection
                      </button>
                      <button
                        type="button"
                        onClick={() => setRejecting(null)}
                        className="rounded-md border border-bad-300 px-3 py-1.5 text-sm font-medium text-bad-900 hover:bg-bad-100"
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
