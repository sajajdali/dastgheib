<template>
  <section class="reports-page" dir="rtl"><NativeStaticReports /></section>
</template>

<script>
import NativeStaticReports from './NativeStaticReports.vue'

export default { name: 'Gozaresh', components: { NativeStaticReports } }
</script>

<style scoped>
.reports-page { width: 100%; min-height: calc(100vh - 150px); background: #f1f4f9; }
</style>
