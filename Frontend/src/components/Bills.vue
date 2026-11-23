<template>
  <div class="bills-page">

    <div class="page-header">
      <h1>مدیریت فاکتورها</h1>
      <div class="header-actions">
        <p v-if="loadError" class="bills-error" role="alert">{{ loadError }}</p>
        <p v-if="saveError" class="bills-error" role="alert">{{ saveError }}</p>
        <button
          type="button"
          class="bills-save-btn"
          :disabled="loading || saving"
          @click="saveAll"
        >
          {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="bills-loading">در حال دریافت هزینه‌ها از سرور...</div>

    <div class="table-wrapper">

      <table class="modern-table">

        <thead>
          <tr>

            <th class="center-title">نام کالا</th>
            <th class="center-title">مبلغ</th>
            <th class="center-title">شماره فکتور</th>
            <th class="center-title date-col">تاریخ فاکتور</th>
            <th class="center-title party-col">طرف حساب</th>

            <th class="filter-th">
              <div class="th-flex">
                <span>دسته بندی</span>
                <button ref="categoryBtn" class="filter-btn" @click.stop="toggleFilter('category', $event)">●</button>
              </div>
            </th>

            <th class="filter-th">
              <div class="th-flex">
                <span>نوع حساب</span>
                <button ref="accountBtn" class="filter-btn" @click.stop="toggleFilter('accountType', $event)">●</button>
              </div>
            </th>

            <th class="filter-th">
              <div class="th-flex">
                <span>روش پرداخت</span>
                <button ref="paymentBtn" class="filter-btn" @click.stop="toggleFilter('paymentMethod', $event)">●</button>
              </div>
            </th>

            <th class="filter-th">
              <div class="th-flex">
                <span>نوع</span>
                <button ref="typeBtn" class="filter-btn" @click.stop="toggleFilter('type', $event)">●</button>
              </div>
            </th>

            <th class="filter-th">
              <div class="th-flex">
                <span>وضعیت</span>
                <button ref="statusBtn" class="filter-btn" @click.stop="toggleFilter('status', $event)">●</button>
              </div>
            </th>

            <th class="center-title description-col">توضیحات</th>
            <th class="center-title attachment-col">عکس</th>
            <th class="center-title actions-col">عملیات</th>

          </tr>
        </thead>

        <tbody>

          <tr
            v-for="(row, index) in filteredRows"
            :key="row.localKey"
          >

            <td>
              <input v-model="row.productName" type="text" />
            </td>

            <td>
              <input
                :value="formatNumber(row.amount)"
                @input="updateAmount($event, row)"
                type="text"
              />
            </td>

            <td>
              <input v-model="row.invoiceNumber" type="text" />
            </td>

            <td class="date-cell">
              <date-picker
                v-model="row.invoiceDate"
                format="jYYYY/jMM/jDD"
                display-format="jYYYY/jMM/jDD"
                input-class="date-input"
                auto-submit
              />
            </td>

            <td class="party-cell">
              <input v-model="row.party" type="text" />
            </td>

            <td>
              <select v-model="row.category">
                <option value="">انتخاب</option>
                <option v-for="item in categoryOptions" :key="item" :value="item">{{ item }}</option>
              </select>
            </td>

            <td>
              <select v-model="row.accountType">
                <option value="">انتخاب</option>
                <option v-for="item in accountTypeOptions" :key="item" :value="item">{{ item }}</option>
              </select>
            </td>

            <td>
              <select v-model="row.paymentMethod">
                <option value="">انتخاب</option>
                <option v-for="item in paymentMethodOptions" :key="item" :value="item">{{ item }}</option>
              </select>
            </td>

            <td>
              <select v-model="row.type">
                <option value="">انتخاب</option>
                <option v-for="item in typeOptions" :key="item" :value="item">{{ item }}</option>
              </select>
            </td>

            <td>
              <select v-model="row.status" :class="statusClass(row.status)">
                <option value="">انتخاب</option>
                <option v-for="item in statusOptions" :key="item" :value="item">{{ item }}</option>
              </select>
            </td>

            <td>
              <textarea v-model="row.description"></textarea>
            </td>

            <td class="attachment-cell">
              <input
                :id="`bill-photo-${row.localKey}`"
                class="bill-photo-input"
                type="file"
                accept="image/*"
                @change="attachImage($event, row)"
              />
              <div v-if="row.imageData || row.attachmentUrl" class="bill-photo-preview">
                <button type="button" class="bill-photo-thumb" title="مشاهده عکس" @click="previewImage(row)">
                  <img :src="row.imageData || row.attachmentUrl" :alt="row.imageName || 'عکس هزینه'">
                </button>
                <button type="button" class="bill-photo-remove" title="حذف عکس" @click="removeImage(row)">×</button>
              </div>
              <label v-else class="bill-photo-add" :for="`bill-photo-${row.localKey}`">+ عکس</label>
            </td>

            <td class="actions-cell">
              <button
                type="button"
                class="bill-row-delete"
                :disabled="saving"
                title="حذف ردیف"
                @click="deleteRow(row, index)"
              >
                حذف
              </button>
            </td>

          </tr>

        </tbody>

      </table>

    </div>

    <div
      v-if="openFilter"
      class="global-filter-popup"
      :style="popupStyle"
      @click.stop
    >
      <label
        v-for="item in currentOptions"
        :key="item"
        class="checkbox-item"
      >
        <input
          type="checkbox"
          :value="item"
          v-model="filters[openFilter]"
        />
        <span>{{ item }}</span>
      </label>

      <div
        v-if="openFilter !== 'status'"
        class="add-option"
        @click="addOption(openFilter)"
      >
        ✏️ افزودن
      </div>
    </div>

    <div class="row-controller">
      <input type="number" v-model.number="rowCount" min="1" />
      <button type="button" @click="applyRows">اعمال تعداد ردیف</button>
      <button
        type="button"
        class="bills-save-btn"
        :disabled="loading || saving"
        @click="saveAll"
      >
        {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
      </button>
    </div>

    <div v-if="previewPhoto" class="bill-photo-modal" @click.self="previewPhoto = null">
      <section>
        <button type="button" @click="previewPhoto = null">×</button>
        <img :src="previewPhoto.data" :alt="previewPhoto.name || 'عکس هزینه'">
        <span>{{ previewPhoto.name || 'عکس هزینه' }}</span>
      </section>
    </div>

  </div>
</template>

<script>

import axios from 'axios'
import Swal from 'sweetalert2'
import DatePicker from 'vue3-persian-datetime-picker'

const TYPE_TO_API = {
  'درآمد': 'income',
  'هزینه': 'expense',
  'طلبکار': 'receivable',
  'بدهکار': 'payable',
  income: 'income',
  expense: 'expense',
  receivable: 'receivable',
  payable: 'payable',
}

const TYPE_TO_UI = {
  income: 'درآمد',
  expense: 'هزینه',
  receivable: 'طلبکار',
  payable: 'بدهکار',
}

const STATUS_TO_API = {
  'پرداخت شد': 'paid',
  'پرداخت نشد': 'unpaid',
  'در انتظار': 'pending',
  paid: 'paid',
  unpaid: 'unpaid',
  pending: 'pending',
}

const STATUS_TO_UI = {
  paid: 'پرداخت شد',
  unpaid: 'پرداخت نشد',
  pending: 'در انتظار',
}

let localKeySeq = 0
const nextLocalKey = () => `bill-${Date.now()}-${++localKeySeq}`

const emptyRow = () => ({
  id: null,
  localKey: nextLocalKey(),
  productName: '',
  amount: '',
  invoiceNumber: '',
  invoiceDate: '',
  party: '',
  category: '',
  accountType: '',
  paymentMethod: '',
  type: '',
  status: '',
  description: '',
  imageData: '',
  imageName: '',
  attachmentUrl: '',
  clearAttachment: false,
  dirtyAttachment: false,
})

export default {
  name: 'Bills',

  components: {
    DatePicker
  },

  data() {
    return {
      openFilter: null,
      popupStyle: {},
      rowCount: 5,
      previewPhoto: null,
      loading: false,
      saving: false,
      loadError: '',
      saveError: '',
      knownIds: [],
      rows: Array.from({ length: 5 }, () => emptyRow()),
      categoryOptions: [
        'حقوق',
        'اجاره',
        'مواد مصرفی',
        'تبلیغات',
        'هزینه‌ها',
        'آشپزخانه',
        'تعمیرات',
        'تجهیزات'
      ],
      accountTypeOptions: [
        'اصلی',
        'تنخواه'
      ],
      paymentMethodOptions: [
        'نقدی',
        'پوز',
        'کارت به کارت'
      ],
      typeOptions: [
        'درآمد',
        'هزینه',
        'طلبکار',
        'بدهکار'
      ],
      statusOptions: [
        'پرداخت شد',
        'پرداخت نشد',
        'در انتظار'
      ],
      filters: {
        category: [],
        accountType: [],
        paymentMethod: [],
        type: [],
        status: []
      }
    }
  },

  computed: {
    currentOptions() {
      if (this.openFilter === 'category') return this.categoryOptions
      if (this.openFilter === 'accountType') return this.accountTypeOptions
      if (this.openFilter === 'paymentMethod') return this.paymentMethodOptions
      if (this.openFilter === 'type') return this.typeOptions
      if (this.openFilter === 'status') return this.statusOptions
      return []
    },

    filteredRows() {
      return this.rows.filter(row => {
        const categoryMatch = !this.filters.category.length || this.filters.category.includes(row.category)
        const accountMatch = !this.filters.accountType.length || this.filters.accountType.includes(row.accountType)
        const paymentMatch = !this.filters.paymentMethod.length || this.filters.paymentMethod.includes(row.paymentMethod)
        const typeMatch = !this.filters.type.length || this.filters.type.includes(row.type)
        const statusMatch = !this.filters.status.length || this.filters.status.includes(row.status)
        return categoryMatch && accountMatch && paymentMatch && typeMatch && statusMatch
      })
    }
  },

  methods: {
    blankRow() {
      return emptyRow()
    },

    normalizeDigits(value) {
      return String(value || '').replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
    },

    toApiDate(value) {
      if (!value) return ''
      if (typeof value === 'object' && value !== null && typeof value.format === 'function') {
        try { return value.format('jYYYY-jMM-jDD') } catch (_) { /* fall through */ }
      }
      const raw = this.normalizeDigits(value).trim().replaceAll('/', '-')
      const match = raw.match(/^(1[34]\d{2})-(\d{1,2})-(\d{1,2})/)
      if (!match) return ''
      return `${match[1]}-${match[2].padStart(2, '0')}-${match[3].padStart(2, '0')}`
    },

    toUiDate(value) {
      const raw = this.normalizeDigits(value).trim().replaceAll('-', '/')
      const match = raw.match(/^(1[34]\d{2})\/(\d{1,2})\/(\d{1,2})/)
      if (!match) return ''
      return `${match[1]}/${match[2].padStart(2, '0')}/${match[3].padStart(2, '0')}`
    },

    mapFromApi(item) {
      const category = String(item.category || '').trim()
      if (category && !this.categoryOptions.includes(category)) this.categoryOptions.push(category)
      const accountType = String(item.account_type || '').trim()
      if (accountType && !this.accountTypeOptions.includes(accountType)) this.accountTypeOptions.push(accountType)
      const paymentMethod = String(item.payment_method || '').trim()
      if (paymentMethod && !this.paymentMethodOptions.includes(paymentMethod)) this.paymentMethodOptions.push(paymentMethod)

      return {
        id: item.id || null,
        localKey: nextLocalKey(),
        productName: item.title || '',
        amount: item.amount == null ? '' : String(item.amount).replace(/\.00$/, ''),
        invoiceNumber: item.invoice_number || '',
        invoiceDate: this.toUiDate(item.occurred_on),
        party: item.party || '',
        category,
        accountType,
        paymentMethod,
        type: TYPE_TO_UI[item.type] || item.type || '',
        status: STATUS_TO_UI[item.payment_status] || item.payment_status || '',
        description: item.note || '',
        imageData: '',
        imageName: '',
        attachmentUrl: item.attachment_url || '',
        clearAttachment: false,
        dirtyAttachment: false,
      }
    },

    rowHasAnyData(row) {
      return [
        row.productName, row.amount, row.invoiceNumber, row.invoiceDate, row.party,
        row.category, row.accountType, row.paymentMethod, row.type, row.status,
        row.description, row.imageData, row.attachmentUrl
      ].some(value => String(value || '').trim() !== '')
    },

    rowIsReady(row) {
      return !!(
        String(row.productName || '').trim()
        && String(row.amount || '').trim() !== ''
        && this.toApiDate(row.invoiceDate)
        && String(row.category || '').trim()
        && TYPE_TO_API[row.type]
        && STATUS_TO_API[row.status]
      )
    },

    buildPayload(row) {
      const payload = {
        title: String(row.productName || '').trim(),
        amount: Number(String(row.amount || '0').replace(/,/g, '')),
        invoice_number: String(row.invoiceNumber || '').trim() || null,
        occurred_on: this.toApiDate(row.invoiceDate),
        party: String(row.party || '').trim() || null,
        category: String(row.category || '').trim(),
        account_type: String(row.accountType || '').trim() || null,
        payment_method: String(row.paymentMethod || '').trim() || null,
        type: TYPE_TO_API[row.type],
        payment_status: STATUS_TO_API[row.status],
        note: String(row.description || '').trim() || null,
      }

      if (row.dirtyAttachment && row.imageData) {
        payload.attachment_data = row.imageData
        payload.attachment_name = row.imageName || 'expense.jpg'
      } else if (row.clearAttachment) {
        payload.clear_attachment = true
      }

      return payload
    },

    async loadExpenses() {
      this.loading = true
      this.loadError = ''
      try {
        const { data } = await axios.get('/api/expenses')
        const items = Array.isArray(data) ? data : (data?.data || [])
        const mapped = items.map(item => this.mapFromApi(item))
        this.knownIds = mapped.map(row => row.id).filter(Boolean)
        const padded = [...mapped]
        while (padded.length < Math.max(this.rowCount, 5)) padded.push(this.blankRow())
        this.rows = padded
        this.rowCount = this.rows.length
      } catch (error) {
        this.loadError = this.errorMessage(error, 'دریافت هزینه‌ها از سرور انجام نشد.')
        this.rows = Array.from({ length: Math.max(this.rowCount, 5) }, () => this.blankRow())
        this.knownIds = []
      } finally {
        this.loading = false
      }
    },

    async saveAll() {
      if (this.saving || this.loading) return
      this.saveError = ''

      const filled = this.rows.filter(row => this.rowHasAnyData(row))
      const incomplete = filled.filter(row => !this.rowIsReady(row))
      if (incomplete.length) {
        this.saveError = 'برای ذخیره، عنوان، مبلغ، تاریخ، دسته‌بندی، نوع و وضعیت هر ردیف پرشده الزامی است.'
        return
      }

      this.saving = true
      try {
        const keepIds = new Set()

        for (const row of filled) {
          const payload = this.buildPayload(row)
          if (row.id) {
            const { data } = await axios.put(`/api/expenses/${row.id}`, payload)
            keepIds.add(Number(data.id || row.id))
          } else {
            const { data } = await axios.post('/api/expenses', payload)
            keepIds.add(Number(data.id))
          }
        }

        for (const id of this.knownIds) {
          if (!keepIds.has(Number(id))) {
            await axios.delete(`/api/expenses/${id}`)
          }
        }

        await this.loadExpenses()

        const savedCount = filled.length
        const verifyIds = this.knownIds.slice()
        if (savedCount && verifyIds.length < savedCount) {
          this.saveError = 'ذخیره انجام شد ولی همخوانی با سرور تأیید نشد. صفحه را تازه کنید.'
          return
        }

        await Swal.fire({
          icon: 'success',
          toast: true,
          position: 'top-end',
          timer: 2200,
          showConfirmButton: false,
          title: savedCount ? `${savedCount.toLocaleString('fa-IR')} هزینه روی سرور ذخیره شد` : 'تغییرات هزینه‌ها ذخیره شد',
        })
      } catch (error) {
        this.saveError = this.errorMessage(error, 'ذخیره هزینه‌ها روی سرور انجام نشد.')
      } finally {
        this.saving = false
      }
    },

    errorMessage(error, fallback) {
      const errors = error?.response?.data?.errors
      if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat()[0]
        if (first) return String(first)
      }
      return error?.response?.data?.message || fallback
    },

    async deleteRow(row) {
      if (this.saving) return
      if (row.id) {
        const confirmed = await Swal.fire({
          icon: 'warning',
          title: 'حذف این هزینه؟',
          text: 'پس از تأیید، از سرور هم حذف می‌شود.',
          showCancelButton: true,
          confirmButtonText: 'حذف',
          cancelButtonText: 'انصراف',
          confirmButtonColor: '#dc2626',
        })
        if (!confirmed.isConfirmed) return
        this.saving = true
        this.saveError = ''
        try {
          await axios.delete(`/api/expenses/${row.id}`)
          await this.loadExpenses()
          await Swal.fire({
            icon: 'success',
            toast: true,
            position: 'top-end',
            timer: 1800,
            showConfirmButton: false,
            title: 'هزینه حذف شد',
          })
        } catch (error) {
          this.saveError = this.errorMessage(error, 'حذف هزینه انجام نشد.')
        } finally {
          this.saving = false
        }
        return
      }

      if (this.rows.length <= 1) {
        Object.assign(row, this.blankRow())
        return
      }
      this.rows = this.rows.filter(item => item.localKey !== row.localKey)
      this.rowCount = this.rows.length
    },

    toggleFilter(name, event) {
      if (this.openFilter === name) {
        this.openFilter = null
        return
      }
      this.openFilter = name
      this.$nextTick(() => {
        const rect = event.target.getBoundingClientRect()
        const popupWidth = 190
        let left = rect.left + window.scrollX - 150
        if (left < 10) left = 10
        if (left + popupWidth > window.innerWidth) left = window.innerWidth - popupWidth - 10
        this.popupStyle = {
          top: `${rect.bottom + window.scrollY + 8}px`,
          left: `${left}px`
        }
      })
    },

    addOption(type) {
      const title = prompt('عنوان جدید را وارد کنید')
      if (!title) return
      this[`${type}Options`].push(title)
    },

    formatNumber(value) {
      if (!value && value !== 0) return ''
      return Number(value.toString().replace(/,/g, '')).toLocaleString('en-US')
    },

    updateAmount(event, row) {
      const raw = event.target.value.replace(/,/g, '')
      if (!isNaN(raw)) row.amount = raw
      event.target.value = this.formatNumber(raw)
    },

    statusClass(status) {
      if (status === 'پرداخت شد') return 'status-paid'
      if (status === 'پرداخت نشد') return 'status-unpaid'
      if (status === 'در انتظار') return 'status-pending'
      return ''
    },

    applyRows() {
      if (this.rowCount > this.rows.length) {
        const diff = this.rowCount - this.rows.length
        for (let i = 0; i < diff; i++) this.rows.push(this.blankRow())
        return
      }

      const removable = this.rows.slice(this.rowCount)
      const hasData = removable.some(row => this.rowHasAnyData(row))
      if (hasData) {
        alert('این ستون ها شامل اطلاعات هستند ، و قابل حذف نمیباشند')
        return
      }
      this.rows = this.rows.slice(0, this.rowCount)
    },

    attachImage(event, row) {
      const file = event.target.files?.[0]
      event.target.value = ''
      if (!file) return
      if (!file.type.startsWith('image/')) {
        alert('فقط فایل عکس قابل ثبت است')
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        alert('حجم عکس باید کمتر از ۵ مگابایت باشد')
        return
      }

      const reader = new FileReader()
      reader.onload = () => {
        row.imageData = String(reader.result || '')
        row.imageName = file.name
        row.attachmentUrl = ''
        row.clearAttachment = false
        row.dirtyAttachment = true
      }
      reader.readAsDataURL(file)
    },

    removeImage(row) {
      row.imageData = ''
      row.imageName = ''
      if (row.attachmentUrl || row.id) row.clearAttachment = true
      row.attachmentUrl = ''
      row.dirtyAttachment = false
    },

    previewImage(row) {
      const data = row.imageData || row.attachmentUrl
      if (!data) return
      this.previewPhoto = {
        data,
        name: row.imageName
      }
    }
  },

  async mounted() {
    document.addEventListener('click', () => {
      this.openFilter = null
    })
    await this.loadExpenses()
  }

}
</script>

<style scoped>

*{
  box-sizing:border-box;
}

.bills-page{
  width:100%;
  min-height:100vh;
  padding:12px;
  background:#f4f7fb;
  direction:rtl;
  overflow:visible;
}

.page-header{
  margin-bottom:10px;
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:10px;
}

.page-header h1{
  font-size:20px;
  font-weight:700;
  color:#222;
  text-align:center;
  margin:0;
}

.header-actions{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:center;
  gap:10px;
}

.bills-error{
  margin:0;
  color:#b91c1c;
  font-size:12px;
  font-weight:700;
  max-width:520px;
  text-align:center;
}

.bills-loading{
  margin:0 0 10px;
  text-align:center;
  color:#64748b;
  font-size:13px;
  font-weight:700;
}

.bills-save-btn{
  height:36px;
  border:none;
  border-radius:10px;
  background:#16a34a;
  color:#fff;
  padding:0 18px;
  cursor:pointer;
  font-size:13px;
  font-weight:800;
  font-family:inherit;
}

.bills-save-btn:disabled{
  opacity:.55;
  cursor:not-allowed;
}

.table-wrapper{
  width:100%;
  background:#fff;
  border-radius:18px;
  overflow:visible;
  box-shadow:0 4px 18px rgba(0,0,0,.05);
}

.modern-table{
  width:100%;
  table-layout:fixed;
  border-collapse:collapse;
}

.modern-table th,
.modern-table td{
  border-bottom:1px solid #eee;
  padding:5px;
  text-align:center;
  vertical-align:middle;
}

.modern-table th{
  background:#fafafa;
  font-size:12px;
  color:#444;
  height:42px;
}

.center-title{
  text-align:center !important;
}

.modern-table td{
  height:52px;
}

.modern-table input,
.modern-table select,
.modern-table textarea{
  width:100%;
  border:1px solid #e3e3e3;
  border-radius:8px;
  background:#fff;
  padding:6px 8px;
  font-size:12px;
  font-family:inherit;
  transition:.2s;
  text-align:center;
}

.modern-table input{
  height:34px;
}

.modern-table select{
  height:34px;
}

.modern-table textarea{
  min-height:34px;
  max-height:60px;
  resize:vertical;
}

.attachment-col {
  width: 92px;
}

.actions-col {
  width: 72px;
}

.attachment-cell {
  position: relative;
}

.bill-photo-input {
  display: none;
}

.bill-photo-add,
.bill-photo-thumb,
.bill-photo-remove {
  font-family: inherit;
  cursor: pointer;
}

.bill-photo-add {
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 10px;
  border: 1px dashed #93c5fd;
  border-radius: 9px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}

.bill-photo-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
}

