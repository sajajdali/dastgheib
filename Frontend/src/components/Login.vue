<template>
  <main class="login-page" dir="rtl">
    <div class="login-decoration decoration-one"></div>
    <div class="login-decoration decoration-two"></div>

    <section class="login-card">
      <div class="brand-mark">د</div>
      <div class="login-heading">
        <span class="eyebrow">سامانه مدیریت کلینیک</span>
        <h1>ورود به سیستم crm مدیریت کلینیک های زیبایی</h1>
        <p>برای دسترسی به پنل، اطلاعات حساب کاربری خود را وارد کنید.</p>
      </div>

      <form @submit.prevent="submitLogin">
        <label class="login-label" for="login">نام کاربری، موبایل یا ایمیل</label>
        <div class="input-wrap">
          <span class="input-icon">●</span>
          <input
            id="login"
            ref="loginInput"
            v-model.trim="form.login"
            type="text"
            autocomplete="username"
            placeholder="مثلاً 09122978167"
            required
          />
        </div>

        <label class="login-label" for="password">رمز عبور</label>
        <div class="input-wrap">
          <span class="input-icon lock-icon">◆</span>
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            placeholder="رمز عبور"
            required
          />
          <button class="password-toggle" type="button" @click="showPassword = !showPassword">
            {{ showPassword ? 'پنهان' : 'نمایش' }}
          </button>
        </div>

        <div class="login-options">
          <label class="remember-option">
            <input v-model="form.remember" type="checkbox" />
            <span>مرا به خاطر بسپار</span>
          </label>
        </div>

        <div v-if="error" class="login-error" role="alert">{{ error }}</div>

        <button class="login-button" type="submit" :disabled="loading">
          <span v-if="loading" class="button-spinner"></span>
          {{ loading ? 'در حال ورود...' : 'ورود به سامانه' }}
        </button>

        <button class="quick-login-button" type="button" :disabled="loading" @click="loginAsSuperAdmin">
          ورود سریع مدیرکل
          <span class="quick-login-credentials">09122978167 / 1234</span>
        </button>
      </form>

      <div class="login-footer">دسترسی شما بر اساس نقش سازمانی کنترل می‌شود.</div>
    </section>
  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import axios from "axios";

const emit = defineEmits(["authenticated"]);
const BACKEND_URL = "";

const superAdminCredentials = {
  login: "09122978167",
  password: "1234",
};

const form = reactive({ login: "", password: "", remember: false });
const loading = ref(false);
const error = ref("");
const showPassword = ref(false);
const loginInput = ref(null);

async function submitLogin() {
  loading.value = true;
  error.value = "";

  try {
    await axios.get(`${BACKEND_URL}/csrf-cookie`);
    const { data } = await axios.post(`${BACKEND_URL}/login`, form);
    form.password = "";
    emit("authenticated", data.user);
  } catch (requestError) {
    const validationErrors = requestError.response?.data?.errors;
    error.value = validationErrors
      ? Object.values(validationErrors).flat()[0]
      : requestError.response?.data?.message || "ارتباط با سرور برقرار نشد.";
  } finally {
    loading.value = false;
  }
}

function loginAsSuperAdmin() {
  form.login = superAdminCredentials.login;
  form.password = superAdminCredentials.password;
  form.remember = true;
  submitLogin();
}

onMounted(() => loginInput.value?.focus());
</script>

