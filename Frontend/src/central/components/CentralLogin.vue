<template>
  <main class="central-login">
    <form class="central-login-card" @submit.prevent="submit">
      <div class="central-login-brand">
        <div class="central-logo">ک</div>
        <strong>پنل کلینیک‌یار</strong>
        <span>ورود مدیر کل سیستم</span>
      </div>

      <div class="central-form-stack">
        <label class="central-form-label">
          ایمیل مدیر کل
          <input v-model.trim="form.email" class="central-input central-ltr" type="email" autocomplete="username" required />
        </label>

        <label class="central-form-label">
          رمز عبور
          <div class="central-password-field">
            <input
              v-model="form.password"
              class="central-input"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="••••••••"
              required
            />
            <button class="central-plain-button" type="button" @click="showPassword = !showPassword">
              {{ showPassword ? "مخفی" : "نمایش" }}
            </button>
          </div>
        </label>

        <p v-if="localError || error" class="central-error">{{ localError || error }}</p>
        <button class="central-button" type="submit" :disabled="loading">
          {{ loading ? "در حال ورود..." : "ورود به پنل" }}
        </button>

        <button class="central-quick-login" type="button" :disabled="loading" @click="loginAsCentralAdmin">
          ورود سریع مدیرکل
          <span>admin@central.local / 12345678</span>
        </button>
      </div>

      <div class="central-login-footer">
        دسترسی این صفحه ویژه مدیر کل سیستم است
      </div>
    </form>
  </main>
</template>

<script setup>
import { reactive, ref } from "vue";

defineProps({
  loading: { type: Boolean, default: false },
  error: { type: String, default: "" },
});

const emit = defineEmits(["login"]);

const form = reactive({
  email: "admin@central.local",
  password: "",
  remember: true,
});
const showPassword = ref(false);
const localError = ref("");

function submit() {
  localError.value = "";
  emit("login", { ...form });
}

function loginAsCentralAdmin() {
  form.email = "admin@central.local";
  form.password = "12345678";
  form.remember = true;
  submit();
}
</script>
