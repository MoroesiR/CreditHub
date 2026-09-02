import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import { fetchCommissions, payCommission } from '@/features/commissions/api'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'

const FILTERS = [
  { value: 'pending', label: 'Owing' },
  { value: 'paid', label: 'Paid' },
  { value: '', label: 'All' },
]

export function CommissionsPage() {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  // Set when the payouts desk arrives straight from paying a loan, so the
  // commission that payment just earned is the one they land on.
  const highlighted = Number(searchParams.get('highlight')) || null

  const [status, setStatus] = useState('pending')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)

  const { data, isPending, isError } = useQuery({
    queryKey: ['commissions', { status, page }],
    queryFn: () => fetchCommissions({ status, page }),
    placeholderData: keepPreviousData,
  })

  const pay = useMutation({
    mutationFn: payCommission,
    onSuccess: async () => {
      setError(null)
      await queryClient.invalidateQueries({ queryKey: ['commissions'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (cause) => setError(errorMessage(cause, 'That commission could not be paid.')),
  })

  const highlightedRow = data?.data.find((row) => row.id === highlighted) ?? null
  const owing = data?.data.filter((row) => row.status === 'pending') ?? []
  const owingTotal = owing.reduce((total, row) => total + row.amount, 0)

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Commissions</h1>
        <p className="mt-1 text-sm text-slate-500">
          Earned when a loan is paid out, not when it is approved - an approved loan that is never
          disbursed earns nobody anything.
        </p>
      </header>

      {error && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </p>
      )}

      {highlightedRow && highlightedRow.status === 'pending' && (
        <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          The loan was paid. {highlightedRow.recruiter?.full_name} is now owed{' '}
          <span className="font-semibold">{formatMoney(highlightedRow.amount)}</span> on{' '}
          {highlightedRow.application?.application_number}, highlighted below.
        </p>
      )}

      {highlightedRow && highlightedRow.status === 'paid' && (
        <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
          {formatMoney(highlightedRow.amount)} paid to {highlightedRow.recruiter?.full_name}. The
          loan and its commission are both settled.
        </p>
      )}

      <div className="flex flex-wrap items-center justify-between gap-4">
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

        {status === 'pending' && owing.length > 0 && (
          <p className="text-sm text-slate-600">
            <span className="font-semibold text-slate-900">{formatMoney(owingTotal)}</span> owing on
            this page
          </p>
        )}
      </div>

      {isPending ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : isError ? (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          Commissions could not be loaded.
        </p>
      ) : data.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
          Nothing in this view.
        </p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Recruiter</th>
                <th className="px-4 py-3 font-medium">Loan</th>
                <th className="px-4 py-3 text-right font-medium">Loan amount</th>
                <th className="px-4 py-3 text-right font-medium">Rate</th>
                <th className="px-4 py-3 text-right font-medium">Commission</th>
                <th className="px-4 py-3 font-medium">Status</th>
                {can('commissions.pay') && <th className="px-4 py-3" />}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.data.map((row) => (
                <tr
                  key={row.id}
                  className={
                    row.id === highlighted
                      ? 'bg-amber-50 ring-2 ring-inset ring-amber-300'
                      : 'hover:bg-slate-50'
                  }
                >
                  <td className="px-4 py-3">
                    {row.recruiter ? (
                      <>
                        <Link
                          to={`/recruiters/${row.recruiter.id}`}
                          className="font-medium text-brand-700 hover:text-brand-800"
                        >
                          {row.recruiter.full_name}
                        </Link>
                        <p className="font-mono text-xs text-slate-500">
                          {row.recruiter.recruiter_number}
                        </p>
                        {!row.recruiter.bank_account_number && (
                          <p className="text-xs text-amber-700">No bank account on file</p>
                        )}
                      </>
                    ) : (
                      '-'
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <Link
                      to={`/applications/${row.application?.id}`}
                      className="font-mono text-xs text-brand-700 hover:text-brand-800"
                    >
                      {row.application?.application_number}
                    </Link>
                    <p className="text-xs text-slate-500">
                      {row.application?.client?.full_name}
                    </p>
                  </td>
                  <td className="px-4 py-3 text-right text-slate-700">
                    {formatMoney(row.loan_amount)}
                  </td>
                  <td className="px-4 py-3 text-right text-slate-700">{row.rate_applied}%</td>
                  <td className="px-4 py-3 text-right font-semibold text-slate-900">
                    {formatMoney(row.amount)}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                        row.status === 'paid'
                          ? 'bg-emerald-600 text-white'
                          : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200'
                      }`}
                    >
                      {row.status_label}
                    </span>
                    {row.paid_at && (
                      <p className="mt-1 text-xs text-slate-500">{formatDate(row.paid_at)}</p>
                    )}
                  </td>
                  {can('commissions.pay') && (
                    <td className="px-4 py-3 text-right">
                      {row.status === 'pending' && (
                        <button
                          type="button"
                          onClick={() => pay.mutate(row.id)}
                          disabled={pay.isPending}
                          className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                          Pay
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
