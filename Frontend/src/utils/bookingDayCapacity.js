/**
 * آیا ظرفیت نوبت‌پذیر یک روز برای فیلتر پزشک/بخش تکمیل است؟
 * فقط وقتی فیلتر فعال است معنا دارد؛ بدون فیلتر همیشه false.
 */
export function isBookingDayCapacityFull({
  filterActive = false,
  emptyBookableCount = 0,
  matchingFilledCount = 0,
} = {}) {
  if (!filterActive) return false;
  return Number(emptyBookableCount) <= 0 && Number(matchingFilledCount) > 0;
}
