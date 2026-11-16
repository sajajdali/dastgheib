<template>
  <Teleport to="body">
    <div v-if="allowed" class="gc" :class="{ 'gc--dark': dark }" dir="rtl">
      <Transition name="gc-panel">
        <section
          v-if="open"
          id="group-chat-panel"
          class="gc-window"
          role="dialog"
          aria-labelledby="group-chat-title"
          @dragover.prevent
          @drop.prevent="onDrop"
        >
          <header class="gc-header">
            <div class="gc-header-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><path d="M7 18.5 3.5 21V6.5A2.5 2.5 0 0 1 6 4h12a2.5 2.5 0 0 1 2.5 2.5v9A2.5 2.5 0 0 1 18 18H7.5"/><path d="M8 9.5h8M8 13h5"/></svg>
            </div>
            <div class="gc-heading">
              <h2 id="group-chat-title">چت گروهی کلینیک</h2>
              <p>
                <span class="gc-dot" :class="{ 'gc-dot--on': presence.connected }"></span>
                <template v-if="presence.connected">{{ toFa(onlineCount) }} نفر آنلاین</template>
                <template v-else-if="presence.connecting">در حال اتصال…</template>
                <template v-else>اتصال زنده برقرار نیست</template>
              </p>
            </div>
            <button
              type="button"
              class="gc-close"
              :aria-label="soundOn ? 'بی‌صدا کردن اعلان پیام' : 'فعال کردن صدای اعلان پیام'"
              :title="soundOn ? 'صدای اعلان روشن است' : 'صدای اعلان خاموش است'"
              :aria-pressed="soundOn"
              @click="toggleSound"
            >
              <svg v-if="soundOn" viewBox="0 0 24 24" fill="none"><path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 1.5h-15L6 16.5Z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/></svg>
              <svg v-else viewBox="0 0 24 24" fill="none"><path d="M6 16.5V11a6 6 0 0 1 9.5-4.9M18 11v5.5l1.5 1.5H8"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0M4 4l16 16"/></svg>
            </button>
            <button type="button" class="gc-close" aria-label="بستن چت گروهی" @click="closePanel">
              <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
          </header>

          <div ref="thread" class="gc-thread" aria-live="polite" @scroll.passive="onThreadScroll">
            <div v-if="loadingOlder" class="gc-loader">در حال بارگذاری پیام‌های قبلی…</div>
            <button v-else-if="hasMore && messages.length" type="button" class="gc-older" @click="loadOlder">نمایش پیام‌های قبلی</button>

            <div v-if="loading && !messages.length" class="gc-empty">در حال بارگذاری…</div>
            <div v-else-if="loadError && !messages.length" class="gc-empty gc-empty--error">
              {{ loadError }}
              <button type="button" class="gc-link" @click="loadLatest">تلاش دوباره</button>
            </div>
            <div v-else-if="!messages.length" class="gc-empty">
              <strong>هنوز پیامی ارسال نشده است</strong>
              <span>اولین پیام را برای همکارانتان بفرستید.</span>
            </div>

            <template v-for="item in threadItems" :key="item.key">
              <div v-if="item.kind === 'day'" class="gc-day"><span>{{ item.label }}</span></div>
              <div v-else-if="item.kind === 'unread'" class="gc-unread-divider" data-unread-divider><span>{{ item.label }}</span></div>
              <div
                v-else
                class="gc-row"
                :class="{ 'gc-row--mine': isMine(item.message), 'gc-row--grouped': item.grouped }"
              >
                <div class="gc-avatar" :title="item.message.sender.name">
                  <template v-if="!item.grouped">
                    <img v-if="item.message.sender.avatar_url" :src="item.message.sender.avatar_url" :alt="item.message.sender.name" loading="lazy" />
                    <span v-else :style="{ background: avatarColor(item.message.sender) }">{{ initial(item.message.sender) }}</span>
                  </template>
                </div>
                <div class="gc-bubble" :class="{ 'gc-bubble--deleted': item.message.is_deleted, 'gc-bubble--image': item.message.type === 'image' && !item.message.is_deleted }">
                  <div v-if="!item.grouped" class="gc-sender">{{ isMine(item.message) ? 'شما' : item.message.sender.name }}</div>

                  <p v-if="item.message.is_deleted" class="gc-deleted">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/></svg>
                    {{ item.message.deleted_text || 'این پیام حذف شده است' }}
                  </p>
                  <template v-else>
                    <button
                      v-if="item.message.type === 'image' && item.message.attachment_url"
                      type="button"
                      class="gc-image"
                      @click="openLightbox(item.message.attachment_url)"
                    >
                      <img :src="item.message.attachment_url" alt="تصویر ارسالی" loading="lazy" @load="onMediaLoad" />
                    </button>
                    <div v-if="item.message.type === 'voice' && item.message.attachment_url" class="gc-voice">
                      <audio :src="item.message.attachment_url" controls preload="metadata"></audio>
                      <span v-if="item.message.voice_duration" class="gc-voice-len">{{ formatDuration(item.message.voice_duration) }}</span>
                    </div>
                    <p v-if="item.message.body" class="gc-text">{{ item.message.body }}</p>
                  </template>

                  <div class="gc-meta" :title="fullDateTime(item.message.created_at)">
                    <span v-if="item.message.is_edited" class="gc-edited">ویرایش‌شده</span>
                    <time :datetime="item.message.created_at">{{ formatTime(item.message.created_at) }}</time>
                    <span v-if="item.message.pending" class="gc-pending" aria-label="در حال ارسال">◷</span>
                  </div>

                  <div v-if="!item.message.is_deleted && !item.message.pending && (canEdit(item.message) || canDelete(item.message))" class="gc-actions">
                    <button
                      type="button"
                      class="gc-actions-toggle"
                      :aria-label="'گزینه‌های پیام'"
                      :aria-expanded="menuFor === item.message.id"
                      @click.stop="menuFor = menuFor === item.message.id ? null : item.message.id"
                    >⋯</button>
                    <div v-if="menuFor === item.message.id" class="gc-menu" @click.stop>
                      <button v-if="canEdit(item.message)" type="button" @click="startEdit(item.message)">ویرایش</button>
                      <button v-if="canDelete(item.message)" type="button" class="gc-menu-danger" @click="removeMessage(item.message)">حذف</button>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <button v-if="showJump" type="button" class="gc-jump" @click="scrollToBottom(true)">
            <template v-if="newWhileScrolled > 0">{{ toFa(newWhileScrolled) }} پیام جدید ↓</template>
            <template v-else>پیام‌های جدید ↓</template>
          </button>

          <footer class="gc-compose">
            <p v-if="notice" class="gc-notice" role="status">{{ notice }}</p>

            <div v-if="editing" class="gc-context">
              <div>
                <strong>ویرایش پیام</strong>
                <span>{{ editing.originalBody || 'بدون متن' }}</span>
              </div>
              <button type="button" aria-label="لغو ویرایش" @click="cancelEdit">✕</button>
            </div>

            <div v-if="pendingImage" class="gc-context gc-context--image">
              <img :src="pendingImage.preview" alt="پیش‌نمایش تصویر" />
              <div>
                <strong>ارسال تصویر</strong>
                <span>{{ pendingImage.file.name }} · {{ formatSize(pendingImage.file.size) }}</span>
              </div>
              <button type="button" aria-label="حذف تصویر انتخاب‌شده" @click="clearImage">✕</button>
            </div>

            <div v-if="recording" class="gc-recording">
              <span class="gc-rec-dot" aria-hidden="true"></span>
              <span>در حال ضبط · {{ formatDuration(recordSeconds) }}</span>
              <button type="button" class="gc-rec-cancel" @click="cancelRecording">لغو</button>
              <button type="button" class="gc-rec-send" @click="stopRecording(true)">ارسال ویس</button>
            </div>

            <form v-else-if="permissions.send || editing" class="gc-form" @submit.prevent="submit">
              <template v-if="!editing">
                <button type="button" class="gc-icon-btn" title="ارسال تصویر" aria-label="ارسال تصویر" :disabled="sending" @click="$refs.imageInput.click()">
                  <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="4.5" width="17" height="15" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="m4.5 17.5 4.8-4.6 3.3 3 2.6-2.4 4.3 4"/></svg>
                </button>
                <input ref="imageInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" hidden @change="onImagePicked" />
              </template>
              <textarea
                ref="input"
                v-model="draft"
                rows="1"
                :maxlength="4000"
                :placeholder="editing ? 'متن جدید پیام…' : (pendingImage ? 'توضیح تصویر (اختیاری)…' : 'پیام خود را بنویسید…')"
                @keydown.enter.exact.prevent="submit"
                @keydown.esc="editing ? cancelEdit() : null"
                @input="autoGrow"
                @paste="onPaste"
              ></textarea>
              <button
                v-if="!editing && !draft.trim() && !pendingImage"
                type="button"
                class="gc-icon-btn gc-mic"
                title="ضبط پیام صوتی"
                aria-label="ضبط پیام صوتی"
                :disabled="sending"
                @click="startRecording"
              >
                <svg viewBox="0 0 24 24" fill="none"><rect x="9" y="3.5" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v2.5"/></svg>
              </button>
              <button v-else type="submit" class="gc-send" :disabled="sending" :aria-label="editing ? 'ذخیره ویرایش' : 'ارسال پیام'">
                <svg v-if="!editing" viewBox="0 0 24 24" fill="none"><path d="M20 4 3.5 11l6.5 2.5L12.5 20 20 4Z"/><path d="m10 13.5 4-4"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
              </button>
            </form>
            <p v-else class="gc-readonly">شما فقط اجازهٔ مشاهدهٔ گفت‌وگو را دارید.</p>
          </footer>
        </section>
      </Transition>


      <button
        type="button"
        class="gc-launcher"
        :class="{ 'gc-launcher--open': open, 'gc-launcher--ping': pinging }"
        :aria-expanded="open"
        aria-controls="group-chat-panel"
        :aria-label="open ? 'بستن چت گروهی' : 'باز کردن چت گروهی کلینیک'"
        :title="open ? 'بستن چت گروهی' : 'چت گروهی کلینیک'"
        @click="togglePanel"
      >
        <span class="gc-launcher-icon" aria-hidden="true">
          <svg v-if="!open" viewBox="0 0 24 24" fill="none"><path d="M7 18.5 3.5 21V6.5A2.5 2.5 0 0 1 6 4h12a2.5 2.5 0 0 1 2.5 2.5v9A2.5 2.5 0 0 1 18 18H7.5"/><path d="M8 9.5h8M8 13h5"/></svg>
          <svg v-else viewBox="0 0 24 24" fill="none"><path d="M5 5 19 19M19 5 5 19"/></svg>
        </span>
        <span
          v-if="!open && unreadCount > 0"
          :key="badgeBump"
          class="gc-badge"
          :class="{ 'gc-badge--bump': badgeBump > 0 }"
          :aria-label="`${toFa(unreadCount)} پیام خوانده‌نشده`"
        >{{ unreadCount > 99 ? '۹۹+' : toFa(unreadCount) }}</span>
      </button>
    </div>
  </Teleport>

  <!-- لایهٔ جدا روی body تا بالای منو، همبرگر منو و اعلان‌ها قرار بگیرد (خارج از stacking context پنجرهٔ چت). -->
  <Teleport to="body">
      <Transition name="gc-fade">
        <div
          v-if="allowed && lightbox"
          class="gc-lightbox"
          :class="{ 'gc-lightbox--zoomed': lightboxZoomed }"
          dir="rtl"
          role="dialog"
          aria-modal="true"
          aria-label="نمایش تصویر در اندازهٔ کامل"
          @click.self="closeLightbox"
        >
          <img
            :src="lightbox"
            alt="تصویر ارسالی"
            :title="lightboxZoomed ? 'کوچک کردن' : 'نمایش در اندازهٔ واقعی'"
            @click.stop="lightboxZoomed = !lightboxZoomed"
          />
          <div class="gc-lightbox-tools">
            <a :href="lightbox" target="_blank" rel="noopener" aria-label="باز کردن تصویر در زبانهٔ جدید" title="باز کردن در زبانهٔ جدید" @click.stop>↗</a>
            <button type="button" :aria-label="lightboxZoomed ? 'کوچک کردن تصویر' : 'بزرگ‌نمایی تصویر'" :title="lightboxZoomed ? 'کوچک کردن' : 'اندازهٔ واقعی'" @click.stop="lightboxZoomed = !lightboxZoomed">{{ lightboxZoomed ? '－' : '＋' }}</button>
            <button type="button" aria-label="بستن تصویر" title="بستن (Esc)" @click.stop="closeLightbox">✕</button>
          </div>
        </div>
      </Transition>
  </Teleport>
