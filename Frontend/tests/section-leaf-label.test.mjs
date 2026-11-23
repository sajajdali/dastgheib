import test from 'node:test';
import assert from 'node:assert/strict';
import { sectionLeafLabel, dateInInclusiveRange } from '../src/utils/sectionLeafLabel.js';

test('sectionLeafLabel keeps only the deepest leaf', () => {
  assert.equal(sectionLeafLabel('لیزر←کندلا←بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel('لیزر / کندلا / بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel('بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel(''), '');
});

test('dateInInclusiveRange supports from-only, to-only, and both bounds', () => {
  assert.equal(dateInInclusiveRange('2026-11-10', '2026-11-10', ''), true);
  assert.equal(dateInInclusiveRange('2026-11-09', '2026-11-10', ''), false);
  assert.equal(dateInInclusiveRange('2026-11-10', '', '2026-11-10'), true);
  assert.equal(dateInInclusiveRange('2026-11-11', '', '2026-11-10'), false);
  assert.equal(dateInInclusiveRange('2026-11-10', '2026-11-01', '2026-11-30'), true);
  assert.equal(dateInInclusiveRange('', '2026-11-01', ''), false);
  assert.equal(dateInInclusiveRange('2026-11-10', '', ''), true);
});
