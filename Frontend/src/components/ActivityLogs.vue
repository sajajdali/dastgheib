<template>
  <section class="activity-page" dir="rtl">
    <section v-if="canViewActivityLogs" class="activity-panel">
      <header>
        <div>
          <small>ردیابی فعالیت‌ها</small>
          <h3>سوابق ثبت، ویرایش و حذف</h3>
        </div>
        <button type="button" :disabled="activityLoading" @click="loadActivityLogs">
          {{ activityLoading ? 'در حال دریافت...' : 'به‌روزرسانی' }}
        </button>
      </header>

      <div class="activity-filters">
        <input v-model.trim="activityFilters.q" placeholder="جستجو در کاربر، بخش یا رکورد" @keydown.enter.prevent="loadActivityLogs" />
        <select v-model="activityFilters.event" @change="loadActivityLogs">
          <option value="">همه عملیات‌ها</option>
          <option value="created">ایجاد</option>
          <option value="updated">ویرایش</option>
          <option value="deleted">حذف</option>
          <option value="login">ورود</option>
          <option value="logout">خروج</option>
          <option value="sms_sent">ارسال پیامک</option>
          <option value="sms_failed">خطای پیامک</option>
          <option value="role_permissions_updated">تغییر دسترسی نقش</option>
        </select>
        <input v-model.trim="activityFilters.section" placeholder="بخش مثل پرونده‌ها یا نوبت‌دهی" @keydown.enter.prevent="loadActivityLogs" />
        <button type="button" @click="loadActivityLogs">اعمال</button>
      </div>

      <div v-if="activityError" class="activity-error">{{ activityError }}</div>
      <div class="activity-table-wrap">
        <table class="activity-table">
          <thead>
            <tr>
              <th>زمان</th>
              <th>کاربر</th>
              <th>بخش</th>
              <th>عملیات</th>
              <th>رکورد</th>
              <th>تغییرات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in activityLogs" :key="log.id">
              <td>{{ formatActivityDate(log.created_at) }}</td>
              <td>{{ log.user_name || 'سیستم' }}</td>
              <td>{{ log.section || '-' }}</td>
              <td><span :class="['event-badge', `event-${log.event}`]">{{ log.event_label || log.event }}</span></td>
              <td>{{ log.subject_label || log.subject_id || '-' }}</td>
              <td>
                <button type="button" class="activity-details-btn" @click="openLogDetails(log)">
                  مشاهده
                </button>
              </td>
            </tr>
            <tr v-if="!activityLoading && !activityLogs.length">
              <td colspan="6">فعلا سابقه‌ای ثبت نشده است.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-else class="activity-denied">
      دسترسی مشاهده سوابق برای شما فعال نیست.
    </section>

    <div v-if="activeLog" class="activity-modal-backdrop" @click.self="activeLog = null">
      <section class="activity-modal" role="dialog" aria-modal="true" aria-labelledby="activity-detail-title">
        <header>
          <div>
            <small>اطلاعات ثبت سابقه</small>
            <h3 id="activity-detail-title">جزئیات ثبت</h3>
          </div>
          <button type="button" class="activity-modal-close" title="بستن" @click="activeLog = null">×</button>
        </header>

        <div class="activity-modal-meta">
          <span>کاربر: <b>{{ activeLog.user_name || 'سیستم' }}</b></span>
          <span>زمان: <b>{{ formatActivityDate(activeLog.created_at) }}</b></span>
        </div>

        <button type="button" class="activity-expand-details" @click="showActiveLogDetails = !showActiveLogDetails">
          {{ showActiveLogDetails ? 'بستن جزئیات' : 'مشاهده جزئیات' }}
        </button>

        <div v-if="showActiveLogDetails" class="activity-detail-table-wrap">
          <table class="activity-detail-table">
            <thead>
              <tr>
                <th>آیتم</th>
                <th>قبل</th>
                <th>بعد</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in activeLogChangeRows" :key="row.key">
                <td>{{ row.key }}</td>
                <td><pre>{{ row.oldValue }}</pre></td>
                <td><pre>{{ row.newValue }}</pre></td>
              </tr>
              <tr v-if="!activeLogChangeRows.length">
                <td colspan="3">تغییری برای نمایش ثبت نشده است.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</template>

