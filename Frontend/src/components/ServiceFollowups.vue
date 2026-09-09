<template>
  <div class="fu" dir="rtl">
    <div class="fu-wrap">
      <!-- header -->
      <header class="fu-head">
        <div class="fu-head-text">
          <h1>پیگیری خدمات</h1>
          <p>پیگیری‌های ثبت‌شده برای نوبت‌ها و خدمات</p>
        </div>
        <div class="fu-head-actions">
          <div class="fu-chip-overdue">
            <span class="dot"></span>
            <span>{{ fa(overdueTotal) }} پیگیری عقب‌افتاده</span>
          </div>
          <button class="fu-btn-primary" @click="refresh">بروزرسانی</button>
        </div>
      </header>

      <!-- calendar -->
      <section class="fu-cal">
        <div class="fu-cal-top">
          <div class="fu-cal-title">
            <span class="m">{{ monthName }}</span>
            <span class="y">{{ fa(year) }}</span>
          </div>
          <button class="fu-btn-ghost" @click="goToday">امروز</button>
        </div>

        <div class="fu-strip-row">
          <button class="fu-nav" title="ماه قبل" @click="prevMonth">
            <span class="chev">›</span>
            <span class="lbl">{{ prevMonthName }}</span>
          </button>

          <div class="fu-strip-scroll">
            <div class="fu-strip">
              <button
                v-for="d in days"
                :key="d.day"
                class="fu-day"
                :class="{ sel: d.isSelected, today: d.isToday, overdue: d.overdue > 0 }"
                @click="selectedDay = d.day"
              >
                <span class="wd">{{ d.weekday }}</span>
                <span class="num">{{ fa(d.day) }}</span>
                <span class="badge-slot">
                  <span v-if="d.count" class="badge">{{ fa(d.count) }}</span>
                </span>
                <span v-if="d.overdue" class="od">{{ fa(d.overdue) }} معوق</span>
              </button>
            </div>
          </div>

          <button class="fu-nav" title="ماه بعد" @click="nextMonth">
            <span class="chev">‹</span>
            <span class="lbl">{{ nextMonthName }}</span>
          </button>
        </div>

        <div class="fu-legend">
          <span><i class="sw sw-sel"></i>روز انتخاب‌شده</span>
          <span><i class="sw sw-od"></i>پیگیری در انتظار و گذشته (معوق)</span>
          <span><i class="sw sw-today"></i>امروز</span>
        </div>
      </section>

      <!-- list header -->
      <div class="fu-list-head">
        <div class="fu-list-title">
          <span class="fu-list-eyebrow">سررسیدهای پیگیری</span>
          <h2>{{ selectedLabel }}</h2>
          <span>{{ selectedCountLabel }}</span>
        </div>
        <div class="fu-tabs">
          <button
            v-for="f in filters"
            :key="f.key"
            class="fu-tab"
            :class="{ on: filter === f.key }"
            @click="filter = f.key"
          >{{ f.key }} ({{ fa(f.count) }})</button>
        </div>
      </div>

      <!-- items -->
      <div class="fu-items">
        <article
          v-for="it in visibleItems"
          :key="it.id"
          class="fu-item"
          :class="{ overdue: isItemOverdue(it) }"
        >
          <button class="fu-avatar" type="button" title="مشاهده خلاصه پرونده" @click="openPatientSummary(it)">
            <img v-if="it.avatar" :src="it.avatar" :alt="it.name"><span v-else>{{ it.name.trim().charAt(0) }}</span>
          </button>

          <div class="fu-meta">
            <div class="fu-meta-top">
              <span class="name">{{ it.name }}</span>
              <span class="service">{{ it.service }}</span>
              <span v-if="isItemOverdue(it)" class="tag-od">عقب‌افتاده</span>
            </div>
            <div class="fu-meta-sub">
              <span>{{ it.phone }}</span>
              <span class="sep"></span>
              <span>سررسید {{ selectedLabel }}</span>
            </div>
          </div>

          <select class="fu-select" v-model="it.status">
            <option v-for="s in STATUSES" :key="s" :value="s">{{ s }}</option>
          </select>

          <input class="fu-input" v-model="it.note" placeholder="شرح اقدام" />

          <button class="fu-btn-primary" @click="save(it)">
            {{ it.saved ? 'ثبت شد' : 'ثبت' }}
          </button>
        </article>

        <div v-if="!visibleItems.length" class="fu-empty">
          <div class="t">پیگیری‌ای برای این روز ثبت نشده</div>
          <div class="s">روز دیگری از نوار بالا را انتخاب کنید</div>
        </div>
      </div>
    </div>

    <div v-if="patientModalOpen" class="fu-profile-overlay" @click.self="patientModalOpen = false">
      <section class="fu-profile-modal" role="dialog" aria-modal="true">
        <header><div><small>خلاصه پرونده مراجعه‌کننده</small><h3>{{ patientSummary?.name || 'بیمار' }}</h3><p>{{ patientSummary?.file_number || '-' }} · {{ patientSummary?.phone || '-' }}</p></div><button type="button" @click="patientModalOpen = false">×</button></header>
        <div v-if="patientSummaryLoading" class="fu-profile-loading">در حال دریافت پرونده...</div>
        <div v-else class="fu-profile-body"><img v-if="patientSummary?.avatar_url" :src="patientSummary.avatar_url" class="fu-profile-image"><div v-else class="fu-profile-letter">{{ (patientSummary?.name || 'ب').charAt(0) }}</div><div class="fu-profile-grid"><span>جنسیت <b>{{ patientSummary?.gender || '-' }}</b></span><span>شماره پرونده <b>{{ patientSummary?.file_number || '-' }}</b></span><span>اعتبار <b>{{ Number(patientSummary?.wallet_balance || 0).toLocaleString('fa-IR') }}</b></span><span>بدهی <b>{{ Number(patientSummary?.outstanding_debt || 0).toLocaleString('fa-IR') }}</b></span></div><p><b>سوابق پزشکی:</b> {{ patientSummary?.medical_history || '-' }}</p></div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import axios from 'axios'

