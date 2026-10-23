import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8');

test('timeline appointment modal is above global navigation and month controls', () => {
  assert.match(
    source,
    /<Teleport to="body">\s*<div v-if="timelineModalOpen" class="timeline-modal-overlay"/,
  );
  assert.match(
    source,
    /\.timeline-modal-overlay\s*\{[\s\S]*?position:\s*fixed;[\s\S]*?inset:\s*0;[\s\S]*?z-index:\s*2147483645;/,
  );
});

test('timeline modal and service results expose a visible stable scrollbar', () => {
  assert.match(source, /\.timeline-modal-body\s*\{[^}]*overflow-y:\s*scroll;[^}]*scrollbar-gutter:\s*stable;[^}]*scrollbar-color:/s);
  assert.match(source, /\.timeline-service-multiselect \.multiselect__content-wrapper\s*\{[^}]*overflow-y:\s*auto;[^}]*scrollbar-gutter:\s*stable;/s);
});

test('service search input and selected label cannot overlap', () => {
  assert.match(source, /\.timeline-service-multiselect:not\(\.multiselect--active\) \.multiselect__input\s*\{\s*display:\s*none!important;/);
  assert.match(source, /\.timeline-service-multiselect\.multiselect--active \.multiselect__single\s*\{\s*display:\s*none!important;/);
});

test('timeline time picker remains above the teleported modal', () => {
  assert.match(source, /picker\.style\.setProperty\("z-index", "2147483646", "important"\)/);
  assert.match(source, /\.timeline-time-picker-layer\s*\{[^}]*z-index:\s*2147483646\s*!important;/s);
});
