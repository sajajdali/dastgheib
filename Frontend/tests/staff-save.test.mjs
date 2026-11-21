import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';
import { reactive, watch, nextTick } from 'vue';

const source = readFileSync(new URL('../src/components/manabe.vue', import.meta.url), 'utf8')
  .split('<script>')[1].split('</script>')[0]
  .replace(/^import .*$/gm, '').replace('export default', 'globalThis.component =');
function setup(fetch) {
  const context = vm.createContext({ CommissionRules: {}, PersonalReportColumns: {}, fetch, setTimeout, clearTimeout, console });
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
    const sent = JSON.parse(options.body).items; requests.push(sent);
    if (requests.length === 1) await new Promise(resolve => { release = resolve; });
    active--;
    return response(sent.map(row => ({ ...row, id: 9, save_version: `v${requests.length}` })));
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
    assert.equal(requests[1][0].save_version, 'v1');
    assert.equal(state.staffRows[0].salary, 200);
    assert.equal(state.staffRows[0].save_version, 'v2');
    assert.equal(state.staffRows.length, 2);
  } finally { app.cleanup(); }
});

test('a staff list loaded after a save does not wipe the saved values', async () => {
  const pending = {};
  const hold = key => new Promise(resolve => { pending[key] = resolve; });
  const json = body => ({ ok: true, json: async () => body });
  const app = setup(async (url, options) => {
    if (url === '/api/settings') return json({ users: [], roles: [] });
    if (url === '/api/inventory/context') return json({ sections: [] });
    if (url === '/api/doctors') { await hold('doctors'); return json([]); }
    if (url === '/api/channels') return json([]);
    if (url === '/api/staff' && options?.method === 'POST') {
      const items = JSON.parse(options.body).items;
      return json({ staff: items.map(row => ({ ...row, id: row.id || 1, save_version: 'v2' })) });
    }
    if (url === '/api/staff') { await hold('staff'); return json([{ id: 1, user_id: 2, name: 'الف', salary: 100, save_version: 'v1' }]); }
    return json([]);
  });
  try {
    const { state } = app;
    state.isSyncingStaffRows = true;
    Object.assign(state.staffRows[0], { id: 1, user_id: 2, name: 'الف', salary: 100, save_version: 'v1' });
    state.savedStaffPayloads['1'] = JSON.stringify(state.resourcePayload('staff', state.normalizeStaffRow(state.staffRows[0])));
    await nextTick();
    state.isSyncingStaffRows = false;
    const loading = state.fetchData();
    while (!pending.doctors) await Promise.resolve();
    state.staffRows[0].salary = 200;
    await nextTick();
    await state.autoSaveStaff();
    pending.doctors();
    while (!pending.staff) await Promise.resolve();
    pending.staff();
    await loading;
    await nextTick();
    assert.equal(state.staffRows[0].salary, 200);
    assert.equal(state.staffRows[0].save_version, 'v2');
    assert.equal(state.staffRows.filter(row => String(row.id) === '1').length, 1);
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
