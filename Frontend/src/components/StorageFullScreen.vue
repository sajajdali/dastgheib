<template>
  <main class="storage-lock" dir="rtl">
    <div class="lock-glow lock-glow-one" aria-hidden="true"></div>
    <div class="lock-glow lock-glow-two" aria-hidden="true"></div>
    <section class="lock-shell">
      <header class="lock-topbar">
        <div class="lock-brand"><span class="lock-brand-icon">✦</span><span>مدیریت فضای کلینیک</span></div>
        <button class="lock-logout" type="button" @click="$emit('logout')">خروج از حساب</button>
      </header>

      <div class="lock-hero">
        <div class="lock-symbol" aria-hidden="true"><span>!</span></div>
        <span class="lock-eyebrow">ظرفیت تکمیل شده</span>
        <h1>فضای ذخیره‌سازی سایت پر شده است</h1>
        <p>برای ادامهٔ کار با سامانه، فضای بیشتری به سایت اضافه کنید. اطلاعات فعلی شما محفوظ است و پس از فعال شدن بسته، دسترسی به‌صورت خودکار برقرار می‌شود.</p>
        <div class="lock-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" aria-label="فضای ذخیره‌سازی تکمیل شده">
          <span></span>
        </div>
        <div class="lock-meter-labels"><strong>{{ percent }}٪ مصرف‌شده</strong><span>{{ formatSize(storage?.used_bytes) }} از {{ formatSize(storage?.quota_bytes) }}</span></div>
      </div>

      <section v-if="manager" class="lock-buy" aria-labelledby="lock-buy-title">
        <div class="lock-buy-heading">
          <div><span class="lock-step">گام بعدی</span><h2 id="lock-buy-title">افزایش فضای ذخیره‌سازی</h2><p>بستهٔ مناسب را انتخاب و درخواست خود را ثبت کنید. فضای جدید پس از تأیید پرداخت توسط مدیریت فعال می‌شود.</p></div>
          <button type="button" class="lock-refresh" :disabled="loading" @click="load">{{ loading ? 'در حال بررسی...' : 'بررسی مجدد وضعیت' }}</button>
        </div>
        <p v-if="error" class="lock-error" role="alert">{{ error }}</p>
        <div v-if="packages.length" class="lock-packages">
          <article v-for="item in packages" :key="item.id" class="lock-package">
            <span class="package-kicker">فضای اضافه</span>
            <strong>{{ Number(item.size_gb).toLocaleString('fa-IR') }} گیگابایت</strong>
            <h3>{{ item.name }}</h3>
            <p><b>{{ Number(item.monthly_price).toLocaleString('fa-IR') }}</b> تومان / ماه</p>
            <button type="button" :disabled="ordering || hasPendingOrder" @click="order(item)">{{ ordering ? 'در حال ثبت...' : hasPendingOrder ? 'درخواست در انتظار تأیید' : 'درخواست خرید بسته' }} <span aria-hidden="true">←</span></button>
          </article>
        </div>
        <p v-else-if="!loading" class="lock-empty">هنوز بسته‌ای برای افزایش فضا تعریف نشده است. با مدیریت سامانه تماس بگیرید.</p>
        <div v-if="hasPendingOrder" class="lock-pending" role="status"><span>◷</span><div><strong>درخواست شما ثبت شده و در انتظار تأیید است.</strong><p>بعد از فعال شدن بسته، این صفحه به‌طور خودکار بسته می‌شود.</p></div></div>
      </section>

      <section v-else class="lock-staff"><strong>برای ادامه با مدیر کلینیک هماهنگ کنید.</strong><p>مدیر کلینیک می‌تواند از همین صفحه درخواست افزایش فضا ثبت کند.</p><button type="button" :disabled="loading" @click="load">{{ loading ? 'در حال بررسی...' : 'بررسی مجدد وضعیت' }}</button></section>
      <footer class="lock-footer">وضعیت فضا هر ۳۰ ثانیه بررسی می‌شود.</footer>
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({ manager: Boolean, initialStorage: { type: Object, default: null } });
const emit = defineEmits(['available', 'logout']);
const storage = ref(props.initialStorage);
const packages = ref([]);
const orders = ref([]);
const loading = ref(false);
const ordering = ref(false);
const error = ref('');
const percent = computed(() => Number(storage.value?.percentage || 100).toLocaleString('fa-IR'));
const hasPendingOrder = computed(() => orders.value.some(item => item.status === 'pending'));
let timer;

function formatSize(bytes) {
  const value = Number(bytes || 0);
  return value >= 1073741824
    ? `${(value / 1073741824).toLocaleString('fa-IR', { maximumFractionDigits: 2 })} گیگابایت`
    : `${(value / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 })} مگابایت`;
}

async function load() {
  if (loading.value) return;
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(props.manager ? '/api/storage' : '/api/storage/status');
    storage.value = data.storage;
    packages.value = data.packages || [];
    orders.value = data.orders || [];
    if (Number(data.storage?.used_bytes) < Number(data.storage?.quota_bytes)) emit('available');
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'بررسی وضعیت فضا انجام نشد. دوباره تلاش کنید.';
  } finally {
    loading.value = false;
  }
}

async function order(item) {
  if (ordering.value || hasPendingOrder.value) return;
  if (!window.confirm(`درخواست بستهٔ ${item.name} با هزینهٔ ماهانهٔ ${Number(item.monthly_price).toLocaleString('fa-IR')} تومان ثبت شود؟`)) return;
  ordering.value = true;
  error.value = '';
  try {
    await axios.post('/api/storage/orders', { package_id: item.id });
    await load();
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'ثبت درخواست انجام نشد. دوباره تلاش کنید.';
  } finally {
    ordering.value = false;
  }
}

