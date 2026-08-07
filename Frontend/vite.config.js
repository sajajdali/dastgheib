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
        headers: { host: 'clinic1.localhost' },
      },
      '/central-api': {
        target: devApiTarget,
        changeOrigin: true,
      },
      '/storage': {
        target: devApiTarget,
        changeOrigin: true,
      },
      '/csrf-cookie': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: 'clinic1.localhost' },
      },
      '/login': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: 'clinic1.localhost' },
      },
      '/logout': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: 'clinic1.localhost' },
      },
      '/broadcasting/auth': {
        target: devApiTarget,
        changeOrigin: false,
        headers: { host: 'clinic1.localhost' },
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
