import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8');

test('appointment tag picker uses only tags assigned to the selected inventory service', () => {
  assert.match(source, /v-for="tag in serviceTagsForSelection\(service, row\)"/);
  assert.match(
    source,
    /serviceTagsForSelection\(service, row = null\)[\s\S]*?const inventory = this\.inventoryForService\(service, row\)[\s\S]*?inventory\.service_tags \|\| inventory\.serviceTags/
  );
  assert.doesNotMatch(source, /serviceTagsForSection\(service\.sectionId\)/);
});

test('changing a service removes tags that are not assigned to the new service', () => {
  assert.match(
    source,
    /onServiceNameChanged\(service, row\)[\s\S]*?const allowedTags = new Set\(this\.serviceTagsForSelection\(service, row\)\)[\s\S]*?service\.tags = \(service\.tags \|\| \[\]\)\.filter\(tag => allowedTags\.has\(tag\)\)/
  );
});

test('the open tag or service dropdown is layered above the other service rows', () => {
  assert.match(source, /'service-item-layer-active': activeServiceTagPicker === service/);
  assert.match(source, /\.service-item:has\(\.multiselect--active\)[\s\S]*?z-index: 1000/);
  assert.match(source, /\.service-item \.service-multiselect\.multiselect--active[\s\S]*?z-index: 1001/);
});
