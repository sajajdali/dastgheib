<template>
  <section class="roles-page" :class="{ embedded }" dir="rtl">
    <header class="roles-header">
      <button class="primary-btn" type="button" @click="startCreateRole">+ نقش جدید</button>
      <div>
        <h1>مدیریت نقش‌ها</h1>
        <p>نقش‌ها، سطح دسترسی هر نقش و کاربران متصل را مدیریت کنید.</p>
      </div>
    </header>

    <div v-if="loading" class="roles-loading">
      <span class="loader"></span>
      در حال دریافت نقش‌ها...
    </div>

    <template v-else>
      <div class="roles-layout">
        <aside class="role-list panel">
          <div class="panel-title">نقش‌های سیستم</div>
          <button
            v-for="role in roles"
            :key="role.id"
            type="button"
            class="role-list-item"
            :class="{ active: draft.id === role.id }"
            @click="selectRole(role)"
          >
            <span>{{ role.name }}</span>
            <small>{{ role.users_count }} کاربر</small>
          </button>
          <div v-if="!roles.length" class="empty-state">هنوز نقشی تعریف نشده است.</div>
        </aside>

        <main class="role-editor panel">
          <div class="editor-head">
            <div>
              <div class="panel-title">{{ draft.id ? 'ویرایش نقش' : 'تعریف نقش جدید' }}</div>
              <small v-if="draft.protected" class="protected-badge">نقش سیستمی</small>
            </div>
            <button
              v-if="draft.id"
              class="danger-link"
              type="button"
              :disabled="draft.protected || draft.users_count > 0"
              @click="removeRole"
            >حذف نقش</button>
          </div>

          <label class="field-label" for="role-name">نام نقش</label>
          <input id="role-name" v-model.trim="draft.name" class="text-input" type="text" placeholder="مثلاً مسئول پذیرش" :disabled="draft.protected" />

          <div class="permissions-title">
            <span>مجوزهای این نقش</span>
            <button class="text-btn" type="button" @click="toggleAllPermissions">
              {{ allPermissionsSelected ? 'لغو انتخاب همه' : 'انتخاب همه' }}
            </button>
          </div>

          <div class="permission-groups">
            <article v-for="group in visiblePermissionGroups" :key="group.key" class="permission-group">
              <label class="group-title">
                <input type="checkbox" :checked="isGroupSelected(group)" @change="toggleGroup(group, $event.target.checked)" />
                <span>{{ group.label }}</span>
              </label>
              <div class="permission-grid">
                <label v-for="permission in plainPermissions(group)" :key="permission.name" class="permission-item">
                  <input v-model="draft.permissions" type="checkbox" :value="permission.name" />
                  <span>{{ permission.label }}</span>
                </label>
              </div>
              <fieldset v-for="choice in group.choices || []" :key="choice.key" class="permission-choice">
                <legend>{{ choice.label }}</legend>
                <label v-for="(option, index) in choice.options" :key="index" class="permission-choice-option">
                  <input type="radio" :name="`${draft.id || 'new'}-${choice.key}`" :checked="selectedChoiceIndex(choice) === index" @change="selectChoice(choice, option)" />
                  <span>{{ option.label }}</span>
                </label>
              </fieldset>
            </article>
          </div>

          <div class="editor-actions">
            <button class="primary-btn" type="button" :disabled="saving" @click="saveRole">
              {{ saving ? 'در حال ذخیره...' : 'ذخیره نقش' }}
            </button>
          </div>
        </main>
      </div>

    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { filterRolePermissionGroups, replaceVisiblePermissions } from "@/utils/rolePermissionModules";

const API_URL = "/api";
const props = defineProps({
  embedded: { type: Boolean, default: false },
  enabledFeatures: { type: Array, default: null },
});
const loading = ref(true);
const saving = ref(false);
const roles = ref([]);
const permissionGroups = ref([]);
const draft = ref(emptyDraft());

function emptyDraft() {
  return { id: null, name: "", permissions: [], users_count: 0, protected: false };
}

