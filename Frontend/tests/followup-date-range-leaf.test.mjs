import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8');

test('general follow-up date filters update one bound without clearing the other', () => {
  assert.match(source, /setDateFilterBound\('contactDate', 'from', \$event\)/);
  assert.match(source, /setDateFilterBound\('contactDate', 'to', \$event\)/);
  assert.match(source, /setDateFilterBound\('followUpDate', 'from', \$event\)/);
  assert.match(source, /setDateFilterBound\('followUpDate', 'to', \$event\)/);
  assert.match(source, /dateInInclusiveRange\(value, from, to\)/);
});

test('general follow-up بخش column and filter use leaf subsection labels', () => {
  assert.match(source, /sectionLeafLabel\(option\.name\)/);
  assert.match(source, /sectionLeafLabel\(row\?\.subsectionName\)/);
  assert.match(source, /this\.followupSubsectionLabel\(r\)/);
});
