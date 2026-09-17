import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const source = fs.readFileSync(new URL('../src/components/ReportBuilder.vue', import.meta.url), 'utf8');

test('report builder has a required rolling 30-day report range', () => {
  assert.match(source, /from\.setDate\(from\.getDate\(\) - 30\)/);
  assert.match(source, /بازه گزارش <span class="rb-required">اجباری<\/span>/);
  assert.match(source, /reportDate: this\.reportDate/);
});

test('created report displays the exact submitted range above its table', () => {
  assert.match(source, /this\.builtReportDate = \{ from: \[\.\.\.this\.reportDate\.from\], to: \[\.\.\.this\.reportDate\.to\] \}/);
  assert.match(source, /بازه گزارش: از تاریخ/);
});

test('saved report templates live in a separate card and open filters when selected', () => {
  const filterCardEnd = source.indexOf('<!-- قالب‌های مستقل گزارش‌ساز -->');
  const builderCardEnd = source.indexOf('<!-- بازه زمانی اجباری گزارش -->');
  const builderCard = source.slice(filterCardEnd, builderCardEnd);

  assert.ok(filterCardEnd > 0);
  assert.match(builderCard, /rb-card rb-builder-card rb-mb/);
  assert.match(builderCard, /v-for="p in visiblePresets"/);
  assert.match(source, /this\.filtersOpen = true/);
});

test('pagination arrows point to the correct previous and next directions', () => {
  assert.match(source, /aria-label="صفحه قبل"[^>]*>‹<\/div>/);
  assert.match(source, /aria-label="صفحه بعد"[^>]*>›<\/div>/);
});
