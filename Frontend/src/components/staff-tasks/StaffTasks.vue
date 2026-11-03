<template>
  <div class="stc" dir="rtl">
    <!-- نوار ابزار -->
    <div class="panel toolbar">
      <div class="tb-row">
        <div class="row">
          <button type="button" class="icon-btn" title="تعریف وضعیت وظایف" @click="showSettings = true">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          </button>
          <div class="col">
            <div class="page-title">مدیریت وظایف پرسنل</div>
            <span class="demo-note">نسخهٔ نمایشی · داده‌ها نمونه هستند و ذخیره نمی‌شوند</span>
          </div>
        </div>
        <div class="legend">
          <span v-for="s in state.statuses" :key="s.id" class="lg"><i :style="{ background: PALETTE[s.color].c }"></i>{{ s.title }}</span>
        </div>
      </div>

      <div class="tb-row">
        <div class="row wrap">
          <button type="button" class="btn primary" @click="openNew(today)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            افزودن وظیفه
          </button>
          <button type="button" class="btn ghost" @click="anchor = today">امروز</button>
          <div class="row" style="gap:2px">
            <button type="button" class="nav" title="قبلی" @click="move(-1)"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
            <button type="button" class="nav" title="بعدی" @click="move(1)"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
          </div>
          <div class="row" style="gap:6px">
            <select class="msel" :value="vm" @change="anchor = j2d(vy, +$event.target.value, 1)">
              <option v-for="(n, i) in MONTHS" :key="i" :value="i + 1">{{ n }}</option>
            </select>
            <select class="msel" :value="vy" @change="anchor = j2d(+$event.target.value, vm, 1)">
              <option v-for="y in [vy - 2, vy - 1, vy, vy + 1, vy + 2]" :key="y" :value="y">{{ fa(y) }}</option>
            </select>
          </div>
        </div>
        <div class="seg">
          <button type="button" :class="{ on: view === 'week' }" @click="view = 'week'">هفته</button>
          <button type="button" :class="{ on: view === 'month' }" @click="view = 'month'">ماه</button>
        </div>
      </div>
    </div>

    <!-- خلاصهٔ حقوق و فیلتر پرسنل -->
    <div class="summary">
      <div class="sum-title">تأثیر روی حقوق · {{ MONTHS[vm - 1] }} {{ fa(vy) }}</div>
      <div class="sum-row">
        <button type="button" class="pchip all" :class="{ on: !filter }" @click="filter = null">همه پرسنل</button>
        <button v-for="p in summary" :key="p.id" type="button" class="pchip" :class="{ on: filter === p.id }" @click="filter = filter === p.id ? null : p.id">
          <span class="av" :style="{ background: p.color }">{{ p.name.charAt(0) }}</span>
          <span class="col">
            <span class="pn">{{ p.name }}</span>
            <span class="pnet" dir="ltr" :style="{ color: amountColor(p.net) }">
              {{ p.net === 0 ? 'بدون تغییر' : (p.net > 0 ? '+' : '−') + money(Math.abs(p.net)) + ' تومان' }}
            </span>
          </span>
        </button>
      </div>
    </div>

    <!-- نمای هفته -->
    <div v-if="view === 'week'" class="panel cal">
      <div class="week">
        <div v-for="d in weekDays" :key="d.day" class="wcol" :class="{ holiday: d.holiday, today: d.today }">
          <div class="whead" @click="openNew(d.day)">
            <div class="wh-top">
              <span class="wname" :class="{ today: d.today }">{{ WEEKDAYS[weekday(d.day)] }}</span>
              <span v-if="d.holiday" class="hbadge">تعطیل</span>
            </div>
            <div class="row" style="gap:6px">
              <span class="wnum" :class="{ today: d.today }">{{ fa(d.jd) }}</span>
              <span class="wmonth">{{ MONTHS[d.jm - 1] }}</span>
            </div>
          </div>
          <div class="wbody">
            <div v-for="t in d.tasks" :key="t.id" class="tcard" :style="cardStyle(t)" @click="openEdit(t)">
              <div class="tt">{{ t.title }}</div>
              <div class="row" style="gap:6px;min-width:0">
                <span class="av sm" :style="{ background: staffOf(t).color }">{{ staffOf(t).name.charAt(0) }}</span>
                <span class="tstaff">{{ staffOf(t).name }}</span>
              </div>
              <div class="trow">
                <span class="pill" :style="pillStyle(t)">{{ statusById(t.statusId)?.title || 'در انتظار' }}</span>
                <span v-if="effectOf(statusById(t.statusId))" class="tamt" dir="ltr" :style="{ color: amountColor(effectOf(statusById(t.statusId))) }">{{ amountText(t) }}</span>
              </div>
              <div v-if="!t.statusId && t.expiry?.enabled" class="tdead">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                مهلت {{ shortDate(t.expiry.day) }} · {{ fa(t.expiry.time) }}
              </div>
              <div v-if="t.statusId && t.auto" class="tauto">تغییر خودکار پس از انقضا</div>
            </div>
            <button type="button" class="addbtn" @click="openNew(d.day)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              افزودن وظیفه
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- نمای ماه -->
    <div v-else class="panel cal">
      <div class="month">
        <div v-for="i in [6, 0, 1, 2, 3, 4, 5]" :key="'h' + i" class="mh" :class="{ red: i === 5 }">{{ WEEKDAYS[i] }}</div>
        <div v-for="c in monthCells" :key="c.day" class="mcell" :class="{ holiday: c.holiday, out: !c.inMonth }" @click="openNew(c.day)">
          <div class="mc-top">
            <span class="mnum" :class="{ today: c.today }">{{ fa(c.jd) }}</span>
            <span v-if="c.holiday" class="mred">تعطیل</span>
          </div>
          <div v-for="t in c.tasks.slice(0, 3)" :key="t.id" class="mtask" :style="cardStyle(t)" @click.stop="openEdit(t)">{{ t.title }}</div>
          <span v-if="c.tasks.length > 3" class="more">{{ fa(c.tasks.length - 3) }} مورد دیگر</span>
        </div>
      </div>
    </div>

    <!-- مودال‌ها به body منتقل می‌شوند تا زیر منو و دکمه‌های شناور نمانند -->
    <Teleport to="body">
      <div v-if="editing || showSettings" class="st-layer" dir="rtl">
        <TaskModal v-if="editing" :task="editing" @close="editing = null" @save="onSave" @delete="onDelete" />
        <StatusSettings v-if="showSettings" @close="showSettings = false" />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import TaskModal from './TaskModal.vue'
