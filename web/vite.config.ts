import { fileURLToPath, URL } from 'node:url'

import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5173,
      // Fail loudly rather than drifting to the next free port: a second
      // instance on 5174 silently serves a stale config to whoever is still
      // pointed at 5173.
      strictPort: true,
      proxy: {
        // The SPA calls /api on its own origin and Vite forwards it to the
        // backend. Same-origin in development, so no CORS round-trip - and no
        // dependence on whether "localhost" resolves to IPv4 or IPv6, which
        // differs between the two dev servers.
        '/api': {
          target: env.VITE_PROXY_TARGET || 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
  }
})
