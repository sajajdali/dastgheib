<template>
  <div class="central-view service-admin-view">
    <section v-if="!selectedTicket" class="central-stats-grid">
      <article class="central-stat-card">
        <div class="central-stat-head"><span>کل سوالات</span></div>
        <div class="central-stat-value">{{ tickets.length.toLocaleString('fa-IR') }}</div>
      </article>
      <article class="central-stat-card">
        <div class="central-stat-head"><span>باز</span></div>
        <div class="central-stat-value">{{ countByStatus('open').toLocaleString('fa-IR') }}</div>
      </article>
      <article class="central-stat-card">
        <div class="central-stat-head"><span>جواب داده شده</span></div>
        <div class="central-stat-value">{{ countByStatus('answered').toLocaleString('fa-IR') }}</div>
      </article>
      <article class="central-stat-card">
        <div class="central-stat-head"><span>بسته</span></div>
        <div class="central-stat-value">{{ countByStatus('closed').toLocaleString('fa-IR') }}</div>
      </article>
    </section>

    <section v-if="!selectedTicket" class="central-panel central-stack">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">سوالات و پشتیبانی‌های سرویس کلینیک‌ها</div>
          <div class="central-section-subtitle">پاسخ‌دهی و مدیریت وضعیت سوالات مدیران کلینیک</div>
        </div>
        <select v-model="statusFilter" class="central-select" style="max-width:190px">
          <option value="all">همه وضعیت‌ها</option>
          <option value="open">باز</option>
          <option value="answered">جواب داده شده</option>
          <option value="closed">بسته</option>
        </select>
      </div>

      <article v-for="ticket in filteredTickets" :key="ticket.id" class="service-admin-ticket" @click="openTicket(ticket)">
        <header>
          <div>
            <strong>{{ ticket.subject }}</strong>
            <small>{{ ticket.tenant_name || ticket.tenant_id }} · {{ ticket.user_name || 'مدیر کلینیک' }} · {{ formatDate(ticket.created_at) }}</small>
          </div>
          <span :class="['central-badge', `service-admin-${ticket.status}`]">{{ statusLabel(ticket.status) }}</span>
        </header>
        <p>{{ ticket.question }}</p>
        <button class="central-button secondary compact service-admin-open-chat" type="button">مشاهده گفت‌وگو</button>
      </article>

      <div v-if="!filteredTickets.length" class="central-empty">سوالی برای نمایش وجود ندارد.</div>
    </section>

    <section v-else class="central-panel service-admin-chat">
      <header>
        <div>
          <div class="central-section-subtitle">گفت‌وگوی پشتیبانی</div>
          <div class="central-section-title">{{ selectedTicket.subject }}</div>
          <div class="central-small">{{ selectedTicket.tenant_name || selectedTicket.tenant_id }} · {{ selectedTicket.user_name || 'مدیر کلینیک' }}</div>
        </div>
        <div class="central-actions">
          <span :class="['central-badge', `service-admin-${selectedTicket.status}`]">{{ statusLabel(selectedTicket.status) }}</span>
          <button class="central-button secondary compact" type="button" @click="selectedTicketId = null">بازگشت به سوالات</button>
        </div>
      </header>

      <div class="service-admin-thread">
        <article class="service-admin-message from-clinic">
          <small>{{ selectedTicket.user_name || 'مدیر کلینیک' }} · {{ formatDate(selectedTicket.created_at) }}</small>
          <p>{{ selectedTicket.question }}</p>
          <button
            v-if="selectedTicket.attachment_url && isImageAttachment(selectedTicket)"
            type="button"
            class="service-admin-image"
            @click="lightboxImage = selectedTicket.attachment_url"
          >
            <img :src="selectedTicket.attachment_url" :alt="selectedTicket.attachment_name || 'ضمیمه سوال'">
          </button>
          <a v-else-if="selectedTicket.attachment_url" :href="selectedTicket.attachment_url" target="_blank" rel="noopener">{{ selectedTicket.attachment_name || 'دانلود ضمیمه' }}</a>
        </article>

        <article v-if="selectedTicket.answer" class="service-admin-message from-central">
          <small>{{ selectedTicket.answered_by || 'مدیر کل سیستم' }} · {{ formatDate(selectedTicket.answered_at) }}</small>
          <p>{{ selectedTicket.answer }}</p>
          <button
            v-if="selectedTicket.answer_attachment_url && isAnswerImageAttachment(selectedTicket)"
            type="button"
            class="service-admin-image"
            @click="lightboxImage = selectedTicket.answer_attachment_url"
          >
            <img :src="selectedTicket.answer_attachment_url" :alt="selectedTicket.answer_attachment_name || 'ضمیمه پاسخ'">
          </button>
          <a v-else-if="selectedTicket.answer_attachment_url" :href="selectedTicket.answer_attachment_url" target="_blank" rel="noopener">{{ selectedTicket.answer_attachment_name || 'دانلود ضمیمه پاسخ' }}</a>
        </article>
        <div v-else class="central-empty service-admin-waiting">هنوز پاسخی ثبت نشده است.</div>
      </div>

      <form class="service-admin-reply" @submit.prevent="submitAnswer">
        <textarea v-model="drafts[selectedTicket.id]" class="central-input service-answer-input" rows="4" placeholder="پاسخ مدیر کل سیستم..."></textarea>
        <label class="service-admin-file">
          <span>{{ answerFileNames[selectedTicket.id] || 'ضمیمه عکس یا فایل پاسخ' }}</span>
          <input type="file" @change="handleAnswerFile">
        </label>
        <footer>
          <button class="central-button compact" type="submit">ثبت پاسخ</button>
          <button class="central-button secondary compact" type="button" @click="$emit('update-ticket-status', { ticket: selectedTicket, status: 'open' })">باز</button>
          <button class="central-button danger compact" type="button" @click="$emit('update-ticket-status', { ticket: selectedTicket, status: 'closed' })">بستن</button>
        </footer>
      </form>
    </section>

    <button v-if="lightboxImage" type="button" class="service-admin-lightbox" @click="lightboxImage = ''">
      <img :src="lightboxImage" alt="نمایش عکس ضمیمه">
    </button>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";

