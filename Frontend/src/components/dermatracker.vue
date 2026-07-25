<template>
  <main class="beauty-flow" dir="rtl">
    <section v-if="!activePatient" class="beauty-flow-card">
      <div class="beauty-flow-main-toolbar">
        <button
          type="button"
          class="beauty-flow-create-btn"
          title="ایجاد برنامه زیبایی"
          aria-label="ایجاد برنامه زیبایی"
          @click="showCreatePanel = !showCreatePanel"
        >+</button>
        <div class="beauty-flow-search-grid beauty-flow-search-grid-compact">
          <input v-model.trim="search.file_number" type="text" placeholder="شماره پرونده" @keydown.enter.prevent="searchPatients">
          <input v-model.trim="search.phone" type="text" placeholder="شماره تماس" @keydown.enter.prevent="searchPatients">
          <button type="button" :disabled="searching" @click="searchPatients">
            {{ searching ? 'در حال جستجو...' : 'جستجو' }}
          </button>
        </div>
      </div>

      <div v-if="showCreatePanel || searched || searching || searchError" class="beauty-flow-create-panel">
        <div v-if="patients.length" class="beauty-flow-create-results">
          <button v-for="patient in patients" :key="patient.id" type="button" @click="openRecord(patient)">
            <span :class="['beauty-flow-avatar', `beauty-flow-avatar-${patient.customer_level || 'silver'}`]">
              <img v-if="patient.avatar_url" :src="patient.avatar_url" alt="">
              <b v-else>{{ patientInitial(patient) }}</b>
            </span>
            <span><strong>{{ patientName(patient) }}</strong><small>پرونده {{ patient.file_number || '-' }} · {{ patient.phone || '-' }}</small></span>
            <b>ساخت برنامه</b>
          </button>
        </div>
        <p v-if="searched && !patients.length" class="beauty-flow-muted">پرونده‌ای با این مشخصات پیدا نشد.</p>
        <p v-if="searching" class="beauty-flow-muted">در حال دریافت اطلاعات پرونده...</p>
        <p v-if="searchError" class="beauty-flow-error">{{ searchError }}</p>
      </div>

      <header class="beauty-flow-card-header">
        <div><h2>پرونده‌های در انتظار</h2></div>
        <button type="button" class="beauty-flow-light-btn beauty-flow-refresh-btn" :disabled="worklistLoading" @click="loadWorklist">
          {{ worklistLoading ? 'در حال دریافت...' : 'به‌روزرسانی' }}
        </button>
      </header>

      <div class="beauty-flow-worklist-filters">
        <date-picker v-model="worklistFilters.date_from" format="YYYY-MM-DD" display-format="jYYYY/jMM/jDD" placeholder="از تاریخ" auto-submit @change="loadWorklist" />
        <date-picker v-model="worklistFilters.date_to" format="YYYY-MM-DD" display-format="jYYYY/jMM/jDD" placeholder="تا تاریخ" auto-submit @change="loadWorklist" />
      </div>

      <div class="beauty-flow-table-wrap">
        <table class="beauty-flow-table beauty-flow-worklist-table">
          <thead><tr><th>نام</th><th>شماره پرونده</th><th>شماره تماس</th><th>ناحیه / مشکل</th><th>توضیح</th><th>تاریخ</th><th>وضعیت</th><th>پرونده</th></tr></thead>
          <tbody>
            <tr v-if="worklistLoading"><td colspan="8" class="beauty-flow-table-empty">در حال دریافت پرونده‌ها...</td></tr>
            <tr v-else-if="!worklist.length"><td colspan="8" class="beauty-flow-table-empty">پرونده در انتظاری برای این بازه پیدا نشد.</td></tr>
            <template v-else>
              <tr v-for="item in worklist" :key="item.patient_id">
                <td><div class="beauty-flow-patient-cell"><span :class="['beauty-flow-avatar', `beauty-flow-avatar-${item.patient?.customer_level || 'silver'}`]"><img v-if="item.patient?.avatar_url" :src="item.patient.avatar_url" alt=""><b v-else>{{ patientInitial(item.patient) }}</b></span><span>{{ patientName(item.patient) }}</span></div></td>
                <td>{{ item.patient?.file_number || '-' }}</td>
                <td>{{ item.patient?.phone || '-' }}</td>
                <td>{{ annotationCategory(item) }}</td>
                <td>{{ annotationNoteSummary(item) }}</td>
                <td>{{ formatDate(item.annotation_date || item.created_at) }}</td>
                <td><span class="beauty-flow-status beauty-flow-status-pending">در انتظار</span></td>
                <td><button type="button" class="beauty-flow-open-btn" @click="openWorklistRecord(item)">باز کردن پرونده</button></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>
    <section v-if="false" class="beauty-flow-card">
      <header class="beauty-flow-card-header">
        <h2>نتایج جستجو</h2>
        <span>{{ patients.length }} مورد</span>
      </header>

      <div class="beauty-flow-table-wrap">
        <table class="beauty-flow-table">
          <thead>
            <tr>
              <th>نام</th>
              <th>نام خانوادگی</th>
              <th>شماره پرونده</th>
              <th>شماره تماس</th>
              <th>نوع مشتری</th>
              <th>پرونده</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="patient in patients" :key="patient.id">
              <td>
                <div class="beauty-flow-patient-cell">
                  <span :class="['beauty-flow-avatar', `beauty-flow-avatar-${patient.customer_level || 'silver'}`]">
                    <img v-if="patient.avatar_url" :src="patient.avatar_url" alt="">
                    <b v-else>{{ patientInitial(patient) }}</b>
                  </span>
                  <span>{{ patient.first_name || '-' }}</span>
                </div>
              </td>
              <td>{{ patient.last_name || '-' }}</td>
              <td>{{ patient.file_number || '-' }}</td>
              <td>{{ patient.phone || '-' }}</td>
              <td>
                <span :class="['beauty-flow-level', `beauty-flow-level-${patient.customer_level || 'normal'}`]">
                  {{ customerLevelLabel(patient.customer_level) }}
                </span>
              </td>
              <td>
                <button type="button" class="beauty-flow-open-btn" @click="openRecord(patient)">باز کردن پرونده</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="false" class="beauty-flow-card beauty-flow-empty">
      پرونده‌ای با این مشخصات پیدا نشد.
    </section>

    <section v-if="activePatient" class="beauty-flow-record">
      <header class="beauty-flow-record-header">
        <div>
          <h2>زیبایار پرونده</h2>
          <p>{{ patientName(activePatient) }} - پرونده {{ activePatient.file_number || '-' }}</p>
        </div>
        <div class="beauty-flow-record-actions">
          <button type="button" class="beauty-flow-back" @click="$emit('back-to-patient-profile', activePatient)">بازگشت به پرونده</button>
          <button type="button" class="beauty-flow-back" @click="closeRecord">بازگشت</button>
        </div>
      </header>

      <div class="beauty-flow-workspace">
        <aside class="beauty-flow-sidebar">
          <section class="beauty-flow-patient-box">
            <span :class="['beauty-flow-avatar beauty-flow-avatar-large', `beauty-flow-avatar-${activePatient.customer_level || 'silver'}`]">
              <img v-if="activePatient.avatar_url" :src="activePatient.avatar_url" alt="">
              <b v-else>{{ patientInitial(activePatient) }}</b>
            </span>
            <strong>{{ patientName(activePatient) }}</strong>
            <span>{{ activePatient.phone || '-' }}</span>
            <span :class="['beauty-flow-level', `beauty-flow-level-${activePatient.customer_level || 'normal'}`]">
              {{ customerLevelLabel(activePatient.customer_level) }}
            </span>
          </section>

          <section class="beauty-flow-side-block">
            <label>عکس برنامه زیبایی</label>
            <select v-model="selectedPhotoId" @change="loadRecord">
              <option v-for="photo in frontPhotos" :key="photo.id" :value="photo.id">
                {{ photoLabel(photo) }}
              </option>
            </select>
            <small v-if="frontPhotos.length">تمام‌رخ، نیم‌رخ، سایر و شیپ بدن قابل انتخاب است.</small>
          </section>

          <section v-if="draftPoint" class="beauty-flow-editor">
            <h3>ثبت نقطه جدید</h3>

            <label>تاریخ نقطه</label>
            <date-picker
              v-model="draftPoint.annotation_date"
              format="YYYY-MM-DD"
              display-format="jYYYY/jMM/jDD"
              placeholder="انتخاب تاریخ"
              auto-submit
            />

            <label>نواحی</label>
            <select v-model="draftPoint.area">
              <option value="">انتخاب ناحیه</option>
              <option v-for="area in areas" :key="area" :value="area">{{ area }}</option>
            </select>

            <label>مشکل صورت</label>
            <select v-model="draftPoint.problem">
              <option value="">انتخاب مشکل</option>
              <option v-for="problem in problems" :key="problem" :value="problem">{{ problem }}</option>
            </select>

            <label>توضیح</label>
            <textarea v-model.trim="draftPoint.note" rows="4" placeholder="توضیح پزشک"></textarea>

            <div class="beauty-flow-editor-actions">
              <button type="button" class="beauty-flow-light-btn" @click="draftPoint = null">لغو</button>
              <button type="button" :disabled="savingPoint" @click="savePoint">
                {{ savingPoint ? 'در حال ذخیره...' : 'ثبت نقطه' }}
              </button>
            </div>
          </section>

          <section v-if="selectedAnnotation" class="beauty-flow-editor beauty-flow-editor-selected">
            <div class="beauty-flow-editor-title">
              <h3>جزئیات نقطه</h3>
              <button
                type="button"
                class="beauty-flow-delete-icon"
                title="حذف نقطه"
                :disabled="savingPoint"
                @click="deleteAnnotation(selectedAnnotation)"
              >
                ×
              </button>
            </div>
            <p><b>ناحیه:</b> {{ selectedAnnotation.area || '-' }}</p>
            <p><b>مشکل:</b> {{ selectedAnnotation.problem || '-' }}</p>
            <p><b>توضیح:</b> {{ selectedAnnotation.note || '-' }}</p>
            <p><b>تاریخ:</b> {{ formatDate(selectedAnnotation.annotation_date || selectedAnnotation.created_at) }}</p>
            <div class="beauty-flow-editor-actions">
              <button type="button" class="beauty-flow-light-btn" @click="selectedAnnotation = null">بستن</button>
              <button v-if="selectedAnnotation.status !== 'done'" type="button" :disabled="savingPoint" @click="markDone(selectedAnnotation)">انجام شد</button>
              <button
                v-else
                type="button"
                class="beauty-flow-light-btn"
                :disabled="savingPoint"
                @click="markPending(selectedAnnotation)"
              >
                انجام نشد
              </button>
            </div>
          </section>
        </aside>

        <section class="beauty-flow-photo-panel">
          <div v-if="recordLoading" class="beauty-flow-photo-empty">در حال دریافت پرونده...</div>

          <div v-else-if="!selectedPhoto" class="beauty-flow-photo-empty">
            <strong>عکسی برای برنامه زیبایی ثبت نشده است.</strong>
            <span>در پرونده بیمار یک عکس تمام‌رخ، نیم‌رخ، سایر یا شیپ بدن آپلود کنید.</span>
          </div>

          <template v-else>
            <header class="beauty-flow-photo-header">
              <div>
                <h3>{{ selectedPhoto.photo_angle_label || 'تمام‌رخ' }}</h3>
                <p>{{ selectedPhoto.comparison_stage === 'before' ? 'عکس قبل' : selectedPhoto.comparison_stage === 'after' ? 'عکس بعد' : 'عکس پرونده' }}</p>
              </div>
              <div class="beauty-flow-point-count">
                <span>{{ annotations.length }} نقطه</span>
                <span>{{ doneCount }} انجام شده</span>
              </div>
              <button
                v-if="annotations.some(item => item.status !== 'done')"
                type="button"
                class="beauty-flow-mark-all"
                :disabled="savingPoint"
                @click="markAllDone"
              >
                همه نقاط انجام شد
              </button>
            </header>

            <div class="beauty-flow-photo-stage" @click="startPoint">
              <img :src="selectedPhoto.url" alt="عکس تمام‌رخ بیمار">
              <button
                v-for="point in annotations"
                :key="point.id"
                type="button"
                class="beauty-flow-point"
                :class="{ 'beauty-flow-point-done': point.status === 'done' }"
                :style="{ left: `${point.x_percent}%`, top: `${point.y_percent}%` }"
                @click.stop="selectedAnnotation = point; draftPoint = null"
              ></button>
              <div
                v-for="point in annotations"
                :key="`label-${point.id}`"
                class="beauty-flow-point-note"
                :class="{ 'beauty-flow-point-note-done': point.status === 'done' }"
                :style="{ left: `${point.x_percent}%`, top: `${point.y_percent}%` }"
                @click.stop="selectedAnnotation = point; draftPoint = null"
              >
                <strong>{{ annotationCategory(point) }}</strong>
                <span>{{ annotationNoteSummary(point) }}</span>
                <button
                  v-if="hasMoreAnnotationText(point)"
                  type="button"
                  @click.stop="selectedAnnotation = point; draftPoint = null"
                >
                  ادامه
                </button>
              </div>
              <span
                v-if="draftPoint"
                class="beauty-flow-point beauty-flow-point-draft"
                :style="{ left: `${draftPoint.x_percent}%`, top: `${draftPoint.y_percent}%` }"
              ></span>
            </div>
          </template>
        </section>
      </div>
    </section>
  </main>
