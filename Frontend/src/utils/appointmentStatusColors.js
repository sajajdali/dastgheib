/**
 * Shared appointment-status colors for booking table, Timeline, and reports charts.
 * Always resolve by canonical status name/key — never by array index.
 */

export const APPOINTMENT_STATUS_DEFAULT_COLOR = '#94a3b8';

/** Hex colors matching tabular booking (`.st-*`) status cells. */
export const APPOINTMENT_STATUS_COLORS = Object.freeze({
  'وقت داده شد': '#0c8f3d',
  'آمد': '#b9f6ca',
  'کنسل شد': '#d50000',
  'پاسخ نداد': '#ffcdd2',
  'پیگیری': '#bbdefb',
  'انتقال داده شده': '#7c3aed',
});

/** Display/legacy aliases → canonical status name used by booking. */
export const APPOINTMENT_STATUS_ALIASES = Object.freeze({
  'انتقال روز': 'انتقال داده شده',
  'انتقال وقت': 'انتقال داده شده',
  'انتقال یافت': 'انتقال داده شده',
});

export const APPOINTMENT_STATUS_CSS_CLASS = Object.freeze({
  'وقت داده شد': 'st-given',
  'آمد': 'st-arrived',
  'کنسل شد': 'st-cancel',
  'پاسخ نداد': 'st-noans',
  'پیگیری': 'st-follow',
  'انتقال داده شده': 'st-transferred',
});

export function normalizeAppointmentStatusName(status) {
  const raw = String(status || '').trim();
  if (!raw || raw === '—') return '';
  return APPOINTMENT_STATUS_ALIASES[raw] || raw;
}

export function appointmentStatusChartColor(status) {
  const key = normalizeAppointmentStatusName(status);
  return APPOINTMENT_STATUS_COLORS[key] || APPOINTMENT_STATUS_DEFAULT_COLOR;
}

export function appointmentStatusCssClass(status) {
  const key = normalizeAppointmentStatusName(status);
  return APPOINTMENT_STATUS_CSS_CLASS[key] || '';
}
