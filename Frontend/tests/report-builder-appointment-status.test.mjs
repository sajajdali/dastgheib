import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../src/components/ReportBuilder.vue', import.meta.url), 'utf8');

test('appointment status is a multi-select populated by the backend', () => {
  assert.match(source, /id:\s*'status'[\s\S]*?type:\s*'multi'/);
  assert.match(source, /axios\.get\('\/api\/report-builder\/options'\)/);
  assert.match(source, /this\.appointmentStatuses\s*=\s*data\.appointment_statuses/);
  assert.match(source, /filters:\s*\{\s*values:\s*this\.vals,\s*multi:\s*this\.multi/);
});
