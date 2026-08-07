<template>
  <div class="central-view central-stack">
    <section v-if="mode === 'packages'" class="central-panel">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">بسته‌های زمانی و تعرفه کاربران</div>
          <div class="central-section-subtitle">مدت و قیمت بسته‌هایی که برای سایت‌ها قابل انتخاب هستند</div>
        </div>
        <button class="central-button" type="button" @click="startNewPlan">+ بسته جدید</button>
      </div>

      <div class="central-layout-grid">
        <form v-if="isEditing" class="central-card central-form-panel" @submit.prevent="savePlan">
          <div class="central-card-title">{{ editingPlanId ? "ویرایش بسته" : "بسته جدید" }}</div>
          <label class="central-form-label">
            عنوان
            <input v-model.trim="planForm.name" class="central-input" required />
          </label>
          <label class="central-form-label">
            مدت به روز
            <input v-model.number="planForm.duration_days" class="central-input" type="number" min="1" required />
          </label>
          <label class="central-form-label">
            قیمت پایه برای {{ formatNumber(userPricing.included_users) }} کاربر به تومان
            <input v-model.number="planForm.base_price" class="central-input" type="number" min="0" step="1000" placeholder="12000000" required />
          </label>
          <label class="central-form-label">
            ترتیب نمایش
            <input v-model.number="planForm.sort_order" class="central-input" type="number" min="0" />
          </label>
          <div class="central-check-row">
            <label class="central-check"><input v-model="planForm.is_active" type="checkbox" /> فعال</label>
            <label class="central-check"><input v-model="planForm.is_trial" type="checkbox" /> تستی</label>
          </div>
          <p v-if="error" class="central-error">{{ error }}</p>
          <div class="central-actions">
            <button class="central-button" type="submit" :disabled="saving">ذخیره بسته</button>
            <button class="central-button secondary" type="button" @click="resetPlanForm">انصراف</button>
          </div>
        </form>

        <form class="central-card central-form-panel" @submit.prevent="saveUserPricing">
          <div class="central-card-title">تعرفه تعداد کاربر</div>
          <label class="central-form-label">
            کاربر پیش‌فرض
            <input v-model.number="pricingForm.included_users" class="central-input" type="number" min="1" required />
          </label>
          <label class="central-form-label">
            مبلغ هر کاربر اضافه به تومان
            <input v-model.number="pricingForm.extra_user_price" class="central-input" type="number" min="0" step="1000" placeholder="12000000" required />
          </label>
          <button class="central-button" type="submit" :disabled="saving">ذخیره تعرفه کاربر</button>
          <div class="central-section-subtitle">قیمت نهایی = مبلغ بسته + کاربر اضافه × مبلغ ثابت</div>
        </form>
      </div>
    </section>

    <section v-if="mode === 'packages'" class="central-grid">
      <article v-for="plan in plans" :key="plan.id" class="central-card" :style="{ opacity: plan.is_active ? 1 : 0.58 }">
        <div class="central-actions">
          <span class="central-badge">{{ formatNumber(plan.duration_days) }} روزه</span>
          <span v-if="plan.is_trial" class="central-badge" style="--badge-color:#d97706;--badge-bg:#fffbeb;">تستی</span>
        </div>
        <div class="central-card-title">{{ plan.name }}</div>
        <div class="central-card-price">{{ formatMoney(plan.base_price) }}</div>
        <div class="central-section-subtitle">برای {{ formatNumber(userPricing.included_users) }} کاربر پایه</div>
        <strong>برای {{ formatNumber(quoteUsers) }} کاربر: {{ formatMoney(quotePlan(plan)) }}</strong>
        <div class="central-actions">
          <button class="central-button secondary" type="button" @click="editPlan(plan)">ویرایش</button>
          <button class="central-button danger" type="button" @click="$emit('delete-plan', plan)">حذف</button>
        </div>
      </article>
    </section>

    <section v-if="mode === 'discounts'" class="central-panel central-stack">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">کدهای تخفیف</div>
          <div class="central-section-subtitle">تعریف کد تخفیف با بازه زمانی، محدودیت استفاده و وضعیت فعال/غیرفعال</div>
        </div>
        <button class="central-button" type="button" @click="startNewDiscount">+ کد جدید</button>
      </div>

      <div class="central-card central-table discount-table">
        <div class="discount-row discount-head">
          <span>کد</span>
          <span>نوع و مقدار</span>
          <span>بازه اعتبار</span>
          <span>استفاده</span>
          <span>وضعیت</span>
          <span>عملیات</span>
        </div>
        <div v-for="discount in discountCodes" :key="discount.id" class="discount-row">
          <div>
            <strong class="central-ltr">{{ discount.code }}</strong>
            <small>{{ discount.title || "بدون عنوان" }}</small>
          </div>
          <span>{{ discountLabel(discount) }}</span>
          <span>{{ dateRange(discount) }}</span>
          <span>{{ usageLabel(discount) }}</span>
          <span :class="['central-badge', discount.is_active ? '' : 'danger']">{{ discount.is_active ? "فعال" : "غیرفعال" }}</span>
          <div class="central-actions">
            <button class="central-button secondary compact" type="button" @click="editDiscount(discount)">ویرایش</button>
            <button class="central-button danger compact" type="button" @click="$emit('delete-discount', discount)">حذف</button>
          </div>
        </div>
        <div v-if="!discountCodes.length" class="central-empty">کد تخفیفی ثبت نشده است.</div>
      </div>

      <section v-for="discount in usedDiscounts" :key="`used-${discount.id}`" class="central-card">
        <div class="central-card-title">استفاده‌کنندگان {{ discount.code }}</div>
        <div class="discount-usage-list">
          <div v-for="usage in discount.redemptions" :key="usage.id" class="discount-usage-row">
            <strong>{{ usage.tenant_name || usage.tenant_id || "سایت نامشخص" }}</strong>
            <span>{{ usage.buyer_name || usage.buyer_email || "کاربر نامشخص" }}</span>
            <span>{{ formatMoney(usage.discount_amount) }}</span>
            <span>{{ usage.used_at || "-" }}</span>
          </div>
        </div>
      </section>
    </section>

    <div v-if="discountEditing" class="central-modal-backdrop" @click.self="resetDiscountForm">
      <form class="central-modal discount-editor" @submit.prevent="saveDiscount">
        <header class="central-panel-head">
          <div>
            <div class="central-section-title">{{ editingDiscountId ? "ویرایش کد تخفیف" : "کد تخفیف جدید" }}</div>
            <div class="central-section-subtitle">تاریخ‌ها را شمسی وارد کنید؛ ذخیره در دیتابیس به میلادی انجام می‌شود.</div>
          </div>
          <button class="central-icon-button" type="button" @click="resetDiscountForm">×</button>
        </header>

        <div class="discount-editor-grid">
          <label class="central-form-label">
            کد تخفیف
            <input v-model.trim="discountForm.code" class="central-input central-ltr" placeholder="OFF10" required />
          </label>
          <label class="central-form-label">
            عنوان
            <input v-model.trim="discountForm.title" class="central-input" placeholder="تخفیف افتتاحیه" />
          </label>
          <label class="central-form-label">
            نوع
            <select v-model="discountForm.type" class="central-select">
              <option value="percent">درصدی</option>
              <option value="fixed">مبلغ ثابت</option>
            </select>
          </label>
          <label class="central-form-label">
            مقدار
            <input v-model.number="discountForm.value" class="central-input" type="number" min="1" required />
          </label>
          <label class="central-form-label">
            شروع شمسی
            <date-picker
              v-model="discountForm.starts_at_date"
              format="jYYYY/jMM/jDD"
              display-format="jYYYY/jMM/jDD"
              input-class="central-input central-ltr central-date-input"
              placeholder="انتخاب تاریخ"
              append-to="body"
              popover
              :auto-submit="true"
              :editable="false"
              :clearable="false"
            />
          </label>
          <label class="central-form-label">
            پایان شمسی
            <date-picker
              v-model="discountForm.ends_at_date"
              format="jYYYY/jMM/jDD"
              display-format="jYYYY/jMM/jDD"
              input-class="central-input central-ltr central-date-input"
              placeholder="انتخاب تاریخ"
              append-to="body"
              popover
              :auto-submit="true"
              :editable="false"
              :clearable="false"
            />
          </label>
          <label class="central-form-label">
            تعداد استفاده مجاز
            <input v-model.number="discountForm.usage_limit" class="central-input" type="number" min="1" placeholder="نامحدود" />
          </label>
          <label class="central-check discount-active-check"><input v-model="discountForm.is_active" type="checkbox" /> فعال باشد</label>
        </div>

        <p v-if="discountFormError" class="central-error">{{ discountFormError }}</p>

        <div class="central-actions">
          <button class="central-button" type="submit" :disabled="saving">ذخیره کد تخفیف</button>
          <button class="central-button secondary" type="button" @click="resetDiscountForm">انصراف</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import DatePicker from "vue3-persian-datetime-picker";

