<template>
  <section class="aiu" dir="rtl">
    <header class="aiu-hero" :class="balanceTone">
      <div class="aiu-hero-main">
        <span class="aiu-hero-kicker">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.6 5.4L19 10l-5.4 1.6L12 17l-1.6-5.4L5 10l5.4-1.6L12 3Z"/></svg>
          {{ central ? 'پنل مرکزی · همهٔ کلینیک‌ها' : 'دستیار هوش مصنوعی' }}
        </span>
        <h2>گزارش مصرف و هزینه</h2>
        <p>{{ rangeLabel }}<template v-if="!canViewAll"> · فقط مصرف شما</template></p>
      </div>
      <div v-if="!central && balance" class="aiu-hero-balance">
        <span>موجودی دستیار</span>
        <strong>{{ usd(balance.balance_usd) }}</strong>
        <div class="aiu-hero-meta">
          <em class="aiu-pill" :class="balanceTone">
            <i aria-hidden="true">{{ balance.blocked ? '!' : balance.low ? '!' : '✓' }}</i>
            {{ balance.blocked ? `قفل · زیر ${usd(balance.min_balance_usd)}` : balance.low ? 'رو به اتمام' : 'وضعیت عادی' }}
          </em>
          <small v-if="balance.days_left_estimate !== null">حدود {{ num(balance.days_left_estimate) }} روز با مصرف ۷ روز اخیر</small>
        </div>
      </div>
      <div v-else-if="central && summary?.range" class="aiu-hero-balance">
        <span>سود این بازه</span>
        <strong>{{ usd(summary.range.profit_usd) }}</strong>
        <div class="aiu-hero-meta"><small>فروش {{ usd(summary.range.charge_usd) }} · تمام‌شده {{ usd(summary.range.cost_usd) }}</small></div>
      </div>
      <button type="button" class="aiu-icon-btn" :disabled="loading" title="به‌روزرسانی" aria-label="به‌روزرسانی" @click="loadAll">
        <svg :class="{ spin: loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg>
      </button>
    </header>
    <p v-if="!central && balance?.blocked" class="aiu-alert">
      {{ balance.can_manage ? 'استفاده از دستیار بسته شده است. برای ادامه، موجودی باید توسط مدیریت مرکزی سامانه شارژ شود.' : 'استفاده از دستیار فعلاً بسته است؛ لطفاً به مدیر کلینیک اطلاع دهید.' }}
    </p>

    <div class="aiu-periods">
      <button v-for="period in summary?.periods || []" :key="period.key" type="button" class="aiu-period" :class="[`tone-${period.key}`, { active: preset === period.key }]" @click="applyPreset(period.key)">
        <div class="aiu-period-top">
          <span class="aiu-period-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="3"/><path d="M8 3v4M16 3v4M4 10h16"/></svg></span>
          <span>{{ period.label }}</span>
        </div>
        <strong>{{ usd(period.charge_usd) }}</strong>
        <div class="aiu-tokens">
          <span title="توکن ورودی"><b>↓</b>{{ compact(period.input_tokens) }}</span>
          <span title="توکن خروجی"><b>↑</b>{{ compact(period.output_tokens) }}</span>
        </div>
        <small>{{ num(period.questions) }} پرسش · {{ num(period.calls) }} درخواست</small>
        <small v-if="central" class="aiu-period-profit">سود {{ usd(period.profit_usd) }}</small>
      </button>
    </div>

    <section class="aiu-toolbar">
      <div class="aiu-segment">
        <button v-for="item in presets" :key="item.key" type="button" :class="{ active: preset === item.key }" @click="applyPreset(item.key)">{{ item.label }}</button>
      </div>
      <div class="aiu-toolbar-row">
        <div class="aiu-dates">
          <JalaliDateInput v-model="filters.from" format="jYYYY-jMM-jDD" display-format="jYYYY/jMM/jDD" placeholder="از تاریخ" auto-submit color="#2563eb" input-class="aiu-date" @change="preset = 'custom'" />
          <span>تا</span>
          <JalaliDateInput v-model="filters.to" format="jYYYY-jMM-jDD" display-format="jYYYY/jMM/jDD" placeholder="تا تاریخ" auto-submit color="#2563eb" input-class="aiu-date" @change="preset = 'custom'" />
        </div>
        <select v-if="central" v-model="filters.tenant_id" class="aiu-select">
          <option value="">همهٔ کلینیک‌ها</option>
          <option v-for="tenant in options.tenants || []" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
        </select>
        <div v-for="menu in filterMenus" :key="menu.key" class="aiu-pop">
          <button type="button" class="aiu-pop-btn" :class="{ active: filters[menu.key].length }" @click="toggleMenu(menu.key)">
            {{ menu.label }}<b v-if="filters[menu.key].length">{{ num(filters[menu.key].length) }}</b>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div v-if="openMenu === menu.key" class="aiu-pop-panel">
            <label v-for="item in menu.items" :key="item.id">
              <input v-model="filters[menu.key]" type="checkbox" :value="item.id">
              <i v-if="menu.key === 'models'" class="aiu-dot" :style="{ background: modelColor(item.id) }"></i>
              <span>{{ item.label || item.name }}</span>
            </label>
            <em v-if="!menu.items.length">موردی نیست</em>
            <div class="aiu-pop-foot">
              <button type="button" @click="filters[menu.key] = []">پاک کردن</button>
              <button type="button" class="primary" @click="openMenu = ''; loadAll()">اعمال</button>
            </div>
          </div>
        </div>
        <div class="aiu-toolbar-actions">
          <button type="button" class="aiu-btn" :disabled="loading" @click="loadAll">اعمال فیلتر</button>
          <button type="button" class="aiu-btn ghost" :disabled="exporting" @click="exportCsv">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
            {{ exporting ? 'در حال ساخت...' : 'خروجی CSV' }}
          </button>
        </div>
      </div>
      <p v-if="error" class="aiu-error">{{ error }}</p>
    </section>

    <div v-if="summary?.range" class="aiu-kpis">
      <div v-for="kpi in kpis" :key="kpi.label" class="aiu-kpi">
        <span class="aiu-kpi-icon" :class="kpi.tone" aria-hidden="true" v-html="kpi.icon"></span>
        <div><span>{{ kpi.label }}</span><b>{{ kpi.value }}</b><small v-if="kpi.hint">{{ kpi.hint }}</small></div>
      </div>
    </div>

    <div class="aiu-charts">
      <section class="aiu-card aiu-trend">
        <div class="aiu-card-head">
          <div><h4>روند هزینه</h4><small>{{ trend?.group_by === 'month' ? 'ماهانه' : 'روزانه' }} · به تفکیک مدل</small></div>
          <div class="aiu-legend">
            <span v-for="model in trendModels" :key="model"><i :style="{ background: modelColor(model) }"></i>{{ model }}</span>
          </div>
        </div>
        <div v-if="!trendMax" class="aiu-empty">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M7 16V9m5 7V5m5 11v-4"/></svg>
          در این بازه مصرفی ثبت نشده است.
        </div>
        <div v-else class="aiu-plot" @mouseleave="hoverIndex = null">
          <div class="aiu-y">
            <span v-for="tick in yTicks" :key="tick" :style="{ bottom: `${(tick / niceMax) * 100}%` }">{{ usdShort(tick) }}</span>
          </div>
          <div class="aiu-plot-area">
            <i v-for="tick in yTicks" :key="`g${tick}`" class="aiu-grid" :style="{ bottom: `${(tick / niceMax) * 100}%` }"></i>
            <div class="aiu-bars" :style="{ '--bars': trendBars.length }">
              <div v-for="(bar, index) in trendBars" :key="bar.key" class="aiu-bar" :class="{ hovered: hoverIndex === index }" @mouseenter="hoverIndex = index">
                <div class="aiu-bar-stack" :style="{ height: `${(bar.total / niceMax) * 100}%` }">
                  <i v-for="part in bar.parts" :key="part.model" :style="{ height: `${(part.value / bar.total) * 100}%`, background: modelColor(part.model) }"></i>
                </div>
              </div>
            </div>
            <div v-if="hoverBar" class="aiu-tip" :style="tipStyle">
              <strong>{{ hoverBar.label }}</strong>
              <span v-for="part in hoverBar.parts" :key="part.model"><i :style="{ background: modelColor(part.model) }"></i>{{ part.model }}<b>{{ usd(part.value) }}</b></span>
              <span class="aiu-tip-total">جمع<b>{{ usd(hoverBar.total) }}</b></span>
            </div>
          </div>
          <div class="aiu-x" :style="{ '--bars': trendBars.length }">
            <span v-for="(bar, index) in trendBars" :key="`x${bar.key}`">{{ index % xStep === 0 ? bar.short : '' }}</span>
          </div>
        </div>
      </section>

      <section class="aiu-card aiu-share-card">
        <div class="aiu-card-head"><div><h4>سهم مدل‌ها</h4><small>از هزینهٔ این بازه</small></div></div>
        <div v-if="!donut.total" class="aiu-empty">داده‌ای نیست.</div>
        <template v-else>
          <div class="aiu-donut">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle cx="60" cy="60" r="44" class="aiu-donut-track" />
              <circle v-for="seg in donut.segments" :key="seg.model" cx="60" cy="60" r="44" :stroke="modelColor(seg.model)"
                :stroke-dasharray="`${seg.length} ${donut.circumference}`" :stroke-dashoffset="-seg.offset" class="aiu-donut-seg" />
            </svg>
            <div><b>{{ usd(donut.total) }}</b><span>کل هزینه</span></div>
          </div>
          <ul class="aiu-share-list">
            <li v-for="seg in donut.segments" :key="seg.model">
              <i :style="{ background: modelColor(seg.model) }"></i>
              <span>{{ seg.model }}</span>
              <b>{{ num(seg.percent) }}٪</b>
              <small>{{ usd(seg.value) }}</small>
            </li>
          </ul>
        </template>
      </section>
    </div>

    <section class="aiu-card">
      <div class="aiu-card-head">
        <div><h4>گزارش گروه‌بندی‌شده</h4><small>روی هر ردیف بزنید تا زیرگروه‌ها باز شود</small></div>
        <div class="aiu-grouping">
          <div class="aiu-segment small">
            <button v-for="item in groupOptions" :key="item.key" type="button" :class="{ active: groupBy === item.key }" @click="setGroup(item.key)">{{ item.label }}</button>
          </div>
          <select v-model="thenBy" class="aiu-select" @change="loadGrouped">
            <option value="">بدون زیرگروه</option>
            <option v-for="item in groupOptions.filter((g) => g.key !== groupBy)" :key="item.key" :value="item.key">زیرگروه: {{ item.label }}</option>
          </select>
        </div>
      </div>
      <div class="aiu-table-wrap">
        <table class="aiu-table">
          <thead>
            <tr>
              <th>{{ groupOptions.find((g) => g.key === groupBy)?.label }}</th>
              <th>پرسش</th><th>درخواست</th><th>توکن ورودی</th><th>cache</th><th>توکن خروجی</th>
              <th>هزینه</th><th v-if="central">تمام‌شده</th><th v-if="central">سود</th><th>سهم</th><th>هر پرسش</th><th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in grouped?.rows || []" :key="String(row.key)">
              <tr class="aiu-row" :class="{ open: expanded.has(String(row.key)), expandable: row.children?.length }" @click="toggle(row)">
                <td>
                  <div class="aiu-cell-label">
                    <span v-if="row.children?.length" class="aiu-caret" aria-hidden="true">‹</span>
                    <i v-if="groupBy === 'model'" class="aiu-dot" :style="{ background: modelColor(row.key) }"></i>
                    <span v-else-if="groupBy === 'user' || groupBy === 'tenant'" class="aiu-avatar">{{ initial(row.label) }}</span>
                    <span v-else-if="groupBy === 'type'" class="aiu-badge" :class="`type-${row.key}`">{{ row.label }}</span>
                    <span v-if="groupBy !== 'type'">{{ groupLabel(groupBy, row) }}</span>
                  </div>
                </td>
                <td>{{ num(row.questions) }}</td><td>{{ num(row.calls) }}</td><td>{{ num(row.input_tokens) }}</td>
                <td>{{ num(row.cached_input_tokens) }} <small>{{ num(row.cache_rate) }}٪</small></td>
                <td>{{ num(row.output_tokens) }} <small v-if="row.reasoning_tokens">استدلال {{ num(row.reasoning_tokens) }}</small></td>
                <td><b class="aiu-money">{{ usd(row.charge_usd) }}</b></td>
                <td v-if="central">{{ usd(row.cost_usd) }}</td><td v-if="central" class="aiu-pos">{{ usd(row.profit_usd) }}</td>
                <td><div class="aiu-sharebar"><i :style="{ width: `${row.share}%` }"></i></div><small>{{ num(row.share) }}٪</small></td>
                <td>{{ usd(row.avg_per_question_usd) }}</td>
                <td><button type="button" class="aiu-chip-btn" @click.stop="drill(groupBy, row)">جزئیات</button></td>
              </tr>
              <template v-if="expanded.has(String(row.key))">
                <tr v-for="child in row.children" :key="`${row.key}-${child.key}`" class="aiu-child">
                  <td>
                    <div class="aiu-cell-label">
                      <i v-if="thenBy === 'model'" class="aiu-dot" :style="{ background: modelColor(child.key) }"></i>
                      <span>{{ groupLabel(thenBy, child) }}</span>
                    </div>
                  </td>
                  <td>{{ num(child.questions) }}</td><td>{{ num(child.calls) }}</td><td>{{ num(child.input_tokens) }}</td>
                  <td>{{ num(child.cached_input_tokens) }}</td><td>{{ num(child.output_tokens) }}</td>
                  <td>{{ usd(child.charge_usd) }}</td><td v-if="central">{{ usd(child.cost_usd) }}</td><td v-if="central">{{ usd(child.profit_usd) }}</td>
                  <td></td><td>{{ usd(child.avg_per_question_usd) }}</td><td></td>
                </tr>
              </template>
            </template>
            <tr v-if="!(grouped?.rows || []).length"><td :colspan="central ? 12 : 10" class="aiu-empty-cell">داده‌ای برای نمایش نیست.</td></tr>
          </tbody>
          <tfoot v-if="grouped?.totals && (grouped?.rows || []).length">
            <tr>
              <td>جمع کل</td><td>{{ num(grouped.totals.questions) }}</td><td>{{ num(grouped.totals.calls) }}</td><td>{{ num(grouped.totals.input_tokens) }}</td>
              <td>{{ num(grouped.totals.cached_input_tokens) }}</td><td>{{ num(grouped.totals.output_tokens) }}</td><td>{{ usd(grouped.totals.charge_usd) }}</td>
              <td v-if="central">{{ usd(grouped.totals.cost_usd) }}</td><td v-if="central">{{ usd(grouped.totals.profit_usd) }}</td><td>۱۰۰٪</td><td>{{ usd(grouped.totals.avg_per_question_usd) }}</td><td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section ref="requestsSection" class="aiu-card">
      <div class="aiu-card-head">
        <div><h4>ریز درخواست‌ها</h4><small v-if="requests?.meta">{{ num(requests.meta.total) }} درخواست به OpenAI</small></div>
        <span v-if="drillState" class="aiu-drill">{{ drillState.label }}<button type="button" aria-label="حذف فیلتر" @click="clearDrill">×</button></span>
      </div>
      <div class="aiu-table-wrap">
        <table class="aiu-table">
          <thead>
            <tr><th>زمان</th><th v-if="central">کلینیک</th><th v-if="canViewAll">کاربر</th><th>مدل</th><th>نوع</th><th>دور</th><th>ورودی</th><th>cache</th><th>خروجی</th><th>صوت</th><th>هزینه</th><th v-if="central">تمام‌شده</th><th>وضعیت</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in requests?.data || []" :key="row.id">
              <td><div class="aiu-when"><b>{{ dateLabel(row.jalali_date) }}</b><small>{{ timeLabel(row.created_at) }}</small></div></td>
              <td v-if="central">{{ tenantName(row.tenant_id) }}</td>
              <td v-if="canViewAll"><div class="aiu-cell-label"><span class="aiu-avatar sm">{{ initial(row.user_name) }}</span>{{ row.user_name || '—' }}</div></td>
              <td><div class="aiu-cell-label"><i class="aiu-dot" :style="{ background: modelColor(row.model) }"></i>{{ row.model }}</div></td>
              <td><span class="aiu-badge" :class="`type-${row.type}`">{{ row.type_label }}</span></td>
              <td>{{ num(row.round + 1) }}</td>
              <td>{{ num(row.input_tokens) }}</td><td>{{ num(row.cached_input_tokens) }}</td>
              <td>{{ num(row.output_tokens) }} <small v-if="row.reasoning_tokens">({{ num(row.reasoning_tokens) }})</small></td>
              <td>{{ row.audio_seconds !== null ? `${num(row.audio_seconds)} ثانیه` : row.audio_input_tokens ? num(row.audio_input_tokens) : '—' }}</td>
              <td><b class="aiu-money">{{ usd(row.charge_usd) }}</b></td>
              <td v-if="central">{{ usd(row.cost_usd) }}<small v-if="row.uncollected_usd"> · وصول‌نشده {{ usd(row.uncollected_usd) }}</small></td>
              <td><span class="aiu-status" :class="`is-${row.status}`">{{ row.status_label }}</span><small v-if="row.estimated" class="aiu-est" title="usage در پاسخ نبود و با سقف مجاز حساب شد">تخمینی</small></td>
            </tr>
            <tr v-if="!(requests?.data || []).length"><td :colspan="13" class="aiu-empty-cell">درخواستی نیست.</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="requests?.meta?.last_page > 1" class="aiu-pager">
        <button type="button" :disabled="requestsPage <= 1" @click="loadRequests(requestsPage - 1)">‹ قبلی</button>
        <span>صفحهٔ {{ num(requestsPage) }} از {{ num(requests.meta.last_page) }}</span>
        <button type="button" :disabled="requestsPage >= requests.meta.last_page" @click="loadRequests(requestsPage + 1)">بعدی ›</button>
      </div>
    </section>

    <section v-if="!central && canViewAll" class="aiu-card">
      <div class="aiu-card-head"><div><h4>شارژها و اصلاحیه‌های موجودی</h4><small>شارژ فقط توسط مدیریت مرکزی ثبت می‌شود</small></div></div>
      <div class="aiu-table-wrap">
        <table class="aiu-table">
          <thead><tr><th>زمان</th><th>نوع</th><th>مبلغ</th><th>موجودی پس از آن</th><th>شمارهٔ پیگیری</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr v-for="row in transactions" :key="row.id">
              <td>{{ jalaliDateTime(row.created_at) }}</td><td><span class="aiu-badge" :class="row.amount_usd < 0 ? 'type-neg' : 'type-pos'">{{ row.type_label }}</span></td>
              <td :class="row.amount_usd < 0 ? 'aiu-neg' : 'aiu-pos'"><b>{{ usd(row.amount_usd) }}</b></td><td>{{ usd(row.balance_after_usd) }}</td>
              <td>{{ row.reference || '—' }}</td><td>{{ row.note || '—' }}</td>
            </tr>
            <tr v-if="!transactions.length"><td colspan="6" class="aiu-empty-cell">هنوز شارژی ثبت نشده است.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </section>
