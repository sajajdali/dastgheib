// Jalali <-> Gregorian helpers. Dates are stored as JDN (integer day number).
const dv = (a, b) => ~~(a / b)
const BRK = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178]

function jalCal(jy) {
  let gy = jy + 621, leapJ = -14, jp = BRK[0], jm, jump = 0, n, i
  for (i = 1; i < BRK.length; i++) { jm = BRK[i]; jump = jm - jp; if (jy < jm) break; leapJ += dv(jump, 33) * 8 + dv(jump % 33, 4); jp = jm }
  n = jy - jp; leapJ += dv(n, 33) * 8 + dv((n % 33) + 3, 4)
  if (jump % 33 === 4 && jump - n === 4) leapJ++
  const leapG = dv(gy, 4) - dv((dv(gy, 100) + 1) * 3, 4) - 150, march = 20 + leapJ - leapG
  if (jump - n < 6) n = n - jump + dv(jump + 4, 33) * 33
  let leap = (((n + 1) % 33) - 1) % 4; if (leap === -1) leap = 4
  return { leap, gy, march }
}
export function g2d(gy, gm, gd) {
  const d = dv((gy + dv(gm - 8, 6) + 100100) * 1461, 4) + dv(153 * ((gm + 9) % 12) + 2, 5) + gd - 34840408
  return d - dv(dv(gy + 100100 + dv(gm - 8, 6), 100) * 3, 4) + 752
}
export function d2g(jdn) {
  let j = 4 * jdn + 139361631; j = j + dv(dv(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908
  const i = dv(j % 1461, 4) * 5 + 308, gd = dv(i % 153, 5) + 1, gm = (dv(i, 153) % 12) + 1, gy = dv(j, 1461) - 100100 + dv(8 - gm, 6)
  return [gy, gm, gd]
}
export function j2d(jy, jm, jd) { const r = jalCal(jy); return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - dv(jm, 7) * (jm - 7) + jd - 1 }
export function d2j(jdn) {
  const gy = d2g(jdn)[0]; let jy = gy - 621; const r = jalCal(jy); let k = jdn - g2d(gy, 3, r.march)
  if (k >= 0) { if (k <= 185) return [jy, 1 + dv(k, 31), (k % 31) + 1]; k -= 186 }
  else { jy -= 1; k += 179; if (r.leap === 1) k += 1 }
  return [jy, 7 + dv(k, 30), (k % 30) + 1]
}
export const monthLength = (jy, jm) => (jm <= 6 ? 31 : jm <= 11 ? 30 : jalCal(jy).leap === 0 ? 30 : 29)
export const weekday = d => (d + 1) % 7 // 0 = Sunday ... 6 = Saturday (same as Date.getDay)
export const weekStart = d => d - ((weekday(d) + 1) % 7) // Saturday
export const todayJdn = () => { const d = new Date(); return g2d(d.getFullYear(), d.getMonth() + 1, d.getDate()) }

// JDN <-> 'YYYY-MM-DD' (Gregorian, for API)
export const jdnToIso = d => d2g(d).map((v, i) => String(v).padStart(i ? 2 : 4, '0')).join('-')
export const isoToJdn = s => { const [y, m, d] = s.slice(0, 10).split('-').map(Number); return g2d(y, m, d) }
export const deadlineTs = (day, time) => { const [gy, gm, gd] = d2g(day); const [h, m] = (time || '00:00').split(':').map(Number); return new Date(gy, gm - 1, gd, h || 0, m || 0).getTime() }

export const WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه']
export const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']

export const fa = v => String(v ?? '').replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d])
export const money = n => Number(n || 0).toLocaleString('fa-IR')
export const shortMoney = n => (n % 1000 === 0 && n >= 1000 ? money(n / 1000) + ' هزار' : money(n))
export const toEnDigits = s => String(s).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '')
export const fullDate = d => { const [y, m, dd] = d2j(d); return `${WEEKDAYS[weekday(d)]} ${fa(dd)} ${MONTHS[m - 1]} ${fa(y)}` }
