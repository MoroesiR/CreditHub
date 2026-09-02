import { z } from 'zod'

import { hasValidCheckDigit, parseIdNumber } from '@/lib/idNumber'

/**
 * Mirrors the API's StoreClientRequest. The browser copy exists to give
 * immediate feedback - the server validates the same rules again, and its
 * answer is the one that decides whether a client is registered.
 *
 * Date of birth and gender are absent: both are read out of the ID number,
 * shown on screen as it is typed, and derived again server-side on save.
 */

const money = z
  .number({ message: 'Enter an amount.' })
  .min(0, 'Cannot be negative')
  .max(9_999_999, 'That amount looks wrong')

export const registerClientSchema = z
  .object({
    first_name: z.string().min(1, 'First name is required').max(255),
    last_name: z.string().min(1, 'Last name is required').max(255),
    id_number: z
      .string()
      .regex(/^\d{13}$/, 'A South African ID number is 13 digits')
      .refine(hasValidCheckDigit, 'That ID number fails its check digit')
      .refine(
        (value) => parseIdNumber(value) !== null,
        'That ID number does not start with a real date of birth',
      )
      .refine(
        (value) => (parseIdNumber(value)?.age ?? 18) >= 18,
        'A client must be 18 or older',
      ),
    phone: z.string().min(1, 'Phone number is required').max(20),
    email: z.union([z.literal(''), z.email('Enter a valid email address')]),
    city: z.string().max(80),
    province: z.string().max(80),

    employer_name: z.string().max(255),
    employment_status: z.enum(['permanent', 'contract', 'self_employed', 'pensioner']),

    bank_name: z.string(),
    bank_account_number: z.string(),

    recruiter_mode: z.enum(['none', 'existing', 'new']),
    recruiter_id: z.string(),
    new_recruiter_first_name: z.string(),
    new_recruiter_last_name: z.string(),
    new_recruiter_id_number: z.string(),
    new_recruiter_phone: z.string(),
    new_recruiter_bank_name: z.string(),
    new_recruiter_bank_account_number: z.string(),

    gross_monthly_income: money,
    net_monthly_income: money,
    monthly_living_expenses: money,
    monthly_debt_repayments: money,
  })
  .superRefine((values, ctx) => {
    if (values.recruiter_mode === 'existing' && !values.recruiter_id) {
      ctx.addIssue({
        code: 'custom',
        path: ['recruiter_id'],
        message: 'Choose the recruiter who introduced this client',
      })
    }

    if (values.recruiter_mode === 'new') {
      const required = [
        ['new_recruiter_first_name', 'First name is required'],
        ['new_recruiter_last_name', 'Last name is required'],
        ['new_recruiter_phone', 'Phone number is required'],
      ] as const

      for (const [field, message] of required) {
        if (!values[field]) {
          ctx.addIssue({ code: 'custom', path: [field], message })
        }
      }

      if (!/^\d{13}$/.test(values.new_recruiter_id_number)) {
        ctx.addIssue({
          code: 'custom',
          path: ['new_recruiter_id_number'],
          message: 'A South African ID number is 13 digits',
        })
      } else if (!hasValidCheckDigit(values.new_recruiter_id_number)) {
        ctx.addIssue({
          code: 'custom',
          path: ['new_recruiter_id_number'],
          message: 'That ID number fails its check digit',
        })
      }
    }

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

    if (
      values.recruiter_mode === 'new' &&
      values.new_recruiter_bank_name &&
      !/^\d{6,20}$/.test(values.new_recruiter_bank_account_number)
    ) {
      ctx.addIssue({
        code: 'custom',
        path: ['new_recruiter_bank_account_number'],
        message: 'Enter the account number (6 to 20 digits)',
      })
    }

    if (values.net_monthly_income > values.gross_monthly_income) {
      ctx.addIssue({
        code: 'custom',
        path: ['net_monthly_income'],
        message: 'Net income cannot exceed gross income',
      })
    }
  })

export type RegisterClientForm = z.infer<typeof registerClientSchema>
