const ACCOUNTS = [
  { email: 'admin@credithub.test', role: 'System Administrator' },
  { email: 'officer@credithub.test', role: 'Loan Officer' },
  { email: 'credit@credithub.test', role: 'Credit Manager' },
  { email: 'payouts@credithub.test', role: 'Disbursement Officer' },
  { email: 'collections@credithub.test', role: 'Collections Officer' },
  { email: 'auditor@credithub.test', role: 'Auditor' },
]

export function DemoAccounts({ onPick }: { onPick: (email: string) => void }) {
  if (!import.meta.env.VITE_DEMO) {
    return null
  }

  return (
    <div className="mt-6 rounded-xl border border-ink-800 bg-ink-900/60 p-4">
      <p className="text-sm font-medium text-white">This is a demonstration</p>
      <p className="mt-1 text-xs text-ink-400">
        Every client, loan and payment here is made up. Sign in as any role to see a different
        system. The password is <span className="font-mono text-ink-200">password</span>.
      </p>

      <ul className="mt-3 space-y-1">
        {ACCOUNTS.map((account) => (
          <li key={account.email}>
            <button
              type="button"
              onClick={() => onPick(account.email)}
              className="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-ink-800"
            >
              <span className="font-mono text-ink-300">{account.email}</span>
              <span className="text-ink-400">{account.role}</span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
