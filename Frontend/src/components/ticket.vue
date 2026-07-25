<template>
  <div class="ticket-page">

    <!-- دکمه افزودن تیکت -->
    <button class="add-ticket-btn" @click="showForm = true">
      افزودن تیکت
    </button>

    <!-- فرم افزودن -->
    <div v-if="showForm" class="popup-overlay" @click.self="showForm = false">
      <div class="popup-form">
        <h3>افزودن تیکت جدید</h3>

        <input v-model="newTicket.subject" placeholder="موضوع" />
        <textarea v-model="newTicket.description" placeholder="توضیحات"></textarea>

        <label class="priority-field">
          <span>اهمیت</span>
          <select v-model="newTicket.priority">
            <option value="low">کم</option>
            <option value="medium">متوسط</option>
            <option value="high">زیاد</option>
            <option value="urgent">فوری</option>
          </select>
        </label>

        <!-- تاریخ -->
        <date-picker
          v-model="newTicket.date"
          format="jYYYY-jMM-jDD"
          display-format="jYYYY-jMM-jDD"
          auto-submit
          input-class="date-input"
          placeholder="تاریخ"
          color="#0f766e"
        />

        <input v-model="newTicket.owner" placeholder="مسئول" />

        <div class="popup-actions">
          <button class="submit-btn" @click="addTicket">افزودن</button>
          <button class="cancel-btn" @click="showForm = false">لغو</button>
        </div>
      </div>
    </div>

    <!-- پاپ آپ فعال‌سازی -->
    <div v-if="showActivatePopup" class="popup-overlay" @click.self="showActivatePopup = false">
      <div class="popup-form">
        <h3>تاریخ جدید برای فعال شدن</h3>

        <date-picker
          v-model="activateDate"
          format="jYYYY-jMM-jDD"
          display-format="jYYYY-jMM-jDD"
          auto-submit
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
    blinking: isToday(element.date)
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
      activateDate: ""
    };
  },

  computed: {
    canDeleteTickets() {
      return this.permissions.includes("tickets.delete");
    }
  },

  mounted() {
    this.loadTickets();
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

    async loadTickets() {
      try {
        const response = await fetch("/api/tickets", { headers: { Accept: "application/json" } });
        if (!response.ok) throw new Error("دریافت تیکت‌ها انجام نشد");
        const all = await response.json();
        this.tickets = all.filter(ticket => ticket.status === "active");
        this.doneTickets = all.filter(ticket => ticket.status === "done");
        this.expiredTickets = all.filter(ticket => ticket.status === "expired");
        this.sortTickets();
      } catch {
        const state = JSON.parse(localStorage.getItem("tickets_v1") || "{}");
        this.tickets = Array.isArray(state.tickets) ? state.tickets : [];
        this.doneTickets = Array.isArray(state.doneTickets) ? state.doneTickets : [];
        this.expiredTickets = Array.isArray(state.expiredTickets) ? state.expiredTickets : [];
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
      if (!this.newTicket.subject || !this.newTicket.date) return;

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
    },

    async deleteActive(index) {
      await fetch(`/api/tickets/${this.tickets[index].id}`, { method: "DELETE", headers: { Accept: "application/json" } });
      this.tickets.splice(index, 1);
    },

    async deleteDone(index) {
      await fetch(`/api/tickets/${this.doneTickets[index].id}`, { method: "DELETE", headers: { Accept: "application/json" } });
      this.doneTickets.splice(index, 1);
    },

    async deleteExpired(index) {
      await fetch(`/api/tickets/${this.expiredTickets[index].id}`, { method: "DELETE", headers: { Accept: "application/json" } });
      this.expiredTickets.splice(index, 1);
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
    },

    sortTickets() {
      this.tickets.sort((a, b) => {
        return this.toNumber(a.date) - this.toNumber(b.date);
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

/* پاپ‌آپ */
.popup-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.4);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 100;
}
.popup-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    width: 30%;
    height: 50%;
    display: flex;
    flex-direction: column;
    gap: 8px;
    justify-content: space-between;
}
.popup-form input, .popup-form textarea, .popup-form select {
  padding: 6px 8px;
  font-size: 13px;
  border-radius: 5px;
  border: 1px solid #ccc;
}
.priority-field{display:flex;align-items:center;gap:10px;color:#334155;font-size:13px}.priority-field select{flex:1;background:#fff}
.popup-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.submit-btn { background: #28a745; color: white; border: none; padding: 6px 30px !important; border-radius: 6px; cursor: pointer; }
.cancel-btn { background: #ff3b30; color: white; border: none; padding: 6px 20px; border-radius: 6px; cursor: pointer; }

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
  border-left: 4px solid red; 
  background: #e0f0ff; 
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
</style>