const visiblePermissionGroups = computed(() =>
  filterRolePermissionGroups(permissionGroups.value, props.enabledFeatures)
);
const allPermissionNames = computed(() =>
  visiblePermissionGroups.value.flatMap(group => group.permissions.map(permission => permission.name))
);
const allPermissionsSelected = computed(() =>
  allPermissionNames.value.length > 0 && allPermissionNames.value.every(name => draft.value.permissions.includes(name))
);

function selectRole(role) {
  draft.value = {
    id: role.id,
    name: role.name,
    permissions: [...role.permissions],
    users_count: role.users_count,
    protected: role.protected,
  };
}
function startCreateRole() { draft.value = emptyDraft(); }
// مجوزهایی که در یک انتخاب چندحالته (مثل نوبت‌های گذشته) هستند به‌صورت دکمهٔ رادیویی نمایش داده می‌شوند.
function choicePermissionNames(group) {
  return new Set((group.choices || []).flatMap(choice => choice.options.flatMap(option => option.permissions)));
}
function plainPermissions(group) {
  const inChoices = choicePermissionNames(group);
  return group.permissions.filter(permission => !inChoices.has(permission.name));
}
function selectedChoiceIndex(choice) {
  const all = new Set(choice.options.flatMap(option => option.permissions));
  const selected = draft.value.permissions.filter(name => all.has(name)).sort().join("|");
  const index = choice.options.findIndex(option => [...option.permissions].sort().join("|") === selected);
  return index >= 0 ? index : choice.options.length - 1;
}
function selectChoice(choice, option) {
  const all = new Set(choice.options.flatMap(item => item.permissions));
  draft.value.permissions = [...draft.value.permissions.filter(name => !all.has(name)), ...option.permissions];
}
function isGroupSelected(group) {
  return group.permissions.length > 0 && group.permissions.every(permission => draft.value.permissions.includes(permission.name));
}
function toggleGroup(group, checked) {
  const next = new Set(draft.value.permissions);
  group.permissions.forEach(permission => checked ? next.add(permission.name) : next.delete(permission.name));
  draft.value.permissions = [...next];
}
function toggleAllPermissions() {
  draft.value.permissions = replaceVisiblePermissions(
    draft.value.permissions,
    allPermissionNames.value,
    allPermissionsSelected.value ? [] : allPermissionNames.value
  );
}
function errorMessage(error, fallback) {
  const errors = error.response?.data?.errors;
  if (errors) return Object.values(errors).flat()[0] || fallback;
  return error.response?.data?.message || fallback;
}

async function loadData(preferredRoleId = null) {
  loading.value = true;
  try {
    const { data } = await axios.get(`${API_URL}/roles`);
    roles.value = data.roles || [];
    permissionGroups.value = data.permission_groups || [];
    const role = roles.value.find(item => item.id === preferredRoleId) || roles.value[0];
    draft.value = role ? {
      id: role.id,
      name: role.name,
      permissions: [...role.permissions],
      users_count: role.users_count,
      protected: role.protected,
    } : emptyDraft();
  } catch (error) {
    Swal.fire({ icon: "error", title: "خطا", text: errorMessage(error, "دریافت نقش‌ها انجام نشد.") });
  } finally {
    loading.value = false;
  }
}

async function saveRole() {
  if (!draft.value.name) {
    Swal.fire({ icon: "warning", title: "نام نقش را وارد کنید" });
    return;
  }
  saving.value = true;
  try {
    const payload = { name: draft.value.name, permissions: draft.value.permissions };
    const response = draft.value.id
      ? await axios.put(`${API_URL}/roles/${draft.value.id}`, payload)
      : await axios.post(`${API_URL}/roles`, payload);
    await loadData(response.data.id);
    Swal.fire({ icon: "success", title: "نقش ذخیره شد", timer: 1400, showConfirmButton: false });
  } catch (error) {
    Swal.fire({ icon: "error", title: "خطا", text: errorMessage(error, "ذخیره نقش انجام نشد.") });
  } finally {
    saving.value = false;
  }
}

