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

/**
 * برچسب ستون خدمات جدول نوبت‌دهی: اولین زیربخش مستقیم زیر ریشه، نه برگ عمیق.
 * مسیر از sectionId تا ریشه با پیمایش والدها ساخته می‌شود (بدون عمق ثابت).
 * اگر خدمت مستقیم زیر ریشه باشد، نام خود خدمت برگردانده می‌شود.
 */
export function firstRootSubsectionLabel({
  sectionId = '',
  rootSectionId = '',
  sections = [],
  serviceName = '',
} = {}) {
  const name = String(serviceName || '').trim();
  const sid = String(sectionId || '').trim();
  let rootId = String(rootSectionId || '').trim();
  if (!sid) return name;

  const byId = new Map((sections || []).map(section => [String(section.id), section]));

  // اگر root داده نشده، ریشه واقعی را با پیمایش تا parent تهی پیدا کن.
  if (!rootId) {
    let probe = byId.get(sid);
    const seen = new Set();
    while (probe && !seen.has(String(probe.id))) {
      seen.add(String(probe.id));
      const parentKey = String(probe.parent_id || probe.parentId || '');
      if (!parentKey) {
        rootId = String(probe.id);
        break;
      }
      probe = byId.get(parentKey);
    }
  }

  if (!rootId || sid === rootId) return name;

  const pathUnderRoot = [];
  let current = byId.get(sid);
  const visited = new Set();
  while (current && !visited.has(String(current.id))) {
    visited.add(String(current.id));
    if (String(current.id) === rootId) break;
    pathUnderRoot.unshift(current);
    const parentKey = String(current.parent_id || current.parentId || '');
    if (!parentKey) break;
    current = byId.get(parentKey);
  }

  if (current && String(current.id) === rootId && pathUnderRoot.length) {
    return String(pathUnderRoot[0].name || '').trim() || name;
  }
  return name;
}

/** برچسب‌های یکتای دکمه خلاصه ستون خدمات (چند خدمت → یک‌بار هر زیربخش). */
export function uniqueServiceSummaryLabels(labels = []) {
  return [...new Set((labels || []).map(label => String(label || '').trim()).filter(Boolean))];
}

/** خلاصه چند خدمت روی درخت بخش‌ها؛ برای تست جدول نمونه و Time.vue. */
export function serviceButtonSummaryLabels(services = [], sections = []) {
  const labels = (services || []).map(service => firstRootSubsectionLabel({
    sectionId: service?.sectionId || service?.section_id || '',
    rootSectionId: service?.rootSectionId || service?.root_section_id || '',
    sections,
    serviceName: String(service?.name || '').trim(),
  }));
  return uniqueServiceSummaryLabels(labels);
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
