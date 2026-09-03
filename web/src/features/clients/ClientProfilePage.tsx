import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { DocumentModal } from '@/components/DocumentModal'
import { Spinner } from '@/components/Spinner'
import { StatusBadge } from '@/features/applications/StatusBadge'
import { fetchClientProfile } from '@/features/clients/api'
import { api } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import type { ApplicationStatus } from '@/types/applications'
import type { ProfileDocument } from '@/types/profile'

/**
 * The client's likeness comes from the photograph taken when they signed, so
 * it has to be fetched with the same authenticated request as any other stored
 * image rather than dropped into a src attribute.
 */
function ClientPhoto({ path, name }: { path: string | null; name: string }) {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    if (path === null) {
      return
    }

    let cancelled = false
    let created: string | null = null

    async function load() {
      try {
        const response = await api.get<Blob>(path as string, { responseType: 'blob' })

        if (cancelled) {
          return
        }

        created = URL.createObjectURL(response.data)
        setUrl(created)
      } catch {
        setUrl(null)
      }
    }

    void load()

    return () => {
      cancelled = true

      if (created) {
        URL.revokeObjectURL(created)
      }
    }
  }, [path])

  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')

  if (url) {
    return (
      <img
        src={url}
        alt={name}
        className="h-24 w-24 shrink-0 rounded-full object-cover ring-2 ring-slate-200"
      />
    )
  }

  return (
    <span className="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-slate-200 text-2xl font-semibold text-slate-600">
      {initials}
    </span>
  )
}

function Detail({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <dt className="text-xs uppercase tracking-wide text-slate-500">{label}</dt>
      <dd className="mt-0.5 text-sm text-slate-900">{value ? value : 'Not captured'}</dd>
    </div>
  )
}

