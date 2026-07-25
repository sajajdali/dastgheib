<template>
  <div class="hr-page">
    <div v-if="isLoading" class="attendance-loading" role="status" aria-live="polite">
      <span class="attendance-spinner"></span>
      <strong>در حال بارگذاری حضور و غیاب</strong>
      <small>لطفاً چند لحظه صبر کنید...</small>
    </div>

    <!-- سایدبار -->
    <aside class="staff-sidebar">

      <div class="sidebar-header">

        <div>
          <h2>حضور و غیاب</h2>
          
        </div>

      </div>

      <!-- لیست پرسنل -->
      <div class="staff-list">
        <section v-if="visibleDoctors.length" class="resource-group">
          <header><span class="group-icon doctor">✚</span><strong>پزشکان</strong><b>{{ visibleDoctors.length.toLocaleString('fa-IR') }}</b></header>
          <button
            v-for="staff in visibleDoctors"
            :key="staff.id"
            type="button"
            class="staff-card"
            :class="{ active: selectedStaff?.id === staff.id }"
            @click="selectStaff(staff)"
          >
            <span class="avatar">
              <img v-if="staff.image" :src="staff.image" alt="">
              <span v-else>{{ staff.name.charAt(0) }}</span>
            </span>
            <span class="staff-info"><h4>{{ staff.name }}</h4><span>{{ staff.months.length }} ماه ثبت شده</span></span>
          </button>
        </section>

        <section v-if="visibleStaff.length" class="resource-group">
          <header><span class="group-icon staff">♙</span><strong>پرسنل</strong><b>{{ visibleStaff.length.toLocaleString('fa-IR') }}</b></header>
          <button
            v-for="staff in visibleStaff"
            :key="staff.id"
            type="button"
            class="staff-card"
            :class="{ active: selectedStaff?.id === staff.id }"
            @click="selectStaff(staff)"
          >
            <span class="avatar">
              <img v-if="staff.image" :src="staff.image" alt="">
              <span v-else>{{ staff.name.charAt(0) }}</span>
            </span>
            <span class="staff-info"><h4>{{ staff.name }}</h4><span>{{ staff.months.length }} ماه ثبت شده</span></span>
          </button>
        </section>

        <div v-if="!visibleResources.length" class="no-resource-access">
          رکورد حضور و غیاب متصل به حساب شما پیدا نشد.
        </div>

      </div>

    </aside>

    <!-- محتوا -->
    <main class="main-content">

      <div
        v-if="selectedStaff"
        class="content-wrapper"
      >

        <!-- هدر -->
        <div class="top-header">

          <div>
            <h1>{{ selectedStaff.name }}</h1>
            <p>سیستم مدیریت حضور و غیاب</p>
          </div>

          <button
            v-if="canManageAttendance"
            class="month-btn"
            type="button"
            title="افزودن ماه جدید"
            aria-label="افزودن ماه جدید"
            @click="showMonthModal = true"
          >
            <span aria-hidden="true">+</span>
          </button>

        </div>

        <div v-if="selectedStaff.months.length" class="month-tabs-bar">
          <div class="month-tabs">
            <button
              v-for="month in recentMonths"
              :key="month.id"
              type="button"
              :class="{ active: activeMonth?.id === month.id }"
              @click="selectedMonthId = month.id"
            >
              <strong>{{ monthLabel(month) }}</strong>
              <small>{{ month.year || extractYear(month.name) }}</small>
            </button>
            <div v-if="oldMonths.length" ref="oldMonthsMenu" class="old-months-menu">
              <button
                type="button"
                class="old-months-trigger"
                :class="{ active: activeMonthIsOld }"
                @click.stop="showOldMonths = !showOldMonths"
              >
                <strong>ماه‌های قدیمی</strong>
                <small>{{ oldMonths.length.toLocaleString('fa-IR') }} ماه ▾</small>
              </button>
              <div v-if="showOldMonths" class="old-months-list">
                <button
                  v-for="month in oldMonths"
                  :key="month.id"
                  type="button"
                  :class="{ active: activeMonth?.id === month.id }"
                  @click="selectOldMonth(month)"
                >
                  <strong>{{ monthLabel(month) }}</strong>
                  <small>{{ month.year || extractYear(month.name) }}</small>
                </button>
              </div>
            </div>
          </div>
          <span>{{ sortedMonths.length.toLocaleString('fa-IR') }} ماه ثبت‌شده</span>
        </div>

        <!-- اگر ماهی وجود نداشت -->
        <div
          v-if="selectedStaff.months.length === 0"
          class="empty-months"
        >

          <h2>هنوز ماهی ثبت نشده</h2>

          <p>
            برای شروع روی دکمه افزودن ماه جدید کلیک کنید
          </p>

        </div>

        <!-- لیست ماه ها -->
        <div
          v-else
          class="months-container"
        >

          <div v-if="activeMonth" class="month-card">

            <!-- هدر ماه -->
            <div class="month-header">

              <div class="month-top">

                <div class="month-title">
                  <h2>{{ activeMonth.name }}</h2>
                  <span>تنظیمات ماه</span>
                </div>

                <button
                  v-if="canManageAttendance"
                  class="delete-month-btn"
                  @click="deleteMonth(selectedStaff.months.indexOf(activeMonth))"
                >
                  🗑
                </button>

              </div>

              <!-- تنظیمات -->
              <div class="resource-pay-summary">
                <span>نرخ ساعت کاری: <b>{{ formatMoney(selectedStaff.hourly_rate) }}</b></span>
                <span>اضافه‌کاری: <b>{{ formatMoney(selectedStaff.overtime_hourly_rate) }}</b></span>
                <span>کسرکاری: <b>{{ formatMoney(selectedStaff.shortage_hourly_deduction) }}</b></span>
                <span>کسر عدم حضور: <b>{{ formatMoney(selectedStaff.absence_deduction) }}</b></span>
                <span>کسرکاری مجاز: <b>{{ Number(selectedStaff.allowed_shortage_hours || 0) }} ساعت</b></span>
                <small>این مقادیر فقط از بخش «منابع» قابل تغییر هستند.</small>
              </div>

            </div>

            <!-- جدول -->
            <div class="table-wrapper">

              <table>

                <thead>

                  <tr>
                    <th>روز</th>
                    <th>ورود</th>
                    <th>وضعیت حضور</th>
                    <th>تأیید کارفرما</th>
                    <th>درخواست مرخصی</th>
                    <th>خروج</th>
                    <th>ساعت کار</th>
                    <th>اضافه / کسری</th>
                    <th>محاسبه مبلغ</th>
                  </tr>

                </thead>

                <tbody>

                  <tr
                    v-for="day in activeMonth.days"
                    :key="day.day"
                  >

                    <td data-label="روز">{{ day.day }}</td>

                    <td data-label="ورود">

                      <input
                        type="time"
                        v-model="day.in"
                        :disabled="!attendanceEnabled || !canClockAttendance"
                        @focus="setCurrentTime(day, 'in')"
                        @input="calculateDay(activeMonth, day)"
                      />

                    </td>

                    <td data-label="وضعیت حضور">
                      <label class="absence-toggle" :class="{ absent: day.absent }">
                        <input v-model="day.absent" type="checkbox" :disabled="!attendanceEnabled" @change="onAbsenceChanged(activeMonth, day)">
                        <span>{{ day.absent ? 'غایب' : 'حاضر' }}</span>
                      </label>
                    </td>

                    <td data-label="تأیید کارفرما">
                      <label class="approval-toggle" :class="{ approved: day.employerApproved }">
                        <input v-model="day.employerApproved" type="checkbox" :disabled="!attendanceEnabled || !canManageAttendance" @change="calculateDay(activeMonth, day)">
                        <span>{{ day.employerApproved ? 'تأیید شد' : 'در انتظار تأیید' }}</span>
                      </label>
                    </td>

                    <td data-label="درخواست مرخصی">
                      <div class="leave-request-cell">
                        <button
                          type="button"
                          class="leave-request-btn"
                          :class="{ pending: day.leaveRequestTitle && !day.leaveApproved, approved: day.leaveApproved }"
                          :disabled="!attendanceEnabled"
                          @click="openLeaveModal(activeMonth, day)"
                        >
                          {{ day.leaveApproved ? 'مرخصی تأیید شد' : day.leaveRequestTitle ? 'درخواست در انتظار تأیید' : 'درخواست مرخصی' }}
                        </button>
                      </div>
                    </td>

                    <td data-label="خروج">

                      <input
                        type="time"
                        v-model="day.out"
                        :disabled="!attendanceEnabled || !canClockAttendance"
                        @input="calculateDay(activeMonth, day)"
                      />

                    </td>

                    <td data-label="ساعت کار">
                      {{ day.workedHours }}
                    </td>

                    <td data-label="اضافه / کسری">

                      <span
                        :class="{
                          overtime: day.diff > 0,
                          minus: day.diff < 0
                        }"
                      >
                        {{ formatDiff(day.diff) }}
                      </span>

                    </td>

                    <td data-label="محاسبه مبلغ">

                      <span
                        :class="{
                          overtime: day.amount > 0,
                          minus: day.amount < 0
                        }"
                      >
                        {{ formatMoney(day.amount) }}
                      </span>

                    </td>

                  </tr>

                </tbody>

              </table>

            </div>

            <!-- فوتر -->
            <div class="month-footer">

              <div class="summary-card">

                <span>جمع ساعات ماه</span>

                <h3
                  :class="{
                    overtime: totalMonthHours(activeMonth) > 0,
                    minus: totalMonthHours(activeMonth) < 0
                  }"
                >
                  {{ formatDiff(totalMonthHours(activeMonth)) }}
                </h3>

              </div>

              <div class="summary-card money-card">

                <span>جمع مبلغ ماه</span>

                <h3
                  :class="{
                    overtime: totalMonthAmount(activeMonth) > 0,
                    minus: totalMonthAmount(activeMonth) < 0
                  }"
                >
                  {{ formatMoney(totalMonthAmount(activeMonth)) }}
                </h3>

              </div>

            </div>

          </div>

        </div>

      </div>

      <!-- خالی -->
      <div
        v-else
        class="empty-state"
      >

        <h2>پرسنلی انتخاب نشده</h2>
        <p>از سمت راست یک پرسنل انتخاب کنید</p>

      </div>

    </main>

    <!-- مودال افزودن ماه -->
    <div
      v-if="showMonthModal"
      class="modal-overlay"
      @click.self="showMonthModal = false"
    >

      <div class="modal-box">

        <h2>افزودن ماه جدید</h2>

        <div class="month-picker-grid">
          <label>
            <span>سال</span>
            <select v-model.number="newMonthYear">
              <option v-for="year in yearOptions" :key="year" :value="year">{{ year.toLocaleString('fa-IR', { useGrouping: false }) }}</option>
            </select>
          </label>
          <label>
            <span>ماه</span>
            <select v-model.number="newMonthIndex">
              <option v-for="(month, index) in persianMonths" :key="month" :value="index + 1">{{ month }}</option>
            </select>
          </label>
        </div>
        <p v-if="monthFormError" class="month-form-error">{{ monthFormError }}</p>

        <div class="modal-actions">

          <button
            class="cancel-btn"
            @click="showMonthModal = false"
          >
            انصراف
          </button>

          <button
            class="save-btn"
            @click="addMonth"
          >
            ثبت ماه
          </button>

        </div>

      </div>

    </div>

    <div
      v-if="showLeaveModal"
      class="modal-overlay"
      @click.self="closeLeaveModal"
    >
      <div class="modal-box leave-modal">
        <div class="leave-modal-heading">
          <div>
            <h2>درخواست مرخصی</h2>
            <p>روز {{ selectedLeaveDay?.day }} از {{ activeMonth?.name }}</p>
          </div>
          <button type="button" class="leave-modal-close" @click="closeLeaveModal">×</button>
        </div>

        <div v-if="leaveSuccessMessage" class="leave-success-message">
          <strong>درخواست شما ثبت شد</strong>
          <span>درخواست پس از تأیید مدیر در محاسبات لحاظ می‌شود.</span>
          <button type="button" class="save-btn" @click="closeLeaveModal">متوجه شدم</button>
        </div>

        <template v-else>
          <label class="leave-reason-field">
            <span>عنوان یا دلیل مرخصی</span>
            <textarea
              v-model.trim="leaveRequestDraft"
              :readonly="canManageAttendance && Boolean(selectedLeaveDay?.leaveRequestTitle)"
              rows="4"
              placeholder="دلیل درخواست مرخصی را بنویسید..."
            ></textarea>
          </label>

          <div v-if="selectedLeaveDay?.leaveRequestTitle" class="leave-request-status" :class="{ approved: selectedLeaveDay.leaveApproved }">
            {{ selectedLeaveDay.leaveApproved ? 'این مرخصی توسط مدیر تأیید شده است.' : 'این درخواست در انتظار تأیید مدیر است.' }}
          </div>

          <div class="modal-actions">
            <button type="button" class="cancel-btn" @click="closeLeaveModal">بستن</button>
            <button
              v-if="!canManageAttendance && selectedLeaveDay?.leaveRequestTitle && !selectedLeaveDay?.leaveApproved"
              type="button"
              class="cancel-leave-request-btn"
              @click="cancelLeaveRequest"
            >
              لغو درخواست
            </button>
            <button
              v-if="canManageAttendance && selectedLeaveDay?.leaveRequestTitle && !selectedLeaveDay?.leaveApproved"
              type="button"
              class="save-btn approve-leave-btn"
              @click="approveLeaveRequest"
            >
              تأیید مرخصی
            </button>
            <button
              v-else-if="!selectedLeaveDay?.leaveRequestTitle && !selectedLeaveDay?.leaveApproved"
              type="button"
              class="save-btn"
              :disabled="!leaveRequestDraft"
              @click="submitLeaveRequest"
            >
              ثبت درخواست
            </button>
          </div>
        </template>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { avatarUrl } from '@/utils/avatar'

