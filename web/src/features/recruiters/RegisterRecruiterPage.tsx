import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { Field, Section, inputClass } from '@/components/Field'
import { fetchBanks } from '@/features/clients/api'
import { registerRecruiter } from '@/features/recruiters/api'
import { errorMessage } from '@/lib/api'
import { hasValidCheckDigit, inspectIdNumber } from '@/lib/idNumber'

const schema = z
  .object({
    first_name: z.string().min(1, 'First name is required').max(255),
    last_name: z.string().min(1, 'Last name is required').max(255),
    id_number: z
      .string()
      .regex(/^\d{13}$/, 'A South African ID number is 13 digits')
      .refine(hasValidCheckDigit, 'That ID number fails its check digit'),
    phone: z.string().min(1, 'Phone number is required').max(20),
    email: z.union([z.literal(''), z.email('Enter a valid email address')]),
    bank_name: z.string(),
    bank_account_number: z.string(),
  })
  .superRefine((values, ctx) => {
    if (values.bank_name && !/^\d{6,20}$/.test(values.bank_account_number)) {
      ctx.addIssue({
        code: 'custom',
        path: ['bank_account_number'],
        message: 'Enter the account number (6 to 20 digits)',
      })
    }

    if (!values.bank_name && values.bank_account_number) {
      ctx.addIssue({
        code: 'custom',
        path: ['bank_name'],
        message: 'Choose the bank this account is held at',
      })
    }
  })

type RegisterRecruiterForm = z.infer<typeof schema>

export function RegisterRecruiterPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [submitError, setSubmitError] = useState<string | null>(null)

  const { data: banks } = useQuery({
    queryKey: ['reference', 'banks'],
    queryFn: fetchBanks,
    staleTime: Infinity,
  })

  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<RegisterRecruiterForm>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      first_name: '',
      last_name: '',
      id_number: '',
      phone: '',
      email: '',
      bank_name: '',
      bank_account_number: '',
    },
  })

  // A recruiter's ID number carries the same details a client's does. Shown
  // here because commission is taxable income and the person earning it has to
  // be the person on the ID.
  const idNumber = useWatch({ control, name: 'id_number' })
  const { details: identity, problem: idProblem } = inspectIdNumber(idNumber ?? '')

  const bankName = useWatch({ control, name: 'bank_name' })
  const branchCode = banks?.find((bank) => bank.name === bankName)?.branch_code ?? ''

  const mutation = useMutation({
    mutationFn: registerRecruiter,
    onSuccess: async (recruiter) => {
      await queryClient.invalidateQueries({ queryKey: ['recruiters'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
      // Land on the recruiter, where the unlinked clients are offered.
      void navigate(`/recruiters/${recruiter.id}`, { replace: true })
    },
    onError: (error) =>
      setSubmitError(errorMessage(error, 'The recruiter could not be registered.')),
  })

  function onSubmit(values: RegisterRecruiterForm) {
    setSubmitError(null)

    mutation.mutate({
      first_name: values.first_name,
      last_name: values.last_name,
      id_number: values.id_number,
      phone: values.phone,
      email: values.email || null,
      bank_name: values.bank_name || null,
      bank_account_number: values.bank_account_number || null,
    })
  }

  return (
    <form onSubmit={(event) => void handleSubmit(onSubmit)(event)} className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Register recruiter</h1>
        <p className="mt-1 text-sm text-slate-500">
          Recruiters introduce borrowers and earn commission on the loans that result. They are not
          staff and never sign in.
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
          hint="Commission is taxable income, so the recruiter must be identifiable."
        >
          <input
            {...register('id_number')}
            inputMode="numeric"
            maxLength={13}
            className={inputClass}
          />
        </Field>
        <Field label="Date of birth and gender">
          <input
            value={
              identity
                ? `${identity.dateOfBirth.toLocaleDateString('en-ZA')} · ${identity.age} · ${
                    identity.gender === 'female' ? 'Female' : 'Male'
                  }`
                : ''
            }
            readOnly
            placeholder="-"
            className={`${inputClass} bg-slate-50 text-slate-600`}
          />
        </Field>
        <Field label="Phone" error={errors.phone?.message}>
          <input {...register('phone')} className={inputClass} />
        </Field>
        <Field label="Email" error={errors.email?.message} hint="Optional">
          <input {...register('email')} className={inputClass} />
        </Field>
      </Section>

      <Section
        title="Banking details"
        description="Where this recruiter's commission is paid. The branch code follows from the bank."
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
          <input {...register('bank_account_number')} inputMode="numeric" className={inputClass} />
        </Field>
        <Field label="Universal branch code" hint="Set by the bank, not captured by hand.">
          <input
            value={branchCode}
            readOnly
            placeholder="-"
            className={`${inputClass} bg-slate-50 text-slate-500`}
          />
        </Field>
      </Section>

      <div className="flex justify-end gap-3">
        <button
          type="button"
          onClick={() => void navigate('/recruiters')}
          className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={isSubmitting || mutation.isPending}
          className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
        >
          {mutation.isPending ? 'Registering…' : 'Register recruiter'}
        </button>
      </div>
    </form>
  )
}
