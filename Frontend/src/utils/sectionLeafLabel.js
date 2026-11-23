/** آخرین (عمیق‌ترین) بخش مسیر زیربخش؛ والدین تکرار نمی‌شوند. */
export function sectionLeafLabel(value) {
  const text = String(value || '')
    .replace(/ي/g, 'ی')
    .replace(/ك/g, 'ک')
    .trim();
  if (!text) return '';
  const parts = text
    .split(/\s*(?:←|→|->|\/|‹|›)\s*/)
    .map(part => part.trim())
    .filter(Boolean);
  return parts[parts.length - 1] || text;
}

/** آیا مقدار تاریخ در بازهٔ اختیاری [from, to] (هر دو شامل) است. */
export function dateInInclusiveRange(value, from, to) {
  const day = String(value || '').slice(0, 10);
  if (!from && !to) return true;
  if (!day) return false;
  if (from && day < from) return false;
  if (to && day > to) return false;
  return true;
}
