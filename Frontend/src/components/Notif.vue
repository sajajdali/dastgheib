<template>
  <div class="notif-page">
    <h2 class="notif-page-title">سرنخ ها</h2>

    <div v-if="notifications.length" class="notif-table">
      <div class="notif-row notif-header">
        <div>نوع اعلان</div>
        <div>عنوان</div>
        <div>توضیحات</div>
        <div>عملیات</div>
      </div>

      <div
        v-for="notif in notifications"
        :key="notif.id"
        :class="['notif-row', notif.tone === 'orange' ? 'notif-row-orange' : '']"
      >
        <div class="notif-type">{{ notif.type }}</div>
        <div class="notif-title">{{ notif.title }}</div>
        <div class="notif-message" v-html="notif.message"></div>
        <div class="notif-actions">
          <button class="notif-icon-btn notif-done-btn" title="انجام شد" aria-label="انجام شد" @click="completeNotif(notif)">
            ✓
          </button>
          <button class="notif-icon-btn notif-dismiss-btn" title="دیگر نمایش نده" aria-label="دیگر نمایش نده" @click="dismissNotif(notif)">
            ×
          </button>
          <button class="notif-btn" @click="handleNotif(notif)">
            بررسی
          </button>
        </div>
      </div>
    </div>

    <div v-else class="empty-notif">
      سرنخ فعالی وجود ندارد.
    </div>
  </div>
</template>

<script>
const API = "/api"
const INVENTORY_ZERO_NOTIFICATIONS_KEY = "inventory_zero_stock_notifs_v1"
const NOTIFICATION_HANDLED_KEY = "handled_notifications_v1"

