import { formatDate } from '@/lib/format'
import type { JourneyStage } from '@/types/applications'

/**
 * Where the file has been and whose desk it sat on.
 *
 * Stages that have not happened are still drawn, greyed out, so the reader can
 * see what is still to come rather than only what is done.
 */
export function JourneyTimeline({ stages }: { stages: JourneyStage[] }) {
  return (
    <ol className="mt-4 space-y-0">
      {stages.map((stage, index) => {
        const isLast = index === stages.length - 1

        return (
          <li key={stage.key} className="relative flex gap-4 pb-6 last:pb-0">
            {!isLast && (
              <span
                aria-hidden="true"
                className={`absolute left-[7px] top-5 h-full w-px ${
                  stage.done ? 'bg-brand-300' : 'bg-slate-200'
                }`}
              />
            )}

            <span
              className={`relative z-10 mt-1 h-4 w-4 shrink-0 rounded-full ring-4 ring-white ${
                stage.done ? 'bg-brand-600' : 'border border-slate-300 bg-slate-100'
              }`}
            />

            <div className="min-w-0 flex-1">
              <p
                className={`text-sm font-medium ${
                  stage.done ? 'text-slate-900' : 'text-slate-400'
                }`}
              >
                {stage.label}
              </p>

              {stage.done ? (
                <p className="text-xs text-slate-500">
                  {stage.actor ?? 'Unrecorded'}
                  {stage.at ? ` · ${formatDate(stage.at)}` : ''}
                </p>
              ) : (
                <p className="text-xs text-slate-400">Not yet</p>
              )}

              {stage.detail && (
                <p className={`text-xs ${stage.done ? 'text-slate-600' : 'text-slate-400'}`}>
                  {stage.detail}
                </p>
              )}
            </div>
          </li>
        )
      })}
    </ol>
  )
}