const props = defineProps({
  permissions: { type: Array, default: () => [] },
  currentUser: { type: Object, default: null }
})

const canManageAttendance = props.permissions.includes('attendance.manage')
  || (props.currentUser?.roles || []).some(role => /مدیر|admin/i.test(String(role)))
const canClockAttendance = props.permissions.includes('attendance.clock')
const attendanceEnabled = ref(false)
const isLoading = ref(true)

const staffs = ref([])

const selectedStaff = ref(null)

const showMonthModal = ref(false)
const showLeaveModal = ref(false)
const selectedLeaveMonth = ref(null)
const selectedLeaveDay = ref(null)
const leaveRequestDraft = ref('')
const leaveSuccessMessage = ref(false)
const persianMonths = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند']
const currentPersianParts = Object.fromEntries(
  new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', { year: 'numeric', month: 'numeric' })
    .formatToParts(new Date())
    .filter(part => part.type === 'year' || part.type === 'month')
    .map(part => [part.type, Number(part.value)])
)
const currentPersianYear = currentPersianParts.year
const yearOptions = Array.from({ length: 9 }, (_, index) => currentPersianYear + 3 - index)
const newMonthYear = ref(currentPersianYear)
const newMonthIndex = ref(currentPersianParts.month)
const monthFormError = ref('')
const selectedMonthId = ref(null)
const sortedMonths = computed(() => [...(selectedStaff.value?.months || [])].sort((a, b) => monthSortValue(b) - monthSortValue(a)))
// در رابط RTL، ترتیب صعودی باعث می‌شود جدیدترین ماه در سمت چپ قرار بگیرد.
const recentMonths = computed(() => sortedMonths.value.slice(0, 6).reverse())
const oldMonths = computed(() => sortedMonths.value.slice(6))
const showOldMonths = ref(false)
const oldMonthsMenu = ref(null)
const activeMonthIsOld = computed(() => oldMonths.value.some(month => month.id === activeMonth.value?.id))
const activeMonth = computed(() => {
  const months = selectedStaff.value?.months || []
  return months.find(month => month.id === selectedMonthId.value) || sortedMonths.value[0] || null
})
const visibleResources = computed(() => {
  if (canManageAttendance) return staffs.value
  const userId = String(props.currentUser?.id || '')
  const userName = String(props.currentUser?.name || '').trim()
  return staffs.value.filter(item => {
    if (userId && item.user_id && String(item.user_id) === userId) return true
    return userName && String(item.name || '').trim() === userName
  })
})
const visibleDoctors = computed(() => visibleResources.value.filter(item => item.resourceType === 'doctor'))
const visibleStaff = computed(() => visibleResources.value.filter(item => item.resourceType === 'staff'))

