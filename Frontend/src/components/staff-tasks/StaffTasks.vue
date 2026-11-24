<template>
  <div class="stc" dir="rtl">
    <div v-if="state.error" class="err-banner">{{ state.error }} <button type="button" @click="init">تلاش دوباره</button></div>
    <div v-if="state.loading && !state.loaded" class="loading">در حال بارگذاری…</div>

    <template v-else>
      <!-- نوار باریک یک‌ردیفه -->
      <div class="panel toolbar">
        <div class="tb">
          <button type="button" class="icon-btn primary" title="افزودن وظیفه" @click="openNew(today)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          </button>
          <button type="button" class="icon-btn" title="وضعیت‌ها" @click="showSettings = true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          </button>
          <button type="button" class="icon-btn" title="گزارش مدیریتی" @click="showReport = true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19V5M4 19h16M8 15v4M12 11v8M16 7v12"/></svg>
          </button>
          <button type="button" class="icon-btn" title="خروجی اکسل" @click="exportCsv">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
          </button>

          <button type="button" class="ghost-sm" @click="goToday">امروز</button>

          <select class="msel" :value="vm" @change="anchor = j2d(vy, +$event.target.value, 1); refresh()">
            <option v-for="(n, i) in MONTHS" :key="i" :value="i + 1">{{ n }}</option>
          </select>
          <select class="msel" :value="vy" @change="anchor = j2d(+$event.target.value, vm, 1); refresh()">
            <option v-for="y in [vy - 2, vy - 1, vy, vy + 1, vy + 2]" :key="y" :value="y">{{ fa(y) }}</option>
          </select>

          <div class="navs">
            <button type="button" class="nav" title="قبلی" @click="move(-1)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg></button>
            <button type="button" class="nav" title="بعدی" @click="move(1)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg></button>
          </div>

          <div class="zoom">
            <button type="button" class="nav" title="کوچک‌نمایی" @click="zoomOut">−</button>
            <button type="button" class="nav" title="بزرگ‌نمایی" @click="zoomIn">+</button>
          </div>

          <div class="seg">
            <button type="button" :class="{ on: view === 'week' }" @click="view = 'week'">هفته</button>
            <button type="button" :class="{ on: view === 'month' }" @click="view = 'month'">ماه</button>
          </div>

          <div class="filters">
            <button type="button" class="fchip" :class="{ on: statusFilter === null }" @click="statusFilter = null">همه</button>
            <button v-for="s in state.statuses" :key="s.id" type="button" class="fchip" :class="{ on: statusFilter === s.id }" :style="statusFilter === s.id ? { background: PALETTE[s.color].c, color: PALETTE[s.color].fg, borderColor: PALETTE[s.color].c } : {}" @click="statusFilter = statusFilter === s.id ? null : s.id">
              <i :style="{ background: PALETTE[s.color].c }"></i>{{ s.title }}
            </button>
          </div>
        </div>
      </div>

      <!-- نمای هفته -->
      <div
        v-if="view === 'week'"
        class="panel cal"
        :class="'zoom-' + zoom"
        @pointerdown="onSwipeStart"
        @pointermove="onSwipeMove"
        @pointerup="onSwipeEnd"
        @pointercancel="onSwipeEnd"
      >
        <div class="week" :style="weekStyle">
          <div v-for="d in weekDays" :key="d.day" class="wcol" :class="{ holiday: d.holiday, today: d.today }">
            <div class="whead">
              <span class="wname" :class="{ today: d.today }">{{ WEEKDAYS[weekday(d.day)] }}</span>
              <span class="wnum" :class="{ today: d.today }">{{ fa(d.jd) }}</span>
              <button type="button" class="add-ico" title="افزودن" @click="openNew(d.day)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              </button>
            </div>
            <div class="wbody">
              <div v-for="t in d.tasks" :key="t.id" class="tcard" :style="cardStyle(t)" @click="openDetails(t)">
                <div class="tt">{{ t.title }}</div>
                <div class="avs">
                  <span v-for="a in assigneesOf(t)" :key="a.id" class="av sm" :title="a.name" :style="avStyle(a)">
                    <img v-if="a.avatar_url" :src="a.avatar_url" alt="" />
                    <template v-else>{{ (a.name || '?').charAt(0) }}</template>
                  </span>
                </div>
                <div class="trow">
                  <div class="status-wrap" @click.stop>
                    <button type="button" class="pill" :style="pillStyle(t)" @click="toggleStatusMenu(t.id)">
                      {{ statusById(t.statusId)?.title || 'بدون وضعیت' }}
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div v-if="statusMenu === t.id" class="status-menu">
                      <button type="button" @click="setStatus(t, null)">بدون وضعیت</button>
                      <button v-for="s in state.statuses" :key="s.id" type="button" @click="setStatus(t, s.id)">
                        <i :style="{ background: PALETTE[s.color].c }"></i>{{ s.title }}
                      </button>
                    </div>
                  </div>
                  <button type="button" class="edit-ico" title="ویرایش" @click.stop="openEdit(t)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                  </button>
                </div>
                <div class="tdead">ثبت‌شده در تاریخ {{ registeredLabel(t) }}</div>
                <div v-if="t.voices?.length" class="voice-mini" @click.stop>
                  <audio :src="t.voices[t.voices.length - 1].url" controls preload="none"></audio>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- نمای ماه -->
      <div v-else class="panel cal" :class="'zoom-' + zoom">
        <div class="month" :style="monthStyle">
          <div v-for="i in [6, 0, 1, 2, 3, 4, 5]" :key="'h' + i" class="mh" :class="{ red: i === 5 }">{{ WEEKDAYS[i] }}</div>
          <div v-for="c in monthCells" :key="c.day" class="mcell" :class="{ holiday: c.holiday, out: !c.inMonth }">
            <div class="mc-top">
              <span class="mnum" :class="{ today: c.today }">{{ fa(c.jd) }}</span>
              <button type="button" class="add-ico sm" title="افزودن" @click="openNew(c.day)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              </button>
            </div>
            <div v-for="t in c.tasks.slice(0, zoom >= 2 ? 5 : 3)" :key="t.id" class="mtask" :style="cardStyle(t)" @click="openDetails(t)">{{ t.title }}</div>
            <span v-if="c.tasks.length > (zoom >= 2 ? 5 : 3)" class="more">{{ fa(c.tasks.length - (zoom >= 2 ? 5 : 3)) }}+</span>
          </div>
        </div>
      </div>
    </template>

    <Teleport to="body">
      <div v-if="editing || details || showSettings || showReport" class="st-layer" dir="rtl">
        <TaskModal v-if="editing" :task="editing" @close="editing = null" @save="onSave" @delete="onDelete" />
        <StatusSettings v-if="showSettings" @close="showSettings = false" />
        <TaskReport v-if="showReport" @close="showReport = false" @open="id => openDetailsById(id)" @edit="id => openEditById(id)" />
        <div v-if="details" class="st-overlay" @click.self="details = null">
          <div class="detail-modal">
            <div class="st-mhead">
              <div class="col"><span class="h">{{ details.title }}</span><span class="sub">ثبت‌شده در تاریخ {{ registeredLabel(details) }}</span></div>
              <div class="row">
                <button type="button" class="icon-btn" title="ویرایش" @click="openEdit(details); details = null"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>
                <button type="button" class="st-x" @click="details = null"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
              </div>
            </div>
            <div class="detail-body">
              <p v-if="details.note">{{ details.note }}</p>
              <div class="avs">
                <span v-for="a in assigneesOf(details)" :key="a.id" class="av" :style="avStyle(a)" :title="a.name">
                  <img v-if="a.avatar_url" :src="a.avatar_url" alt="" /><template v-else>{{ a.name.charAt(0) }}</template>
                </span>
              </div>
              <div class="status-wrap">
                <button type="button" class="pill" :style="pillStyle(details)" @click="toggleStatusMenu('d'+details.id)">
                  {{ statusById(details.statusId)?.title || 'بدون وضعیت' }}
                </button>
                <div v-if="statusMenu === 'd'+details.id" class="status-menu">
                  <button type="button" @click="setStatus(details, null)">بدون وضعیت</button>
                  <button v-for="s in state.statuses" :key="s.id" type="button" @click="setStatus(details, s.id)"><i :style="{ background: PALETTE[s.color].c }"></i>{{ s.title }}</button>
                </div>
              </div>
              <div v-if="details.expiry?.enabled" class="meta">مهلت: {{ shortDate(details.expiry.day) }} · {{ fa(details.expiry.time) }}</div>
              <img v-if="details.image_url" class="dimg" :src="details.image_url" alt="" />
              <div class="voice-thread">
                <div v-for="v in (details.voices || [])" :key="v.id" class="vmsg">
                  <span class="av sm" :style="uploaderAv(v)"><img v-if="v.uploader_avatar_url" :src="v.uploader_avatar_url" alt="" /><template v-else>{{ (v.uploader_name||'?').charAt(0) }}</template></span>
                  <div>
                    <b>{{ v.uploader_name }}</b>
                    <small>{{ formatTime(v.created_at) }}</small>
                    <audio :src="v.url" controls preload="none"></audio>
                  </div>
                </div>
                <div class="rec-row">
                  <button type="button" class="ghost-sm" :class="{ rec: recording }" @click="toggleDetailRec">{{ recording ? 'توقف ضبط' : 'پیام صوتی' }}</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import TaskModal from './TaskModal.vue'