</template>

<script>
import DatePicker from 'vue3-persian-datetime-picker'

const API = '/api'

export default {
  name: 'Dermatracker',
  components: { DatePicker },
  props: {
    openPatientRequest: {
      type: Object,
      default: null
    }
  },
  data: () => ({
    search: { file_number: '', phone: '', q: '' },
    searching: false,
    searched: false,
    searchError: '',
    showCreatePanel: false,
    patients: [],
    activePatient: null,
    recordLoading: false,
    frontPhotos: [],
    selectedPhotoId: '',
    selectedPhoto: null,
    annotations: [],
    serviceHistory: [],
    servicesLoading: false,
    servicesExpanded: false,
    worklist: [],
    worklistLoading: false,
    worklistFilters: { date_from: '', date_to: '' },
    areas: [],
    problems: [],
    draftPoint: null,
    selectedAnnotation: null,
    savingPoint: false,
    annotationFilters: { date_from: '', date_to: '' }
  }),
  computed: {
    doneCount() {
      return this.annotations.filter(item => item.status === 'done').length
    },
    visibleServiceHistory() {
      return this.servicesExpanded ? this.serviceHistory : this.serviceHistory.slice(0, 3)
    }
  },
  watch: {
    openPatientRequest: {
      deep: true,
      handler(patient) {
        if (patient?.id) this.openRecord(patient, patient.mediaId || '')
      }
    }
  },
  mounted() {
    this.loadContext()
    this.loadWorklist()
    if (this.openPatientRequest?.id) this.openRecord(this.openPatientRequest, this.openPatientRequest.mediaId || '')
  },
  methods: {
    patientName(patient) {
      return [patient?.first_name, patient?.last_name].filter(Boolean).join(' ') || 'بیمار بدون نام'
    },
    patientInitial(patient) {
      return this.patientName(patient).trim().slice(0, 1) || 'ب'
    },
    customerLevelLabel(level) {
      return { problematic: 'مشتری دردسرساز', blue: 'آبی', silver: 'نقره‌ای', gold: 'طلایی' }[level] || 'عادی'
    },
    statusLabel(status) {
      return status === 'done' ? 'انجام شده' : 'در انتظار'
    },
    statusClass(status) {
      return status === 'done' ? 'beauty-flow-status-done' : 'beauty-flow-status-pending'
    },
    formatDate(raw) {
      if (!raw) return '-'
      try {
        return new Date(raw).toLocaleDateString('fa-IR')
      } catch {
        return String(raw)
      }
    },
    photoLabel(photo) {
      const stage = photo.comparison_stage === 'before' ? 'قبل' : photo.comparison_stage === 'after' ? 'بعد' : 'پرونده'
      const date = photo.created_at ? new Date(photo.created_at).toLocaleDateString('fa-IR') : 'بدون تاریخ'
      return `${photo.photo_angle_label || 'سایر'} · ${stage} · ${date}`
    },
    annotationFullText(point) {
      return [point?.area, point?.problem, point?.note].filter(Boolean).join(' - ') || 'بدون توضیح'
    },
    annotationCategory(point) {
      return [point?.area, point?.problem].filter(Boolean).join(' / ') || 'بدون دسته‌بندی'
    },
    annotationNoteSummary(point) {
      const text = point?.note || 'بدون توضیح'
      return text.length > 46 ? `${text.slice(0, 46)}...` : text
    },
    hasMoreAnnotationText(point) {
      return String(point?.note || '').length > 46
    },
    async loadContext() {
      try {
        const res = await fetch(`${API}/beauty/context`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت اطلاعات خدمتیار انجام نشد.')
        this.areas = data.areas || []
        this.problems = data.problems || []
      } catch (error) {
        this.searchError = error.message || 'دریافت اطلاعات خدمتیار انجام نشد.'
      }
    },
    async loadWorklist() {
      this.worklistLoading = true
      try {
        const params = new URLSearchParams()
        Object.entries(this.worklistFilters).forEach(([key, value]) => {
          if (value) params.set(key, value)
        })
        const res = await fetch(`${API}/beauty/annotations?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت لیست نقاط انجام نشد.')
        this.worklist = data.annotations || []
      } catch (error) {
        this.searchError = error.message || 'دریافت لیست نقاط انجام نشد.'
      } finally {
        this.worklistLoading = false
      }
    },
    async searchPatients() {
      if (this.searching) return
      if (!this.search.file_number && !this.search.phone && !this.search.q) {
        this.searchError = 'شماره پرونده، شماره تماس یا نام بیمار را وارد کنید.'
        return
      }

      this.searching = true
      this.searched = false
      this.searchError = ''
      this.patients = []

      try {
        const params = new URLSearchParams()
        if (this.search.file_number) params.append('file_number', this.search.file_number)
        if (this.search.phone) params.append('phone', this.search.phone)
        if (this.search.q) params.append('q', this.search.q)
        const res = await fetch(`${API}/patients/search?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'جستجو انجام نشد.')
        this.patients = Array.isArray(data) ? data : []
        this.searched = true
      } catch (error) {
        this.searchError = error.message || 'جستجو انجام نشد.'
      } finally {
        this.searching = false
      }
    },
    async openRecord(patient, mediaId = '') {
      this.activePatient = patient
      this.showCreatePanel = false
      this.selectedPhotoId = mediaId || ''
      this.draftPoint = null
      this.selectedAnnotation = null
      this.serviceHistory = []
      this.servicesExpanded = false
      await this.loadRecord()
    },
    async openWorklistRecord(item) {
      if (!item?.patient) return
      await this.openRecord(item.patient, item.patient_media_id || '')
    },
    async loadServiceHistory() {
      if (!this.activePatient?.id) return
      this.servicesLoading = true
      try {
        const params = new URLSearchParams()
        if (this.activePatient.file_number) params.append('file_number', this.activePatient.file_number)
        if (this.activePatient.phone) params.append('phone', this.activePatient.phone)
        const res = await fetch(`${API}/appointments/patient-history?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت خدمات انجام نشد.')
        this.serviceHistory = (Array.isArray(data) ? data : [])
          .filter(item => this.appointmentServicesText(item) !== '-')
          .slice(0, 12)
      } catch (error) {
        this.searchError = error.message || 'دریافت خدمات انجام نشد.'
      } finally {
        this.servicesLoading = false
      }
    },
    appointmentServicesText(item) {
      const services = Array.isArray(item?.services) ? item.services : []
      const names = services
        .flatMap(service => [
          service?.name,
          ...(Array.isArray(service?.addons) ? service.addons.map(addon => addon?.name) : [])
        ])
        .filter(Boolean)
      return names.length ? names.join('، ') : (item?.done || '-')
    },
    appointmentDateText(item) {
      const raw = item?.created_at || item?.month || ''
      if (!raw) return 'بدون تاریخ'
      try {
        return new Date(raw).toLocaleDateString('fa-IR')
      } catch {
        return String(raw)
      }
    },
    async loadRecord() {
      if (!this.activePatient?.id) return
      this.recordLoading = true
      this.searchError = ''
      try {
        const params = new URLSearchParams()
        if (this.selectedPhotoId) params.set('media_id', this.selectedPhotoId)
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت پرونده زیبایار انجام نشد.')
        this.activePatient = data.patient || this.activePatient
        this.frontPhotos = data.front_photos || []
        this.selectedPhoto = data.selected_photo || null
        this.selectedPhotoId = this.selectedPhoto?.id || ''
        this.annotations = data.annotations || []
        this.draftPoint = null
        this.selectedAnnotation = null
      } catch (error) {
        this.searchError = error.message || 'دریافت پرونده زیبایار انجام نشد.'
      } finally {
        this.recordLoading = false
      }
    },
    startPoint(event) {
      if (!this.selectedPhoto) return
      const rect = event.currentTarget.getBoundingClientRect()
      this.selectedAnnotation = null
      this.draftPoint = {
        x_percent: Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100)),
        y_percent: Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100)),
        area: '',
        problem: '',
        note: '',
        annotation_date: new Date().toISOString().slice(0, 10)
      }
    },
    async savePoint() {
      if (!this.activePatient?.id || !this.selectedPhoto?.id || !this.draftPoint) return
      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ patient_media_id: this.selectedPhoto.id, ...this.draftPoint })
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'ثبت نقطه انجام نشد.')
        this.annotations.unshift(data)
        this.selectedAnnotation = data
        this.draftPoint = null
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'ثبت نقطه انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async markDone(point) {
      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ status: 'done' })
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'ثبت وضعیت انجام نشد.')
        this.annotations = this.annotations.map(item => item.id === point.id ? data : item)
        this.selectedAnnotation = data
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'ثبت وضعیت انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async markPending(point) {
      if (!point?.id || !this.activePatient?.id) return
      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ status: 'pending' })
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'برگرداندن وضعیت نقطه انجام نشد.')
        this.annotations = this.annotations.map(item => item.id === point.id ? data : item)
        this.selectedAnnotation = data
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'برگرداندن وضعیت نقطه انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async markAllDone() {
      const pending = this.annotations.filter(item => item.status !== 'done')
      if (!pending.length || !this.activePatient?.id) return

      const confirmed = window.confirm('با زدن این کلید همه نقاط به حالت انجام شده تغییر پیدا می‌کنند. اطمینان دارید؟')
      if (!confirmed) return

      this.savingPoint = true
      try {
        const updated = await Promise.all(pending.map(async (point) => {
          const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ status: 'done' })
          })
          const data = await res.json()
          if (!res.ok) throw new Error(data.message || 'ثبت وضعیت نقاط انجام نشد.')
          return data
        }))

        const updatedById = new Map(updated.map(item => [item.id, item]))
        this.annotations = this.annotations.map(item => updatedById.get(item.id) || item)
        if (this.selectedAnnotation && updatedById.has(this.selectedAnnotation.id)) {
          this.selectedAnnotation = updatedById.get(this.selectedAnnotation.id)
        }
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'ثبت وضعیت نقاط انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async deleteAnnotation(point) {
      if (!point?.id || !this.activePatient?.id) return
      const confirmed = window.confirm('این نقطه از روی عکس حذف شود؟')
      if (!confirmed) return

      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
          method: 'DELETE',
          headers: { Accept: 'application/json' }
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'حذف نقطه انجام نشد.')
        this.annotations = this.annotations.filter(item => item.id !== point.id)
        this.selectedAnnotation = null
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'حذف نقطه انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    clearAnnotationFilters() {
      this.annotationFilters = { date_from: '', date_to: '' }
      this.loadRecord()
    },
    closeRecord() {
      this.activePatient = null
      this.frontPhotos = []
      this.selectedPhoto = null
      this.annotations = []
      this.serviceHistory = []
      this.servicesExpanded = false
      this.draftPoint = null
      this.selectedAnnotation = null
    }
  }
}
</script>

<style scoped>
.beauty-flow {
  min-height: calc(100vh - 120px);
  padding: 18px;
  background: #f6f8fa;
  color: #0f172a;
  font-family: "Vazir", Tahoma, sans-serif;
}
.beauty-flow-card {
  margin-bottom: 18px;
  padding: 18px;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
}

.beauty-flow-create-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 40px;
  width: 40px;
  min-width: 40px;
  padding: 0;
  font-size: 22px !important;
  line-height: 1;
}
.beauty-flow-main-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.beauty-flow-create-panel {
  margin-top: 18px;
  padding: 20px;
  border: 1px solid #dbeafe;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(30, 64, 175, .08);
}

.beauty-flow-search-grid-compact {
  flex: 1;
  grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) 90px;
}
.beauty-flow-create-results { display: grid; gap: 8px; margin-top: 14px; }
.beauty-flow-create-results > button {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 12px;
  color: #172554;
  text-align: right;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}
.beauty-flow-create-results > button:hover { background: #eff6ff; border-color: #93c5fd; }
.beauty-flow-create-results > button > span:nth-child(2) { display: flex; flex-direction: column; gap: 3px; }
.beauty-flow-create-results small { color: #64748b; font-weight: 500; }
.beauty-flow-card-header,
.beauty-flow-record-header,
.beauty-flow-photo-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.beauty-flow-card-header {
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #edf2f7;
}
.beauty-flow-card-header h2,
.beauty-flow-record-header h2,
.beauty-flow-photo-header h3 {
  margin: 0;
  color: #0f172a;
  font-size: 16px;
  font-weight: 900;
}
.beauty-flow-card-header span,
.beauty-flow-record-header p,
.beauty-flow-photo-header p,
.beauty-flow-muted {
  margin: 0;
  color: #64748b;
  font-size: 12px;
}
.beauty-flow-search-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr)) 120px;
  gap: 12px;
  align-items: center;
}
.beauty-flow input,
.beauty-flow select,
.beauty-flow textarea {
  width: 100%;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  background: #fff;
  color: #111827;
  font-family: inherit;
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
}
.beauty-flow input,
.beauty-flow select {
  height: 42px;
  padding: 0 12px;
}
.beauty-flow textarea {
  min-height: 92px;
  padding: 10px 12px;
  resize: vertical;
}
.beauty-flow button {
  height: 40px;
  border: 0;
  border-radius: 12px;
  background: #0f766e;
  color: #fff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}
.beauty-flow button:disabled {
  opacity: .65;
  cursor: wait;
}
.beauty-flow-error {
  margin: 12px 0 0;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fff1f2;
  color: #be123c;
  font-size: 12px;
  font-weight: 800;
}
.beauty-flow-table-wrap {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
}
.beauty-flow-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  table-layout: fixed;
}
.beauty-flow-table th {
  padding: 13px 14px;
  background: #f8fafc;
  color: #475569;
  text-align: right;
  font-size: 12px;
  font-weight: 900;
}
.beauty-flow-table td {
  padding: 12px 14px;
  border-top: 1px solid #eef2f7;
  color: #334155;
  font-size: 13px;
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.beauty-flow-worklist-filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(150px, 190px));
  gap: 10px;
  margin-bottom: 12px;
}
.beauty-flow-worklist-table {
  min-width: 1040px;
}
.beauty-flow-table-empty {
  height: 72px;
  color: #64748b !important;
  text-align: center;
  font-weight: 900;
}
.beauty-flow-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 78px;
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
}
.beauty-flow-status-pending {
  border: 1px solid #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}
.beauty-flow-status-done {
  border: 1px solid #bbf7d0;
  background: #ecfdf5;
  color: #047857;
}
.beauty-flow-refresh-btn {
  width: auto;
  min-width: 98px;
  padding: 0 12px;
}
.beauty-flow-patient-cell {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}
.beauty-flow-patient-cell > span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
}
.beauty-flow-avatar {
  position: relative;
  width: 38px;
  height: 38px;
  flex: none;
  display: grid;
  place-items: center;
  overflow: visible;
  border: 2px solid #dbe3ea;
  border-radius: 50%;
  background: #f8fafc;
  color: #64748b;
  font-weight: 900;
  box-shadow: 0 0 0 3px rgba(148, 163, 184, .12);
}
.beauty-flow-avatar-large {
  width: 86px;
  height: 86px;
  justify-self: center;
  margin-bottom: 4px;
  font-size: 28px;
}
.beauty-flow-avatar img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: inherit;
}
.beauty-flow-avatar-blue {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, .16);
}
.beauty-flow-avatar-problematic {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, .16);
}
.beauty-flow-avatar-silver {
  border-color: #94a3b8;
  box-shadow: 0 0 0 3px rgba(148, 163, 184, .2);
}
.beauty-flow-avatar-gold {
  border-color: #d4a72c;
  box-shadow: 0 0 0 3px rgba(212, 167, 44, .2);
}
.beauty-flow-avatar-gold::before {
  content: "♛";
  position: absolute;
  top: -18px;
  left: 50%;
  transform: translateX(-50%);
  color: #d4a72c;
  font-size: 24px;
  line-height: 1;
  text-shadow: 0 1px 0 #fff, 0 2px 6px rgba(146, 64, 14, .3);
}
.beauty-flow-open-btn {
  width: 118px;
}
.beauty-flow-level {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 58px;
  padding: 5px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
}
.beauty-flow-level-blue { background: #dbeafe; color: #1d4ed8; }
.beauty-flow-level-problematic { background: #fee2e2; color: #b91c1c; }
.beauty-flow-level-silver { background: #e2e8f0; color: #475569; }
.beauty-flow-level-gold { background: #fef3c7; color: #92400e; }
.beauty-flow-patient-cell:has(.beauty-flow-avatar-problematic) span,
.beauty-flow-patient-box:has(.beauty-flow-avatar-problematic) strong {
  color: #dc2626;
  font-weight: 1000;
}
.beauty-flow-empty {
  display: grid;
  min-height: 120px;
  place-items: center;
  color: #64748b;
  font-weight: 900;
}
.beauty-flow-record {
  display: grid;
  gap: 14px;
}
.beauty-flow-record-header {
  justify-content: space-between;
  padding: 14px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
}
.beauty-flow-back,
.beauty-flow-light-btn {
  border: 1px solid #dbe3ea !important;
  background: #fff !important;
  color: #475569 !important;
}
.beauty-flow-record-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.beauty-flow-back {
  width: auto;
  min-width: 86px;
  padding: 0 14px;
}
.beauty-flow-workspace {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  gap: 14px;
}
.beauty-flow-sidebar,
.beauty-flow-photo-panel {
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
}
.beauty-flow-sidebar {
  align-self: start;
  padding: 16px;
}
.beauty-flow-patient-box {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding-bottom: 14px;
  border-bottom: 1px solid #edf2f7;
  text-align: center;
}
.beauty-flow-patient-box strong {
  color: #0f172a;
  font-size: 16px;
}
.beauty-flow-patient-box span:not(.beauty-flow-level),
.beauty-flow-side-block small {
  color: #64748b;
  font-size: 12px;
}
.beauty-flow-side-block,
.beauty-flow-editor {
  display: grid;
  gap: 9px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #edf2f7;
}
.beauty-flow-side-block label,
.beauty-flow-editor label {
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}
.beauty-flow-services-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.beauty-flow-mini-btn {
  width: auto;
  height: 28px !important;
  padding: 0 9px;
  border: 1px solid #dbe3ea !important;
  background: #fff !important;
  color: #475569 !important;
  border-radius: 9px !important;
  font-size: 10px !important;
}
.beauty-flow-services-list {
  display: grid;
  gap: 7px;
}
.beauty-flow-services-list article {
  display: grid;
  gap: 3px;
  padding: 8px 9px;
  border: 1px solid #edf2f7;
  border-radius: 10px;
  background: #f8fafc;
}
.beauty-flow-services-list article strong {
  overflow: hidden;
  color: #0f172a;
  font-size: 11px;
  font-weight: 900;
  line-height: 1.7;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.beauty-flow-services-list article span {
  color: #64748b;
  font-size: 10px;
  line-height: 1.6;
}
.beauty-flow-editor {
  padding: 12px;
  border: 1px solid #ccfbf1;
  border-radius: 14px;
  background: #f0fdfa;
}
.beauty-flow-editor-selected {
  border-color: #bbf7d0;
  background: #f0fdf4;
}
.beauty-flow-editor h3,
.beauty-flow-editor p {
  margin: 0;
}
.beauty-flow-editor-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.beauty-flow-editor h3 {
  color: #0f766e;
  font-size: 14px;
}
.beauty-flow-delete-icon {
  width: 26px !important;
  height: 26px !important;
  min-width: 26px;
  padding: 0 !important;
  border: 1px solid #fecaca !important;
  border-radius: 8px !important;
  background: #fff1f2 !important;
  color: #be123c !important;
  font-size: 18px !important;
  line-height: 1 !important;
}
.beauty-flow-editor p {
  color: #334155;
  font-size: 12px;
  line-height: 1.9;
}
.beauty-flow-editor-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.beauty-flow-photo-panel {
  min-height: 640px;
  padding: 16px;
}
.beauty-flow-photo-empty {
  min-height: 560px;
  display: grid;
  place-items: center;
  gap: 8px;
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  background: #f8fafc;
  color: #64748b;
  text-align: center;
  font-weight: 900;
}
.beauty-flow-photo-empty span {
  color: #94a3b8;
  font-size: 12px;
}
.beauty-flow-photo-header {
  margin-bottom: 12px;
}
.beauty-flow-point-count {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.beauty-flow-point-count span {
  padding: 6px 10px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
  font-size: 11px;
  font-weight: 900;
}
.beauty-flow-mark-all {
  width: auto;
  height: 32px !important;
  padding: 0 10px;
  border-radius: 999px !important;
  background: #0f766e !important;
  color: #fff !important;
  font-size: 11px !important;
}
.beauty-flow-photo-stage {
  position: relative;
  min-height: 570px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border-radius: 14px;
  background: #111827;
  cursor: crosshair;
}
.beauty-flow-photo-stage img {
  display: block;
  width: 100%;
  max-width: 100%;
  max-height: 72vh;
  object-fit: contain;
  user-select: none;
}
.beauty-flow-point {
  position: absolute;
  width: 18px !important;
  height: 18px !important;
  min-width: 18px;
  padding: 0 !important;
  border: 3px solid #111827 !important;
  border-radius: 50% !important;
  background: #fff !important;
  box-shadow: 0 0 0 2px rgba(255,255,255,.9);
  transform: translate(-50%, -50%);
}
.beauty-flow-point-done {
  background: #22c55e !important;
  box-shadow: 0 0 0 2px rgba(34,197,94,.4);
}
.beauty-flow-point-draft {
  border-color: #0f766e !important;
  background: #99f6e4 !important;
}
.beauty-flow-point-note {
  position: absolute;
  z-index: 3;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  column-gap: 6px;
  row-gap: 3px;
  width: max-content;
  min-width: 90px;
  max-width: 220px;
  min-height: 36px;
  padding: 5px 8px;
  border: 1px solid rgba(15, 23, 42, .08);
  border-radius: 10px;
  background: rgba(255, 255, 255, .96);
  color: #1f2937;
  box-shadow: 0 10px 24px rgba(15, 23, 42, .16);
  transform: translate(calc(-100% - 18px), -50%);
  cursor: pointer;
}
.beauty-flow-point-note strong,
.beauty-flow-point-note span {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.beauty-flow-point-note strong {
  grid-column: 1 / -1;
  color: #0f172a;
  font-size: 11px;
  font-weight: 900;
  line-height: 1.4;
}
.beauty-flow-point-note span {
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  line-height: 1.4;
}
.beauty-flow-point-note button {
  width: auto;
  height: 22px;
  flex: none;
  padding: 0 7px;
  border-radius: 8px;
  background: #e0f2fe;
  color: #0369a1;
  font-size: 10px;
}
.beauty-flow-point-note-done {
  border-color: rgba(34, 197, 94, .25);
  background: rgba(240, 253, 244, .98);
}
.beauty-flow-point-note-done button {
  background: #dcfce7;
  color: #047857;
}
@media (max-width: 1100px) {
  .beauty-flow-search-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .beauty-flow-worklist-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .beauty-flow-workspace {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 700px) {
  .beauty-flow {
    padding: 12px;
  }
  .beauty-flow-search-grid {
    grid-template-columns: 1fr;
  }
  .beauty-flow-worklist-filters {
    grid-template-columns: 1fr;
  }
  .beauty-flow-card-header,
  .beauty-flow-record-header,
  .beauty-flow-photo-header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
