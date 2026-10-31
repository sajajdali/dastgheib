// Shared store for incoming calls (Vue 3). Single instance across the app.
import { ref, computed } from 'vue'

const calls = ref([])
const now = ref(Date.now())
let ticker = null

function ensureTicker() {
  if (!ticker) ticker = setInterval(() => { now.value = Date.now() }, 1000)
}

/**
 * Call shape:
 * {
 *   id: string|number,              // unique call id from PBX
 *   phone: string,
 *   patient: null | {                // null => unknown number
 *     id, name, fileNo,
 *     appointmentsCount: number,
 *     nextAppointment: string        // e.g. 'پنج‌شنبه ۲۵ آبان'
 *   },
 *   callsToday: number,
 *   callsTotal: number,
 *   stage: 'ivr' | 'queue' | 'talking' | 'survey' | 'ended',
 *   startedAt: number,               // ms timestamp
 *   connectedAt?: number|null,       // set when operator answers
 *   talkEndedAt?: number|null,       // set when conversation ends (survey/ended)
 *   queueName?: string, queuePosition?: number,
 *   agentName?: string, extension?: string
 * }
 */
function upsertCall(evt) {
  ensureTicker()
  const t = Date.now()
  const i = calls.value.findIndex(c => c.id === evt.id)
  const prev = i >= 0 ? calls.value[i] : { startedAt: t, connectedAt: null, talkEndedAt: null, callsToday: 1, callsTotal: 1 }
  const next = { ...prev, ...evt }
  if (next.stage === 'talking' && !next.connectedAt) next.connectedAt = evt.connectedAt ?? t
  if ((next.stage === 'survey' || next.stage === 'ended') && next.connectedAt && !next.talkEndedAt) next.talkEndedAt = evt.talkEndedAt ?? t
  if (next.stage === 'ended') { removeCall(next.id); return }
  if (i >= 0) calls.value.splice(i, 1, next)
  else calls.value.push(next)
}

function removeCall(id) {
  calls.value = calls.value.filter(c => c.id !== id)
}

function clearAll() { calls.value = [] }

function dispose() {
  clearAll()
  if (ticker) clearInterval(ticker)
  ticker = null
}

export function useIncomingCalls() {
  ensureTicker()
  return {
    calls: computed(() => calls.value),
    now: computed(() => now.value),
    upsertCall,
    removeCall,
    clearAll,
    dispose,
  }
}
