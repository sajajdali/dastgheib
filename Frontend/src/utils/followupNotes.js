/** یادداشت‌های چندتایی توضیحات ردیف پیگیری کمپین. */

function makeNoteId() {
  return `note-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function stableNoteId(seed, text, kind) {
  const basis = `${seed || 'row'}|${kind}|${text}`
  let hash = 0
  for (let i = 0; i < basis.length; i += 1) {
    hash = ((hash << 5) - hash) + basis.charCodeAt(i)
    hash |= 0
  }
  return `note-${Math.abs(hash).toString(16).padStart(8, '0')}${String(text.length).padStart(4, '0')}`
}

function timestamp(value) {
  const text = String(value || '').trim()
  if (!text) return null
  const parsed = new Date(text)
  return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString()
}

export function normalizeFollowupNotes(notes, legacyDescription = '', fallbackAuthor = '', seed = '') {
  const normalized = []
  if (Array.isArray(notes)) {
    notes.forEach((note) => {
      if (!note || typeof note !== 'object') return
      const text = String(note.text || '').trim()
      if (!text) return
      const createdAt = timestamp(note.created_at) || new Date().toISOString()
      const updatedAt = timestamp(note.updated_at) || createdAt
      const author = String(note.author || fallbackAuthor || '').trim()
      normalized.push({
        id: String(note.id || '').trim() || stableNoteId(seed, text, 'missing'),
        text,
        created_at: createdAt,
        updated_at: updatedAt,
        author: author || null,
      })
    })
  }

  if (!normalized.length) {
    const legacy = String(legacyDescription || '').trim()
    if (legacy) {
      // زمان ثابت تا نرمال‌سازی سمت کلاینت بدون ذخیره، اثر انگشت را عوض نکند.
      const createdAt = '1970-01-01T00:00:00.000Z'
      const author = String(fallbackAuthor || '').trim()
      normalized.push({
        id: stableNoteId(seed, legacy, 'legacy'),
        text: legacy,
        created_at: createdAt,
        updated_at: createdAt,
        author: author || null,
      })
    }
  }

  normalized.sort((a, b) => String(a.created_at).localeCompare(String(b.created_at)) || String(a.id).localeCompare(String(b.id)))
  return normalized
}

export function latestFollowupNoteText(notes) {
  if (!Array.isArray(notes) || !notes.length) return ''
  return String(notes[notes.length - 1]?.text || '').trim()
}

export function normalizeFollowupRowNotes(row, fallbackAuthor = '') {
  if (!row || typeof row !== 'object') return row
  const seed = String(row._localId || row.rowId || '')
  const notes = normalizeFollowupNotes(row.notes, row.description, fallbackAuthor, seed)
  row.notes = notes
  row.description = latestFollowupNoteText(notes)
  return row
}

/** متن نمایشی/خروجی: همیشه آخرین یادداشت (یا description هم‌تراز). */
export function followupLatestDescription(row) {
  if (!row || typeof row !== 'object') return ''
  const notes = normalizeFollowupNotes(row.notes, row.description, '', String(row._localId || row.rowId || ''))
  return latestFollowupNoteText(notes) || String(row.description || '').trim()
}

/** آیا ردیف حداقل یک یادداشت/توضیح دارد (حتی اگر description کهنه باشد). */
export function followupRowHasDescription(row) {
  return Boolean(followupLatestDescription(row))
}

/** متن قابل جست‌وجو از همه یادداشت‌ها + description. */
export function followupNotesSearchText(row) {
  if (!row || typeof row !== 'object') return ''
  const notes = normalizeFollowupNotes(row.notes, row.description, '', String(row._localId || row.rowId || ''))
  return [latestFollowupNoteText(notes), ...notes.map(note => note.text), row.description]
    .filter(Boolean)
    .join(' ')
}

export function truncateFollowupNote(text, max = 72) {
  const value = String(text || '').replace(/\s+/g, ' ').trim()
  if (!value) return ''
  if (value.length <= max) return value
  return `${value.slice(0, Math.max(0, max - 1)).trim()}…`
}

export function createFollowupNote(text, author = '') {
  const now = new Date().toISOString()
  const cleaned = String(text || '').trim()
  const who = String(author || '').trim()
  return {
    id: makeNoteId(),
    text: cleaned,
    created_at: now,
    updated_at: now,
    author: who || null,
  }
}
