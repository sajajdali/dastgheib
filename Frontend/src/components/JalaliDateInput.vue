<template>
  <DatePicker :editable="true" />
</template>

<script>
import DatePicker from 'vue3-persian-datetime-picker'
import moment from 'moment-jalaali'
import Swal from 'sweetalert2'

const toEnglishDigits = value => String(value ?? '')
  .replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
  .replace(/[٠-٩]/g, digit => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))
  .trim()

const pad = value => String(value).padStart(2, '0')

/** متن تایپ‌شده را به سال/ماه/روز شمسی تبدیل می‌کند؛ نامعتبر → null */
export function parseTypedJalaliDate(text) {
  const raw = toEnglishDigits(text)
  let parts = raw.split(/[^0-9]+/).filter(Boolean)
  if (parts.length === 1 && parts[0].length === 8) parts = [parts[0].slice(0, 4), parts[0].slice(4, 6), parts[0].slice(6)]
  if (parts.length === 1 && parts[0].length === 6) parts = [parts[0].slice(0, 2), parts[0].slice(2, 4), parts[0].slice(4)]
  if (parts.length !== 3) return null

  let [year, month, day] = parts.map(Number)
  // سال دورقمی: ۷۰ → ۱۳۷۰ و ۰۵ → ۱۴۰۵ (هر چیزی تا سال جاری ۱۴۰۰ به بعد است)
  if (parts[0].length <= 2) {
    const currentShortYear = moment().jYear() % 100
    year += year <= currentShortYear ? 1400 : 1300
  }
  if (year < 1200 || year > 1500 || month < 1 || month > 12) return null
  // moment-jalaali روز/ماه بیش از حد را به ماه بعد سرریز می‌کند؛ بازه را خودمان چک می‌کنیم.
  if (day < 1 || day > moment.jDaysInMonth(year, month - 1)) return null

  return { year, month, day }
}

// همان date-picker همیشگی، با این تفاوت که تاریخ را می‌شود دستی هم تایپ کرد؛
// دکمهٔ تقویم کنار فیلد همچنان تقویم را باز می‌کند.
export default {
  name: 'JalaliDateInput',
  components: { DatePicker },
  mounted() {
    if (!this.isDateOnly()) return
    // شنونده‌ها در فاز capture روی ریشهٔ پیکر نصب می‌شوند تا قبل از هندلر blur/enter
    // خود پیکر اجرا شوند (این نسخهٔ پیکر $refs را پر نمی‌کند، پس از DOM استفاده می‌شود).
    this.root = this.$el
    this.root.addEventListener('focus', this.rememberValue, true)
    this.root.addEventListener('blur', this.applyTypedDate, true)
    this.root.addEventListener('keydown', this.onKeydown, true)
    const input = this.mainInput()
    if (input) {
      input.setAttribute('autocomplete', 'off')
      if (!input.title) input.title = 'تاریخ را تایپ کنید (مثل ۱۳۷۰/۰۵/۱۲) یا از دکمهٔ تقویم انتخاب کنید'
    }
  },
  beforeUnmount() {
    this.root?.removeEventListener('focus', this.rememberValue, true)
    this.root?.removeEventListener('blur', this.applyTypedDate, true)
    this.root?.removeEventListener('keydown', this.onKeydown, true)
  },
  methods: {
    isDateOnly() {
      const type = this.$attrs.type
      return !type || type === 'date'
    },
    mainInput() {
      return this.root?.querySelector?.('.vpd-input-group > input[type="text"]') || null
    },
    displayFormat() {
      return this.$attrs['display-format'] || this.$attrs.displayFormat || 'jYYYY/jMM/jDD'
    },
    rememberValue(event) {
      if (event.target === this.mainInput()) this.valueOnFocus = event.target.value
    },
    onKeydown(event) {
      if (event.key === 'Enter') this.applyTypedDate(event)
    },
    // متن تایپ‌شده (ارقام فارسی، جداکننده‌های مختلف، ۱۳۷۰۰۵۱۲، سال دورقمی) را خودمان
    // تبدیل و emit می‌کنیم؛ پارس خود پیکر تاریخ نامعتبر را سرریز می‌کند و با view="year" اصلاً ثبت نمی‌کند.
    applyTypedDate(event) {
      const input = event.target
      if (input !== this.mainInput()) return
      const previous = this.valueOnFocus ?? input.defaultValue ?? ''
      const typed = toEnglishDigits(input.value)
      const current = toEnglishDigits(previous)

      // بدون تغییر: پیکر را دوباره submit نکن تا رویداد change بیهوده نخورد.
      if (typed === current) {
        event.stopPropagation()
        return
      }
      if (typed === '') return

      event.stopPropagation()
      const parsed = parseTypedJalaliDate(typed)
      if (!parsed) {
        input.value = previous
        Swal.fire({
          toast: true,
          position: 'top',
          icon: 'warning',
          title: 'تاریخ واردشده معتبر نیست؛ مثلاً ۱۳۷۰/۰۵/۱۲ وارد کنید.',
          timer: 3500,
          showConfirmButton: false
        })
        return
      }

      const date = moment(`${parsed.year}/${parsed.month}/${parsed.day}`, 'jYYYY/jM/jD').locale('en')
      const displayFormat = this.displayFormat()
      input.value = /j/.test(displayFormat)
        ? displayFormat
          .replace('jYYYY', parsed.year)
          .replace('jMM', pad(parsed.month))
          .replace('jDD', pad(parsed.day))
        : date.format(displayFormat)
      this.valueOnFocus = input.value
      this.$emit('update:modelValue', date.format(this.$attrs.format || 'YYYY-MM-DD'))
      this.$emit('change', date.clone())
    }
  }
}
</script>
