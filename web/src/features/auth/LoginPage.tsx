import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { FullPageSpinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import { errorMessage } from '@/lib/api'

const loginSchema = z.object({
  email: z.email('Enter a valid email address.'),
  password: z.string().min(1, 'Enter your password.'),
})

type LoginFields = z.infer<typeof loginSchema>

export function LoginPage() {
  const { user, isLoading, signIn } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFields>({ resolver: zodResolver(loginSchema) })

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (user) {
    return <Navigate to="/" replace />
  }

  async function onSubmit(fields: LoginFields) {
    setFormError(null)

    try {
      await signIn(fields.email, fields.password)

      const from = (location.state as { from?: string } | null)?.from
      void navigate(from ?? '/', { replace: true })
    } catch (error) {
      setFormError(errorMessage(error, 'Unable to sign in. Please try again.'))
    }
  }

  return (
    <div className="grid min-h-full lg:grid-cols-2">
      <section className="hidden flex-col justify-between bg-brand-900 p-12 text-white lg:flex">
        <span className="text-xl font-semibold tracking-tight">CreditHub</span>
        <div className="max-w-md">
          <h1 className="text-3xl font-semibold leading-tight">
            Origination, credit and disbursement in one ledger.
          </h1>
          <p className="mt-4 text-brand-100">
            Register a client, assess what they can afford, decide the application, and release the
            payout - with every step attributed to the person who took it.
          </p>
        </div>
        <p className="text-sm text-brand-100">Loan management system</p>
      </section>

      <section className="flex items-center justify-center px-6 py-16">
        <div className="w-full max-w-sm">
          <h2 className="text-2xl font-semibold tracking-tight">Sign in</h2>
          <p className="mt-1 text-sm text-slate-500">Use your CreditHub staff account.</p>

          <form
            onSubmit={(event) => void handleSubmit(onSubmit)(event)}
            className="mt-8 space-y-5"
            noValidate
          >
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-slate-700">
                Email address
              </label>
              <input
                id="email"
                type="email"
                autoComplete="username"
                autoFocus
                aria-invalid={errors.email ? 'true' : undefined}
                {...register('email')}
                className="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              />
              {errors.email && (
                <p className="mt-1.5 text-sm text-red-600">{errors.email.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-slate-700">
                Password
              </label>
              <input
                id="password"
                type="password"
                autoComplete="current-password"
                aria-invalid={errors.password ? 'true' : undefined}
                {...register('password')}
                className="mt-1.5 w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
              />
              {errors.password && (
                <p className="mt-1.5 text-sm text-red-600">{errors.password.message}</p>
              )}
            </div>

            {formError && (
              <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                {formError}
              </p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full rounded-md bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
            >
              {isSubmitting ? 'Signing in…' : 'Sign in'}
            </button>
          </form>
        </div>
      </section>
    </div>
  )
}