.bill-photo-thumb {
  width: 42px;
  height: 32px;
  overflow: hidden;
  padding: 0;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #f8fafc;
}

.bill-photo-thumb img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

.bill-photo-remove {
  width: 24px;
  height: 24px;
  border: 0;
  border-radius: 50%;
  background: #fee2e2;
  color: #dc2626;
  font-size: 16px;
  line-height: 1;
}

.bill-row-delete{
  height:30px;
  border:0;
  border-radius:8px;
  background:#fee2e2;
  color:#b91c1c;
  padding:0 10px;
  font-size:11px;
  font-weight:800;
  font-family:inherit;
  cursor:pointer;
}

.bill-row-delete:disabled{
  opacity:.5;
  cursor:not-allowed;
}

.bill-photo-modal {
  position: fixed;
  inset: 0;
  z-index: 1000000;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(15, 23, 42, .6);
}

.bill-photo-modal section {
  position: relative;
  width: min(720px, 96vw);
  max-height: 90vh;
  display: grid;
  gap: 10px;
  padding: 14px;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 25px 70px rgba(15, 23, 42, .35);
}

.bill-photo-modal button {
  position: absolute;
  top: 10px;
  left: 10px;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 10px;
  background: #f1f5f9;
  color: #475569;
  font-size: 22px;
  cursor: pointer;
}

