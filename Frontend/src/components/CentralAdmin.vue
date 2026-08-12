<template>
  <section v-if="authLoading" class="central-loading">در حال بررسی ورود...</section>

  <CentralLogin
    v-else-if="!admin"
    :loading="loginLoading"
    :error="loginError"
    @login="login"
  />

  <CentralLayout
    v-else
    :active-tab="activeTab"
    :admin="admin"
    :loading="loading || billingSaving || saving"
    :open-service-question-count="openServiceQuestionCount"
    @change-tab="changeCentralTab"
    @refresh="refreshAll"
    @logout="logout"
  >
    <CentralDashboard
      v-if="activeTab === 'dashboard'"
      :tenants="tenants"
      :billing-plans="billingPlans"
      :service-tickets="serviceTickets"
      @open-service-tickets="activeTab = 'serviceTickets'"
    />

    <CentralPackages
      v-else-if="activeTab === 'packages' || activeTab === 'discounts'"
      :mode="activeTab"
      :plans="billingPlans"
      :user-pricing="userPricing"
      :discount-codes="discountCodes"
      :saving="billingSaving"
      :error="billingError"
      @save-plan="savePlan"
      @delete-plan="deletePlan"
      @save-user-pricing="saveUserPricing"
      @save-discount="saveDiscount"
      @delete-discount="deleteDiscount"
    />

    <CentralModules
      v-else-if="activeTab === 'modules'"
      v-model="modules"
    />

    <CentralStoreTerms
      v-else-if="activeTab === 'storeTerms'"
      :terms="storeTerms"
      :saving="billingSaving"
      :error="billingError"
      :message="billingMessage"
      @save="saveStoreTerms"
    />

    <CentralServiceTickets
      v-else-if="activeTab === 'serviceTickets'"
      :tickets="serviceTickets"
      :reset-key="serviceTicketsResetKey"
      @answer-ticket="answerServiceTicket"
      @update-ticket-status="updateServiceTicketStatus"
    />

    <CentralTenantManager
      v-else
      :mode="activeTab"
      :tenants="tenants"
      :plans="billingPlans"
      :user-pricing="userPricing"
      :modules="modules"
      :saving="saving"
      :error="error"
      :message="message"
      @create-tenant="createTenant"
      @update-tenant="updateTenant"
      @add-domain="addDomain"
      @remove-domain="removeDomain"
      @seed-demo-data="seedDemoData"
      @delete-tenant="deleteTenant"
    />
  </CentralLayout>
</template>

<script setup>
import axios from "axios";
import { computed, onMounted, ref, watch } from "vue";
import CentralDashboard from "../central/components/CentralDashboard.vue";
import CentralLayout from "../central/components/CentralLayout.vue";
import CentralLogin from "../central/components/CentralLogin.vue";
import CentralModules from "../central/components/CentralModules.vue";
import CentralPackages from "../central/components/CentralPackages.vue";
import CentralServiceTickets from "../central/components/CentralServiceTickets.vue";
import CentralStoreTerms from "../central/components/CentralStoreTerms.vue";
import CentralTenantManager from "../central/components/CentralTenantManager.vue";
import { CENTRAL_MODULES } from "../central/data/modules";
import "../central/styles/central-admin.css";

const admin = ref(null);
const authLoading = ref(true);
const loginLoading = ref(false);
const tenants = ref([]);
const billingPlans = ref([]);
const userPricing = ref({ included_users: 1, extra_user_price: 0 });
const discountCodes = ref([]);
const storeTerms = ref(null);
const serviceTickets = ref([]);
const modules = ref(loadModules());
const activeTab = ref("dashboard");
const serviceTicketsResetKey = ref(0);
const loading = ref(false);
const saving = ref(false);
const billingSaving = ref(false);
const loginError = ref("");
const error = ref("");
const billingError = ref("");
const billingMessage = ref("");
const message = ref("");
const openServiceQuestionCount = computed(() => serviceTickets.value.filter((ticket) => ticket.status === "open").length);

