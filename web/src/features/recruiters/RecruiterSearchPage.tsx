import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'

import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import { searchRecruiters } from '@/features/recruiters/api'

export function RecruiterSearchPage() {
  const { can } = useAuth()
  const [term, setTerm] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(term)
      setPage(1)
    }, 300)

    return () => clearTimeout(timer)
  }, [term])

  const { data, isPending, isError } = useQuery({
    queryKey: ['recruiters', { search, page }],
    queryFn: () => searchRecruiters({ search: search || undefined, page }),
    placeholderData: keepPreviousData,
  })

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Recruiters</h1>
          <p className="mt-1 text-sm text-ink-500">
            Search by name, recruiter number, ID number or phone.
          </p>
        </div>

        {can('recruiters.create') && (
          <Link
            to="/recruiters/register"
            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
          >
            Register recruiter
          </Link>
        )}
      </header>

      <input
        type="search"
        value={term}
        onChange={(event) => setTerm(event.target.value)}
        placeholder="Search recruiters…"
        className="w-full max-w-md rounded-md border border-ink-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
      />

      {isPending ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : isError ? (
        <p className="rounded-lg border border-bad-200 bg-bad-50 p-4 text-sm text-bad-700">
          Recruiters could not be loaded.
        </p>
      ) : data.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-ink-300 bg-white p-8 text-center text-sm text-ink-500">
          {search ? `No recruiter matches “${search}”.` : 'No recruiters registered yet.'}
        </p>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-ink-200 bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-ink-200 bg-ink-50 text-xs uppercase tracking-wide text-ink-500">
                <tr>
                  <th className="px-4 py-3 font-medium">Recruiter</th>
                  <th className="px-4 py-3 font-medium">ID number</th>
                  <th className="px-4 py-3 font-medium">Phone</th>
                  <th className="px-4 py-3 font-medium">Bank</th>
                  <th className="px-4 py-3 text-right font-medium">Clients introduced</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-100">
                {data.data.map((recruiter) => (
                  <tr key={recruiter.id} className="hover:bg-ink-50">
                    <td className="px-4 py-3">
                      <Link
                        to={`/recruiters/${recruiter.id}`}
                        className="font-medium text-brand-700 hover:text-brand-800"
                      >
                        {recruiter.full_name}
                      </Link>
                      <p className="font-mono text-xs text-ink-500">
                        {recruiter.recruiter_number}
                      </p>
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-ink-600">
                      {recruiter.id_number}
                    </td>
                    <td className="px-4 py-3 text-ink-600">{recruiter.phone}</td>
                    <td className="px-4 py-3 text-ink-600">
                      {recruiter.bank_name ?? <span className="text-ink-400">Not captured</span>}
                    </td>
                    <td className="px-4 py-3 text-right font-medium text-ink-900">
                      {recruiter.clients_count ?? 0}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between text-sm text-ink-500">
            <p>
              Showing {data.meta.from ?? 0}–{data.meta.to ?? 0} of {data.meta.total}
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={data.meta.current_page <= 1}
                className="rounded-md border border-ink-300 px-3 py-1.5 font-medium text-ink-700 transition-colors hover:bg-ink-100 disabled:opacity-50"
              >
                Previous
              </button>
              <button
                type="button"
                onClick={() => setPage((current) => current + 1)}
                disabled={data.meta.current_page >= data.meta.last_page}
                className="rounded-md border border-ink-300 px-3 py-1.5 font-medium text-ink-700 transition-colors hover:bg-ink-100 disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
