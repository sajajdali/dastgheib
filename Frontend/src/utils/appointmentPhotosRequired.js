/**
 * آیا بخش/زیربخش خدمات نیاز به عکس قبل و بعد دارد؟ پیش‌فرض: بله.
 */
export function sectionPhotosRequired(section) {
  if (!section) return true;
  if (Object.prototype.hasOwnProperty.call(section, 'photos_required')) {
    return section.photos_required !== false;
  }
  if (Object.prototype.hasOwnProperty.call(section, 'photosRequired')) {
    return section.photosRequired !== false;
  }
  return true;
}

/**
 * نوبت وقتی عکس می‌خواهد که حداقل یک خدمتش الزام عکس داشته باشد.
 * فقط وقتی همهٔ خدمات الزام نداشته باشند، اندیکاتور خاکستری/غیرفعال است.
 */
export function appointmentPhotosRequired(row, sections = [], resolveSectionId = () => '') {
  const services = (row?.services || []).filter(service =>
    String(service?.name || '').trim()
    || service?.sectionId
    || service?.section_id
  );
  if (!services.length) return true;

  const byId = new Map((sections || []).map(section => [String(section.id), section]));

  return services.some(service => {
    const sectionId = String(
      service.sectionId
      || service.section_id
      || resolveSectionId(service, row)
      || ''
    );
    if (!sectionId) return true;
    return sectionPhotosRequired(byId.get(sectionId));
  });
}
