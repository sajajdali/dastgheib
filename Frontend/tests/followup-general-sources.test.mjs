import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8')

test('general followups list the channels configured in resource settings', () => {
  assert.match(
    source,
    /v-if="isGeneralFollowupCampaign\(activeCampaign\)"[\s\S]*?<select v-model="row\.source"[\s\S]*?v-for="channel in channelOptions"[\s\S]*?:value="channel\.name"/
  )

  const start = source.indexOf('async openGeneralFollowups()')
  const end = source.indexOf('toggleArchivedView()', start)
  const method = source.slice(start, end)

  assert.match(method, /this\.loadChannels\(\)/)
})

test('general followups preserve a previously selected source that is no longer configured', () => {
  assert.match(
    source,
    /v-if="row\.source && !channelOptions\.some\(channel => channel\.name === row\.source\)"[\s\S]*?:value="row\.source"/
  )
})
