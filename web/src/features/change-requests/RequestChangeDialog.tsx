import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { DocumentModal } from '@/components/DocumentModal'
import { useState } from 'react'

import { submitChangeRequest } from '@/features/change-requests/api'
import { EVIDENCE_FOR, EVIDENCE_LABELS } from '@/types/changeRequests'
import type { ChangeRequestDocument } from '@/types/changeRequests'
import { fetchBanks, fetchLocations } from '@/features/clients/api'
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

  // The same lists registration uses, served by the API so a name chosen here
  // is one the validator will accept.
  const { data: banks } = useQuery({
    queryKey: ['reference', 'banks'],
    queryFn: fetchBanks,
    staleTime: Infinity,
  })

  const { data: locations } = useQuery({
    queryKey: ['reference', 'locations'],
    queryFn: fetchLocations,
    staleTime: Infinity,
  })

  const [values, setValues] = useState<Record<string, string>>({})
  const [reason, setReason] = useState('')
  const [documents, setDocuments] = useState<Record<string, File>>({})
  // The proof is the whole basis for the decision, so it can be checked here
  // rather than after an administrator has already been sent the wrong page.
  const [previewing, setPreviewing] = useState<keyof typeof EVIDENCE_LABELS | null>(null)
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

  const optionsFor = (field: EditableField): EditableField['options'] => {
    if (field.options) {
      return field.options
    }

    if (field.name === 'bank_name') {
      return banks?.map((bank) => ({ value: bank.name, label: bank.name }))
    }

    if (field.name === 'province') {
      return locations?.provinces.map((province) => ({ value: province, label: province }))
    }

    return undefined
  }

  // Shown under the bank so the officer can see what the branch code will
  // become. The server derives it again on approval; this is only a preview.
  const chosenBank = values.bank_name
  const branchCode = banks?.find((bank) => bank.name === chosenBank)?.branch_code ?? null

  // Which proofs this particular set of changes calls for. A surname needs an
  // ID copy; a bank account needs a statement. Worked out as the officer types
  // rather than sprung on them at submission.
  const requiredEvidence = [
    ...new Set(
      changed
        .map((field) => EVIDENCE_FOR[field.name])
        .filter((type): type is ChangeRequestDocument['type'] => type !== undefined),
    ),
  ]

  const missingEvidence = requiredEvidence.filter((type) => !documents[type])

  const groups = [...new Set(fields.map((field) => field.group))]

  const mutation = useMutation({
    mutationFn: () =>
      submitChangeRequest({
        subject_kind: subjectKind,
        subject_id: subjectId,
        reason,
        changes: payload,
        documents,
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
    <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-ink-900/40 p-4">
      <div className="my-10 w-full max-w-2xl rounded-lg border border-ink-200 bg-white shadow-xl">
        <div className="flex items-start justify-between gap-4 border-b border-ink-200 p-5">
          <div>
            <h2 className="text-lg font-semibold tracking-tight">
              {sent ? 'Request sent' : reviewing ? 'Check before sending' : 'Request a change'}
            </h2>
            <p className="mt-0.5 text-sm text-ink-500">{subjectLabel}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md px-2 py-1 text-sm text-ink-500 hover:bg-ink-100"
          >
            Close
          </button>
        </div>

        {sent ? (
          <div className="p-6">
            <p className="rounded-md border border-good-200 bg-good-50 p-4 text-sm text-good-800">
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
              <p className="mb-4 rounded-md border border-bad-200 bg-bad-50 p-3 text-sm text-bad-700">
                {error}
              </p>
            )}

            <p className="text-sm text-ink-600">
              An administrator will be asked to approve exactly this:
            </p>

            <dl className="mt-4 divide-y divide-ink-100 rounded-md border border-ink-200">
              {changed.map((field) => (
                <div key={field.name} className="flex flex-wrap gap-2 p-3 text-sm">
                  <dt className="w-40 font-medium text-ink-700">{field.label}</dt>
                  <dd className="text-ink-500">
                    <span className="line-through">{field.current || 'not captured'}</span>
                    <span className="mx-2">to</span>
                    <span className="font-medium text-ink-900">{values[field.name]}</span>
                  </dd>
                </div>
              ))}
            </dl>

            {chosenBank && branchCode && (
              <p className="mt-2 text-xs text-ink-500">
                The branch code follows from the bank and becomes {branchCode}. It is set by the
                system, not typed.
              </p>
            )}

            {requiredEvidence.length > 0 && (
              <div className="mt-4 rounded-md border border-ink-200 p-3">
                <p className="text-sm font-medium text-ink-700">Attached</p>
                <ul className="mt-1 space-y-0.5">
                  {requiredEvidence.map((type) => (
                    <li key={type} className="text-sm text-ink-600">
                      {EVIDENCE_LABELS[type]}: {documents[type]?.name}
                    </li>
                  ))}
                </ul>
                <p className="mt-2 text-xs text-ink-500">
                  On approval these replace the same documents on the client's open loan files.
                  Files already paid out keep the documents they were decided on.
                </p>
              </div>
            )}

            <p className="mt-4 rounded-md bg-ink-50 p-3 text-sm text-ink-600">
              <span className="font-medium text-ink-700">Reason given: </span>
              {reason}
            </p>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                onClick={() => setReviewing(false)}
                className="rounded-md border border-ink-300 px-4 py-2 text-sm font-medium text-ink-700 hover:bg-ink-100"
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
              <p className="mb-4 rounded-md border border-bad-200 bg-bad-50 p-3 text-sm text-bad-700">
                {error}
              </p>
            )}

            <p className="mb-5 text-sm text-ink-500">
              Fill in only what should change. Anything left blank stays as it is.
            </p>

            <div className="space-y-5">
              {groups.map((group) => (
                <fieldset key={group}>
                  <legend className="text-xs font-semibold uppercase tracking-wide text-ink-500">
                    {group}
                  </legend>
                  <div className="mt-2 grid gap-3 sm:grid-cols-2">
                    {fields
                      .filter((field) => field.group === group)
                      .map((field) => (
                        <label key={field.name} className="block">
                          <span className="text-sm font-medium text-ink-700">{field.label}</span>
                          <span className="mt-0.5 block text-xs text-ink-400">
                            Now: {field.current || 'not captured'}
                          </span>
                          {optionsFor(field) ? (
                            <select
                              value={values[field.name] ?? ''}
                              onChange={(event) =>
                                setValues((current) => ({
                                  ...current,
                                  [field.name]: event.target.value,
                                }))
                              }
                              className="mt-1 w-full rounded-md border border-ink-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                            >
                              <option value="">Leave unchanged</option>
                              {optionsFor(field)?.map((option) => (
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
                              className="mt-1 w-full rounded-md border border-ink-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
                            />
                          )}

                          {field.name === 'bank_name' && chosenBank && (
                            <span className="mt-1 block text-xs text-ink-500">
                              Branch code becomes {branchCode ?? 'unknown'}
                            </span>
                          )}
                        </label>
                      ))}
                  </div>
                </fieldset>
              ))}
            </div>

            {requiredEvidence.length > 0 && (
              <div className="mt-6 rounded-md border border-warn-200 bg-warn-50 p-4">
                <p className="text-sm font-medium text-warn-900">Proof required</p>
                <p className="mt-0.5 text-xs text-warn-800">
                  An administrator decides on the document, not on the request. Approving replaces
                  the same document on this client's open loan files.
                </p>

                <div className="mt-3 space-y-2">
                  {requiredEvidence.map((type) => (
                    <div
                      key={type}
                      className="flex flex-wrap items-center justify-between gap-3 rounded-md bg-white p-3"
                    >
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-ink-900">
                          {EVIDENCE_LABELS[type]}
                        </p>
                        <p className="truncate text-xs text-ink-500">
                          {documents[type]
                            ? `${documents[type].name} · ${Math.round(documents[type].size / 1024)} KB`
                            : 'PDF or image, up to 5 MB'}
                        </p>
                      </div>

                      <div className="flex items-center gap-2">
                        {documents[type] && (
                          <button
                            type="button"
                            onClick={() => setPreviewing(type)}
                            className="whitespace-nowrap rounded-md border border-ink-300 px-3 py-1.5 text-sm font-medium text-ink-700 hover:bg-ink-100"
                          >
                            View
                          </button>
                        )}

                        <label className="cursor-pointer whitespace-nowrap rounded-md border border-ink-300 px-3 py-1.5 text-sm font-medium text-ink-700 hover:bg-ink-100">
                        {documents[type] ? 'Replace' : 'Choose file'}
                        <input
                          type="file"
                          accept=".pdf,.jpg,.jpeg,.png"
                          hidden
                          onChange={(event) => {
                            const file = event.target.files?.[0]

                            setDocuments((current) => {
                              const next = { ...current }

                              if (file) {
                                next[type] = file
                              } else {
                                delete next[type]
                              }

                              return next
                            })
                          }}
                        />
                        </label>
                      </div>
                    </div>
                  ))}
                </div>

                {previewing && documents[previewing] && (
                  <DocumentModal
                    file={documents[previewing]}
                    title={EVIDENCE_LABELS[previewing]}
                    subtitle={documents[previewing].name}
                    onClose={() => setPreviewing(null)}
                  />
                )}
              </div>
            )}

            <label className="mt-6 block">
              <span className="text-sm font-medium text-ink-700">Why is this needed?</span>
              <span className="mt-0.5 block text-xs text-ink-400">
                The administrator sees this when deciding.
              </span>
              <input
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="e.g. Client phoned to say they have changed banks"
                className="mt-1 w-full rounded-md border border-ink-300 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </label>

            <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
              <p className="mr-auto text-sm text-ink-500">
                {changed.length === 0
                  ? 'Change at least one field.'
                  : missingEvidence.length > 0
                    ? `Attach the ${missingEvidence
                        .map((type) => EVIDENCE_LABELS[type].toLowerCase())
                        .join(' and ')}.`
                    : reason.trim().length === 0
                      ? 'Say why the change is needed.'
                      : `${changed.length} field${changed.length === 1 ? '' : 's'} will be sent.`}
              </p>
              <button
                type="button"
                onClick={onClose}
                className="rounded-md border border-ink-300 px-4 py-2 text-sm font-medium text-ink-700 hover:bg-ink-100"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => setReviewing(true)}
                disabled={
                  changed.length === 0 ||
                  reason.trim().length === 0 ||
                  missingEvidence.length > 0
                }
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
