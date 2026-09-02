import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'

import { Field, Section, inputClass } from '@/components/Field'
import { fetchBanks, fetchLocations, fetchRecruiters, registerClient } from '@/features/clients/api'
import { registerClientSchema, type RegisterClientForm } from '@/features/clients/schema'
import { errorMessage } from '@/lib/api'
import { formatDate, formatMoney } from '@/lib/format'
import { inspectIdNumber } from '@/lib/idNumber'

export function RegisterClientPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [submitError, setSubmitError] = useState<string | null>(null)

  const { data: recruiters } = useQuery({
    queryKey: ['recruiters', 'active'],
    queryFn: fetchRecruiters,
  })

  const { data: banks } = useQuery({
    queryKey: ['reference', 'banks'],
    queryFn: fetchBanks,
    // Reference data: it does not change while the officer is at the desk.
    staleTime: Infinity,
  })

  const { data: locations } = useQuery({
    queryKey: ['reference', 'locations'],
    queryFn: fetchLocations,
    staleTime: Infinity,
  })

  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<RegisterClientForm>({
    resolver: zodResolver(registerClientSchema),
    mode: 'onChange',
    defaultValues: {
      first_name: '',
      last_name: '',
      id_number: '',
      phone: '',
      email: '',
      city: '',
      province: '',
      employer_name: '',
      employment_status: 'permanent',
      bank_name: '',
      bank_account_number: '',
      recruiter_mode: 'none',
      recruiter_id: '',
      new_recruiter_first_name: '',
      new_recruiter_last_name: '',
      new_recruiter_id_number: '',
      new_recruiter_phone: '',
      new_recruiter_bank_name: '',
      new_recruiter_bank_account_number: '',
      gross_monthly_income: 0,
      net_monthly_income: 0,
      monthly_living_expenses: 0,
      monthly_debt_repayments: 0,
    },
  })

  const recruiterMode = useWatch({ control, name: 'recruiter_mode' })

  // The ID number carries the date of birth, age and gender, so they fill in
  // as it is typed rather than being keyed a second time. Null until the
  // number is 13 digits with a sound check digit.
  const idNumber = useWatch({ control, name: 'id_number' })
  const { details: identity, problem: idProblem } = inspectIdNumber(idNumber ?? '')

  // Shown read-only beside the bank. The server derives the same code from the
  // bank name on save, so this is a preview, never the value that is stored.
  const [bankName, newRecruiterBankName] = useWatch({
    control,
    name: ['bank_name', 'new_recruiter_bank_name'],
  })
  const selectedBranchCode = banks?.find((bank) => bank.name === bankName)?.branch_code ?? ''
  const recruiterBranchCode =
    banks?.find((bank) => bank.name === newRecruiterBankName)?.branch_code ?? ''

  // Shown live so the officer sees the figure the lender will underwrite
  // against before the client has left the desk.
  const [net, living, debt] = useWatch({
    control,
    name: ['net_monthly_income', 'monthly_living_expenses', 'monthly_debt_repayments'],
  })
  const disposable = (net || 0) - (living || 0) - (debt || 0)

  const mutation = useMutation({
    mutationFn: registerClient,
    onSuccess: async (client) => {
      await queryClient.invalidateQueries({ queryKey: ['clients'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      await queryClient.invalidateQueries({ queryKey: ['recruiters'] })
      void navigate('/clients', {
        replace: true,
        state: { registered: `${client.full_name} registered as ${client.client_number}` },
      })
    },
    onError: (error) => setSubmitError(errorMessage(error, 'The client could not be registered.')),
  })

  function onSubmit(values: RegisterClientForm) {
    setSubmitError(null)

    mutation.mutate({
      first_name: values.first_name,
      last_name: values.last_name,
      id_number: values.id_number,
      phone: values.phone,
      email: values.email || null,
      city: values.city || null,
      province: values.province || null,
      employer_name: values.employer_name || null,
      employment_status: values.employment_status,
      bank_name: values.bank_name || null,
      bank_account_number: values.bank_account_number || null,
      recruiter_id: values.recruiter_mode === 'existing' ? Number(values.recruiter_id) : null,
      new_recruiter:
        values.recruiter_mode === 'new'
          ? {
              first_name: values.new_recruiter_first_name,
              last_name: values.new_recruiter_last_name,
              id_number: values.new_recruiter_id_number,
              phone: values.new_recruiter_phone,
              bank_name: values.new_recruiter_bank_name || null,
              bank_account_number: values.new_recruiter_bank_account_number || null,
            }
          : null,
      affordability: {
        gross_monthly_income: values.gross_monthly_income,
        net_monthly_income: values.net_monthly_income,
        monthly_living_expenses: values.monthly_living_expenses,
        monthly_debt_repayments: values.monthly_debt_repayments,
      },
    })
  }

  return (
    <form onSubmit={(event) => void handleSubmit(onSubmit)(event)} className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Register client</h1>
        <p className="mt-1 text-sm text-slate-500">
          The client number is issued on save and stays with this borrower for every loan they
          subsequently take.
        </p>
      </header>

      {submitError && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {submitError}
        </p>
      )}

      <Section title="Personal details">
        <Field label="First name" error={errors.first_name?.message}>
          <input {...register('first_name')} className={inputClass} />
        </Field>
        <Field label="Last name" error={errors.last_name?.message}>
          <input {...register('last_name')} className={inputClass} />
        </Field>
        <Field
          label="ID number"
          error={errors.id_number?.message ?? idProblem ?? undefined}
          hint="Date of birth, age and gender are read from it."
        >
          <input
            {...register('id_number')}
            inputMode="numeric"
            maxLength={13}
            className={inputClass}
          />
        </Field>

        <div className="grid grid-cols-3 gap-3">
          <Field label="Date of birth">
            <input
              value={identity ? formatDate(identity.dateOfBirth.toISOString()) : ''}
              readOnly
              placeholder="-"
              className={`${inputClass} bg-slate-50 text-slate-600`}
            />
          </Field>
          <Field label="Age">
            <input
              value={identity ? `${identity.age}` : ''}
              readOnly
              placeholder="-"
              className={`${inputClass} bg-slate-50 text-slate-600`}
            />
          </Field>
          <Field label="Gender">
            <input
              value={identity ? (identity.gender === 'female' ? 'Female' : 'Male') : ''}
              readOnly
              placeholder="-"
              className={`${inputClass} bg-slate-50 capitalize text-slate-600`}
            />
          </Field>
        </div>
        <Field label="Phone" error={errors.phone?.message}>
          <input {...register('phone')} className={inputClass} />
        </Field>
        <Field label="Email" error={errors.email?.message} hint="Optional">
          <input {...register('email')} className={inputClass} />
        </Field>
        <Field label="City" error={errors.city?.message} hint="Optional">
          <input {...register('city')} className={inputClass} />
        </Field>
        <Field label="Province" error={errors.province?.message} hint="Optional">
          <select {...register('province')} className={inputClass}>
            <option value="">Select a province…</option>
            {locations?.provinces.map((province) => (
              <option key={province} value={province}>
                {province}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Country" hint="CreditHub lends in South Africa only.">
          <input
            value={locations?.country ?? 'South Africa'}
            readOnly
            className={`${inputClass} bg-slate-50 text-slate-600`}
          />
        </Field>
      </Section>

      <Section title="Employment">
        <Field label="Employer" error={errors.employer_name?.message} hint="Optional">
          <input {...register('employer_name')} className={inputClass} />
        </Field>
        <Field label="Employment status" error={errors.employment_status?.message}>
          <select {...register('employment_status')} className={inputClass}>
            <option value="permanent">Permanent</option>
            <option value="contract">Contract</option>
            <option value="self_employed">Self-employed</option>
            <option value="pensioner">Pensioner</option>
          </select>
        </Field>
      </Section>

      <Section
        title="Banking details"
        description="Where this client's loan will be paid. The branch code follows from the bank."
      >
        <Field label="Bank" error={errors.bank_name?.message} hint="Optional at registration">
          <select {...register('bank_name')} className={inputClass}>
            <option value="">No bank captured</option>
            {banks?.map((bank) => (
              <option key={bank.name} value={bank.name}>
                {bank.name}
              </option>
            ))}
          </select>
        </Field>

        <Field label="Account number" error={errors.bank_account_number?.message}>
          <input
            {...register('bank_account_number')}
            inputMode="numeric"
            className={inputClass}
          />
        </Field>

        <Field
          label="Universal branch code"
          hint="Set by the bank, not captured by hand."
        >
          <input
            value={selectedBranchCode}
            readOnly
            placeholder="-"
            className={`${inputClass} bg-slate-50 text-slate-500`}
          />
        </Field>
      </Section>

      <Section
        title="Recruiter"
        description="Link the person who introduced this client. Commission is earned against this link."
      >
        <Field label="Introduced by">
          <select {...register('recruiter_mode')} className={inputClass}>
            <option value="none">Walk-in - no recruiter</option>
            <option value="existing">An existing recruiter</option>
            <option value="new">A recruiter not yet registered</option>
          </select>
        </Field>

        {recruiterMode === 'existing' && (
          <Field label="Recruiter" error={errors.recruiter_id?.message}>
            <select {...register('recruiter_id')} className={inputClass}>
              <option value="">Choose a recruiter…</option>
              {recruiters?.map((recruiter) => (
                <option key={recruiter.id} value={recruiter.id}>
                  {recruiter.full_name} ({recruiter.recruiter_number})
                </option>
              ))}
            </select>
          </Field>
        )}

        {recruiterMode === 'new' && (
          <>
            <Field label="Recruiter first name" error={errors.new_recruiter_first_name?.message}>
              <input {...register('new_recruiter_first_name')} className={inputClass} />
            </Field>
            <Field label="Recruiter last name" error={errors.new_recruiter_last_name?.message}>
              <input {...register('new_recruiter_last_name')} className={inputClass} />
            </Field>
            <Field label="Recruiter ID number" error={errors.new_recruiter_id_number?.message}>
              <input
                {...register('new_recruiter_id_number')}
                inputMode="numeric"
                className={inputClass}
              />
            </Field>
            <Field label="Recruiter phone" error={errors.new_recruiter_phone?.message}>
              <input {...register('new_recruiter_phone')} className={inputClass} />
            </Field>
            <Field
              label="Recruiter bank"
              error={errors.new_recruiter_bank_name?.message}
              hint="Where their commission is paid"
            >
              <select {...register('new_recruiter_bank_name')} className={inputClass}>
                <option value="">No bank captured</option>
                {banks?.map((bank) => (
                  <option key={bank.name} value={bank.name}>
                    {bank.name}
                  </option>
                ))}
              </select>
            </Field>
            <Field
              label="Recruiter account number"
              error={errors.new_recruiter_bank_account_number?.message}
              hint={recruiterBranchCode ? `Branch code ${recruiterBranchCode}` : undefined}
            >
              <input
                {...register('new_recruiter_bank_account_number')}
                inputMode="numeric"
                className={inputClass}
              />
            </Field>
          </>
        )}
      </Section>

      <Section
        title="Affordability"
        description="Taken at registration. The figure below is the ceiling on any instalment this client can be granted."
      >
        <Field label="Gross monthly income" error={errors.gross_monthly_income?.message}>
          <input
            type="number"
            step="0.01"
            {...register('gross_monthly_income', { valueAsNumber: true })}
            className={inputClass}
          />
        </Field>
        <Field label="Net monthly income" error={errors.net_monthly_income?.message}>
          <input
            type="number"
            step="0.01"
            {...register('net_monthly_income', { valueAsNumber: true })}
            className={inputClass}
          />
        </Field>
        <Field label="Monthly living expenses" error={errors.monthly_living_expenses?.message}>
          <input
            type="number"
            step="0.01"
            {...register('monthly_living_expenses', { valueAsNumber: true })}
            className={inputClass}
          />
        </Field>
        <Field label="Monthly debt repayments" error={errors.monthly_debt_repayments?.message}>
          <input
            type="number"
            step="0.01"
            {...register('monthly_debt_repayments', { valueAsNumber: true })}
            className={inputClass}
          />
        </Field>

        <div className="sm:col-span-2">
          <div
            className={`rounded-md border p-4 ${
              disposable > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'
            }`}
          >
            <p className="text-sm font-medium text-slate-700">Disposable income</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
              {formatMoney(disposable)}
            </p>
            <p className="mt-1 text-xs text-slate-600">
              {disposable > 0
                ? 'Net income less living expenses and existing debt repayments.'
                : 'This client has nothing left each month. A loan would not be affordable.'}
            </p>
          </div>
        </div>
      </Section>

      <div className="flex justify-end gap-3">
        <button
          type="button"
          onClick={() => void navigate('/clients')}
          className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={isSubmitting || mutation.isPending}
          className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
        >
          {mutation.isPending ? 'Registering…' : 'Register client'}
        </button>
      </div>
    </form>
  )
}
