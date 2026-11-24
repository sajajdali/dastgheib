<template>
  <div class="ftr" dir="rtl">
    <header class="ftr-top">
      <button type="button" class="ftr-back" @click="$emit('back')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
        بازگشت به تقویم
      </button>
      <label class="ftr-search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input
          ref="searchEl"
          v-model.trim="query"
          type="search"
          placeholder="جستجو در تسک ها..."
          @keydown.esc="query = ''"
        />
        <kbd>Ctrl + /</kbd>
      </label>
      <div class="ftr-period">
        <button
          v-for="p in periods"
          :key="p.key"
          type="button"
          :class="{ on: period === p.key }"
          @click="setPeriod(p.key)"
        >{{ p.label }}</button>
      </div>
    </header>

    <div class="ftr-title">
      <span class="ftr-ico" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>
      </span>
      <div>
        <h1>مدیریت تسک ها</h1>
        <p>ایجاد، مدیریت و پیگیری وظایف و شرح وظایف پرسنل</p>
      </div>
    </div>

    <div v-if="loading" class="ftr-loading">در حال بارگذاری گزارش…</div>
    <template v-else-if="data">
      <div class="ftr-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="ftr-tab"
          :class="{ on: filter === tab.key, danger: tab.key === 'overdue' }"
          @click="filter = tab.key; page = 1"
        >
          <span>{{ tab.label }}</span>
          <b>{{ fa(tab.count) }}</b>
        </button>
      </div>

      <section class="ftr-card ftr-table-card">
        <div class="ftr-table-wrap">
          <table>
            <thead>
              <tr>
                <th class="chk"></th>
                <th>عنوان تسک</th>
                <th>اولویت</th>
                <th>مسئول</th>
                <th>مهلت انجام</th>
                <th>وضعیت</th>
                <th>پیشرفت</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in pagedTasks" :key="t.id">
                <td class="chk"><span class="dot" :class="{ on: selected.has(t.id) }" @click="toggleSelect(t.id)"></span></td>
                <td class="title prewrap">{{ t.title }}</td>
                <td><span class="prio" :class="t.priority || 'normal'">{{ priorityLabel(t.priority) }}</span></td>
                <td>
                  <div class="who" v-if="primaryAssignee(t)">
                    <span class="av"><img v-if="primaryAssignee(t).avatar_url" :src="primaryAssignee(t).avatar_url" alt="" /><template v-else>{{ primaryAssignee(t).name?.charAt(0) }}</template></span>
                    <span>{{ primaryAssignee(t).name }}</span>
                    <small v-if="(t.assignees || []).length > 1">+{{ fa((t.assignees || []).length - 1) }}</small>
                  </div>
                  <span v-else class="muted">—</span>
                </td>
                <td>{{ deadlineLabel(t) }}</td>
                <td><span class="status" :style="statusStyle(t)">{{ t.status?.title || 'بدون وضعیت' }}</span></td>
                <td class="prog">
                  <div class="prog-top"><span>{{ fa(t.progress || 0) }}٪</span></div>
                  <div class="bar"><i :style="{ width: Math.min(100, t.progress || 0) + '%', background: progressColor(t) }"></i></div>
                </td>
                <td class="acts">
                  <button type="button" title="مشاهده" @click="$emit('open', t.id)">👁</button>
                  <button type="button" title="ویرایش" @click="$emit('edit', t.id)">✎</button>
                </td>
              </tr>
              <tr v-if="!filteredTasks.length"><td colspan="8" class="empty">در این فیلتر وظیفه‌ای نیست.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="ftr-pager" v-if="filteredTasks.length">
          <span>نمایش {{ fa(pageFrom) }} تا {{ fa(pageTo) }} از {{ fa(filteredTasks.length) }} تسک</span>
          <div class="pages">
            <button type="button" :disabled="page <= 1" @click="page--">‹</button>
            <button
              v-for="n in pageButtons"
              :key="n"
              type="button"
              :class="{ on: n === page }"
              @click="page = n"
            >{{ fa(n) }}</button>
            <button type="button" :disabled="page >= pageCount" @click="page++">›</button>
          </div>
        </div>
      </section>

      <div class="ftr-widgets">
        <section class="ftr-card">
          <div class="wh">
            <h3>تسک های عقب افتاده</h3>
          </div>
          <ul class="od-list">
            <li v-for="t in overduePreview" :key="t.id" @click="$emit('open', t.id)">
              <div>
                <b class="prewrap">{{ t.title }}</b>
                <small>{{ deadlineLabel(t) }}</small>
              </div>
              <span class="prio" :class="t.priority || 'normal'">{{ priorityLabel(t.priority) }}</span>
            </li>
            <li v-if="!overduePreview.length" class="empty-li">موردی نیست.</li>
          </ul>
          <button v-if="(data.overdue_tasks || []).length" type="button" class="view-all" @click="filter = 'overdue'; page = 1">مشاهده همه</button>
        </section>

        <section class="ftr-card">
          <div class="wh">
            <h3>عملکرد پرسنل</h3>
            <select v-model="perfPeriod" @change="loadPerf">
              <option v-for="p in periods" :key="p.key" :value="p.key">{{ p.short }}</option>
            </select>
          </div>
          <div class="perf">
            <article v-for="row in (perfData?.per_staff || [])" :key="row.staff.id">
              <div class="perf-top">
                <span>{{ row.staff.name }}</span>
                <b>{{ fa(row.percent_done) }}٪</b>
              </div>
              <div class="bar"><i :style="{ width: row.percent_done + '%', background: perfColor(row.percent_done) }"></i></div>
            </article>
            <p v-if="!(perfData?.per_staff || []).length" class="empty-li">داده‌ای نیست.</p>
          </div>
        </section>

        <section class="ftr-card">
          <div class="wh">
            <h3>نمودار تسک ها</h3>
            <select v-model="chartPeriod" @change="loadChart">
              <option v-for="p in periods" :key="p.key" :value="p.key">{{ p.short }}</option>
            </select>
          </div>
          <div class="donut-wrap">
            <div class="donut" :style="{ background: donutGradient }">
              <div class="donut-hole">
                <b>{{ fa(donutTotal) }}</b>
                <small>تسک</small>
              </div>
            </div>
            <ul class="legend">
              <li v-for="row in donutRows" :key="row.key">
                <i :style="{ background: row.color }"></i>
                <span>{{ row.title }}</span>
                <b>{{ fa(row.count) }}</b>
                <em>{{ fa(row.pct) }}٪</em>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { fetchReport, PALETTE } from './useStaffTasks'
