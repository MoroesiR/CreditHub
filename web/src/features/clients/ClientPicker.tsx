import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'

import { inputClass } from '@/components/Field'
import { searchClients } from '@/features/clients/api'
import { formatMoney } from '@/lib/format'
import type { Client } from '@/types/clients'

/**
 * A dropdown of clients that narrows as you type.
 *
 * It opens on focus with the most recently registered clients already listed,
 * so the common case - a client captured minutes ago - needs no typing at all.
 */
export function ClientPicker({
  value,
  onChange,
}: {
  value: Client | null
  onChange: (client: Client | null) => void
}) {
  const [isOpen, setIsOpen] = useState(false)
  const [term, setTerm] = useState('')
  const [search, setSearch] = useState('')
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const timer = setTimeout(() => setSearch(term), 250)

    return () => clearTimeout(timer)
  }, [term])

  useEffect(() => {
    if (!isOpen) {
      return
    }

    function handlePointerDown(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('mousedown', handlePointerDown)
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [isOpen])

  // With no search term this is the latest registrations, which is what makes
  // the list useful before anything has been typed.
  const { data, isFetching } = useQuery({
    queryKey: ['clients', { search, page: 1 }],
    queryFn: () => searchClients({ search: search || undefined, per_page: 8 }),
    placeholderData: keepPreviousData,
  })

  if (value) {
    return (
      <div className="flex flex-wrap items-center justify-between gap-4 rounded-md border border-slate-200 bg-slate-50 p-4">
        <div>
          <p className="font-medium text-slate-900">{value.full_name}</p>
          <p className="font-mono text-xs text-slate-500">
            {value.client_number} · {value.id_number}
          </p>
          <p className="mt-1 text-sm text-slate-600">
            {value.recruiter
              ? `Introduced by ${value.recruiter.full_name} (${value.recruiter.recruiter_number})`
              : 'Walk-in - no recruiter, so no commission on this loan.'}
          </p>
        </div>
        <button
          type="button"
          onClick={() => {
            onChange(null)
            setTerm('')
          }}
          className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
          Change
        </button>
      </div>
    )
  }

  return (
    <div ref={containerRef} className="relative">
      <input
        type="text"
        role="combobox"
        aria-expanded={isOpen}
        aria-controls="client-options"
        value={term}
        onFocus={() => setIsOpen(true)}
        onChange={(event) => {
          setTerm(event.target.value)
          setIsOpen(true)
        }}
        placeholder="Select a client - or type to narrow the list"
        className={inputClass}
      />

      {isOpen && (
        <div
          id="client-options"
          role="listbox"
          className="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
        >
          {!search && (
            <p className="border-b border-slate-100 px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-400">
              Recently registered
            </p>
          )}

          {isFetching && !data ? (
            <p className="px-4 py-3 text-sm text-slate-500">Loading…</p>
          ) : !data || data.data.length === 0 ? (
            <p className="px-4 py-3 text-sm text-slate-500">
              {search ? `No client matches “${search}”.` : 'No clients registered yet.'}
            </p>
          ) : (
            <ul className="max-h-72 overflow-y-auto">
              {data.data.map((client) => (
                <li key={client.id}>
                  <button
                    type="button"
                    role="option"
                    aria-selected={false}
                    onClick={() => {
                      onChange(client)
                      setIsOpen(false)
                    }}
                    className="flex w-full items-center justify-between gap-4 px-4 py-2.5 text-left hover:bg-slate-50"
                  >
                    <span>
                      <span className="block text-sm font-medium text-slate-900">
                        {client.full_name}
                      </span>
                      <span className="block font-mono text-xs text-slate-500">
                        {client.client_number} · {client.id_number}
                      </span>
                    </span>
                    <span className="whitespace-nowrap text-right text-sm">
                      {client.affordability ? (
                        <>
                          <span className="block font-medium text-slate-900">
                            {formatMoney(client.affordability.disposable_income)}
                          </span>
                          <span className="block text-xs text-slate-500">free monthly</span>
                        </>
                      ) : (
                        <span className="text-xs text-amber-700">No assessment</span>
                      )}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
