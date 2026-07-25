<template>
  <div class="manabe-container">
    <!-- تب‌ها -->
    <div class="tabs">
      <button :class="{ active: activeTab === 'doctor' }" @click="activeTab = 'doctor'">پزشک</button>
      <button :class="{ active: activeTab === 'staff' }" @click="activeTab = 'staff'">پرسنل</button>
      <button :class="{ active: activeTab === 'channels' }" @click="activeTab = 'channels'">کانال‌ها</button>
    </div>

    <!-- تب پزشک -->
    <div v-if="activeTab === 'doctor'" class="tab-content doctor-content">
      <div class="doctor-table-heading">
        <h2>مدیریت پزشکان</h2>
        <p>اطلاعات، روزهای حضور و تنظیمات مالی پزشکان را مدیریت کنید.</p>
      </div>
      <table>
        <thead>
          <tr>
            <th>عکس</th>
            <th>نام</th>
            <th>خدمات و پورسانت</th>
           <th>حقوق ثابت (تومان)</th>
<th>روزهای حضور</th>
<th>حذف / اضافه</th>
          </tr>
        </thead>
        <tbody>
  <tr v-for="(row, index) in doctorRows" :key="index">
    <td class="resource-photo-cell" data-label="عکس">
      <label class="resource-avatar">
        <img v-if="resourceAvatar(row)" :src="resourceAvatar(row)" alt="" />
        <span v-else>{{ resourceInitial(row) }}</span>
        <input type="file" accept="image/*" @change="uploadResourcePhoto('doctor', row, index, $event)" />
      </label>
    </td>

    <td data-label="نام پزشک">
      <select v-model="row.user_id" class="resource-user-select" @change="syncResourceUser(row, 'doctor')">
        <option value="">انتخاب پزشک از رول پزشک</option>
        <option v-for="user in doctorUserOptions" :key="user.id" :value="user.id">
          {{ user.user }}
        </option>
        <option v-if="!doctorUserOptions.length" value="" disabled>کاربری با رول پزشک پیدا نشد</option>
      </select>
      <small v-if="row.name && !row.user_id" class="legacy-resource-name">{{ row.name }}</small>
    </td>

    <td class="doctor-settings-cell" data-label="خدمات و پورسانت">
      <button type="button" class="doctor-settings-btn" @click="openDoctorSettings(index)">
        <span class="doctor-settings-icon">٪</span>
        <span>
          <strong>تنظیم خدمات و پورسانت</strong>
          <small>{{ doctorServiceSummary(row) }}</small>
        </span>
        <b>{{ Number(row.bonus || 0).toLocaleString('fa-IR') }}٪</b>
      </button>
    </td>

    <td data-label="حقوق ثابت">
      <label class="salary-field">
        <span class="salary-field-icon">﷼</span>
        <input
          class="salary-input"
          type="text"
          inputmode="numeric"
          :value="formatSalary(row.salary)"
          @input="updateSalary(row, $event)"
          placeholder="مثلاً ۱۰,۰۰۰,۰۰۰"
        />
        <small>تومان</small>
      </label>
    </td>

    <!-- روزهای کاری -->
    <td class="days-cell" data-label="روزهای حضور">

      <label
        v-for="day in weekDays"
        :key="day"
        class="day-check"
        :class="{ selected: row.available_days.includes(day) }"
      >
        <input
          type="checkbox"
          :value="day"
          v-model="row.available_days"
        />
        {{ day }}
      </label>

    </td>

    <td class="actions-cell" data-label="عملیات">
      <button class="btn-add" type="button" title="اضافه کردن پزشک" @click="addDoctorRow">
        <span>+</span><small>اضافه</small>
      </button>

      <button 
        class="btn-remove" 
        type="button"
        title="حذف پزشک"
        @click="removeDoctorRow(index)" 
        v-if="doctorRows.length > 1"
      >
        <span>−</span><small>حذف</small>
      </button>
    </td>

  </tr>
</tbody>
      </table>
      <div class="doctor-table-toolbar">
        <span :class="{ dirty: doctorsDirty, success: doctorsSaveMessage && !doctorsSaveError, error: doctorsSaveError }">
          {{ doctorsSaveMessage || (doctorsDirty ? 'تغییرات ذخیره‌نشده دارید.' : 'اطلاعات پزشکان به‌روز است.') }}
        </span>
        <button type="button" :disabled="savingDoctors || !doctorsDirty" @click="saveDoctorsManually">
          {{ savingDoctors ? 'در حال ذخیره...' : 'ذخیره تغییرات پزشکان' }}
        </button>
      </div>
    </div>

    <div
      v-if="activeDoctorIndex !== null && activeDoctor"
      class="resource-modal-backdrop"
      @click.self="closeDoctorSettings"
    >
      <section class="doctor-settings-modal" role="dialog" aria-modal="true" aria-labelledby="doctor-settings-title">
        <header class="doctor-settings-modal-head">
          <div>
            <span class="modal-eyebrow">تنظیمات پزشک</span>
            <h3 id="doctor-settings-title">خدمات و پورسانت {{ activeDoctor.name || 'پزشک' }}</h3>
            <p>خدمات قابل ارائه و قوانین مالی این پزشک را مدیریت کنید.</p>
          </div>
          <button type="button" class="resource-modal-close" aria-label="بستن" @click="closeDoctorSettings">×</button>
        </header>

        <div class="doctor-settings-modal-body">
          <section class="doctor-services-panel">
            <div class="modal-section-title">
              <div>
                <h4>خدمات قابل ارائه</h4>
                <small>{{ activeDoctor.service_section_ids.length.toLocaleString('fa-IR') }} بخش انتخاب شده</small>
              </div>
            </div>
            <div v-if="inventorySections.length" class="doctor-services-grid">
              <label
                v-for="section in inventorySections"
                :key="section.id"
                class="doctor-service-option"
                :class="{ selected: doctorHasService(activeDoctor, section.id) }"
              >
                <input v-model="activeDoctor.service_section_ids" type="checkbox" :value="section.id">
                <span class="service-option-check">✓</span>
                <strong>{{ section.name }}</strong>
              </label>
            </div>
            <div v-else class="doctor-services-empty">ابتدا گروه‌بندی خدمات را در بخش انبار ثبت کنید.</div>
          </section>

          <section class="doctor-base-commission">
            <label>
              <span>درصد پورسانت پایه</span>
              <div>
                <input v-model.number="activeDoctor.bonus" type="number" min="0" max="100" placeholder="مثلاً ۱۰">
                <b>درصد</b>
              </div>
            </label>
          </section>

          <CommissionRules v-model="doctorRows[activeDoctorIndex]" />
        </div>

        <footer class="doctor-settings-modal-actions">
          <span>تغییرات به‌صورت خودکار ذخیره می‌شوند.</span>
          <button type="button" @click="closeDoctorSettings">تأیید و بستن</button>
        </footer>
      </section>
    </div>

