import { reactive, ref } from 'vue'
import axios from 'axios'
import { isoToJdn, jdnToIso, todayJdn, deadlineTs, j2d, d2j } from './jalali'

export const PALETTE = [
  { c: '#15803d', fg: '#fff' }, { c: '#eab308', fg: '#422006' }, { c: '#7c3aed', fg: '#fff' }, { c: '#b91c1c', fg: '#fff' },
  { c: '#2563eb', fg: '#fff' }, { c: '#ea580c', fg: '#fff' }, { c: '#0f766e', fg: '#fff' }, { c: '#db2777', fg: '#fff' },
]

const state = reactive({
  staff: [],
  statuses: [],
  expiryDefault: null,
  tasks: [],
  /** @type {Map<number, { title: string, holiday: boolean }>} keyed by JDN */
  holidays: new Map(),
  canManage: true,
  canAdmin: false,
  currentUserId: null,
  /** Manager-only: empty = all staff; otherwise show tasks assigned to any selected staff id. */
  staffFilterIds: [],
  loading: false,
  loaded: false,
  error: '',
})

export function taskMatchesStaffFilter(task, staffFilterIds = state.staffFilterIds) {
  if (!staffFilterIds?.length) return true
  const ids = (task.staffIds || task.assignee_ids || task.assignees?.map(a => a.id) || []).map(Number)
  return ids.some(id => staffFilterIds.map(Number).includes(id))
}

export function toggleStaffFilterId(id) {
  const nid = Number(id)
  const i = state.staffFilterIds.findIndex(x => Number(x) === nid)
  if (i >= 0) state.staffFilterIds.splice(i, 1)
  else state.staffFilterIds.push(nid)
}

export function clearStaffFilter() {
  state.staffFilterIds.splice(0, state.staffFilterIds.length)
}

const holidayMonthCache = new Map()
const openTaskId = ref(null)

/** Same source as Time.vue booking: GET /api/calendar/events?year=&month= (Jalali). */
export async function loadHolidayMonth(jy, jm) {
  const key = `${jy}-${String(jm).padStart(2, '0')}`
  if (holidayMonthCache.has(key)) {
    const cached = holidayMonthCache.get(key)
    cached.forEach((info, jdn) => state.holidays.set(jdn, info))
    return cached
  }
  try {
    const { data } = await axios.get('/api/calendar/events', { params: { year: jy, month: jm } })
    const map = new Map()
    ;(data.events || []).forEach((item) => {
      const day = Number(item.day)
      if (!day) return
      const jdn = j2d(jy, jm, day)
      const info = { title: item.title || '', holiday: item.holiday === true }
      map.set(jdn, info)
      state.holidays.set(jdn, info)
    })
    holidayMonthCache.set(key, map)
    return map
  } catch {
    const empty = new Map()
    holidayMonthCache.set(key, empty)
    return empty
  }
}

/** Load holiday months covering a JDN range (and neighbors). */
export async function loadHolidaysForRange(fromJdn, toJdn) {
  const months = new Set()
  for (let d = fromJdn; d <= toJdn; d += 1) {
    const [y, m] = d2j(d)
    months.add(`${y}-${m}`)
  }
  // Also load adjacent months so week view edges stay correct.
  const [y0, m0] = d2j(fromJdn - 7)
  const [y1, m1] = d2j(toJdn + 7)
  months.add(`${y0}-${m0}`)
  months.add(`${y1}-${m1}`)
  await Promise.all([...months].map((key) => {
    const [y, m] = key.split('-').map(Number)
    return loadHolidayMonth(y, m)
  }))
}

export function holidayInfo(jdn) {
  return state.holidays.get(jdn) || null
}

function normalizeTask(raw) {
  if (!raw) return null
  const day = raw.task_date ? isoToJdn(raw.task_date) : (raw.day || todayJdn())
  const expiryDay = raw.expiry?.date ? isoToJdn(raw.expiry.date) : (raw.expiry?.day || day)
  return {
    ...raw,
    day,
    staffIds: (raw.assignee_ids || raw.assignees?.map(a => a.id) || raw.staffIds || []).map(Number),
    statusId: raw.status_id ?? raw.statusId ?? null,
    auto: !!raw.auto_expired || !!raw.auto,
    expiry: {
      enabled: !!raw.expiry?.enabled,
      day: expiryDay,
      time: raw.expiry?.time || '18:00',
      statusId: raw.expiry?.status_id ?? raw.expiry?.statusId ?? state.expiryDefault,
    },
  }
}

