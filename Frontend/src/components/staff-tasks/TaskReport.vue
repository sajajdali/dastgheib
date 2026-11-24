<template>
  <div class="st-overlay" @click.self="$emit('close')">
    <div class="st-modal report">
      <div class="head">
        <div class="col">
          <span class="h">گزارش مدیریتی وظایف</span>
          <span class="sub">{{ rangeLabel }}</span>
        </div>
        <div class="row">
          <div class="seg">
            <button v-for="p in periods" :key="p.key" type="button" :class="{ on: period === p.key }" @click="period = p.key; load()">{{ p.label }}</button>
          </div>
          <button class="st-x" type="button" @click="$emit('close')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
      </div>

      <div class="body" v-if="data">
        <div class="sum-grid">
          <article v-for="c in summaryCards" :key="c.key"><span>{{ c.label }}</span><b>{{ fa(c.value) }}</b></article>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>عنوان</th><th>مسئولان</th><th>اولویت</th><th>ثبت</th><th>مهلت</th><th>وضعیت</th><th>پیشرفت</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in data.tasks" :key="t.id">
                <td>{{ t.title }}</td>
                <td>
                  <span class="who" v-for="a in t.assignees" :key="a.id">
                    <span class="av"><img v-if="a.avatar_url" :src="a.avatar_url" alt="" /><template v-else>{{ a.name.charAt(0) }}</template></span>{{ a.name }}
                  </span>
                </td>
                <td>{{ priorityLabel(t.priority) }}</td>
                <td>{{ shortIso(t.registered_at || t.task_date) }}</td>
                <td>{{ t.expiry?.enabled ? shortIso(t.expiry.date) + ' ' + fa(t.expiry.time || '') : '—' }}</td>
                <td><span class="pill" :style="pill(t)">{{ t.status?.title || 'بدون وضعیت' }}</span></td>
                <td>{{ fa(t.progress || 0) }}٪</td>
                <td>
                  <button type="button" class="ib" title="مشاهده" @click="$emit('open', t.id)">👁</button>
                  <button type="button" class="ib" title="ویرایش" @click="$emit('edit', t.id)">✎</button>
                </td>
              </tr>
              <tr v-if="!data.tasks?.length"><td colspan="8">در این بازه وظیفه‌ای نیست.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="analytics">
          <section>
            <h3>نمودار وضعیت</h3>
            <div class="bars">
              <div v-for="row in data.status_chart" :key="String(row.status_id)" class="bar">
                <span>{{ row.title }}</span>
                <i :style="{ width: barWidth(row.count) + '%', background: PALETTE[row.color ?? 7].c }"></i>
                <b>{{ fa(row.count) }}</b>
              </div>
            </div>
          </section>

          <section>
            <h3>عقب‌افتاده</h3>
            <ul>
              <li v-for="t in data.overdue_tasks" :key="t.id">{{ t.title }} · {{ (t.assignees||[]).map(a=>a.name).join('، ') }}</li>
              <li v-if="!data.overdue_tasks?.length">موردی نیست.</li>
            </ul>
          </section>

          <section>
            <h3>عملکرد پرسنل</h3>
            <div class="perf">
              <article v-for="row in data.per_staff" :key="row.staff.id">
                <span class="av"><img v-if="row.staff.avatar_url" :src="row.staff.avatar_url" alt="" /><template v-else>{{ row.staff.name.charAt(0) }}</template></span>
                <div>
                  <b>{{ row.staff.name }}</b>
                  <small>{{ fa(row.done) }} انجام · {{ fa(row.not_done) }} نه‌انجام · {{ fa(row.overdue) }} عقب · {{ fa(row.percent_done) }}٪</small>
                </div>
              </article>
              <p v-if="!data.per_staff?.length">داده‌ای نیست.</p>
            </div>
          </section>

          <section class="kpi">
            <article><span>درصد انجام</span><b>{{ fa(data.summary?.percent_done || 0) }}٪</b></article>
            <article><span>انجام‌شده</span><b>{{ fa(data.counts?.done || 0) }}</b></article>
            <article><span>انجام‌نشده</span><b>{{ fa(data.counts?.not_done || 0) }}</b></article>
            <article><span>عقب‌افتاده</span><b>{{ fa(data.counts?.overdue || 0) }}</b></article>
            <article class="best" v-if="data.best_performer">
              <span>بهترین عملکرد</span>
              <b>{{ data.best_performer.staff.name }}</b>
              <small>{{ fa(data.best_performer.percent_done) }}٪</small>
            </article>
          </section>
        </div>
      </div>
      <div v-else class="loading">در حال محاسبه…</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchReport, PALETTE } from './useStaffTasks'
import { fa, isoToJdn, d2j, MONTHS } from './jalali'

defineEmits(['close', 'open', 'edit'])
const periods = [
  { key: 'daily', label: 'روزانه' },
  { key: 'weekly', label: 'هفتگی' },
  { key: 'monthly', label: 'ماهانه' },
  { key: 'yearly', label: 'سالانه' },
]
const period = ref('monthly')
const data = ref(null)

