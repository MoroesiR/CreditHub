import type { Module } from '@/navigation'

/**
 * Stands in for a module whose screens are not built yet, so the shell and its
 * permission gating can be exercised ahead of the feature work.
 */
export function ModulePage({ module }: { module: Module }) {
  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">{module.label}</h1>
        <p className="mt-1 max-w-2xl text-sm text-ink-500">{module.summary}</p>
      </header>

      <div className="rounded-lg border border-dashed border-ink-300 bg-white p-8 text-center">
        <p className="text-sm font-medium text-ink-900">Not built yet</p>
        <p className="mt-1 text-sm text-ink-500">
          Your role may open this screen. The module itself lands in a later slice.
        </p>
      </div>
    </div>
  )
}
