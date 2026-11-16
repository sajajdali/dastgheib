<template>
  <section class="leads-page" dir="rtl">
    <div class="online-strip" aria-live="polite">
      <div class="online-title">
        <span class="online-pulse" :class="{ muted: !presence.connected }"></span>
        <div>
          <strong>{{ presence.connected ? "آنلاین" : "وضعیت اتصال" }}</strong>
          <small v-if="presence.connected">{{ faNumber(presence.users.length) }} نفر</small>
          <small v-else>{{ presence.connecting ? "در حال اتصال…" : "ارتباط لحظه‌ای قطع است" }}</small>
        </div>
      </div>

      <div v-if="presence.users.length" class="online-avatars">
        <div
          v-for="onlineUser in visibleUsers"
          :key="onlineUser.id"
          class="online-person"
          :title="userTitle(onlineUser)"
        >
          <img v-if="onlineUser.avatar_url" :src="onlineUser.avatar_url" :alt="onlineUser.name" />
          <span v-else>{{ onlineUser.name.charAt(0) }}</span>
          <i aria-hidden="true"></i>
        </div>
        <span v-if="hiddenCount" class="online-more">+{{ faNumber(hiddenCount) }}</span>
      </div>
      <span v-else-if="presence.connected" class="online-empty">کاربر دیگری آنلاین نیست</span>
    </div>

    <div v-if="visibleReports.length > 1" class="leads-toolbar" role="tablist" aria-label="نوع نمایش سرنخ‌ها">
      <button
        v-for="report in visibleReports"
        :key="report.key"
        type="button"
        class="leads-tab"
        :class="{ active: activeReport === report.key }"
        role="tab"
        :aria-selected="activeReport === report.key"
        @click="selectReport(report.key)"
      >
        {{ report.label }}
      </button>
    </div>

    <iframe
      :key="activeReport"
      class="leads-frame"
      :src="activeReportSrc"
      title="سرنخ‌ها"
      loading="eager"
      @load="isLoading = false"
    ></iframe>

    <div v-if="isLoading" class="leads-loading" role="status">
      <span class="leads-spinner"></span>
      در حال بارگذاری سرنخ‌ها...
    </div>
  </section>
</template>

<script>
import { presenceState, startPresence } from "../services/presence";

const SHOW_LEGACY_REPORT = false;

export default {
  name: "Saranj",
  props: {
    currentUser: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      isLoading: true,
      activeReport: "current",
      reports: [
        { key: "legacy", label: "نسخه قدیمی", src: "/reports/سرنج ها.dc.html", visible: SHOW_LEGACY_REPORT },
        { key: "current", label: "نسخه فعلی", src: "/reports/leads.html" },
      ],
      presence: presenceState,
      reconnectTimer: null,
    };
  },
  mounted() {
    this.ensurePresence();
    this.reconnectTimer = window.setTimeout(this.ensurePresence, 1500);
  },
  beforeUnmount() {
    if (this.reconnectTimer) window.clearTimeout(this.reconnectTimer);
  },
  watch: {
    currentUser: {
      deep: false,
      handler() {
        this.ensurePresence();
      },
    },
  },
  computed: {
    visibleReports() {
      return this.reports.filter((report) => report.visible !== false);
    },
    activeReportSrc() {
      const src = this.reports.find((report) => report.key === this.activeReport)?.src || "/reports/leads.html";
      const separator = src.includes("?") ? "&" : "?";
      return `${src}${separator}v=20261116-dark-leads`;
    },
    visibleUsers() {
      return this.presence.users.slice(0, 10);
    },
    hiddenCount() {
      return Math.max(0, this.presence.users.length - this.visibleUsers.length);
    },
  },
  methods: {
    ensurePresence() {
      startPresence(this.currentUser);
    },
    selectReport(reportKey) {
      if (this.activeReport === reportKey) return;
      this.activeReport = reportKey;
      this.isLoading = true;
    },
    faNumber(value) {
      return Number(value || 0).toLocaleString("fa-IR");
    },
    userTitle(user) {
      const role = user.roles?.[0];
      return role ? `${user.name} — ${role}` : user.name;
    },
  },
};
</script>

<style scoped>
.leads-page {
  position: relative;
  width: 100%;
  min-height: calc(100vh - 150px);
  overflow: hidden;
  border-radius: 18px;
  background: #f1f4f9;
}

