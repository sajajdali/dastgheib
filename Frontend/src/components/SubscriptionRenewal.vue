<template>
  <section :class="['subscription-shell', { fullscreen }]" dir="rtl">
    <div class="subscription-card">
      <header>
        <div>
          <small>{{ subscription.is_expired ? 'اشتراک منقضی شده' : 'مدیریت اشتراک' }}</small>
          <h1>{{ subscription.is_expired ? 'اشتراک این مجموعه به پایان رسیده است' : 'تمدید اشتراک سیستم' }}</h1>
          <p v-if="subscription.is_expired">اطلاعات مجموعه محفوظ است؛ برای ورود دوباره به محیط کاری، اشتراک را تمدید کنید.</p>
          <p v-else>بسته زمانی و ظرفیت کاربران را انتخاب کنید. مبلغ نهایی روی سرور محاسبه می‌شود.</p>
        </div>
        <button v-if="closable" class="subscription-close" type="button" aria-label="بستن" @click="$emit('close')">×</button>
      </header>

      <div v-if="loading" class="subscription-state">در حال دریافت اطلاعات اشتراک…</div>
      <template v-else>
        <div class="subscription-current">
          <article><span>پلن فعلی</span><strong>{{ subscription.plan_name || 'بدون پلن' }}</strong></article>
          <article><span>تاریخ پایان</span><strong>{{ formatDate(subscription.expires_at) }}</strong></article>
          <article><span>ظرفیت کاربران</span><strong>{{ number(subscription.licensed_users) }} کاربر</strong></article>
          <article><span>کاربران فعال</span><strong>{{ number(subscription.actual_users) }} کاربر</strong></article>
        </div>

        <div v-if="subscription.pending_renewal" class="subscription-pending">
          درخواست {{ subscription.pending_renewal.plan_name }} برای {{ number(subscription.pending_renewal.licensed_users) }} کاربر
          با مبلغ {{ money(subscription.pending_renewal.price) }} ثبت شده و منتظر تأیید پرداخت است.
        </div>

        <div v-if="manager" class="subscription-form">
          <label>
            <span>بسته زمانی</span>
            <select v-model="selectedPlanId">
              <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">
                {{ plan.name }} — {{ number(plan.duration_days) }} روز
              </option>
            </select>
          </label>
          <label>
            <span>تعداد کاربران مجاز</span>
            <input v-model.number="licensedUsers" type="number" :min="minimumUsers" max="100000">
            <small>کمتر از {{ number(minimumUsers) }} کاربر فعال فعلی قابل انتخاب نیست.</small>
          </label>

          <div class="subscription-price">
            <span>مبلغ تمدید</span>
            <strong>{{ money(quotedPrice) }}</strong>
            <small>قیمت پایه برای {{ number(userPricing.included_users || 1) }} کاربر + {{ money(userPricing.extra_user_price || 0) }} برای هر کاربر اضافه</small>
          </div>

          <details v-if="terms.content" class="subscription-terms">
            <summary>مشاهده قوانین و مقررات تمدید</summary>
            <p>{{ terms.content }}</p>
          </details>
          <label class="subscription-accept">
            <input v-model="accepted" type="checkbox">
            <span>قوانین و مبلغ محاسبه‌شده را مطالعه و تأیید می‌کنم.</span>
          </label>

          <p v-if="message" :class="['subscription-message', { error }]">{{ message }}</p>
          <button class="subscription-renew" type="button" :disabled="saving || !accepted || !selectedPlan || Boolean(subscription.pending_renewal)" @click="renew">
            {{ saving ? 'در حال ثبت تمدید…' : (quotedPrice > 0 ? 'ثبت درخواست تمدید' : 'فعال‌سازی تمدید رایگان') }}
          </button>
        </div>

        <div v-else class="subscription-contact">
          برای تمدید اشتراک با مدیر سیستم مجموعه تماس بگیرید.
        </div>
      </template>

      <footer v-if="fullscreen">
        <button type="button" @click="$emit('logout')">خروج از حساب</button>
      </footer>
    </div>
  </section>
</template>

