<template><article class="report-card" @click="$emit('open')"><header><span>{{ title }}</span><small v-if="hint">{{ hint }}</small></header><strong>{{ value }}</strong><slot /></article></template>
<script>export default { props:{title:String,value:[String,Number],hint:String}, emits:['open'] }</script>
<style scoped>.report-card{min-width:0;padding:16px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05);cursor:pointer}.report-card:hover{border-color:#93c5fd}.report-card header{display:flex;justify-content:space-between;gap:8px;color:#64748b;font-size:11px;font-weight:900}.report-card strong{display:block;margin-top:10px;color:#0f172a;font-size:23px}.report-card small{color:#2563eb}</style>

<style>
/* ReportCard dark mode */
html body.dark .report-card,
html #app.dark .report-card {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
  box-shadow: 0 8px 22px rgba(0,0,0,.32) !important;
}
html body.dark .report-card:hover,
html #app.dark .report-card:hover {
  border-color: #5b6472 !important;
  background: #22252d !important;
}
html body.dark .report-card header,
html #app.dark .report-card header {
  color: #9aa0a6 !important;
}
html body.dark .report-card strong,
html #app.dark .report-card strong {
  color: #f1f3f4 !important;
}
html body.dark .report-card small,
html #app.dark .report-card small {
  color: #4f8cff !important;
}
</style>
