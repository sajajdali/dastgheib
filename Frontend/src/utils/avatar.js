const API_ORIGIN = ''

export function avatarUrl(entity) {
  const value = entity?.avatarUrl
    || entity?.avatar_url
    || entity?.profile_thumbnail_url
    || entity?.profile_photo_url
    || entity?.photo_url
    || entity?.profile_thumbnail_path
    || entity?.profile_photo_path
    || ''

  if (!value) return ''
  if (/^(https?:|data:|blob:)/i.test(value)) return value
  return `${API_ORIGIN}/${String(value).replace(/^\/+/, '')}`
}

export function avatarInitial(entity, fallback = '؟') {
  const name = String(entity?.name || entity?.user || entity?.fullName || '').trim()
  return name.charAt(0) || fallback
}

export function findResourceByName(resources, name) {
  return (resources || []).find(item => item?.name === name || item?.user === name) || null
}
