<template>
  <div class="icp-stack" dir="rtl">
    <transition name="icp-fade">
      <div v-if="calls.length > 1" class="icp-head">
        <span>{{ fa(calls.length) }} تماس ورودی</span>
        <button class="icp-head-btn" @click="dismissAll">بستن همه</button>
      </div>
    </transition>

    <transition-group name="icp-pop" tag="div" class="icp-list">
      <div v-for="c in viewCalls" :key="c.id" class="icp-card">
        <!-- Stage + timers -->
        <div class="icp-top">
          <div class="icp-stage" :style="{ color: c.s.color, background: c.s.bg }">
            <span class="icp-dot" :style="{ background: c.s.color }"></span>
            {{ c.s.label }}
          </div>
          <div class="icp-timers">
            <div class="icp-timer">
              <span class="icp-timer-label">مدت انتظار</span>
              <span class="icp-timer-val" dir="ltr" :style="{ color: c.waiting ? '#d97706' : '#64748b' }">{{ c.wait }}</span>
            </div>
            <div class="icp-sep"></div>
            <div class="icp-timer">
              <span class="icp-timer-label">مدت مکالمه</span>
              <span class="icp-timer-val" dir="ltr" :style="{ color: c.talkColor }">{{ c.talk }}</span>
            </div>
          </div>
        </div>

        <!-- Caller -->
        <div class="icp-caller">
          <div class="icp-avatar-wrap">
            <template v-if="c.waiting">
              <span class="icp-ring" :style="{ background: c.s.color }"></span>
              <span class="icp-ring icp-ring2" :style="{ background: c.s.color }"></span>
            </template>
            <span v-else class="icp-ring-static" :style="{ borderColor: c.s.color }"></span>
            <div class="icp-avatar" :class="{ unknown: !c.patient }">{{ c.patient ? (String(c.patient.name || '').charAt(0) || '؟') : '؟' }}</div>
          </div>
          <div class="icp-info">
            <div class="icp-name">{{ c.patient ? c.patient.name : 'شماره ناشناس' }}</div>
            <div class="icp-phone" dir="ltr">{{ c.phone ? fa(c.phone) : 'شماره نامشخص' }}</div>
            <div v-if="!c.patient" class="icp-nofile">پرونده‌ای برای این شماره ثبت نشده</div>
          </div>
        </div>

        <!-- File summary -->
        <div v-if="c.patient" class="icp-file">
          <div class="icp-file-cell">
            <span class="icp-k">شماره پرونده</span>
            <span class="icp-v">{{ fa(c.patient.fileNo) }}</span>
          </div>
          <div class="icp-file-cell">
            <span class="icp-k">نوبت دریافتی</span>
            <span class="icp-v">{{ fa(c.patient.appointmentsCount) }} نوبت</span>
          </div>
          <div class="icp-file-cell grow">
            <span class="icp-k">نوبت بعدی</span>
            <span class="icp-v blue">{{ c.patient.nextAppointment ? fa(c.patient.nextAppointment) : '—' }}</span>
          </div>
        </div>

        <!-- Call counts -->
        <div class="icp-counts">
          <div class="icp-count"><span>تماس امروز</span><b>{{ fa(c.callsToday ?? 1) }}</b></div>
          <div class="icp-count"><span>کل تماس‌ها</span><b>{{ fa(c.callsTotal ?? 1) }}</b></div>
        </div>

        <!-- Steps + location -->
        <div class="icp-progress">
          <div class="icp-steps">
            <div v-for="(st, k) in STAGES" :key="st.key" class="icp-step">
              <div class="icp-bar" :style="{ background: k < c.idx ? c.s.soft : k === c.idx ? c.s.color : '#eef2f7' }"></div>
              <span :style="{ color: k === c.idx ? c.s.color : k < c.idx ? '#64748b' : '#94a3b8', fontWeight: k === c.idx ? 800 : 600 }">{{ st.label }}</span>
            </div>
          </div>
          <div class="icp-where">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" :stroke="c.s.color" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <span class="icp-where-text">{{ c.where }}</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="icp-actions">
          <button class="icp-btn ghost" @click="dismissCall(c.id)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            بستن
          </button>
          <button v-if="c.patient || canCreate" class="icp-btn primary" @click="onOpen(c)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            {{ c.patient ? 'مشاهده پرونده' : 'ایجاد پرونده' }}
          </button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { useIncomingCalls } from './useIncomingCalls'

defineProps({
  canCreate: { type: Boolean, default: false },
})
const emit = defineEmits(['open-file', 'create-file'])

const { calls, now, upsertCall, removeCall, clearAll, dispose } = useIncomingCalls()
const dismissed = new Set()
let pollTimer
let polling = false
let disposed = false

