import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')

test('services and amount columns keep their complete controls when swapped', () => {
  const headerStart = source.indexOf('<thead>')
  const headerEnd = source.indexOf('</thead>', headerStart)
  const header = source.slice(headerStart, headerEnd)

  const serviceHeader = header.indexOf('class="sticky-header service-col resizable-th"')
  const amountHeader = header.indexOf('class="sticky-header amount-col resizable-th"')
  assert.ok(serviceHeader > -1 && amountHeader > -1)
  assert.ok(serviceHeader < amountHeader, 'services header must appear before amount header')
  assert.match(header.slice(serviceHeader, amountHeader), /openServiceFilterModal/)
  assert.match(header.slice(serviceHeader, amountHeader), /startResize\(\$event, 'service'\)/)
  assert.match(header.slice(amountHeader), /openAmountFilterModal/)
  assert.match(header.slice(amountHeader), /startResize\(\$event, 'amount'\)/)

  const bodyStart = source.indexOf('<tbody', headerEnd)
  const bodyEnd = source.indexOf('</tbody>', bodyStart)
  const body = source.slice(bodyStart, bodyEnd)
  const serviceCell = body.indexOf('class="service-col service-cell"')
  const amountCell = body.indexOf('class="amount-col"')
  assert.ok(serviceCell > -1 && amountCell > -1)
  assert.ok(serviceCell < amountCell, 'services cells must appear before amount cells')
  assert.match(body.slice(serviceCell, amountCell), /toggleServicePopup/)
  assert.match(body.slice(amountCell), /openFinancialPanel/)
})
