<template>
  <div class="bills-page">

    <!-- HEADER -->
    <div class="page-header">
      <h1>مدیریت فاکتورها</h1>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper">

      <table class="modern-table">

        <thead>
          <tr>

            <th class="center-title">نام کالا</th>

            <th class="center-title">مبلغ</th>

            <th class="center-title">شماره فکتور</th>

            <th class="center-title date-col">
              تاریخ فاکتور
            </th>

            <th class="center-title party-col">
              طرف حساب
            </th>

            <!-- دسته بندی -->
            <th class="filter-th">

              <div class="th-flex">

                <span>دسته بندی</span>

                <button
                  ref="categoryBtn"
                  class="filter-btn"
                  @click.stop="toggleFilter('category', $event)"
                >
                  ●
                </button>

              </div>

            </th>

            <!-- نوع حساب -->
            <th class="filter-th">

              <div class="th-flex">

                <span>نوع حساب</span>

                <button
                  ref="accountBtn"
                  class="filter-btn"
                  @click.stop="toggleFilter('accountType', $event)"
                >
                  ●
                </button>

              </div>

            </th>

            <!-- روش پرداخت -->
            <th class="filter-th">

              <div class="th-flex">

                <span>روش پرداخت</span>

                <button
                  ref="paymentBtn"
                  class="filter-btn"
                  @click.stop="toggleFilter('paymentMethod', $event)"
                >
                  ●
                </button>

              </div>

            </th>

            <!-- نوع -->
            <th class="filter-th">

              <div class="th-flex">

                <span>نوع</span>

                <button
                  ref="typeBtn"
                  class="filter-btn"
                  @click.stop="toggleFilter('type', $event)"
                >
                  ●
                </button>

              </div>

            </th>

            <!-- وضعیت -->
            <th class="filter-th">

              <div class="th-flex">

                <span>وضعیت</span>

                <button
                  ref="statusBtn"
                  class="filter-btn"
                  @click.stop="toggleFilter('status', $event)"
                >
                  ●
                </button>

              </div>

            </th>

            <th class="center-title description-col">
              توضیحات
            </th>

            <th class="center-title attachment-col">
              عکس
            </th>

          </tr>
        </thead>

        <tbody>

          <tr
            v-for="(row, index) in filteredRows"
            :key="index"
          >

            <!-- نام کالا -->
            <td>
              <input
                v-model="row.productName"
                type="text"
              />
            </td>

            <!-- مبلغ -->
            <td>
              <input
                :value="formatNumber(row.amount)"
                @input="updateAmount($event, row)"
                type="text"
              />
            </td>

            <!-- شماره فکتور -->
            <td>
              <input
                v-model="row.invoiceNumber"
                type="text"
              />
            </td>

            <!-- تاریخ -->
            <td class="date-cell">

              <date-picker
                v-model="row.invoiceDate"
                format="jYYYY/jMM/jDD"
                display-format="jYYYY/jMM/jDD"
                input-class="date-input"
                auto-submit
              />

            </td>

            <!-- طرف حساب -->
            <td class="party-cell">

              <input
                v-model="row.party"
                type="text"
              />

            </td>

            <!-- دسته بندی -->
            <td>

              <select v-model="row.category">

                <option value="">
                  انتخاب
                </option>

                <option
                  v-for="item in categoryOptions"
                  :key="item"
                  :value="item"
                >
                  {{ item }}
                </option>

              </select>

            </td>

            <!-- نوع حساب -->
            <td>

              <select v-model="row.accountType">

                <option value="">
                  انتخاب
                </option>

                <option
                  v-for="item in accountTypeOptions"
                  :key="item"
                  :value="item"
                >
                  {{ item }}
                </option>

              </select>

            </td>

            <!-- روش پرداخت -->
            <td>

              <select v-model="row.paymentMethod">

                <option value="">
                  انتخاب
                </option>

                <option
                  v-for="item in paymentMethodOptions"
                  :key="item"
                  :value="item"
                >
                  {{ item }}
                </option>

              </select>

            </td>

            <!-- نوع -->
            <td>

              <select v-model="row.type">

                <option value="">
                  انتخاب
                </option>

                <option
                  v-for="item in typeOptions"
                  :key="item"
                  :value="item"
                >
                  {{ item }}
                </option>

              </select>

            </td>

            <!-- وضعیت -->
            <td>

              <select
                v-model="row.status"
                :class="statusClass(row.status)"
              >

                <option value="">
                  انتخاب
                </option>

                <option
                  v-for="item in statusOptions"
                  :key="item"
                  :value="item"
                >
                  {{ item }}
                </option>

              </select>

            </td>

            <!-- توضیحات -->
            <td>

              <textarea
                v-model="row.description"
              ></textarea>

            </td>

            <!-- عکس -->
            <td class="attachment-cell">
              <input
                :id="`bill-photo-${index}`"
                class="bill-photo-input"
                type="file"
                accept="image/*"
                @change="attachImage($event, row)"
              />
              <div v-if="row.imageData" class="bill-photo-preview">
                <button type="button" class="bill-photo-thumb" title="مشاهده عکس" @click="previewImage(row)">
                  <img :src="row.imageData" :alt="row.imageName || 'عکس هزینه'">
                </button>
                <button type="button" class="bill-photo-remove" title="حذف عکس" @click="removeImage(row)">×</button>
              </div>
              <label v-else class="bill-photo-add" :for="`bill-photo-${index}`">+ عکس</label>
            </td>

          </tr>

        </tbody>

      </table>

    </div>

    <!-- FILTER POPUP -->
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

    <!-- کنترل ردیف -->
    <div class="row-controller">

      <input
        type="number"
        v-model.number="rowCount"
        min="1"
      />

      <button @click="applyRows">
        اعمال تعداد ردیف
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

