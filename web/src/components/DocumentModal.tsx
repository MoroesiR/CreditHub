import { useEffect, useState } from 'react'

import { Spinner } from '@/components/Spinner'
import { api } from '@/lib/api'

/**
 * Shows a stored document without ever exposing a URL to it.
 *
 * The file lives on the private disk and is only handed over on an
 * authenticated request, so it is fetched as a blob and rendered from an
 * object URL held for as long as the modal is open. That URL is revoked on
 * close: a blob left registered is a copy of a payslip sitting in the tab
 * until the page is reloaded.
 */
export function DocumentModal({
  path,
  title,
  subtitle,
  onClose,
}: {
  path: string
  title: string
  subtitle?: string
  onClose: () => void
}) {
  const [objectUrl, setObjectUrl] = useState<string | null>(null)
  const [mimeType, setMimeType] = useState<string>('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let revoked = false
    let created: string | null = null

    async function load() {
      try {
        const response = await api.get<Blob>(path, { responseType: 'blob' })

        if (revoked) {
          return
        }

        created = URL.createObjectURL(response.data)
        setMimeType(response.data.type)
        setObjectUrl(created)
      } catch {
        setError('That document could not be opened.')
      }
    }

    void load()

    return () => {
      revoked = true

      if (created) {
        URL.revokeObjectURL(created)
      }
    }
  }, [path])

  useEffect(() => {
    function handleKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose()
      }
    }

    document.addEventListener('keydown', handleKey)

    return () => document.removeEventListener('keydown', handleKey)
  }, [onClose])

  const isImage = mimeType.startsWith('image/')

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={title}
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
      onClick={onClose}
    >
      <div
        className="flex max-h-full w-full max-w-4xl flex-col overflow-hidden rounded-lg bg-white shadow-xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
            {subtitle && <p className="text-xs text-slate-500">{subtitle}</p>}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
          >
            Close
          </button>
        </div>

        <div className="flex min-h-[60vh] flex-1 items-center justify-center overflow-auto bg-slate-50 p-4">
          {error ? (
            <p className="text-sm text-red-700">{error}</p>
          ) : !objectUrl ? (
            <Spinner />
          ) : isImage ? (
            <img src={objectUrl} alt={title} className="max-h-[70vh] max-w-full object-contain" />
          ) : (
            <iframe src={objectUrl} title={title} className="h-[70vh] w-full rounded border-0" />
          )}
        </div>
      </div>
    </div>
  )
}