<!-- تب پرسنل -->
    <div v-if="activeTab === 'staff'" class="tab-content resource-content staff-content">
      <div class="doctor-table-heading">
        <h2>مدیریت پرسنل</h2>
        <p>اطلاعات، حقوق و قوانین پورسانت پرسنل را مانند پزشکان مدیریت کنید.</p>
      </div>
      <table>
        <thead><tr><th>عکس</th><th>نام</th><th>تنظیمات مالی و پورسانت</th><th>حقوق ثابت (تومان)</th><th>حذف / اضافه</th></tr></thead>
        <tbody>
          <tr v-for="(row, index) in staffRows" :key="row.id || index">
            <td class="resource-photo-cell" data-label="عکس">
              <label class="resource-avatar">
                <img v-if="resourceAvatar(row)" :src="resourceAvatar(row)" alt="" />
                <span v-else>{{ resourceInitial(row) }}</span>
                <input type="file" accept="image/*" @change="uploadResourcePhoto('staff', row, index, $event)" />
              </label>
            </td>
            <td data-label="نام پرسنل">
              <select v-model="row.user_id" class="resource-user-select" @change="syncResourceUser(row, 'staff')">
                <option value="">انتخاب پرسنل از رول پرسنل</option>
                <option v-for="user in staffUserOptions" :key="user.id" :value="user.id">{{ user.user }}</option>
                <option v-if="!staffUserOptions.length" value="" disabled>کاربری با رول پرسنل پیدا نشد</option>
              </select>
              <small v-if="row.name && !row.user_id" class="legacy-resource-name">{{ row.name }}</small>
            </td>
            <td class="doctor-settings-cell" data-label="تنظیمات مالی و پورسانت">
              <button type="button" class="doctor-settings-btn" @click="openStaffSettings(index)">
                <span class="doctor-settings-icon">٪</span>
                <span><strong>تنظیم درصد و پورسانت</strong><small>{{ resourceCommissionSummary(row) }}</small></span>
                <b>{{ Number(row.bonus || 0).toLocaleString('fa-IR') }}٪</b>
              </button>
            </td>
            <td data-label="حقوق ثابت">
              <label class="salary-field"><span class="salary-field-icon">﷼</span><input class="salary-input" type="text" inputmode="numeric" :value="formatSalary(row.salary)" @input="updateSalary(row, $event)" placeholder="مثلاً ۸,۰۰۰,۰۰۰"><small>تومان</small></label>
            </td>
            <td class="actions-cell" data-label="عملیات">
              <button class="btn-add" type="button" title="اضافه کردن پرسنل" @click="addStaffRow"><span>+</span><small>اضافه</small></button>
              <button v-if="staffRows.length > 1" class="btn-remove" type="button" title="حذف پرسنل" @click="removeStaffRow(index)"><span>−</span><small>حذف</small></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="activeStaffIndex !== null && activeStaff" class="resource-modal-backdrop" @click.self="closeStaffSettings">
      <section class="doctor-settings-modal" role="dialog" aria-modal="true" aria-labelledby="staff-settings-title">
        <header class="doctor-settings-modal-head">
          <div><span class="modal-eyebrow">تنظیمات پرسنل</span><h3 id="staff-settings-title">درصد و پورسانت {{ activeStaff.name || 'پرسنل' }}</h3><p>درصد پایه، نوع مشتری، کسر مواد و پاداش پلکانی را مدیریت کنید.</p></div>
          <button type="button" class="resource-modal-close" title="بستن" aria-label="بستن" @click="closeStaffSettings">×</button>
        </header>
        <div class="doctor-settings-modal-body">
          <section class="doctor-base-commission"><label><span>درصد پورسانت پایه</span><div><input v-model.number="activeStaff.bonus" type="number" min="0" max="100" placeholder="مثلاً ۵"><b>درصد</b></div></label></section>
          <CommissionRules v-model="staffRows[activeStaffIndex]" />
        </div>
        <footer class="doctor-settings-modal-actions"><span>تغییرات پس از بستن به‌صورت خودکار ذخیره می‌شوند.</span><button type="button" @click="closeStaffSettings">تأیید و بستن</button></footer>
      </section>
    </div>

    <!-- تب کانال‌ها -->
    <div v-if="activeTab === 'channels'" class="tab-content resource-content channels-content">
      <div class="doctor-table-heading"><h2>مدیریت کانال‌ها</h2><p>کانال‌های تبلیغاتی و آیکون نمایشی آن‌ها را مدیریت کنید.</p></div>
      <table>
        <thead><tr><th>آیکون</th><th>نام کانال</th><th>تنظیمات</th><th>حذف / اضافه</th></tr></thead>
        <tbody>
          <tr v-for="(row, index) in channelRows" :key="row.id || index">
            <td data-label="آیکون"><span class="channel-card-icon">{{ row.icon || '📣' }}</span></td>
            <td data-label="نام کانال"><strong class="channel-card-name">{{ row.name || 'کانال جدید' }}</strong></td>
            <td class="doctor-settings-cell" data-label="تنظیمات">
              <button type="button" class="doctor-settings-btn channel-settings-btn" @click="openChannelSettings(index)"><span class="doctor-settings-icon">✎</span><span><strong>تنظیم کانال</strong><small>{{ row.icon ? 'آیکون اختصاصی انتخاب شده' : 'نمایش با آیکون پیش‌فرض' }}</small></span><b>ویرایش</b></button>
            </td>
            <td class="actions-cell" data-label="عملیات"><button class="btn-add" type="button" title="اضافه کردن کانال" @click="addChannelRow"><span>+</span><small>اضافه</small></button><button v-if="channelRows.length > 1" class="btn-remove" type="button" title="حذف کانال" @click="removeChannelRow(index)"><span>−</span><small>حذف</small></button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="activeChannelIndex !== null && activeChannel" class="resource-modal-backdrop" @click.self="closeChannelSettings">
      <section class="doctor-settings-modal channel-settings-modal" role="dialog" aria-modal="true" aria-labelledby="channel-settings-title">
        <header class="doctor-settings-modal-head"><div><span class="modal-eyebrow">تنظیمات کانال</span><h3 id="channel-settings-title">{{ activeChannel.name || 'کانال جدید' }}</h3><p>نام و آیکون نمایشی کانال تبلیغاتی را تعیین کنید.</p></div><button type="button" class="resource-modal-close" title="بستن" aria-label="بستن" @click="closeChannelSettings">×</button></header>
        <div class="doctor-settings-modal-body">
          <section class="channel-modal-form"><label><span>نام کانال</span><input v-model.trim="activeChannel.name" type="text" placeholder="نام کانال تبلیغات"></label><label><span>آیکون نمایشی</span><select v-model="activeChannel.icon"><option value="">آیکون پیش‌فرض</option><option v-for="icon in channelIconOptions" :key="icon" :value="icon">{{ icon }}</option></select></label><div class="channel-modal-preview"><span>{{ activeChannel.icon || '📣' }}</span><strong>{{ activeChannel.name || 'پیش‌نمایش کانال' }}</strong></div></section>
        </div>
        <footer class="doctor-settings-modal-actions"><span>تغییرات پس از بستن به‌صورت خودکار ذخیره می‌شوند.</span><button type="button" @click="closeChannelSettings">تأیید و بستن</button></footer>
      </section>
    </div>

  </div>
