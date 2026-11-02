<template>
  <div class="ail" dir="rtl">
    <div class="ail-head">
      <strong>{{ card.title }}</strong>
      <span class="ail-count">{{ fa(total) }} مورد</span>
    </div>

    <div v-if="asking" class="ail-ask">
      <div class="ail-ask-text">
        <span class="ail-ask-icon" aria-hidden="true">؟</span>
        <p><b>{{ fa(total) }} مورد</b> پیدا شد. چطور نشانشان بدهم؟</p>
      </div>
      <div class="ail-choices">
        <button type="button" class="primary" @click="reveal(20)">۲۰ تای اول</button>
        <button v-if="total > 20" type="button" @click="reveal(50)">{{ total > 50 ? '۵۰ تا' : `همهٔ ${fa(total)} مورد` }}</button>
        <button v-if="total > 50" type="button" @click="reveal(ALL_CAP)">همه{{ total > ALL_CAP ? ` (تا ${fa(ALL_CAP)} مورد)` : '' }}</button>
      </div>
      <div v-if="card.suggestions?.length || card.page" class="ail-suggest">
        <span>یا محدودش کنید:</span>
        <button v-for="item in card.suggestions || []" :key="item.prompt" type="button" @click="$emit('prompt', item.prompt)">{{ item.label }}</button>
        <button v-if="card.page" type="button" class="ail-open" @click="$emit('navigate', card)">{{ card.label || 'باز کردن در صفحهٔ مربوط' }} ↗</button>
        <button v-if="card.report" type="button" class="ail-open ail-report" @click="openReport">{{ card.report_label || 'نمایش در گزارش‌ساز' }} ↗</button>
      </div>
      <small>انتخاب‌های بالا بدون مصرف اعتبار هوش مصنوعی نمایش داده می‌شوند.</small>
    </div>

    <template v-else>
      <ul v-if="compact" class="ail-list">
        <li v-for="(row, index) in rows" :key="index">
          <span class="ail-index">{{ fa(index + 1) }}</span>
          <div class="ail-main">
            <b>{{ primary(row) }}</b>
            <small v-if="secondary(row)">{{ secondary(row) }}</small>
          </div>
          <button v-if="action(row, index)" type="button" @click="$emit('action', action(row, index))">{{ action(row, index).label }}</button>
        </li>
      </ul>
      <div v-else class="ail-table-shell" :class="{ 'can-scroll': overflow }">
        <div ref="scroller" class="ail-table-wrap" @scroll="measure">
          <table>
            <thead><tr><th>#</th><th v-for="(label, key) in card.columns" :key="key">{{ label }}</th><th v-if="hasActions" class="ail-act"></th></tr></thead>
            <tbody>
              <tr v-for="(row, index) in rows" :key="index">
                <td class="ail-num">{{ fa(index + 1) }}</td>
                <td v-for="(_, key) in card.columns" :key="key">{{ cell(row, key) }}</td>
                <td v-if="hasActions" class="ail-act"><button v-if="action(row, index)" type="button" @click="$emit('action', action(row, index))">{{ action(row, index).label }}</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <span v-if="overflow" class="ail-scroll-hint">↔ برای دیدن همهٔ ستون‌ها جدول را بکشید</span>
      </div>

      <div v-if="card.list || card.page" class="ail-foot">
        <span v-if="card.list">نمایش {{ fa(rows.length) }} از {{ fa(total) }}</span>
        <button v-if="canLoadMore" type="button" :disabled="loading" @click="loadMore(20)">{{ loading ? 'در حال دریافت...' : `${fa(Math.min(20, total - rows.length))} مورد بعدی` }}</button>
        <button v-if="card.page" type="button" class="ail-open" @click="$emit('navigate', card)">{{ card.label || 'نمایش در برنامه' }} ↗</button>
      </div>
      <button v-if="card.report" type="button" class="ail-report-cta" @click="openReport">
        <span aria-hidden="true">▤</span><b>{{ card.report_label || 'نمایش در گزارش‌ساز' }}</b><small>با همین فیلترها؛ قابل ویرایش و خروجی Excel</small>
      </button>
      <div v-if="card.followups?.length" class="ail-followups">
        <span>پیشنهاد:</span>
        <button v-for="item in card.followups" :key="item.prompt" type="button" @click="$emit('prompt', item.prompt)">{{ item.label }}</button>
      </div>
      <p v-if="loading && !canLoadMore" class="ail-loading">در حال دریافت فهرست...</p>
    </template>
    <p v-if="error" class="ail-error">{{ error }}</p>
    <small v-if="card.definition" class="ail-definition">{{ card.definition }}</small>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({ card: { type: Object, required: true } });
