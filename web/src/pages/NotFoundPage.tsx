import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return (
    <div className="flex h-full flex-col items-center justify-center gap-3 text-center">
      <p className="text-sm font-semibold text-brand-600">404</p>
      <h1 className="text-2xl font-semibold tracking-tight">Page not found</h1>
      <Link to="/" className="text-sm font-medium text-brand-600 hover:text-brand-700">
        Back to the dashboard
      </Link>
    </div>
  )
}
