<template>
  <template v-if="isCentralApp">
    <CentralAdmin />
    <a v-if="showLocalClinicShortcut" class="central-clinic-shortcut" :href="localClinicUrl">
      ورود به محیط کلینیک
    </a>
  </template>

  <div v-else-if="authLoading" class="auth-boot" dir="rtl">
    <span class="auth-boot-spinner"></span>
    در حال بررسی ورود...
  </div>

  <Login v-else-if="!user" @authenticated="handleAuthenticated" />

  <div v-else id="app" :class="{ dark: isDark }">

    <button
      class="utility-menu-toggle"
      type="button"
      :class="{ active: uiMenuOpen }"
      :aria-expanded="uiMenuOpen"
      aria-controls="utility-menu-panel"
      aria-label="منوی کاربری"
      title="منوی کاربری"
      @click="uiMenuOpen = !uiMenuOpen"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 7h16M4 12h16M4 17h16"/>
      </svg>
    </button>

    <button v-if="uiMenuOpen" class="utility-menu-backdrop" type="button" aria-label="بستن منوی کاربری" @click="uiMenuOpen = false"></button>

    <aside v-if="uiMenuOpen" id="utility-menu-panel" class="utility-menu-panel" dir="rtl">
      <header>
        <button class="auth-avatar" type="button" :style="{ background: userAvatarUrl ? '#fff' : menuAvatarColor }" title="ویرایش پروفایل" @click="openProfileEditor">
          <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user.name || 'کاربر'">
          <span v-else>{{ userInitial }}</span>
        </button>
        <div class="auth-user-info">
          <strong>{{ user.name }}</strong>
          <small>{{ userContactText }}</small>
          <em>{{ user.roles?.join('، ') || 'بدون نقش' }}</em>
        </div>
      </header>

      <button type="button" @click="openProfileEditor">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
        <span>ویرایش پروفایل</span>
      </button>
      <button type="button" @click="uiMenuOpen = false; openMyReport()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V10M12 20V4M19 20v-7"/><path d="M3 20h18"/></svg>
        <span>گزارش شخصی</span>
      </button>
      <button type="button" @click="uiMenuOpen = false; toggleDark()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="4"/></svg>
        <span>{{ isDark ? 'حالت روشن' : 'حالت شب' }}</span>
      </button>
      <button v-if="isClinicManager" type="button" @click="openServiceStatusPage">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/><path d="M7 5v4M12 10v4M17 15v4"/></svg>
        <span>سرویس‌ها</span>
      </button>
      <button v-if="canViewActivityLogs" type="button" @click="uiMenuOpen = false; changePage('ActivityLogs')">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 18 20H6a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 6 4Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
        <span>سوابق</span>
      </button>
      <button v-if="isClinicManager" type="button" @click="uiMenuOpen = false; changePage('Store')">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/><path d="M9 13h6"/></svg>
        <span>فروشگاه</span>
      </button>
      <button v-if="isClinicManager" type="button" @click="uiMenuOpen = false; changePage('Setting')">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.8 1.8 0 0 0 15 19.4a1.8 1.8 0 0 0-1 .6l-.04.08a2 2 0 0 1-3.84 0L10.08 20A1.8 1.8 0 0 0 9 19.4a1.8 1.8 0 0 0-1.98.36l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.8 1.8 0 0 0 4.6 15a1.8 1.8 0 0 0-.6-1l-.08-.04a2 2 0 0 1 0-3.84L4 10.08A1.8 1.8 0 0 0 4.6 9a1.8 1.8 0 0 0-.36-1.98l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.8 1.8 0 0 0 9 4.6a1.8 1.8 0 0 0 1-.6l.04-.08a2 2 0 0 1 3.84 0L13.92 4A1.8 1.8 0 0 0 15 4.6a1.8 1.8 0 0 0 1.98-.36l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.8 1.8 0 0 0 19.4 9a1.8 1.8 0 0 0 .6 1l.08.04a2 2 0 0 1 0 3.84L20 13.92a1.8 1.8 0 0 0-.6 1.08Z"/></svg>
        <span>تنظیمات</span>
      </button>
      <button class="utility-logout" type="button" @click="uiMenuOpen = false; logout()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H6.8A1.8 1.8 0 0 0 5 6.8v10.4A1.8 1.8 0 0 0 6.8 19H10"/><path d="M14 8l4 4-4 4M9 12h9"/></svg>
        <span>خروج از حساب</span>
      </button>
    </aside>

    <div v-if="myReportOpen" class="my-report-overlay" dir="rtl" @click.self="myReportOpen = false">
      <section class="my-report-modal">
        <header>
          <div><small>گزارش شخصی</small><h2>{{ myReport?.name || user.name }}</h2></div>
          <button type="button" @click="myReportOpen = false">×</button>
        </header>
        <div class="my-report-month-nav">
          <button type="button" title="ماه قبل" @click="shiftReportMonth(-1)">‹</button>
          <strong>{{ personalReportMonthLabel }}</strong>
          <button type="button" title="ماه بعد" :disabled="reportMonth >= currentJalaliMonth" @click="shiftReportMonth(1)">›</button>
        </div>
        <div v-if="myReportLoading" class="my-report-loading"><i></i><span>در حال محاسبه گزارش...</span></div>
        <div v-else-if="myReportError" class="my-report-error">{{ myReportError }}</div>
        <div v-else-if="myReport" class="my-report-grid">
          <article class="earned"><span>مبلغ نهایی این ماه</span><strong>{{ formatReportMoney(myReport.total_earned) }}</strong><small>تومان</small></article>
          <article><span>حقوق ثابت</span><strong>{{ formatReportMoney(myReport.salary) }}</strong><small>تومان</small></article>
          <article><span>پورسانت</span><strong>{{ formatReportMoney(myReport.commission) }}</strong><small>تومان</small></article>
          <article><span>تعدیلات حضور و غیاب</span><strong>{{ formatReportMoney(myReport.attendance_adjustment) }}</strong><small>تومان</small></article>
          <article><span>اضافه‌کاری</span><strong>{{ Number(myReport.overtime_hours || 0).toLocaleString('fa-IR') }}</strong><small>ساعت</small></article>
          <article><span>تعداد وقت ثبت‌شده</span><strong>{{ Number(myReport.appointments_given || 0).toLocaleString('fa-IR') }}</strong><small>نوبت</small></article>
        </div>
      </section>
    </div>

    <Menu
      :currentPage="currentPage"
      :permissions="user.permissions || []"
      :roles="user.roles || []"
      :attendance-enabled="attendanceEnabled"
      :enabled-features="tenantEnabledFeatures"
      @select="changePage"
      @close-all="closeAllPages"
    />

    <!-- صفحات -->
    <div class="page-container">

      <!-- پیشفرض -->
      <Saranj v-if="currentPage === null" :current-user="user" />

      <!-- صفحات -->
      <section v-if="currentPage === 'Profile'" class="profile-page" dir="rtl">
        <section class="profile-editor-modal">
          <header>
            <div><small>پروفایل کاربری</small><h2>ویرایش پروفایل</h2></div>
          </header>
          <div class="profile-editor-body">
            <label class="profile-editor-avatar" :style="{ background: userAvatarUrl ? '#fff' : menuAvatarColor }">
              <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user.name || 'کاربر'">
              <span v-else>{{ userInitial }}</span>
              <input type="file" accept="image/*" @change="uploadCurrentUserPhoto">
            </label>
            <div>
              <strong>{{ user.name }}</strong>
              <small>{{ userContactText || 'بدون اطلاعات تماس' }}</small>
            </div>
          </div>
          <div class="profile-editor-fields">
            <label><span>نام کاربر</span><input v-model.trim="profileForm.name" type="text"></label>
            <label><span>نام مستعار</span><input v-model.trim="profileForm.nickname" type="text"></label>
            <label><span>موبایل</span><input v-model.trim="profileForm.mobile" type="text" inputmode="tel"></label>
            <label>
              <span>جنسیت</span>
              <div class="profile-gender-picker">
                <button type="button" :class="{ active: profileForm.gender === 'female' }" @click="profileForm.gender = profileForm.gender === 'female' ? '' : 'female'">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4.2"/><path d="M12 12.2v7.3"/><path d="M8.7 16.5h6.6"/></svg>
                </button>
                <button type="button" :class="{ active: profileForm.gender === 'male' }" @click="profileForm.gender = profileForm.gender === 'male' ? '' : 'male'">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="15" r="4.2"/><path d="M12 12 19 5"/><path d="M14.6 5H19v4.4"/></svg>
                </button>
              </div>
            </label>
            <label class="profile-password-field">
              <span>رمز عبور جدید</span>
              <input :type="profilePasswordVisible ? 'text' : 'password'" v-model="profileForm.password" placeholder="برای تغییر رمز وارد کنید">
              <button type="button" @click="profilePasswordVisible = !profilePasswordVisible">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 12s3.5-6.25 9.75-6.25S21.75 12 21.75 12 18.25 18.25 12 18.25 2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </label>
          </div>
          <footer>
            <label class="profile-upload-btn">
              تغییر عکس
              <input type="file" accept="image/*" @change="uploadCurrentUserPhoto">
            </label>
            <button type="button" class="profile-remove-btn" :disabled="!userAvatarUrl" @click="removeCurrentUserPhoto">حذف عکس</button>
            <button type="button" class="profile-save-btn" :disabled="profileSaving" @click="saveProfile">
              {{ profileSaving ? 'در حال ذخیره...' : 'ذخیره پروفایل' }}
            </button>
          </footer>
        </section>
      </section>

      <Parvande
        v-if="currentPage === 'Parvande'"
        :permissions="user.permissions || []"
        :open-patient-request="pendingPatientProfileRequest"
        :enabled-features="tenantEnabledFeatures"
        @open-page="changePage"
        @open-beauty-record="openBeautyRecordFromPatient"
        @open-followups="openFollowupsFromPatient"
      />

      <Photos v-if="currentPage === 'Photos'" :permissions="user.permissions || []" />

      <Time
        v-if="currentPage === 'Vaghtdahi'"
        :permissions="user.permissions || []"
        :open-view-request="pendingAppointmentViewRequest"
        @open-patient-profile="openPatientProfileFromAppointment"
        @followup-appointment-created="handleFollowupAppointmentCreated"
      />

      <FlwUp
        v-if="currentPage === 'Peygiri'"
        :permissions="user.permissions || []"
        :appointment-result="pendingFollowupAppointmentResult"
        :open-followup-request="pendingFollowupOpenRequest"
        @open-appointments-timeline="openAppointmentsTimeline"
      />

      <Notif
        v-if="legacyLeadsEnabled && currentPage === 'Notif'"
        @open-beauty-record="openBeautyRecordFromNotification"
        @open-inventory="openInventoryFromNotification"
        @open-ticket="openTicketFromNotification"
        @open-appointments="openAppointmentsFromNotification"
        @open-patient-profile="openPatientProfileFromNotification"
        @open-followups="openFollowupsFromNotification"
        @open-attendance="openAttendanceFromNotification"
        @open-reports="openReportsFromNotification"
        @open-photos="openPhotosFromNotification"
      />

      <Gozaresh v-if="currentPage === 'Gozaresh'" :permissions="user.permissions || []" />
      <ActivityLogs v-if="currentPage === 'ActivityLogs'" :permissions="user.permissions || []" />

      <Anbar ref="inventory" v-if="currentPage === 'Anbar'" />

      <Dermatracker
        v-if="currentPage === 'dermatracker'"
        :permissions="user.permissions || []"
        :open-patient-request="pendingBeautyRecordRequest"
        @back-to-patient-profile="openPatientProfileFromBeauty"
        @open-patient-media="openPatientMediaFromBeauty"
      />

      <Ticket v-if="currentPage === 'Ticket'" :permissions="user.permissions || []" />

      <Products v-if="currentPage === 'Products'" />

      <!-- هزینه‌ها -->
      <Bills v-if="currentPage === 'Bills'" />

      <!-- حضور غیاب -->
      <HRtimes
        v-if="currentPage === 'HRtimes' && attendanceEnabled"
        :permissions="user.permissions || []"
        :current-user="user"
      />

      <PayrollSettlement v-if="currentPage === 'Payroll'" />

      <!-- تنظیمات -->
      <Setting v-if="currentPage === 'Setting'" :current-user="user" :enabled-features="tenantEnabledFeatures" />
      <Store
        v-if="currentPage === 'Store'"
        :enabled-features="tenantEnabledFeatures"
        :initial-module-key="pendingStoreModuleKey"
      />
      <ServiceTickets v-if="currentPage === 'ServiceTickets'" @back="currentPage = 'ServiceStatus'" />

      <section v-if="currentPage === 'ServiceStatus'" class="service-status-page" dir="rtl">
        <header class="service-page-hero">
          <div>
            <small>وضعیت سرویس کلینیک</small>
            <h1>{{ serviceTenantName }}</h1>
            <p>جزئیات سرویس، ماژول‌های فعال، زمان باقی‌مانده و شارژ پیامک در این صفحه دیده می‌شود.</p>
          </div>
          <button type="button" @click="currentPage = null">بازگشت</button>
        </header>

        <div class="service-page-grid">
          <section class="service-status-card service-status-card-main">
            <div>
              <span>سرویس فعال</span>
              <strong>{{ servicePlanName }}</strong>
              <small>{{ serviceStatusLabel }}</small>
            </div>
            <b>{{ serviceDaysRemainingLabel }}</b>
          </section>

          <article class="service-metric-card">
            <span>تاریخ اتمام</span>
            <strong>{{ serviceExpiresAtLabel }}</strong>
          </article>
          <article class="service-metric-card">
            <span>شارژ پنل پیامک</span>
            <strong>{{ smsBalanceLabel }}</strong>
          </article>
          <article class="service-metric-card">
            <span>ماژول‌های فعال</span>
            <strong>{{ activeModuleNames.length.toLocaleString('fa-IR') }} مورد</strong>
          </article>
        </div>

        <section class="service-modules-card service-page-card">
          <div class="service-card-head">
            <strong>امکانات فعال برای این کلینیک</strong>
            <span>{{ activeModuleNames.length.toLocaleString('fa-IR') }} ماژول</span>
          </div>
          <div v-if="activeModuleNames.length" class="service-module-list">
            <span v-for="module in activeModuleNames" :key="module">{{ module }}</span>
          </div>
          <p v-else>هیچ ماژول خریداری‌شده‌ای برای این کلینیک فعال نیست.</p>
        </section>

        <section class="service-modules-card service-page-card">
          <div class="service-card-head">
            <strong>ماژول‌های قابل خرید</strong>
            <span>{{ purchasableModules.length.toLocaleString('fa-IR') }} مورد</span>
          </div>
          <div v-if="purchasableModules.length" class="service-purchase-list">
            <article v-for="module in purchasableModules" :key="module.id">
              <div>
                <strong>{{ module.name }}</strong>
                <small>{{ formatServicePrice(module.price) }}</small>
              </div>
              <button type="button" @click="buyServiceModule(module.id)">خرید ماژول</button>
            </article>
          </div>
          <p v-else>همه ماژول‌های قابل خرید برای این کلینیک فعال شده‌اند.</p>
        </section>

        <section class="service-question-card">
          <div>
            <small>ارتباط با مدیر کل سیستم</small>
            <h2>سوالات و پشتیبانی سرویس</h2>
            <p>سوال‌ها، پاسخ‌ها، وضعیت پیگیری و فایل‌های ضمیمه را از صفحه پشتیبانی سرویس مدیریت کن.</p>
          </div>
          <footer>
            <span>برای ثبت سوال جدید یا دیدن جواب‌ها وارد پشتیبانی سرویس شو.</span>
            <button type="button" @click="currentPage = 'ServiceTickets'">ورود به پشتیبانی</button>
          </footer>
        </section>
      </section>

    </div>

  </div>
