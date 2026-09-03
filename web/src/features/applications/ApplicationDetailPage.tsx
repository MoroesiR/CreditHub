import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { JourneyTimeline } from '@/features/applications/JourneyTimeline'
import { StatusBadge } from '@/features/applications/StatusBadge'
import {
  decideApplication,
  downloadDocument,
  fetchApplication,
  fetchApplicationAuditTrail,
} from '@/features/applications/api'
import { useAuth } from '@/features/auth/useAuth'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'

export function ApplicationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const applicationId = Number(id)
  const { can } = useAuth()
  const queryClient = useQueryClient()

  const [declineReason, setDeclineReason] = useState('')
  const [isDeclining, setIsDeclining] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  const { data: application, isPending } = useQuery({
    queryKey: ['applications', applicationId],
    queryFn: () => fetchApplication(applicationId),
    enabled: Number.isFinite(applicationId),
  })

  const { data: trail } = useQuery({
    queryKey: ['applications', applicationId, 'audit-trail'],
    queryFn: () => fetchApplicationAuditTrail(applicationId),
    enabled: Number.isFinite(applicationId),
  })

  const decision = useMutation({
    mutationFn: ({ approved, reason }: { approved: boolean; reason?: string }) =>
      decideApplication(applicationId, approved, reason),
    onSuccess: async () => {
      setActionError(null)
      setIsDeclining(false)
      setDeclineReason('')
      await queryClient.invalidateQueries({ queryKey: ['applications'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (error) => setActionError(errorMessage(error, 'The decision could not be recorded.')),
  })

  if (isPending) {
    return (
      <div className="flex justify-center py-10">
        <Spinner />
      </div>
    )
  }

  if (!application) {
    return (
      <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        That application could not be found.
      </p>
    )
  }

  const awaitingDecision = application.status === 'submitted'

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <Link to="/applications" className="text-sm text-brand-600 hover:text-brand-700">
            ← All applications
          </Link>
          <h1 className="mt-1 font-mono text-2xl font-semibold tracking-tight">
            {application.application_number}
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            {application.client?.full_name} · {application.client?.client_number}
          </p>
        </div>
        <StatusBadge status={application.status} label={application.status_label} />
      </header>

      {application.status === 'declined' && application.decline_reason && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <span className="font-medium">Declined:</span> {application.decline_reason}
        </p>
      )}

      {application.status === 'approved' && can('agreements.view') && (
        <section className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-6">
          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-emerald-900">
              Approved - the agreement can be signed
            </h2>
            <p className="mt-1 text-sm text-emerald-800">
              Nothing is paid until the client signs. Signing moves this file into the disbursement
              queue.
            </p>
          </div>
          <Link
            to={`/applications/${applicationId}/agreement`}
            className="whitespace-nowrap rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700"
          >
            Open agreement
          </Link>
        </section>
      )}

      {application.status === 'agreement_signed' && can('agreements.view') && (
        <section className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-sky-200 bg-sky-50 p-6">
          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-sky-900">
              Agreement signed
            </h2>
            <p className="mt-1 text-sm text-sky-800">
              Waiting on a disbursement officer to verify and pay.
            </p>
          </div>
          <Link
            to={`/applications/${applicationId}/agreement`}
            className="whitespace-nowrap rounded-md border border-sky-300 bg-white px-4 py-2 text-sm font-medium text-sky-800 transition-colors hover:bg-sky-100"
          >
            View agreement
          </Link>
        </section>
      )}

      <section className="grid gap-4 sm:grid-cols-4">
        {[
          { label: 'Amount', value: formatMoney(application.amount) },
          { label: 'Monthly instalment', value: formatMoney(application.monthly_instalment) },
          { label: 'Total repayable', value: formatMoney(application.total_repayable) },
          {
            label: 'Term',
            value: `${application.term_months} months @ ${application.interest_rate}%`,
          },
        ].map((tile) => (
          <div key={tile.label} className="rounded-lg border border-slate-200 bg-white p-5">
            <p className="text-sm font-medium text-slate-500">{tile.label}</p>
            <p className="mt-2 text-xl font-semibold tracking-tight text-slate-900">{tile.value}</p>
          </div>
        ))}
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        <div className="rounded-lg border border-slate-200 bg-white p-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Affordability at capture
          </h2>
          <p className="mt-3 text-sm text-slate-600">
            Disposable income when this application was made:{' '}
            <span className="font-medium text-slate-900">
              {application.disposable_income_at_capture !== null
                ? formatMoney(application.disposable_income_at_capture)
                : '-'}
            </span>
          </p>
          <p className="mt-1 text-xs text-slate-500">
            Copied onto the file at capture, so the decision can be judged against the figure it was
            actually made on.
          </p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Introduction
          </h2>
          {application.recruiter ? (
            <p className="mt-3 text-sm text-slate-600">
              Introduced by{' '}
              <Link
                to={`/recruiters/${application.recruiter.id}`}
                className="font-medium text-brand-700 hover:text-brand-800"
              >
                {application.recruiter.full_name}
              </Link>{' '}
              ({application.recruiter.recruiter_number}). Commission is earned on this loan when it
              is disbursed.
            </p>
          ) : (
            <p className="mt-3 text-sm text-slate-600">
              Walk-in - no recruiter, so no commission is earned on this loan.
            </p>
          )}
          {application.purpose && (
            <p className="mt-3 text-sm text-slate-600">Purpose: {application.purpose}</p>
          )}
        </div>
      </section>

      {application.journey && application.journey.length > 0 && (
        <section className="rounded-lg border border-slate-200 bg-white p-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Where this file has been
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            Each stage is held by a different person on purpose, so no single
            desk can carry a loan from capture to payment.
          </p>
          <JourneyTimeline stages={application.journey} />
        </section>
      )}

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Supporting documents
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Held in private storage and streamed through an authenticated request - never a public
          URL.
        </p>

        {!application.documents || application.documents.length === 0 ? (
          <p className="mt-4 text-sm text-slate-500">No documents on this file.</p>
        ) : (
          <ul className="mt-4 divide-y divide-slate-100">
            {application.documents.map((document) => (
              <li key={document.id} className="flex items-center justify-between gap-4 py-3">
                <div>
                  <p className="text-sm font-medium text-slate-900">{document.type_label}</p>
                  <p className="text-xs text-slate-500">
                    {document.original_name} · {Math.round(document.size_bytes / 1024)} KB
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() =>
                    void downloadDocument(applicationId, document.id, document.original_name)
                  }
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                >
                  Download
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      {can('applications.decide') && awaitingDecision && (
        <section className="rounded-lg border border-amber-200 bg-amber-50 p-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-amber-900">
            Credit decision
          </h2>
          <p className="mt-1 text-sm text-amber-800">
            Approving does not release money. The agreement must still be signed, and a
            disbursement officer verifies and pays it.
          </p>

          {actionError && (
            <p className="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {actionError}
            </p>
          )}

          {isDeclining ? (
            <div className="mt-4 space-y-3">
              <label className="block">
                <span className="text-sm font-medium text-amber-900">Reason for declining</span>
                <input
                  value={declineReason}
                  onChange={(event) => setDeclineReason(event.target.value)}
                  placeholder="The client is entitled to a reason."
                  className="mt-1 w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-sm outline-none focus:border-amber-500"
                />
              </label>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => decision.mutate({ approved: false, reason: declineReason })}
                  disabled={declineReason.trim().length === 0 || decision.isPending}
                  className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
                >
                  Confirm decline
                </button>
                <button
                  type="button"
                  onClick={() => setIsDeclining(false)}
                  className="rounded-md border border-amber-300 px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100"
                >
                  Cancel
                </button>
              </div>
            </div>
          ) : (
            <div className="mt-4 flex gap-3">
              <button
                type="button"
                onClick={() => decision.mutate({ approved: true })}
                disabled={decision.isPending}
                className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
              >
                {decision.isPending ? 'Recording…' : 'Approve'}
              </button>
              <button
                type="button"
                onClick={() => setIsDeclining(true)}
                className="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
              >
                Decline
              </button>
            </div>
          )}
        </section>
      )}

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Audit trail
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Every step this file has been through. Entries are never edited or removed.
        </p>

        {!trail || trail.length === 0 ? (
          <p className="mt-4 text-sm text-slate-500">Nothing recorded yet.</p>
        ) : (
          <ol className="mt-4 space-y-3">
            {trail.map((event) => (
              <li key={event.id} className="border-l-2 border-slate-200 pl-4">
                <p className="text-sm text-slate-900">{event.summary}</p>
                <p className="mt-0.5 text-xs text-slate-500">
                  {event.actor_name} · {formatDate(event.created_at)}
                  {event.created_at
                    ? ` at ${new Date(event.created_at).toLocaleTimeString('en-ZA')}`
                    : ''}
                  {event.ip_address ? ` · ${event.ip_address}` : ''}
                </p>
              </li>
            ))}
          </ol>
        )}
      </section>
    </div>
  )
}