async function pollCalls() {
  if (polling || document.visibilityState === 'hidden') return
  polling = true
  try {
    const { data } = await axios.get('/api/voip/incoming-calls/live')
    if (disposed) return
    const active = new Set()
    for (const call of data.enabled ? (data.calls || []) : []) {
      const id = String(call.id)
      active.add(id)
      if (dismissed.has(id)) continue
      upsertCall({
        ...call,
        id,
        startedAt: Date.parse(call.startedAt) || Date.now(),
        connectedAt: call.connectedAt ? Date.parse(call.connectedAt) : null,
        talkEndedAt: call.talkEndedAt ? Date.parse(call.talkEndedAt) : null,
      })
    }
    for (const call of calls.value) if (!active.has(String(call.id))) removeCall(call.id)
    for (const id of dismissed) if (!active.has(id)) dismissed.delete(id)
    if (!data.enabled) clearAll()
  } catch {
    // Temporary network failures must not hide a call already on screen.
  } finally {
    polling = false
  }
}

function dismissCall(id) {
  dismissed.add(String(id))
  removeCall(id)
}

function dismissAll() {
  for (const call of calls.value) dismissed.add(String(call.id))
  clearAll()
}

onMounted(() => {
  pollCalls()
  pollTimer = window.setInterval(pollCalls, 3000)
  document.addEventListener('visibilitychange', pollCalls)
})
onBeforeUnmount(() => {
  disposed = true
  window.clearInterval(pollTimer)
  document.removeEventListener('visibilitychange', pollCalls)
  dispose()
})

const STAGES = [
  { key: 'ivr', label: 'منوی صوتی', color: '#6366f1', bg: '#eef2ff', soft: '#c7d2fe' },
  { key: 'queue', label: 'صف انتظار', color: '#d97706', bg: '#fffbeb', soft: '#fde68a' },
  { key: 'talking', label: 'در حال مکالمه', color: '#16a34a', bg: '#ecfdf3', soft: '#bbf7d0' },
  { key: 'survey', label: 'نظرسنجی', color: '#2563eb', bg: '#eff4ff', soft: '#bfdbfe' },
]