function changeCentralTab(tab) {
  if (tab === "serviceTickets") serviceTicketsResetKey.value += 1;
  activeTab.value = tab;
}

function loadModules() {
  const defaults = CENTRAL_MODULES.map((module) => ({ ...module, enabled: module.enabled !== false }));
  try {
    const stored = JSON.parse(localStorage.getItem("central-system-features") || "[]");
    if (Array.isArray(stored) && stored.length) {
      const storedById = new Map(stored.map(normalizeModule).map((module) => [module.id, module]));
      return defaults.map((module) => normalizeModule({ ...module, ...(storedById.get(module.id) || {}) }));
    }
  } catch {
    //
  }

  return defaults.map(normalizeModule);
}

function normalizeModule(module) {
  const aliases = {
    chat: "patients",
    staffEval: "resources",
    tasks: "followups",
    campaign: "automation",
    aiReport: "beauty",
  };
  const id = String(module.id || Date.now()).trim();

  return {
    id: aliases[id] || id,
    name: String(module.name || "").trim(),
    price: Number(module.price || 0),
    periods: normalizeModulePeriods(module),
    enabled: module.enabled !== false,
  };
}

function normalizeModulePeriods(module) {
  if (Array.isArray(module.periods) && module.periods.length) {
    return module.periods.map((period) => ({
      key: String(period.key || "one_time"),
      label: String(period.label || period.key || "یک‌باره"),
      price: Number(period.price || 0),
    }));
  }

  return [{ key: "one_time", label: "یک‌باره", price: Number(module.price || 0) }];
}

function uniqueModules(nextModules) {
  const seen = new Set();
  return nextModules.filter((module) => {
    if (seen.has(module.id)) return false;
    seen.add(module.id);
    return true;
  });
}

function responseMessage(requestError, fallback) {
  const errors = requestError.response?.data?.errors;
  if (errors) return Object.values(errors).flat().filter(Boolean).join(" ");
  return requestError.response?.data?.message || fallback;
}

async function checkAuth() {
  authLoading.value = true;
  try {
    const { data } = await axios.get("/central-api/me");
    admin.value = data.admin;
    await refreshAll();
  } catch {
    admin.value = null;
  } finally {
    authLoading.value = false;
  }
}

async function login(payload) {
  loginLoading.value = true;
  loginError.value = "";
  try {
    const { data } = await axios.post("/central-api/login", payload);
    admin.value = data.admin;
    await refreshAll();
  } catch (requestError) {
    loginError.value = responseMessage(requestError, "ورود انجام نشد.");
  } finally {
    loginLoading.value = false;
  }
}

async function logout() {
  try {
    await axios.post("/central-api/logout");
  } catch {
    // خروج سمت رابط کاربری حتی اگر session سمت سرور قبلا منقضی شده باشد انجام می‌شود.
  }
  admin.value = null;
  tenants.value = [];
  billingPlans.value = [];
  storeTerms.value = null;
}

async function refreshAll() {
  await Promise.allSettled([loadBilling(), loadStoreTerms(), loadTenants(), loadServiceTickets()]);
}

async function loadBilling() {
  billingError.value = "";
  try {
    const { data } = await axios.get("/central-api/billing");
    billingPlans.value = data.plans || [];
    userPricing.value = data.user_pricing || { included_users: 1, extra_user_price: 0 };
    discountCodes.value = data.discount_codes || [];
  } catch (requestError) {
    if (requestError.response?.status === 401) admin.value = null;
    billingError.value = responseMessage(requestError, "دریافت اطلاعات مالی انجام نشد.");
  }
}

async function loadStoreTerms() {
  billingError.value = "";
  try {
    const { data } = await axios.get("/central-api/store-terms");
    storeTerms.value = data.terms || null;
  } catch (requestError) {
    if (requestError.response?.status === 401) admin.value = null;
    billingError.value = responseMessage(requestError, "دریافت قوانین فروشگاه انجام نشد.");
  }
}

