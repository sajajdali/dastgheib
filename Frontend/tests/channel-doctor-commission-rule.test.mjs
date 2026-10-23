import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const resources = readFileSync(new URL('../src/components/manabe.vue', import.meta.url), 'utf8');
const appointments = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8');

test('channel settings expose a doctor-only commission exclusion', () => {
  assert.match(resources, /v-model="activeChannel\.exclude_doctor_commission"/);
  assert.match(resources, /عدم تعلق پورسانت به پزشک/);
  assert.match(resources, /پورسانت پرسنل و مبالغ مالی تغییر نمی‌کند/);
});

test('appointment financial previews suppress only the doctor share', () => {
  assert.match(appointments, /commissionBase\(doctor, row, serviceAmount, materialCost, 'doctor'\)/);
  assert.match(appointments, /commissionBase\(consultant, row, serviceAmount, materialCost, 'staff'\)/);
  assert.match(appointments, /recipientType === 'doctor' && this\.sourceBlocksDoctorCommission\(row\)/);
});
