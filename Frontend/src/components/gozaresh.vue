<template>
  <section class="reports-page" dir="rtl">
    <NativeStaticReports v-if="activeView === 'reports'" @open-builder="activeView = 'builder'" />
    <template v-else>
      <div class="builder-page-tabs">
        <button type="button" @click="activeView = 'reports'">داشبورد</button>
        <button type="button" class="active">گزارش‌ساز</button>
      </div>
      <ReportBuilder />
    </template>
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
.builder-page-tabs { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 24px 0; }
.builder-page-tabs button {
  appearance: none; border: 0; border-radius: 999px; background: #e6ebf3; color: #64748b;
  padding: 8px 22px; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer;
}
.builder-page-tabs button:hover, .builder-page-tabs button.active {
  background: #fff; color: #1d4ed8; font-weight: 800; box-shadow: 0 1px 4px rgba(15,23,42,.1);
}
@media (max-width: 640px) {
  .builder-page-tabs { padding: 12px 16px 0; }
  .builder-page-tabs button { flex: 1; }
}
</style>