import StatusSettings from './StatusSettings.vue'
import TaskReport from './TaskReport.vue'
import { useStaffTasks, PALETTE } from './useStaffTasks'
import { d2j, j2d, monthLength, weekday, weekStart, todayJdn, WEEKDAYS, MONTHS, fa, jdnToIso } from './jalali'

const {
  state, openTaskId, bootstrap, loadTasks, saveTask, changeStatus, deleteTask, addVoice,
  statusById,
} = useStaffTasks()

const today = todayJdn()
const anchor = ref(today)
const view = ref('week')
const zoom = ref(1) // 0 compact more days feel, 1 default, 2 detailed
const statusFilter = ref(null)
const editing = ref(null)
const details = ref(null)
const showSettings = ref(false)
const showReport = ref(false)
const statusMenu = ref(null)
const recording = ref(false)
let mediaRecorder = null
let chunks = []
let swipe = null

const ws = computed(() => weekStart(anchor.value))
const viewYM = computed(() => (view.value === 'week' ? d2j(ws.value + 3) : d2j(anchor.value)))
const vy = computed(() => viewYM.value[0])
const vm = computed(() => viewYM.value[1])

const visibleDays = computed(() => {
  if (view.value !== 'week') return 7
  if (zoom.value <= 0) return 10
  if (zoom.value >= 2) return 5
  return 7
})