function upsertTask(task) {
  const n = normalizeTask(task)
  const i = state.tasks.findIndex(t => t.id === n.id)
  if (i >= 0) state.tasks.splice(i, 1, n)
  else state.tasks.push(n)
  return n
}

export const statusById = id => state.statuses.find(s => s.id === id || String(s.id) === String(id))
export const effectOf = st => (!st || st.effect === 'none' ? 0 : st.effect === 'plus' ? +st.amount || 0 : -(+st.amount || 0))
export const staffById = id => state.staff.find(s => s.id === id || String(s.id) === String(id))

export async function bootstrap() {
  state.loading = true
  state.error = ''
  try {
    const { data } = await axios.get('/api/staff-tasks/bootstrap')
    state.staff = data.staff || []
    state.statuses = data.statuses || []
    state.expiryDefault = data.settings?.expiry_default_status_id ?? null
    state.canManage = data.can_manage !== false
    state.canAdmin = !!data.can_admin
    state.currentUserId = data.current_user_id ?? null
    state.loaded = true
  } catch (e) {
    state.error = e.response?.data?.message || 'بارگذاری وظایف ممکن نشد.'
    throw e
  } finally {
    state.loading = false
  }
}

export async function loadTasks({ from, to, statusId, staffId } = {}) {
  const params = {}
  if (from != null) params.from = typeof from === 'number' ? jdnToIso(from) : from
  if (to != null) params.to = typeof to === 'number' ? jdnToIso(to) : to
  if (statusId !== undefined && statusId !== null && statusId !== '') params.status_id = statusId
  if (staffId) params.staff_id = staffId
  const { data } = await axios.get('/api/staff-tasks', { params })
  state.tasks = (data.tasks || []).map(normalizeTask)
  return state.tasks
}

export async function saveTask(draft, { image, attachments, voice, voiceDuration, removeImage } = {}) {
  const fd = new FormData()
  fd.append('title', (draft.title || '').trim())
  if (draft.note != null) fd.append('note', draft.note)
  fd.append('task_date', jdnToIso(draft.day))
  fd.append('priority', draft.priority || 'normal')
  if (draft.progress != null) fd.append('progress', String(draft.progress))
  const ids = draft.staffIds?.length ? draft.staffIds : (draft.staffId ? [draft.staffId] : [])
  ids.forEach((id, i) => fd.append(`assignee_ids[${i}]`, id))
  fd.append('expiry[enabled]', draft.expiry?.enabled ? '1' : '0')
  if (draft.expiry?.enabled) {
    fd.append('expiry[date]', jdnToIso(draft.expiry.day || draft.day))
    fd.append('expiry[time]', draft.expiry.time || '18:00')
    if (draft.expiry.statusId) fd.append('expiry[status_id]', draft.expiry.statusId)
  }
  if (image) fd.append('image', image)
  if (removeImage) fd.append('remove_image', '1')
  ;(attachments || []).forEach(file => fd.append('attachments[]', file))
  if (voice) {
    fd.append('voice', voice)
    if (voiceDuration) fd.append('voice_duration', String(voiceDuration))
  }

  const { data } = draft.id
    ? await axios.post(`/api/staff-tasks/${draft.id}`, fd)
    : await axios.post('/api/staff-tasks', fd)
  return upsertTask(data.task)
}

export async function changeStatus(taskId, statusId, progress) {
  const { data } = await axios.patch(`/api/staff-tasks/${taskId}/status`, {
    status_id: statusId || null,
    progress,
  })
  return upsertTask(data.task)
}

export function canDeleteTask(task) {
  if (!task) return false
  if (task.can_delete != null) return !!task.can_delete
  if (state.canAdmin) return true
  return state.currentUserId != null && Number(task.created_by) === Number(state.currentUserId)
}

