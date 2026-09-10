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

test("financial panel separates session debt and creates one deposit allocation per service", () => {
  assert.match(source, /بدهی همین جلسه/);
  assert.match(source, /بدهی کل بیمار با این جلسه/);
  assert.match(source, /ثبت بیعانه برای هر خدمت/);
  assert.match(source, /\(service\.addons \|\| \[\]\).*?return \[base, \.\.\.addons\]/s);
  assert.match(source, /allocations: depositLines\.map/);
  assert.match(source, /parent_service: line\.parentService/);
});

test("the amount field and payment icon reflect debt and settled payment states", () => {
  assert.match(source, /'debt-amount-input': appointmentDisplayedDebtAmount\(row\) > 0/);
  assert.match(source, /'paid-amount-input': appointmentPaymentIsSettled\(row\)/);
  assert.match(source, /danger: appointmentDisplayedDebtAmount\(row\) > 0/);
  assert.match(source, /paid: appointmentPaymentIsSettled\(row\)/);
  assert.match(source, /appointmentPaymentIsSettled\(row\)[\s\S]*?recordedPaymentAmount\(row\) > 0/);
});

test("empty scheduler select fields do not render dash placeholders", () => {
  assert.doesNotMatch(source, /<option value="">-<\/option>/);
  for (const field of ['status', 'done', 'appointment_sms', 'info_sms']) {
    assert.match(source, new RegExp(`${field.replace('_', '\\_')}\\)?,?\\s*?`));
  }
  assert.match(source, /return \['-', '–', '—'\]\.includes\(normalized\) \? '' : normalized/);
});

test("pending SMS rows without a valid mobile number are blocked in Persian", () => {
  assert.match(source, /invalidPhones = pending\.filter/);
  assert.match(source, /title: 'شماره موبایل وارد نشده است'/);
  assert.match(source, /شماره موبایل این ردیف وارد نشده یا معتبر نیست/);
});
