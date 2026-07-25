<template>
  <div class="service-finder-page" dir="rtl" @click="closeAll">
    <header class="service-finder-hero">
      <div>
        <h1>خدمت‌یاب</h1>
      </div>
      <div class="service-stats">
        <span><b>{{ products.length.toLocaleString('fa-IR') }}</b> خدمت</span>
        <span><b>{{ zoneOptions.length.toLocaleString('fa-IR') }}</b> ناحیه</span>
        <span><b>{{ treatmentOptions.length.toLocaleString('fa-IR') }}</b> درمان</span>
      </div>
    </header>

    <section class="service-table-card">
      <div class="table-card-head">
        <div><h2>فهرست خدمات</h2></div>
        <label class="instant-search service-problem-search" @click.stop>
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
          <input v-model.trim="searchQuery" type="search" placeholder="مشکل، ناحیه یا هدف؛ مثلا چانه، لک، فرم‌دهی">
          <button v-if="searchQuery" type="button" @click="searchQuery = ''">×</button>
        </label>
        <div class="management-menus">
          <HeaderPicker
            title="مدیریت نواحی‌ها"
            :open="headerOpen === 'zone'"
            :options="zoneOptions"
            v-model:new-value="newZone"
            @toggle="toggleHeader('zone')"
            @close="headerOpen = null"
            @add="addZone"
            @remove="removeZone"
          />
          <HeaderPicker
            title="مدیریت درمان‌ها"
            tone="green"
            :open="headerOpen === 'treatment'"
            :options="treatmentOptions"
            v-model:new-value="newTreatment"
            @toggle="toggleHeader('treatment')"
            @close="headerOpen = null"
            @add="addTreatment"
            @remove="removeTreatment"
          />
        </div>
      </div>

      <div class="service-filter-bar" @click.stop>
        <SelectionPicker
          title="فیلتر ناحیه"
          :open="open.row === 'filters' && open.type === 'zone'"
          :options="zoneOptions"
          v-model="filters.zones"
          @toggle="openCell('filters', 'zone')"
          @close="closeAll"
        />
        <SelectionPicker
          title="فیلتر مشکل / درمان"
          tone="green"
          :open="open.row === 'filters' && open.type === 'treatment'"
          :options="treatmentOptions"
          v-model="filters.treatments"
          @toggle="openCell('filters', 'treatment')"
          @close="closeAll"
        />
        <button
          v-if="hasActiveFilters"
          class="clear-service-filters"
          type="button"
          @click="clearFilters"
        >
          پاک کردن فیلتر
        </button>
        <span class="service-filter-count">{{ filtered.length.toLocaleString('fa-IR') }} نتیجه</span>
      </div>

      <div class="service-table-scroll" @click.stop>
        <table>
          <thead>
            <tr>
              <th class="service-name-col">نام خدمت</th>
              <th class="select-col">دسته‌بندی</th>
              <th class="picker-col">نواحی</th>
              <th class="picker-col">درمان</th>
              <th class="select-col">نوع حساب</th>
              <th class="select-col">روش پرداخت</th>
              <th class="select-col">نوع</th>
              <th class="select-col">وضعیت</th>
              <th>قیمت فعلی</th><th>قیمت نهایی</th><th>USP</th><th>Cross</th><th>Upsell</th><th>FAB</th><th>مراقبت</th><th>بازار هدف</th><th class="row-action-col">عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in filtered" :key="product.id">
              <td class="service-name-cell"><input v-model.trim="product.name" placeholder="نام خدمت"></td>
              <td class="select-cell">
                <select v-model="product.category">
                  <option value="">انتخاب دسته‌بندی</option>
                  <option v-for="item in paymentOptions.service_categories" :key="`category-${item}`" :value="item">{{ item }}</option>
                </select>
              </td>
              <td class="picker-cell">
                <SelectionPicker
                  title="انتخاب نواحی"
                  :open="open.row === product.id && open.type === 'zone'"
                  :options="zoneOptions"
                  v-model="product.zones"
                  @toggle="openCell(product.id, 'zone')"
                  @close="closeAll"
                />
              </td>
              <td class="picker-cell">
                <SelectionPicker
                  title="انتخاب درمان"
                  tone="green"
                  :open="open.row === product.id && open.type === 'treatment'"
                  :options="treatmentOptions"
                  v-model="product.treatments"
                  @toggle="openCell(product.id, 'treatment')"
                  @close="closeAll"
                />
              </td>
              <td class="select-cell">
                <select v-model="product.account">
                  <option value="">انتخاب حساب</option>
                  <option v-for="item in paymentOptions.accounts" :key="`account-${item}`" :value="item">{{ item }}</option>
                </select>
              </td>
              <td class="select-cell">
                <select v-model="product.paymentMethod">
                  <option value="">انتخاب روش</option>
                  <option v-for="item in paymentOptions.methods" :key="`method-${item}`" :value="item">{{ item }}</option>
                </select>
              </td>
              <td class="select-cell">
                <select v-model="product.type">
                  <option value="">انتخاب نوع</option>
                  <option v-for="item in paymentOptions.service_types" :key="`type-${item}`" :value="item">{{ item }}</option>
                </select>
              </td>
              <td class="select-cell">
                <select v-model="product.status">
                  <option value="">انتخاب وضعیت</option>
                  <option v-for="item in paymentOptions.service_statuses" :key="`status-${item}`" :value="item">{{ item }}</option>
                </select>
              </td>
              <td class="price-cell"><MoneyInput :model-value="product.price" @update:model-value="product.price = $event" /></td>
              <td class="price-cell"><MoneyInput :model-value="product.finalPrice" @update:model-value="product.finalPrice = $event" /></td>
              <td><textarea v-model="product.usp" placeholder="مزیت منحصربه‌فرد"></textarea></td>
              <td><textarea v-model="product.cross" placeholder="فروش مکمل"></textarea></td>
              <td><textarea v-model="product.upsell" placeholder="پیشنهاد ارتقا"></textarea></td>
              <td><textarea v-model="product.fab" placeholder="ویژگی و مزیت"></textarea></td>
              <td><textarea v-model="product.care" placeholder="مراقبت‌های لازم"></textarea></td>
              <td><textarea v-model="product.target" placeholder="مخاطب هدف"></textarea></td>
              <td class="row-action-cell">
                <button
                  class="delete-service-row"
                  type="button"
                  title="حذف این ردیف"
                  :disabled="products.length <= 1"
                  @click="removeRow(product.id)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5"/>
                  </svg>
                  <span>حذف</span>
                </button>
              </td>
            </tr>
            <tr v-if="!filtered.length"><td colspan="17" class="empty-row">خدمتی با این فیلتر پیدا نشد.</td></tr>
          </tbody>
        </table>
      </div>

      <footer class="service-table-actions">
        <div><strong>افزودن خدمت جدید</strong><small>یک ردیف خالی به انتهای فهرست اضافه می‌شود.</small></div>
        <button class="add-row-btn" type="button" @click="addRow"><span>+</span> افزودن ردیف جدید</button>
      </footer>
    </section>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, Teleport } from 'vue'

