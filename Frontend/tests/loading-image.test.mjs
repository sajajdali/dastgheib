import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const component = readFileSync(new URL('../src/components/LoadingImage.vue', import.meta.url), 'utf8')
const photos = readFileSync(new URL('../src/components/Photos.vue', import.meta.url), 'utf8')
const parvande = readFileSync(new URL('../src/components/parvande.vue', import.meta.url), 'utf8')
const dermatracker = readFileSync(new URL('../src/components/dermatracker.vue', import.meta.url), 'utf8')
const time = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')

test('LoadingImage shows a spinner until onLoad and resets when src changes', () => {
  assert.match(component, /loading-image__spinner/)
  assert.match(component, /is-loading/)
  assert.match(component, /watch:\s*\{\s*src:/)
  assert.match(component, /@load="onLoad"/)
  assert.match(component, /@error="onError"/)
  assert.match(component, /syncCachedState/)
})

test('photos module before/after and next navigation use LoadingImage', () => {
  assert.match(photos, /import LoadingImage from '\.\/LoadingImage\.vue'/)
  assert.match(photos, /<LoadingImage v-if="selected\.before"/)
  assert.match(photos, /<LoadingImage v-if="selected\.after"/)
  assert.match(photos, /selectAdjacentResult\(1\)/)
  assert.match(photos, /photos-loading/)
})

test('parvande galleries and before/after compare use LoadingImage', () => {
  assert.match(parvande, /import LoadingImage from '\.\/LoadingImage\.vue'/)
  assert.match(parvande, /openBeforeAfterCompare/)
  assert.match(parvande, /beforeAfterCompareLoading/)
  assert.match(parvande, /activeBeforeAfterPair\.before[\s\S]*LoadingImage/)
  assert.match(parvande, /activeBeforeAfterPair\.after[\s\S]*LoadingImage/)
  assert.match(parvande, /media-preview[\s\S]*LoadingImage/)
  assert.match(parvande, /mediaViewerItem[\s\S]*LoadingImage/)
})

test('زیبایار dermatracker photo surfaces use LoadingImage while keeping lightbox load tracking', () => {
  assert.match(dermatracker, /import LoadingImage from '\.\/LoadingImage\.vue'/)
  assert.match(dermatracker, /beauty-flow-photo-stage[\s\S]*LoadingImage/)
  assert.match(dermatracker, /beauty-compare-photo[\s\S]*LoadingImage/)
  assert.match(dermatracker, /markPhotoLoaded/)
  assert.match(dermatracker, /bf-photo-spinner/)
})

test('appointment patient profile media thumbnails use LoadingImage', () => {
  assert.match(time, /import LoadingImage from '\.\/LoadingImage\.vue'/)
  assert.match(time, /time-profile-photo-list[\s\S]*LoadingImage/)
})