import { fa, isoToJdn, d2j } from './jalali'

defineEmits(['back', 'open', 'edit'])

const periods = [
  { key: 'daily', label: 'روزانه', short: 'امروز' },
  { key: 'weekly', label: 'هفتگی', short: 'هفتگی' },
  { key: 'monthly', label: 'ماهانه', short: 'این ماه' },
  { key: 'yearly', label: 'سالانه', short: 'امسال' },
]

const period = ref('monthly')
const chartPeriod = ref('weekly')
const perfPeriod = ref('monthly')
const filter = ref('all')
const query = ref('')
const page = ref(1)
const pageSize = 5
const loading = ref(true)
const data = ref(null)
const chartData = ref(null)
const perfData = ref(null)
const selected = ref(new Set())
const searchEl = ref(null)

const tabs = computed(() => {
  const s = data.value?.summary || {}
  return [
    { key: 'all', label: 'همه تسک ها', count: s.all || 0 },
    { key: 'created_by_me', label: 'تسک های من', count: s.created_by_me ?? s.assigned_by_me ?? 0 },
    { key: 'assigned_to_me', label: 'واگذار شده به من', count: s.assigned_to_me ?? s.mine ?? 0 },
    { key: 'overdue', label: 'عقب افتاده', count: s.overdue || 0 },
  ]
})

