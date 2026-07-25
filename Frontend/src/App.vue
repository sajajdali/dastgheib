<template>
  <div v-if="authLoading" class="auth-boot" dir="rtl">
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
      <span></span><span></span><span></span>
    </button>

    <button v-if="uiMenuOpen" class="utility-menu-backdrop" type="button" aria-label="بستن منوی کاربری" @click="uiMenuOpen = false"></button>

    <aside v-if="uiMenuOpen" id="utility-menu-panel" class="utility-menu-panel" dir="rtl">
      <header>
        <div class="auth-avatar">{{ user.name?.charAt(0) || 'ک' }}</div>
        <div class="auth-user-info">
          <strong>{{ user.name }}</strong>
          <small>{{ user.roles?.join('، ') || 'بدون نقش' }}</small>
        </div>
      </header>

      <button type="button" @click="uiMenuOpen = false; openMyReport()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V10M12 20V4M19 20v-7"/><path d="M3 20h18"/></svg>
        <span>گزارش شخصی</span>
      </button>
      <button type="button" @click="uiMenuOpen = false; toggleDark()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="4"/></svg>
        <span>{{ isDark ? 'حالت روشن' : 'حالت شب' }}</span>
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
      @select="changePage"
      @close-all="closeAllPages"
    />

    <!-- صفحات -->
    <div class="page-container">

      <!-- پیشفرض -->
      <Saranj v-if="currentPage === null" :current-user="user" />

      <!-- صفحات -->
      <Parvande
        v-if="currentPage === 'Parvande'"
        :open-patient-request="pendingPatientProfileRequest"
        @open-page="changePage"
        @open-beauty-record="openBeautyRecordFromPatient"
      />

      <Photos v-if="currentPage === 'Photos'" />

      <Time
        v-if="currentPage === 'Vaghtdahi'"
        :permissions="user.permissions || []"
        :open-view-request="pendingAppointmentViewRequest"
        @open-patient-profile="openPatientProfileFromAppointment"
      />

      <FlwUp
        v-if="currentPage === 'Peygiri'"
        :permissions="user.permissions || []"
        @open-appointments-timeline="openAppointmentsTimeline"
      />

      <Notif
        v-if="currentPage === 'Notif'"
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

      <Gozaresh v-if="currentPage === 'Gozaresh'" />

      <Anbar v-if="currentPage === 'Anbar'" />

      <Dermatracker
        v-if="currentPage === 'dermatracker'"
        :open-patient-request="pendingBeautyRecordRequest"
        @back-to-patient-profile="openPatientProfileFromBeauty"
      />

      <Ticket v-if="currentPage === 'Ticket'" :permissions="user.permissions || []" />

      <Products v-if="currentPage === 'Products'" />

      <!-- قبوض -->
      <Bills v-if="currentPage === 'Bills'" />

      <!-- حضور غیاب -->
      <HRtimes
        v-if="currentPage === 'HRtimes' && attendanceEnabled"
        :permissions="user.permissions || []"
        :current-user="user"
      />

      <!-- تنظیمات -->
      <Setting v-if="currentPage === 'Setting'" :current-user="user" />
      <Store v-if="currentPage === 'Store'" />

    </div>

  </div>
</template>

<script>

import axios from "axios";
import Swal from "sweetalert2";

import Login from "./components/Login.vue";

import Menu from "./components/menu.vue";

import Notif from "./components/notif.vue";

import Saranj from "./components/saranj.vue";

import Parvande from "./components/parvande.vue";
import Photos from "./components/Photos.vue";

import Time from "./components/Time.vue";

import Anbar from "./components/anbar.vue";

import FlwUp from "./components/flwup.vue";

import Gozaresh from "./components/gozaresh.vue";

import Dermatracker from "./components/dermatracker.vue";

import Ticket from "./components/Ticket.vue";

import Products from "./components/products.vue";