</template>

<script>
import { avatarInitial, avatarUrl } from '@/utils/avatar'
import CommissionRules from './CommissionRules.vue'

export default {
  components: { CommissionRules },
  data() {
  return {
    activeTab: null,
    activeDoctorIndex: null,
    activeStaffIndex: null,
    activeChannelIndex: null,

    weekDays: [
      "شنبه",
      "یکشنبه",
      "دوشنبه",
      "سه شنبه",
      "چهارشنبه",
      "پنجشنبه",
      "جمعه"
    ],

    doctorRows: [{
      user_id: '',
      name: '',
      bonus: 0,
      commission_customer_scope: 'both',
      commission_after_materials: false,
      sales_bonus_enabled: false,
      sales_bonus_tiers: [],
      hourly_rate: 0, overtime_hourly_rate: 0, shortage_hourly_deduction: 0, absence_deduction: 0, allowed_shortage_hours: 0,
      salary: '',
      available_days: [],
      service_section_ids: [],
      profile_photo_path: null,
      profile_thumbnail_path: null,
      profile_photo_url: null,
      profile_thumbnail_url: null,
      avatar_url: null
    }],

    staffRows: [{
      user_id: '',
      name: '',
      bonus: 0,
      commission_customer_scope: 'both',
      commission_after_materials: false,
      sales_bonus_enabled: false,
      sales_bonus_tiers: [],
      hourly_rate: 0, overtime_hourly_rate: 0, shortage_hourly_deduction: 0, absence_deduction: 0, allowed_shortage_hours: 0,
      salary: '',
      profile_photo_path: null,
      profile_thumbnail_path: null,
      profile_photo_url: null,
      profile_thumbnail_url: null,
      avatar_url: null
    }],

    channelRows: [{
      name: '', icon: ''
    }],
    channelIconOptions: ['📱','📷','🌐','🔍','📣','👥','💬','✉️','☎️','🎯','⭐','🤝','📍','🎬','▶️','🟢'],
    users: [],
    roles: [],
    inventorySections: [],

    saveTimeout: null,
    isSyncingResourceRows: false,
    doctorRowsRevision: 0,
    doctorsDirty: false,
    savingDoctors: false,
    doctorsSaveMessage: '',
    doctorsSaveError: false,
  };
},
  
  mounted() {
    this.fetchData();
  },

  watch: {
    doctorRows: {
      handler() {
        if (this.isSyncingResourceRows) return;
        this.doctorRowsRevision += 1;
        this.doctorsDirty = true;
        this.doctorsSaveMessage = '';
        this.doctorsSaveError = false;
      },
      deep: true
    },
    staffRows: {
      handler() {
        if (this.isSyncingResourceRows) return;
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
          this.autoSaveStaff();
        }, 1000);
      },
      deep: true
    },
    channelRows: {
      handler() {
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
          this.autoSaveChannels();
        }, 1000);
      },
      deep: true
    },
  },

  computed: {
    activeDoctor() {
      return this.activeDoctorIndex === null ? null : this.doctorRows[this.activeDoctorIndex];
    },
    activeStaff() {
      return this.activeStaffIndex === null ? null : this.staffRows[this.activeStaffIndex];
    },
    activeChannel() {
      return this.activeChannelIndex === null ? null : this.channelRows[this.activeChannelIndex];
    },
    doctorRoleIds() {
      return this.roles
        .filter(role => String(role.name || '').includes('پزشک'))
        .map(role => role.id);
    },

    staffRoleIds() {
      return this.roles
        .filter(role => String(role.name || '').includes('پرسنل'))
        .map(role => role.id);
    },

    doctorUserOptions() {
      return this.users.filter(user => this.userHasAnyRole(user, this.doctorRoleIds));
    },

    staffUserOptions() {
      return this.users.filter(user => this.userHasAnyRole(user, this.staffRoleIds));
    }
  },

  methods: {
    async saveDoctorsManually() {
      if (this.savingDoctors) return;
      const hasIncompleteRow = this.doctorRows.some(row => !row.user_id && !String(row.name || '').trim());
      if (hasIncompleteRow) {
        this.doctorsSaveError = true;
        this.doctorsSaveMessage = 'برای ردیف جدید، ابتدا پزشک را انتخاب کنید.';
        return;
      }
      this.savingDoctors = true;
      this.doctorsSaveMessage = '';
      this.doctorsSaveError = false;
      try {
        const result = await this.autoSaveDoctors();
        if (!result) throw new Error('ذخیره پزشکان انجام نشد.');
        this.doctorsDirty = false;
        this.doctorsSaveMessage = 'تغییرات پزشکان با موفقیت ذخیره شد.';
      } catch (error) {
        this.doctorsSaveError = true;
        this.doctorsSaveMessage = error.message || 'ذخیره اطلاعات پزشکان انجام نشد.';
      } finally {
        this.savingDoctors = false;
      }
    },

    openDoctorSettings(index) {
      this.activeDoctorIndex = index;
    },

    closeDoctorSettings() {
      this.activeDoctorIndex = null;
    },
    openStaffSettings(index) {
      this.activeStaffIndex = index;
    },

    closeStaffSettings() {
      this.activeStaffIndex = null;
      this.autoSaveStaff();
    },

    openChannelSettings(index) {
      this.activeChannelIndex = index;
    },

    closeChannelSettings() {
      this.activeChannelIndex = null;
      this.autoSaveChannels();
    },

    resourceCommissionSummary(row) {
      const scope = { new: 'مشتری جدید', existing: 'مشتری قدیمی', both: 'همه مشتریان' }[row?.commission_customer_scope] || 'همه مشتریان';
      const basis = row?.commission_after_materials ? 'پس از کسر مواد' : 'از مبلغ خدمت';
      const tiers = row?.sales_bonus_enabled ? `${(row.sales_bonus_tiers || []).length.toLocaleString('fa-IR')} پله فروش` : 'بدون پاداش پلکانی';
      return `${scope}، ${basis}، ${tiers}`;
    },

    doctorServiceSummary(row) {
      const selectedIds = Array.isArray(row?.service_section_ids) ? row.service_section_ids : [];
      const names = this.inventorySections
        .filter(section => selectedIds.some(id => String(id) === String(section.id)))
        .map(section => section.name);
      if (!names.length) return 'خدمتی انتخاب نشده';
      if (names.length <= 2) return names.join('، ');
      return `${names.slice(0, 2).join('، ')} و ${names.length - 2} مورد دیگر`;
    },

    doctorHasService(row, sectionId) {
      return (row?.service_section_ids || []).some(id => String(id) === String(sectionId));
    },

    userHasAnyRole(user, roleIds) {
      if (!roleIds.length) return false;
      const userRoleIds = (user.role_ids || []).map(id => Number(id));
      return roleIds.some(id => userRoleIds.includes(Number(id)));
    },

    normalizeDoctorRow(row = {}) {
      return {
        ...row,
        user_id: row.user_id || '',
        commission_customer_scope: row.commission_customer_scope || 'both',
        commission_after_materials: Boolean(row.commission_after_materials),
        sales_bonus_enabled: Boolean(row.sales_bonus_enabled),
        sales_bonus_tiers: Array.isArray(row.sales_bonus_tiers) ? row.sales_bonus_tiers : [],
        hourly_rate: Number(row.hourly_rate || 0), overtime_hourly_rate: Number(row.overtime_hourly_rate || 0), shortage_hourly_deduction: Number(row.shortage_hourly_deduction || 0), absence_deduction: Number(row.absence_deduction || 0), allowed_shortage_hours: Number(row.allowed_shortage_hours || 0),
        available_days: Array.isArray(row.available_days) ? row.available_days : [],
        service_section_ids: Array.isArray(row.service_section_ids) ? row.service_section_ids : []
      };
    },

    normalizeStaffRow(row = {}) {
      return {
        ...row,
        user_id: row.user_id || '',
        commission_customer_scope: row.commission_customer_scope || 'both',
        commission_after_materials: Boolean(row.commission_after_materials),
        sales_bonus_enabled: Boolean(row.sales_bonus_enabled),
        sales_bonus_tiers: Array.isArray(row.sales_bonus_tiers) ? row.sales_bonus_tiers : []
        ,hourly_rate: Number(row.hourly_rate || 0), overtime_hourly_rate: Number(row.overtime_hourly_rate || 0), shortage_hourly_deduction: Number(row.shortage_hourly_deduction || 0), absence_deduction: Number(row.absence_deduction || 0), allowed_shortage_hours: Number(row.allowed_shortage_hours || 0)
      };
    },

    syncResourceUser(row, type) {
      const options = type === 'doctor' ? this.doctorUserOptions : this.staffUserOptions;
      const selected = options.find(user => String(user.id) === String(row.user_id));
      row.name = selected?.user || '';
    },

    async fetchResourceOptions() {
      const [settingsResponse, inventoryResponse] = await Promise.all([
        fetch('/api/settings', { headers: { 'Accept': 'application/json' } }),
        fetch('/api/inventory/context', { headers: { 'Accept': 'application/json' } })
      ]);

      if (settingsResponse.ok) {
        const settingsData = await settingsResponse.json();
        this.users = Array.isArray(settingsData.users) ? settingsData.users : [];
        this.roles = Array.isArray(settingsData.roles) ? settingsData.roles : [];
      }

      if (inventoryResponse.ok) {
        const inventoryData = await inventoryResponse.json();
        this.inventorySections = Array.isArray(inventoryData.sections) ? inventoryData.sections : [];
      }
    },

    async fetchData() {
      try {
        await this.fetchResourceOptions();

        // بارگذاری پزشکان
        const doctorsResponse = await fetch('/api/doctors');
        const doctorsData = await doctorsResponse.json();
        if (doctorsData.length > 0) {
          this.isSyncingResourceRows = true;
          this.doctorRows = doctorsData.map(this.normalizeDoctorRow);
          this.$nextTick(() => {
            this.isSyncingResourceRows = false;
            this.doctorsDirty = false;
          });
        }

        // بارگذاری پرسنل
        const staffResponse = await fetch('/api/staff');
        const staffData = await staffResponse.json();
        if (staffData.length > 0) {
          this.staffRows = staffData.map(this.normalizeStaffRow);
        }

        // بارگذاری کانال‌ها
        const channelsResponse = await fetch('/api/channels');
        const channelsData = await channelsResponse.json();
        if (channelsData.length > 0) {
          this.channelRows = channelsData;
        }

      } catch (error) {
        console.error('خطا در بارگذاری اولیه اطلاعات:', error);
      }
    },

    addDoctorRow() {
      this.doctorRows.push({
        user_id: '',
        name: '',
        bonus: 0,
        commission_customer_scope: 'both', commission_after_materials: false, sales_bonus_enabled: false, sales_bonus_tiers: [],
        hourly_rate: 0, overtime_hourly_rate: 0, shortage_hourly_deduction: 0, absence_deduction: 0, allowed_shortage_hours: 0,
        salary: '',
        available_days: [],
        service_section_ids: [],
        profile_photo_path: null,
        profile_thumbnail_path: null,
        profile_photo_url: null,
        profile_thumbnail_url: null,
        avatar_url: null
      });
    },
    removeDoctorRow(index) {
      this.doctorRows.splice(index, 1);
    },

    addStaffRow() {
      this.staffRows.push({
        user_id: '',
        name: '',
        bonus: 0,
        commission_customer_scope: 'both', commission_after_materials: false, sales_bonus_enabled: false, sales_bonus_tiers: [],
        hourly_rate: 0, overtime_hourly_rate: 0, shortage_hourly_deduction: 0, absence_deduction: 0, allowed_shortage_hours: 0,
        salary: '',
        profile_photo_path: null,
        profile_thumbnail_path: null,
        profile_photo_url: null,
        profile_thumbnail_url: null,
        avatar_url: null
      });
    },
    removeStaffRow(index) {
      this.staffRows.splice(index, 1);
    },

    addChannelRow() {
      this.channelRows.push({ name: '', icon: '' });
    },
    removeChannelRow(index) {
      this.channelRows.splice(index, 1);
    },

    
    formatSalary(value) {
      const digits = String(value ?? '')
        .replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[^\d]/g, '');
      if (!digits) return '';
      return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    updateSalary(row, event) {
      const digits = String(event.target.value || '')
        .replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[^\d]/g, '');
      row.salary = digits;
      event.target.value = this.formatSalary(digits);
    },

    resourceAvatar(row) {
      return avatarUrl(row);
    },

    resourceInitial(row) {
      return avatarInitial(row);
    },

    async makeSquareWebp(file, size, quality, fileName) {
      const imageUrl = URL.createObjectURL(file);
      const image = new Image();
      image.src = imageUrl;
      await new Promise((resolve, reject) => {
        image.onload = resolve;
        image.onerror = reject;
      });

      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const context = canvas.getContext('2d');
      const side = Math.min(image.width, image.height);
      const sourceX = (image.width - side) / 2;
      const sourceY = (image.height - side) / 2;
      context.drawImage(image, sourceX, sourceY, side, side, 0, 0, size, size);
      URL.revokeObjectURL(imageUrl);

      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', quality));
      return new File([blob], fileName, { type: 'image/webp' });
    },

    async uploadResourcePhoto(type, row, index, event) {
      const file = event.target.files?.[0];
      event.target.value = '';
      if (!file) return;

      if (!row.name || !row.name.trim()) {
        alert('اول نام را وارد کنید، بعد عکس را اضافه کنید.');
        return;
      }

      const rowsAfterSave = type === 'doctor'
        ? await this.autoSaveDoctors()
        : await this.autoSaveStaff();
      row = (rowsAfterSave || (type === 'doctor' ? this.doctorRows : this.staffRows))[index];

      if (!row?.id) {
        alert('ذخیره اولیه انجام نشد. دوباره تلاش کنید.');
        return;
      }

      const formData = new FormData();
      formData.append('photo', await this.makeSquareWebp(file, 512, 0.72, 'photo.webp'));
      formData.append('thumbnail', await this.makeSquareWebp(file, 50, 0.48, 'thumbnail.webp'));

      const endpoint = type === 'doctor'
        ? `/api/doctors/${row.id}/photo`
        : `/api/staff/${row.id}/photo`;

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData
      });

      if (!response.ok) {
        alert('ذخیره عکس انجام نشد.');
        return;
      }

      const data = await response.json();
      if (data.resource) {
        const rows = type === 'doctor' ? this.doctorRows : this.staffRows;
        rows.splice(index, 1, data.resource);
      }
    },

    async autoSaveDoctors() {
      const requestRevision = this.doctorRowsRevision;
      const validDoctors = this.doctorRows
        .map(this.normalizeDoctorRow)
        .filter(row => row.user_id || (row.name && row.name.trim() !== ''));
      if (validDoctors.length === 0) return this.doctorRows;

      try {
        const response = await fetch('/api/doctors', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(validDoctors)
        });
        const data = await response.json();
        if (!response.ok) {
          const validationMessage = data.errors
            ? Object.values(data.errors).flat()[0]
            : data.message;
          throw new Error(validationMessage || 'ذخیره پزشکان انجام نشد.');
        }
        if (data.doctors) {
          if (requestRevision !== this.doctorRowsRevision) {
            return this.doctorRows;
          }

          const serverDoctors = data.doctors.map(this.normalizeDoctorRow);
          const unsavedDoctors = this.doctorRows
            .filter(row => !row.id)
            .filter(row => !serverDoctors.some(serverRow => {
              if (row.user_id && serverRow.user_id) {
                return String(row.user_id) === String(serverRow.user_id);
              }
              const rowName = String(row.name || '').trim();
              const serverName = String(serverRow.name || '').trim();
              return rowName && serverName && rowName === serverName;
            }))
            .map(this.normalizeDoctorRow);

          this.isSyncingResourceRows = true;
          this.doctorRows = [
            ...serverDoctors,
            ...unsavedDoctors
          ];
          this.$nextTick(() => { this.isSyncingResourceRows = false; });
        }
        console.log('پزشکان با موفقیت ذخیره شدند:', data);
        return this.doctorRows;
      } catch (error) {
        console.error('خطا در ذخیره اطلاعات پزشکان:', error);
        return null;
      }
    },

    async autoSaveStaff() {
      const validStaff = this.staffRows
        .map(this.normalizeStaffRow)
        .filter(row => row.user_id || (row.name && row.name.trim() !== ''));
      if (validStaff.length === 0) return this.staffRows;

      try {
        const response = await fetch('/api/staff', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(validStaff)
        });
        const data = await response.json();
        if (data.staff) {
          this.isSyncingResourceRows = true;
          this.staffRows = data.staff.map(this.normalizeStaffRow);
          this.$nextTick(() => { this.isSyncingResourceRows = false; });
        }
        console.log('پرسنل با موفقیت ذخیره شدند:', data);
        return this.staffRows;
      } catch (error) {
        console.error('خطا در ذخیره اطلاعات پرسنل:', error);
        return this.staffRows;
      }
    },

    async autoSaveChannels() {
      const validChannels = this.channelRows.filter(row => row.name && row.name.trim() !== '');
      if (validChannels.length === 0) return;

      try {
        const response = await fetch('/api/channels', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(validChannels)
        });
        const data = await response.json();
        console.log('کانال‌ها با موفقیت ذخیره شدند:', data);
      } catch (error) {
        console.error('خطا در ذخیره اطلاعات کانال‌ها:', error);
      }
    },

  }
};
</script>