</template>

<script>

import axios from "axios";
import Swal from "sweetalert2";
import { CENTRAL_MODULES } from "./central/data/modules";

import Login from "./components/Login.vue";
import CentralAdmin from "./components/CentralAdmin.vue";

import Menu from "./components/menu.vue";

import Notif from "./components/Notif.vue";

import Saranj from "./components/saranj.vue";

import Parvande from "./components/parvande.vue";
import Photos from "./components/Photos.vue";

import Time from "./components/Time.vue";

import Anbar from "./components/anbar.vue";

import FlwUp from "./components/flwup.vue";

import Gozaresh from "./components/gozaresh.vue";
import ActivityLogs from "./components/ActivityLogs.vue";

import Dermatracker from "./components/dermatracker.vue";

import Ticket from "./components/ticket.vue";

import Products from "./components/products.vue";

import Bills from "./components/Bills.vue";

import Setting from "./components/Setting.vue";
import Store from "./components/Store.vue";
import ServiceTickets from "./components/ServiceTickets.vue";

import HRtimes from "./components/HRtimes.vue";
import PayrollSettlement from "./components/PayrollSettlement.vue";
import { startPresence, stopPresence } from "./services/presence";

const centralDomains = (import.meta.env.VITE_CENTRAL_DOMAINS || "localhost,127.0.0.1,admin.s8n.ir")
  .split(",")
  .map((domain) => domain.trim().toLowerCase())
  .filter(Boolean);
