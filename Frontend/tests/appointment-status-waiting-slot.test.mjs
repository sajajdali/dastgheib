import test from 'node:test';
import assert from 'node:assert/strict';
import {
  APPOINTMENT_STATUS_COLORS,
  APPOINTMENT_STATUS_CSS_CLASS,
  APPOINTMENT_STATUS_KEYS,
  WAITING_SLOT_STATUS,
  appointmentStatusChartColor,
  appointmentStatusCssClass,
  isWaitingSlotStatus,
  normalizeAppointmentStatusName,
} from '../src/utils/appointmentStatusColors.js';

test('waiting_slot key maps to Persian label and yellow color', () => {
  assert.equal(APPOINTMENT_STATUS_KEYS.waiting_slot, 'در انتظار وقت');
  assert.equal(WAITING_SLOT_STATUS, 'در انتظار وقت');
  assert.equal(normalizeAppointmentStatusName('waiting_slot'), WAITING_SLOT_STATUS);
  assert.equal(isWaitingSlotStatus('waiting_slot'), true);
  assert.equal(isWaitingSlotStatus('در انتظار وقت'), true);
  assert.equal(isWaitingSlotStatus('وقت داده شد'), false);
  assert.equal(APPOINTMENT_STATUS_COLORS[WAITING_SLOT_STATUS], '#facc15');
  assert.equal(appointmentStatusChartColor(WAITING_SLOT_STATUS), '#facc15');
  assert.equal(APPOINTMENT_STATUS_CSS_CLASS[WAITING_SLOT_STATUS], 'st-waiting-slot');
  assert.equal(appointmentStatusCssClass('waiting_slot'), 'st-waiting-slot');
});
