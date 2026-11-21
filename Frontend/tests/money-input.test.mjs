import test from 'node:test'
import assert from 'node:assert/strict'
import { applyMoneyInput, formatMoneyInput } from '../src/utils/moneyInput.js'

function field(value, caret = value.length) {
  return {
    value,
    selectionStart: caret,
    selectionEnd: caret,
    setSelectionRange(start, end) {
      this.selectionStart = start
      this.selectionEnd = end
    },
  }
}

test('money fields group digits while typing and keep a number', () => {
  assert.equal(formatMoneyInput(5600000), '5,600,000')
  assert.equal(formatMoneyInput(0), '')

  const ascii = field('100000000')
  assert.equal(applyMoneyInput(ascii), 100000000)
  assert.equal(ascii.value, '100,000,000')
  assert.equal(ascii.selectionStart, ascii.value.length)

  const persian = field('۲۵۰۰۰۰۰۰')
  assert.equal(applyMoneyInput(persian), 25000000)
  assert.equal(persian.value, '25,000,000')

  const grouped = field('۱٬۰۰۰٬۰۰۰')
  assert.equal(applyMoneyInput(grouped), 1000000)
  assert.equal(grouped.value, '1,000,000')

  const mid = field('1010,000,000', 3)
  assert.equal(applyMoneyInput(mid), 1010000000)
  assert.equal(mid.value, '1,010,000,000')
  assert.equal(mid.selectionStart, 4)

  const cleared = field('')
  assert.equal(applyMoneyInput(cleared), 0)
  assert.equal(cleared.value, '')
})