</template>

<script setup>
import axios from 'axios';
import moment from 'moment-jalaali';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import JalaliDateInput from './JalaliDateInput.vue';

const props = defineProps({
  apiBase: { type: String, required: true },
  central: { type: Boolean, default: false },
});

// پالت دسته‌ای اعتبارسنجی‌شده؛ رنگ هر مدل از جایگاه ثابتش در کاتالوگ می‌آید، نه از رتبه.
const palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#6250d6'];
const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
const presets = [
  { key: 'today', label: 'امروز' }, { key: 'yesterday', label: 'دیروز' }, { key: 'this_week', label: 'این هفته' },
  { key: 'this_month', label: 'این ماه' }, { key: 'last_month', label: 'ماه قبل' }, { key: 'last_30', label: '۳۰ روز اخیر' }, { key: 'all', label: 'همه' },
];
const icons = {
  money: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M14.5 9.5c-.5-.9-1.4-1.3-2.5-1.3-1.5 0-2.5.8-2.5 1.9 0 2.6 5 1.4 5 4 0 1.1-1 1.9-2.5 1.9-1.1 0-2.1-.5-2.6-1.4M12 6.5v1.7m0 7.6v1.7"/></svg>',
  chat: '<svg viewBox="0 0 24 24"><path d="M5 18.5V7a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H8l-3 2.5Z"/></svg>',
  api: '<svg viewBox="0 0 24 24"><path d="m8 8-4 4 4 4m8-8 4 4-4 4M14 5l-4 14"/></svg>',
  input: '<svg viewBox="0 0 24 24"><path d="M12 4v12m0 0-4-4m4 4 4-4M5 20h14"/></svg>',
  output: '<svg viewBox="0 0 24 24"><path d="M12 20V8m0 0-4 4m4-4 4 4M5 4h14"/></svg>',
  avg: '<svg viewBox="0 0 24 24"><path d="M4 19h16M7 15l3-4 3 2 4-6"/></svg>',
  profit: '<svg viewBox="0 0 24 24"><path d="M4 17l6-6 4 4 6-7M14 8h6v6"/></svg>',
};

