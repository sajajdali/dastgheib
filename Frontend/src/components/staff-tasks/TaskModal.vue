<template>
  <div class="st-overlay" @click.self="$emit('close')">
    <div class="st-modal">
      <div class="st-mhead">
        <div class="col">
          <span class="h">{{ draft.id ? 'ویرایش وظیفه' : 'وظیفه جدید' }}</span>
          <span class="sub">{{ fullDate(draft.day) }}</span>
        </div>
        <button class="st-x" @click="$emit('close')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>

      <div class="st-mbody">
        <div class="grid2">
          <label class="fld">پرسنل
            <select v-model="draft.staffId" class="st-input">
              <option v-for="p in state.staff" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <div class="fld">تاریخ وظیفه
            <JalaliDateSelect v-model="draft.day" />
          </div>
        </div>

        <label class="fld">عنوان وظیفه
          <input v-model="draft.title" class="st-input" :class="{ err: error }" placeholder="مثلاً تماس با بیماران نوبت فردا" @input="error = false" />
        </label>
        <label class="fld">توضیحات
          <textarea v-model="draft.note" rows="2" class="st-input" placeholder="جزئیات وظیفه (اختیاری)"></textarea>
        </label>

        <div class="fld">وضعیت
          <div class="chips">
            <button class="chip" :style="chipStyle(null)" @click="draft.statusId = null">
              <span class="dot" style="background:var(--st-strong-border)"></span>تعیین نشده
            </button>
            <button v-for="s in state.statuses" :key="s.id" class="chip" :style="chipStyle(s)" @click="draft.statusId = s.id">
              <span class="dot" :style="{ background: PALETTE[s.color].c }"></span>{{ s.title }}
            </button>
          </div>
          <div class="note" :style="{ color: effColor }">{{ effectNote }}</div>
        </div>

        <div class="exp-box">
          <div class="exp-head">
            <div class="col">
              <span class="t">انقضای خودکار</span>
              <span class="sub">اگر تا زمان مهلت وضعیت تعیین نشود، خودکار تغییر می‌کند</span>
            </div>
            <button class="switch" :class="{ on: draft.expiry.enabled }" @click="draft.expiry.enabled = !draft.expiry.enabled"><span></span></button>
          </div>
          <template v-if="draft.expiry.enabled">
            <div class="grid-exp">
              <div class="fld">تاریخ انقضا · {{ WEEKDAYS[weekday(draft.expiry.day)] }}
                <JalaliDateSelect v-model="draft.expiry.day" />
              </div>
              <label class="fld">ساعت دقیق
                <input type="time" dir="ltr" v-model="draft.expiry.time" class="st-input" style="text-align:right" />
              </label>
            </div>
            <label class="fld">وضعیت پس از انقضا
              <select v-model="draft.expiry.statusId" class="st-input">
                <option v-for="s in state.statuses" :key="s.id" :value="s.id">{{ s.title }}</option>
              </select>
            </label>
            <div class="note" style="color:var(--st-text-3);line-height:1.7">{{ expiryNote }}</div>
          </template>
        </div>
      </div>

      <div class="st-mfoot">
        <div>
          <button v-if="draft.id" class="btn danger" @click="$emit('delete', draft.id)">حذف وظیفه</button>
        </div>
        <div class="row">
          <button class="btn ghost" @click="$emit('close')">انصراف</button>
          <button class="btn primary" @click="submit">ذخیره</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import JalaliDateSelect from './JalaliDateSelect.vue'
import { useStaffTasks, PALETTE } from './useStaffTasks'
import { fullDate, weekday, d2j, WEEKDAYS, MONTHS, fa, money } from './jalali'

const props = defineProps({ task: { type: Object, required: true } })
const emit = defineEmits(['close', 'save', 'delete'])
const { state, statusById, effectOf } = useStaffTasks()

const draft = reactive(JSON.parse(JSON.stringify(props.task)))
const error = ref(false)