const props = defineProps({
  tickets: { type: Array, default: () => [] },
  resetKey: { type: Number, default: 0 },
});

const emits = defineEmits(["answer-ticket", "update-ticket-status"]);

const statusFilter = ref("all");
const lightboxImage = ref("");
const selectedTicketId = ref(null);
const drafts = reactive({});
const answerFiles = reactive({});
const answerFileNames = reactive({});

const filteredTickets = computed(() => {
  if (statusFilter.value === "all") return props.tickets;
  return props.tickets.filter((ticket) => ticket.status === statusFilter.value);
});

const selectedTicket = computed(() => props.tickets.find((ticket) => ticket.id === selectedTicketId.value) || null);

watch(
  () => props.tickets,
  (tickets) => {
    tickets.forEach((ticket) => {
      if (drafts[ticket.id] === undefined) drafts[ticket.id] = ticket.answer || "";
    });
  },
  { immediate: true, deep: true },
);

watch(
  () => props.resetKey,
  () => {
    selectedTicketId.value = null;
    lightboxImage.value = "";
  },
);

function countByStatus(status) {
  return props.tickets.filter((ticket) => ticket.status === status).length;
}

function statusLabel(status) {
  return { open: "باز", answered: "جواب داده شده", closed: "بسته" }[status] || status;
}

function openTicket(ticket) {
  selectedTicketId.value = ticket.id;
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function handleAnswerFile(event) {
  const file = event.target.files?.[0] || null;
  if (!selectedTicket.value) return;
  answerFiles[selectedTicket.value.id] = file;
  answerFileNames[selectedTicket.value.id] = file?.name || "";
}

function submitAnswer() {
  if (!selectedTicket.value) return;
  const ticket = selectedTicket.value;
  emits("answer-ticket", {
    ticket,
    answer: drafts[ticket.id] || "",
    attachment: answerFiles[ticket.id] || null,
  });
  answerFiles[ticket.id] = null;
  answerFileNames[ticket.id] = "";
}

function formatDate(value) {
  if (!value) return "";
  return new Date(value).toLocaleDateString("fa-IR-u-ca-persian", { year: "numeric", month: "long", day: "numeric" });
}

function isImageAttachment(ticket) {
  const name = String(ticket.attachment_name || ticket.attachment_url || "").toLowerCase();
  return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name);
}

function isAnswerImageAttachment(ticket) {
  const name = String(ticket.answer_attachment_name || ticket.answer_attachment_url || "").toLowerCase();
  return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(name);
}
</script>

<style scoped>
.service-admin-view{gap:18px}.service-admin-ticket{display:grid;gap:12px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;transition:.16s}.service-admin-ticket:hover{border-color:#93c5fd;background:#f8fbff}.service-admin-ticket header,.service-admin-chat>header,.service-admin-reply footer{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.service-admin-ticket header div{display:grid;gap:5px}.service-admin-ticket small,.service-admin-message small{color:#64748b;font-size:12px;font-weight:800}.service-admin-ticket p,.service-admin-message p{margin:0;color:#334155;line-height:2}.service-admin-ticket a,.service-admin-message a{width:max-content;color:#2563eb;font-weight:900}.service-admin-open-chat{width:max-content}.service-admin-chat{display:grid;gap:16px}.service-admin-chat>header{padding-bottom:14px;border-bottom:1px solid #e2e8f0}.service-admin-thread{display:grid;gap:12px;padding:14px;border-radius:8px;background:#f8fafc}.service-admin-message{max-width:min(760px,92%);display:grid;gap:9px;padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#fff}.from-clinic{justify-self:end;border-bottom-right-radius:5px}.from-central{justify-self:start;border-color:#bbf7d0;background:#ecfdf5;border-bottom-left-radius:5px}.service-admin-reply{display:grid;gap:10px}.service-answer-input{height:auto;min-height:96px;padding:12px;line-height:2}.service-admin-file{height:40px;display:grid;place-items:center;border:1px dashed #93c5fd;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:900;cursor:pointer}.service-admin-file input{display:none}.service-admin-image{width:150px;height:110px;padding:0;border:1px solid #dbeafe;border-radius:8px;overflow:hidden;background:#fff;cursor:pointer}.service-admin-image img{width:100%;height:100%;display:block;object-fit:cover}.service-admin-lightbox{position:fixed;inset:0;z-index:1000003;display:grid;place-items:center;padding:22px;border:0;background:rgba(15,23,42,.78);cursor:zoom-out}.service-admin-lightbox img{max-width:min(980px,96vw);max-height:90vh;border-radius:14px;background:#fff;box-shadow:0 26px 90px rgba(0,0,0,.35)}.service-admin-waiting{margin:0}.service-admin-open{--badge-color:#c2410c;--badge-bg:#fff7ed}.service-admin-answered{--badge-color:#15803d;--badge-bg:#dcfce7}.service-admin-closed{--badge-color:#475569;--badge-bg:#e2e8f0}@media(max-width:760px){.service-admin-ticket header,.service-admin-chat>header,.service-admin-reply footer{align-items:stretch;flex-direction:column}.service-admin-message{max-width:100%}}
</style>