async function loadResources() {
  isLoading.value = true
  try {
    const [staffResponse, doctorResponse, settingsResponse, attendanceResponse] = await Promise.all([
      fetch('/api/staff', { headers: { Accept: 'application/json' } }),
      fetch('/api/doctors', { headers: { Accept: 'application/json' } }),
      fetch('/api/settings', { headers: { Accept: 'application/json' } }),
      fetch('/api/attendance/months', { headers: { Accept: 'application/json' } })
    ])
    const staff = staffResponse.ok ? await staffResponse.json() : []
    const doctors = doctorResponse.ok ? await doctorResponse.json() : []
    const settings = settingsResponse.ok ? await settingsResponse.json() : {}
    const attendanceData = attendanceResponse.ok ? await attendanceResponse.json() : { months: [] }
    const attendanceMonths = Array.isArray(attendanceData.months) ? attendanceData.months : []
    attendanceEnabled.value = Boolean(settings.attendance_enabled)
    staffs.value = [
      ...staff.map(item => ({ ...item, resourceId: item.id, resourceType: 'staff', id: `staff-${item.id}` })),
      ...doctors.map(item => ({ ...item, resourceId: item.id, resourceType: 'doctor', id: `doctor-${item.id}` }))
    ].map(item => ({
      ...item,
      image: avatarUrl(item),
      months: attendanceMonths
        .filter(month => month.resource_type === item.resourceType && Number(month.resource_id) === Number(item.resourceId))
        .map(normalizeStoredMonth)
    }))
    selectedStaff.value = visibleResources.value[0] || null
    selectedMonthId.value = preferredMonthId(selectedStaff.value)
  } catch (error) {
    console.error('Resource attendance load error', error)
  } finally {
    isLoading.value = false
  }
}

function closeOldMonths(event) {
  if (!oldMonthsMenu.value?.contains(event.target)) showOldMonths.value = false
}

function selectOldMonth(month) {
  selectedMonthId.value = month.id
  showOldMonths.value = false
}

onMounted(() => {
  loadResources()
  document.addEventListener('click', closeOldMonths)
})
onBeforeUnmount(() => document.removeEventListener('click', closeOldMonths))

