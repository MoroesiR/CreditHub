import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import {
  fetchDisbursements,
  holdDisbursement,
  payDisbursement,
  verifyDisbursement,
} from '@/features/disbursements/api'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import type { Disbursement, DisbursementStatus } from '@/types/disbursements'

const FILTERS: { value: DisbursementStatus | ''; label: string }[] = [
  { value: 'pending', label: 'Awaiting verification' },
  { value: 'verified', label: 'Ready to pay' },
  { value: 'on_hold', label: 'On hold' },
  { value: 'paid', label: 'Paid' },
  { value: '', label: 'All' },
]

const STATUS_STYLES: Record<DisbursementStatus, string> = {
  pending: 'bg-warn-50 text-warn-800 ring-1 ring-warn-200',
  verified: 'bg-info-50 text-info-800 ring-1 ring-info-200',
  paid: 'bg-good-600 text-white',
  on_hold: 'bg-bad-50 text-bad-700 ring-1 ring-bad-200',
}

export function DisbursementQueuePage() {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const [status, setStatus] = useState<DisbursementStatus | ''>('pending')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [paying, setPaying] = useState<Disbursement | null>(null)
  const [holding, setHolding] = useState<Disbursement | null>(null)
  const [reference, setReference] = useState('')
  const [holdReason, setHoldReason] = useState('')

  const { data, isPending, isError } = useQuery({
    queryKey: ['disbursements', { status, page }],
    queryFn: () => fetchDisbursements({ status, page }),
    placeholderData: keepPreviousData,
  })

  async function refresh() {
    setError(null)
    await queryClient.invalidateQueries({ queryKey: ['disbursements'] })
    await queryClient.invalidateQueries({ queryKey: ['commissions'] })
    await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    await queryClient.invalidateQueries({ queryKey: ['applications'] })
  }

  const verify = useMutation({
    mutationFn: verifyDisbursement,
    onSuccess: refresh,
    onError: (cause) => setError(errorMessage(cause, 'That payout could not be verified.')),
  })

  const pay = useMutation({
    mutationFn: ({ id, ref }: { id: number; ref: string }) => payDisbursement(id, ref),
    onSuccess: async (disbursement) => {
      setPaying(null)
      setReference('')
      await refresh()

      // Paying the loan is only half the payout. If the client was introduced,
      // the recruiter is now owed money, so the desk is taken straight there
      // rather than being left to remember.
      if (disbursement.commission && disbursement.commission.status === 'pending') {
        void navigate(`/commissions?highlight=${disbursement.commission.id}`)

        return
      }

      setNotice(
        `${disbursement.application?.application_number ?? 'The loan'} was paid. This client was a walk-in, so no commission is owed.`,
      )
    },
    onError: (cause) => setError(errorMessage(cause, 'That payment could not be recorded.')),
  })

  const hold = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => holdDisbursement(id, reason),
    onSuccess: async () => {
      setHolding(null)
      setHoldReason('')
      await refresh()
    },
    onError: (cause) => setError(errorMessage(cause, 'That payout could not be held.')),
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Disbursements</h1>
        <p className="mt-1 text-sm text-ink-500">
          Signed loans waiting to be paid. Verification and payment are two separate acts, and both
          are recorded against whoever performed them.
        </p>
      </header>

      {error && (
        <p className="rounded-lg border border-bad-200 bg-bad-50 p-4 text-sm text-bad-700">
          {error}
        </p>
      )}

      {notice && (
        <p className="rounded-lg border border-good-200 bg-good-50 p-4 text-sm text-good-800">
          {notice}
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
                : 'text-ink-600 hover:bg-ink-100'
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
        <p className="rounded-lg border border-bad-200 bg-bad-50 p-4 text-sm text-bad-700">
          The queue could not be loaded.
        </p>
      ) : data.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-ink-300 bg-white p-8 text-center text-sm text-ink-500">
          Nothing in this view.
        </p>
      ) : (
        <ul className="space-y-3">
          {data.data.map((item) => (
            <li key={item.id} className="rounded-lg border border-ink-200 bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <div className="flex items-center gap-3">
                    <Link
                      to={`/applications/${item.application?.id}`}
                      className="font-mono text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                      {item.application?.application_number}
                    </Link>
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_STYLES[item.status]}`}
                    >
                      {item.status_label}
                    </span>
                  </div>
                  <p className="mt-1 text-sm font-medium text-ink-900">
                    {item.application?.client?.full_name}
                  </p>
                  <p className="text-xs text-ink-500">
                    Signed {formatDate(item.created_at)}
                    {item.application?.recruiter
                      ? ` · introduced by ${item.application.recruiter.full_name}`
                      : ' · walk-in, no commission'}
                  </p>

                  {item.hold_reason && (
                    <p className="mt-2 text-sm text-bad-700">On hold: {item.hold_reason}</p>
                  )}

                  {item.status === 'paid' && (
                    <p className="mt-2 text-xs text-ink-500">
                      Paid {formatDate(item.paid_at)} by {item.paid_by} · reference{' '}
                      <span className="font-mono">{item.payment_reference}</span> · to{' '}
                      {item.paid_to_bank_name} {item.paid_to_account_number}
                    </p>
                  )}

                  {item.verified_at && item.status !== 'paid' && (
                    <p className="mt-2 text-xs text-ink-500">
                      Verified {formatDate(item.verified_at)} by {item.verified_by}
                    </p>
                  )}
                </div>

                <div className="text-right">
                  <p className="text-2xl font-semibold tracking-tight text-ink-900">
                    {formatMoney(item.amount)}
                  </p>

                  <div className="mt-3 flex flex-wrap justify-end gap-2">
                    {can('disbursements.verify') &&
                      (item.status === 'pending' || item.status === 'on_hold') && (
                        <button
                          type="button"
                          onClick={() => verify.mutate(item.id)}
                          disabled={verify.isPending}
                          className="rounded-md bg-info-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-info-700 disabled:opacity-60"
                        >
                          Verify
                        </button>
                      )}

                    {can('disbursements.pay') && item.status === 'verified' && (
                      <button
                        type="button"
                        onClick={() => {
                          setPaying(item)
                          setError(null)
                        }}
                        className="rounded-md bg-good-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-good-700"
                      >
                        Pay
                      </button>
                    )}

                    {can('disbursements.verify') && item.status !== 'paid' && (
                      <button
                        type="button"
                        onClick={() => {
                          setHolding(item)
                          setError(null)
                        }}
                        className="rounded-md border border-ink-300 px-3 py-1.5 text-sm font-medium text-ink-700 hover:bg-ink-100"
                      >
                        Hold
                      </button>
                    )}
                  </div>
                </div>
              </div>

              {paying?.id === item.id && (
                <div className="mt-4 rounded-md border border-good-200 bg-good-50 p-4">
                  <label className="block">
                    <span className="text-sm font-medium text-good-900">
                      Bank reference for this transfer
                    </span>
                    <input
                      value={reference}
                      onChange={(event) => setReference(event.target.value)}
                      placeholder="e.g. EFT-20260902-0001"
                      className="mt-1 w-full max-w-sm rounded-md border border-good-300 bg-white px-3 py-2 text-sm outline-none focus:border-good-500"
                    />
                  </label>
                  <p className="mt-1 text-xs text-good-800">
                    Without it, a payment here cannot be tied to one on a bank statement.
                  </p>
                  <div className="mt-3 flex gap-2">
                    <button
                      type="button"
                      onClick={() => pay.mutate({ id: item.id, ref: reference })}
                      disabled={reference.trim().length === 0 || pay.isPending}
                      className="rounded-md bg-good-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-good-700 disabled:opacity-60"
                    >
                      {pay.isPending ? 'Releasing…' : `Release ${formatMoney(item.amount)}`}
                    </button>
                    <button
                      type="button"
                      onClick={() => setPaying(null)}
                      className="rounded-md border border-good-300 px-3 py-1.5 text-sm font-medium text-good-900 hover:bg-good-100"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}

              {holding?.id === item.id && (
                <div className="mt-4 rounded-md border border-bad-200 bg-bad-50 p-4">
                  <label className="block">
                    <span className="text-sm font-medium text-bad-900">Why is this on hold?</span>
                    <input
                      value={holdReason}
                      onChange={(event) => setHoldReason(event.target.value)}
                      className="mt-1 w-full max-w-sm rounded-md border border-bad-300 bg-white px-3 py-2 text-sm outline-none focus:border-bad-500"
                    />
                  </label>
                  <div className="mt-3 flex gap-2">
                    <button
                      type="button"
                      onClick={() => hold.mutate({ id: item.id, reason: holdReason })}
                      disabled={holdReason.trim().length === 0 || hold.isPending}
                      className="rounded-md bg-bad-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-bad-700 disabled:opacity-60"
                    >
                      Put on hold
                    </button>
                    <button
                      type="button"
                      onClick={() => setHolding(null)}
                      className="rounded-md border border-bad-300 px-3 py-1.5 text-sm font-medium text-bad-900 hover:bg-bad-100"
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
        <div className="flex items-center justify-between text-sm text-ink-500">
          <p>
            Showing {data.meta.from ?? 0}–{data.meta.to ?? 0} of {data.meta.total}
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setPage((current) => Math.max(1, current - 1))}
              disabled={data.meta.current_page <= 1}
              className="rounded-md border border-ink-300 px-3 py-1.5 font-medium text-ink-700 hover:bg-ink-100 disabled:opacity-50"
            >
              Previous
            </button>
            <button
              type="button"
              onClick={() => setPage((current) => current + 1)}
              disabled={data.meta.current_page >= data.meta.last_page}
              className="rounded-md border border-ink-300 px-3 py-1.5 font-medium text-ink-700 hover:bg-ink-100 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
