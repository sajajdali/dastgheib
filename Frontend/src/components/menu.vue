<template>
  <div class="menu">
    <button
      v-if="overflowOpen"
      type="button"
      class="more-backdrop"
      aria-label="بستن زیرمنو"
      @click.stop="overflowOpen = false"
    ></button>
    <ul class="menu-items">
      <!-- آیتم‌ها -->
      <li
        v-for="item in primaryItems"
        :key="item.value"
        class="menu-item"
        :class="{ active: currentPage === item.value }"
        @click="select(item.value)"
      >
        <div class="menu-dot"></div>
        <span>{{ item.label }}</span>
        <span
          v-if="item.value !== 'Vaghtdahi' && item.value !== 'Photos' && notificationCounts[item.value] > 0"
          class="notification-badge"
          :aria-label="`${notificationCounts[item.value]} مورد سررسید شده`"
        >
          {{ formatBadgeCount(notificationCounts[item.value]) }}
        </span>
      </li>

      <li
        v-if="overflowItems.length"
        class="menu-item more-menu-item"
        :class="{ active: overflowOpen || overflowActive }"
        @click.stop="overflowOpen = !overflowOpen"
      >
        <div class="menu-dot"></div>
        <span>بیشتر</span>
        <svg class="more-arrow" :class="{ open: overflowOpen }" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M8 10l4 4 4-4"/>
        </svg>
        <span
          v-if="overflowNotificationCount > 0"
          class="notification-badge"
          :aria-label="`${overflowNotificationCount} مورد سررسید شده`"
        >
          {{ formatBadgeCount(overflowNotificationCount) }}
        </span>
        <div v-if="overflowOpen" class="more-submenu" @click.stop>
          <button
            v-for="item in overflowItems"
            :key="item.value"
            type="button"
            :class="{ active: currentPage === item.value }"
            @click="selectOverflow(item.value)"
          >
            <span>{{ item.label }}</span>
            <b v-if="notificationCounts[item.value] > 0">{{ formatBadgeCount(notificationCounts[item.value]) }}</b>
          </button>
        </div>
      </li>

      <!-- دکمه بستن -->
      <li class="menu-item close-btn" @click="$emit('close-all')">
        <svg
          viewBox="0 0 24 24"
          width="16"
          height="16"
          class="close-icon"
        >
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </li>
    </ul>
  </div>
</template>

<script>
const API = '/api'
const INVENTORY_ZERO_NOTIFICATIONS_KEY = 'inventory_zero_stock_notifs_v1'

