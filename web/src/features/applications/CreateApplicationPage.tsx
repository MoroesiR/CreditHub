import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { Field, Section, inputClass } from '@/components/Field'
import { DocumentUpload } from '@/features/applications/DocumentUpload'
import { createApplication, quoteLoan } from '@/features/applications/api'
import { ClientPicker } from '@/features/clients/ClientPicker'
import { errorMessage } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import type { DocumentType } from '@/types/applications'
import type { Client } from '@/types/clients'

const MIN_AMOUNT = 500
const MAX_AMOUNT = 250000
const TERMS = [3, 6, 12, 18, 24, 36, 48, 60, 72]

const REQUIRED_DOCUMENTS: { type: DocumentType; label: string; hint: string }[] = [
  {
    type: 'id_copy',
    label: 'ID copy',
    hint: 'Proves who is borrowing. PDF or image, up to 5 MB.',
  },
  {
    type: 'bank_statement',
    label: 'Bank statement',
    hint: 'Three months. The one document the client cannot easily author.',
  },
  {
    type: 'payslip',
    label: 'Payslip',
    hint: 'Evidences the income the affordability was calculated from.',
  },
]

export function CreateApplicationPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [client, setClient] = useState<Client | null>(null)
  const [amount, setAmount] = useState(5000)
  const [months, setMonths] = useState(12)
  const [purpose, setPurpose] = useState('')
  const [documents, setDocuments] = useState<Partial<Record<DocumentType, File>>>({})
  const [submitError, setSubmitError] = useState<string | null>(null)

  // Priced by the API, not in the browser: the instalment shown to the client
  // must be the one the lender will actually book.
  const { data: quote } = useQuery({
    queryKey: ['quote', amount, months],
    queryFn: () => quoteLoan(amount, months),
    enabled: amount >= MIN_AMOUNT && amount <= MAX_AMOUNT && months > 0,
    placeholderData: keepPreviousData,
  })

  const disposable = client?.affordability?.disposable_income ?? null
  const instalment = quote?.monthly_instalment ?? null
  const affordable = disposable !== null && instalment !== null ? instalment <= disposable : null

  const missingDocuments = REQUIRED_DOCUMENTS.filter((doc) => !documents[doc.type])

  const mutation = useMutation({
    mutationFn: createApplication,
    onSuccess: async (application) => {
      await queryClient.invalidateQueries({ queryKey: ['applications'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      void navigate(`/applications/${application.id}`, { replace: true })
    },
    onError: (error) =>
      setSubmitError(errorMessage(error, 'The application could not be submitted.')),
  })

  function handleDocumentChange(type: DocumentType, file: File | null) {
    setDocuments((current) => {
      const next = { ...current }

      if (file) {
        next[type] = file
      } else {
        delete next[type]
      }

      return next
    })
  }

  function handleSubmit() {
    if (!client) {
      return
    }

    setSubmitError(null)
    mutation.mutate({
      client_id: client.id,
      amount,
      term_months: months,
      purpose: purpose || null,
      documents,
    })
  }

  const canSubmit =
    client !== null && affordable === true && missingDocuments.length === 0 && !mutation.isPending

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Create loan application</h1>
        <p className="mt-1 text-sm text-slate-500">
          The application number is issued on submission and the file goes to a credit manager for
          a decision.
        </p>
      </header>

      {submitError && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {submitError}
        </p>
      )}

      <Section title="Client">
        <div className="sm:col-span-2">
          <ClientPicker value={client} onChange={setClient} />
        </div>
      </Section>

      <Section title="Loan">
        <Field label="Amount" hint={`${formatMoney(MIN_AMOUNT)} to ${formatMoney(MAX_AMOUNT)}`}>
          <input
            type="number"
            step="100"
            min={MIN_AMOUNT}
            max={MAX_AMOUNT}
            value={amount}
            onChange={(event) => setAmount(Number(event.target.value))}
            className={inputClass}
          />
        </Field>

        <Field label="Term">
          <select
            value={months}
            onChange={(event) => setMonths(Number(event.target.value))}
            className={inputClass}
          >
            {TERMS.map((option) => (
              <option key={option} value={option}>
                {option} months
              </option>
            ))}
          </select>
        </Field>

        <Field label="Purpose" hint="Optional">
          <input
            value={purpose}
            onChange={(event) => setPurpose(event.target.value)}
            className={inputClass}
          />
        </Field>

        <Field label="Interest rate" hint="Set by the lender, not per application.">
          <input
            value={quote ? `${quote.interest_rate}% a year` : ''}
            readOnly
            placeholder="-"
            className={`${inputClass} bg-slate-50 text-slate-600`}
          />
        </Field>
      </Section>

      <section className="rounded-lg border border-slate-200 bg-white p-6">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
          Supporting documents
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          All three are required. The file cannot be submitted without them, and it cannot be
          defended later without them either.
        </p>

        <div className="mt-4 space-y-3">
          {REQUIRED_DOCUMENTS.map((doc) => (
            <DocumentUpload
              key={doc.type}
              type={doc.type}
              label={doc.label}
              hint={doc.hint}
              file={documents[doc.type] ?? null}
              onChange={handleDocumentChange}
            />
          ))}
        </div>
      </section>

      <section
        className={`rounded-lg border p-6 ${
          affordable === false
            ? 'border-red-200 bg-red-50'
            : affordable === true
              ? 'border-emerald-200 bg-emerald-50'
              : 'border-slate-200 bg-white'
        }`}
      >
        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Quote</h2>

        <dl className="mt-4 grid gap-4 sm:grid-cols-3">
          <div>
            <dt className="text-sm text-slate-500">Monthly instalment</dt>
            <dd className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
              {quote ? formatMoney(quote.monthly_instalment) : '-'}
            </dd>
          </div>
          <div>
            <dt className="text-sm text-slate-500">Total repayable</dt>
            <dd className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
              {quote ? formatMoney(quote.total_repayable) : '-'}
            </dd>
          </div>
          <div>
            <dt className="text-sm text-slate-500">Cost of credit</dt>
            <dd className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
              {quote ? formatMoney(quote.total_interest) : '-'}
            </dd>
          </div>
        </dl>

        {client && (
          <p className="mt-4 text-sm">
            {disposable === null ? (
              <span className="text-amber-800">
                This client has no affordability assessment, so no application can be made.
              </span>
            ) : affordable ? (
              <span className="text-emerald-800">
                Fits within {formatMoney(disposable)} of disposable income.
              </span>
            ) : (
              <span className="text-red-700">
                The instalment exceeds {formatMoney(disposable)} of disposable income. Reduce the
                amount or lengthen the term.
              </span>
            )}
          </p>
        )}
      </section>

      <div className="flex flex-wrap items-center justify-end gap-3">
        {!canSubmit && (
          <p className="mr-auto text-sm text-slate-500">
            {!client
              ? 'Select a client to continue.'
              : affordable === false
                ? 'The instalment is not affordable on the assessment on file.'
                : missingDocuments.length > 0
                  ? `Still needed: ${missingDocuments.map((doc) => doc.label.toLowerCase()).join(', ')}.`
                  : ''}
          </p>
        )}

        <button
          type="button"
          onClick={() => void navigate('/applications')}
          className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={handleSubmit}
          disabled={!canSubmit}
          className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
        >
          {mutation.isPending ? 'Submitting…' : 'Submit for decision'}
        </button>
      </div>
    </div>
  )
}