export default {

  name: "Notif",

  data() {

    return {

      notifications: [],

      handledNotificationIds: []

    }

  },

  mounted() {

    this.loadNotifications()

  },

  methods: {

    gregorianToday() {
      const now = new Date()
      return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`
    },

    normalizeDigits(value) {
      const persian = "۰۱۲۳۴۵۶۷۸۹"
      const arabic = "٠١٢٣٤٥٦٧٨٩"
      return String(value || "")
        .replace(/[۰-۹]/g, digit => persian.indexOf(digit))
        .replace(/[٠-٩]/g, digit => arabic.indexOf(digit))
        .replace(/\//g, "-")
        .replace(/\s/g, "")
    },

    jalaliToday() {
      return this.normalizeDigits(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric", month: "2-digit", day: "2-digit"
      }).format(new Date()))
    },

    currentJalaliParts() {
      const parts = Object.fromEntries(new Intl.DateTimeFormat("en-US-u-ca-persian-nu-latn", {
        year: "numeric", month: "2-digit", day: "2-digit"
      }).formatToParts(new Date())
        .filter(part => ["year", "month", "day"].includes(part.type))
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
      return `${year}-${String(month).padStart(2, "0")}`
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
      return `${current.year}-${String(current.month).padStart(2, "0")}`
    },

    isTodayDate(value) {
      const date = this.normalizeDigits(value)
      return date === this.gregorianToday() || date === this.jalaliToday()
    },

    isTodayAppointment(appointment) {
      const current = this.currentJalaliParts()
      return String(appointment?.month || "") === `${current.year}-${String(current.month).padStart(2, "0")}`
        && Number(appointment?.day_num || 0) === current.day
    },

    isAfterEightMorning() {
      return new Date().getHours() >= 8
    },

    patientName(patient) {
      return [patient?.first_name, patient?.last_name].filter(Boolean).join(" ") || "بیمار بدون نام"
    },

    patientTitle(patient) {
      const gender = String(patient?.gender || "").trim()
      if (gender.includes("مرد") || gender.toLowerCase() === "male") return "آقای"
      return "خانم"
    },

    async loadNotifications() {

      const saved =
        localStorage.getItem(
          "pending_appointments_notif"
        )

      this.handledNotificationIds = this.readHandledNotificationIds()
      this.notifications = []

      if (saved) {

        let pendingDays = []

        try {
          const parsed = JSON.parse(saved)
          pendingDays = Array.isArray(parsed) ? parsed : []
        } catch {
          pendingDays = []
        }

        pendingDays.forEach((day, index) => {

          this.notifications.push({

            id: `pending-appointment-${index}`,

            type: "نوبت",

            title: "نیاز به تعیین تکلیف نوبت",

            day: day,

            message: `
              مشکلی در تعیین تکلیف روز
              <b>${day}</b>
              به وجود آمده است.
              خواهشمند است نسبت به تعیین تکلیف نوبت اقدام فرمایید.
            `,

            action: "time"

          })

        })

      }

      this.loadFollowupInterestNotifications()

      this.loadUncalledCampaignLeadNotifications()

      this.loadInventoryNotifications()

      await this.loadTodayAppointmentSummaryNotification()

      await this.loadTodayVipAppointmentNotification()

      await this.loadConsultationOnlyNotifications()

      await this.loadUnconvertedAppointmentNotifications()

      await this.loadPreviousMonthCancellationNotifications()

      await this.loadBirthdayNotifications()

      await this.loadMissingMaterialNotifications()

      await this.loadMissingPhoneNotifications()

      await this.loadMissingDoctorNotifications()

      await this.loadMissingPhotoNotifications()

      await this.loadMissingAttendanceExitNotifications()

      await this.loadPendingSmsQueueNotifications()

      await this.loadUnresolvedYesterdayAppointmentNotifications()

      await this.loadMissingDoneAfterArrivalNotifications()

      await this.loadMissingSourceYesterdayNotifications()

      await this.loadHighCancellationNotifications()

      await this.loadTicketNotifications()

      await this.loadBeautyNotifications()

      this.notifications = this.notifications.filter(notif => !this.isNotifHandled(notif))

    },

    readHandledNotificationIds() {
      try {
        const value = JSON.parse(localStorage.getItem(NOTIFICATION_HANDLED_KEY) || "[]")
        return Array.isArray(value) ? value : []
      } catch {
        return []
      }
    },

    saveHandledNotificationIds() {
      localStorage.setItem(NOTIFICATION_HANDLED_KEY, JSON.stringify(this.handledNotificationIds))
      window.dispatchEvent(new CustomEvent("app:notifications-changed"))
    },

    isNotifHandled(notif) {
      return this.handledNotificationIds.includes(String(notif?.id || ""))
    },

    markNotifHandled(notif) {
      const id = String(notif?.id || "")
      if (!id) return
      if (!this.handledNotificationIds.includes(id)) {
        this.handledNotificationIds.push(id)
      }
      this.saveHandledNotificationIds()
      this.notifications = this.notifications.filter(item => String(item.id || "") !== id)
    },

    completeNotif(notif) {
      this.markNotifHandled(notif)
    },

    dismissNotif(notif) {
      this.markNotifHandled(notif)
    },

    personTitle(person) {
      const gender = String(person?.gender || "").trim()
      if (gender.includes("مرد") || gender.toLowerCase() === "male") return "آقای"
      return "خانم"
    },

    async loadTodayAppointmentSummaryNotification() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()
        const count = (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isTodayAppointment(appointment))
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .length

        if (!count) return

        this.notifications.push({
          id: `today-appointments-summary-${this.jalaliToday()}`,
          type: "وقت‌دهی",
          title: "نوبت‌های امروز",
          message: `امروز ${Number(count).toLocaleString("fa-IR")} نوبت دارید، براتون آرزوی موفقیت می‌کنم`,
          action: "appointment-material"
        })
      } catch {
        // Today appointment summary is only shown when appointment data is available.
      }
    },

    isVipAppointment(appointment) {
      return String(appointment?.customer_level || appointment?.customerLevel || "").trim() === "gold"
    },

    async loadTodayVipAppointmentNotification() {
      if (!this.isAfterEightMorning()) return

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isTodayAppointment(appointment))
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => this.isVipAppointment(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        if (!people.size) return

        this.notifications.push({
          id: `today-vip-appointments-${this.jalaliToday()}`,
          type: "وقت‌دهی",
          title: "مشتری‌های ویژه امروز",
          message: `امروز ${Number(people.size).toLocaleString("fa-IR")} مشتری ویژه داری، هواشونو داشته باش`,
          action: "appointment-material"
        })
      } catch {
        // VIP appointment reminder is only shown when appointment data is available.
      }
    },

    numericValue(value) {
      const normalized = this.normalizeDigits(value).replace(/,/g, ".")
      const number = Number(normalized)
      return Number.isFinite(number) ? number : 0
    },

    serviceNeedsMaterialCount(serviceName, inventoryByName) {
      const item = inventoryByName.get(String(serviceName || "").trim())
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
          const name = String(line.name || "").trim()
          if (!name || !this.serviceNeedsMaterialCount(name, inventoryByName)) return false
          return this.numericValue(line.cc) <= 0
        })
    },

    async loadMissingPhoneNotifications() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => String(appointment?.lastname || "").trim())
          .filter(appointment => !String(appointment?.phone || "").trim())
          .forEach((appointment) => {
            const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()

            this.notifications.push({
              id: `appointment-missing-phone-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}-${patientName}`}`,
              type: "وقت‌دهی",
              title: "شماره تماس مشخص نشده",
              appointment,
              message: `${this.personTitle(appointment)} ${patientName} شماره تماس نداره`,
              action: "appointment-material"
            })
          })
      } catch {
        // Missing phone reminders are shown only when appointment data is available.
      }
    },

    async loadMissingMaterialNotifications() {

      try {
        const [appointmentsRes, inventoryRes] = await Promise.all([
          fetch(`${API}/appointments`, { headers: { Accept: "application/json" } }),
          fetch(`${API}/inventory`, { headers: { Accept: "application/json" } })
        ])

        if (!appointmentsRes.ok || !inventoryRes.ok) return

        const appointments = await appointmentsRes.json()
        const inventory = await inventoryRes.json()
        const inventoryByName = new Map((Array.isArray(inventory) ? inventory : [])
          .filter(item => item?.active !== false && item?.name)
          .map(item => [String(item.name).trim(), item]))

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment?.lastname || "").trim())
          .forEach((appointment) => {
            this.missingMaterialLines(appointment, inventoryByName).forEach((line, index) => {
              const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()
              const serviceName = String(line.name || "خدمت").trim()

              this.notifications.push({

                id: `appointment-missing-material-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}`}-${serviceName}-${index}`,

                type: "وقت‌دهی",

                title: "تعداد مواد مصرفی مشخص نشده",

                appointment,

                message: `${this.personTitle(appointment)} ${patientName} تعداد مواد مصرفی ${serviceName} مشخص نشده`,

                action: "appointment-material"

              })
            })
          })

      } catch {
        // Missing material reminders are best-effort so the notifications page stays usable.
      }

    },

    serviceSectionId(serviceName, inventoryByName) {
      const item = inventoryByName.get(String(serviceName || "").trim())
      return item?.section_id || item?.section?.id || ""
    },

    serviceHasDefinedDoctor(sectionId, doctors) {
      const value = String(sectionId || "")
      if (!value) return false
      return (Array.isArray(doctors) ? doctors : []).some(doctor =>
        (Array.isArray(doctor?.service_section_ids) ? doctor.service_section_ids : [])
          .map(String)
          .includes(value)
      )
    },

    missingDoctorLines(appointment, inventoryByName, doctors) {
      return (Array.isArray(appointment?.services) ? appointment.services : [])
        .filter(service => {
          const name = String(service?.name || "").trim()
          if (!name || String(service?.doctor || "").trim()) return false
          const sectionId = service?.section_id || service?.sectionId || this.serviceSectionId(name, inventoryByName)
          return this.serviceHasDefinedDoctor(sectionId, doctors)
        })
    },

    appointmentServiceNames(appointment) {
      return (Array.isArray(appointment?.services) ? appointment.services : [])
        .flatMap(service => [
          service?.name,
          ...(Array.isArray(service?.addons) ? service.addons.map(addon => addon?.name) : [])
        ])
        .map(name => String(name || "").trim())
        .filter(Boolean)
    },

    normalizeServiceName(value) {
      return String(value || "")
        .toLowerCase()
        .replace(/[ي]/g, "ی")
        .replace(/[ك]/g, "ک")
        .replace(/\s+/g, " ")
        .trim()
    },

    mediaServiceNames(media) {
      let services = media?.services || []
      if (typeof services === "string") {
        try { services = JSON.parse(services) } catch { services = [] }
      }

      if (!Array.isArray(services)) return []

      return services
        .flatMap(service => [service?.section, service?.name, service?.service, service?.title, typeof service === "string" ? service : ""])
        .map(name => String(name || "").trim())
        .filter(Boolean)
    },

    mediaIsPhoto(media) {
      return String(media?.media_type || "").trim() === "image" || String(media?.mime_type || "").startsWith("image/")
    },

    mediaMatchesService(media, serviceName) {
      const wanted = this.normalizeServiceName(serviceName)
      if (!wanted) return false
      return this.mediaServiceNames(media)
        .map(name => this.normalizeServiceName(name))
        .some(name => name === wanted)
    },

    missingPhotoAlertsForAppointment(appointment, media) {
      const photos = (Array.isArray(media) ? media : []).filter(item => this.mediaIsPhoto(item))
      const beforePhotos = photos.filter(item => String(item?.comparison_stage || "").trim() === "before")
      const title = this.personTitle(appointment)
      const patientName = String(appointment?.lastname || "مراجعه‌کننده").trim()

      if (!beforePhotos.length) {
        return [{
          id: `missing-before-photo-${appointment.id || appointment.patient_id || `${appointment.month}-${appointment.day_num}-${appointment.time}`}`,
          title: "عکس بیمار ثبت نشده",
          message: `${title} ${patientName} عکس نداره`
        }]
      }

      return this.appointmentServiceNames(appointment)
        .filter((serviceName, index, list) => list.indexOf(serviceName) === index)
        .filter(serviceName => !photos.some(item =>
          String(item?.comparison_stage || "").trim() === "after" && this.mediaMatchesService(item, serviceName)
        ))
        .map(serviceName => ({
          id: `missing-after-photo-${appointment.id || appointment.patient_id || `${appointment.month}-${appointment.day_num}-${appointment.time}`}-${serviceName}`,
          title: "عکس بعد خدمت ثبت نشده",
          message: `${title} ${patientName} عکس بعد ${serviceName} نداره`
        }))
    },

    async loadMissingPhotoNotifications() {
      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()
        const arrivedAppointments = (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment?.status || "").trim() === "آمد")
          .filter(appointment => appointment?.patient_id)
          .filter(appointment => String(appointment?.lastname || "").trim())

        const mediaCache = new Map()

        for (const appointment of arrivedAppointments) {
          const patientId = appointment.patient_id
          if (!mediaCache.has(patientId)) {
            const mediaResponse = await fetch(`${API}/patients/${patientId}/media?all=1`, { headers: { Accept: "application/json" } })
            if (!mediaResponse.ok) {
              mediaCache.set(patientId, [])
            } else {
              const data = await mediaResponse.json()
              mediaCache.set(patientId, Array.isArray(data.media) ? data.media : [])
            }
          }

          this.missingPhotoAlertsForAppointment(appointment, mediaCache.get(patientId))
            .forEach((alert) => {
              this.notifications.push({
                id: alert.id,
                type: "عکس‌ها",
                title: alert.title,
                appointment,
                patient: {
                  id: patientId,
                  first_name: appointment.firstname || "",
                  last_name: appointment.lastname || "",
                  file_number: appointment.file_number || "",
                  gender: appointment.gender || ""
                },
                message: alert.message,
                action: "photos",
                tone: "orange"
              })
            })
        }
      } catch {
        // Missing photo reminders should not block the notification list.
      }
    },

    async loadMissingDoctorNotifications() {

      try {
        const [appointmentsRes, inventoryRes, doctorsRes] = await Promise.all([
          fetch(`${API}/appointments`, { headers: { Accept: "application/json" } }),
          fetch(`${API}/inventory`, { headers: { Accept: "application/json" } }),
          fetch(`${API}/doctors`, { headers: { Accept: "application/json" } })
        ])

        if (!appointmentsRes.ok || !inventoryRes.ok || !doctorsRes.ok) return

        const appointments = await appointmentsRes.json()
        const inventory = await inventoryRes.json()
        const doctors = await doctorsRes.json()
        const inventoryByName = new Map((Array.isArray(inventory) ? inventory : [])
          .filter(item => item?.active !== false && item?.name)
          .map(item => [String(item.name).trim(), item]))

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment?.lastname || "").trim())
          .forEach((appointment) => {
            const missingLines = this.missingDoctorLines(appointment, inventoryByName, doctors)
            if (!missingLines.length) return

            const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()

            this.notifications.push({

              id: `appointment-missing-doctor-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}`}`,

              type: "وقت‌دهی",

              title: "نام پزشک مشخص نشده",

              appointment,

              message: `نام پزشک ${this.personTitle(appointment)} ${patientName} مشخص نشده`,

              action: "appointment-material"

            })
          })

      } catch {
        // Missing doctor reminders should not block the notification list.
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

    async loadMissingAttendanceExitNotifications() {

      try {
        const response = await fetch(`${API}/attendance/months`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const data = await response.json()
        const months = Array.isArray(data.months) ? data.months : []

        months.forEach((month) => {
          ;(Array.isArray(month.days) ? month.days : [])
            .filter(day => this.attendanceDayIsBeforeToday(month, day))
            .filter(day => String(day?.in || "").trim() && !String(day?.out || "").trim())
            .forEach((day) => {
              const name = String(month.name || "پرسنل").trim()

              this.notifications.push({

                id: `attendance-missing-out-${month.id || `${month.resource_type}-${month.resource_id}-${month.year}-${month.month}`}-${day.day}`,

                type: "حضور و غیاب",

                title: "ساعت خروج مشخص نشده",

                month,

                message: `پرسنل ${name} ساعت خروجش تعیین نشده`,

                action: "attendance"

              })
            })
        })

      } catch {
        // Attendance reminders should not block the notification list.
      }

    },

    appointmentHasPendingSms(appointment) {
      return [appointment?.appointment_sms, appointment?.info_sms]
        .some(value => String(value || "").trim() === "انتظار")
    },

    async loadPendingSmsQueueNotifications() {

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.appointmentHasPendingSms(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        if (!people.size) return

        this.notifications.push({

          id: "pending-sms-queue",

          type: "وقت‌دهی",

          title: "صف انتظار پیامک",

          message: `${Number(people.size).toLocaleString("fa-IR")} نفر در صف انتظار ارسال پیامک`,

          action: "appointment-material"

        })

      } catch {
        // Pending SMS queue is only shown when appointment data is available.
      }

    },

    previousGregorianDate() {
      const date = new Date(`${this.gregorianToday()}T12:00:00`)
      date.setDate(date.getDate() - 1)
      return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
    },

    previousJalaliDayParts() {
      const date = new Date(`${this.gregorianToday()}T12:00:00`)
      date.setDate(date.getDate() - 1)
      const parts = Object.fromEntries(new Intl.DateTimeFormat("en-US-u-ca-persian-nu-latn", {
        year: "numeric", month: "2-digit", day: "2-digit"
      }).formatToParts(date)
        .filter(part => ["year", "month", "day"].includes(part.type))
        .map(part => [part.type, part.value]))

      return {
        year: Number(parts.year),
        month: Number(parts.month),
        day: Number(parts.day)
      }
    },

    isYesterdayAppointment(appointment) {
      const previous = this.previousJalaliDayParts()
      return String(appointment?.month || "") === `${previous.year}-${String(previous.month).padStart(2, "0")}`
        && Number(appointment?.day_num || 0) === previous.day
    },

    appointmentOutcomeIsUnresolved(appointment) {
      const status = String(appointment?.status || "").trim()
      const done = String(appointment?.done || "").trim()
      return status === "وقت داده شد" && !done
    },

    async loadUnresolvedYesterdayAppointmentNotifications() {

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isYesterdayAppointment(appointment))
          .filter(appointment => this.appointmentOutcomeIsUnresolved(appointment))
          .filter(appointment => String(appointment?.lastname || "").trim())
          .forEach((appointment) => {
            const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()

            this.notifications.push({

              id: `unresolved-yesterday-appointment-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}-${patientName}`}`,

              type: "وقت‌دهی",

              title: "وضعیت نوبت مشخص نشده",

              appointment,

              message: `وضعیت ${this.personTitle(appointment)} ${patientName} را مشخص کنید`,

              action: "appointment-material"

            })
          })

      } catch {
        // Yesterday appointment outcome reminder is only shown when appointment data is available.
      }

    },

    parseDateTime(value) {
      const raw = String(value || "").trim()
      if (!raw) return null
      const normalized = raw.includes("T") ? raw : raw.replace(" ", "T")
      const date = new Date(normalized)
      return Number.isNaN(date.getTime()) ? null : date
    },

    appointmentMissingDoneAfterArrival(appointment) {
      const status = String(appointment?.status || "").trim()
      if (status !== "آمد" || String(appointment?.done || "").trim()) return false
      const arrivedAt = this.parseDateTime(appointment?.arrived_at)
      if (!arrivedAt) return false
      return Date.now() - arrivedAt.getTime() >= 60 * 60 * 1000
    },

    async loadMissingDoneAfterArrivalNotifications() {

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.appointmentMissingDoneAfterArrival(appointment))
          .filter(appointment => String(appointment?.lastname || "").trim())
          .forEach((appointment) => {
            const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()

            this.notifications.push({

              id: `missing-done-after-arrival-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}-${patientName}`}`,

              type: "وقت‌دهی",

              title: "انجام کار مشخص نشده",

              appointment,

              message: `ستون «انجام کار» ${this.personTitle(appointment)} ${patientName} را مشخص کنید`,

              action: "appointment-material"

            })
          })

      } catch {
        // Missing done reminder is only shown when appointment data is available.
      }

    },

    async loadMissingSourceYesterdayNotifications() {

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const appointments = await response.json()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => this.isYesterdayAppointment(appointment))
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => !String(appointment?.source || "").trim())
          .filter(appointment => String(appointment?.lastname || "").trim())
          .forEach((appointment) => {
            const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()

            this.notifications.push({

              id: `missing-source-yesterday-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}-${patientName}`}`,

              type: "وقت‌دهی",

              title: "منبع مراجعه مشخص نشده",

              appointment,

              message: `منبع ${this.personTitle(appointment)} ${patientName} مشخص نشده`,

              action: "appointment-material"

            })
          })

      } catch {
        // Missing source reminder is only shown when appointment data is available.
      }

    },

    isCanceledAppointment(appointment) {
      return String(appointment?.status || "").trim() === "کنسل شد"
    },

    async loadHighCancellationNotifications() {
      if (!this.isJalaliMonthEndReviewDay()) return

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const currentMonth = this.currentJalaliMonth()
        const appointments = (await response.json())
        const rows = (Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || "") === currentMonth)
          .filter(appointment => this.isMeaningfulAppointment(appointment))

        if (!rows.length) return

        const cancelRate = rows.filter(appointment => this.isCanceledAppointment(appointment)).length / rows.length
        if (cancelRate < 0.5) return

        this.notifications.push({

          id: `high-cancellation-${currentMonth}`,

          type: "گزارش",

          title: "کنسلی زیاد نوبت‌ها",

          message: "این ماه خیلی کنسلی داشتی، از گزارش‌ها علتش رو تحلیل کن یا اگه خودت نمی‌تونی از یک مشاور یا مدیر فروش کمک بگیر",

          action: "reports"

        })

      } catch {
        // Cancellation analysis reminder is only shown when appointment data is available.
      }

    },

    async loadConsultationOnlyNotifications() {
      if (!this.isFirstJalaliDay()) return

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const previousMonth = this.previousJalaliMonth()
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || "") === previousMonth)
          .filter(appointment => String(appointment.done || "").trim() === "مشاوره")
          .forEach((appointment, index) => {
            const identity = String(appointment.phone || "").replace(/\D/g, "")
              || String(appointment.file_number || "").trim()
              || String(appointment.lastname || "").trim()
              || `${appointment.id || index}`
            people.add(identity)
          })

        if (!people.size) return

        this.notifications.push({

          id: `consultation-only-${previousMonth}`,

          type: "وقت‌دهی",

          title: "مشاوره‌های تبدیل‌نشده",

          message: `ماه پیش ${Number(people.size).toLocaleString("fa-IR")} نفر فقط مشاوره گرفتند و منجر به انجام کار نشدن، برنامه‌ای براشون نداری؟`,

          action: "appointment-material"

        })

      } catch {
        // Consultation-only reminder is only shown when appointment data is available.
      }
    },

    appointmentIdentity(appointment, index) {
      return String(appointment.phone || "").replace(/\D/g, "")
        || String(appointment.file_number || "").trim()
        || String(appointment.lastname || "").trim()
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
        appointment?.services?.length ? "service" : ""
      ].some(value => String(value || "").trim())
    },

    isCompletedAppointment(appointment) {
      return String(appointment?.done || "").trim() === "انجام شد"
    },

    async loadUnconvertedAppointmentNotifications() {
      if (!this.isFirstJalaliDay()) return

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const previousMonth = this.previousJalaliMonth()
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || "") === previousMonth)
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => !this.isCompletedAppointment(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        if (!people.size) return

        this.notifications.push({

          id: `unconverted-appointments-${previousMonth}`,

          type: "وقت‌دهی",

          title: "نوبت‌های تبدیل‌نشده",

          message: `ماه پیش ${Number(people.size).toLocaleString("fa-IR")} نفر منجر به انجام کار نشدن، برنامه‌ای براشون نداری؟`,

          action: "appointment-material"

        })

      } catch {
        // Unconverted appointment reminder is only shown when appointment data is available.
      }
    },

    async loadPreviousMonthCancellationNotifications() {
      if (!this.isFirstJalaliDay()) return

      try {
        const response = await fetch(`${API}/appointments`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const previousMonth = this.previousJalaliMonth()
        const appointments = await response.json()
        const people = new Set()

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment.month || "") === previousMonth)
          .filter(appointment => this.isMeaningfulAppointment(appointment))
          .filter(appointment => this.isCanceledAppointment(appointment))
          .forEach((appointment, index) => {
            people.add(this.appointmentIdentity(appointment, index))
          })

        if (!people.size) return

        this.notifications.push({
          id: `previous-month-cancellations-${previousMonth}`,
          type: "وقت‌دهی",
          title: "کنسلی‌های ماه پیش",
          message: `ماه پیش ${Number(people.size).toLocaleString("fa-IR")} نفر کنسلی داشتی، برنامه‌ای براشون نداری؟`,
          action: "appointment-material"
        })
      } catch {
        // Previous month cancellation reminder is only shown when appointment data is available.
      }
    },

    readLocalArray(key) {
      try {
        const value = JSON.parse(localStorage.getItem(key) || "[]")
        return Array.isArray(value) ? value : []
      } catch {
        return []
      }
    },

    loadFollowupInterestNotifications() {
      const thresholdMs = 10 * 60 * 1000
      const now = Date.now()

      this.readLocalArray("campaigns_flwup_v1").forEach((campaign) => {
        const campaignNumber = campaign.id || campaign.title || "نامشخص"

        ;(Array.isArray(campaign.rows) ? campaign.rows : []).forEach((row, index) => {
          if (row.status !== "پاسخ داد" || String(row.interest || "").trim()) return

          const answeredAt = Date.parse(row.answeredWithoutInterestAt || "")
          if (!Number.isFinite(answeredAt) || now - answeredAt < thresholdMs) return

          const name = String(row.fullName || "مراجعه‌کننده").trim()

          this.notifications.push({

            id: `followup-interest-${campaign.id || campaign.title || "campaign"}-${row._localId || index}`,

            type: "پیگیری",

            title: "درجه تمایل مشخص نشده",

            campaignId: campaign.id || null,

            message: `درجه تمایل ${this.personTitle(row)} ${name} در کمپین شماره ${campaignNumber} مشخص نشده`,

            action: "followup"

          })
        })
      })
    },

    parseLocalDate(value) {
      const normalized = this.normalizeDigits(value).replace(/\//g, "-")
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
      ].some(value => String(value || "").trim())
    },

    campaignNumber(campaign, index) {
      return campaign.number || campaign.campaignNumber || campaign.id || index + 1
    },

    loadUncalledCampaignLeadNotifications() {
      this.readLocalArray("campaigns_flwup_v1").forEach((campaign, campaignIndex) => {
        if (this.daysSince(campaign.date) < 4) return

        const uncalledCount = (Array.isArray(campaign.rows) ? campaign.rows : [])
          .filter(row => this.isMeaningfulLead(row))
          .filter(row => !String(row.status || "").trim())
          .length

        if (!uncalledCount) return

        const number = this.campaignNumber(campaign, campaignIndex)

        this.notifications.push({

          id: `uncalled-campaign-leads-${campaign.id || campaignIndex}`,

          type: "پیگیری",

          title: "لیدهای تماس‌گرفته‌نشده",

          campaignId: campaign.id || null,

          message: `${Number(uncalledCount).toLocaleString("fa-IR")} لید تماس‌گرفته‌نشده در کمپین شماره ${number} داری، تا سوخت نشده یه فکری براشون بکن`,

          action: "followup"

        })
      })
    },

    patientDisplayName(patient) {
      return [patient?.first_name, patient?.last_name].filter(Boolean).join(" ").trim()
        || patient?.last_name
        || patient?.first_name
        || "مراجعه‌کننده"
    },

    async loadBirthdayNotifications() {

      try {
        const response = await fetch(`${API}/patients/upcoming-birthdays?days=7`, { headers: { Accept: "application/json" } })
        if (!response.ok) return

        const patients = await response.json()

        ;(Array.isArray(patients) ? patients : []).forEach((patient) => {
          const name = this.patientDisplayName(patient)

          this.notifications.push({

            id: `birthday-${patient.id || name}-${patient.birth_date || ""}`,

            type: "پرونده",

            title: "تولد نزدیک",

            patient,

            message: `تولد ${this.patientTitle(patient)} ${name} نزدیکه، برنامه‌ای براش داری؟`,

            action: "birthday"

          })

        })

      } catch {
        // Birthday reminders should not block the rest of the notification list.
      }

    },

    doctorAvailableForService(service, doctors) {
      const sectionId = service?.section_id || service?.sectionId || ""
      if (!sectionId) return doctors.length > 0

      return doctors.some(doctor => {
        const sections = Array.isArray(doctor?.service_section_ids) ? doctor.service_section_ids.map(String) : []
        return sections.includes(String(sectionId))
      })
    },

    appointmentNeedsDoctor(appointment, doctors) {
      return (Array.isArray(appointment?.services) ? appointment.services : [])
        .some(service => {
          const hasService = String(service?.name || "").trim()
          const hasDoctor = String(service?.doctor || appointment?.doctor || "").trim()
          return hasService && !hasDoctor && this.doctorAvailableForService(service, doctors)
        })
    },

    async loadMissingDoctorNotifications() {

      try {
        const [appointmentsRes, doctorsRes] = await Promise.all([
          fetch(`${API}/appointments`, { headers: { Accept: "application/json" } }),
          fetch(`${API}/doctors`, { headers: { Accept: "application/json" } })
        ])

        if (!appointmentsRes.ok || !doctorsRes.ok) return

        const appointments = await appointmentsRes.json()
        const doctors = await doctorsRes.json()
        const doctorList = Array.isArray(doctors) ? doctors : []

        ;(Array.isArray(appointments) ? appointments : [])
          .filter(appointment => String(appointment?.lastname || "").trim())
          .filter(appointment => this.appointmentNeedsDoctor(appointment, doctorList))
          .forEach((appointment) => {
            const patientName = String(appointment.lastname || "مراجعه‌کننده").trim()

            this.notifications.push({

              id: `appointment-missing-doctor-${appointment.id || `${appointment.month}-${appointment.day_num}-${appointment.time}`}`,

              type: "وقت‌دهی",

              title: "نام پزشک مشخص نشده",

              appointment,

              message: `نام پزشک ${this.personTitle(appointment)} ${patientName} مشخص نشده`,

              action: "appointment-material"

            })
          })

      } catch {
        // Missing doctor reminders should not block other notifications.
      }

    },

    readLocalTicketState() {
      try {
        const state = JSON.parse(localStorage.getItem("tickets_v1") || "{}")
        return [
          ...(Array.isArray(state.tickets) ? state.tickets : []),
          ...(Array.isArray(state.expiredTickets) ? state.expiredTickets.map(ticket => ({ ...ticket, status: ticket.status || "expired" })) : [])
        ]
      } catch {
        return []
      }
    },

    async loadTicketNotifications() {

      let tickets = []

      try {
        const response = await fetch(`${API}/tickets`, { headers: { Accept: "application/json" } })
        if (!response.ok) throw new Error("tickets request failed")
        const data = await response.json()
        tickets = Array.isArray(data) ? data : []
      } catch {
        tickets = this.readLocalTicketState()
      }

      tickets
        .filter(ticket => ticket.status === "active" && this.isTodayDate(ticket.date))
        .forEach((ticket) => {
          const subject = ticket.subject || "تیکت بدون موضوع"

          this.notifications.push({

            id: `ticket-today-${ticket.id || subject}-${this.normalizeDigits(ticket.date)}`,

            type: "تیکت",

            title: "تیکت فعال امروز",

            ticket,

            message: `امروز تیکت فعال ${subject} دارید`,

            action: "ticket"

          })

        })

      tickets
        .filter(ticket => ticket.status === "expired")
        .forEach((ticket) => {
          const subject = ticket.subject || "تیکت بدون موضوع"

          this.notifications.push({

            id: `ticket-expired-${ticket.id || subject}-${this.normalizeDigits(ticket.date)}`,

            type: "تیکت",

            title: "تیکت منقضی شده",

            ticket,

            message: `تیکت ${subject} منقضی شده، نمی‌خوای دوباره به جریان بندازیش؟`,

            action: "ticket"

          })

        })

    },

    loadInventoryNotifications() {

      let notifications = []

      try {
        const parsed = JSON.parse(localStorage.getItem(INVENTORY_ZERO_NOTIFICATIONS_KEY) || "[]")
        notifications = Array.isArray(parsed) ? parsed : []
      } catch {
        notifications = []
      }

      notifications.forEach((item) => {
        const itemName = item.itemName || "محصول بدون نام"

        this.notifications.push({

          id: `inventory-zero-${item.id || item.eventKey || item.inventoryKey || itemName}`,

          type: "انبار",

          title: "اتمام موجودی محصول",

          itemName,

          message: `محصول ${itemName} به اتمام رسید`,

          action: "inventory"

        })

      })

    },

    async loadBeautyNotifications() {

      try {

        const today = this.gregorianToday()
        const params = new URLSearchParams({ status: "pending", annotation_date: today })
        const response = await fetch(`${API}/beauty/annotations?${params}`)

        if (!response.ok) return

        const data = await response.json()
        const annotations = Array.isArray(data.annotations) ? data.annotations : []

        annotations
          .filter(item => item.annotation_date === today && item.patient?.id)
          .forEach((item) => {
            const patient = item.patient
            const fullName = this.patientName(patient)

            this.notifications.push({

              id: `beauty-${item.patient_id}-${item.id}`,

              type: "زیبایار",

              title: "برنامه زیبایی امروز",

              patient,

              mediaId: item.patient_media_id || "",

              message: `امروز وقت برنامه زیبایی ${this.patientTitle(patient)} ${fullName} هست`,

              action: "beauty"

            })

          })

      } catch {
        // Keep the notifications page usable even if beauty reminders fail.
      }

    },

    handleNotif(notif) {

      if (notif.action === "time") {

        localStorage.setItem(
          "highlight_notif_day",
          notif.day
        )

        this.$router.push("/time")

      }

      if (notif.action === "beauty") {

        this.$emit("open-beauty-record", {
          ...(notif.patient || {}),
          mediaId: notif.mediaId || ""
        })

      }

      if (notif.action === "inventory") {

        this.$emit("open-inventory", {
          itemName: notif.itemName || ""
        })

      }

      if (notif.action === "ticket") {

        this.$emit("open-ticket", {
          ...(notif.ticket || {})
        })

      }

      if (notif.action === "appointment-material") {

        this.$emit("open-appointments", {
          ...(notif.appointment || {})
        })

      }

      if (notif.action === "birthday") {

        this.$emit("open-patient-profile", {
          ...(notif.patient || {})
        })

      }

      if (notif.action === "attendance") {

        this.$emit("open-attendance", {
          ...(notif.month || {})
        })

      }

      if (notif.action === "reports") {

        this.$emit("open-reports")

      }

      if (notif.action === "followup") {

        this.$emit("open-followups", {
          campaignId: notif.campaignId || null
        })

      }

      if (notif.action === "photos") {

        this.$emit("open-photos", {
          ...(notif.patient || {})
        })

      }

    }

  }

}
</script>

<style scoped>
.notif-page {
  direction: rtl;
  padding: 24px;
  font-family: "IranSans", sans-serif;
  background: #f5f6fa;
  min-height: 100vh;
}

.notif-page-title {
  margin-bottom: 20px;
  font-size: 22px;
  color: #222;
}

.notif-table {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(0,0,0,0.08);
}

.notif-row {
  display: grid;
  grid-template-columns: 140px 220px 1fr 160px;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid #eee;
}

.notif-row:last-child {
  border-bottom: none;
}

.notif-row-orange {
  border-right: 5px solid #f97316;
  background: #fff7ed;
}

.notif-row-orange .notif-type,
.notif-row-orange .notif-title {
  color: #c2410c;
}

.notif-row-orange .notif-btn {
  background: #f97316;
}

.notif-row-orange .notif-btn:hover {
  background: #ea580c;
}

.notif-header {
  background: #d33;
  color: #fff;
  font-weight: bold;
}

.notif-type {
  font-weight: bold;
  color: #d33;
}

.notif-title {
  font-weight: bold;
  color: #222;
}

.notif-message {
  color: #444;
  line-height: 1.8;
}

.notif-btn {
  background: #d33;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 7px 16px;
  cursor: pointer;
}

.notif-actions {
  display: flex;
  align-items: center;
  gap: 7px;
  justify-content: flex-start;
}

.notif-icon-btn {
  width: 31px;
  height: 31px;
  flex: 0 0 31px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 8px;
  color: #fff;
  font-size: 18px;
  font-weight: 900;
  line-height: 1;
  cursor: pointer;
}

.notif-done-btn {
  background: #16a34a;
}

.notif-dismiss-btn {
  background: #64748b;
}

.notif-done-btn:hover {
  background: #15803d;
}

.notif-dismiss-btn:hover {
  background: #475569;
}

.notif-btn:hover {
  background: #b22;
}

.empty-notif {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  color: #777;
}
</style>
