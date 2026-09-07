import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

// Exercise the component's real methods without mounting the full calendar.
const source = readFileSync(new URL('../src/components/Time.vue', import.meta.url), 'utf8')
  .split('<script>')[1].split('</script>')[0]
  .replace(/^import .*;\s*$/gm, '')
  .replace('export default', 'globalThis.component =');
function calendar(rows, post) {
  const dialogs = [];
  const context = vm.createContext({
    DatePicker: {}, Multiselect: {}, PatientAvatar: {},
    axios: { post }, Swal: { fire: async options => dialogs.push(options) },
    setTimeout, clearTimeout, console: { error() {} },
  });
  vm.runInContext(source, context);
  const state = {
    days: [{ dayNum: 15, rows }], months: ['1405-06'], currentMonth: 0,
    saveWaiters: [], saveRetryCount: 0, draftRevision: 0,
    isFetching: false, saveInProgress: false, saveQueued: false,
  };
  for (const [name, method] of Object.entries(context.component.methods)) state[name] = method.bind(state);
  Object.assign(state, {
    expandedServices: () => [], calculateFinalAmount() {},
    normalizePaymentDetails: value => value,
    clearPendingDraft() {}, retrySave: () => false,
  });
  return { state, dialogs };
}
const row = (name = 'بیمار') => ({ lastname: name, time: '09:00', services: [], _rowId: name });

test('leaving an empty slot does not save or display an error', async () => {
  let requests = 0;
  const empty = row('');
  const { state, dialogs } = calendar([empty], async () => { requests++; });
  assert.equal(await state.persistDirectAppointment(empty), false);
  assert.equal(requests, 0);
  assert.equal(dialogs.length, 0);
});

test('simultaneous blur and submit share one confirmation and one write', async () => {
  let requests = 0;
  const booking = row();
  const { state, dialogs } = calendar([booking], async () => {
    requests++;
    return { data: { appointment: { id: 42, lock_version: 1 } } };
  });
  const first = state.persistDirectAppointment(booking);
  const second = state.persistDirectAppointment(booking);
  assert.equal(first, second);
  assert.equal(await first, true);
  assert.equal(requests, 1);
  assert.equal(booking.appointmentId, 42);
  assert.equal(dialogs.length, 0);
});

test('a different failing row does not lose the successful booking ID or resend it', async () => {
  const good = row('good'), bad = row('bad');
  const requests = [];
  const { state } = calendar([good, bad], async (_, payload) => {
    requests.push(payload.lastname);
    if (payload.lastname === 'bad') throw { response: { status: 409, data: { message: 'conflict' } } };
    return { data: { appointment: { id: 42, lock_version: 1 } } };
  });
  assert.equal(await state.persistDirectAppointment(good), true);
  assert.equal(good.appointmentId, 42);
  await state.saveData(0, true);
  assert.equal(requests.filter(name => name === 'good').length, 1);
});

test('validation failure displays the original error once without three retries', async () => {
  let requests = 0;
  const booking = row();
  const { state, dialogs } = calendar([booking], async () => {
    requests++;
    throw { response: { status: 422, data: { errors: { time: ['ساعت معتبر نیست'] } } } };
  });
  assert.equal(await state.persistDirectAppointment(booking), false);
  assert.equal(requests, 1);
  assert.equal(dialogs.length, 1);
  assert.equal(dialogs[0].text, 'ساعت معتبر نیست');
});

test('incomplete drafts do not prevent saving a complete booking', async () => {
  const booking = row(), incomplete = row('');
  incomplete.phone = '09120000000';
  let requests = 0;
  const { state } = calendar([incomplete, booking], async () => {
    requests++;
    return { data: { appointment: { id: 42, lock_version: 1 } } };
  });
  assert.equal(await state.persistDirectAppointment(booking), true);
  assert.equal(requests, 1);
});
