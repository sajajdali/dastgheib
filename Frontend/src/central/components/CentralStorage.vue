<template>
  <div class="central-view central-stack">
    <section class="central-panel central-stack">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">بسته‌های فضای ذخیره‌سازی</div>
          <div class="central-section-subtitle">قیمت ماهانه و حجم هر بسته را تعیین کنید. فضای پایهٔ هر سایت جداگانه در «مدیریت سایت‌ها» قابل تغییر است.</div>
        </div>
      </div>
      <form class="central-card central-form-panel storage-package-form" @submit.prevent="save">
        <label class="central-form-label">نام بسته<input v-model.trim="form.name" class="central-input" required placeholder="مثلاً ۲ گیگابایت اضافه" /></label>
        <label class="central-form-label">حجم بسته (گیگابایت)<input v-model.number="form.size_gb" class="central-input" type="number" min="1" required /></label>
        <label class="central-form-label">قیمت هر ماه (تومان)<input v-model.number="form.monthly_price" class="central-input" type="number" min="0" required /></label>
        <label class="central-check"><input v-model="form.is_active" type="checkbox" /> فعال</label>
        <div class="central-actions"><button class="central-button" :disabled="saving">{{ editId ? 'ذخیره تغییرات' : 'افزودن بسته' }}</button><button v-if="editId" class="central-button secondary" type="button" @click="reset">انصراف</button></div>
      </form>
      <div class="storage-package-grid">
        <article v-for="item in packages" :key="item.id" class="central-card">
          <strong>{{ item.name }}</strong>
          <div>{{ Number(item.size_gb).toLocaleString('fa-IR') }} گیگابایت · {{ Number(item.monthly_price).toLocaleString('fa-IR') }} تومان / ماه</div>
          <span>{{ item.is_active ? 'فعال' : 'غیرفعال' }}</span>
          <button class="central-button secondary compact" type="button" @click="edit(item)">ویرایش</button>
        </article>
      </div>
    </section>
    <section class="central-panel central-stack">
      <div class="central-section-title">درخواست‌های خرید فضا</div>
      <div v-if="!orders.length" class="central-empty">درخواستی ثبت نشده است.</div>
      <div v-for="order in orders" :key="order.id" class="central-card storage-order-row">
        <div><strong>{{ order.tenant_id }}</strong><small>{{ order.package_name }}</small></div>
        <div>{{ formatBytes(order.size_bytes) }} · {{ Number(order.monthly_price).toLocaleString('fa-IR') }} تومان / ماه</div>
        <div>{{ statusLabel(order) }}<small v-if="order.expires_at">تا {{ order.expires_at }}</small></div>
        <div class="central-actions">
          <button v-if="order.status === 'pending'" class="central-button compact" type="button" :disabled="saving" @click="$emit('decide-order', { order, status: 'active' })">تأیید پرداخت و فعال‌سازی</button>
          <button v-if="order.status === 'pending'" class="central-button danger compact" type="button" :disabled="saving" @click="$emit('decide-order', { order, status: 'rejected' })">رد</button>
          <button v-if="order.status === 'active'" class="central-button secondary compact" type="button" :disabled="saving" @click="$emit('renew-order', order)">تمدید یک ماه</button>
        </div>
      </div>
    </section>
    <p v-if="error" class="central-error">{{ error }}</p>
    <p v-if="message" class="central-message">{{ message }}</p>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
defineProps({ packages: { type: Array, default: () => [] }, orders: { type: Array, default: () => [] }, saving: Boolean, error: String, message: String });
const emit = defineEmits(['save-package', 'decide-order', 'renew-order']);
const editId = ref(null);
const form = reactive({ name: '', size_gb: 2, monthly_price: 0, is_active: true });
function reset() { editId.value = null; Object.assign(form, { name: '', size_gb: 2, monthly_price: 0, is_active: true }); }
function edit(item) { editId.value = item.id; Object.assign(form, { name: item.name, size_gb: Number(item.size_gb), monthly_price: Number(item.monthly_price), is_active: Boolean(item.is_active) }); }
function save() { emit('save-package', { id: editId.value, payload: { ...form } }); reset(); }
function formatBytes(value) { return `${(Number(value || 0) / 1073741824).toLocaleString('fa-IR', { maximumFractionDigits: 2 })} گیگابایت`; }
function statusLabel(order) { return order.status === 'pending' ? 'در انتظار تأیید' : order.status === 'rejected' ? 'رد شده' : new Date(order.expires_at) > new Date() ? 'فعال' : 'منقضی شده'; }
</script>

<style scoped>
.storage-package-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}.storage-package-form .central-actions{grid-column:1/-1}.storage-package-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.storage-package-grid article{display:grid;gap:8px}.storage-order-row{display:grid;grid-template-columns:1fr 1.2fr 1fr 1.5fr;align-items:center;gap:12px}.storage-order-row small{display:block;color:#64748b}@media(max-width:800px){.storage-package-form,.storage-order-row{grid-template-columns:1fr}}
</style>