<style scoped>
@import '@/scss/main.scss';

@font-face {
  font-family: "Vazir";
  src: url("@/assets/fonts/vazir/Vazir-Medium-FD.woff") format("woff");
}

.manabe-container {
  direction: rtl;
  font-family: "Vazir", Tahoma, sans-serif !important;
  width: 100%;
  max-width: 1500px;
  min-width: 0;
  margin: 0 auto;
  padding: 24px;
  box-sizing: border-box;
  overflow: visible;
  border: 1px solid #e6edf7;
  border-radius: 26px;
  background: linear-gradient(145deg, #f8fafc, #f1f5f9);
  text-align: right;
}

.manabe-container * {
  font-family: "Vazir", Tahoma, sans-serif !important;
  box-sizing: border-box;
}

.tabs {
  display: flex;
  gap: 8px;
  width: fit-content;
  margin-bottom: 25px;
  padding: 6px;
  position: relative;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
}

.tabs button {
  position: relative;
  background: transparent;
  border: none;
  padding: 10px 22px;
  cursor: pointer;
  font-weight: 600;
  font-size: 16px;
  color: #444;
  border-radius: 11px;
  z-index: 1;
  transition: color 0.3s ease;
}

.tabs button.active {
  background: #2563eb;
  color: white;
  box-shadow: 0 7px 16px rgba(37, 99, 235, .22);
}

.tabs button.active::after {
  display: none;
}

.tab-content {
  margin-bottom: 40px;
}

.tab-content.doctor-content {
  margin-bottom: 40px;
}

.tab-content.staff-content {
  margin-top: 30px;
}

table {
  width: 820px;
  border-collapse: collapse;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  border-radius: 8px;
}

th,
td {
  border: 1px solid #eee;
  padding: 10px;
  text-align: center;
}

th {
  background: #f8f9fa;
}

.narrow-col,
.doctor-content table th:nth-child(3),
.doctor-content table td:nth-child(3),
.staff-content table th:nth-child(3),
.staff-content table td:nth-child(3) {
  width: 80px;
}

input {
  width: 90%;
  padding: 6px;
  border: 1px solid #ddd;
  border-radius: 4px;
  text-align: center;
}

.salary-input {
  font-variant-numeric: tabular-nums;
  letter-spacing: .2px;
  direction: ltr;
}

select {
  width: 90%;
  padding: 6px;
  border: 1px solid #ddd;
  border-radius: 4px;
  background: #fff;
  text-align: center;
}

.resource-user-select {
  min-width: 180px;
}

.legacy-resource-name {
  display: block;
  margin-top: 5px;
  color: #777;
  font-size: 11px;
}

.service-sections-cell {
  min-width: 230px;
  text-align: right;
}

.doctor-content {
  width: 100%;
  overflow: visible;
  padding: 4px 4px 18px;
}

.doctor-table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-top: 14px;
  padding: 16px 18px;
  border: 1px solid #dbeafe;
  border-radius: 17px;
  background: #fff;
  box-shadow: 0 7px 20px rgba(15, 23, 42, .05);
}