/* ---------- تقویم شمسی ---------- */
const MONTHS = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند']
const WD = ['ش','ی','د','س','چ','پ','ج']            // 0 = شنبه
const STATUSES = ['در انتظار','تماس گرفته شد','نوبت داده شد','انجام شد','لغو شد']
const PENDING = ['در انتظار','تماس گرفته شد']

const SERVICES = ['ژل لب','بوتاکس مصپورت','لیزر صورت','مزوتراپی مو','هایفوتراپی','پاکسازی پوست','تزریق چربی']
const NAMES = ['سارا کریمی','مریم موسوی','نگار احمدی','فرهاد رضایی','الهام صادقی','حسین نوری','پریسا کاظمی','محمد شریفی','لیلا بهرامی','رضا موحد','آیدا فراهانی','سپیده رستمی']

const jalaliParts = (value = new Date()) => {
  const parts = new Intl.DateTimeFormat('en-u-ca-persian', { year: 'numeric', month: 'numeric', day: 'numeric' }).formatToParts(new Date(value))
  const pick = (type) => Number(parts.find(part => part.type === type)?.value || 0)
  return { y: pick('year'), m: pick('month'), d: pick('day') }
}
const TODAY = jalaliParts()

const fa = (n) => String(n).replace(/\d/g, (x) => '۰۱۲۳۴۵۶۷۸۹'[x])
const monthLen = (y, m) => (m <= 6 ? 31 : m <= 11 ? 30 : 29)

/** ایندکس روز هفته اولِ ماه (۰ = شنبه). ۱۴۰۵/۰۱/۰۱ = شنبه */
function firstWeekday(y, m) {
  let off = 0
  for (let i = 1; i < m; i++) off += monthLen(y, i)
  return (((off + (y - 1405) * 2) % 7) + 7) % 7
}

function isPast(y, m, d) {
  if (y !== TODAY.y) return y < TODAY.y
  if (m !== TODAY.m) return m < TODAY.m
  return d < TODAY.d
}