const props = defineProps({
  mode: { type: String, default: "packages" },
  plans: { type: Array, default: () => [] },
  userPricing: { type: Object, default: () => ({ included_users: 1, extra_user_price: 0 }) },
  discountCodes: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: "" },
});

const emit = defineEmits(["save-plan", "delete-plan", "save-user-pricing", "save-discount", "delete-discount"]);

const isEditing = ref(false);
const editingPlanId = ref(null);
const quoteUsers = ref(1);
const planForm = reactive(defaultPlanForm());
const pricingForm = reactive({
  included_users: 1,
  extra_user_price: 0,
});
const discountEditing = ref(false);
const editingDiscountId = ref(null);
const discountForm = reactive(defaultDiscountForm());
const discountFormError = ref("");
const usedDiscounts = computed(() => props.discountCodes.filter((discount) => discount.redemptions?.length));

watch(
  () => props.userPricing,
  (pricing) => {
    pricingForm.included_users = pricing?.included_users || 1;
    pricingForm.extra_user_price = pricing?.extra_user_price || 0;
    quoteUsers.value = Math.max(quoteUsers.value, pricingForm.included_users);
  },
  { immediate: true },
);

function defaultPlanForm() {
  return {
    name: "",
    duration_days: 30,
    base_price: 0,
    is_trial: false,
    is_active: true,
    sort_order: 0,
  };
}

