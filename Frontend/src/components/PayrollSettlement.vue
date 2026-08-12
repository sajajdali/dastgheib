<template>
  <section class="payroll-page" dir="rtl">
    <header class="payroll-header">
      <div>
        <small>حقوق و تسویه</small>
        <h1>تسویه حقوق پزشکان و پرسنل</h1>
      </div>
      <div class="payroll-month">
        <button type="button" title="ماه قبل" @click="shiftMonth(-1)">‹</button>
        <input v-model.trim="selectedMonth" type="text" inputmode="numeric" placeholder="1405-05" @change="loadReport">
        <button type="button" title="ماه بعد" :disabled="selectedMonth >= currentJalaliMonth" @click="shiftMonth(1)">›</button>
      </div>
    </header>

    <div class="payroll-layout">
      <aside class="payroll-sidebar">
        <label class="payroll-search">
          <span>جستجوی کاربر</span>
          <input v-model.trim="search" type="search" placeholder="نام پزشک یا پرسنل">
        </label>
        <div class="payroll-segments">
          <button type="button" :class="{ active: filterType === 'all' }" @click="filterType = 'all'">همه</button>
          <button type="button" :class="{ active: filterType === 'doctor' }" @click="filterType = 'doctor'">پزشک</button>
          <button type="button" :class="{ active: filterType === 'staff' }" @click="filterType = 'staff'">پرسنل</button>
        </div>
        <div class="payroll-user-list">
          <button
            v-for="resource in filteredResources"
            :key="`${resource.type}-${resource.id}`"
            type="button"
            :class="{ active: selectedKey === `${resource.type}-${resource.id}` }"
            @click="selectResource(resource)"
          >
            <img v-if="resource.avatar_url" :src="resource.avatar_url" :alt="resource.name">
            <b v-else>{{ initial(resource.name) }}</b>
            <span>
              <strong>{{ resource.name }}</strong>
              <small>{{ resource.type === 'doctor' ? 'پزشک' : 'پرسنل' }}</small>
            </span>
          </button>
          <p v-if="!filteredResources.length" class="payroll-empty">کاربری پیدا نشد.</p>
        </div>
      </aside>

      <main class="payroll-content">
        <div v-if="loading" class="payroll-state"><i></i><span>در حال محاسبه گزارش...</span></div>
        <div v-else-if="error" class="payroll-state error">{{ error }}</div>
        <div v-else-if="!report" class="payroll-state">یک پزشک یا پرسنل را انتخاب کنید.</div>
        <template v-else>
          <section class="payroll-person">
            <div>
              <small>{{ report.resource.type === 'doctor' ? 'پزشک' : 'پرسنل' }}</small>
              <h2>{{ report.resource.name }}</h2>
            </div>
            <strong>{{ monthLabel(selectedMonth) }}</strong>
          </section>

          <section class="payroll-summary">
            <article class="total"><span>قابل پرداخت</span><strong>{{ money(report.summary.total_earned) }}</strong></article>
            <article><span>حقوق ثابت</span><strong>{{ money(report.summary.salary) }}</strong></article>
            <article><span>پورسانت خدمات</span><strong>{{ money(report.summary.service_commission) }}</strong></article>
            <article><span>پورسانت انبار</span><strong>{{ money(report.summary.inventory_commission) }}</strong></article>
            <article><span>پاداش پلکانی</span><strong>{{ money(report.summary.sales_bonus) }}</strong></article>
            <article><span>اضافه‌کاری</span><strong>{{ money(report.summary.overtime) }}</strong></article>
            <article><span>کسری ساعت</span><strong>{{ money(report.summary.shortage) }}</strong></article>
            <article><span>غیبت</span><strong>{{ money(report.summary.absence) }}</strong></article>
          </section>

          <section class="payroll-tabs">
            <button type="button" :class="{ active: activeTab === 'lines' }" @click="activeTab = 'lines'">ریز محاسبات</button>
            <button type="button" :class="{ active: activeTab === 'attendance' }" @click="activeTab = 'attendance'">حضور و غیاب</button>
            <button type="button" :class="{ active: activeTab === 'settings' }" @click="activeTab = 'settings'">تنظیمات ذخیره‌شده</button>
          </section>

          <section v-if="activeTab === 'lines'" class="payroll-table-wrap">
            <table class="payroll-table">
              <thead>
                <tr>
                  <th>روز</th>
                  <th>نوع</th>
                  <th>شرح</th>
                  <th>خدمت/آیتم</th>
                  <th>مبنا</th>
                  <th>نرخ</th>
                  <th>مبلغ</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in report.lines" :key="line.id" :class="{ edited: line.manually_edited && line.status !== 'deleted', deleted: line.status === 'deleted' }">
                  <td>{{ line.day_num ? Number(line.day_num).toLocaleString('fa-IR') : '-' }}</td>
                  <td>{{ lineType(line.earning_type) }}</td>
                  <td>
                    <span>{{ line.description || '-' }}</span>
                    <small v-if="line.status === 'deleted'" class="line-status">حذف‌شده</small>
                    <small v-else-if="line.manually_edited" class="line-status edited">ویرایش‌شده</small>
                  </td>
                  <td>{{ line.service_name || line.inventory_name || '-' }}</td>
                  <td>{{ money(line.commission_base) }}</td>
                  <td>{{ rateText(line) }}</td>
                  <td :class="{ positive: Number(line.amount) > 0, negative: Number(line.amount) < 0 }">{{ money(line.amount) }}</td>
                  <td>
                    <div class="line-actions">
                      <button type="button" class="info-btn" title="جزئیات محاسبه" @click="openLineDetail(line)">i</button>
                      <button type="button" class="delete-line-btn" title="حذف از تسویه" :disabled="deletingLineId === line.id || line.status === 'deleted'" @click="deleteLine(line)">×</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!report.lines.length">
                  <td colspan="8">برای این ماه ریزمحاسبه‌ای ثبت نشده است.</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-else-if="activeTab === 'attendance'" class="payroll-table-wrap">
            <table class="payroll-table attendance-ledger-table">
              <thead>
                <tr>
                  <th>روز</th>
                  <th>نوع</th>
                  <th>شرح</th>
                  <th>ساعت/تعداد</th>
                  <th>نرخ</th>
                  <th>مبلغ</th>
                  <th>وضعیت</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in attendanceLines" :key="line.id" :class="{ edited: line.manually_edited && line.status !== 'deleted', deleted: line.status === 'deleted' }">
                  <td>{{ line.day_num ? Number(line.day_num).toLocaleString('fa-IR') : '-' }}</td>
                  <td>{{ lineType(line.earning_type) }}</td>
                  <td>{{ line.description || '-' }}</td>
                  <td>{{ Number(line.quantity || 0).toLocaleString('fa-IR') }}</td>
                  <td>{{ rateText(line) }}</td>
                  <td :class="{ positive: Number(line.amount) > 0, negative: Number(line.amount) < 0 }">{{ money(line.amount) }}</td>
                  <td>
                    <small v-if="line.status === 'deleted'" class="line-status">حذف‌شده</small>
                    <small v-else-if="line.manually_edited" class="line-status edited">ویرایش‌شده</small>
                    <small v-else>-</small>
                  </td>
                  <td>
                    <div class="line-actions">
                      <button type="button" class="info-btn" title="جزئیات محاسبه" @click="openLineDetail(line)">i</button>
                      <button type="button" class="delete-line-btn" title="حذف از تسویه" :disabled="deletingLineId === line.id || line.status === 'deleted'" @click="deleteLine(line)">×</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!attendanceLines.length">
                  <td colspan="8">برای حضور و غیاب این ماه ردیف مالی ثبت نشده است.</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-else class="payroll-settings">
            <article>
              <span>درصد پورسانت پایه</span>
              <strong>{{ Number(report.resource.bonus || 0).toLocaleString('fa-IR') }}٪</strong>
            </article>
            <article>
              <span>مبنای پورسانت</span>
              <strong>{{ report.resource.commission_after_materials ? 'پس از کسر مواد' : 'مبلغ خالص خدمت' }}</strong>
            </article>
            <article>
              <span>دامنه مشتری</span>
              <strong>{{ scopeLabel(report.resource.commission_customer_scope) }}</strong>
            </article>
            <article>
              <span>نرخ اضافه‌کاری</span>
              <strong>{{ money(report.resource.overtime_hourly_rate) }}</strong>
            </article>
            <article>
              <span>کسر کسری ساعت</span>
              <strong>{{ money(report.resource.shortage_hourly_deduction) }}</strong>
            </article>
            <article>
              <span>کسر غیبت</span>
              <strong>{{ money(report.resource.absence_deduction) }}</strong>
            </article>
          </section>
        </template>
      </main>
    </div>

    <div v-if="detailLine" class="line-detail-overlay" @click.self="closeLineDetail">
      <section class="line-detail-modal">
        <header>
          <div>
            <small>{{ lineType(detailLine.earning_type) }}</small>
            <h2>{{ editingLine ? 'ویرایش محاسبه' : (detailLine.description || 'جزئیات محاسبه') }}</h2>
          </div>
          <button type="button" @click="closeLineDetail">×</button>
        </header>
        <div class="line-detail-grid">
          <article><span>روز نوبت/کارکرد</span><strong>{{ detailLine.day_num ? Number(detailLine.day_num).toLocaleString('fa-IR') : '-' }}</strong></article>
          <article><span>نوبت</span><strong>{{ appointmentTitle(detailLine) }}</strong></article>
          <article><span>خدمات نوبت</span><strong>{{ appointmentServices(detailLine) }}</strong></article>
          <article><span>کل پول خدمات نوبت</span><strong>{{ appointmentTotal(detailLine) }}</strong></article>
          <article v-if="isAttendanceLine(detailLine)"><span>ورود / خروج</span><strong>{{ attendanceClockText(detailLine) }}</strong></article>
          <article v-if="isAttendanceLine(detailLine)"><span>ساعت کارکرد</span><strong>{{ Number(detailLine.calculation_snapshot?.worked_hours || 0).toLocaleString('fa-IR') }}</strong></article>
          <article v-if="isAttendanceLine(detailLine)"><span>اختلاف ساعت</span><strong>{{ Number(detailLine.calculation_snapshot?.diff_hours || 0).toLocaleString('fa-IR') }}</strong></article>
          <article v-if="!editingLine"><span>خدمت/آیتم موثر</span><strong>{{ detailLine.service_name || detailLine.inventory_name || '-' }}</strong></article>
          <article v-if="!editingLine"><span>مبلغ خام همین خط</span><strong>{{ money(detailLine.gross_amount) }}</strong></article>
          <article v-if="!editingLine"><span>تخفیف همین خط</span><strong>{{ money(detailLine.discount_amount) }}</strong></article>
          <article v-if="!editingLine"><span>خالص همین خط</span><strong>{{ money(detailLine.net_amount) }}</strong></article>
          <article v-if="!editingLine"><span>هزینه مواد</span><strong>{{ money(detailLine.material_cost) }}</strong></article>
          <article v-if="!editingLine"><span>مبنای محاسبه</span><strong>{{ money(detailLine.commission_base) }}</strong></article>
          <article v-if="!editingLine"><span>نرخ/نوع</span><strong>{{ rateText(detailLine) }}</strong></article>
          <article v-if="!editingLine" class="earned"><span>مبلغ بابت این مورد</span><strong>{{ money(detailLine.amount) }}</strong></article>
          <article v-if="!editingLine && detailLine.manually_edited" class="audit"><span>آخرین ویرایش</span><strong>{{ auditText(detailLine, 'edited') }}</strong></article>
          <article v-if="!editingLine && detailLine.status === 'deleted'" class="audit deleted"><span>حذف شده توسط</span><strong>{{ auditText(detailLine, 'deleted') }}</strong></article>
        </div>
        <div v-if="editingLine" class="line-edit-form">
          <label><span>شرح</span><input v-model.trim="lineForm.description" type="text"></label>
          <label><span>خدمت/آیتم موثر</span><input v-model.trim="lineForm.service_name" type="text"></label>
          <label><span>تعداد / ساعت</span><input v-model.number="lineForm.quantity" type="number" step="0.001"></label>
          <label><span>مبلغ خام همین خط</span><input v-model.number="lineForm.gross_amount" type="number"></label>
          <label><span>تخفیف همین خط</span><input v-model.number="lineForm.discount_amount" type="number"></label>
          <label><span>خالص همین خط</span><input v-model.number="lineForm.net_amount" type="number"></label>
          <label><span>هزینه مواد</span><input v-model.number="lineForm.material_cost" type="number"></label>
          <label><span>مبنای محاسبه</span><input v-model.number="lineForm.commission_base" type="number"></label>
          <label>
            <span>نوع نرخ</span>
            <select v-model="lineForm.commission_type">
              <option value="percent">درصدی</option>
              <option value="fixed">ثابت</option>
              <option value="hourly">ساعتی</option>
              <option value="tier">پلکانی</option>
              <option value="absence">غیبت</option>
            </select>
          </label>
          <label><span>مقدار نرخ</span><input v-model.number="lineForm.commission_value" type="number"></label>
          <label class="line-final-amount"><span>مبلغ نهایی این ردیف</span><input v-model.number="lineForm.amount" type="number"></label>
        </div>
        <footer class="line-detail-actions">
          <button type="button" class="delete-line-btn wide" :disabled="savingLine || detailLine.status === 'deleted'" @click="deleteLine(detailLine)">حذف ردیف</button>
          <button v-if="detailLine.status === 'deleted'" type="button" class="restore-line-btn wide" :disabled="savingLine || restoringLineId === detailLine.id" @click="restoreLine(detailLine)">بازگردانی ردیف</button>
          <button v-if="editingLine" type="button" class="cancel-line-btn" :disabled="savingLine" @click="editingLine = false">انصراف</button>
          <button v-if="!editingLine && detailLine.status !== 'deleted'" type="button" class="edit-line-btn" @click="editingLine = true">ویرایش</button>
          <button v-if="editingLine" type="button" class="save-line-btn" :disabled="savingLine" @click="saveLine">{{ savingLine ? 'در حال ذخیره...' : 'ذخیره و محاسبه مجدد' }}</button>
        </footer>
        <section v-if="auditEvents(detailLine).length" class="line-audit-list">
          <h3>سوابق تغییرات</h3>
          <div class="audit-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>زمان</th>
                  <th>کاربر</th>
                  <th>عملیات</th>
                  <th>مبلغ قبل</th>
                  <th>مبلغ بعد</th>
                  <th>مبنا قبل</th>
                  <th>مبنا بعد</th>
                  <th>نرخ قبل</th>
                  <th>نرخ بعد</th>
                  <th>شرح قبل</th>
                  <th>شرح بعد</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(event, index) in auditEvents(detailLine)" :key="index">
                  <td>{{ formatDateTime(event.at) }}</td>
                  <td>{{ event.user_name || 'کاربر نامشخص' }}</td>
                  <td>{{ auditActionLabel(event.action) }}</td>
                  <td>{{ auditMoney(event.before?.amount) }}</td>
                  <td>{{ auditMoney(event.after?.amount) }}</td>
                  <td>{{ auditMoney(event.before?.commission_base) }}</td>
                  <td>{{ auditMoney(event.after?.commission_base) }}</td>
                  <td>{{ auditRate(event.before) }}</td>
                  <td>{{ auditRate(event.after) }}</td>
                  <td>{{ event.before?.description || '-' }}</td>
                  <td>{{ event.after?.description || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </div>
  </section>
</template>

<script>
export default {
  name: 'PayrollSettlement',
  data() {
    return {
      resources: [],
      selected: null,
      selectedMonth: this.previousJalaliMonth(),
      search: '',
      filterType: 'all',
      report: null,
      loading: false,
      error: '',
      activeTab: 'lines',
      detailLine: null,
      lineForm: {},
      editingLine: false,
      deletingLineId: null,
      restoringLineId: null,
      savingLine: false
    }
  },
  computed: {
    currentJalaliMonth() {
      const parts = this.jalaliParts(new Date())
      return `${parts.year}-${String(parts.month).padStart(2, '0')}`
    },
    selectedKey() {
      return this.selected ? `${this.selected.type}-${this.selected.id}` : ''
    },
    filteredResources() {
      const query = this.search.toLowerCase()
      return this.resources.filter(resource => {
        if (this.filterType !== 'all' && resource.type !== this.filterType) return false
        return !query || String(resource.name || '').toLowerCase().includes(query)
      })
    },
    attendanceLines() {
      const types = ['attendance_overtime', 'attendance_shortage', 'attendance_absence']
      return Array.isArray(this.report?.lines)
        ? this.report.lines.filter(line => types.includes(line.earning_type))
        : []
    }
  },
  async mounted() {
    await this.loadResources()
    if (this.resources.length) this.selectResource(this.resources[0])
  },
  methods: {
    async loadResources() {
      try {
        const response = await fetch('/api/payroll/resources', { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('دریافت کاربران انجام نشد.')
        const data = await response.json()
        this.resources = Array.isArray(data.resources) ? data.resources : []
      } catch (error) {
        this.error = error.message || 'خطا در دریافت کاربران'
      }
    },
    async loadReport() {
      if (!this.selected) return
      this.loading = true
      this.error = ''
      try {
        const params = new URLSearchParams({
          resource_type: this.selected.type,
          resource_id: this.selected.id,
          month: this.selectedMonth
        })
        const response = await fetch(`/api/payroll/report?${params}`, { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('گزارش این ماه قابل دریافت نیست.')
        this.report = await response.json()
      } catch (error) {
        this.report = null
        this.error = error.message || 'خطا در محاسبه گزارش'
      } finally {
        this.loading = false
      }
    },
    selectResource(resource) {
      this.selected = resource
      this.activeTab = 'lines'
      this.loadReport()
    },
    openLineDetail(line) {
      this.detailLine = line
      this.editingLine = false
      this.lineForm = {
        description: line.description || '',
        service_name: line.service_name || line.inventory_name || '',
        inventory_name: line.inventory_name || '',
        quantity: Number(line.quantity || 0),
        gross_amount: Number(line.gross_amount || 0),
        discount_amount: Number(line.discount_amount || 0),
        net_amount: Number(line.net_amount || 0),
        material_cost: Number(line.material_cost || 0),
        commission_base: Number(line.commission_base || 0),
        commission_type: line.commission_type || 'fixed',
        commission_value: Number(line.commission_value || 0),
        amount: Number(line.amount || 0)
      }
    },
    closeLineDetail() {
      this.detailLine = null
      this.lineForm = {}
      this.editingLine = false
    },
    async saveLine() {
      if (!this.detailLine) return
      this.savingLine = true
      try {
        const response = await fetch(`/api/payroll/lines/${this.detailLine.id}`, {
          method: 'PATCH',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(this.lineForm)
        })
        if (!response.ok) throw new Error('ویرایش ریزمحاسبه انجام نشد.')
        this.closeLineDetail()
        await this.loadReport()
      } catch (error) {
        window.alert(error.message || 'خطا در ویرایش ریزمحاسبه')
      } finally {
        this.savingLine = false
      }
    },
    async deleteLine(line) {
      if (line.status === 'deleted') return
      if (!window.confirm('این ریزمحاسبه از تسویه حذف شود؟ جمع حقوق دوباره محاسبه می‌شود.')) return
      this.deletingLineId = line.id
      try {
        const response = await fetch(`/api/payroll/lines/${line.id}`, {
          method: 'DELETE',
          headers: { Accept: 'application/json' }
        })
        if (!response.ok) throw new Error('حذف ریزمحاسبه انجام نشد.')
        if (this.detailLine?.id === line.id) this.closeLineDetail()
        await this.loadReport()
      } catch (error) {
        window.alert(error.message || 'خطا در حذف ریزمحاسبه')
      } finally {
        this.deletingLineId = null
      }
    },
    async restoreLine(line) {
      if (!window.confirm('این ردیف حذف‌شده به تسویه برگردانده شود؟ جمع حقوق دوباره محاسبه می‌شود.')) return
      this.restoringLineId = line.id
      try {
        const response = await fetch(`/api/payroll/lines/${line.id}/restore`, {
          method: 'POST',
          headers: { Accept: 'application/json' }
        })
        if (!response.ok) throw new Error('بازگردانی ردیف انجام نشد.')
        if (this.detailLine?.id === line.id) this.closeLineDetail()
        await this.loadReport()
      } catch (error) {
        window.alert(error.message || 'خطا در بازگردانی ردیف')
      } finally {
        this.restoringLineId = null
      }
    },
    shiftMonth(delta) {
      const [year, month] = this.selectedMonth.split('-').map(Number)
      const date = { year, month: month + delta }
      if (date.month < 1) { date.year -= 1; date.month = 12 }
      if (date.month > 12) { date.year += 1; date.month = 1 }
      const next = `${date.year}-${String(date.month).padStart(2, '0')}`
      if (next > this.currentJalaliMonth) return
      this.selectedMonth = next
      this.loadReport()
    },
    jalaliParts(date) {
      const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
        year: 'numeric', month: '2-digit'
      }).formatToParts(date).filter(part => ['year', 'month'].includes(part.type)).map(part => [part.type, part.value]))
      return { year: Number(parts.year), month: Number(parts.month) }
    },
    previousJalaliMonth() {
      const parts = this.jalaliParts(new Date())
      const month = parts.month === 1 ? 12 : parts.month - 1
      const year = parts.month === 1 ? parts.year - 1 : parts.year
      return `${year}-${String(month).padStart(2, '0')}`
    },
    monthLabel(month) {
      const [year, value] = String(month || '').split('-')
      return `${this.monthNames[Number(value) - 1] || value} ${year}`
    },
    money(value) {
      return `${Math.round(Number(value || 0)).toLocaleString('fa-IR')} تومان`
    },
    initial(name) {
      return String(name || '؟').trim().slice(0, 1)
    },
    lineType(type) {
      return {
        base_commission: 'پورسانت پایه',
        inventory_commission: 'پورسانت انبار',
        sales_bonus: 'پاداش فروش',
        attendance_overtime: 'اضافه‌کاری',
        attendance_shortage: 'کسری ساعت',
        attendance_absence: 'غیبت'
      }[type] || type || '-'
    },
    isAttendanceLine(line) {
      return ['attendance_overtime', 'attendance_shortage', 'attendance_absence'].includes(line?.earning_type)
    },
    attendanceClockText(line) {
      const snapshot = line?.calculation_snapshot || {}
      if (snapshot.absent) return 'غیبت'
      return `${snapshot.in || '-'} تا ${snapshot.out || '-'}`
    },
    rateText(line) {
      if (line.commission_type === 'percent') return `${Number(line.commission_value || 0).toLocaleString('fa-IR')}٪`
      if (line.commission_type === 'hourly') return `${this.money(line.commission_value)} / ساعت`
      if (line.commission_type === 'tier') return `از ${this.money(line.commission_value)}`
      return this.money(line.commission_value)
    },
    appointmentTitle(line) {
      if (this.isAttendanceLine(line)) return 'حضور و غیاب'
      const appointment = line.appointment
      if (!appointment) return 'بدون نوبت مستقیم'
      return `${appointment.lastname || 'نوبت'} - ${appointment.month || ''}/${appointment.day_num || ''} ${appointment.time || ''}`
    },
    appointmentServices(line) {
      if (this.isAttendanceLine(line)) return line.description || this.lineType(line.earning_type)
      const services = line.appointment?.services
      if (!Array.isArray(services) || !services.length) return line.service_name || '-'
      return services.map(service => {
        const addons = Array.isArray(service.addons) ? service.addons.map(addon => addon.name).filter(Boolean) : []
        return [service.name, ...addons].filter(Boolean).join(' + ')
      }).filter(Boolean).join('، ') || '-'
    },
    appointmentTotal(line) {
      if (this.isAttendanceLine(line)) return '-'
      const appointment = line.appointment
      if (!appointment) return '-'
      const original = Number(appointment.original_amount || 0)
      const amount = Number(appointment.amount || 0)
      return this.money(original || amount)
    },
    auditEvents(line) {
      return Array.isArray(line?.audit_events) ? line.audit_events : []
    },
    auditText(line, type) {
      const name = type === 'deleted' ? line.deleted_by_name : line.edited_by_name
      const at = type === 'deleted' ? line.deleted_at : line.edited_at
      return `${name || 'کاربر نامشخص'} - ${this.formatDateTime(at)}`
    },
    auditMoney(value) {
      if (value === undefined || value === null || value === '') return '-'
      return this.money(value)
    },
    auditRate(record = {}) {
      if (!record || (!record.commission_type && record.commission_value === undefined)) return '-'
      if (record.commission_type === 'percent') return `${Number(record.commission_value || 0).toLocaleString('fa-IR')}٪`
      if (record.commission_type === 'hourly') return `${this.money(record.commission_value)} / ساعت`
      return this.money(record.commission_value)
    },
    auditActionLabel(action) {
      return { edit: 'ویرایش', delete: 'حذف', restore: 'بازگردانی' }[action] || action || '-'
    },
    formatDateTime(value) {
      if (!value) return '-'
      try {
        return new Intl.DateTimeFormat('fa-IR', {
          dateStyle: 'short',
          timeStyle: 'short'
        }).format(new Date(value))
      } catch {
        return String(value)
      }
    },
    scopeLabel(scope) {
      return { new: 'مشتری جدید', existing: 'مشتری قدیمی', both: 'همه مشتریان' }[scope] || 'همه مشتریان'
    }
  },
  created() {
    this.monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
  }
}
</script>

<style scoped>
.payroll-page{min-height:100vh;padding:24px;background:#f4f7fb;color:#172033}
.payroll-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
.payroll-header small{font-size:12px;font-weight:900;color:#2563eb}.payroll-header h1{margin:4px 0 0;font-size:24px}
.payroll-month{display:flex;align-items:center;gap:8px}.payroll-month button{width:36px;height:36px;border:1px solid #dbe3ed;border-radius:8px;background:#fff;color:#2563eb;font-size:24px;cursor:pointer}.payroll-month button:disabled{opacity:.4}.payroll-month input{height:36px;border:1px solid #dbe3ed;border-radius:8px;padding:0 10px;background:#fff}
.payroll-layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px}.payroll-sidebar,.payroll-content{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
.payroll-search{display:grid;gap:6px}.payroll-search span{font-size:12px;font-weight:800;color:#64748b}.payroll-search input{height:40px;border:1px solid #dbe3ed;border-radius:8px;padding:0 12px;outline:none}
.payroll-segments{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:12px 0}.payroll-segments button,.payroll-tabs button{height:34px;border:1px solid #dbe3ed;border-radius:8px;background:#f8fafc;color:#475569;cursor:pointer;font-weight:800}.payroll-segments button.active,.payroll-tabs button.active{background:#2563eb;color:#fff;border-color:#2563eb}
.payroll-user-list{display:grid;gap:8px;max-height:calc(100vh - 205px);overflow:auto}.payroll-user-list button{display:flex;align-items:center;gap:10px;width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;text-align:right;cursor:pointer}.payroll-user-list button.active{border-color:#2563eb;background:#eff6ff}.payroll-user-list img,.payroll-user-list b{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#dbeafe;color:#1d4ed8}.payroll-user-list img{object-fit:cover}.payroll-user-list span{display:grid}.payroll-user-list strong{font-size:13px}.payroll-user-list small{color:#64748b;font-size:11px}
.payroll-state{min-height:420px;display:flex;align-items:center;justify-content:center;gap:10px;color:#64748b}.payroll-state.error{color:#b91c1c}.payroll-state i{width:24px;height:24px;border:3px solid #dbeafe;border-top-color:#2563eb;border-radius:50%;animation:spin .8s linear infinite}
.payroll-person{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.payroll-person small{font-weight:900;color:#2563eb}.payroll-person h2{margin:3px 0 0;font-size:21px}.payroll-person>strong{padding:8px 12px;border-radius:8px;background:#f1f5f9}
.payroll-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.payroll-summary article{display:grid;gap:6px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.payroll-summary .total{grid-column:span 2;background:#ecfdf5;border-color:#bbf7d0}.payroll-summary span{font-size:11px;font-weight:900;color:#64748b}.payroll-summary strong{font-size:19px;color:#0f172a}.payroll-summary .total strong{font-size:26px;color:#047857}
.payroll-tabs{display:flex;gap:8px;margin:16px 0}.payroll-tabs button{padding:0 16px}
.payroll-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:8px}.payroll-table{width:100%;border-collapse:collapse;min-width:850px}.payroll-table th,.payroll-table td{padding:11px;border-bottom:1px solid #edf2f7;text-align:right;font-size:12px}.payroll-table th{background:#f8fafc;color:#64748b;font-weight:900}.payroll-table td.positive{color:#047857;font-weight:900}.payroll-table td.negative{color:#b91c1c;font-weight:900}
.payroll-table tr.edited td{background:#fffbeb}.payroll-table tr.deleted td{background:#f8fafc;color:#94a3b8;text-decoration:line-through}.line-status{display:inline-block;margin-right:7px;padding:2px 6px;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:900;text-decoration:none}.line-status.edited{background:#fef3c7;color:#b45309}
.line-actions{display:flex;align-items:center;gap:6px;min-width:66px}.line-actions button{width:28px;height:28px;border-radius:8px;cursor:pointer;font-weight:900}.info-btn{border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb}.delete-line-btn{border:1px solid #fecaca;background:#fff1f2;color:#dc2626;font-size:18px;line-height:1}.restore-line-btn{border:1px solid #bbf7d0;background:#ecfdf5;color:#047857}.delete-line-btn:disabled,.restore-line-btn:disabled{opacity:.45;cursor:not-allowed}.delete-line-btn.wide,.restore-line-btn.wide{width:auto;height:40px;padding:0 16px;font-size:13px}
.payroll-attendance{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}.payroll-attendance article{display:grid;gap:5px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff}.payroll-attendance article.overtime{background:#ecfdf5}.payroll-attendance article.shortage,.payroll-attendance article.absent{background:#fef2f2}.payroll-attendance b{color:#2563eb}.payroll-attendance span,.payroll-attendance small{color:#64748b;font-size:11px}
.payroll-settings{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.payroll-settings article{display:grid;gap:7px;padding:13px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.payroll-settings span{color:#64748b;font-size:12px;font-weight:900}
.payroll-empty{padding:20px;text-align:center;color:#64748b}
.line-detail-overlay{position:fixed;inset:0;z-index:1000002;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58);backdrop-filter:blur(4px)}.line-detail-modal{width:min(880px,96vw);max-height:90vh;overflow:auto;padding:18px;border-radius:10px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.35)}.line-detail-modal header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.line-detail-modal header small{font-weight:900;color:#2563eb}.line-detail-modal h2{margin:3px 0 0;font-size:18px}.line-detail-modal header button{width:34px;height:34px;border:0;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:22px;cursor:pointer}.line-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}.line-detail-grid article{display:grid;gap:6px;padding:11px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.line-detail-grid article.earned{background:#ecfdf5;border-color:#bbf7d0}.line-detail-grid article.audit{background:#fffbeb;border-color:#fde68a}.line-detail-grid article.deleted{background:#fef2f2;border-color:#fecaca}.line-detail-grid article.earned strong{color:#047857;font-size:17px}.line-detail-grid span{font-size:11px;font-weight:900;color:#64748b}.line-detail-grid strong{font-size:13px;color:#0f172a;line-height:1.8}.line-edit-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.line-edit-form label{display:grid;gap:6px}.line-edit-form span{font-size:11px;font-weight:900;color:#64748b}.line-edit-form input,.line-edit-form select{height:40px;border:1px solid #dbe3ed;border-radius:8px;padding:0 10px;background:#fff;color:#172033;outline:none}.line-edit-form input:focus,.line-edit-form select:focus{border-color:#2563eb;box-shadow:0 0 0 3px #dbeafe}.line-final-amount{grid-column:span 2}.line-final-amount input{font-weight:900;color:#047857}.line-detail-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.save-line-btn,.edit-line-btn,.cancel-line-btn{height:40px;border:0;border-radius:8px;padding:0 18px;font-weight:900;cursor:pointer}.save-line-btn,.edit-line-btn{background:#2563eb;color:#fff}.cancel-line-btn{background:#f1f5f9;color:#475569}.save-line-btn:disabled,.cancel-line-btn:disabled{opacity:.6;cursor:wait}.line-audit-list{margin-top:14px;padding-top:12px;border-top:1px solid #e2e8f0}.line-audit-list h3{margin:0 0 8px;font-size:14px}.audit-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:8px}.line-audit-list table{width:100%;min-width:980px;border-collapse:collapse}.line-audit-list th,.line-audit-list td{padding:9px;border-bottom:1px solid #edf2f7;text-align:right;font-size:11px;white-space:nowrap}.line-audit-list th{background:#f8fafc;color:#64748b;font-weight:900}.line-audit-list td:nth-child(3){font-weight:900;color:#2563eb}
@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:980px){.payroll-layout{grid-template-columns:1fr}.payroll-summary{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.payroll-page{padding:12px}.payroll-header{align-items:stretch;flex-direction:column}.payroll-summary,.payroll-settings{grid-template-columns:1fr}.payroll-summary .total{grid-column:auto}}
</style>