const normalizeMoney = value => String(value || '').replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')

const MoneyInput = defineComponent({
  props: { modelValue: { type: String, default: '' } },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => h('label', { class: 'money-input' }, [
      h('input', {
        value: props.modelValue,
        inputmode: 'numeric',
        placeholder: '۰',
        onInput: event => emit('update:modelValue', normalizeMoney(event.target.value))
      })
    ])
  }
})

const optionItem = (option, selected, toggle, removable, remove) => h('label', {
  class: ['option-item', { selected: selected.includes(option) }]
}, [
  h('input', { type: 'checkbox', value: option, checked: selected.includes(option), onChange: () => toggle(option) }),
  h('span', { class: 'option-check' }, '✓'),
  h('b', option),
  removable ? h('button', { class: 'option-delete', type: 'button', title: 'حذف', onClick: event => { event.preventDefault(); event.stopPropagation(); remove(option) } }, '×') : null
])

const HeaderPicker = defineComponent({
  props: { title: String, tone: String, open: Boolean, options: Array, newValue: String },
  emits: ['toggle', 'close', 'add', 'remove', 'update:newValue'],
  setup(props, { emit }) {
    const query = ref('')
    const visibleOptions = () => props.options.filter(option => option.toLocaleLowerCase('fa-IR').includes(query.value.toLocaleLowerCase('fa-IR')))
    return () => h('div', { class: 'management-picker' }, [
      h('button', { class: ['management-menu-btn', props.tone], type: 'button', onClick: event => { event.stopPropagation(); emit('toggle') } }, [
        h('span', props.tone === 'green' ? '✦' : '⌖'),
        props.title
      ]),
      props.open ? h(Teleport, { to: 'body' }, [
        h('div', { class: 'picker-modal-layer', onClick: () => emit('close') }, [
          h('div', { class: 'picker-panel header-panel', onClick: event => event.stopPropagation() }, [
          h('header', [h('div', [h('strong', props.title), h('small', 'افزودن، جست‌وجو و حذف گزینه‌ها')]), h('button', { onClick: () => emit('close') }, '×')]),
          h('label', { class: 'picker-search' }, [
            h('span', '⌕'),
            h('input', { value: query.value, placeholder: `جست‌وجو در ${props.title}...`, onInput: event => { query.value = event.target.value } }),
            query.value ? h('button', { type: 'button', onClick: () => { query.value = '' } }, '×') : null
          ]),
          h('div', { class: 'option-list management-option-list' }, visibleOptions().map(option => h('div', { class: 'management-option' }, [
            h('b', option),
            h('button', { type: 'button', title: 'حذف', onClick: () => emit('remove', option) }, '×')
          ]))),
          !visibleOptions().length ? h('div', { class: 'picker-empty' }, 'موردی پیدا نشد.') : null,
          h('div', { class: 'option-add' }, [
            h('input', { value: props.newValue, placeholder: `${props.title} جدید`, onInput: event => emit('update:newValue', event.target.value), onKeyup: event => { if (event.key === 'Enter') emit('add') } }),
            h('button', { type: 'button', onClick: () => emit('add') }, 'افزودن')
          ])
        ])
        ])
      ]) : null
    ])
  }
})

