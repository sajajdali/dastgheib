<template>
  <main class="store-page" dir="rtl">
    <section v-if="step === 'terms'" class="invoice-page">
      <section class="invoice-document terms-document">
        <header class="invoice-head">
          <div class="invoice-brand">
            <span class="invoice-logo">ق</span>
            <div>
              <small>مرحله قبل از پرداخت</small>
              <strong>قوانین و مقررات</strong>
            </div>
          </div>
          <div class="invoice-title-block">
            <span>تایید خریدار</span>
            <h1>مطالعه و پذیرش قوانین خرید</h1>
          </div>
        </header>

        <section class="terms-body">
          <div class="terms-text">
            <div v-if="termsLoading" class="cart-empty">در حال دریافت قوانین...</div>
            <p v-else>{{ storeTerms.content }}</p>
          </div>

          <aside class="invoice-side">
            <div class="invoice-summary">
              <div><span>تعداد اقلام</span><strong>{{ selectedItems.length.toLocaleString("fa-IR") }}</strong></div>
              <div v-if="discountAmount" class="invoice-discount"><span>تخفیف</span><strong>{{ formatPrice(discountAmount) }}</strong></div>
              <div class="invoice-total"><span>جمع قابل پرداخت</span><strong>{{ formatPrice(payableTotal) }}</strong></div>
            </div>

            <label class="terms-confirm">
              <input v-model="termsAccepted" type="checkbox">
              <span>قوانین و مقررات را خوانده‌ام و تایید می‌کنم.</span>
            </label>

            <p v-if="paymentMessage" :class="['checkout-message', { error: paymentError }]">{{ paymentMessage }}</p>

            <button
              class="checkout-btn"
              type="button"
              :disabled="paying || termsLoading || !termsAccepted || !selectedItems.length"
              @click="payInvoice"
            >
              {{ paying ? "در حال ثبت..." : "پرداخت و فعال‌سازی" }}
            </button>

            <button type="button" class="details-btn invoice-back" @click="step = 'invoice'">بازگشت به پیش‌فاکتور</button>
          </aside>
        </section>
      </section>
    </section>

    <section v-else-if="step === 'invoice'" class="invoice-page">
      <section class="invoice-document">
        <header class="invoice-head">
          <div class="invoice-brand">
            <span class="invoice-logo">ک</span>
            <div>
              <small>فروشگاه امکانات</small>
              <strong>کلینیک‌یار</strong>
            </div>
          </div>
          <div class="invoice-title-block">
            <span>پیش‌فاکتور</span>
            <h1>خرید امکانات سیستم</h1>
          </div>
        </header>

        <div class="invoice-meta-grid">
          <div><span>شماره پیش‌فاکتور</span><strong>{{ invoiceNumber }}</strong></div>
          <div><span>تاریخ صدور</span><strong>{{ todayLabel }}</strong></div>
          <div><span>وضعیت</span><strong class="invoice-status">در انتظار پرداخت</strong></div>
        </div>

        <section class="invoice-body">
          <div class="invoice-items">
            <div class="invoice-section-title">اقلام پیش‌فاکتور</div>
            <article v-for="item in selectedItems" :key="item.key" class="invoice-item">
              <div class="invoice-item-icon" :style="{ '--accent': item.color }">{{ item.icon }}</div>
              <div>
                <strong>{{ item.title }}</strong>
                <small>{{ item.categoryLabel }} · {{ item.billing }}</small>
              </div>
              <span>{{ formatPrice(item.price) }}</span>
            </article>
          </div>

          <aside class="invoice-side">
            <div class="invoice-summary">
              <div><span>تعداد اقلام</span><strong>{{ selectedItems.length.toLocaleString("fa-IR") }}</strong></div>
              <div v-if="discountAmount" class="invoice-discount"><span>تخفیف</span><strong>{{ formatPrice(discountAmount) }}</strong></div>
              <div class="invoice-total"><span>جمع کل قابل پرداخت</span><strong>{{ formatPrice(payableTotal) }}</strong></div>
            </div>

            <form class="discount-box" @submit.prevent="applyDiscount">
              <label>
                کد تخفیف
                <input v-model.trim="discountCode" type="text" placeholder="مثلا OFF10">
              </label>
              <button type="submit">اعمال کد</button>
              <p v-if="discountMessage" :class="{ error: discountError }">{{ discountMessage }}</p>
            </form>

            <section class="payment-card">
              <div>
                <strong>پرداخت آنلاین</strong>
                <span>پس از اتصال درگاه، پرداخت این پیش‌فاکتور از همین بخش انجام می‌شود.</span>
              </div>
              <button class="checkout-btn" type="button" :disabled="paying || !selectedItems.length" @click="payInvoice">
                {{ paying ? "در حال انتقال..." : "خرید و سفارش" }}
              </button>
            </section>

            <button type="button" class="details-btn invoice-back" @click="step = 'catalog'">بازگشت به فروشگاه</button>
          </aside>
        </section>

        <section class="invoice-note">
          <strong>توضیح</strong>
          <span>بعد از پرداخت موفق، امکانات خریداری‌شده برای همین سایت فعال می‌شوند و در فروشگاه با وضعیت خریداری شده نمایش داده خواهند شد.</span>
        </section>
      </section>
    </section>

    <template v-else>
      <section class="store-hero">
        <div>
          <span>فروشگاه ماژول‌ها</span>
          <h1>افزونه‌های کلینیک</h1>
          <p>ماژول‌های مورد نیازتان را انتخاب کنید، جزئیات را ببینید و چند مورد را همزمان به سبد خرید اضافه کنید.</p>
        </div>
        <aside>
          <strong>{{ selectedItems.length.toLocaleString("fa-IR") }}</strong>
          <small>ماژول انتخاب شده</small>
        </aside>
      </section>

      <section class="store-layout">
        <div class="store-catalog">
          <div class="store-toolbar">
            <div class="store-tabs">
              <button
                v-for="category in categories"
                :key="category.value"
                type="button"
                :class="{ active: activeCategory === category.value }"
                @click="activeCategory = category.value"
              >
                {{ category.label }}
              </button>
            </div>
            <label class="store-search">
              <input v-model.trim="search" type="search" placeholder="جستجوی ماژول">
            </label>
          </div>

          <div class="plugin-grid">
            <article
              v-for="item in filteredItems"
              :key="item.key"
              class="plugin-card"
              :class="{ selected: isSelected(item.key) }"
            >
              <div class="plugin-main">
                <div v-if="item.image" class="plugin-image">
                  <img :src="item.image" :alt="item.title">
                </div>
                <div v-else class="plugin-image plugin-image-fallback" :style="{ '--accent': item.color }">
                  <span>{{ item.icon }}</span>
                </div>
                <div class="plugin-content">
                  <header>
                    <div>
                      <h3>{{ item.title }}</h3>
                      <small>{{ item.categoryLabel }}</small>
                    </div>
                    <button type="button" class="details-btn" @click="openDetails(item)">جزئیات</button>
                  </header>
                  <p>{{ item.description }}</p>
                </div>
              </div>

              <div class="plugin-actions">
                <div class="plugin-meta">
                  <strong>{{ formatPrice(selectedPeriod(item).price) }}</strong>
                  <span>{{ isPurchased(item) ? "خریداری شده و فعال است" : selectedPeriod(item).label }}</span>
                </div>

                <div v-if="!isPurchased(item) && item.periods?.length > 1" class="period-options">
                  <button
                    v-for="period in item.periods"
                    :key="`${item.key}-${period.key}`"
                    type="button"
                    :class="{ active: selectedPeriodKey(item) === period.key }"
                    @click.stop="selectPeriod(item, period.key)"
                  >
                    {{ period.label }}
                  </button>
                </div>

                <footer>
                  <span v-if="isPurchased(item)" class="purchased-badge">خریداری شده و فعال است</span>
                  <button
                    v-else
                    type="button"
                    class="select-btn"
                    :class="{ selected: isSelected(item.key) }"
                    @click="toggleItem(item)"
                  >
                    {{ isSelected(item.key) ? "حذف از سبد" : "افزودن به سبد" }}
                  </button>
                </footer>
              </div>
            </article>
          </div>

          <section v-if="filteredComingSoonItems.length" class="coming-soon-section">
            <header>
              <div>
                <span>درحال پیاده‌سازی</span>
                <h2>ماژول‌های درحال پیاده‌سازی</h2>
              </div>
            </header>
            <div class="plugin-grid coming-soon-grid">
              <article
                v-for="item in filteredComingSoonItems"
                :key="item.key"
                class="plugin-card coming-soon-card"
              >
                <div class="plugin-main">
                  <div v-if="item.image" class="plugin-image">
                    <img :src="item.image" :alt="item.title">
                  </div>
                  <div v-else class="plugin-image plugin-image-fallback" :style="{ '--accent': item.color }">
                    <span>{{ item.icon }}</span>
                  </div>
                  <div class="plugin-content">
                    <header>
                      <div>
                        <h3>{{ item.title }}</h3>
                        <small>{{ item.categoryLabel }}</small>
                      </div>
                      <button type="button" class="details-btn" @click="openDetails(item)">جزئیات</button>
                    </header>
                    <p>{{ item.description }}</p>
                  </div>
                </div>
                <div class="plugin-actions">
                  <div class="plugin-meta">
                    <strong>{{ formatPrice(selectedPeriod(item).price) }}</strong>
                    <span>{{ selectedPeriod(item).label }}</span>
                  </div>
                  <footer>
                    <span class="coming-soon-badge">فعلا قابل خرید نیست</span>
                  </footer>
                </div>
              </article>
            </div>
          </section>
        </div>

        <aside class="cart-panel">
          <header>
            <span>سبد خرید</span>
            <button v-if="selectedItems.length" type="button" @click="clearCart">پاک کردن</button>
          </header>

          <div v-if="selectedItems.length" class="cart-list">
            <article v-for="item in selectedItems" :key="item.key">
              <div>
                <strong>{{ item.title }}</strong>
                <small>{{ formatPrice(item.price) }}</small>
              </div>
              <button type="button" aria-label="حذف" @click="toggleItem(item)">×</button>
            </article>
          </div>

          <div v-else class="cart-empty">ماژولی انتخاب نشده است.</div>

          <div class="cart-total">
            <span>جمع کل</span>
            <strong>{{ formatPrice(totalPrice) }}</strong>
          </div>

          <button class="checkout-btn" type="button" :disabled="!selectedItems.length" @click="checkout">
            ادامه خرید
          </button>
        </aside>
      </section>

      <div v-if="detailsItem" class="store-modal" @click.self="detailsItem = null">
        <section>
          <button class="modal-close" type="button" @click="detailsItem = null">×</button>
          <header class="modal-plugin-head">
            <div v-if="detailsItem.image" class="modal-image">
              <img :src="detailsItem.image" :alt="detailsItem.title">
            </div>
            <div v-else class="plugin-icon modal-icon" :style="{ '--accent': detailsItem.color }">
              <span>{{ detailsItem.icon }}</span>
            </div>
            <div>
              <small>{{ detailsItem.categoryLabel }}</small>
              <h2>{{ detailsItem.title }}</h2>
            </div>
          </header>
          <p class="modal-description">{{ detailsItem.longDescription }}</p>
          <div class="modal-actions">
            <div class="modal-price">
              <span>قیمت</span>
              <strong>{{ formatPrice(detailsItem.price) }}</strong>
            </div>
            <button type="button" class="select-btn" :disabled="isPurchased(detailsItem) || detailsItem.comingSoon" @click="toggleItem(detailsItem)">
              {{ detailsItem.comingSoon ? "به زودی" : (isPurchased(detailsItem) ? "خریداری شده و فعال است" : (isSelected(detailsItem.key) ? "حذف از سبد" : "افزودن به سبد")) }}
            </button>
          </div>
        </section>
      </div>
    </template>
  </main>