function startNewPlan() {
  Object.assign(planForm, defaultPlanForm());
  editingPlanId.value = null;
  isEditing.value = true;
}

function editPlan(plan) {
  editingPlanId.value = plan.id;
  isEditing.value = true;
  Object.assign(planForm, {
    name: plan.name,
    duration_days: plan.duration_days,
    base_price: plan.base_price,
    is_trial: Boolean(plan.is_trial),
    is_active: Boolean(plan.is_active),
    sort_order: plan.sort_order || 0,
  });
}

function resetPlanForm() {
  isEditing.value = false;
  editingPlanId.value = null;
  Object.assign(planForm, defaultPlanForm());
}

function savePlan() {
  emit("save-plan", { id: editingPlanId.value, payload: { ...planForm }, done: resetPlanForm });
}

function saveUserPricing() {
  emit("save-user-pricing", { ...pricingForm });
}

function defaultDiscountForm() {
  return {
    code: "",
    title: "",
    type: "percent",
    value: 10,
    starts_at_date: "",
    ends_at_date: "",
    usage_limit: null,
    is_active: true,
  };
}

function startNewDiscount() {
  Object.assign(discountForm, defaultDiscountForm());
  discountFormError.value = "";
  editingDiscountId.value = null;
  discountEditing.value = true;
}

function editDiscount(discount) {
  editingDiscountId.value = discount.id;
  discountEditing.value = true;
  Object.assign(discountForm, {
    code: discount.code,
    title: discount.title || "",
    type: discount.type,
    value: discount.value,
    ...splitJalaliDateTime(gregorianToJalaliInput(discount.starts_at), "starts_at", "00:00"),
    ...splitJalaliDateTime(gregorianToJalaliInput(discount.ends_at), "ends_at", "23:59"),
    usage_limit: discount.usage_limit,
    is_active: Boolean(discount.is_active),
  });
  discountFormError.value = "";
}

function resetDiscountForm() {
  discountEditing.value = false;
  editingDiscountId.value = null;
  discountFormError.value = "";
  Object.assign(discountForm, defaultDiscountForm());
}

function saveDiscount() {
  const startsAt = jalaliInputToGregorian(joinJalaliDateTime(discountForm.starts_at_date, "00:00"));
  const endsAt = jalaliInputToGregorian(joinJalaliDateTime(discountForm.ends_at_date, "23:59"));

  if (startsAt === false || endsAt === false) {
    discountFormError.value = "فرمت تاریخ شمسی درست نیست. نمونه: 1405/05/07 09:00";
    return;
  }

  emit("save-discount", {
    id: editingDiscountId.value,
    payload: {
      code: discountForm.code.trim().toUpperCase(),
      title: discountForm.title,
      type: discountForm.type,
      value: discountForm.value,
      usage_limit: discountForm.usage_limit || null,
      starts_at: startsAt,
      ends_at: endsAt,
      is_active: discountForm.is_active,
    },
    done: resetDiscountForm,
  });
}