import Bills from "./components/bills.vue";

import Setting from "./components/Setting.vue";
import Store from "./components/Store.vue";

import HRtimes from "./components/HRtimes.vue";
import { startPresence, stopPresence } from "./services/presence";

export default {

  name: "App",

  components: {

    Login,

    Menu,

    Notif,

    Saranj,

    Parvande,
    Photos,

    Time,

    Anbar,

    FlwUp,

    Gozaresh,

    Dermatracker,

    Ticket,

    Products,

    Bills,

    Setting,
    Store,

    HRtimes

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
      ,authNoticeOpen: false
      ,myReportOpen: false
      ,myReportLoading: false
      ,myReport: null
      ,myReportError: ""
      ,reportMonth: ""
      ,attendanceEnabled: false
      ,uiMenuOpen: false

    };

  },

  mounted() {

    window.addEventListener("app:auth-expired", this.handleAuthExpired);
    window.addEventListener("app:attendance-status-changed", this.handleAttendanceStatusChanged);
    this.checkAuth();

    const saved = localStorage.getItem("darkMode");

    if (saved === "true") {

      this.isDark = true;

      document.body.classList.add("dark");

    }

  },

  beforeUnmount() {
    stopPresence();
    window.removeEventListener("app:auth-expired", this.handleAuthExpired);
    window.removeEventListener("app:attendance-status-changed", this.handleAttendanceStatusChanged);
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

    }

  },

  computed: {
    isClinicManager() {
      const allowed = ["مدیر کل", "مدیر سیستم", "super admin", "super-admin"];
      return (this.user?.roles || []).some(role => allowed.includes(String(role).trim().toLowerCase()));
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
    }
  },

  methods: {
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
        this.myReportError = error.response?.data?.message || "دریافت گزارش شخصی انجام نشد.";
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

    async loadAttendanceStatus() {
      try {
        const { data } = await axios.get("/api/settings");
        this.attendanceEnabled = Boolean(data.attendance_enabled);
        if (!this.attendanceEnabled && this.currentPage === "HRtimes") this.currentPage = null;
      } catch {
        this.attendanceEnabled = false;
      }
    },

    handleAttendanceStatusChanged(event) {
      this.attendanceEnabled = Boolean(event.detail?.enabled);
      if (!this.attendanceEnabled && this.currentPage === "HRtimes") this.currentPage = null;
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

    changePage(menuValue) {
      const permissionMap = {
        Parvande: 'patients.view',
        Photos: 'photos.view',
        Vaghtdahi: 'appointments.view',
        Peygiri: 'followups.view',
        Notif: 'followups.view',
        Gozaresh: 'reports.view',
        Anbar: 'inventory.view',
        dermatracker: 'beauty.view',
        Ticket: 'tickets.view',
        Products: 'services.view',
        Bills: 'bills.view',
        HRtimes: 'attendance.view'
      };
      const requiredPermission = permissionMap[menuValue];
      if (requiredPermission && !this.user.permissions.includes(requiredPermission)) return;

      if (menuValue === "HRtimes" && !this.attendanceEnabled) return;
      if (menuValue === "Setting" && !this.isClinicManager) return;
      if (menuValue === "Store" && !this.isClinicManager) return;

      if (menuValue === "Vaghtdahi") {
        this.pendingAppointmentViewRequest = null;
      }

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
      if (!this.user?.permissions?.includes('beauty.view') || !patient?.id) return;
      this.pendingBeautyRecordRequest = {
        ...patient,
        requestedAt: Date.now()
      };
      this.currentPage = 'dermatracker';
    },

    openBeautyRecordFromNotification(patient) {
      if (!this.user?.permissions?.includes('beauty.view') || !patient?.id) return;
      this.pendingBeautyRecordRequest = {
        ...patient,
        requestedAt: Date.now()
      };
      this.currentPage = 'dermatracker';
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

    openAppointmentsTimeline(payload = {}) {
      this.pendingAppointmentViewRequest = {
        view: "timeline",
        date: payload.date || "",
        requestedAt: Date.now()
      };
      this.currentPage = "Vaghtdahi";
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

.page-container {

  margin-top: 24px;

}

/* دکمه دارک مود */

.dark-toggle {

  position: fixed;

  top: 20px;

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

.auth-avatar { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 10px; color: #fff; background: #2563eb; font-weight: 800; }
.auth-user-info { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.auth-user-info strong,
.auth-user-info small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.auth-user-info strong { color: #1f2937; font-size: 12px; }
.auth-user-info small { color: #94a3b8; font-size: 9px; }
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

.utility-menu-toggle {
  position: fixed;
  top: 16px;
  left: 16px;
  z-index: 1002;
  width: 44px;
  height: 44px;
  display: grid;
  place-content: center;
  gap: 4px;
  padding: 0;
  border: 1px solid #dbeafe;
  border-radius: 13px;
  background: rgba(255, 255, 255, .96);
  color: #2563eb;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .1);
  transition: background-color 160ms ease, transform 160ms ease, box-shadow 160ms ease;
}
.utility-menu-toggle span {
  width: 18px;
  height: 2px;
  display: block;
  border-radius: 999px;
  background: currentColor;
  transition: transform 160ms ease, opacity 160ms ease;
}
.utility-menu-toggle:hover,
.utility-menu-toggle.active {
  background: #eff6ff;
  box-shadow: 0 10px 26px rgba(37, 99, 235, .16);
}
.utility-menu-toggle.active span:nth-child(1) { transform: translateY(6px) rotate(45deg); }
.utility-menu-toggle.active span:nth-child(2) { opacity: 0; }
.utility-menu-toggle.active span:nth-child(3) { transform: translateY(-6px) rotate(-45deg); }

.utility-menu-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: rgba(15, 23, 42, .18);
  backdrop-filter: blur(1px);
}

.utility-menu-panel {
  position: fixed;
  top: 68px;
  left: 16px;
  z-index: 1001;
  width: 230px;
  box-sizing: border-box;
  display: grid;
  gap: 7px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 17px;
  background: rgba(255, 255, 255, .98);
  box-shadow: 0 20px 45px rgba(15, 23, 42, .18);
}
.utility-menu-panel header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
  padding: 3px 3px 11px;
  border-bottom: 1px solid #eef2f7;
}
.utility-menu-panel > button {
  width: 100%;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  padding: 0 12px;
  border: 1px solid transparent;
  border-radius: 11px;
  background: #f8fafc;
  color: #334155;
  font-size: 11px;
  font-weight: 900;
  transition: color 160ms ease, background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}
.utility-menu-panel > button:hover {
  border-color: #bfdbfe;
  background: #eff6ff;
  color: #1d4ed8;
  transform: translateX(-2px);
}
.utility-menu-panel > button svg {
  width: 19px;
  height: 19px;
  flex: 0 0 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.utility-menu-panel > .utility-logout {
  border-color: #fee2e2;
  background: #fff7f7;
  color: #dc2626;
}
.utility-menu-panel > .utility-logout:hover {
  border-color: #fecaca;
  background: #fff1f2;
  color: #b91c1c;
}
.dark .utility-menu-toggle,
.dark .utility-menu-panel {
  border-color: #334155;
  background: rgba(23, 32, 51, .98);
}
.dark .utility-menu-panel header { border-color: #334155; }
.dark .utility-menu-panel > button { background: #1e293b; color: #e2e8f0; }

@media (max-width: 600px) {
  .utility-menu-toggle { top: 12px; left: 12px; width: 40px; height: 40px; }
  .utility-menu-panel { top: 60px; left: 12px; width: min(230px, calc(100vw - 24px)); }
}

@keyframes auth-spin { to { transform: rotate(360deg); } }

</style>
