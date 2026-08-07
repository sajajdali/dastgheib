<template>
  <main class="service-ticket-page" dir="rtl">
    <header class="service-ticket-head">
      <div>
        <small>پشتیبانی مدیر کل سیستم</small>
        <h1>سوالات و پشتیبانی‌های سرویس</h1>
      </div>
      <button type="button" @click="$emit('back')">بازگشت به سرویس‌ها</button>
    </header>

    <section v-if="!selectedTicket" class="service-ticket-stats">
      <article><span>کل سوالات</span><strong>{{ tickets.length.toLocaleString('fa-IR') }}</strong></article>
      <article><span>باز</span><strong>{{ countByStatus('open').toLocaleString('fa-IR') }}</strong></article>
      <article><span>جواب داده شده</span><strong>{{ countByStatus('answered').toLocaleString('fa-IR') }}</strong></article>
      <article><span>بسته</span><strong>{{ countByStatus('closed').toLocaleString('fa-IR') }}</strong></article>
    </section>

    <section v-if="!selectedTicket" class="service-ticket-layout">
      <form class="service-ticket-form" @submit.prevent="submitTicket">
        <h2>سوال جدید</h2>
        <input v-model.trim="form.subject" type="text" placeholder="موضوع سوال" required>
        <textarea v-model.trim="form.question" rows="6" placeholder="متن سوال یا درخواست..." required></textarea>
        <label class="service-file-field">
          <span>{{ fileName || 'ضمیمه فایل' }}</span>
          <input type="file" @change="handleFile">
        </label>
        <p v-if="message" class="service-ticket-message">{{ message }}</p>
        <p v-if="error" class="service-ticket-error">{{ error }}</p>
        <button type="submit" :disabled="saving || !form.subject || !form.question">
          {{ saving ? 'در حال ثبت...' : 'ثبت سوال' }}
        </button>
      </form>

      <section class="service-ticket-list">
        <div class="service-ticket-toolbar">
          <strong>سوالات مطرح‌شده</strong>
          <select v-model="statusFilter">
            <option value="all">همه وضعیت‌ها</option>
            <option value="open">باز</option>
            <option value="answered">جواب داده شده</option>
            <option value="closed">بسته</option>
          </select>
        </div>

        <article v-for="ticket in filteredTickets" :key="ticket.id" class="service-ticket-card" @click="openTicket(ticket)">
          <header>
            <div>
              <strong>{{ ticket.subject }}</strong>
              <small>{{ formatDate(ticket.created_at) }} · {{ ticket.user_name || 'مدیر کلینیک' }}</small>
            </div>
            <span :class="['status-pill', `status-${ticket.status}`]">{{ statusLabel(ticket.status) }}</span>
          </header>
          <p>{{ ticket.question }}</p>
          <button type="button" class="service-open-question">مشاهده گفت‌وگو</button>
        </article>

        <div v-if="!filteredTickets.length" class="service-ticket-empty">سوالی در این وضعیت وجود ندارد.</div>
      </section>
    </section>

    <section v-else class="service-chat-page">
      <header>
        <div>
          <small>گفت‌وگوی پشتیبانی</small>
          <h2>{{ selectedTicket.subject }}</h2>
          <span :class="['status-pill', `status-${selectedTicket.status}`]">{{ statusLabel(selectedTicket.status) }}</span>
        </div>
        <button type="button" @click="selectedTicketId = null">بازگشت به سوالات</button>
      </header>

      <div class="service-chat-thread">
        <article class="chat-message from-user">
          <small>{{ selectedTicket.user_name || 'مدیر کلینیک' }} · {{ formatDate(selectedTicket.created_at) }}</small>
          <p>{{ selectedTicket.question }}</p>
          <button
            v-if="selectedTicket.attachment_url && isImageAttachment(selectedTicket)"
            type="button"
            class="chat-image-thumb"
            @click="lightboxImage = selectedTicket.attachment_url"
          >
            <img :src="selectedTicket.attachment_url" :alt="selectedTicket.attachment_name || 'ضمیمه سوال'">
          </button>
          <a v-else-if="selectedTicket.attachment_url" :href="selectedTicket.attachment_url" target="_blank" rel="noopener">
            {{ selectedTicket.attachment_name || 'دانلود ضمیمه' }}
          </a>
        </article>

        <article v-if="selectedTicket.answer" class="chat-message from-admin">
          <small>{{ selectedTicket.answered_by || 'مدیر کل سیستم' }} · {{ formatDate(selectedTicket.answered_at) }}</small>
          <p>{{ selectedTicket.answer }}</p>
          <button
            v-if="selectedTicket.answer_attachment_url && isAnswerImageAttachment(selectedTicket)"
            type="button"
            class="chat-image-thumb"
            @click="lightboxImage = selectedTicket.answer_attachment_url"
          >
            <img :src="selectedTicket.answer_attachment_url" :alt="selectedTicket.answer_attachment_name || 'ضمیمه پاسخ'">
          </button>
          <a v-else-if="selectedTicket.answer_attachment_url" :href="selectedTicket.answer_attachment_url" target="_blank" rel="noopener">
            {{ selectedTicket.answer_attachment_name || 'دانلود ضمیمه پاسخ' }}
          </a>
        </article>
        <div v-else class="chat-waiting">هنوز پاسخی از مدیر کل سیستم ثبت نشده است.</div>
      </div>
    </section>

    <button v-if="lightboxImage" type="button" class="image-lightbox" @click="lightboxImage = ''">
      <img :src="lightboxImage" alt="نمایش عکس ضمیمه">
    </button>
  </main>
