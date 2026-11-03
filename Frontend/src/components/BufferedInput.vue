<template>
  <textarea
    v-if="multiline"
    v-bind="$attrs"
    :value="draft"
    @input="onInput"
    @focus="focused = true"
    @blur="onBlur"
  ></textarea>
  <input
    v-else
    v-bind="$attrs"
    :value="draft"
    @input="onInput"
    @focus="focused = true"
    @blur="onBlur"
  >
</template>

<script setup>
// فیلدی که هنگام تایپ فقط خودش را دوباره رسم می‌کند و مقدار را پس از مکث کوتاه
// یا هنگام خروج از فیلد به والد می‌دهد. برای فرم‌های داخل کامپوننت‌های سنگین
// (مثل پنل مالی وقت‌دهی) که هر تغییر مدل، کل جدول نوبت‌ها را دوباره رسم می‌کند.
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  // قالب‌بندی هم‌زمان با تایپ، مثل جداکنندهٔ هزارگان مبلغ
  format: { type: Function, default: null },
  delay: { type: Number, default: 350 },
  multiline: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'commit'])

const draft = ref(props.modelValue ?? '')
const focused = ref(false)
let dirty = false
let timer = null

// تا وقتی کاربر داخل فیلد است، متن او بر مقدار والد مقدم است.
watch(() => props.modelValue, (value) => {
  if (!focused.value && !dirty) draft.value = value ?? ''
})

function onInput(event) {
  const raw = event.target.value
  const next = props.format ? props.format(raw) : raw
  draft.value = next
  if (event.target.value !== next) event.target.value = next
  dirty = true
  clearTimeout(timer)
  timer = setTimeout(commit, props.delay)
}

function commit() {
  clearTimeout(timer)
  timer = null
  if (!dirty) return
  dirty = false
  emit('update:modelValue', draft.value)
  emit('commit', draft.value)
}

// blur پیش از click دکمهٔ ذخیره اجرا می‌شود؛ پس آخرین عدد همیشه ثبت می‌شود.
function onBlur() {
  commit()
  focused.value = false
  // والد ممکن است مقدار را اصلاح کند (مثلاً حذف فاصله‌ها) بدون اینکه prop عوض شود.
  nextTick(() => {
    if (!focused.value && !dirty) draft.value = props.modelValue ?? ''
  })
}

onBeforeUnmount(commit)
</script>