const summaryCards = computed(() => {
  const s = data.value?.summary || {}
  return [
    { key: 'all', label: 'همه وظایف', value: s.all || 0 },
    { key: 'mine', label: 'وظایف من', value: s.mine || 0 },
    { key: 'byme', label: 'واگذارشده توسط من', value: s.assigned_by_me || 0 },
    { key: 'prog', label: 'در حال انجام', value: s.in_progress || 0 },
    { key: 'done', label: 'انجام‌شده', value: s.done || 0 },
    { key: 'nd', label: 'انجام‌نشده', value: s.not_done || 0 },
    { key: 'od', label: 'عقب‌افتاده', value: s.overdue || 0 },
  ]
})

const rangeLabel = computed(() => {
  if (!data.value) return ''
  return `${data.value.from} تا ${data.value.to}`
})

const maxBar = computed(() => Math.max(1, ...(data.value?.status_chart || []).map(r => r.count)))
const barWidth = n => Math.round((n / maxBar.value) * 100)
const priorityLabel = p => ({ low: 'کم', normal: 'عادی', high: 'زیاد', urgent: 'فوری' }[p] || p)
function pill(t) {
  const st = t.status
  if (!st) return { background: 'var(--st-muted-bg)', color: 'var(--st-muted)' }
  return { background: PALETTE[st.color].c, color: PALETTE[st.color].fg }
}
function shortIso(v) {
  if (!v) return '—'
  const iso = String(v).slice(0, 10)
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso) && Number(iso.slice(0, 4)) > 1500) {
    const [y, m, d] = d2j(isoToJdn(iso))
    return `${fa(d)} ${MONTHS[m - 1]}`
  }
  return fa(iso)
}

async function load() {
  data.value = null
  data.value = await fetchReport(period.value)
}
onMounted(load)
</script>

<style scoped>
.st-overlay{position:fixed;inset:0;background:var(--st-overlay);z-index:2147483010;display:flex;align-items:center;justify-content:center;padding:16px}
.st-modal.report{width:min(1100px,100%);max-height:calc(100vh - 32px);background:var(--st-surface);border-radius:18px;overflow:hidden;display:flex;flex-direction:column}
.head{padding:12px 16px;border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.col{display:flex;flex-direction:column;gap:2px}.h{font-size:15px;font-weight:800}.sub{font-size:11px;color:var(--st-muted)}
.row{display:flex;align-items:center;gap:8px}
.seg{display:flex;background:var(--st-muted-bg);border-radius:9px;padding:2px;gap:2px}
.seg button{font-family:inherit;border:0;border-radius:7px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;background:transparent;color:var(--st-muted)}
.seg button.on{background:var(--st-surface);color:var(--st-text);box-shadow:0 1px 3px rgba(15,23,42,.12)}
.st-x{border:0;background:var(--st-muted-bg);width:32px;height:32px;border-radius:9px;cursor:pointer;color:var(--st-muted);display:flex;align-items:center;justify-content:center}
.body{overflow:auto;padding:14px 16px;display:flex;flex-direction:column;gap:14px}
.sum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px}
.sum-grid article{padding:10px 12px;border-radius:12px;background:var(--st-surface-2);display:flex;flex-direction:column;gap:4px}
.sum-grid span{font-size:11px;color:var(--st-muted);font-weight:700}.sum-grid b{font-size:18px}
.table-wrap{overflow:auto;border:1px solid var(--st-line);border-radius:12px}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:8px 10px;border-bottom:1px solid var(--st-line);text-align:right;white-space:nowrap}
th{background:var(--st-surface-2);color:var(--st-muted);font-size:11px}
.who{display:inline-flex;align-items:center;gap:4px;margin-left:6px}
.av{width:20px;height:20px;border-radius:50%;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;background:#64748b;color:#fff;font-size:9px;font-weight:800}
.av img{width:100%;height:100%;object-fit:cover}
.pill{font-size:10px;font-weight:700;border-radius:999px;padding:2px 8px}
.ib{border:0;background:var(--st-muted-bg);border-radius:8px;width:28px;height:28px;cursor:pointer;margin-right:4px}
.analytics{display:grid;grid-template-columns:1.2fr 1fr;gap:12px}
.analytics section{border:1px solid var(--st-line);border-radius:12px;padding:12px;background:var(--st-surface-2)}
.analytics h3{margin:0 0 10px;font-size:13px}
.bars{display:flex;flex-direction:column;gap:7px}
.bar{display:grid;grid-template-columns:90px 1fr 28px;align-items:center;gap:8px;font-size:11px}
.bar i{display:block;height:8px;border-radius:999px;background:var(--st-accent)}
.perf{display:flex;flex-direction:column;gap:8px}
.perf article{display:flex;gap:8px;align-items:center}
.perf small{display:block;color:var(--st-muted);font-size:11px}
.kpi{display:grid!important;grid-template-columns:repeat(2,1fr);gap:8px}
.kpi article{padding:10px;border-radius:10px;background:var(--st-surface);display:flex;flex-direction:column;gap:3px}
.kpi .best{grid-column:1/-1;background:var(--st-accent-soft)}
.loading{padding:40px;text-align:center;color:var(--st-muted)}
@media(max-width:800px){.analytics{grid-template-columns:1fr}}
</style>