export function canDeleteMediaItem(task, item) {
  if (!task || !item) return false
  if (item.can_delete != null) return !!item.can_delete
  if (task.can_delete_media || canDeleteTask(task)) return true
  return state.currentUserId != null && Number(item.user_id) === Number(state.currentUserId)
}

export async function deleteTask(id) {
  await axios.delete(`/api/staff-tasks/${id}`)
  state.tasks = state.tasks.filter(t => t.id !== id)
}

export async function deleteAttachment(taskId, attachmentId) {
  const { data } = await axios.delete(`/api/staff-tasks/${taskId}/attachments/${attachmentId}`)
  return upsertTask(data.task)
}

export async function deleteVoice(taskId, voiceId) {
  const { data } = await axios.delete(`/api/staff-tasks/${taskId}/voices/${voiceId}`)
  return upsertTask(data.task)
}

export async function deleteTaskImage(taskId) {
  const { data } = await axios.delete(`/api/staff-tasks/${taskId}/image`)
  return upsertTask(data.task)
}

export async function addVoice(taskId, file, duration) {
  const fd = new FormData()
  fd.append('voice', file)
  if (duration) fd.append('duration', String(duration))
  const { data } = await axios.post(`/api/staff-tasks/${taskId}/voices`, fd)
  if (data.task) upsertTask(data.task)
  return data.voice
}

export async function addStatus(payload = {}) {
  const { data } = await axios.post('/api/staff-tasks/statuses', {
    title: payload.title || 'وضعیت جدید',
    color: payload.color ?? state.statuses.length % PALETTE.length,
    effect: payload.effect || 'none',
    amount: payload.amount || 0,
  })
  state.statuses.push(data.status)
  return data.status
}

export async function updateStatus(status) {
  const { data } = await axios.patch(`/api/staff-tasks/statuses/${status.id}`, {
    title: status.title,
    color: status.color,
    effect: status.effect,
    amount: status.amount,
  })
  const i = state.statuses.findIndex(s => s.id === status.id)
  if (i >= 0) state.statuses.splice(i, 1, data.status)
  return data.status
}

export async function removeStatus(id) {
  await axios.delete(`/api/staff-tasks/statuses/${id}`)
  state.statuses = state.statuses.filter(s => s.id !== id)
  if (state.expiryDefault === id) state.expiryDefault = null
  state.tasks.forEach(t => {
    if (t.statusId === id) t.statusId = null
    if (t.expiry?.statusId === id) t.expiry.statusId = null
  })
}

export async function saveSettings(expiryDefault) {
  const { data } = await axios.patch('/api/staff-tasks/settings', {
    expiry_default_status_id: expiryDefault,
  })
  state.expiryDefault = data.settings?.expiry_default_status_id ?? null
}

export async function fetchReport(period = 'monthly', date) {
  const params = { period }
  if (date) params.date = typeof date === 'number' ? jdnToIso(date) : date
  const { data } = await axios.get('/api/staff-tasks/report', { params })
  return data
}

export function runExpiryLocal() {
  const now = Date.now()
  state.tasks.forEach(t => {
    if (!t.statusId && t.expiry?.enabled && statusById(t.expiry.statusId) && deadlineTs(t.expiry.day, t.expiry.time) <= now) {
      t.statusId = t.expiry.statusId
      t.auto = true
    }
  })
}

export function useStaffTasks() {
  return {
    state,
    openTaskId,
    bootstrap,
    loadTasks,
    loadHolidayMonth,
    loadHolidaysForRange,
    holidayInfo,
    saveTask,
    changeStatus,
    deleteTask,
    deleteAttachment,
    deleteVoice,
    deleteTaskImage,
    canDeleteTask,
    canDeleteMediaItem,
    taskMatchesStaffFilter,
    toggleStaffFilterId,
    clearStaffFilter,
    addVoice,
    addStatus,
    updateStatus,
    removeStatus,
    saveSettings,
    fetchReport,
    runExpiry: runExpiryLocal,
    statusById,
    effectOf,
    staffById,
    normalizeTask,
    upsertTask,
  }
}