const filters = reactive({ from: '', to: '', user_ids: [], models: [], types: [], tenant_id: '' });
const preset = ref('this_month');
const groupBy = ref('day');
const thenBy = ref('');
const summary = ref(null);
const grouped = ref(null);
const trend = ref(null);
const requests = ref(null);
const requestsPage = ref(1);
const options = ref({});
const balance = ref(null);
const transactions = ref([]);
const loading = ref(false);
const exporting = ref(false);
const error = ref('');
const expanded = ref(new Set());
const drillState = ref(null);
const requestsSection = ref(null);
const openMenu = ref('');
const hoverIndex = ref(null);
const canViewAll = computed(() => props.central || options.value.can_view_all === true);

const groupOptions = computed(() => [
  { key: 'day', label: 'روز' }, { key: 'week', label: 'هفته' }, { key: 'month', label: 'ماه' },
  ...(canViewAll.value ? [{ key: 'user', label: 'کاربر' }] : []),
  { key: 'model', label: 'مدل' }, { key: 'type', label: 'نوع درخواست' }, { key: 'conversation', label: 'گفت‌وگو' },
  ...(props.central ? [{ key: 'tenant', label: 'کلینیک' }] : []),
]);
const filterMenus = computed(() => [
  ...(canViewAll.value ? [{ key: 'user_ids', label: 'کاربر', items: options.value.users || [] }] : []),
  { key: 'models', label: 'مدل', items: options.value.models || [] },
  { key: 'types', label: 'نوع درخواست', items: options.value.types || [] },
]);

