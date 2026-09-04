import type { ApplicationStatus } from '@/types/applications'

/**
 * Colour carries meaning here, so it is deliberate: amber is waiting on
 * someone, emerald has cleared a gate, red has stopped.
 */
const STYLES: Record<ApplicationStatus, string> = {
  draft: 'bg-ink-100 text-ink-700',
  submitted: 'bg-warn-50 text-warn-800 ring-1 ring-warn-200',
  approved: 'bg-good-50 text-good-800 ring-1 ring-good-200',
  declined: 'bg-bad-50 text-bad-700 ring-1 ring-bad-200',
  agreement_signed: 'bg-info-50 text-info-800 ring-1 ring-info-200',
  disbursed: 'bg-good-600 text-white',
  cancelled: 'bg-ink-100 text-ink-500',
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
