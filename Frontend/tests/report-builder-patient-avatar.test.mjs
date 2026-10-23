import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/ReportBuilder.vue', import.meta.url), 'utf8')

test('report rows render a patient avatar whenever a patient is linked', () => {
  assert.match(source, /v-if="cell\.key === patientIdentityColumnKey && r\.patient\?\.id"/)
  assert.match(source, /<PatientAvatar[\s\S]*?:patient="r\.patient"[\s\S]*?:src="r\.patient\.avatar_url \|\| r\.patient\.profile_thumbnail_url \|\| r\.patient\.profile_photo_url \|\| ''"/)
  assert.doesNotMatch(source, /v-if="r\.patient\.avatar_url"/)
})
