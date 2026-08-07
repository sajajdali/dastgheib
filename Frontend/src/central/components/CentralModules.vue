<template>
  <div class="central-view">
    <section class="central-panel">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">امکانات سیستم</div>
          <div class="central-section-subtitle">امکاناتی که برای سایت‌های جدید قابل انتخاب و قیمت‌گذاری هستند</div>
        </div>
        <label class="central-check">
          <input :checked="allEnabled" type="checkbox" @change="toggleAll" />
          فعال‌سازی همه
        </label>
      </div>

      <form class="central-feature-form" @submit.prevent="saveModule">
        <label class="central-form-label">
          نام امکان
          <input v-model.trim="moduleForm.name" class="central-input" placeholder="مثلا باشگاه مشتریان" required />
        </label>
        <div class="central-module-period-editor">
          <label
            v-for="period in periodOptions"
            :key="period.key"
            class="central-period-price"
            :class="{ active: moduleForm.periods[period.key].enabled }"
          >
            <span>
              <input v-model="moduleForm.periods[period.key].enabled" type="checkbox" />
              {{ period.label }}
            </span>
            <input
              v-model.number="moduleForm.periods[period.key].price"
              class="central-input"
              type="number"
              min="0"
              step="1000"
              :disabled="!moduleForm.periods[period.key].enabled"
              placeholder="قیمت به تومان"
            />
          </label>
        </div>
        <button class="central-button" type="submit">{{ editingModuleId ? "ذخیره تغییرات" : "افزودن امکان" }}</button>
        <button v-if="editingModuleId" class="central-button secondary" type="button" @click="resetForm">انصراف</button>
      </form>

      <div class="central-module-grid">
        <article v-for="module in visibleModules" :key="module.id" class="central-module-card">
          <div>
            <div class="central-row-title">{{ module.name }}</div>
            <div class="central-period-list">
              <span v-for="period in normalizedPeriods(module)" :key="`${module.id}-${period.key}`">
                {{ period.label }}: {{ formatMoney(period.price) }}
              </span>
            </div>
          </div>
          <div class="central-module-actions">
            <button class="central-button secondary compact" type="button" @click="editModule(module)">ویرایش</button>
            <button class="central-button danger compact" type="button" @click="removeModule(module)">حذف</button>
            <label class="central-switch" title="فعال/غیرفعال">
              <input v-model="module.enabled" type="checkbox" />
              <span></span>
            </label>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { CENTRAL_MODULES } from "../data/modules";

const modules = defineModel({ type: Array, required: true });
const editingModuleId = ref("");
const periodOptions = [
  { key: "one_time", label: "یک‌باره" },
  { key: "monthly", label: "ماهانه" },
  { key: "quarterly", label: "۳ ماهه" },
  { key: "semiannual", label: "۶ ماهه" },
  { key: "annual", label: "۱ ساله" },
];
const moduleForm = reactive(defaultModuleForm());

const visibleModules = computed(() => {
  if (Array.isArray(modules.value) && modules.value.length) return modules.value;
  return CENTRAL_MODULES.map((module) => ({ ...module, enabled: module.enabled !== false }));
});
const allEnabled = computed(() => visibleModules.value.length > 0 && visibleModules.value.every((module) => module.enabled));

function defaultModuleForm() {
  return {
    name: "",
    periods: defaultPeriods(),
  };
}

function defaultPeriods() {
  return Object.fromEntries(periodOptions.map((period) => [period.key, { enabled: period.key === "one_time", price: 0 }]));
}

function toggleAll(event) {
  modules.value = visibleModules.value.map((module) => ({ ...module, enabled: event.target.checked }));
}

function saveModule() {
  const periods = formPeriods();
  if (!periods.length) {
    window.alert("حداقل یک دوره فروش را برای این امکان فعال کنید.");
    return;
  }

  const payload = {
    id: editingModuleId.value || moduleId(moduleForm.name),
    name: moduleForm.name.trim(),
    price: Number(periods.find((period) => period.key === "one_time")?.price ?? periods[0]?.price ?? 0),
    periods,
    enabled: true,
  };

  if (editingModuleId.value) {
    modules.value = modules.value.map((module) => module.id === editingModuleId.value
      ? { ...module, name: payload.name, price: payload.price, periods: payload.periods }
      : module);
  } else {
    modules.value = [...modules.value, uniqueModule(payload)];
  }

  resetForm();
}

function editModule(module) {
  editingModuleId.value = module.id;
  moduleForm.name = module.name;
  moduleForm.periods = periodsToForm(module);
}

function removeModule(module) {
  if (!window.confirm(`امکان ${module.name} حذف شود؟`)) return;
  modules.value = modules.value.filter((item) => item.id !== module.id);
  if (editingModuleId.value === module.id) resetForm();
}

function resetForm() {
  editingModuleId.value = "";
  Object.assign(moduleForm, defaultModuleForm());
}

function formPeriods() {
  return periodOptions
    .filter((period) => moduleForm.periods[period.key]?.enabled)
    .map((period) => ({
      key: period.key,
      label: period.label,
      price: Number(moduleForm.periods[period.key]?.price || 0),
    }));
}

function periodsToForm(module) {
  const next = defaultPeriods();
  normalizedPeriods(module).forEach((period) => {
    if (!next[period.key]) return;
    next[period.key] = { enabled: true, price: Number(period.price || 0) };
  });
  return next;
}

function normalizedPeriods(module) {
  if (Array.isArray(module.periods) && module.periods.length) {
    return module.periods.map((period) => ({
      key: period.key,
      label: period.label || periodOptions.find((item) => item.key === period.key)?.label || period.key,
      price: Number(period.price || 0),
    }));
  }

  return [{ key: "one_time", label: "یک‌باره", price: Number(module.price || 0) }];
}

function moduleId(name) {
  const base = String(name || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^a-z0-9آ-ی_-]/g, "");

  return base || `feature-${Date.now()}`;
}

function uniqueModule(module) {
  if (!modules.value.some((item) => item.id === module.id)) return module;
  return { ...module, id: `${module.id}-${Date.now()}` };
}

function formatMoney(value) {
  const amount = Number(value || 0);
  return amount === 0 ? "رایگان" : `${amount.toLocaleString("fa-IR")} تومان`;
}
</script>

<style scoped>
.central-module-period-editor{grid-column:1/-1;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.central-period-price{display:grid;gap:7px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#64748b;font-size:12px;font-weight:900}.central-period-price.active{border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8}.central-period-price span{display:flex;align-items:center;gap:7px}.central-period-price input[type="checkbox"]{width:16px;height:16px;accent-color:#2563eb}.central-period-list{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}.central-period-list span{padding:5px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:1000}@media(max-width:900px){.central-module-period-editor{grid-template-columns:1fr 1fr}}@media(max-width:560px){.central-module-period-editor{grid-template-columns:1fr}}
</style>
