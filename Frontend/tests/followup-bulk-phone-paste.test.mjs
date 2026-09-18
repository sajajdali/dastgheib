import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../src/components/flwup.vue', import.meta.url), 'utf8')
  .split('<script>')[1].split('</script>')[0]
  .replace(/^import .*;?\s*$/gm, '')
  .replace('export default', 'globalThis.component =');

const context = vm.createContext({
  axios: {}, moment: () => ({}), DatePicker: {}, draggable: {},
  avatarInitial: () => '', avatarUrl: () => '', findResourceByName: () => null,
  setTimeout, clearTimeout, console,
});
vm.runInContext(source, context);

function bulkPasteState() {
  const target = { _localId: 'first', phone: '', fullName: '' };
  const state = {
    activeCampaign: { rows: [target] },
    bulkPhonePasteNotice: '', bulkPhonePasteNoticeTimer: null,
  };
  for (const name of ['normalizeBulkMobile', 'extractBulkMobiles', 'createEmptyRow', 'handleBulkPhonePaste']) {
    state[name] = context.component.methods[name].bind(state);
  }
  return { state, target };
}

test('pasting many Iranian mobile formats creates one row per valid number', () => {
  const { state, target } = bulkPasteState();
  let prevented = false;
  state.handleBulkPhonePaste({
    clipboardData: { getData: () => '۰۹۱۲ ۱۲۳ ۴۵۶۷\n+98 913-222-3344\n09145556677' },
    preventDefault: () => { prevented = true; },
  }, target);

  assert.equal(prevented, true);
  assert.deepEqual(
    Array.from(state.activeCampaign.rows, row => row.phone),
    ['09121234567', '09132223344', '09145556677'],
  );
  assert.match(state.bulkPhonePasteNotice, /۳ شماره/);
});

test('paste without a valid mobile keeps normal browser paste behavior', () => {
  const { state, target } = bulkPasteState();
  let prevented = false;
  state.handleBulkPhonePaste({
    clipboardData: { getData: () => 'متن بدون شماره معتبر' },
    preventDefault: () => { prevented = true; },
  }, target);

  assert.equal(prevented, false);
  assert.equal(state.activeCampaign.rows.length, 1);
});