</template>

<script>
import axios from "axios";

export default {
  name: "ServiceTickets",
  emits: ["back"],
  data() {
    return {
      tickets: [],
      statusFilter: "all",
      saving: false,
      message: "",
      error: "",
      file: null,
      fileName: "",
      selectedTicketId: null,
      lightboxImage: "",
      form: {
        subject: "",
        question: "",
      },
    };
  },
  computed: {
    filteredTickets() {
      if (this.statusFilter === "all") return this.tickets;
      return this.tickets.filter((ticket) => ticket.status === this.statusFilter);
    },
    selectedTicket() {
      return this.tickets.find((ticket) => ticket.id === this.selectedTicketId) || null;
    },
  },
  mounted() {
    this.loadTickets();
  },
  methods: {
    async loadTickets() {
      this.error = "";
      try {
        const { data } = await axios.get("/api/service-tickets");
        this.tickets = data.tickets || [];
      } catch (requestError) {
        this.error = requestError.response?.data?.message || "دریافت سوالات انجام نشد.";
      }
    },
    handleFile(event) {
      this.file = event.target.files?.[0] || null;
      this.fileName = this.file?.name || "";
    },
    async submitTicket() {
      this.saving = true;
      this.message = "";
      this.error = "";
      try {
        const payload = new FormData();
        payload.append("subject", this.form.subject);
        payload.append("question", this.form.question);
        if (this.file) payload.append("attachment", this.file);
        const { data } = await axios.post("/api/service-tickets", payload);
        this.tickets = [data.ticket, ...this.tickets];
        this.selectedTicketId = data.ticket?.id || null;
        this.form = { subject: "", question: "" };
        this.file = null;
        this.fileName = "";
        this.message = "سوال شما ثبت شد.";
      } catch (requestError) {
        this.error = requestError.response?.data?.message || "ثبت سوال انجام نشد.";
      } finally {
        this.saving = false;
      }
    },
    countByStatus(status) {
      return this.tickets.filter((ticket) => ticket.status === status).length;
    },
    statusLabel(status) {
      return { open: "باز", answered: "جواب داده شده", closed: "بسته" }[status] || status;
    },
    openTicket(ticket) {
      this.selectedTicketId = ticket.id;
      window.scrollTo({ top: 0, behavior: "smooth" });
    },
    isImageAttachment(ticket) {
      const name = String(ticket.attachment_name || ticket.attachment_url || "").toLowerCase();
      return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name);
    },
    isAnswerImageAttachment(ticket) {
      const name = String(ticket.answer_attachment_name || ticket.answer_attachment_url || "").toLowerCase();
      return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name);
    },
    formatDate(value) {
      if (!value) return "";
      return new Date(value).toLocaleDateString("fa-IR-u-ca-persian", { year: "numeric", month: "long", day: "numeric" });
    },
  },
};
</script>

