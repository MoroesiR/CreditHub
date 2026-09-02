import type { ApplicationStatus } from '@/types/applications'

/**
 * Colour carries meaning here, so it is deliberate: amber is waiting on
 * someone, emerald has cleared a gate, red has stopped.
 */
const STYLES: Record<ApplicationStatus, string> = {
  draft: 'bg-slate-100 text-slate-700',
  submitted: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
  approved: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
  declined: 'bg-red-50 text-red-700 ring-1 ring-red-200',
  agreement_signed: 'bg-sky-50 text-sky-800 ring-1 ring-sky-200',
  disbursed: 'bg-emerald-600 text-white',
  cancelled: 'bg-slate-100 text-slate-500',
}

export function StatusBadge({ status, label }: { status: ApplicationStatus; label: string }) {
  return (
    <span
      className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${STYLES[status]}`}
    >
      {label}
    </span>
  )
}
