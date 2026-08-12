<template>
  <section class="photos-page" dir="rtl">
    <header class="photos-title">
      <div><h1>عکس‌ها</h1><p>جست‌وجو و مقایسه یک‌نگاه تصاویر قبل و بعد بیماران</p></div>
      <span>{{ comparisons.length }} نتیجه</span>
    </header>

    <div class="advanced-search">
      <div class="photos-filter-row photos-filter-primary">
        <div class="search-main"><span>⌕</span><input v-model.trim="filters.q" @keyup.enter="load" placeholder="نام، پرونده یا موبایل"></div>
        <div class="tag-search-filter">
          <span>⌕</span>
          <input v-model.trim="filters.tag" list="photo-service-tags" placeholder="جست‌وجوی تگ" @keyup.enter="load">
          <button v-if="filters.tag" type="button" title="پاک کردن تگ" @click="filters.tag = ''">×</button>
          <datalist id="photo-service-tags">
            <option v-for="tag in tags" :key="tag" :value="tag"></option>
          </datalist>
        </div>
        <select v-model="filters.gender"><option value="">جنسیت</option><option value="female">زن</option><option value="male">مرد</option></select>
        <select v-model="filters.age_group"><option value="">سن</option><option value="young">جوان</option><option value="old">پیر</option></select>
        <select v-model="filters.angle"><option value="">زاویه</option><option v-for="item in angles" :key="item.key" :value="item.key">{{ item.label }}</option></select>
        <select v-model="filters.featured"><option value="">نوع تصویر</option><option value="featured">★ برترین‌ها</option><option value="regular">معمولی</option></select>
      </div>

      <div class="photos-filter-row photos-filter-secondary">
        <div class="photos-date-field">
          <date-picker v-model="filters.date_from" format="jYYYY-jMM-jDD" display-format="jYYYY/jMM/jDD" placeholder="از تاریخ" auto-submit color="#2563eb" input-class="photos-date-input" />
        </div>
        <div class="photos-date-field">
          <date-picker v-model="filters.date_to" format="jYYYY-jMM-jDD" display-format="jYYYY/jMM/jDD" placeholder="تا تاریخ" auto-submit color="#2563eb" input-class="photos-date-input" />
        </div>
        <label class="complete-filter"><input v-model="filters.only_complete" type="checkbox"> جفت کامل</label>
        <label class="complete-filter consent-filter"><input v-model="filters.consented_only" type="checkbox"> فقط دارای رضایت</label>
        <div class="photos-filter-actions">
          <button class="search-btn photo-icon-action" type="button" :disabled="loading" title="جست‌وجو" aria-label="جست‌وجو" @click="load">
            <i v-if="loading" class="photo-action-spinner"></i>
            <svg v-else viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
          </button>
          <button class="clear-btn photo-icon-action" type="button" title="پاک‌کردن فیلترها" aria-label="پاک‌کردن فیلترها" @click="clearFilters">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 15 13.5 5.5a2.1 2.1 0 0 1 3 0l2 2a2.1 2.1 0 0 1 0 3L9 20H4z"/><path d="m11 8 5 5M9 20h11"/></svg>
          </button>
        </div>
      </div>
    </div>

    <div class="angle-filter-row">
      <button :class="{ active: !filters.angle }" @click="filters.angle = ''; load()">زاویه‌ها</button>
      <button v-for="item in angles" :key="`chip-${item.key}`" :class="{ active: filters.angle === item.key }" @click="filters.angle = item.key; load()">{{ item.label }}</button>
    </div>

    <div v-if="error" class="photos-error">{{ error }}</div>
    <div v-if="loading" class="photos-loading"><i></i><span>در حال دریافت تصاویر...</span></div>

    <div v-else-if="comparisons.length" class="comparison-layout">
      <aside class="comparison-results">
        <div v-if="featuredComparisons.length" class="result-section-title featured-title"><span>★</span> برترین‌ها <b>{{ featuredComparisons.length }}</b></div>
        <button v-for="item in featuredComparisons" :key="`featured-${item.key}`" :class="{ active: selected?.key === item.key, featured: true }" @click="selected = item">
          <div class="result-thumbs" :class="{ sensitive: hasSensitivePhoto(item) }">
            <img v-if="item.before" :class="{ sensitive: item.before.usage_consent === false }" :src="item.before.url" alt="قبل"><span v-else>قبل</span>
            <img v-if="item.after" :class="{ sensitive: item.after.usage_consent === false }" :src="item.after.url" alt="بعد"><span v-else>بعد</span>
            <b v-if="hasSensitivePhoto(item)" class="sensitive-photo-badge">عکس حساس</b>
          </div>
          <strong>{{ patientName(item.patient) }} <em v-if="item.before?.usage_consent === false || item.after?.usage_consent === false" class="consent-warning">عدم رضایت استفاده از تصاویر</em></strong>
          <small>{{ item.angle_label }} · {{ item.date || 'بدون تاریخ' }}</small>
          <div class="tiny-tags"><em v-for="tag in item.tags" :key="tag.id">{{ tag.name }}</em></div>
        </button>
        <div v-if="regularComparisons.length" class="result-section-title">سایر تصاویر <b>{{ regularComparisons.length }}</b></div>
        <button v-for="item in regularComparisons" :key="item.key" :class="{ active: selected?.key === item.key }" @click="selected = item">
          <div class="result-thumbs" :class="{ sensitive: hasSensitivePhoto(item) }"><img v-if="item.before" :class="{ sensitive: item.before.usage_consent === false }" :src="item.before.url" alt="قبل"><span v-else>قبل</span><img v-if="item.after" :class="{ sensitive: item.after.usage_consent === false }" :src="item.after.url" alt="بعد"><span v-else>بعد</span><b v-if="hasSensitivePhoto(item)" class="sensitive-photo-badge">عکس حساس</b></div>
          <strong>{{ patientName(item.patient) }} <em v-if="item.before?.usage_consent === false || item.after?.usage_consent === false" class="consent-warning">عدم رضایت استفاده از تصاویر</em></strong><small>{{ item.angle_label }} · {{ item.date || 'بدون تاریخ' }}</small>
          <div class="tiny-tags"><em v-for="tag in item.tags" :key="tag.id">{{ tag.name }}</em></div>
        </button>
      </aside>

      <main v-if="selected" class="comparison-viewer">
        <div class="viewer-meta">
          <div><h2>{{ selected.is_featured ? '★ ' : '' }}{{ patientName(selected.patient) }}</h2><p>پرونده {{ selected.patient?.file_number || '-' }} · {{ displayPatientPhone(selected.patient?.phone) || '-' }}</p><em v-if="selected.before?.usage_consent === false || selected.after?.usage_consent === false" class="consent-warning large">عدم رضایت استفاده از تصاویر</em></div>
          <div class="viewer-facts"><span>{{ selected.angle_label }}</span><span>{{ selected.date || 'بدون تاریخ' }}</span></div>
        </div>
        <div v-if="selectedPatientAngles.length > 1" class="angle-view-nav">
          <button type="button" @click="selectAdjacentAngle(-1)">زاویه قبلی</button>
          <div>
            <button
              v-for="item in selectedPatientAngles"
              :key="`angle-nav-${item.key}`"
              type="button"
              :class="{ active: item.key === selected.key }"
              @click="selected = item"
            >
              {{ item.angle_label }}
            </button>
          </div>
          <button type="button" @click="selectAdjacentAngle(1)">زاویه بعدی</button>
        </div>
        <div class="viewer-tags"><span v-for="tag in selected.tags" :key="tag.id">{{ tag.name }}</span></div>
        <div class="before-after-grid">
          <article :class="{ 'no-consent-photo': selected.before?.usage_consent === false }"><header><b>قبل</b><small>{{ selected.angle_label }}</small></header><img v-if="selected.before" :src="selected.before.url" alt="عکس قبل"><div v-else class="missing-photo">عکس قبل ثبت نشده</div></article>
          <article :class="{ 'no-consent-photo': selected.after?.usage_consent === false }"><header><b>بعد</b><small>{{ selected.angle_label }}</small></header><img v-if="selected.after" :src="selected.after.url" alt="عکس بعد"><div v-else class="missing-photo">عکس بعد ثبت نشده</div></article>
        </div>
      </main>
    </div>
    <div v-else class="photos-empty">تصویری مطابق فیلترها پیدا نشد.</div>
  </section>