export default {
  props: {
    currentPage: {
      type: String,
      default: null
    },
    permissions: {
      type: Array,
      default: () => []
    },
    roles: {
      type: Array,
      default: () => []
    },
    attendanceEnabled: {
      type: Boolean,
      default: false
    },
    enabledFeatures: {
      type: Array,
      default: null
    }
  },

  data() {
    return {
      notificationCounts: { Peygiri: 0, dermatracker: 0, Ticket: 0, Notif: 0, Anbar: 0, Vaghtdahi: 0, HRtimes: 0, Payroll: 0, Gozaresh: 0, ActivityLogs: 0 },
      notificationTimer: null,
      overflowOpen: false,
      permissionMap: {
        Parvande: 'patients.view',
        Photos: 'photos.view',
        Vaghtdahi: 'appointments.view',
        Peygiri: 'followups.view',
        Notif: 'followups.view',
        Gozaresh: 'reports.view',
        ActivityLogs: 'activity_logs.view',
        Anbar: 'inventory.view',
        dermatracker: 'beauty.view',
        Ticket: 'tickets.view',
        Products: 'services.view',
        Automation: 'automation.view',
        Bills: 'bills.view',
        Payroll: 'payroll.view',
        HRtimes: 'attendance.view',
        Roles: 'roles.view',
        Setting: 'settings.view',
        Store: 'store.view'
      },
      items: [
        { label: 'پرونده', value: 'Parvande', feature: 'patients' },
        { label: 'وقت دهی', value: 'Vaghtdahi', feature: 'booking' },
        { label: 'پیگیری', value: 'Peygiri', feature: 'followups' },
        { label: 'زیبایار', value: 'dermatracker', feature: 'beauty' },
        { label: 'عکس‌ها', value: 'Photos', feature: 'gallery' },
        { label: 'گزارش', value: 'Gozaresh', feature: 'report' },
        { label: 'انبار', value: 'Anbar', feature: 'inventory' },
        { label: 'تیکت', value: 'Ticket', feature: 'tickets' },
        { label: 'خدمت یاب', value: 'Products', feature: 'finder' },
        { label: 'اتوماسیون', value: 'Automation', feature: 'automation' },
        { label: 'هزینه‌ها', value: 'Bills', feature: 'bills' },
        { label: 'حضور غیاب', value: 'HRtimes', feature: 'attendance' },
        { label: 'حقوق و تسویه', value: 'Payroll', feature: null }
      ]
    }
  },

  computed: {
    isClinicManager() {
      const allowed = ['مدیر کل', 'مدیر سیستم', 'super admin', 'super-admin']
      return this.roles.some(role => allowed.includes(String(role).trim().toLowerCase()))
    },

    visibleItems() {
      return this.items.filter(item => {
        if (item.value === 'HRtimes' && !this.attendanceEnabled) return false
        if (item.value === 'Payroll' && ['payroll.view', 'reports.financial', 'reports.staff', 'reports.doctors'].some(permission => this.permissions.includes(permission))) return true
        if (item.value === 'Setting') {
          return this.isClinicManager
        }
        if (!this.featureEnabled(item.feature)) return false
        const requiredPermission = this.permissionMap[item.value]
        return requiredPermission && this.permissions.includes(requiredPermission)
      })
    },

    primaryItems() {
      const primaryValues = ['Parvande', 'Vaghtdahi', 'Peygiri', 'dermatracker', 'Photos', 'Gozaresh', 'Anbar', 'Ticket']
      return this.visibleItems.filter(item => primaryValues.includes(item.value))
    },

    overflowItems() {
      const primaryValues = ['Parvande', 'Vaghtdahi', 'Peygiri', 'dermatracker', 'Photos', 'Gozaresh', 'Anbar', 'Ticket']
      return this.visibleItems.filter(item => !primaryValues.includes(item.value))
    },

    overflowActive() {
      return this.overflowItems.some(item => item.value === this.currentPage)
    },

    overflowNotificationCount() {
      return this.overflowItems.reduce((sum, item) => sum + Number(this.notificationCounts[item.value] || 0), 0)
    }
  },

  mounted() {
    this.refreshNotificationCounts()
    this.notificationTimer = window.setInterval(this.refreshNotificationCounts, 60000)
    window.addEventListener('storage', this.refreshNotificationCounts)
    window.addEventListener('app:notifications-changed', this.refreshNotificationCounts)
    document.addEventListener('pointerdown', this.closeOverflowOnOutsidePointer, true)
  },

  beforeUnmount() {
    window.clearInterval(this.notificationTimer)
    window.removeEventListener('storage', this.refreshNotificationCounts)
    window.removeEventListener('app:notifications-changed', this.refreshNotificationCounts)
    document.removeEventListener('pointerdown', this.closeOverflowOnOutsidePointer, true)
  },

  methods: {
    closeOverflowOnOutsidePointer(event) {
      if (!this.overflowOpen) return

      const overflowMenu = this.$el?.querySelector('.more-menu-item')
      if (!overflowMenu?.contains(event.target)) this.overflowOpen = false
    },
    select(val) {
      this.overflowOpen = false
      this.$emit('select', val)
    },
    selectOverflow(val) {
      this.overflowOpen = false
      this.$emit('select', val)
    },
    featureEnabled(feature) {
      if (!feature || !Array.isArray(this.enabledFeatures)) return true
      const aliases = {
        chat: 'patients',
        staffEval: 'resources',
        tasks: 'followups',
        campaign: 'automation',
        aiReport: 'beauty',
        shop: 'online_store',
        store: 'online_store'
      }
      const normalized = this.enabledFeatures.map(item => aliases[item] || item)
      return normalized.includes(feature)
    },
    normalizeDigits(value) {
      const persian = '۰۱۲۳۴۵۶۷۸۹'
      const arabic = '٠١٢٣٤٥٦٧٨٩'
      return String(value || '')
        .replace(/[۰-۹]/g, digit => persian.indexOf(digit))
        .replace(/[٠-٩]/g, digit => arabic.indexOf(digit))
        .replace(/\//g, '-')
        .replace(/\s/g, '')
    },
    jalaliToday() {
      return this.normalizeDigits(new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        year: 'numeric', month: '2-digit', day: '2-digit'
      }).format(new Date()))
    },
    currentJalaliParts() {
      const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
        year: 'numeric', month: '2-digit', day: '2-digit'
      }).formatToParts(new Date())
        .filter(part => ['year', 'month', 'day'].includes(part.type))
        .map(part => [part.type, part.value]))

      return {
        year: Number(parts.year),
        month: Number(parts.month),
        day: Number(parts.day)
      }
    },
    previousJalaliMonth() {
      const current = this.currentJalaliParts()
      const month = current.month === 1 ? 12 : current.month - 1
      const year = current.month === 1 ? current.year - 1 : current.year
      return `${year}-${String(month).padStart(2, '0')}`
    },
    isFirstJalaliDay() {
      return this.currentJalaliParts().day === 1
    },
    isJalaliMonthEndReviewDay() {
      const day = this.currentJalaliParts().day
      return day === 29 || day === 30
    },
    currentJalaliMonth() {
      const current = this.currentJalaliParts()
      return `${current.year}-${String(current.month).padStart(2, '0')}`
    },
    gregorianToday() {
      const now = new Date()
      return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
    },
    readLocalArray(key) {
      try {
        const value = JSON.parse(localStorage.getItem(key) || '[]')
        return Array.isArray(value) ? value : []
      } catch {
        return []
      }
    },
    normalizeFollowupDate(value) {
      const date = this.normalizeDigits(value)
      const parts = date.split('-').map(Number)
      if (parts.length !== 3 || parts.some(part => !Number.isFinite(part))) return ''
      return `${parts[0]}-${String(parts[1]).padStart(2, '0')}-${String(parts[2]).padStart(2, '0')}`
    },
    isPendingFollowup(row) {
      const status = String(row?.status || '').trim()
      return !row?.appointmentRegistered && !['پاسخ داد', 'اشتباه'].includes(status)
    },
    isActiveFollowupCampaign(campaign) {
      return String(campaign?.campaignStatus || 'active').trim().toLowerCase() === 'active'
    },
    countTodayFollowups() {
      const jalaliToday = this.jalaliToday()
      const gregorianToday = this.gregorianToday()
      let count = 0
      this.readLocalArray('campaigns_flwup_v1').forEach(campaign => {
        if (!this.isActiveFollowupCampaign(campaign)) return
        ;(Array.isArray(campaign.rows) ? campaign.rows : []).forEach(row => {
          if (!this.isPendingFollowup(row)) return
          const followUpDate = this.normalizeFollowupDate(row.followUpDate)
          if (followUpDate !== gregorianToday && followUpDate !== jalaliToday) return
          count += 1
        })
      })
      return count
    },
    countDelayedInterestFollowups() {
      const thresholdMs = 10 * 60 * 1000
      const now = Date.now()
      let count = 0

      this.readLocalArray('campaigns_flwup_v1').forEach(campaign => {
        ;(Array.isArray(campaign.rows) ? campaign.rows : []).forEach(row => {
          if (row.status !== 'پاسخ داد' || String(row.interest || '').trim()) return
          const answeredAt = Date.parse(row.answeredWithoutInterestAt || '')
          if (Number.isFinite(answeredAt) && now - answeredAt >= thresholdMs) count += 1
        })
      })

      return count
    },
    parseLocalDate(value) {
      const normalized = this.normalizeDigits(value).replace(/\//g, '-')
      const date = new Date(`${normalized}T12:00:00`)
      return Number.isNaN(date.getTime()) ? null : date
    },
    daysSince(value) {
      const date = this.parseLocalDate(value)
      if (!date) return 0
      const today = new Date(`${this.gregorianToday()}T12:00:00`)
      return Math.floor((today.getTime() - date.getTime()) / 86400000)
    },
    isMeaningfulLead(row) {
      return [
        row?.fullName,
        row?.phone,
        row?.contactDate,
        row?.followUpDate,
        row?.gender,
        row?.consultant,
        row?.description,
        row?.source,
        row?.status,
        row?.interest,
        row?.reason
      ].some(value => String(value || '').trim())
    },
    countUncalledCampaignLeadWarnings() {
      let count = 0

      this.readLocalArray('campaigns_flwup_v1').forEach(campaign => {
        if (this.daysSince(campaign.date) < 4) return

        const hasUncalled = (Array.isArray(campaign.rows) ? campaign.rows : [])
          .filter(row => this.isMeaningfulLead(row))
          .some(row => !String(row.status || '').trim())

        if (hasUncalled) count += 1
      })

      return count
    },
    readTicketState() {
      try {
        const state = JSON.parse(localStorage.getItem('tickets_v1') || '{}')
        return {
          tickets: [
            ...(Array.isArray(state.tickets) ? state.tickets : []),
            ...(Array.isArray(state.expiredTickets) ? state.expiredTickets.map(ticket => ({ ...ticket, status: ticket.status || 'expired' })) : [])
          ]
        }
      } catch {
        return { tickets: [] }
      }
    },
    localTodayTicketCount() {
      const jalaliToday = this.jalaliToday()
      const gregorianToday = this.gregorianToday()
      return this.readTicketState().tickets
        .filter(ticket => {
          const date = this.normalizeDigits(ticket.date)
          return (ticket.status === 'active' && (date === jalaliToday || date === gregorianToday)) || ticket.status === 'expired'
        }).length
    },
    async countTodayTickets() {
      const jalaliToday = this.jalaliToday()
      const gregorianToday = this.gregorianToday()

      try {
        const response = await fetch(`${API}/tickets`, { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('tickets request failed')
        const data = await response.json()
        return (Array.isArray(data) ? data : [])
          .filter(ticket => {
            const date = this.normalizeDigits(ticket.date)
            return (ticket.status === 'active' && (date === jalaliToday || date === gregorianToday)) || ticket.status === 'expired'
          }).length
      } catch {
        return this.localTodayTicketCount()
      }
    },
    countInventoryZeroNotifications() {
      try {
        const value = JSON.parse(localStorage.getItem(INVENTORY_ZERO_NOTIFICATIONS_KEY) || '[]')
        return Array.isArray(value) ? value.length : 0
      } catch {
        return 0
      }
    },
    numericValue(value) {
      const normalized = this.normalizeDigits(value).replace(/,/g, '.')
      const number = Number(normalized)
      return Number.isFinite(number) ? number : 0
    },
    serviceNeedsMaterialCount(serviceName, inventoryByName) {
      const item = inventoryByName.get(String(serviceName || '').trim())
      if (!item) return false
      return Number(item.amount || 0) > 0 || item.stock != null || item.min_stock != null
    },
    missingMaterialLines(appointment, inventoryByName) {
      return (Array.isArray(appointment?.services) ? appointment.services : [])
        .flatMap(service => [
          { name: service?.name, cc: service?.cc },
          ...(Array.isArray(service?.addons) ? service.addons.map(addon => ({ name: addon?.name, cc: addon?.cc })) : [])
        ])
        .filter(line => {
          const name = String(line.name || '').trim()
          if (!name || !this.serviceNeedsMaterialCount(name, inventoryByName)) return false
          return this.numericValue(line.cc) <= 0
        })
    },
    async countMissingMaterialAppointments() {
      try {
        const [appointmentsRes, inventoryRes] = await Promise.all([
          fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } }),
          fetch(`${API}/inventory`, { headers: { Accept: 'application/json' } })
        ])
        if (!appointmentsRes.ok || !inventoryRes.ok) return this.notificationCounts.Vaghtdahi

        const appointments = await appointmentsRes.json()
        const inventory = await inventoryRes.json()
        const inventoryByName = new Map((Array.isArray(inventory) ? inventory : [])
          .filter(item => item?.active !== false && item?.name)
          .map(item => [String(item.name).trim(), item]))

        return (Array.isArray(appointments) ? appointments : [])
          .reduce((total, appointment) => total + this.missingMaterialLines(appointment, inventoryByName).length, 0)
      } catch {
        return this.notificationCounts.Vaghtdahi
      }
    },
    async countMissingPhoneAppointments() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        return (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => String(appointment?.lastname || '').trim())
          .filter(appointment => !String(appointment?.phone || '').trim())
          .length
      } catch {
        return 0
      }
    },
    serviceSectionId(serviceName, inventoryByName) {
      const item = inventoryByName.get(String(serviceName || '').trim())
      return item?.section_id || item?.section?.id || ''
    },
    serviceHasDefinedDoctor(sectionId, doctors) {
      const value = String(sectionId || '')
      if (!value) return false
      return (Array.isArray(doctors) ? doctors : []).some(doctor =>
        (Array.isArray(doctor?.service_section_ids) ? doctor.service_section_ids : [])
          .map(String)
          .includes(value)
      )
    },
    appointmentNeedsDoctor(appointment, inventoryByName, doctors) {
      return (Array.isArray(appointment?.services) ? appointment.services : [])
        .some(service => {
          const name = String(service?.name || '').trim()
          if (!name || String(service?.doctor || '').trim()) return false
          const sectionId = service?.section_id || service?.sectionId || this.serviceSectionId(name, inventoryByName)
          return this.serviceHasDefinedDoctor(sectionId, doctors)
        })
    },
    async countMissingDoctorAppointments() {
      try {
        const [appointmentsRes, inventoryRes, doctorsRes] = await Promise.all([
          fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } }),
          fetch(`${API}/inventory`, { headers: { Accept: 'application/json' } }),
          fetch(`${API}/doctors`, { headers: { Accept: 'application/json' } })
        ])
        if (!appointmentsRes.ok || !inventoryRes.ok || !doctorsRes.ok) return this.notificationCounts.Vaghtdahi

        const appointments = await appointmentsRes.json()
        const inventory = await inventoryRes.json()
        const doctors = await doctorsRes.json()
        const doctorList = Array.isArray(doctors) ? doctors : []
        const inventoryByName = new Map((Array.isArray(inventory) ? inventory : [])
          .filter(item => item?.active !== false && item?.name)
          .map(item => [String(item.name).trim(), item]))

        return (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment?.lastname || '').trim())
          .filter(appointment => this.appointmentNeedsDoctor(appointment, inventoryByName, doctorList))
          .length
      } catch {
        return this.notificationCounts.Vaghtdahi
      }
    },
    async countConsultationOnlyPreviousMonth() {
      if (!this.isFirstJalaliDay()) return 0

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0

        const previousMonth = this.previousJalaliMonth()
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || '') === previousMonth)
          .filter(appointment => String(appointment.done || '').trim() === 'مشاوره')
          .forEach((appointment, index) => {
            const identity = String(appointment.phone || '').replace(/\D/g, '')
              || String(appointment.file_number || '').trim()
              || String(appointment.lastname || '').trim()
              || `${appointment.id || index}`
            people.add(identity)
          })

        return people.size > 0 ? 1 : 0
      } catch {
        return 0
      }
    },
    appointmentIdentity(appointment, index) {
      return String(appointment.phone || '').replace(/\D/g, '')
        || String(appointment.file_number || '').trim()
        || String(appointment.lastname || '').trim()
        || `${appointment.id || index}`
    },
    isMeaningfulAppointment(appointment) {
      return [
        appointment?.lastname,
        appointment?.phone,
        appointment?.file_number,
        appointment?.time,
        appointment?.status,
        appointment?.done,
        appointment?.services?.length ? 'service' : ''
      ].some(value => String(value || '').trim())
    },
    isTodayAppointment(appointment) {
      const current = this.currentJalaliParts()
      return String(appointment?.month || '') === `${current.year}-${String(current.month).padStart(2, '0')}`
        && Number(appointment?.day_num || 0) === current.day
    },
    isAfterEightMorning() {
      return new Date().getHours() >= 8
    },
    async countTodayAppointmentSummary() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        const count = (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isTodayAppointment(appointment))
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .length

        return count > 0 ? 1 : 0
      } catch {
        return 0
      }
    },
    isVipAppointment(appointment) {
      return String(appointment?.customer_level || appointment?.customerLevel || '').trim() === 'gold'
    },
    async countTodayVipAppointmentWarning() {
      if (!this.isAfterEightMorning()) return 0

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isTodayAppointment(appointment))
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => this.isVipAppointment(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        return people.size > 0 ? 1 : 0
      } catch {
        return 0
      }
    },
    isCompletedAppointment(appointment) {
      return String(appointment?.done || '').trim() === 'انجام شد'
    },
    async countUnconvertedAppointmentsPreviousMonth() {
      if (!this.isFirstJalaliDay()) return 0

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0

        const previousMonth = this.previousJalaliMonth()
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || '') === previousMonth)
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => !this.isCompletedAppointment(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        return people.size > 0 ? 1 : 0
      } catch {
        return 0
      }
    },
    async countPreviousMonthCancellationWarning() {
      if (!this.isFirstJalaliDay()) return 0

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0

        const previousMonth = this.previousJalaliMonth()
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || '') === previousMonth)
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => this.isCanceledAppointment(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        return people.size > 0 ? 1 : 0
      } catch {
        return 0
      }
    },
    attendanceDayIsBeforeToday(month, day) {
      const current = this.currentJalaliParts()
      const year = Number(month?.year || 0)
      const monthNumber = Number(month?.month || 0)
      const dayNumber = Number(day?.day || 0)
      if (!year || !monthNumber || !dayNumber) return false
      const value = year * 10000 + monthNumber * 100 + dayNumber
      const today = current.year * 10000 + current.month * 100 + current.day
      return value < today
    },
    async countMissingAttendanceExits() {
      try {
        const response = await fetch(`${API}/attendance/months`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return this.notificationCounts.HRtimes
        const data = await response.json()
        const months = Array.isArray(data.months) ? data.months : []

        return months.reduce((total, month) => {
          const missing = (Array.isArray(month.days) ? month.days : [])
            .filter(day => this.attendanceDayIsBeforeToday(month, day))
            .filter(day => String(day?.in || '').trim() && !String(day?.out || '').trim())
            .length
          return total + missing
        }, 0)
      } catch {
        return this.notificationCounts.HRtimes
      }
    },
    appointmentHasPendingSms(appointment) {
      return [appointment?.appointment_sms, appointment?.info_sms]
        .some(value => String(value || '').trim() === 'انتظار')
    },
    async countPendingSmsQueue() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.appointmentHasPendingSms(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        return people.size > 0 ? 1 : 0
      } catch {
        return 0
      }
    },
    previousJalaliDayParts() {
      const date = new Date(`${this.gregorianToday()}T12:00:00`)
      date.setDate(date.getDate() - 1)
      const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
        year: 'numeric', month: '2-digit', day: '2-digit'
      }).formatToParts(date)
        .filter(part => ['year', 'month', 'day'].includes(part.type))
        .map(part => [part.type, part.value]))

      return {
        year: Number(parts.year),
        month: Number(parts.month),
        day: Number(parts.day)
      }
    },
    isYesterdayAppointment(appointment) {
      const previous = this.previousJalaliDayParts()
      return String(appointment?.month || '') === `${previous.year}-${String(previous.month).padStart(2, '0')}`
        && Number(appointment?.day_num || 0) === previous.day
    },
    appointmentOutcomeIsUnresolved(appointment) {
      const status = String(appointment?.status || '').trim()
      const done = String(appointment?.done || '').trim()
      return status === 'وقت داده شد' && !done
    },
    async countUnresolvedYesterdayAppointments() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        return (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isYesterdayAppointment(appointment))
          .filter(appointment => this.appointmentOutcomeIsUnresolved(appointment))
          .filter(appointment => String(appointment?.lastname || '').trim())
          .length
      } catch {
        return 0
      }
    },
    parseDateTime(value) {
      const raw = String(value || '').trim()
      if (!raw) return null
      const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T')
      const date = new Date(normalized)
      return Number.isNaN(date.getTime()) ? null : date
    },
    appointmentMissingDoneAfterArrival(appointment) {
      const status = String(appointment?.status || '').trim()
      if (status !== 'آمد' || String(appointment?.done || '').trim()) return false
      const arrivedAt = this.parseDateTime(appointment?.arrived_at)
      if (!arrivedAt) return false
      return Date.now() - arrivedAt.getTime() >= 60 * 60 * 1000
    },
    async countMissingDoneAfterArrivalAppointments() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        return (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.appointmentMissingDoneAfterArrival(appointment))
          .filter(appointment => String(appointment?.lastname || '').trim())
          .length
      } catch {
        return 0
      }
    },
    async countMissingSourceYesterdayAppointments() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const appointments = await response.json()
        return (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isYesterdayAppointment(appointment))
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => !String(appointment?.source || '').trim())
          .filter(appointment => String(appointment?.lastname || '').trim())
          .length
      } catch {
        return 0
      }
    },
    isCanceledAppointment(appointment) {
      return String(appointment?.status || '').trim() === 'کنسل شد'
    },
    async countHighCancellationWarning() {
      if (!this.isJalaliMonthEndReviewDay()) return 0

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const currentMonth = this.currentJalaliMonth()
        const appointments = await response.json()
        const rows = (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || '') === currentMonth)
          .filter(appointment => this.isMeaningfulAppointment(appointment))

        if (!rows.length) return 0

        const cancelRate = rows.filter(appointment => this.isCanceledAppointment(appointment)).length / rows.length
        return cancelRate >= 0.5 ? 1 : 0
      } catch {
        return 0
      }
    },
    async countUpcomingBirthdays() {
      try {
        const response = await fetch(`${API}/patients/upcoming-birthdays?days=7`, { headers: { Accept: 'application/json' } })
        if (!response.ok) return 0
        const data = await response.json()
        return Array.isArray(data) ? data.length : 0
      } catch {
        return 0
      }
    },
    async countTodayBeautyPatients() {
      try {
        const today = this.gregorianToday()
        const params = new URLSearchParams({ status: 'pending', annotation_date: today })
        const response = await fetch(`${API}/beauty/annotations?${params}`)
        if (!response.ok) return this.notificationCounts.dermatracker
        const data = await response.json()
        if (Number.isFinite(Number(data.patient_count))) return Number(data.patient_count)
        return new Set((data.annotations || [])
          .filter(item => item.annotation_date === today)
          .map(item => item.patient_id)
          .filter(Boolean)).size
      } catch {
        return this.notificationCounts.dermatracker
      }
    },
    async refreshNotificationCounts() {
      const delayedInterestFollowups = this.countDelayedInterestFollowups()
      const uncalledCampaignLeadWarnings = this.countUncalledCampaignLeadWarnings()
      // نشانِ پیگیری فقط تعداد پیگیری‌های واقعیِ سررسید امروز است؛
      // هشدارهای دیگر صرفاً در اعلان کلی محاسبه می‌شوند.
      this.notificationCounts.Peygiri = this.countTodayFollowups()
      this.notificationCounts.Ticket = await this.countTodayTickets()
      this.notificationCounts.Anbar = this.countInventoryZeroNotifications()
      this.notificationCounts.Gozaresh = await this.countHighCancellationWarning()
      this.notificationCounts.HRtimes = await this.countMissingAttendanceExits()
      this.notificationCounts.Vaghtdahi = await this.countTodayAppointmentSummary()
      this.notificationCounts.Vaghtdahi += await this.countTodayVipAppointmentWarning()
      this.notificationCounts.Vaghtdahi += await this.countMissingMaterialAppointments()
      this.notificationCounts.Vaghtdahi += await this.countMissingPhoneAppointments()
      this.notificationCounts.Vaghtdahi += await this.countPendingSmsQueue()
      this.notificationCounts.Vaghtdahi += await this.countUnresolvedYesterdayAppointments()
      this.notificationCounts.Vaghtdahi += await this.countMissingDoneAfterArrivalAppointments()
      this.notificationCounts.Vaghtdahi += await this.countMissingSourceYesterdayAppointments()
      this.notificationCounts.Vaghtdahi += await this.countMissingDoctorAppointments()
      const consultationOnlyPreviousMonth = await this.countConsultationOnlyPreviousMonth()
      this.notificationCounts.Vaghtdahi += consultationOnlyPreviousMonth
      const unconvertedAppointmentsPreviousMonth = await this.countUnconvertedAppointmentsPreviousMonth()
      this.notificationCounts.Vaghtdahi += unconvertedAppointmentsPreviousMonth
      this.notificationCounts.Vaghtdahi += await this.countPreviousMonthCancellationWarning()
      this.notificationCounts.Notif = this.notificationCounts.Anbar + this.notificationCounts.Ticket + this.notificationCounts.Vaghtdahi + this.notificationCounts.HRtimes + this.notificationCounts.Gozaresh + delayedInterestFollowups + uncalledCampaignLeadWarnings
      this.notificationCounts.dermatracker = await this.countTodayBeautyPatients()
      this.notificationCounts.Notif += this.notificationCounts.dermatracker
      this.notificationCounts.Notif += await this.countUpcomingBirthdays()
    },
    formatBadgeCount(count) {
      return count > 99 ? '۹۹+' : Number(count).toLocaleString('fa-IR')
    }
  }
}
</script>

