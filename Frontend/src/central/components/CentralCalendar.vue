<template>
  <section class="calendar-admin" dir="rtl">
    <div class="calendar-toolbar">
      <select v-model.number="month" @change="load"><option v-for="(name, index) in monthNames" :key="name" :value="index + 1">{{ name }}</option></select>
      <input v-model.number="year" type="number" min="1300" max="1600" @change="load">
      <button class="central-button primary" type="button" :disabled="loading" @click="load">بروزرسانی</button>
    </div>
    <p v-if="error" class="calendar-error">{{ error }}</p>
    <div v-else class="calendar-grid">
      <article v-for="day in days" :key="day.day" :class="['calendar-day', { holiday: day.holiday }]">
        <header><strong>{{ day.day.toLocaleString('fa-IR') }}</strong><span>{{ day.weekday }}</span></header>
        <input v-model="day.title" placeholder="عنوان مناسبت">
        <label><input v-model="day.holiday" type="checkbox"> تعطیل</label>
        <small v-if="day.holiday" class="holiday-label">تعطیل رسمی</small>
        <button type="button" :disabled="day.saving" @click="save(day)">{{ day.saving ? '...' : 'ذخیره' }}</button>
      </article>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import moment from 'moment-jalaali';
const now = new Date();
const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const weekdayNames = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
const year = ref(1405), month = ref(1), loading = ref(false), error = ref(''), days = ref([]);
const load = async () => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/central-api/calendar/events', {
      params: { year: year.value, month: month.value },
    });
    const map = new Map((data.events || []).map(event => {
      const day = Number(event.day || String(event.jalali_date || '').slice(-2));
      return [day, event];
    }));
    const count = month.value <= 6 ? 31 : month.value <= 11 ? 30 : (year.value % 4 === 3 ? 30 : 29);
    days.value = Array.from({ length: count }, (_, index) => {
      const event = map.get(index + 1) || {};
      const holiday = event.holiday ?? event.is_official_holiday;
      return {
        day: index + 1,
        weekday: weekdayNames[moment(`${year.value}/${month.value}/${index + 1}`, 'jYYYY/jM/jD').day()],
        title: event.title || '',
        holiday: holiday === true || holiday === 1 || holiday === '1',
        saving: false,
      };
    });
  } catch {
    error.value = 'دریافت تقویم انجام نشد.';
  } finally {
    loading.value = false;
  }
};
const save = async day => { day.saving = true; try { await axios.put(`/central-api/calendar/events/${year.value}-${String(month.value).padStart(2, '0')}-${String(day.day).padStart(2, '0')}`, { title: day.title, is_official_holiday: day.holiday }); } catch { error.value = 'ذخیره تعطیلی انجام نشد.'; } finally { day.saving = false; } };
onMounted(load);
</script>

<style scoped>
.calendar-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:18px}.calendar-toolbar select,.calendar-toolbar input{height:40px;padding:0 10px;border:1px solid #cbd5e1;border-radius:9px;background:#fff}.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:9px}.calendar-day{display:grid;gap:7px;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.calendar-day header{display:flex;align-items:center;justify-content:space-between}.calendar-day header span{color:#64748b;font-size:11px;font-weight:800}.calendar-day.holiday{border-color:#fca5a5;background:#fff7f7}.calendar-day.holiday header span{color:#b91c1c}.calendar-day input:not([type=checkbox]){width:100%;height:30px;border:1px solid #e2e8f0;border-radius:7px;padding:0 6px}.calendar-day label{font-size:10px;color:#64748b}.holiday-label{color:#b91c1c;font-size:10px;font-weight:900}.calendar-day button{height:28px;border:0;border-radius:7px;background:#2563eb;color:#fff;font-family:inherit;cursor:pointer}.calendar-error{color:#b91c1c}@media(max-width:800px){.calendar-grid{grid-template-columns:repeat(3,1fr)}}
</style>
