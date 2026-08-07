import { createApp } from 'vue'
import App from './App.vue'
import axios from 'axios'
import './scss/main.scss'

const backendPaths = ['/api', '/central-api', '/csrf-cookie', '/login', '/logout', '/broadcasting/auth']

const isBackendUrl = (url) => {
  const value = String(url || '')
  if (!/^https?:\/\//i.test(value)) {
    return backendPaths.some((path) => value === path || value.startsWith(`${path}/`))
  }

  try {
    const parsed = new URL(value)
    return parsed.origin === window.location.origin
      && backendPaths.some((path) => parsed.pathname === path || parsed.pathname.startsWith(`${path}/`))
  } catch {
    return false
  }
}

const getCookie = (name) => {
  return document.cookie
    .split('; ')
    .find((row) => row.startsWith(`${name}=`))
    ?.split('=')
    .slice(1)
    .join('=')
}

axios.interceptors.request.use((config) => {
  const url = String(config.url || '')
  if (isBackendUrl(url)) {
    config.withCredentials = true
    config.withXSRFToken = true
    config.headers.Accept = 'application/json'
  }
  return config
})

let authExpiredEventSent = false
const notifyAuthExpired = () => {
  if (window.__intentionalLogout || authExpiredEventSent) return
  authExpiredEventSent = true
  window.dispatchEvent(new CustomEvent('app:auth-expired'))
  window.setTimeout(() => { authExpiredEventSent = false }, 3000)
}

axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const url = String(error.config?.url || '')
    const isAuthCheck = url.includes('/api/auth/user') || url.endsWith('/login') || url.endsWith('/logout')
    if ([401, 419].includes(status) && !isAuthCheck) {
      error.isAuthExpired = true
      notifyAuthExpired()
    }
    return Promise.reject(error)
  },
)

const nativeFetch = window.fetch.bind(window)
window.fetch = async (input, init = {}) => {
  const url = typeof input === 'string' ? input : input.url
  if (!isBackendUrl(url)) return nativeFetch(input, init)

  const method = String(init.method || 'GET').toUpperCase()
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && !getCookie('XSRF-TOKEN')) {
    await nativeFetch('/csrf-cookie', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
  }

  const headers = {
    Accept: 'application/json',
    ...(init.headers || {}),
  }

  const xsrfToken = getCookie('XSRF-TOKEN')
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken)
  }

  const response = await nativeFetch(input, {
    ...init,
    credentials: init.credentials || 'include',
    headers,
  })
  if ([401, 419].includes(response.status) && !String(url).includes('/api/auth/user')) notifyAuthExpired()
  return response
}

createApp(App).mount('#app')