<style scoped>
@import '@/scss/main.scss';

.menu {
  position: sticky;
  top: 10px;
  /* Keep the global navigation and its overflow menu above page-level
     controls such as date pickers on every module. */
  z-index: 2147482000;
  width: calc(100% - 58px);
  min-width: 0;
  margin: 0 0 0 58px;
  padding: 7px 8px;
  overflow-x: visible;
  overflow-y: visible;
  direction: rtl;
  border: 1px solid rgba(219, 234, 254, .9);
  border-radius: 20px;
  background: rgba(255, 255, 255, .84);
  box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
  backdrop-filter: blur(18px) saturate(150%);
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}

.menu::-webkit-scrollbar {
  height: 4px;
}

.menu::-webkit-scrollbar-track {
  background: transparent;
}

.menu::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: #cbd5e1;
}

.menu-items {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: nowrap;
  width: max-content;
  min-width: 100%;
}

.menu-item {
  flex: 0 0 auto;
  white-space: nowrap;
  cursor: pointer;
  min-height: 42px;
  padding: 0 14px;
  color: #475569;
  position: relative;
  user-select: none;
  transition: color 180ms ease, background-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;

  border: 1px solid transparent;
  border-radius: 13px;
  font-size: 12px;
  font-weight: 800;

  background: transparent;

  display: flex;
  align-items: center;
  gap: 8px;
}