async function loadTenants() {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get("/central-api/tenants");
    tenants.value = data.tenants || [];
  } catch (requestError) {
    if (requestError.response?.status === 401) admin.value = null;
    error.value = responseMessage(requestError, "دریافت لیست سیستم‌ها انجام نشد.");
  } finally {
    loading.value = false;
  }
}

async function loadServiceTickets() {
  try {
    const { data } = await axios.get("/central-api/service-tickets");
    serviceTickets.value = data.tickets || [];
  } catch (requestError) {
    if (requestError.response?.status === 401) admin.value = null;
    serviceTickets.value = [];
  }
}

async function answerServiceTicket({ ticket, answer, attachment }) {
  const payload = new FormData();
  payload.append("answer", answer || "");
  payload.append("status", "answered");
  if (attachment) payload.append("answer_attachment", attachment);
  const { data } = await axios.post(`/central-api/service-tickets/${ticket.id}`, payload, {
    headers: { "X-HTTP-Method-Override": "PATCH" },
  });
  replaceServiceTicket(data.ticket);
}

async function updateServiceTicketStatus({ ticket, status }) {
  const { data } = await axios.patch(`/central-api/service-tickets/${ticket.id}`, {
    answer: ticket.answer || "",
    status,
  });
  replaceServiceTicket(data.ticket);
}

function replaceServiceTicket(nextTicket) {
  const index = serviceTickets.value.findIndex((ticket) => ticket.id === nextTicket.id);
  if (index >= 0) serviceTickets.value.splice(index, 1, nextTicket);
}

async function savePlan({ id, payload, done }) {
  billingSaving.value = true;
  billingError.value = "";
  try {
    if (id) {
      await axios.patch(`/central-api/billing/plans/${id}`, payload);
    } else {
      await axios.post("/central-api/billing/plans", payload);
    }
    done?.();
    await loadBilling();
  } catch (requestError) {
    billingError.value = responseMessage(requestError, "ذخیره بسته انجام نشد.");
  } finally {
    billingSaving.value = false;
  }
}

async function deletePlan(plan) {
  if (!window.confirm(`بسته ${plan.name} حذف شود؟`)) return;
  billingSaving.value = true;
  billingError.value = "";
  try {
    await axios.delete(`/central-api/billing/plans/${plan.id}`);
    await loadBilling();
  } catch (requestError) {
    billingError.value = responseMessage(requestError, "حذف بسته انجام نشد.");
  } finally {
    billingSaving.value = false;
  }
}

async function saveUserPricing(payload) {
  billingSaving.value = true;
  billingError.value = "";
  try {
    const { data } = await axios.patch("/central-api/billing/user-pricing", payload);
    userPricing.value = data.user_pricing;
  } catch (requestError) {
    billingError.value = responseMessage(requestError, "ذخیره تعرفه کاربر انجام نشد.");
  } finally {
    billingSaving.value = false;
  }
}

async function saveDiscount({ id, payload, done }) {
  billingSaving.value = true;
  billingError.value = "";
  try {
    if (id) {
      await axios.patch(`/central-api/billing/discounts/${id}`, payload);
    } else {
      await axios.post("/central-api/billing/discounts", payload);
    }
    done?.();
    await loadBilling();
  } catch (requestError) {
    billingError.value = responseMessage(requestError, "ذخیره کد تخفیف انجام نشد.");
  } finally {
    billingSaving.value = false;
  }
}

async function deleteDiscount(discount) {
  if (!window.confirm(`کد ${discount.code} حذف شود؟`)) return;
  billingSaving.value = true;
  billingError.value = "";
  try {
    await axios.delete(`/central-api/billing/discounts/${discount.id}`);
    await loadBilling();
  } catch (requestError) {
    billingError.value = responseMessage(requestError, "حذف کد تخفیف انجام نشد.");
  } finally {
    billingSaving.value = false;
  }
}

