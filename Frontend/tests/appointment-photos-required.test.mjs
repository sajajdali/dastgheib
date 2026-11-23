import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
  appointmentPhotosRequired,
  sectionPhotosRequired,
} from '../src/utils/appointmentPhotosRequired.js';

const anbar = readFileSync(new URL('../src/components/anbar.vue', import.meta.url), 'utf8');
const time = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8');

test('section photos default to required', () => {
  assert.equal(sectionPhotosRequired(undefined), true);
  assert.equal(sectionPhotosRequired({ id: 1 }), true);
  assert.equal(sectionPhotosRequired({ id: 1, photos_required: true }), true);
  assert.equal(sectionPhotosRequired({ id: 1, photos_required: false }), false);
});

test('appointment requires photos when any service section requires them', () => {
  const sections = [
    { id: 10, photos_required: false },
    { id: 20, photos_required: true },
  ];
  assert.equal(
    appointmentPhotosRequired({ services: [{ name: 'لیزر', sectionId: 10 }] }, sections),
    false
  );
  assert.equal(
    appointmentPhotosRequired({
      services: [
        { name: 'لیزر', sectionId: 10 },
        { name: 'ژل', sectionId: 20 },
      ],
    }, sections),
    true
  );
  assert.equal(appointmentPhotosRequired({ services: [] }, sections), true);
});

test('inventory tree exposes a camera toggle for section photo requirement', () => {
  assert.match(anbar, /tree-photo-btn/);
  assert.match(anbar, /toggleSectionPhotosRequired/);
  assert.match(anbar, /photos_required/);
});

test('booking photo indicators respect appointmentPhotosRequired', () => {
  assert.match(time, /appointmentPhotosRequired/);
  assert.match(time, /برای این خدمت ثبت عکس قبل و بعد الزامی نیست/);
});