</template>

<script>
import DatePicker from 'vue3-persian-datetime-picker'

const ANGLES = [
  { key: 'left_profile', label: 'نیم‌رخ چپ' }, { key: 'left_three_quarter_60', label: 'سه‌رخ اول چپ' },
  { key: 'left_three_quarter_30', label: 'سه‌رخ دوم چپ' }, { key: 'front', label: 'تمام‌رخ' },
  { key: 'right_three_quarter_30', label: 'سه‌رخ اول راست' }, { key: 'right_three_quarter_60', label: 'سه‌رخ دوم راست' },
  { key: 'right_profile', label: 'نیم‌رخ راست' }
]

export default {
  name: 'Photos',
  components: { DatePicker },
  props: {
    permissions: { type: Array, default: () => [] }
  },
  data: () => ({
    angles: ANGLES, services: [], tags: [], comparisons: [], selected: null, loading: false, error: '',
    filters: { q: '', tag: '', gender: '', age_group: '', angle: '', featured: '', date_from: '', date_to: '', only_complete: false, consented_only: false }
  }),
  computed: {
    canViewPatientPhone() { return this.permissions.includes('patients.view_phone') },
    featuredComparisons() { return this.comparisons.filter(item => item.is_featured) },
    regularComparisons() { return this.comparisons.filter(item => !item.is_featured) },
    selectedPatientAngles() {
      if (!this.selected) return []
      const patientId = this.selected.patient?.id
      const patientFile = this.selected.patient?.file_number
      const selectedDate = this.selected.date || ''
      const selectedServiceId = this.selected.service?.id || ''
      const samePatient = this.comparisons.filter(item => {
        const patientMatches = patientId
          ? item.patient?.id === patientId
          : patientFile && item.patient?.file_number === patientFile
        const dateMatches = (item.date || '') === selectedDate
        const serviceMatches = (item.service?.id || '') === selectedServiceId
        return patientMatches && dateMatches && serviceMatches
      })
      const angleOrder = new Map(this.angles.map((angle, index) => [angle.key, index]))
      return samePatient.sort((a, b) => {
        const aOrder = angleOrder.has(a.angle_key) ? angleOrder.get(a.angle_key) : 999
        const bOrder = angleOrder.has(b.angle_key) ? angleOrder.get(b.angle_key) : 999
        return aOrder - bOrder
      })
    }
  },
  mounted() { this.load() },
  methods: {
    displayPatientPhone(value) {
      const text = String(value || '').trim()
      if (!text) return ''
      if (this.canViewPatientPhone || text.includes('•') || text.includes('*')) return text
      const digits = text.replace(/\D/g, '')
      if (digits.length <= 4) return '••••'
      return `${digits.slice(0, 3)}••••${digits.slice(-2)}`
    },
    patientName(patient) { return [patient?.first_name, patient?.last_name].filter(Boolean).join(' ') || 'بیمار نامشخص' },
    hasSensitivePhoto(item) { return item?.before?.usage_consent === false || item?.after?.usage_consent === false },
    selectAdjacentAngle(step) {
      const list = this.selectedPatientAngles
      if (!list.length) return
      const currentIndex = Math.max(0, list.findIndex(item => item.key === this.selected?.key))
      const nextIndex = (currentIndex + step + list.length) % list.length
      this.selected = list[nextIndex]
    },
    async load() {
      this.loading = true; this.error = ''
      try {
        const params = new URLSearchParams()
        Object.entries(this.filters).forEach(([key, value]) => { if (value !== '' && value !== false) params.set(key, value === true ? '1' : value) })
        const res = await fetch(`/api/photo-comparisons?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت تصاویر انجام نشد')
        this.services = data.services || []
        this.tags = data.tags || []
        this.comparisons = data.comparisons || []
        this.selected = this.comparisons.find(item => item.key === this.selected?.key) || this.comparisons[0] || null
      } catch (error) { this.error = error.message || 'دریافت تصاویر انجام نشد' }
      finally { this.loading = false }
    },
    clearFilters() {
      this.filters = { q: '', tag: '', gender: '', age_group: '', angle: '', featured: '', date_from: '', date_to: '', only_complete: false, consented_only: false }
      this.load()
    }
  }
}
</script>

<style scoped>
.consent-warning{display:inline-block;margin:3px;padding:3px 6px;border-radius:7px;background:#991b1b;color:#fff;font-size:8px;font-style:normal;font-weight:900}.consent-warning.large{padding:6px 9px;font-size:10px}
.tag-search-filter{position:relative;min-width:0}.tag-search-filter>span{position:absolute;right:11px;top:8px;z-index:1;color:#2563eb;font-size:19px}.tag-search-filter input{width:100%;padding-right:34px!important;padding-left:30px!important;box-sizing:border-box}.tag-search-filter button{position:absolute;left:6px;top:7px;width:27px;height:27px;border:0;border-radius:50%;background:#fee2e2;color:#dc2626;cursor:pointer;font-size:17px;line-height:1}
.consent-filter{border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;color:#047857!important}.consent-filter input{accent-color:#059669}
.before-after-grid article.no-consent-photo{border:3px solid #ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.12)}
.photos-page{min-height:calc(100vh - 130px);padding:24px;background:#f5f7fb;color:#0f172a;font-family:inherit}.photos-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.photos-title h1{margin:0 0 5px;font-size:25px}.photos-title p{margin:0;color:#64748b;font-size:13px}.photos-title>span{padding:8px 13px;border-radius:20px;background:#e0e7ff;color:#3730a3;font-size:12px;font-weight:900}.advanced-search{display:grid;grid-template-columns:2fr repeat(3,minmax(130px,1fr)) repeat(2,minmax(145px,.8fr));gap:9px;padding:15px;border:1px solid #e2e8f0;border-radius:17px;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.05)}.advanced-search input,.advanced-search select{height:41px;min-width:0;border:1px solid #dbe3ec;border-radius:10px;padding:0 10px;background:#fff;font-family:inherit;outline:none}.search-main{position:relative}.search-main input{width:100%;padding-right:34px}.search-main span{position:absolute;right:12px;top:8px;color:#64748b;font-size:20px}.complete-filter{display:flex;align-items:center;gap:7px;padding:0 8px;color:#475569;font-size:12px;font-weight:800}.complete-filter input{height:auto}.search-btn,.clear-btn{height:40px;border:0;border-radius:10px;font-family:inherit;font-weight:900;cursor:pointer}.search-btn{background:#2563eb;color:#fff}.clear-btn{background:#f1f5f9;color:#475569}.photo-icon-action{width:42px;min-width:42px;padding:0;display:grid;place-items:center}.photo-icon-action svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.photo-action-spinner{width:18px;height:18px;border:3px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}.angle-filter-row{display:flex;gap:7px;margin:13px 0;overflow-x:auto;padding-bottom:3px}.angle-filter-row button{flex:none;padding:8px 12px;border:1px solid #dbe3ec;border-radius:20px;background:#fff;color:#64748b;font-family:inherit;font-size:11px;font-weight:800;cursor:pointer}.angle-filter-row button.active{border-color:#2563eb;background:#eff6ff;color:#1d4ed8}.comparison-layout{display:grid;grid-template-columns:270px minmax(0,1fr);gap:14px;min-height:590px}.comparison-results{display:flex;flex-direction:column;gap:8px;max-height:calc(100vh - 285px);overflow-y:auto}.comparison-results>button{padding:10px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;text-align:right;font-family:inherit;cursor:pointer}.comparison-results>button.active{border-color:#2563eb;box-shadow:0 0 0 2px #dbeafe}.result-thumbs{display:grid;grid-template-columns:1fr 1fr;height:82px;gap:4px;margin-bottom:8px;overflow:hidden;border-radius:9px;background:#f1f5f9}.result-thumbs img{width:100%;height:100%;object-fit:cover}.result-thumbs span{display:grid;place-items:center;color:#94a3b8;font-size:11px}.comparison-results strong{display:block;font-size:12px}.comparison-results small{display:block;margin-top:3px;color:#64748b;font-size:10px}.tiny-tags{display:flex;gap:4px;flex-wrap:wrap;margin-top:6px}.tiny-tags em{padding:3px 6px;border-radius:10px;background:#ecfdf5;color:#047857;font-size:9px;font-style:normal;font-weight:800}.comparison-viewer{padding:18px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 12px 35px rgba(15,23,42,.06)}.viewer-meta{display:flex;align-items:center;justify-content:space-between;gap:12px}.viewer-meta h2{margin:0 0 4px;font-size:19px}.viewer-meta p{margin:0;color:#64748b;font-size:11px}.viewer-facts{display:flex;gap:7px}.viewer-facts span,.viewer-tags span{padding:6px 10px;border-radius:15px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:900}.viewer-tags{display:flex;gap:6px;flex-wrap:wrap;margin:12px 0}.viewer-tags span{background:#ecfdf5;color:#047857}.before-after-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;height:490px}.before-after-grid article{display:flex;flex-direction:column;min-width:0;border:1px solid #e2e8f0;border-radius:15px;overflow:hidden;background:#0f172a}.before-after-grid header{display:flex;align-items:center;justify-content:space-between;padding:10px 13px;background:#fff}.before-after-grid header b{font-size:14px}.before-after-grid header small{color:#64748b}.before-after-grid img{width:100%;height:100%;min-height:0;object-fit:contain}.missing-photo{flex:1;display:grid;place-items:center;color:#94a3b8}.photos-loading,.photos-empty,.photos-error{display:flex;align-items:center;justify-content:center;gap:10px;min-height:250px;border:1px dashed #cbd5e1;border-radius:16px;background:#fff;color:#64748b;font-weight:800}.photos-loading i{width:30px;height:30px;border:4px solid #dbeafe;border-top-color:#2563eb;border-radius:50%;animation:spin .8s linear infinite}.photos-error{color:#b91c1c;background:#fef2f2}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1100px){.advanced-search{grid-template-columns:repeat(3,1fr)}.search-main{grid-column:span 2}}@media(max-width:800px){.photos-page{padding:12px}.advanced-search{grid-template-columns:1fr 1fr}.search-main{grid-column:span 2}.comparison-layout{grid-template-columns:1fr}.comparison-results{flex-direction:row;max-height:none;overflow-x:auto}.comparison-results>button{min-width:220px}.before-after-grid{height:auto;grid-template-columns:1fr}.before-after-grid article{min-height:360px}}
.comparison-results>button.featured{border-color:#f6c453;background:linear-gradient(180deg,#fffdf4,#fff)}
.result-section-title{display:flex;align-items:center;gap:6px;padding:7px 9px;color:#64748b;font-size:11px;font-weight:900}
.result-section-title b{margin-right:auto;padding:2px 7px;border-radius:10px;background:#e2e8f0;color:#475569}
.featured-title{color:#a16207}.featured-title span{color:#f59e0b;font-size:17px}.featured-title b{background:#fef3c7;color:#92400e}
:deep(.photos-date-input){width:100%!important;height:41px!important;border:1px solid #dbe3ec!important;border-radius:10px!important;padding:0 10px!important;background:#fff!important;color:#0f172a!important;font-family:inherit!important;box-sizing:border-box!important}
.photos-date-field{width:100%;min-width:0}
.photos-date-field :deep(.vpd-input-group),.photos-date-field :deep(.vpd-input-group input),.photos-date-field :deep(.vpd-input-group .photos-date-input){width:100%!important;min-width:0!important;max-width:100%!important;box-sizing:border-box!important}
.photos-date-field :deep(.vpd-input-group){display:block!important}
.advanced-search{display:grid!important;grid-template-columns:1fr!important;gap:10px!important;padding:16px!important}
.photos-filter-row{display:grid;gap:10px;align-items:center;min-width:0}
.photos-filter-primary{grid-template-columns:minmax(260px,1.7fr) minmax(220px,1.1fr) repeat(4,minmax(128px,.72fr))}
.photos-filter-secondary{grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) minmax(130px,.55fr) minmax(180px,.72fr) auto}
.advanced-search input,.advanced-search select{width:100%;height:44px!important;box-sizing:border-box;font-size:12px;font-weight:800}
.search-main,.tag-search-filter,.photos-date-field{height:44px;min-width:0}
.search-main span,.tag-search-filter>span{top:50%!important;transform:translateY(-50%);line-height:1}
.tag-search-filter button{top:50%!important;transform:translateY(-50%);left:8px!important}
.photos-date-field{position:relative;overflow:hidden;border:1px solid #dbe3ec;border-radius:10px;background:#fff}
.photos-date-field :deep(.vpd-input-group){position:relative!important;width:100%!important;height:100%!important;display:block!important}
.photos-date-field :deep(.vpd-icon-btn){position:absolute!important;left:0!important;top:0!important;width:44px!important;height:44px!important;display:grid!important;place-items:center!important;background:#2563eb!important;color:#fff!important;border-radius:0!important}
.photos-date-field :deep(.vpd-icon-btn svg){width:18px!important;height:18px!important}
:deep(.photos-date-input){height:44px!important;padding:0 12px 0 52px!important;border:0!important;border-radius:10px!important}
.complete-filter{height:36px;align-self:center;justify-content:center;gap:6px;padding:0 11px;border:1px solid #e2e8f0;border-radius:999px;background:#fff;white-space:nowrap;font-size:11px;font-weight:900;line-height:1}
.complete-filter input{width:14px!important;height:14px!important;min-width:14px!important;margin:0}
.consent-filter{height:36px;border-radius:999px}
.photos-filter-actions{display:flex;gap:8px;align-items:center;justify-content:flex-end}
.photo-icon-action{width:44px!important;height:44px!important;min-width:44px!important;border-radius:11px!important}
.result-thumbs{position:relative}
.result-thumbs.sensitive{border:2px solid #ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.12)}
.result-thumbs img.sensitive{outline:3px solid #ef4444;outline-offset:-3px}
.sensitive-photo-badge{position:absolute;right:6px;top:6px;z-index:3;padding:4px 7px;border-radius:999px;background:#dc2626;color:#fff;font-size:8px;font-weight:1000;line-height:1.2;box-shadow:0 3px 10px rgba(127,29,29,.4)}
.angle-view-nav{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:8px;margin:10px 0 8px;padding:8px;border:1px solid #dbeafe;border-radius:13px;background:#f8fbff}
.angle-view-nav>button{height:32px;padding:0 10px;border:1px solid #bfdbfe;border-radius:9px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}
.angle-view-nav>button:hover{border-color:#60a5fa;background:#dbeafe}
.angle-view-nav>div{display:flex;gap:6px;overflow-x:auto;padding-bottom:2px}
.angle-view-nav>div button{flex:0 0 auto;height:28px;padding:0 9px;border:1px solid #e2e8f0;border-radius:999px;background:#fff;color:#64748b;font-family:inherit;font-size:9px;font-weight:900;cursor:pointer}
.angle-view-nav>div button.active{border-color:#2563eb;background:#2563eb;color:#fff}
@media(max-width:800px){.angle-view-nav{grid-template-columns:1fr 1fr}.angle-view-nav>div{grid-column:1/-1;order:3}.angle-view-nav>button{width:100%}}
@media(max-width:1200px){.photos-filter-primary{grid-template-columns:repeat(3,minmax(0,1fr))}.photos-filter-primary .search-main{grid-column:span 2}.photos-filter-secondary{grid-template-columns:repeat(2,minmax(0,1fr))}.photos-filter-actions{justify-content:flex-start}}
@media(max-width:700px){.photos-filter-primary,.photos-filter-secondary{grid-template-columns:1fr}.photos-filter-primary .search-main{grid-column:auto}.photos-filter-actions{justify-content:stretch}.photo-icon-action{flex:1}}
</style>
