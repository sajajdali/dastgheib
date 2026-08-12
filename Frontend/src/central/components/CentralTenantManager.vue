<template>
  <div class="central-view">
    <section v-if="mode === 'newsite'" class="central-layout-grid">
      <form class="central-panel central-form-panel" @submit.prevent="submitTenant">
        <div class="central-section-title">اطلاعات سایت جدید</div>
        <label class="central-form-label">
          نام شعبه
          <input v-model.trim="form.name" class="central-input" placeholder="مثلا کلینیک سعادت" required />
        </label>
        <label class="central-form-label">
          شناسه سیستم
          <input v-model.trim="form.id" class="central-input central-ltr" placeholder="clinic7" pattern="[a-z0-9][a-z0-9_-]*" required />
        </label>
        <label class="central-form-label">
          دامنه
          <input v-model.trim="form.domain" class="central-input central-ltr" placeholder="clinic7.localhost" required />
        </label>
        <label class="central-form-label">
          بسته زمانی
          <select v-model="form.planId" class="central-select">
            <option value="">بدون بسته</option>
            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
              {{ plan.name }} - {{ formatMoney(plan.base_price) }}
            </option>
          </select>
        </label>
        <label class="central-form-label">
          تعداد کاربران سیستم
          <input v-model.number="form.userCount" class="central-input" type="number" min="1" required />
        </label>
        <label class="central-check">
          <input v-model="form.seed" type="checkbox" />
          ساخت کاربر مدیر و دسترسی‌های اولیه
        </label>
        <p v-if="error" class="central-error">{{ error }}</p>
        <p v-if="message" class="central-message">{{ message }}</p>
        <div class="central-cost-summary">
          <div class="central-small"><span>هزینه بسته زمانی</span><span>{{ formatMoney(selectedPlanPrice) }}</span></div>
          <div class="central-small"><span>هزینه کاربران اضافه</span><span>{{ formatMoney(extraUsersTotal) }}</span></div>
          <div class="central-small"><span>تعداد امکانات انتخابی</span><span>{{ formatNumber(selectedModules.length) }}</span></div>
          <div class="central-small"><span>هزینه امکانات یک‌باره</span><span>{{ formatMoney(estimatedModuleTotal) }}</span></div>
          <div class="central-card-price"><span>جمع تخمینی</span><span>{{ formatMoney(estimatedTotal) }}</span></div>
        </div>
        <button class="central-button" type="submit" :disabled="saving">
          {{ saving ? "در حال ایجاد..." : "ثبت سایت جدید" }}
        </button>
      </form>

      <section class="central-panel">
        <div class="central-panel-head">
          <div>
            <div class="central-section-title">امکانات سایت</div>
            <div class="central-section-subtitle">امکانات خرید اولیه سایت را انتخاب کنید</div>
          </div>
          <label class="central-check">
            <input :checked="allSelectableModulesSelected" type="checkbox" @change="toggleAllSelectableModules" />
            انتخاب همه
          </label>
        </div>
        <div class="central-module-select-grid">
          <label
            v-for="module in enabledModules"
            :key="module.id"
            class="central-module-option"
            :class="{ active: selectedModules.includes(module.id) }"
          >
            <input v-model="selectedModules" :value="module.id" type="checkbox" />
            <span style="flex:1;">{{ module.name }}</span>
            <span class="central-small">{{ formatMoney(module.price) }}</span>
          </label>
        </div>
      </section>
    </section>

    <section v-else class="central-panel central-stack">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">مدیریت سایت‌ها</div>
          <div class="central-section-subtitle">فهرست سایت‌های فعال و غیرفعال ثبت‌شده</div>
        </div>
        <input v-model.trim="siteSearch" class="central-input" style="max-width:260px;" placeholder="جستجوی نام یا دامنه..." />
      </div>

      <div class="central-card central-table">
        <div class="central-table-head">
          <span>نام شعبه</span>
          <span>دامنه</span>
          <span>دیتابیس</span>
          <span>وضعیت</span>
          <span>دامنه جدید</span>
          <span>عملیات</span>
        </div>
        <div v-for="tenant in filteredTenants" :key="tenant.id" class="central-table-row">
          <div>
            <strong>{{ tenant.name || tenant.id }}</strong>
            <div class="central-small">{{ tenant.id }}</div>
          </div>
          <div class="central-domain-list">
            <a
              v-for="domain in tenant.domains"
              :key="domain.id"
              class="central-domain-pill"
              :href="domain.url"
              target="_blank"
              rel="noreferrer"
            >
              {{ domain.domain }}
              <button type="button" title="حذف دامنه" @click.prevent="$emit('remove-domain', { tenant, domain })">×</button>
            </a>
          </div>
          <span class="central-small central-ltr">{{ tenant.database }}</span>
          <select class="central-select" :value="tenant.status" @change="$emit('update-tenant', { tenant, payload: { status: $event.target.value } })">
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
          </select>
          <form class="central-inline-form" @submit.prevent="addDomain(tenant)">
            <input v-model.trim="domainDrafts[tenant.id]" class="central-input central-ltr" placeholder="new-domain.localhost" />
            <button class="central-button secondary" type="submit">افزودن</button>
          </form>
          <div class="central-actions">
            <button class="central-button secondary compact" type="button" :disabled="saving" @click="$emit('seed-demo-data', tenant)">دیتای تستی</button>
            <button class="central-button secondary compact" type="button" @click="startEditTenant(tenant)">ویرایش</button>
            <button class="central-button danger compact" type="button" @click="$emit('delete-tenant', tenant)">حذف</button>
          </div>
        </div>
        <div v-if="!filteredTenants.length" class="central-empty">سایتی یافت نشد.</div>
      </div>
    </section>

    <div v-if="editingTenant" class="central-modal-backdrop" @click.self="resetTenantEditor">
      <form class="central-modal" @submit.prevent="saveTenantSettings">
        <header class="central-panel-head">
          <div>
            <div class="central-section-title">ویرایش امکانات سایت</div>
            <div class="central-section-subtitle">{{ editingTenant.name || editingTenant.id }}</div>
          </div>
          <button class="central-icon-button" type="button" @click="resetTenantEditor">×</button>
        </header>

        <label class="central-form-label">
          تعداد کاربران سیستم
          <input v-model.number="tenantEditor.userCount" class="central-input" type="number" min="1" required />
        </label>

        <label class="central-form-label">
          بسته زمانی
          <select v-model="tenantEditor.planId" class="central-select">
            <option value="">بدون بسته</option>
            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
              {{ plan.name }} - {{ formatMoney(plan.base_price) }}
            </option>
          </select>
        </label>

        <div class="central-panel-head compact-head">
          <div class="central-section-title">امکانات فعال</div>
          <label class="central-check">
            <input :checked="allEditedModulesSelected" type="checkbox" @change="toggleEditedModules" />
            انتخاب همه
          </label>
        </div>

        <div class="central-module-select-grid">
          <label
            v-for="module in enabledModules"
            :key="module.id"
            class="central-module-option"
            :class="{ active: tenantEditor.moduleIds.includes(module.id) }"
          >
            <input v-model="tenantEditor.moduleIds" :value="module.id" type="checkbox" />
            <span style="flex:1;">{{ module.name }}</span>
            <span class="central-small">{{ formatMoney(module.price) }}</span>
          </label>
        </div>

        <div class="central-actions">
          <button class="central-button" type="submit" :disabled="saving">ذخیره تغییرات</button>
          <button class="central-button secondary" type="button" @click="resetTenantEditor">انصراف</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { CENTRAL_MODULES } from "../data/modules";

