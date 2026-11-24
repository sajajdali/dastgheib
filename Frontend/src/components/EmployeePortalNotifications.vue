<template>
  <div class="epn" dir="rtl">
    <button class="epn-bell" type="button" title="اعلان‌های پرسنلی" @click="open = !open">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
      <b v-if="unread">{{ Number(unread).toLocaleString('fa-IR') }}</b>
    </button>
    <button v-if="open" class="epn-backdrop" type="button" aria-label="بستن اعلان‌ها" @click="open = false"></button>
    <section v-if="open" class="epn-panel">
      <header><div class="epn-title"><i><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></i><div><small>پرتال پرسنلی</small><h3>اعلان‌ها</h3></div></div><button v-if="unread" type="button" @click="readAll">خواندن همه</button></header>
      <div class="epn-list">
        <button v-for="item in notifications" :key="item.id" type="button" :class="{ unread: !item.read_at }" @click="select(item)">
          <i class="epn-event"><svg viewBox="0 0 24 24"><path :d="iconPath(item.action_tab)"/></svg></i><span class="epn-copy"><strong>{{ item.title }}</strong><span>{{ item.body || 'برای مشاهده جزئیات کلیک کنید.' }}</span><small>{{ dateTime(item.created_at) }}</small></span><em v-if="!item.read_at"></em>
        </button>
        <p v-if="!notifications.length">اعلانی ثبت نشده است.</p>
      </div>
    </section>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'EmployeePortalNotifications',
  props: { manager: { type: Boolean, default: false } },
  emits: ['open-personal-report', 'open-payroll'],
  data: () => ({ open: false, notifications: [], unread: 0, timer: null }),
  mounted() { this.load(); this.timer = window.setInterval(this.load, 30000) },
  beforeUnmount() { window.clearInterval(this.timer) },
  methods: {
    async load() {
      try {
        const data = (await axios.get('/api/employee-portal/notifications')).data
        this.notifications = data.notifications || []
        this.unread = Number(data.unread_count || 0)
      } catch {}
    },
    async select(item) {
      try { if (!item.read_at) await axios.patch(`/api/employee-portal/notifications/${item.id}/read`) } catch {}
      this.open = false
      await this.load()
      this.$emit(this.manager ? 'open-payroll' : 'open-personal-report', item)
    },
    async readAll() { await axios.patch('/api/employee-portal/notifications/read-all'); await this.load() },
    dateTime(value) { return value ? new Date(value).toLocaleString('fa-IR') : '—' },
    iconPath(tab) {
      return {
        leaves:'M6 3v3M18 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1m4 9 2 2 4-4',
        advances:'M3 7h18v12H3zM16 11h5M6 7V5h12v2',
        messages:'M4 5h16v12H8l-4 4z',
        adjustments:'m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z',
      }[tab] || 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5'
    },
  },
}
</script>

