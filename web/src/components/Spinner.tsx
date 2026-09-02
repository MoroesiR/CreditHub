export function Spinner({ label = 'Loading' }: { label?: string }) {
  return (
    <span role="status" aria-label={label} className="inline-flex items-center gap-2">
      <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
    </span>
  )
}

export function FullPageSpinner() {
  return (
    <div className="flex h-full items-center justify-center text-slate-400">
      <Spinner label="Loading CreditHub" />
    </div>
  )
}