</template>

<script>
import { STORE_MODULES } from "../central/data/store-modules";

const CART_KEY = "clinic_store_cart_v1"
const PURCHASED_KEY = "clinic_store_purchased_v1"

export default {
  name: "Store",
  props: {
    enabledFeatures: {
      type: Array,
      default: null
    },
    initialModuleKey: {
      type: String,
      default: ""
    }
  },
  data() {
    return {
      activeCategory: "all",
      step: "catalog",
      paying: false,
      search: "",
      selectedKeys: [],
      selectedPeriods: {},
      detailsItem: null,
      discountCode: "",
      discountMessage: "",
      discountError: false,
      paymentMessage: "",
      paymentError: false,
      appliedDiscount: null,
      termsLoading: false,
      termsAccepted: false,
      storeTerms: {
        id: null,
        content: ""
      },
      localPurchasedKeys: [],
      categories: [
        { value: "all", label: "همه" },
        { value: "operations", label: "عملیات" },
        { value: "marketing", label: "بازاریابی" },
        { value: "finance", label: "مالی" },
        { value: "insight", label: "تحلیل" }
      ],
      items: STORE_MODULES
    }
  },
  computed: {
    filteredItems() {
      const query = this.search.toLowerCase()
      return this.items.filter(item => {
        if (item.comingSoon) return false
        const categoryOk = this.activeCategory === "all" || item.category === this.activeCategory
        const searchOk = !query || `${item.title} ${item.description} ${item.categoryLabel}`.toLowerCase().includes(query)
        return categoryOk && searchOk
      })
    },
    filteredComingSoonItems() {
      const query = this.search.toLowerCase()
      return this.items.filter(item => {
        if (!item.comingSoon) return false
        const categoryOk = this.activeCategory === "all" || item.category === this.activeCategory
        const searchOk = !query || `${item.title} ${item.description} ${item.categoryLabel}`.toLowerCase().includes(query)
        return categoryOk && searchOk
      })
    },
    selectedItems() {
      return this.items
        .filter(item => this.selectedKeys.includes(item.key) && !this.isPurchased(item) && !item.comingSoon)
        .map(item => {
          const period = this.selectedPeriod(item)
          return {
            ...item,
            price: period.price,
            billing: period.label,
            billingPeriod: period.key
          }
        })
    },
    totalPrice() {
      return this.selectedItems.reduce((sum, item) => sum + Number(item.price || 0), 0)
    },
    discountAmount() {
      if (!this.appliedDiscount) return 0
      if (this.appliedDiscount.type === "percent") {
        return Math.floor(this.totalPrice * this.appliedDiscount.value / 100)
      }
      return Math.min(this.totalPrice, this.appliedDiscount.value)
    },
    payableTotal() {
      return Math.max(0, this.totalPrice - this.discountAmount)
    },
    invoiceNumber() {
      return `PF-${new Date().getFullYear()}-${String(this.selectedKeys.length).padStart(2, "0")}${String(this.payableTotal).slice(-4).padStart(4, "0")}`
    },
    todayLabel() {
      return new Intl.DateTimeFormat("fa-IR-u-ca-persian", { year: "numeric", month: "long", day: "numeric" }).format(new Date())
    }
  },
  mounted() {
    try {
      const saved = JSON.parse(localStorage.getItem(CART_KEY) || "[]")
      this.selectedKeys = Array.isArray(saved) ? saved : []
    } catch {
      this.selectedKeys = []
    }
    try {
      const periods = JSON.parse(localStorage.getItem(`${CART_KEY}_periods`) || "{}")
      this.selectedPeriods = periods && typeof periods === "object" && !Array.isArray(periods) ? periods : {}
    } catch {
      this.selectedPeriods = {}
    }
    try {
      const purchased = JSON.parse(localStorage.getItem(PURCHASED_KEY) || "[]")
      this.localPurchasedKeys = Array.isArray(purchased) ? purchased : []
    } catch {
      this.localPurchasedKeys = []
    }
    this.addInitialModuleToCart()
  },
  watch: {
    initialModuleKey() {
      this.addInitialModuleToCart()
    }
  },
  methods: {
    addInitialModuleToCart() {
      const key = String(this.initialModuleKey || "")
      const item = this.items.find(module => module.key === key)
      if (!item || item.comingSoon || this.isPurchased(item) || this.isSelected(key)) return
      this.selectedKeys = [...this.selectedKeys, key]
      this.step = "catalog"
      this.activeCategory = "all"
      this.persistCart()
    },
    selectedPeriodKey(item) {
      return this.selectedPeriods[item.key] || item.periods?.[0]?.key || "one_time"
    },
    selectedPeriod(item) {
      return item.periods?.find(period => period.key === this.selectedPeriodKey(item)) || item.periods?.[0] || { key: "one_time", label: item.billing || "یک‌باره", price: item.price || 0 }
    },
    selectPeriod(item, periodKey) {
      this.selectedPeriods = { ...this.selectedPeriods, [item.key]: periodKey }
      this.persistCart()
    },
    formatPrice(value) {
      if (!Number(value)) return "رایگان"
      return `${Number(value).toLocaleString("fa-IR")} تومان`
    },
    isPurchased(item) {
      const purchased = Array.isArray(this.enabledFeatures) ? this.enabledFeatures : []
      return [...purchased, ...this.localPurchasedKeys].includes(item.key)
    },
    isSelected(key) {
      return this.selectedKeys.includes(key)
    },
    persistCart() {
      localStorage.setItem(CART_KEY, JSON.stringify(this.selectedKeys))
      localStorage.setItem(`${CART_KEY}_periods`, JSON.stringify(this.selectedPeriods))
    },
    toggleItem(item) {
      if (item.comingSoon) return
      if (this.isPurchased(item)) return
      if (this.isSelected(item.key)) {
        this.selectedKeys = this.selectedKeys.filter(key => key !== item.key)
      } else {
        this.selectedKeys = [...this.selectedKeys, item.key]
      }
      this.persistCart()
    },
    clearCart() {
      this.selectedKeys = []
      this.persistCart()
    },
    openDetails(item) {
      this.detailsItem = item
    },
    checkout() {
      if (!this.selectedItems.length) return
      this.step = "invoice"
      this.termsAccepted = false
      this.paymentMessage = ""
      window.scrollTo({ top: 0, behavior: "smooth" })
    },
    async loadStoreTerms() {
      if (this.storeTerms.id || this.termsLoading) return
      this.termsLoading = true
      this.paymentMessage = ""
      this.paymentError = false
      try {
        const response = await fetch("/api/store/terms")
        const data = await response.json()
        if (!response.ok) throw new Error(data.message || "دریافت قوانین انجام نشد.")
        this.storeTerms = data.terms || { id: null, content: "" }
      } catch (error) {
        this.paymentError = true
        this.paymentMessage = error.message || "دریافت قوانین انجام نشد."
      } finally {
        this.termsLoading = false
      }
    },
    async payInvoice() {
      if (!this.selectedItems.length) return
      if (this.step === "invoice") {
        this.step = "terms"
        this.termsAccepted = false
        window.scrollTo({ top: 0, behavior: "smooth" })
        await this.loadStoreTerms()
        return
      }
      if (!this.termsAccepted || !this.storeTerms.id) return
      this.paying = true
      this.paymentMessage = ""
      this.paymentError = false
      try {
        const payload = {
          term_id: this.storeTerms.id,
          accepted: true,
          items: this.selectedItems.map(item => ({ key: item.key, title: item.title, price: Number(item.price || 0), billing_period: item.billingPeriod })),
          subtotal: this.totalPrice,
          discount_amount: this.discountAmount,
          payable_total: this.payableTotal
        }
        const response = await fetch("/api/store/checkout", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        })
        const data = await response.json()
        if (!response.ok) throw new Error(data.message || "ثبت تایید قوانین انجام نشد.")
        this.localPurchasedKeys = Array.isArray(data.enabled_features) ? data.enabled_features : [...this.localPurchasedKeys, ...this.selectedKeys]
        localStorage.setItem(PURCHASED_KEY, JSON.stringify(this.localPurchasedKeys))
        this.clearCart()
        this.paymentMessage = data.message || "خرید ثبت و فعال شد."
        alert(`خرید ثبت شد.\nزمان تایید: ${data.acceptance?.accepted_at || "-"}`)
        this.step = "catalog"
      } catch (error) {
        this.paymentError = true
        this.paymentMessage = error.message || "ثبت تایید قوانین انجام نشد."
      } finally {
        this.paying = false
      }
    },
    applyDiscount() {
      const code = String(this.discountCode || "").trim().toUpperCase()
      const discounts = {
        OFF10: { type: "percent", value: 10, label: "۱۰٪ تخفیف" },
        TAKANDAM: { type: "percent", value: 15, label: "۱۵٪ تخفیف" },
        WELCOME: { type: "fixed", value: 500000, label: "۵۰۰٬۰۰۰ تومان تخفیف" }
      }

      if (!code) {
        this.appliedDiscount = null
        this.discountError = true
        this.discountMessage = "کد تخفیف را وارد کنید."
        return
      }

      if (!discounts[code]) {
        this.appliedDiscount = null
        this.discountError = true
        this.discountMessage = "کد تخفیف معتبر نیست."
        return
      }

      this.appliedDiscount = discounts[code]
      this.discountError = false
      this.discountMessage = `${discounts[code].label} اعمال شد.`
    }
  }
}
</script>

