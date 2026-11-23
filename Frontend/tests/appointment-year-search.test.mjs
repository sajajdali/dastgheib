import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8');

test('tabular booking search supports whole-year toggle and Jalali year picker', () => {
  assert.match(source, /جست‌وجو در کل سال/);
  assert.match(source, /v-model="searchEntireYear"/);
  assert.match(source, /v-model="searchYear"/);
  assert.match(source, /ensureYearMonthsCached/);
  assert.match(source, /activeSearchResultDateLabel/);
  assert.match(source, /rowMatchesActiveTableFilters\(day, row, \{ forSearch: true \}\)/);
});

test('services column uses deepest leaf subsection label helper', () => {
  assert.match(source, /sectionLeafLabel\(this\.serviceSectionLabel\(sectionId\) \|\| name\)/);
});