.menu-dot {
  width: 7px;
  height: 7px;
  flex: 0 0 7px;
  border-radius: 50%;
  background: #94a3b8;
  box-shadow: 0 0 0 3px rgba(148, 163, 184, .12);
  transition: background-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
}

.menu-item:hover {
  border-color: #dbeafe;
  color: #2563eb;
  background: rgba(239, 246, 255, .9);
}

.menu-item:hover .menu-dot {
  background: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, .12);
  transform: scale(1.08);
}

.menu-item.active {
  border-color: transparent;
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  color: white;
  box-shadow: 0 7px 18px rgba(37, 99, 235, .24);
  transform: translateY(-1px);
}

.menu-item.active .menu-dot {
  background: white;
  box-shadow: 0 0 0 4px rgba(255, 255, 255, .18);
}

.more-menu-item { overflow: visible; }
.more-backdrop{position:fixed;inset:0;z-index:2147482001;border:0;background:transparent;cursor:default}
.more-arrow{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.3;stroke-linecap:round;stroke-linejoin:round;transition:transform 180ms ease}.more-arrow.open{transform:rotate(180deg)}
.more-submenu{position:absolute;top:calc(100% + 8px);right:0;z-index:2147482002;width:190px;padding:8px;display:grid;gap:5px;border:1px solid #dbe3ed;border-radius:12px;background:#fff;box-shadow:0 18px 48px rgba(15,23,42,.18)}
.more-submenu button{min-height:36px;padding:0 10px;display:flex;align-items:center;justify-content:space-between;gap:8px;border:0;border-radius:8px;background:transparent;color:#475569;cursor:pointer;font-size:12px;font-weight:850;text-align:right}.more-submenu button:hover,.more-submenu button.active{background:#eff6ff;color:#2563eb}.more-submenu button b{min-width:20px;height:20px;padding:0 6px;display:inline-grid;place-items:center;border-radius:999px;background:#dc2626;color:#fff;font-size:10px}

.notification-badge {
  position: absolute;
  top: -7px;
  left: -7px;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  border-radius: 999px;
  background: linear-gradient(145deg, #ff453a, #dc2626);
  color: #fff;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.42);
  font-size: 11px;
  font-weight: 900;
  line-height: 1;
  z-index: 2;
  animation: notification-pop 0.28s ease-out;
}

.menu-item.active .notification-badge { border-color: #318dfd; }

@keyframes notification-pop {
  from { opacity: 0; transform: scale(0.45); }
  to { opacity: 1; transform: scale(1); }
}

/* دکمه ضربدر */
.close-btn {
  min-width: 42px;
  justify-content: center;
  padding: 0;
  color: #94a3b8;
}

.close-btn:hover {
  border-color: #e2e8f0;
  color: #475569;
  background: #f8fafc;
}

.close-icon {
  stroke: currentColor;
  stroke-width: 2.4;
  fill: none;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* موبایل */
@media (max-width: 768px) {
  .menu {
    width: calc(100% - 48px);
    margin-left: 48px;
    top: 7px;
    padding: 6px 7px;
    border-radius: 16px;
  }

  .menu-items {
    gap: 3px;
  }

  .menu-item {
    min-height: 38px;
    padding: 0 11px;
    font-size: 11px;
  }

  .menu-dot {
    width: 6px;
    height: 6px;
    flex-basis: 6px;
  }

  .notification-badge {
    top: -6px;
    left: -6px;
    min-width: 21px;
    height: 21px;
    font-size: 10px;
  }
}

:global(.dark) .menu {
  border-color: rgba(51, 65, 85, .9);
  background: rgba(23, 32, 51, .88);
  box-shadow: 0 12px 32px rgba(0, 0, 0, .22);
}

:global(.dark) .menu-item {
  background: transparent !important;
  color: #cbd5e1 !important;
}

:global(.dark) .menu-item:hover {
  border-color: #334155;
  background: #1e293b !important;
  color: #93c5fd !important;
}

:global(.dark) .menu-item.active {
  background: linear-gradient(135deg, #2563eb, #3b82f6) !important;
  color: #fff !important;
}

:global(.dark) .more-submenu {
  border-color: #334155;
  background: #172033;
}

:global(.dark) .more-submenu button {
  color: #cbd5e1;
}

:global(.dark) .more-submenu button:hover,
:global(.dark) .more-submenu button.active {
  background: #1e293b;
  color: #93c5fd;
}
</style>
