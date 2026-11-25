import test from 'node:test';
import assert from 'node:assert/strict';
import {
  sectionLeafLabel,
  dateInInclusiveRange,
  firstRootSubsectionLabel,
} from '../src/utils/sectionLeafLabel.js';

test('sectionLeafLabel keeps only the deepest leaf', () => {
  assert.equal(sectionLeafLabel('لیزر←کندلا←بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel('لیزر / کندلا / بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel('بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel(''), '');
});

const laserTree = [
  { id: 'laser', name: 'لیزر', parent_id: null },
  { id: 'alex', name: 'الکساندرایت', parent_id: 'laser' },
  { id: 'women', name: 'بانوان', parent_id: 'alex' },
  { id: 'body', name: 'ناحیه بدن', parent_id: 'women' },
];

test('firstRootSubsectionLabel shows first direct child under root, not deepest leaf', () => {
  assert.equal(firstRootSubsectionLabel({
    sectionId: 'body',
    rootSectionId: 'laser',
    sections: laserTree,
    serviceName: 'فول‌بادی',
  }), 'الکساندرایت');

  assert.equal(firstRootSubsectionLabel({
    sectionId: 'alex',
    rootSectionId: 'laser',
    sections: laserTree,
    serviceName: 'فول‌بادی',
  }), 'الکساندرایت');

  assert.equal(firstRootSubsectionLabel({
    sectionId: 'laser',
    rootSectionId: 'laser',
    sections: laserTree,
    serviceName: 'مشاوره لیزر',
  }), 'مشاوره لیزر');

  assert.equal(firstRootSubsectionLabel({
    sectionId: '',
    rootSectionId: '',
    sections: laserTree,
    serviceName: 'خدمت آزاد',
  }), 'خدمت آزاد');
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
