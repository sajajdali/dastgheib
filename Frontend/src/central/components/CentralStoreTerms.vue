<template>
  <section class="central-view central-stack">
    <form class="central-panel central-stack" @submit.prevent="save">
      <div class="central-panel-head">
        <div>
          <div class="central-section-title">قوانین و مقررات</div>
          <div class="central-section-subtitle">متنی که خریدار قبل از پرداخت فروشگاه باید بخواند و تایید کند</div>
        </div>
        <span v-if="terms?.updated_at" class="central-badge">آخرین ویرایش: {{ terms.updated_at }}</span>
      </div>

      <label class="central-form-label">
        متن قوانین فروشگاه
        <textarea
          v-model.trim="content"
          class="central-input central-terms-textarea"
          rows="14"
          required
        />
      </label>

      <label class="central-check">
        <input v-model="isActive" type="checkbox" />
        این قوانین در فروشگاه فعال باشد
      </label>

      <p v-if="error" class="central-error">{{ error }}</p>
      <p v-if="message" class="central-message">{{ message }}</p>

      <div class="central-actions">
        <button class="central-button" type="submit" :disabled="saving">ذخیره قوانین</button>
        <button class="central-button secondary" type="button" :disabled="saving" @click="reset">بازگردانی متن فعلی</button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  terms: { type: Object, default: null },
  saving: { type: Boolean, default: false },
  error: { type: String, default: "" },
  message: { type: String, default: "" },
});

const emit = defineEmits(["save"]);

const content = ref("");
const isActive = ref(true);

watch(
  () => props.terms,
  reset,
  { immediate: true },
);

function reset() {
  content.value = props.terms?.content || "";
  isActive.value = props.terms?.is_active !== false;
}

function save() {
  emit("save", {
    content: content.value,
    is_active: isActive.value,
  });
}
</script>

<style scoped>
.central-terms-textarea {
  min-height: 320px;
  padding: 14px;
  resize: vertical;
  line-height: 2;
}
</style>