const isHoliday = d => weekday(d) === 5 || state.holidays.has(d)
const filteredTasks = computed(() => state.tasks.filter(t => {
  if (statusFilter.value == null) return true
  return t.statusId === statusFilter.value
}))
const byDay = computed(() => {
  const map = {}
  filteredTasks.value.forEach(t => {
    map[t.day] = map[t.day] || []
    map[t.day].push(t)
  })
  return map
})
const dayInfo = day => { const [, jm, jd] = d2j(day); return { day, jm, jd, today: day === today, holiday: isHoliday(day), tasks: byDay.value[day] || [] } }
const weekDays = computed(() => {
  const n = visibleDays.value
  const start = n === 7 ? ws.value : (n < 7 ? ws.value + 1 : ws.value - 1)
  return Array.from({ length: n }, (_, i) => dayInfo(start + i))
})
const monthStart = computed(() => j2d(vy.value, vm.value, 1))
const monthEnd = computed(() => monthStart.value + monthLength(vy.value, vm.value) - 1)
const monthCells = computed(() => {
  const gs = weekStart(monthStart.value)
  const n = Math.ceil((monthEnd.value - gs + 1) / 7) * 7
  return Array.from({ length: n }, (_, i) => { const c = dayInfo(gs + i); c.inMonth = c.jm === vm.value; return c })
})

const weekStyle = computed(() => ({
  gridTemplateColumns: `repeat(${visibleDays.value}, minmax(${zoom.value >= 2 ? 180 : 140}px, 1fr))`,
  minWidth: `${visibleDays.value * (zoom.value >= 2 ? 180 : 140)}px`,
}))
const monthStyle = computed(() => ({
  minWidth: zoom.value >= 2 ? '980px' : zoom.value <= 0 ? '640px' : '840px',
}))

