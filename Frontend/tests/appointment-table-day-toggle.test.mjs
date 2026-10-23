import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')

test('the whole table day header toggles the day without a separate arrow button', () => {
  assert.match(source, /<td[\s\S]*?:aria-expanded="!day\.collapsed"[\s\S]*?@click="toggleDay\(day\)"[\s\S]*?>/)
  assert.match(source, /@keydown\.enter\.self\.prevent="toggleDay\(day\)"/)
  assert.match(source, /@keydown\.space\.self\.prevent="toggleDay\(day\)"/)
  assert.doesNotMatch(source, /class="collapse-btn"/)
  assert.doesNotMatch(source, /@click\.stop="removeRow\(day\)"/)
})
