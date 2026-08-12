<template>
  <div class="ticket-page">

    <!-- دکمه افزودن تیکت -->
    <button class="add-ticket-btn" @click="openTicketForm">
      افزودن تیکت
    </button>

    <section v-if="todayTickets.length" class="today-ticket-alert" aria-live="polite">
      <div>
        <span>تیکت‌های امروز</span>
        <strong>{{ todayTickets.length.toLocaleString("fa-IR") }} تیکت فعال برای امروز دارید</strong>
        <small>{{ todayTicketSubjects }}</small>
      </div>
      <button type="button" @click="scrollToTodayTicket">مشاهده</button>
    </section>

    <!-- فرم افزودن -->
    <div v-if="showForm" class="popup-overlay" @click.self="showForm = false">
      <div class="popup-form" @click.stop>
        <header class="popup-head">
          <div>
            <small>ثبت کار داخلی</small>
            <h3>افزودن تیکت جدید</h3>
          </div>
          <button type="button" aria-label="بستن" @click="showForm = false">×</button>
        </header>

        <label class="form-field">
          <span>موضوع</span>
          <input v-model="newTicket.subject" placeholder="مثلا پیگیری تماس بیمار" />
        </label>

        <label class="form-field">
          <span>توضیحات</span>
          <textarea v-model="newTicket.description" placeholder="جزئیات کار، توضیح یا نکته لازم"></textarea>
        </label>

        <div class="form-grid">
          <label class="form-field priority-field">
            <span>اهمیت</span>
            <select v-model="newTicket.priority">
              <option value="low">کم</option>
              <option value="medium">متوسط</option>
              <option value="high">زیاد</option>
              <option value="urgent">فوری</option>
            </select>
          </label>

          <label class="form-field date-field">
            <span>تاریخ</span>
            <date-picker
              v-model="newTicket.date"
              format="jYYYY-jMM-jDD"
              display-format="jYYYY-jMM-jDD"
              auto-submit
              popover="bottom-left"
              append-to="body"
              input-class="date-input"
              placeholder="انتخاب تاریخ"
              color="#0f766e"
            />
          </label>
        </div>

        <label class="form-field">
          <span>مسئول</span>
          <select v-model="newTicket.owner" :disabled="staffLoading">
            <option value="">{{ staffOwnerPlaceholder }}</option>
            <option v-for="person in staffOptions" :key="person.id || person.name" :value="person.name">
              {{ person.name }}
            </option>
          </select>
          <button type="button" class="reload-staff-btn" :disabled="staffLoading" @click="loadStaffOptions">
            به‌روزرسانی لیست پرسنل
          </button>
          <small v-if="staffError" class="field-error">{{ staffError }}</small>
        </label>

        <div class="popup-actions">
          <button class="submit-btn" :disabled="!newTicket.subject || !newTicket.date || !newTicket.owner" @click="addTicket">افزودن</button>
          <button class="cancel-btn" @click="showForm = false">لغو</button>
        </div>
      </div>
    </div>

    <!-- پاپ آپ فعال‌سازی -->
    <div v-if="showActivatePopup" class="popup-overlay" @click.self="showActivatePopup = false">
      <div class="popup-form" @click.stop>
        <h3>تاریخ جدید برای فعال شدن</h3>

        <date-picker
          v-model="activateDate"
          format="jYYYY-jMM-jDD"
          display-format="jYYYY-jMM-jDD"
          auto-submit
          popover="bottom-left"
          append-to="body"
          input-class="date-input"
          placeholder="تاریخ جدید"
          color="#0f766e"
        />

        <div class="popup-actions">
          <button class="submit-btn" @click="confirmActivateTicket">تایید</button>
          <button class="cancel-btn" @click="showActivatePopup = false">لغو</button>
        </div>
      </div>
    </div>

    <!-- سه ستون -->
    <div class="tickets-container">

      <!-- فعال -->
      <div class="active-tickets">
        <h3>تیکت‌های فعال</h3>

        <draggable v-model="tickets" handle=".drag-handle" animation="200">
          <template #item="{ element, index }">
            <div
  class="ticket-item"
  :class="{
    today: isToday(element.date)
  }"
