<template>
  <section class="reports-page" dir="rtl">
    <NativeStaticReports v-if="activeView === 'reports'" @open-builder="activeView = 'builder'" />
    <template v-else>
      <div class="builder-page-tabs">
        <button type="button" @click="activeView = 'reports'">داشبورد</button>
        <button type="button" class="active">گزارش‌ساز</button>
      </div>
      <ReportBuilder :permissions="permissions" :assistant-request="assistantReport" />
    </template>
  </section>
</template>

<script>
import NativeStaticReports from './NativeStaticReports.vue'
import ReportBuilder from './ReportBuilder.vue'

export default {
  name: 'Gozaresh',
  components: { NativeStaticReports, ReportBuilder },
  props: {
    permissions: { type: Array, default: () => [] },
    initialView: { type: String, default: 'reports' },
    assistantReport: { type: Object, default: null }
  },
  data() {
    return { activeView: this.initialView }
  },
  watch: { initialView(value) { this.activeView = value } }
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

<style>
/* reports shell dark mode */
html body.dark .reports-page,
html #app.dark .reports-page {
  background: #121316 !important;
  color: #e8eaed !important;
}
html body.dark .reports-page .builder-page-tabs button,
html #app.dark .reports-page .builder-page-tabs button {
  background: #22252d !important;
  color: #9aa0a6 !important;
  box-shadow: none !important;
}
html body.dark .reports-page .builder-page-tabs button:hover,
html body.dark .reports-page .builder-page-tabs button.active,
html #app.dark .reports-page .builder-page-tabs button:hover,
html #app.dark .reports-page .builder-page-tabs button.active {
  background: #2a2f3a !important;
  color: #4f8cff !important;
  box-shadow: 0 1px 4px rgba(0,0,0,.35) !important;
}
</style>