function joinJalaliDateTime(date, time) {
  const normalizedDate = String(date || "").trim();
  if (!normalizedDate) return "";
  const normalizedTime = String(time || "").trim() || "00:00";
  return `${normalizedDate} ${normalizedTime}`;
}

function splitJalaliDateTime(value, prefix, fallbackTime) {
  const [date = "", time = fallbackTime] = String(value || "").trim().split(/\s+/);
  return {
    [`${prefix}_date`]: date,
  };
}

function quotePlan(plan) {
  const extraUsers = Math.max(0, Number(quoteUsers.value || 1) - Number(props.userPricing.included_users || 1));
  return Number(plan.base_price || 0) + extraUsers * Number(props.userPricing.extra_user_price || 0);
}

function formatMoney(value) {
  const amount = Number(value || 0);
  return amount === 0 ? "رایگان" : `${amount.toLocaleString("fa-IR")} تومان`;
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString("fa-IR");
}

function discountLabel(discount) {
  return discount.type === "percent"
    ? `${formatNumber(discount.value)} درصد`
    : formatMoney(discount.value);
}

function dateRange(discount) {
  const start = gregorianToJalaliInput(discount.starts_at) || "بدون شروع";
  const end = gregorianToJalaliInput(discount.ends_at) || "بدون پایان";
  return `${start} تا ${end}`;
}

function usageLabel(discount) {
  const used = Number(discount.redemptions_count || 0);
  return discount.usage_limit ? `${formatNumber(used)} از ${formatNumber(discount.usage_limit)}` : `${formatNumber(used)} / نامحدود`;
}

function normalizeDigits(value) {
  const persian = "۰۱۲۳۴۵۶۷۸۹";
  const arabic = "٠١٢٣٤٥٦٧٨٩";
  return String(value || "")
    .replace(/[۰-۹]/g, (digit) => persian.indexOf(digit))
    .replace(/[٠-٩]/g, (digit) => arabic.indexOf(digit));
}

function jalaliInputToGregorian(value) {
  const normalized = normalizeDigits(value).trim();
  if (!normalized) return null;

  const match = normalized.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/);
  if (!match) return false;

  const [, jy, jm, jd, hour = "00", minute = "00"] = match;
  const { gy, gm, gd } = jalaliToGregorian(Number(jy), Number(jm), Number(jd));
  return `${gy}-${String(gm).padStart(2, "0")}-${String(gd).padStart(2, "0")} ${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`;
}

function gregorianToJalaliInput(value) {
  if (!value) return "";
  const normalized = String(value).replace("T", " ");
  const match = normalized.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?/);
  if (!match) return "";

  const [, gy, gm, gd, hour = "00", minute = "00"] = match;
  const { jy, jm, jd } = gregorianToJalali(Number(gy), Number(gm), Number(gd));
  return `${jy}/${String(jm).padStart(2, "0")}/${String(jd).padStart(2, "0")} ${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
}

function jalaliToGregorian(jy, jm, jd) {
  jy += 1595;
  let days = -355668 + (365 * jy) + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4) + jd;
  days += jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186;
  let gy = 400 * Math.floor(days / 146097);
  days %= 146097;
  if (days > 36524) {
    gy += 100 * Math.floor(--days / 36524);
    days %= 36524;
    if (days >= 365) days += 1;
  }
  gy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days > 365) {
    gy += Math.floor((days - 1) / 365);
    days = (days - 1) % 365;
  }
  const gd = days + 1;
  const salA = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  let gm = 0;
  let day = gd;
  for (gm = 1; gm <= 12 && day > salA[gm]; gm += 1) day -= salA[gm];
  return { gy, gm, gd: day };
}

function gregorianToJalali(gy, gm, gd) {
  const gDM = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  let jy = gy <= 1600 ? 0 : 979;
  gy -= gy <= 1600 ? 621 : 1600;
  const gy2 = gm > 2 ? gy + 1 : gy;
  let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + gDM[gm - 1];
  jy += 33 * Math.floor(days / 12053);
  days %= 12053;
  jy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days > 365) {
    jy += Math.floor((days - 1) / 365);
    days = (days - 1) % 365;
  }
  const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
  const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);
  return { jy, jm, jd };
}
</script>
