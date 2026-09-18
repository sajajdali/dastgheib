import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8')
const timeSource = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')

test('campaign appointment action opens the appointment timeline with followup context', () => {
  const start = source.indexOf('openRowAppointmentTimeline(row)')
  const end = source.indexOf('closeFollowupAppointmentModal()', start)
  const method = source.slice(start, end)

  assert.match(method, /closeCampaignModal\(\)/)
  assert.match(method, /\$emit\('open-appointments-timeline', \{ date: requestedDate, followup \}\)/)
  assert.match(method, /campaignId: this\.activeCampaign\.id/)
  assert.match(method, /rowId: row\._localId/)
  assert.doesNotMatch(method, /appointmentModalOpen = true/)
})

test('landing selector is layered above the campaign page', () => {
  assert.match(source, /\.landing-multi-menu-floating,[\s\S]*?\.landing-sms-modal-overlay[\s\S]*?z-index: 2147483560 !important/)
})

test('followup waits for the user to select a timeline day and time', () => {
  const start = timeSource.indexOf('async applyOpenViewRequest(request)')
  const end = timeSource.indexOf('showAvatarPreview(', start)
  const method = timeSource.slice(start, end)

  assert.match(method, /this\.pendingTimelineFollowup = request\.followup \|\| null/)
  assert.doesNotMatch(method, /openNewTimelineAppointment\(day\)/)
  assert.match(timeSource, /pendingTimelineFollowup && isEmptyAppointmentRow\(row\) \? 'is-followup-target'/)
  assert.match(timeSource, /applyFollowupPrefillToDraft\(this\.activeTimelineDraft, this\.pendingTimelineFollowup\)/)
})
