import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/ReportBuilder.vue', import.meta.url), 'utf8')

test('national id is an optional report column immediately after phone', () => {
  const phone = source.indexOf("id: 'phone', label: 'شماره تماس'")
  const nationalId = source.indexOf("id: 'nationalId', label: 'کد ملی'")
  const fileNumber = source.indexOf("id: 'fileNo', label: 'شماره پرونده'")

  assert.ok(phone > -1)
  assert.ok(nationalId > phone)
  assert.ok(fileNumber > nationalId)
  assert.match(source, /cols\(\) \{ return FIELDS\.filter\(f => this\.show\[f\.id\]\)/)
})

test('selected national id is sent to the report API', () => {
  assert.match(source, /columns: this\.cols\.map\(c => c\.key\)/)
  assert.match(source, /values: this\.vals/)
})