<script>
import axios from 'axios'

export default {
  name: 'SubscriptionRenewal',
  props: {
    initialSubscription: { type: Object, default: () => ({}) },
    manager: { type: Boolean, default: false },
    fullscreen: { type: Boolean, default: false },
    closable: { type: Boolean, default: false },
  },
  emits: ['renewed', 'logout', 'close'],
  data() {
    return {
      loading: true,
      saving: false,
      subscription: { ...(this.initialSubscription || {}) },
      plans: [],
      userPricing: { included_users: 1, extra_user_price: 0 },
      terms: { id: null, content: '' },
      selectedPlanId: '',
      licensedUsers: 1,
      accepted: false,
      message: '',
      error: false,
      paymentReference: '',
    }
  },
  computed: {
    minimumUsers() {
      return Math.max(1, Number(this.subscription.actual_users || 1))
    },
    selectedPlan() {
      return this.plans.find(plan => String(plan.id) === String(this.selectedPlanId)) || null
    },
    quotedPrice() {
      if (!this.selectedPlan) return 0
      const users = Math.max(this.minimumUsers, Number(this.licensedUsers || 1))
      const included = Math.max(1, Number(this.userPricing.included_users || 1))
      return Number(this.selectedPlan.base_price || 0)
        + Math.max(0, users - included) * Number(this.userPricing.extra_user_price || 0)
    },
  },
  mounted() {
    this.load()
  },
  methods: {
    number(value) {
      return Number(value || 0).toLocaleString('fa-IR')
    },
    money(value) {
      const amount = Number(value || 0)
      return amount ? `${amount.toLocaleString('fa-IR')} تومان` : 'رایگان'
    },
    formatDate(value) {
      if (!value) return 'ثبت نشده'
      const normalized = String(value).includes('T') ? value : String(value).replace(' ', 'T')
      return new Date(normalized).toLocaleDateString('fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric' })
    },
    async load() {
      this.loading = true
      this.message = ''
      try {
        const [subscriptionResponse, termsResponse] = await Promise.all([
          axios.get('/api/subscription'),
          axios.get('/api/store/terms'),
        ])
        this.subscription = subscriptionResponse.data.subscription || this.subscription
        this.plans = Array.isArray(this.subscription.plans) ? this.subscription.plans : []
        this.userPricing = this.subscription.user_pricing || this.userPricing
        this.terms = termsResponse.data.terms || this.terms
        this.licensedUsers = Math.max(this.minimumUsers, Number(this.subscription.licensed_users || 1))
        this.selectedPlanId = String(this.subscription.plan_id || this.plans[0]?.id || '')
      } catch (error) {
        this.error = true
        this.message = error.response?.data?.message || 'دریافت اطلاعات اشتراک انجام نشد.'
      } finally {
        this.loading = false
      }
    },
    async renew() {
      if (!this.manager || !this.selectedPlan || !this.accepted) return
      this.saving = true
      this.error = false
      this.message = ''
      this.paymentReference ||= globalThis.crypto?.randomUUID?.()
        || `renew-${Date.now()}-${Math.random().toString(36).slice(2)}`
      try {
        const { data } = await axios.post('/api/subscription/renew', {
          plan_id: Number(this.selectedPlan.id),
          licensed_users: Math.max(this.minimumUsers, Number(this.licensedUsers || 1)),
          term_id: this.terms.id,
          accepted: true,
          payment_reference: this.paymentReference,
        })
        this.subscription = data.subscription || this.subscription
        this.message = data.message || 'اشتراک تمدید شد.'
        this.accepted = false
        if (data.activated) this.$emit('renewed', this.subscription)
        else this.paymentReference = ''
      } catch (error) {
        this.error = true
        this.message = error.response?.data?.message || 'تمدید اشتراک انجام نشد.'
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.subscription-shell{position:fixed;inset:0;z-index:2147483645;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.62);backdrop-filter:blur(7px);font-family:Tahoma,sans-serif}.subscription-shell.fullscreen{background:radial-gradient(circle at 80% 10%,#dbeafe,transparent 35%),linear-gradient(135deg,#f8fafc,#e2e8f0)}.subscription-card{width:min(760px,96vw);max-height:94vh;overflow:auto;border:1px solid #dbeafe;border-radius:24px;background:#fff;box-shadow:0 30px 90px rgba(15,23,42,.25)}.subscription-card>header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:24px;background:linear-gradient(135deg,#eff6ff,#f8fafc);border-bottom:1px solid #dbeafe}.subscription-card header small{color:#dc2626;font-size:11px;font-weight:1000}.subscription-card h1{margin:5px 0;color:#0f172a;font-size:24px}.subscription-card header p{margin:0;color:#64748b;line-height:1.9}.subscription-close{width:38px;height:38px;border:0;border-radius:11px;background:#fff;color:#64748b;font-size:25px;cursor:pointer}.subscription-state,.subscription-contact{margin:20px;padding:24px;border-radius:14px;background:#f8fafc;color:#475569;text-align:center;font-weight:900}.subscription-current{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:20px}.subscription-current article{padding:13px;border:1px solid #e2e8f0;border-radius:13px;background:#f8fafc}.subscription-current span,.subscription-current strong{display:block}.subscription-current span{margin-bottom:6px;color:#64748b;font-size:10px;font-weight:900}.subscription-current strong{color:#1e293b;font-size:13px}.subscription-pending{margin:0 20px 18px;padding:13px;border:1px solid #fde68a;border-radius:12px;background:#fffbeb;color:#92400e;font-size:12px;font-weight:900;line-height:1.9}.subscription-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:0 20px 22px}.subscription-form>label:not(.subscription-accept){display:grid;gap:7px;color:#334155;font-size:12px;font-weight:900}.subscription-form select,.subscription-form input[type=number]{height:44px;padding:0 11px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-family:inherit}.subscription-form label small{color:#94a3b8;font-size:10px}.subscription-price,.subscription-terms,.subscription-accept,.subscription-message,.subscription-renew{grid-column:1/-1}.subscription-price{display:grid;grid-template-columns:1fr auto;gap:6px;padding:15px;border:1px solid #bbf7d0;border-radius:13px;background:#f0fdf4}.subscription-price span{color:#166534;font-size:12px;font-weight:900}.subscription-price strong{color:#15803d;font-size:20px}.subscription-price small{grid-column:1/-1;color:#4b7b5d}.subscription-terms{padding:12px;border:1px solid #e2e8f0;border-radius:12px}.subscription-terms summary{cursor:pointer;color:#1d4ed8;font-weight:900}.subscription-terms p{max-height:150px;overflow:auto;white-space:pre-line;color:#475569;font-size:11px;line-height:2}.subscription-accept{display:flex!important;align-items:flex-start;gap:9px;padding:12px;border-radius:11px;background:#eff6ff;color:#1e40af!important}.subscription-accept input{width:18px;height:18px;accent-color:#2563eb}.subscription-message{margin:0;padding:10px;border-radius:10px;background:#dcfce7;color:#15803d;font-size:12px;font-weight:900}.subscription-message.error{background:#fee2e2;color:#b91c1c}.subscription-renew{height:48px;border:0;border-radius:11px;background:#2563eb;color:#fff;font-family:inherit;font-weight:1000;cursor:pointer}.subscription-renew:disabled{opacity:.5;cursor:not-allowed}.subscription-card>footer{padding:14px 20px;border-top:1px solid #e2e8f0;text-align:left}.subscription-card>footer button{padding:9px 16px;border:0;border-radius:9px;background:#e2e8f0;color:#334155;font-family:inherit;font-weight:900;cursor:pointer}@media(max-width:700px){.subscription-current,.subscription-form{grid-template-columns:1fr 1fr}.subscription-current{padding:14px}.subscription-form{padding:0 14px 16px}.subscription-card h1{font-size:19px}}@media(max-width:480px){.subscription-current,.subscription-form{grid-template-columns:1fr}.subscription-price,.subscription-terms,.subscription-accept,.subscription-message,.subscription-renew{grid-column:auto}}
</style>
