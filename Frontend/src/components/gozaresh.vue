<template>
  <section class="reports-page" dir="rtl">
    <iframe
      class="reports-frame"
      src="/reports/index.html"
      title="گزارشات کلینیک"
      loading="eager"
      @load="handleFrameLoad"
    ></iframe>

    <div v-if="isLoading" class="reports-loading" role="status">
      <span class="reports-spinner"></span>
      در حال بارگذاری گزارشات...
    </div>
  </section>
</template>

<script>
export default {
  name: "Gozaresh",
  data() {
    return {
      isLoading: true,
      frameObserver: null,
    };
  },
  beforeUnmount() {
    this.frameObserver?.disconnect();
  },
  methods: {
    handleFrameLoad(event) {
      this.isLoading = false;

      const frame = event.currentTarget;
      const documentElement = frame.contentDocument?.documentElement;
      if (!documentElement) return;

      const syncHeight = () => {
        frame.style.height = `${documentElement.scrollHeight}px`;
      };

      this.frameObserver?.disconnect();
      this.frameObserver = new ResizeObserver(syncHeight);
      this.frameObserver.observe(documentElement);
      syncHeight();
    },
  },
};
</script>

<style scoped>
.reports-page {
  position: relative;
  width: 100%;
  min-height: calc(100vh - 150px);
  overflow: hidden;
  border-radius: 18px;
  background: #f1f4f9;
}

.reports-frame {
  display: block;
  width: 100%;
  min-height: calc(100vh - 150px);
  height: calc(100vh - 150px);
  border: 0;
  background: #f1f4f9;
}

.reports-loading {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: #475569;
  background: #f1f4f9;
  font-size: 14px;
  font-weight: 700;
}

.reports-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #dbe3ef;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: reports-spin 0.7s linear infinite;
}

@keyframes reports-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .reports-page,
  .reports-frame {
    min-height: calc(100vh - 120px);
  }
}
</style>
