import { readFileSync } from "node:fs";
import test from "node:test";
import assert from "node:assert/strict";

const source = readFileSync(new URL("../src/components/Time.vue", import.meta.url), "utf8");

test("appointment debt reason is stored with payment details", () => {
  assert.match(source, /debtDescription:\s*String\(details\?\.debtDescription/);
  assert.match(source, /debtDescription:\s*newDebt > 0 \? this\.financialDebtDescriptionDraft : ""/);
});

test("the amount column displays remaining debt and explicit debt has priority", () => {
  assert.match(source, /appointmentDisplayedDebtAmount\(row\)[\s\S]*?if \(explicitDebt > 0\) return explicitDebt/);
  assert.match(source, /serviceAmount - this\.recordedPaymentAmount\(row\)/);
  assert.match(source, /appointmentAmountColumnValue\(row\)[\s\S]*?formatDisplayMoney\(this\.appointmentDisplayedDebtAmount\(row\)\)/);
  assert.match(source, /class="appointment-debt-reason"/);
});

test("new cash, card and check payments reduce the remaining appointment debt", () => {
  assert.match(source, /financialDraftPaymentTotal\(\)[\s\S]*?financialCashDraft[\s\S]*?financialCardDraft[\s\S]*?financialCheckAmountDraft/);
  assert.match(source, /const newDebt = this\.financialRemainingDebtPreview\(\)/);
  assert.match(source, /currentPayments - Number\(this\.financialOriginalRecordedPayment/);
});
