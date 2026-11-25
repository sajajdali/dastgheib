import test from 'node:test';
import assert from 'node:assert/strict';
import {
  sectionLeafLabel,
  dateInInclusiveRange,
  firstRootSubsectionLabel,
  uniqueServiceSummaryLabels,
  serviceButtonSummaryLabels,
} from '../src/utils/sectionLeafLabel.js';

test('sectionLeafLabel keeps only the deepest leaf', () => {
  assert.equal(sectionLeafLabel('لیزر←کندلا←بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel('لیزر / کندلا / بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel('بانوان'), 'بانوان');
  assert.equal(sectionLeafLabel(''), '');
});

/** درخت نمونه مطابق جدول مشخصات ستون خدمات. */
const clinicTree = [
  { id: 'laser', name: 'لیزر', parent_id: null },
  { id: 'alex', name: 'الکساندرایت', parent_id: 'laser' },
  { id: 'women', name: 'بانوان', parent_id: 'alex' },
  { id: 'body', name: 'ناحیه بدن', parent_id: 'women' },
  { id: 'diode', name: 'دیود', parent_id: 'laser' },
  { id: 'diode-men', name: 'آقایان', parent_id: 'diode' },
  { id: 'inject', name: 'تزریقات', parent_id: null },
  { id: 'botox', name: 'بوتاکس', parent_id: 'inject' },
  { id: 'forehead', name: 'پیشانی', parent_id: 'botox' },
  { id: 'consult', name: 'مشاوره', parent_id: null },
];

test('firstRootSubsectionLabel shows first direct child under root, not deepest leaf', () => {
  assert.equal(firstRootSubsectionLabel({
    sectionId: 'body',
    rootSectionId: 'laser',
    sections: clinicTree,
    serviceName: 'فول‌بادی',
  }), 'الکساندرایت');

  assert.equal(firstRootSubsectionLabel({
    sectionId: 'alex',
    rootSectionId: 'laser',
    sections: clinicTree,
    serviceName: 'فول‌بادی',
  }), 'الکساندرایت');
});

test('firstRootSubsectionLabel walks sectionId→root recursively with no hardcoded depth', () => {
  // laser → alex → women → body (۴ سطح زیر ریشه) همچنان فقط فرزند مستقیم ریشه
  assert.equal(firstRootSubsectionLabel({
    sectionId: 'body',
    sections: clinicTree,
    serviceName: 'فول‌بادی',
  }), 'الکساندرایت');

  assert.equal(firstRootSubsectionLabel({
    sectionId: 'forehead',
    sections: clinicTree,
    serviceName: 'بوتاکس پیشانی',
  }), 'بوتاکس');
});

test('table examples: alex / diode / botox / skin consult', () => {
  assert.equal(firstRootSubsectionLabel({
    sectionId: 'women',
    rootSectionId: 'laser',
    sections: clinicTree,
    serviceName: 'فول‌بادی',
  }), 'الکساندرایت');

  assert.equal(firstRootSubsectionLabel({
    sectionId: 'diode-men',
    rootSectionId: 'laser',
    sections: clinicTree,
    serviceName: 'پشت',
  }), 'دیود');

  assert.equal(firstRootSubsectionLabel({
    sectionId: 'forehead',
    rootSectionId: 'inject',
    sections: clinicTree,
    serviceName: 'بوتاکس پیشانی',
  }), 'بوتاکس');

  // خدمت مستقیم زیر ریشه مشاوره → نام خود خدمت
  assert.equal(firstRootSubsectionLabel({
    sectionId: 'consult',
    rootSectionId: 'consult',
    sections: clinicTree,
    serviceName: 'مشاوره پوست',
  }), 'مشاوره پوست');
});

test('service directly under root shows service name', () => {
  assert.equal(firstRootSubsectionLabel({
    sectionId: 'laser',
    rootSectionId: 'laser',
    sections: clinicTree,
    serviceName: 'مشاوره لیزر',
  }), 'مشاوره لیزر');

  assert.equal(firstRootSubsectionLabel({
    sectionId: '',
    rootSectionId: '',
    sections: clinicTree,
    serviceName: 'خدمت آزاد',
  }), 'خدمت آزاد');
});

test('multiple services: same subsection once; different subsections joined by caller', () => {
  const labels = serviceButtonSummaryLabels([
    { name: 'فول‌بادی', sectionId: 'body', rootSectionId: 'laser' },
    { name: 'زیربغل', sectionId: 'women', rootSectionId: 'laser' },
    { name: 'پشت', sectionId: 'diode-men', rootSectionId: 'laser' },
    { name: 'بوتاکس پیشانی', sectionId: 'forehead', rootSectionId: 'inject' },
    { name: 'مشاوره پوست', sectionId: 'consult', rootSectionId: 'consult' },
  ], clinicTree);

  assert.deepEqual(labels, ['الکساندرایت', 'دیود', 'بوتاکس', 'مشاوره پوست']);
  assert.equal(labels.join('، '), 'الکساندرایت، دیود، بوتاکس، مشاوره پوست');
  assert.deepEqual(uniqueServiceSummaryLabels(['الکساندرایت', 'الکساندرایت', 'دیود']), ['الکساندرایت', 'دیود']);
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