function assigneesOf(t) {
  if (t.assignees?.length) return t.assignees
  return (t.staffIds || []).map(id => state.staff.find(s => s.id === id)).filter(Boolean)
}
function avStyle(a) {
  return a?.avatar_url ? {} : { background: '#64748b' }
}
function uploaderAv(v) {
  return v.uploader_avatar_url ? {} : { background: '#64748b' }
}
const mix = (c, p) => `color-mix(in srgb, ${c} ${p}%, var(--st-surface))`
function cardStyle(t) {
  const st = statusById(t.statusId)
  if (!st) return { background: 'var(--st-surface)', borderColor: 'var(--st-border)' }
  const c = PALETTE[st.color].c
  return { background: mix(c, 16), borderColor: mix(c, 45) }
}
function pillStyle(t) {
  const st = statusById(t.statusId)
  return st ? { background: PALETTE[st.color].c, color: PALETTE[st.color].fg } : { background: 'var(--st-muted-bg)', color: 'var(--st-muted)' }
}
const shortDate = d => { const [, m, dd] = d2j(d); return `${fa(dd)} ${MONTHS[m - 1]}` }
function registeredLabel(t) {
  if (t.registered_at) {
    const d = new Date(t.registered_at)
    const jdn = todayJdn() - Math.round((Date.now() - d.getTime()) / 86400000)
    // Prefer task_date display for consistency
  }
  return shortDate(t.day)
}
function formatTime(v) { return v ? new Date(v).toLocaleString('fa-IR') : '' }

function move(k) {
  if (view.value === 'week') { anchor.value += visibleDays.value * k; refresh(); return }
  let y = vy.value, m = vm.value + k
  if (m > 12) { m = 1; y++ }
  if (m < 1) { m = 12; y-- }
  anchor.value = j2d(y, m, 1)
  refresh()
}
function goToday() { anchor.value = today; refresh() }
function zoomIn() { zoom.value = Math.min(2, zoom.value + 1) }
function zoomOut() { zoom.value = Math.max(0, zoom.value - 1) }

function onSwipeStart(e) {
  if (e.pointerType === 'mouse' && e.button !== 0) return
  swipe = { x: e.clientX, id: e.pointerId, moved: false }
  e.currentTarget.setPointerCapture?.(e.pointerId)
}
function onSwipeMove(e) {
  if (!swipe || swipe.id !== e.pointerId) return
  if (Math.abs(e.clientX - swipe.x) > 40) swipe.moved = true
}
function onSwipeEnd(e) {
  if (!swipe || swipe.id !== e.pointerId) return
  const dx = e.clientX - swipe.x
  swipe = null
  if (Math.abs(dx) < 60) return
  // RTL: drag right (positive dx) → previous week; left → next
  move(dx > 0 ? -1 : 1)
}

function openNew(day) {
  editing.value = {
    id: null, title: '', note: '', day, priority: 'normal', progress: 0,
    staffIds: [], statusId: null, auto: false,
    expiry: { enabled: false, day, time: '18:00', statusId: state.expiryDefault },
    attachments: [], voices: [],
  }
}
const openEdit = t => { editing.value = { ...t, staffIds: [...(t.staffIds || [])], expiry: { ...t.expiry } } }
function openDetails(t) { details.value = t; statusMenu.value = null }
async function fetchTask(id) {
  let t = state.tasks.find(x => x.id === id)
  if (t) return t
  const axios = (await import('axios')).default
  const { data } = await axios.get(`/api/staff-tasks/${id}`)
  const { normalizeTask, upsertTask } = useStaffTasks()
  return upsertTask(normalizeTask(data.task))
}
async function openDetailsById(id) {
  showReport.value = false
  try { openDetails(await fetchTask(id)) } catch { /* ignore */ }
}
async function openEditById(id) {
  showReport.value = false
  try { openEdit(await fetchTask(id)) } catch { /* ignore */ }
}
function toggleStatusMenu(id) { statusMenu.value = statusMenu.value === id ? null : id }

async function setStatus(t, statusId) {
  statusMenu.value = null
  const updated = await changeStatus(t.id, statusId)
  if (details.value?.id === t.id) details.value = updated
}
async function onSave({ draft, image, attachments, voice, voiceDuration, removeImage }) {
  const saved = await saveTask(draft, { image, attachments, voice, voiceDuration, removeImage })
  if (draft.statusId !== undefined && draft.id && draft.statusId !== saved.statusId) {
    await changeStatus(saved.id, draft.statusId)
  }
  editing.value = null
  await refresh()
}
async function onDelete(id) {
  await deleteTask(id)
  editing.value = null
  if (details.value?.id === id) details.value = null
}

