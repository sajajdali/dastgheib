/**
 * Shared appointment-status colors for booking table, Timeline, and reports charts.
 * Always resolve by canonical status name/key — never by array index.
 *
 * Stable key `waiting_slot` maps to Persian label «در انتظار وقت».
 */

export const APPOINTMENT_STATUS_DEFAULT_COLOR = '#94a3b8';

/** Stable machine keys → canonical Persian labels stored on appointments. */
export const APPOINTMENT_STATUS_KEYS = Object.freeze({
  waiting_slot: 'در انتظار وقت',
  scheduled: 'وقت داده شد',
  arrived: 'آمد',
  cancelled: 'کنسل شد',
  no_answer: 'پاسخ نداد',
  followup: 'پیگیری',
  transferred: 'انتقال داده شده',
});

export const WAITING_SLOT_STATUS = APPOINTMENT_STATUS_KEYS.waiting_slot;

/** Hex colors matching tabular booking (`.st-*`) status cells and Timeline cards. */
export const APPOINTMENT_STATUS_COLORS = Object.freeze({
  'در انتظار وقت': '#facc15',
  'وقت داده شد': '#1d4ed8',
  'آمد': '#bfdbfe',
  'کنسل شد': '#d50000',
  'پاسخ نداد': '#ffcdd2',
  'پیگیری': '#bbdefb',
  'انتقال داده شده': '#7c3aed',
});

/** Display/legacy aliases → canonical status name used by booking. */
export const APPOINTMENT_STATUS_ALIASES = Object.freeze({
  waiting_slot: 'در انتظار وقت',
  'درانتظار وقت': 'در انتظار وقت',
  'انتقال روز': 'انتقال داده شده',
  'انتقال وقت': 'انتقال داده شده',
  'انتقال یافت': 'انتقال داده شده',
});

export const APPOINTMENT_STATUS_CSS_CLASS = Object.freeze({
  'در انتظار وقت': 'st-waiting-slot',
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

export function isWaitingSlotStatus(status) {
  return normalizeAppointmentStatusName(status) === WAITING_SLOT_STATUS;
}

export function appointmentStatusChartColor(status) {
  const key = normalizeAppointmentStatusName(status);
  return APPOINTMENT_STATUS_COLORS[key] || APPOINTMENT_STATUS_DEFAULT_COLOR;
}

export function appointmentStatusCssClass(status) {
  const key = normalizeAppointmentStatusName(status);
  return APPOINTMENT_STATUS_CSS_CLASS[key] || '';
}