const idSet = computed(() => {
  const ids = data.value?.task_ids || {}
  const map = {
    all: new Set((ids.all || data.value?.tasks?.map(t => t.id) || []).map(Number)),
    created_by_me: new Set((ids.created_by_me || []).map(Number)),
    assigned_to_me: new Set((ids.assigned_to_me || []).map(Number)),
    overdue: new Set((ids.overdue || (data.value?.overdue_tasks || []).map(t => t.id) || []).map(Number)),
  }
  return map
})

const filteredTasks = computed(() => {
  let list = data.value?.tasks || []
  const set = idSet.value[filter.value]
  if (set && filter.value !== 'all') list = list.filter(t => set.has(Number(t.id)))
  const q = query.value.trim().toLowerCase()
  if (q) {
    list = list.filter(t => {
      const names = (t.assignees || []).map(a => a.name || '').join(' ')
      return String(t.title || '').toLowerCase().includes(q)
        || String(t.note || '').toLowerCase().includes(q)
        || names.toLowerCase().includes(q)
        || String(t.status?.title || '').toLowerCase().includes(q)
    })
  }
  return list
})

const pageCount = computed(() => Math.max(1, Math.ceil(filteredTasks.value.length / pageSize)))
const pageFrom = computed(() => (filteredTasks.value.length ? (page.value - 1) * pageSize + 1 : 0))
const pageTo = computed(() => Math.min(filteredTasks.value.length, page.value * pageSize))
const pagedTasks = computed(() => filteredTasks.value.slice((page.value - 1) * pageSize, page.value * pageSize))
const pageButtons = computed(() => {
  const total = pageCount.value
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)
  const cur = page.value
  const set = new Set([1, total, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= total))
  return [...set].sort((a, b) => a - b)
})

const overduePreview = computed(() => (data.value?.overdue_tasks || []).slice(0, 5))

const donutSource = computed(() => chartData.value?.donut_chart || data.value?.donut_chart || [])
const donutTotal = computed(() => donutSource.value.reduce((s, r) => s + (r.count || 0), 0) || (chartData.value?.summary?.all ?? data.value?.summary?.all ?? 0))
const donutRows = computed(() => {
  const total = Math.max(1, donutTotal.value)
  return donutSource.value.map(r => ({
    ...r,
    pct: Math.round(((r.count || 0) / total) * 100),
  }))
})
const donutGradient = computed(() => {
  const rows = donutRows.value
  const total = Math.max(1, rows.reduce((s, r) => s + (r.count || 0), 0))
  if (!rows.length || total === 0) return 'conic-gradient(#e2e8f0 0 100%)'
  let start = 0
  const parts = rows.map(r => {
    const slice = ((r.count || 0) / total) * 100
    const end = start + slice
    const part = `${r.color} ${start}% ${end}%`
    start = end
    return part
  })
  return `conic-gradient(${parts.join(', ')})`
})

watch(filteredTasks, () => {
  if (page.value > pageCount.value) page.value = pageCount.value
})

function setPeriod(key) {
  period.value = key
  loadMain()
}
function toggleSelect(id) {
  const next = new Set(selected.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selected.value = next
}
function primaryAssignee(t) {
  return (t.assignees || [])[0] || null
}
function priorityLabel(p) {
  return ({ low: 'کم', normal: 'معمولی', high: 'فوری', urgent: 'خیلی فوری' }[p] || 'معمولی')
}
function deadlineLabel(t) {
  const iso = t.expiry?.enabled && t.expiry?.date ? t.expiry.date : t.task_date
  if (!iso) return '—'
  const s = String(iso).slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return fa(s)
  const [y, m, d] = d2j(isoToJdn(s))
  return `${fa(y)}/${fa(String(m).padStart(2, '0'))}/${fa(String(d).padStart(2, '0'))}`
}
function statusStyle(t) {
  const st = t.status
  if (!st) return { background: '#f1f5f9', color: '#64748b' }
  const p = PALETTE[st.color] || PALETTE[7]
  return { background: p.c, color: p.fg }
}
function progressColor(t) {
  if (t.is_overdue) return '#ef4444'
  const st = t.status
  if (st) return (PALETTE[st.color] || PALETTE[7]).c
  const n = t.progress || 0
  if (n >= 100) return '#22c55e'
  if (n >= 50) return '#3b82f6'
  if (n > 0) return '#f59e0b'
  return '#94a3b8'
}
function perfColor(pct) {
  if (pct >= 85) return '#22c55e'
  if (pct >= 60) return '#3b82f6'
  if (pct >= 35) return '#f59e0b'
  return '#ef4444'
}

async function loadMain() {
  loading.value = true
  try {
    data.value = await fetchReport(period.value)
    page.value = 1
  } finally {
    loading.value = false
  }
}
async function loadChart() {
  chartData.value = await fetchReport(chartPeriod.value)
}
async function loadPerf() {
  perfData.value = await fetchReport(perfPeriod.value)
}

function onHotkey(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === '/') {
    e.preventDefault()
    searchEl.value?.focus()
  }
}

