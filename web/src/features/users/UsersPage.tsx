import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { Field, inputClass } from '@/components/Field'
import { Spinner } from '@/components/Spinner'
import { useAuth } from '@/features/auth/useAuth'
import { createUser, fetchAssignableRoles, fetchUsers, updateUser } from '@/features/users/api'
import { errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'

const BLANK = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  job_title: '',
  password: '',
}

export function UsersPage() {
  const { can, user: signedIn } = useAuth()
  const queryClient = useQueryClient()

  const [form, setForm] = useState(BLANK)
  const [chosenRoles, setChosenRoles] = useState<string[]>([])
  const [isCreating, setIsCreating] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const { data: users, isPending } = useQuery({ queryKey: ['users'], queryFn: fetchUsers })
  const { data: roles } = useQuery({
    queryKey: ['users', 'roles'],
    queryFn: fetchAssignableRoles,
    staleTime: Infinity,
  })

  async function refresh() {
    await queryClient.invalidateQueries({ queryKey: ['users'] })
  }

  const create = useMutation({
    mutationFn: () => createUser({ ...form, roles: chosenRoles }),
    onSuccess: async (created) => {
      setError(null)
      setNotice(`${created.full_name} can now sign in as ${created.email}.`)
      setForm(BLANK)
      setChosenRoles([])
      setIsCreating(false)
      await refresh()
    },
    onError: (cause) => {
      setNotice(null)
      setError(errorMessage(cause, 'That account could not be created.'))
    },
  })

  const amend = useMutation({
    mutationFn: ({ id, changes }: { id: number; changes: Record<string, unknown> }) =>
      updateUser(id, changes),
    onSuccess: async () => {
      setError(null)
      await refresh()
    },
    onError: (cause) => setError(errorMessage(cause, 'That account could not be updated.')),
  })

  function toggleRole(slug: string) {
    setChosenRoles((current) =>
      current.includes(slug) ? current.filter((item) => item !== slug) : [...current, slug],
    )
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Staff accounts</h1>
          <p className="mt-1 text-sm text-slate-500">
            Who may sign in, and what each of them is allowed to do. A role decides that, so
            changing someone's role changes what they can reach immediately.
          </p>
        </div>

        {can('users.manage') && (
          <button
            type="button"
            onClick={() => {
              setIsCreating((open) => !open)
              setError(null)
              setNotice(null)
            }}
            className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700"
          >
            {isCreating ? 'Cancel' : 'Add staff member'}
          </button>
        )}
      </header>

      {notice && (
        <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
          {notice}
        </p>
      )}

      {error && (
        <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </p>
      )}

      {isCreating && can('users.manage') && (
        <section className="rounded-lg border border-slate-200 bg-white p-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            New staff member
          </h2>

          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <Field label="First name">
              <input
                value={form.first_name}
                onChange={(event) => setForm({ ...form, first_name: event.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Last name">
              <input
                value={form.last_name}
                onChange={(event) => setForm({ ...form, last_name: event.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Email" hint="This is what they sign in with.">
              <input
                type="email"
                value={form.email}
                onChange={(event) => setForm({ ...form, email: event.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Phone" hint="Optional">
              <input
                value={form.phone}
                onChange={(event) => setForm({ ...form, phone: event.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Job title" hint="Optional">
              <input
                value={form.job_title}
                onChange={(event) => setForm({ ...form, job_title: event.target.value })}
                className={inputClass}
              />
            </Field>
            <Field
              label="Initial password"
              hint="At least 12 characters, with upper and lower case, a number and a symbol."
            >
              <input
                type="text"
                value={form.password}
                onChange={(event) => setForm({ ...form, password: event.target.value })}
                className={inputClass}
              />
            </Field>
          </div>

          <div className="mt-5">
            <p className="text-sm font-medium text-slate-700">Roles</p>
            <p className="text-xs text-slate-500">
              An account with no role can sign in and do nothing, so at least one is required.
            </p>

            <div className="mt-3 grid gap-2 sm:grid-cols-2">
              {roles?.map((role) => (
                <label
                  key={role.slug}
                  className={`flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors ${
                    chosenRoles.includes(role.slug)
                      ? 'border-brand-300 bg-brand-50'
                      : 'border-slate-200 hover:bg-slate-50'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={chosenRoles.includes(role.slug)}
                    onChange={() => toggleRole(role.slug)}
                    className="mt-0.5"
                  />
                  <span>
                    <span className="block text-sm font-medium text-slate-900">{role.name}</span>
                    <span className="block text-xs text-slate-500">{role.description}</span>
                    <span className="mt-1 block text-xs text-slate-400">
                      {role.permission_count} permissions
                    </span>
                  </span>
                </label>
              ))}
            </div>
          </div>

          <button
            type="button"
            onClick={() => create.mutate()}
            disabled={
              create.isPending ||
              !form.first_name ||
              !form.last_name ||
              !form.email ||
              !form.password ||
              chosenRoles.length === 0
            }
            className="mt-5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
          >
            {create.isPending ? 'Creating...' : 'Create account'}
          </button>
        </section>
      )}

      {isPending ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Roles</th>
                <th className="px-4 py-3 font-medium">Last signed in</th>
                <th className="px-4 py-3 font-medium">Status</th>
                {can('users.manage') && <th className="px-4 py-3" />}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {users?.map((staff) => (
                <tr key={staff.id} className={staff.is_active ? '' : 'bg-slate-50'}>
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-900">
                      {staff.full_name}
                      {staff.id === signedIn?.id && (
                        <span className="ml-2 text-xs font-normal text-slate-400">you</span>
                      )}
                    </p>
                    <p className="text-xs text-slate-500">{staff.email}</p>
                    {staff.job_title && (
                      <p className="text-xs text-slate-400">{staff.job_title}</p>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-1">
                      {staff.roles.map((role) => (
                        <span
                          key={role.slug}
                          className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700"
                        >
                          {role.name}
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-slate-600">
                    {staff.last_login_at ? formatDate(staff.last_login_at) : 'Never'}
                  </td>
                  <td className="px-4 py-3">
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                        staff.is_active
                          ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200'
                          : 'bg-slate-200 text-slate-600'
                      }`}
                    >
                      {staff.is_active ? 'Active' : 'Suspended'}
                    </span>
                  </td>
                  {can('users.manage') && (
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        onClick={() =>
                          amend.mutate({
                            id: staff.id,
                            changes: { is_active: !staff.is_active },
                          })
                        }
                        disabled={amend.isPending || staff.id === signedIn?.id}
                        title={
                          staff.id === signedIn?.id
                            ? 'You cannot suspend your own account'
                            : undefined
                        }
                        className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-100 disabled:opacity-40"
                      >
                        {staff.is_active ? 'Suspend' : 'Reactivate'}
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