<style scoped>
/* left slots: hamburger 20/46 → gap → bell 84/46 (desktop); 14/42 → 68/42 (mobile) */
.epn{position:fixed;top:23px;left:84px;z-index:2147483008;font-family:Vazir,sans-serif}.epn svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.epn-bell{position:relative;width:46px;height:46px;display:grid;place-items:center;border:1px solid #e5eaf1;border-radius:15px;background:rgba(255,255,255,.96);color:#475569;box-shadow:0 8px 22px rgba(15,23,42,.09);cursor:pointer;transition:.2s}.epn-bell:hover{transform:translateY(-1px);border-color:#bfdbfe;color:#2563eb;box-shadow:0 10px 28px rgba(37,99,235,.15)}.epn-bell b{position:absolute;top:-6px;right:-6px;min-width:19px;height:19px;display:grid;place-items:center;padding:0 4px;border:2px solid #fff;border-radius:10px;background:#ef4444;color:#fff;font-size:9px;line-height:1}.epn-backdrop{position:fixed;inset:0;z-index:-1;border:0;background:rgba(15,23,42,.06);backdrop-filter:blur(1px)}.epn-panel{position:absolute;top:55px;left:0;width:min(370px,calc(100vw - 24px));max-height:72vh;overflow:hidden;border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 24px 65px rgba(15,23,42,.2)}.epn-panel>header{display:flex;align-items:center;justify-content:space-between;padding:12px 13px;border-bottom:1px solid #edf1f6;background:linear-gradient(135deg,#f8fbff,#fff)}.epn-title{display:flex;align-items:center;gap:9px}.epn-title>i{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#eaf2ff;color:#2563eb}.epn-title h3{margin:1px 0 0;font-size:14px}.epn-title small{font-size:9px;color:#94a3b8}.epn-panel header button{padding:5px 8px;border:0;border-radius:8px;background:#eff6ff;color:#2563eb;font:10px inherit;cursor:pointer}.epn-list{max-height:58vh;overflow:auto;padding:7px}.epn-list>button{position:relative;width:100%;display:grid;grid-template-columns:34px 1fr;align-items:center;gap:9px;margin-bottom:5px;padding:9px;border:1px solid transparent;border-radius:12px;background:#f8fafc;text-align:right;font-family:inherit;cursor:pointer;transition:.18s}.epn-list>button:hover{border-color:#dbeafe;background:#f1f7ff}.epn-list>button.unread{background:#eef6ff}.epn-event{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#fff;color:#3b82f6;box-shadow:0 2px 7px rgba(15,23,42,.06)}.epn-event svg{width:17px;height:17px}.epn-copy{min-width:0;display:grid;gap:2px}.epn-copy strong{overflow:hidden;color:#1e293b;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.epn-copy>span{overflow:hidden;color:#64748b;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.epn-copy small{color:#94a3b8;font-size:8px}.epn-list>button>em{position:absolute;top:10px;left:9px;width:6px;height:6px;border-radius:50%;background:#2563eb}.epn-list p{padding:25px;text-align:center;color:#94a3b8;font-size:11px}@media(max-width:600px){.epn{top:29px;left:68px}.epn-bell{width:42px;height:42px;border-radius:13px}.epn-panel{top:50px;left:-51px}}

body.dark .epn-bell,#app.dark .epn-bell{background:rgba(30,41,59,.96);border-color:#334155;color:#e2e8f0;box-shadow:0 8px 22px rgba(0,0,0,.35)}
body.dark .epn-bell:hover,#app.dark .epn-bell:hover{border-color:#3b82f6;color:#93c5fd}
body.dark .epn-bell b,#app.dark .epn-bell b{border-color:#1e293b}
body.dark .epn-panel,#app.dark .epn-panel{background:#172033;border-color:#334155;box-shadow:0 24px 65px rgba(0,0,0,.45)}
body.dark .epn-panel>header,#app.dark .epn-panel>header{background:linear-gradient(135deg,#1e293b,#172033);border-color:#334155}
body.dark .epn-title h3,#app.dark .epn-title h3{color:#f8fafc}
body.dark .epn-title>i,#app.dark .epn-title>i{background:#1e3a5f;color:#93c5fd}
body.dark .epn-panel header button,#app.dark .epn-panel header button{background:#1e3a5f;color:#93c5fd}
body.dark .epn-list>button,#app.dark .epn-list>button{background:#1e293b;color:#e2e8f0}
body.dark .epn-list>button:hover,#app.dark .epn-list>button:hover{background:#253349;border-color:#334155}
body.dark .epn-list>button.unread,#app.dark .epn-list>button.unread{background:#1e3a5f;box-shadow:inset -3px 0 #3b82f6}
body.dark .epn-event,#app.dark .epn-event{background:#172033;color:#60a5fa;box-shadow:0 2px 7px rgba(0,0,0,.2)}
body.dark .epn-copy strong,#app.dark .epn-copy strong{color:#f8fafc}
</style>
