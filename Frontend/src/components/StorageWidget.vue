<template>
  <section v-if="storage && percentage >= 80" class="storage-alert" :class="tone" dir="rtl" role="alert">
    <span class="storage-alert-icon" aria-hidden="true">!</span>
    <div class="storage-alert-content">
      <strong>{{ title }}</strong>
      <p>{{ description }}</p>
    </div>
    <button type="button" @click="$emit('open')">مشاهده فضا <span aria-hidden="true">←</span></button>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

defineEmits(['open']);
const storage = ref(null);
const percentage = computed(() => Number(storage.value?.percentage || 0));
const tone = computed(() => percentage.value >= 100 ? 'full' : percentage.value >= 90 ? 'critical' : 'warning');
const title = computed(() => percentage.value >= 100 ? 'فضای ذخیره‌سازی پر شده است' : percentage.value >= 90 ? 'فضای ذخیره‌سازی رو به اتمام است' : 'فضای ذخیره‌سازی در آستانه پر شدن است');
const description = computed(() => percentage.value >= 100
  ? 'دسترسی به سامانه تا فعال شدن فضای بیشتر متوقف شده است. درخواست افزایش فضا ثبت کنید.'
  : `${percentage.value.toLocaleString('fa-IR')}٪ از فضای فایل‌ها استفاده شده است. برای جلوگیری از توقف آپلود، وضعیت فضا را بررسی کنید.`);

let timer;
async function load() {
  try {
    const { data } = await axios.get('/api/storage');
    storage.value = data.storage || null;
  } catch {
    storage.value = null;
  }
}
onMounted(() => {
  load();
  timer = setInterval(load, 60000);
  window.addEventListener('focus', load);
});
onUnmounted(() => {
  clearInterval(timer);
  window.removeEventListener('focus', load);
});
</script>

<style scoped>
.storage-alert{max-width:1180px;margin:0 auto 18px;padding:14px 18px;display:flex;align-items:center;gap:14px;border:1px solid #f7d697;border-radius:16px;background:#fffaf0;color:#84551a;box-shadow:0 8px 22px #8a5b1010}
.storage-alert-icon{width:34px;height:34px;flex:none;display:grid;place-items:center;border-radius:10px;background:#fef0c7;color:#a66208;font-size:20px;font-weight:900}
.storage-alert-content{flex:1;min-width:0}.storage-alert strong{font-size:13px}.storage-alert p{margin:4px 0 0;font-size:11px;line-height:1.8;color:#926a36}
.storage-alert button{flex:none;padding:9px 13px;border:0;border-radius:9px;background:#a36510;color:#fff;font:inherit;font-size:11px;font-weight:800;cursor:pointer}
.storage-alert button span{margin-right:4px}
.storage-alert.critical{border-color:#fecdd3;background:#fff1f2;color:#9f1239;box-shadow:0 8px 22px #be123c17}.storage-alert.critical .storage-alert-icon{background:#ffe4e6;color:#be123c}.storage-alert.critical p{color:#be123c}.storage-alert.critical button{background:#e11d48}
.storage-alert.full{border-color:#7f1d1d;background:#991b1b;color:#fff;box-shadow:0 10px 28px #7f1d1d45}.storage-alert.full .storage-alert-icon{background:#ffffff24;color:#fff}.storage-alert.full p{color:#fee2e2}.storage-alert.full button{background:#fff;color:#991b1b}
@media(max-width:600px){.storage-alert{margin:0 12px 16px;padding:12px;flex-wrap:wrap}.storage-alert-content{flex-basis:calc(100% - 50px)}.storage-alert button{margin-right:48px}}
</style>