async function saveStoreTerms(payload) {
  billingSaving.value = true;
  billingError.value = "";
  billingMessage.value = "";
  try {
    const { data } = await axios.patch("/central-api/store-terms", payload);
    storeTerms.value = data.terms;
    billingMessage.value = data.message || "قوانین ذخیره شد.";
  } catch (requestError) {
    billingError.value = responseMessage(requestError, "ذخیره قوانین انجام نشد.");
  } finally {
    billingSaving.value = false;
  }
}

async function createTenant(payload) {
  saving.value = true;
  message.value = "";
  error.value = "";
  try {
    await axios.post("/central-api/tenants", payload);
    message.value = "سایت جدید ساخته شد.";
    activeTab.value = "sites";
    await loadTenants();
  } catch (requestError) {
    error.value = responseMessage(requestError, "ساخت سایت انجام نشد.");
  } finally {
    saving.value = false;
  }
}

async function updateTenant({ tenant, payload }) {
  error.value = "";
  saving.value = true;
  try {
    const { data } = await axios.patch(`/central-api/tenants/${tenant.id}`, payload);
    replaceTenant(data.tenant);
  } catch (requestError) {
    error.value = responseMessage(requestError, "به‌روزرسانی انجام نشد.");
    await loadTenants();
  } finally {
    saving.value = false;
  }
}

async function addDomain({ tenant, domain, done }) {
  error.value = "";
  try {
    const { data } = await axios.post(`/central-api/tenants/${tenant.id}/domains`, { domain });
    done?.();
    replaceTenant(data.tenant);
  } catch (requestError) {
    error.value = responseMessage(requestError, "افزودن دامنه انجام نشد.");
  }
}

async function removeDomain({ tenant, domain }) {
  if (!window.confirm(`دامنه ${domain.domain} حذف شود؟`)) return;
  error.value = "";
  try {
    const { data } = await axios.delete(`/central-api/tenants/${tenant.id}/domains/${domain.id}`);
    replaceTenant(data.tenant);
  } catch (requestError) {
    error.value = responseMessage(requestError, "حذف دامنه انجام نشد.");
  }
}

async function seedDemoData(tenant) {
  if (!window.confirm(`برای سایت ${tenant.name || tenant.id} دیتای تستی کامل ساخته شود؟`)) return;
  saving.value = true;
  error.value = "";
  message.value = "";
  try {
    const { data } = await axios.post(`/central-api/tenants/${tenant.id}/demo-data`);
    replaceTenant(data.tenant);
    const summary = data.summary || {};
    message.value = `${data.message || "دیتای تستی ساخته شد."} نوبت‌ها: ${summary.appointments || 0}، بیماران: ${summary.patients || 0}، ریزحقوق: ${summary.payroll_lines || 0}`;
  } catch (requestError) {
    error.value = responseMessage(requestError, "ساخت دیتای تستی انجام نشد.");
  } finally {
    saving.value = false;
  }
}

async function deleteTenant(tenant) {
  if (!window.confirm(`سیستم ${tenant.name || tenant.id} و دیتابیس آن حذف شود؟`)) return;
  saving.value = true;
  error.value = "";
  try {
    await axios.delete(`/central-api/tenants/${tenant.id}`);
    tenants.value = tenants.value.filter((item) => item.id !== tenant.id);
  } catch (requestError) {
    error.value = responseMessage(requestError, "حذف سیستم انجام نشد.");
  } finally {
    saving.value = false;
  }
}

function replaceTenant(nextTenant) {
  const index = tenants.value.findIndex((tenant) => tenant.id === nextTenant.id);
  if (index >= 0) tenants.value.splice(index, 1, nextTenant);
}

onMounted(checkAuth);

watch(
  modules,
  (nextModules) => {
    localStorage.setItem("central-system-features", JSON.stringify(nextModules.map(normalizeModule)));
  },
  { deep: true },
);
</script>