<style scoped>
.store-page{min-height:100vh;display:grid;gap:18px;padding:22px;background:#f6f8fb;color:#0f172a;font-family:"IranSans",Tahoma,sans-serif}.invoice-page{width:min(1040px,100%);display:grid;gap:16px;margin:0 auto}.invoice-document,.payment-card,.invoice-note{border:1px solid #e2e8f0;border-radius:8px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.06)}.invoice-document{overflow:hidden}.invoice-head{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:28px 30px;background:linear-gradient(135deg,#f8fbff,#eef6ff);border-bottom:1px solid #dbeafe}.invoice-brand,.invoice-title-block,.invoice-meta-grid div,.invoice-summary div,.payment-card div,.invoice-note{display:grid;gap:5px}.invoice-brand{grid-template-columns:auto 1fr;align-items:center}.invoice-logo{width:46px;height:46px;display:grid;place-items:center;border-radius:8px;background:#2563eb;color:#fff;font-size:20px;font-weight:1000}.invoice-brand small,.invoice-title-block span,.invoice-meta-grid span,.invoice-summary span,.invoice-row small,.payment-card span,.invoice-note span{color:#64748b;font-size:12px;font-weight:800}.invoice-brand strong{font-size:17px}.invoice-title-block{text-align:left}.invoice-title-block span{color:#2563eb}.invoice-title-block h1{margin:0;color:#111827;font-size:26px;font-weight:1000}.invoice-meta-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#e2e8f0}.invoice-meta-grid div{padding:18px 22px;background:#fff}.invoice-status{width:max-content;padding:5px 10px;border-radius:999px;background:#fff7ed;color:#c2410c;font-size:12px}.invoice-card{display:grid}.invoice-row{display:grid;grid-template-columns:minmax(0,1.5fr) 120px 180px;align-items:center;gap:18px;padding:18px 30px;border-top:1px solid #eef2f7}.invoice-title{border-top:0;background:#f8fafc;color:#475569;font-size:12px;font-weight:1000}.invoice-row strong{color:#111827;font-size:15px}.invoice-row>strong:last-child{text-align:left}.invoice-footer{display:grid;grid-template-columns:auto minmax(320px,420px);align-items:end;gap:18px;padding:22px 30px 28px;background:#fbfdff;border-top:1px solid #e2e8f0}.invoice-summary{display:grid;gap:10px}.invoice-summary div{grid-template-columns:1fr auto;align-items:center;padding:12px 14px;border-radius:8px;background:#f8fafc}.invoice-total{background:#eff6ff!important;color:#1d4ed8}.invoice-total strong{color:#1d4ed8;font-size:20px}.payment-card{display:grid;grid-template-columns:1fr 190px;align-items:center;gap:18px;padding:20px 24px}.payment-card strong,.invoice-note strong{font-size:16px}.invoice-note{padding:16px 20px}.store-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:24px;border:1px solid #dbeafe;border-radius:8px;background:linear-gradient(135deg,#fff,#eff6ff);box-shadow:0 14px 40px rgba(15,23,42,.08)}.store-hero span{color:#2563eb;font-size:12px;font-weight:900}.store-hero h1{margin:5px 0;font-size:28px}.store-hero p{max-width:620px;margin:0;color:#64748b;line-height:1.9}.store-hero aside{min-width:150px;display:grid;place-items:center;gap:3px;padding:17px;border-radius:8px;background:#0f172a;color:#fff}.store-hero aside strong{font-size:30px}.store-hero aside small{color:#cbd5e1}.store-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}.store-catalog{display:grid;gap:14px}.store-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff}.store-tabs{display:flex;gap:6px;overflow:auto}.store-tabs button{height:36px;padding:0 13px;border:1px solid transparent;border-radius:8px;background:#f8fafc;color:#475569;font-family:inherit;font-weight:900;white-space:nowrap;cursor:pointer}.store-tabs button.active{background:#2563eb;color:#fff}.store-search input{width:210px;height:38px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-family:inherit}.plugin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.plugin-card{min-height:245px;display:flex;flex-direction:column;gap:13px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05);transition:.18s}.plugin-card:hover{border-color:#bfdbfe;transform:translateY(-2px)}.plugin-card.selected{border-color:#60a5fa;background:#f8fbff}.plugin-card header{display:flex;align-items:center;gap:11px}.plugin-icon{width:48px;height:48px;flex:0 0 48px;display:grid;place-items:center;border-radius:8px;background:color-mix(in srgb,var(--accent) 14%,#fff);color:var(--accent);font-weight:1000;box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--accent) 28%,transparent)}.plugin-icon span{font-size:19px}.plugin-card h3{margin:0;color:#111827;font-size:15px}.plugin-card small{color:#64748b;font-size:10px;font-weight:800}.plugin-card p{flex:1;margin:0;color:#475569;font-size:12px;line-height:1.9}.plugin-meta{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:10px;border-radius:8px;background:#f8fafc}.plugin-meta strong{color:#0f172a}.plugin-meta span{color:#94a3b8;font-size:10px;font-weight:900}.plugin-card footer{display:flex;gap:8px}.plugin-card button,.checkout-btn,.cart-panel button,.store-modal button{font-family:inherit;cursor:pointer}.details-btn,.select-btn{height:38px;border-radius:8px;font-weight:900}.details-btn{flex:0 0 120px;border:1px solid #dbeafe;background:#fff;color:#2563eb}.select-btn{flex:1;border:0;background:#2563eb;color:#fff}.select-btn.selected{background:#64748b}.select-btn:disabled{background:#16a34a;cursor:default}.purchased-badge{min-height:38px;display:grid;flex:1;place-items:center;border-radius:8px;background:#dcfce7;color:#15803d;font-size:12px;font-weight:1000;text-align:center}.cart-panel{position:sticky;top:74px;display:grid;gap:13px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.08)}.cart-panel header{display:flex;align-items:center;justify-content:space-between}.cart-panel header span{font-size:16px;font-weight:1000}.cart-panel header button{border:0;background:transparent;color:#dc2626;font-weight:900}.cart-list{display:grid;gap:8px;max-height:330px;overflow:auto}.cart-list article{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.cart-list article div{display:grid;gap:3px}.cart-list strong{font-size:12px}.cart-list small{color:#64748b}.cart-list button{width:28px;height:28px;border:0;border-radius:8px;background:#fee2e2;color:#dc2626;font-size:18px}.cart-empty{padding:28px 10px;border:1px dashed #cbd5e1;border-radius:8px;color:#64748b;text-align:center}.cart-total{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid #e2e8f0}.cart-total span{color:#64748b}.cart-total strong{font-size:18px}.checkout-btn{height:44px;border:0;border-radius:8px;background:#16a34a;color:#fff;font-weight:1000}.checkout-btn:disabled{background:#cbd5e1;cursor:not-allowed}.store-modal{position:fixed;inset:0;z-index:1000002;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58);backdrop-filter:blur(5px)}.store-modal section{position:relative;width:min(480px,96vw);display:grid;gap:12px;padding:24px;border-radius:8px;background:#fff;box-shadow:0 30px 80px rgba(15,23,42,.35)}.modal-close{position:absolute;top:12px;left:12px;width:34px;height:34px;border:0;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:22px}.modal-icon{width:62px;height:62px}.store-modal h2{margin:0;font-size:24px}.store-modal p{margin:0;color:#475569;line-height:2}.modal-price{display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:8px;background:#f8fafc}.modal-price span{color:#64748b}.modal-price strong{font-size:18px}@media(max-width:1100px){.store-layout{grid-template-columns:1fr}.cart-panel{position:static}.plugin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.store-page{padding:14px}.invoice-head,.payment-card,.store-hero,.store-toolbar{align-items:stretch;flex-direction:column}.invoice-meta-grid,.invoice-footer,.payment-card{grid-template-columns:1fr}.invoice-row{grid-template-columns:1fr}.invoice-title{display:none}.invoice-title-block{text-align:right}.store-hero aside{min-width:0}.store-search input{width:100%}.plugin-grid{grid-template-columns:1fr}.plugin-card footer{flex-direction:column}.details-btn{flex:auto}.store-layout{gap:14px}}

.invoice-page {
  align-content: start;
  padding-block: 10px;
}

.plugin-image {
  width: 112px;
  height: 112px;
  flex: 0 0 112px;
  overflow: hidden;
  border-radius: 8px;
  border: 1px solid #dbeafe;
  background: #f8fafc;
}

.plugin-image img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: contain;
  padding: 8px;
}

