import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')

test('daily appointment report is teleported above global navigation and month controls', () => {
  assert.match(source, /<Teleport to="body">\s*<div v-if="dailyReportModalOpen" class="daily-report-overlay"/)
  assert.match(source, /\.daily-report-overlay\s*\{[\s\S]*?position:\s*fixed;[\s\S]*?inset:\s*0;[\s\S]*?z-index:\s*2147483646;/)
})