export function ClientProfilePage() {
  const { id } = useParams<{ id: string }>()
  const clientId = Number(id)
  const [viewing, setViewing] = useState<ProfileDocument | null>(null)

  const { data, isPending, isError } = useQuery({
    queryKey: ['clients', clientId, 'profile'],
    queryFn: () => fetchClientProfile(clientId),
    enabled: Number.isFinite(clientId),
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
      <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        That client could not be found.
      </p>
    )
  }

  const { client, photo, applications, repayments, documents, totals } = data

  return (
    <div className="space-y-6">
      <Link to="/clients" className="text-sm text-brand-600 hover:text-brand-700">
        Back to clients
      </Link>

      <header className="flex flex-wrap items-start gap-6 rounded-lg border border-slate-200 bg-white p-6">
        <ClientPhoto
          path={
            photo
              ? `/applications/${photo.loan_application_id}/agreement/${photo.agreement_id}/photo`
              : null
          }
          name={client.full_name}
        />

        <div className="flex-1">
          <h1 className="text-2xl font-semibold tracking-tight">{client.full_name}</h1>
          <p className="mt-1 font-mono text-sm text-slate-500">
            {client.client_number} · {client.id_number}
          </p>
          <p className="mt-1 text-sm text-slate-600">
            {client.recruiter
              ? `Introduced by ${client.recruiter.full_name} (${client.recruiter.recruiter_number})`
              : 'Walk-in, no recruiter'}
          </p>
          <p className="mt-1 text-xs text-slate-500">
            {photo
              ? `Photograph taken at signing on ${formatDate(photo.captured_at)}`
              : 'No photograph on file. One is captured when an agreement is signed.'}
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="rounded-md border border-slate-200 p-4">
            <p className="text-xs uppercase tracking-wide text-slate-500">Borrowed to date</p>
            <p className="mt-1 text-xl font-semibold text-slate-900">
              {formatMoney(totals.borrowed)}
            </p>
          </div>
          <div
            className={`rounded-md border p-4 ${
              totals.is_in_arrears ? 'border-red-200 bg-red-50' : 'border-slate-200'
            }`}
          >
            <p className="text-xs uppercase tracking-wide text-slate-500">Outstanding</p>
            <p className="mt-1 text-xl font-semibold text-slate-900">
              {formatMoney(totals.outstanding)}
            </p>
            {totals.is_in_arrears && (
              <p className="text-xs font-medium text-red-700">
                {formatMoney(totals.arrears)} in arrears
              </p>
            )}
          </div>
        </div>
      </header>

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Details</h2>

        <dl className="mt-4 grid gap-4 sm:grid-cols-3">
          <Detail label="Date of birth" value={formatDate(client.date_of_birth)} />
          <Detail label="Gender" value={client.gender} />
          <Detail label="Phone" value={client.phone} />
          <Detail label="Email" value={client.email} />
          <Detail label="City" value={client.city} />
          <Detail label="Province" value={client.province} />
          <Detail label="Country" value={client.country} />
          <Detail label="Employer" value={client.employer_name} />
          <Detail label="Employment" value={client.employment_status} />
          <Detail label="Bank" value={client.bank_name} />
          <Detail label="Account number" value={client.bank_account_number} />
          <Detail label="Branch code" value={client.bank_branch_code} />
        </dl>

        {client.affordability && (
          <div className="mt-6 rounded-md bg-slate-50 p-4">
            <p className="text-xs uppercase tracking-wide text-slate-500">
              Affordability on file
            </p>
            <dl className="mt-2 grid gap-4 sm:grid-cols-4">
              <Detail
                label="Net income"
                value={formatMoney(client.affordability.net_monthly_income)}
              />
              <Detail
                label="Living expenses"
                value={formatMoney(client.affordability.monthly_living_expenses)}
              />
              <Detail
                label="Debt repayments"
                value={formatMoney(client.affordability.monthly_debt_repayments)}
              />
              <Detail
                label="Disposable"
                value={formatMoney(client.affordability.disposable_income)}
              />
            </dl>
          </div>
        )}
      </section>

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Loan history
        </h2>

        {applications.length === 0 ? (
          <p className="mt-4 text-sm text-slate-500">This client has never applied for a loan.</p>
        ) : (
          <div className="mt-4 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="py-2 pr-4 font-medium">Application</th>
                  <th className="py-2 pr-4 text-right font-medium">Amount</th>
                  <th className="py-2 pr-4 text-right font-medium">Instalment</th>
                  <th className="py-2 pr-4 font-medium">Status</th>
                  <th className="py-2 text-right font-medium">Balance</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {applications.map((application) => (
                  <tr key={application.id}>
                    <td className="py-2 pr-4">
                      <Link
                        to={`/applications/${application.id}`}
                        className="font-mono text-xs font-medium text-brand-700 hover:text-brand-800"
                      >
                        {application.application_number}
                      </Link>
                      <p className="text-xs text-slate-500">
                        {application.term_months} months at {application.interest_rate}%
                        {application.disbursed_on
                          ? `, paid out ${formatDate(application.disbursed_on)}`
                          : ''}
                      </p>
                      {application.decline_reason && (
                        <p className="text-xs text-red-700">{application.decline_reason}</p>
                      )}
                    </td>
                    <td className="py-2 pr-4 text-right text-slate-900">
                      {formatMoney(application.amount)}
                    </td>
                    <td className="py-2 pr-4 text-right text-slate-700">
                      {formatMoney(application.monthly_instalment)}
                    </td>
                    <td className="py-2 pr-4">
                      <StatusBadge
                        status={application.status as ApplicationStatus}
                        label={application.status_label}
                      />
                    </td>
                    <td className="py-2 text-right">
                      {application.account ? (
                        <>
                          <span className="font-medium text-slate-900">
                            {formatMoney(application.account.balance)}
                          </span>
                          {application.account.arrears > 0 && (
                            <p className="text-xs text-red-700">
                              {formatMoney(application.account.arrears)} behind
                            </p>
                          )}
                        </>
                      ) : (
                        <span className="text-slate-400">not disbursed</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Repayment history
        </h2>

        {repayments.length === 0 ? (
          <p className="mt-4 text-sm text-slate-500">Nothing has been received from this client.</p>
        ) : (
          <ul className="mt-4 divide-y divide-slate-100">
            {repayments.map((entry) => (
              <li key={entry.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                  <p
                    className={`text-sm font-medium ${
                      entry.amount < 0 ? 'text-red-700' : 'text-slate-900'
                    }`}
                  >
                    {formatMoney(entry.amount)} · {entry.method_label}
                  </p>
                  <p className="text-xs text-slate-500">
                    {formatDate(entry.received_on)} · {entry.application_number}
                    {entry.reference ? ` · ${entry.reference}` : ''} · captured by{' '}
                    {entry.recorded_by}
                  </p>
                  {entry.note && <p className="text-xs text-slate-600">{entry.note}</p>}
                </div>
                {entry.is_reversal && (
                  <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 ring-1 ring-red-200">
                    Reversal
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Documents</h2>
        <p className="mt-1 text-sm text-slate-500">
          Held in private storage. Opening one fetches it through an authenticated request rather
          than exposing a link.
        </p>

        {documents.length === 0 ? (
          <p className="mt-4 text-sm text-slate-500">No documents on file.</p>
        ) : (
          <ul className="mt-4 divide-y divide-slate-100">
            {documents.map((document) => (
              <li
                key={document.id}
                className="flex flex-wrap items-center justify-between gap-3 py-3"
              >
                <div>
                  <p className="text-sm font-medium text-slate-900">{document.type_label}</p>
                  <p className="text-xs text-slate-500">
                    {document.original_name} · {Math.round(document.size_bytes / 1024)} KB ·{' '}
                    {document.application_number} · uploaded {formatDate(document.uploaded_at)}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setViewing(document)}
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                >
                  View
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      {viewing && (
        <DocumentModal
          path={`/applications/${viewing.loan_application_id}/documents/${viewing.id}`}
          title={`${viewing.type_label} · ${client.full_name}`}
          subtitle={`${viewing.original_name} · ${viewing.application_number}`}
          onClose={() => setViewing(null)}
        />
      )}
    </div>
  )
}