const cache = reactive({})
const patientModalOpen = ref(false)
const patientSummaryLoading = ref(false)
const patientSummary = ref(null)
const key = (y, m, d) => `${y}-${m}-${d}`

function dayItems(y, m, d) {
  const k = key(y, m, d)
  return cache[k] || []
}

/* ---------- state ---------- */
const year = ref(TODAY.y)
const month = ref(TODAY.m)
const selectedDay = ref(TODAY.d)
const filter = ref('همه')

const monthName = computed(() => MONTHS[month.value - 1])
const prevMonthName = computed(() => MONTHS[(month.value + 10) % 12])
const nextMonthName = computed(() => MONTHS[month.value % 12])

const overdueCount = (y, m, d) =>
  isPast(y, m, d) ? dayItems(y, m, d).filter((i) => PENDING.includes(i.status)).length : 0

const days = computed(() => {
  const len = monthLen(year.value, month.value)
  const fw = firstWeekday(year.value, month.value)
  const out = []
  for (let d = 1; d <= len; d++) {
    out.push({
      day: d,
      weekday: WD[(fw + d - 1) % 7],
      count: dayItems(year.value, month.value, d).length,
      overdue: overdueCount(year.value, month.value, d),
      isToday: year.value === TODAY.y && month.value === TODAY.m && d === TODAY.d,
      isSelected: d === selectedDay.value,
    })
  }
  return out
})

const overdueTotal = computed(() => days.value.reduce((s, d) => s + d.overdue, 0))
const dayList = computed(() => dayItems(year.value, month.value, selectedDay.value))
const pendingList = computed(() => dayList.value.filter((i) => PENDING.includes(i.status)))
const doneList = computed(() => dayList.value.filter((i) => !PENDING.includes(i.status)))

const filters = computed(() => [
  { key: 'همه', count: dayList.value.length },
  { key: 'در انتظار', count: pendingList.value.length },
  { key: 'انجام‌شده', count: doneList.value.length },
])

const visibleItems = computed(() =>
  filter.value === 'در انتظار' ? pendingList.value : filter.value === 'انجام‌شده' ? doneList.value : dayList.value
)

const selectedLabel = computed(() => `${fa(selectedDay.value)} ${monthName.value} ${fa(year.value)}`)
const selectedCountLabel = computed(() => {
  if (!dayList.value.length) return 'بدون پیگیری'
  const od = overdueCount(year.value, month.value, selectedDay.value)
  return `${fa(dayList.value.length)} پیگیری${od ? ' • ' + fa(od) + ' عقب‌افتاده' : ''}`
})

const isItemOverdue = (it) => isPast(it.due.y, it.due.m, it.due.d) && PENDING.includes(it.status)

function clampDay() {
  const len = monthLen(year.value, month.value)
  if (selectedDay.value > len) selectedDay.value = len
}
function prevMonth() {
  if (month.value === 1) { year.value--; month.value = 12 } else month.value--
  selectedDay.value = 1; clampDay()
}
function nextMonth() {
  if (month.value === 12) { year.value++; month.value = 1 } else month.value++
  selectedDay.value = 1; clampDay()
}
function goToday() {
  year.value = TODAY.y; month.value = TODAY.m; selectedDay.value = TODAY.d
}
const apiStatus = { 'در انتظار':'pending', 'تماس گرفته شد':'called', 'نوبت داده شد':'booked', 'انجام شد':'done', 'لغو شد':'declined' }
const uiStatus = Object.fromEntries(Object.entries(apiStatus).map(([label, value]) => [value, label]))
async function save(it) {
  await axios.patch(`/api/service-followups/${it.id}`, { status: apiStatus[it.status] || 'pending', action_note: it.note || '' })
  it.saved = true
}
async function openPatientSummary(item) {
  patientModalOpen.value = true
  patientSummaryLoading.value = true
  patientSummary.value = { ...(item.patient || {}), phone: item.phone, name: item.name, avatar_url: item.avatar }
  try {
    const params = item.patient?.file_number ? { file_number: item.patient.file_number } : { phone: item.phone }
    const { data } = await axios.get('/api/patients/search', { params })
    const patient = Array.isArray(data) ? data[0] : null
    if (patient) patientSummary.value = { ...patient, name: [patient.first_name, patient.last_name].filter(Boolean).join(' ') }
  } finally { patientSummaryLoading.value = false }
}
async function refresh() {
  Object.keys(cache).forEach((k) => delete cache[k])
  const { data } = await axios.get('/api/service-followups')
  data.forEach((row) => {
    const due = jalaliParts(row.due_date)
    const patient = row.patient || {}
    const item = { id: row.id, name: [patient.first_name, patient.last_name].filter(Boolean).join(' ') || row.patient_name || 'بیمار', service: row.service_name, phone: patient.phone || row.patient_phone || '', avatar: patient.avatar_url || '', patient, status: uiStatus[row.status] || 'در انتظار', note: row.action_note || '', saved: Boolean(row.actioned_at), due }
    const bucket = key(due.y, due.m, due.d)
    if (!cache[bucket]) cache[bucket] = []
    cache[bucket].push(item)
  })
}
onMounted(refresh)
</script>