import DatePicker from 'vue3-persian-datetime-picker'

export default {

  components: {
    DatePicker
  },

  data() {

    return {

      openFilter: null,

      popupStyle: {},

      rowCount: 5,
      previewPhoto: null,

      rows: Array.from({ length: 5 }, () => ({
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
        imageName: ''
      })),

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

      if (this.openFilter === 'category') {
        return this.categoryOptions
      }

      if (this.openFilter === 'accountType') {
        return this.accountTypeOptions
      }

      if (this.openFilter === 'paymentMethod') {
        return this.paymentMethodOptions
      }

      if (this.openFilter === 'type') {
        return this.typeOptions
      }

      if (this.openFilter === 'status') {
        return this.statusOptions
      }

      return []

    },

    filteredRows() {

      return this.rows.filter(row => {

        const categoryMatch =
          !this.filters.category.length ||
          this.filters.category.includes(row.category)

        const accountMatch =
          !this.filters.accountType.length ||
          this.filters.accountType.includes(row.accountType)

        const paymentMatch =
          !this.filters.paymentMethod.length ||
          this.filters.paymentMethod.includes(row.paymentMethod)

        const typeMatch =
          !this.filters.type.length ||
          this.filters.type.includes(row.type)

        const statusMatch =
          !this.filters.status.length ||
          this.filters.status.includes(row.status)

        return (
          categoryMatch &&
          accountMatch &&
          paymentMatch &&
          typeMatch &&
          statusMatch
        )

      })

    }

  },

  methods: {

    toggleFilter(name, event) {

      if (this.openFilter === name) {

        this.openFilter = null
        return

      }

      this.openFilter = name

      this.$nextTick(() => {

        const rect =
          event.target.getBoundingClientRect()

        const popupWidth = 190

        let left =
          rect.left + window.scrollX - 150

        if (left < 10) {
          left = 10
        }

        if (left + popupWidth > window.innerWidth) {
          left = window.innerWidth - popupWidth - 10
        }

        this.popupStyle = {
          top: `${rect.bottom + window.scrollY + 8}px`,
          left: `${left}px`
        }

      })

    },

    addOption(type) {

      const title =
        prompt('عنوان جدید را وارد کنید')

      if (!title) return

      this[`${type}Options`].push(title)

    },

    formatNumber(value) {

      if (!value) return ''

      return Number(
        value.toString().replace(/,/g, '')
      ).toLocaleString('en-US')

    },

    updateAmount(event, row) {

      const raw =
        event.target.value.replace(/,/g, '')

      if (!isNaN(raw)) {
        row.amount = raw
      }

      event.target.value =
        this.formatNumber(raw)

    },

    statusClass(status) {

      if (status === 'پرداخت شد') {
        return 'status-paid'
      }

      if (status === 'پرداخت نشد') {
        return 'status-unpaid'
      }

      if (status === 'در انتظار') {
        return 'status-pending'
      }

      return ''

    },

    applyRows() {

      if (this.rowCount > this.rows.length) {

        const diff =
          this.rowCount - this.rows.length

        for (let i = 0; i < diff; i++) {

          this.rows.push({
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
            imageName: ''
          })

        }

      } else {

        const removable =
          this.rows.slice(this.rowCount)

        const hasData = removable.some(row =>
          Object.values(row).some(
            value => value !== ''
          )
        )

        if (hasData) {

          alert(
            'این ستون ها شامل اطلاعات هستند ، و قابل حذف نمیباشند'
          )

          return
        }

        this.rows =
          this.rows.slice(0, this.rowCount)

      }

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
      }
      reader.readAsDataURL(file)
    },

    removeImage(row) {
      row.imageData = ''
      row.imageName = ''
    },

    previewImage(row) {
      if (!row.imageData) return
      this.previewPhoto = {
        data: row.imageData,
        name: row.imageName
      }
    }
  },

  mounted() {

    document.addEventListener('click', () => {
      this.openFilter = null
    })

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
}

.page-header h1{
  font-size:20px;
  font-weight:700;
  color:#222;
  text-align:center;
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

/* POPUP */

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
}

</style>
