<template>
  <div class="central-view central-stack" dir="rtl">
    <section class="central-panel central-stack">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">دستیار هوش مصنوعی</div>
          <div class="central-section-subtitle">موجودی دلاری هر سایت، شارژ، ضریب سود و نرخ مدل‌ها. موجودی هیچ سایتی منفی نمی‌شود و زیر حداقل موجودی، استفاده بسته می‌شود.</div>
        </div>
      </div>
      <div class="cai-tabs">
        <button v-for="item in tabs" :key="item.key" type="button" :class="{ active: tab === item.key }" @click="tab = item.key">{{ item.label }}</button>
      </div>
      <p v-if="error" class="central-error">{{ error }}</p>
      <p v-if="message" class="central-message">{{ message }}</p>

      <template v-if="tab === 'wallets'">
        <div class="cai-table-wrap">
          <table class="cai-table">
            <thead><tr><th>سایت</th><th>دستیار</th><th>موجودی</th><th>رزرو در جریان</th><th>ضریب سود</th><th>مصرف ۳۰ روز</th><th>تمام‌شده ۳۰ روز</th><th>سود ۳۰ روز</th><th></th></tr></thead>
            <tbody>
              <tr v-for="wallet in wallets" :key="wallet.tenant_id" :class="{ selected: selected?.tenant_id === wallet.tenant_id }">
                <td><b>{{ wallet.name }}</b><small>{{ wallet.tenant_id }}</small></td>
                <td><span class="central-badge" :class="{ danger: !wallet.ai_enabled }">{{ wallet.ai_enabled ? 'فعال' : 'غیرفعال' }}</span></td>
                <td><b :class="{ 'cai-danger': wallet.blocked, 'cai-warn': !wallet.blocked && wallet.low }">{{ usd(wallet.balance_usd) }}</b><small v-if="wallet.blocked">قفل (زیر حداقل)</small></td>
                <td>{{ usd(wallet.held_usd) }}</td>
                <td>×{{ num(wallet.effective_multiplier) }}<small>{{ wallet.markup_multiplier === null ? 'سراسری' : 'اختصاصی' }}</small></td>
                <td>{{ usd(wallet.charge_30d_usd) }}</td><td>{{ usd(wallet.cost_30d_usd) }}</td><td>{{ usd(wallet.charge_30d_usd - wallet.cost_30d_usd) }}</td>
                <td><button class="central-button compact" type="button" @click="select(wallet)">مدیریت</button></td>
              </tr>
              <tr v-if="!wallets.length"><td colspan="9" class="cai-empty">سایتی ثبت نشده است.</td></tr>
            </tbody>
          </table>
        </div>

        <section v-if="selected" class="central-card cai-wallet">
          <div class="cai-wallet-head">
            <div><strong>{{ selected.name }}</strong><small>موجودی فعلی: {{ usd(selected.balance_usd) }}</small></div>
            <button class="central-button secondary compact" type="button" @click="selected = null">بستن</button>
          </div>
          <div class="cai-wallet-grid">
            <form class="cai-form" @submit.prevent="charge">
              <h4>شارژ موجودی</h4>
              <label class="central-form-label">مبلغ (دلار)<input v-model="chargeForm.amount_usd" class="central-input" type="number" min="0.01" step="0.01" required /></label>
              <label class="central-form-label">مبلغ دریافتی (تومان، اختیاری)<input v-model="chargeForm.paid_toman" class="central-input" type="number" min="0" /></label>
              <label class="central-form-label">شمارهٔ پیگیری<input v-model.trim="chargeForm.reference" class="central-input" maxlength="120" /></label>
              <label class="central-form-label">توضیح<input v-model.trim="chargeForm.note" class="central-input" maxlength="500" /></label>
              <button class="central-button" :disabled="saving">ثبت شارژ</button>
            </form>
            <form class="cai-form" @submit.prevent="adjust">
              <h4>اصلاح دستی</h4>
              <label class="central-form-label">مبلغ (دلار؛ منفی برای کسر)<input v-model="adjustForm.amount_usd" class="central-input" type="number" step="0.01" required /></label>
              <label class="central-form-label">دلیل (اجباری)<input v-model.trim="adjustForm.note" class="central-input" minlength="3" maxlength="500" required /></label>
              <small>کسر فقط تا سقف موجودی آزاد مجاز است؛ موجودی منفی نمی‌شود.</small>
              <button class="central-button secondary" :disabled="saving">ثبت اصلاح</button>
            </form>
            <form class="cai-form" @submit.prevent="saveMultiplier">
              <h4>ضریب سود اختصاصی</h4>
              <label class="central-form-label">ضریب (خالی = سراسری ×{{ num(settingsForm.markup_multiplier) }})<input v-model="multiplierForm" class="central-input" type="number" min="1" max="100" step="0.01" placeholder="مثلاً ۱٫۵" /></label>
              <small>هزینهٔ هر درخواست = قیمت OpenAI × این ضریب. فقط روی درخواست‌های بعدی اعمال می‌شود.</small>
              <button class="central-button secondary" :disabled="saving">ذخیره ضریب</button>
            </form>
          </div>
          <h4>شارژها و اصلاحیه‌ها</h4>
          <div class="cai-table-wrap">
            <table class="cai-table">
              <thead><tr><th>زمان</th><th>نوع</th><th>مبلغ</th><th>موجودی پس از آن</th><th>تومان</th><th>پیگیری</th><th>توضیح</th></tr></thead>
              <tbody>
                <tr v-for="row in transactions" :key="row.id">
                  <td>{{ dateTime(row.created_at) }}</td><td>{{ row.type_label }}</td><td :class="row.amount_usd < 0 ? 'cai-danger' : 'cai-ok'">{{ usd(row.amount_usd) }}</td>
                  <td>{{ usd(row.balance_after_usd) }}</td><td>{{ row.paid_toman !== null ? num(row.paid_toman) : '—' }}</td><td>{{ row.reference || '—' }}</td><td>{{ row.note || '—' }}</td>
                </tr>
                <tr v-if="!transactions.length"><td colspan="7" class="cai-empty">تراکنشی ثبت نشده است.</td></tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <template v-else-if="tab === 'prices'">
        <p class="central-section-subtitle">نرخ‌ها را از صفحهٔ قیمت OpenAI و به دلار برای هر ۱ میلیون توکن وارد کنید. نرخ تازه از همین لحظه اعمال می‌شود و هزینهٔ درخواست‌های گذشته تغییر نمی‌کند. مدل بدون نرخ قابل استفاده نیست.</p>
        <div class="cai-prices">
          <article v-for="model in models" :key="model.id" class="central-card cai-price">
            <div class="cai-wallet-head">
              <div><strong>{{ model.id }}</strong><small>{{ model.label }}</small></div>
              <span class="central-badge" :class="{ danger: !model.current }">{{ model.current ? 'نرخ دارد' : 'بدون نرخ؛ غیرقابل استفاده' }}</span>
            </div>
            <div v-if="model.current" class="cai-current">
              <span>ورودی: <b>{{ rate(model.current.input_per_m_usd) }}</b></span>
              <span v-if="model.kind === 'chat'">ورودی cache: <b>{{ rate(model.current.cached_input_per_m_usd ?? model.current.input_per_m_usd) }}</b></span>
              <span>خروجی: <b>{{ rate(model.current.output_per_m_usd) }}</b></span>
              <span v-if="model.kind === 'transcription'">صوت ورودی: <b>{{ rate(model.current.audio_input_per_m_usd) }}</b></span>
              <span v-if="model.kind === 'transcription'">هر دقیقه: <b>{{ model.current.per_minute_usd !== null ? usd(model.current.per_minute_usd) : '—' }}</b></span>
              <small>از {{ dateTime(model.current.effective_from) }}</small>
            </div>
            <form class="cai-price-form" @submit.prevent="savePrice(model)">
              <label class="central-form-label">ورودی ($/1M)<input v-model="priceForms[model.id].input_per_m_usd" class="central-input" type="number" min="0" step="0.0001" required /></label>
              <label v-if="model.kind === 'chat'" class="central-form-label">ورودی cache ($/1M)<input v-model="priceForms[model.id].cached_input_per_m_usd" class="central-input" type="number" min="0" step="0.0001" /></label>
              <label class="central-form-label">خروجی ($/1M)<input v-model="priceForms[model.id].output_per_m_usd" class="central-input" type="number" min="0" step="0.0001" required /></label>
              <label v-if="model.kind === 'transcription'" class="central-form-label">صوت ورودی ($/1M)<input v-model="priceForms[model.id].audio_input_per_m_usd" class="central-input" type="number" min="0" step="0.0001" /></label>
              <label v-if="model.kind === 'transcription'" class="central-form-label">هر دقیقه ($)<input v-model="priceForms[model.id].per_minute_usd" class="central-input" type="number" min="0" step="0.0001" /></label>
              <button class="central-button compact" :disabled="saving">ثبت نرخ تازه</button>
            </form>
            <details v-if="model.history.length > 1"><summary>سابقهٔ نرخ‌ها</summary>
              <div v-for="item in model.history" :key="item.id" class="cai-history">{{ dateTime(item.effective_from) }} · ورودی {{ rate(item.input_per_m_usd) }} · خروجی {{ rate(item.output_per_m_usd) }}</div>
            </details>
          </article>
        </div>
      </template>

      <template v-else-if="tab === 'settings'">
        <form class="central-card cai-settings" @submit.prevent="saveSettings">
          <label class="central-form-label">ضریب سود سراسری<input v-model="settingsForm.markup_multiplier" class="central-input" type="number" min="1" max="100" step="0.01" required /></label>
          <label class="central-form-label">حداقل موجودی برای استفاده (دلار)<input v-model="settingsForm.min_balance_usd" class="central-input" type="number" min="0" step="0.01" required /></label>
          <label class="central-form-label">آستانهٔ هشدار کم بودن موجودی (دلار)<input v-model="settingsForm.low_balance_usd" class="central-input" type="number" min="0" step="0.01" required /></label>
          <small>ضریب سراسری برای همهٔ سایت‌هایی است که ضریب اختصاصی ندارند. مثلاً ۱٫۳ یعنی هزینهٔ هر درخواست ۳۰٪ بیشتر از قیمت OpenAI از موجودی کم می‌شود.</small>
          <div class="central-actions"><button class="central-button" :disabled="saving">ذخیره تنظیمات</button></div>
        </form>
      </template>

      <AiUsageReport v-else-if="tab === 'report'" api-base="/central-api/ai" central />
    </section>
  </div>