onMounted(() => { load(); timer = window.setInterval(load, 30000); window.addEventListener('focus', load); });
onUnmounted(() => { window.clearInterval(timer); window.removeEventListener('focus', load); });
</script>

<style scoped>
.storage-lock{position:relative;isolation:isolate;min-height:100vh;padding:28px 20px 50px;overflow:hidden;background:radial-gradient(circle at 50% -10%,#2f4b68 0,#101c33 47%,#0b1426 100%);color:#f8fafc;font-family:inherit}.lock-glow{position:absolute;z-index:-1;border-radius:50%;filter:blur(75px);opacity:.35;pointer-events:none}.lock-glow-one{width:360px;height:360px;top:80px;right:-150px;background:#e11d48}.lock-glow-two{width:300px;height:300px;bottom:-130px;left:-120px;background:#2563eb}.lock-shell{width:min(100%,960px);margin:auto}.lock-topbar{display:flex;justify-content:space-between;align-items:center;gap:16px}.lock-brand{display:flex;align-items:center;gap:10px;color:#e2e8f0;font-size:13px;font-weight:900}.lock-brand-icon{display:grid;place-items:center;width:36px;height:36px;border:1px solid #ffffff26;border-radius:11px;background:#ffffff14;color:#fda4af;font-size:19px}.lock-logout,.lock-refresh,.lock-staff button{border:1px solid #ffffff36;border-radius:11px;background:#ffffff12;color:#f8fafc;padding:10px 16px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}.lock-logout:hover,.lock-refresh:hover,.lock-staff button:hover{background:#ffffff22}.lock-hero{text-align:center;max-width:730px;margin:62px auto 44px}.lock-symbol{display:grid;place-items:center;width:94px;height:94px;margin:0 auto 22px;border:1px solid #fecdd36b;border-radius:27px;background:linear-gradient(145deg,#fda4af42,#9f123947);box-shadow:0 17px 42px #fb718527;transform:rotate(-8deg)}.lock-symbol span{font-size:52px;font-weight:900;color:#fff;transform:rotate(8deg)}.lock-eyebrow,.lock-step{color:#fda4af;font-size:12px;font-weight:900;letter-spacing:.02em}.lock-hero h1{margin:11px 0 12px;font-size:clamp(27px,4vw,42px);line-height:1.5}.lock-hero>p{max-width:620px;margin:0 auto;color:#cbd5e1;font-size:14px;line-height:2}.lock-meter{height:12px;margin:30px auto 10px;border-radius:20px;background:#ffffff22;overflow:hidden}.lock-meter span{display:block;width:100%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#fb7185,#be123c);box-shadow:0 0 20px #fb7185}.lock-meter-labels{display:flex;justify-content:space-between;gap:15px;color:#cbd5e1;font-size:12px}.lock-meter-labels strong{color:#fecdd3}.lock-buy,.lock-staff{padding:30px;border:1px solid #ffffff25;border-radius:25px;background:#ffffff10;box-shadow:0 20px 60px #0004;backdrop-filter:blur(16px)}.lock-buy-heading{display:flex;align-items:center;justify-content:space-between;gap:20px}.lock-buy-heading h2{margin:7px 0;font-size:22px}.lock-buy-heading p,.lock-staff p{margin:0;color:#cbd5e1;font-size:12px;line-height:1.9}.lock-refresh{flex:none}.lock-packages{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:15px;margin-top:26px}.lock-package{display:flex;flex-direction:column;align-items:flex-start;padding:22px;border:1px solid #ffffff28;border-radius:18px;background:#f8fafc;color:#172033}.package-kicker{color:#be123c;font-size:11px;font-weight:900}.lock-package>strong{margin-top:9px;font-size:29px}.lock-package h3{margin:4px 0 13px;color:#475569;font-size:13px}.lock-package p{margin:0 0 20px;color:#64748b;font-size:12px}.lock-package p b{color:#172033;font-size:18px}.lock-package button{width:100%;margin-top:auto;padding:12px 15px;border:0;border-radius:11px;background:#be123c;color:#fff;font:inherit;font-size:12px;font-weight:900;cursor:pointer}.lock-package button:hover:not(:disabled){background:#9f1239}.lock-package button:disabled{opacity:.55;cursor:not-allowed}.lock-pending{display:flex;gap:12px;align-items:center;margin-top:18px;padding:14px 17px;border:1px solid #fbbf2466;border-radius:14px;background:#f59e0b1f}.lock-pending>span{font-size:25px;color:#fcd34d}.lock-pending strong,.lock-staff strong{font-size:14px}.lock-pending p{margin:4px 0 0;color:#fde68a;font-size:11px}.lock-error,.lock-empty{margin:20px 0 0;padding:13px 15px;border-radius:12px;background:#fef2f2;color:#991b1b;font-size:12px}.lock-empty{background:#ffffff14;color:#e2e8f0}.lock-staff{text-align:center}.lock-staff p{margin:8px 0 20px}.lock-footer{text-align:center;margin-top:24px;color:#94a3b8;font-size:11px}@media(max-width:650px){.storage-lock{padding:18px 14px 35px}.lock-hero{margin:52px auto 34px}.lock-buy,.lock-staff{padding:20px}.lock-buy-heading{align-items:flex-start;flex-direction:column}.lock-refresh{width:100%}.lock-meter-labels{font-size:10px}}
</style>
