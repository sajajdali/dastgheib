<template>
  <div class="st-overlay" @mousedown.self="$emit('close')">
    <div class="st-modal" @mousedown.stop>
      <div class="st-mhead">
        <div class="col">
          <span class="h">{{ draft.id ? 'ویرایش وظیفه' : 'وظیفه جدید' }}</span>
          <span class="sub">{{ dayLabel }}</span>
        </div>
        <button class="st-x" type="button" @click="$emit('close')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>

      <div class="st-mbody">
        <div class="fld" :class="{ err: err && !draft.staffIds.length }">مسئولان
          <div class="asg-box" :class="{ err: err && !draft.staffIds.length }">
            <div v-if="selectedStaff.length" class="asg-selected">
              <button
                v-for="p in selectedStaff"
                :key="'sel-' + p.id"
                type="button"
                class="asg-chip"
                :title="'حذف ' + p.name"
                @click="toggleStaff(p.id)"
              >
                <span class="av xs" :style="avStyle(p)">
                  <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
                  <template v-else>{{ (p.name || '?').charAt(0) }}</template>
                </span>
                <span class="asg-chip-name">{{ p.name }}</span>
                <span class="asg-chip-x" aria-hidden="true">×</span>
              </button>
            </div>

            <div v-if="showStaffSearch" class="asg-search">
              <input
                v-model.trim="staffQuery"
                type="search"
                class="st-input asg-search-input"
                placeholder="جستجوی پرسنل…"
                autocomplete="off"
              />
            </div>

            <div class="asg-list" role="listbox" aria-multiselectable="true">
              <button
                v-for="p in filteredStaff"
                :key="p.id"
                type="button"
                class="asg-pick"
                role="option"
                :aria-selected="isSelected(p.id)"
                :class="{ on: isSelected(p.id) }"
                @click="toggleStaff(p.id)"
              >
                <span class="av xs" :style="avStyle(p)">
                  <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
                  <template v-else>{{ (p.name || '?').charAt(0) }}</template>
                </span>
                <span class="asg-pick-name">{{ p.name }}</span>
                <span v-if="isSelected(p.id)" class="asg-pick-check" aria-hidden="true">✓</span>
              </button>
              <p v-if="!filteredStaff.length" class="asg-empty muted">نتیجه‌ای یافت نشد</p>
            </div>
          </div>
        </div>

        <div class="grid2">
          <div class="fld">تاریخ
            <JalaliDateSelect v-model="draft.day" />
          </div>
          <label class="fld">اولویت
            <select v-model="draft.priority" class="st-input">
              <option value="low">کم</option>
              <option value="normal">عادی</option>
              <option value="high">زیاد</option>
              <option value="urgent">فوری</option>
            </select>
          </label>
        </div>

        <label class="fld">عنوان
          <input v-model="draft.title" class="st-input" :class="{ err }" placeholder="عنوان وظیفه" @input="err = false" />
        </label>
        <label class="fld">توضیحات
          <textarea v-model="draft.note" rows="2" class="st-input" placeholder="اختیاری"></textarea>
        </label>

        <div v-if="draft.id" class="fld">وضعیت فعلی
          <div class="chips">
            <button type="button" class="chip" :style="chipStyle(null)" @click="draft.statusId = null">بدون وضعیت</button>
            <button v-for="s in state.statuses" :key="s.id" type="button" class="chip" :style="chipStyle(s)" @click="draft.statusId = s.id">{{ s.title }}</button>
          </div>
        </div>

        <div class="media-row">
          <label class="media-btn">
            <input type="file" accept="image/*,.heic,.heif,image/heic,image/heif" hidden @change="onImage" />
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            تصویر
          </label>
          <label class="media-btn">
            <input type="file" multiple hidden @change="onFiles" />
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21.44 11.05 12.07 20.4a5 5 0 0 1-7.07-7.07l9.78-9.78a3.5 3.5 0 0 1 4.95 4.95l-9.79 9.78a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            فایل
          </label>
          <button type="button" class="media-btn" :class="{ rec: recording }" @click="toggleRec">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/></svg>
            {{ recording ? 'توقف' : 'ضبط صدا' }}
          </button>
        </div>

        <div v-if="imagePreview || draft.image_url" class="preview">
          <img :src="imagePreview || draft.image_url" alt="" />
          <button type="button" class="rm" @click="clearImage">حذف تصویر</button>
        </div>

        <div v-if="pendingFiles.length || (draft.attachments || []).length" class="att-list">
          <div v-for="(f, i) in pendingFiles" :key="'p'+i" class="att">
            <span>{{ f.name }}</span>
            <button type="button" @click="pendingFiles.splice(i,1)">×</button>
          </div>
          <div v-for="a in (draft.attachments || [])" :key="a.id" class="att">
            <span class="who">
              <span class="av xs" :style="uploaderStyle(a)"><img v-if="a.uploader_avatar_url" :src="a.uploader_avatar_url" alt="" /><template v-else>{{ (a.uploader_name||'?').charAt(0) }}</template></span>
              {{ a.uploader_name }} · {{ a.original_name || 'پیوست' }}
            </span>
            <button v-if="canDeleteMediaItem(draft, a)" type="button" class="rm" @click="removeAttachment(a)">حذف</button>
          </div>
        </div>

        <div v-if="pendingVoice || (draft.voices || []).length" class="voice-box">
          <div v-if="pendingVoice" class="voice-row">
            <div class="voice-player" dir="ltr">
              <audio :src="pendingVoiceUrl" controls preload="metadata" controlsList="nodownload"></audio>
            </div>
            <div class="voice-actions">
              <span class="who">آماده ارسال</span>
              <button type="button" class="rm" @click="clearVoice">حذف</button>
            </div>
          </div>
          <div v-for="v in (draft.voices || [])" :key="v.id" class="voice-row">
            <div class="voice-actions">
              <span class="who">
                <span class="av xs" :style="uploaderStyle(v)"><img v-if="v.uploader_avatar_url" :src="v.uploader_avatar_url" alt="" /><template v-else>{{ (v.uploader_name||'?').charAt(0) }}</template></span>
                {{ v.uploader_name }}
              </span>
              <button v-if="canDeleteMediaItem(draft, v)" type="button" class="rm" @click="removeVoice(v)">حذف</button>
            </div>
            <div class="voice-player" dir="ltr">
              <audio :src="voiceSrc(v)" controls preload="metadata" controlsList="nodownload"></audio>
            </div>
            <small>{{ formatTime(v.created_at) }}</small>
          </div>
        </div>

        <div class="exp-box">
          <div class="exp-head">
            <span class="t">مهلت خودکار</span>
            <button type="button" class="switch" :class="{ on: draft.expiry.enabled }" @click="draft.expiry.enabled = !draft.expiry.enabled"><span></span></button>
          </div>
          <template v-if="draft.expiry.enabled">
            <div class="grid-exp">
              <div class="fld">تاریخ
                <JalaliDateSelect v-model="draft.expiry.day" />
              </div>
              <label class="fld">ساعت
                <input type="time" dir="ltr" v-model="draft.expiry.time" class="st-input" style="text-align:right" />
              </label>
            </div>
            <label class="fld">وضعیت پس از انقضا
              <select v-model="draft.expiry.statusId" class="st-input">
                <option :value="null">—</option>
                <option v-for="s in state.statuses" :key="s.id" :value="s.id">{{ s.title }}</option>
              </select>
            </label>
          </template>
        </div>
      </div>

      <div class="st-mfoot">
        <div>
          <button v-if="draft.id && canDeleteTask(draft)" type="button" class="btn danger" :disabled="saving" @click="askDelete">حذف</button>
        </div>
        <div class="row">
          <button type="button" class="btn ghost" @click="$emit('close')">انصراف</button>
          <button type="button" class="btn primary" :disabled="saving" @click="submit">{{ saving ? '…' : 'ذخیره' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onBeforeUnmount } from 'vue'
import JalaliDateSelect from './JalaliDateSelect.vue'
import { useStaffTasks, PALETTE } from './useStaffTasks'
import { fullDate, todayJdn } from './jalali'

const props = defineProps({ task: { type: Object, required: true } })
const emit = defineEmits(['close', 'save', 'delete'])
const { state, canDeleteTask, canDeleteMediaItem, deleteAttachment, deleteVoice } = useStaffTasks()

const src = props.task || {}
const initialDay = Number(src.day) || todayJdn()
const draft = reactive({
  id: src.id || null,
  title: src.title || '',
  note: src.note || '',
  day: initialDay,
  priority: src.priority || 'normal',
  progress: src.progress || 0,
  staffIds: [...(src.staffIds || (src.staffId ? [src.staffId] : []))].map(Number),
  statusId: src.statusId ?? null,
  expiry: {
    enabled: !!src.expiry?.enabled,
    day: Number(src.expiry?.day) || initialDay,
    time: src.expiry?.time || '18:00',
    statusId: src.expiry?.statusId ?? state.expiryDefault,
  },
  image_url: src.image_url || null,
  attachments: src.attachments || [],
  voices: src.voices || [],
})
const dayLabel = computed(() => {
  try { return fullDate(draft.day) } catch { return '' }
})

const err = ref(false)
const saving = ref(false)
const staffQuery = ref('')
const imageFile = ref(null)
const imagePreview = ref(null)
const removeImage = ref(false)
const pendingFiles = reactive([])
const pendingVoice = ref(null)
const pendingVoiceUrl = ref(null)
const voiceDuration = ref(0)
const recording = ref(false)
let mediaRecorder = null
let chunks = []
let recStarted = 0

const showStaffSearch = computed(() => (state.staff?.length || 0) > 10)
const selectedStaff = computed(() =>
  draft.staffIds
    .map(id => state.staff.find(p => Number(p.id) === Number(id)))
    .filter(Boolean)
)
const filteredStaff = computed(() => {
  const q = staffQuery.value.trim().toLowerCase()
  const list = state.staff || []
  if (!q) return list
  return list.filter(p => String(p.name || '').toLowerCase().includes(q))
})

function isSelected(id) {
  return draft.staffIds.some(x => Number(x) === Number(id))
}
function toggleStaff(id) {
  const nid = Number(id)
  const i = draft.staffIds.findIndex(x => Number(x) === nid)
  if (i >= 0) draft.staffIds.splice(i, 1)
  else draft.staffIds.push(nid)
  if (draft.staffIds.length) err.value = false
}
function avStyle(p) {
  return p.avatar_url ? {} : { background: '#64748b' }
}
function uploaderStyle(a) {
  return a.uploader_avatar_url ? {} : { background: '#64748b' }
}
function voiceSrc(v) {
  const url = typeof v === 'string' ? v : (v?.url || '')
  if (!url) return ''
  try {
    if (url.startsWith('/')) return url
    const u = new URL(url, window.location.origin)
    if (u.pathname.startsWith('/storage/')) return u.pathname + u.search
    return url
  } catch {
    return url
  }
}
function chipStyle(s) {
  const on = (draft.statusId || null) === (s ? s.id : null)
  const p = s ? PALETTE[s.color] : { c: '#475569', fg: '#fff' }
  return on ? { background: p.c, color: p.fg, borderColor: p.c } : {}
}
function onImage(e) {
  const f = e.target.files?.[0]
  if (!f) return
  const name = String(f.name || '')
  const type = String(f.type || '')
  const ok = type.startsWith('image/') || /\.(heic|heif)$/i.test(name)
  if (!ok) {
    e.target.value = ''
    return
  }
  imageFile.value = f
  removeImage.value = false
  // HEIC often has empty/odd mime; object URL may not preview in every browser — upload still works.
  imagePreview.value = URL.createObjectURL(f)
  e.target.value = ''
}
function clearImage() {
  imageFile.value = null
  imagePreview.value = null
  removeImage.value = true
  draft.image_url = null
}
function onFiles(e) {
  Array.from(e.target.files || []).forEach(f => pendingFiles.push(f))
  e.target.value = ''
}
async function toggleRec() {
  if (recording.value) {
    mediaRecorder?.stop()
    return
  }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    chunks = []
    recStarted = Date.now()
    mediaRecorder = new MediaRecorder(stream)
    mediaRecorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data) }
    mediaRecorder.onstop = () => {
      const blob = new Blob(chunks, { type: 'audio/webm' })
      pendingVoice.value = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' })
      pendingVoiceUrl.value = URL.createObjectURL(blob)
      voiceDuration.value = Math.round((Date.now() - recStarted) / 1000)
      stream.getTracks().forEach(t => t.stop())
      recording.value = false
    }
    mediaRecorder.start()
    recording.value = true
  } catch {
    alert('دسترسی میکروفن داده نشد.')
  }
}
function clearVoice() {
  pendingVoice.value = null
  if (pendingVoiceUrl.value) URL.revokeObjectURL(pendingVoiceUrl.value)
  pendingVoiceUrl.value = null
  voiceDuration.value = 0
}
function askDelete() {
  if (!draft.id || !canDeleteTask(draft)) return
  if (!confirm('این وظیفه حذف شود؟')) return
  emit('delete', draft.id)
}
async function removeAttachment(a) {
  if (!draft.id || !a?.id) return
  if (!confirm('این پیوست حذف شود؟')) return
  try {
    const updated = await deleteAttachment(draft.id, a.id)
    draft.attachments = updated.attachments || []
  } catch (e) {
    alert(e.response?.data?.message || e.message || 'حذف پیوست ممکن نشد.')
  }
}
async function removeVoice(v) {
  if (!draft.id || !v?.id) return
  if (!confirm('این پیام صوتی حذف شود؟')) return
  try {
    const updated = await deleteVoice(draft.id, v.id)
    draft.voices = updated.voices || []
  } catch (e) {
    alert(e.response?.data?.message || e.message || 'حذف پیام صوتی ممکن نشد.')
  }
}
function formatTime(v) {
  return v ? new Date(v).toLocaleString('fa-IR') : ''
}
async function submit() {
  if (!draft.title.trim() || !draft.staffIds.length) { err.value = true; return }
  saving.value = true
  try {
    emit('save', {
      draft: JSON.parse(JSON.stringify(draft)),
      image: imageFile.value,
      attachments: [...pendingFiles],
      voice: pendingVoice.value,
      voiceDuration: voiceDuration.value,
      removeImage: removeImage.value,
    })
  } finally {
    saving.value = false
  }
}
onBeforeUnmount(() => {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
  if (pendingVoiceUrl.value) URL.revokeObjectURL(pendingVoiceUrl.value)
  if (recording.value) mediaRecorder?.stop()
})
</script>