const emit = defineEmits(['action', 'prompt', 'navigate']);

const ALL_CAP = 500;
const rows = ref([...(props.card.rows || [])]);
const actions = ref([...(props.card.row_actions || [])]);
const asking = ref(props.card.ask === true);
const loading = ref(false);
const error = ref('');
const overflow = ref(false);
const scroller = ref(null);

const total = computed(() => Number(props.card.list?.total ?? props.card.count ?? rows.value.length));
const columnKeys = computed(() => Object.keys(props.card.columns || {}));
const compact = computed(() => columnKeys.value.length <= 4);
const primaryKey = computed(() => columnKeys.value.find((key) => ['name', 'patient', 'doctor'].includes(key)) || columnKeys.value[0]);
// نام و نام خانوادگیِ گزارش‌ساز با هم عنوان ردیف می‌شوند.
const primaryKeys = computed(() => (primaryKey.value === 'name' && columnKeys.value.includes('family') ? ['name', 'family'] : [primaryKey.value]));
function primary(row) {
  return primaryKeys.value.map((key) => row?.[key]).filter((value) => value !== null && value !== undefined && value !== '' && value !== '—').join(' ') || '—';
}
const hasActions = computed(() => rows.value.some((row, index) => action(row, index)));
const canLoadMore = computed(() => Boolean(props.card.list) && rows.value.length < total.value);

const fa = (value) => Number(value || 0).toLocaleString('fa-IR');
function cell(row, key) {
  const value = row?.[key];
  if (value === null || value === undefined || value === '') return '—';
  return typeof value === 'number' ? fa(value) : String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
}
function secondary(row) {
  return columnKeys.value.filter((key) => !primaryKeys.value.includes(key))
    .map((key) => (row?.[key] === null || row?.[key] === undefined || row?.[key] === '' ? '' : `${props.card.columns[key]}: ${cell(row, key)}`))
    .filter(Boolean).join(' · ');
}
function action(row, index) {
  const explicit = actions.value[index];
  if (explicit) return explicit;
  if (props.card.page === 'patients' && row?.file_number) return { page: 'patient_profile', file_number: String(row.file_number), label: 'مشاهده پرونده' };
  return null;
}

async function fetchPage(limit) {
  const { data } = await axios.post('/api/assistant/lists', {
    tool: props.card.list.tool, arguments: props.card.list.arguments || {}, offset: rows.value.length, limit,
  });
  rows.value.push(...(data.rows || []));
  actions.value.push(...(data.row_actions || []));
  return (data.rows || []).length;
}
async function loadMore(count) {
  if (!props.card.list || loading.value) return;
  loading.value = true; error.value = '';
  try {
    const target = Math.min(total.value, rows.value.length + count);
    while (rows.value.length < target) {
      const got = await fetchPage(Math.min(100, target - rows.value.length));
      if (!got) break;
    }
  } catch (e) { error.value = e?.response?.data?.message || 'دریافت ادامهٔ فهرست انجام نشد.'; }
  finally { loading.value = false; }
}
async function reveal(count) {
  asking.value = false;
  if (rows.value.length > count) { rows.value = rows.value.slice(0, count); actions.value = actions.value.slice(0, count); }
  if (count > rows.value.length) await loadMore(count - rows.value.length);
}
function openReport() {
  emit('navigate', { page: 'report_builder', report: { ...props.card.report, title: props.card.title } });
}
function measure() {
  const el = scroller.value;
  overflow.value = Boolean(el && el.scrollWidth - el.clientWidth > 4 && Math.abs(el.scrollLeft) + el.clientWidth < el.scrollWidth - 4);
}
watch(() => [rows.value.length, asking.value], () => nextTick(measure));
onMounted(() => nextTick(measure));
</script>

