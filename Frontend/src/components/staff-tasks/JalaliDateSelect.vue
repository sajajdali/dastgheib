<template>
  <div class="jds">
    <select :value="d" @change="setDay(+$event.target.value)" class="st-input f1">
      <option v-for="n in days" :key="n" :value="n">{{ fa(n) }}</option>
    </select>
    <select :value="m" @change="setMonth(+$event.target.value)" class="st-input f16">
      <option v-for="(name, i) in MONTHS" :key="i" :value="i + 1">{{ name }}</option>
    </select>
    <select :value="y" @change="setYear(+$event.target.value)" class="st-input f12">
      <option v-for="v in [y - 1, y, y + 1]" :key="v" :value="v">{{ fa(v) }}</option>
    </select>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { d2j, j2d, monthLength, MONTHS, fa } from './jalali'

const props = defineProps({ modelValue: { type: Number, required: true } }) // JDN
const emit = defineEmits(['update:modelValue'])

const parts = computed(() => d2j(props.modelValue))
const y = computed(() => parts.value[0])
const m = computed(() => parts.value[1])
const d = computed(() => parts.value[2])
const days = computed(() => monthLength(y.value, m.value))

const setDay = v => emit('update:modelValue', j2d(y.value, m.value, v))
const setMonth = v => emit('update:modelValue', j2d(y.value, v, Math.min(d.value, monthLength(y.value, v))))
const setYear = v => emit('update:modelValue', j2d(v, m.value, Math.min(d.value, monthLength(v, m.value))))
</script>

<style scoped>
.jds{display:flex;gap:6px}
.st-input{font-family:inherit;font-size:14px;border:1px solid var(--st-border);border-radius:11px;padding:9px 6px;background:var(--st-surface);color:var(--st-text);min-width:0}
.f1{flex:1}.f16{flex:1.6}.f12{flex:1.2}
</style>