<style scoped>
/* فونت: وزیرمتن (در پروژه لوکال نصبش کنید) */
@import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');

.fu {
  --accent: #2f5bea;
  --accent-dark: #2449c4;
  --accent-soft: #eef2ff;
  --red: #d8323f;
  --red-dark: #b7242f;
  --red-deep: #7a1220;
  --red-soft: #fce3e6;
  --ink: #0e1726;
  --muted: #6b7789;
  --faint: #8a93a3;
  --line: #e6e9ef;
  --bg: #f5f6f8;
  font-family: Vazirmatn, system-ui, sans-serif;
  background: var(--bg);
  color: var(--ink);
  min-height: 100vh;
  padding: 40px 32px 72px;
}
.fu *, .fu *::before, .fu *::after { box-sizing: border-box; }
.fu-wrap { max-width: 1080px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }

/* header */
.fu-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
.fu-head-text { display: flex; flex-direction: column; gap: 6px; }
.fu-eyebrow { font-size: 12px; letter-spacing: .06em; color: var(--faint); font-weight: 500; }
.fu-head-text h1 { margin: 0; font-size: 30px; font-weight: 700; letter-spacing: -.01em; }
.fu-head-text p { margin: 0; font-size: 14px; color: var(--muted); }
.fu-head-actions { display: flex; align-items: center; gap: 10px; }
.fu-chip-overdue {
  display: flex; align-items: center; gap: 8px; white-space: nowrap; flex-shrink: 0;
  background: #fff; border: 1px solid var(--line); border-radius: 12px;
  padding: 9px 14px; font-size: 13px; color: var(--muted);
}
.fu-chip-overdue .dot { width: 7px; height: 7px; border-radius: 99px; background: var(--red); }