.bill-photo-modal img {
  max-width: 100%;
  max-height: 76vh;
  object-fit: contain;
  border-radius: 10px;
}

.bill-photo-modal span {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.modern-table input:focus,
.modern-table select:focus,
.modern-table textarea:focus{
  border-color:#4f8cff;
  outline:none;
}

.date-col{
  width:160px;
}

.date-cell{
  width:160px;
  min-width:160px;
  padding:4px !important;
}

.party-col{
  width:150px;
}

.party-cell{
  width:150px;
}

.description-col{
  width:16%;
}

.date-input{
  width:100% !important;
  height:34px !important;
}

.date-cell :deep(.vpd-input-group){
  width:100% !important;
  display:flex !important;
}

.date-cell :deep(.vpd-input-group input){
  width:calc(100% - 40px) !important;
}

.date-cell :deep(.vpd-icon-btn){
  width:40px !important;
  flex-shrink:0;
}

.th-flex{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:5px;
}

.filter-btn{
  width:15px;
  height:15px;
  border:none;
  border-radius:50%;
  background:#d7d7d7;
  color:#666;
  font-size:7px;
  cursor:pointer;
  display:flex;
  align-items:center;
  justify-content:center;
}

.global-filter-popup{
  position:fixed;
  width:190px;
  background:#fff;
  border-radius:14px;
  padding:12px;
  box-shadow:0 8px 30px rgba(0,0,0,.14);
  z-index:999999;
}

.checkbox-item{
  width:100%;
  display:flex;
  align-items:center;
  gap:8px;
  margin-bottom:10px;
  font-size:13px;
  cursor:pointer;
}

.checkbox-item input{
  width:15px;
  height:15px;
  margin:0;
  flex-shrink:0;
}

.checkbox-item span{
  flex:1;
  text-align:right;
}

.add-option{
  margin-top:10px;
  padding-top:8px;
  border-top:1px solid #eee;
  color:#4f8cff;
  cursor:pointer;
  font-size:13px;
  text-align:center;
}

.status-paid{
  background:#dff5e1;
  color:#157a2f;
}

.status-unpaid{
  background:#ffe2e2;
  color:#c62828;
}

.status-pending{
  background:#fff5cf;
  color:#b38300;
}

.row-controller{
  margin-top:14px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  flex-wrap:wrap;
}

.row-controller input{
  width:100px;
  height:36px;
  border:1px solid #ddd;
  border-radius:10px;
  padding:0 10px;
  text-align:center;
}

.row-controller button{
  height:36px;
  border:none;
  border-radius:10px;
  background:#4f8cff;
  color:#fff;
  padding:0 14px;
  cursor:pointer;
  font-size:13px;
  font-family:inherit;
}

.row-controller .bills-save-btn{
  background:#16a34a;
  font-weight:800;
}

body.dark .bills-page,#app.dark .bills-page{background:#0f172a;color:#e2e8f0}
body.dark .page-header h1,#app.dark .page-header h1{color:#f8fafc}
body.dark .table-wrapper,#app.dark .table-wrapper{background:#172033;box-shadow:0 4px 18px rgba(0,0,0,.25)}
body.dark .modern-table th,#app.dark .modern-table th{background:#1e293b;color:#94a3b8;border-color:#334155}
body.dark .modern-table td,#app.dark .modern-table td{border-color:#334155;color:#e2e8f0}
body.dark .modern-table input,body.dark .modern-table select,body.dark .modern-table textarea,#app.dark .modern-table input,#app.dark .modern-table select,#app.dark .modern-table textarea{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .filter-btn,#app.dark .filter-btn{background:#334155;color:#94a3b8}
body.dark .global-filter-popup,#app.dark .global-filter-popup{background:#172033;border:1px solid #334155;color:#e2e8f0;box-shadow:0 8px 30px rgba(0,0,0,.4)}
body.dark .checkbox-item,#app.dark .checkbox-item{color:#e2e8f0}
body.dark .add-option,#app.dark .add-option{border-color:#334155;color:#60a5fa}
body.dark .bill-photo-modal section,#app.dark .bill-photo-modal section{background:#172033;color:#e2e8f0}
body.dark .bill-photo-modal button,#app.dark .bill-photo-modal button{background:#1e293b;color:#94a3b8}
body.dark .row-controller input,#app.dark .row-controller input{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .status-paid,#app.dark .status-paid{background:#052e16;color:#4ade80}
body.dark .status-unpaid,#app.dark .status-unpaid{background:#450a0a;color:#fca5a5}
body.dark .status-pending,#app.dark .status-pending{background:#422006;color:#fbbf24}
body.dark .bills-save-btn,#app.dark .bills-save-btn{background:#15803d;color:#fff}
body.dark .bill-row-delete,#app.dark .bill-row-delete{background:#450a0a;color:#fca5a5}
body.dark .bills-error,#app.dark .bills-error{color:#fca5a5}

</style>