async function toggleDetailRec() {
  if (!details.value) return
  if (recording.value) { mediaRecorder?.stop(); return }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    chunks = []
    const started = Date.now()
    mediaRecorder = new MediaRecorder(stream)
    mediaRecorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data) }
    mediaRecorder.onstop = async () => {
      const file = new File([new Blob(chunks, { type: 'audio/webm' })], `voice-${Date.now()}.webm`, { type: 'audio/webm' })
      const updated = await addVoice(details.value.id, file, Math.round((Date.now() - started) / 1000))
      // addVoice upserts task; refresh details
      details.value = state.tasks.find(t => t.id === details.value.id) || details.value
      stream.getTracks().forEach(t => t.stop())
      recording.value = false
      void updated
    }
    mediaRecorder.start()
    recording.value = true
  } catch {
    alert('دسترسی میکروفن داده نشد.')
  }
}

function exportCsv() {
  const rows = [['عنوان', 'مسئولان', 'تاریخ', 'وضعیت', 'اولویت']]
  filteredTasks.value.forEach(t => {
    rows.push([
      t.title,
      assigneesOf(t).map(a => a.name).join('، '),
      jdnToIso(t.day),
      statusById(t.statusId)?.title || '',
      t.priority || '',
    ])
  })
  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `staff-tasks-${jdnToIso(today)}.csv`
  a.click()
}

async function refresh() {
  const from = view.value === 'week' ? ws.value - 2 : weekStart(monthStart.value)
  const to = view.value === 'week' ? ws.value + 14 : monthEnd.value + 7
  await loadTasks({ from, to })
}

async function init() {
  await bootstrap()
  await refresh()
  if (openTaskId.value) {
    const t = state.tasks.find(x => x.id === openTaskId.value)
    if (t) openDetails(t)
    else {
      try {
        const { data } = await (await import('axios')).default.get(`/api/staff-tasks/${openTaskId.value}`)
        const { normalizeTask, upsertTask } = useStaffTasks()
        openDetails(upsertTask(normalizeTask(data.task)))
      } catch { /* ignore */ }
    }
    openTaskId.value = null
  }
}

watch(statusFilter, () => { /* client-side filter already */ })

let timer
let onDocClick
onMounted(() => {
  init()
  timer = setInterval(() => refresh(), 60000)
  onDocClick = () => { statusMenu.value = null }
  document.addEventListener('click', onDocClick)
  window.addEventListener('app:open-staff-task', onOpenTaskEvent)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  document.removeEventListener('click', onDocClick)
  window.removeEventListener('app:open-staff-task', onOpenTaskEvent)
  if (recording.value) mediaRecorder?.stop()
})
function onOpenTaskEvent(e) {
  const id = e.detail?.taskId
  if (id) { openTaskId.value = id; init() }
}
</script>