async function removeRole() {
  const result = await Swal.fire({
    icon: "warning",
    title: `حذف نقش «${draft.value.name}»؟`,
    text: "این عملیات قابل بازگشت نیست.",
    showCancelButton: true,
    confirmButtonText: "حذف",
    cancelButtonText: "انصراف",
  });
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`${API_URL}/roles/${draft.value.id}`);
    await loadData();
    Swal.fire({ icon: "success", title: "نقش حذف شد", timer: 1200, showConfirmButton: false });
  } catch (error) {
    Swal.fire({ icon: "error", title: "خطا", text: errorMessage(error, "حذف نقش انجام نشد.") });
  }
}

onMounted(() => loadData());
</script>

<style scoped>
.roles-page { min-height:100vh; padding:24px; color:#1f2937; background:#f6f8fc; font-family:"Vazir",sans-serif; }
.roles-page.embedded { min-height:auto; padding:0; background:transparent; }
.roles-header,.editor-head,.permissions-title { display:flex; align-items:center; justify-content:space-between; gap:16px; }
.roles-header { margin-bottom:22px; }
.roles-header h1 { margin:0 0 5px; font-size:24px; }
.roles-header p,.section-help { margin:0; color:#6b7280; }
.roles-layout { display:grid; grid-template-columns:minmax(210px,260px) minmax(0,1fr); gap:18px; align-items:start; }
.panel { border:1px solid #e5e7eb; border-radius:18px; background:#fff; box-shadow:0 10px 30px rgba(31,41,55,.06); }
.role-list,.role-editor { padding:18px; }
.panel-title { font-size:17px; font-weight:800; margin-bottom:14px; }
.role-list-item { width:100%; display:flex; justify-content:space-between; align-items:center; border:0; border-radius:12px; padding:11px 12px; margin-bottom:7px; background:#f8fafc; color:#374151; font-family:inherit; cursor:pointer; }
.role-list-item small { color:#94a3b8; }
.role-list-item.active { background:#eaf2ff; color:#1769d2; }
.field-label { display:block; margin:18px 0 7px; font-weight:700; }
.text-input { width:100%; border:1px solid #d7dee8; border-radius:11px; padding:11px 13px; font-family:inherit; outline:none; }
.text-input:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.permissions-title { margin:22px 0 12px; font-weight:800; }
.permission-groups { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.permission-group { border:1px solid #e8edf4; border-radius:14px; padding:13px; }
.group-title { display:flex; gap:8px; align-items:center; font-weight:800; margin-bottom:11px; }
.permission-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
.permission-item,.user-role-list label { display:flex; gap:7px; align-items:center; font-size:13px; }
.primary-btn,.secondary-btn { border:0; border-radius:11px; padding:10px 16px; font-family:inherit; font-weight:700; cursor:pointer; }
.primary-btn { color:#fff; background:#2563eb; }
.secondary-btn { color:#1d4ed8; background:#eaf2ff; }
.primary-btn:disabled,.danger-link:disabled { opacity:.5; cursor:not-allowed; }
.text-btn,.danger-link { border:0; background:transparent; font-family:inherit; cursor:pointer; }
.text-btn { color:#2563eb; }
.danger-link { color:#dc2626; }
.editor-actions { display:flex; justify-content:flex-end; margin-top:18px; }
.protected-badge { display:inline-block; color:#92400e; background:#fef3c7; border-radius:999px; padding:4px 9px; }
.roles-loading { min-height:260px; display:flex; align-items:center; justify-content:center; gap:10px; }
.loader { width:22px; height:22px; border:3px solid #dbe3ef; border-top-color:#2563eb; border-radius:50%; animation:spin .7s linear infinite; }
.empty-state { color:#94a3b8; padding:16px 4px; text-align:center; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width:1000px) { .permission-groups { grid-template-columns:1fr; } }
@media (max-width:700px) { .roles-page{padding:14px}.roles-layout{grid-template-columns:1fr}.permission-grid{grid-template-columns:1fr}.roles-header{align-items:flex-start;flex-direction:column} }
.permission-choice{margin:10px 0 0;padding:9px 11px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb}
.permission-choice legend{padding:0 6px;color:#92400e;font-size:12px;font-weight:900}
.permission-choice-option{display:flex;align-items:center;gap:7px;padding:5px 2px;font-size:12px;cursor:pointer}
.permission-choice-option input{width:16px;height:16px;accent-color:#d97706}
</style>

<style>
/* Dark charcoal: roles-page */
html body.dark .roles-page,
html #app.dark .roles-page {
  background: #121316 !important;
  color: #e8eaed !important;
}
html body.dark .roles-page.embedded,
html #app.dark .roles-page.embedded {
  background: transparent !important;
}
html body.dark .roles-page .roles-header h1,
html body.dark .roles-page .panel-title,
html body.dark .roles-page .group-title,
html body.dark .roles-page .permissions-title,
html #app.dark .roles-page .roles-header h1,
html #app.dark .roles-page .panel-title,
html #app.dark .roles-page .group-title,
html #app.dark .roles-page .permissions-title {
  color: #f1f3f4 !important;
}
html body.dark .roles-page .roles-header p,
html body.dark .roles-page .section-help,
html body.dark .roles-page .role-list-item small,
html body.dark .roles-page .empty-state,
html body.dark .roles-page .field-label,
html #app.dark .roles-page .roles-header p,
html #app.dark .roles-page .section-help,
html #app.dark .roles-page .role-list-item small,
html #app.dark .roles-page .empty-state,
html #app.dark .roles-page .field-label {
  color: #9aa0a6 !important;
}
html body.dark .roles-page .panel,
html body.dark .roles-page .permission-group,
html body.dark .roles-page .permission-choice,
html #app.dark .roles-page .panel,
html #app.dark .roles-page .permission-group,
html #app.dark .roles-page .permission-choice {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  box-shadow: 0 10px 28px rgba(0,0,0,.32) !important;
  color: #e8eaed !important;
}
html body.dark .roles-page .role-list-item,
html #app.dark .roles-page .role-list-item {
  background: #22252d !important;
  color: #e8eaed !important;
}
html body.dark .roles-page .role-list-item:hover,
html #app.dark .roles-page .role-list-item:hover {
  background: #2a2f3a !important;
}
html body.dark .roles-page .role-list-item.active,
html #app.dark .roles-page .role-list-item.active {
  background: #2a3a52 !important;
  color: #93c5fd !important;
}
html body.dark .roles-page .text-input,
html #app.dark .roles-page .text-input {
  background: #121417 !important;
  border-color: #3a3f4a !important;
  color: #f1f3f4 !important;
}
html body.dark .roles-page .secondary-btn,
html body.dark .roles-page .text-btn,
html #app.dark .roles-page .secondary-btn,
html #app.dark .roles-page .text-btn {
  background: #22252d !important;
  color: #93c5fd !important;
}
html body.dark .roles-page .primary-btn,
html #app.dark .roles-page .primary-btn {
  background: #4f8cff !important;
  color: #fff !important;
}
html body.dark .roles-page .protected-badge,
html body.dark .roles-page .permission-choice legend,
html #app.dark .roles-page .protected-badge,
html #app.dark .roles-page .permission-choice legend {
  background: #3a3220 !important;
  color: #fbbf24 !important;
}
html body.dark .roles-page .permission-choice,
html #app.dark .roles-page .permission-choice {
  background: #2a2618 !important;
  border-color: #6b5a2e !important;
}
html body.dark .roles-page .permission-item,
html body.dark .roles-page .user-role-list label,
html body.dark .roles-page .permission-choice-option,
html #app.dark .roles-page .permission-item,
html #app.dark .roles-page .user-role-list label,
html #app.dark .roles-page .permission-choice-option {
  color: #e8eaed !important;
}
html body.dark .roles-page .danger-link,
html #app.dark .roles-page .danger-link {
  color: #f5a8a0 !important;
}
html body.dark .roles-page .loader,
html #app.dark .roles-page .loader {
  border-color: #3a3f4a !important;
  border-top-color: #4f8cff !important;
}
</style>
