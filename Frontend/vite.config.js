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
        changeOrigin: true,
      },
      '/sanctum': {
        target: devApiTarget,
        changeOrigin: true,
      },
      '/login': {
        target: devApiTarget,
        changeOrigin: true,
      },
      '/logout': {
        target: devApiTarget,
        changeOrigin: true,
      },
      '/broadcasting/auth': {
        target: devApiTarget,
        changeOrigin: true,
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