<style scoped>
.stc,.st-layer{
  --st-surface:#fff;--st-surface-2:#f8fafc;--st-muted-bg:#f1f5f9;--st-border:#e2e8f0;--st-strong-border:#cbd5e1;--st-line:#eef2f7;
  --st-text:#0f172a;--st-text-2:#334155;--st-text-3:#475569;--st-muted:#64748b;--st-faint:#94a3b8;
  --st-accent:#2563eb;--st-accent-hover:#1d4ed8;--st-accent-soft:#eff4ff;--st-hover:rgba(37,99,235,.04);
  --st-red:#dc2626;--st-holiday:#fef2f2;--st-holiday-badge:#fee2e2;--st-pos:#15803d;--st-neg:#b91c1c;
  --st-overlay:rgba(15,23,42,.35);--st-shadow:0 2px 10px rgba(15,23,42,.05)
}
.st-layer{color:var(--st-text);font-family:inherit}
.stc{display:flex;flex-direction:column;gap:10px;color:var(--st-text);font-family:inherit}
.dark .stc,.dark .st-layer{
  --st-surface:#172033;--st-surface-2:#111a2b;--st-muted-bg:#1e293b;--st-border:#334155;--st-strong-border:#475569;--st-line:#243044;
  --st-text:#f8fafc;--st-text-2:#e2e8f0;--st-text-3:#cbd5e1;--st-muted:#94a3b8;--st-faint:#64748b;
  --st-accent:#3b82f6;--st-accent-hover:#2563eb;--st-accent-soft:rgba(59,130,246,.14);--st-hover:rgba(59,130,246,.08);
  --st-red:#f87171;--st-holiday:rgba(220,38,38,.10);--st-holiday-badge:rgba(220,38,38,.22);--st-pos:#4ade80;--st-neg:#f87171;
  --st-overlay:rgba(2,6,23,.6);--st-shadow:0 2px 10px rgba(0,0,0,.25)
}
.panel{background:var(--st-surface);border-radius:16px;box-shadow:var(--st-shadow)}
.toolbar{padding:8px 10px}
.tb{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.icon-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-3);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.icon-btn.primary{background:var(--st-accent);border-color:var(--st-accent);color:#fff}
.icon-btn:hover{border-color:var(--st-accent);color:var(--st-accent)}
.icon-btn.primary:hover{background:var(--st-accent-hover);color:#fff}
.ghost-sm{font-family:inherit;height:34px;border-radius:10px;border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-2);font-size:12px;font-weight:700;padding:0 10px;cursor:pointer}
.ghost-sm.rec{background:var(--st-holiday);color:var(--st-red);border-color:var(--st-red)}
.navs,.zoom{display:flex;gap:2px}
.nav{width:30px;height:30px;border:0;border-radius:8px;background:transparent;color:var(--st-text-3);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700}
.nav:hover{background:var(--st-muted-bg)}
.msel{font-family:inherit;font-size:13px;font-weight:800;color:var(--st-text);border:1px solid var(--st-border);border-radius:9px;padding:5px 8px;background:var(--st-surface);cursor:pointer}
.seg{display:flex;background:var(--st-muted-bg);border-radius:9px;padding:2px;gap:2px}
.seg button{font-family:inherit;border:0;border-radius:7px;padding:5px 11px;font-size:12px;font-weight:700;cursor:pointer;background:transparent;color:var(--st-muted)}
.seg button.on{background:var(--st-surface);color:var(--st-text);box-shadow:0 1px 3px rgba(15,23,42,.12)}
.filters{display:flex;gap:4px;flex-wrap:wrap;margin-right:auto}
.fchip{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface-2);border-radius:999px;padding:4px 9px;font-size:11px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:var(--st-text-3)}
.fchip.on{border-color:var(--st-accent);color:var(--st-accent)}
.fchip i{width:7px;height:7px;border-radius:2px}
.cal{overflow-x:auto;touch-action:pan-y}
.week{display:grid}
.wcol{display:flex;flex-direction:column;border-left:1px solid var(--st-line);background:var(--st-surface);min-height:480px}
.wcol:last-child{border-left:0}
.wcol.holiday{background:var(--st-holiday)}
.whead{padding:8px 10px;border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:6px}
.wname{font-size:12px;font-weight:700;color:var(--st-muted)}
.wname.today{color:var(--st-accent)}
.holiday .wname,.holiday .wnum{color:var(--st-red)}
.wnum{min-width:28px;height:28px;border-radius:8px;font-size:15px;font-weight:800;display:flex;align-items:center;justify-content:center}
.wnum.today{background:var(--st-accent);color:#fff !important}
.add-ico{width:26px;height:26px;border:0;border-radius:8px;background:transparent;color:var(--st-muted);cursor:pointer;display:flex;align-items:center;justify-content:center}
.add-ico:hover{background:var(--st-muted-bg);color:var(--st-accent)}
.add-ico.sm{width:22px;height:22px}
.wbody{flex:1;padding:8px 6px;display:flex;flex-direction:column;gap:5px}
.tcard{cursor:pointer;border:1px solid;border-radius:9px;padding:7px 8px;display:flex;flex-direction:column;gap:5px}
.tcard:hover{box-shadow:0 4px 12px rgba(15,23,42,.1)}
.tt{font-size:12.5px;font-weight:700;line-height:1.45}
.avs{display:flex;gap:3px;flex-wrap:wrap}
.av{width:28px;height:28px;border-radius:50%;overflow:hidden;color:#fff;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#64748b}
.av.sm{width:18px;height:18px;font-size:9px}
.av img{width:100%;height:100%;object-fit:cover}
.trow{display:flex;align-items:center;justify-content:space-between;gap:4px}
.status-wrap{position:relative}
.pill{font-family:inherit;font-size:10px;font-weight:700;border-radius:999px;padding:2px 7px;white-space:nowrap;border:0;cursor:pointer;display:inline-flex;align-items:center;gap:3px;color:inherit}
.status-menu{position:absolute;top:100%;right:0;z-index:5;min-width:140px;background:var(--st-surface);border:1px solid var(--st-border);border-radius:10px;box-shadow:var(--st-shadow);padding:4px;display:flex;flex-direction:column}
.status-menu button{font-family:inherit;border:0;background:transparent;text-align:right;padding:6px 8px;border-radius:7px;cursor:pointer;font-size:12px;font-weight:700;color:var(--st-text-2);display:flex;align-items:center;gap:6px}
.status-menu button:hover{background:var(--st-muted-bg)}
.status-menu i{width:8px;height:8px;border-radius:2px}
.edit-ico{width:26px;height:26px;border:0;border-radius:7px;background:transparent;color:var(--st-muted);cursor:pointer;display:flex;align-items:center;justify-content:center}
.edit-ico:hover{background:var(--st-muted-bg);color:var(--st-accent)}
.tdead{font-size:10.5px;font-weight:600;color:var(--st-muted)}
.voice-mini audio{width:100%;height:28px}
.month{display:grid;grid-template-columns:repeat(7,minmax(100px,1fr))}
.mh{padding:8px 10px;font-size:11px;font-weight:700;color:var(--st-muted);border-bottom:1px solid var(--st-line);border-left:1px solid var(--st-line)}
.mh.red{color:var(--st-red)}
.mcell{min-height:100px;padding:6px;box-sizing:border-box;border-bottom:1px solid var(--st-line);border-left:1px solid var(--st-line);background:var(--st-surface);display:flex;flex-direction:column;gap:3px;min-width:0}
.zoom-2 .mcell{min-height:130px}
.zoom-0 .mcell{min-height:72px}
.mcell.out{background:var(--st-surface-2)}
.mcell.holiday{background:var(--st-holiday)}
.mc-top{display:flex;align-items:center;justify-content:space-between}
.mnum{min-width:22px;height:22px;border-radius:7px;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center}
.mnum.today{background:var(--st-accent);color:#fff !important}
.mtask{border:1px solid;border-radius:6px;padding:2px 5px;font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer}
.more{font-size:10px;font-weight:700;color:var(--st-muted)}
.st-overlay{position:fixed;inset:0;background:var(--st-overlay);z-index:2147483010;display:flex;align-items:center;justify-content:center;padding:16px}
.detail-modal{width:520px;max-width:100%;max-height:calc(100vh - 32px);overflow:auto;background:var(--st-surface);border-radius:16px;box-shadow:0 30px 60px -15px rgba(15,23,42,.35)}
.st-mhead{padding:12px 16px;border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:8px}
.col{display:flex;flex-direction:column;gap:2px}.h{font-size:15px;font-weight:800}.sub{font-size:11px;color:var(--st-muted);font-weight:600}
.st-x{border:0;background:var(--st-muted-bg);width:32px;height:32px;border-radius:9px;cursor:pointer;color:var(--st-muted);display:flex;align-items:center;justify-content:center}
.detail-body{padding:14px 16px;display:flex;flex-direction:column;gap:10px}
.meta{font-size:12px;color:var(--st-muted);font-weight:600}
.dimg{max-width:100%;border-radius:10px}
.voice-thread{display:flex;flex-direction:column;gap:8px}
.vmsg{display:flex;gap:8px;align-items:flex-start;padding:8px;border-radius:10px;background:var(--st-surface-2)}
.vmsg b{font-size:12px;display:block}.vmsg small{font-size:10px;color:var(--st-muted)}
.vmsg audio{width:min(280px,100%);margin-top:4px}
.rec-row{display:flex;gap:6px}
.err-banner,.loading{padding:12px;border-radius:12px;background:var(--st-holiday);color:var(--st-red);font-weight:700}
.loading{background:var(--st-accent-soft);color:var(--st-accent)}
.row{display:flex;align-items:center;gap:6px}
@media (max-width:700px){
  .week{grid-template-columns:1fr !important;min-width:0 !important}
  .wcol{min-height:0;border-left:0;border-bottom:1px solid var(--st-line)}
  .month{grid-template-columns:repeat(7,minmax(0,1fr));min-width:0 !important}
}
</style>
