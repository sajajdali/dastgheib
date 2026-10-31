<template>
  <Teleport to="body">
    <div v-if="allowed" class="ai-preview" :class="{ 'ai-preview--dark': dark }" dir="rtl">
      <Transition name="ai-preview-fade">
        <button
          v-if="open"
          type="button"
          class="ai-preview-backdrop"
          aria-label="بستن دستیار هوش مصنوعی"
          @click="closePanel"
        ></button>
      </Transition>

      <Transition name="ai-preview-panel">
        <section
          v-if="open"
          id="ai-assistant-preview-panel"
          class="ai-preview-window"
          role="dialog"
          aria-modal="true"
          aria-labelledby="ai-assistant-preview-title"
        >
          <header class="ai-preview-header">
            <div class="ai-preview-avatar" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="m12 2 1.7 6.3L20 10l-6.3 1.7L12 18l-1.7-6.3L4 10l6.3-1.7L12 2Z"/><path d="m19 16 .7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7L19 16Z"/></svg>
            </div>
            <div class="ai-preview-heading">
              <h2 id="ai-assistant-preview-title">دستیار هوش مصنوعی</h2>
              <span><i aria-hidden="true"></i> {{ settings?.configured ? 'کلید OpenAI تنظیم شده' : 'نیازمند تنظیم کلید OpenAI' }}</span>
            </div>
            <button type="button" class="ai-preview-header-action" title="گفت‌وگوی جدید" @click="newConversation">＋</button>
            <button type="button" class="ai-preview-header-action" title="گفت‌وگوهای پیشین" @click="showHistory = !showHistory">☷</button>
            <button type="button" class="ai-preview-close" aria-label="بستن پنجره دستیار" @click="closePanel">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5 19 19M19 5 5 19"/></svg>
            </button>
          </header>

          <div v-if="showHistory" class="ai-preview-history">
            <button type="button" @click="newConversation">＋ گفت‌وگوی جدید</button>
            <div v-for="item in conversations" :key="item.id" class="ai-preview-history-row">
              <button type="button" @click="loadConversation(item.id)">{{ item.title || 'گفت‌وگو' }}</button>
              <button type="button" title="حذف گفت‌وگو" @click="deleteConversation(item.id)">×</button>
            </div>
          </div>

          <div class="ai-preview-model-row">
            <label for="ai-assistant-model">مدل</label>
            <select id="ai-assistant-model" v-model="selectedModel" :disabled="busy || !settings?.enabled">
              <option v-for="model in settings?.models || []" :key="model.id" :value="model.id">{{ model.label }}</option>
            </select>
            <button v-if="settings?.can_manage" type="button" @click="showSettings = !showSettings">تنظیمات</button>
          </div>
          <div v-if="showSettings && settings?.can_manage" class="ai-preview-settings">
            <label><input v-model="settingsDraft.enabled" type="checkbox"> فعال</label>
            <label>سقف پیام روزانه <input v-model.number="settingsDraft.daily_limit" type="number" min="1" max="500"></label>
            <label>مدل‌های مجاز</label>
            <label v-for="model in settings?.available_models || []" :key="model.id"><span>{{ model.label }}</span><input v-model="settingsDraft.allowed_models" type="checkbox" :value="model.id"></label>
            <label>مدل پیش‌فرض <select v-model="settingsDraft.default_model"><option v-for="model in settings?.available_models || []" :key="model.id" :value="model.id">{{ model.label }}</option></select></label>
            <button type="button" :disabled="busy" @click="saveSettings">ذخیره تنظیمات</button>
          </div>

          <div ref="thread" class="ai-preview-thread" aria-live="polite">
            <div class="ai-preview-day">گفت‌وگوی جدید</div>
            <div class="ai-preview-message ai-preview-message--assistant">
              <span class="ai-preview-message-icon" aria-hidden="true">✦</span>
              <div class="ai-preview-bubble">
                <strong>سلام! چه کمکی از دستم برمیاد؟</strong>
                <p>پرونده‌ها، نوبت‌ها، پیگیری‌ها و گزارش‌ها را جست‌وجو می‌کنم و نتیجه را در صفحهٔ مربوط باز می‌کنم.</p>
              </div>
            </div>

            <div v-if="messages.length === 0" class="ai-preview-suggestions">
              <span>برای شروع، یکی از این‌ها رو امتحان کن</span>
              <button v-for="suggestion in suggestions" :key="suggestion" type="button" @click="useSuggestion(suggestion)">
                {{ suggestion }}
                <span aria-hidden="true">↖</span>
              </button>
            </div>

            <div v-for="message in messages" :key="message.id" class="ai-preview-message" :class="`ai-preview-message--${message.role}`">
              <span v-if="message.role === 'assistant'" class="ai-preview-message-icon" aria-hidden="true">✦</span>
              <div class="ai-preview-bubble">
                <template v-if="message.role === 'assistant'">
                  <span class="ai-preview-message-role">پاسخ دستیار</span>
                  <div class="ai-preview-answer">
                    <template v-for="(block, blockIndex) in messageBlocks(message.text)" :key="blockIndex">
                      <h3 v-if="block.type === 'heading'">{{ block.text }}</h3>
                      <ul v-else-if="block.type === 'list'"><li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li></ul>
                      <p v-else>{{ block.text }}</p>
                    </template>
                  </div>
                </template>
                <p v-else>{{ message.text }}</p>
                <div v-for="(card, index) in message.presentation || []" :key="index" class="ai-preview-card" :class="`ai-preview-card--${card.type}`">
                  <template v-if="card.type === 'metric'"><strong>{{ card.title }}</strong><div class="ai-preview-metric-value"><b>{{ formatValue(card.value) }}</b><span v-if="card.unit">{{ card.unit }}</span></div><small>{{ card.subtitle }}</small></template>
                  <template v-else-if="card.type === 'table'"><div class="ai-preview-card-heading"><strong>{{ card.title }}</strong><span>{{ formatValue(card.count) }} مورد</span></div><div class="ai-preview-table-wrap"><table :class="{ 'has-row-actions': hasTableRowActions(card) }"><thead><tr><th v-for="(label, key) in card.columns" :key="key">{{ label }}</th><th v-if="hasTableRowActions(card)">مشاهده</th></tr></thead><tbody><tr v-for="(row, rowIndex) in card.rows" :key="rowIndex"><td v-for="(_, key) in card.columns" :key="key">{{ typeof row[key] === 'number' ? formatValue(row[key]) : row[key] }}</td><td v-if="hasTableRowActions(card)"><button v-if="tableRowAction(card, row, rowIndex)" type="button" @click="runAction(tableRowAction(card, row, rowIndex))">{{ tableRowAction(card, row, rowIndex).label }}</button></td></tr></tbody></table></div><small v-if="card.definition">{{ card.definition }}</small></template>
                  <template v-else-if="card.type === 'list'"><strong>{{ card.title }}</strong><div v-if="card.items?.length" class="ai-preview-list-items"><span v-for="(item, itemIndex) in card.items" :key="itemIndex">{{ item }}</span></div><small v-else>موردی پیدا نشد</small></template>
                  <template v-else-if="card.type === 'record'"><strong>{{ card.title }}</strong><div class="ai-preview-record-fields"><div v-for="(value, label) in card.fields" :key="label" class="ai-preview-record-field"><span>{{ label }}</span><b>{{ value ?? '—' }}</b></div></div><div class="ai-preview-card-actions"><button v-if="card.action" type="button" @click="runAction(card.action)">{{ card.action.label }} ↗</button><button v-for="(item, relatedIndex) in card.related_actions || []" :key="relatedIndex" type="button" @click="runAction(item)">{{ item.title }} · {{ formatValue(item.count) }} مورد</button></div></template>
                  <strong v-else-if="card.type === 'navigation'">{{ card.title }}</strong>
                  <button v-if="card.page" type="button" @click="navigate(card)">{{ card.label || 'نمایش در برنامه' }} ↗</button>
                </div>
              </div>
            </div>
            <div v-if="busy" class="ai-preview-working" role="status" :aria-label="transcribing ? 'در حال تبدیل صدا به متن' : 'در حال بررسی اطلاعات کلینیک'">
              <span class="ai-preview-working-mark" aria-hidden="true">✦</span>
              <span class="ai-preview-working-dots" aria-hidden="true"><i></i><i></i><i></i></span>
              <span class="ai-preview-visually-hidden">{{ transcribing ? 'در حال تبدیل صدا به متن' : 'در حال بررسی اطلاعات کلینیک' }}</span>
            </div>
          </div>

          <div class="ai-preview-compose-area">
            <p v-if="notice" class="ai-preview-notice" role="status">{{ notice }}</p>
            <form class="ai-preview-composer" @submit.prevent="sendMessage">
              <textarea
                ref="composer"
                v-model="draft"
                rows="1"
                maxlength="1000"
                placeholder="سؤالت رو اینجا بنویس..."
                aria-label="متن سؤال برای دستیار"
                :disabled="busy || !settings?.enabled || !settings?.configured"
                @keydown.enter.exact.prevent="sendMessage"
              ></textarea>
              <div class="ai-preview-compose-actions">
                <button type="button" class="ai-preview-mic" :class="{ 'ai-preview-mic--recording': recording }" :disabled="busy || stopping || !settings?.configured" :aria-label="recording ? 'پایان ضبط و ارسال ویس' : 'ضبط پیام صوتی'" :title="recording ? 'پایان ضبط و ارسال ویس' : 'ضبط پیام صوتی'" @click="toggleRecording">
                  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M6 11a6 6 0 0 0 12 0M12 17v4m-4 0h8"/></svg>
                </button>
                <span class="ai-preview-divider"></span>
                <button type="submit" class="ai-preview-send" :disabled="busy || !draft.trim() || !settings?.configured" aria-label="ارسال پیام" title="ارسال پیام">
                  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 7-7 7 7M12 5v14"/></svg>
                </button>
              </div>
            </form>
            <small>{{ recording ? `در حال ضبط · ${recordingSeconds} ثانیه · برای پایان و ارسال دوباره بزنید` : 'پس از پایان ضبط، ویس خودکار به دستیار ارسال می‌شود. پاسخ‌ها بر اساس دسترسی شما هستند.' }}</small>
          </div>
        </section>
      </Transition>

      <button
        ref="launcher"
        type="button"
        class="ai-preview-launcher"
        :class="{ 'ai-preview-launcher--open': open }"
        :aria-expanded="open"
        aria-controls="ai-assistant-preview-panel"
        :aria-label="open ? 'بستن دستیار هوش مصنوعی' : 'باز کردن دستیار هوش مصنوعی'"
        @click="togglePanel"
      >
        <span class="ai-preview-launcher-icon" aria-hidden="true">
          <svg v-if="!open" viewBox="0 0 24 24" fill="none"><path d="m12 2 1.7 6.3L20 10l-6.3 1.7L12 18l-1.7-6.3L4 10l6.3-1.7L12 2Z"/><path d="m19 16 .7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7L19 16Z"/></svg>
          <svg v-else viewBox="0 0 24 24" fill="none"><path d="M5 5 19 19M19 5 5 19"/></svg>
        </span>
        <span class="ai-preview-launcher-label">{{ open ? 'بستن دستیار' : 'دستیار هوش مصنوعی' }}</span>
      </button>
    </div>
  </Teleport>