const errorText = (e) => e?.response?.data?.message || 'دریافت گزارش انجام نشد.';
const fa = (value) => String(value ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const num = (value) => Number(value || 0).toLocaleString('fa-IR', { maximumFractionDigits: 1 });
const compact = (value) => {
  const n = Number(value || 0);
  if (n >= 1e6) return `${(n / 1e6).toLocaleString('fa-IR', { maximumFractionDigits: 1 })} میلیون`;
  if (n >= 1e4) return `${(n / 1e3).toLocaleString('fa-IR', { maximumFractionDigits: 1 })} هزار`;
  return num(n);
};
function usd(value) {
  const n = Number(value || 0);
  const digits = Math.abs(n) > 0 && Math.abs(n) < 1 ? 4 : 2;
  return `${n.toLocaleString('fa-IR', { minimumFractionDigits: digits, maximumFractionDigits: digits })} $`;
}
const usdShort = (value) => `${Number(value || 0).toLocaleString('fa-IR', { maximumFractionDigits: value < 1 ? 3 : 1 })} $`;
const jDate = (m) => m.clone().locale('en').format('jYYYY-jMM-jDD');
const dateLabel = (value) => fa(String(value || '').replaceAll('-', '/'));
const timeLabel = (value) => fa(moment(value).format('HH:mm'));
const jalaliDateTime = (value) => fa(moment(value).locale('en').format('jYYYY/jMM/jDD · HH:mm'));
const monthLabel = (value) => { const [y, m] = String(value || '').split('-'); return m ? `${monthNames[Number(m) - 1]} ${fa(y)}` : value; };
const longDate = (value) => { const [y, m, d] = String(value).split('-'); return `${fa(Number(d))} ${monthNames[Number(m) - 1]} ${fa(y)}`; };
const tenantName = (id) => (options.value.tenants || []).find((t) => t.id === id)?.name || id;
const initial = (value) => String(value || '؟').trim().charAt(0) || '؟';
const modelIndex = computed(() => new Map((options.value.models || []).map((m, i) => [m.id, i])));
const modelColor = (id) => palette[(modelIndex.value.get(id) ?? palette.length - 1) % palette.length];

const rangeLabel = computed(() => {
  if (!filters.from && !filters.to) return 'کل دوره';
  if (filters.from === filters.to) return longDate(filters.from);
  return `${filters.from ? longDate(filters.from) : 'ابتدا'} تا ${filters.to ? longDate(filters.to) : 'امروز'}`;
});
const balanceTone = computed(() => (props.central || !balance.value ? '' : balance.value.blocked ? 'is-blocked' : balance.value.low ? 'is-low' : 'is-ok'));

const kpis = computed(() => {
  const r = summary.value?.range || {};
  return [
    { label: 'هزینه', value: usd(r.charge_usd), icon: icons.money, tone: 'blue' },
    ...(props.central ? [{ label: 'تمام‌شده', value: usd(r.cost_usd), icon: icons.money, tone: 'slate' }, { label: 'سود', value: usd(r.profit_usd), icon: icons.profit, tone: 'green' }] : []),
    { label: 'پرسش', value: num(r.questions), icon: icons.chat, tone: 'violet' },
    { label: 'درخواست API', value: num(r.calls), icon: icons.api, tone: 'slate' },
    { label: 'توکن ورودی', value: compact(r.input_tokens), hint: `${num(r.cache_rate)}٪ از cache`, icon: icons.input, tone: 'teal' },
    { label: 'توکن خروجی', value: compact(r.output_tokens), hint: r.reasoning_tokens ? `${compact(r.reasoning_tokens)} استدلال` : '', icon: icons.output, tone: 'amber' },
    { label: 'میانگین هر پرسش', value: usd(r.avg_per_question_usd), icon: icons.avg, tone: 'rose' },
  ];
});

function groupLabel(group, row) {
  if (row.key === null || row.key === undefined || row.key === '') return group === 'conversation' ? 'بدون گفت‌وگو' : 'نامشخص';
  if (group === 'day') return dateLabel(row.key);
  if (group === 'week') return `هفتهٔ ${dateLabel(row.key)}`;
  if (group === 'month') return monthLabel(row.key);
  if (group === 'model') return row.key;
  return row.label || String(row.key);
}

function applyPreset(key) {
  const now = moment();
  const ranges = {
    today: [now, now],
    yesterday: [now.clone().subtract(1, 'day'), now.clone().subtract(1, 'day')],
    this_week: [now.clone().subtract((now.day() + 1) % 7, 'day'), now],
    this_month: [now.clone().startOf('jMonth'), now],
    last_month: [now.clone().subtract(1, 'jMonth').startOf('jMonth'), now.clone().subtract(1, 'jMonth').endOf('jMonth')],
    last_30: [now.clone().subtract(29, 'day'), now],
  };
  preset.value = key;
  if (key === 'all') { filters.from = ''; filters.to = ''; }
  else { filters.from = jDate(ranges[key][0]); filters.to = jDate(ranges[key][1]); }
  loadAll();
}

function params(extra = {}) {
  return {
    from: filters.from || undefined, to: filters.to || undefined,
    user_ids: canViewAll.value && filters.user_ids.length ? filters.user_ids : undefined,
    models: filters.models.length ? filters.models : undefined,
    types: filters.types.length ? filters.types : undefined,
    tenant_id: props.central && filters.tenant_id ? filters.tenant_id : undefined,
    ...extra,
  };
}

async function loadAll() {
  loading.value = true; error.value = '';
  try {
    const tasks = [
      axios.get(`${props.apiBase}/usage/summary`, { params: params() }).then(({ data }) => { summary.value = data; }),
      axios.get(`${props.apiBase}/usage/options`, { params: props.central && filters.tenant_id ? { tenant_id: filters.tenant_id } : {} }).then(({ data }) => { options.value = data; }),
      loadGrouped(),
      loadTrend(),
      loadRequests(1),
    ];
    if (!props.central) {
      tasks.push(axios.get(`${props.apiBase}/balance`).then(({ data }) => { balance.value = data; }));
      tasks.push(axios.get(`${props.apiBase}/wallet/transactions`).then(({ data }) => { transactions.value = data.data || []; }).catch(() => { transactions.value = []; }));
    }
    await Promise.all(tasks);
  } catch (e) { error.value = errorText(e); }
  finally { loading.value = false; }
}

async function loadGrouped() {
  const { data } = await axios.get(`${props.apiBase}/usage/grouped`, { params: params({ group_by: groupBy.value, then_by: thenBy.value || undefined }) });
  grouped.value = data; expanded.value = new Set();
}

function rangeDays() {
  if (!filters.from || !filters.to) return null;
  return moment(filters.to, 'jYYYY-jMM-jDD').diff(moment(filters.from, 'jYYYY-jMM-jDD'), 'days') + 1;
}

async function loadTrend() {
  const days = rangeDays();
  const group = days !== null && days <= 120 ? 'day' : 'month';
  const { data } = await axios.get(`${props.apiBase}/usage/grouped`, { params: params({ group_by: group, then_by: 'model' }) });
  trend.value = { ...data, days };
  hoverIndex.value = null;
}

const trendModels = computed(() => [...new Set((trend.value?.rows || []).flatMap((row) => row.children.map((c) => c.key)))]
  .sort((a, b) => (modelIndex.value.get(a) ?? 99) - (modelIndex.value.get(b) ?? 99)));
const trendBars = computed(() => {
  if (!trend.value) return [];
  const byKey = new Map((trend.value.rows || []).map((row) => [String(row.key), row]));
  let keys = [...byKey.keys()];
  if (trend.value.group_by === 'day' && filters.from && filters.to) {
    keys = [];
    const cursor = moment(filters.from, 'jYYYY-jMM-jDD');
    const end = moment(filters.to, 'jYYYY-jMM-jDD');
    while (cursor.isSameOrBefore(end, 'day') && keys.length < 400) { keys.push(jDate(cursor)); cursor.add(1, 'day'); }
  }
  return keys.map((key) => {
    const row = byKey.get(key);
    const parts = trendModels.value.map((model) => ({ model, value: Number(row?.children.find((c) => c.key === model)?.charge_usd || 0) })).filter((p) => p.value > 0);
    return {
      key, total: Number(row?.charge_usd || 0), parts,
      label: trend.value.group_by === 'day' ? longDate(key) : monthLabel(key),
      short: trend.value.group_by === 'day' ? fa(Number(key.slice(8))) : monthLabel(key).split(' ')[0],
    };
  });
});
const trendMax = computed(() => Math.max(0, ...trendBars.value.map((b) => b.total)));
const niceMax = computed(() => {
  const max = trendMax.value;
  if (!max) return 1;
  const power = 10 ** Math.floor(Math.log10(max));
  const n = max / power;
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * power;
});
const yTicks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => Number((f * niceMax.value).toPrecision(6))));
const xStep = computed(() => Math.max(1, Math.ceil(trendBars.value.length / 14)));
const hoverBar = computed(() => (hoverIndex.value === null ? null : trendBars.value[hoverIndex.value] || null));
const tipStyle = computed(() => {
  const pct = ((hoverIndex.value + 0.5) / trendBars.value.length) * 100;
  return { right: `${pct}%`, transform: pct < 20 ? 'translateX(0)' : pct > 80 ? 'translateX(100%)' : 'translateX(50%)' };
});