.plugin-image-fallback {
  display: grid;
  place-items: center;
  background: transparent;
  color: var(--accent);
  box-shadow: none;
}

.plugin-image-fallback span {
  font-size: 42px;
  line-height: 1;
}

.plugin-main {
  min-width: 0;
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.plugin-content {
  min-width: 0;
  flex: 1;
  display: grid;
  gap: 8px;
}

.plugin-content header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.plugin-content .details-btn {
  flex: 0 0 auto;
  width: 94px;
  height: 32px;
  font-size: 11px;
}

.plugin-card {
  gap: 11px;
}

.plugin-card p {
  flex: initial;
}

.plugin-card footer {
  justify-content: flex-end;
}

.plugin-card footer .select-btn,
.plugin-card footer .purchased-badge,
.plugin-card footer .coming-soon-badge {
  flex: 0 1 230px;
}

@media (min-width: 1101px) {
  .plugin-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .plugin-card {
    min-height: 218px;
  }
}

@media (max-width: 680px) {
  .plugin-main {
    flex-direction: column;
  }

  .plugin-image {
    width: 100%;
    height: 150px;
    flex-basis: auto;
  }

  .plugin-content header {
    width: 100%;
  }
}

.modal-image {
  overflow: hidden;
  border-radius: 8px;
  border: 1px solid #dbeafe;
  background: #f8fafc;
}

.modal-image img {
  width: 100%;
  max-height: 220px;
  display: block;
  object-fit: cover;
}

.store-page {
  background: #f0f0f1;
  color: #1d2327;
}

.store-hero {
  border-color: #c3c4c7;
  background: #fff;
  box-shadow: none;
}

.store-layout {
  grid-template-columns: minmax(0, 1fr) 300px;
}

.store-toolbar {
  border-color: #c3c4c7;
  border-radius: 0;
  box-shadow: none;
}

.store-tabs button {
  border-radius: 0;
  background: transparent;
}

.store-tabs button.active {
  background: #2271b1;
}

.store-search input {
  border-radius: 0;
  border-color: #8c8f94;
}

.plugin-grid,
.coming-soon-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  gap: 12px;
}

