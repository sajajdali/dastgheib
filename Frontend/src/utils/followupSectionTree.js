/** Helpers for hierarchical follow-up section filter/picker. */

export const UNKNOWN_SECTION_PATH = 'مسیر نامشخص';

export function normalizeSectionSearch(value) {
  return String(value || '')
    .replace(/ي/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/[\u200c\s]+/g, ' ')
    .trim()
    .toLowerCase();
}

export function sectionToken(id) {
  return `section:${id}`;
}

export function serviceToken(id) {
  return `service:${id}`;
}

export function parseSectionFilterToken(value) {
  const text = String(value || '');
  if (text === '__none__') return { type: 'none' };
  const section = text.match(/^section:(\d+)$/);
  if (section) return { type: 'section', id: Number(section[1]) };
  const service = text.match(/^service:(\d+)$/);
  if (service) return { type: 'service', id: Number(service[1]) };
  if (/^\d+$/.test(text)) return { type: 'section', id: Number(text) };
  return { type: 'label', label: text };
}

/**
 * @param {{ tree?: any[], sections?: any[], services?: any[] }} payload
 */
export function indexSectionTree(payload = {}) {
  const sections = Array.isArray(payload.sections) ? payload.sections : [];
  const services = Array.isArray(payload.services) ? payload.services : [];
  const tree = Array.isArray(payload.tree) ? payload.tree : [];
  const sectionById = new Map();
  const serviceById = new Map();
  const childrenByParent = new Map();

  for (const section of sections) {
    if (!section?.id) continue;
    sectionById.set(String(section.id), section);
    const parentKey = section.parent_id == null || section.parent_id === '' ? 'root' : String(section.parent_id);
    if (!childrenByParent.has(parentKey)) childrenByParent.set(parentKey, []);
    childrenByParent.get(parentKey).push(section);
  }

  for (const service of services) {
    if (!service?.id) continue;
    serviceById.set(String(service.id), service);
  }

  return { tree, sections, services, sectionById, serviceById, childrenByParent };
}

export function descendantSectionIds(sectionId, childrenByParent) {
  const ids = [];
  const stack = [String(sectionId)];
  const seen = new Set();
  while (stack.length) {
    const current = stack.pop();
    if (seen.has(current)) continue;
    seen.add(current);
    ids.push(Number(current));
    for (const child of childrenByParent.get(current) || []) {
      stack.push(String(child.id));
    }
  }
  return ids;
}

export function rowMatchesSectionFilters(row, selected, index) {
  const values = Array.isArray(selected) ? selected : [];
  if (!values.length) return true;

  const pathIds = Array.isArray(row?.sectionPathIds)
    ? row.sectionPathIds.map(Number)
    : [];
  const subsectionId = row?.subsectionId != null && row.subsectionId !== ''
    ? Number(row.subsectionId)
    : null;
  const serviceId = row?.serviceId != null && row.serviceId !== ''
    ? Number(row.serviceId)
    : null;
  const hasSection = Boolean(subsectionId || pathIds.length || row?.sectionPath || row?.subsectionName);

  return values.some((token) => {
    const parsed = parseSectionFilterToken(token);
    if (parsed.type === 'none') return !hasSection;
    if (parsed.type === 'service') return serviceId === parsed.id;
    if (parsed.type === 'section') {
      const descendants = new Set(
        descendantSectionIds(parsed.id, index?.childrenByParent || new Map())
      );
      if (subsectionId && descendants.has(subsectionId)) return true;
      if (pathIds.some((id) => descendants.has(id) || id === parsed.id)) return true;
      if (Number(row?.rootSectionId) === parsed.id) return true;
      return false;
    }
    const label = normalizeSectionSearch(parsed.label);
    if (!label) return false;
    return [row?.sectionPath, row?.subsectionName, row?.serviceName]
      .map(normalizeSectionSearch)
      .some((text) => text === label || text.includes(label));
  });
}

export function searchSectionOptions(index, term) {
  const needle = normalizeSectionSearch(term);
  const sections = (index?.sections || []).map((section) => ({
    type: 'section',
    id: section.id,
    value: sectionToken(section.id),
    label: section.path || section.name,
    name: section.name,
    path: section.path || section.name,
    pathIds: section.path_ids || [],
    isLeaf: Boolean(section.is_leaf),
  }));
  const services = (index?.services || []).map((service) => ({
    type: 'service',
    id: service.id,
    value: serviceToken(service.id),
    label: service.path || service.name,
    name: service.name,
    path: service.path || service.name,
    pathIds: service.path_ids || [],
    sectionId: service.section_id,
  }));
  const options = [...sections, ...services];
  if (!needle) return options;
  return options.filter((option) => normalizeSectionSearch(option.label).includes(needle)
    || normalizeSectionSearch(option.name).includes(needle));
}

export function selectionFieldsFromSection(section, service = null) {
  if (!section && !service) {
    return {
      rootSectionId: null,
      sectionPathIds: [],
      sectionPath: '',
      subsectionId: null,
      subsectionName: '',
      serviceId: null,
      serviceName: '',
    };
  }

  const pathIds = Array.isArray(section?.path_ids)
    ? section.path_ids.map(Number)
    : Array.isArray(service?.path_ids)
      ? service.path_ids.map(Number)
      : [];
  const path = service?.path || section?.path || section?.name || '';
  const leafId = pathIds.length ? pathIds[pathIds.length - 1] : (section?.id ?? null);
  const leafName = section?.name || '';

  return {
    rootSectionId: pathIds[0] ?? section?.id ?? null,
    sectionPathIds: pathIds,
    sectionPath: path,
    subsectionId: leafId,
    subsectionName: leafName,
    serviceId: service?.id ?? null,
    serviceName: service?.name || '',
  };
}
