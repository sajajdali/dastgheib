import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8')

test('editable followup rows search patients by partial identity', () => {
  assert.match(source, /placeholder="نام، پرونده یا موبایل\.\.\."/)
  assert.match(source, /@input="onFollowupNameInput\(row\)"/)
  assert.match(source, /@focus="searchFollowupPatients\(row\)"/)
  assert.match(source, /axios\.get\('\/api\/patients\/search', \{ params: \{ q: query \} \}\)/)
  assert.match(source, /query\.length < 2/)
})

test('selecting a followup patient keeps the stable patient identity', () => {
  const start = source.indexOf('applyPatientToFollowupRow(row, patient) {')
  const end = source.indexOf('searchFollowupPatients(row) {', start)
  const method = source.slice(start, end)

  assert.ok(start > -1 && end > start)
  assert.match(method, /row\.fullName =/)
  assert.match(method, /row\.phone = patient\.phone/)
  assert.match(method, /row\.patient_id = patient\.id/)
  assert.match(method, /row\.file_number = patient\.file_number/)
  assert.match(method, /row\.customer_level =/)
})

test('followup autocomplete ignores stale requests and exposes useful result details', () => {
  const start = source.indexOf('searchFollowupPatients(row) {')
  const end = source.indexOf('closeFollowupPatientSearch(row = null) {', start)
  const method = source.slice(start, end)

  assert.ok(start > -1 && end > start)
  assert.match(method, /requestId !== this\.followupPatientSearchRequest/)
  assert.match(method, /this\.followupPatientSearchRowId !== rowId/)
  assert.match(source, /پرونده \{\{ patient\.file_number \|\| '—' \}\}/)
  assert.match(source, /displayPatientPhone\(patient\.phone, row\)/)
})
