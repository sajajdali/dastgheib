<template>
  <div class="rb" dir="rtl">
    <!-- فیلتر گزارشات -->
    <div class="rb-card rb-mb">
      <div class="rb-head" @click="filtersOpen = !filtersOpen">
        <div class="rb-icon">⛃</div>
        <div class="rb-title">فیلتر گزارشات</div>
        <div class="rb-sub">انتخاب فیلدها و شرط‌های گزارش</div>
        <div class="rb-headright">
          <div class="rb-badge">{{ pd(cols.length) }} فیلد در نمایش</div>
          <div class="rb-collapse">{{ filtersOpen ? 'بستن ⌃' : 'باز کردن ⌄' }}</div>
        </div>
      </div>

      <div v-if="filtersOpen" class="rb-body">
        <div class="rb-secrow">
          <div class="rb-h2">تعریف گزارش</div>
          <div class="rb-line"></div>
          <div class="rb-btn rb-btn--ghostTeal" @click.stop="selectAll">انتخاب همه نمایش‌ها</div>
          <div class="rb-btn rb-btn--ghost" @click.stop="clearAll">پاک کردن</div>
        </div>

        <div v-for="g in groups" :key="g.title" class="rb-group">
          <div class="rb-grouphead"><span class="rb-bar"></span><span>{{ g.title }}</span></div>
          <div class="rb-grid">
            <div v-for="f in g.fields" :key="f.id" class="rb-field">
              <div class="rb-fieldhead">
                <div class="rb-label">{{ f.label }}</div>
                <div class="rb-showtoggle" @click="toggleShow(f.id)">
                  <span v-if="show[f.id]" class="rb-check rb-check--on">✓</span>
                  <span v-else class="rb-check"></span>
                  <span class="rb-showtext">نمایش</span>
                </div>
              </div>

              <input v-if="f.type === 'text'" class="rb-input" :placeholder="f.ph || ''"
                     v-model="vals[f.id]" :inputmode="['discount','debt','deposit'].includes(f.id) ? 'numeric' : undefined" @input="['discount','debt','deposit'].includes(f.id) && formatMoney(f.id)" />

              <select v-else-if="f.type === 'select'" class="rb-input rb-select" v-model="vals[f.id]">
                <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
              </select>

              <div v-else-if="f.type === 'compare'" class="rb-compare">
                <select class="rb-compare-op" v-model="comparators[f.id]" :title="comparators[f.id] === 'lt' ? 'کمتر از' : comparators[f.id] === 'gt' ? 'بیشتر از' : 'دقیقاً برابر'"><option value="lt">کمتر</option><option value="gt">بیشتر</option><option value="eq">برابر</option></select>
                <input class="rb-input" :placeholder="f.ph || ''" v-model="vals[f.id]" @input="formatMoney(f.id)" inputmode="numeric" />
              </div>

              <div v-else-if="isPicker(f)" class="rb-picker" @click="openField(f)">
                <div v-if="f.type === 'date'" class="rb-date-summary" :class="{ 'rb-muted': !date.from && !date.to }">
                  <template v-if="date.from || date.to">
                    <span><small>از</small>{{ date.from ? fmt(date.from) : '—' }}</span>
                    <i></i>
                    <span><small>تا</small>{{ date.to ? fmt(date.to) : '—' }}</span>
                  </template>
                  <span v-else>انتخاب بازه تاریخ تولد</span>
                </div>
                <span v-else class="rb-pickertext" :class="{ 'rb-muted': selectedText(f).muted }">{{ selectedText(f).text }}</span>
                <button v-if="f.type === 'date' && (date.from || date.to)" type="button" class="rb-picker-clear"
                        title="پاک کردن بازه" @click.stop="clearDateRange">×</button>
                <span class="rb-pickericon">{{ f.type === 'date' ? '▦' : (f.type === 'range' ? '⇿' : '⌄') }}</span>
              </div>

              <div v-else class="rb-display">
                <span>فقط نمایشی</span><span class="rb-dim">⌁</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- قالب‌های مستقل گزارش‌ساز -->
    <div class="rb-card rb-builder-card rb-mb">
      <div class="rb-builder-head">
        <div class="rb-report-range-copy">
          <div class="rb-icon">▤</div>
          <div>
            <div class="rb-title">گزارش‌ساز</div>
            <div class="rb-sub">قالب‌های ذخیره‌شده — با انتخاب هر قالب، فیلتر گزارشات باز و فیلدهای آن انتخاب می‌شوند</div>
          </div>
        </div>
        <div class="rb-btn rb-btn--dashed rb-push" role="button" tabindex="0" @click.stop="newOpen = true" @keydown.enter.stop="newOpen = true">
          <span class="rb-plus">＋</span><span>اضافه به گزارش‌ساز</span>
        </div>
      </div>
      <div class="rb-presets">
        <div v-for="p in visiblePresets" :key="p.id" class="rb-preset"
             :class="{ 'rb-preset--on': activePreset === p.id }" @click="applyPreset(p)">
          <span>{{ p.label }}</span>
          <span class="rb-presetcount">{{ pd(p.fields.length) }} فیلد</span>
          <span class="rb-presetx" @click.stop="askRemove(p)">✕</span>
        </div>
      </div>
    </div>

    <!-- بازه زمانی اجباری گزارش -->
    <div class="rb-card rb-report-range-card rb-mb">
      <div class="rb-report-range-copy">
        <div class="rb-icon">▦</div>
        <div>
          <div class="rb-title">بازه گزارش <span class="rb-required">اجباری</span></div>
          <div class="rb-sub">فقط اطلاعات و نوبت‌های داخل این بازه در گزارش محاسبه می‌شوند.</div>
        </div>
      </div>
      <div class="rb-picker rb-report-range-picker" role="button" tabindex="0" @click="openReportDate" @keydown.enter.prevent="openReportDate">
        <div class="rb-date-summary">
          <span><small>از تاریخ</small>{{ fmt(reportDate.from) }}</span><i></i>
          <span><small>تا تاریخ</small>{{ fmt(reportDate.to) }}</span>
        </div>
        <span class="rb-pickericon">▦</span>
      </div>
    </div>

    <!-- نمونه گزارش -->
    <div class="rb-card rb-card--rel">
      <div class="rb-reporthead">
        <div class="rb-title">نمونه گزارش</div>
        <div class="rb-sub">
          {{ cols.length ? pd(totalRows) + ' رکورد · ' + pd(cols.length) + ' ستون' : 'بدون ستون' }}
          · برای نهایی شدن و مشاهدهٔ گزارش، روی «ایجاد گزارش» بزنید
        </div>
        <div class="rb-btn rb-btn--teal rb-push" :style="{ opacity: busy || !resultRows.length ? .6 : 1 }" @click="exportExcel">
          <span v-if="busy === 'export'" class="rb-spin rb-spin--sm"></span>
          <span v-else class="rb-dl">⤓</span>
          <span>{{ busy === 'export' ? 'در حال آماده‌سازی…' : 'خروجی اکسل' }}</span>
        </div>
        <div class="rb-btn rb-btn--dark" :style="{ opacity: busy ? .6 : 1 }" @click="buildReport">
          <span v-if="busy === 'build'" class="rb-spin rb-spin--sm"></span>
          <span>{{ busy === 'build' ? 'در حال ساخت…' : 'ایجاد گزارش' }}</span>
        </div>
      </div>

      <div v-if="busy" class="rb-overlay">
        <div class="rb-loader">
          <div class="rb-loader-track"></div>
          <div class="rb-loader-arc"></div>
          <div class="rb-loader-dot"></div>
        </div>
        <div class="rb-loadertext">
          <div class="rb-loadertitle">{{ busy === 'export' ? 'در حال ساخت فایل اکسل' : 'در حال ساخت گزارش' }}</div>
          <div class="rb-sub">{{ busyStep }}</div>
        </div>
        <div class="rb-progress"><div class="rb-progress-fill" :style="{ width: reportProgress + '%' }"></div></div>
        <div class="rb-progress-number">{{ pd(reportProgress) }}٪</div>
      </div>

      <div v-if="reportError" class="rb-report-error" role="alert">{{ reportError }}</div>
      <div v-if="builtReportDate" class="rb-built-range">
        بازه گزارش: از تاریخ <strong>{{ fmt(builtReportDate.from) }}</strong> تا تاریخ <strong>{{ fmt(builtReportDate.to) }}</strong>
      </div>

      <template v-if="cols.length">
        <div class="rb-tablewrap">
          <table class="rb-table">
            <thead>
              <tr>
                <th class="rb-thnum">#</th>
                <th v-for="c in cols" :key="c.key">{{ c.label }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rows" :key="r.no">
                <td class="rb-tdnum">{{ r.no }}</td>
                <td v-for="(cell, i) in r.cells" :key="i">{{ cell }}</td>
              </tr>
            </tbody>
            <tfoot v-if="cols.some(column => column.key === 'amount')">
              <tr class="rb-total-row"><td class="rb-tdnum">جمع کل</td><td v-for="c in cols" :key="c.key">{{ c.key === 'amount' ? formatReportTotal(reportTotals.amount) : '—' }}</td></tr>
            </tfoot>
          </table>
        </div>

        <div class="rb-pager">
          <div class="rb-sub rb-nowrap">
            نمایش {{ pd(pageMeta.from || 0) }} تا {{ pd(pageMeta.to || 0) }} از {{ pd(totalRows) }} رکورد
          </div>
          <div class="rb-pagesize">
            <span class="rb-sub rb-nowrap">تعداد در صفحه</span>
            <select class="rb-minisel" v-model.number="pageSize" @change="changePageSize">
              <option v-for="s in [10, 25, 50, 100]" :key="s" :value="s">{{ pd(s) }}</option>
            </select>
          </div>
          <div class="rb-pagenums">
            <div class="rb-pg" title="صفحه قبل" aria-label="صفحه قبل" @click="goToPage(page - 1)">‹</div>
            <div v-for="(p, i) in pageNums" :key="i" class="rb-pg"
                 :class="{ 'rb-pg--on': p === page, 'rb-pg--gap': p === '…' }"
                 @click="p !== '…' && goToPage(p)">{{ p === '…' ? '…' : pd(p) }}</div>
            <div class="rb-pg" title="صفحه بعد" aria-label="صفحه بعد" @click="goToPage(page + 1)">›</div>
          </div>
        </div>
      </template>
      <div v-else class="rb-empty">
        هنوز فیلدی برای نمایش انتخاب نشده است — تیک «نمایش» هر فیلد را بزنید تا ستون آن در گزارش درج شود.
      </div>
    </div>

    <!-- تقویم شمسی بازه گزارش -->
    <div v-if="reportDateOpen" class="rb-scrim" @click="reportDateOpen = false">
      <div class="rb-modal rb-modal--sm" @click.stop>
        <div class="rb-modalhead">
          <div class="rb-modaltitle">بازه گزارش — از / تا</div>
          <div class="rb-x rb-push" @click="reportDateOpen = false">✕</div>
        </div>
        <div class="rb-tabs">
          <div class="rb-tab" :class="{ 'rb-tab--on': reportDateTarget === 'from' }" @click="selectReportDateTarget('from')">از: {{ fmt(reportDate.from) }}</div>
          <div class="rb-tab" :class="{ 'rb-tab--on': reportDateTarget === 'to' }" @click="selectReportDateTarget('to')">تا: {{ fmt(reportDate.to) }}</div>
        </div>
        <div class="rb-calhead">
          <div class="rb-navbtn" @click="shiftReportMonth(-1)">‹</div>
          <div class="rb-cal-selects">
            <label><span>ماه</span><select v-model.number="reportCalM" class="rb-cal-select"><option v-for="(month, index) in JM" :key="month" :value="index + 1">{{ month }}</option></select></label>
            <label><span>سال</span><select v-model.number="reportCalY" class="rb-cal-select"><option v-for="year in reportYears" :key="year" :value="year">{{ pd(year) }}</option></select></label>
          </div>
          <div class="rb-navbtn" @click="shiftReportMonth(1)">›</div>
        </div>
        <div class="rb-cal rb-cal--dow"><div v-for="w in ['ش','ی','د','س','چ','پ','ج']" :key="w">{{ w }}</div></div>
        <div class="rb-cal">
          <div v-for="(c, i) in reportCalCells" :key="i" class="rb-day" :class="{ 'rb-day--on': c.selected, 'rb-day--range': c.inRange, 'rb-day--void': !c.day }" @click="c.day && pickReportDay(c.day)">{{ c.day ? pd(c.day) : '' }}</div>
        </div>
        <div v-if="reportDateError" class="rb-date-error">{{ reportDateError }}</div>
        <div class="rb-modalactions">
          <div class="rb-sub rb-flex">انتخاب هر دو تاریخ الزامی است.</div>
          <div class="rb-btn rb-btn--teal rb-flex" @click="confirmReportDate">تایید بازه</div>
        </div>
      </div>
    </div>

    <!-- مدال انتخاب چندگانه / جدول پرسنل -->
    <div v-if="pickerField" class="rb-scrim" @click="pickerId = null">
      <div class="rb-modal rb-modal--list" @click.stop>
        <div class="rb-modalhead">
          <div class="rb-modaltitle">{{ pickerField.label }}</div>
          <div class="rb-sub">انتخاب چندگانه</div>
          <div class="rb-x" @click="pickerId = null">✕</div>
        </div>
        <div class="rb-modalsearch">
          <input class="rb-input rb-input--sm" v-model="pickerSearch" placeholder="جستجو…" />
        </div>
        <div v-if="pickerField.type === 'table'" class="rb-tablelegend">
          <span>نام</span><span class="rb-push">دستمزد</span>
        </div>
        <div class="rb-optlist">
          <div v-for="o in pickerOptions" :key="o.label" class="rb-opt" @click="toggleMulti(pickerField.id, o.label)">
            <span v-if="isSelected(pickerField.id, o.label)" class="rb-check rb-check--on">✓</span>
            <span v-else class="rb-check"></span>
            <span class="rb-optlabel">{{ o.label }}</span>
            <span class="rb-optmeta">{{ o.meta }}</span>
          </div>
        </div>
        <div class="rb-modalfoot">
          <div class="rb-sub">{{ pd((multi[pickerField.id] || []).length) }} مورد انتخاب شده</div>
          <div class="rb-btn rb-btn--ghost rb-push" @click="multi[pickerField.id] = []">پاک کردن</div>
          <div class="rb-btn rb-btn--teal" @click="pickerId = null">تایید</div>
        </div>
      </div>
    </div>

    <!-- مدال بازه عددی (مبلغ) -->
    <div v-if="rangeField" class="rb-scrim" @click="rangeId = null">
      <div class="rb-modal rb-modal--xs" @click.stop>
        <div class="rb-modalhead">
          <div class="rb-modaltitle">{{ rangeField.label }} — از / تا</div>
          <div class="rb-x rb-push" @click="rangeId = null">✕</div>
        </div>
        <div class="rb-tworow">
          <label><span class="rb-minilabel">از</span>
            <input class="rb-input rb-input--center" v-model="rangeModel.from" @input="formatRangeMoney('from')" placeholder="۰" /></label>
          <label><span class="rb-minilabel">تا</span>
            <input class="rb-input rb-input--center" v-model="rangeModel.to" @input="formatRangeMoney('to')" placeholder="بی‌نهایت" /></label>
        </div>
        <div class="rb-modalactions">
          <div class="rb-btn rb-btn--ghost rb-flex" @click="rangeModel.from = ''; rangeModel.to = ''">پاک کردن</div>
          <div class="rb-btn rb-btn--teal rb-flex" @click="rangeId = null">تایید</div>
        </div>
      </div>
    </div>

    <!-- تقویم شمسی (تاریخ تولد از/تا) -->
    <div v-if="dateOpen" class="rb-scrim" @click="dateOpen = false">
      <div class="rb-modal rb-modal--sm" @click.stop>
        <div class="rb-modalhead">
          <div class="rb-modaltitle">تاریخ تولد — از / تا</div>
          <div class="rb-x rb-push" @click="dateOpen = false">✕</div>
        </div>
        <div class="rb-tabs">
          <div class="rb-tab" :class="{ 'rb-tab--on': dateTarget === 'from' }" @click="dateTarget = 'from'">
            از: {{ date.from ? fmt(date.from) : '—' }}
          </div>
          <div class="rb-tab" :class="{ 'rb-tab--on': dateTarget === 'to' }" @click="dateTarget = 'to'">
            تا: {{ date.to ? fmt(date.to) : '—' }}
          </div>
        </div>
        <div class="rb-calhead">
          <div class="rb-navbtn" @click="shiftMonth(-1)">‹</div>
          <div class="rb-cal-selects">
            <label>
              <span>ماه</span>
              <select v-model.number="calM" class="rb-cal-select">
                <option v-for="(month, index) in JM" :key="month" :value="index + 1">{{ month }}</option>
              </select>
            </label>
            <label>
              <span>سال</span>
              <select v-model.number="calY" class="rb-cal-select">
                <option v-for="year in birthYears" :key="year" :value="year">{{ pd(year) }}</option>
              </select>
            </label>
          </div>
          <div class="rb-navbtn" @click="shiftMonth(1)">›</div>
        </div>
        <div class="rb-cal rb-cal--dow">
          <div v-for="w in ['ش','ی','د','س','چ','پ','ج']" :key="w">{{ w }}</div>
        </div>
        <div class="rb-cal">
          <div v-for="(c, i) in calCells" :key="i" class="rb-day"
               :class="{ 'rb-day--on': c.selected, 'rb-day--range': c.inRange, 'rb-day--void': !c.day }"
               @click="c.day && pickDay(c.day)">{{ c.day ? pd(c.day) : '' }}</div>
        </div>
        <div v-if="dateError" class="rb-date-error">{{ dateError }}</div>
        <div class="rb-modalactions">
          <div class="rb-btn rb-btn--ghost rb-flex" @click="clearDateRange">پاک کردن</div>
          <div class="rb-btn rb-btn--teal rb-flex" @click="confirmDateRange">تایید بازه</div>
        </div>
      </div>
    </div>

    <!-- ذخیره قالب در گزارش‌ساز -->
    <div v-if="newOpen" class="rb-scrim" @click="newOpen = false">
      <div class="rb-modal rb-modal--sm" @click.stop>
        <div class="rb-modalhead">
          <div class="rb-modaltitle">اضافه به گزارش‌ساز</div>
          <div class="rb-x rb-push" @click="newOpen = false">✕</div>
        </div>
        <div class="rb-modalpad">
          <label class="rb-stack"><span class="rb-minilabel">نام قالب</span>
            <input class="rb-input" v-model="newName" placeholder="مثلاً: گزارش ماهانه لیزر" /></label>
          <div class="rb-summary">
            <div class="rb-minilabel">فیلدهای این قالب — {{ pd(cols.length) }} فیلد</div>
            <div v-if="cols.length" class="rb-chips">
              <span v-for="c in cols" :key="c.key" class="rb-chip">{{ c.label }}</span>
            </div>
            <div v-else class="rb-sub">ابتدا تیک «نمایش» فیلدهای موردنظر را در بالا بزنید.</div>
          </div>
          <div class="rb-modalactions">
            <div class="rb-btn rb-btn--ghost rb-flex" @click="newOpen = false">انصراف</div>
            <div class="rb-btn rb-flex" :class="cols.length ? 'rb-btn--teal' : 'rb-btn--disabled'"
                 :aria-disabled="!cols.length" @click.stop="savePreset">ذخیره قالب</div>
          </div>
        </div>
      </div>
    </div>

    <!-- تایید حذف قالب -->
    <div v-if="confirm" class="rb-scrim rb-scrim--top" @click="confirm = null">
      <div class="rb-modal rb-modal--confirm" @click.stop>
        <div class="rb-warn">!</div>
        <div class="rb-modaltitle">حذف قالب «{{ confirm.label }}»</div>
        <div class="rb-sub rb-loose">این قالب از گزارش‌ساز حذف می‌شود. فیلدهای انتخاب‌شده در بالا تغییری نمی‌کنند.</div>
        <div class="rb-modalactions rb-full">
          <div class="rb-btn rb-btn--ghost rb-flex" @click="confirm = null">انصراف</div>
          <div class="rb-btn rb-btn--danger rb-flex" @click="removeConfirmed">حذف</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { subscribeReportProgress } from '../services/presence';

const FA = '۰۱۲۳۴۵۶۷۸۹';
const pd = s => String(s).replace(/[0-9]/g, d => FA[d]);
const JM = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
const currentJalaliYear = () => {
  const part = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric' })
    .formatToParts(new Date()).find(item => item.type === 'year')?.value || '1405';
  return Number(part.replace(/[۰-۹]/g, digit => String(FA.indexOf(digit)))) || 1405;
};
const jalaliParts = date => {
  const parts = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year: 'numeric', month: 'numeric', day: 'numeric' }).formatToParts(date);
  const number = type => Number((parts.find(item => item.type === type)?.value || '').replace(/[۰-۹]/g, digit => String(FA.indexOf(digit))));
  return [number('year'), number('month'), number('day')];
};
const defaultReportDate = () => {
  const today = new Date();
  const from = new Date(today); from.setDate(from.getDate() - 30);
  return { from: jalaliParts(from), to: jalaliParts(today) };
};