</template>

<script setup>
import axios from 'axios';
import moment from 'moment-jalaali';
import { onMounted, reactive, ref } from 'vue';
import AiUsageReport from '../../components/AiUsageReport.vue';

const tabs = [
  { key: 'wallets', label: 'موجودی و شارژ سایت‌ها' }, { key: 'prices', label: 'نرخ مدل‌ها' },
  { key: 'settings', label: 'ضریب سود و حداقل موجودی' }, { key: 'report', label: 'گزارش مصرف' },
];
const tab = ref('wallets');
const wallets = ref([]);
const models = ref([]);
const selected = ref(null);
const transactions = ref([]);
const saving = ref(false);
const error = ref('');
const message = ref('');
const settingsForm = reactive({ markup_multiplier: 1.3, min_balance_usd: 1, low_balance_usd: 3 });
const chargeForm = reactive({ amount_usd: '', paid_toman: '', reference: '', note: '' });
const adjustForm = reactive({ amount_usd: '', note: '' });
const multiplierForm = ref('');
const priceForms = reactive({});

const num = (value) => Number(value || 0).toLocaleString('fa-IR', { maximumFractionDigits: 4 });
function usd(value) {
  const n = Number(value || 0);
  const digits = Math.abs(n) > 0 && Math.abs(n) < 1 ? 4 : 2;
  return `${n.toLocaleString('fa-IR', { minimumFractionDigits: digits, maximumFractionDigits: digits })} دلار`;
}
const rate = (value) => (value === null || value === undefined ? '—' : `${Number(value).toLocaleString('fa-IR', { maximumFractionDigits: 4 })} $`);
const dateTime = (value) => moment(value).locale('en').format('jYYYY/jMM/jDD HH:mm').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const errorText = (e) => e?.response?.data?.message || 'عملیات انجام نشد.';

