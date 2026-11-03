<template>
  <div class="st-overlay" @click.self="close">
    <div class="st-modal">
      <div class="head">
        <div class="col">
          <span class="h">تعریف وضعیت وظایف</span>
          <span class="sub">عنوان، رنگ، تأثیر روی حقوق و وضعیت پیش‌فرض انقضا</span>
        </div>
        <button class="st-x" @click="close"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>

      <div class="body">
        <div v-for="s in state.statuses" :key="s.id" class="card">
          <div class="row">
            <span class="sq" :style="{ background: PALETTE[s.color].c }"></span>
            <input v-model="s.title" class="title-in" placeholder="عنوان وضعیت" />
            <button v-if="!s.builtin" class="del" title="حذف وضعیت" @click="removeStatus(s.id)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>

          <div class="sec">
            <span class="lbl">رنگ</span>
            <div class="sw">
              <button v-for="(p, i) in PALETTE" :key="i" class="swatch" :style="{ background: p.c, boxShadow: '0 0 0 2px ' + (i === s.color ? 'var(--st-text)' : 'transparent') }" @click="s.color = i"></button>
            </div>
          </div>

          <div class="sec">
            <span class="lbl">تأثیر روی حقوق</span>
            <div class="seg">
              <button v-for="e in EFFECTS" :key="e.key" :class="{ on: s.effect === e.key }" @click="s.effect = e.key">{{ e.label }}</button>
            </div>
            <div v-if="s.effect !== 'none'" class="amount">
              <span class="sign" :style="{ color: s.effect === 'plus' ? 'var(--st-pos)' : 'var(--st-neg)' }">{{ s.effect === 'plus' ? '+' : '−' }}</span>
              <input dir="ltr" inputmode="numeric" class="amount-in" :value="s.amount ? money(s.amount) : ''" placeholder="۰" @input="s.amount = +toEnDigits($event.target.value) || 0" />
              <span class="unit">تومان</span>
            </div>
          </div>

          <button class="exp" :class="{ on: state.expiryDefault === s.id }" @click="state.expiryDefault = s.id">
            <span class="radio"><span></span></span>
            <span class="col">
              <span class="exp-t">وضعیت انقضا</span>
              <span class="exp-s">وظایف منقضی‌شده بدون وضعیت، خودکار به این وضعیت منتقل شوند</span>
            </span>
          </button>
        </div>

        <button class="add" @click="addStatus">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          افزودن وضعیت جدید
        </button>
      </div>

      <div class="foot">
        <button class="btn primary" @click="close">تأیید</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useStaffTasks, PALETTE } from './useStaffTasks'
import { money, toEnDigits } from './jalali'

const emit = defineEmits(['close'])
const { state, addStatus, removeStatus } = useStaffTasks()
const EFFECTS = [{ key: 'plus', label: 'افزایش حقوق' }, { key: 'minus', label: 'کسر از حقوق' }, { key: 'none', label: 'بدون تأثیر' }]

// نسخهٔ استاتیک: تغییرات وضعیت‌ها فقط در حافظهٔ صفحه می‌ماند.
const close = () => emit('close')
</script>

<style scoped>
.st-overlay{position:fixed;inset:0;background:var(--st-overlay);z-index:2147483010;display:flex;align-items:center;justify-content:center;padding:20px}
.st-modal{width:860px;max-width:100%;max-height:calc(100vh - 40px);background:var(--st-surface-2);border-radius:22px;box-shadow:0 30px 60px -15px rgba(15,23,42,.35);display:flex;flex-direction:column;overflow:hidden}
.head{padding:18px 22px;background:var(--st-surface);border-bottom:1px solid var(--st-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
.col{display:flex;flex-direction:column;gap:2px}
.h{font-size:17px;font-weight:800}
.sub{font-size:12px;color:var(--st-muted)}
.st-x{border:0;background:var(--st-muted-bg);width:34px;height:34px;border-radius:10px;cursor:pointer;color:var(--st-muted);display:flex;align-items:center;justify-content:center}
.body{flex:1;overflow-y:auto;padding:18px 22px;display:grid;grid-template-columns:repeat(auto-fill,minmax(min(360px,100%),1fr));gap:12px;align-content:start}
.card{background:var(--st-surface);border-radius:18px;border:1px solid var(--st-border);padding:16px;display:flex;flex-direction:column;gap:14px}
.row{display:flex;align-items:center;gap:10px}
.sq{width:14px;height:14px;border-radius:5px;flex-shrink:0}
.title-in{font-family:inherit;flex:1;min-width:0;font-size:15px;font-weight:800;border:1px solid transparent;border-radius:10px;padding:6px 8px;color:var(--st-text);outline-color:var(--st-accent);background:var(--st-surface-2)}
.del{border:0;background:var(--st-holiday);color:var(--st-red);width:32px;height:32px;border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sec{display:flex;flex-direction:column;gap:8px}
.lbl{font-size:12px;font-weight:700;color:var(--st-muted)}
.sw{display:flex;gap:8px;flex-wrap:wrap}
.swatch{width:28px;height:28px;border-radius:50%;border:3px solid var(--st-surface);cursor:pointer;padding:0}
.seg{display:flex;background:var(--st-muted-bg);border-radius:11px;padding:3px;gap:2px}
.seg button{font-family:inherit;flex:1;border:0;border-radius:9px;padding:7px 6px;font-size:12.5px;font-weight:700;cursor:pointer;background:transparent;color:var(--st-muted);white-space:nowrap}
.seg button.on{background:var(--st-surface);color:var(--st-text);box-shadow:0 1px 3px rgba(15,23,42,.12)}
.amount{display:flex;align-items:center;gap:8px}
.sign{font-size:18px;font-weight:800;width:16px;text-align:center}
.amount-in{font-family:inherit;flex:1;min-width:0;font-size:14px;font-weight:700;border:1px solid var(--st-border);border-radius:11px;padding:9px 12px;color:var(--st-text);outline-color:var(--st-accent);text-align:right}
.unit{font-size:13px;font-weight:600;color:var(--st-muted)}
.exp{font-family:inherit;border:1px solid var(--st-border);background:var(--st-surface);border-radius:12px;padding:9px 12px;cursor:pointer;display:flex;align-items:center;gap:10px;text-align:right}
.exp.on{border-color:var(--st-accent);background:var(--st-accent-soft)}
.radio{width:18px;height:18px;border-radius:50%;border:2px solid var(--st-strong-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-sizing:border-box}
.radio span{width:8px;height:8px;border-radius:50%}
.exp.on .radio{border-color:var(--st-accent)}
.exp.on .radio span{background:var(--st-accent)}
.exp-t{font-size:13px;font-weight:700;color:var(--st-text)}
.exp-s{font-size:11.5px;color:var(--st-muted)}
.add{font-family:inherit;border:2px dashed var(--st-strong-border);background:transparent;border-radius:18px;min-height:120px;color:var(--st-text-3);font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
.add:hover{border-color:var(--st-accent);color:var(--st-accent)}
.foot{padding:14px 22px;background:var(--st-surface);border-top:1px solid var(--st-line);display:flex;justify-content:flex-end}
.btn{font-family:inherit;border:0;border-radius:11px;padding:10px 26px;font-size:13px;font-weight:700;cursor:pointer}
.btn.primary{background:var(--st-accent);color:#fff}
.btn.primary:hover{background:var(--st-accent-hover)}
@media (max-width: 560px){
  .st-overlay{padding:10px}
  .st-modal{max-height:calc(100vh - 20px)}
  .head,.body,.foot{padding-left:14px;padding-right:14px}
}
</style>