.doctor-table-heading {
  margin-bottom: 14px;
  padding: 4px 3px;
}

.doctor-table-heading h2 {
  margin: 0 0 5px;
  color: #1e293b;
  font-size: 17px;
}

.doctor-table-heading p {
  margin: 0;
  color: #64748b;
  font-size: 11px;
}

.doctor-table-toolbar span {
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
}

.doctor-table-toolbar span.dirty { color: #b45309; }
.doctor-table-toolbar span.success { color: #15803d; }
.doctor-table-toolbar span.error { color: #dc2626; }

.doctor-table-toolbar button {
  min-width: 190px;
  height: 43px;
  padding: 0 17px;
  border: 0;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  font-family: inherit;
  font-weight: 900;
  cursor: pointer;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .2);
}

.doctor-table-toolbar button:disabled {
  cursor: not-allowed;
  opacity: .45;
  box-shadow: none;
}

.doctor-content table {
  width: 100%;
  min-width: 980px;
  border-collapse: separate;
  border-spacing: 0 10px;
  background: transparent;
  box-shadow: none;
}

.doctor-content thead th {
  padding: 8px 14px;
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.doctor-content tbody tr {
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
}

.doctor-content tbody td {
  padding: 14px;
  border-color: #eef2f7;
}

.doctor-content tbody td:first-child {
  border-radius: 0 16px 16px 0;
}

.doctor-content tbody td:last-child {
  border-radius: 16px 0 0 16px;
}

.doctor-content table th:nth-child(3),
.doctor-content table td:nth-child(3) {
  width: 310px;
  min-width: 310px;
}

.doctor-content table th:nth-child(4),
.doctor-content table td:nth-child(4) {
  width: 240px;
  min-width: 240px;
}

.staff-content table {
  width: 100%;
  min-width: 900px;
}

.staff-content table th:nth-child(4),
.staff-content table td:nth-child(4) {
  width: 230px;
  min-width: 230px;
}

.staff-content table td:nth-child(4) input {
  width: 100%;
  min-width: 190px;
  height: 42px;
  padding: 0 12px;
  color: #1e293b;
  font-size: 13px;
  font-weight: 900;
  text-align: right;
  direction: ltr;
}

.doctor-settings-cell {
  text-align: right;
}

.doctor-settings-btn {
  width: 100%;
  min-height: 62px;
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border: 1px solid #dbeafe;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8fbff, #eff6ff);
  color: #1e3a8a;
  font-family: inherit;
  text-align: right;
  cursor: pointer;
  transition: .2s ease;
}

.doctor-settings-btn:hover {
  border-color: #60a5fa;
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(37, 99, 235, .12);
}

.doctor-settings-icon {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  font-size: 19px;
  font-weight: 900;
}

.doctor-settings-btn > span:nth-child(2) {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.doctor-settings-btn strong {
  color: #1e293b;
  font-size: 12px;
}

.doctor-settings-btn small {
  overflow: hidden;
  color: #64748b;
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.doctor-settings-btn > b {
  padding: 5px 8px;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 12px;
  white-space: nowrap;
}

.salary-field {
  width: 100%;
  min-width: 215px;
  height: 48px;
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  align-items: center;
  overflow: hidden;
  border: 1px solid #dbe3ed;
  border-radius: 13px;
  background: #fff;
  box-shadow: inset 0 1px 2px rgba(15, 23, 42, .03);
  transition: .2s ease;
}

.salary-field:focus-within {
  border-color: #34d399;
  box-shadow: 0 0 0 4px rgba(52, 211, 153, .13);
}

.salary-field-icon {
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  margin-right: 3px;
  border-radius: 9px;
  background: #ecfdf5;
  color: #059669;
  font-size: 13px;
  font-weight: 900;
}

.salary-field input {
  width: 100%;
  min-width: 0;
  height: 46px;
  padding: 0 8px;
  border: 0;
  outline: 0;
  background: transparent;
  color: #1e293b;
  font-size: 13px;
  font-weight: 900;
  text-align: right;
  direction: ltr;
}

.salary-field small {
  padding: 0 11px;
  color: #64748b;
  font-size: 10px;
  white-space: nowrap;
}

.resource-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 22px;
  background: rgba(15, 23, 42, .62);
  backdrop-filter: blur(6px);
}

.doctor-settings-modal {
  width: min(850px, 100%);
  max-height: calc(100vh - 44px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, .65);
  border-radius: 24px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .3);
  direction: rtl;
}

.doctor-settings-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 22px 24px 18px;
  border-bottom: 1px solid #e2e8f0;
  background: linear-gradient(135deg, #eff6ff, #f8fafc);
}

.modal-eyebrow {
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}

.doctor-settings-modal-head h3 {
  margin: 5px 0;
  color: #0f172a;
  font-size: 20px;
}

.doctor-settings-modal-head p {
  margin: 0;
  color: #64748b;
  font-size: 12px;
}

.resource-modal-close {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border: 0;
  border-radius: 12px;
  background: #fff;
  color: #64748b;
  box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
  font-size: 25px;
  cursor: pointer;
}

.doctor-settings-modal-body {
  overflow-y: auto;
  padding: 20px 24px 24px;
}

.doctor-services-panel {
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  background: #f8fafc;
}

.modal-section-title {
  display: flex;
  justify-content: space-between;
  margin-bottom: 13px;
}

.modal-section-title h4 {
  margin: 0 0 3px;
  color: #1e293b;
}

.modal-section-title small {
  color: #64748b;
}

.doctor-services-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 9px;
}

.doctor-service-option {
  min-height: 48px;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  color: #475569;
  font-size: 12px;
  cursor: pointer;
  transition: .18s ease;
}

.doctor-service-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.service-option-check {
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  display: grid;
  place-items: center;
  border: 2px solid #cbd5e1;
  border-radius: 8px;
  color: transparent;
  font-weight: 900;
}

.doctor-service-option.selected {
  border-color: #60a5fa;
  background: #eff6ff;
  color: #1d4ed8;
}

.doctor-service-option.selected .service-option-check {
  border-color: #2563eb;
  background: #2563eb;
  color: #fff;
}

.doctor-services-empty {
  padding: 18px;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  color: #64748b;
  text-align: center;
}

.doctor-base-commission {
  margin-top: 14px;
  padding: 15px;
  border: 1px solid #bbf7d0;
  border-radius: 16px;
  background: #f0fdf4;
}

.doctor-base-commission label {
  display: grid;
  grid-template-columns: 1fr 210px;
  align-items: center;
  gap: 14px;
  color: #166534;
  font-size: 13px;
  font-weight: 900;
}

.doctor-base-commission label > div {
  display: flex;
  align-items: center;
  overflow: hidden;
  border: 1px solid #86efac;
  border-radius: 11px;
  background: #fff;
}

.doctor-base-commission input {
  width: 100%;
  height: 40px;
  border: 0;
  outline: 0;
}

.doctor-base-commission b {
  padding: 0 12px;
  color: #15803d;
  font-size: 11px;
}

.doctor-settings-modal-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 15px 24px;
  border-top: 1px solid #e2e8f0;
  background: #fff;
}

.doctor-settings-modal-actions span {
  color: #64748b;
  font-size: 11px;
}

.doctor-settings-modal-actions button {
  padding: 10px 18px;
  border: 0;
  border-radius: 11px;
  background: #2563eb;
  color: #fff;
  font-family: inherit;
  font-weight: 900;
  cursor: pointer;
}

@media (max-width: 720px) {
  .resource-modal-backdrop { padding: 0; align-items: end; }
  .doctor-settings-modal { max-height: 94vh; border-radius: 22px 22px 0 0; }
  .doctor-settings-modal-head,
  .doctor-settings-modal-body { padding-right: 16px; padding-left: 16px; }
  .doctor-services-grid { grid-template-columns: 1fr 1fr; }
  .doctor-base-commission label { grid-template-columns: 1fr; }
  .doctor-settings-modal-actions { padding: 13px 16px; }
  .doctor-settings-modal-actions span { display: none; }
  .doctor-settings-modal-actions button { width: 100%; }
}

@media (max-width: 460px) {
  .doctor-services-grid { grid-template-columns: 1fr; }
}

@media (max-width: 1100px) {
  .manabe-container { padding: 18px; }
  .doctor-content table,
  .doctor-content tbody {
    display: block;
    width: 100%;
    min-width: 0;
  }
  .doctor-content thead { display: none; }
  .doctor-content tbody tr {
    display: grid;
    grid-template-columns: 110px minmax(0, 1fr);
    width: 100%;
    margin-bottom: 16px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, .07);
  }
  .doctor-content tbody td,
  .doctor-content table td:nth-child(3) {
    width: auto;
    min-width: 0;
    display: grid;
    grid-template-columns: 105px minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    padding: 13px 16px;
    border: 0;
    border-bottom: 1px solid #eef2f7;
    border-radius: 0;
    text-align: right;
  }
  .doctor-content tbody td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
  }
  .doctor-content tbody .resource-photo-cell {
    grid-row: 1 / span 2;
    display: grid;
    grid-template-columns: 1fr;
    place-items: center;
    padding: 16px;
    border-left: 1px solid #eef2f7;
    background: #f8fafc;
  }
  .doctor-content tbody .resource-photo-cell::before { display: none; }
  .doctor-content tbody td:nth-child(2) { grid-column: 2; }
  .doctor-content tbody td:nth-child(n+3) { grid-column: 1 / -1; }
  .doctor-content tbody td:last-child { border-bottom: 0; }
  .doctor-content .resource-user-select { width: 100%; min-width: 0; }
  .doctor-content .days-cell { min-width: 0; }
  .doctor-content .actions-cell {
    display: grid;
    grid-template-columns: 105px 1fr;
    justify-content: initial;
  }
  .doctor-content .actions-cell::after {
    content: "";
    grid-column: 2;
  }
}

