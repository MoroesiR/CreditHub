import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { submitChangeRequest } from '@/features/change-requests/api'
import { errorMessage } from '@/lib/api'

export interface EditableField {
  name: string
  label: string
  /** Current value, shown beside the box so the difference is obvious. */
  current: string | null
  options?: { value: string; label: string }[]
}

/**
 * Asks an administrator to correct a client or recruiter.
 *
 * Deliberately not an edit form. Both records decide where money goes - the
 * client is paid the loan, the recruiter the commission - so the officer who
 * spots the mistake proposes the change and somebody else agrees to it.
 */
export function RequestChangeDialog({
  subjectKind,
  subjectId,
  subjectLabel,
  fields,
  onClose,
}: {
  subjectKind: 'client' | 'recruiter'
  subjectId: number
  subjectLabel: string
  fields: EditableField[]
  onClose: () => void
}) {
  const queryClient = useQueryClient()
  const [values, setValues] = useState<Record<string, string>>({})
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [sent, setSent] = useState(false)

  // Only fields the officer actually touched, and only where the value really
  // differs from what is on the record.
  const changed = Object.fromEntries(
    Object.entries(values).filter(([name, value]) => {
      const field = fields.find((entry) => entry.name === name)

      return value.trim() !== '' && value !== (field?.current ?? '')
    }),
  )

  const mutation = useMutation({
    mutationFn: () =>
      submitChangeRequest({
        subject_kind: subjectKind,
        subject_id: subjectId,
        reason,
        changes: changed,
      }),
    onSuccess: async () => {
      setError(null)
      setSent(true)
      await queryClient.invalidateQueries({ queryKey: ['change-requests'] })
    },
    onError: (cause) => setError(errorMessage(cause, 'The request could not be sent.')),
  })

  const canSend =
    Object.keys(changed).length > 0 && reason.trim().length > 0 && !mutation.isPending

  return (
    <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4">
      <div className="mt-10 w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
          <div>
            <h2 className="text-lg font-semibold tracking-tight">Request a change</h2>
            <p className="mt-0.5 text-sm text-slate-500">{subjectLabel}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100"
          >
            Close
          </button>
        </div>

        {sent ? (
          <div className="p-6">
            <p className="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
              Sent to the administrators. Nothing on the record changes until one of them approves
              it, and you will be notified either way.
            </p>
            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={onClose}
                className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
              >
                Done
              </button>
            </div>
          </div>
        ) : (
          <div className="p-5">
            {error && (
              <p className="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {error}
              </p>
            )}

            <p className="mb-4 text-sm text-slate-500">
              Fill in only what should change. Anything left blank stays as it is.
            </p>

            <div className="grid gap-3 sm:grid-cols-2">
              {fields.map((field) => (
                <label key={field.name} className="block">
                  <span className="text-sm font-medium text-slate-700">{field.label}</span>
                  <span className="mt-0.5 block text-xs text-slate-400">
                    Now: {field.current || 'not captured'}
                  </span>
                  {field.options ? (
                    <select
                      value={values[field.name] ?? ''}
                      onChange={(event) =>
                        setValues((current) => ({ ...current, [field.name]: event.target.value }))
                      }
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                    >
                      <option value="">Leave unchanged</option>
                      {field.options.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <input
                      value={values[field.name] ?? ''}
                      onChange={(event) =>
                        setValues((current) => ({ ...current, [field.name]: event.target.value }))
                      }
                      placeholder="Leave blank to keep"
                      className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                    />
                  )}
                </label>
              ))}
            </div>

            <label className="mt-5 block">
              <span className="text-sm font-medium text-slate-700">Why is this needed?</span>
              <input
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="e.g. Client phoned to say they have changed banks"
                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </label>

            <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
              {!canSend && (
                <p className="mr-auto text-sm text-slate-500">
                  {Object.keys(changed).length === 0
                    ? 'Change at least one field.'
                    : 'Say why the change is needed.'}
                </p>
              )}
              <button
                type="button"
                onClick={onClose}
                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => mutation.mutate()}
                disabled={!canSend}
                className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
              >
                {mutation.isPending ? 'Sending...' : 'Send to administrator'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
