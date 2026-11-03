// وضعیت مشترک صفحهٔ وظایف پرسنل.
// فعلاً قالب استاتیک است: داده‌ها نمونه‌اند، فقط در حافظهٔ مرورگر تغییر می‌کنند و با رفرش صفحه به حالت اول برمی‌گردند.
import { reactive } from 'vue'
import { deadlineTs, todayJdn } from './jalali'

export const PALETTE = [
  { c: '#15803d', fg: '#fff' }, { c: '#eab308', fg: '#422006' }, { c: '#7c3aed', fg: '#fff' }, { c: '#b91c1c', fg: '#fff' },
  { c: '#2563eb', fg: '#fff' }, { c: '#ea580c', fg: '#fff' }, { c: '#0f766e', fg: '#fff' }, { c: '#db2777', fg: '#fff' },
]

export const DEFAULT_STATUSES = [
  { id: 'done', title: 'انجام شد', color: 0, effect: 'plus', amount: 100000, builtin: true },
  { id: 'incomplete', title: 'تکمیل نشد', color: 1, effect: 'minus', amount: 50000, builtin: true },
  { id: 'follow', title: 'پیگیری', color: 2, effect: 'none', amount: 0, builtin: true },
  { id: 'notdone', title: 'انجام نشد', color: 3, effect: 'minus', amount: 100000, builtin: true },
]

const SAMPLE_STAFF = [
  { id: 's1', name: 'سارا محمدی', color: '#6366f1' },
  { id: 's2', name: 'مریم احمدی', color: '#0ea5e9' },
  { id: 's3', name: 'نگار رضایی', color: '#f97316' },
  { id: 's4', name: 'علی کریمی', color: '#14b8a6' },
]

// روزها نسبت به امروز تعریف شده‌اند تا هفتهٔ جاری همیشه نمونه داشته باشد.
const SAMPLE_TASKS = [
  { offset: -3, staffId: 's1', title: 'تماس با بیماران نوبت فردا', statusId: 'done' },
  { offset: -3, staffId: 's3', title: 'شارژ مواد مصرفی اتاق لیزر', statusId: 'incomplete' },
  { offset: -2, staffId: 's2', title: 'پیگیری بیماران جلسهٔ دوم بوتاکس', statusId: 'follow' },
  { offset: -2, staffId: 's4', title: 'بایگانی پرونده‌های کاغذی', statusId: 'notdone', auto: true },
  { offset: -1, staffId: 's1', title: 'ارسال پیامک تبریک تولد', statusId: 'done' },
  { offset: -1, staffId: 's2', title: 'هماهنگی نوبت پزشک مهمان', statusId: 'done' },
  { offset: 0, staffId: 's1', title: 'تماس با بیماران نوبت فردا', note: 'لیست از صفحهٔ وقت‌دهی گرفته شود', expiry: '18:00' },
  { offset: 0, staffId: 's3', title: 'ضدعفونی و چک‌لیست اتاق‌ها', statusId: 'done' },
  { offset: 0, staffId: 's4', title: 'به‌روزرسانی قیمت خدمات در سایت', expiry: '20:00' },
  { offset: 1, staffId: 's2', title: 'پیگیری رضایت بیماران هفتهٔ قبل', expiry: '17:00' },
  { offset: 1, staffId: 's3', title: 'سفارش ژل و کرم‌های مصرفی' },
  { offset: 2, staffId: 's1', title: 'جلسهٔ هماهنگی پذیرش' },
  { offset: 2, staffId: 's4', title: 'گزارش هزینه‌های ماه', expiry: '16:00' },
  { offset: 4, staffId: 's2', title: 'تماس با سرنخ‌های کمپین پاییزه' },
  { offset: 5, staffId: 's3', title: 'آموزش دستگاه جدید به پرسنل' },
  { offset: 9, staffId: 's1', title: 'بررسی تیکت‌های باز' },
  { offset: -9, staffId: 's4', title: 'مرتب‌سازی انبار', statusId: 'done' },
  { offset: -12, staffId: 's2', title: 'تماس با بیماران غایب', statusId: 'incomplete' },
]

function sampleTasks() {
  const today = todayJdn()
  return SAMPLE_TASKS.map((t, i) => {
    const day = today + t.offset
    return {
      id: 'sample' + (i + 1), staffId: t.staffId, title: t.title, note: t.note || '', day,
      statusId: t.statusId || null, auto: !!t.auto,
      expiry: { enabled: !!t.expiry, day, time: t.expiry || '18:00', statusId: 'notdone' },
    }
  })
}

/*
 * شکل داده‌ها (day = عدد JDN، jalali.js را ببینید):
 * staff:   { id, name, color? }
 * status:  { id, title, color: 0-7 (اندیس PALETTE), effect: 'plus'|'minus'|'none', amount: تومان, builtin }
 * task:    { id, staffId, title, note, day, statusId|null, auto, expiry: { enabled, day, time: 'HH:MM', statusId } }
 */
const state = reactive({
  staff: SAMPLE_STAFF,
  statuses: DEFAULT_STATUSES.map(s => ({ ...s })),
  expiryDefault: 'notdone',
  tasks: sampleTasks(),
  holidays: new Set(), // تعطیلات رسمی؛ جمعه‌ها همیشه تعطیل‌اند
})

let seq = Date.now()
const uid = p => p + (++seq).toString(36)

export const statusById = id => state.statuses.find(s => s.id === id)
export const effectOf = st => (!st || st.effect === 'none' ? 0 : st.effect === 'plus' ? +st.amount || 0 : -(+st.amount || 0))

// انقضای خودکار؛ در نسخهٔ داینامیک همین قانون باید در بک‌اند (cron) هم اجرا شود.
export function runExpiry() {
  const now = Date.now()
  state.tasks.forEach(t => {
    if (!t.statusId && t.expiry?.enabled && statusById(t.expiry.statusId) && deadlineTs(t.expiry.day, t.expiry.time) <= now) {
      t.statusId = t.expiry.statusId
      t.auto = true
    }
  })
}

export function saveTask(draft) {
  const prev = state.tasks.find(t => t.id === draft.id)
  const task = { ...draft, title: draft.title.trim(), statusId: draft.statusId || null }
  task.auto = prev && prev.statusId === task.statusId ? prev.auto : false
  if (!task.id) task.id = uid('t')
  const i = state.tasks.findIndex(t => t.id === task.id)
  if (i >= 0) state.tasks.splice(i, 1, task); else state.tasks.push(task)
  runExpiry()
}

export function deleteTask(id) {
  state.tasks = state.tasks.filter(t => t.id !== id)
}

export function addStatus() {
  state.statuses.push({ id: uid('st'), title: 'وضعیت جدید', color: state.statuses.length % PALETTE.length, effect: 'none', amount: 0, builtin: false })
}
export function removeStatus(id) {
  state.statuses = state.statuses.filter(s => s.id !== id)
  const fb = state.statuses[0]?.id || ''
  if (state.expiryDefault === id) state.expiryDefault = fb
  state.tasks.forEach(t => {
    if (t.statusId === id) t.statusId = null
    if (t.expiry?.statusId === id) t.expiry.statusId = fb
  })
}

export function useStaffTasks() {
  return { state, saveTask, deleteTask, addStatus, removeStatus, runExpiry, statusById, effectOf }
}