import StatusSettings from './StatusSettings.vue'
import { useStaffTasks, PALETTE } from './useStaffTasks'
import { d2j, j2d, monthLength, weekday, weekStart, todayJdn, WEEKDAYS, MONTHS, fa, money, shortMoney } from './jalali'

const { state, saveTask, deleteTask, runExpiry, statusById, effectOf } = useStaffTasks()

const today = todayJdn()
const anchor = ref(today)
const view = ref('week')
const filter = ref(null)
const editing = ref(null)
const showSettings = ref(false)

let timer
onMounted(() => { runExpiry(); timer = setInterval(runExpiry, 30000) })
onBeforeUnmount(() => clearInterval(timer))

const ws = computed(() => weekStart(anchor.value))
const viewYM = computed(() => (view.value === 'week' ? d2j(ws.value + 3) : d2j(anchor.value)))
const vy = computed(() => viewYM.value[0])
const vm = computed(() => viewYM.value[1])

const isHoliday = d => weekday(d) === 5 || state.holidays.has(d)
const byDay = computed(() => {
  const map = {}
  state.tasks.forEach(t => {
    if (filter.value && t.staffId !== filter.value) return
    map[t.day] = map[t.day] || []
    map[t.day].push(t)
  })
  return map
})
const dayInfo = day => { const [, jm, jd] = d2j(day); return { day, jm, jd, today: day === today, holiday: isHoliday(day), tasks: byDay.value[day] || [] } }

const weekDays = computed(() => Array.from({ length: 7 }, (_, i) => dayInfo(ws.value + i)))

const monthStart = computed(() => j2d(vy.value, vm.value, 1))
const monthEnd = computed(() => monthStart.value + monthLength(vy.value, vm.value) - 1)
const monthCells = computed(() => {
  const gs = weekStart(monthStart.value)
  const n = Math.ceil((monthEnd.value - gs + 1) / 7) * 7
  return Array.from({ length: n }, (_, i) => { const c = dayInfo(gs + i); c.inMonth = c.jm === vm.value; return c })
})

const summary = computed(() => state.staff.map(p => ({
  ...p,
  color: p.color || '#6366f1',
  net: state.tasks
    .filter(t => t.staffId === p.id && t.day >= monthStart.value && t.day <= monthEnd.value)
    .reduce((a, t) => a + effectOf(statusById(t.statusId)), 0),
})))

function move(k) {
  if (view.value === 'week') { anchor.value += 7 * k; return }
  let y = vy.value, m = vm.value + k
  if (m > 12) { m = 1; y++ }
  if (m < 1) { m = 12; y-- }
  anchor.value = j2d(y, m, 1)
}

const staffOf = t => state.staff.find(p => p.id === t.staffId) || { name: '—', color: '#94a3b8' }
// رنگ کارت با سطح زمینه ترکیب می‌شود تا در حالت شب هم خوانا بماند.
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
const amountColor = n => (n > 0 ? 'var(--st-pos)' : n < 0 ? 'var(--st-neg)' : 'var(--st-faint)')
const amountText = t => { const e = effectOf(statusById(t.statusId)); return (e > 0 ? '+' : '−') + shortMoney(Math.abs(e)) }
const shortDate = d => { const [, m, dd] = d2j(d); return `${fa(dd)} ${MONTHS[m - 1]}` }

