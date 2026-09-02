const STORAGE_KEY = 'credithub.token'

/**
 * The bearer token lives in localStorage so a page refresh keeps the session.
 * Reads are guarded because a browser with site data blocked throws here
 * rather than returning null.
 */
export const tokenStore = {
  get(): string | null {
    try {
      return window.localStorage.getItem(STORAGE_KEY)
    } catch {
      return null
    }
  },

  set(token: string): void {
    try {
      window.localStorage.setItem(STORAGE_KEY, token)
    } catch {
      /* Session simply will not survive a refresh. */
    }
  },

  clear(): void {
    try {
      window.localStorage.removeItem(STORAGE_KEY)
    } catch {
      /* Nothing to clear. */
    }
  },
}