const LAST_CLINIC_PAGE_KEY = "clinic:last-active-page:v1";

export default {

  name: "App",

  components: {

    Login,
    CentralAdmin,

    Menu,

    Notif,

    Saranj,

    Parvande,
    Photos,

    Time,

    Anbar,

    FlwUp,

    Gozaresh,
    ActivityLogs,

    Dermatracker,

    Ticket,

    Products,

    Bills,

    Setting,
    Store,
    ServiceTickets,

    HRtimes,
    PayrollSettlement

  },

  data() {

    return {

      currentPage: null,

      isDark: false,

      authLoading: true,

      user: null,

      pendingPatientProfileRequest: null
      ,pendingBeautyRecordRequest: null
      ,pendingAppointmentViewRequest: null
      ,pendingFollowupAppointmentResult: null
      ,pendingFollowupOpenRequest: null
      ,authNoticeOpen: false
      ,profileSaving: false
      ,profilePasswordVisible: false
      ,profileForm: { name: "", nickname: "", mobile: "", gender: "", password: "" }
      ,myReportOpen: false
      ,myReportLoading: false
      ,myReport: null
      ,myReportError: ""
      ,reportMonth: ""
      ,pendingStoreModuleKey: ""
      // حضور و غیاب فقط بر اساس ماژول اشتراک مجموعه کنترل می‌شود.
      ,attendanceEnabled: true
      ,uiMenuOpen: false
      ,legacyLeadsEnabled: false
      ,isCentralApp: centralDomains.includes(window.location.hostname.toLowerCase())
      ,backGuardActive: false
      ,allowBrowserBack: false

    };

  },

  mounted() {

    window.addEventListener("app:auth-expired", this.handleAuthExpired);
    window.addEventListener("app:open-appointments-timeline", this.handleOpenAppointmentsTimelineEvent);
    if (this.isCentralApp) {
      document.body.classList.add("central-host");
      this.authLoading = false;
      return;
    }
    this.installBrowserBackGuard();
    this.checkAuth();

    const saved = localStorage.getItem("darkMode");

    if (saved === "true") {

      this.isDark = true;

      document.body.classList.add("dark");

    }

  },

  beforeUnmount() {
    stopPresence();
    document.body.classList.remove("central-host");
    window.removeEventListener("app:auth-expired", this.handleAuthExpired);
    window.removeEventListener("app:open-appointments-timeline", this.handleOpenAppointmentsTimelineEvent);
    window.removeEventListener("popstate", this.handleBrowserBack);
    window.removeEventListener("beforeunload", this.handleBeforeUnload);
  },

  watch: {

    isDark(newVal) {

      localStorage.setItem("darkMode", newVal);

      if (newVal) {

        document.body.classList.add("dark");

      }

      else {

        document.body.classList.remove("dark");

      }

    },

    currentPage(newPage) {
      this.rememberLastClinicPage(newPage);
    }

  },

  computed: {
    showLocalClinicShortcut() {
      return ["localhost", "127.0.0.1"].includes(window.location.hostname.toLowerCase());
    },

    localClinicUrl() {
      const port = window.location.port ? `:${window.location.port}` : "";
      return `${window.location.protocol}//clinic1.localhost${port}/`;
    },

    tenantEnabledFeatures() {
      const features = this.user?.tenant?.module_ids;
      if (!Array.isArray(features)) return null;
      const aliases = {
        chat: 'patients',
        staffEval: 'resources',
        tasks: 'followups',
        campaign: 'automation',
        aiReport: 'beauty',
        appointments: 'booking',
        appointment: 'booking',
        time: 'booking',
        Vaghtdahi: 'booking',
        shop: 'online_store',
        store: 'online_store',
      };
      return features.map((feature) => aliases[feature] || feature);
    },

    attendanceModuleEnabled() {
      return !Array.isArray(this.tenantEnabledFeatures) || this.tenantEnabledFeatures.includes('attendance');
    },

    isClinicManager() {
      const allowed = ["مدیر کل", "مدیر سیستم", "super admin", "super-admin"];
      return (this.user?.roles || []).some(role => allowed.includes(String(role).trim().toLowerCase()));
    },

    canViewActivityLogs() {
      return this.user?.permissions?.includes("activity_logs.view");
    },

    userAvatarUrl() {
      return this.user?.avatar_url || this.user?.profile_thumbnail_url || this.user?.profile_photo_url || "";
    },

    userInitial() {
      return String(this.user?.name || "کاربر").trim().slice(0, 1) || "ک";
    },

    userContactText() {
      return this.user?.email || this.user?.mobile || this.user?.phone || "";
    },

    userAvatarColor() {
      const colors = ["#1a73e8", "#d93025", "#188038", "#f29900", "#9334e6", "#00897b", "#c5221f", "#5f6368"];
      const name = String(this.user?.name || "کاربر");
      const sum = [...name].reduce((total, char) => total + char.charCodeAt(0), 0);
      return colors[sum % colors.length];
    },

    menuAvatarColor() {
      return "#d11f1f";
    },

    currentJalaliMonth() {
      const parts = Object.fromEntries(new Intl.DateTimeFormat("en-US-u-ca-persian-nu-latn", { year: "numeric", month: "2-digit" })
        .formatToParts(new Date()).filter(part => part.type === "year" || part.type === "month").map(part => [part.type, part.value]));
      return `${parts.year}-${String(parts.month).padStart(2, "0")}`;
    },
    personalReportMonthLabel() {
      if (!this.reportMonth) return "";
      const names = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
      const [year, month] = this.reportMonth.split("-").map(Number);
      return `${names[month - 1]} ${Number(year).toLocaleString("fa-IR", { useGrouping: false })}`;
    },

    serviceTenantName() {
      return this.user?.tenant?.name || this.user?.tenant?.id || "کلینیک";
    },

    servicePlanName() {
      return this.user?.tenant?.plan?.name || "بدون پلن ثبت‌شده";
    },

    serviceStatusLabel() {
      return (this.user?.tenant?.status || "active") === "active" ? "فعال" : "غیرفعال";
    },

    serviceDaysRemainingLabel() {
      const days = this.user?.tenant?.plan?.days_remaining;
      if (days === null || days === undefined) return "نامشخص";
      return days > 0 ? `${Number(days).toLocaleString("fa-IR")} روز مانده` : "منقضی شده";
    },

    serviceExpiresAtLabel() {
      const value = this.user?.tenant?.plan?.expires_at;
      if (!value) return "ثبت نشده";
      return new Date(`${value}T12:00:00`).toLocaleDateString("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    },

    smsBalanceLabel() {
      const value = this.user?.tenant?.sms_balance;
      if (value === null || value === undefined || value === "") return "ثبت نشده";
      const amount = Number(value);
      return Number.isFinite(amount) ? `${amount.toLocaleString("fa-IR")} تومان` : String(value);
    },

    activeModuleNames() {
      const ids = Array.isArray(this.tenantEnabledFeatures) ? this.tenantEnabledFeatures : [];
      return ids.map((id) => CENTRAL_MODULES.find((module) => module.id === id)?.name || id);
    },

    purchasableModules() {
      const activeIds = Array.isArray(this.tenantEnabledFeatures) ? this.tenantEnabledFeatures : [];
      return CENTRAL_MODULES.filter((module) => !activeIds.includes(module.id));
    }
  },

  methods: {
    installBrowserBackGuard() {
      if (this.backGuardActive) return;
      this.backGuardActive = true;
      window.history.replaceState({ appGuard: true }, "", window.location.href);
      window.history.pushState({ appGuard: true }, "", window.location.href);
      window.addEventListener("popstate", this.handleBrowserBack);
      window.addEventListener("beforeunload", this.handleBeforeUnload);
    },

    handleBrowserBack() {
      if (!this.user || this.allowBrowserBack) return;
      const hasUnsavedInventoryChanges = this.currentPage === 'Anbar' && this.$refs.inventory?.hasUnsavedChanges;
      const confirmed = window.confirm(hasUnsavedInventoryChanges
        ? "تغییرات انبار ذخیره نشده است. مطمئنی می‌خواهی صفحه را ترک کنی؟"
        : "مطمئنی می‌خواهی از سیستم خارج شوی یا صفحه را ترک کنی؟");
      if (confirmed) {
        this.allowBrowserBack = true;
        window.history.back();
        return;
      }
      window.history.pushState({ appGuard: true }, "", window.location.href);
    },

    handleBeforeUnload(event) {
      if (!this.user || window.__intentionalLogout || this.allowBrowserBack) return;
      event.preventDefault();
      event.returnValue = "";
    },

    openServiceStatusPage() {
      if (!this.isClinicManager) return;
      this.uiMenuOpen = false;
      this.currentPage = "ServiceStatus";
    },

    formatServicePrice(value) {
      if (!Number(value)) return "رایگان";
      return `${Number(value).toLocaleString("fa-IR")} تومان`;
    },

    buyServiceModule(moduleId) {
      this.pendingStoreModuleKey = moduleId;
      this.currentPage = "Store";
      window.setTimeout(() => {
        this.pendingStoreModuleKey = "";
      }, 500);
    },

    openMyReport() {
      this.reportMonth = this.currentJalaliMonth;
      this.myReportOpen = true;
      this.loadMyReport();
    },

    async loadMyReport() {
      this.myReportLoading = true;
      this.myReportError = "";
      try {
        const { data } = await axios.get("/api/personal-report", { params: { month: this.reportMonth } });
        this.myReport = data;
      } catch (error) {
        this.myReport = null;
        this.myReportError = error.response?.data?.message || "این قسمت برای پزشکان و کارمندان می‌باشد؛ با حساب آن‌ها وارد شوید تا بتوانید گزارش را مشاهده کنید.";
      } finally {
        this.myReportLoading = false;
      }
    },

    shiftReportMonth(delta) {
      const [year, month] = this.reportMonth.split("-").map(Number);
      let nextYear = year;
      let nextMonth = month + delta;
      if (nextMonth < 1) { nextMonth = 12; nextYear -= 1; }
      if (nextMonth > 12) { nextMonth = 1; nextYear += 1; }
      const next = `${nextYear}-${String(nextMonth).padStart(2, "0")}`;
      if (next > this.currentJalaliMonth) return;
      this.reportMonth = next;
      this.loadMyReport();
    },

    formatReportMoney(value) {
      return Number(value || 0).toLocaleString("fa-IR");
    },

    async checkAuth() {
      try {
        const { data } = await axios.get("/api/auth/user");
        this.user = data.user;
        startPresence(this.user);
        await this.loadAttendanceStatus();
        this.restoreLastClinicPage();
        this.consumeFollowupAppointmentIntent();
      } catch (error) {
        if (error.response?.status !== 401) {
          console.error("خطا در بررسی ورود", error);
        }
        this.user = null;
      } finally {
        this.authLoading = false;
      }
    },

    handleAuthenticated(user) {
      this.user = user;
      startPresence(user);
      this.currentPage = null;
      window.__intentionalLogout = false;
      this.loadAttendanceStatus();
    },

    lastClinicPageStorageKey() {
      const host = window.location.hostname.toLowerCase();
      return `${LAST_CLINIC_PAGE_KEY}:${host}:${this.user?.id || 'guest'}`;
    },

    rememberLastClinicPage(page) {
      if (this.isCentralApp || !this.user) return;
      try {
        localStorage.setItem(this.lastClinicPageStorageKey(), page || '__home__');
      } catch {
        // در صورت محدود بودن فضای مرورگر، نمایش صفحه مختل نشود.
      }
    },

    canRestoreClinicPage(page) {
      if (!page || page === '__home__') return true;

      const featureMap = {
        Parvande: 'patients', Vaghtdahi: 'booking', Peygiri: 'followups',
        dermatracker: 'beauty', Photos: 'gallery', Gozaresh: 'report',
        Anbar: 'inventory', Ticket: 'tickets', Products: 'finder',
        Automation: 'automation', Bills: 'bills', HRtimes: 'attendance', Setting: 'settings'
      };
      const permissionMap = {
        Parvande: 'patients.view', Photos: 'photos.view', Vaghtdahi: 'appointments.view',
        Peygiri: 'followups.view', Gozaresh: 'reports.view', Anbar: 'inventory.view',
        dermatracker: 'beauty.view', Ticket: 'tickets.view', Products: 'services.view',
        Bills: 'bills.view', HRtimes: 'attendance.view', Payroll: 'payroll.view',
        ActivityLogs: 'activity_logs.view'
      };
      const feature = featureMap[page];
      if (Array.isArray(this.tenantEnabledFeatures) && feature && !this.tenantEnabledFeatures.includes(feature)) return false;
      if (page === 'Setting' || page === 'Store' || page === 'ServiceStatus' || page === 'ServiceTickets') return this.isClinicManager;
      if (page === 'HRtimes' && !this.attendanceEnabled) return false;
      if (page === 'Payroll') return ['payroll.view', 'reports.financial', 'reports.staff', 'reports.doctors'].some(permission => this.user?.permissions?.includes(permission));
      return !permissionMap[page] || this.user?.permissions?.includes(permissionMap[page]);
    },

    restoreLastClinicPage() {
      if (this.isCentralApp || !this.user) return;
      try {
        const savedPage = localStorage.getItem(this.lastClinicPageStorageKey());
        if (savedPage && savedPage !== '__home__' && this.canRestoreClinicPage(savedPage)) {
          this.currentPage = savedPage;
        }
      } catch {
        // نبودن دسترسی localStorage نباید جلوی ورود به سامانه را بگیرد.
      }
    },

    consumeFollowupAppointmentIntent() {
      const url = new URL(window.location.href);
      const token = url.searchParams.get('followupAppointment');
      if (!token || !this.user?.permissions?.includes('appointments.view')) return;
      try {
        const raw = localStorage.getItem(token);
        const payload = raw ? JSON.parse(raw) : null;
        if (!payload?.followup) return;
        localStorage.removeItem(token);
        this.pendingAppointmentViewRequest = {
          view: 'timeline',
          date: payload.date || '',
          followup: payload.followup,
          requestedAt: Date.now()
        };
        this.currentPage = 'Vaghtdahi';
        url.searchParams.delete('followupAppointment');
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
      } catch (error) {
        console.warn('دریافت درخواست نوبت‌دهی پیگیری انجام نشد.', error);
      }
    },

    async loadAttendanceStatus() {
      this.attendanceEnabled = this.attendanceModuleEnabled;
      if (!this.attendanceEnabled && this.currentPage === "HRtimes") this.currentPage = null;
    },

    async makeSquareWebp(file, size, quality, fileName) {
      const image = new Image();
      const url = URL.createObjectURL(file);
      try {
        await new Promise((resolve, reject) => {
          image.onload = resolve;
          image.onerror = reject;
          image.src = url;
        });
        const canvas = document.createElement("canvas");
        canvas.width = size;
        canvas.height = size;
        const context = canvas.getContext("2d");
        const side = Math.min(image.width, image.height);
        const sx = (image.width - side) / 2;
        const sy = (image.height - side) / 2;
        context.drawImage(image, sx, sy, side, side, 0, 0, size, size);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, "image/webp", quality));
        return new File([blob], fileName, { type: "image/webp" });
      } finally {
        URL.revokeObjectURL(url);
      }
    },

    async uploadCurrentUserPhoto(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (!file) return;
      try {
        const formData = new FormData();
        formData.append("photo", await this.makeSquareWebp(file, 512, 0.72, "photo.webp"));
        formData.append("thumbnail", await this.makeSquareWebp(file, 50, 0.48, "thumbnail.webp"));
        const { data } = await axios.post("/api/auth/user/photo", formData, {
          headers: { "Accept": "application/json" }
        });
        if (data.user) this.user = { ...this.user, ...data.user };
        await Swal.fire({ icon: "success", title: "عکس ذخیره شد", timer: 1300, showConfirmButton: false });
      } catch (error) {
        await Swal.fire({ icon: "error", title: "خطا", text: error.response?.data?.message || "آپلود عکس انجام نشد." });
      }
    },

    openProfileEditor() {
      this.uiMenuOpen = false;
      this.profileForm = {
        name: this.user?.name || "",
        nickname: this.user?.nickname || "",
        mobile: this.user?.mobile || "",
        gender: this.user?.gender || "",
        password: ""
      };
      this.profilePasswordVisible = false;
      this.currentPage = "Profile";
    },

    async saveProfile() {
      if (this.profileSaving) return;
      this.profileSaving = true;
      try {
        const payload = {
          name: this.profileForm.name || "",
          nickname: this.profileForm.nickname || "",
          mobile: this.profileForm.mobile || "",
          gender: this.profileForm.gender || "",
          password: this.profileForm.password || ""
        };
        const { data } = await axios.put("/api/auth/user", payload, {
          headers: { "Accept": "application/json" }
        });
        if (data.user) this.user = { ...this.user, ...data.user };
        this.profileForm.password = "";
        await Swal.fire({ icon: "success", title: "پروفایل ذخیره شد", timer: 1400, showConfirmButton: false });
      } catch (error) {
        const errors = error.response?.data?.errors;
        const firstError = errors ? Object.values(errors).flat()[0] : null;
        await Swal.fire({ icon: "error", title: "خطا", text: firstError || error.response?.data?.message || "ذخیره پروفایل انجام نشد." });
      } finally {
        this.profileSaving = false;
      }
    },

    async removeCurrentUserPhoto() {
      if (!this.userAvatarUrl) return;
      const result = await Swal.fire({
        icon: "warning",
        title: "حذف عکس پروفایل؟",
        text: "بعد از حذف، آواتار پیش‌فرض با حرف اول نام نمایش داده می‌شود.",
        showCancelButton: true,
        confirmButtonText: "بله، حذف شود",
        cancelButtonText: "انصراف",
        confirmButtonColor: "#dc2626",
        cancelButtonColor: "#64748b"
      });
      if (!result.isConfirmed) return;
      try {
        const { data } = await axios.delete("/api/auth/user/photo", { headers: { "Accept": "application/json" } });
        if (data.user) this.user = { ...this.user, ...data.user };
        await Swal.fire({ icon: "success", title: "عکس حذف شد", timer: 1300, showConfirmButton: false });
      } catch (error) {
        await Swal.fire({ icon: "error", title: "خطا", text: error.response?.data?.message || "حذف عکس انجام نشد." });
      }
    },

    async handleAuthExpired() {
      if (this.authNoticeOpen || !this.user) return;
      this.authNoticeOpen = true;
      stopPresence();
      this.user = null;
      this.currentPage = null;
      await Swal.fire({
        icon: "info",
        title: "از حساب خارج شدید",
        text: "نشست شما پایان یافته است. برای ادامه دوباره وارد حساب شوید.",
        confirmButtonText: "متوجه شدم",
        allowOutsideClick: false,
        allowEscapeKey: false
      });
      this.authNoticeOpen = false;
    },

    async logout() {
      window.__intentionalLogout = true;
      stopPresence();
      this.user = null;
      this.currentPage = null;
      try {
        await axios.post("/logout");
      } catch (error) {
        if (![401, 419].includes(error.response?.status)) console.error("خطا در خروج", error);
      }
      await Swal.fire({
        icon: "success",
        title: "با موفقیت خارج شدید",
        text: "برای ورود دوباره از فرم ورود استفاده کنید.",
        confirmButtonText: "متوجه شدم",
        timer: 2200,
        timerProgressBar: true
      });
      window.__intentionalLogout = false;
    },

    toggleDark() {

      this.isDark = !this.isDark;

    },

    async confirmLeavingInventory(nextPage) {
      if (this.currentPage !== 'Anbar' || nextPage === 'Anbar' || !this.$refs.inventory?.hasUnsavedChanges) return true;
      const result = await Swal.fire({
        icon: 'warning',
        title: 'تغییرات انبار ذخیره نشده است',
        text: 'اگر ادامه دهید، تغییرات ثبت‌نشده از بین می‌روند.',
        showCancelButton: true,
        confirmButtonText: 'خروج بدون ذخیره',
        cancelButtonText: 'بازگشت و ذخیره',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#0f766e'
      });
      return result.isConfirmed;
    },

    async changePage(menuValue) {
      if (menuValue === "Notif") {
        menuValue = "Peygiri";
      }
      const featureMap = {
        Parvande: 'patients',
        Vaghtdahi: 'booking',
        Peygiri: 'followups',
        Notif: 'followups',
        dermatracker: 'beauty',
        Photos: 'gallery',
        Gozaresh: 'report',
        ActivityLogs: null,
        Anbar: 'inventory',
        Ticket: 'tickets',
        Products: 'finder',
        Automation: 'automation',
        Bills: 'bills',
        HRtimes: 'attendance',
        Payroll: null,
        Store: null,
        Setting: 'settings',
      };
      const feature = featureMap[menuValue];
      if (menuValue === "Setting") {
        if (this.isClinicManager && await this.confirmLeavingInventory(menuValue)) this.currentPage = menuValue;
        return;
      }
      if (Array.isArray(this.tenantEnabledFeatures) && feature && !this.tenantEnabledFeatures.includes(feature)) return;

      const permissionMap = {
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
        Bills: 'bills.view',
        HRtimes: 'attendance.view',
        Payroll: 'payroll.view'
      };
      const requiredPermission = permissionMap[menuValue];
      if (menuValue === "Payroll" && ['payroll.view', 'reports.financial', 'reports.staff', 'reports.doctors'].some(permission => this.user.permissions.includes(permission))) {
        if (await this.confirmLeavingInventory(menuValue)) this.currentPage = menuValue;
        return;
      }
      if (requiredPermission && !this.user.permissions.includes(requiredPermission)) return;

      if (menuValue === "HRtimes" && !this.attendanceEnabled) return;
      if (menuValue === "Store" && !this.isClinicManager) return;

      if (menuValue === "Vaghtdahi") {
        this.pendingAppointmentViewRequest = null;
      }

      if (!await this.confirmLeavingInventory(menuValue)) return;

      this.currentPage = menuValue;

      if (menuValue === "Peygiri") {

        this.$root.$emit("toggleFlwupOptions");

      }

    },

    openPatientProfileFromAppointment(payload) {
      this.pendingPatientProfileRequest = {
        ...(payload || {}),
        requestedAt: Date.now()
      };
      this.currentPage = "Parvande";
    },

    openBeautyRecordFromPatient(patient) {
      if (
        !this.user?.permissions?.includes('beauty.view')
        || !this.featureEnabledForTenant('beauty')
        || !patient?.id
      ) return;
      this.pendingBeautyRecordRequest = {
        ...patient,
        requestedAt: Date.now()
      };
      this.currentPage = 'dermatracker';
    },

    openBeautyRecordFromNotification(patient) {
      if (
        !this.user?.permissions?.includes('beauty.view')
        || !this.featureEnabledForTenant('beauty')
        || !patient?.id
      ) return;
      this.pendingBeautyRecordRequest = {
        ...patient,
        requestedAt: Date.now()
      };
      this.currentPage = 'dermatracker';
    },

    featureEnabledForTenant(feature) {
      return !Array.isArray(this.tenantEnabledFeatures) || this.tenantEnabledFeatures.includes(feature);
    },

    openInventoryFromNotification() {
      if (!this.user?.permissions?.includes('inventory.view')) return;
      this.currentPage = 'Anbar';
    },

    openTicketFromNotification() {
      if (!this.user?.permissions?.includes('tickets.view')) return;
      this.currentPage = 'Ticket';
    },

    openAppointmentsFromNotification() {
      if (!this.user?.permissions?.includes('appointments.view')) return;
      this.currentPage = 'Vaghtdahi';
    },

    openPatientProfileFromNotification(patient) {
      if (!this.user?.permissions?.includes('patients.view') || !patient?.id) return;
      this.pendingPatientProfileRequest = {
        ...patient,
        requestedAt: Date.now()
      };
      this.currentPage = 'Parvande';
    },

    openFollowupsFromNotification() {
      if (!this.user?.permissions?.includes('followups.view')) return;
      this.currentPage = 'Peygiri';
    },

    openFollowupsFromPatient(patient) {
      if (!this.user?.permissions?.includes('followups.view') || !patient) return;
      this.pendingFollowupOpenRequest = {
        id: patient.id || null,
        fullName: [patient.first_name, patient.last_name].filter(Boolean).join(" ").trim(),
        phone: patient.phone || "",
        fileNumber: patient.file_number || "",
        requestedAt: Date.now()
      };
      this.currentPage = "Peygiri";
    },

    openAttendanceFromNotification() {
      if (!this.user?.permissions?.includes('attendance.view')) return;
      if (!this.attendanceEnabled) return;
      this.currentPage = 'HRtimes';
    },

    openReportsFromNotification() {
      if (!this.user?.permissions?.includes('reports.view')) return;
      this.currentPage = 'Gozaresh';
    },

    openPhotosFromNotification() {
      if (!this.user?.permissions?.includes('photos.view')) return;
      this.currentPage = 'Photos';
    },

    openPatientProfileFromBeauty(patient) {
      if (!patient?.id) return;
      this.pendingPatientProfileRequest = {
        ...patient,
        requestedAt: Date.now()
      };
      this.currentPage = "Parvande";
    },

    openPatientMediaFromBeauty(patient) {
      if (!patient?.id) return;
      this.pendingPatientProfileRequest = {
        ...patient,
        open_media: true,
        requestedAt: Date.now()
      };
      this.currentPage = "Parvande";
    },

    openAppointmentsTimeline(payload = {}) {
      this.pendingAppointmentViewRequest = {
        view: "timeline",
        date: payload.date || "",
        followup: payload.followup || null,
        requestedAt: Date.now()
      };
      this.currentPage = "Vaghtdahi";
    },

    handleOpenAppointmentsTimelineEvent(event) {
      this.openAppointmentsTimeline(event?.detail || {});
    },

    handleFollowupAppointmentCreated(payload = {}) {
      if (!payload?.followup?.campaignId || !payload?.followup?.rowId) return;
      this.pendingFollowupAppointmentResult = {
        ...payload,
        requestedAt: Date.now()
      };
      this.currentPage = "Peygiri";
    },

    closeAllPages() {

      this.currentPage = null;

    }

  }

};