const jIsLeap = jy => [1, 5, 9, 13, 17, 22, 26, 30].indexOf((jy + 12) % 33) > -1;
const jDays = (jy, jm) => (jm <= 6 ? 31 : jm <= 11 ? 30 : jIsLeap(jy) ? 30 : 29);
function j2g(jy, jm, jd) {
  jy += 1595;
  let days = -355668 + 365 * jy + ~~(jy / 33) * 8 + ~~(((jy % 33) + 3) / 4) + jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
  let gy = 400 * ~~(days / 146097); days %= 146097;
  if (days > 36524) { gy += 100 * ~~(--days / 36524); days %= 36524; if (days >= 365) days++; }
  gy += 4 * ~~(days / 1461); days %= 1461;
  if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
  let gd = days + 1;
  const ml = [0, 31, (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0 ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  let gm = 1;
  while (gm <= 12 && gd > ml[gm]) { gd -= ml[gm]; gm++; }
  return [gy, gm, gd];
}

const OPT = {
  source: ['اینستاگرام','معرفی دوستان','گوگل','تبلیغات محیطی','مراجعه حضوری','تلگرام'],
  work: ['انجام شد','انجام نشد','ترمیم','مشاوره'],
  section2: ['پوست','مو','لیزر','زیبایی','دندان'],
  subsection: ['بوتاکس','ژل و فیلر','مزوتراپی','هیدرودرم','کاشت مو','لیزر موهای زائد'],
  areas: ['صورت','گردن','دست','شکم','پا','زیر بغل'],
  problem: ['آکنه','لک و کک‌مک','چروک','ریزش مو','اسکار','منافذ باز'],
  extra: ['مشاوره','عکس‌برداری','تست پوست','ویزیت مجدد'],
  payment: ['نقدی','کارت‌خوان','کارت به کارت','چک','اقساط'],
  account: ['بانک ملت','بانک ملی','بانک سامان','بانک پاسارگاد','صندوق'],
  finstatus: ['ضعیف','متوسط','خوب','عالی'],
  custseg: ['آبی','نقره‌ای','طلایی'],
  doctor: [
    { label: 'دکتر مریم احمدی', meta: '۳۵٪' }, { label: 'دکتر سارا کریمی', meta: '۴۰٪' },
    { label: 'دکتر رضا موسوی', meta: '۳۰٪' }, { label: 'دکتر نگار رستمی', meta: '۴۵٪' },
    { label: 'دکتر امیر صادقی', meta: '۲۵٪' }, { label: 'دکتر هانیه پورزند', meta: '۳۸٪' }
  ],
  consultant: [
    { label: 'زهرا نیکو', meta: '۱۰٪' }, { label: 'مهسا رضوی', meta: '۱۲٪' },
    { label: 'علی شریفی', meta: '۸٪' }, { label: 'الهام داوری', meta: '۱۵٪' }, { label: 'نازنین فلاح', meta: '۹٪' }
  ]
};

const FIELDS = [
  { g: 'مشخصات مراجع', id: 'name', label: 'نام', type: 'text' },
  { g: 'مشخصات مراجع', id: 'family', label: 'نام خانوادگی', type: 'text' },
  { g: 'مشخصات مراجع', id: 'gender', label: 'جنسیت', type: 'select', options: ['همه','مرد','زن'] },
  { g: 'مشخصات مراجع', id: 'phone', label: 'شماره تماس', type: 'text', ph: '۰۹…' },
  { g: 'مشخصات مراجع', id: 'fileNo', label: 'شماره پرونده', type: 'text' },
  { g: 'مشخصات مراجع', id: 'city', label: 'شهر', type: 'text' },
  { g: 'مشخصات مراجع', id: 'birth', label: 'تاریخ تولد', type: 'date' },
  { g: 'مشخصات مراجع', id: 'custseg', label: 'تفکیک مشتری', type: 'multi' },
  { g: 'مشخصات مراجع', id: 'referrer', label: 'معرف', type: 'text' },
  { g: 'مشخصات مراجع', id: 'noreturn', label: 'عدم بازگشت', type: 'select',
    options: ['بدون محدودیت','۱ ماه','۲ ماه','۳ ماه','۴ ماه','۵ ماه','۶ ماه'] },

  { g: 'خدمات و درمان', id: 'status', label: 'وضعیت', type: 'multi' },
  { g: 'خدمات و درمان', id: 'appointmentCreatedDate', label: 'تاریخ ثبت نوبت', type: 'display' },
  { g: 'خدمات و درمان', id: 'appointmentDate', label: 'تاریخ نوبت (خدمت)', type: 'display' },
  { g: 'خدمات و درمان', id: 'source', label: 'منبع', type: 'multi' },
  { g: 'خدمات و درمان', id: 'work', label: 'انجام کار', type: 'multi' },
  { g: 'خدمات و درمان', id: 'section2', label: 'بخش', type: 'multi' },
  { g: 'خدمات و درمان', id: 'subsection', label: 'زیر بخش', type: 'multi' },
  { g: 'خدمات و درمان', id: 'count', label: 'تعداد (سی‌سی)', type: 'display' },
  { g: 'خدمات و درمان', id: 'areas', label: 'نواحی', type: 'multi' },
  { g: 'خدمات و درمان', id: 'problem', label: 'مشکل', type: 'multi' },
  { g: 'خدمات و درمان', id: 'extra', label: 'جانبی', type: 'multi' },

  { g: 'مالی', id: 'amount', label: 'مبلغ', type: 'range' },
  { g: 'مالی', id: 'income', label: 'درآمد و هزینه', type: 'display' },
  { g: 'مالی', id: 'discount', label: 'تخفیف', type: 'text', ph: 'تومان' },
  { g: 'مالی', id: 'finstatus', label: 'وضعیت مالی', type: 'multi' },
  { g: 'مالی', id: 'debt', label: 'بدهی', type: 'compare', ph: 'تومان' },
  { g: 'مالی', id: 'deposit', label: 'بیعانه', type: 'compare', ph: 'تومان' },
  { g: 'مالی', id: 'payment', label: 'روش پرداخت', type: 'multi' },
  { g: 'مالی', id: 'account', label: 'حساب واریزی', type: 'multi' },

  { g: 'پرسنل', id: 'doctor', label: 'پزشک', type: 'table' },
  { g: 'پرسنل', id: 'consultant', label: 'مشاور', type: 'table' },
  { g: 'پرسنل', id: 'salary', label: 'حقوق', type: 'display' },
  { g: 'پرسنل', id: 'overtime', label: 'اضافه کار', type: 'display' }
];

const PRESETS = [
  { id: 'fin', label: 'مالی', fields: ['amount','income','discount','finstatus','debt','deposit','payment','account'] },
  { id: 'pay', label: 'حقوق', fields: ['doctor','consultant','salary','overtime','work','amount'] },
  { id: 'cli', label: 'مراجعین', fields: ['name','family','gender','phone','city','birth','custseg'] },
  { id: 'trt', label: 'عملکرد درمان', fields: ['section2','subsection','areas','problem','extra','count'] },
  { id: 'mkt', label: 'بازاریابی', fields: ['source','referrer','custseg','noreturn','city'] }
];

// داده نمونه — در پروژه واقعی جای آن از API استفاده کنید
const MOCK = {
  name: ['سارا','مهدی','نگین','فرهاد','الهام','آرش'],
  family: ['محمدی','رضایی','کاظمی','جعفری','نوری','سلطانی'],
  gender: ['زن','مرد','زن','مرد','زن','مرد'],
  phone: ['۰۹۱۲۳۳۴۵۵۶۷','۰۹۳۵۱۱۲۴۴۷۸','۰۹۱۹۸۸۷۶۵۴۳','۰۹۳۰۲۲۱۱۳۴۵','۰۹۱۲۷۷۸۸۹۹۰','۰۹۳۷۴۵۶۱۲۳۴'],
  fileNo: ['۱۰۲۴','۱۰۲۵','۱۰۲۶','۱۰۲۷','۱۰۲۸','۱۰۲۹'],
  city: ['تهران','کرج','اصفهان','تهران','شیراز','مشهد'],
  birth: ['۱۳۷۲/۰۴/۱۱','۱۳۶۸/۰۹/۲۳','۱۳۷۵/۰۱/۰۵','۱۳۶۵/۱۱/۱۷','۱۳۷۹/۰۶/۲۹','۱۳۷۰/۰۳/۰۸'],
  custseg: ['جدید','بازگشتی','VIP','بازگشتی','جدید','معرفی‌شده'],
  referrer: ['—','زهرا نیکو','اینستاگرام','علی شریفی','—','مهسا رضوی'],
  noreturn: ['۳ ماه','۱ ماه','—','۶ ماه','۲ ماه','—'],
  status: ['فعال','تکمیل شده','در انتظار','فعال','لغو شده','فعال'],
  source: ['اینستاگرام','معرفی دوستان','گوگل','اینستاگرام','مراجعه حضوری','تلگرام'],
  work: ['انجام شده','انجام شده','نیمه‌کاره','انجام نشده','کنسل شده','انجام شده'],
  section2: ['پوست','لیزر','مو','زیبایی','پوست','دندان'],
  subsection: ['بوتاکس','لیزر موهای زائد','کاشت مو','ژل و فیلر','مزوتراپی','—'],
  areas: ['صورت','پا','—','صورت','گردن','—'],
  problem: ['چروک','—','ریزش مو','—','آکنه','—'],
  extra: ['مشاوره','عکس‌برداری','مشاوره','—','تست پوست','ویزیت مجدد'],
  count: ['۲','۱','۴','۳','۱','۲'],
  amount: ['۴٬۵۰۰٬۰۰۰','۱۲٬۰۰۰٬۰۰۰','۸٬۲۰۰٬۰۰۰','۳٬۱۰۰٬۰۰۰','۶٬۷۵۰٬۰۰۰','۹٬۹۰۰٬۰۰۰'],
  income: ['+۳٬۲۰۰٬۰۰۰','+۸٬۹۰۰٬۰۰۰','+۵٬۱۰۰٬۰۰۰','−۴۰۰٬۰۰۰','+۴٬۳۰۰٬۰۰۰','+۷٬۰۰۰٬۰۰۰'],
  discount: ['۵۰۰٬۰۰۰','۰','۱٬۲۰۰٬۰۰۰','۰','۳۰۰٬۰۰۰','۸۰۰٬۰۰۰'],
  finstatus: ['تسویه شده','بدهکار','اقساطی','تسویه شده','بدهکار','تسویه شده'],
  debt: ['۰','۲٬۰۰۰٬۰۰۰','۴٬۱۰۰٬۰۰۰','۰','۱٬۵۰۰٬۰۰۰','۰'],
  deposit: ['۱٬۰۰۰٬۰۰۰','۳٬۰۰۰٬۰۰۰','۲٬۰۰۰٬۰۰۰','۵۰۰٬۰۰۰','۱٬۲۰۰٬۰۰۰','۲٬۵۰۰٬۰۰۰'],
  payment: ['کارت‌خوان','نقدی','اقساط','کارت به کارت','کارت‌خوان','چک'],
  account: ['بانک ملت','صندوق','بانک سامان','بانک ملی','بانک ملت','بانک پاسارگاد'],
  doctor: ['دکتر مریم احمدی','دکتر رضا موسوی','دکتر نگار رستمی','دکتر سارا کریمی','دکتر امیر صادقی','دکتر مریم احمدی'],
  consultant: ['زهرا نیکو','مهسا رضوی','الهام داوری','علی شریفی','نازنین فلاح','زهرا نیکو'],
  salary: ['۱٬۵۷۵٬۰۰۰','۳٬۶۰۰٬۰۰۰','۳٬۶۹۰٬۰۰۰','۱٬۲۴۰٬۰۰۰','۱٬۶۸۷٬۵۰۰','۳٬۷۶۲٬۰۰۰'],
  overtime: ['۲ ساعت','—','۴ ساعت','۱ ساعت','—','۳ ساعت']
};

export default {
  name: 'ReportBuilder',
  data() {
    const vals = {};
    FIELDS.forEach(f => { if (f.type === 'text') vals[f.id] = ''; if (f.type === 'select') vals[f.id] = f.options[0]; });
    const initialReportDate = defaultReportDate();
    return {
      JM, FIELDS,
      filtersOpen: true,
      show: { name: true, family: true }, vals, multi: {}, range: { amount: { from: '', to: '' } },
      appointmentStatuses: ['وقت داده شد','آمد','کنسل شد','پاسخ نداد','پیگیری','انتقال داده شده'], comparators: { debt: 'eq', deposit: 'eq' },
      reportAreas: OPT.areas.slice(),
      reportExtras: OPT.extra.slice(),
      reportPaymentMethods: OPT.payment.slice(),
      reportPaymentAccounts: OPT.account.slice(),
      reportDoctors: OPT.doctor.slice(), reportConsultants: OPT.consultant.slice(),
      date: { from: null, to: null }, dateOpen: false, dateTarget: 'from', dateError: '',
      reportDate: initialReportDate, reportDateOpen: false, reportDateTarget: 'from', reportDateError: '',
      reportCalY: initialReportDate.from[0], reportCalM: initialReportDate.from[1], builtReportDate: null,
      calY: currentJalaliYear() - 30, calM: 1,
      pickerId: null, pickerSearch: '', rangeId: null,
      custom: [], removed: [], activePreset: null,
      newOpen: false, newName: '', confirm: null,
      busy: null, busyStep: '', timer: null,
      reportId: null, reportProgress: 0, reportError: '', resultRows: [], totalRows: 0,
      unsubscribeReport: null,
      pageMeta: { from: 0, to: 0 }, page: 1, pageSize: 10, reportTotals: { amount: 0 }
    };
  },
  computed: {
    groups() {
      const out = [];
      FIELDS.forEach(f => {
        let g = out.find(x => x.title === f.g);
        if (!g) { g = { title: f.g, fields: [] }; out.push(g); }
        g.fields.push(f);
      });
      return out;
    },
    visiblePresets() {
      return PRESETS.filter(p => this.removed.indexOf(p.id) === -1).concat(this.custom);
    },
    birthYears() {
      const years = [];
      for (let year = currentJalaliYear(); year >= 1250; year--) years.push(year);
      return years;
    },
    reportYears() {
      const years = [];
      for (let year = currentJalaliYear() + 1; year >= 1300; year--) years.push(year);
      return years;
    },
    cols() { return FIELDS.filter(f => this.show[f.id]).map(f => ({ key: f.id, label: f.label })); },
    pageCount() { return Math.max(1, Math.ceil(this.totalRows / this.pageSize)); },
    rows() {
      const offset = (this.page - 1) * this.pageSize;
      return this.resultRows.map((row, index) => ({
        no: pd(offset + index + 1),
        cells: this.cols.map(column => row[column.key] ?? '—')
      }));
    },
    pageNums() {
      const out = [];
      for (let p = 1; p <= this.pageCount; p++) {
        if (p === 1 || p === this.pageCount || Math.abs(p - this.page) <= 1) out.push(p);
        else if (out[out.length - 1] !== '…') out.push('…');
      }
      return out;
    },
    pickerField() { return this.pickerId ? FIELDS.find(f => f.id === this.pickerId) : null; },
    rangeField() { return this.rangeId ? FIELDS.find(f => f.id === this.rangeId) : null; },
    rangeModel() {
      if (!this.range[this.rangeId]) this.range[this.rangeId] = { from: '', to: '' };
      return this.range[this.rangeId];
    },
    pickerOptions() {
      if (!this.pickerField) return [];
      const raw = this.pickerField.id === 'status' ? this.appointmentStatuses : (this.pickerField.id === 'areas' ? this.reportAreas : (this.pickerField.id === 'extra' ? this.reportExtras : (this.pickerField.id === 'payment' ? this.reportPaymentMethods : (this.pickerField.id === 'account' ? this.reportPaymentAccounts : (this.pickerField.id === 'doctor' ? this.reportDoctors : (this.pickerField.id === 'consultant' ? this.reportConsultants : (OPT[this.pickerField.id] || [])))))));
      const q = this.pickerSearch.trim();
      return raw.map(o => (typeof o === 'string' ? { label: o, meta: '' } : o))
        .filter(o => !q || o.label.indexOf(q) > -1);
    },
    calCells() {
      const g = j2g(this.calY, this.calM, 1);
      const off = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7;
      const sel = this.date[this.dateTarget];
      const cells = [];
      for (let i = 0; i < off; i++) cells.push({ day: 0, selected: false });
      for (let d = 1; d <= jDays(this.calY, this.calM); d++) {
        const value = this.dateValue([this.calY, this.calM, d]);
        const from = this.dateValue(this.date.from), to = this.dateValue(this.date.to);
        cells.push({
          day: d,
          selected: !!sel && sel[0] === this.calY && sel[1] === this.calM && sel[2] === d,
          inRange: !!from && !!to && value >= from && value <= to
        });
      }
      return cells;
    },
    reportCalCells() {
      const g = j2g(this.reportCalY, this.reportCalM, 1);
      const off = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7;
      const selected = this.reportDate[this.reportDateTarget];
      const cells = [];
      for (let i = 0; i < off; i++) cells.push({ day: 0, selected: false, inRange: false });
      for (let day = 1; day <= jDays(this.reportCalY, this.reportCalM); day++) {
        const value = this.dateValue([this.reportCalY, this.reportCalM, day]);
        cells.push({ day, selected: !!selected && selected[0] === this.reportCalY && selected[1] === this.reportCalM && selected[2] === day, inRange: value >= this.dateValue(this.reportDate.from) && value <= this.dateValue(this.reportDate.to) });
      }
      return cells;
    }
  },
  methods: {
    pd,
    async loadReportOptions() {
      try {
        const { data } = await axios.get('/api/report-builder/options');
        if (Array.isArray(data.appointment_statuses) && data.appointment_statuses.length) {
          this.appointmentStatuses = data.appointment_statuses;
        }
        if (Array.isArray(data.areas) && data.areas.length) this.reportAreas = data.areas;
        if (Array.isArray(data.extras) && data.extras.length) this.reportExtras = data.extras;
        const payment = await axios.get('/api/payment-options');
        if (Array.isArray(payment.data?.methods) && payment.data.methods.length) this.reportPaymentMethods = payment.data.methods;
        if (Array.isArray(payment.data?.accounts) && payment.data.accounts.length) this.reportPaymentAccounts = payment.data.accounts;
        if (Array.isArray(data.doctors) && data.doctors.length) this.reportDoctors = data.doctors;
        if (Array.isArray(data.consultants) && data.consultants.length) this.reportConsultants = data.consultants;
      } catch (error) {
        console.warn('گزینه‌های وضعیت گزارش‌ساز دریافت نشد؛ مقادیر استاندارد استفاده شد.', error);
      }
    },
    isPicker(f) { return ['multi', 'table', 'range', 'date'].indexOf(f.type) > -1; },
    toggleShow(id) { this.show = Object.assign({}, this.show, { [id]: !this.show[id] }); this.activePreset = null; },
    selectAll() { const s = {}; FIELDS.forEach(f => { s[f.id] = true; }); this.show = s; this.activePreset = null; },
    clearAll() { this.show = {}; this.activePreset = null; },
    applyPreset(p) {
      const s = {};
      p.fields.forEach(id => { s[id] = true; });
      this.show = s; this.activePreset = p.id; this.page = 1; this.filtersOpen = true;
    },
    savePreset() {
      const fields = this.cols.map(c => c.key);
      if (!fields.length) return;
      const id = 'c' + Date.now();
      this.custom.push({ id, label: this.newName.trim() || 'قالب بدون نام', fields });
      this.persistPresets();
      this.activePreset = id; this.newOpen = false; this.newName = '';
    },
    askRemove(p) { this.confirm = p; },
    removeConfirmed() {
      const id = this.confirm.id;
      this.custom = this.custom.filter(x => x.id !== id);
      if (this.removed.indexOf(id) === -1) this.removed.push(id);
      if (this.activePreset === id) this.activePreset = null;
      this.persistPresets();
      this.confirm = null;
    },
    persistPresets() {
      localStorage.setItem('report-builder-custom-presets', JSON.stringify(this.custom));
    },
    restorePresets() {
      try {
        const presets = JSON.parse(localStorage.getItem('report-builder-custom-presets') || '[]');
        if (Array.isArray(presets)) this.custom = presets.filter(p => p && p.id && p.label && Array.isArray(p.fields));
      } catch (_) { this.custom = []; }
    },
    openField(f) {
      if (f.type === 'range') this.rangeId = f.id;
      else if (f.type === 'date') {
        this.dateOpen = true; this.dateTarget = 'from'; this.dateError = '';
        const initial = this.date.from || [currentJalaliYear() - 30, 1, 1];
        this.calY = initial[0]; this.calM = initial[1];
      }
      else { this.pickerId = f.id; this.pickerSearch = ''; }
    },
    isSelected(id, label) { return (this.multi[id] || []).indexOf(label) > -1; },
    toggleMulti(id, label) {
      const cur = (this.multi[id] || []).slice();
      const i = cur.indexOf(label);
      if (i > -1) cur.splice(i, 1); else cur.push(label);
      this.multi = Object.assign({}, this.multi, { [id]: cur });
    },
    selectedText(f) {
      if (f.type === 'multi' || f.type === 'table') {
        const sel = this.multi[f.id] || [];
        if (!sel.length) return { text: 'انتخاب کنید', muted: true };
        if (sel.length <= 2) return { text: sel.join('، '), muted: false };
        return { text: sel[0] + ' + ' + pd(sel.length - 1) + ' مورد دیگر', muted: false };
      }
      if (f.type === 'range') {
        const r = this.range[f.id] || { from: '', to: '' };
        if (!r.from && !r.to) return { text: 'از — تا', muted: true };
        return { text: 'از ' + pd(r.from || '۰') + ' تا ' + pd(r.to || '∞'), muted: false };
      }
      if (f.type === 'date') {
        if (!this.date.from && !this.date.to) return { text: 'از — تا', muted: true };
        return {
          text: (this.date.from ? this.fmt(this.date.from) : '…') + ' — ' + (this.date.to ? this.fmt(this.date.to) : '…'),
          muted: false
        };
      }
      return { text: '', muted: true };
    },
    fmt(d) { return d ? pd(d[0] + '/' + String(d[1]).padStart(2, '0') + '/' + String(d[2]).padStart(2, '0')) : '—'; },
    formatMoney(id) { const v = String(this.vals[id] || '').replace(/[۰-۹]/g, d => String(FA.indexOf(d))).replace(/[٬,\s]/g, ''); if (v && /^\d+(\.\d+)?$/.test(v)) this.vals[id] = Number(v).toLocaleString('en-US'); },
    formatRangeMoney(side) { const v = String(this.rangeModel[side] || '').replace(/[۰-۹]/g, d => String(FA.indexOf(d))).replace(/[٬,\s]/g, ''); if (v && /^\d+(\.\d+)?$/.test(v)) this.rangeModel[side] = Number(v).toLocaleString('en-US'); },
    dateValue(d) { return d ? d[0] * 10000 + d[1] * 100 + d[2] : 0; },
    shiftMonth(n) {
      let m = this.calM + n, y = this.calY;
      if (m < 1) { m = 12; y--; } else if (m > 12) { m = 1; y++; }
      this.calM = m; this.calY = y;
    },
    pickDay(d) {
      const picked = [this.calY, this.calM, d];
      this.dateError = '';
      if (this.dateTarget === 'from') {
        this.date = { from: picked, to: this.date.to && this.dateValue(this.date.to) >= this.dateValue(picked) ? this.date.to : null };
        this.dateTarget = 'to';
      } else if (this.date.from && this.dateValue(picked) < this.dateValue(this.date.from)) {
        this.date = { from: picked, to: this.date.from };
      } else {
        this.date = { from: this.date.from, to: picked };
      }
    },
    clearDateRange() {
      this.date = { from: null, to: null }; this.dateTarget = 'from'; this.dateError = '';
    },
    confirmDateRange() {
      if (this.date.from && this.date.to && this.dateValue(this.date.from) > this.dateValue(this.date.to)) {
        this.dateError = 'تاریخ شروع باید قبل از تاریخ پایان باشد.';
        return;
      }
      this.dateOpen = false;
    },
    openReportDate() {
      this.reportDateOpen = true; this.reportDateTarget = 'from'; this.reportDateError = '';
      this.reportCalY = this.reportDate.from[0]; this.reportCalM = this.reportDate.from[1];
    },
    selectReportDateTarget(target) {
      this.reportDateTarget = target;
      const selected = this.reportDate[target];
      if (selected) { this.reportCalY = selected[0]; this.reportCalM = selected[1]; }
    },
    shiftReportMonth(n) {
      let month = this.reportCalM + n, year = this.reportCalY;
      if (month < 1) { month = 12; year--; } else if (month > 12) { month = 1; year++; }
      this.reportCalM = month; this.reportCalY = year;
    },
    pickReportDay(day) {
      const picked = [this.reportCalY, this.reportCalM, day]; this.reportDateError = '';
      if (this.reportDateTarget === 'from') {
        this.reportDate = { from: picked, to: this.dateValue(this.reportDate.to) >= this.dateValue(picked) ? this.reportDate.to : null };
        this.reportDateTarget = 'to';
      } else if (this.dateValue(picked) < this.dateValue(this.reportDate.from)) {
        this.reportDate = { from: picked, to: this.reportDate.from };
      } else this.reportDate = { from: this.reportDate.from, to: picked };
    },
    confirmReportDate() {
      if (!this.reportDate.from || !this.reportDate.to) { this.reportDateError = 'تاریخ شروع و پایان گزارش هر دو الزامی هستند.'; return; }
      if (this.dateValue(this.reportDate.from) > this.dateValue(this.reportDate.to)) { this.reportDateError = 'تاریخ شروع باید قبل از تاریخ پایان باشد.'; return; }
      this.reportDateOpen = false;
    },
    runSteps(kind, steps, done) {
      if (this.busy) return;
      this.busy = kind; this.busyStep = steps[0];
      let i = 0;
      this.timer = setInterval(() => {
        i++;
        if (i < steps.length) this.busyStep = steps[i];
        else {
          clearInterval(this.timer);
          this.busy = null; this.busyStep = '';
          if (done) done();
        }
      }, 620);
    },
    async buildReport() {
      if (!this.cols.length || this.busy) return;
      if (!this.reportDate.from || !this.reportDate.to) { this.reportError = 'برای ایجاد گزارش، تاریخ شروع و پایان بازه گزارش را انتخاب کنید.'; this.openReportDate(); return; }
      clearTimeout(this.timer);
      this.busy = 'build'; this.busyStep = 'ارسال گزارش به صف پردازش…';
      this.reportProgress = 0; this.reportError = ''; this.resultRows = []; this.totalRows = 0; this.page = 1;
      this.builtReportDate = { from: [...this.reportDate.from], to: [...this.reportDate.to] };
      try {
        const { data } = await axios.post('/api/report-builder/reports', this.payload());
        this.reportId = data.report.id;
        this.unsubscribeReport?.();
        this.unsubscribeReport = subscribeReportProgress(this.reportId, report => this.handleRealtimeStatus(report));
        this.applyReportStatus(data.report);
        this.pollReport();
      } catch (error) {
        this.failReport(error.response?.data?.message || 'ایجاد گزارش ناموفق بود.');
      }
    },
    async pollReport() {
      if (!this.reportId || this.busy !== 'build') return;
      try {
        const { data } = await axios.get(`/api/report-builder/reports/${this.reportId}`);
        this.applyReportStatus(data.report);
        if (data.report.status === 'completed') {
          this.busy = null;
          this.unsubscribeReport?.(); this.unsubscribeReport = null;
          await this.loadReportRows();
          this.$emit('build', this.payload());
          return;
        }
        if (data.report.status === 'failed') {
          this.unsubscribeReport?.(); this.unsubscribeReport = null;
          this.failReport(data.report.error || 'ساخت گزارش ناموفق بود.');
          return;
        }
        this.timer = setTimeout(() => this.pollReport(), 2000);
      } catch (error) {
        this.failReport(error.response?.data?.message || 'دریافت وضعیت گزارش ناموفق بود.');
      }
    },
    async handleRealtimeStatus(report) {
      if (!report || report.id !== this.reportId || this.busy !== 'build') return;
      this.applyReportStatus(report);
      if (report.status === 'completed') {
        clearTimeout(this.timer);
        this.busy = null;
        this.unsubscribeReport?.(); this.unsubscribeReport = null;
        await this.loadReportRows();
        this.$emit('build', this.payload());
      } else if (report.status === 'failed') {
        this.unsubscribeReport?.(); this.unsubscribeReport = null;
        this.failReport(report.error || 'ساخت گزارش ناموفق بود.');
      }
    },
    applyReportStatus(report) {
      this.reportProgress = Number(report.progress || 0);
      this.busyStep = report.stage || 'در حال پردازش گزارش…';
      this.totalRows = Number(report.total_rows || 0);
    },
    failReport(message) {
      clearTimeout(this.timer);
      this.unsubscribeReport?.(); this.unsubscribeReport = null;
      this.busy = null; this.reportError = message; this.busyStep = '';
    },
    async loadReportRows() {
      if (!this.reportId) return;
      try {
        const { data } = await axios.get(`/api/report-builder/reports/${this.reportId}/rows`, {
          params: { page: this.page, per_page: this.pageSize }
        });
        this.resultRows = data.data || [];
        this.totalRows = Number(data.meta?.total || 0);
        this.pageMeta = { from: data.meta?.from || 0, to: data.meta?.to || 0 };
        this.reportTotals = { amount: Number(data.summary?.amount_total || 0) };
      } catch (error) {
        this.reportError = error.response?.data?.message || 'دریافت ردیف‌های گزارش ناموفق بود.';
      }
    },
    formatReportTotal(value) { return Number(value || 0).toLocaleString('en-US'); },
    goToPage(page) {
      const next = Math.min(this.pageCount, Math.max(1, Number(page) || 1));
      if (next === this.page) return;
      this.page = next; this.loadReportRows();
    },
    changePageSize() {
      this.page = 1; this.loadReportRows();
    },
    exportExcel() {
      if (!this.cols.length || !this.resultRows.length || this.busy) return;
      this.runSteps('export', ['تهیه ستون‌ها…','نوشتن سطرها در فایل…','فشرده‌سازی و آماده‌سازی دانلود…'], () => this.download());
    },
    download() {
      const cols = this.cols;
      let html = '<meta charset="utf-8"><table border="1" dir="rtl"><tr>' +
        cols.map(c => '<th>' + c.label + '</th>').join('') + '</tr>';
      this.resultRows.forEach(row => {
        html += '<tr>' + cols.map(c => '<td>' + (row[c.key] ?? '—') + '</td>').join('') + '</tr>';
      });
      html += '</table>';
      const url = URL.createObjectURL(new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel' }));
      const a = document.createElement('a');
      a.href = url; a.download = 'گزارش-کلینیک.xls'; a.click();
      setTimeout(() => URL.revokeObjectURL(url), 2000);
      this.$emit('export', this.payload());
    },
    // ساختار فیلترها برای ارسال به API
    payload() {
      return {
        columns: this.cols.map(c => c.key),
        filters: { values: this.vals, multi: this.multi, range: this.range, birthDate: this.date, reportDate: this.reportDate, comparators: this.comparators },
        pagination: { page: this.page, pageSize: this.pageSize }
      };
    }
  },
  mounted() { this.restorePresets(); this.loadReportOptions(); },
  beforeUnmount() { clearTimeout(this.timer); this.unsubscribeReport?.(); },
  beforeDestroy() { clearTimeout(this.timer); this.unsubscribeReport?.(); }
};
</script>

<style scoped>
.rb-compare{display:grid;grid-template-columns:72px minmax(0,1fr);gap:6px}.rb-compare-op{width:72px;height:38px;padding:0 4px;border:1px solid #CFE3DD;border-radius:9px;background:#F7FBFA;color:#0E6B5E;font:700 10px Vazir, sans-serif;text-align:center;cursor:pointer}.rb-compare-op:focus{outline:0;border-color:#0E6B5E;box-shadow:0 0 0 3px rgba(14,107,94,.12)}
.rb-total-row td{padding:13px 10px!important;border-top:2px solid #0E6B5E!important;background:#EAF6F3!important;color:#075A4F!important;font-weight:900!important}
.rb { direction: rtl; background: #F4F7F8; color: #1F2A37; padding: 18px 20px 60px;
  font-family: Vazirmatn, system-ui, sans-serif; }
.rb * { box-sizing: border-box; }
.rb-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 12px rgba(20,45,60,.05); }
.rb-card--rel { position: relative; padding: 22px 26px 26px; }
.rb-mb { margin-bottom: 18px; overflow: hidden; }
.rb-push { margin-inline-start: auto; }
.rb-flex { flex: 1; text-align: center; }
.rb-nowrap { white-space: nowrap; }
.rb-builder-card { padding: 20px 26px 22px; }
.rb-builder-head { display: flex; align-items: center; gap: 18px; margin-bottom: 16px; }
.rb-report-range-card { padding: 18px 26px; display: flex; align-items: center; gap: 22px; }
.rb-report-range-copy { display: flex; align-items: center; gap: 12px; min-width: 280px; }
.rb-report-range-picker { max-width: 430px; margin-inline-start: auto; background: #F9FCFB; border-color: #CFE3DD; }
.rb-report-range-picker .rb-pickericon { margin-inline-start: auto; }
.rb-required { display: inline-block; margin-inline-start: 6px; padding: 2px 7px; border-radius: 999px; background: #FDECEA; color: #B7352A; font-size: 10px; vertical-align: middle; }
.rb-built-range { margin: -4px 0 16px; padding: 10px 14px; border: 1px solid #CFE3DD; border-radius: 11px; background: #F1F8F6; color: #315B54; font-size: 13px; }
.rb-built-range strong { color: #0E6B5E; }

.rb-head { display: flex; align-items: center; gap: 12px; padding: 20px 26px; cursor: pointer; user-select: none; }
.rb-icon { width: 34px; height: 34px; border-radius: 10px; background: #E8F3F0; color: #0E6B5E;
  display: flex; align-items: center; justify-content: center; font-size: 16px; }
.rb-title { font-size: 17px; font-weight: 700; color: #16232F; }
.rb-sub { font-size: 12.5px; color: #5A6675; }
.rb-headright { margin-inline-start: auto; display: flex; align-items: center; gap: 14px; }
.rb-badge { font-size: 12.5px; color: #0E6B5E; background: #E8F3F0; border-radius: 999px;
  padding: 5px 12px; font-weight: 600; white-space: nowrap; }
.rb-collapse { font-size: 13px; color: #6B7784; white-space: nowrap; }
.rb-body { border-top: 1px solid #EDF1F4; padding: 6px 26px 26px; }

.rb-secrow { display: flex; align-items: center; gap: 10px; padding: 18px 0 4px; flex-wrap: wrap; }
.rb-secrow--tight { padding: 0 0 14px; }
.rb-h2 { font-size: 15px; font-weight: 700; color: #16232F; }
.rb-line { height: 1px; flex: 1; background: #EDF1F4; }
.rb-hr { height: 1px; background: #EDF1F4; margin: 22px 0 20px; }

.rb-btn { display: flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px;
  padding: 11px 18px; font-size: 13.5px; font-weight: 600; cursor: pointer; white-space: nowrap;
  border: 1px solid transparent; }
.rb-btn--teal { background: #0E6B5E; color: #fff; }
.rb-btn--teal:hover { background: #0A5247; }
.rb-btn--dark { background: #2B3445; color: #fff; }
.rb-btn--dark:hover { background: #1E2734; }
.rb-btn--danger { background: #C0392B; color: #fff; }
.rb-btn--danger:hover { background: #A53023; }
.rb-btn--disabled { background: #AEBDC4; color: #fff; }
.rb-btn--ghost { color: #5A6675; border-color: #E3E9ED; font-size: 13px; padding: 9px 16px; }
.rb-btn--ghost:hover { background: #F3F6F8; }
.rb-btn--ghostTeal { color: #0E6B5E; border-color: #CFE3DD; font-size: 12.5px; padding: 6px 12px; border-radius: 9px; }
.rb-btn--ghostTeal:hover { background: #E8F3F0; }
.rb-btn--dashed { border: 1px dashed #B9CFC9; background: #F7FBFA; color: #0E6B5E; }
.rb-btn--dashed:hover { background: #EAF3F1; }
.rb-plus { font-size: 15px; }
.rb-dl { font-size: 14px; }
.rb-addrow { display: flex; justify-content: flex-end; margin-top: 24px; }

.rb-group { margin-top: 22px; }
.rb-grouphead { display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
  font-size: 13.5px; font-weight: 700; color: #33414E; }
.rb-bar { width: 5px; height: 16px; border-radius: 3px; background: #0E6B5E; }
.rb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); gap: 16px; }
.rb-field { display: flex; flex-direction: column; gap: 7px; }
.rb-fieldhead { display: flex; align-items: center; gap: 8px; }
.rb-label { font-size: 12.5px; font-weight: 600; color: #46525F; }
.rb-showtoggle { margin-inline-start: auto; display: flex; align-items: center; gap: 5px;
  cursor: pointer; user-select: none; }
.rb-showtext { font-size: 11.5px; color: #5A6675; font-weight: 500; }
.rb-check { width: 16px; height: 16px; border-radius: 5px; border: 1.5px solid #D2DBE2;
  background: #fff; flex: none; }
.rb-check--on { background: #0E6B5E; border-color: #0E6B5E; color: #fff; font-size: 10px;
  display: flex; align-items: center; justify-content: center; }

.rb-input { width: 100%; height: 44px; border: 1px solid #E3E9ED; border-radius: 11px; background: #fff;
  padding: 0 14px; font-size: 13.5px; color: #1F2A37; outline: none; font-family: inherit; }
.rb-input:focus { border-color: #0E6B5E; box-shadow: 0 0 0 3px rgba(14,107,94,.08); }
.rb-input--sm { height: 40px; border-radius: 10px; font-size: 13px; padding: 0 12px; }
.rb-input--center { text-align: center; height: 42px; border-radius: 10px; }
.rb-input::placeholder { color: #A6B1BC; }
.rb-select { appearance: none; cursor: pointer; padding: 0 12px; }

.rb-picker { width: 100%; min-height: 44px; border: 1px solid #E3E9ED; border-radius: 11px; background: #fff;
  padding: 0 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.rb-picker:hover { border-color: #BFD6D0; background: #FBFDFC; }
.rb-pickertext { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rb-date-summary { min-width: 0; display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; }
.rb-date-summary span { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.rb-date-summary small { color: #7C8894; font-size: 10px; font-weight: 600; }
.rb-date-summary i { width: 12px; height: 1px; background: #D2DBE2; }
.rb-picker-clear { width: 22px; height: 22px; margin-inline-start: auto; padding: 0; border: 0; border-radius: 7px;
  background: #FDECEA; color: #C0392B; font-family: inherit; font-size: 16px; line-height: 1; cursor: pointer; }
.rb-picker-clear:hover { background: #F9D8D4; }
.rb-muted { color: #A6B1BC; }
.rb-pickericon { color: #A6B1BC; font-size: 11px; }
.rb-pickertext + .rb-pickericon { margin-inline-start: auto; }
.rb-display { width: 100%; min-height: 44px; border: 1px dashed #DCE4E9; border-radius: 11px;
  background: #F8FAFB; padding: 10px 12px; display: flex; align-items: center; gap: 8px;
  font-size: 12px; color: #5A6675; }
.rb-dim { margin-inline-start: auto; color: #B6C0C9; font-size: 11px; }

.rb-presets { display: flex; flex-wrap: wrap; gap: 10px; }
.rb-preset { display: flex; align-items: center; gap: 9px; border: 1px solid #E3E9ED; background: #fff;
  color: #46525F; border-radius: 12px; padding: 11px 16px; cursor: pointer; font-size: 13.5px;
  font-weight: 600; white-space: nowrap; }
.rb-preset:hover { border-color: #0E6B5E; }
.rb-preset--on { background: #E8F3F0; color: #0E6B5E; border-color: #0E6B5E; }
.rb-presetcount { font-size: 11px; opacity: .7; }
.rb-presetx { color: #B6C0C9; font-size: 12px; }
.rb-presetx:hover { color: #C0392B; }

.rb-reporthead { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.rb-tablewrap { overflow: auto; border: 1px solid #EDF1F4; border-radius: 14px; }
.rb-table { width: 100%; border-collapse: collapse; font-size: 12.5px; white-space: nowrap; }
.rb-table th { background: #F7FAFB; padding: 13px 14px; text-align: right; font-weight: 700;
  color: #33414E; border-bottom: 1px solid #EDF1F4; }
.rb-thnum { color: #5A6675 !important; }
.rb-table td { padding: 12px 14px; color: #2B3845; border-bottom: 1px solid #F2F5F7; }
.rb-tdnum { color: #9AA6B1 !important; }
.rb-table tbody tr:hover { background: #FAFCFD; }
.rb-empty { border: 1px dashed #DCE4E9; border-radius: 14px; padding: 54px; text-align: center;
  color: #5A6675; font-size: 13.5px; }

.rb-pager { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 14px; }
.rb-pagesize { display: flex; align-items: center; gap: 7px; }
.rb-minisel { height: 34px; border: 1px solid #E3E9ED; border-radius: 9px; padding: 0 10px;
  font-size: 12.5px; color: #1F2A37; outline: none; cursor: pointer; font-family: inherit; }
.rb-pagenums { margin-inline-start: auto; display: flex; align-items: center; gap: 6px; }
.rb-pg { min-width: 34px; height: 34px; padding: 0 9px; border-radius: 9px; border: 1px solid #E3E9ED;
  background: #fff; color: #46525F; display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 12.5px; font-weight: 600; }
.rb-pg:hover { background: #F3F6F8; }
.rb-pg--on { background: #0E6B5E; border-color: #0E6B5E; color: #fff; }
.rb-pg--gap { border-color: transparent; cursor: default; background: transparent; }

.rb-overlay { position: absolute; inset: 0; background: rgba(255,255,255,.82); backdrop-filter: blur(3px);
  border-radius: 18px; display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 16px; z-index: 20; animation: rb-fade .2s ease; }
.rb-loader { position: relative; width: 66px; height: 66px; }
.rb-loader-track { position: absolute; inset: 0; border-radius: 50%; border: 3px solid #E4EFEC; }
.rb-loader-arc { position: absolute; inset: 0; border-radius: 50%; border: 3px solid transparent;
  border-top-color: #0E6B5E; border-inline-end-color: #0E6B5E;
  animation: rb-spin .9s cubic-bezier(.5,.1,.4,.9) infinite; }
.rb-loader-dot { position: absolute; inset: 16px; border-radius: 50%; background: #0E6B5E;
  animation: rb-pulse 1.4s ease-in-out infinite; }
.rb-loadertext { display: flex; flex-direction: column; align-items: center; gap: 7px; }
.rb-loadertitle { font-size: 14px; font-weight: 700; color: #16232F; }
.rb-progress { width: 190px; height: 4px; border-radius: 4px; background: #E8EFF1; overflow: hidden; }
.rb-progress-fill { height: 100%; border-radius: 4px; transition: width .35s ease;
  background: linear-gradient(90deg,#0E6B5E,#3FAF95); }
.rb-progress-number { color: #0E6B5E; font-size: 12px; font-weight: 800; }
.rb-report-error { margin-bottom: 16px; padding: 12px 14px; border: 1px solid #fecaca; border-radius: 11px;
  background: #fff1f2; color: #b91c1c; font-size: 13px; font-weight: 600; }
.rb-spin { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35);
  border-top-color: #fff; animation: rb-spin .7s linear infinite; }

.rb-scrim { position: fixed; inset: 0; z-index: 50; background: rgba(18,30,40,.36); display: flex; align-items: center;
  justify-content: center; z-index: 50; padding: 24px; }
.rb-scrim--top { z-index: 60; }
.rb-modal { background: #fff; border-radius: 18px; box-shadow: 0 24px 60px rgba(10,25,35,.25);
  overflow: hidden; max-width: 100%; animation: rb-fade .15s ease; }
.rb-modal--list { width: 440px; max-height: 78vh; display: flex; flex-direction: column; }
.rb-modal--sm { width: 400px; }
.rb-modal--xs { width: 330px; }
.rb-modal--confirm { width: 340px; padding: 22px; display: flex; flex-direction: column;
  align-items: center; gap: 12px; text-align: center; }
.rb-modalhead { padding: 16px 20px; border-bottom: 1px solid #EDF1F4; display: flex; align-items: center; gap: 10px; }
.rb-modaltitle { font-size: 14.5px; font-weight: 700; color: #16232F; }
.rb-x { margin-inline-start: auto; color: #5A6675; cursor: pointer; font-size: 15px; }
.rb-modalsearch { padding: 14px 20px 6px; }
.rb-modalpad { padding: 18px; display: flex; flex-direction: column; gap: 14px; }
.rb-modalfoot { padding: 10px 16px; border-top: 1px solid #EDF1F4; display: flex; align-items: center; gap: 6px; }
.rb-modalactions { display: flex; gap: 6px; padding: 0 14px 14px; }
.rb-modalactions.rb-full { padding: 0; width: 100%; margin-top: 6px; }
.rb-modal--list .rb-modalfoot .rb-btn,
.rb-modalactions .rb-btn { padding: 7px 12px; font-size: 11px; }
.rb-tablelegend { display: flex; padding: 10px 20px 6px; font-size: 11.5px; color: #5A6675; font-weight: 600; }
.rb-optlist { overflow: auto; padding: 4px 12px 12px; flex: 1; }
.rb-opt { display: flex; align-items: center; gap: 7px; padding: 7px 8px; min-height: 30px; border-bottom: 1px dashed #DCE5E2; border-radius: 6px; cursor: pointer; }
.rb-opt:last-child { border-bottom: 0; }
.rb-opt:hover { background: #F5F9F8; }
.rb-optlabel { font-size: 11.5px; color: #2B3845; font-weight: 500; }
.rb-optmeta { margin-inline-start: auto; font-size: 10.5px; color: #7C8894; }
.rb-tworow { padding: 18px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.rb-tworow label, .rb-stack { display: flex; flex-direction: column; gap: 6px; }
.rb-minilabel { font-size: 12px; color: #46525F; font-weight: 600; }
.rb-summary { background: #F7FAFB; border: 1px solid #EDF1F4; border-radius: 12px; padding: 12px 14px;
  display: flex; flex-direction: column; gap: 8px; }
.rb-chips { display: flex; flex-wrap: wrap; gap: 6px; max-height: 110px; overflow: auto; }
.rb-chip { font-size: 11.5px; background: #E8F3F0; color: #0E6B5E; border-radius: 8px; padding: 5px 9px;
  font-weight: 600; white-space: nowrap; }
.rb-warn { width: 46px; height: 46px; border-radius: 14px; background: #FDECEA; color: #C0392B;
  display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; }
.rb-loose { line-height: 1.8; }

.rb-tabs { display: flex; gap: 8px; padding: 14px 18px 0; }
.rb-tab { flex: 1; text-align: center; border-radius: 10px; padding: 9px; font-size: 12.5px;
  font-weight: 600; cursor: pointer; background: #F5F8F9; color: #6B7784; }
.rb-tab--on { background: #E8F3F0; color: #0E6B5E; }
.rb-calhead { display: flex; align-items: center; gap: 10px; padding: 16px 18px 8px; }
.rb-cal-selects { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.rb-cal-selects label { display: flex; align-items: center; gap: 6px; }
.rb-cal-selects label > span { color: #6B7784; font-size: 10.5px; font-weight: 600; }
.rb-cal-select { min-width: 0; flex: 1; height: 34px; padding: 0 8px; border: 1px solid #E3E9ED;
  border-radius: 9px; background: #fff; color: #1F2A37; font-family: inherit; font-size: 12px; outline: none; }
.rb-cal-select:focus { border-color: #0E6B5E; box-shadow: 0 0 0 2px rgba(14,107,94,.08); }
.rb-navbtn { width: 30px; height: 30px; border-radius: 9px; border: 1px solid #E9EEF1; display: flex;
  align-items: center; justify-content: center; cursor: pointer; color: #5A6675; }
.rb-navbtn:hover { background: #F3F6F8; }
.rb-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; padding: 2px 18px 16px; }
.rb-cal--dow { padding: 0 18px; text-align: center; font-size: 11px; color: #5A6675; font-weight: 600; }
.rb-day { height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 9px;
  font-size: 12.5px; cursor: pointer; color: #2B3845; font-weight: 500; }
.rb-day:hover { background: #EAF3F1; }
.rb-day--on { background: #0E6B5E; color: #fff; font-weight: 700; }
.rb-day--range:not(.rb-day--on) { background: #E8F3F0; color: #0E6B5E; }
.rb-day--void { cursor: default; }
.rb-day--void:hover { background: transparent; }
.rb-date-error { margin: -5px 18px 14px; color: #B91C1C; font-size: 11.5px; font-weight: 700; }

@media (max-width: 700px) {
  .rb-builder-card { padding: 16px; }
  .rb-builder-head { align-items: stretch; flex-direction: column; }
  .rb-builder-head .rb-push { margin-inline-start: 0; }
  .rb-report-range-card { padding: 16px; align-items: stretch; flex-direction: column; gap: 14px; }
  .rb-report-range-copy { min-width: 0; }
  .rb-report-range-picker { max-width: none; margin-inline-start: 0; }
  .rb-report-range-picker .rb-date-summary { flex: 1; justify-content: space-between; }
}

@keyframes rb-spin { to { transform: rotate(360deg); } }
@keyframes rb-bar { 0% { transform: translateX(100%); } 100% { transform: translateX(-260%); } }
@keyframes rb-pulse { 0%,100% { opacity: .25; transform: scale(.82); } 50% { opacity: 1; transform: scale(1); } }
@keyframes rb-fade { from { opacity: 0; } to { opacity: 1; } }
</style>