>

              <span class="drag-handle">☰</span>

              <div class="ticket-info">
                <div class="ticket-title"><strong>{{ element.subject }}</strong><span :class="['priority-badge', `priority-${element.priority || 'medium'}`]">{{ priorityLabel(element.priority) }}</span></div>
                <p>{{ element.description }}</p>
                <small>
                  مسئول: {{ element.owner }} | تاریخ: {{ element.date }}
                </small>
              </div>

              <div class="ticket-actions">
                <button class="done-btn" @click="markDone(index)">انجام شد</button>
                <button v-if="canDeleteTickets" class="delete-btn" @click="deleteActive(index)">حذف</button>
              </div>
            </div>
          </template>
        </draggable>
      </div>

      <!-- انجام شده -->
      <div class="done-tickets">
        <h3>تیکت‌های انجام شده</h3>

        <div v-for="(ticket, index) in doneTickets"
             :key="ticket.id"
             class="ticket-item done">

          <div class="ticket-info">
            <div class="ticket-title"><strong>{{ ticket.subject }}</strong><span :class="['priority-badge', `priority-${ticket.priority || 'medium'}`]">{{ priorityLabel(ticket.priority) }}</span></div>
            <p>{{ ticket.description }}</p>
            <small>مسئول: {{ ticket.owner }} | تاریخ: {{ ticket.date }}</small>
          </div>

          <button v-if="canDeleteTickets" class="delete-btn" @click="deleteDone(index)">
            حذف
          </button>
        </div>
      </div>

      <!-- منقضی -->
      <div class="expired-tickets">
        <h3>تیکت‌های منقضی</h3>

        <div v-for="(ticket, index) in expiredTickets"
             :key="ticket.id"
             class="ticket-item expired">

          <div class="ticket-info">
            <div class="ticket-title"><strong>{{ ticket.subject }}</strong><span :class="['priority-badge', `priority-${ticket.priority || 'medium'}`]">{{ priorityLabel(ticket.priority) }}</span></div>
            <p>{{ ticket.description }}</p>
            <small>مسئول: {{ ticket.owner }} | تاریخ: {{ ticket.date }}</small>
          </div>

          <div style="width: 153px;">
            <button class="activate-btn" @click="openActivatePopup(index)">
              فعال کردن
            </button>

            <button v-if="canDeleteTickets" class="delete-btn" @click="deleteExpired(index)">
              حذف
            </button>
          </div>
        </div>

      </div>

    </div>

  </div>
</template>

<script>
import { defineComponent } from "vue";
import draggable from "vuedraggable";
import DatePicker from "vue3-persian-datetime-picker";

