import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import {
  fetchLoanBook,
  fetchRepayments,
  recordRepayment,
  reverseRepayment,
} from '@/features/repayments/api'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import type { LoanBookRow } from '@/types/repayments'

function today(): string {
  return new Date().toISOString().slice(0, 10)
}

export function RepaymentsPage() {
  const { can } = useAuth()
  const queryClient = useQueryClient()

  const [term, setTerm] = useState('')
  const [search, setSearch] = useState('')
  const [openLoan, setOpenLoan] = useState<LoanBookRow | null>(null)
  const [error, setError] = useState<string | null>(null)

  const [amount, setAmount] = useState('')
  const [receivedOn, setReceivedOn] = useState(today())
  const [method, setMethod] = useState('debit_order')
  const [reference, setReference] = useState('')
  const [reversing, setReversing] = useState<number | null>(null)
  const [reason, setReason] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setSearch(term), 300)

    return () => clearTimeout(timer)
  }, [term])

  const { data, isPending, isError } = useQuery({
    queryKey: ['loans', { search }],
    queryFn: () => fetchLoanBook(search),
    placeholderData: keepPreviousData,
  })

  const { data: history } = useQuery({
    queryKey: ['loans', openLoan?.id, 'repayments'],
    queryFn: () => fetchRepayments(openLoan?.id ?? 0),
    enabled: openLoan !== null,
  })

  async function refresh() {
    await queryClient.invalidateQueries({ queryKey: ['loans'] })
    await queryClient.invalidateQueries({ queryKey: ['reports'] })
  }

  const record = useMutation({
    mutationFn: () =>
      recordRepayment(openLoan?.id ?? 0, {
        amount: Number(amount),
        received_on: receivedOn,
        method,
        reference: reference || null,
      }),
    onSuccess: async () => {
      setError(null)
      setAmount('')
      setReference('')
      await refresh()
    },
    onError: (cause) => setError(errorMessage(cause, 'That repayment could not be recorded.')),
  })

  const reverse = useMutation({
    mutationFn: ({ id, why }: { id: number; why: string }) =>
      reverseRepayment(openLoan?.id ?? 0, id, why),
    onSuccess: async () => {
      setError(null)
      setReversing(null)
      setReason('')
      await refresh()
    },
    onError: (cause) => setError(errorMessage(cause, 'That receipt could not be reversed.')),
  })

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Repayments</h1>
        <p className="mt-1 text-sm text-slate-500">
          Loans with money out against them, what has come back, and who is behind. Receipts are
          never edited: a mistake is corrected by reversing it, so the account history stays a
          record of what happened.
        </p>
      </header>

      {error && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </p>
      )}

      {data && (
        <section className="grid gap-4 sm:grid-cols-3">
          <div className="rounded-lg border border-slate-200 bg-white p-5">
            <p className="text-sm font-medium text-slate-500">Outstanding</p>
            <p className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
              {formatMoney(data.meta.total_outstanding)}
            </p>
            <p className="mt-1 text-xs text-slate-500">across {data.data.length} loans</p>
          </div>
          <div
            className={`rounded-lg border p-5 ${
              data.meta.total_arrears > 0
                ? 'border-red-200 bg-red-50'
                : 'border-emerald-200 bg-emerald-50'
            }`}
          >
            <p className="text-sm font-medium text-slate-600">In arrears</p>
            <p className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
              {formatMoney(data.meta.total_arrears)}
            </p>
            <p className="mt-1 text-xs text-slate-600">
              {data.meta.accounts_in_arrears} {data.meta.accounts_in_arrears === 1 ? 'account' : 'accounts'} behind
            </p>
          </div>
          <div className="rounded-lg border border-slate-200 bg-white p-5">
            <p className="text-sm font-medium text-slate-500">Collected</p>
            <p className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
              {formatMoney(data.data.reduce((total, row) => total + row.account.paid, 0))}
            </p>
            <p className="mt-1 text-xs text-slate-500">received to date</p>
          </div>
        </section>
      )}

      <input
        type="search"
        value={term}
        onChange={(event) => setTerm(event.target.value)}
        placeholder="Search by loan number, client name, client number or ID"
        className="w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
      />

      {isPending ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : isError ? (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          The loan book could not be loaded.
        </p>
      ) : data.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
          No loans have been disbursed yet, so there is nothing to collect.
        </p>
      ) : (
        <ul className="space-y-3">
          {data.data.map((loan) => (
            <li key={loan.id} className="rounded-lg border border-slate-200 bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <div className="flex flex-wrap items-center gap-3">
                    <Link
                      to={`/applications/${loan.id}`}
                      className="font-mono text-sm font-medium text-brand-700 hover:text-brand-800"
                    >
                      {loan.application_number}
                    </Link>
                    {loan.account.is_settled ? (
                      <span className="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white">
                        Settled
                      </span>
                    ) : loan.account.is_in_arrears ? (
                      <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 ring-1 ring-red-200">
                        {loan.account.months_behind} instalments behind
                      </span>
                    ) : (
                      <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">
                        Up to date
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-sm font-medium text-slate-900">{loan.client_name}</p>
                  <p className="text-xs text-slate-500">
                    {formatMoney(loan.monthly_instalment)} a month over {loan.term_months} months,
                    paid out {formatDate(loan.disbursed_on)}
                  </p>
                </div>

                <div className="text-right">
                  <p className="text-2xl font-semibold tracking-tight text-slate-900">
                    {formatMoney(loan.account.balance)}
                  </p>
                  <p className="text-xs text-slate-500">
                    of {formatMoney(loan.account.total_repayable)} outstanding
                  </p>
                  {loan.account.arrears > 0 && (
                    <p className="mt-1 text-xs font-medium text-red-700">
                      {formatMoney(loan.account.arrears)} in arrears
                    </p>
                  )}

                  <button
                    type="button"
                    onClick={() => {
                      setOpenLoan(openLoan?.id === loan.id ? null : loan)
                      setError(null)
                      setAmount(String(loan.monthly_instalment))
                    }}
                    className="mt-3 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                  >
                    {openLoan?.id === loan.id ? 'Close' : 'Open account'}
                  </button>
                </div>
              </div>

              {openLoan?.id === loan.id && (
                <div className="mt-5 border-t border-slate-100 pt-5">
                  {can('repayments.record') && !loan.account.is_settled && (
                    <div className="rounded-md border border-slate-200 bg-slate-50 p-4">
                      <h3 className="text-sm font-medium text-slate-900">Record a repayment</h3>
                      <div className="mt-3 grid gap-3 sm:grid-cols-4">
                        <label className="block">
                          <span className="text-xs font-medium text-slate-600">Amount</span>
                          <input
                            type="number"
                            step="0.01"
                            value={amount}
                            onChange={(event) => setAmount(event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                          />
                        </label>
                        <label className="block">
                          <span className="text-xs font-medium text-slate-600">Received on</span>
                          <input
                            type="date"
                            max={today()}
                            value={receivedOn}
                            onChange={(event) => setReceivedOn(event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                          />
                        </label>
                        <label className="block">
                          <span className="text-xs font-medium text-slate-600">Method</span>
                          <select
                            value={method}
                            onChange={(event) => setMethod(event.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                          >
                            {data.meta.methods.map((option) => (
                              <option key={option.value} value={option.value}>
                                {option.label}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label className="block">
                          <span className="text-xs font-medium text-slate-600">Reference</span>
                          <input
                            value={reference}
                            onChange={(event) => setReference(event.target.value)}
                            placeholder="Bank or payroll reference"
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                          />
                        </label>
                      </div>
                      <button
                        type="button"
                        onClick={() => record.mutate()}
                        disabled={!amount || Number(amount) <= 0 || record.isPending}
                        className="mt-3 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
                      >
                        {record.isPending ? 'Recording...' : 'Record repayment'}
                      </button>
                    </div>
                  )}

                  <h3 className="mt-5 text-sm font-medium text-slate-900">Account history</h3>

                  {!history || history.data.length === 0 ? (
                    <p className="mt-2 text-sm text-slate-500">Nothing received yet.</p>
                  ) : (
                    <ul className="mt-2 divide-y divide-slate-100">
                      {history.data.map((entry) => (
                        <li key={entry.id} className="py-3">
                          <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                              <p
                                className={`text-sm font-medium ${
                                  entry.amount < 0 ? 'text-red-700' : 'text-slate-900'
                                }`}
                              >
                                {formatMoney(entry.amount)} · {entry.method_label}
                              </p>
                              <p className="text-xs text-slate-500">
                                {formatDate(entry.received_on)}
                                {entry.reference ? ` · ${entry.reference}` : ''} · captured by{' '}
                                {entry.recorded_by}
                              </p>
                              {entry.note && (
                                <p className="text-xs text-slate-600">{entry.note}</p>
                              )}
                            </div>

                            {can('repayments.record') && !entry.is_reversal && (
                              <button
                                type="button"
                                onClick={() => setReversing(entry.id)}
                                className="text-xs font-medium text-slate-500 hover:text-red-700"
                              >
                                Reverse
                              </button>
                            )}
                          </div>

                          {reversing === entry.id && (
                            <div className="mt-3 rounded-md border border-red-200 bg-red-50 p-3">
                              <label className="block">
                                <span className="text-xs font-medium text-red-900">
                                  Why is this being reversed?
                                </span>
                                <input
                                  value={reason}
                                  onChange={(event) => setReason(event.target.value)}
                                  placeholder="Debit order returned unpaid"
                                  className="mt-1 w-full max-w-sm rounded-md border border-red-300 bg-white px-3 py-2 text-sm outline-none focus:border-red-500"
                                />
                              </label>
                              <div className="mt-2 flex gap-2">
                                <button
                                  type="button"
                                  onClick={() => reverse.mutate({ id: entry.id, why: reason })}
                                  disabled={reason.trim().length === 0 || reverse.isPending}
                                  className="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60"
                                >
                                  Confirm reversal
                                </button>
                                <button
                                  type="button"
                                  onClick={() => setReversing(null)}
                                  className="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-900 hover:bg-red-100"
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
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
