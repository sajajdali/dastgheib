<template>
  <div class="central-view">
    <section class="central-stats-grid">
      <article
        v-for="stat in stats"
        :key="stat.label"
        class="central-stat-card"
        :style="{ '--stat-color': stat.color, '--icon-bg': stat.iconBg, '--accent': stat.accent }"
      >
        <div class="central-stat-head">
          <span>{{ stat.label }}</span>
          <div class="central-stat-icon">{{ stat.icon }}</div>
        </div>
        <div class="central-stat-value">{{ formatNumber(stat.value) }}</div>
      </article>
    </section>

    <section v-if="openServiceQuestions.length" class="central-panel central-open-questions">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">سوالات جدید سرویس</div>
          <div class="central-section-subtitle">سوالات باز کلینیک‌ها که هنوز پاسخ نگرفته‌اند</div>
        </div>
        <button class="central-button" type="button" @click="$emit('open-service-tickets')">مشاهده همه</button>
      </div>

      <div class="central-list">
        <article v-for="question in openServiceQuestions" :key="question.id" class="central-list-row central-question-row">
          <div class="central-row-main">
            <div class="central-row-icon">؟</div>
            <div>
              <div class="central-row-title">{{ question.subject }}</div>
              <div class="central-small">
                {{ question.tenant_name || question.tenant_id }} · {{ question.user_name || "مدیر کلینیک" }} · {{ formatDate(question.created_at) }}
              </div>
            </div>
          </div>
          <span class="central-badge" style="--badge-color:#c2410c;--badge-bg:#fff7ed">باز</span>
        </article>
      </div>
    </section>

    <section class="central-panel">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">سایت‌های رو به اتمام</div>
          <div class="central-section-subtitle">سایت‌هایی که بسته زمانی‌شان به‌زودی تمام می‌شود</div>
        </div>
        <div class="central-filters">
          <button
            v-for="filter in expiryFilters"
            :key="filter.key"
            class="central-filter"
            :class="{ active: activeFilter === filter.key }"
            type="button"
            @click="activeFilter = filter.key"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>

      <div v-if="expiringTenants.length" class="central-list">
        <article v-for="tenant in expiringTenants" :key="tenant.id" class="central-list-row">
          <div class="central-row-main">
            <div class="central-row-icon">{{ tenant.name.charAt(0) }}</div>
            <div>
              <div class="central-row-title">{{ tenant.name }}</div>
              <div class="central-small">
                <span class="central-ltr">{{ tenant.primaryDomain || tenant.id }}</span>
                <span> · {{ tenant.planName || "بدون بسته" }}</span>
              </div>
            </div>
          </div>
          <span class="central-badge" :style="{ '--badge-color': tenant.urgency.color, '--badge-bg': tenant.urgency.bg }">
            {{ daysLabel(tenant.daysLeft) }}
          </span>
        </article>
      </div>

      <div v-else class="central-empty">در این بازه سایتی رو به اتمام نیست.</div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { EXPIRY_FILTERS } from "../data/modules";

const props = defineProps({
  tenants: { type: Array, default: () => [] },
  billingPlans: { type: Array, default: () => [] },
  serviceTickets: { type: Array, default: () => [] },
});

defineEmits(["open-service-tickets"]);

const activeFilter = ref("week");
const expiryFilters = EXPIRY_FILTERS;

const activeTenants = computed(() => props.tenants.filter((tenant) => tenant.status === "active"));
const inactiveTenants = computed(() => props.tenants.length - activeTenants.value.length);
const openServiceQuestions = computed(() => props.serviceTickets
  .filter((ticket) => ticket.status === "open")
  .slice(0, 5));

const stats = computed(() => [
  { label: "کل سایت‌ها", value: props.tenants.length, color: "#0f766e", accent: "linear-gradient(90deg,#2dd4bf,#0f766e)", iconBg: "#ecfdf9", icon: "◆" },
  { label: "فعال", value: activeTenants.value.length, color: "#15803d", accent: "linear-gradient(90deg,#4ade80,#15803d)", iconBg: "#f0fdf4", icon: "✓" },
  { label: "غیرفعال", value: inactiveTenants.value, color: "#dc2626", accent: "linear-gradient(90deg,#f87171,#dc2626)", iconBg: "#fef2f2", icon: "×" },
  { label: "بسته‌های زمانی", value: props.billingPlans.length, color: "#7c3aed", accent: "linear-gradient(90deg,#c4b5fd,#7c3aed)", iconBg: "#f5f3ff", icon: "▤" },
]);

const expiringTenants = computed(() => {
  const selected = expiryFilters.find((filter) => filter.key === activeFilter.value);
  const limit = selected?.limit ?? 7;

  return props.tenants
    .map(normalizeTenantExpiry)
    .filter((tenant) => tenant.daysLeft <= limit)
    .sort((a, b) => a.daysLeft - b.daysLeft);
});

function normalizeTenantExpiry(tenant) {
  const daysLeft = Number(tenant.days_left ?? tenant.daysLeft ?? tenant.remaining_days ?? 365);
  return {
    ...tenant,
    name: tenant.name || tenant.id,
    primaryDomain: tenant.domains?.[0]?.domain,
    planName: tenant.plan_name || tenant.planName,
    daysLeft,
    urgency: urgency(daysLeft),
  };
}

function urgency(days) {
  if (days <= 1) return { color: "#dc2626", bg: "#fef2f2" };
  if (days <= 3) return { color: "#ea580c", bg: "#fff7ed" };
  if (days <= 7) return { color: "#d97706", bg: "#fffbeb" };
  return { color: "#15803d", bg: "#f0fdf4" };
}

function daysLabel(days) {
  if (days <= 0) return "امروز";
  if (days === 1) return "فردا";
  return `${formatNumber(days)} روز مانده`;
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString("fa-IR");
}

function formatDate(value) {
  if (!value) return "";
  return new Date(value).toLocaleDateString("fa-IR-u-ca-persian", { year: "numeric", month: "long", day: "numeric" });
}
</script>

<style scoped>
.central-open-questions{border-color:#fed7aa;background:linear-gradient(135deg,#fff,#fff7ed)}.central-question-row{border-color:#ffedd5}.central-question-row .central-row-icon{background:#fff7ed;color:#c2410c}
</style>