.plugin-card {
  min-height: 205px !important;
  display: grid;
  grid-template-rows: 1fr auto;
  gap: 0;
  padding: 0;
  border: 1px solid #c3c4c7;
  border-radius: 0;
  background: #fff;
  box-shadow: none;
  overflow: hidden;
}

.plugin-card:hover {
  border-color: #8c8f94;
  transform: none;
}

.plugin-card.selected {
  border-color: #72aee6;
  background: #f6fbff;
}

.plugin-main {
  min-height: 128px;
  align-items: flex-start;
  gap: 14px;
  padding: 14px 14px 10px;
}

.plugin-image {
  width: 112px;
  height: 112px;
  flex: 0 0 112px;
  align-self: flex-start;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.plugin-image img {
  width: 100%;
  height: 100%;
  padding: 0;
  object-fit: contain;
  object-position: top right;
}

.plugin-content {
  gap: 8px;
}

.plugin-content header {
  align-items: flex-start;
}

.plugin-card h3 {
  color: #2271b1;
  font-size: 15px;
  font-weight: 1000;
  line-height: 1.45;
}

.plugin-card small {
  color: #646970;
  font-size: 11px;
}

.plugin-card p {
  color: #3c434a;
  font-size: 12.5px;
  line-height: 1.9;
  margin-top: 2px;
  max-width: 96%;
}

.plugin-content .details-btn,
.details-btn {
  width: auto;
  height: 30px;
  padding: 0 10px;
  border-color: #2271b1;
  border-radius: 3px;
  background: #f6f7f7;
  color: #2271b1;
  font-size: 11px;
}

.plugin-actions {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px 12px;
  min-height: 48px;
  padding: 8px 12px;
  border-top: 1px solid #dcdcde;
  border-radius: 0;
  background: #f6f7f7;
}

.plugin-meta {
  min-width: 0;
  min-height: 0;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  padding: 0;
  border-top: 0;
  border-radius: 0;
  background: transparent;
}

.plugin-meta strong {
  color: #1d2327;
  font-size: 12px;
  white-space: nowrap;
}

.plugin-meta span {
  color: #3c434a;
  font-size: 11px;
  white-space: nowrap;
}

.period-options {
  grid-column: 1 / -1;
  display: flex;
  gap: 6px;
  padding: 0;
}

.plugin-card footer {
  min-width: 116px;
  padding: 0;
  justify-content: flex-end;
}

.select-btn,
.purchased-badge,
.coming-soon-badge {
  width: 100%;
  min-height: 32px;
  border-radius: 3px;
  font-size: 11px;
}

.select-btn {
  background: #2271b1;
}

.select-btn.selected {
  background: #646970;
}

.cart-panel {
  border-color: #c3c4c7;
  border-radius: 0;
  box-shadow: none;
}

.modal-image {
  width: 104px;
  height: 104px;
  flex: 0 0 104px;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.modal-image img {
  width: 100%;
  height: 100%;
  max-height: none;
  object-fit: contain;
  object-position: top right;
}

.modal-plugin-head {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid #dcdcde;
}

.modal-plugin-head > div:last-child {
  min-width: 0;
  display: grid;
  gap: 5px;
  padding-top: 7px;
}

.modal-plugin-head small {
  color: #646970;
  font-size: 12px;
  font-weight: 800;
}

.store-modal h2 {
  color: #2271b1;
  font-size: 22px;
  font-weight: 1000;
  line-height: 1.45;
}

.modal-description {
  padding: 12px 0 2px;
  color: #2c3338;
  font-size: 13px;
  line-height: 2.05;
  text-align: right;
}

.modal-actions {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 150px;
  align-items: center;
  gap: 12px;
  margin-top: 4px;
  padding: 12px;
  border-top: 1px solid #dcdcde;
  background: #f6f7f7;
}

.modal-actions .select-btn {
  height: 34px;
}

.modal-actions .modal-price {
  padding: 0;
  background: transparent;
}

@media (max-width: 560px) {
  .modal-plugin-head {
    align-items: center;
  }

  .modal-image {
    width: 82px;
    height: 82px;
    flex-basis: 82px;
  }

  .modal-actions {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .plugin-grid,
  .coming-soon-grid {
    grid-template-columns: 1fr !important;
  }

  .plugin-actions {
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .plugin-card footer {
    min-width: 0;
  }
}

.invoice-document {
  border-color: #dbe7f3;
}

.invoice-head {
  min-height: 126px;
}

.invoice-brand {
  min-width: 260px;
  gap: 12px;
  direction: rtl;
}

.invoice-brand > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.invoice-brand small,
.invoice-brand strong {
  display: block;
  line-height: 1.5;
  white-space: nowrap;
}

.invoice-title-block {
  align-items: start;
  text-align: right;
  direction: rtl;
}

.invoice-title-block h1 {
  line-height: 1.45;
}

.invoice-meta-grid div {
  min-height: 92px;
  justify-content: center;
}

.invoice-row {
  min-height: 70px;
}

.invoice-row > span,
.invoice-row > strong {
  white-space: nowrap;
}

.invoice-row > div {
  min-width: 0;
}

.invoice-row > div strong,
.invoice-row > div small {
  display: block;
  overflow-wrap: anywhere;
  line-height: 1.7;
}

.invoice-footer {
  grid-template-columns: minmax(220px, 1fr) minmax(300px, 390px);
  align-items: center;
}

.invoice-back {
  width: min(320px, 100%);
  justify-self: start;
}

.payment-card,
.invoice-note {
  margin: 0 30px 22px;
  box-shadow: none;
}

.payment-card {
  min-height: 88px;
}

.invoice-note {
  min-height: auto;
}

@media(max-width:680px){
  .invoice-brand{min-width:0}
  .invoice-meta-grid div{min-height:auto}
  .invoice-footer{grid-template-columns:1fr}
  .invoice-back{justify-self:stretch;width:100%}
  .payment-card,.invoice-note{margin:0 14px 14px}
}

.invoice-page{width:min(900px,100%);align-content:start;padding-block:10px}.invoice-document{border:1px solid transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#bfdbfe,#d1fae5) border-box;box-shadow:0 24px 70px rgba(15,23,42,.1)}.invoice-head{min-height:118px;padding:26px 28px;background:radial-gradient(circle at 88% 15%,rgba(37,99,235,.16),transparent 32%),linear-gradient(135deg,#f8fbff,#eef7ff)}.invoice-brand{gap:12px}.invoice-logo{width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,#2563eb,#14b8a6);font-size:22px;box-shadow:0 12px 24px rgba(37,99,235,.24)}.invoice-title-block{text-align:right;align-items:start}.invoice-title-block h1{font-size:30px;line-height:1.45}.invoice-meta-grid{background:#dbeafe}.invoice-meta-grid div{min-height:82px;padding:16px 22px;justify-content:center}.invoice-body{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:18px;padding:24px;background:#f8fbff}.invoice-items,.invoice-side{display:grid;align-content:start;gap:12px}.invoice-section-title{color:#334155;font-size:13px;font-weight:1000}.invoice-item{display:grid;grid-template-columns:46px minmax(0,1fr) auto;align-items:center;gap:12px;min-height:76px;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.04)}.invoice-item-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:color-mix(in srgb,var(--accent) 13%,#fff);color:var(--accent);font-size:14px;font-weight:1000}.invoice-item strong,.invoice-item small{display:block;line-height:1.7}.invoice-item small{color:#64748b;font-size:11px;font-weight:800}.invoice-item>span{color:#111827;font-weight:1000;white-space:nowrap}.invoice-side .invoice-summary{gap:12px}.invoice-side .invoice-summary div{min-height:58px;padding:13px 15px;border:1px solid #e2e8f0;background:#fff}.invoice-side .invoice-total{min-height:96px;align-content:center;border-color:#bfdbfe;background:linear-gradient(135deg,#eff6ff,#e0f2fe)!important}.invoice-side .invoice-total strong{font-size:24px}.invoice-side .payment-card{margin:0;grid-template-columns:1fr;min-height:0;padding:16px;border-radius:14px;box-shadow:none}.invoice-side .checkout-btn{height:48px;font-size:15px}.invoice-back{width:100%;flex-basis:auto}.invoice-note{margin:0 24px 24px;min-height:auto;padding:14px 16px;border-radius:14px;background:#fff;box-shadow:none}
.invoice-discount{border-color:#bbf7d0!important;background:#f0fdf4!important;color:#15803d}.invoice-discount strong{color:#15803d}.discount-box{display:grid;gap:9px;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.discount-box label{display:grid;gap:6px;color:#475569;font-size:12px;font-weight:900}.discount-box input{height:40px;border:1px solid #cbd5e1;border-radius:10px;padding:0 11px;font-family:inherit;font-weight:900;text-transform:uppercase}.discount-box button{height:40px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-family:inherit;font-weight:1000;cursor:pointer}.discount-box p{margin:0;color:#15803d;font-size:12px;font-weight:900}.discount-box p.error{color:#dc2626}

.coming-soon-section{display:grid;gap:13px;margin-top:18px;padding-top:18px;border-top:1px dashed #cbd5e1}.coming-soon-section>header{display:flex;align-items:center;justify-content:space-between;gap:12px}.coming-soon-section>header span{color:#be123c;font-size:12px;font-weight:1000}.coming-soon-section>header h2{margin:4px 0 0;color:#111827;font-size:18px}.coming-soon-card{background:#fff7f7;border-color:#fecdd3}.coming-soon-card:hover{border-color:#fda4af}.coming-soon-badge{min-height:38px;display:grid;flex:1;place-items:center;border-radius:8px;background:#ffe4e6;color:#be123c;font-size:12px;font-weight:1000;text-align:center}.coming-soon-grid .plugin-meta strong{color:#be123c}
.period-options{display:flex;gap:6px;flex-wrap:wrap}.period-options button{height:30px;padding:0 9px;border:1px solid #dbeafe;border-radius:8px;background:#fff;color:#2563eb;font-family:inherit;font-size:10px;font-weight:1000;cursor:pointer}.period-options button.active{border-color:#2563eb;background:#2563eb;color:#fff}
.terms-body{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;padding:24px;background:#f8fbff}.terms-document{overflow:visible}.terms-text{min-height:420px;max-height:58vh;overflow:auto;padding:22px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.04)}.terms-text p{margin:0;white-space:pre-line;color:#334155;font-size:14px;font-weight:800;line-height:2.25}.terms-confirm{display:flex;align-items:flex-start;gap:10px;padding:14px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;color:#1e40af;font-size:13px;font-weight:1000;line-height:1.9;cursor:pointer}.terms-confirm input{width:18px;height:18px;flex:0 0 18px;margin-top:4px;accent-color:#2563eb}.checkout-message{margin:0;padding:10px 12px;border-radius:10px;background:#dcfce7;color:#15803d;font-size:12px;font-weight:900;line-height:1.8}.checkout-message.error{background:#fee2e2;color:#b91c1c}

@media(max-width:760px){.invoice-body,.terms-body{grid-template-columns:1fr;padding:14px}.invoice-head{gap:16px}.invoice-item{grid-template-columns:40px minmax(0,1fr)}.invoice-item>span{grid-column:2}.invoice-note{margin:0 14px 14px}.terms-text{max-height:none;min-height:320px}}
</style>