</script>

<style>

* {

  margin: 0;

  padding: 0;

  box-sizing: border-box;

}

body {

  font-family: tahoma;

  background: #f4f7fb;

  transition: 0.3s;

}

body.dark {

  background: #0f172a;

  color: white;

}

#app {

  min-height: 100vh;

  padding: 20px;

  transition: 0.3s;

}

body.central-host {

  background: #0c2723;

}

body.central-host #app {

  padding: 0;

}

.page-container {

  margin-top: 24px;

}

/* دکمه دارک مود */

.dark-toggle {

  position: fixed;

  top: 30px;

  left: 274px;

  width: 52px;

  height: 52px;

  border: none;

  border-radius: 18px;

  background: white;

  color: #333;

  font-size: 22px;

  cursor: pointer;

  z-index: 999;

  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);

  transition: 0.3s;

}

.dark-toggle:hover {

  transform: translateY(-2px);

}

.my-report-toggle{position:fixed;top:20px;left:214px;z-index:999;width:52px;height:52px;display:grid;place-items:center;border:0;border-radius:18px;background:#fff;color:#2563eb;cursor:pointer;box-shadow:0 10px 25px rgba(0,0,0,.08);transition:.3s}.my-report-toggle:hover{transform:translateY(-2px)}.my-report-toggle svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.my-report-overlay{position:fixed;z-index:1000001;inset:0;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.55);backdrop-filter:blur(5px)}.my-report-modal{width:min(620px,96vw);max-height:92vh;overflow:auto;padding:22px;border:1px solid rgba(255,255,255,.7);border-radius:24px;background:#fff;color:#0f172a;box-shadow:0 28px 80px rgba(15,23,42,.35)}.my-report-modal>header{display:flex;align-items:flex-start;justify-content:space-between}.my-report-modal header small{color:#2563eb;font-weight:900}.my-report-modal h2{margin:4px 0 0;font-size:22px}.my-report-modal>header>button{width:36px;height:36px;border:0;border-radius:11px;background:#f1f5f9;color:#64748b;font-size:24px;cursor:pointer}.my-report-month-nav{display:flex;align-items:center;justify-content:center;gap:14px;margin:18px 0;padding:10px;border-radius:14px;background:#f8fafc}.my-report-month-nav button{width:34px;height:34px;border:1px solid #dbe3ed;border-radius:9px;background:#fff;color:#2563eb;font-size:23px;cursor:pointer}.my-report-month-nav button:disabled{opacity:.35;cursor:not-allowed}.my-report-month-nav strong{min-width:150px;text-align:center}.my-report-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.my-report-grid article{display:grid;gap:5px;padding:15px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}.my-report-grid article.earned{grid-column:1/-1;background:linear-gradient(135deg,#eff6ff,#ecfdf5);border-color:#bfdbfe}.my-report-grid span{color:#64748b;font-size:11px;font-weight:800}.my-report-grid strong{color:#1e293b;font-size:22px}.my-report-grid .earned strong{color:#047857;font-size:28px}.my-report-grid small{color:#94a3b8;font-size:9px}.my-report-loading,.my-report-error{min-height:210px;display:flex;align-items:center;justify-content:center;gap:9px;color:#64748b}.my-report-loading i{width:28px;height:28px;border:4px solid #dbeafe;border-top-color:#2563eb;border-radius:50%;animation:reportSpin .8s linear infinite}.my-report-error{color:#b91c1c}@keyframes reportSpin{to{transform:rotate(360deg)}}.dark .my-report-toggle{background:#1e293b;color:#93c5fd}.dark .my-report-modal{background:#172033;color:#f8fafc}.dark .my-report-modal h2,.dark .my-report-grid strong{color:#f8fafc}.dark .my-report-grid article,.dark .my-report-month-nav{background:#1e293b;border-color:#334155}@media(max-width:600px){.my-report-grid{grid-template-columns:1fr}.my-report-grid article.earned{grid-column:auto}.my-report-toggle{left:190px;width:46px;height:46px}.dark-toggle{left:244px;width:46px;height:46px}}

#app.dark {

  background: #0f172a;

  color: white;

}

