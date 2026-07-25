<template>
  <main class="store-page" dir="rtl">
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
            <header>
              <div class="plugin-icon" :style="{ '--accent': item.color }">
                <span>{{ item.icon }}</span>
              </div>
              <div>
                <h3>{{ item.title }}</h3>
                <small>{{ item.categoryLabel }}</small>
              </div>
            </header>

            <p>{{ item.description }}</p>

            <div class="plugin-meta">
              <strong>{{ formatPrice(item.price) }}</strong>
              <span>{{ item.billing }}</span>
            </div>

            <footer>
              <button type="button" class="details-btn" @click="openDetails(item)">جزئیات</button>
              <button
                type="button"
                class="select-btn"
                :class="{ selected: isSelected(item.key) }"
                @click="toggleItem(item)"
              >
                {{ isSelected(item.key) ? "حذف از سبد" : "افزودن به سبد" }}
              </button>
            </footer>
          </article>
        </div>
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
        <div class="plugin-icon modal-icon" :style="{ '--accent': detailsItem.color }">
          <span>{{ detailsItem.icon }}</span>
        </div>
        <small>{{ detailsItem.categoryLabel }}</small>
        <h2>{{ detailsItem.title }}</h2>
        <p>{{ detailsItem.longDescription }}</p>
        <div class="modal-price">
          <span>قیمت</span>
          <strong>{{ formatPrice(detailsItem.price) }}</strong>
        </div>
        <button type="button" class="select-btn" @click="toggleItem(detailsItem)">
          {{ isSelected(detailsItem.key) ? "حذف از سبد" : "افزودن به سبد" }}
        </button>
      </section>
    </div>
  </main>
</template>

<script>
const CART_KEY = "clinic_store_cart_v1"

