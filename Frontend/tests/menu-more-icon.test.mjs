import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/components/menu.vue', import.meta.url), 'utf8');

test('more navigation control is arrow-only and remains accessible', () => {
  const item = source.match(/<li\s+v-if="overflowItems\.length"[\s\S]*?<\/li>/)?.[0] || '';
  assert.doesNotMatch(item, /<span>بیشتر<\/span>/);
  assert.doesNotMatch(item, /<div class="menu-dot"><\/div>/);
  assert.match(item, /class="more-arrow"/);
  assert.match(item, /:aria-label="overflowOpen \? 'بستن منوی بیشتر' : 'باز کردن منوی بیشتر'"/);
  assert.match(source, /\.more-arrow\{width:24px;height:24px;[^}]*stroke-width:3;/);
});
