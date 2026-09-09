import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/parvande.vue', import.meta.url), 'utf8')

test('enabled optional patient fields are rendered in the profile view', () => {
  for (const key of [
    'national_id',
    'foreign_national_code',
    'father_name',
    'marriage_date',
    'education',
    'second_phone',
    'address'
  ]) {
    assert.match(source, new RegExp(`key: '${key}'`))
  }
  assert.match(source, /profileEnabledDetails\.length/)
  assert.match(source, /activeProfileFields\?\.\[field\.key\]/)
})

test('the secondary phone still uses the phone visibility policy', () => {
  assert.match(source, /field\.phone \? this\.displayPatientPhone\(rawValue\) : rawValue/)
})