<style scoped>
.st-overlay{
  --st-surface:#fff;--st-surface-2:#f8fafc;--st-muted-bg:#f1f5f9;--st-border:#e2e8f0;--st-strong-border:#cbd5e1;--st-line:#eef2f7;
  --st-text:#0f172a;--st-text-2:#334155;--st-text-3:#475569;--st-muted:#64748b;
  --st-accent:#2563eb;--st-accent-hover:#1d4ed8;--st-accent-soft:#eff4ff;--st-red:#dc2626;--st-holiday:#fef2f2;
  --st-pos:#15803d;--st-neg:#b91c1c;--st-overlay:rgba(15,23,42,.45);
  position:fixed;inset:0;background:var(--st-overlay);z-index:2147483012;display:flex;align-items:center;justify-content:center;padding:16px;animation:stFade .2s
}
.dark .st-overlay,#app.dark .st-overlay{
  --st-surface:#172033;--st-surface-2:#111a2b;--st-muted-bg:#1e293b;--st-border:#334155;--st-strong-border:#475569;--st-line:#243044;
  --st-text:#f8fafc;--st-text-2:#e2e8f0;--st-text-3:#cbd5e1;--st-muted:#94a3b8;
  --st-accent:#3b82f6;--st-accent-hover:#2563eb;--st-accent-soft:rgba(59,130,246,.14);--st-red:#f87171;--st-holiday:rgba(220,38,38,.10);
  --st-overlay:rgba(2,6,23,.65)
}
.st-modal{width:560px;max-width:100%;max-height:calc(100vh - 32px);overflow-y:auto;background:var(--st-surface);color:var(--st-text);border-radius:18px;box-shadow:0 30px 60px -15px rgba(15,23,42,.35);display:flex;flex-direction:column}
.st-mhead{padding:14px 18px;border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.col{display:flex;flex-direction:column;gap:2px}
.h{font-size:15px;font-weight:800}
.sub{font-size:11px;color:var(--st-muted);font-weight:600}
.t{font-size:13px;font-weight:800}
.st-x{border:0;background:var(--st-muted-bg);width:32px;height:32px;border-radius:9px;cursor:pointer;color:var(--st-muted);display:flex;align-items:center;justify-content:center}
.st-mbody{padding:14px 18px;display:flex;flex-direction:column;gap:12px}
.grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}
.grid-exp{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:8px}
.fld{display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:700;color:var(--st-text-3)}
.st-input{font-family:inherit;font-size:13px;border:1px solid var(--st-border);border-radius:10px;padding:8px 10px;background:var(--st-surface);color:var(--st-text);outline-color:var(--st-accent);resize:vertical}
.st-input.err{border-color:var(--st-red)}
.asg-box{border:1px solid var(--st-border);border-radius:12px;background:var(--st-surface-2);padding:8px;display:flex;flex-direction:column;gap:8px;min-width:0}
.asg-box.err,.fld.err .asg-box{border-color:var(--st-red)}
.asg-selected{display:flex;flex-wrap:wrap;gap:8px;padding:2px 2px 8px;border-bottom:1px solid var(--st-line)}
.asg-chip{font-family:inherit;border:1px solid var(--st-accent);background:var(--st-accent-soft);color:var(--st-accent);border-radius:999px;padding:5px 12px 5px 6px;display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;font-weight:700;max-width:100%}
.asg-chip-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px}
.asg-chip-x{font-size:14px;line-height:1;opacity:.75;margin-inline-start:2px}
.asg-empty{margin:0;padding:10px 4px;font-size:12px;font-weight:600;color:var(--st-muted);text-align:center}
.asg-empty.muted{padding:10px 4px;text-align:center}
.asg-search{min-width:0}
.asg-search-input{width:100%;box-sizing:border-box}
.asg-list{max-height:180px;overflow-y:auto;display:flex;flex-wrap:wrap;align-content:flex-start;gap:8px;padding:10px;border-radius:10px;background:var(--st-surface);border:1px solid var(--st-line);min-height:0}
.asg-pick{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface);border-radius:999px;padding:5px 12px 5px 6px;display:inline-flex;align-items:center;gap:8px;cursor:pointer;color:var(--st-text-2);font-size:12px;font-weight:700;max-width:100%}
.asg-pick:hover{background:var(--st-muted-bg)}
.asg-pick.on{border-color:var(--st-accent);background:var(--st-accent-soft);color:var(--st-accent)}
.asg-pick-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px}
.asg-pick-check{font-size:11px;font-weight:900;opacity:.9;margin-inline-start:2px}
.av{width:26px;height:26px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:800;flex-shrink:0;background:#64748b}
.av.xs{width:20px;height:20px;font-size:9px}
.av img{width:100%;height:100%;object-fit:cover}
.chips{display:flex;flex-wrap:wrap;gap:5px}
.chip{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-2);border-radius:999px;padding:5px 11px;font-size:12px;font-weight:700;cursor:pointer}
.media-row{display:flex;flex-wrap:wrap;gap:6px}
.media-btn{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface-2);border-radius:10px;padding:7px 11px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;color:var(--st-text-2)}
.media-btn.rec{background:var(--st-holiday);color:var(--st-red);border-color:var(--st-red)}
.preview{display:flex;align-items:center;gap:10px}
.preview img{width:72px;height:72px;object-fit:cover;border-radius:10px}
.att-list,.voice-box{display:flex;flex-direction:column;gap:6px}
.att{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:10px;background:var(--st-surface-2);font-size:12px}
.voice-row{display:flex;flex-direction:column;gap:6px;padding:8px 10px;border-radius:10px;background:var(--st-surface-2);font-size:12px;min-width:0}
.voice-actions{display:flex;align-items:center;justify-content:space-between;gap:8px}
.voice-player{width:100%;min-width:0}
.voice-player audio{display:block;width:100%;height:36px;border-radius:8px;color-scheme:light}
.who{display:flex;align-items:center;gap:6px;font-weight:700;color:var(--st-text-2)}
.rm{border:0;background:transparent;color:var(--st-red);cursor:pointer;font-size:12px;font-weight:700}
.exp-box{border:1px solid var(--st-line);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:10px}
.exp-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
.switch{width:40px;height:24px;border-radius:999px;border:0;background:var(--st-strong-border);padding:3px;cursor:pointer;display:flex;justify-content:flex-end;flex-shrink:0}
.switch.on{background:var(--st-accent);justify-content:flex-start}
.switch span{width:18px;height:18px;border-radius:50%;background:var(--st-surface);box-shadow:0 1px 3px rgba(0,0,0,.2)}
.st-mfoot{padding:12px 18px;border-top:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.row{display:flex;gap:6px}
.btn{font-family:inherit;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:700;cursor:pointer;border:0}
.btn.primary{background:var(--st-accent);color:#fff;padding:8px 20px}
.btn.ghost{border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-3)}
.btn.danger{background:var(--st-holiday);color:var(--st-red)}
@keyframes stFade{0%{opacity:0}100%{opacity:1}}
@media (max-width:560px){.grid2,.grid-exp{grid-template-columns:1fr}}
</style>