.online-strip {
  width: fit-content;
  max-width: 100%;
  min-height: 46px;
  margin: 0 10px 8px auto;
  padding: 5px 10px;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  border: 1px solid #dbe5f3;
  border-radius: 14px;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 6px 18px rgba(30, 64, 175, .06);
}

.online-title { display: flex; align-items: center; gap: 7px; flex: 0 0 auto; }
.online-title > div { display: grid; gap: 2px; }
.online-title strong { color: #172033; font-size: 11px; line-height: 1; }
.online-title small, .online-empty { color: #718096; font-size: 9px; line-height: 1; }
.online-pulse { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .13); }
.online-pulse.muted { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148, 163, 184, .13); }
.online-avatars { display: flex; align-items: center; padding-left: 6px; direction: rtl; }
.online-person {
  position: relative;
  width: 30px;
  height: 30px;
  margin-left: -6px;
  display: grid;
  place-items: center;
  overflow: visible;
  border: 2px solid #fff;
  border-radius: 50%;
  color: #fff;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  box-shadow: 0 4px 13px rgba(15, 23, 42, .14);
  font-size: 11px;
  font-weight: 900;
}
.online-person img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; }
.online-person i { position: absolute; right: -1px; bottom: -1px; width: 8px; height: 8px; border: 2px solid #fff; border-radius: 50%; background: #22c55e; }
.online-more { min-width: 28px; height: 28px; margin-right: 7px; display: grid; place-items: center; border-radius: 50%; color: #334155; background: #eef3fb; font-size: 9px; font-weight: 800; }

.leads-toolbar {
  width: fit-content;
  max-width: calc(100% - 20px);
  margin: 0 10px 8px auto;
  padding: 4px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid #dbe5f3;
  border-radius: 14px;
  background: rgba(255, 255, 255, .94);
}

.leads-tab {
  min-width: 96px;
  height: 32px;
  border: 0;
  border-radius: 10px;
  color: #64748b;
  background: transparent;
  cursor: pointer;
  font-size: 12px;
  font-weight: 800;
}

.leads-tab.active {
  color: #fff;
  background: #2563eb;
  box-shadow: 0 4px 12px rgba(37, 99, 235, .2);
}

.leads-frame {
  display: block;
  width: 100%;
  min-height: calc(100vh - 210px);
  border: 0;
  background: #f1f4f9;
}

.leads-loading {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: #475569;
  background: #f1f4f9;
  font-size: 14px;
  font-weight: 700;
}

.leads-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #dbe3ef;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: leads-spin 0.7s linear infinite;
}

@keyframes leads-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
  .online-strip { margin-right: 6px; padding: 5px 8px; }
  .online-person { width: 28px; height: 28px; }
  .online-avatars .online-person:nth-child(n+7) { display: none; }
}
</style>

<style>
html body.dark .leads-page,
html body.dark .leads-frame,
html body.dark .leads-loading,
html #app.dark .leads-page,
html #app.dark .leads-frame,
html #app.dark .leads-loading {
  background: #121316 !important;
  color: #e8eaed !important;
}
html body.dark .leads-spinner,
html #app.dark .leads-spinner {
  border-color: #3a3f4a !important;
  border-top-color: #4f8cff !important;
}
html body.dark .online-strip,
html body.dark .leads-toolbar,
html #app.dark .online-strip,
html #app.dark .leads-toolbar {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  box-shadow: none !important;
}
html body.dark .online-title strong,
html #app.dark .online-title strong { color: #f1f3f4 !important; }
html body.dark .online-title small,
html body.dark .online-empty,
html #app.dark .online-title small,
html #app.dark .online-empty { color: #9aa0a6 !important; }
html body.dark .online-person,
html body.dark .online-person i,
html #app.dark .online-person,
html #app.dark .online-person i { border-color: #1a1c22 !important; }
html body.dark .online-more,
html #app.dark .online-more {
  background: #2a2f3a !important;
  color: #e8eaed !important;
}
html body.dark .leads-tab,
html #app.dark .leads-tab { color: #c5cad3 !important; }
html body.dark .leads-tab.active,
html #app.dark .leads-tab.active {
  background: #4f8cff !important;
  color: #fff !important;
}
</style>