function applyData(data) {
  if (data.settings) Object.assign(settingsForm, data.settings);
  if (data.wallets) {
    wallets.value = data.wallets;
    if (selected.value) selected.value = data.wallets.find((w) => w.tenant_id === selected.value.tenant_id) || null;
  }
  if (data.models) {
    models.value = data.models;
    for (const model of data.models) {
      const c = model.current || {};
      priceForms[model.id] = {
        input_per_m_usd: c.input_per_m_usd ?? '', cached_input_per_m_usd: c.cached_input_per_m_usd ?? '',
        output_per_m_usd: c.output_per_m_usd ?? '', audio_input_per_m_usd: c.audio_input_per_m_usd ?? '', per_minute_usd: c.per_minute_usd ?? '',
      };
    }
  }
}

async function run(action) {
  saving.value = true; error.value = ''; message.value = '';
  try {
    const { data } = await action();
    applyData(data);
    message.value = data.message || '';
    return true;
  } catch (e) { error.value = errorText(e); return false; }
  finally { saving.value = false; }
}

const load = () => run(() => axios.get('/central-api/ai'));
async function select(wallet) {
  selected.value = wallet;
  multiplierForm.value = wallet.markup_multiplier ?? '';
  await loadTransactions();
}
async function loadTransactions() {
  if (!selected.value) return;
  try { const { data } = await axios.get(`/central-api/ai/wallets/${selected.value.tenant_id}/transactions`); transactions.value = data.data || []; }
  catch (e) { error.value = errorText(e); }
}
const clean = (form) => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
async function charge() {
  if (await run(() => axios.post(`/central-api/ai/wallets/${selected.value.tenant_id}/charges`, clean(chargeForm)))) {
    Object.assign(chargeForm, { amount_usd: '', paid_toman: '', reference: '', note: '' }); await loadTransactions();
  }
}
async function adjust() {
  if (await run(() => axios.post(`/central-api/ai/wallets/${selected.value.tenant_id}/adjustments`, adjustForm))) {
    Object.assign(adjustForm, { amount_usd: '', note: '' }); await loadTransactions();
  }
}
const saveMultiplier = () => run(() => axios.patch(`/central-api/ai/wallets/${selected.value.tenant_id}`, { markup_multiplier: multiplierForm.value === '' ? null : multiplierForm.value }));
const saveSettings = () => run(() => axios.patch('/central-api/ai/settings', settingsForm));
const savePrice = (model) => run(() => axios.post('/central-api/ai/prices', { model: model.id, ...clean(priceForms[model.id]) }));