function openNew(day) {
  editing.value = {
    id: null, staffId: filter.value || state.staff[0]?.id, title: '', note: '', day, statusId: null, auto: false,
    expiry: { enabled: false, day, time: '18:00', statusId: state.expiryDefault },
  }
}
const openEdit = t => { editing.value = t }
function onSave(task) { saveTask(task); editing.value = null }
function onDelete(id) { deleteTask(id); editing.value = null }
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
.stc{display:flex;flex-direction:column;gap:14px;color:var(--st-text);font-family:inherit}
.dark .stc,.dark .st-layer{
  --st-surface:#172033;--st-surface-2:#111a2b;--st-muted-bg:#1e293b;--st-border:#334155;--st-strong-border:#475569;--st-line:#243044;
  --st-text:#f8fafc;--st-text-2:#e2e8f0;--st-text-3:#cbd5e1;--st-muted:#94a3b8;--st-faint:#64748b;
  --st-accent:#3b82f6;--st-accent-hover:#2563eb;--st-accent-soft:rgba(59,130,246,.14);--st-hover:rgba(59,130,246,.08);
  --st-red:#f87171;--st-holiday:rgba(220,38,38,.10);--st-holiday-badge:rgba(220,38,38,.22);--st-pos:#4ade80;--st-neg:#f87171;
  --st-overlay:rgba(2,6,23,.6);--st-shadow:0 2px 10px rgba(0,0,0,.25)
}
.panel{background:var(--st-surface);border-radius:20px;box-shadow:var(--st-shadow)}
.toolbar{padding:14px 18px;display:flex;flex-direction:column;gap:14px}
.tb-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.row{display:flex;align-items:center;gap:10px}
.row.wrap{flex-wrap:wrap;gap:8px}
.col{display:flex;flex-direction:column;gap:1px}
.page-title{font-size:18px;font-weight:800}
.demo-note{font-size:11px;font-weight:600;color:var(--st-muted)}
.icon-btn{width:40px;height:40px;border-radius:12px;border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-3);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.icon-btn:hover{border-color:var(--st-accent);color:var(--st-accent)}
.legend{display:flex;gap:6px;flex-wrap:wrap}
.lg{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--st-text-3);padding:4px 10px;border-radius:999px;background:var(--st-surface-2)}
.lg i{width:9px;height:9px;border-radius:3px}
.btn{font-family:inherit;height:38px;border-radius:11px;font-size:13px;font-weight:700;padding:0 16px;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap}
.btn.primary{border:0;background:var(--st-accent);color:#fff}
.btn.primary:hover{background:var(--st-accent-hover)}
.btn.ghost{border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-2)}
.nav{width:34px;height:34px;border:0;border-radius:10px;background:transparent;color:var(--st-text-3);cursor:pointer;display:flex;align-items:center;justify-content:center}
.nav:hover{background:var(--st-muted-bg)}
.msel{font-family:inherit;font-size:15px;font-weight:800;color:var(--st-text);border:1px solid var(--st-border);border-radius:10px;padding:6px 10px;background:var(--st-surface);cursor:pointer}
.seg{display:flex;background:var(--st-muted-bg);border-radius:11px;padding:3px;gap:2px}
.seg button{font-family:inherit;border:0;border-radius:9px;padding:7px 16px;font-size:13px;font-weight:700;cursor:pointer;background:transparent;color:var(--st-muted)}
.seg button.on{background:var(--st-surface);color:var(--st-text);box-shadow:0 1px 3px rgba(15,23,42,.12)}
.summary{display:flex;flex-direction:column;gap:8px}
.sum-title{font-size:12px;font-weight:700;color:var(--st-muted);padding:0 4px}
.sum-row{display:flex;gap:8px;overflow-x:auto;padding-bottom:2px}
.pchip{font-family:inherit;flex-shrink:0;border:1px solid var(--st-line);background:var(--st-surface);color:var(--st-text);border-radius:14px;padding:6px 12px 6px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;text-align:right}
.pchip.all{padding:8px 14px;font-size:13px;font-weight:700;color:var(--st-text-2)}
.pchip.on{border-color:var(--st-accent)}
.av{width:30px;height:30px;border-radius:50%;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.av.sm{width:18px;height:18px;font-size:10px}
.pn{font-size:13px;font-weight:700;white-space:nowrap}
.pnet{font-size:12px;font-weight:800;white-space:nowrap;text-align:right}
.cal{overflow-x:auto}
.week{display:grid;grid-template-columns:repeat(7,minmax(150px,1fr));min-width:1050px}
.wcol{display:flex;flex-direction:column;border-left:1px solid var(--st-line);background:var(--st-surface);min-height:560px}
.wcol:last-child{border-left:0}
.wcol.holiday{background:var(--st-holiday)}
.whead{cursor:pointer;padding:12px 14px 10px;border-bottom:1px solid var(--st-line);display:flex;flex-direction:column;gap:4px}
.whead:hover{background:var(--st-hover)}
.wh-top{display:flex;align-items:center;justify-content:space-between;gap:6px}
.wname{font-size:12px;font-weight:700;color:var(--st-muted)}
.wname.today{color:var(--st-accent)}
.holiday .wname,.holiday .wnum{color:var(--st-red)}
.hbadge{font-size:11px;font-weight:800;color:var(--st-red);background:var(--st-holiday-badge);border-radius:999px;padding:1px 9px}
.wnum{min-width:32px;height:32px;padding:0 4px;box-sizing:border-box;border-radius:9px;font-size:19px;font-weight:800;display:flex;align-items:center;justify-content:center}
.wnum.today{background:var(--st-accent);color:#fff !important}
.wmonth{font-size:12px;color:var(--st-faint);font-weight:600}
.wbody{flex:1;padding:10px 8px;display:flex;flex-direction:column;gap:6px}
.tcard{cursor:pointer;border:1px solid;border-radius:10px;padding:8px 10px;display:flex;flex-direction:column;gap:6px}
.tcard:hover{box-shadow:0 4px 12px rgba(15,23,42,.1)}
.tt{font-size:13px;font-weight:700;line-height:1.55;text-wrap:pretty}
.tstaff{font-size:11.5px;font-weight:600;color:var(--st-text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.trow{display:flex;align-items:center;justify-content:space-between;gap:6px;flex-wrap:wrap}
.pill{font-size:10.5px;font-weight:700;border-radius:999px;padding:2px 9px;white-space:nowrap}
.tamt{font-size:11.5px;font-weight:800;white-space:nowrap}
.tdead{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--st-muted)}
.tauto{font-size:10.5px;font-weight:700;color:var(--st-muted)}
.addbtn{font-family:inherit;border:0;background:transparent;color:var(--st-muted);font-size:12.5px;font-weight:700;padding:8px;border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px}
.addbtn:hover{background:var(--st-muted-bg);color:var(--st-accent)}
.month{display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));min-width:840px}
.mh{padding:10px 12px;font-size:12px;font-weight:700;color:var(--st-muted);border-bottom:1px solid var(--st-line);border-left:1px solid var(--st-line)}
.mh.red{color:var(--st-red)}
.mcell{cursor:pointer;min-height:118px;padding:8px;box-sizing:border-box;border-bottom:1px solid var(--st-line);border-left:1px solid var(--st-line);background:var(--st-surface);display:flex;flex-direction:column;gap:4px;min-width:0}
.mcell:hover{box-shadow:inset 0 0 0 2px var(--st-accent-soft)}
.mcell.out{background:var(--st-surface-2)}
.mcell.holiday{background:var(--st-holiday)}
.mc-top{display:flex;align-items:center;justify-content:space-between;gap:4px}
.mnum{min-width:26px;height:26px;border-radius:8px;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center}
.mcell.out .mnum{color:var(--st-faint);opacity:.6}
.mcell.holiday .mnum{color:var(--st-red)}
.mnum.today{background:var(--st-accent);color:#fff !important}
.mred{font-size:10.5px;font-weight:800;color:var(--st-red)}
.mtask{border:1px solid;border-radius:7px;padding:3px 7px;font-size:11.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.more{font-size:11px;font-weight:700;color:var(--st-muted);padding:0 4px}

/* موبایل: روزهای هفته زیر هم و تقویم ماه بدون اسکرول افقی */
@media (max-width: 700px){
  .toolbar{padding:12px}
  .page-title{font-size:16px}
  .tb-row{gap:10px}
  .seg{width:100%}
  .seg button{flex:1}
  .week{grid-template-columns:1fr;min-width:0}
  .wcol{min-height:0;border-left:0;border-bottom:1px solid var(--st-line)}
  .wcol:last-child{border-bottom:0}
  .whead{flex-direction:row;align-items:center;justify-content:space-between}
  .wbody{padding:8px 10px 10px}
  .addbtn{justify-content:flex-start;padding:6px 8px}
  .month{grid-template-columns:repeat(7,minmax(0,1fr));min-width:0}
  .mh{padding:8px 2px;font-size:10px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .mcell{min-height:68px;padding:4px 3px;gap:3px}
  .mnum{min-width:22px;height:22px;font-size:12px}
  .mred{display:none}
  .mtask{font-size:9.5px;padding:2px 3px;border-radius:5px}
  .more{font-size:9.5px;padding:0}
}
</style>
