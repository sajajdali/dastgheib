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
  const devTenantHost = (env.VITE_TENANT_HOST || 'clinic1.localhost').trim()

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
      '/api': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: devTenantHost },
      },
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
      '/csrf-cookie': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: devTenantHost },
      },
      '/login': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: devTenantHost },
      },
      '/logout': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: devTenantHost },
      },
      '/broadcasting/auth': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: devTenantHost },
      },
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  }
})