<style scoped>
.login-page {
  position: relative;
  min-height: 100vh;
  display: grid;
  place-items: center;
  overflow: hidden;
  padding: 24px;
  font-family: "Vazir", sans-serif;
  background:
    radial-gradient(circle at 15% 20%, rgba(59, 130, 246, .16), transparent 35%),
    radial-gradient(circle at 85% 80%, rgba(14, 165, 233, .13), transparent 36%),
    linear-gradient(145deg, #f6f9ff 0%, #eef4fb 100%);
}

.login-decoration { position:absolute; border-radius:50%; filter:blur(1px); pointer-events:none; }
.decoration-one { width:260px; height:260px; top:-110px; right:-70px; background:rgba(37,99,235,.09); }
.decoration-two { width:330px; height:330px; bottom:-180px; left:-100px; background:rgba(14,165,233,.08); }

.login-card {
  position: relative;
  z-index: 1;
  width: min(100%, 430px);
  padding: 34px;
  border: 1px solid rgba(255,255,255,.8);
  border-radius: 26px;
  background: rgba(255,255,255,.92);
  box-shadow: 0 25px 70px rgba(30,64,175,.12);
  backdrop-filter: blur(18px);
}

.brand-mark {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  margin: 0 auto 18px;
  border-radius: 18px;
  color: #fff;
  background: linear-gradient(145deg, #2563eb, #0ea5e9);
  box-shadow: 0 12px 25px rgba(37,99,235,.25);
  font-size: 28px;
  font-weight: 900;
}

.login-heading { text-align:center; margin-bottom:26px; }
.eyebrow { display:block; margin-bottom:6px; color:#2563eb; font-size:12px; font-weight:800; }
.login-heading h1 { margin:0 0 8px; color:#172033; font-size:25px; }
.login-heading p { margin:0; color:#7a8498; font-size:13px; line-height:1.9; }
.login-label { display:block; margin:15px 0 7px; color:#374151; font-size:13px; font-weight:800; }

.input-wrap { position:relative; }
.input-wrap input {
  width:100%;
  height:48px;
  padding:0 42px 0 62px;
  border:1px solid #dbe3ef;
  border-radius:13px;
  outline:none;
  background:#fbfdff;
  color:#1f2937;
  font-family:inherit;
  transition:.2s;
}
.input-wrap input:focus { border-color:#3b82f6; background:#fff; box-shadow:0 0 0 4px rgba(59,130,246,.1); }
.input-icon { position:absolute; z-index:1; top:50%; right:15px; transform:translateY(-50%); color:#60a5fa; font-size:10px; }
.lock-icon { transform:translateY(-50%) rotate(45deg); }
.password-toggle { position:absolute; top:50%; left:12px; transform:translateY(-50%); border:0; background:transparent; color:#2563eb; font-family:inherit; font-size:11px; cursor:pointer; }
.login-options { display:flex; justify-content:space-between; margin:15px 0; }
.remember-option { display:flex; align-items:center; gap:7px; color:#64748b; font-size:12px; cursor:pointer; }
.remember-option input { accent-color:#2563eb; }
.login-error { margin:0 0 13px; padding:10px 12px; border:1px solid #fecaca; border-radius:10px; background:#fff1f2; color:#be123c; font-size:12px; }

.login-button {
  width:100%;
  height:49px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:9px;
  border:0;
  border-radius:13px;
  color:#fff;
  background:linear-gradient(135deg,#2563eb,#0ea5e9);
  box-shadow:0 12px 24px rgba(37,99,235,.22);
  font-family:inherit;
  font-weight:800;
  cursor:pointer;
}
.login-button:disabled { opacity:.7; cursor:wait; }
.button-spinner { width:18px; height:18px; border:2px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; }
.quick-login-button {
  width:100%;
  min-height:46px;
  margin-top:12px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  flex-wrap:wrap;
  border:1px solid #dbeafe;
  border-radius:13px;
  color:#1d4ed8;
  background:#eff6ff;
  font-family:inherit;
  font-size:12px;
  font-weight:900;
  cursor:pointer;
  transition:.2s;
}
.quick-login-button:hover { border-color:#93c5fd; background:#dbeafe; }
.quick-login-button:disabled { opacity:.7; cursor:wait; }
.quick-login-credentials {
  direction:ltr;
  color:#475569;
  font-size:12px;
  font-weight:800;
}
.login-footer { margin-top:22px; padding-top:17px; border-top:1px solid #edf1f7; color:#94a3b8; text-align:center; font-size:11px; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width:500px) { .login-page{padding:14px}.login-card{padding:25px 20px;border-radius:20px} }
</style>