const donut = computed(() => {
  const totals = new Map();
  for (const row of trend.value?.rows || []) for (const c of row.children) totals.set(c.key, (totals.get(c.key) || 0) + Number(c.charge_usd || 0));
  const total = [...totals.values()].reduce((a, b) => a + b, 0);
  const circumference = 2 * Math.PI * 44;
  const gap = totals.size > 1 ? 2 : 0;
  let offset = 0;
  const segments = trendModels.value.filter((m) => totals.get(m) > 0).map((model) => {
    const value = totals.get(model);
    const full = (value / total) * circumference;
    const seg = { model, value, percent: Math.round((value / total) * 1000) / 10, offset, length: Math.max(0.5, full - gap) };
    offset += full;
    return seg;
  });
  return { total, segments, circumference };
});

async function loadRequests(page = 1) {
  const { data } = await axios.get(`${props.apiBase}/usage/requests`, { params: params({ ...(drillState.value?.params || {}), page }) });
  requests.value = data; requestsPage.value = data.meta?.page || page;
}

function toggleMenu(key) { openMenu.value = openMenu.value === key ? '' : key; }
function onDocumentClick(event) { if (!event.target.closest?.('.aiu-pop')) openMenu.value = ''; }
function setGroup(key) { groupBy.value = key; if (thenBy.value === key) thenBy.value = ''; loadGrouped().catch((e) => { error.value = errorText(e); }); }
function toggle(row) {
  if (!row.children?.length) return;
  const key = String(row.key); const next = new Set(expanded.value);
  next.has(key) ? next.delete(key) : next.add(key); expanded.value = next;
}
function drill(group, row) {
  const key = row.key;
  const map = {
    day: { from: key, to: key },
    week: { from: key, to: jDate(moment(key, 'jYYYY-jMM-jDD').add(6, 'day')) },
    month: { month: key },
    user: { user_ids: [key] }, model: { models: [key] }, type: { types: [key] },
    conversation: key ? { conversation_id: key } : {}, tenant: { tenant_id: key },
  };
  drillState.value = { label: `${groupOptions.value.find((g) => g.key === group)?.label}: ${groupLabel(group, row)}`, params: map[group] || {} };
  loadRequests(1).then(() => requestsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })).catch((e) => { error.value = errorText(e); });
}
function clearDrill() { drillState.value = null; loadRequests(1).catch((e) => { error.value = errorText(e); }); }