<script>
export default {
  name: "ActivityLogs",
  props: {
    permissions: { type: Array, default: () => [] }
  },
  data() {
    return {
      activityLoading: false,
      activityError: "",
      activityLogs: [],
      activeLog: null,
      showActiveLogDetails: false,
      activityFilters: {
        q: "",
        event: "",
        section: "",
      },
    };
  },
  computed: {
    canViewActivityLogs() {
      return this.permissions.includes("activity_logs.view");
    },
    activeLogChangeRows() {
      return this.activityChangeRows(this.activeLog);
    },
  },
  mounted() {
    if (this.canViewActivityLogs) this.loadActivityLogs();
  },
  methods: {
    openLogDetails(log) {
      this.activeLog = log;
      this.showActiveLogDetails = false;
    },

    async loadActivityLogs() {
      if (!this.canViewActivityLogs) return;
      this.activityLoading = true;
      this.activityError = "";
      try {
        const params = new URLSearchParams();
        Object.entries(this.activityFilters).forEach(([key, value]) => {
          if (value) params.set(key, value);
        });
        params.set("per_page", "40");
        const response = await fetch(`/api/activity-logs?${params}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "دریافت سوابق انجام نشد");
        this.activityLogs = Array.isArray(data.data) ? data.data : [];
      } catch (error) {
        this.activityError = error.message || "دریافت سوابق انجام نشد";
      } finally {
        this.activityLoading = false;
      }
    },
    formatActivityDate(value) {
      if (!value) return "-";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      }).format(date);
    },
    formatActivityValue(key, value) {
      if (value === null || value === undefined || value === "") return "-";
      const keyName = String(key || "").toLowerCase();
      const looksLikeDateKey = keyName.includes("date") || keyName.endsWith("_at");
      if (looksLikeDateKey && typeof value === "string") {
        return this.formatActivityDate(value);
      }
      if (typeof value === "object") return JSON.stringify(value, null, 2);
      return value;
    },
    summarizeActivity(log) {
      const oldValues = log.old_values || {};
      const newValues = log.new_values || {};
      const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])].slice(0, 8);
      if (!keys.length && log.metadata) return JSON.stringify(log.metadata, null, 2);
      if (!keys.length) return "-";
      return keys
        .map(key => `${key}: ${this.formatActivityValue(key, oldValues[key])} ← ${this.formatActivityValue(key, newValues[key])}`)
        .join("\n");
    },
    activityChangeRows(log) {
      if (!log) return [];
      const oldValues = log.old_values || {};
      const newValues = log.new_values || {};
      const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])];
      if (keys.length) {
        return keys.map(key => ({
          key,
          oldValue: this.formatActivityValue(key, oldValues[key]),
          newValue: this.formatActivityValue(key, newValues[key]),
        }));
      }
      const metadata = log.metadata || {};
      return Object.keys(metadata).map(key => ({
        key,
        oldValue: "-",
        newValue: this.formatActivityValue(key, metadata[key]),
      }));
    },
  },
};
</script>

<style scoped>
.activity-page{min-height:calc(100vh - 150px);padding:18px;border-radius:18px;background:#f1f4f9;color:#0f172a}.activity-panel,.activity-denied{padding:16px;border:1px solid #dbe3ef;border-radius:14px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.06)}.activity-panel header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.activity-panel small{color:#2563eb;font-size:11px;font-weight:900}.activity-panel h3{margin:3px 0 0;font-size:17px}.activity-panel button{height:38px;border:0;border-radius:9px;padding:0 14px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900;cursor:pointer}.activity-panel button:disabled{opacity:.55;cursor:wait}.activity-filters{display:grid;grid-template-columns:minmax(240px,1.4fr) minmax(150px,.8fr) minmax(190px,1fr) auto;gap:8px;margin-bottom:12px}.activity-filters input,.activity-filters select{height:38px;min-width:0;border:1px solid #dbe3ef;border-radius:9px;padding:0 10px;background:#fff;font-family:inherit;font-size:12px;outline:none}.activity-error{margin-bottom:10px;padding:10px;border-radius:9px;background:#fef2f2;color:#b91c1c;font-size:12px;font-weight:800}.activity-table-wrap{max-height:calc(100vh - 290px);overflow:auto;border:1px solid #e2e8f0;border-radius:11px}.activity-table{width:100%;border-collapse:collapse;background:#fff;font-size:12px}.activity-table th,.activity-table td{border-bottom:1px solid #eef2f7;padding:9px;vertical-align:top;text-align:right}.activity-table th{position:sticky;top:0;z-index:1;background:#f8fafc;color:#475569;font-size:11px}.activity-details-btn{height:32px!important;padding:0 12px!important;border:1px solid #bfdbfe!important;border-radius:9px!important;background:#eff6ff!important;color:#1d4ed8!important;font-size:11px!important}.event-badge{display:inline-flex;align-items:center;min-width:54px;justify-content:center;border-radius:999px;padding:4px 8px;background:#eef2ff;color:#3730a3;font-size:10px;font-weight:900}.event-deleted,.event-sms_failed{background:#fee2e2;color:#b91c1c}.event-created,.event-sms_sent{background:#dcfce7;color:#047857}.event-updated,.event-role_permissions_updated{background:#fef3c7;color:#92400e}.activity-denied{display:grid;place-items:center;min-height:220px;color:#64748b;font-weight:900}.activity-modal-backdrop{position:fixed;inset:0;z-index:1000000;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.5);backdrop-filter:blur(4px)}.activity-modal{width:min(900px,96vw);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border:1px solid #dbe3ef;border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.32)}.activity-modal>header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid #eef2f7}.activity-modal>header small{color:#2563eb;font-size:11px;font-weight:900}.activity-modal>header h3{margin:4px 0 0;font-size:18px}.activity-modal-close{width:36px;height:36px;border:0;border-radius:10px;background:#f1f5f9;color:#64748b;font-size:24px;cursor:pointer}.activity-modal-meta{display:flex;flex-wrap:wrap;gap:10px;padding:12px 20px;border-bottom:1px solid #eef2f7;background:#f8fafc;color:#64748b;font-size:12px}.activity-modal-meta b{color:#0f172a}.activity-detail-table-wrap{overflow:auto;padding:0}.activity-detail-table{width:100%;border-collapse:collapse;font-size:12px}.activity-detail-table th,.activity-detail-table td{padding:10px 12px;border-bottom:1px solid #eef2f7;vertical-align:top;text-align:right}.activity-detail-table th{position:sticky;top:0;background:#fff;color:#475569;font-size:11px}.activity-detail-table td:first-child{width:180px;color:#1e293b;font-weight:900}.activity-detail-table pre{max-width:330px;margin:0;white-space:pre-wrap;word-break:break-word;color:#475569;font-family:inherit;font-size:11px;line-height:1.8}@media(max-width:768px){.activity-page{padding:12px}.activity-filters{grid-template-columns:1fr}.activity-panel header{align-items:stretch;flex-direction:column}.activity-table-wrap{max-height:none}.activity-modal{width:100%;max-height:92vh}.activity-detail-table td:first-child{width:auto}}
.activity-expand-details{align-self:flex-start;margin:12px 20px;padding:0;border:0;background:transparent!important;color:#2563eb!important;font-family:inherit;font-size:11px;font-weight:900;cursor:pointer;text-decoration:underline;text-underline-offset:3px}.activity-expand-details:hover{color:#1d4ed8!important}.activity-detail-table-wrap{border-top:1px solid #eef2f7}
</style>