/* استایل‌های دارک مود */

.dark .content-card,
.dark .settings-sidebar,
.dark .accordion,
.dark .permission-box,
.dark .table-wrapper,
.dark .modern-table {

  background: #172033 !important;

  color: white !important;

  border-color: #243046 !important;

}

.dark input,
.dark textarea,
.dark select {

  background: #1e293b !important;

  color: white !important;

  border-color: #334155 !important;

}

.dark .menu-item {

  background: #172033 !important;

  color: #ddd !important;

}

.dark .menu-item.active {

  background: linear-gradient(
    135deg,
    #2563eb,
    #3b82f6
  ) !important;

  color: white !important;

}

.dark table th {

  background: #1e293b !important;

  color: white !important;

}

.dark table td {

  background: #172033 !important;

  color: white !important;

}

.dark .row-control button,
.dark .add-option button {

  background: #2563eb !important;

}

/* موبایل */

@media (max-width: 768px) {

  #app {

    padding: 14px;

  }

  body.central-host #app {

    padding: 0;

  }

  .page-container {

    margin-top: 18px;

  }

}

.auth-boot {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: #475569;
  background: #f4f7fb;
  font-family: "Vazir", sans-serif;
}

.auth-boot-spinner {
  width: 22px;
  height: 22px;
  border: 3px solid #dbe3ef;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: auth-spin .7s linear infinite;
}

