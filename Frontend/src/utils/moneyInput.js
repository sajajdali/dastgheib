const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
const ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩'

// رقم انگلیسی، فارسی و عربی هر سه قبول می‌شوند؛ جداکنندهٔ هزارگان فقط نمایش است.
export function moneyDigits(value) {
  return String(value ?? '')
    .replace(/[۰-۹]/g, digit => String(PERSIAN_DIGITS.indexOf(digit)))
    .replace(/[٠-٩]/g, digit => String(ARABIC_DIGITS.indexOf(digit)))
    .replace(/\D/g, '')
}

export function formatMoneyInput(value) {
  const digits = moneyDigits(value)
  const amount = digits ? Number(digits) : 0
  return amount ? amount.toLocaleString('en-US') : ''
}

function caretAfterDigits(formatted, count) {
  if (count <= 0) return 0
  let seen = 0
  for (let index = 0; index < formatted.length; index += 1) {
    const code = formatted.charCodeAt(index)
    if (code >= 48 && code <= 57) {
      seen += 1
      if (seen === count) return index + 1
    }
  }
  return formatted.length
}

// مقدار ذخیره‌شده عدد خام است؛ متن فیلد همان لحظه با جداکنندهٔ هزارگان بازنویسی می‌شود.
export function applyMoneyInput(input) {
  const raw = String(input.value)
  const caret = input.selectionStart ?? raw.length
  const digitsBeforeCaret = moneyDigits(raw.slice(0, caret)).length
  const formatted = formatMoneyInput(raw)
  const amount = formatted ? Number(moneyDigits(formatted)) : 0
  if (input.value !== formatted) {
    input.value = formatted
    const nextCaret = caretAfterDigits(formatted, digitsBeforeCaret)
    input.setSelectionRange(nextCaret, nextCaret)
  }
  return amount
}
