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

.activity-panel {
  margin-bottom: 14px;
  padding: 16px;
  border: 1px solid #dbe3ef;
  border-radius: 14px;
  background: #fff;
  color: #0f172a;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.activity-panel header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.activity-panel small {
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}

.activity-panel h3 {
  margin: 3px 0 0;
  font-size: 17px;
}

.activity-panel button {
  height: 38px;
  border: 0;
  border-radius: 9px;
  padding: 0 14px;
  background: #2563eb;
  color: #fff;
  font-family: inherit;
  font-weight: 900;
  cursor: pointer;
}

.activity-panel button:disabled {
  opacity: 0.55;
  cursor: wait;
}

.activity-filters {
  display: grid;
  grid-template-columns: minmax(240px, 1.4fr) minmax(150px, 0.8fr) minmax(190px, 1fr) auto;
  gap: 8px;
  margin-bottom: 12px;
}

.activity-filters input,
.activity-filters select {
  height: 38px;
  min-width: 0;
  border: 1px solid #dbe3ef;
  border-radius: 9px;
  padding: 0 10px;
  background: #fff;
  font-family: inherit;
  font-size: 12px;
  outline: none;
}

.activity-error {
  margin-bottom: 10px;
  padding: 10px;
  border-radius: 9px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 12px;
  font-weight: 800;
}

.activity-table-wrap {
  max-height: 360px;
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 11px;
}

.activity-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  font-size: 12px;
}

.activity-table th,
.activity-table td {
  border-bottom: 1px solid #eef2f7;
  padding: 9px;
  vertical-align: top;
  text-align: right;
}

.activity-table th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f8fafc;
  color: #475569;
  font-size: 11px;
}

.activity-table pre {
  max-width: 420px;
  margin: 0;
  white-space: pre-wrap;
  color: #475569;
  font-family: inherit;
  font-size: 11px;
  line-height: 1.8;
}

.event-badge {
  display: inline-flex;
  align-items: center;
  min-width: 54px;
  justify-content: center;
  border-radius: 999px;
  padding: 4px 8px;
  background: #eef2ff;
  color: #3730a3;
  font-size: 10px;
  font-weight: 900;
}

.event-deleted,
.event-sms_failed {
  background: #fee2e2;
  color: #b91c1c;
}

.event-created,
.event-sms_sent {
  background: #dcfce7;
  color: #047857;
}

.event-updated,
.event-role_permissions_updated {
  background: #fef3c7;
  color: #92400e;
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

  .activity-filters {
    grid-template-columns: 1fr;
  }
}
</style>