onMounted(async () => {
  document.addEventListener('keydown', onHotkey)
  await Promise.all([loadMain(), loadChart(), loadPerf()])
})
onBeforeUnmount(() => document.removeEventListener('keydown', onHotkey))
</script>

<style scoped>
.ftr{
  --purple:#7c3aed;--purple-soft:#f5f3ff;--line:#e8eef5;--text:#0f172a;--muted:#64748b;--card:#fff;--bg:#f4f7fb;
  background:var(--bg);color:var(--text);border-radius:18px;padding:14px 16px 20px;display:flex;flex-direction:column;gap:14px;min-height:70vh
}
.ftr-top{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
.ftr-back{font-family:inherit;border:1px solid #dbe3ef;background:#fff;border-radius:10px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;font-weight:700;color:#475569}
.ftr-search{flex:1;min-width:220px;display:flex;align-items:center;gap:8px;background:#eef2f7;border-radius:12px;padding:8px 12px;color:#64748b}
.ftr-search input{flex:1;border:0;background:transparent;outline:0;font-family:inherit;font-size:13px;color:var(--text)}
.ftr-search kbd{font-size:10px;font-weight:700;background:#fff;border:1px solid #dbe3ef;border-radius:6px;padding:2px 6px;color:#94a3b8}
.ftr-period{display:flex;background:#fff;border:1px solid #dbe3ef;border-radius:10px;padding:2px;gap:2px}
.ftr-period button{font-family:inherit;border:0;background:transparent;border-radius:8px;padding:6px 10px;font-size:11px;font-weight:700;cursor:pointer;color:#64748b}
.ftr-period button.on{background:var(--purple-soft);color:var(--purple)}
.ftr-title{display:flex;align-items:center;gap:12px;padding:4px 2px}
.ftr-ico{width:44px;height:44px;border-radius:14px;background:var(--purple-soft);color:var(--purple);display:flex;align-items:center;justify-content:center}
.ftr-title h1{margin:0;font-size:22px;font-weight:900;color:var(--purple)}
.ftr-title p{margin:2px 0 0;font-size:12px;color:var(--muted)}
.ftr-loading{padding:40px;text-align:center;color:var(--muted);font-weight:700}
.ftr-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.ftr-tab{font-family:inherit;border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-size:12px;font-weight:700;color:#475569;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.ftr-tab b{font-size:20px;color:var(--text)}
.ftr-tab.on{border-color:#c4b5fd;background:var(--purple-soft);color:var(--purple);box-shadow:0 0 0 1px #ddd6fe}
.ftr-tab.on b{color:var(--purple)}
.ftr-tab.danger b{color:#ef4444}
.ftr-tab.danger.on{border-color:#fecaca;background:#fef2f2;color:#b91c1c}
.ftr-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:12px;box-shadow:0 8px 24px rgba(15,23,42,.04)}
.ftr-table-wrap{overflow:auto;border-radius:12px}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th,td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:right;vertical-align:middle}
th{color:var(--muted);font-size:11px;font-weight:800;background:#fafbfd;position:sticky;top:0}
td.title{font-weight:700;max-width:240px}
.prewrap{white-space:pre-wrap;overflow-wrap:anywhere}
.chk{width:28px}
.dot{display:inline-block;width:16px;height:16px;border-radius:50%;border:1.5px solid #cbd5e1;cursor:pointer}
.dot.on{background:var(--purple);border-color:var(--purple);box-shadow:inset 0 0 0 3px #fff}
.prio{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:3px 8px;font-size:10px;font-weight:800}
.prio.urgent{background:#fef2f2;color:#dc2626}
.prio.high{background:#fff7ed;color:#ea580c}
.prio.normal{background:#eff6ff;color:#2563eb}
.prio.low{background:#f8fafc;color:#64748b}
.prio.urgent::before,.prio.high::before{content:'!'}
.prio.normal::before{content:'•'}
.who{display:inline-flex;align-items:center;gap:6px;font-weight:700}
.who small{color:var(--muted);font-weight:600}
.av{width:24px;height:24px;border-radius:50%;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;background:#64748b;color:#fff;font-size:10px;font-weight:800;flex-shrink:0}
.av img{width:100%;height:100%;object-fit:cover}
.status{display:inline-block;border-radius:999px;padding:3px 9px;font-size:10px;font-weight:800;color:#fff}
.prog{min-width:110px}
.prog-top{font-size:11px;font-weight:800;margin-bottom:4px}
.bar{height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden}
.bar i{display:block;height:100%;border-radius:999px}
.acts{white-space:nowrap}
.acts button{border:0;background:#f1f5f9;border-radius:8px;width:28px;height:28px;cursor:pointer;margin-right:3px}
.empty{text-align:center;color:var(--muted);padding:24px !important}
.muted{color:var(--muted)}
.ftr-pager{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 4px 2px;flex-wrap:wrap;font-size:11px;color:var(--muted);font-weight:700}
.pages{display:flex;gap:4px}
.pages button{font-family:inherit;border:1px solid #e2e8f0;background:#fff;border-radius:8px;min-width:28px;height:28px;cursor:pointer;font-size:11px;font-weight:700;color:#475569}
.pages button.on{background:var(--purple);border-color:var(--purple);color:#fff}
.pages button:disabled{opacity:.4;cursor:default}
.ftr-widgets{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.wh{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
.wh h3{margin:0;font-size:13px;font-weight:900;color:var(--purple)}
.wh select{font-family:inherit;border:1px solid #e2e8f0;border-radius:8px;padding:4px 8px;font-size:11px;font-weight:700;background:#fff;color:#475569}
.od-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
.od-list li{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px;border-radius:10px;background:#f8fafc;cursor:pointer}
.od-list b{display:block;font-size:12px}.od-list small{color:var(--muted);font-size:10px}
.empty-li{text-align:center;color:var(--muted);font-size:12px;font-weight:600;padding:12px}
.view-all{margin-top:10px;width:100%;border:0;background:var(--purple-soft);color:var(--purple);border-radius:10px;padding:8px;font-family:inherit;font-weight:800;font-size:12px;cursor:pointer}
.perf{display:flex;flex-direction:column;gap:10px}
.perf-top{display:flex;justify-content:space-between;font-size:12px;font-weight:700;margin-bottom:4px}
.donut-wrap{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.donut{width:132px;height:132px;border-radius:50%;display:grid;place-items:center;flex-shrink:0}
.donut-hole{width:78px;height:78px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:inset 0 0 0 1px #eef2f7}
.donut-hole b{font-size:18px;line-height:1}.donut-hole small{font-size:10px;color:var(--muted);font-weight:700}
.legend{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;flex:1;min-width:140px}
.legend li{display:grid;grid-template-columns:10px 1fr auto auto;align-items:center;gap:6px;font-size:11px;font-weight:700}
.legend i{width:8px;height:8px;border-radius:50%}
.legend em{color:var(--muted);font-style:normal}
@media (max-width:980px){
  .ftr-tabs,.ftr-widgets{grid-template-columns:1fr 1fr}
}
@media (max-width:700px){
  .ftr-tabs,.ftr-widgets{grid-template-columns:1fr}
  .ftr-title h1{font-size:18px}
}
</style>
