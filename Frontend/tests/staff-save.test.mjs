import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';
import { reactive, watch, nextTick } from 'vue';

const source = readFileSync(new URL('../src/components/manabe.vue', import.meta.url), 'utf8')
  .split('<script>')[1].split('</script>')[0]
  .replace(/^import .*$/gm, '').replace('export default', 'globalThis.component =');
function setup(fetch) {
  const context = vm.createContext({ CommissionRules: {}, fetch, setTimeout, clearTimeout, console });
  vm.runInContext(source, context);
  const component = context.component;
  const state = reactive(component.data());
  for (const [key, value] of Object.entries(component.methods)) state[key] = value.bind(state);
  state.$nextTick = nextTick;
  const stop = watch(() => state.staffRows, component.watch.staffRows.handler.bind(state), { deep: true });
  return { state, cleanup() { stop(); clearTimeout(state.saveTimeout); } };
}
const response = staff => ({ ok: true, json: async () => ({ staff }) });

test('adding and saving preserves row order, object, key and blank drafts', async () => {
  const app = setup(async () => response([{ id: 9, user_id: 2, name: 'الف' }, { id: 1, user_id: 1, name: 'ی' }]));
  try {
    const { state } = app;
    Object.assign(state.staffRows[0], { id: 1, user_id: 1, name: 'ی' });
    state.addStaffRow();
    const added = state.staffRows[1], key = added._staffKey;
    Object.assign(added, { user_id: 2, name: 'الف' });
    state.addStaffRow();
    const blank = state.staffRows[2];
    await nextTick();
    await state.autoSaveStaff();
    assert.equal(state.staffRows[1], added);
    assert.equal(added._staffKey, key);
    assert.equal(added.id, 9);
    assert.equal(state.staffRows[2], blank);
    assert.equal(state.staffRows[0].id, 1);
  } finally { app.cleanup(); }
});

test('edits during a pending save retain values and send a serialized followup with assigned ID', async () => {
  let release, active = 0, maximum = 0;
  const requests = [];
  const app = setup(async (_, options) => {
    active++; maximum = Math.max(maximum, active);
    const sent = JSON.parse(options.body); requests.push(sent);
    if (requests.length === 1) await new Promise(resolve => { release = resolve; });
    active--;
    return response(sent.map(row => ({ ...row, id: 9 })));
  });
  try {
    const { state } = app;
    Object.assign(state.staffRows[0], { user_id: 2, name: 'الف', salary: 100 });
    await nextTick();
    const first = state.autoSaveStaff();
    state.staffRows[0].salary = 200;
    state.addStaffRow();
    await nextTick();
    assert.equal(state.autoSaveStaff(), first);
    release();
    await first;
    assert.equal(maximum, 1);
    assert.equal(requests.length, 2);
    assert.equal(requests[1][0].id, 9);
    assert.equal(requests[1][0].salary, 200);
    assert.equal(state.staffRows[0].salary, 200);
    assert.equal(state.staffRows.length, 2);
  } finally { app.cleanup(); }
});

test('save failure is visible and retains the unsaved selection', async () => {
  const app = setup(async () => ({ ok: false, json: async () => ({ message: 'اجازه ذخیره ندارید' }) }));
  try {
    Object.assign(app.state.staffRows[0], { user_id: 2, name: 'الف' });
    await nextTick();
    assert.equal(await app.state.autoSaveStaff(), null);
    assert.equal(app.state.staffSaveError, true);
    assert.equal(app.state.staffSaveMessage, 'اجازه ذخیره ندارید');
    assert.equal(app.state.staffRows[0].user_id, 2);
  } finally { app.cleanup(); }
});