<style scoped>
.service-ticket-page{display:grid;gap:18px;color:#0f172a}.service-ticket-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px;border:1px solid #dbeafe;border-radius:18px;background:#fff;box-shadow:0 14px 36px rgba(15,23,42,.07)}.service-ticket-head small{color:#2563eb;font-size:11px;font-weight:900}.service-ticket-head h1{margin:4px 0 0;font-size:24px}.service-ticket-head button,.service-ticket-form button{height:40px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900;cursor:pointer}.service-ticket-head button{padding:0 14px;background:#0f172a}.service-ticket-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.service-ticket-stats article{display:grid;gap:5px;padding:15px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.service-ticket-stats span{color:#64748b;font-size:11px;font-weight:900}.service-ticket-stats strong{font-size:22px}.service-ticket-layout{display:grid;grid-template-columns:360px minmax(0,1fr);gap:16px;align-items:start}.service-ticket-form,.service-ticket-list,.service-chat-page{display:grid;gap:12px;padding:16px;border:1px solid #e2e8f0;border-radius:18px;background:#fff}.service-ticket-form h2{margin:0;font-size:18px}.service-ticket-form input,.service-ticket-form textarea,.service-ticket-toolbar select{width:100%;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;font-family:inherit;font-weight:800}.service-ticket-form input,.service-ticket-toolbar select{height:42px;padding:0 12px}.service-ticket-form textarea{padding:12px;line-height:2;resize:vertical}.service-file-field{height:42px;display:grid;place-items:center;border:1px dashed #93c5fd;border-radius:12px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:900;cursor:pointer}.service-file-field input{display:none}.service-ticket-message{margin:0;color:#15803d;font-size:12px;font-weight:900}.service-ticket-error{margin:0;color:#dc2626;font-size:12px;font-weight:900}.service-ticket-form button:disabled{background:#cbd5e1;cursor:not-allowed}.service-ticket-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px}.service-ticket-toolbar strong{font-size:17px}.service-ticket-toolbar select{max-width:180px}.service-ticket-card{display:grid;gap:10px;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;cursor:pointer;transition:.16s}.service-ticket-card:hover{border-color:#93c5fd;background:#fff}.service-ticket-card header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.service-ticket-card header div{display:grid;gap:4px}.service-ticket-card small{color:#64748b;font-size:11px;font-weight:800}.service-ticket-card p{margin:0;color:#334155;font-size:13px;line-height:2}.service-open-question{width:max-content;height:34px;padding:0 12px;border:0;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:12px;font-weight:900;cursor:pointer}.status-pill{width:max-content;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:900}.status-open{background:#fff7ed;color:#c2410c}.status-answered{background:#dcfce7;color:#15803d}.status-closed{background:#e2e8f0;color:#475569}.service-ticket-empty{padding:26px;border:1px dashed #cbd5e1;border-radius:14px;color:#64748b;text-align:center;font-weight:900}.service-chat-page>header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;border-bottom:1px solid #e2e8f0;padding-bottom:12px}.service-chat-page h2{margin:4px 0 8px;font-size:20px}.service-chat-page small,.chat-message small{color:#64748b;font-size:11px;font-weight:900}.service-chat-page>header button{height:38px;padding:0 12px;border:0;border-radius:11px;background:#0f172a;color:#fff;font-family:inherit;font-weight:900;cursor:pointer}.service-chat-thread{display:grid;gap:12px;padding:12px;border-radius:16px;background:#f8fafc}.chat-message{max-width:min(680px,92%);display:grid;gap:8px;padding:13px;border:1px solid #e2e8f0;border-radius:16px;background:#fff}.chat-message p{margin:0;color:#1f2937;line-height:2}.from-user{justify-self:end;border-bottom-right-radius:5px}.from-admin{justify-self:start;border-color:#bbf7d0;background:#ecfdf5;border-bottom-left-radius:5px}.chat-image-thumb{width:150px;height:110px;padding:0;border:1px solid #dbeafe;border-radius:12px;overflow:hidden;background:#fff;cursor:pointer}.chat-image-thumb img{width:100%;height:100%;object-fit:cover;display:block}.chat-message a{width:max-content;color:#2563eb;font-size:12px;font-weight:900}.chat-waiting{padding:14px;border:1px dashed #cbd5e1;border-radius:14px;color:#64748b;text-align:center;font-weight:900}.image-lightbox{position:fixed;inset:0;z-index:1000003;display:grid;place-items:center;padding:22px;border:0;background:rgba(15,23,42,.78);cursor:zoom-out}.image-lightbox img{max-width:min(980px,96vw);max-height:90vh;border-radius:14px;background:#fff;box-shadow:0 26px 90px rgba(0,0,0,.35)}@media(max-width:900px){.service-ticket-layout,.service-ticket-stats{grid-template-columns:1fr}.service-ticket-head,.service-ticket-toolbar,.service-chat-page>header{align-items:stretch;flex-direction:column}.service-ticket-toolbar select{max-width:none}.chat-message{max-width:100%}}
</style>
