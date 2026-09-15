<template>
  <section class="reports-page" dir="rtl">
    <nav class="reports-navigation" aria-label="صفحات گزارش">
      <button type="button" :class="{ active: activeView === 'reports' }" @click="activeView = 'reports'">
        گزارشات
      </button>
      <button type="button" :class="{ active: activeView === 'builder' }" @click="activeView = 'builder'">
        گزارش‌ساز
      </button>
    </nav>

    <NativeStaticReports v-if="activeView === 'reports'" />
    <ReportBuilder v-else />
  </section>
</template>

<script>
import NativeStaticReports from './NativeStaticReports.vue'
import ReportBuilder from './ReportBuilder.vue'

export default {
  name: 'Gozaresh',
  components: { NativeStaticReports, ReportBuilder },
  data() {
    return { activeView: 'reports' }
  }
}
</script>

<style scoped>
.reports-page { width: 100%; min-height: calc(100vh - 150px); background: #f1f4f9; }
.reports-navigation { display: flex; align-items: center; gap: 8px; padding: 16px 24px 0; }
.reports-navigation button {
  appearance: none; border: 1px solid #dbe3eb; border-radius: 10px; background: #fff; color: #526172;
  padding: 9px 18px; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer;
  transition: background-color .2s ease, border-color .2s ease, color .2s ease;
}
.reports-navigation button:hover, .reports-navigation button.active {
  border-color: #0e6b5e; background: #e8f3f0; color: #0e6b5e;
}
@media (max-width: 640px) {
  .reports-navigation { padding: 12px 16px 0; }
  .reports-navigation button { flex: 1; }
}
</style>