export default {
  name: "Store",
  data() {
    return {
      activeCategory: "all",
      search: "",
      selectedKeys: [],
      detailsItem: null,
      categories: [
        { value: "all", label: "همه" },
        { value: "operations", label: "عملیات" },
        { value: "marketing", label: "بازاریابی" },
        { value: "finance", label: "مالی" },
        { value: "insight", label: "تحلیل" }
      ],
      items: [
        { key: "online_chat", title: "چت آنلاین", price: 2500, category: "marketing", categoryLabel: "ارتباط با مشتری", icon: "💬", color: "#0ea5e9", billing: "ماهیانه", description: "گفتگوی مستقیم با مراجعه‌کننده‌ها از داخل سیستم.", longDescription: "ماژول چت آنلاین برای پاسخ سریع، ثبت گفتگو و تبدیل پیام‌ها به سرنخ قابل پیگیری طراحی شده است." },
        { key: "attendance", title: "حضور غیاب", price: 2200, category: "operations", categoryLabel: "پرسنل", icon: "⏱", color: "#16a34a", billing: "ماهیانه", description: "ثبت ورود و خروج و کنترل کارکرد پرسنل.", longDescription: "حضور غیاب برای مدیریت شیفت، ورود، خروج و گزارش کارکرد روزانه پرسنل استفاده می‌شود." },
        { key: "staff_review", title: "ارزیابی پرسنل", price: 3200, category: "operations", categoryLabel: "پرسنل", icon: "★", color: "#f59e0b", billing: "ماهیانه", description: "امتیازدهی و بررسی عملکرد اعضای تیم.", longDescription: "با ارزیابی پرسنل می‌توانید کیفیت پاسخگویی، فروش، نظم و عملکرد هر نفر را در بازه‌های مختلف بسنجید." },
        { key: "tasks", title: "وظایف", price: 2800, category: "operations", categoryLabel: "مدیریت کار", icon: "✓", color: "#6366f1", billing: "ماهیانه", description: "تعریف کار، مسئول، موعد و وضعیت انجام.", longDescription: "ماژول وظایف برای سپردن کارهای داخلی، پیگیری موعدها و جلوگیری از فراموشی کارهای تیمی است." },
        { key: "campaign_builder", title: "طراحی کمپین تبلیغات", price: 2100, category: "marketing", categoryLabel: "کمپین", icon: "◉", color: "#ec4899", billing: "ماهیانه", description: "ساخت کمپین و مدیریت لیدهای تبلیغاتی.", longDescription: "کمپین تبلیغات به شما کمک می‌کند لیدها، تماس‌ها، درجه تمایل و خروجی هر کمپین را یک‌جا ببینید." },
        { key: "online_booking", title: "وقت دهی آنلاین", price: 4200, category: "marketing", categoryLabel: "رزرو", icon: "◷", color: "#2563eb", billing: "ماهیانه", description: "رزرو آنلاین نوبت توسط مشتری.", longDescription: "وقت‌دهی آنلاین مسیر رزرو را برای مشتری ساده می‌کند و ظرفیت‌های آزاد را به شکل کنترل‌شده نمایش می‌دهد." },
        { key: "wallet", title: "کیف پول", price: 2300, category: "finance", categoryLabel: "پرداخت", icon: "▣", color: "#059669", billing: "ماهیانه", description: "مدیریت اعتبار و مانده حساب مشتری.", longDescription: "کیف پول برای شارژ حساب، برداشت، اعتبار هدیه و کنترل مانده مالی مراجعه‌کننده‌ها استفاده می‌شود." },
        { key: "photo_manager", title: "مدیریت عکس", price: 4500, category: "operations", categoryLabel: "پرونده تصویری", icon: "▧", color: "#d97706", billing: "ماهیانه", description: "آرشیو عکس قبل و بعد و دسته‌بندی خدمات.", longDescription: "مدیریت عکس برای پرونده تصویری، مقایسه قبل و بعد و نگهداری منظم تصاویر درمانی طراحی شده است." },
        { key: "service_finder", title: "خدمت یاب", price: 1800, category: "operations", categoryLabel: "خدمات", icon: "⌕", color: "#7c3aed", billing: "ماهیانه", description: "تعریف و جستجوی سریع خدمات کلینیک.", longDescription: "خدمت‌یاب کمک می‌کند خدمات، گروه‌ها، پزشک مرتبط و تنظیمات اجرایی هر خدمت قابل مدیریت باشد." },
        { key: "expert_reports", title: "گزارش تخصصی", price: 1500, category: "insight", categoryLabel: "گزارش", icon: "▤", color: "#0891b2", billing: "ماهیانه", description: "گزارش‌های مدیریتی دقیق‌تر و قابل تصمیم.", longDescription: "گزارش تخصصی برای تحلیل فروش، نوبت، پرسنل، کنسلی و تبدیل مشاوره به انجام کار کاربرد دارد." },
        { key: "inventory", title: "انبار داری", price: 2800, category: "operations", categoryLabel: "انبار", icon: "▦", color: "#475569", billing: "ماهیانه", description: "کنترل موجودی، هشدار اتمام و مصرف مواد.", longDescription: "انبارداری برای مدیریت کالا، مصرف مواد، هشدار موجودی و اتصال خدمات به محصولات مصرفی است." },
        { key: "bills", title: "قبوض", price: 1900, category: "finance", categoryLabel: "هزینه‌ها", icon: "▥", color: "#dc2626", billing: "ماهیانه", description: "ثبت و پیگیری هزینه‌ها و قبوض دوره‌ای.", longDescription: "قبوض به شما کمک می‌کند هزینه‌های ثابت، پرداخت‌های دوره‌ای و بدهی‌های مرکز را فراموش نکنید." },
        { key: "satisfaction", title: "رضایت مندی", price: 3200, category: "marketing", categoryLabel: "کیفیت", icon: "☺", color: "#22c55e", billing: "ماهیانه", description: "سنجش تجربه مشتری بعد از مراجعه.", longDescription: "رضایت‌مندی برای جمع‌آوری بازخورد، امتیاز مشتری و تشخیص نقاط ضعف تجربه مراجعه‌کننده کاربرد دارد." },
        { key: "leads", title: "سرنخ ها", price: 3400, category: "marketing", categoryLabel: "پیگیری", icon: "!", color: "#f97316", billing: "ماهیانه", description: "هشدارهای عملیاتی و پیگیری‌های مهم روز.", longDescription: "سرنخ‌ها اعلان‌های مهم کلینیک را در یک صفحه جمع می‌کند تا هیچ پیگیری مهمی از دست نرود." },
        { key: "two_users", title: "هر دو کاربر", price: 1100, category: "operations", categoryLabel: "کاربران", icon: "👥", color: "#14b8a6", billing: "ماهیانه", description: "افزایش ظرفیت کاربران فعال سیستم.", longDescription: "این ماژول برای اضافه کردن ظرفیت کاربران بیشتر و مدیریت دسترسی تیمی در سیستم استفاده می‌شود." },
        { key: "ai_report_analysis", title: "تحلیل گزارش با هوش مصنوعی", price: 0, category: "insight", categoryLabel: "هوش مصنوعی", icon: "AI", color: "#8b5cf6", billing: "توکنی", description: "تحلیل مدیریتی گزارش‌ها با مصرف توکن.", longDescription: "تحلیل گزارش با هوش مصنوعی به‌صورت توکنی فعال می‌شود و برای خلاصه‌سازی، پیشنهاد مدیریتی و تحلیل روندها کاربرد دارد." },
        { key: "aftercare", title: "مراقبت های بعد از درمان", price: 1100, category: "marketing", categoryLabel: "مشتری", icon: "+", color: "#0d9488", billing: "ماهیانه", description: "ارسال و پیگیری مراقبت‌های بعد از خدمت.", longDescription: "مراقبت‌های بعد از درمان کمک می‌کند پیام‌ها، دستورالعمل‌ها و پیگیری‌های بعد از مراجعه منظم انجام شود." },
        { key: "payment_link", title: "ارسال لینک پرداخت", price: 1100, category: "finance", categoryLabel: "پرداخت", icon: "↗", color: "#0284c7", billing: "ماهیانه", description: "ارسال لینک پرداخت برای تسویه سریع.", longDescription: "با ارسال لینک پرداخت، مشتری بدون مراجعه حضوری می‌تواند بدهی یا پیش‌پرداخت را تسویه کند." },
        { key: "staff_exam", title: "آزمون پرسنل", price: 2800, category: "operations", categoryLabel: "آموزش", icon: "؟", color: "#9333ea", billing: "ماهیانه", description: "برگزاری آزمون داخلی برای تیم.", longDescription: "آزمون پرسنل برای آموزش، سنجش دانش داخلی و کنترل آمادگی اعضای تیم طراحی شده است." }
      ]
    }
  },
  computed: {
    filteredItems() {
      const query = this.search.toLowerCase()
      return this.items.filter(item => {
        const categoryOk = this.activeCategory === "all" || item.category === this.activeCategory
        const searchOk = !query || `${item.title} ${item.description} ${item.categoryLabel}`.toLowerCase().includes(query)
        return categoryOk && searchOk
      })
    },
    selectedItems() {
      return this.items.filter(item => this.selectedKeys.includes(item.key))
    },
    totalPrice() {
      return this.selectedItems.reduce((sum, item) => sum + Number(item.price || 0), 0)
    }
  },
  mounted() {
    try {
      const saved = JSON.parse(localStorage.getItem(CART_KEY) || "[]")
      this.selectedKeys = Array.isArray(saved) ? saved : []
    } catch {
      this.selectedKeys = []
    }
  },
  methods: {
    formatPrice(value) {
      if (!Number(value)) return "توکنی"
      return `${Number(value).toLocaleString("fa-IR")} میلیون تومان`
    },
    isSelected(key) {
      return this.selectedKeys.includes(key)
    },
    persistCart() {
      localStorage.setItem(CART_KEY, JSON.stringify(this.selectedKeys))
    },
    toggleItem(item) {
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
      alert("مسیر پرداخت در فاز بعدی وصل می‌شود.")
    }
  }
}
</script>

