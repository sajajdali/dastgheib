<template>
  <main class="central-shell">
    <aside class="central-sidebar">
      <div class="central-sidebar-brand">
        <div class="central-sidebar-logo">ک</div>
        <div>
          <strong>کلینیک‌یار</strong>
          <span>مدیریت مرکزی</span>
        </div>
      </div>

      <nav class="central-nav">
        <button
          v-for="item in navItems"
          :key="item.key"
          type="button"
          :class="{ active: activeTab === item.key }"
          @click="$emit('change-tab', item.key)"
        >
          <span v-html="item.icon"></span>
          <span>{{ item.label }}</span>
          <b v-if="item.key === 'serviceTickets' && openServiceQuestionCount" class="central-nav-badge">
            {{ openServiceQuestionCount.toLocaleString('fa-IR') }}
          </b>
        </button>
      </nav>

      <div class="central-sidebar-footer">نسخه مدیریت مرکزی · ۱۴۰۵</div>
    </aside>

    <section class="central-main">
      <header class="central-topbar">
        <div>
          <div class="central-kicker">مدیریت مرکزی</div>
          <div class="central-title">{{ pageTitle }}</div>
        </div>
        <div class="central-topbar-actions">
          <button class="central-button secondary" type="button" :disabled="loading" @click="$emit('refresh')">بروزرسانی</button>
          <button class="central-button danger" type="button" @click="$emit('logout')">خروج</button>
          <div class="central-admin-chip">{{ adminInitial }}</div>
        </div>
      </header>

      <section class="central-content">
        <slot />
      </section>
    </section>
  </main>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  activeTab: { type: String, required: true },
  admin: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  openServiceQuestionCount: { type: Number, default: 0 },
});

defineEmits(["change-tab", "refresh", "logout"]);

const navItems = [
  {
    key: "dashboard",
    label: "داشبورد",
    title: "داشبورد سیستم‌ها",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
  },
  {
    key: "packages",
    label: "بسته‌های زمانی",
    title: "بسته‌های زمانی و تعرفه کاربران",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/></svg>',
  },
  {
    key: "discounts",
    label: "کدهای تخفیف",
    title: "مدیریت کدهای تخفیف",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V7H4v5"/><path d="M4 12v5h16v-5"/><path d="M8 8h.01M16 16h.01"/><path d="M16 8l-8 8"/></svg>',
  },
  {
    key: "storeTerms",
    label: "قوانین و مقررات",
    title: "قوانین و مقررات فروشگاه",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6M9 8h2"/></svg>',
  },
  {
    key: "modules",
    label: "ماژول‌های نوبت‌دهی",
    title: "ماژول‌های سامانه نوبت‌دهی",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 005 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019 9c.36.24.63.6.73 1H21a2 2 0 010 4h-.09c-.4 0-.76.27-1 .63z"/></svg>',
  },
  {
    key: "serviceTickets",
    label: "پشتیبانی‌های سرویس",
    title: "سوالات و پشتیبانی‌های سرویس",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 01-4 4H8l-5 3V7a4 4 0 014-4h10a4 4 0 014 4z"/><path d="M8 9h8M8 13h5"/></svg>',
  },
  {
    key: "newsite",
    label: "ثبت سایت جدید",
    title: "ثبت سایت جدید",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>',
  },
  {
    key: "sites",
    label: "مدیریت سایت‌ها",
    title: "مدیریت سایت‌ها",
    icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>',
  },
];

const pageTitle = computed(() => navItems.find((item) => item.key === props.activeTab)?.title || "مدیریت مرکزی");
const adminInitial = computed(() => props.admin?.name?.charAt(0) || "A");
</script>

<style scoped>
.central-nav button{position:relative}.central-nav-badge{min-width:22px;height:22px;display:grid;place-items:center;margin-inline-start:auto;padding:0 6px;border-radius:999px;background:#dc2626;color:#fff;font-size:11px;font-weight:1000;line-height:1}
</style>
