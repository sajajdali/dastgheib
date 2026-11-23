import { readFileSync } from 'node:fs'
import test from 'node:test'
import assert from 'node:assert/strict'
import {
  createFollowupNote,
  followupLatestDescription,
  followupNotesSearchText,
  followupRowHasDescription,
  latestFollowupNoteText,
  normalizeFollowupNotes,
  normalizeFollowupRowNotes,
  truncateFollowupNote,
} from '../src/utils/followupNotes.js'

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8')

test('legacy description becomes the first dated note and fills description', () => {
  const notes = normalizeFollowupNotes(undefined, 'تماس اول', 'سارا', 'row-a')
  assert.equal(notes.length, 1)
  assert.equal(notes[0].text, 'تماس اول')
  assert.equal(notes[0].author, 'سارا')
  assert.match(notes[0].id, /^note-/)
  const again = normalizeFollowupNotes(undefined, 'تماس اول', '', 'row-a')
  assert.equal(again[0].id, notes[0].id)
})

test('description always mirrors the newest note text', () => {
  const row = normalizeFollowupRowNotes({
    _localId: 'a',
    description: 'قدیمی',
    notes: [
      { id: 'n1', text: 'اول', created_at: '2026-11-01T10:00:00.000Z' },
      { id: 'n2', text: 'دوم', created_at: '2026-11-02T10:00:00.000Z' },
    ],
  })
  assert.equal(row.description, 'دوم')
  assert.equal(latestFollowupNoteText(row.notes), 'دوم')
})

test('createFollowupNote stamps author and timestamps', () => {
  const note = createFollowupNote('پیگیری بعدی', 'حنانه')
  assert.equal(note.text, 'پیگیری بعدی')
  assert.equal(note.author, 'حنانه')
  assert.ok(note.created_at)
  assert.ok(note.updated_at)
})

test('truncateFollowupNote shortens long table previews', () => {
  const text = truncateFollowupNote('الف'.repeat(100), 20)
  assert.ok(text.length <= 20)
  assert.match(text, /…$/)
})

test('followup description modal uses a chat-like notes thread', () => {
  assert.match(source, /description-notes-thread/)
  assert.match(source, /descriptionNotesDraft/)
  assert.match(source, /addDescriptionNote/)
  assert.match(source, /startEditDescriptionNote/)
  assert.match(source, /history-notes-thread/)
  assert.match(source, /historyLatestNotePreview/)
  assert.match(source, /truncateFollowupDescription\(row\)/)
})

test('adding or editing a note persists immediately without a bottom save', () => {
  assert.match(source, /persistDescriptionNotes\(\)/)
  const addMethod = source.match(/addDescriptionNote\(\) \{[\s\S]*?\n    \},/)?.[0] || ''
  const editMethod = source.match(/applyEditDescriptionNote\(note\) \{[\s\S]*?\n    \},/)?.[0] || ''
  assert.match(addMethod, /this\.persistDescriptionNotes\(\)/)
  assert.match(editMethod, /this\.persistDescriptionNotes\(\)/)
  assert.doesNotMatch(source, /ثبت یادداشت‌ها/)
  assert.doesNotMatch(source, /saveDescriptionModal\(/)
})

test('latest note wins for display helpers even when description is stale', () => {
  const row = {
    _localId: 'x',
    description: 'کهنه',
    notes: [
      { id: 'n1', text: 'اول', created_at: '2026-11-01T10:00:00.000Z' },
      { id: 'n2', text: 'آخرین ذخیره', created_at: '2026-11-03T10:00:00.000Z' },
    ],
  }
  assert.equal(followupLatestDescription(row), 'آخرین ذخیره')
  assert.equal(followupRowHasDescription({ description: '', notes: [{ id: 'n', text: 'فقط notes', created_at: '2026-11-01T10:00:00.000Z' }] }), true)
  assert.match(followupNotesSearchText(row), /آخرین ذخیره/)
})

test('campaign table export search and appointment prefill use latest description helpers', () => {
  assert.match(source, /\['توضیحات', row => this\.latestFollowupDescription\(row\)\]/)
  assert.match(source, /followupNotesSearchText\(r\)/)
  assert.match(source, /description: this\.latestFollowupDescription\(row\)/)
})
