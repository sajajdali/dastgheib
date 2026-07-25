<template>
  <section class="commission-card">
    <header><strong>تنظیمات پورسانت</strong><small>ذخیره خودکار</small></header>
    <div class="pay-rates-grid">
      <label><span>ساعت کاری</span><div><input :value="money(model.hourly_rate)" inputmode="numeric" @input="setModelMoney('hourly_rate',$event)"><b>تومان</b></div></label>
      <label><span>مبلغ اضافه‌کاری</span><div><input :value="money(model.overtime_hourly_rate)" inputmode="numeric" @input="setModelMoney('overtime_hourly_rate',$event)"><b>تومان</b></div></label>
      <label><span>مبلغ کسرکاری</span><div><input :value="money(model.shortage_hourly_deduction)" inputmode="numeric" @input="setModelMoney('shortage_hourly_deduction',$event)"><b>تومان</b></div></label>
      <label><span>کسر حقوق در صورت عدم حضور</span><div><input :value="money(model.absence_deduction)" inputmode="numeric" @input="setModelMoney('absence_deduction',$event)"><b>تومان</b></div></label>
      <label><span>میزان مجاز کسرکاری</span><div><input v-model.number="model.allowed_shortage_hours" type="number" min="0" step="0.25"><b>ساعت</b></div></label>
    </div>
    <div class="commission-grid">
      <label>پورسانت برای
        <select v-model="model.commission_customer_scope">
          <option value="new">فقط مشتری جدید</option><option value="existing">فقط مشتری قدیمی</option><option value="both">مشتری جدید و قدیمی</option>
        </select>
      </label>
      <label class="check-card"><input v-model="model.commission_after_materials" type="checkbox"><span><b>پس از کسر مواد مصرفی</b><small>پورسانت از مبلغ خالص محاسبه شود</small></span></label>
    </div>
    <label class="sales-switch"><input v-model="model.sales_bonus_enabled" type="checkbox"><span><b>افزایش حقوق در صورت فروش</b><small>تعریف پله‌های تشویقی فروش</small></span></label>
    <div v-if="model.sales_bonus_enabled" class="tier-list">
      <div v-for="(tier,index) in model.sales_bonus_tiers" :key="index" class="tier-row">
        <span>اگر بیشتر از</span><input :value="money(tier.sales_from)" inputmode="numeric" @input="setMoney(tier,'sales_from',$event)"><span>تومان فروخت،</span>
        <input :value="money(tier.salary_addition)" inputmode="numeric" @input="setMoney(tier,'salary_addition',$event)"><span>تومان به حقوق اضافه شود</span>
        <button type="button" @click="removeTier(index)">×</button>
      </div>
      <button type="button" class="add-tier" @click="addTier">+ افزودن پله فروش</button>
    </div>
  </section>
</template>

<script setup>
const model = defineModel({ type: Object, required: true })
const money = value => value ? Number(value).toLocaleString('en-US') : ''
const setMoney = (tier,key,event) => { tier[key] = Number(String(event.target.value).replace(/\D/g,'')) || 0 }
const setModelMoney = (key,event) => { model.value[key] = Number(String(event.target.value).replace(/\D/g,'')) || 0 }
const addTier = () => model.value.sales_bonus_tiers.push({ sales_from: 0, salary_addition: 0 })
const removeTier = index => model.value.sales_bonus_tiers.splice(index,1)
</script>

<style scoped>
.pay-rates-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:9px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #dbeafe}.pay-rates-grid label{display:grid;gap:5px;color:#334155;font-size:11px;font-weight:900}.pay-rates-grid label>div{display:flex;align-items:center;overflow:hidden;border:1px solid #bfdbfe;border-radius:10px;background:#fff}.pay-rates-grid input{width:100%;height:38px;padding:0 10px;border:0!important;outline:0;background:transparent;text-align:right}.pay-rates-grid b{padding:0 9px;color:#64748b;font-size:10px;white-space:nowrap}
.commission-card{min-width:560px;margin-top:10px;padding:14px;border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(135deg,#f8fbff,#f0fdf4);box-shadow:0 8px 24px rgba(15,23,42,.06);text-align:right}.commission-card header{display:flex;justify-content:space-between;margin-bottom:11px;color:#1e3a8a}.commission-card header small{color:#16a34a}.commission-grid{display:grid;grid-template-columns:1fr 1.4fr;gap:10px}.commission-grid>label{display:grid;gap:5px;font-size:11px;font-weight:900}.commission-grid select{height:38px;border:1px solid #bfdbfe;border-radius:10px;background:#fff;font-family:inherit}.check-card,.sales-switch{display:flex!important;align-items:center;gap:9px;padding:9px;border:1px solid #dbeafe;border-radius:11px;background:#fff}.check-card input,.sales-switch input{width:18px;height:18px;accent-color:#2563eb}.check-card span,.sales-switch span{display:grid}.check-card small,.sales-switch small{color:#64748b;font-weight:500}.sales-switch{margin-top:10px}.tier-list{display:grid;gap:8px;margin-top:10px}.tier-row{display:flex;align-items:center;gap:6px;padding:8px;border-radius:10px;background:#fff;color:#475569;font-size:11px}.tier-row input{width:115px;height:34px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px}.tier-row button{width:29px;height:29px;border:0;border-radius:8px;background:#fee2e2;color:#dc2626;font-size:20px;cursor:pointer}.add-tier{justify-self:start;padding:8px 13px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900;cursor:pointer}@media(max-width:800px){.commission-card{min-width:480px}.commission-grid{grid-template-columns:1fr}.tier-row{flex-wrap:wrap}}
</style>
