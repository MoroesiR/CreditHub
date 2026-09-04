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
  const [showPassword, setShowPassword] = useState(false)
  const [capsLock, setCapsLock] = useState(false)

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
    <div className="relative flex min-h-full flex-col overflow-hidden bg-ink-950">
      {/*
        Two soft washes of brand colour rather than a gradient across the whole
        page. They sit behind the card and give the screen some depth without
        turning the sign-in into a marketing banner.
      */}
      <span
        aria-hidden="true"
        className="pointer-events-none absolute -left-40 -top-40 h-96 w-96 rounded-full bg-brand-700/25 blur-3xl"
      />
      <span
        aria-hidden="true"
        className="pointer-events-none absolute -bottom-48 -right-32 h-[28rem] w-[28rem] rounded-full bg-info-700/20 blur-3xl"
      />

      <main className="relative flex flex-1 items-center justify-center px-4 py-12">
        <div className="w-full max-w-sm">
          <div className="mb-7 flex items-center gap-3">
            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-500 text-sm font-bold text-white shadow-lg shadow-brand-900/40">
              CH
            </span>
            <div className="leading-tight">
              <p className="text-lg font-semibold tracking-tight text-white">CreditHub</p>
              <p className="text-xs text-ink-400">Loan management</p>
            </div>
          </div>

          <div className="overflow-hidden rounded-xl border border-ink-800 bg-white shadow-2xl shadow-ink-950/50">
            <span aria-hidden="true" className="block h-1 w-full bg-brand-500" />

            <div className="p-6">
              <h1 className="text-base font-semibold text-ink-900">Staff sign in</h1>
              <p className="mt-1 text-sm text-ink-500">Access is recorded against your account.</p>

              <form
                onSubmit={(event) => void handleSubmit(onSubmit)(event)}
                className="mt-6 space-y-4"
                noValidate
              >
                <div>
                  <label htmlFor="email" className="block text-sm font-medium text-ink-700">
                    Email address
                  </label>
                  <input
                    id="email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    aria-invalid={errors.email ? 'true' : undefined}
                    {...register('email')}
                    className="mt-1.5 w-full rounded-md border border-ink-300 px-3 py-2 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                  />
                  {errors.email && (
                    <p className="mt-1.5 text-sm text-bad-600">{errors.email.message}</p>
                  )}
                </div>

                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-ink-700">
                    Password
                  </label>
                  <div className="relative mt-1.5">
                    <input
                      id="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="current-password"
                      aria-invalid={errors.password ? 'true' : undefined}
                      // Caps lock accounts for a good share of failed sign-ins,
                      // and the field hides the evidence by design.
                      onKeyUp={(event) => setCapsLock(event.getModifierState('CapsLock'))}
                      {...register('password')}
                      className="w-full rounded-md border border-ink-300 py-2 pl-3 pr-16 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((shown) => !shown)}
                      className="absolute inset-y-0 right-0 px-3 text-xs font-medium text-ink-500 transition-colors hover:text-ink-800"
                    >
                      {showPassword ? 'Hide' : 'Show'}
                    </button>
                  </div>
                  {errors.password && (
                    <p className="mt-1.5 text-sm text-bad-600">{errors.password.message}</p>
                  )}
                  {capsLock && !errors.password && (
                    <p className="mt-1.5 text-sm text-warn-700">Caps lock is on.</p>
                  )}
                </div>

                {formError && (
                  <p
                    role="alert"
                    className="rounded-md border border-bad-200 bg-bad-50 px-3 py-2 text-sm text-bad-700"
                  >
                    {formError}
                  </p>
                )}

                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full rounded-md bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 disabled:opacity-60"
                >
                  {isSubmitting ? 'Signing in...' : 'Sign in'}
                </button>
              </form>
            </div>

            <div className="border-t border-ink-100 bg-ink-50 px-6 py-3">
              <p className="text-xs text-ink-500">
                Lost your password? An administrator can reset it. Repeated failed attempts lock the
                account for a few minutes.
              </p>
            </div>
          </div>
        </div>
      </main>

      <footer className="relative px-4 pb-6 text-center text-xs text-ink-500">
        Authorised users only. Activity on this system is logged.
      </footer>
    </div>
  )
}