const props = defineProps({
  mode: { type: String, required: true },
  tenants: { type: Array, default: () => [] },
  plans: { type: Array, default: () => [] },
  userPricing: { type: Object, default: () => ({ included_users: 1, extra_user_price: 0 }) },
  modules: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: "" },
  message: { type: String, default: "" },
});

const emit = defineEmits(["create-tenant", "update-tenant", "add-domain", "remove-domain", "delete-tenant", "seed-demo-data"]);

const form = reactive({
  id: "",
  name: "",
  domain: "",
  planId: "",
  userCount: 1,
  seed: true,
});
const selectedModules = ref([]);
const selectedModulesInitialized = ref(false);
const siteSearch = ref("");
const domainDrafts = reactive({});
const editingTenant = ref(null);
const tenantEditor = reactive({
  planId: "",
  userCount: 1,
  moduleIds: [],
});

const catalogModules = computed(() => {
  const source = Array.isArray(props.modules) && props.modules.length ? props.modules : CENTRAL_MODULES;
  return source.map((module) => ({
    ...module,
    enabled: module.enabled !== false,
  }));
});
const enabledModules = computed(() => catalogModules.value.filter((module) => module.enabled));
const selectedPlan = computed(() => props.plans.find((plan) => String(plan.id) === String(form.planId)));
const selectedPlanPrice = computed(() => Number(selectedPlan.value?.base_price || 0));
const includedUsers = computed(() => Number(props.userPricing?.included_users || 1));
const extraUsers = computed(() => Math.max(0, Number(form.userCount || 1) - includedUsers.value));
const extraUsersTotal = computed(() => extraUsers.value * Number(props.userPricing?.extra_user_price || 0));
const estimatedModuleTotal = computed(() => selectedModules.value.reduce((sum, id) => {
  const module = catalogModules.value.find((item) => item.id === id);
  return sum + Number(module?.price || 0);
}, 0));
const estimatedTotal = computed(() => selectedPlanPrice.value + extraUsersTotal.value + estimatedModuleTotal.value);
const allSelectableModulesSelected = computed(() => enabledModules.value.length > 0 && enabledModules.value.every((module) => selectedModules.value.includes(module.id)));
const allEditedModulesSelected = computed(() => enabledModules.value.length > 0 && enabledModules.value.every((module) => tenantEditor.moduleIds.includes(module.id)));
const filteredTenants = computed(() => {
  const search = siteSearch.value.toLowerCase();
  return props.tenants.filter((tenant) => `${tenant.name || ""} ${tenant.id} ${(tenant.domains || []).map((domain) => domain.domain).join(" ")}`.toLowerCase().includes(search));
});