function createMonth(year, monthIndex) {
  const dayCount = monthIndex <= 6 ? 31 : monthIndex <= 11 ? 30 : (isPersianLeapYear(year) ? 30 : 29)

  return {

    id: Date.now() + Math.random(),

    name: `${persianMonths[monthIndex - 1]} ${year}`,
    year,
    month: monthIndex,

    dailyHours: 8,

    days: Array.from({ length: dayCount }, (_, i) => ({

      day: i + 1,

      in: "",

      out: "",

      workedHours: 0,

      diff: 0,

      amount: 0,

      employerApproved: false,

      leaveRequestTitle: "",

      leaveApproved: false

      ,absent: false

    }))
  }
}

function selectStaff(staff) {

  selectedStaff.value = staff
  selectedMonthId.value = preferredMonthId(staff)
  showOldMonths.value = false
}

async function addMonth() {
  monthFormError.value = ''
  if (!selectedStaff.value) return
  const duplicate = staffs.value.length > 0 && staffs.value.every(staff =>
    staff.months.some(month =>
      Number(month.year || extractYear(month.name)) === newMonthYear.value
      && Number(month.month || extractMonthIndex(month.name)) === newMonthIndex.value
    )
  )
  if (duplicate) {
    monthFormError.value = 'این ماه برای سال انتخاب‌شده قبلاً ثبت شده است.'
    return
  }
  const draft = createMonth(newMonthYear.value, newMonthIndex.value)
  try {
    const response = await fetch('/api/attendance/months', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        scope: 'all',
        year: draft.year,
        month: draft.month,
        name: draft.name,
        daily_hours: draft.dailyHours,
        days: draft.days
      })
    })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || 'ثبت ماه انجام نشد.')
    const storedMonths = Array.isArray(data.months) ? data.months.map(normalizeStoredMonth) : []
    storedMonths.forEach(stored => {
      const staff = staffs.value.find(item =>
        item.resourceType === stored.resource_type
        && Number(item.resourceId) === Number(stored.resource_id)
      )
      if (!staff) return
      const sameMonthIndex = staff.months.findIndex(month => month.id === stored.id)
      if (sameMonthIndex >= 0) staff.months.splice(sameMonthIndex, 1, stored)
      else staff.months.push(stored)
    })
    // افزودن یک رکورد قدیمی نباید آن را به‌عنوان آخرین ماه فعال نمایش دهد.
    // ترتیب و ماه فعال همیشه از سال و شماره ماه محاسبه می‌شود، نه زمان ایجاد رکورد.
    selectedMonthId.value = preferredMonthId(selectedStaff.value)
    showMonthModal.value = false
  } catch (error) {
    monthFormError.value = error.message || 'ثبت ماه انجام نشد.'
  }
}

