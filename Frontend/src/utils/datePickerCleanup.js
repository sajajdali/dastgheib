import DatePicker from 'vue3-persian-datetime-picker'

// کتابخانهٔ تقویم هوک پاک‌سازی را با نام `onBeforeUnmount` نوشته که در Options API ویو ۳
// هرگز صدا زده نمی‌شود؛ در نتیجه تایمر یک‌ثانیه‌ای و شنونده‌های window/body هر تقویم
// بعد از حذف آن باقی می‌ماندند و با هر فیلتر جدول انباشته شده و صفحه را قفل می‌کردند.
if (!DatePicker.__cleanupPatched) {
  const originalMounted = DatePicker.mounted
  const originalCleanup = DatePicker.onBeforeUnmount

  DatePicker.mounted = function () {
    // شنوندهٔ keydown روی body بی‌نام ثبت می‌شود؛ آن را نگه می‌داریم تا بعداً حذف شود.
    const body = document.body
    const nativeAdd = body.addEventListener
    body.addEventListener = (type, listener, options) => {
      if (type === 'keydown') this.__bodyKeydownListener = listener
      return nativeAdd.call(body, type, listener, options)
    }
    try {
      return originalMounted?.call(this)
    } finally {
      delete body.addEventListener
    }
  }

  DatePicker.beforeUnmount = function () {
    originalCleanup?.call(this)
    if (this.__bodyKeydownListener) {
      document.body.removeEventListener('keydown', this.__bodyKeydownListener)
      this.__bodyKeydownListener = null
    }
  }

  DatePicker.__cleanupPatched = true
}
