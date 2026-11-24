<template>
  <div class="st-overlay" @click.self="close">
    <div class="st-modal">
      <div class="head">
        <div class="col">
          <span class="h">وضعیت‌ها</span>
          <span class="sub">رنگ، اثر حقوق، انقضای پیش‌فرض (اختیاری)</span>
        </div>
        <button class="st-x" type="button" @click="close"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>

      <div class="body">
        <div v-for="s in local" :key="s.id" class="card">
          <div class="row">
            <span class="sq" :style="{ background: PALETTE[s.color].c }"></span>
            <input v-model="s.title" class="title-in" placeholder="عنوان" @change="persist(s)" />
            <button class="del" type="button" title="حذف" @click="onRemove(s.id)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>

          <div class="sw">
            <button v-for="(p, i) in PALETTE" :key="i" type="button" class="swatch" :style="{ background: p.c, boxShadow: '0 0 0 2px ' + (i === s.color ? 'var(--st-text)' : 'transparent') }" @click="s.color = i; persist(s)"></button>
          </div>

          <div class="seg">
            <button v-for="e in EFFECTS" :key="e.key" type="button" :class="{ on: s.effect === e.key }" @click="s.effect = e.key; persist(s)">{{ e.label }}</button>
          </div>
          <div v-if="s.effect !== 'none'" class="amount">
            <span class="sign" :style="{ color: s.effect === 'plus' ? 'var(--st-pos)' : 'var(--st-neg)' }">{{ s.effect === 'plus' ? '+' : '−' }}</span>
            <input dir="ltr" inputmode="numeric" class="amount-in" :value="s.amount ? money(s.amount) : ''" placeholder="۰" @change="s.amount = +toEnDigits($event.target.value) || 0; persist(s)" />
            <span class="unit">تومان</span>
          </div>

          <button type="button" class="exp" :class="{ on: expiryDefault === s.id }" @click="toggleExpiry(s.id)">
            <span class="radio"><span></span></span>
            <span class="exp-t">انقضای پیش‌فرض</span>
          </button>
        </div>

        <button class="add" type="button" @click="onAdd">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          وضعیت جدید
        </button>
      </div>

      <div class="foot">
        <button class="btn primary" type="button" @click="close">تأیید</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { useStaffTasks, PALETTE } from './useStaffTasks'
import { money, toEnDigits } from './jalali'

const emit = defineEmits(['close'])
const { state, addStatus, updateStatus, removeStatus, saveSettings } = useStaffTasks()
const EFFECTS = [{ key: 'plus', label: 'پاداش' }, { key: 'minus', label: 'کسر' }, { key: 'none', label: 'بدون اثر' }]
const local = reactive([])
const expiryDefault = ref(state.expiryDefault)

watch(() => state.statuses, (list) => {
  local.splice(0, local.length, ...list.map(s => ({ ...s })))
}, { immediate: true, deep: true })

async function persist(s) {
  await updateStatus(s)
}
async function onAdd() {
  await addStatus()
}
async function onRemove(id) {
  await removeStatus(id)
}
async function toggleExpiry(id) {
  expiryDefault.value = expiryDefault.value === id ? null : id
  await saveSettings(expiryDefault.value)
}
const close = () => emit('close')
</script>

<style scoped>
.st-overlay{position:fixed;inset:0;background:var(--st-overlay);z-index:2147483010;display:flex;align-items:center;justify-content:center;padding:20px}
.st-modal{width:820px;max-width:100%;max-height:calc(100vh - 40px);background:var(--st-surface-2);border-radius:18px;box-shadow:0 30px 60px -15px rgba(15,23,42,.35);display:flex;flex-direction:column;overflow:hidden}
.head{padding:14px 18px;background:var(--st-surface);border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.col{display:flex;flex-direction:column;gap:2px}
.h{font-size:15px;font-weight:800}
.sub{font-size:11px;color:var(--st-muted)}
.st-x{border:0;background:var(--st-muted-bg);width:32px;height:32px;border-radius:9px;cursor:pointer;color:var(--st-muted);display:flex;align-items:center;justify-content:center}
.body{flex:1;overflow-y:auto;padding:14px 18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(min(300px,100%),1fr));gap:10px;align-content:start}
.card{background:var(--st-surface);border-radius:14px;border:1px solid var(--st-border);padding:12px;display:flex;flex-direction:column;gap:10px}
.row{display:flex;align-items:center;gap:8px}
.sq{width:12px;height:12px;border-radius:4px;flex-shrink:0}
.title-in{font-family:inherit;flex:1;min-width:0;font-size:14px;font-weight:800;border:1px solid transparent;border-radius:8px;padding:5px 7px;color:var(--st-text);outline-color:var(--st-accent);background:var(--st-surface-2)}
.del{border:0;background:var(--st-holiday);color:var(--st-red);width:30px;height:30px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sw{display:flex;gap:6px;flex-wrap:wrap}
.swatch{width:24px;height:24px;border-radius:50%;border:2px solid var(--st-surface);cursor:pointer;padding:0}
.seg{display:flex;background:var(--st-muted-bg);border-radius:9px;padding:2px;gap:2px}
.seg button{font-family:inherit;flex:1;border:0;border-radius:7px;padding:6px 4px;font-size:11.5px;font-weight:700;cursor:pointer;background:transparent;color:var(--st-muted);white-space:nowrap}
.seg button.on{background:var(--st-surface);color:var(--st-text);box-shadow:0 1px 3px rgba(15,23,42,.12)}
.amount{display:flex;align-items:center;gap:6px}
.sign{font-size:16px;font-weight:800;width:14px;text-align:center}
.amount-in{font-family:inherit;flex:1;min-width:0;font-size:13px;font-weight:700;border:1px solid var(--st-border);border-radius:9px;padding:7px 10px;color:var(--st-text);outline-color:var(--st-accent);text-align:right}
.unit{font-size:12px;font-weight:600;color:var(--st-muted)}
.exp{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface);border-radius:10px;padding:7px 10px;cursor:pointer;display:flex;align-items:center;gap:8px;text-align:right}
.exp.on{border-color:var(--st-accent);background:var(--st-accent-soft)}
.radio{width:16px;height:16px;border-radius:50%;border:2px solid var(--st-strong-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-sizing:border-box}
.radio span{width:7px;height:7px;border-radius:50%}
.exp.on .radio{border-color:var(--st-accent)}
.exp.on .radio span{background:var(--st-accent)}
.exp-t{font-size:12px;font-weight:700;color:var(--st-text)}
.add{font-family:inherit;border:2px dashed var(--st-strong-border);background:transparent;border-radius:14px;min-height:90px;color:var(--st-text-3);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px}
.add:hover{border-color:var(--st-accent);color:var(--st-accent)}
.foot{padding:12px 18px;background:var(--st-surface);border-top:1px solid var(--st-line);display:flex;justify-content:flex-end}
.btn{font-family:inherit;border:0;border-radius:10px;padding:9px 22px;font-size:13px;font-weight:700;cursor:pointer}
.btn.primary{background:var(--st-accent);color:#fff}
</style>