async function deleteMonth(index) {

  const confirmDelete =
    confirm("آیا مطمئن هستید ؟")

  if (!confirmDelete) return

  const month = selectedStaff.value.months[index]
  if (!month?.id) return
  try {
    const response = await fetch(`/api/attendance/months/${month.id}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json' }
    })
    if (!response.ok) throw new Error('حذف ماه انجام نشد.')
    selectedStaff.value.months.splice(index, 1)
    selectedMonthId.value = preferredMonthId(selectedStaff.value)
  } catch (error) {
    alert(error.message || 'حذف ماه انجام نشد.')
  }
}

function normalizeStoredMonth(month) {
  return {
    ...month,
    year: Number(month.year),
    month: Number(month.month),
    dailyHours: Number(month.daily_hours ?? month.dailyHours ?? 8),
    days: Array.isArray(month.days) ? month.days : []
  }
}

const monthSaveTimers = new Map()
function scheduleMonthSave(month) {
  if (!month?.id) return
  clearTimeout(monthSaveTimers.get(month.id))
  monthSaveTimers.set(month.id, setTimeout(() => saveMonth(month), 500))
}

async function saveMonth(month) {
  try {
    const response = await fetch(`/api/attendance/months/${month.id}`, {
      method: 'PATCH',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ daily_hours: month.dailyHours, days: month.days })
    })
    if (!response.ok) throw new Error('ذخیره حضور و غیاب انجام نشد.')
    return true
  } catch (error) {
    console.error(error)
    return false
  }
}

function normalizeDigits(value) {
  const fa = '۰۱۲۳۴۵۶۷۸۹'
  const ar = '٠١٢٣٤٥٦٧٨٩'
  return String(value || '')
    .replace(/[۰-۹]/g, digit => fa.indexOf(digit))
    .replace(/[٠-٩]/g, digit => ar.indexOf(digit))
}

function extractYear(name) {
  const match = normalizeDigits(name).match(/(13|14)\d{2}/)
  return match ? Number(match[0]) : currentPersianYear
}

function extractMonthIndex(name) {
  const index = persianMonths.findIndex(month => String(name || '').includes(month))
  return index >= 0 ? index + 1 : 1
}

function monthSortValue(month) {
  return Number(month.year || extractYear(month.name)) * 100 + Number(month.month || extractMonthIndex(month.name))
}

function latestMonthId(staff) {
  return [...(staff?.months || [])].sort((a, b) => monthSortValue(b) - monthSortValue(a))[0]?.id || null
}

function preferredMonthId(staff) {
  const currentMonth = (staff?.months || []).find(month =>
    Number(month.year || extractYear(month.name)) === currentPersianYear
    && Number(month.month || extractMonthIndex(month.name)) === currentPersianParts.month
  )
  return currentMonth?.id || latestMonthId(staff)
}

function monthLabel(month) {
  return persianMonths[Number(month.month || extractMonthIndex(month.name)) - 1] || month.name
}

function isPersianLeapYear(year) {
  const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178]
  let leapJ = -14
  let jp = breaks[0]
  let jump = 0
  for (let i = 1; i < breaks.length; i += 1) {
    const jm = breaks[i]
    jump = jm - jp
    if (year < jm) break
    leapJ += Math.floor(jump / 33) * 8 + Math.floor((jump % 33) / 4)
    jp = jm
  }
  let n = year - jp
  leapJ += Math.floor(n / 33) * 8 + Math.floor(((n % 33) + 3) / 4)
  if (jump % 33 === 4 && jump - n === 4) leapJ += 1
  const leap = ((n + 1) % 33 - 1) % 4
  return leap === 0 || leap === -0
}

function openLeaveModal(month, day) {
  selectedLeaveMonth.value = month
  selectedLeaveDay.value = day
  leaveRequestDraft.value = day.leaveRequestTitle || ''
  leaveSuccessMessage.value = false
  showLeaveModal.value = true
}

function closeLeaveModal() {
  showLeaveModal.value = false
  selectedLeaveMonth.value = null
  selectedLeaveDay.value = null
  leaveRequestDraft.value = ''
  leaveSuccessMessage.value = false
}

async function submitLeaveRequest() {
  const title = leaveRequestDraft.value.trim()
  if (!title || !selectedLeaveMonth.value || !selectedLeaveDay.value) return
  selectedLeaveDay.value.leaveRequestTitle = title
  selectedLeaveDay.value.leaveApproved = false
  const saved = await saveMonth(selectedLeaveMonth.value)
  if (saved) leaveSuccessMessage.value = true
}

async function approveLeaveRequest() {
  if (!canManageAttendance || !selectedLeaveMonth.value || !selectedLeaveDay.value) return
  selectedLeaveDay.value.leaveApproved = true
  selectedLeaveDay.value.absent = true
  calculateDay(selectedLeaveMonth.value, selectedLeaveDay.value)
  const saved = await saveMonth(selectedLeaveMonth.value)
  if (saved) closeLeaveModal()
}

async function cancelLeaveRequest() {
  if (canManageAttendance || !selectedLeaveMonth.value || !selectedLeaveDay.value || selectedLeaveDay.value.leaveApproved) return
  if (!confirm('درخواست مرخصی لغو شود؟')) return

  const previousTitle = selectedLeaveDay.value.leaveRequestTitle
  selectedLeaveDay.value.leaveRequestTitle = ''
  selectedLeaveDay.value.leaveApproved = false
  const saved = await saveMonth(selectedLeaveMonth.value)
  if (saved) {
    closeLeaveModal()
    return
  }
  selectedLeaveDay.value.leaveRequestTitle = previousTitle
}

function calculateDay(month, day) {

  if (day.absent) {
    day.in = ""
    day.out = ""
    day.workedHours = 0
    day.diff = 0
    day.amount = day.leaveApproved ? 0 : -Number(selectedStaff.value?.absence_deduction || 0)
    scheduleMonthSave(month)
    return
  }

  if (!day.in || !day.out) {

    day.workedHours = 0
    day.diff = 0
    day.amount = 0
    scheduleMonthSave(month)
    return
  }

  const [inH, inM] =
    day.in.split(":").map(Number)

  const [outH, outM] =
    day.out.split(":").map(Number)

  const inMinutes =
    inH * 60 + inM

  const outMinutes =
    outH * 60 + outM

  let totalHours =
    (outMinutes - inMinutes) / 60

  if (totalHours < 0)
    totalHours = 0

  day.workedHours =
    totalHours.toFixed(2)

  const diff =
    +(totalHours - month.dailyHours).toFixed(2)

  day.diff = diff

  if (diff > 0 && day.employerApproved) {

    day.amount =
      Math.round(diff * Number(selectedStaff.value?.overtime_hourly_rate || 0))
  }

  else if (diff < 0) {

    day.amount =
      -Math.round(
        Math.abs(diff) * Number(selectedStaff.value?.shortage_hourly_deduction || 0)
      )
  }

  else {

    day.amount = 0
  }
  scheduleMonthSave(month)
}

function totalMonthHours(month) {

  return month.days.reduce((sum, d) => {

    const diff = Number(d.diff) || 0
    return sum + (diff > 0 && !d.employerApproved ? 0 : diff)

  }, 0)
}

function totalMonthAmount(month) {
  const overtimeHours = month.days.reduce((sum, day) => sum + (day.employerApproved ? Math.max(Number(day.diff) || 0, 0) : 0), 0)
  const shortageHours = month.days.reduce((sum, day) => sum + Math.max(-(Number(day.diff) || 0), 0), 0)
  const allowed = Number(selectedStaff.value?.allowed_shortage_hours || 0)
  const payableShortage = Math.max(shortageHours - allowed, 0)
  const absenceAmount = month.days.reduce((sum, day) => sum + (day.absent && !day.leaveApproved ? Number(selectedStaff.value?.absence_deduction || 0) : 0), 0)
  return Math.round(
    overtimeHours * Number(selectedStaff.value?.overtime_hourly_rate || 0)
    - payableShortage * Number(selectedStaff.value?.shortage_hourly_deduction || 0)
    - absenceAmount
  )
}

function onAbsenceChanged(month, day) {
  calculateDay(month, day)
}

function formatDiff(value) {

  if (value > 0) {

    return `+ ${value.toFixed(2)} ساعت`
  }

  if (value < 0) {

    return `${value.toFixed(2)} ساعت`
  }

  return "0"
}

function formatNumber(value) {

  if (!value && value !== 0)
    return ""

  return Number(value)
    .toLocaleString("en-US")
}

function updateNumber(event, month, field) {

  const raw =
    event.target.value.replace(/,/g, "")

  month[field] =
    Number(raw) || 0
}

function formatMoney(value) {

  const number =
    Number(value || 0)

  const formatted =
    Math.abs(number)
      .toLocaleString("en-US")

  if (number > 0) {

    return `+ ${formatted} تومان`
  }

  if (number < 0) {

    return `- ${formatted} تومان`
  }

  return "0 تومان"
}

function setCurrentTime(day, field) {
  if (!attendanceEnabled.value || !canClockAttendance) return
  if (day[field]) return
  const now = new Date()
  day[field] = `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`
  if (activeMonth.value) {
    calculateDay(activeMonth.value, day)
  }
}
</script>

<style scoped>

* {
  box-sizing: border-box;
}

.hr-page {
  width: 100%;
  height: 100vh;
  position: relative;
  display: flex;
  overflow: hidden;
  background: #f4f7fb;
  font-family: sans-serif;
}

.attendance-loading {
  position: absolute;
  z-index: 100;
  inset: 0;
  min-height: 420px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 9px;
  border-radius: inherit;
  background: rgba(244, 247, 251, .94);
  backdrop-filter: blur(5px);
  color: #0f172a;
}

.attendance-loading strong { font-size: 14px; }
.attendance-loading small { color: #64748b; font-size: 10px; }

.attendance-spinner {
  width: 42px;
  height: 42px;
  margin-bottom: 5px;
  border: 4px solid #dbeafe;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: attendance-spin .75s linear infinite;
}

@keyframes attendance-spin {
  to { transform: rotate(360deg); }
}

/* سایدبار */

.resource-pay-summary{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:12px;border:1px solid #dbeafe;border-radius:13px;background:#f8fbff}.resource-pay-summary span{padding:7px 10px;border-radius:9px;background:#fff;color:#475569;font-size:11px}.resource-pay-summary b{color:#1d4ed8}.resource-pay-summary small{width:100%;color:#64748b;font-size:10px}
.attendance-status-control{display:flex;align-items:center;gap:10px;margin-right:auto;margin-left:14px}.attendance-status-control>span{padding:7px 10px;border-radius:9px;background:#fee2e2;color:#b91c1c;font-size:11px;font-weight:800}.attendance-status-control>span.enabled{background:#dcfce7;color:#15803d}.manager-switch{display:flex;align-items:center;gap:6px;padding:7px 10px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;cursor:pointer;font-size:11px}.manager-switch input{accent-color:#16a34a}
.approval-toggle,.leave-approval{display:inline-flex;align-items:center;gap:6px;padding:6px 8px;border-radius:8px;background:#fff7ed;color:#c2410c;font-size:11px;font-weight:700;white-space:nowrap}.approval-toggle.approved,.leave-approval.approved{background:#dcfce7;color:#15803d}.approval-toggle input,.leave-approval input{accent-color:#16a34a}.leave-request-cell{display:flex;flex-direction:column;gap:5px;min-width:170px}.leave-request-cell>input{min-width:160px;padding:7px;border:1px solid #dbe3ef;border-radius:7px}
.absence-toggle{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:8px;background:#ecfdf5;color:#15803d;font-size:11px;font-weight:800;white-space:nowrap}.absence-toggle.absent{background:#fee2e2;color:#b91c1c}.absence-toggle input{accent-color:#dc2626}

.staff-sidebar {
  width: 320px;
  background: white;
  border-left: 1px solid #ececec;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  padding: 24px;
  border-bottom: 1px solid #eee;
}

.sidebar-header h2 {
  margin: 0;
  font-size: 24px;
}

.sidebar-header p {
  margin-top: 8px;
  color: #888;
  font-size: 14px;
}

.add-btn {
  width: 100%;
  margin-top: 20px;
  height: 52px;
  border: none;
  border-radius: 16px;
  background: linear-gradient(135deg,#4f46e5,#7c3aed);
  color: white;
  font-size: 15px;
  cursor: pointer;
  transition: .3s;
}

.add-btn:hover {
  transform: translateY(-2px);
}

.staff-list {
  flex: 1;
  overflow-y: auto;
  padding: 18px;
}

.staff-card {
  background: #f8fafc;
  border-radius: 20px;
  padding: 16px;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 14px;
  cursor: pointer;
  transition: .3s;
  border: 1px solid transparent;
}

.staff-card:hover {
  transform: translateY(-2px);
}

.staff-card.active {
  background: white;
  border-color: #6d5dfc;
  box-shadow: 0 10px 30px rgba(109,93,252,.12);
}

.avatar {
  width: 58px;
  height: 58px;
  border-radius: 18px;
  overflow: hidden;
  background: linear-gradient(135deg,#4f46e5,#7c3aed);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar span {
  color: white;
  font-size: 22px;
  font-weight: bold;
}

.staff-info h4 {
  margin: 0;
  font-size: 16px;
}

.staff-info span {
  color: #888;
  font-size: 13px;
}

/* محتوا */

.main-content {
  flex: 1;
  overflow: hidden;
}

.content-wrapper {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.top-header {
  background: white;
  padding: 28px;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.top-header h1 {
  margin: 0;
  font-size: 34px;
}

.top-header p {
  margin-top: 8px;
  color: #777;
}

.month-btn {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 1px solid #dbeafe;
  border-radius: 12px;
  background: #eff6ff;
  cursor: pointer;
  color: #2563eb;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
  transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.month-btn span {
  font-family: Arial, sans-serif;
  font-size: 25px;
  font-weight: 400;
  line-height: 1;
  transform: translateY(-1px);
}

.month-btn:hover {
  background: #dbeafe;
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.16);
  transform: translateY(-1px);
}

.month-btn:active {
  transform: translateY(0) scale(0.96);
}

.month-btn:focus-visible {
  outline: 3px solid rgba(37, 99, 235, 0.2);
  outline-offset: 2px;
}

/* حالت خالی ماه */

.empty-months {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-months h2 {
  margin: 0;
  font-size: 34px;
}

.empty-months p {
  margin-top: 12px;
  color: #888;
}

/* ماه ها */

.months-container {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.month-card {
  background: white;
  border-radius: 30px;
  overflow: hidden;
  margin-bottom: 28px;
  box-shadow: 0 10px 40px rgba(0,0,0,.05);
}

.month-header {
  padding: 28px;
  border-bottom: 1px solid #eee;
}

.month-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.month-title h2 {
  margin: 0;
  font-size: 26px;
}

.month-title span {
  color: #888;
  font-size: 14px;
}

.delete-month-btn {
  width: 48px;
  height: 48px;
  border: none;
  border-radius: 14px;
  background: #fff1f2;
  color: #dc2626;
  cursor: pointer;
  font-size: 18px;
}

.rules-box {
  display: flex;
  gap: 18px;
  margin-top: 24px;
  flex-wrap: wrap;
}

.rule-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rule-item label {
  font-size: 13px;
  color: #666;
}

.rule-item input {
  width: 190px;
  height: 48px;
  border-radius: 14px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 0 14px;
}

/* جدول */

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f9fafb;
}

th {
  padding: 18px;
  font-size: 14px;
  color: #555;
}

td {
  padding: 14px 18px;
  text-align: center;
  border-top: 1px solid #f1f1f1;
}

td input {
  width: 120px;
  height: 44px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 0 10px;
}

.overtime {
  color: #16a34a;
  font-weight: bold;
}

.minus {
  color: #dc2626;
  font-weight: bold;
}

/* فوتر */

.month-footer {
  padding: 24px;
  background: #fafafa;
  display: flex;
  gap: 20px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.summary-card {
  min-width: 280px;
  background: white;
  border-radius: 24px;
  padding: 24px;
  box-shadow: 0 6px 30px rgba(0,0,0,.05);
}

.summary-card span {
  color: #888;
  font-size: 14px;
}

.summary-card h3 {
  margin: 12px 0 0;
  font-size: 30px;
}

/* مودال */

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(5px);
  z-index: 999;
}

.modal-box {
  width: 430px;
  background: white;
  border-radius: 28px;
  padding: 28px;
}

.modal-box h2 {
  margin-top: 0;
  margin-bottom: 20px;
}

.modal-box input[type="text"] {
  width: 100%;
  height: 54px;
  border-radius: 14px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 0 16px;
  font-size: 15px;
}

.leave-request-btn {
  width: 100%;
  min-height: 38px;
  padding: 7px 10px;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-family: inherit;
  font-size: 10px;
  font-weight: 900;
  cursor: pointer;
}

.leave-request-btn.pending { border-color: #fed7aa; background: #fff7ed; color: #c2410c; }
.leave-request-btn.approved { border-color: #bbf7d0; background: #ecfdf5; color: #15803d; }
.leave-request-btn:disabled { cursor: not-allowed; opacity: .55; }

.leave-modal { width: min(470px, calc(100% - 24px)); }
.leave-modal-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.leave-modal-heading h2 { margin-bottom: 5px; }
.leave-modal-heading p { margin: 0 0 18px; color: #64748b; font-size: 11px; }
.leave-modal-close { width: 35px; height: 35px; border: 0; border-radius: 10px; background: #f1f5f9; color: #64748b; font-size: 22px; cursor: pointer; }
.leave-reason-field { display: grid; gap: 8px; color: #334155; font-size: 11px; font-weight: 900; }
.leave-reason-field textarea { width: 100%; resize: vertical; padding: 12px; border: 1px solid #dbe3ed; border-radius: 13px; background: #f8fafc; font-family: inherit; font-size: 12px; line-height: 1.9; outline: none; }
.leave-reason-field textarea:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px #dbeafe; }
.leave-request-status { margin-top: 12px; padding: 10px 12px; border-radius: 11px; background: #fff7ed; color: #c2410c; font-size: 11px; font-weight: 800; }
.leave-request-status.approved { background: #ecfdf5; color: #15803d; }
.leave-success-message { display: grid; gap: 6px; padding: 18px; border: 1px solid #bbf7d0; border-radius: 15px; background: #ecfdf5; color: #166534; text-align: center; }
.leave-success-message strong { font-size: 15px; }
.leave-success-message span { font-size: 11px; }
.approve-leave-btn { background: #16a34a; }
.cancel-leave-request-btn { min-height: 40px; padding: 0 14px; border: 1px solid #fecaca; border-radius: 11px; background: #fff1f2; color: #dc2626; font-family: inherit; font-size: 11px; font-weight: 900; cursor: pointer; }

/* آپلود */

.upload-box {
  margin-top: 18px;
}

.upload-label {
  width: 100%;
  height: 56px;
  border: 2px dashed #d1d5db;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  background: #fafafa;
}

.preview-image {
  width: 100px;
  height: 100px;
  border-radius: 24px;
  overflow: hidden;
  margin-top: 16px;
}

.preview-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* دکمه ها */

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}

.cancel-btn,
.save-btn {
  border: none;
  height: 46px;
  padding: 0 20px;
  border-radius: 12px;
  cursor: pointer;
}

.cancel-btn {
  background: #f3f4f6;
}

.save-btn {
  background: #111827;
  color: white;
}

/* خالی */

.empty-state {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-state h2 {
  margin: 0;
  font-size: 34px;
}

.empty-state p {
  margin-top: 12px;
  color: #888;
}

/* اسکرول */

::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-thumb {
  background: #d4d4d8;
  border-radius: 10px;
}

/* ریسپانسیو */

@media(max-width:1100px){

  .hr-page{
    flex-direction: column;
  }

  .staff-sidebar{
    width: 100%;
    height: 300px;
  }

  .top-header{
    flex-direction: column;
    align-items: flex-start;
    gap: 18px;
  }
}

/* Attendance layout refresh */
.hr-page {
  width: 100%;
  min-height: calc(100vh - 170px);
  height: auto;
  display: grid;
  grid-template-columns: 270px minmax(0, 1fr);
  direction: rtl;
  overflow: visible;
  border: 1px solid #e2e8f0;
  border-radius: 24px;
  background: #f8fafc;
  font-family: "Vazir", Tahoma, sans-serif;
  box-shadow: 0 14px 40px rgba(15, 23, 42, .06);
}

.staff-sidebar {
  grid-column: 1;
  width: auto;
  min-width: 0;
  max-height: calc(100vh - 120px);
  position: sticky;
  top: 18px;
  align-self: start;
  overflow: hidden;
  border: 0;
  border-left: 1px solid #e2e8f0;
  border-radius: 0 24px 24px 0;
  background: #fff;
}

.sidebar-header { padding: 20px 18px 14px; }
.sidebar-header h2 { color: #172033; font-size: 20px; }
.staff-list { padding: 12px; }
.staff-card { width: 100%; margin-bottom: 8px; padding: 11px; border-radius: 14px; font-family: inherit; text-align: right; }
.staff-card.active { border-color: #60a5fa; background: #eff6ff; box-shadow: 0 7px 18px rgba(37, 99, 235, .1); }
.avatar { width: 44px; height: 44px; border-radius: 12px; }
.avatar span { font-size: 17px; }
.staff-info h4 { font-size: 13px; }
.staff-info span { font-size: 10px; }
.resource-group + .resource-group { margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0; }
.resource-group > header { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; padding: 0 4px; color: #334155; }
.resource-group > header strong { font-size: 11px; }
.resource-group > header b { min-width: 22px; height: 22px; display: grid; place-items: center; margin-right: auto; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: 9px; }
.group-icon { width: 27px; height: 27px; display: grid; place-items: center; border-radius: 9px; font-size: 13px; }
.group-icon.doctor { background: #dbeafe; color: #1d4ed8; }
.group-icon.staff { background: #dcfce7; color: #15803d; }
.no-resource-access { padding: 18px 12px; border: 1px dashed #cbd5e1; border-radius: 12px; color: #64748b; text-align: center; font-size: 10px; line-height: 1.9; }

.main-content {
  grid-column: 2;
  min-width: 0;
  overflow: visible;
}

.content-wrapper { height: auto; min-height: 100%; }
.top-header { gap: 14px; padding: 18px 20px; border-radius: 24px 0 0 0; }
.top-header h1 { font-size: 22px; }
.top-header p { margin: 4px 0 0; font-size: 11px; }
.attendance-status-control { margin-right: auto; margin-left: 0; flex-wrap: wrap; }
.month-btn { width: 36px; height: 36px; flex-basis: 36px; border-radius: 11px; }

.month-tabs-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
}

.month-tabs {
  min-width: 0;
  display: flex;
  flex: 1;
  gap: 8px;
  overflow: visible;
  padding: 3px 0;
  scrollbar-width: thin;
}

.month-tabs button {
  min-width: 105px;
  height: 48px;
  display: grid;
  place-content: center;
  gap: 2px;
  padding: 0 13px;
  border: 1px solid #dbe3ed;
  border-radius: 12px;
  background: #f8fafc;
  color: #475569;
  font-family: inherit;
  cursor: pointer;
}

.month-tabs button strong { font-size: 11px; }
.month-tabs button small { color: #94a3b8; font-size: 9px; }
.month-tabs button.active { border-color: #2563eb; background: #2563eb; color: #fff; box-shadow: 0 7px 16px rgba(37, 99, 235, .2); }
.month-tabs button.active small { color: #dbeafe; }
.month-tabs-bar > span { color: #64748b; font-size: 10px; white-space: nowrap; }

.old-months-menu {
  position: relative;
  flex: 0 0 auto;
  order: -1;
}

.month-tabs .old-months-trigger {
  border-style: dashed;
  background: #f1f5f9;
}

.old-months-list {
  position: absolute;
  z-index: 30;
  top: calc(100% + 8px);
  right: 0;
  width: 290px;
  max-height: 310px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px;
  padding: 10px;
  overflow-y: auto;
  border: 1px solid #dbe3ed;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, .18);
}

.month-tabs .old-months-list button {
  width: 100%;
  min-width: 0;
  height: 44px;
}

.months-container { overflow: visible; padding: 16px; }
.month-card { margin: 0; overflow: visible; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 8px 25px rgba(15, 23, 42, .05); }
.month-header { padding: 16px; }
.month-title h2 { font-size: 19px; }
.month-title span { font-size: 10px; }
.delete-month-btn { width: 38px; height: 38px; border-radius: 10px; }
.resource-pay-summary { margin-top: 12px; padding: 9px; }
.resource-pay-summary span { padding: 6px 8px; font-size: 9px; }
.resource-pay-summary small { font-size: 9px; }

.table-wrapper { width: 100%; overflow: visible; }
.table-wrapper table { width: 100%; table-layout: fixed; }
.table-wrapper th { padding: 11px 5px; font-size: 9px; }
.table-wrapper td { padding: 8px 5px; font-size: 10px; }
.table-wrapper td input[type="time"] { width: 100%; min-width: 0; height: 36px; padding: 0 4px; border-radius: 9px; font-size: 10px; }
.leave-request-cell { min-width: 0; }
.leave-request-cell > input { width: 100%; min-width: 0; height: 36px; padding: 5px; font-size: 9px; }
.approval-toggle, .leave-approval, .absence-toggle { justify-content: center; padding: 5px; font-size: 9px; white-space: normal; }
.table-wrapper .approval-toggle input[type="checkbox"],
.table-wrapper .leave-approval input[type="checkbox"],
.table-wrapper .absence-toggle input[type="checkbox"] {
  width: 16px !important;
  min-width: 16px;
  height: 16px !important;
  margin: 0;
  padding: 0;
  border-radius: 5px;
  accent-color: #2563eb;
  cursor: pointer;
}
.table-wrapper .absence-toggle input[type="checkbox"] { accent-color: #dc2626; }
.table-wrapper input[type="checkbox"]:disabled { cursor: not-allowed; opacity: .5; }
.month-footer { padding: 14px; gap: 10px; }
.summary-card { min-width: 210px; padding: 15px; border-radius: 15px; }
.summary-card span { font-size: 10px; }
.summary-card h3 { margin-top: 7px; font-size: 18px; }

.month-picker-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.month-picker-grid label { display: grid; gap: 7px; color: #475569; font-size: 11px; font-weight: 900; }
.month-picker-grid select { width: 100%; height: 48px; padding: 0 12px; border: 1px solid #dbe3ed; border-radius: 12px; background: #f8fafc; font-family: inherit; outline: 0; }
.month-picker-grid select:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96, 165, 250, .12); }
.month-form-error { margin: 10px 0 0; padding: 9px; border-radius: 9px; background: #fff1f2; color: #dc2626; font-size: 10px; }

@media (max-width: 1450px) {
  .table-wrapper table,
  .table-wrapper tbody { display: block; }
  .table-wrapper thead { display: none; }
  .table-wrapper tbody { padding: 10px; background: #f8fafc; }
  .table-wrapper tbody tr {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0;
    margin-bottom: 10px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
  }
  .table-wrapper tbody td {
    min-width: 0;
    display: grid;
    grid-template-columns: 78px minmax(0, 1fr);
    align-items: center;
    gap: 7px;
    padding: 9px;
    border: 0;
    border-bottom: 1px solid #edf2f7;
    text-align: right;
  }
  .table-wrapper tbody td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: 9px;
    font-weight: 900;
  }
  .table-wrapper tbody td:first-child { background: #eff6ff; color: #1d4ed8; font-weight: 900; }
  .table-wrapper tbody td:first-child::before { color: #1d4ed8; }
}

@media (max-width: 900px) {
  .hr-page { display: flex; flex-direction: column; border-radius: 18px; }
  .staff-sidebar { width: 100%; max-height: none; position: static; border-left: 0; border-bottom: 1px solid #e2e8f0; border-radius: 18px 18px 0 0; }
  .staff-list { display: flex; gap: 8px; overflow-x: auto; }
  .resource-group { min-width: max-content; }
  .resource-group + .resource-group { margin: 0; padding: 0 14px 0 0; border-top: 0; border-right: 1px solid #e2e8f0; }
  .resource-group > header { position: sticky; right: 0; }
  .resource-group .staff-card { width: 180px; display: inline-flex; margin: 0 0 0 7px; }
  .main-content { width: 100%; }
  .top-header { align-items: stretch; flex-direction: column; border-radius: 0; }
  .attendance-status-control { margin: 0; }
  .month-tabs-bar { align-items: stretch; flex-direction: column; }
  .month-tabs-bar > span { display: none; }
  .months-container { padding: 10px; }
  .table-wrapper tbody tr { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 600px) {
  .old-months-list { position: fixed; inset: auto 12px 16px; width: auto; max-height: 55vh; }
  .table-wrapper tbody tr { grid-template-columns: 1fr; }
  .month-picker-grid { grid-template-columns: 1fr; }
  .modal-box { width: calc(100% - 24px); padding: 20px; border-radius: 20px; }
  .month-footer { flex-direction: column; }
  .summary-card { width: 100%; min-width: 0; }
}

</style>
