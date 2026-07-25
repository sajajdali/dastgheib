<template>
  <span
    class="patient-avatar"
    :class="[`level-${normalizedLevel}`, { 'is-clickable': clickable, 'has-crown': normalizedLevel === 'gold' }]"
    :style="avatarStyle"
    role="img"
    :aria-label="alt || 'تصویر مشتری'"
  >
    <img
      v-if="imageUrl && !failed"
      :src="imageUrl"
      :alt="alt"
      loading="lazy"
      decoding="async"
      @error="failed = true"
    >
    <span v-else class="patient-avatar__fallback" aria-hidden="true">{{ initials || '👤' }}</span>
  </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  patient: { type: Object, default: () => ({}) },
  src: { type: String, default: '' },
  level: { type: String, default: '' },
  alt: { type: String, default: '' },
  size: { type: [Number, String], default: 50 },
  original: { type: Boolean, default: false },
  clickable: { type: Boolean, default: false }
})

const failed = ref(false)
const imageUrl = computed(() => props.src || (props.original
  ? props.patient.profile_photo_url
  : props.patient.avatar_url || props.patient.profile_thumbnail_url || props.patient.profile_photo_url) || '')
const normalizedLevel = computed(() => ['problematic', 'blue', 'silver', 'gold'].includes(props.level || props.patient.customer_level)
  ? (props.level || props.patient.customer_level)
  : 'silver')
const initials = computed(() => `${String(props.patient.first_name || '').trim().charAt(0)}${String(props.patient.last_name || '').trim().charAt(0)}`)
const avatarStyle = computed(() => ({ '--avatar-size': `${Number(props.size) || 50}px` }))

watch(imageUrl, () => { failed.value = false })
</script>

<style scoped>
.patient-avatar{--level-color:#94a3b8;position:relative;width:var(--avatar-size);height:var(--avatar-size);flex:0 0 var(--avatar-size);display:inline-grid;place-items:center;overflow:visible;border:2px solid var(--level-color);border-radius:50%;background:#f1f5f9;box-shadow:0 0 0 3px color-mix(in srgb,var(--level-color) 18%,transparent);vertical-align:middle}
.patient-avatar.level-silver{--level-color:#94a3b8}.patient-avatar.level-gold{--level-color:#d4a72c}.patient-avatar.level-blue{--level-color:#3b82f6}.patient-avatar.level-problematic{--level-color:#dc2626}
.patient-avatar.has-crown::before{content:"♛";position:absolute;z-index:2;top:calc(var(--avatar-size) * -.34);left:50%;transform:translateX(-50%);color:#d4a72c;font-size:calc(var(--avatar-size) * .38);line-height:1;text-shadow:0 1px 0 #fff,0 2px 6px rgba(146,64,14,.3)}
.patient-avatar img{width:100%;height:100%;display:block;object-fit:cover;border-radius:inherit}
.patient-avatar__fallback{font-size:calc(var(--avatar-size) * .34);font-weight:900;color:#64748b;line-height:1}
.is-clickable{cursor:pointer;transition:transform .15s ease}
.is-clickable:hover{transform:translateY(-1px)}
</style>
