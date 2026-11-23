import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { isBookingDayCapacityFull } from '../src/utils/bookingDayCapacity.js'

const timeSource = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')
const reportSource = readFileSync(new URL('../src/components/ReportBuilder.vue', import.meta.url), 'utf8')

test('capacity full only when filter is active and no empty bookable slots remain', () => {
  assert.equal(isBookingDayCapacityFull({ filterActive: false, emptyBookableCount: 0, matchingFilledCount: 5 }), false)
  assert.equal(isBookingDayCapacityFull({ filterActive: true, emptyBookableCount: 2, matchingFilledCount: 3 }), false)
  assert.equal(isBookingDayCapacityFull({ filterActive: true, emptyBookableCount: 0, matchingFilledCount: 0 }), false)
  assert.equal(isBookingDayCapacityFull({ filterActive: true, emptyBookableCount: 0, matchingFilledCount: 4 }), true)
})

test('table and timeline day headers show تکمیل ظرفیت via shared capacity helper', () => {
  assert.match(timeSource, /import \{ isBookingDayCapacityFull as bookingDayCapacityFull \} from '\.\.\/utils\/bookingDayCapacity'/)
  assert.match(timeSource, /class="day-label"[\s\S]*?isBookingDayCapacityFull\(day\)[\s\S]*?تکمیل ظرفیت/)
  assert.match(timeSource, /class="timeline-day-label"[\s\S]*?isBookingDayCapacityFull\(day\)[\s\S]*?تکمیل ظرفیت/)
  assert.match(timeSource, /isBookingDayCapacityFull\(day\) \{[\s\S]*?bookingTimelineFilterActive/)
  assert.match(timeSource, /bookingScheduleAllowsRow\(day, row\)/)
  assert.match(timeSource, /bookingSlotIsOccupied\(day, row\)/)
})

test('report builder money compare fields start empty and use دقیقاً برابر با label', () => {
  assert.match(reportSource, /<option value="eq">دقیقاً برابر با<\/option>/)
  assert.doesNotMatch(reportSource, /id: 'walletDeposit'[\s\S]{0,80}ph:\s*'/)
  assert.doesNotMatch(reportSource, /id: 'walletWithdraw'[\s\S]{0,80}ph:\s*'/)
  assert.doesNotMatch(reportSource, /id: 'doctorCommission'[\s\S]{0,80}ph:\s*'/)
  assert.doesNotMatch(reportSource, /id: 'consultantCommission'[\s\S]{0,80}ph:\s*'/)
  assert.doesNotMatch(reportSource, /id: 'discount'[\s\S]{0,60}ph:\s*'تومان'/)
  assert.match(reportSource, /id: 'walletDeposit', label: 'واریز کیف پول', type: 'compare' \}/)
})
