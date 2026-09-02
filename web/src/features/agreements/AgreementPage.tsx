import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { SignaturePad } from '@/features/agreements/SignaturePad'
import { WebcamCapture } from '@/features/agreements/WebcamCapture'
import { fetchAgreement, generateAgreement, signAgreement } from '@/features/agreements/api'
import { fetchApplication } from '@/features/applications/api'
import { useAuth } from '@/features/auth/useAuth'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'

export function AgreementPage() {
  const { id } = useParams<{ id: string }>()
  const applicationId = Number(id)
  const { can } = useAuth()
  const queryClient = useQueryClient()

  const [signedName, setSignedName] = useState('')
  const [signature, setSignature] = useState<string | null>(null)
  const [photo, setPhoto] = useState<string | null>(null)
  const [accepted, setAccepted] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const { data: application } = useQuery({
    queryKey: ['applications', applicationId],
    queryFn: () => fetchApplication(applicationId),
    enabled: Number.isFinite(applicationId),
  })

  const { data: agreement, isPending } = useQuery({
    queryKey: ['agreements', applicationId],
    queryFn: () => fetchAgreement(applicationId),
    enabled: Number.isFinite(applicationId),
  })

  const draw = useMutation({
    mutationFn: () => generateAgreement(applicationId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['agreements', applicationId] }),
    onError: (cause) => setError(errorMessage(cause, 'The agreement could not be drawn.')),
  })

  const sign = useMutation({
    mutationFn: () =>
      signAgreement(applicationId, {
        signed_name: signedName,
        signature: signature ?? '',
        photo,
      }),
    onSuccess: async () => {
      setError(null)
      await queryClient.invalidateQueries({ queryKey: ['agreements', applicationId] })
      await queryClient.invalidateQueries({ queryKey: ['applications'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (cause) => setError(errorMessage(cause, 'The signature could not be recorded.')),
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

  const canSign =
    signedName.trim().length > 0 && signature !== null && accepted && !sign.isPending

  return (
    <div className="space-y-6">
      <header>
        <Link
          to={`/applications/${applicationId}`}
          className="text-sm text-brand-600 hover:text-brand-700"
        >
          ← Back to {application.application_number}
        </Link>
        <h1 className="mt-1 text-2xl font-semibold tracking-tight">Credit agreement</h1>
        <p className="mt-1 text-sm text-slate-500">
          {application.client?.full_name} · {application.client?.client_number}
        </p>
      </header>

      {error && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </p>
      )}

      {application.status !== 'approved' && application.status !== 'agreement_signed' ? (
        <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          This application is {application.status_label.toLowerCase()}. An agreement is only drawn
          once a credit manager has approved it.
        </p>
      ) : !agreement ? (
        <div className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
          <p className="text-sm text-slate-600">
            No agreement has been drawn for this application yet.
          </p>
          {can('agreements.generate') && (
            <button
              type="button"
              onClick={() => draw.mutate()}
              disabled={draw.isPending}
              className="mt-4 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
            >
              {draw.isPending ? 'Drawing…' : 'Draw the agreement'}
            </button>
          )}
        </div>
      ) : (
        <>
          <article className="rounded-lg border border-slate-200 bg-white p-8">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
              <div>
                <p className="text-lg font-semibold tracking-tight text-brand-700">CreditHub</p>
                <p className="text-sm text-slate-500">Credit agreement</p>
              </div>
              <p className="font-mono text-sm text-slate-600">{agreement.agreement_number}</p>
            </div>

            <dl className="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2">
              <div>
                <dt className="text-sm text-slate-500">Borrower</dt>
                <dd className="text-sm font-medium text-slate-900">
                  {application.client?.full_name}
                </dd>
                <dd className="font-mono text-xs text-slate-500">
                  {application.client?.id_number}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Application</dt>
                <dd className="font-mono text-sm text-slate-900">
                  {application.application_number}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Amount advanced</dt>
                <dd className="text-lg font-semibold text-slate-900">
                  {formatMoney(agreement.amount)}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Monthly instalment</dt>
                <dd className="text-lg font-semibold text-slate-900">
                  {formatMoney(agreement.monthly_instalment)}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Term</dt>
                <dd className="text-sm text-slate-900">
                  {agreement.term_months} months at {agreement.interest_rate}% a year
                </dd>
              </div>
              <div>
                <dt className="text-sm text-slate-500">Total repayable</dt>
                <dd className="text-sm text-slate-900">
                  {formatMoney(agreement.total_repayable)}
                </dd>
              </div>
            </dl>

            <p className="mt-6 border-t border-slate-200 pt-4 text-sm leading-relaxed text-slate-600">
              The borrower agrees to repay {formatMoney(agreement.total_repayable)} in{' '}
              {agreement.term_months} monthly instalments of{' '}
              {formatMoney(agreement.monthly_instalment)}, the first falling due one month after the
              amount is advanced. These terms were fixed when this agreement was drawn on{' '}
              {formatDate(agreement.generated_at)} and do not change.
            </p>
          </article>

          {agreement.is_signed ? (
            <section className="rounded-lg border border-emerald-200 bg-emerald-50 p-6">
              <h2 className="text-sm font-semibold uppercase tracking-wide text-emerald-900">
                Signed
              </h2>
              <p className="mt-2 text-sm text-emerald-800">
                Signed by <span className="font-medium">{agreement.signed_name}</span> on{' '}
                {formatDate(agreement.signed_at)}, witnessed by {agreement.witnessed_by}
                {agreement.signed_ip ? ` from ${agreement.signed_ip}` : ''}.
              </p>
              <p className="mt-1 text-xs text-emerald-700">
                Signature captured: {agreement.has_signature ? 'yes' : 'no'} · Photograph captured:{' '}
                {agreement.has_photo ? 'yes' : 'no'}. This loan is now in the disbursement queue.
              </p>
            </section>
          ) : can('agreements.sign') ? (
            <section className="rounded-lg border border-slate-200 bg-white p-6">
              <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Client signature
              </h2>
              <p className="mt-1 text-sm text-slate-500">
                The client signs on screen. The photograph is separate evidence that they were the
                one at the desk when they signed.
              </p>

              <div className="mt-5 grid gap-6 lg:grid-cols-2">
                <div>
                  <p className="mb-2 text-sm font-medium text-slate-700">Signature</p>
                  <SignaturePad onChange={setSignature} />
                </div>

                <div>
                  <p className="mb-2 text-sm font-medium text-slate-700">
                    Photograph <span className="font-normal text-slate-500">(optional)</span>
                  </p>
                  <WebcamCapture photo={photo} onChange={setPhoto} />
                </div>
              </div>

              <label className="mt-6 block">
                <span className="text-sm font-medium text-slate-700">
                  Full name of the person signing
                </span>
                <input
                  value={signedName}
                  onChange={(event) => setSignedName(event.target.value)}
                  placeholder={application.client?.full_name}
                  className="mt-1 w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                />
              </label>

              <label className="mt-4 flex items-start gap-2">
                <input
                  type="checkbox"
                  checked={accepted}
                  onChange={(event) => setAccepted(event.target.checked)}
                  className="mt-0.5"
                />
                <span className="text-sm text-slate-600">
                  The terms above were read to the client, and the client signed in my presence.
                </span>
              </label>

              <div className="mt-6 flex items-center justify-end gap-3">
                {!canSign && (
                  <p className="mr-auto text-sm text-slate-500">
                    {signature === null
                      ? 'The client must sign before this can be submitted.'
                      : signedName.trim().length === 0
                        ? 'Type the name of the person signing.'
                        : !accepted
                          ? 'Confirm the terms were read and the client signed in your presence.'
                          : ''}
                  </p>
                )}
                <button
                  type="button"
                  onClick={() => sign.mutate()}
                  disabled={!canSign}
                  className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
                >
                  {sign.isPending ? 'Submitting…' : 'Submit signed agreement'}
                </button>
              </div>
            </section>
          ) : (
            <p className="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500">
              This agreement has not been signed. Your role may not capture signatures.
            </p>
          )}
        </>
      )}
    </div>
  )
}
