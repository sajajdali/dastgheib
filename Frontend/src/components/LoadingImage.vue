<template>
  <span
    class="loading-image"
    :class="[
      `fit-${fit}`,
      {
        'is-loading': isLoading,
        'is-error': failed,
        'is-loaded': !isLoading && !failed,
        'is-dark': dark,
      },
    ]"
  >
    <span v-if="isLoading" class="loading-image__overlay" aria-hidden="true">
      <i class="loading-image__spinner" role="status" :aria-label="loadingLabel"></i>
      <em v-if="showLabel">{{ loadingLabel }}</em>
    </span>
    <img
      v-show="src && !failed"
      ref="imgEl"
      :key="src"
      :src="src || undefined"
      :alt="alt"
      :loading="lazy ? 'lazy' : undefined"
      :decoding="decoding"
      :class="imgClass"
      @load="onLoad"
      @error="onError"
    >
    <span v-if="failed" class="loading-image__error" role="status">بارگذاری نشد</span>
  </span>
</template>

<script>
export default {
  name: 'LoadingImage',
  props: {
    src: { type: String, default: '' },
    alt: { type: String, default: '' },
    fit: { type: String, default: 'contain' }, // contain | cover | fill
    lazy: { type: Boolean, default: false },
    decoding: { type: String, default: 'async' },
    dark: { type: Boolean, default: false },
    showLabel: { type: Boolean, default: false },
    loadingLabel: { type: String, default: 'در حال بارگذاری عکس' },
    imgClass: { type: [String, Object, Array], default: '' },
  },
  emits: ['load', 'error'],
  data() {
    return {
      loading: Boolean(this.src),
      failed: false,
    }
  },
  computed: {
    isLoading() {
      return Boolean(this.src) && this.loading && !this.failed
    },
  },
  watch: {
    src: {
      immediate: true,
      handler(value) {
        this.failed = false
        this.loading = Boolean(value)
        this.$nextTick(() => this.syncCachedState())
      },
    },
  },
  mounted() {
    this.syncCachedState()
  },
  methods: {
    syncCachedState() {
      const image = this.$refs.imgEl
      if (!this.src || !image) return
      if (image.complete && image.naturalWidth > 0) this.onLoad()
      else if (image.complete) this.onError()
    },
    onLoad(event) {
      this.loading = false
      this.failed = false
      this.$emit('load', event)
    },
    onError(event) {
      this.loading = false
      this.failed = true
      this.$emit('error', event)
    },
  },
}
</script>

<style scoped>
.loading-image {
  position: relative;
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  min-height: 0;
  overflow: hidden;
  background: #eef2f7;
}
.loading-image.is-dark { background: #0f172a; }
.loading-image img {
  width: 100%;
  height: 100%;
  min-height: 0;
  display: block;
  object-fit: contain;
}
.loading-image.fit-cover img { object-fit: cover; }
.loading-image.fit-fill img { object-fit: fill; }
.loading-image.is-loading img { opacity: 0; }
.loading-image__overlay {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: grid;
  place-content: center;
  gap: 8px;
  background: inherit;
  pointer-events: none;
}
.loading-image__spinner {
  width: 34px;
  height: 34px;
  border: 3px solid rgba(37, 99, 235, .22);
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: loading-image-spin .75s linear infinite;
}
.loading-image.is-dark .loading-image__spinner {
  border-color: rgba(255, 255, 255, .22);
  border-top-color: #fff;
}
.loading-image__overlay em {
  color: #64748b;
  font-size: 11px;
  font-style: normal;
  font-weight: 800;
  text-align: center;
}
.loading-image.is-dark .loading-image__overlay em { color: #cbd5e1; }
.loading-image__error {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 12px;
  color: #b91c1c;
  background: #fef2f2;
  font-size: 11px;
  font-weight: 900;
  text-align: center;
}
.loading-image.is-dark .loading-image__error {
  color: #fecaca;
  background: #450a0a;
}
@keyframes loading-image-spin {
  to { transform: rotate(360deg); }
}
</style>