<style scoped>
.ail { display: grid; gap: 10px; }
.ail-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.ail-head strong { color: #1e3554; font-size: 12.5px; font-weight: 900; line-height: 1.7; }
.ail-count { flex: none; padding: 3px 9px; border-radius: 999px; background: #eef4ff; color: #2c5bc4; font-size: 10.5px; font-weight: 900; }
.ail-ask { display: grid; gap: 10px; padding: 12px; border: 1px solid #dbe6f7; border-radius: 13px; background: linear-gradient(135deg, #f6f9ff, #f3fbfb); }
.ail-ask-text { display: flex; align-items: center; gap: 9px; }
.ail-ask-text p { margin: 0; color: #2a405f; font-size: 12px; line-height: 1.8; }
.ail-ask-icon { flex: none; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 9px; background: #2c5bc4; color: #fff; font-weight: 900; }
.ail-choices { display: flex; flex-wrap: wrap; gap: 6px; }
.ail-choices button { height: 32px; padding: 0 13px; border: 1px solid #cfdcf1; border-radius: 10px; background: #fff; color: #23406b; font: inherit; font-size: 11.5px; font-weight: 900; cursor: pointer; transition: transform .12s, box-shadow .12s; }
.ail-choices button:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(40, 80, 150, .12); }
.ail-choices button.primary { border-color: #2c5bc4; background: #2c5bc4; color: #fff; }
.ail-suggest { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.ail-suggest > span { color: #6d7f99; font-size: 10.5px; font-weight: 800; }
.ail-suggest button { min-height: 28px; padding: 4px 10px; border: 1px dashed #b9c9e3; border-radius: 999px; background: transparent; color: #3b5b8f; font: inherit; font-size: 10.5px; font-weight: 800; cursor: pointer; }
.ail-ask > small { color: #8392a8; font-size: 10px; }
.ail-list { display: grid; max-height: 320px; margin: 0; padding: 2px; overflow-y: auto; list-style: none; border: 1px solid #e4ebf4; border-radius: 12px; background: #fff; }
.ail-list li { display: flex; align-items: center; gap: 9px; padding: 8px 10px; border-bottom: 1px solid #eef2f8; }
.ail-list li:last-child { border-bottom: 0; }
.ail-list li:nth-child(even) { background: #fafcff; }
.ail-index { flex: none; display: grid; place-items: center; min-width: 22px; height: 22px; padding: 0 4px; border-radius: 7px; background: #eef3fa; color: #6b7d97; font-size: 10px; font-weight: 900; }
.ail-main { display: grid; flex: 1; gap: 1px; min-width: 0; }
.ail-main b { overflow: hidden; color: #1f3352; font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.ail-main small { overflow: hidden; color: #7a8aa2; font-size: 10.5px; text-overflow: ellipsis; white-space: nowrap; }
.ail-list button, .ail-table-wrap td button { flex: none; height: 27px; padding: 0 10px; border: 1px solid #cddcf3; border-radius: 8px; background: #f3f7ff; color: #2c5bc4; font: inherit; font-size: 10.5px; font-weight: 900; cursor: pointer; white-space: nowrap; }
.ail-list button:hover, .ail-table-wrap td button:hover { border-color: #2c5bc4; background: #2c5bc4; color: #fff; }
.ail-table-shell { position: relative; display: grid; gap: 5px; }
.ail-table-wrap { max-height: 320px; overflow: auto; border: 1px solid #e4ebf4; border-radius: 12px; background:
  linear-gradient(to left, #fff 30%, rgba(255,255,255,0)) right / 28px 100% no-repeat local,
  linear-gradient(to right, #fff 30%, rgba(255,255,255,0)) left / 28px 100% no-repeat local,
  radial-gradient(farthest-side at 100% 50%, rgba(30,50,90,.16), transparent) right / 12px 100% no-repeat scroll,
  radial-gradient(farthest-side at 0 50%, rgba(30,50,90,.16), transparent) left / 12px 100% no-repeat scroll, #fff; }
.ail-table-wrap table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 11.5px; white-space: nowrap; }
.ail-table-wrap th { position: sticky; top: 0; z-index: 1; padding: 8px 10px; border-bottom: 1px solid #e4ebf4; background: #f5f8fc; color: #6b7d97; font-size: 10.5px; font-weight: 900; text-align: right; }
.ail-table-wrap td { padding: 8px 10px; border-bottom: 1px solid #eef2f8; color: #2a3f5f; background: transparent; }
.ail-table-wrap tbody tr:nth-child(even) td { background: rgba(245, 249, 255, .7); }
.ail-table-wrap .ail-act { position: sticky; left: 0; z-index: 1; background: #fff; box-shadow: 8px 0 12px -10px rgba(30,50,90,.35); }
.ail-table-wrap th.ail-act { z-index: 2; background: #f5f8fc; }
.ail-table-wrap tbody tr:nth-child(even) td.ail-act { background: #f7faff; }
.ail-num { color: #98a6ba !important; font-size: 10px; }
.ail-scroll-hint { justify-self: start; padding: 2px 8px; border-radius: 999px; background: #fff7e6; color: #9a6400; font-size: 10px; font-weight: 800; }
.ail-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: #6d7f99; font-size: 10.5px; font-weight: 800; }
.ail-foot > span { margin-inline-end: auto; }
.ail-foot button { height: 29px; padding: 0 12px; border: 1px solid #cddcf3; border-radius: 9px; background: #fff; color: #2c5bc4; font: inherit; font-size: 10.5px; font-weight: 900; cursor: pointer; }
.ail-foot button:disabled { opacity: .6; cursor: wait; }
.ail-open { border-style: solid !important; background: #eef4ff !important; }
.ail-report-cta { display: grid; grid-template-columns: auto 1fr; align-items: center; column-gap: 9px; padding: 10px 12px; border: 1px solid #b7e0d6; border-radius: 12px; background: linear-gradient(135deg, #effaf6, #f4fbff); color: #0e6b5e; font: inherit; text-align: right; cursor: pointer; transition: transform .12s, box-shadow .12s; }
.ail-report-cta:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(14, 107, 94, .14); }
.ail-report-cta > span { grid-row: span 2; display: grid; place-items: center; width: 30px; height: 30px; border-radius: 9px; background: #0e6b5e; color: #fff; font-size: 15px; }
.ail-report-cta b { font-size: 12px; font-weight: 900; }
.ail-report-cta small { color: #4c7d74; font-size: 10px; }
.ail-followups { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.ail-followups > span { color: #6d7f99; font-size: 10.5px; font-weight: 800; }
.ail-followups button { min-height: 28px; padding: 4px 10px; border: 1px dashed #b9c9e3; border-radius: 999px; background: transparent; color: #3b5b8f; font: inherit; font-size: 10.5px; font-weight: 800; cursor: pointer; }
.ail-followups button:hover { border-style: solid; background: #f3f7ff; }
.ail-loading { margin: 0; color: #6d7f99; font-size: 11px; }
.ail-error { margin: 0; padding: 7px 10px; border-radius: 9px; background: #fef2f2; color: #b91c1c; font-size: 11px; font-weight: 800; }
.ail-definition { color: #7a8aa2; font-size: 10.5px; line-height: 1.8; }

:global(.ai-preview--dark .ail-head strong), :global(.ai-preview--dark .ail-main b), :global(.ai-preview--dark .ail-ask-text p) { color: #e3edf9; }
:global(.ai-preview--dark .ail-count) { background: #24395c; color: #b9cff5; }
:global(.ai-preview--dark .ail-ask) { border-color: #34496b; background: #1d2d46; }
:global(.ai-preview--dark .ail-choices button) { border-color: #3a5179; background: #22344f; color: #dbe7f8; }
:global(.ai-preview--dark .ail-choices button.primary) { border-color: #4b7be0; background: #4b7be0; color: #fff; }
:global(.ai-preview--dark .ail-list), :global(.ai-preview--dark .ail-table-wrap) { border-color: #34496b; background: #1b2a40; }
:global(.ai-preview--dark .ail-list li) { border-color: #2a3c59; }
:global(.ai-preview--dark .ail-list li:nth-child(even)), :global(.ai-preview--dark .ail-table-wrap tbody tr:nth-child(even) td) { background: #1f3049; }
:global(.ai-preview--dark .ail-table-wrap th) { border-color: #34496b; background: #22344f; color: #a9bbd4; }
:global(.ai-preview--dark .ail-table-wrap td) { border-color: #2a3c59; color: #d7e3f4; }
:global(.ai-preview--dark .ail-table-wrap .ail-act) { background: #1b2a40; }
:global(.ai-preview--dark .ail-report-cta) { border-color: #2c5a52; background: #17312e; color: #9fe0cf; }
:global(.ai-preview--dark .ail-report-cta small) { color: #7fb5a8; }
:global(.ai-preview--dark .ail-followups button) { border-color: #3a5179; color: #b9cff5; }
:global(.ai-preview--dark .ail-index) { background: #263a59; color: #a9bbd4; }
:global(.ai-preview--dark .ail-foot button), :global(.ai-preview--dark .ail-list button), :global(.ai-preview--dark .ail-table-wrap td button) { border-color: #3a5179; background: #22344f; color: #b9cff5; }
</style>