const fa = v => String(v ?? '').replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d])
const clock = ms => {
  const s = Math.max(0, Math.floor(ms / 1000))
  return fa(Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'))
}

const viewCalls = computed(() => calls.value.map(c => {
  const t = now.value
  const idx = Math.max(0, STAGES.findIndex(s => s.key === c.stage))
  const s = STAGES[idx]
  const waiting = !c.connectedAt && idx < 2
  const waitMs = (c.connectedAt ?? t) - c.startedAt
  const talk = c.connectedAt ? clock((c.talkEndedAt ?? t) - c.connectedAt) : '—'
  const talkColor = idx === 2 ? '#16a34a' : idx === 3 ? '#64748b' : '#cbd5e1'
  let where
  if (idx === 0) where = 'منوی صوتی · در حال انتخاب گزینه'
  else if (idx === 1) where = `صف ${c.queueName || ''}${c.queuePosition ? ' · نفر ' + fa(c.queuePosition) + ' در صف' : ''}`
  else if (idx === 2) where = [c.agentName, c.extension && 'داخلی ' + fa(c.extension)].filter(Boolean).join(' · ') || 'متصل به اپراتور'
  else where = 'در حال ثبت امتیاز رضایت'
  return { ...c, idx, s, waiting, wait: clock(waitMs), talk, talkColor, where }
}))

function onOpen(c) {
  emit(c.patient ? 'open-file' : 'create-file', c)
}
</script>

<style scoped>
.icp-stack{position:fixed;left:24px;bottom:96px;width:360px;max-width:calc(100vw - 48px);max-height:calc(100vh - 120px);overflow-y:auto;overscroll-behavior:contain;scrollbar-width:none;z-index:2147483010;display:flex;flex-direction:column;gap:10px;font-family:inherit;color:#0f172a}
.icp-stack::-webkit-scrollbar{display:none}
.icp-list{display:flex;flex-direction:column;gap:10px}
.icp-head{position:sticky;top:0;z-index:1;display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid #e6ebf3;border-radius:14px;padding:8px 14px 8px 8px;box-shadow:0 8px 20px -8px rgba(15,23,42,.18);font-size:13px;font-weight:700;color:#334155}
.icp-head-btn{font-family:inherit;border:0;background:#f1f5f9;color:#475569;border-radius:10px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer}
.icp-head-btn:hover{background:#e2e8f0}
.icp-card{flex-shrink:0;background:#fff;border-radius:22px;border:1px solid #e6ebf3;box-shadow:0 24px 48px -12px rgba(15,23,42,.22),0 4px 12px rgba(15,23,42,.06);overflow:hidden}
.icp-top{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px 0}
.icp-stage{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;border-radius:999px;padding:5px 12px;white-space:nowrap}
.icp-dot{width:7px;height:7px;border-radius:50%;animation:icpBlink 1.2s infinite}
.icp-timers{display:flex;align-items:stretch;gap:12px}
.icp-timer{display:flex;flex-direction:column;align-items:flex-end;gap:2px}
.icp-timer-label{font-size:10.5px;color:#94a3b8;font-weight:600;white-space:nowrap}
.icp-timer-val{font-size:16px;line-height:1;font-weight:800;font-variant-numeric:tabular-nums}
.icp-sep{width:1px;background:#eef2f7}
.icp-caller{display:flex;align-items:center;gap:14px;margin:10px 8px 0;padding:10px 8px}
.icp-avatar-wrap{position:relative;width:54px;height:54px;flex-shrink:0}
.icp-ring{position:absolute;inset:0;border-radius:50%;animation:icpRing 1.8s infinite ease-out}
.icp-ring2{animation-delay:.9s}
.icp-ring-static{position:absolute;inset:-3px;border-radius:50%;border:2px solid}
.icp-avatar{position:relative;width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-size:21px;font-weight:800}
.icp-avatar.unknown{background:linear-gradient(135deg,#fbbf24,#f59e0b)}
.icp-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px;padding-right:6px}
.icp-name{font-size:16px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.icp-phone{font-size:13px;color:#64748b;text-align:right;font-weight:500}
.icp-nofile{font-size:12px;color:#d97706;font-weight:600}
.icp-file{display:grid;grid-template-columns:auto auto minmax(0,1fr);margin:2px 16px 4px;border:1px solid #eef2f7;border-radius:12px;overflow:hidden}
.icp-file-cell{display:flex;flex-direction:column;gap:2px;padding:8px 12px;border-left:1px solid #eef2f7;min-width:0}
.icp-file-cell.grow{border-left:0}
.icp-k{font-size:10.5px;color:#94a3b8;font-weight:600}
.icp-v{font-size:13px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.icp-v.blue{color:#2563eb}
.icp-counts{display:flex;gap:6px;margin:4px 16px 2px}
.icp-count{flex:1;display:flex;align-items:center;justify-content:space-between;gap:6px;background:#f8fafc;border-radius:10px;padding:6px 10px}
.icp-count span{font-size:11px;color:#64748b;font-weight:600}
.icp-count b{font-size:13px;font-weight:800}
.icp-progress{padding:6px 16px 0;display:flex;flex-direction:column;gap:10px}
.icp-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:5px}
.icp-step{display:flex;flex-direction:column;gap:6px;min-width:0;font-size:11px}
.icp-step span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.icp-bar{height:4px;border-radius:99px;transition:background .3s}
.icp-where{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:#475569;background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:8px 10px}
.icp-where svg{flex-shrink:0}
.icp-where-text{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.icp-actions{display:flex;gap:10px;padding:12px 16px 16px}
.icp-btn{font-family:inherit;height:46px;border-radius:14px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;white-space:nowrap}
.icp-btn.ghost{flex:1;border:1px solid #e2e8f0;background:#fff;color:#475569}
.icp-btn.ghost:hover{background:#f8fafc}
.icp-btn.primary{flex:1.6;border:0;background:#2563eb;color:#fff;box-shadow:0 6px 14px rgba(37,99,235,.28)}
.icp-btn.primary:hover{background:#1d4ed8}
.icp-pop-enter-active{transition:all .35s cubic-bezier(.2,.9,.3,1.2)}
.icp-pop-leave-active{transition:all .2s ease}
.icp-pop-enter-from{opacity:0;transform:translateY(24px) scale(.96)}
.icp-pop-leave-to{opacity:0;transform:translateX(-30px)}
.icp-fade-enter-active,.icp-fade-leave-active{transition:opacity .2s}
.icp-fade-enter-from,.icp-fade-leave-to{opacity:0}
@keyframes icpRing{0%{transform:scale(1);opacity:.55}100%{transform:scale(1.9);opacity:0}}
@keyframes icpBlink{0%,100%{opacity:1}50%{opacity:.3}}
@media(max-width:480px){.icp-stack{left:12px;bottom:82px;width:calc(100vw - 24px);max-width:none;max-height:calc(100dvh - 96px)}.icp-top{align-items:flex-start;flex-wrap:wrap}.icp-file{grid-template-columns:repeat(3,minmax(0,1fr))}.icp-file-cell{padding:8px}.icp-v{font-size:11px}}
</style>
