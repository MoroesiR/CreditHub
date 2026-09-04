import { useQuery } from '@tanstack/react-query'

import { Spinner } from '@/components/Spinner'
import { fetchPortfolioReport } from '@/features/reports/api'
import { formatMoney, formatNumber } from '@/lib/format'

/** Stages worth showing money against; the rest are noise on this screen. */
const PIPELINE_ORDER = ['submitted', 'approved', 'agreement_signed', 'disbursed', 'declined']

export function ReportsPage() {
  const { data, isPending, isError } = useQuery({
    queryKey: ['reports', 'portfolio'],
    queryFn: fetchPortfolioReport,
  })

  if (isPending) {
    return (
      <div className="flex justify-center py-10">
        <Spinner />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <p className="rounded-lg border border-bad-200 bg-bad-50 p-4 text-sm text-bad-700">
        The report could not be loaded.
      </p>
    )
  }

  const pipeline = PIPELINE_ORDER.map((status) =>
    data.pipeline.find((row) => row.status === status),
  ).filter((row) => row !== undefined)

  const peak = Math.max(...data.monthly.map((month) => month.total), 1)

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
        <p className="mt-1 text-sm text-ink-500">
          What has been committed against what has actually been paid. A loan can be approved and
          never disbursed, so the two are never added together.
        </p>
      </header>

      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="rounded-lg border border-good-200 bg-good-50 p-5">
          <p className="text-sm font-medium text-good-900">Total paid out</p>
          <p className="mt-2 text-3xl font-semibold tracking-tight text-good-900">
            {formatMoney(data.cash_out.total)}
          </p>
          <p className="mt-1 text-xs text-good-800">
            {formatMoney(data.cash_out.loans_paid_total)} in loans plus{' '}
            {formatMoney(data.cash_out.commission_paid_total)} in commission
          </p>
        </div>

        <div className="rounded-lg border border-ink-200 bg-white p-5">
          <p className="text-sm font-medium text-ink-500">Loans disbursed</p>
          <p className="mt-2 text-3xl font-semibold tracking-tight text-ink-900">
            {formatNumber(data.cash_out.loans_paid_count)}
          </p>
          <p className="mt-1 text-xs text-ink-500">
            {formatMoney(data.cash_out.loans_paid_total)} released to clients
          </p>
        </div>

        <div className="rounded-lg border border-warn-200 bg-warn-50 p-5">
          <p className="text-sm font-medium text-warn-900">Committed, not yet paid</p>
          <p className="mt-2 text-3xl font-semibold tracking-tight text-warn-900">
            {formatMoney(data.cash_out.awaiting_payout_total)}
          </p>
          <p className="mt-1 text-xs text-warn-800">
            {formatNumber(data.cash_out.awaiting_payout_count)} signed and waiting in the payout
            queue
          </p>
        </div>

        <div className="rounded-lg border border-ink-200 bg-white p-5">
          <p className="text-sm font-medium text-ink-500">Commission owing</p>
          <p className="mt-2 text-3xl font-semibold tracking-tight text-ink-900">
            {formatMoney(data.commission.owing_total)}
          </p>
          <p className="mt-1 text-xs text-ink-500">
            across {formatNumber(data.commission.owing_count)} recruiters,{' '}
            {formatMoney(data.commission.paid_total)} already paid
          </p>
        </div>
      </section>

      <section className="rounded-lg border border-ink-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-500">
          The book by stage
        </h2>

        <div className="mt-4 overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-ink-200 text-xs uppercase tracking-wide text-ink-500">
              <tr>
                <th className="py-2 pr-4 font-medium">Stage</th>
                <th className="py-2 pr-4 text-right font-medium">Files</th>
                <th className="py-2 text-right font-medium">Value</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-ink-100">
              {pipeline.map((row) => (
                <tr key={row.status}>
                  <td className="py-2 pr-4 text-ink-900">{row.label}</td>
                  <td className="py-2 pr-4 text-right text-ink-700">{formatNumber(row.count)}</td>
                  <td className="py-2 text-right font-medium text-ink-900">
                    {formatMoney(row.total)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-lg border border-ink-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-500">
          Coming back in
        </h2>
        <p className="mt-1 text-sm text-ink-500">
          Arrears is the part of the outstanding balance that should already have been paid. It is
          the figure that says whether the book is healthy, rather than merely large.
        </p>

        <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt className="text-sm text-ink-500">Collected</dt>
            <dd className="mt-1 text-2xl font-semibold tracking-tight text-ink-900">
              {formatMoney(data.repayments.collected)}
            </dd>
          </div>
          <div>
            <dt className="text-sm text-ink-500">Still outstanding</dt>
            <dd className="mt-1 text-2xl font-semibold tracking-tight text-ink-900">
              {formatMoney(data.repayments.outstanding)}
            </dd>
          </div>
          <div>
            <dt className="text-sm text-ink-500">In arrears</dt>
            <dd
              className={`mt-1 text-2xl font-semibold tracking-tight ${
                data.repayments.arrears > 0 ? 'text-bad-700' : 'text-good-700'
              }`}
            >
              {formatMoney(data.repayments.arrears)}
            </dd>
            <dd className="text-xs text-ink-500">
              {formatNumber(data.repayments.accounts_in_arrears)} of{' '}
              {formatNumber(data.repayments.live_loans)} accounts behind
            </dd>
          </div>
          <div>
            <dt className="text-sm text-ink-500">Settled</dt>
            <dd className="mt-1 text-2xl font-semibold tracking-tight text-ink-900">
              {formatNumber(data.repayments.accounts_settled)}
            </dd>
            <dd className="text-xs text-ink-500">loans paid off in full</dd>
          </div>
        </dl>
      </section>

      <section className="rounded-lg border border-ink-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-500">
          Paid out by month
        </h2>

        <ul className="mt-4 space-y-3">
          {data.monthly.map((month) => (
            <li key={month.month} className="flex items-center gap-4">
              <span className="w-20 shrink-0 text-sm text-ink-600">{month.label}</span>
              <span className="h-6 flex-1 overflow-hidden rounded bg-ink-100">
                <span
                  className="block h-full rounded bg-brand-500"
                  style={{ width: `${Math.round((month.total / peak) * 100)}%` }}
                />
              </span>
              <span className="w-32 shrink-0 text-right text-sm font-medium text-ink-900">
                {formatMoney(month.total)}
              </span>
              <span className="w-16 shrink-0 text-right text-xs text-ink-500">
                {month.count} {month.count === 1 ? 'loan' : 'loans'}
              </span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