@media (max-width: 600px) {
  .manabe-container { padding: 12px; border-radius: 18px; }
  .doctor-table-toolbar { align-items: stretch; flex-direction: column; }
  .doctor-table-toolbar button { width: 100%; }
  .tabs { width: 100%; overflow-x: auto; }
  .tabs button { flex: 1 0 auto; padding: 9px 16px; font-size: 13px; }
  .doctor-content tbody tr { grid-template-columns: 82px minmax(0, 1fr); }
  .doctor-content tbody td,
  .doctor-content table td:nth-child(3) {
    grid-template-columns: 1fr;
    gap: 7px;
  }
  .doctor-content tbody .resource-photo-cell { padding: 10px; }
  .doctor-settings-btn { grid-template-columns: 38px minmax(0, 1fr); }
  .doctor-settings-btn > b { grid-column: 2; justify-self: start; }
  .doctor-content .actions-cell { grid-template-columns: 1fr auto; }
}

.section-check {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin: 4px;
  font-size: 12px;
}

.section-check input {
  width: auto;
}

.resource-photo-cell {
  width: 72px;
}

.resource-avatar {
  width: 52px;
  height: 52px;
  margin: 0 auto;
  border-radius: 12px;
  border: 1px solid #dbeafe;
  background: #eef5ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  cursor: pointer;
  overflow: hidden;
}