async function exportCsv() {
  exporting.value = true;
  try {
    const { data } = await axios.get(`${props.apiBase}/usage/export`, { params: params(drillState.value?.params || {}), responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url; link.download = `ai-usage-${jDate(moment())}.csv`; link.click();
    URL.revokeObjectURL(url);
  } catch (e) { error.value = errorText(e); }
  finally { exporting.value = false; }
}

onMounted(() => { document.addEventListener('click', onDocumentClick); applyPreset('this_month'); });
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<style scoped>
.aiu {
  --aiu-bg: #f3f6fb; --aiu-card: #fff; --aiu-line: #e6ebf3; --aiu-line-2: #eef2f7;
  --aiu-ink: #0f172a; --aiu-ink-2: #475569; --aiu-muted: #8a97ab; --aiu-soft: #f7f9fc;
  --aiu-brand: #2563eb; --aiu-brand-soft: #eaf1ff; --aiu-shadow: 0 1px 2px rgba(15,23,42,.04), 0 10px 30px rgba(15,23,42,.06);
  display: grid; gap: 16px; min-height: calc(100vh - 150px); padding: 20px; border-radius: 22px;
  background: var(--aiu-bg); color: var(--aiu-ink); font-size: 12px;
}
.aiu svg { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* سربرگ */
.aiu-hero { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 22px; padding: 24px 26px; overflow: hidden; border-radius: 20px; background: linear-gradient(120deg, #1e3a8a 0%, #2b55c6 52%, #0e7490 100%); color: #fff; box-shadow: 0 18px 40px rgba(30,58,138,.22); }
.aiu-hero::before, .aiu-hero::after { content: ''; position: absolute; border-radius: 50%; background: rgba(255,255,255,.07); pointer-events: none; }
.aiu-hero::before { width: 260px; height: 260px; top: -120px; left: 18%; }
.aiu-hero::after { width: 180px; height: 180px; bottom: -110px; left: -40px; }
.aiu-hero.is-low { background: linear-gradient(120deg, #1e3a8a 0%, #2b55c6 55%, #b45309 100%); }
.aiu-hero.is-blocked { background: linear-gradient(120deg, #1e293b 0%, #7f1d1d 60%, #b91c1c 100%); }
.aiu-hero-main { position: relative; flex: 1 1 260px; display: grid; gap: 6px; }
.aiu-hero-kicker { display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,.14); font-size: 11px; font-weight: 800; }
.aiu-hero-kicker svg { width: 14px; height: 14px; fill: currentColor; stroke: none; }
.aiu-hero h2 { margin: 2px 0 0; font-size: 22px; font-weight: 900; letter-spacing: -.3px; }
.aiu-hero p { margin: 0; color: rgba(255,255,255,.82); font-size: 13px; }
.aiu-hero-balance { position: relative; display: grid; gap: 4px; min-width: 230px; padding: 14px 18px; border: 1px solid rgba(255,255,255,.2); border-radius: 16px; background: rgba(255,255,255,.1); backdrop-filter: blur(6px); }
.aiu-hero-balance > span { color: rgba(255,255,255,.8); font-weight: 800; }
.aiu-hero-balance strong { font-size: 28px; font-weight: 900; letter-spacing: -.5px; direction: rtl; }
.aiu-hero-meta { display: grid; gap: 6px; }
.aiu-hero-meta small { color: rgba(255,255,255,.78); font-size: 11px; }
.aiu-pill { display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 3px 10px 3px 4px; border-radius: 999px; background: rgba(255,255,255,.18); font-style: normal; font-weight: 800; font-size: 11px; }
.aiu-pill i { display: grid; place-items: center; width: 18px; height: 18px; border-radius: 50%; background: #22c55e; color: #fff; font-style: normal; font-size: 11px; }
.aiu-pill.is-low i { background: #f59e0b; }
.aiu-pill.is-blocked i { background: #ef4444; }
.aiu-icon-btn { position: relative; align-self: flex-start; display: grid; place-items: center; width: 40px; height: 40px; border: 1px solid rgba(255,255,255,.25); border-radius: 12px; background: rgba(255,255,255,.12); color: #fff; cursor: pointer; }
.aiu-icon-btn svg { width: 18px; height: 18px; }
.aiu-icon-btn:disabled { cursor: wait; }
.spin { animation: aiu-spin 1s linear infinite; }
@keyframes aiu-spin { to { transform: rotate(360deg); } }
.aiu-alert { margin: -4px 0 0; padding: 11px 14px; border: 1px solid #fecaca; border-radius: 12px; background: #fef2f2; color: #991b1b; font-weight: 800; }

/* کارت‌های دوره */
.aiu-periods { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.aiu-period { --tone: #2563eb; --tone-soft: #eaf1ff; position: relative; display: grid; gap: 8px; padding: 16px; overflow: hidden; border: 1px solid var(--aiu-line); border-radius: 16px; background: var(--aiu-card); color: inherit; font-family: inherit; text-align: right; cursor: pointer; box-shadow: var(--aiu-shadow); transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
.aiu-period::before { content: ''; position: absolute; inset: 0 0 auto; height: 3px; background: var(--tone); opacity: .85; }
.aiu-period:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(15,23,42,.1); }
.aiu-period.active { border-color: var(--tone); box-shadow: 0 0 0 3px var(--tone-soft), var(--aiu-shadow); }
.tone-today { --tone: #2563eb; --tone-soft: #e3ecff; }
.tone-yesterday { --tone: #64748b; --tone-soft: #eef2f6; }
.tone-this_week { --tone: #0d9488; --tone-soft: #dcf5f2; }
.tone-this_month { --tone: #7c3aed; --tone-soft: #efe7ff; }
.tone-last_month { --tone: #d97706; --tone-soft: #fdf0dc; }
.aiu-period-top { display: flex; align-items: center; gap: 8px; color: var(--aiu-ink-2); font-weight: 900; }
.aiu-period-icon { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 10px; background: var(--tone-soft); color: var(--tone); }
.aiu-period-icon svg { width: 16px; height: 16px; }
.aiu-period strong { font-size: 21px; font-weight: 900; letter-spacing: -.4px; }
.aiu-tokens { display: flex; flex-wrap: wrap; gap: 6px; }
.aiu-tokens span { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 8px; background: var(--aiu-soft); color: var(--aiu-ink-2); font-size: 11px; font-weight: 700; }
.aiu-tokens b { color: var(--tone); }
.aiu-period small { color: var(--aiu-muted); font-size: 11px; }
.aiu-period-profit { color: #047857 !important; font-weight: 800; }

/* نوار فیلتر */
.aiu-toolbar { position: relative; z-index: 6; display: grid; gap: 12px; padding: 14px 16px; border: 1px solid var(--aiu-line); border-radius: 16px; background: var(--aiu-card); box-shadow: var(--aiu-shadow); }
.aiu-toolbar-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.aiu-segment { display: inline-flex; flex-wrap: wrap; gap: 4px; width: fit-content; padding: 4px; border-radius: 12px; background: var(--aiu-soft); border: 1px solid var(--aiu-line-2); }
.aiu-segment button { height: 32px; padding: 0 13px; border: 0; border-radius: 9px; background: transparent; color: var(--aiu-ink-2); font-family: inherit; font-weight: 800; cursor: pointer; transition: background .15s, color .15s; }
.aiu-segment button:hover { color: var(--aiu-ink); }
.aiu-segment button.active { background: var(--aiu-card); color: var(--aiu-brand); box-shadow: 0 1px 2px rgba(15,23,42,.08), 0 2px 8px rgba(37,99,235,.12); }
.aiu-segment.small button { height: 28px; padding: 0 10px; font-size: 11px; }
.aiu-dates { display: flex; align-items: center; gap: 8px; color: var(--aiu-muted); font-weight: 800; }
.aiu-dates > :deep(div) { width: 150px; }
:deep(.aiu-date), .aiu-select { height: 38px; width: 100%; min-width: 0; padding: 0 12px; border: 1px solid var(--aiu-line); border-radius: 10px; background: var(--aiu-card); color: var(--aiu-ink); font-family: inherit; font-size: 12px; outline: none; }
.aiu-select { width: auto; min-width: 150px; cursor: pointer; }
:deep(.aiu-date):focus, .aiu-select:focus { border-color: var(--aiu-brand); box-shadow: 0 0 0 3px var(--aiu-brand-soft); }
.aiu-pop { position: relative; }
.aiu-pop-btn { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 12px; border: 1px dashed #c8d2e2; border-radius: 10px; background: var(--aiu-card); color: var(--aiu-ink-2); font-family: inherit; font-weight: 800; cursor: pointer; }
.aiu-pop-btn svg { width: 14px; height: 14px; }
.aiu-pop-btn.active { border-style: solid; border-color: var(--aiu-brand); background: var(--aiu-brand-soft); color: var(--aiu-brand); }
.aiu-pop-btn b { display: grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--aiu-brand); color: #fff; font-size: 10px; }
.aiu-pop-panel { position: absolute; top: calc(100% + 6px); right: 0; z-index: 20; display: grid; gap: 2px; width: 240px; max-height: 300px; padding: 8px; overflow: auto; border: 1px solid var(--aiu-line); border-radius: 14px; background: var(--aiu-card); box-shadow: 0 20px 44px rgba(15,23,42,.16); }
.aiu-pop-panel label { display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 9px; color: var(--aiu-ink); cursor: pointer; }
.aiu-pop-panel label:hover { background: var(--aiu-soft); }
.aiu-pop-panel input { accent-color: var(--aiu-brand); }
.aiu-pop-panel em { padding: 10px; color: var(--aiu-muted); font-style: normal; }
.aiu-pop-foot { display: flex; justify-content: space-between; gap: 6px; margin-top: 4px; padding-top: 8px; border-top: 1px solid var(--aiu-line-2); }
.aiu-pop-foot button { height: 30px; padding: 0 12px; border: 0; border-radius: 8px; background: var(--aiu-soft); color: var(--aiu-ink-2); font-family: inherit; font-weight: 800; cursor: pointer; }
.aiu-pop-foot button.primary { background: var(--aiu-brand); color: #fff; }
.aiu-toolbar-actions { display: flex; gap: 8px; margin-inline-start: auto; }
.aiu-btn { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 16px; border: 0; border-radius: 10px; background: var(--aiu-brand); color: #fff; font-family: inherit; font-weight: 900; cursor: pointer; white-space: nowrap; box-shadow: 0 6px 16px rgba(37,99,235,.24); }
.aiu-btn svg { width: 16px; height: 16px; }
.aiu-btn.ghost { border: 1px solid var(--aiu-line); background: var(--aiu-card); color: var(--aiu-ink); box-shadow: none; }
.aiu-btn:disabled { opacity: .55; cursor: wait; }
.aiu-error { margin: 0; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #b91c1c; font-weight: 800; }

/* شاخص‌های بازه */
.aiu-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 12px; }
.aiu-kpi { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid var(--aiu-line); border-radius: 14px; background: var(--aiu-card); box-shadow: var(--aiu-shadow); }
.aiu-kpi > div { display: grid; gap: 2px; min-width: 0; }
.aiu-kpi span { color: var(--aiu-muted); font-weight: 800; font-size: 11px; }
.aiu-kpi b { font-size: 17px; font-weight: 900; letter-spacing: -.3px; white-space: nowrap; }
.aiu-kpi small { color: var(--aiu-muted); font-size: 10px; }
.aiu-kpi-icon { flex: none; display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; }
.aiu-kpi-icon :deep(svg) { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.aiu-kpi-icon.blue { background: #e3ecff; color: #2563eb; } .aiu-kpi-icon.violet { background: #efe7ff; color: #7c3aed; }
.aiu-kpi-icon.slate { background: #eef2f6; color: #475569; } .aiu-kpi-icon.teal { background: #dcf5f2; color: #0d9488; }
.aiu-kpi-icon.amber { background: #fdf0dc; color: #d97706; } .aiu-kpi-icon.rose { background: #fde7ef; color: #db2777; }
.aiu-kpi-icon.green { background: #dcfce7; color: #047857; }

/* کارت‌ها و نمودارها */
.aiu-charts { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(260px, 1fr); gap: 16px; }
.aiu-card { display: grid; gap: 14px; min-width: 0; padding: 18px; border: 1px solid var(--aiu-line); border-radius: 18px; background: var(--aiu-card); box-shadow: var(--aiu-shadow); }
.aiu-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.aiu-card-head h4 { margin: 0; font-size: 15px; font-weight: 900; }
.aiu-card-head small { color: var(--aiu-muted); font-size: 11px; }
.aiu-legend { display: flex; flex-wrap: wrap; gap: 6px; }
.aiu-legend span { display: inline-flex; align-items: center; gap: 6px; padding: 4px 9px; border-radius: 999px; background: var(--aiu-soft); color: var(--aiu-ink-2); font-weight: 700; font-size: 11px; }
.aiu-legend i, .aiu-share-list i, .aiu-tip i, .aiu-dot { flex: none; display: inline-block; width: 9px; height: 9px; border-radius: 3px; }
.aiu-plot { display: grid; grid-template-columns: 54px 1fr; grid-template-rows: 230px auto; column-gap: 8px; }
.aiu-y { position: relative; }
.aiu-y span { position: absolute; left: 0; right: 0; transform: translateY(50%); color: var(--aiu-muted); font-size: 10px; text-align: left; white-space: nowrap; }
.aiu-plot-area { position: relative; }
.aiu-grid { position: absolute; left: 0; right: 0; height: 0; border-top: 1px dashed var(--aiu-line); }
.aiu-grid:first-child { border-top: 1px solid #cfd8e5; }
.aiu-bars { position: absolute; inset: 0; display: grid; grid-template-columns: repeat(var(--bars), minmax(0, 1fr)); align-items: end; gap: 0; }
.aiu-bar { position: relative; display: flex; align-items: flex-end; justify-content: center; height: 100%; padding: 0 18%; cursor: default; }
.aiu-bar::before { content: ''; position: absolute; inset: 0 2px; border-radius: 8px; background: var(--aiu-brand-soft); opacity: 0; transition: opacity .12s; }
.aiu-bar.hovered::before { opacity: .7; }
.aiu-bar-stack { position: relative; display: flex; flex-direction: column-reverse; gap: 2px; width: 100%; max-width: 34px; min-height: 0; }
.aiu-bar-stack i { display: block; flex: none; min-height: 2px; }
.aiu-bar-stack i:last-child { border-radius: 4px 4px 0 0; }
.aiu-x { grid-column: 2; display: grid; grid-template-columns: repeat(var(--bars), minmax(0, 1fr)); padding-top: 6px; }
.aiu-x span { color: var(--aiu-muted); font-size: 10px; text-align: center; white-space: nowrap; }
.aiu-tip { position: absolute; top: 4px; z-index: 4; display: grid; gap: 5px; min-width: 170px; padding: 10px 12px; border-radius: 12px; background: #0f172a; color: #e2e8f0; font-size: 11px; transform: translateX(50%); pointer-events: none; box-shadow: 0 14px 30px rgba(15,23,42,.3); }
.aiu-tip strong { color: #fff; font-size: 12px; }
.aiu-tip span { display: flex; align-items: center; gap: 6px; }
.aiu-tip b { margin-inline-start: auto; color: #fff; }
.aiu-tip-total { margin-top: 2px; padding-top: 6px; border-top: 1px solid rgba(255,255,255,.15); }
.aiu-empty { display: grid; place-items: center; gap: 8px; min-height: 200px; color: var(--aiu-muted); font-weight: 700; text-align: center; }
.aiu-empty svg { width: 34px; height: 34px; color: #c3cddc; }
.aiu-share-card { align-content: start; }
.aiu-donut { position: relative; display: grid; place-items: center; width: 170px; margin: 4px auto 0; }
.aiu-donut svg { width: 170px; height: 170px; transform: rotate(-90deg); }
.aiu-donut-track { fill: none; stroke: var(--aiu-line-2); stroke-width: 14; }
.aiu-donut-seg { fill: none; stroke-width: 14; transition: stroke-dasharray .3s; }
.aiu-donut > div { position: absolute; display: grid; gap: 2px; text-align: center; }
.aiu-donut b { font-size: 16px; font-weight: 900; }
.aiu-donut span { color: var(--aiu-muted); font-size: 11px; }
.aiu-share-list { display: grid; gap: 4px; margin: 0; padding: 0; list-style: none; }
.aiu-share-list li { display: grid; grid-template-columns: auto 1fr auto auto; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 10px; background: var(--aiu-soft); }
.aiu-share-list span { overflow: hidden; color: var(--aiu-ink); font-weight: 800; text-overflow: ellipsis; white-space: nowrap; direction: ltr; text-align: right; }
.aiu-share-list b { font-weight: 900; }
.aiu-share-list small { color: var(--aiu-muted); }

/* جدول‌ها */
.aiu-grouping { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.aiu-grouping .aiu-select { height: 36px; }
.aiu-table-wrap { max-height: 560px; overflow: auto; border: 1px solid var(--aiu-line-2); border-radius: 14px; }
.aiu-table { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--aiu-card); white-space: nowrap; }
.aiu-table th, .aiu-table td { padding: 11px 12px; border-bottom: 1px solid var(--aiu-line-2); text-align: right; vertical-align: middle; }
.aiu-table th { position: sticky; top: 0; z-index: 1; background: var(--aiu-soft); color: var(--aiu-muted); font-size: 11px; font-weight: 800; }
.aiu-table td small { margin-inline-start: 4px; color: var(--aiu-muted); font-size: 10px; }
.aiu-table tbody tr:hover td { background: #f8fbff; }
.aiu-table tfoot td { position: sticky; bottom: 0; border-top: 1px solid var(--aiu-line); border-bottom: 0; background: #f1f5fb; font-weight: 900; }
.aiu-row.expandable { cursor: pointer; }
.aiu-row.open td { background: #f5f9ff; }
.aiu-child td { background: #fbfcfe; color: var(--aiu-ink-2); font-size: 11.5px; }
.aiu-child td:first-child { padding-right: 40px; }
.aiu-cell-label { display: flex; align-items: center; gap: 8px; font-weight: 800; }
.aiu-caret { display: inline-block; width: 12px; color: var(--aiu-muted); font-size: 15px; transition: transform .15s; }
.aiu-row.open .aiu-caret { transform: rotate(-90deg); color: var(--aiu-brand); }
.aiu-avatar { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 9px; background: linear-gradient(135deg, #dbe7ff, #e9ddff); color: #3b4ba8; font-size: 12px; font-weight: 900; }
.aiu-avatar.sm { width: 24px; height: 24px; border-radius: 8px; font-size: 11px; }
.aiu-money { font-weight: 900; }
.aiu-sharebar { display: inline-block; width: 64px; height: 6px; overflow: hidden; border-radius: 99px; background: var(--aiu-line-2); vertical-align: middle; }
.aiu-sharebar i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #60a5fa, #2563eb); }
.aiu-chip-btn { height: 28px; padding: 0 12px; border: 1px solid var(--aiu-line); border-radius: 999px; background: var(--aiu-card); color: var(--aiu-brand); font-family: inherit; font-size: 11px; font-weight: 800; cursor: pointer; }
.aiu-chip-btn:hover { border-color: var(--aiu-brand); background: var(--aiu-brand-soft); }
.aiu-badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; }
.type-chat { background: #e3ecff; color: #1d4ed8; } .type-tool_round { background: #efe7ff; color: #6d28d9; }
.type-transcription { background: #dcf5f2; color: #0f766e; } .type-pos { background: #dcfce7; color: #047857; } .type-neg { background: #fee2e2; color: #b91c1c; }
.aiu-when { display: grid; gap: 1px; } .aiu-when small { margin: 0 !important; }
.aiu-status { display: inline-flex; padding: 3px 9px; border-radius: 999px; background: #dcfce7; color: #047857; font-size: 11px; font-weight: 800; }
.aiu-status.is-incomplete { background: #fef3c7; color: #92400e; }
.aiu-est { padding: 2px 6px; border-radius: 6px; background: #fef3c7; color: #92400e !important; }
.aiu-drill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 6px 5px 12px; border-radius: 999px; background: var(--aiu-brand-soft); color: var(--aiu-brand); font-weight: 800; }
.aiu-drill button { display: grid; place-items: center; width: 20px; height: 20px; border: 0; border-radius: 50%; background: var(--aiu-brand); color: #fff; font-size: 13px; cursor: pointer; }
.aiu-neg { color: #b91c1c; } .aiu-pos { color: #047857; }
.aiu-empty-cell { padding: 30px !important; color: var(--aiu-muted); text-align: center !important; }
.aiu-pager { display: flex; align-items: center; justify-content: center; gap: 12px; color: var(--aiu-ink-2); font-weight: 800; }
.aiu-pager button { height: 32px; padding: 0 14px; border: 1px solid var(--aiu-line); border-radius: 9px; background: var(--aiu-card); color: var(--aiu-ink); font-family: inherit; font-weight: 800; cursor: pointer; }
.aiu-pager button:disabled { opacity: .45; cursor: default; }

/* حالت شب (کل سلکتور داخل :global، چون Vue بخش بعد از :global را حذف می‌کند) */
:global(#app.dark .aiu) {
  --aiu-bg: #0b1222; --aiu-card: #111a2e; --aiu-line: #1f2b45; --aiu-line-2: #1a2540;
  --aiu-ink: #e2e8f0; --aiu-ink-2: #b6c2d6; --aiu-muted: #7f8ca3; --aiu-soft: #16213a;
  --aiu-brand: #5b8def; --aiu-brand-soft: rgba(91,141,239,.16); --aiu-shadow: 0 10px 30px rgba(0,0,0,.25);
}
:global(#app.dark .aiu .aiu-table tbody tr:hover td), :global(#app.dark .aiu .aiu-row.open td) { background: #15203a; }
:global(#app.dark .aiu .aiu-child td) { background: #0f1729; }
:global(#app.dark .aiu .aiu-table tfoot td) { background: #16213a; }
:global(#app.dark .aiu .aiu-grid:first-child) { border-top-color: #2a3a5c; }
:global(#app.dark .aiu .aiu-alert) { border-color: #5b1d24; background: #2a1116; color: #fecaca; }

@media (max-width: 1200px) { .aiu-periods { grid-template-columns: repeat(3, minmax(0, 1fr)); } .aiu-charts { grid-template-columns: 1fr; } }
@media (max-width: 760px) {
  .aiu { padding: 12px; gap: 12px; }
  .aiu-hero { padding: 18px; } .aiu-hero-balance { flex: 1 1 100%; }
  .aiu-periods { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .aiu-toolbar-actions { margin: 0; width: 100%; } .aiu-toolbar-actions .aiu-btn { flex: 1; justify-content: center; }
  .aiu-dates { width: 100%; } .aiu-dates > :deep(div) { flex: 1; width: auto; }
  .aiu-plot { grid-template-columns: 44px 1fr; grid-template-rows: 190px auto; }
}
</style>
