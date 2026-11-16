<template><article class="report-card" @click="$emit('open')"><header><span>{{ title }}</span><small v-if="hint">{{ hint }}</small></header><strong>{{ value }}</strong><slot /></article></template>
<script>export default { props:{title:String,value:[String,Number],hint:String}, emits:['open'] }</script>
<style scoped>.report-card{min-width:0;padding:16px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05);cursor:pointer}.report-card:hover{border-color:#93c5fd}.report-card header{display:flex;justify-content:space-between;gap:8px;color:#64748b;font-size:11px;font-weight:900}.report-card strong{display:block;margin-top:10px;color:#0f172a;font-size:23px}.report-card small{color:#2563eb}</style>

<style>
/* Dark charcoal: reports-static */
html body.dark .nsr,
html body.dark .reports-dashboard,
html body.dark .report-card,
html body.dark .native-reports,
html body.dark .static-reports,
html #app.dark .nsr,
html #app.dark .reports-dashboard,
html #app.dark .report-card,
html #app.dark .native-reports,
html #app.dark .static-reports {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
}
html body.dark .report-card,
html body.dark .nsr-card,
html body.dark .dashboard-card,
html body.dark .stat-card,
html #app.dark .report-card,
html #app.dark .nsr-card,
html #app.dark .dashboard-card,
html #app.dark .stat-card {
  background: #22252d !important;
  border-color: #3a3f4a !important;
  color: #e8eaed !important;
}
html body.dark .report-card:hover,
html body.dark .nsr-card:hover,
html #app.dark .report-card:hover,
html #app.dark .nsr-card:hover {
  background: #2a2f3a !important;
  border-color: #5b6472 !important;
}
html body.dark .nsr input,
html body.dark .nsr select,
html body.dark .reports-dashboard input,
html body.dark .reports-dashboard select,
html body.dark .report-card input,
html body.dark .report-card select,
html #app.dark .nsr input,
html #app.dark .nsr select,
html #app.dark .reports-dashboard input,
html #app.dark .reports-dashboard select,
html #app.dark .report-card input,
html #app.dark .report-card select {
  background: #121417 !important;
  border-color: #3a3f4a !important;
  color: #f1f3f4 !important;
}
html body.dark .nsr table th,
html body.dark .reports-dashboard table th,
html #app.dark .nsr table th,
html #app.dark .reports-dashboard table th {
  background: #22252d !important;
  color: #c5cad3 !important;
}
html body.dark .nsr table td,
html body.dark .reports-dashboard table td,
html #app.dark .nsr table td,
html #app.dark .reports-dashboard table td {
  background: #1a1c22 !important;
  color: #e8eaed !important;
  border-color: #2c3038 !important;
}
html body.dark .nsr h1,
html body.dark .nsr h2,
html body.dark .nsr h3,
html body.dark .reports-dashboard h1,
html body.dark .reports-dashboard h2,
html body.dark .reports-dashboard h3,
html body.dark .report-card h3,
html #app.dark .nsr h1,
html #app.dark .nsr h2,
html #app.dark .nsr h3,
html #app.dark .reports-dashboard h1,
html #app.dark .reports-dashboard h2,
html #app.dark .reports-dashboard h3,
html #app.dark .report-card h3 { color: #f1f3f4 !important; }
html body.dark .nsr p,
html body.dark .reports-dashboard p,
html body.dark .report-card p,
html body.dark .muted,
html #app.dark .nsr p,
html #app.dark .reports-dashboard p,
html #app.dark .report-card p { color: #9aa0a6 !important; }
</style>

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
