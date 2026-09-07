import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../src/components/NativeStaticReports.vue', import.meta.url), 'utf8')
  .split('<script>')[1].split('</script>')[0]
  .replace(/^import .*$/gm, '').replace('export default', 'globalThis.component =');
function setup(get) {
  const context = vm.createContext({ axios: { get }, console: { warn() {} }, setTimeout, clearTimeout });
  vm.runInContext(source, context);
  const component = context.component;
  const state = component.data.call({});
  for (const [key, value] of Object.entries(component.methods)) state[key] = value.bind(state);
  return { state, view: () => component.computed.v.call(state) };
}
const summary = (amount, category = 'اجاره') => ({
  kpis: { recognized_revenue: 5000000 },
  expenses: { total: amount, items: amount ? [{ category, amount }] : [] },
});

test('month selection loads actual expenses for its date range', async () => {
  const calls = [];
  const { state, view } = setup(async (url, config) => {
    calls.push({ url, ...config.params });
    return { data: url.includes('dashboard') ? summary(1200000) : {} };
  });
  state.selectReportMonth(1);
  await new Promise(resolve => setImmediate(resolve));
  const request = calls.find(call => call.url.includes('dashboard'));
  assert.equal(request.from, '1405/02/01');
  assert.equal(request.to, '1405/02/31');
  assert.equal(view().billRows[0].n, 'اجاره');
  assert.equal(view().billSumV, (1200000).toLocaleString('fa-IR') + ' تومان');
  assert.equal(view().billNetV, (3800000).toLocaleString('fa-IR') + ' تومان');
});

test('empty month shows no demo rows and a zero total', async () => {
  const { state, view } = setup(async () => ({ data: summary(0) }));
  await state.loadReportSummary();
  assert.equal(view().billRows.length, 0);
  assert.equal(view().billSumV, '۰ تومان');
});

test('late response cannot overwrite the selected month', async () => {
  const pending = [];
  const { state, view } = setup(() => new Promise(resolve => pending.push(resolve)));
  const first = state.loadReportSummary();
  const second = state.loadReportSummary();
  assert.equal(view().billSumV, '—');
  pending[1]({ data: summary(200, 'برق') });
  await second;
  pending[0]({ data: summary(999) });
  await first;
  assert.equal(view().billRows[0].n, 'برق');
});

test('failed loading clears old values and exposes an error', async () => {
  const { state, view } = setup(async () => { throw new Error('offline'); });
  state.s.reportSummary = summary(999);
  await state.loadReportSummary();
  assert.ok(state.s.reportError);
  assert.equal(state.s.reportLoading, false);
  assert.equal(view().billSumV, '—');
  assert.equal(view().billRows.length, 0);
});