const SelectionPicker = defineComponent({
  props: { title: String, tone: String, open: Boolean, options: Array, modelValue: Array },
  emits: ['toggle', 'close', 'update:modelValue'],
  setup(props, { emit }) {
    const query = ref('')
    const toggle = option => emit('update:modelValue', props.modelValue.includes(option) ? props.modelValue.filter(item => item !== option) : [...props.modelValue, option])
    const visibleOptions = () => props.options.filter(option => option.toLocaleLowerCase('fa-IR').includes(query.value.toLocaleLowerCase('fa-IR')))
    return () => h('div', { class: 'selection-wrap' }, [
      h('button', { class: 'picker-trigger', type: 'button', onClick: event => { event.stopPropagation(); emit('toggle') } }, [
        !props.modelValue.length ? h('span', { class: 'picker-placeholder' }, props.title) : props.modelValue.slice(0, 2).map(item => h('span', { class: ['selection-chip', props.tone] }, item)),
        props.modelValue.length > 2 ? h('b', `+${(props.modelValue.length - 2).toLocaleString('fa-IR')}`) : null,
        h('i', '⌄')
      ]),
      props.open ? h(Teleport, { to: 'body' }, [
        h('div', { class: 'picker-modal-layer', onClick: () => emit('close') }, [
          h('div', { class: 'picker-panel cell-panel', onClick: event => event.stopPropagation() }, [
          h('header', [h('div', [h('strong', props.title), h('small', `${props.modelValue.length.toLocaleString('fa-IR')} مورد انتخاب شده`)]), h('button', { onClick: () => emit('close') }, '×')]),
          h('label', { class: 'picker-search' }, [
            h('span', '⌕'),
            h('input', { value: query.value, placeholder: `جست‌وجو در ${props.title}...`, onInput: event => { query.value = event.target.value } }),
            query.value ? h('button', { type: 'button', onClick: () => { query.value = '' } }, '×') : null
          ]),
          h('div', { class: 'option-list' }, visibleOptions().map(option => optionItem(option, props.modelValue, toggle, false))),
          !visibleOptions().length ? h('div', { class: 'picker-empty' }, 'موردی پیدا نشد.') : null
          ])
        ])
      ]) : null
    ])
  }
})

