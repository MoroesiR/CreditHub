import { useRef } from 'react'

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
  const tooLarge = file !== null && file.size > MAX_BYTES

  return (
    <div className="rounded-md border border-slate-200 p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-slate-900">{label}</p>
          <p className="mt-0.5 text-xs text-slate-500">{hint}</p>
        </div>

        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="whitespace-nowrap rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
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
        <div className="mt-3 flex items-center justify-between gap-4 rounded-md bg-slate-50 px-3 py-2">
          <span className="truncate text-sm text-slate-700">{file.name}</span>
          <span className="flex items-center gap-3 whitespace-nowrap">
            <span className={`text-xs ${tooLarge ? 'text-red-600' : 'text-slate-500'}`}>
              {readableSize(file.size)}
            </span>
            <button
              type="button"
              onClick={() => onChange(type, null)}
              className="text-xs font-medium text-slate-500 hover:text-red-600"
            >
              Remove
            </button>
          </span>
        </div>
      )}

      {tooLarge && (
        <p className="mt-2 text-xs text-red-600">
          That file is over the 5 MB limit and will be refused.
        </p>
      )}

      {error && !tooLarge && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  )
}