const staffName = computed(() => state.staff.find(p => p.id === draft.staffId)?.name || '')
const st = computed(() => statusById(draft.statusId))
const eff = computed(() => effectOf(st.value))
const effColor = computed(() => (eff.value > 0 ? 'var(--st-pos)' : eff.value < 0 ? 'var(--st-neg)' : 'var(--st-muted)'))
const effectNote = computed(() => {
  if (!st.value) return 'تا تعیین وضعیت، تأثیری روی حقوق ثبت نمی‌شود.'
  if (!eff.value) return `وضعیت «${st.value.title}» تأثیری روی حقوق ندارد.`
  return eff.value > 0 ? `${money(eff.value)} تومان به حقوق ${staffName.value} اضافه می‌شود.` : `${money(-eff.value)} تومان از حقوق ${staffName.value} کسر می‌شود.`
})
const expiryNote = computed(() => {
  const est = statusById(draft.expiry.statusId), e = effectOf(est), [, m, d] = d2j(draft.expiry.day)
  return `اگر تا ${WEEKDAYS[weekday(draft.expiry.day)]} ${fa(d)} ${MONTHS[m - 1]} ساعت ${fa(draft.expiry.time)} وضعیتی ثبت نشود، وظیفه خودکار «${est?.title || '—'}» می‌شود${e ? ` و ${money(Math.abs(e))} تومان ${e > 0 ? 'به حقوق اضافه' : 'از حقوق کسر'} می‌شود` : ''}.`
})

function chipStyle(s) {
  const on = (draft.statusId || null) === (s ? s.id : null)
  const p = s ? PALETTE[s.color] : { c: '#475569', fg: '#fff' }
  return on ? { background: p.c, color: p.fg, borderColor: p.c } : {}
}
function submit() {
  if (!draft.title.trim()) { error.value = true; return }
  emit('save', JSON.parse(JSON.stringify(draft)))
}
</script>

<style scoped>
.st-overlay{position:fixed;inset:0;background:var(--st-overlay);z-index:2147483010;display:flex;align-items:center;justify-content:center;padding:20px;animation:stFade .2s}
.st-modal{width:560px;max-width:100%;max-height:calc(100vh - 40px);overflow-y:auto;background:var(--st-surface);border-radius:22px;box-shadow:0 30px 60px -15px rgba(15,23,42,.35);display:flex;flex-direction:column;animation:stIn .25s}
.st-mhead{padding:18px 22px;border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.col{display:flex;flex-direction:column;gap:2px}
.h{font-size:16px;font-weight:800}
.sub{font-size:12px;color:var(--st-muted);font-weight:600}
.t{font-size:14px;font-weight:800}
.st-x{border:0;background:var(--st-muted-bg);width:34px;height:34px;border-radius:10px;cursor:pointer;color:var(--st-muted);display:flex;align-items:center;justify-content:center}
.st-mbody{padding:18px 22px;display:flex;flex-direction:column;gap:16px}
.grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
.grid-exp{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:10px}
.fld{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;color:var(--st-text-3)}
.st-input{font-family:inherit;font-size:14px;border:1px solid var(--st-border);border-radius:11px;padding:9px 10px;background:var(--st-surface);color:var(--st-text);outline-color:var(--st-accent);resize:vertical}
.st-input.err{border-color:var(--st-red)}
.chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-2);border-radius:999px;padding:6px 14px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px}
.dot{width:8px;height:8px;border-radius:50%}
.note{font-size:12.5px;font-weight:600;background:var(--st-surface-2);border-radius:10px;padding:8px 12px}
.exp-box{border:1px solid var(--st-line);border-radius:16px;padding:14px;display:flex;flex-direction:column;gap:12px}
.exp-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
.switch{width:44px;height:26px;border-radius:999px;border:0;background:var(--st-strong-border);padding:3px;cursor:pointer;display:flex;justify-content:flex-end;flex-shrink:0}
.switch.on{background:var(--st-accent);justify-content:flex-start}
.switch span{width:20px;height:20px;border-radius:50%;background:var(--st-surface);box-shadow:0 1px 3px rgba(0,0,0,.2)}
.st-mfoot{padding:14px 22px;border-top:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.row{display:flex;gap:8px}
.btn{font-family:inherit;border-radius:11px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;border:0}
.btn.primary{background:var(--st-accent);color:#fff;padding:10px 22px}
.btn.primary:hover{background:var(--st-accent-hover)}
.btn.ghost{border:1px solid var(--st-border);background:var(--st-surface);color:var(--st-text-3)}
.btn.danger{background:var(--st-holiday);color:var(--st-red)}
.btn.danger:hover{background:var(--st-holiday-badge)}
@keyframes stIn{0%{opacity:0;transform:translateY(12px) scale(.98)}100%{opacity:1;transform:none}}
@keyframes stFade{0%{opacity:0}100%{opacity:1}}
@media (max-width: 560px){
  .st-overlay{padding:10px}
  .st-modal{max-height:calc(100vh - 20px)}
  .st-mhead,.st-mbody,.st-mfoot{padding-left:14px;padding-right:14px}
  .grid2,.grid-exp{grid-template-columns:minmax(0,1fr)}
}
</style>