export default defineComponent({
  name: "Ticket",
  components: {
    draggable,
    DatePicker
  },

  props: {
    permissions: { type: Array, default: () => [] }
  },

  data() {
    return {
      showForm: false,

      newTicket: {
        subject: "",
        description: "",
        date: "",
        owner: "",
        priority: "medium"
      },

      tickets: [],
      doneTickets: [],
      expiredTickets: [],

      showActivatePopup: false,
      activateIndex: null,
      activateDate: "",
      staffOptions: [],
      staffLoading: false,
      staffError: ""
    };
  },

  computed: {
    canDeleteTickets() {
      return this.permissions.includes("tickets.delete");
    },
    todayTickets() {
      return this.tickets.filter(ticket => this.isToday(ticket.date));
    },
    todayTicketSubjects() {
      return this.todayTickets
        .slice(0, 3)
        .map(ticket => ticket.subject || "تیکت بدون موضوع")
        .join("، ");
    },
    staffOwnerPlaceholder() {
      if (this.staffLoading) return "در حال دریافت پرسنل...";
      if (!this.staffOptions.length) return "ابتدا پرسنل را در بخش پرسنل تعریف کنید";
      return "انتخاب از کارمندان کلینیک";
    }
  },

  mounted() {
    this.loadTickets();
    this.loadStaffOptions();
    document.addEventListener("pointerdown", this.keepDatePickerOpen, true);
  },

  beforeUnmount() {
    document.removeEventListener("pointerdown", this.keepDatePickerOpen, true);
  },

  watch: {
    tickets: {
      handler() { this.saveTickets(); },
      deep: true
    },
    doneTickets: {
      handler() { this.saveTickets(); },
      deep: true
    },
    expiredTickets: {
      handler() { this.saveTickets(); },
      deep: true
    }
  },

  methods: {
    openTicketForm() {
      this.showForm = true;
      this.loadStaffOptions();
    },

    keepDatePickerOpen(event) {
      if (event.target?.closest?.(".vpd-wrapper, .vpd-container, .vpd-main")) {
        event.stopPropagation();
      }
    },

    async loadTickets() {
      try {
        const response = await fetch("/api/tickets", { headers: { Accept: "application/json" } });
        if (!response.ok) throw new Error("دریافت تیکت‌ها انجام نشد");
        const all = await response.json();
        this.tickets = all.filter(ticket => ticket.status === "active");
        this.doneTickets = all.filter(ticket => ticket.status === "done");
        this.expiredTickets = all.filter(ticket => ticket.status === "expired");
        this.sortTickets();
        this.saveTickets();
      } catch {
        const state = JSON.parse(localStorage.getItem("tickets_v1") || "{}");
        this.tickets = Array.isArray(state.tickets) ? state.tickets : [];
        this.doneTickets = Array.isArray(state.doneTickets) ? state.doneTickets : [];
        this.expiredTickets = Array.isArray(state.expiredTickets) ? state.expiredTickets : [];
      }
    },

    async loadStaffOptions() {
      this.staffLoading = true;
      this.staffError = "";
      try {
        const response = await fetch("/api/staff", { headers: { Accept: "application/json" } });
        if (!response.ok) throw new Error("دریافت لیست پرسنل انجام نشد");
        const staff = await response.json();
        this.staffOptions = (Array.isArray(staff) ? staff : [])
          .map(person => ({
            id: person.id,
            name: String(person.name || "").trim(),
            avatar: person.avatar_url || person.profile_thumbnail_url || person.profile_photo_url || ""
          }))
          .filter(person => person.name);
      } catch (error) {
        this.staffError = error.message || "دریافت لیست پرسنل انجام نشد";
      } finally {
        this.staffLoading = false;
      }
    },

    saveTickets() {
      localStorage.setItem("tickets_v1", JSON.stringify({
        tickets: this.tickets,
        doneTickets: this.doneTickets,
        expiredTickets: this.expiredTickets
      }));
      window.dispatchEvent(new CustomEvent("app:notifications-changed"));
    },

    // 🔥 تبدیل اعداد فارسی به انگلیسی + نرمال‌سازی تاریخ
    normalize(date) {
      if (!date) return "";

      const map = {
        "۰":"0","۱":"1","۲":"2","۳":"3","۴":"4",
        "۵":"5","۶":"6","۷":"7","۸":"8","۹":"9",
        "٠":"0","١":"1","٢":"2","٣":"3","٤":"4",
        "٥":"5","٦":"6","٧":"7","٨":"8","٩":"9"
      };

      return date
        .toString()
        .trim()
        .replace(/[۰-۹٠-٩]/g, (d) => map[d])
        .replace(/\//g, "-");
    },

    // 🔥 تبدیل به عدد قابل مقایسه
    toNumber(date) {
      return Number(this.normalize(date).replace(/-/g, ""));
    },

    // 🔥 گرفتن امروز (جلالی واقعی از خود JS)
    getToday() {
      const now = new Date();

      const p = new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit"
      }).format(now);

      return this.normalize(p);
    },
    // 🔥 بررسی اینکه تاریخ امروز هست یا نه
isToday(date) {
  if (!date) return false;

  const clean = (v) =>
    String(v)
      .replace(/[۰-۹٠-٩]/g, d => "0123456789"[d.charCodeAt(0) % 1776 % 10])
      .replace(/\//g, "-")
      .replace(/\s/g, "")
      .trim();

  const d1 = clean(date);
  const d2 = clean(this.getToday());

  return d1 === d2;
},

// 🔥 پیدا کردن اولین تیکت با تاریخ امروز
getFirstTodayIndex() {
  return this.tickets
    .map((t, i) => ({ t, i }))
    .find(item => this.isToday(item.t.date))?.i ?? -1;
},
    

    // 🔥 آیا گذشته است؟
    isExpired(date) {
      return this.toNumber(date) < this.toNumber(this.getToday());
    },

    async addTicket() {
      if (!this.newTicket.subject || !this.newTicket.date || !this.newTicket.owner) return;
      if (!this.staffOptions.some(person => person.name === this.newTicket.owner)) return;

      const draft = {
        ...this.newTicket,
        date: this.normalize(String(this.newTicket.date)),
        status: this.isExpired(this.newTicket.date) ? "expired" : "active"
      };
      const response = await fetch("/api/tickets", { method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json" }, body: JSON.stringify(draft) });
      if (!response.ok) return;
      const ticket = await response.json();
      if (ticket.status === "expired") {
        this.expiredTickets.push(ticket);
      } else {
        this.tickets.push(ticket);
        this.sortTickets();
      }
      this.notifyTicketsChanged();

      this.newTicket = {
        subject: "",
        description: "",
        date: "",
        owner: "",
        priority: "medium"
      };

      this.showForm = false;
    },

    priorityLabel(priority) {
      return ({ low: "کم", medium: "متوسط", high: "زیاد", urgent: "فوری" })[priority] || "متوسط";
    },

    async markDone(index) {
      const ticket = this.tickets[index];
      const response = await fetch(`/api/tickets/${ticket.id}`, { method: "PUT", headers: { Accept: "application/json", "Content-Type": "application/json" }, body: JSON.stringify({ status: "done" }) });
      if (!response.ok) return;
      this.doneTickets.push({ ...ticket, status: "done" });
      this.tickets.splice(index, 1);
      this.notifyTicketsChanged();
    },

    async deleteActive(index) {
      await fetch(`/api/tickets/${this.tickets[index].id}`, { method: "DELETE", headers: { Accept: "application/json" } });
      this.tickets.splice(index, 1);
      this.notifyTicketsChanged();
    },

    async deleteDone(index) {
      await fetch(`/api/tickets/${this.doneTickets[index].id}`, { method: "DELETE", headers: { Accept: "application/json" } });
      this.doneTickets.splice(index, 1);
      this.notifyTicketsChanged();
    },

    async deleteExpired(index) {
      await fetch(`/api/tickets/${this.expiredTickets[index].id}`, { method: "DELETE", headers: { Accept: "application/json" } });
      this.expiredTickets.splice(index, 1);
      this.notifyTicketsChanged();
    },

    openActivatePopup(index) {
      this.activateIndex = index;
      this.activateDate = this.expiredTickets[index].date;
      this.showActivatePopup = true;
    },

    async confirmActivateTicket() {
      if (this.activateIndex === null || !this.activateDate) return;

      const ticket = {
        ...this.expiredTickets[this.activateIndex],
        date: this.normalize(this.activateDate)
      };
      const response = await fetch(`/api/tickets/${ticket.id}`, { method: "PUT", headers: { Accept: "application/json", "Content-Type": "application/json" }, body: JSON.stringify({ date: ticket.date, status: "active" }) });
      if (!response.ok) return;
      ticket.status = "active";

      this.tickets.push(ticket);
      this.sortTickets();

      this.expiredTickets.splice(this.activateIndex, 1);

      this.activateIndex = null;
      this.activateDate = "";
      this.showActivatePopup = false;
      this.notifyTicketsChanged();
    },

    sortTickets() {
      this.tickets.sort((a, b) => {
        return this.toNumber(a.date) - this.toNumber(b.date);
      });
    },

    notifyTicketsChanged() {
      window.dispatchEvent(new CustomEvent("app:notifications-changed"));
    },

    scrollToTodayTicket() {
      this.$nextTick(() => {
        document.querySelector(".ticket-item.today")?.scrollIntoView({ behavior: "smooth", block: "center" });
      });
    }
  }
});
</script>

<style scoped>
.ticket-page {
  padding: 20px;
  font-family: Vazir;
  max-width: 1200px;
  margin: auto;
}

/* دکمه افزودن */
.add-ticket-btn {
  background: #007aff;
  color: white;
  border: none;
  padding: 10px 16px;
  border-radius: 8px;
  cursor: pointer;
  margin-bottom: 20px;
}
.add-ticket-btn:hover { opacity: 0.85; }

.today-ticket-alert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin: 0 0 18px;
  padding: 14px 16px;
  border: 1px solid #fecaca;
  border-right: 5px solid #dc2626;
  border-radius: 12px;
  background: linear-gradient(135deg, #fff, #fff7f7);
  color: #0f172a;
  box-shadow: 0 10px 24px rgba(220, 38, 38, .08);
}

.today-ticket-alert div {
  display: grid;
  gap: 4px;
}

.today-ticket-alert span {
  color: #dc2626;
  font-size: 11px;
  font-weight: 900;
}

.today-ticket-alert strong {
  font-size: 15px;
}

.today-ticket-alert small {
  color: #64748b;
  font-size: 12px;
}

.today-ticket-alert button {
  min-width: 86px;
  height: 36px;
  border: 0;
  border-radius: 9px;
  background: #dc2626;
  color: #fff;
  font-family: inherit;
  font-weight: 900;
  cursor: pointer;
}

/* پاپ‌آپ */
.popup-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15,23,42,0.52);
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
  z-index: 100000;
  backdrop-filter: blur(4px);
}
.popup-form {
  width: min(620px, 96vw);
  max-height: 92vh;
  overflow: visible;
  background: #fff;
  padding: 22px;
  border: 1px solid rgba(255,255,255,.74);
  border-radius: 18px;
  display: grid;
  gap: 16px;
  box-shadow: 0 28px 80px rgba(15,23,42,.30);
}
.popup-form input, .popup-form textarea, .popup-form select {
  width: 100%;
  min-width: 0;
  min-height: 44px;
  box-sizing: border-box;
  padding: 0 12px;
  font-size: 13px;
  border-radius: 11px;
  border: 1px solid #d8e1ee;
  background: #fff;
  color: #0f172a;
  font-family: inherit;
  outline: 0;
  transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
}
.popup-form textarea {
  min-height: 92px;
  padding: 12px;
  resize: vertical;
  line-height: 1.9;
}
.popup-form input:focus,
.popup-form textarea:focus,
.popup-form select:focus {
  border-color: #2563eb;
  background: #f8fbff;
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}
.popup-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  padding-bottom: 14px;
  border-bottom: 1px solid #edf2f7;
}
.popup-head small {
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}
.popup-head h3 {
  margin: 4px 0 0;
  text-align: right;
}
.popup-head button {
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 10px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 24px;
  cursor: pointer;
}
.form-field {
  display: grid;
  gap: 7px;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}