watch(
  enabledModules,
  (modules) => {
    if (!selectedModulesInitialized.value) {
      selectedModules.value = modules.map((module) => module.id);
      selectedModulesInitialized.value = true;
    }
  },
  { immediate: true },
);

function submitTenant() {
  emit("create-tenant", {
    id: form.id.trim().toLowerCase(),
    name: form.name.trim(),
    domain: normalizeDomain(form.domain),
    seed: form.seed,
    plan_id: form.planId || null,
    user_count: Number(form.userCount || 1),
    module_ids: selectedModules.value,
  });
}

function addDomain(tenant) {
  const domain = normalizeDomain(domainDrafts[tenant.id]);
  if (!domain) return;
  emit("add-domain", { tenant, domain, done: () => { domainDrafts[tenant.id] = ""; } });
}

function toggleAllSelectableModules(event) {
  selectedModules.value = event.target.checked ? enabledModules.value.map((module) => module.id) : [];
}

function startEditTenant(tenant) {
  editingTenant.value = tenant;
  tenantEditor.planId = tenant.plan_id || "";
  tenantEditor.userCount = Number(tenant.user_count || 1);
  tenantEditor.moduleIds = Array.isArray(tenant.module_ids)
    ? [...tenant.module_ids]
    : enabledModules.value.map((module) => module.id);
}

function resetTenantEditor() {
  editingTenant.value = null;
  tenantEditor.planId = "";
  tenantEditor.userCount = 1;
  tenantEditor.moduleIds = [];
}

function toggleEditedModules(event) {
  tenantEditor.moduleIds = event.target.checked ? enabledModules.value.map((module) => module.id) : [];
}

function saveTenantSettings() {
  if (!editingTenant.value) return;

  emit("update-tenant", {
    tenant: editingTenant.value,
    payload: {
      plan_id: tenantEditor.planId || null,
      user_count: Number(tenantEditor.userCount || 1),
      module_ids: tenantEditor.moduleIds,
    },
  });

  resetTenantEditor();
}

function normalizeDomain(value) {
  return String(value || "").trim().toLowerCase();
}

function formatMoney(value) {
  const amount = Number(value || 0);
  return amount === 0 ? "رایگان" : `${amount.toLocaleString("fa-IR")} تومان`;
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString("fa-IR");
}
</script>