<style scoped>
.store-page{min-height:100vh;display:grid;gap:18px;padding:22px;background:#f6f8fb;color:#0f172a;font-family:"IranSans",Tahoma,sans-serif}.store-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:24px;border:1px solid #dbeafe;border-radius:18px;background:linear-gradient(135deg,#fff,#eff6ff);box-shadow:0 14px 40px rgba(15,23,42,.08)}.store-hero span{color:#2563eb;font-size:12px;font-weight:900}.store-hero h1{margin:5px 0;font-size:28px}.store-hero p{max-width:620px;margin:0;color:#64748b;line-height:1.9}.store-hero aside{min-width:150px;display:grid;place-items:center;gap:3px;padding:17px;border-radius:16px;background:#0f172a;color:#fff}.store-hero aside strong{font-size:30px}.store-hero aside small{color:#cbd5e1}.store-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start}.store-catalog{display:grid;gap:14px}.store-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.store-tabs{display:flex;gap:6px;overflow:auto}.store-tabs button{height:36px;padding:0 13px;border:1px solid transparent;border-radius:10px;background:#f8fafc;color:#475569;font-family:inherit;font-weight:900;white-space:nowrap;cursor:pointer}.store-tabs button.active{background:#2563eb;color:#fff}.store-search input{width:210px;height:38px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-family:inherit}.plugin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.plugin-card{min-height:245px;display:flex;flex-direction:column;gap:13px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05);transition:.18s}.plugin-card:hover{border-color:#bfdbfe;transform:translateY(-2px)}.plugin-card.selected{border-color:#60a5fa;background:#f8fbff}.plugin-card header{display:flex;align-items:center;gap:11px}.plugin-icon{width:48px;height:48px;flex:0 0 48px;display:grid;place-items:center;border-radius:12px;background:color-mix(in srgb,var(--accent) 14%,#fff);color:var(--accent);font-weight:1000;box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--accent) 28%,transparent)}.plugin-icon span{font-size:19px}.plugin-card h3{margin:0;color:#111827;font-size:15px}.plugin-card small{color:#64748b;font-size:10px;font-weight:800}.plugin-card p{flex:1;margin:0;color:#475569;font-size:12px;line-height:1.9}.plugin-meta{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:10px;border-radius:10px;background:#f8fafc}.plugin-meta strong{color:#0f172a}.plugin-meta span{color:#94a3b8;font-size:10px;font-weight:900}.plugin-card footer{display:flex;gap:8px}.plugin-card button,.checkout-btn,.cart-panel button,.store-modal button{font-family:inherit;cursor:pointer}.details-btn,.select-btn{height:38px;border-radius:10px;font-weight:900}.details-btn{flex:0 0 80px;border:1px solid #dbeafe;background:#fff;color:#2563eb}.select-btn{flex:1;border:0;background:#2563eb;color:#fff}.select-btn.selected{background:#64748b}.cart-panel{position:sticky;top:74px;display:grid;gap:13px;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.08)}.cart-panel header{display:flex;align-items:center;justify-content:space-between}.cart-panel header span{font-size:16px;font-weight:1000}.cart-panel header button{border:0;background:transparent;color:#dc2626;font-weight:900}.cart-list{display:grid;gap:8px;max-height:330px;overflow:auto}.cart-list article{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.cart-list article div{display:grid;gap:3px}.cart-list strong{font-size:12px}.cart-list small{color:#64748b}.cart-list button{width:28px;height:28px;border:0;border-radius:8px;background:#fee2e2;color:#dc2626;font-size:18px}.cart-empty{padding:28px 10px;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;text-align:center}.cart-total{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid #e2e8f0}.cart-total span{color:#64748b}.cart-total strong{font-size:18px}.checkout-btn{height:44px;border:0;border-radius:11px;background:#16a34a;color:#fff;font-weight:1000}.checkout-btn:disabled{background:#cbd5e1;cursor:not-allowed}.store-modal{position:fixed;inset:0;z-index:1000002;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58);backdrop-filter:blur(5px)}.store-modal section{position:relative;width:min(480px,96vw);display:grid;gap:12px;padding:24px;border-radius:18px;background:#fff;box-shadow:0 30px 80px rgba(15,23,42,.35)}.modal-close{position:absolute;top:12px;left:12px;width:34px;height:34px;border:0;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:22px}.modal-icon{width:62px;height:62px}.store-modal h2{margin:0;font-size:24px}.store-modal p{margin:0;color:#475569;line-height:2}.modal-price{display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:12px;background:#f8fafc}.modal-price span{color:#64748b}.modal-price strong{font-size:18px}@media(max-width:1100px){.store-layout{grid-template-columns:1fr}.cart-panel{position:static}.plugin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.store-page{padding:14px}.store-hero,.store-toolbar{align-items:stretch;flex-direction:column}.store-hero aside{min-width:0}.store-search input{width:100%}.plugin-grid{grid-template-columns:1fr}.plugin-card footer{flex-direction:column}.details-btn{flex:auto}.store-layout{gap:14px}}
</style>
