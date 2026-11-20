import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import { loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const productionApiOrigin = (env.VITE_API_ORIGIN || 'https://api.s8n.ir').replace(/\/$/, '')
  const devApiTarget = (env.VITE_API_TARGET || 'http://127.0.0.1:8000').replace(/\/$/, '')
  // Fallback only for bare localhost / 127.0.0.1. Subdomain hosts like
  // demo.localhost and clinic1.localhost must be forwarded as-is so tenancy
  // resolves to the matching tenant DB instead of always hitting clinic1.
  const devTenantHostFallback = (env.VITE_TENANT_HOST || 'clinic1.localhost').trim()

  const resolveDevTenantHost = (req) => {
    const raw = String(req.headers.host || '').split(',')[0].trim()
    const hostname = raw.split(':')[0].toLowerCase()
    if (!hostname || hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]' || hostname === '::1') {
      return devTenantHostFallback
    }
    return hostname
  }

  const withTenantHostProxy = (extra = {}) => ({
    target: devApiTarget,
    changeOrigin: false,
    configure: (proxy) => {
      proxy.on('proxyReq', (proxyReq, req) => {
        proxyReq.setHeader('host', resolveDevTenantHost(req))
      })
    },
    ...extra,
  })

  return {
  plugins: [
    vue(),
    vueDevTools(),
    mode === 'production' && {
      name: 'production-same-origin-api',
      enforce: 'pre',
      transform(code, id) {
        if (!id.includes('/src/') && !id.includes('\\src\\')) return null
        return code.includes('http://127.0.0.1:8000')
          ? code.replaceAll('http://127.0.0.1:8000', productionApiOrigin)
          : null
      },
    },
  ].filter(Boolean),
  server: {
    host: true,
    proxy: {
      '/api': withTenantHostProxy(),
      '/external-api': withTenantHostProxy(),
      '/central-api': {
        target: devApiTarget,
        // Central routes are registered only on the central domain. Preserve
        // that host when the SPA proxies requests to the local Laravel port.
        changeOrigin: false,
        headers: { host: 'localhost' },
      },
      '/storage': {
        target: devApiTarget,
        changeOrigin: true,
      },
      '/tenant-files': withTenantHostProxy(),
      '/csrf-cookie': withTenantHostProxy(),
      '/login': withTenantHostProxy(),
      '/quick-login': withTenantHostProxy(),
      '/logout': withTenantHostProxy(),
      '/broadcasting/auth': withTenantHostProxy(),
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  }
})
