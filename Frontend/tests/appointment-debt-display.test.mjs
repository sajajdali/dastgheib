import { readFileSync } from "node:fs";
import test from "node:test";
import assert from "node:assert/strict";

const source = readFileSync(new URL("../src/components/Time.vue", import.meta.url), "utf8");

test("appointment debt reason is stored with payment details", () => {
  assert.match(source, /debtDescription:\s*String\(details\?\.debtDescription/);
  assert.match(source, /debtDescription:\s*newDebt > 0 \? debtLines\.map/);
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
  assert.match(source, /deposit_allocations: depositLines\.map/);
  assert.match(source, /parent_service: line\.parent_service/);
});

test("financial panel records debt amount and description separately for every selected service", () => {
  assert.match(source, /ثبت بدهی برای هر خدمت/);
  assert.match(source, /v-for="line in financialDebtLines"/);
  assert.match(source, /v-model="line\.amount"/);
  assert.match(source, /v-model\.trim="line\.reason"/);
  assert.match(source, /const debtAllocations = debtLines\.map/);
});

test("financial checkout uses configured payment methods and accounts", () => {
  assert.match(source, /v-for="method in paymentOptions\.methods"/);
  assert.match(source, /v-for="account in paymentOptions\.accounts"/);
  assert.match(source, /await this\.fetchPaymentOptions\(\)/);
  assert.doesNotMatch(source, /v-for="method in \['card','cash','check'\]"/);
});

test("selecting a payment method prefills the current session payable amount", () => {
  assert.match(source, /toggleConfiguredPaymentMethod\(method\)[\s\S]*?const remainingAmount = this\.financialRemainingDebtPreview\(\)/);
  assert.match(source, /amount: remainingAmount \? this\.formatDisplayMoney\(remainingAmount\) : ''/);
  assert.match(source, /this\.financialPaymentLines = \[\]/);
});

test("opening checkout prefills payment and service debt reduces that payment", () => {
  assert.match(source, /const initialPaymentAmount = Math\.max\(0, this\.moneyToNumber\(row\?\.amount\) - this\.financialOriginalRecordedPayment\)/);
  assert.match(source, /this\.financialDebtLines = this\.debtLinesForInvoice\(this\.financialInvoiceLines, 0/);
  assert.match(source, /formatFinancialDebtLine\(line\)[\s\S]*?this\.syncFinancialPaymentsWithDebt\(\)/);
  assert.match(source, /target = Math\.max\(0, payable - this\.financialOriginalRecordedPayment - this\.financialWalletApplied - this\.financialDebtLinesTotal\(\)\)/);
});

test("previous debts show service audit details and settlement action", () => {
  assert.match(source, /بدهی‌های گذشته/);
  assert.match(source, /detail\.created_by_name/);
  assert.match(source, /item\.settlement\.created_at/);
  assert.match(source, /پرداخت بدهی/);
  assert.match(source, /applySettledPreviousDebt\(data\.appointment, data\.outstanding_debt\)/);
  assert.match(source, /row\.debt = ''[\s\S]*?row\.originalDebt = 0/);
});

test("past deposits can pay this session and payment allocations retain service detail", () => {
  assert.match(source, /بیعانه این جلسه/);
  assert.match(source, /بیعانه‌های گذشته \/ استفاده در این جلسه/);
  assert.match(source, /financialWalletApplied = Number\(result\.value \|\| 0\)/);
  assert.match(source, /wallet_payment: this\.financialWalletApplied > 0/);
  assert.match(source, /allocateFinancialAmount\(amount, offset = 0\)/);
  assert.match(source, /transaction\.allocations \|\| \[\]/);
});

test("financial labels are clear and deleted payments keep an auditable snapshot", () => {
  assert.match(source, /<span>بدهی قبلی<\/span>/);
  assert.match(source, /<span>بیعانه قبلی<\/span>/);
  assert.match(source, /transaction\.voided_at \? 'پرداخت ابطال‌شده'/);
  assert.match(source, /transaction\.voided_by_name/);
});

test("reopening checkout keeps the persisted appointment debt visible", () => {
  assert.match(source, /this\.financialDebtOpen = false/);
  assert.match(source, /financialRemainingDebtPreview\(\)[\s\S]*?payable - this\.financialDraftPaymentTotal\(\)/);
  assert.doesNotMatch(source, /this\.financialOpeningDebt > 0 \|\| this\.financialPreviousDebts\.length/);
});

test("debt and deposit detail actions expose settlement flows", () => {
  assert.match(source, /پرداخت بدهی قبلی/);
  assert.match(source, /جزئیات و مصرف بیعانه/);
  assert.match(source, /openDebtPaymentDetails\(\)/);
  assert.match(source, /openDepositDetails\(\)/);
  assert.match(source, /settle-debt-with-wallet/);
  assert.match(source, /ثبت‌کننده:/);
});

test("the amount field and payment icon reflect debt and settled payment states", () => {
  assert.match(source, /'debt-amount-input': appointmentDisplayedDebtAmount\(row\) > 0/);
  assert.match(source, /'paid-amount-input': appointmentPaymentIsSettled\(row\)/);
  assert.match(source, /danger: appointmentDisplayedDebtAmount\(row\) > 0/);
  assert.match(source, /paid: appointmentPaymentIsSettled\(row\)/);
  assert.match(source, /appointmentPaymentIsSettled\(row\)[\s\S]*?recordedPaymentAmount\(row\) > 0/);
});

test("all appointments for a debtor patient stay visibly red", () => {
  assert.match(source, /isDebtor\(row\)[\s\S]*?patientDebtAmount\(row\) > 0 \|\| this\.appointmentBalanceAmount\(row\) > 0/);
  assert.match(source, /danger: isDebtor\(row\)/);
  assert.match(source, /debtorOk = !this\.amountFilterDebtorsOnly \|\| this\.isDebtor\(row\)/);
  assert.match(source, /tr\.debtor-row[\s\S]*?box-shadow: inset -5px 0 0 #dc2626/);
  assert.match(source, /background-color: #fff1f2/);
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

test("clearing an appointment name also clears its stale patient avatar", () => {
  assert.match(source, /@input="onPatientNameInput\(row\)"/);
  assert.match(source, /onPatientNameInput\(row\)[\s\S]*?if \(!String\(row\?\.lastname \|\| ''\)\.trim\(\)\)/);
  assert.match(source, /row\.profileThumbnailUrl = ''/);
  assert.match(source, /row\.profilePhotoUrl = ''/);
  assert.match(source, /row\.hasPatientFile = false/);
  assert.match(source, /row\.patientId = null/);
});
