import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { submitChangeRequest } from '@/features/change-requests/api'
import { errorMessage } from '@/lib/api'

export interface EditableField {
  name: string
  label: string
  /** Which part of the record this belongs to, used to group the form. */
  group: string
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
 *
 * Laid out in two steps. Nobody edits eleven fields at once, so the form is
 * grouped the way the record is, and the second step restates only what will
 * actually be sent: the request goes to another person, and the officer should
 * see exactly what that person will be asked to approve.
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
  const [reviewing, setReviewing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [sent, setSent] = useState(false)

  // Only fields the officer touched, and only where the value really differs
  // from what is on the record.
  const changed = fields.filter((field) => {
    const next = values[field.name]

    return next !== undefined && next.trim() !== '' && next !== (field.current ?? '')
  })

  const payload: Record<string, string> = Object.fromEntries(
    changed.map((field) => [field.name, values[field.name] ?? '']),
  )

  const groups = [...new Set(fields.map((field) => field.group))]

  const mutation = useMutation({
    mutationFn: () =>
      submitChangeRequest({
        subject_kind: subjectKind,
        subject_id: subjectId,
        reason,
        changes: payload,
      }),
    onSuccess: async () => {
      setError(null)
      setSent(true)
      await queryClient.invalidateQueries({ queryKey: ['change-requests'] })
    },
    onError: (cause) => {
      setReviewing(false)
      setError(errorMessage(cause, 'The request could not be sent.'))
    },
  })

  return (
    <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4">
      <div className="my-10 w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
          <div>
            <h2 className="text-lg font-semibold tracking-tight">
              {sent ? 'Request sent' : reviewing ? 'Check before sending' : 'Request a change'}
            </h2>
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
        ) : reviewing ? (
          <div className="p-5">
            {error && (
              <p className="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {error}
              </p>
            )}

            <p className="text-sm text-slate-600">
              An administrator will be asked to approve exactly this:
            </p>

            <dl className="mt-4 divide-y divide-slate-100 rounded-md border border-slate-200">
              {changed.map((field) => (
                <div key={field.name} className="flex flex-wrap gap-2 p-3 text-sm">
                  <dt className="w-40 font-medium text-slate-700">{field.label}</dt>
                  <dd className="text-slate-500">
                    <span className="line-through">{field.current || 'not captured'}</span>
                    <span className="mx-2">to</span>
                    <span className="font-medium text-slate-900">{values[field.name]}</span>
                  </dd>
                </div>
              ))}
            </dl>

            <p className="mt-4 rounded-md bg-slate-50 p-3 text-sm text-slate-600">
              <span className="font-medium text-slate-700">Reason given: </span>
              {reason}
            </p>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                onClick={() => setReviewing(false)}
                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
              >
                Back
              </button>
              <button
                type="button"
                onClick={() => mutation.mutate()}
                disabled={mutation.isPending}
                className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
              >
                {mutation.isPending ? 'Sending...' : 'Send to administrator'}
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

            <p className="mb-5 text-sm text-slate-500">
              Fill in only what should change. Anything left blank stays as it is.
            </p>

            <div className="space-y-5">
              {groups.map((group) => (
                <fieldset key={group}>
                  <legend className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {group}
                  </legend>
                  <div className="mt-2 grid gap-3 sm:grid-cols-2">
                    {fields
                      .filter((field) => field.group === group)
                      .map((field) => (
                        <label key={field.name} className="block">
                          <span className="text-sm font-medium text-slate-700">{field.label}</span>
                          <span className="mt-0.5 block text-xs text-slate-400">
                            Now: {field.current || 'not captured'}
                          </span>
                          {field.options ? (
                            <select
                              value={values[field.name] ?? ''}
                              onChange={(event) =>
                                setValues((current) => ({
                                  ...current,
                                  [field.name]: event.target.value,
                                }))
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
                                setValues((current) => ({
                                  ...current,
                                  [field.name]: event.target.value,
                                }))
                              }
                              placeholder="Leave blank to keep"
                              className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                            />
                          )}
                        </label>
                      ))}
                  </div>
                </fieldset>
              ))}
            </div>

            <label className="mt-6 block">
              <span className="text-sm font-medium text-slate-700">Why is this needed?</span>
              <span className="mt-0.5 block text-xs text-slate-400">
                The administrator sees this when deciding.
              </span>
              <input
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="e.g. Client phoned to say they have changed banks"
                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </label>

            <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
              <p className="mr-auto text-sm text-slate-500">
                {changed.length === 0
                  ? 'Change at least one field.'
                  : reason.trim().length === 0
                    ? 'Say why the change is needed.'
                    : `${changed.length} field${changed.length === 1 ? '' : 's'} will be sent.`}
              </p>
              <button
                type="button"
                onClick={onClose}
                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => setReviewing(true)}
                disabled={changed.length === 0 || reason.trim().length === 0}
                className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60"
              >
                Review
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
