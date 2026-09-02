import { QueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      // Retrying a 401 or a 403 only delays the redirect to the login screen.
      retry: (failureCount, error) => {
        const status = isAxiosError(error) ? error.response?.status : undefined

        if (status !== undefined && status >= 400 && status < 500) {
          return false
        }

        return failureCount < 2
      },
    },
  },
})