.fu-btn-primary {
  border: none; background: var(--accent); color: #fff; font: inherit; font-size: 13.5px; font-weight: 600;
  padding: 11px 22px; border-radius: 12px; cursor: pointer; box-shadow: 0 6px 16px rgba(47,91,234,.24);
}
.fu-btn-primary:hover { background: var(--accent-dark); }
.fu-btn-ghost {
  border: 1px solid var(--line); background: #fff; color: #3b475c; font: inherit; font-size: 12.5px;
  font-weight: 600; padding: 7px 14px; border-radius: 10px; cursor: pointer;
}
.fu-btn-ghost:hover { border-color: #c9d0db; background: #fafbfc; }

/* calendar */
.fu-cal {
  background: #fff; border: 1px solid var(--line); border-radius: 20px;
  padding: 18px 18px 20px; box-shadow: 0 1px 2px rgba(16,24,40,.04);
}
.fu-cal-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding: 0 4px; }
.fu-cal-title { display: flex; align-items: baseline; gap: 10px; }
.fu-cal-title .m { font-size: 18px; font-weight: 700; }
.fu-cal-title .y { font-size: 14px; color: #9aa2b1; }

.fu-strip-row { display: flex; align-items: stretch; gap: 10px; }
.fu-nav {
  flex: 0 0 46px; border: 1px solid var(--line); background: #fff; border-radius: 14px; cursor: pointer;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--muted);
}
.fu-nav:hover { background: #f6f8ff; border-color: #c7d3f7; color: var(--accent); }
.fu-nav .chev { font-size: 16px; line-height: 1; }
.fu-nav .lbl { font-size: 9.5px; font-weight: 600; writing-mode: vertical-rl; letter-spacing: .04em; }

.fu-strip-scroll { flex: 1 1 auto; overflow-x: auto; padding: 2px 2px 8px; }
.fu-strip { display: flex; gap: 8px; min-width: max-content; }
.fu-strip-scroll::-webkit-scrollbar { height: 6px; }
.fu-strip-scroll::-webkit-scrollbar-thumb { background: #d7dbe2; border-radius: 99px; }

.fu-day {
  flex: 0 0 auto; width: 62px; border: 1px solid var(--line); background: #fff; color: #2a3546;
  border-radius: 14px; padding: 9px 0 8px; cursor: pointer; font: inherit;
  display: flex; flex-direction: column; align-items: center; gap: 5px; transition: transform .12s ease;
}
.fu-day:hover { transform: translateY(-2px); }
.fu-day .wd { font-size: 10.5px; font-weight: 500; opacity: .62; }
.fu-day .num { font-size: 19px; font-weight: 700; line-height: 1.05; }
.fu-day .badge-slot { min-height: 19px; display: flex; align-items: center; }
.fu-day .badge { font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 99px; background: #edeff3; color: #5b6678; }
.fu-day .od { font-size: 9.5px; font-weight: 700; letter-spacing: .02em; opacity: .92; }

.fu-day.today { border-color: var(--accent); }
.fu-day.overdue {
  background: var(--red); border-color: var(--red-dark); color: #fff;
  box-shadow: 0 4px 12px rgba(199,48,61,.28);
}
.fu-day.overdue .badge { background: #fff; color: var(--red-dark); }
.fu-day.overdue.today { border-color: var(--red-deep); }
.fu-day.sel { background: var(--accent); border-color: transparent; color: #fff; }
.fu-day.sel .badge { background: rgba(255,255,255,.24); color: #fff; }
.fu-day.sel.overdue {
  background: var(--red-dark); border-color: var(--red-deep);
  box-shadow: 0 0 0 3px rgba(216,50,63,.28);
}
.fu-day.sel.overdue .badge { background: #fff; color: var(--red-dark); }

.fu-legend {
  display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
  padding: 12px 6px 0; margin-top: 6px; border-top: 1px solid #f1f3f6;
  font-size: 11.5px; color: var(--faint);
}
.fu-legend span { display: flex; align-items: center; gap: 6px; }
.fu-legend .sw { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }
.sw-sel { background: var(--accent); }
.sw-od { background: var(--red); }
.sw-today { background: #fff; border: 1.5px solid var(--accent); }

/* list header */
.fu-list-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 0 4px; }
.fu-list-title { display: flex; align-items: baseline; gap: 10px; }
.fu-list-title h2 { margin: 0; font-size: 19px; font-weight: 700; }
.fu-list-title span { font-size: 13.5px; color: var(--faint); }
.fu-tabs { display: flex; gap: 6px; background: #edeff3; padding: 4px; border-radius: 12px; }
.fu-tab {
  border: none; background: transparent; color: var(--muted); font: inherit; font-size: 13px; font-weight: 600;
  padding: 8px 16px; border-radius: 9px; cursor: pointer;
}
.fu-tab.on { background: #fff; color: #1b2436; }

/* items */
.fu-items { display: flex; flex-direction: column; gap: 12px; }
.fu-item {
  background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px 20px;
  display: grid; grid-template-columns: auto 1fr 200px 260px auto; align-items: center; gap: 18px;
  box-shadow: 0 1px 2px rgba(16,24,40,.04); animation: fu-rise .28s ease both;
}
.fu-item:hover { border-color: #c7d3f7; }
.fu-item.overdue { border-color: #f2c4ca; }
@keyframes fu-rise { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

.fu-avatar {
  width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 700; background: var(--accent-soft); color: var(--accent);
}
.fu-avatar{border:0;cursor:pointer;overflow:hidden;padding:0}.fu-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.fu-profile-overlay{position:fixed;inset:0;z-index:1000004;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.55);backdrop-filter:blur(5px)}.fu-profile-modal{width:min(680px,96vw);max-height:88vh;overflow:auto;border:1px solid rgba(255,255,255,.75);border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.34);direction:rtl}.fu-profile-modal>header{display:flex;align-items:flex-start;justify-content:space-between;padding:17px 19px;border-bottom:1px solid #e2e8f0;background:#f8fafc}.fu-profile-modal small{color:#2563eb;font-size:10px;font-weight:900}.fu-profile-modal h3{margin:4px 0;color:#0f172a;font-size:20px}.fu-profile-modal header p{margin:0;color:#64748b;font-size:11px;font-weight:800}.fu-profile-modal header button{width:36px;height:36px;border:0;border-radius:11px;background:#e2e8f0;color:#475569;font-size:22px;cursor:pointer}.fu-profile-loading{min-height:180px;display:grid;place-items:center;color:#64748b;font-weight:900}.fu-profile-body{display:grid;grid-template-columns:auto 1fr;gap:17px;padding:19px}.fu-profile-image,.fu-profile-letter{width:74px;height:74px;border-radius:20px;object-fit:cover;background:#eff6ff;color:#2563eb;display:grid;place-items:center;font-size:28px;font-weight:900}.fu-profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.fu-profile-grid span{display:grid;gap:4px;padding:9px 10px;border:1px solid #e2e8f0;border-radius:11px;background:#f8fafc;color:#64748b;font-size:10px}.fu-profile-grid b{color:#1e293b;font-size:12px}.fu-profile-body>p{grid-column:1/-1;margin:0;padding:12px;border:1px solid #e2e8f0;border-radius:12px;color:#475569;font-size:12px;line-height:1.9}@media(max-width:520px){.fu-profile-body{grid-template-columns:1fr}.fu-profile-image,.fu-profile-letter{justify-self:center}}
.fu-item.overdue .fu-avatar { background: var(--red-soft); color: #c22a36; }

.fu-meta { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.fu-meta-top { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.fu-meta-top .name { font-size: 15.5px; font-weight: 700; }
.fu-meta-top .service { font-size: 11.5px; font-weight: 600; padding: 3px 9px; border-radius: 8px; background: #f1f3f6; color: #5b6678; }
.fu-meta-top .tag-od { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 8px; background: var(--red-soft); color: #c22a36; }
.fu-meta-sub { display: flex; align-items: center; gap: 12px; font-size: 12.5px; color: var(--faint); }
.fu-meta-sub .sep { width: 3px; height: 3px; border-radius: 99px; background: #c9d0db; }

.fu-select, .fu-input {
  width: 100%; border: 1px solid #e1e5ec; border-radius: 12px; padding: 11px 14px;
  font: inherit; font-size: 13.5px; background: #fbfcfd; color: #2a3546;
}
.fu-select { appearance: none; font-weight: 600; cursor: pointer; }
.fu-input::placeholder { color: #9aa2b1; }

.fu-empty {
  background: #fff; border: 1px dashed #dde2ea; border-radius: 18px;
  padding: 56px 24px; text-align: center; color: var(--faint);
}
.fu-empty .t { font-size: 15px; font-weight: 600; color: #5b6678; margin-bottom: 6px; }
.fu-empty .s { font-size: 13px; }

@media (max-width: 900px) {
  .fu { padding: 24px 16px 56px; }
  .fu-item { grid-template-columns: auto 1fr; grid-auto-rows: auto; }
  .fu-item .fu-select, .fu-item .fu-input, .fu-item .fu-btn-primary { grid-column: 1 / -1; }
}
</style>