onMounted(load);
</script>

<style scoped>
.cai-tabs{display:flex;flex-wrap:wrap;gap:8px}
.cai-tabs button{height:36px;border:1px solid #dbe3ef;border-radius:999px;padding:0 14px;background:#f8fafc;color:#334155;font-family:inherit;font-weight:800;cursor:pointer}
.cai-tabs button.active{border-color:#2563eb;background:#2563eb;color:#fff}
.cai-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}
.cai-table{width:100%;border-collapse:collapse;background:#fff;font-size:12px;white-space:nowrap}
.cai-table th,.cai-table td{padding:10px;border-bottom:1px solid #eef2f7;text-align:right;vertical-align:middle}
.cai-table th{background:#f8fafc;color:#475569;font-size:11px}
.cai-table td small{display:block;color:#94a3b8;font-size:10px}
.cai-table tr.selected td{background:#eff6ff}
.cai-danger{color:#b91c1c}.cai-warn{color:#b45309}.cai-ok{color:#047857}
.cai-empty{padding:20px;color:#94a3b8;text-align:center}
.cai-wallet{display:grid;gap:14px}
.cai-wallet-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.cai-wallet-head strong{display:block;font-size:15px}.cai-wallet-head small{color:#64748b}
.cai-wallet-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.cai-form{display:grid;gap:10px;align-content:start;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}
.cai-form h4,.cai-wallet h4{margin:0;font-size:13px}.cai-form small,.cai-settings small{color:#64748b;line-height:1.8}
.cai-prices{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px}
.cai-price{display:grid;gap:12px}
.cai-current{display:flex;flex-wrap:wrap;gap:6px 14px;padding:10px;border-radius:10px;background:#f8fafc;font-size:12px}.cai-current small{width:100%;color:#94a3b8}
.cai-price-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:end}
.cai-history{padding:4px 0;color:#64748b;font-size:11px}
.cai-settings{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:end}.cai-settings small,.cai-settings .central-actions{grid-column:1/-1}
@media(max-width:900px){.cai-wallet-grid,.cai-settings,.cai-price-form{grid-template-columns:1fr}}
</style>
