import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/parvande.vue', import.meta.url), 'utf8')

test('enabled optional patient fields are rendered in the profile view', () => {
  for (const key of [
    'national_id',
    'foreign_national_code',
    'father_name',
    'marital_status',
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

test('patient age distinguishes stored Gregorian dates from Jalali input', () => {
  assert.match(source, /const parts = normalized\.match\(\/\^\(\\d\{4\}\)\\\/\(\\d\{1,2\}\)\\\/\(\\d\{1,2\}\)\$\//)
  assert.match(source, /year >= 1700/)
  assert.match(source, /parsed\.jYear\(\) !== year/)
  assert.match(source, /parsed\.jMonth\(\) \+ 1 !== month/)
  assert.match(source, /parsed\.jDate\(\) !== day/)
  assert.match(source, /moment\(\)\.diff\(parsed, 'years'\)/)
})

test('patient age shows a dash when no complete valid birth date exists', () => {
  assert.match(source, /String\(birthDate \?\? ''\)\.trim\(\)/)
  assert.match(source, /if \(!parts\) return '-'/)
  assert.match(source, /if \(month < 1 \|\| month > 12 \|\| day < 1 \|\| day > 31\) return '-'/)
})

test('patient age does not describe an infant as zero years old', () => {
  assert.match(source, /if \(age < 0\) return '-'/)
  assert.match(source, /age === 0 \? 'کمتر از یک سال' : `\$\{age\} سال`/)
})

test('the secondary phone still uses the phone visibility policy', () => {
  assert.match(source, /field\.phone\s*\? this\.displayPatientPhone\(rawValue\)\s*:/)
})
