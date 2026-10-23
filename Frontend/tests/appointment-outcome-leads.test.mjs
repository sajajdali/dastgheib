import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const notifications = readFileSync(new URL('../src/components/Notif.vue', import.meta.url), 'utf8')
const menu = readFileSync(new URL('../src/components/menu.vue', import.meta.url), 'utf8')

test('unresolved booked appointments stay in leads after their appointment day', () => {
  assert.match(notifications, /loadOverdueUnresolvedAppointmentNotifications\(\)/)
  assert.match(notifications, /filter\(appointment => this\.isPastAppointmentDay\(appointment\)\)/)
  assert.match(notifications, /status === "وقت داده شد" && !done/)
  assert.match(notifications, /را در روز <b>\$\{this\.appointmentJalaliDateLabel\(appointment\)\}<\/b> مشخص کنید/)
  assert.doesNotMatch(notifications, /unresolved-yesterday-appointment-/)
})

test('the leads badge counts all overdue unresolved booked appointments', () => {
  assert.match(menu, /countOverdueUnresolvedAppointments\(\)/)
  assert.match(menu, /filter\(appointment => this\.isPastAppointmentDay\(appointment\)\)/)
  assert.match(menu, /notificationCounts\.Vaghtdahi \+= await this\.countOverdueUnresolvedAppointments\(\)/)
})
