import { useRef, useState } from 'react'

import { DocumentModal } from '@/components/DocumentModal'

import type { DocumentType } from '@/types/applications'

const MAX_BYTES = 5 * 1024 * 1024
const ACCEPT = '.pdf,.jpg,.jpeg,.png'

function readableSize(bytes: number): string {
  return bytes < 1024 * 1024
    ? `${Math.round(bytes / 1024)} KB`
    : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

export function DocumentUpload({
  type,
  label,
  hint,
  file,
  error,
  onChange,
}: {
  type: DocumentType
  label: string
  hint: string
  file: File | null
  error?: string
  onChange: (type: DocumentType, file: File | null) => void
}) {
  const inputRef = useRef<HTMLInputElement>(null)
  // Attaching the wrong file is easy and invisible until someone downstream
  // opens it, so the officer can check the page before the application goes.
  const [isPreviewing, setIsPreviewing] = useState(false)
  const tooLarge = file !== null && file.size > MAX_BYTES

  return (
    <div className="rounded-md border border-ink-200 p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-ink-900">{label}</p>
          <p className="mt-0.5 text-xs text-ink-500">{hint}</p>
        </div>

        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="whitespace-nowrap rounded-md border border-ink-300 px-3 py-1.5 text-sm font-medium text-ink-700 transition-colors hover:bg-ink-100"
        >
          {file ? 'Replace' : 'Choose file'}
        </button>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept={ACCEPT}
        hidden
        onChange={(event) => onChange(type, event.target.files?.[0] ?? null)}
      />

      {file && (
        <div className="mt-3 flex items-center justify-between gap-4 rounded-md bg-ink-50 px-3 py-2">
          <span className="truncate text-sm text-ink-700">{file.name}</span>
          <span className="flex items-center gap-3 whitespace-nowrap">
            <span className={`text-xs ${tooLarge ? 'text-bad-600' : 'text-ink-500'}`}>
              {readableSize(file.size)}
            </span>
            <button
              type="button"
              onClick={() => setIsPreviewing(true)}
              className="rounded border border-ink-300 px-2 py-0.5 text-xs font-medium text-ink-700 hover:bg-white"
            >
              View
            </button>
            <button
              type="button"
              onClick={() => onChange(type, null)}
              className="text-xs font-medium text-ink-500 hover:text-bad-600"
            >
              Remove
            </button>
          </span>
        </div>
      )}

      {isPreviewing && file && (
        <DocumentModal
          file={file}
          title={label}
          subtitle={file.name}
          onClose={() => setIsPreviewing(false)}
        />
      )}

      {tooLarge && (
        <p className="mt-2 text-xs text-bad-600">
          That file is over the 5 MB limit and will be refused.
        </p>
      )}

      {error && !tooLarge && <p className="mt-2 text-xs text-bad-600">{error}</p>}
    </div>
  )
}