const zoneOptions = ref(['لب','بینی','زاویه فک','چانه','گونه','پیشانی','خط اخم','غبغب','مو','شقیقه','خط لبخند','اطراف چشم','اطراف لب','ماریونت','ابرو','کل صورت','بدن','گوش'])
const treatmentOptions = ref(['تیرگی','لک','منافذ باز','چروک','افتادگی','جوش','جای جوش','رشد مو','فرم‌دهی','فرم چانه','عدم تقارن','حجم‌دهی','زاویه‌سازی','شلی','پوست خشک','پوست چرب','چربی‌سوزی','رفع تعریق'])
let nextId = 1
const paymentOptions = reactive({
  methods: ['کارتخوان', 'کارت به کارت', 'شبا'],
  accounts: ['حساب اصلی'],
  service_categories: ['زیبایی', 'درمانی', 'لیزر', 'پوست و مو'],
  service_types: ['خدمت اصلی', 'خدمت جانبی', 'مشاوره'],
  service_statuses: ['فعال', 'غیرفعال', 'نیازمند بررسی']
})
const makeProduct = () => ({
  id: nextId++,
  name:'',
  category:'',
  zones:[],
  treatments:[],
  account:'',
  paymentMethod:'',
  type:'',
  status:'',
  price:'',
  finalPrice:'',
  usp:'',
  cross:'',
  upsell:'',
  fab:'',
  care:'',
  target:''
})
const products = reactive([makeProduct()])
const headerOpen = ref(null)
const open = reactive({ row:null, type:null })
const newZone = ref('')
const newTreatment = ref('')
const searchQuery = ref('')
const filters = reactive({ zones: [], treatments: [] })

const toggleHeader = type => { headerOpen.value = headerOpen.value === type ? null : type; open.row = null }
const openCell = (id, type) => { headerOpen.value = null; open.row = open.row === id && open.type === type ? null : id; open.type = type }
const closeAll = () => { headerOpen.value = null; open.row = null; open.type = null }
const addZone = () => { const value = newZone.value.trim(); if (value && !zoneOptions.value.includes(value)) zoneOptions.value.push(value); newZone.value = '' }
const addTreatment = () => { const value = newTreatment.value.trim(); if (value && !treatmentOptions.value.includes(value)) treatmentOptions.value.push(value); newTreatment.value = '' }
const removeZone = value => { zoneOptions.value = zoneOptions.value.filter(item => item !== value); products.forEach(product => { product.zones = product.zones.filter(item => item !== value) }) }
const removeTreatment = value => { treatmentOptions.value = treatmentOptions.value.filter(item => item !== value); products.forEach(product => { product.treatments = product.treatments.filter(item => item !== value) }) }
const addRow = () => { products.push(makeProduct()); closeAll() }
const removeRow = id => {
  if (products.length <= 1) return
  const index = products.findIndex(product => product.id === id)
  if (index === -1) return
  products.splice(index, 1)
  closeAll()
}
const normalizeSearch = value => String(value || '')
  .toLocaleLowerCase('fa-IR')
  .replace(/[يى]/g, 'ی')
  .replace(/ك/g, 'ک')
  .replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
  .replace(/[٠-٩]/g, digit => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))
const hasActiveFilters = computed(() => Boolean(searchQuery.value || filters.zones.length || filters.treatments.length))
const clearFilters = () => {
  searchQuery.value = ''
  filters.zones = []
  filters.treatments = []
  closeAll()
}
const fetchPaymentOptions = async () => {
  try {
    const response = await fetch('/api/payment-options')
    const data = await response.json()
    if (!response.ok) throw new Error()
    ;['methods', 'accounts', 'service_categories', 'service_types', 'service_statuses'].forEach(key => {
      if (Array.isArray(data[key]) && data[key].length) paymentOptions[key] = data[key]
    })
  } catch {
    // پیش‌فرض‌ها کافی هستند؛ مدیریت اصلی در تنظیمات پرداخت انجام می‌شود.
  }
}
const filtered = computed(() => {
  const terms = normalizeSearch(searchQuery.value).split(/\s+/).filter(Boolean)
  return products.filter(product => {
    const zoneOk = !filters.zones.length || filters.zones.some(zone => product.zones.includes(zone))
    const treatmentOk = !filters.treatments.length || filters.treatments.some(treatment => product.treatments.includes(treatment))
    if (!zoneOk || !treatmentOk) return false

    if (!terms.length) return true
    const searchable = normalizeSearch([
      product.name,
      product.category,
      product.zones.join(' '),
      product.treatments.join(' '),
      product.account,
      product.paymentMethod,
      product.type,
      product.status,
      product.usp,
      product.cross,
      product.upsell,
      product.fab,
      product.care,
      product.target
    ].join(' '))
    return terms.every(term => searchable.includes(term))
  })
})
onMounted(fetchPaymentOptions)
</script>