</template>

<script>
import axios from "axios";
import Swal from "sweetalert2";
import { presenceState, subscribeGroupChat, onRealtimeReconnect } from "../services/presence";

const PAGE_SIZE = 40;
const MAX_RECORD_SECONDS = 300;
const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const AVATAR_COLORS = ["#6d28d9", "#2563eb", "#0e7490", "#be185d", "#b45309", "#15803d", "#7c3aed", "#0f766e"];

export default {
  name: "GroupChat",
  props: {
    dark: { type: Boolean, default: false },
    user: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      open: false,
      messages: [],
      hasMore: false,
      loading: false,
      loadingOlder: false,
      loadError: "",
      unreadCount: 0,
      permissions: { send: false, edit: false, delete: false, moderate: false },
      draft: "",
      sending: false,
      notice: "",
      editing: null,
      pendingImage: null,
      recording: false,
      recordSeconds: 0,
      menuFor: null,
      lightbox: null,
      lightboxZoomed: false,
      showJump: false,
      firstUnreadId: null,
      unreadDividerCount: 0,
      newWhileScrolled: 0,
      badgeBump: 0,
      pinging: false,
      soundOn: (() => { try { return localStorage.getItem("groupChatSound") !== "off"; } catch { return true; } })(),
      presence: presenceState,
    };
  },
  computed: {
    allowed() {
      const modules = this.user?.tenant?.module_ids;
      return Array.isArray(modules)
        && modules.includes("group_chat")
        && Array.isArray(this.user?.permissions)
        && this.user.permissions.includes("group_chat.view");
    },
    meId() {
      return Number(this.user?.id || 0);
    },
    onlineCount() {
      return this.presence.users.length;
    },
    threadItems() {
      const items = [];
      let lastDay = null;
      let previous = null;
      for (const message of this.messages) {
        const day = this.dayKey(message.created_at);
        if (day !== lastDay) {
          items.push({ kind: "day", key: `day-${day}`, label: this.dayLabel(message.created_at) });
          lastDay = day;
          previous = null;
        }
        if (this.firstUnreadId && message.id === this.firstUnreadId) {
          items.push({ kind: "unread", key: "unread-divider", label: `${this.toFa(this.unreadDividerCount)} پیام جدید` });
          previous = null;
        }
        const grouped = Boolean(previous
          && previous.sender.id === message.sender.id
          && new Date(message.created_at) - new Date(previous.created_at) < 5 * 60 * 1000);
        items.push({ kind: "message", key: `m-${message.id}`, message, grouped });
        previous = message;
      }
      return items;
    },
  },
  watch: {
    allowed: {
      immediate: true,
      handler(allowed) {
        if (allowed) this.activate();
        else this.deactivate();
      },
    },
  },
  beforeUnmount() {
    this.deactivate();
  },
  methods: {
    activate() {
      if (this.unsubscribe) return;
      this.unsubscribe = subscribeGroupChat(this.onRealtime);
      this.refreshUnread();
      this.reconnectTimer = window.setInterval(this.bindReconnect, 4000);
      this.bindReconnect();
      this.onVisibility = () => {
        if (document.visibilityState === "visible") this.open ? this.syncLatest() : this.refreshUnread();
      };
      document.addEventListener("visibilitychange", this.onVisibility);
      this.onDocClick = () => { this.menuFor = null; };
      document.addEventListener("click", this.onDocClick);
      // مرورگرها پخش صدا را فقط پس از اولین تعامل کاربر اجازه می‌دهند.
      this.unlockAudio = () => {
        this.audioContext();
        if (this.audioCtx?.state === "running") {
          document.removeEventListener("pointerdown", this.unlockAudio, true);
          document.removeEventListener("keydown", this.unlockAudio, true);
        }
      };
      document.addEventListener("pointerdown", this.unlockAudio, true);
      document.addEventListener("keydown", this.unlockAudio, true);
    },
    deactivate() {
      this.unsubscribe?.();
      this.unsubscribe = null;
      this.unbindReconnect?.();
      this.unbindReconnect = null;
      window.clearInterval(this.reconnectTimer);
      if (this.onVisibility) document.removeEventListener("visibilitychange", this.onVisibility);
      if (this.onDocClick) document.removeEventListener("click", this.onDocClick);
      if (this.unlockAudio) {
        document.removeEventListener("pointerdown", this.unlockAudio, true);
        document.removeEventListener("keydown", this.unlockAudio, true);
      }
      window.clearTimeout(this.pingTimer);
      this.cancelRecording();
      this.closeLightbox();
      this.open = false;
      this.messages = [];
    },
    bindReconnect() {
      // Echo پس از ورود دوباره ساخته می‌شود؛ اتصال به رویداد reconnect را تازه نگه می‌داریم.
      if (this.unbindReconnect && this.presence.connected) return;
      this.unbindReconnect?.();
      this.unbindReconnect = onRealtimeReconnect(() => {
        if (this.open) this.syncLatest();
        else this.refreshUnread();
      });
    },

    async togglePanel() {
      if (this.open) return this.closePanel();
      this.open = true;
      this.notice = "";
      await this.loadLatest();
      this.$nextTick(() => this.$refs.input?.focus());
    },
    closePanel() {
      if (this.recording) this.cancelRecording();
      this.open = false;
      this.menuFor = null;
      this.firstUnreadId = null;
      this.newWhileScrolled = 0;
      this.showJump = false;
      this.closeLightbox();
    },
    toggleSound() {
      this.soundOn = !this.soundOn;
      try { localStorage.setItem("groupChatSound", this.soundOn ? "on" : "off"); } catch { /* نادیده */ }
      if (this.soundOn) { this.lastDingAt = 0; this.playDing(); }
    },
    audioContext() {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return null;
      if (!this.audioCtx) this.audioCtx = new Ctx();
      if (this.audioCtx.state === "suspended") this.audioCtx.resume().catch(() => {});
      return this.audioCtx;
    },
    /** صدای «دینگ» کوتاه با Web Audio؛ بدون فایل صوتی و با محدودیت تکرار. */
    playDing() {
      if (!this.soundOn) return;
      const now = Date.now();
      if (now - (this.lastDingAt || 0) < 1200) return;
      this.lastDingAt = now;
      try {
        const ctx = this.audioContext();
        if (!ctx || ctx.state !== "running") return;
        const start = ctx.currentTime + 0.01;
        [[1318.5, 0.2, 0.9], [2637, 0.06, 0.5], [1975.5, 0.05, 0.7]].forEach(([frequency, volume, length]) => {
          const oscillator = ctx.createOscillator();
          const gain = ctx.createGain();
          oscillator.type = "sine";
          oscillator.frequency.setValueAtTime(frequency, start);
          gain.gain.setValueAtTime(0.0001, start);
          gain.gain.exponentialRampToValueAtTime(volume, start + 0.012);
          gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
          oscillator.connect(gain).connect(ctx.destination);
          oscillator.start(start);
          oscillator.stop(start + length + 0.05);
        });
      } catch {
        // مرورگر اجازهٔ پخش نداد؛ نادیده.
      }
    },
    pingLauncher() {
      this.badgeBump += 1;
      this.pinging = true;
      window.clearTimeout(this.pingTimer);
      this.pingTimer = window.setTimeout(() => { this.pinging = false; }, 1300);
    },
    openLightbox(url) {
      this.lightbox = url;
      this.lightboxZoomed = false;
      this.onLightboxKey = (event) => { if (event.key === "Escape") { event.stopPropagation(); this.closeLightbox(); } };
      document.addEventListener("keydown", this.onLightboxKey, true);
    },
    closeLightbox() {
      this.lightbox = null;
      this.lightboxZoomed = false;
      if (this.onLightboxKey) document.removeEventListener("keydown", this.onLightboxKey, true);
      this.onLightboxKey = null;
    },

    async loadLatest() {
      this.loading = true;
      this.loadError = "";
      try {
        const { data } = await axios.get("/api/group-chat/messages", { params: { limit: PAGE_SIZE } });
        const unreadBefore = Number(data.unread_count || 0);
        this.applyMeta(data);
        this.messages = data.messages || [];
        this.hasMore = Boolean(data.has_more);
        // خط «N پیام جدید» قبل از اولین پیام خوانده‌نشده (مثل واتس‌اپ).
        const lastRead = Number(data.last_read_id || 0);
        const unread = this.messages.filter((message) => message.id > lastRead && !this.isMine(message) && !message.is_deleted);
        this.firstUnreadId = unread.length ? unread[0].id : null;
        this.unreadDividerCount = Math.max(unread.length, unreadBefore);
        this.newWhileScrolled = 0;
        this.$nextTick(() => {
          const divider = this.$refs.thread?.querySelector("[data-unread-divider]");
          if (divider) this.$refs.thread.scrollTop = Math.max(0, divider.offsetTop - 48);
          else this.scrollToBottom(true);
        });
        this.markRead();
      } catch (error) {
        this.loadError = this.errorText(error, "بارگذاری پیام‌ها انجام نشد.");
      } finally {
        this.loading = false;
      }
    },
    /** همگام‌سازی پس از قطعی: آخرین صفحه را با فهرست فعلی ادغام می‌کند (پیام جدید، ویرایش و حذف). */
    async syncLatest() {
      if (!this.messages.length) return this.loadLatest();
      try {
        const { data } = await axios.get("/api/group-chat/messages", { params: { limit: PAGE_SIZE } });
        this.applyMeta(data);
        const stick = this.isNearBottom();
        (data.messages || []).forEach((message) => this.upsert(message));
        if (stick) this.$nextTick(() => this.scrollToBottom(true));
        this.markRead();
      } catch {
        // بی‌صدا؛ دفعهٔ بعد دوباره تلاش می‌شود.
      }
    },
    async loadOlder() {
      if (this.loadingOlder || !this.hasMore || !this.messages.length) return;
      this.loadingOlder = true;
      const thread = this.$refs.thread;
      const previousHeight = thread?.scrollHeight || 0;
      try {
        const firstId = this.messages.find((message) => !message.pending)?.id;
        const { data } = await axios.get("/api/group-chat/messages", { params: { before_id: firstId, limit: PAGE_SIZE } });
        const known = new Set(this.messages.map((message) => message.id));
        this.messages = [...(data.messages || []).filter((message) => !known.has(message.id)), ...this.messages];
        this.hasMore = Boolean(data.has_more);
        this.$nextTick(() => {
          if (thread) thread.scrollTop = thread.scrollHeight - previousHeight + thread.scrollTop;
        });
      } catch (error) {
        this.notice = this.errorText(error, "پیام‌های قبلی بارگذاری نشد.");
      } finally {
        this.loadingOlder = false;
      }
    },
    applyMeta(data) {
      if (data.permissions) this.permissions = data.permissions;
      if (typeof data.unread_count === "number") this.unreadCount = data.unread_count;
    },
    async refreshUnread() {
      try {
        const { data } = await axios.get("/api/group-chat/unread");
        this.unreadCount = Number(data.unread_count || 0);
      } catch {
        this.unreadCount = 0;
      }
    },
    async markRead() {
      const last = [...this.messages].reverse().find((message) => !message.pending);
      if (!last) { this.unreadCount = 0; return; }
      try {
        const { data } = await axios.post("/api/group-chat/read", { message_id: last.id });
        this.unreadCount = Number(data.unread_count || 0);
      } catch {
        // نادیده
      }
    },

    onRealtime(event) {
      const message = event?.message;
      if (!message?.id) return;
      if (event.action === "created") {
        const mine = Number(message.sender?.id) === this.meId;
        const incoming = !mine && !message.is_deleted;
        if (incoming) this.playDing();
        if (!this.open) {
          if (incoming) {
            this.unreadCount += 1;
            this.pingLauncher();
          }
          return;
        }
        const stick = this.isNearBottom();
        this.upsert(message);
        if ((stick && document.visibilityState === "visible") || mine) {
          this.$nextTick(() => this.scrollToBottom(true));
          this.markRead();
        } else if (incoming) {
          this.newWhileScrolled += 1;
          this.showJump = true;
        }
        return;
      }
      // updated / deleted
      if (this.open) this.upsert(message, { onlyExisting: true });
      if (!this.open && event.action === "deleted") this.refreshUnread();
      if (this.editing?.id === message.id && message.is_deleted) this.cancelEdit();
    },
    upsert(message, { onlyExisting = false } = {}) {
      const index = this.messages.findIndex((item) => item.id === message.id);
      if (index >= 0) {
        this.messages.splice(index, 1, message);
        return;
      }
      if (onlyExisting) return;
      const position = this.messages.findIndex((item) => !item.pending && item.id > message.id);
      if (position === -1) {
        const firstPending = this.messages.findIndex((item) => item.pending);
        if (firstPending === -1) this.messages.push(message);
        else this.messages.splice(firstPending, 0, message);
      } else {
        this.messages.splice(position, 0, message);
      }
    },

    isMine(message) {
      return Number(message.sender?.id) === this.meId;
    },
    canEdit(message) {
      return this.permissions.edit && this.isMine(message) && message.type !== "voice";
    },
    canDelete(message) {
      return (this.isMine(message) && this.permissions.delete) || this.permissions.moderate;
    },

    async submit() {
      if (this.sending) return;
      if (this.editing) return this.saveEdit();
      const body = this.draft.trim();
      if (this.pendingImage) {
        const form = new FormData();
        form.append("type", "image");
        form.append("image", this.pendingImage.file);
        if (body) form.append("body", body);
        const ok = await this.send(form, { type: "image", body: body || null, attachment_url: this.pendingImage.preview });
        if (ok) { this.draft = ""; this.clearImage(); this.autoGrow(); }
        return;
      }
      if (!body) return;
      const form = new FormData();
      form.append("type", "text");
      form.append("body", body);
      this.draft = "";
      this.autoGrow();
      const ok = await this.send(form, { type: "text", body });
      if (!ok) this.draft = body;
    },
    async send(form, preview) {
      this.sending = true;
      this.notice = "";
      const tempId = `tmp-${Date.now()}`;
      const optimistic = {
        id: tempId,
        type: preview.type,
        body: preview.body || null,
        attachment_url: preview.attachment_url || null,
        voice_duration: preview.voice_duration || null,
        is_deleted: false,
        is_edited: false,
        created_at: new Date().toISOString(),
        sender: { id: this.meId, name: this.user?.name || "شما", avatar_url: this.user?.avatar_url || null },
        pending: true,
      };
      this.messages.push(optimistic);
      this.$nextTick(() => this.scrollToBottom(true));
      try {
        const { data } = await axios.post("/api/group-chat/messages", form);
        const index = this.messages.findIndex((item) => item.id === tempId);
        if (index >= 0) this.messages.splice(index, 1);
        this.upsert(data.message);
        this.unreadCount = 0;
        this.$nextTick(() => this.scrollToBottom(true));
        return true;
      } catch (error) {
        this.messages = this.messages.filter((item) => item.id !== tempId);
        this.notice = this.errorText(error, "ارسال پیام انجام نشد.");
        return false;
      } finally {
        this.sending = false;
        if (preview.attachment_url?.startsWith("blob:") && preview.type === "voice") URL.revokeObjectURL(preview.attachment_url);
      }
    },

    startEdit(message) {
      this.menuFor = null;
      this.clearImage();
      this.editing = { id: message.id, originalBody: message.body || "" };
      this.draft = message.body || "";
      this.$nextTick(() => { this.autoGrow(); this.$refs.input?.focus(); });
    },
    cancelEdit() {
      this.editing = null;
      this.draft = "";
      this.$nextTick(this.autoGrow);
    },
    async saveEdit() {
      const { id, originalBody } = this.editing;
      const body = this.draft.trim();
      const message = this.messages.find((item) => item.id === id);
      if (!message) return this.cancelEdit();
      if (body === originalBody.trim()) return this.cancelEdit();
      if (!body && message.type === "text") { this.notice = "متن پیام نمی‌تواند خالی باشد."; return; }
      this.sending = true;
      try {
        const { data } = await axios.patch(`/api/group-chat/messages/${id}`, { body });
        this.upsert(data.message, { onlyExisting: true });
        this.cancelEdit();
      } catch (error) {
        this.notice = this.errorText(error, "ویرایش پیام انجام نشد.");
      } finally {
        this.sending = false;
      }
    },
    async removeMessage(message) {
      this.menuFor = null;
      const others = !this.isMine(message);
      const result = await Swal.fire({
        icon: "warning",
        title: "حذف پیام",
        text: others
          ? `پیام «${message.sender.name}» برای همه به «این پیام حذف شده است» تبدیل می‌شود.`
          : "این پیام برای همه به «این پیام حذف شده است» تبدیل می‌شود.",
        showCancelButton: true,
        confirmButtonText: "حذف شود",
        cancelButtonText: "انصراف",
        confirmButtonColor: "#dc2626",
        reverseButtons: true,
        customClass: { container: "gc-swal-top" },
      });
      if (!result.isConfirmed) return;
      try {
        const { data } = await axios.delete(`/api/group-chat/messages/${message.id}`);
        this.upsert(data.message, { onlyExisting: true });
        if (this.editing?.id === message.id) this.cancelEdit();
      } catch (error) {
        this.notice = this.errorText(error, "حذف پیام انجام نشد.");
      }
    },

    onImagePicked(event) {
      const file = event.target.files?.[0];
      event.target.value = "";
      if (file) this.setImage(file);
    },
    onPaste(event) {
      if (this.editing) return;
      const file = [...(event.clipboardData?.files || [])].find((item) => item.type.startsWith("image/"));
      if (file) { event.preventDefault(); this.setImage(file); }
    },
    onDrop(event) {
      if (this.editing || !this.permissions.send) return;
      const file = [...(event.dataTransfer?.files || [])].find((item) => item.type.startsWith("image/"));
      if (file) this.setImage(file);
    },
    setImage(file) {
      if (!/^image\/(png|jpe?g|webp|gif)$/i.test(file.type)) { this.notice = "فقط تصویر PNG، JPG، WEBP یا GIF قابل ارسال است."; return; }
      if (file.size > MAX_IMAGE_BYTES) { this.notice = "حجم تصویر باید کمتر از ۱۰ مگابایت باشد."; return; }
      this.clearImage();
      this.notice = "";
      this.pendingImage = { file, preview: URL.createObjectURL(file) };
      this.$nextTick(() => this.$refs.input?.focus());
    },
    clearImage() {
      if (this.pendingImage?.preview) {
        const url = this.pendingImage.preview;
        // پیش‌نمایش ممکن است هنوز در پیام موقت استفاده شود.
        window.setTimeout(() => URL.revokeObjectURL(url), 30000);
      }
      this.pendingImage = null;
    },

    async startRecording() {
      this.notice = "";
      if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === "undefined") {
        this.notice = "مرورگر شما ضبط صدا را پشتیبانی نمی‌کند.";
        return;
      }
      try {
        this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch {
        this.notice = "دسترسی به میکروفون داده نشد.";
        return;
      }
      const mimeType = ["audio/webm;codecs=opus", "audio/mp4", "audio/ogg;codecs=opus", "audio/webm"]
        .find((type) => MediaRecorder.isTypeSupported?.(type));
      this.recorder = new MediaRecorder(this.mediaStream, mimeType ? { mimeType } : undefined);
      this.chunks = [];
      this.sendAfterStop = false;
      this.recorder.ondataavailable = (event) => { if (event.data?.size) this.chunks.push(event.data); };
      this.recorder.onstop = () => this.onRecorderStop();
      this.recorder.start(250);
      this.recordStartedAt = Date.now();
      this.recordSeconds = 0;
      this.recording = true;
      this.recordTimer = window.setInterval(() => {
        this.recordSeconds = Math.floor((Date.now() - this.recordStartedAt) / 1000);
        if (this.recordSeconds >= MAX_RECORD_SECONDS) this.stopRecording(true);
      }, 250);
    },
    stopRecording(send) {
      if (!this.recorder) return;
      this.sendAfterStop = send;
      window.clearInterval(this.recordTimer);
      if (this.recorder.state !== "inactive") this.recorder.stop();
      else this.onRecorderStop();
    },
    cancelRecording() {
      if (!this.recorder) { this.recording = false; return; }
      this.stopRecording(false);
    },
    async onRecorderStop() {
      const recorder = this.recorder;
      const duration = Math.max(1, Math.round((Date.now() - (this.recordStartedAt || Date.now())) / 1000));
      this.mediaStream?.getTracks().forEach((track) => track.stop());
      this.mediaStream = null;
      this.recorder = null;
      this.recording = false;
      if (!this.sendAfterStop || !this.chunks?.length) { this.chunks = []; return; }
      const type = recorder?.mimeType || "audio/webm";
      const blob = new Blob(this.chunks, { type });
      this.chunks = [];
      if (duration < 1 || blob.size < 800) { this.notice = "ویس خیلی کوتاه بود."; return; }
      const extension = type.includes("mp4") ? "m4a" : type.includes("ogg") ? "ogg" : "webm";
      const form = new FormData();
      form.append("type", "voice");
      form.append("voice", blob, `voice.${extension}`);
      form.append("voice_duration", String(duration));
      await this.send(form, { type: "voice", attachment_url: URL.createObjectURL(blob), voice_duration: duration });
    },

    onThreadScroll() {
      const thread = this.$refs.thread;
      if (!thread) return;
      if (thread.scrollTop < 60 && this.hasMore && !this.loadingOlder) this.loadOlder();
      if (this.isNearBottom()) {
        if (this.showJump) { this.showJump = false; this.newWhileScrolled = 0; this.markRead(); }
      }
    },
    onMediaLoad() {
      if (this.isNearBottom(260)) this.scrollToBottom(false);
    },
    isNearBottom(threshold = 120) {
      const thread = this.$refs.thread;
      if (!thread) return true;
      return thread.scrollHeight - thread.scrollTop - thread.clientHeight < threshold;
    },
    scrollToBottom(force) {
      const thread = this.$refs.thread;
      if (!thread) return;
      if (force || this.isNearBottom()) thread.scrollTop = thread.scrollHeight;
      if (force && this.showJump) { this.showJump = false; this.newWhileScrolled = 0; this.markRead(); }
    },
    autoGrow() {
      const input = this.$refs.input;
      if (!input) return;
      input.style.height = "auto";
      input.style.height = `${Math.min(input.scrollHeight, 132)}px`;
    },

    dayKey(value) {
      const date = new Date(value);
      return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
    },
    dayLabel(value) {
      const date = new Date(value);
      const today = new Date();
      const yesterday = new Date();
      yesterday.setDate(today.getDate() - 1);
      if (this.dayKey(date) === this.dayKey(today)) return "امروز";
      if (this.dayKey(date) === this.dayKey(yesterday)) return "دیروز";
      const sameYear = new Intl.DateTimeFormat("en-US-u-ca-persian", { year: "numeric" }).format(date)
        === new Intl.DateTimeFormat("en-US-u-ca-persian", { year: "numeric" }).format(today);
      return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        weekday: "long", day: "numeric", month: "long", ...(sameYear ? {} : { year: "numeric" }),
      }).format(date);
    },
    formatTime(value) {
      return new Intl.DateTimeFormat("fa-IR", { hour: "2-digit", minute: "2-digit", hour12: false }).format(new Date(value));
    },
    fullDateTime(value) {
      return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric", month: "long", day: "numeric", weekday: "long", hour: "2-digit", minute: "2-digit", hour12: false,
      }).format(new Date(value));
    },
    formatDuration(seconds) {
      const total = Math.max(0, Number(seconds) || 0);
      const minutes = Math.floor(total / 60);
      const rest = String(total % 60).padStart(2, "0");
      return this.toFa(`${minutes}:${rest}`);
    },
    formatSize(bytes) {
      const kb = bytes / 1024;
      return kb > 1024 ? `${this.toFa((kb / 1024).toFixed(1))} مگابایت` : `${this.toFa(Math.round(kb))} کیلوبایت`;
    },
    toFa(value) {
      return String(value).replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[digit]);
    },
    initial(sender) {
      return String(sender?.name || "؟").trim().charAt(0) || "؟";
    },
    avatarColor(sender) {
      const key = String(sender?.id ?? sender?.name ?? "");
      let hash = 0;
      for (const char of key) hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
      return AVATAR_COLORS[hash % AVATAR_COLORS.length];
    },
    errorText(error, fallback) {
      const data = error?.response?.data;
      const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;
      if (error?.response?.status === 413) return data?.message || "حجم فایل بیش از فضای مجاز است.";
      return firstError || data?.message || fallback;
    },
  },
};
</script>