</template>

<script setup>
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({ dark: { type: Boolean, default: false }, user: { type: Object, default: () => ({}) } });
const emit = defineEmits(['navigate']);
const allowed = computed(() => props.user?.tenant?.ai_assistant_enabled === true
  && (props.user?.permissions?.includes('assistant.use') || props.user?.roles?.some((r) => ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'].includes(typeof r === 'string' ? r : r.name))));
const suggestions = [
  'امروز چند نوبت فعال داریم؟',
  'درآمد کلینیک در این ماه چقدر بوده؟',
  'کدام پیگیری‌ها امروز سررسید دارند؟',
  'چند پروندهٔ فعال در کلینیک داریم؟',
  'درآمد هر پزشک در این ماه چقدر بوده؟',
];
const open = ref(false);
const draft = ref('');
const notice = ref('');
const messages = ref([]);
const conversations = ref([]);
const conversationId = ref(null);
const settings = ref(null);
const settingsDraft = ref({});
const selectedModel = ref('');
const busy = ref(false);
const transcribing = ref(false);
const showHistory = ref(false);
const showSettings = ref(false);
const recording = ref(false);
const stopping = ref(false);
const recordingSeconds = ref(0);
const composer = ref(null);
const launcher = ref(null);
const thread = ref(null);
let recorder;
let mediaStream;
let recorderTimer;
let nextMessageId = 1;
let discardRecording = false;

const errorText = (error) => error?.response?.data?.message || 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.';
const formatValue = (value) => Number(value || 0).toLocaleString('fa-IR');
function tableRowAction(card, row, index) {
  const explicit = card?.row_actions?.[index];
  if (explicit) return explicit;
  if (card?.page === 'patients' && row?.file_number) {
    return { page: 'patient_profile', file_number: String(row.file_number), label: 'مشاهده پرونده' };
  }
  return null;
}
function hasTableRowActions(card) {
  return (card?.rows || []).some((row, index) => tableRowAction(card, row, index));
}
function messageBlocks(value) {
  const blocks = [];
  let paragraph = [];
  let list = [];
  const clean = (line) => line.replace(/\*\*(.*?)\*\*/g, '$1').replace(/`([^`]+)`/g, '$1').trim();
  const flushParagraph = () => { if (paragraph.length) blocks.push({ type: 'paragraph', text: paragraph.join('\n') }); paragraph = []; };
  const flushList = () => { if (list.length) blocks.push({ type: 'list', items: list }); list = []; };
  for (const rawLine of String(value || '').split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line) { flushParagraph(); flushList(); continue; }
    const heading = line.match(/^#{1,3}\s+(.+)$/);
    if (heading) { flushParagraph(); flushList(); blocks.push({ type: 'heading', text: clean(heading[1]) }); continue; }
    const item = line.match(/^(?:[-*•]|\d+[.)]|[۰-۹]+[.)])\s+(.+)$/);
    if (item) { flushParagraph(); list.push(clean(item[1])); continue; }
    flushList();
    paragraph.push(clean(line));
  }
  flushParagraph(); flushList();
  return blocks;
}
async function scrollDown(instant = false) {
  await nextTick();
  if (!thread.value) return;
  if (instant) thread.value.scrollTop = thread.value.scrollHeight;
  else thread.value.scrollTo({ top: thread.value.scrollHeight, behavior: 'smooth' });
}

async function loadBase() {
  try {
    const [config, history] = await Promise.all([axios.get('/api/assistant/settings'), axios.get('/api/assistant/conversations')]);
    settings.value = config.data;
    settingsDraft.value = { enabled: config.data.enabled, default_model: config.data.default_model, allowed_models: [...config.data.allowed_models], daily_limit: config.data.daily_limit };
    selectedModel.value ||= config.data.default_model;
    conversations.value = history.data;
    if (!config.data.configured) notice.value = 'کلید OpenAI باید در backend/.env روی سرور تنظیم شود.';
  } catch (error) { notice.value = errorText(error); }
}

async function showPanel() {
  open.value = true;
  await loadBase();
  if (!open.value) return;
  await scrollDown(true);
  composer.value?.focus();
}
async function closePanel() { if (recording.value || stopping.value) { discardRecording = true; if (recorder?.state === 'recording') recorder.stop(); } open.value = false; await nextTick(); launcher.value?.focus(); }
function togglePanel() { open.value ? closePanel() : showPanel(); }
async function useSuggestion(value) { draft.value = value; notice.value = ''; await nextTick(); composer.value?.focus(); }

function newConversation() { conversationId.value = null; messages.value = []; draft.value = ''; showHistory.value = false; notice.value = ''; }
async function loadConversation(id) {
  try {
    const { data } = await axios.get(`/api/assistant/conversations/${id}`);
    conversationId.value = id;
    selectedModel.value = data.conversation.model;
    messages.value = data.messages.map((m) => ({ id: m.id, role: m.role, text: m.content, presentation: m.presentation || [] }));
    showHistory.value = false; notice.value = ''; await scrollDown(true);
  } catch (error) { notice.value = errorText(error); }
}
async function deleteConversation(id) {
  try {
    await axios.delete(`/api/assistant/conversations/${id}`);
    conversations.value = conversations.value.filter((item) => item.id !== id);
    if (conversationId.value === id) newConversation();
  } catch (error) { notice.value = errorText(error); }
}
async function saveSettings() {
  busy.value = true;
  try {
    const { data } = await axios.put('/api/assistant/settings', settingsDraft.value);
    settings.value = data; settingsDraft.value = { enabled: data.enabled, default_model: data.default_model, allowed_models: [...data.allowed_models], daily_limit: data.daily_limit };
    notice.value = 'تنظیمات ذخیره شد.'; showSettings.value = false;
  } catch (error) { notice.value = errorText(error); }
  finally { busy.value = false; }
}
async function sendMessage(allowDuringTranscription = false) {
  const text = draft.value.trim();
  if (!text || (busy.value && allowDuringTranscription !== true) || !settings.value?.configured) return;
  busy.value = true; notice.value = '';
  const localId = `local-${nextMessageId++}`;
  messages.value.push({ id: localId, role: 'user', text }); draft.value = ''; await scrollDown();
  try {
    const { data } = await axios.post('/api/assistant/messages', { message: text, model: selectedModel.value, conversation_id: conversationId.value });
    conversationId.value = data.conversation_id;
    messages.value.push({ id: `answer-${nextMessageId++}`, role: 'assistant', text: data.answer, presentation: data.presentation || [] });
    await loadBase(); await scrollDown();
  } catch (error) {
    messages.value = messages.value.filter((m) => m.id !== localId);
    draft.value = text;
    notice.value = errorText(error);
  } finally { busy.value = false; composer.value?.focus(); }
}

async function toggleRecording() {
  if (recording.value) { if (!stopping.value && recorder?.state === 'recording') { stopping.value = true; recorder.stop(); } return; }
  if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { notice.value = 'مرورگر شما ضبط صدا را پشتیبانی نمی‌کند.'; return; }
  try {
    discardRecording = false;
    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const mimeType = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus'].find((type) => MediaRecorder.isTypeSupported(type));
    recorder = new MediaRecorder(mediaStream, mimeType ? { mimeType } : undefined);
    const chunks = [];
    recorder.ondataavailable = (event) => { if (event.data.size) chunks.push(event.data); };
    recorder.onstop = async () => {
      recording.value = false; stopping.value = false; clearInterval(recorderTimer); mediaStream?.getTracks().forEach((track) => track.stop());
      if (discardRecording) { discardRecording = false; return; }
      if (!chunks.length) { notice.value = 'صدایی ضبط نشد. دوباره تلاش کنید.'; return; }
      const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
      const extension = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
      const form = new FormData(); form.append('audio', blob, `voice.${extension}`);
      busy.value = true; transcribing.value = true; notice.value = '';
      try {
        const { data } = await axios.post('/api/assistant/transcriptions', form, { headers: { 'Content-Type': 'multipart/form-data' } });
        const transcript = String(data.text || '').trim();
        if (!transcript) { notice.value = 'متنی از ویس تشخیص داده نشد. دوباره تلاش کنید.'; return; }
        draft.value = transcript;
        transcribing.value = false;
        await sendMessage(true);
      } catch (error) { notice.value = errorText(error); }
      finally { busy.value = false; transcribing.value = false; }
    };
    recordingSeconds.value = 0; recorder.start(); recording.value = true;
    recorderTimer = setInterval(() => { recordingSeconds.value += 1; if (recordingSeconds.value >= 90 && recorder?.state === 'recording') { stopping.value = true; recorder.stop(); } }, 1000);
  } catch { notice.value = 'دسترسی میکروفون داده نشد یا ضبط آغاز نشد.'; mediaStream?.getTracks().forEach((track) => track.stop()); }
}
async function runAction(action) {
  if (action?.prompt) { draft.value = action.prompt; await sendMessage(); return; }
  navigate(action);
}
function navigate(card) { emit('navigate', { page: card.page, date: card.date || null, file_number: card.file_number || null, media_id: card.media_id || null, report: card.report || null }); closePanel(); }
function onKeydown(event) { if (event.key === 'Escape' && open.value) closePanel(); }
window.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => { window.removeEventListener('keydown', onKeydown); discardRecording = true; if (recorder?.state === 'recording') recorder.stop(); clearInterval(recorderTimer); mediaStream?.getTracks().forEach((track) => track.stop()); });
</script>

<style scoped>
.ai-preview { --ai-ink: #16253c; --ai-muted: #73829a; --ai-line: #e6ebf3; position: relative; z-index: 2147483005; }
.ai-preview-backdrop { position: fixed; inset: 0; z-index: 2147483005; border: 0; background: rgba(14, 25, 45, .12); cursor: default; }
.ai-preview-launcher { position: fixed; bottom: max(22px, env(safe-area-inset-bottom)); left: max(22px, env(safe-area-inset-left)); z-index: 2147483007; min-height: 58px; display: inline-flex; align-items: center; gap: 10px; padding: 7px 18px 7px 8px; border: 1px solid rgba(255,255,255,.34); border-radius: 18px; background: linear-gradient(125deg, #253f82 0%, #3760b4 54%, #167d97 100%); color: #fff; box-shadow: 0 16px 38px rgba(22, 55, 117, .28), inset 0 1px rgba(255,255,255,.18); font-size: 13px; font-weight: 900; letter-spacing: -.15px; transition: transform .2s ease, box-shadow .2s ease; }
.ai-preview-launcher:hover { transform: translateY(-3px); box-shadow: 0 20px 42px rgba(22, 55, 117, .34), inset 0 1px rgba(255,255,255,.18); }
.ai-preview-launcher--open { min-height: 48px; bottom: max(25px, env(safe-area-inset-bottom)); padding: 5px 12px 5px 6px; border-radius: 14px; background: #243958; box-shadow: 0 9px 22px rgba(22, 35, 59, .22); font-size: 11px; }
.ai-preview-launcher-icon { width: 42px; height: 42px; flex: none; display: grid; place-items: center; border-radius: 13px; background: rgba(255,255,255,.17); }
.ai-preview-launcher--open .ai-preview-launcher-icon { width: 34px; height: 34px; border-radius: 10px; }
.ai-preview-launcher-icon svg { width: 23px; height: 23px; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ai-preview-launcher:not(.ai-preview-launcher--open) .ai-preview-launcher-icon svg path:first-child { fill: currentColor; stroke: none; }
.ai-preview-launcher--open .ai-preview-launcher-icon svg path { fill: none; stroke: currentColor; }
.ai-preview-launcher--open .ai-preview-launcher-icon svg { width: 19px; height: 19px; }
.ai-preview-window { position: fixed; bottom: max(92px, calc(env(safe-area-inset-bottom) + 72px)); left: max(22px, env(safe-area-inset-left)); z-index: 2147483006; width: min(510px, calc(100vw - 44px)); height: min(710px, calc(100dvh - 125px)); min-height: 340px; display: flex; flex-direction: column; overflow: hidden; border: 1px solid rgba(219, 227, 240, .95); border-radius: 24px; background: #fff; box-shadow: 0 28px 75px rgba(18, 38, 75, .23), 0 5px 18px rgba(18, 38, 75, .08); color: var(--ai-ink); }
.ai-preview-header { min-height: 82px; display: flex; align-items: center; gap: 11px; padding: 15px 18px; border-bottom: 1px solid #e7ecf5; background: linear-gradient(130deg, #f7faff 0%, #f2f7ff 60%, #edf9fb 100%); }
.ai-preview-header-action { width: 27px; height: 27px; flex: none; border: 1px solid #dce5f1; border-radius: 8px; background: #fff; color: #315d93; font-size: 17px; cursor: pointer; }
.ai-preview-model-row { display: flex; align-items: center; gap: 8px; padding: 7px 15px; border-bottom: 1px solid var(--ai-line); color: #60738e; font-size: 10px; }
.ai-preview-model-row select, .ai-preview-model-row button, .ai-preview-settings select, .ai-preview-settings input { min-width: 0; border: 1px solid #dce5f1; border-radius: 7px; background: #fff; color: #294665; padding: 4px 7px; font-size: 10px; }
.ai-preview-model-row button { margin-right: auto; cursor: pointer; }
.ai-preview-history { max-height: 180px; overflow: auto; padding: 8px 14px; border-bottom: 1px solid var(--ai-line); background: #f8faff; }
.ai-preview-history button { border: 0; background: transparent; color: #315d93; cursor: pointer; font-size: 11px; text-align: right; }
.ai-preview-history-row { display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px solid #e7edf6; }
.ai-preview-settings { display: grid; gap: 7px; padding: 10px 15px; border-bottom: 1px solid var(--ai-line); background: #f8faff; color: #40556f; font-size: 11px; }
.ai-preview-settings label { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ai-preview-settings input[type='number'] { width: 68px; }
.ai-preview-settings button { border: 0; border-radius: 8px; padding: 6px; background: #315fa9; color: white; cursor: pointer; }
.ai-preview-card { display: grid; gap: 11px; margin-top: 13px; padding: 15px; border: 1px solid #e1e8f2; border-radius: 15px; background: #fff; color: #334d70; box-shadow: 0 4px 15px rgba(25, 55, 95, .035); font-size: 12px; }
.ai-preview-card > strong, .ai-preview-card-heading strong { color: #243b5b; font-size: 13px; line-height: 1.6; }
.ai-preview-card small { color: #71839b; font-size: 10px; line-height: 1.7; }
.ai-preview-card--metric { gap: 5px; border-color: #d8e8fb; background: linear-gradient(135deg, #f1f7ff, #f8fcff); }
.ai-preview-metric-value { display: flex; align-items: baseline; gap: 7px; color: #1f5ca3; }
.ai-preview-metric-value b { font-size: 30px; line-height: 1.3; font-weight: 900; letter-spacing: -.5px; }
.ai-preview-metric-value span { font-size: 12px; font-weight: 800; }
.ai-preview-card-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ai-preview-card-heading span { flex: none; padding: 4px 9px; border-radius: 999px; background: #eaf3ff; color: #2f67a8; font-size: 10px; font-weight: 800; }
.ai-preview-card button { width: fit-content; border: 1px solid #c9ddf6; border-radius: 9px; padding: 7px 10px; background: #eef6ff; color: #245b9c; cursor: pointer; font-family: inherit; font-size: 10px; font-weight: 800; }
.ai-preview-card button:hover { border-color: #95bcea; background: #deedff; }
.ai-preview-table-wrap { max-width: 100%; overflow-x: auto; border: 1px solid #e4ebf4; border-radius: 11px; background: #fff; }
.ai-preview-card table { width: 100%; border-collapse: collapse; min-width: 320px; text-align: right; white-space: nowrap; }
.ai-preview-card th, .ai-preview-card td { border-bottom: 1px solid #edf1f6; padding: 10px 9px; font-size: 10px; }
.ai-preview-card th { background: #f3f7fc; color: #627895; font-weight: 800; }
.ai-preview-card tbody tr:last-child td { border-bottom: 0; }
.ai-preview-card tbody tr:nth-child(even) { background: #fafcff; }
.ai-preview-card table.has-row-actions th:last-child, .ai-preview-card table.has-row-actions td:last-child { position: sticky; left: 0; z-index: 1; border-right: 1px solid #e4ebf4; background: #fff; }
.ai-preview-card table.has-row-actions th:last-child { z-index: 2; background: #f3f7fc; }
.ai-preview-card table.has-row-actions tbody tr:nth-child(even) td:last-child { background: #fafcff; }
.ai-preview-list-items, .ai-preview-card-actions { display: flex; flex-wrap: wrap; gap: 7px; }
.ai-preview-list-items span { padding: 6px 9px; border: 1px solid #dce9f6; border-radius: 9px; background: #f4f8fd; color: #365777; font-size: 11px; }
.ai-preview-record-fields { display: grid; gap: 2px; border-top: 1px solid #edf1f6; }
.ai-preview-record-field { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #edf1f6; }
.ai-preview-record-field span { color: #71839b; }
.ai-preview-record-field b { color: #263d5c; font-size: 11px; font-weight: 800; text-align: left; overflow-wrap: anywhere; }
.ai-preview-working { width: fit-content; min-width: 74px; min-height: 34px; display: inline-flex; align-items: center; gap: 8px; margin: 7px 35px; padding: 6px 11px; border: 1px solid #dce8f7; border-radius: 13px; background: #fff; color: #4772a5; box-shadow: 0 5px 16px rgba(35, 76, 130, .06); }
.ai-preview-working-mark { font-size: 15px; line-height: 1; animation: ai-working-glow 1.35s ease-in-out infinite; }
.ai-preview-working-dots { display: inline-flex; align-items: center; gap: 4px; }
.ai-preview-working-dots i { width: 5px; height: 5px; border-radius: 50%; background: currentColor; animation: ai-working-bounce 1.2s ease-in-out infinite; }
.ai-preview-working-dots i:nth-child(2) { animation-delay: .16s; }
.ai-preview-working-dots i:nth-child(3) { animation-delay: .32s; }
.ai-preview-visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@keyframes ai-working-bounce { 0%, 60%, 100% { transform: translateY(0); opacity: .45; } 30% { transform: translateY(-4px); opacity: 1; } }
@keyframes ai-working-glow { 0%, 100% { opacity: .5; transform: scale(.85); } 50% { opacity: 1; transform: scale(1.1); } }
@media (prefers-reduced-motion: reduce) { .ai-preview-working-mark, .ai-preview-working-dots i { animation: none; opacity: 1; } }
.ai-preview-mic--recording { background: #fee2e2 !important; color: #dc2626 !important; animation: ai-pulse 1s ease-in-out infinite; }
@keyframes ai-pulse { 50% { opacity: .55; } }
.ai-preview-avatar { width: 45px; height: 45px; flex: none; display: grid; place-items: center; border-radius: 15px; background: linear-gradient(140deg, #2f59a7, #148ca1); color: #fff; box-shadow: 0 7px 16px rgba(36, 102, 160, .22); }
.ai-preview-avatar svg { width: 26px; height: 26px; stroke: currentColor; stroke-width: 1.4; stroke-linejoin: round; }
.ai-preview-avatar svg path:first-child { fill: currentColor; stroke: none; }
.ai-preview-heading { min-width: 0; flex: 1; }
.ai-preview-heading h2 { margin: 0 0 3px; color: #20345d; font-size: 15px; line-height: 1.5; font-weight: 900; }
.ai-preview-heading span { display: flex; align-items: center; gap: 5px; color: #718198; font-size: 10px; font-weight: 700; }
.ai-preview-heading i { width: 6px; height: 6px; border-radius: 50%; background: #6c94ca; box-shadow: 0 0 0 3px rgba(108,148,202,.14); }
.ai-preview-close { width: 34px; height: 34px; flex: none; display: grid; place-items: center; border: 1px solid #dce5f1; border-radius: 10px; background: rgba(255,255,255,.8); color: #61738f; }
.ai-preview-close:hover { background: #fff; color: #263e6e; }
.ai-preview-close svg { width: 17px; height: 17px; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; }
.ai-preview-thread { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 20px 18px 25px; background: radial-gradient(circle at 8% 5%, rgba(222,236,255,.48), transparent 35%), #f8faff; scrollbar-color: #cbd7e8 transparent; }
.ai-preview-day { width: fit-content; margin: 0 auto 22px; padding: 5px 12px; border: 1px solid #e9eef5; border-radius: 999px; background: #fff; color: #8390a3; font-size: 10px; font-weight: 700; }
.ai-preview-message { display: flex; align-items: flex-start; gap: 9px; margin: 0 0 17px; }
.ai-preview-message--user { justify-content: flex-start; padding-right: 42px; }
.ai-preview-message-icon { width: 30px; height: 30px; flex: none; display: grid; place-items: center; border: 1px solid #d1e5f8; border-radius: 11px; background: #e8f3ff; color: #2b69a8; font-size: 17px; font-weight: 900; }
.ai-preview-bubble { width: calc(100% - 39px); max-width: 100%; padding: 15px 16px; border: 1px solid #e0e8f3; border-radius: 17px 17px 6px 17px; background: #fff; box-shadow: 0 6px 20px rgba(31, 61, 103, .055); }
.ai-preview-bubble strong { color: #263d64; font-size: 12px; line-height: 1.7; }
.ai-preview-bubble p { margin: 4px 0 0; color: #42556f; font-size: 12px; line-height: 1.95; white-space: pre-wrap; overflow-wrap: anywhere; }
.ai-preview-message-role { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #2e68a8; font-size: 10px; font-weight: 900; }
.ai-preview-message-role::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: #3b82f6; }
.ai-preview-answer { display: grid; gap: 10px; }
.ai-preview-answer h3 { margin: 5px 0 0; color: #203a60; font-size: 14px; line-height: 1.65; }
.ai-preview-answer p { margin: 0; }
.ai-preview-answer ul { display: grid; gap: 7px; margin: 0; padding: 0 19px 0 0; color: #42556f; font-size: 12px; line-height: 1.9; }
.ai-preview-answer li { padding-right: 2px; }
.ai-preview-answer li::marker { color: #4d8dcc; }
.ai-preview-message--user .ai-preview-bubble { width: auto; max-width: 82%; border-color: #3462a8; border-radius: 17px 17px 17px 6px; background: linear-gradient(135deg, #315fa9, #276ca0); box-shadow: 0 7px 17px rgba(40, 93, 160, .15); }
.ai-preview-message--user .ai-preview-bubble p { margin: 0; color: #fff; font-size: 12px; }
.ai-preview-suggestions { display: grid; gap: 8px; margin: 26px 34px 0 0; }
.ai-preview-suggestions > span { margin-bottom: 2px; color: #8794a5; font-size: 10px; font-weight: 800; }
.ai-preview-suggestions button { width: fit-content; max-width: 100%; display: inline-flex; align-items: center; justify-content: space-between; gap: 15px; padding: 9px 11px; border: 1px solid #dce8f7; border-radius: 11px; background: #fff; color: #386197; box-shadow: 0 3px 8px rgba(27, 69, 121, .04); font-size: 11px; font-weight: 800; text-align: right; }
.ai-preview-suggestions button:hover { border-color: #a8c5ed; background: #f4f8fe; }
.ai-preview-suggestions button span { color: #91a9c8; font-size: 15px; }
.ai-preview-compose-area { padding: 13px 15px 14px; border-top: 1px solid var(--ai-line); background: #fff; }
.ai-preview-notice { margin: 0 0 8px; padding: 8px 10px; border-radius: 9px; background: #eef5fc; color: #315b8b; font-size: 10px; line-height: 1.7; }
.ai-preview-composer { display: flex; align-items: flex-end; gap: 8px; min-height: 54px; padding: 6px 8px 6px 6px; border: 1px solid #d8e2ef; border-radius: 15px; background: #fbfcff; transition: border-color .2s, box-shadow .2s; }
.ai-preview-composer:focus-within { border-color: #91b3e5; box-shadow: 0 0 0 3px rgba(66,119,194,.09); }
.ai-preview-composer textarea { flex: 1; min-width: 0; min-height: 38px; max-height: 105px; resize: none; padding: 9px 5px 3px; border: 0; outline: 0; background: transparent; color: #263b5e; font-size: 12px; line-height: 1.8; box-shadow: none; }
.ai-preview-composer textarea::placeholder { color: #9baabd; }
.ai-preview-compose-actions { flex: none; display: flex; align-items: center; gap: 5px; padding-bottom: 2px; }
.ai-preview-mic, .ai-preview-send { width: 35px; height: 35px; display: grid; place-items: center; border: 0; border-radius: 10px; }
.ai-preview-mic { background: transparent; color: #6d83a1; }
.ai-preview-mic:hover { background: #edf3fa; color: #3769ad; }
.ai-preview-mic svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ai-preview-divider { width: 1px; height: 19px; background: #e0e7f0; }
.ai-preview-send { background: linear-gradient(135deg, #2e5ead, #19849f); color: #fff; box-shadow: 0 4px 10px rgba(37, 96, 162, .18); }
.ai-preview-send:not(:disabled):hover { filter: brightness(1.08); }
.ai-preview-send:disabled { opacity: .45; }
.ai-preview-send svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round; }
.ai-preview-compose-area > small { display: block; margin-top: 8px; color: #9caabd; font-size: 9px; line-height: 1.6; text-align: center; }
.ai-preview-fade-enter-active, .ai-preview-fade-leave-active { transition: opacity .2s ease; }
.ai-preview-fade-enter-from, .ai-preview-fade-leave-to { opacity: 0; }
.ai-preview-panel-enter-active, .ai-preview-panel-leave-active { transition: opacity .22s ease, transform .22s ease; }
.ai-preview-panel-enter-from, .ai-preview-panel-leave-to { opacity: 0; transform: translateY(12px) scale(.98); }
.ai-preview--dark .ai-preview-window { --ai-ink: #e7edf7; --ai-muted: #9faec5; --ai-line: #33445e; border-color: #33445e; background: #17243a; color: #e7edf7; }
.ai-preview--dark .ai-preview-header { border-color: #33445e; background: linear-gradient(130deg, #233757, #1b3146); }
.ai-preview--dark .ai-preview-heading h2, .ai-preview--dark .ai-preview-bubble strong { color: #e8f0fc; }
.ai-preview--dark .ai-preview-heading span { color: #a4b1c4; }
.ai-preview--dark .ai-preview-close, .ai-preview--dark .ai-preview-day, .ai-preview--dark .ai-preview-suggestions button { border-color: #3a4b66; background: #24344d; color: #d4e3f7; }
.ai-preview--dark .ai-preview-thread { background: #17243a; }
.ai-preview--dark .ai-preview-message-icon { background: #254363; color: #b8dcff; }
.ai-preview--dark .ai-preview-bubble { border-color: #33445e; background: #23344e; }
.ai-preview--dark .ai-preview-bubble p { color: #c4d2e5; }
.ai-preview--dark .ai-preview-message--user .ai-preview-bubble { border-color: #3f73bb; background: #315fa9; }
.ai-preview--dark .ai-preview-message--user .ai-preview-bubble p { color: #fff; }
.ai-preview--dark .ai-preview-compose-area { border-color: #33445e; background: #1b2a40; }
.ai-preview--dark .ai-preview-composer { border-color: #3a4d68; background: #203149; }
.ai-preview--dark .ai-preview-composer textarea { color: #f1f5fb; background: transparent; }
.ai-preview--dark .ai-preview-mic { color: #adbfda; }
.ai-preview--dark .ai-preview-notice { background: #243d5d; color: #cce3ff; }
.ai-preview--dark .ai-preview-model-row, .ai-preview--dark .ai-preview-settings, .ai-preview--dark .ai-preview-history { border-color: #33445e; background: #1b2a40; color: #c4d2e5; }
.ai-preview--dark .ai-preview-card { border-color: #3a4b66; background: #1b2a40; color: #d8e7fa; }
.ai-preview--dark .ai-preview-card > strong, .ai-preview--dark .ai-preview-card-heading strong, .ai-preview--dark .ai-preview-record-field b, .ai-preview--dark .ai-preview-answer h3 { color: #e4efff; }
.ai-preview--dark .ai-preview-card--metric { background: linear-gradient(135deg, #203856, #1b2a40); }
.ai-preview--dark .ai-preview-metric-value, .ai-preview--dark .ai-preview-card b { color: #a9d5ff; }
.ai-preview--dark .ai-preview-message-role { color: #a9d5ff; }
.ai-preview--dark .ai-preview-answer ul { color: #c4d2e5; }
.ai-preview--dark .ai-preview-table-wrap { border-color: #3a4b66; background: #203149; }
.ai-preview--dark .ai-preview-card th { background: #2a3d58; color: #b4c7df; }
.ai-preview--dark .ai-preview-card td, .ai-preview--dark .ai-preview-record-field, .ai-preview--dark .ai-preview-record-fields { border-color: #3a4b66; }
.ai-preview--dark .ai-preview-card tbody tr:nth-child(even) { background: #253750; }
.ai-preview--dark .ai-preview-card table.has-row-actions th:last-child { background: #2a3d58; }
.ai-preview--dark .ai-preview-card table.has-row-actions td:last-child { border-color: #3a4b66; background: #203149; }
.ai-preview--dark .ai-preview-card table.has-row-actions tbody tr:nth-child(even) td:last-child { background: #253750; }
.ai-preview--dark .ai-preview-card-heading span, .ai-preview--dark .ai-preview-list-items span { border-color: #3a4b66; background: #273c58; color: #b8d9ff; }
.ai-preview--dark .ai-preview-card button { border-color: #456488; background: #264462; color: #d7eaff; }
.ai-preview--dark .ai-preview-card small, .ai-preview--dark .ai-preview-record-field span { color: #a8bad1; }
.ai-preview--dark .ai-preview-working { border-color: #3a4b66; background: #23344e; color: #a9d5ff; }
@media (max-width: 600px) {
  .ai-preview-backdrop { background: rgba(14,25,45,.36); }
  .ai-preview-launcher { bottom: max(16px, env(safe-area-inset-bottom)); left: max(14px, env(safe-area-inset-left)); min-height: 53px; padding: 5px 12px 5px 6px; font-size: 11px; border-radius: 16px; }
  .ai-preview-launcher-icon { width: 40px; height: 40px; }
  .ai-preview-launcher--open { display: none; }
  .ai-preview-window { left: 0; right: 0; bottom: 0; width: 100%; height: min(720px, 92dvh); max-height: 100dvh; min-height: 0; border-radius: 22px 22px 0 0; border-bottom: 0; }
  .ai-preview-header { min-height: 77px; padding-top: 18px; }
  .ai-preview-thread { padding-bottom: 16px; }
  .ai-preview-compose-area { padding-bottom: max(14px, env(safe-area-inset-bottom)); }
}
@media (prefers-reduced-motion: reduce) {
  .ai-preview-launcher, .ai-preview-fade-enter-active, .ai-preview-fade-leave-active, .ai-preview-panel-enter-active, .ai-preview-panel-leave-active { transition: none; }
}
</style>