.form-field>span {
  padding-right: 2px;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.priority-field{color:#334155;font-size:12px}.priority-field select{background:#fff}
.date-field :deep(.vpd-input-group) {
  width: 100% !important;
  height: 44px !important;
  display: flex !important;
  overflow: hidden;
  border: 1px solid #d8e1ee;
  border-radius: 11px;
  background: #fff;
}
.date-field :deep(.vpd-input-group input.date-input) {
  width: calc(100% - 44px) !important;
  height: 44px !important;
  min-height: 44px !important;
  border: 0 !important;
  border-radius: 0 !important;
  padding: 0 12px !important;
  background: transparent !important;
  box-shadow: none !important;
  font-family: inherit;
}
.date-field :deep(.vpd-icon-btn) {
  width: 44px !important;
  height: 44px !important;
  flex: 0 0 44px !important;
  border: 0 !important;
  border-radius: 0 !important;
  background: #0f766e !important;
}
.date-field :deep(.vpd-input-group:focus-within) {
  border-color: #2563eb;
  background: #f8fbff;
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}
:global(.vpd-container),
:global(.vpd-wrapper) {
  z-index: 100001 !important;
}
.field-error {
  color: #dc2626;
  font-size: 10px;
  font-weight: 800;
}
.reload-staff-btn {
  width: max-content;
  height: 30px;
  padding: 0 10px;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  font-family: inherit;
  font-size: 10px;
  font-weight: 900;
  cursor: pointer;
}
.reload-staff-btn:disabled {
  opacity: .55;
  cursor: wait;
}
.popup-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 4px;
}
.submit-btn { min-width: 112px; height: 42px; background: #16a34a; color: white; border: none; padding: 0 22px !important; border-radius: 11px; cursor: pointer; font-family: inherit; font-weight: 900; }
.submit-btn:disabled { background: #cbd5e1; cursor: not-allowed; }
.cancel-btn { min-width: 92px; height: 42px; background: #fee2e2; color: #b91c1c; border: none; padding: 0 18px; border-radius: 11px; cursor: pointer; font-family: inherit; font-weight: 900; }

/* سه ستون تیکت‌ها */
.tickets-container { display: flex; gap: 20px; }

/* ستون‌ها */
.active-tickets { flex: 1; display: flex; flex-direction: column; align-items: flex-start; }
.done-tickets { flex: 1; display: flex; flex-direction: column; align-items: center; }
.expired-tickets { flex: 1; display: flex; flex-direction: column; align-items: flex-end; }

/* کارت تیکت */
.ticket-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  background: #f9f9f9;
  padding: 10px 12px;
  border-radius: 8px;
  margin-bottom: 10px;
  cursor: grab;
  width: 100%;

  border-left: 4px solid transparent;
  transition: all 0.2s ease;
}

.ticket-item.today { 
  border-left-color: #dc2626;
  background: #fff7f7;
  box-shadow: 0 8px 20px rgba(220, 38, 38, .1);
}

.ticket-item.done { 
  background: #d4f8d4; 
}

.ticket-item.expired { 
  background: #f8d4d4; 
}

.blinking {
  animation: blink 1s infinite;
  border-left-color: red;
}

@keyframes blink {
  0%, 50%, 100% { border-left-color: red; }
  25%, 75% { border-left-color: rgb(255, 255, 0); }
}

.drag-handle { 
  cursor: grab; 
  margin-right: 10px; 
  font-size: 18px; 
  color: #555; 
}

.ticket-info p { 
  margin: 4px 0; 
  font-size: 14px; 
}
.ticket-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.priority-badge{padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800}.priority-low{background:#e2e8f0;color:#475569}.priority-medium{background:#dbeafe;color:#1d4ed8}.priority-high{background:#ffedd5;color:#c2410c}.priority-urgent{background:#fee2e2;color:#b91c1c;animation:priorityPulse 1.4s ease-in-out infinite}@keyframes priorityPulse{50%{box-shadow:0 0 0 4px rgba(239,68,68,.14)}}
.ticket-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}

/* دکمه‌ها */
.done-btn { background: #007aff; color: white; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer; }
.done-btn:hover { opacity: 0.9; }
.delete-btn { background: #ff3b30; color: white; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer; }
.delete-btn:hover { opacity: 0.85; }
.activate-btn { background: #1c356b; color: white; border: none; border-radius: 6px; padding: 4px 8px; cursor: pointer; margin-right: 5px; }
.activate-btn:hover { opacity: 0.9; }

h3 { margin-bottom: 10px; text-align: center; }

@media(max-width: 760px) {
  .popup-form {
    overflow: auto;
  }

  .form-grid,
  .tickets-container {
    grid-template-columns: 1fr;
  }

  .tickets-container {
    display: grid;
  }

  .popup-actions {
    flex-direction: column;
  }

  .submit-btn,
  .cancel-btn {
    width: 100%;
  }
}
</style>