.auth-toolbar {
  position: fixed;
  top: 12px;
  left: 16px;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 9px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
  font-family: "Vazir", sans-serif;
  width: 190px;
  box-sizing: border-box;
}

.auth-avatar { width: 64px; height: 64px; overflow: hidden; display: grid; place-items: center; border-radius: 50%; color: #fff; background: #2563eb; font-weight: 900; box-shadow: 0 10px 24px rgba(15, 23, 42, .14); cursor:pointer; }
.auth-avatar img { width: 100%; height: 100%; display: block; object-fit: cover; }
.auth-avatar span { color: #fff; font-size: 27px; line-height: 1; }
.auth-avatar{border:0;padding:0}
.auth-user-info { min-width: 0; width: 100%; display: flex; flex-direction: column; align-items: center; gap: 2px; }
.auth-user-info strong,
.auth-user-info small,
.auth-user-info em { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.auth-user-info strong { color: #1f2937; font-size: 15px; font-weight: 900; }
.auth-user-info small { color: #64748b; font-size: 11px; direction: ltr; }
.auth-user-info em { margin-top: 4px; padding: 4px 10px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: 9px; font-style: normal; font-weight: 900; }
.auth-toolbar .auth-logout {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #dc2626;
  background: #fff7f7;
  transition: color 160ms ease, background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}
.auth-toolbar .auth-logout svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.auth-toolbar .auth-logout:hover {
  color: #fff;
  border-color: #dc2626;
  background: #dc2626;
  transform: translateY(-1px);
}
.auth-toolbar .auth-logout:active { transform: scale(.95); }

.profile-page{min-height:calc(100vh - 150px);padding:24px;border-radius:18px;background:#f1f4f9;color:#0f172a}
.profile-editor-modal{width:min(820px,100%);margin:0 auto;padding:22px;border:1px solid #dbe3ef;border-radius:22px;background:#fff;color:#0f172a;box-shadow:0 18px 48px rgba(15,23,42,.08)}
.profile-editor-modal>header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px}.profile-editor-modal header small{color:#2563eb;font-weight:900}.profile-editor-modal h2{margin:3px 0 0;font-size:20px}.profile-editor-modal header button{width:36px;height:36px;border:0;border-radius:11px;background:#f1f5f9;color:#64748b;font-size:24px;cursor:pointer}
.profile-editor-body{display:flex;align-items:center;gap:14px;padding:16px;border:1px solid #eef2f7;border-radius:18px;background:#f8fafc}.profile-editor-body>div{display:grid;gap:4px}.profile-editor-body strong{font-size:16px}.profile-editor-body small{color:#64748b;direction:ltr}
.profile-editor-avatar{width:76px;height:76px;flex:0 0 76px;display:grid;place-items:center;overflow:hidden;border-radius:50%;box-shadow:0 10px 24px rgba(15,23,42,.12);cursor:pointer}.profile-editor-avatar img{width:100%;height:100%;object-fit:cover;display:block}.profile-editor-avatar span{color:#fff;font-size:31px;font-weight:900}.profile-editor-avatar input,.profile-upload-btn input{display:none}
.profile-editor-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.profile-editor-fields label{display:grid;gap:7px;color:#334155;font-size:12px;font-weight:900}.profile-editor-fields input{height:46px;border:1px solid #dbe3ef;border-radius:13px;background:#f8fafc;padding:0 13px;font-family:inherit;outline:none}.profile-editor-fields input:focus{border-color:#bfdbfe;box-shadow:0 0 0 4px rgba(37,99,235,.08)}
.profile-gender-picker{height:46px;display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:4px;border:1px solid #dbeafe;border-radius:13px;background:#f8fbff}.profile-gender-picker button{border:0;border-radius:10px;background:#fff;color:#64748b;cursor:pointer;box-shadow:0 0 0 1px #e2e8f0 inset}.profile-gender-picker button.active{background:#2563eb;color:#fff}.profile-gender-picker svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.profile-password-field{position:relative}.profile-password-field input{padding-left:42px}.profile-password-field button{position:absolute;left:8px;bottom:9px;width:28px;height:28px;border:0;border-radius:9px;background:transparent;color:#2563eb;display:grid;place-items:center;cursor:pointer}.profile-password-field svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.profile-editor-modal footer{display:flex;gap:10px;margin-top:16px}.profile-upload-btn,.profile-remove-btn,.profile-save-btn{height:42px;flex:1;display:grid;place-items:center;border:0;border-radius:12px;font-family:inherit;font-weight:900;cursor:pointer}.profile-upload-btn{background:#2563eb;color:#fff}.profile-remove-btn{background:#fff1f2;color:#dc2626;border:1px solid #fecaca}.profile-save-btn{background:#16a34a;color:#fff}.profile-remove-btn:disabled,.profile-save-btn:disabled{opacity:.45;cursor:not-allowed}

.utility-menu-toggle {
  position: fixed;
  top: 20px;
  left: 20px;
  z-index: 2147483002;
  width: 46px;
  height: 46px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 1px solid #dbeafe;
  outline: 0;
  border-radius: 14px;
  background: rgba(255, 255, 255, .96);
  color: #334155;
  box-shadow: 0 10px 24px rgba(15, 23, 42, .14);
  transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease, color 160ms ease;
}
.utility-menu-toggle svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
}
.utility-menu-toggle:hover,
.utility-menu-toggle.active {
  outline: 0;
  border-color: #bfdbfe;
  color: #2563eb;
  background: #fff;
  box-shadow: 0 14px 30px rgba(15, 23, 42, .18);
}

.utility-menu-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2147483000;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.utility-menu-panel {
  position: fixed;
  top: 62px;
  left: 18px;
  z-index: 2147483001;
  width: 304px;
  box-sizing: border-box;
  display: grid;
  gap: 8px;
  padding: 14px;
  border: 1px solid #dbe3ef;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
}
.utility-menu-panel header {
  display: grid;
  justify-items: center;
  gap: 10px;
  margin: -4px -4px 7px;
  padding: 16px 14px 18px;
  border-bottom: 1px solid #eef2f7;
  border-radius: 18px 18px 8px 8px;
  background: linear-gradient(180deg, #f8fafc 0%, #ffffff 78%);
  text-align: center;
}
.utility-menu-panel > button {
  width: 100%;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  padding: 0 13px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: transparent;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
  transition: color 160ms ease, background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}
.utility-menu-panel > button:hover {
  border-color: #e2e8f0;
  background: #f1f5f9;
  color: #1d4ed8;
}
.utility-menu-panel > button svg {
  width: 18px;
  height: 18px;
  flex: 0 0 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.utility-menu-panel > .utility-logout {
  margin-top: 3px;
  border-color: transparent;
  background: #fff7f7;
  color: #dc2626;
}
.utility-menu-panel > .utility-logout:hover {
  border-color: #fecaca;
  background: #fff1f2;
  color: #b91c1c;
}
.service-status-page {
  width: min(1180px, 100%);
  margin: 0 auto;
  display: grid;
  gap: 18px;
  color: #0f172a;
}
.service-page-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 22px;
  border: 1px solid #dbeafe;
  border-radius: 20px;
  background:
    linear-gradient(135deg, rgba(239, 246, 255, .96), rgba(236, 253, 245, .94)),
    #fff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
}
.service-page-hero small,
.service-question-card small {
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}
.service-page-hero h1,
.service-question-card h2 {
  margin: 4px 0 0;
  color: #0f172a;
  font-size: 24px;
}
.service-page-hero p {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 13px;
  font-weight: 800;
  line-height: 1.9;
}
.service-page-hero button {
  flex: 0 0 auto;
  min-width: 92px;
  height: 40px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #fff;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}
.service-page-grid {
  display: grid;
  grid-template-columns: 1.35fr repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.service-status-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 18px;
  border: 1px solid #bfdbfe;
  border-radius: 18px;
  background: linear-gradient(135deg, #eff6ff, #ecfdf5);
}
.service-status-card-main {
  min-height: 142px;
  border-radius: 20px;
}
.service-status-card div {
  display: grid;
  gap: 5px;
}
.service-status-card span,
.service-metric-card span {
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
}
.service-status-card strong {
  color: #1e293b;
  font-size: 20px;
}
.service-status-card small {
  width: max-content;
  padding: 4px 9px;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 10px;
  font-weight: 900;
}
.service-status-card b {
  flex: 0 0 auto;
  padding: 10px 12px;
  border-radius: 14px;
  background: #0f172a;
  color: #fff;
  font-size: 13px;
}
.service-metric-card,
.service-modules-card,
.service-question-card {
  display: grid;
  gap: 7px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: #f8fafc;
}
.service-metric-card {
  align-content: center;
  min-height: 142px;
}
.service-metric-card strong {
  color: #1e293b;
  font-size: 15px;
}
.service-page-card {
  padding: 18px;
  border-radius: 18px;
  background: #fff;
}
.service-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.service-card-head strong {
  color: #1e293b;
}
.service-card-head span {
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}
.service-module-list {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}
.service-purchase-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.service-purchase-list article {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 68px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #f8fafc;
}
.service-purchase-list article div {
  display: grid;
  gap: 4px;
  min-width: 0;
}
.service-purchase-list article strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 1000;
}
.service-purchase-list article small {
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
}
.service-purchase-list article button {
  flex: 0 0 auto;
  height: 38px;
  padding: 0 12px;
  border: 0;
  border-radius: 11px;
  background: #16a34a;
  color: #fff;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}
.service-module-list span {
  padding: 7px 10px;
  border: 1px solid #dbeafe;
  border-radius: 999px;
  background: #fff;
  color: #1d4ed8;
  font-size: 11px;
  font-weight: 900;
}
.service-modules-card p {
  margin: 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}
.service-question-card {
  padding: 20px;
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
}
.service-question-card textarea {
  width: 100%;
  resize: vertical;
  min-height: 132px;
  padding: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  outline: 0;
  background: #f8fafc;
  color: #0f172a;
  font: 800 13px/2 tahoma;
}
.service-question-card textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
}
.service-question-card footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.service-question-card footer span {
  min-height: 22px;
  color: #15803d;
  font-size: 12px;
  font-weight: 900;
}
.service-question-card footer button {
  width: 118px;
  height: 42px;
  border: 0;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  font-size: 13px;
  font-weight: 900;
  cursor: pointer;
}
.service-question-card footer button:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
}
.dark .utility-menu-toggle,
.dark .utility-menu-panel {
  border-color: #334155;
  background: rgba(23, 32, 51, .98);
}
.dark .utility-menu-toggle {
  border-color: #475569;
}
.dark .utility-menu-panel header { border-color: #334155; }
.dark .utility-menu-panel > button { background: #1e293b; color: #e2e8f0; }
.central-clinic-shortcut {
  position: fixed;
  z-index: 10000;
  left: 18px;
  bottom: 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 148px;
  height: 42px;
  padding: 0 16px;
  border-radius: 12px;
  background: #0f172a;
  color: #fff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, .18);
  font-size: 13px;
  font-weight: 900;
  text-decoration: none;
}
.central-clinic-shortcut:hover {
  background: #2563eb;
}

@media (max-width: 600px) {
  .utility-menu-toggle { top: 30px; left: 14px; width: 42px; height: 42px; }
  .utility-menu-panel { top: 60px; left: 12px; width: min(230px, calc(100vw - 24px)); }
  .service-page-grid { grid-template-columns: 1fr; }
  .service-purchase-list { grid-template-columns: 1fr; }
  .service-page-hero,
  .service-question-card footer { align-items: stretch; flex-direction: column; }
  .service-status-card { align-items: stretch; flex-direction: column; }
  .service-question-card footer button { width: 100%; }
}

@keyframes auth-spin { to { transform: rotate(360deg); } }

</style>