<style>
.service-finder-page{min-height:100vh;padding:28px;box-sizing:border-box;background:linear-gradient(180deg,#fff 0,#f8fafc 100%);font-family:"Vazir",Tahoma,sans-serif;color:#172033}.service-finder-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:20px;padding:24px 26px;border:1px solid #e2e8f0;border-radius:24px;background:#fff;box-shadow:0 12px 34px rgba(15,23,42,.055)}.eyebrow{color:#2563eb;font-size:11px;font-weight:900}.service-finder-hero h1{margin:5px 0;font-size:25px}.service-finder-hero p{margin:0;color:#64748b;font-size:12px}.service-stats{display:flex;gap:9px}.service-stats span{min-width:76px;padding:11px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;color:#64748b;text-align:center;font-size:10px}.service-stats b{display:block;margin-bottom:3px;color:#1d4ed8;font-size:17px}
.service-table-card{border:1px solid #e2e8f0;border-radius:24px;background:#fff;box-shadow:0 14px 38px rgba(15,23,42,.06)}.table-card-head{display:flex;align-items:center;gap:16px;padding:17px 22px;border-bottom:1px solid #e8eef6}.table-card-head>div:first-child{margin-left:auto}.table-card-head h2{margin:0;font-size:17px}.active-filter{padding:7px 10px;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:900}.active-filter button{margin-right:7px;border:0;background:transparent;color:#dc2626;font-family:inherit;cursor:pointer}.instant-search{width:min(390px,42vw);height:43px;display:grid;grid-template-columns:24px minmax(0,1fr) 28px;align-items:center;gap:7px;padding:0 10px;border:1px solid #dbe3ed;border-radius:13px;background:#f8fafc;transition:.2s}.instant-search:focus-within{border-color:#60a5fa;background:#fff;box-shadow:0 0 0 4px rgba(96,165,250,.12)}.instant-search svg{width:19px;height:19px;fill:none;stroke:#64748b;stroke-width:1.8;stroke-linecap:round}.instant-search input{width:100%;height:40px;border:0;outline:0;background:transparent;color:#1e293b;font-family:inherit;font-size:11px}.instant-search input::-webkit-search-cancel-button{display:none}.instant-search button{width:26px;height:26px;border:0;border-radius:8px;background:#e2e8f0;color:#64748b;font-size:17px;cursor:pointer}
.service-problem-search{width:min(430px,38vw);flex:0 1 430px}.service-filter-bar{display:grid;grid-template-columns:minmax(190px,250px) minmax(190px,250px) auto 1fr;align-items:center;gap:9px;padding:12px 22px;border-bottom:1px solid #edf2f7;background:#fbfdff}.clear-service-filters{height:38px;padding:0 12px;border:1px solid #fecaca;border-radius:10px;background:#fff1f2;color:#dc2626;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}.service-filter-count{justify-self:end;padding:6px 10px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:10px;font-weight:900}
.management-menus{display:flex;align-items:center;gap:8px}.management-menu-btn{height:39px;display:flex;align-items:center;gap:7px;padding:0 13px;border:1px solid #bfdbfe;border-radius:11px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer;transition:.18s}.management-menu-btn:hover{border-color:#60a5fa;background:#dbeafe;transform:translateY(-1px)}.management-menu-btn>span{font-size:15px}.management-menu-btn.green{border-color:#bbf7d0;background:#f0fdf4;color:#15803d}.management-menu-btn.green:hover{border-color:#4ade80;background:#dcfce7}.management-option-list{grid-template-columns:1fr 1fr}.management-option{min-width:0;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px;border:1px solid #e5eaf0;border-radius:11px;background:#fff;color:#475569}.management-option b{overflow:hidden;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.management-option button{width:25px;height:25px;flex:0 0 25px;border:0;border-radius:7px;background:#fee2e2;color:#dc2626;font-size:16px;cursor:pointer}.management-option button:hover{background:#dc2626;color:#fff}
.service-table-scroll{overflow-x:auto;overflow-y:visible}.service-table-scroll table{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0}.service-table-scroll th{min-width:145px;padding:13px 11px;border-bottom:1px solid #dfe7f1;background:#f8fafc;color:#475569;font-size:11px;white-space:nowrap}.service-table-scroll td{position:relative;min-width:145px;padding:10px;border-bottom:1px solid #edf2f7;background:#fff;vertical-align:top}.service-table-scroll tbody tr:hover td{background:#fbfdff}.service-name-col,.service-name-cell{min-width:190px!important}.picker-col,.picker-cell{min-width:245px!important}.select-col,.select-cell{min-width:145px!important}.service-table-scroll input,.service-table-scroll textarea,.service-table-scroll select{width:100%;box-sizing:border-box;border:1px solid #dce4ee;border-radius:11px;background:#f8fafc;color:#1e293b;font-family:inherit;font-size:11px;outline:0}.service-table-scroll input,.service-table-scroll select{height:43px;padding:0 11px}.service-table-scroll textarea{height:70px;padding:10px;resize:vertical}.service-table-scroll input:focus,.service-table-scroll textarea:focus,.service-table-scroll select:focus{border-color:#60a5fa;background:#fff;box-shadow:0 0 0 3px rgba(96,165,250,.12)}.empty-row{padding:45px!important;color:#94a3b8;text-align:center}
.th-wrap{position:relative;display:flex;align-items:center;justify-content:space-between;gap:9px}.filter-btn{display:flex;align-items:center;gap:5px;padding:6px 8px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:9px;font-weight:900;cursor:pointer}.filter-btn b{min-width:18px;height:18px;display:grid;place-items:center;border-radius:999px;background:#2563eb;color:#fff}.selection-wrap{position:relative}.picker-trigger{width:100%;min-height:44px;display:flex;align-items:center;gap:5px;padding:6px 9px;border:1px solid #dce4ee;border-radius:11px;background:#f8fafc;font-family:inherit;cursor:pointer}.picker-trigger i{margin-right:auto;color:#64748b;font-style:normal}.picker-trigger>b{padding:3px 6px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:9px}.picker-placeholder{color:#94a3b8;font-size:10px}.selection-chip{max-width:75px;overflow:hidden;padding:4px 7px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:9px;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.selection-chip.green{background:#dcfce7;color:#15803d}
.picker-modal-layer{position:fixed;inset:0;z-index:1499;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.48);backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);animation:picker-layer-in .18s ease}.picker-panel{position:relative;z-index:1500;width:min(430px,calc(100vw - 36px));max-height:calc(100vh - 36px);padding:15px;border:1px solid rgba(255,255,255,.75);border-radius:20px;background:#fff!important;box-shadow:0 30px 100px rgba(15,23,42,.38);direction:rtl;text-align:right;animation:picker-panel-in .2s ease}.picker-panel header{display:flex;align-items:center;justify-content:space-between;padding:2px 3px 12px;border-bottom:1px solid #edf2f7}.picker-panel header div{display:grid;gap:3px}.picker-panel header strong{color:#1e293b;font-size:14px}.picker-panel header small{color:#94a3b8;font-size:10px}.picker-panel header>button{width:34px;height:34px;border:0;border-radius:10px;background:#f1f5f9;color:#64748b;font-size:21px;cursor:pointer}.picker-search{height:36px;display:grid;grid-template-columns:20px minmax(0,1fr) 24px;align-items:center;gap:5px;margin-top:10px;padding:0 9px;border:1px solid #dbe3ed;border-radius:10px;background:#f8fafc}.picker-search:focus-within{border-color:#60a5fa;background:#fff;box-shadow:0 0 0 3px rgba(96,165,250,.12)}.picker-search>span{color:#64748b;font-size:18px}.picker-search input{width:100%;height:34px;border:0!important;outline:0;background:transparent!important;box-shadow:none!important;font-family:inherit;font-size:10px}.picker-search button{width:22px;height:22px;border:0;border-radius:6px;background:#e2e8f0;color:#64748b;cursor:pointer}.option-list{display:grid;grid-template-columns:1fr 1fr;gap:8px;max-height:min(400px,calc(100vh - 245px));overflow-y:auto;overscroll-behavior:contain;padding:12px 3px;scrollbar-width:thin;background:#fff}.option-item{min-width:0;display:grid;grid-template-columns:25px minmax(0,1fr) auto;align-items:center;gap:8px;padding:10px;border:1px solid #e5eaf0;border-radius:11px;background:#fff;color:#475569;cursor:pointer}.option-item input{position:absolute;opacity:0;pointer-events:none}.option-check{width:24px;height:24px;display:grid;place-items:center;border:2px solid #cbd5e1;border-radius:8px;color:transparent;font-size:10px}.option-item b{overflow:hidden;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.option-item.selected{border-color:#60a5fa;background:#eff6ff;color:#1d4ed8}.option-item.selected .option-check{border-color:#2563eb;background:#2563eb;color:#fff}.option-delete{width:24px;height:24px;border:0;border-radius:7px;background:#fee2e2;color:#dc2626;cursor:pointer}.picker-empty{padding:25px;color:#94a3b8;text-align:center;font-size:11px}.option-add{display:grid;grid-template-columns:1fr auto;gap:8px;padding-top:12px;border-top:1px solid #edf2f7;background:#fff}.option-add input{height:40px!important}.option-add button{padding:0 14px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}@keyframes picker-layer-in{from{opacity:0}to{opacity:1}}@keyframes picker-panel-in{from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:none}}
.money-input{display:flex;align-items:center;overflow:hidden;border:1px solid #dce4ee;border-radius:11px;background:#f8fafc}.money-input input{min-width:105px;border:0!important;box-shadow:none!important;background:transparent!important}.money-input small{padding:0 8px;color:#64748b;font-size:9px;white-space:nowrap}.price-cell{min-width:180px!important}
.row-action-col,.row-action-cell{min-width:92px!important;text-align:center}.delete-service-row{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:36px;padding:0 10px;border:1px solid #fecaca;border-radius:10px;background:#fff1f2;color:#dc2626;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer;transition:.18s}.delete-service-row svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.delete-service-row:hover:not(:disabled){border-color:#f87171;background:#dc2626;color:#fff;box-shadow:0 6px 14px rgba(220,38,38,.2)}.delete-service-row:disabled{cursor:not-allowed;opacity:.35}
.service-table-actions{display:flex;align-items:center;gap:10px;position:relative;z-index:5;padding:16px 20px;border-top:1px solid #e8eef6;background:#fff;border-radius:0 0 24px 24px}.service-table-actions>div{display:grid;margin-left:auto}.service-table-actions strong{font-size:12px}.service-table-actions small{margin-top:3px;color:#94a3b8;font-size:9px}.service-table-actions button{height:40px;padding:0 14px;border-radius:11px;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}.add-row-btn{display:flex;align-items:center;gap:7px;border:0;background:#2563eb;color:#fff;box-shadow:0 8px 18px rgba(37,99,235,.2)}.add-row-btn span{font-size:19px}
@media(max-width:800px){.service-finder-page{padding:14px}.service-finder-hero{align-items:flex-start;flex-direction:column;padding:19px}.service-stats{width:100%}.service-stats span{flex:1}.service-table-actions{align-items:stretch;flex-direction:column}.service-table-actions>div{margin:0 0 5px;width:100%}.service-table-actions button{width:100%}.picker-modal-layer{align-items:end;padding:0}.picker-panel{width:100%;max-height:86vh;border-radius:22px 22px 0 0}.option-list{grid-template-columns:1fr;max-height:55vh}.table-card-head{align-items:stretch;flex-direction:column;gap:10px}.table-card-head>div:first-child{margin-left:0}.management-menus{display:grid;grid-template-columns:1fr 1fr}.management-menu-btn{width:100%;justify-content:center}.service-problem-search{width:100%;flex:auto}.service-filter-bar{grid-template-columns:1fr;padding:12px}.service-filter-count{justify-self:stretch;text-align:center}}
</style>