<style scoped>
.gc { --gc-ink: #17233a; --gc-muted: #6f7f98; --gc-line: #e5eaf3; --gc-bg: #f4f6fb; --gc-mine: #ede7fd; --gc-other: #ffffff; --gc-accent: #6d28d9; position: relative; z-index: 2147483004; font-family: inherit; }

/* لانچر: هم‌اندازه و هم‌سبک با دستیار هوش مصنوعی، در گوشهٔ پایین راست */
.gc-launcher { position: fixed; bottom: max(22px, env(safe-area-inset-bottom)); right: max(22px, env(safe-area-inset-right)); z-index: 2147483004; width: 58px; height: 58px; display: grid; place-items: center; padding: 7px; border: 1px solid rgba(255,255,255,.34); border-radius: 18px; background: linear-gradient(125deg, #4c1d95 0%, #6d28d9 52%, #2563eb 100%); color: #fff; box-shadow: 0 16px 38px rgba(76, 29, 149, .3), inset 0 1px rgba(255,255,255,.18); cursor: pointer; transition: transform .2s ease, box-shadow .2s ease; }
.gc-launcher:hover { transform: translateY(-3px); box-shadow: 0 20px 42px rgba(76, 29, 149, .36), inset 0 1px rgba(255,255,255,.18); }
.gc-launcher:focus-visible { outline: 3px solid rgba(109, 40, 217, .35); outline-offset: 3px; }
.gc-launcher-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 13px; background: rgba(255,255,255,.17); }
.gc-launcher-icon svg { width: 23px; height: 23px; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.gc-launcher--open { width: 48px; height: 48px; bottom: max(27px, env(safe-area-inset-bottom)); padding: 6px; border-radius: 15px; background: linear-gradient(135deg, #3b1d7e, #2a4ea8); box-shadow: 0 9px 22px rgba(22, 35, 59, .22); }
.gc-launcher--open .gc-launcher-icon { width: 34px; height: 34px; border-radius: 10px; }
.gc-launcher--open .gc-launcher-icon svg { width: 19px; height: 19px; }
.gc-badge--bump { animation: gc-bump .55s cubic-bezier(.3, 1.6, .5, 1); }
@keyframes gc-bump { 0% { transform: scale(.4); } 60% { transform: scale(1.25); } 100% { transform: scale(1); } }
.gc-launcher--ping::after { content: ""; position: absolute; inset: -2px; border-radius: inherit; border: 2px solid rgba(139, 92, 246, .7); animation: gc-ring 1.2s ease-out 1; pointer-events: none; }
@keyframes gc-ring { from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(1.45); } }
.gc-unread-divider { display: flex; align-items: center; gap: 10px; margin: 10px 2px 4px; color: var(--gc-accent); font-size: 11.5px; font-weight: 900; }
.gc-unread-divider::before, .gc-unread-divider::after { content: ""; flex: 1; height: 1px; background: currentColor; opacity: .35; }
.gc-unread-divider span { padding: 3px 12px; border-radius: 999px; background: rgba(109, 40, 217, .1); }
.gc--dark .gc-unread-divider span { background: rgba(183, 156, 255, .14); }
.gc-badge { position: absolute; top: -6px; left: -6px; min-width: 22px; height: 22px; padding: 0 6px; display: grid; place-items: center; border: 2px solid #fff; border-radius: 999px; background: #ef4444; color: #fff; font-size: 11px; font-weight: 900; line-height: 1; box-shadow: 0 4px 10px rgba(239, 68, 68, .35); }

/* پنجره */
.gc-window { position: fixed; bottom: max(92px, calc(env(safe-area-inset-bottom) + 72px)); right: max(22px, env(safe-area-inset-right)); z-index: 2147483004; width: min(420px, calc(100vw - 44px)); height: min(640px, calc(100dvh - 125px)); min-height: 340px; display: flex; flex-direction: column; overflow: hidden; border-radius: 24px; background: var(--gc-bg); color: var(--gc-ink); box-shadow: 0 30px 80px rgba(16, 34, 70, .26), 0 0 0 1px rgba(20, 45, 90, .06); }
.gc-header { position: relative; min-height: 74px; display: flex; align-items: center; gap: 11px; padding: 14px 16px; background: linear-gradient(125deg, #4c1d95 0%, #6d28d9 55%, #2563eb 100%); color: #fff; }
.gc-header-icon { width: 42px; height: 42px; flex: none; display: grid; place-items: center; border-radius: 13px; background: rgba(255,255,255,.17); }
.gc-header-icon svg, .gc-close svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.gc-heading { flex: 1; min-width: 0; }
.gc-heading h2 { margin: 0; font-size: 15px; font-weight: 900; color: #fff; }
.gc-heading p { margin: 3px 0 0; display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: rgba(255,255,255,.82); }
.gc-dot { width: 8px; height: 8px; border-radius: 50%; background: #fbbf24; box-shadow: 0 0 0 3px rgba(255,255,255,.15); }
.gc-dot--on { background: #34d399; }
.gc-close { width: 36px; height: 36px; flex: none; display: grid; place-items: center; border: 0; border-radius: 11px; background: rgba(255,255,255,.14); color: #fff; cursor: pointer; }
.gc-close:hover { background: rgba(255,255,255,.24); }

.gc-thread { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 12px 12px 8px; display: flex; flex-direction: column; gap: 4px; scrollbar-width: thin; }
.gc-empty { margin: auto; display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 24px; text-align: center; font-size: 13px; color: var(--gc-muted); }
.gc-empty strong { color: var(--gc-ink); font-size: 14px; }
.gc-empty--error { color: #b91c1c; }
.gc-link { border: 0; background: none; color: var(--gc-accent); font-weight: 800; cursor: pointer; }
.gc-loader, .gc-older { align-self: center; margin: 2px 0 6px; padding: 5px 12px; border: 0; border-radius: 999px; background: rgba(109, 40, 217, .08); color: var(--gc-accent); font-size: 11.5px; font-weight: 800; cursor: pointer; }
.gc-day { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; margin: 8px 0 6px; pointer-events: none; }
.gc-day span { padding: 4px 12px; border-radius: 999px; background: rgba(255,255,255,.92); color: var(--gc-muted); font-size: 11px; font-weight: 800; box-shadow: 0 1px 4px rgba(16, 34, 70, .08); backdrop-filter: blur(6px); }

/* ردیف پیام: پیام دیگران سمت راست، پیام خودم سمت چپ (RTL) */
.gc-row { display: flex; align-items: flex-end; gap: 7px; max-width: 100%; margin-top: 6px; }
.gc-row--grouped { margin-top: 1px; }
.gc-row--mine { flex-direction: row-reverse; }
.gc-avatar { width: 32px; height: 32px; flex: none; }
.gc-avatar img, .gc-avatar span { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 50%; object-fit: cover; color: #fff; font-size: 13px; font-weight: 900; box-shadow: 0 0 0 2px #fff; }
.gc-bubble { position: relative; max-width: min(78%, 300px); min-width: 92px; padding: 7px 10px 5px; border-radius: 16px 16px 16px 6px; background: var(--gc-other); box-shadow: 0 1px 2px rgba(16, 34, 70, .08); }
.gc-row:not(.gc-row--mine) .gc-bubble { border-radius: 16px 6px 16px 16px; }
.gc-row--mine .gc-bubble { background: var(--gc-mine); border-radius: 16px 16px 6px 16px; }
.gc-row--grouped .gc-bubble { border-radius: 16px; }
.gc-sender { margin-bottom: 2px; font-size: 11.5px; font-weight: 900; color: var(--gc-accent); }
.gc-row--mine .gc-sender { color: #5b21b6; }
.gc-text { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 13.5px; line-height: 1.75; }
.gc-meta { display: flex; justify-content: flex-end; align-items: center; gap: 5px; margin-top: 1px; font-size: 10.5px; color: var(--gc-muted); direction: rtl; }
.gc-edited { font-style: italic; }
.gc-pending { font-size: 11px; }
.gc-deleted { margin: 0; display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-style: italic; color: var(--gc-muted); }
.gc-deleted svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 1.7; flex: none; }
.gc-bubble--deleted { background: transparent !important; box-shadow: inset 0 0 0 1px var(--gc-line); }
.gc-bubble--image { padding: 4px 4px 5px; }
.gc-bubble--image .gc-sender, .gc-bubble--image .gc-text, .gc-bubble--image .gc-meta { padding: 0 6px; }
.gc-image { display: block; width: 100%; padding: 0; border: 0; border-radius: 13px; overflow: hidden; background: rgba(0,0,0,.04); cursor: zoom-in; }
.gc-image img { display: block; width: 100%; max-height: 300px; min-height: 80px; object-fit: cover; }
.gc-voice { display: flex; align-items: center; gap: 6px; }
.gc-voice audio { width: 220px; max-width: 100%; height: 38px; }
.gc-voice-len { font-size: 11px; color: var(--gc-muted); }

.gc-actions { position: absolute; top: 3px; left: 4px; }
.gc-row--mine .gc-actions { left: auto; right: 4px; }
.gc-actions-toggle { width: 24px; height: 22px; padding: 0; border: 0; border-radius: 8px; background: rgba(255,255,255,.85); color: var(--gc-muted); font-size: 15px; line-height: 1; cursor: pointer; opacity: 0; transition: opacity .15s; }
.gc-bubble:hover .gc-actions-toggle, .gc-actions-toggle[aria-expanded="true"], .gc-actions-toggle:focus-visible { opacity: 1; }
@media (hover: none) { .gc-actions-toggle { opacity: .8; } }
.gc-menu { position: absolute; top: 26px; left: 0; z-index: 5; min-width: 110px; padding: 5px; border-radius: 12px; background: #fff; box-shadow: 0 12px 30px rgba(16, 34, 70, .18), 0 0 0 1px rgba(16, 34, 70, .06); }
.gc-row--mine .gc-menu { left: auto; right: 0; }
.gc-menu button { display: block; width: 100%; padding: 7px 10px; border: 0; border-radius: 8px; background: none; color: var(--gc-ink); font-size: 12.5px; font-weight: 700; text-align: right; cursor: pointer; }
.gc-menu button:hover { background: #f3f0fb; }
.gc-menu .gc-menu-danger { color: #dc2626; }
.gc-menu .gc-menu-danger:hover { background: #fef2f2; }

.gc-jump { position: absolute; bottom: 86px; left: 50%; transform: translateX(-50%); z-index: 3; padding: 6px 14px; border: 0; border-radius: 999px; background: var(--gc-accent); color: #fff; font-size: 12px; font-weight: 800; box-shadow: 0 8px 20px rgba(109, 40, 217, .3); cursor: pointer; }

.gc-compose { padding: 8px 10px 10px; border-top: 1px solid var(--gc-line); background: #fff; }
.gc-notice { margin: 0 4px 6px; font-size: 12px; color: #b91c1c; }
.gc-readonly { margin: 4px; font-size: 12px; color: var(--gc-muted); text-align: center; }
.gc-context { display: flex; align-items: center; gap: 10px; margin-bottom: 7px; padding: 7px 10px; border-radius: 12px; border-right: 3px solid var(--gc-accent); background: #f5f2fd; }
.gc-context div { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.gc-context strong { font-size: 12px; color: var(--gc-accent); }
.gc-context span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 12px; color: var(--gc-muted); }
.gc-context button { border: 0; background: none; color: var(--gc-muted); font-size: 14px; cursor: pointer; }
.gc-context--image img { width: 44px; height: 44px; flex: none; border-radius: 9px; object-fit: cover; }
.gc-form { display: flex; align-items: flex-end; gap: 6px; }
.gc-form textarea { flex: 1; min-height: 42px; max-height: 132px; padding: 10px 13px; border: 1px solid var(--gc-line); border-radius: 14px; background: #f7f8fc; color: var(--gc-ink); font: inherit; font-size: 13.5px; line-height: 1.55; resize: none; outline: none; }
.gc-form textarea:focus { border-color: rgba(109, 40, 217, .45); background: #fff; box-shadow: 0 0 0 3px rgba(109, 40, 217, .1); }
.gc-icon-btn, .gc-send { width: 42px; height: 42px; flex: none; display: grid; place-items: center; border: 0; border-radius: 13px; background: #f1edfb; color: var(--gc-accent); cursor: pointer; }
.gc-icon-btn:hover { background: #e7e0fa; }
.gc-send { background: linear-gradient(135deg, #6d28d9, #2563eb); color: #fff; }
.gc-icon-btn svg, .gc-send svg { width: 21px; height: 21px; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.gc-icon-btn:disabled, .gc-send:disabled { opacity: .55; cursor: default; }
.gc-recording { display: flex; align-items: center; gap: 9px; min-height: 42px; padding: 0 6px; font-size: 13px; font-weight: 800; color: #b91c1c; }
.gc-rec-dot { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; animation: gc-pulse 1s ease-in-out infinite; }
.gc-recording span:nth-child(2) { flex: 1; }
.gc-rec-cancel, .gc-rec-send { padding: 8px 12px; border: 0; border-radius: 11px; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; }
.gc-rec-cancel { background: #f3f4f6; color: #374151; }
.gc-rec-send { background: linear-gradient(135deg, #6d28d9, #2563eb); color: #fff; }
@keyframes gc-pulse { 50% { opacity: .35; } }

/* نمایش تمام‌صفحهٔ تصویر: روی کل صفحه، بالاتر از پنجرهٔ چت و لانچرها */
.gc-lightbox { position: fixed; inset: 0; z-index: 2147483647; isolation: isolate; display: flex; align-items: center; justify-content: center; padding: max(56px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom)); overflow: auto; background: rgba(6, 10, 20, .94); backdrop-filter: blur(4px); cursor: zoom-out; }
.gc-lightbox img { display: block; max-width: 100%; max-height: 100%; border-radius: 10px; object-fit: contain; box-shadow: 0 20px 60px rgba(0,0,0,.5); cursor: zoom-in; }
.gc-lightbox--zoomed { align-items: flex-start; justify-content: flex-start; }
.gc-lightbox--zoomed img { max-width: none; max-height: none; margin: auto; border-radius: 0; cursor: zoom-out; }
.gc-lightbox-tools { position: fixed; top: max(12px, env(safe-area-inset-top)); left: 12px; z-index: 2; display: flex; gap: 8px; }
.gc-lightbox-tools button, .gc-lightbox-tools a { width: 40px; height: 40px; display: grid; place-items: center; border: 0; border-radius: 12px; background: rgba(255,255,255,.16); color: #fff; font-size: 17px; text-decoration: none; cursor: pointer; }
.gc-lightbox-tools button:hover, .gc-lightbox-tools a:hover { background: rgba(255,255,255,.28); }
.gc-fade-enter-active, .gc-fade-leave-active { transition: opacity .18s ease; }
.gc-fade-enter-from, .gc-fade-leave-to { opacity: 0; }

.gc-panel-enter-active, .gc-panel-leave-active { transition: opacity .18s ease, transform .18s ease; transform-origin: bottom right; }
.gc-panel-enter-from, .gc-panel-leave-to { opacity: 0; transform: translateY(12px) scale(.97); }

/* حالت تاریک */
.gc--dark { --gc-ink: #e7edf7; --gc-muted: #9aa8bf; --gc-line: #2f3f59; --gc-bg: #111b2e; --gc-mine: #3b2a6b; --gc-other: #1c2a42; --gc-accent: #b79cff; }
.gc--dark .gc-window { box-shadow: 0 30px 80px rgba(0,0,0,.5), 0 0 0 1px rgba(120, 150, 200, .12); }
.gc--dark .gc-header { background: linear-gradient(125deg, #2e1065 0%, #4c1d95 55%, #1e3a8a 100%); }
.gc--dark .gc-day span { background: rgba(28, 42, 66, .92); color: #b6c2d6; }
.gc--dark .gc-row--mine .gc-sender { color: #d5c6ff; }
.gc--dark .gc-avatar img, .gc--dark .gc-avatar span { box-shadow: 0 0 0 2px #111b2e; }
.gc--dark .gc-actions-toggle { background: rgba(17, 27, 46, .85); color: #c8d3e6; }
.gc--dark .gc-menu { background: #1c2a42; box-shadow: 0 12px 30px rgba(0,0,0,.45), 0 0 0 1px rgba(120,150,200,.15); }
.gc--dark .gc-menu button:hover { background: #283a58; }
.gc--dark .gc-menu .gc-menu-danger { color: #f87171; }
.gc--dark .gc-menu .gc-menu-danger:hover { background: rgba(248, 113, 113, .12); }
.gc--dark .gc-compose { background: #16233a; }
.gc--dark .gc-form textarea { background: #111b2e; border-color: #2f3f59; color: #e7edf7; }
.gc--dark .gc-form textarea:focus { background: #0f192b; border-color: rgba(183, 156, 255, .5); box-shadow: 0 0 0 3px rgba(183, 156, 255, .14); }
.gc--dark .gc-form textarea::placeholder { color: #7f8ea8; }
.gc--dark .gc-icon-btn { background: #24304a; color: #c9b6ff; }
.gc--dark .gc-icon-btn:hover { background: #2c3a58; }
.gc--dark .gc-context { background: #1f2a44; }
.gc--dark .gc-loader, .gc--dark .gc-older { background: rgba(183, 156, 255, .12); }
.gc--dark .gc-notice, .gc--dark .gc-empty--error { color: #fca5a5; }
.gc--dark .gc-rec-cancel { background: #24304a; color: #dbe3f0; }
.gc--dark .gc-recording { color: #fca5a5; }
.gc--dark .gc-badge { border-color: #111b2e; }
.gc--dark .gc-voice audio { color-scheme: dark; }

@media (max-width: 640px) {
  .gc-launcher { bottom: max(16px, env(safe-area-inset-bottom)); right: max(14px, env(safe-area-inset-right)); width: 53px; height: 53px; padding: 5px; border-radius: 16px; }
  .gc-launcher-icon { width: 40px; height: 40px; }
  .gc-launcher--open { display: none; }
  .gc-window { left: 0; right: 0; bottom: 0; width: 100%; height: min(720px, 92dvh); max-height: 100dvh; min-height: 0; border-radius: 22px 22px 0 0; }
  .gc-bubble { max-width: 80%; }
}
@media (prefers-reduced-motion: reduce) {
  .gc-launcher, .gc-panel-enter-active, .gc-panel-leave-active { transition: none; }
  .gc-rec-dot, .gc-badge--bump, .gc-launcher--ping::after { animation: none; }
}
</style>

<style>
/* SweetAlert باید بالای پنجرهٔ چت دیده شود. */
.gc-swal-top { z-index: 2147483100 !important; }
</style>
