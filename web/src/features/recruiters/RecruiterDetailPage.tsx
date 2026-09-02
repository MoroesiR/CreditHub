import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import {
  fetchRecruiter,
  fetchRecruiterAuditTrail,
  fetchRecruiterClients,
  fetchUnlinkedClients,
  linkClientToRecruiter,
} from '@/features/recruiters/api'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'

export function RecruiterDetailPage() {
  const { id } = useParams<{ id: string }>()
  const recruiterId = Number(id)
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [linkError, setLinkError] = useState<string | null>(null)
  const [justLinked, setJustLinked] = useState<string | null>(null)

  const { data: recruiter, isPending } = useQuery({
    queryKey: ['recruiters', recruiterId],
    queryFn: () => fetchRecruiter(recruiterId),
    enabled: Number.isFinite(recruiterId),
  })

  const { data: clients } = useQuery({
    queryKey: ['recruiters', recruiterId, 'clients'],
    queryFn: () => fetchRecruiterClients(recruiterId),
    enabled: Number.isFinite(recruiterId),
  })

  const { data: unlinked } = useQuery({
    queryKey: ['clients', 'unlinked'],
    queryFn: fetchUnlinkedClients,
    enabled: can('clients.update'),
  })

  const { data: trail } = useQuery({
    queryKey: ['recruiters', recruiterId, 'audit-trail'],
    queryFn: () => fetchRecruiterAuditTrail(recruiterId),
    enabled: Number.isFinite(recruiterId),
  })

  const link = useMutation({
    mutationFn: (clientId: number) => linkClientToRecruiter(recruiterId, clientId),
    onSuccess: async (client) => {
      setLinkError(null)
      setJustLinked(`${client.full_name} (${client.client_number}) is now credited to this recruiter.`)
      await queryClient.invalidateQueries({ queryKey: ['clients'] })
      await queryClient.invalidateQueries({ queryKey: ['recruiters'] })
    },
    onError: (error) => {
      setJustLinked(null)
      setLinkError(errorMessage(error, 'That client could not be linked.'))
    },
  })

  if (isPending) {
    return (
      <div className="flex justify-center py-10">
        <Spinner />
      </div>
    )
  }

  if (!recruiter) {
    return (
      <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        That recruiter could not be found.
      </p>
    )
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <Link to="/recruiters" className="text-sm text-brand-600 hover:text-brand-700">
            ← All recruiters
          </Link>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight">{recruiter.full_name}</h1>
          <p className="mt-1 font-mono text-sm text-slate-500">{recruiter.recruiter_number}</p>
        </div>
        <span
          className={`rounded-full px-3 py-1 text-xs font-medium ${
            recruiter.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
          }`}
        >
          {recruiter.is_active ? 'Active' : 'Inactive'}
        </span>
      </header>

      <section className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-lg border border-slate-200 bg-white p-5">
          <p className="text-sm font-medium text-slate-500">Clients introduced</p>
          <p className="mt-2 text-3xl font-semibold tracking-tight">
            {recruiter.clients_count ?? 0}
          </p>
        </div>
        <div className="rounded-lg border border-slate-200 bg-white p-5">
          <p className="text-sm font-medium text-slate-500">Contact</p>
          <p className="mt-2 text-sm text-slate-900">{recruiter.phone}</p>
          <p className="text-sm text-slate-500">{recruiter.email ?? 'No email'}</p>
        </div>
        <div className="rounded-lg border border-slate-200 bg-white p-5">
          <p className="text-sm font-medium text-slate-500">Commission paid into</p>
          {recruiter.bank_name ? (
            <>
              <p className="mt-2 text-sm text-slate-900">{recruiter.bank_name}</p>
              <p className="font-mono text-xs text-slate-500">
                {recruiter.bank_account_number} · branch {recruiter.bank_branch_code}
              </p>
            </>
          ) : (
            <p className="mt-2 text-sm text-amber-700">
              No account captured - commission cannot be paid.
            </p>
          )}
        </div>
      </section>

      {can('clients.update') && (
        <section className="rounded-lg border border-slate-200 bg-white p-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            Credit an earlier introduction
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            Clients captured as walk-ins. If this recruiter introduced one of them before they were
            registered here, attach them now - otherwise no commission is earned on that loan.
          </p>

          {justLinked && (
            <p className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
              {justLinked}
            </p>
          )}

          {linkError && (
            <p className="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {linkError}
            </p>
          )}

          {!unlinked || unlinked.length === 0 ? (
            <p className="mt-4 rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
              Every client on file is already attached to a recruiter.
            </p>
          ) : (
            <ul className="mt-4 divide-y divide-slate-100">
              {unlinked.map((client) => (
                <li key={client.id} className="flex items-center justify-between gap-4 py-3">
                  <div>
                    <p className="text-sm font-medium text-slate-900">{client.full_name}</p>
                    <p className="font-mono text-xs text-slate-500">
                      {client.client_number} · registered {formatDate(client.created_at)}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => link.mutate(client.id)}
                    disabled={link.isPending}
                    className="rounded-md border border-brand-300 px-3 py-1.5 text-sm font-medium text-brand-700 transition-colors hover:bg-brand-50 disabled:opacity-60"
                  >
                    Attach to {recruiter.first_name}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Clients introduced
        </h2>

        {!clients || clients.length === 0 ? (
          <p className="mt-4 rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
            No introductions credited to this recruiter yet.
          </p>
        ) : (
          <div className="mt-4 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="py-2 pr-4 font-medium">Client</th>
                  <th className="py-2 pr-4 font-medium">Registered</th>
                  <th className="py-2 text-right font-medium">Disposable income</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {clients.map((client) => (
                  <tr key={client.id}>
                    <td className="py-2 pr-4">
                      <p className="font-medium text-slate-900">{client.full_name}</p>
                      <p className="font-mono text-xs text-slate-500">{client.client_number}</p>
                    </td>
                    <td className="py-2 pr-4 text-slate-600">{formatDate(client.created_at)}</td>
                    <td className="py-2 text-right text-slate-900">
                      {client.affordability
                        ? formatMoney(client.affordability.disposable_income)
                        : '-'}
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
          Audit trail
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Every change to this recruiter, in order. Entries are never edited or removed.
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