.resource-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.resource-avatar input {
  display: none;
}

.payment-resource-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(260px, 1fr));
  gap: 18px;
  align-items: start;
}

.payment-resource-grid h3 {
  margin: 0 0 10px;
  color: #1f2937;
  font-size: 15px;
}
.channel-icon-picker{display:flex;align-items:center;justify-content:center;gap:8px}.channel-icon-picker select{min-width:105px;padding:7px;border:1px solid #ddd;border-radius:7px;background:#fff;font-family:inherit}.channel-icon-preview{font-size:23px;line-height:1}.channel-icon-picker small{color:#94a3b8;white-space:nowrap}

input:focus {
  border-color: #007bff;
}

.actions-cell {
  display: flex;
  justify-content: center;
  gap: 12px;
}

.btn-add,
.btn-remove {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  font-size: 20px;
  font-weight: bold;
  border: none;
  color: white;
  cursor: pointer;
  line-height: 0;
  user-select: none;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.3s ease;
}

.btn-add {
  background-color: #28a745;
}

.btn-add:hover {
  background-color: #218838;
}

.btn-remove {
  background-color: #dc3545;
}

.btn-remove:hover {
  background-color: #c82333;
}

.doctor-content .actions-cell {
  flex-wrap: wrap;
}

.doctor-content .btn-add,
.doctor-content .btn-remove {
  width: auto;
  min-width: 70px;
  height: 38px;
  gap: 5px;
  padding: 0 11px;
  border-radius: 11px;
  line-height: 1;
  box-shadow: 0 6px 14px rgba(15, 23, 42, .1);
}

.doctor-content .btn-add span,
.doctor-content .btn-remove span {
  font-size: 19px;
  line-height: 1;
}

.doctor-content .btn-add small,
.doctor-content .btn-remove small {
  color: inherit;
  font-size: 10px;
  font-weight: 900;
}

/* Unified resource actions */
.tabs {
  gap: 8px;
  margin: 0 0 18px;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}

.tabs button {
  height: var(--ui-action-height);
  min-height: var(--ui-action-height);
  padding: 0 15px;
  border: 1px solid var(--ui-action-primary-border);
  border-radius: var(--ui-action-radius);
  background: var(--ui-action-primary-soft);
  color: var(--ui-action-primary);
  font-size: var(--ui-action-font-size);
  font-weight: 900;
  transition: transform 160ms ease, background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.tabs button:hover {
  transform: translateY(-1px);
}

.tabs button.active {
  border-color: var(--ui-action-primary);
  background: var(--ui-action-primary);
  color: #fff;
  box-shadow: var(--ui-action-shadow);
}

.doctor-table-toolbar button,
.doctor-settings-modal-actions button {
  height: var(--ui-action-height);
  min-height: var(--ui-action-height);
  padding: 0 16px;
  border: 1px solid var(--ui-action-primary);
  border-radius: var(--ui-action-radius);
  background: var(--ui-action-primary);
  color: #fff;
  font-size: var(--ui-action-font-size);
  font-weight: 900;
  box-shadow: var(--ui-action-shadow);
  transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.doctor-table-toolbar button:not(:disabled):hover,
.doctor-settings-modal-actions button:not(:disabled):hover {
  background: var(--ui-action-primary-hover);
  transform: translateY(-1px);
}

.btn-add,
.btn-remove {
  width: var(--ui-action-height);
  height: var(--ui-action-height);
  border-radius: var(--ui-action-radius);
  font-size: 20px;
  transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.btn-add {
  background: var(--ui-action-primary);
}

.btn-add:hover {
  background: var(--ui-action-primary-hover);
  transform: translateY(-1px);
}

.btn-remove {
  background: var(--ui-action-danger);
}

.btn-remove:hover {
  background: #b91c1c;
  transform: translateY(-1px);
}

/* روزهای کاری پزشک */
.days-cell {
  min-width: 250px;
  text-align: right;
}

.day-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 62px;
  min-height: 32px;
  gap: 4px;
  margin: 3px;
  padding: 5px 9px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  cursor: pointer;
  transition: .18s ease;
}

.day-check:hover {
  border-color: #93c5fd;
  background: #eff6ff;
  color: #2563eb;
}

.day-check.selected {
  border-color: #60a5fa;
  background: #2563eb;
  color: #fff;
  box-shadow: 0 5px 12px rgba(37, 99, 235, .18);
}

.day-check input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

@media (max-width: 1100px) {
  .doctor-content .actions-cell {
    display: grid;
    grid-template-columns: 105px minmax(0, 1fr);
    justify-content: initial;
  }
  .doctor-content .actions-cell::before { grid-column: 1; }
  .doctor-content .actions-cell .btn-add {
    grid-column: 2;
    justify-self: start;
  }
  .doctor-content .actions-cell .btn-remove {
    grid-column: 2;
    justify-self: start;
  }
  .doctor-content .days-cell {
    min-width: 0;
  }
  .doctor-content .salary-field {
    max-width: 280px;
  }
}

@media (max-width: 600px) {
  .doctor-content .actions-cell {
    grid-template-columns: 1fr;
  }
  .doctor-content .actions-cell::before,
  .doctor-content .actions-cell .btn-add,
  .doctor-content .actions-cell .btn-remove {
    grid-column: 1;
  }
  .doctor-content .actions-cell .btn-add,
  .doctor-content .actions-cell .btn-remove {
    justify-self: stretch;
  }
  .doctor-content .salary-field {
    min-width: 0;
    max-width: none;
  }
  .day-check {
    min-width: 56px;
  }
}

/* Shared resource presentation: doctor, staff and channels */
.resource-content{width:100%;overflow:visible;padding:4px 4px 18px;margin-top:0!important}
.resource-content table{width:100%;min-width:900px;border-collapse:separate;border-spacing:0 10px;background:transparent;box-shadow:none}
.resource-content thead th{padding:8px 14px;border:0;background:transparent;color:#64748b;font-size:12px;font-weight:900;white-space:nowrap}
.resource-content tbody tr{background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.resource-content tbody td{padding:14px;border-color:#eef2f7}
.resource-content tbody td:first-child{border-radius:0 16px 16px 0}
.resource-content tbody td:last-child{border-radius:16px 0 0 16px}
.staff-content table th:nth-child(3),.staff-content table td:nth-child(3){width:360px;min-width:360px}
.channels-content table th:nth-child(3),.channels-content table td:nth-child(3){width:390px;min-width:390px}
.channel-card-icon{width:48px;height:48px;display:grid;place-items:center;margin:auto;border:1px solid #dbeafe;border-radius:14px;background:#eff6ff;font-size:23px}
.channel-card-name{display:block;color:#1e293b;font-size:13px;text-align:right}
.channel-settings-btn .doctor-settings-icon{background:#7c3aed}
.channel-settings-btn>b{background:#ede9fe;color:#6d28d9}
.channel-settings-modal{width:min(650px,100%)}
.channel-modal-form{display:grid;grid-template-columns:1fr 180px;gap:14px}
.channel-modal-form label{display:grid;gap:7px;color:#334155;font-size:11px;font-weight:900}
.channel-modal-form input,.channel-modal-form select{width:100%;height:42px;padding:0 12px;border:1px solid #dbe3ed;border-radius:11px;background:#fff;font-family:inherit;text-align:right}
.channel-modal-preview{grid-column:1/-1;display:flex;align-items:center;gap:12px;padding:14px;border:1px solid #ddd6fe;border-radius:14px;background:#faf5ff}
.channel-modal-preview span{width:48px;height:48px;display:grid;place-items:center;border-radius:13px;background:#7c3aed;color:#fff;font-size:23px}
.channel-modal-preview strong{color:#4c1d95;font-size:13px}
@media(max-width:760px){.channel-modal-form{grid-template-columns:1fr}.resource-content{overflow-x:auto}.staff-content table th:nth-child(3),.staff-content table td:nth-child(3),.channels-content table th:nth-child(3),.channels-content table td:nth-child(3){width:300px;min-width:300px}}</style>
