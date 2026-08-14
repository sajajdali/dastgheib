<template>
  <section class="service-tags-page" dir="rtl">
    <header class="service-tags-page-head">
      <div>
        <span>تنظیمات انبار</span>
        <h2>تگ‌های خدمات</h2>
        <p>تگ‌ها را برای اتصال کالاها و خدمات به پیگیری‌ها و پیامک لندینگ تعریف کنید.</p>
      </div>
      <button type="button" class="service-tags-back-btn" @click="$emit('back')">بازگشت به انبار</button>
    </header>

    <section class="service-tags-manager">
      <form class="service-tag-add" @submit.prevent="addTag">
        <label>
          <span>نام تگ</span>
          <input v-model.trim="tagDraft" type="text" placeholder="مثلاً بوتاکس پیشانی">
        </label>
        <label>
          <span>نام الگوی پیامک <small>اختیاری</small></span>
          <input v-model.trim="templateDraft" type="text" placeholder="نام الگوی SHSMS">
        </label>
        <button type="submit">+ افزودن تگ</button>
      </form>

      <div class="service-tag-toolbar">
        <label>
          <span>جست‌وجو</span>
          <input v-model.trim="search" type="search" placeholder="نام تگ را جست‌وجو کنید">
        </label>
        <strong>{{ filteredTags.length.toLocaleString('fa-IR') }} تگ</strong>
      </div>

      <div v-if="loading" class="service-tags-empty">در حال دریافت تگ‌ها...</div>
      <div v-else class="service-tag-list">
        <article v-for="tag in filteredTags" :key="tag.name" class="service-tag-card">
          <div>
            <strong>{{ tag.name }}</strong>
            <small>{{ tag.sms_template ? `الگوی پیامک: ${tag.sms_template}` : 'بدون الگوی پیامک' }}</small>
          </div>
          <div class="service-tag-actions">
            <button type="button" title="ویرایش" aria-label="ویرایش تگ" @click="openEditor(tag)">✎</button>
            <button type="button" title="حذف" aria-label="حذف تگ" @click="removeTag(tag.name)">×</button>
          </div>
        </article>
        <p v-if="!filteredTags.length" class="service-tags-empty">تگی مطابق جست‌وجوی شما پیدا نشد.</p>
      </div>

      <footer class="service-tags-footer">
        <span :class="{ dirty, error: messageError, success: message && !messageError }">
          {{ message || (dirty ? 'تغییرات تگ‌ها هنوز ذخیره نشده است.' : 'تگ‌های خدمات به‌روز هستند.') }}
        </span>
        <button type="button" :disabled="saving || !dirty" @click="saveTags">
          {{ saving ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
        </button>
      </footer>
    </section>

    <div v-if="editingTag" class="service-tag-modal-backdrop" @click.self="closeEditor">
      <section class="service-tag-edit-modal" role="dialog" aria-modal="true" aria-labelledby="service-tag-edit-title">
        <header>
          <div>
            <span>ویرایش تگ خدمات</span>
            <h3 id="service-tag-edit-title">{{ editingTag.originalName }}</h3>
          </div>
          <button type="button" aria-label="بستن" @click="closeEditor">×</button>
        </header>
        <label>نام تگ<input v-model.trim="editingTag.name" type="text"></label>
        <label>نام الگوی پیامک <small>اختیاری</small><input v-model.trim="editingTag.sms_template" type="text"></label>
        <p v-if="editError" class="service-tag-edit-error">{{ editError }}</p>
        <footer><button type="button" @click="closeEditor">انصراف</button><button type="button" @click="saveEdit">ثبت تغییر</button></footer>
      </section>
    </div>
  </section>
</template>

<script>
export default {
  name: 'ServiceTagsManager',
  emits: ['back', 'saved'],
  data() {
    return {
      tags: [],
      tagDraft: '',
      templateDraft: '',
      search: '',
      loading: true,
      saving: false,
      dirty: false,
      message: '',
      messageError: false,
      editingTag: null,
      editError: ''
    }
  },
  computed: {
    filteredTags() {
      const query = this.normalize(this.search)
      return query ? this.tags.filter(tag => this.normalize(tag.name).includes(query)) : this.tags
    }
  },
  mounted() {
    this.loadTags()
  },
  methods: {
    normalize(value) {
      return String(value || '').trim().replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ')
    },
    normalizeTags(payload) {
      const definitions = Array.isArray(payload?.tag_definitions)
        ? payload.tag_definitions
        : (Array.isArray(payload?.tags) ? payload.tags.map(name => ({ name, sms_template: '' })) : [])
      return definitions
        .map(tag => ({ name: this.normalize(tag?.name), sms_template: String(tag?.sms_template || '').trim() }))
        .filter(tag => tag.name)
        .sort((a, b) => a.name.localeCompare(b.name, 'fa'))
    },
    async loadTags() {
      this.loading = true
      try {
        const response = await fetch('/api/service-tags', { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('دریافت تگ‌های خدمات انجام نشد.')
        this.tags = this.normalizeTags(await response.json())
        this.dirty = false
      } catch (error) {
        this.messageError = true
        this.message = error.message || 'دریافت تگ‌های خدمات انجام نشد.'
      } finally {
        this.loading = false
      }
    },
    markDirty() {
      this.dirty = true
      this.message = ''
      this.messageError = false
    },
    addTag() {
      const name = this.normalize(this.tagDraft)
      if (!name) return
      if (this.tags.some(tag => this.normalize(tag.name) === name)) {
        this.messageError = true
        this.message = 'تگی با این نام قبلاً ثبت شده است.'
        return
      }
      this.tags.push({ name, sms_template: String(this.templateDraft || '').trim() })
      this.tags.sort((a, b) => a.name.localeCompare(b.name, 'fa'))
      this.tagDraft = ''
      this.templateDraft = ''
      this.markDirty()
    },
    removeTag(name) {
      const normalized = this.normalize(name)
      this.tags = this.tags.filter(tag => this.normalize(tag.name) !== normalized)
      this.markDirty()
    },
    openEditor(tag) {
      this.editingTag = { originalName: tag.name, name: tag.name, sms_template: tag.sms_template || '' }
      this.editError = ''
    },
    closeEditor() {
      this.editingTag = null
      this.editError = ''
    },
    saveEdit() {
      const draft = this.editingTag
      const name = this.normalize(draft?.name)
      if (!name) {
        this.editError = 'نام تگ را وارد کنید.'
        return
      }
      const original = this.normalize(draft.originalName)
      if (this.tags.some(tag => this.normalize(tag.name) === name && this.normalize(tag.name) !== original)) {
        this.editError = 'تگی با این نام قبلاً ثبت شده است.'
        return
      }
      const target = this.tags.find(tag => this.normalize(tag.name) === original)
      if (target) {
        target.name = name
        target.sms_template = String(draft.sms_template || '').trim()
        this.tags.sort((a, b) => a.name.localeCompare(b.name, 'fa'))
        this.markDirty()
      }
      this.closeEditor()
    },
    async saveTags() {
      this.saving = true
      this.message = ''
      this.messageError = false
      try {
        const response = await fetch('/api/service-tags', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ tags: this.tags })
        })
        const payload = await response.json()
        if (!response.ok) throw new Error(payload.message || 'ذخیره تگ‌ها انجام نشد.')
        this.tags = this.normalizeTags(payload)
        this.dirty = false
        this.message = 'تگ‌های خدمات ذخیره شد.'
        this.$emit('saved', this.tags)
      } catch (error) {
        this.messageError = true
        this.message = error.message || 'ذخیره تگ‌ها انجام نشد.'
      } finally {
        this.saving = false
      }
    }
  }
}
</script>

<style scoped>
.service-tags-page{min-height:calc(100vh - 130px);padding:26px 30px;border:1px solid #dbeafe;border-radius:24px;background:linear-gradient(145deg,#f8fbff,#eef6ff);box-sizing:border-box}.service-tags-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}.service-tags-page-head>div>span{color:#2563eb;font-size:11px;font-weight:900}.service-tags-page-head h2{margin:5px 0 7px;color:#172554;font-size:25px}.service-tags-page-head p{margin:0;color:#64748b;font-size:12px}.service-tags-back-btn{min-height:42px;padding:0 15px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;color:#475569;font-family:inherit;font-size:12px;font-weight:900;cursor:pointer}.service-tags-manager{display:grid;gap:14px;padding:20px;border:1px solid #dbeafe;border-radius:20px;background:#fff;box-shadow:0 14px 34px rgba(15,23,42,.06)}.service-tag-add{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:12px;align-items:end}.service-tag-add label,.service-tag-toolbar label{display:grid;gap:6px;color:#475569;font-size:11px;font-weight:900}.service-tag-add label span small{color:#94a3b8;font-weight:600}.service-tag-add input,.service-tag-toolbar input{box-sizing:border-box;width:100%;height:44px;padding:0 13px;border:1px solid #cbd5e1;border-radius:11px;background:#f8fafc;color:#172554;font-family:inherit;text-align:right}.service-tag-add input:focus,.service-tag-toolbar input:focus{outline:none;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.14)}.service-tag-add button,.service-tags-footer button{height:44px;padding:0 18px;border:0;border-radius:11px;background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;font-family:inherit;font-size:12px;font-weight:900;cursor:pointer;white-space:nowrap}.service-tag-toolbar{display:flex;align-items:end;gap:12px;padding-top:4px;border-top:1px solid #eef2f7}.service-tag-toolbar label{flex:1}.service-tag-toolbar strong{min-width:72px;height:44px;display:grid;place-items:center;border-radius:11px;background:#eff6ff;color:#1d4ed8;font-size:11px}.service-tag-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;min-height:140px;padding:12px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}.service-tag-card{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:68px;padding:11px 12px;border:1px solid #dbeafe;border-radius:13px;background:#fff}.service-tag-card>div:first-child{min-width:0;display:grid;gap:5px}.service-tag-card strong{overflow:hidden;color:#1e3a8a;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.service-tag-card small{overflow:hidden;color:#64748b;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.service-tag-actions{display:flex;gap:5px}.service-tag-actions button{display:grid;place-items:center;width:28px;height:28px;padding:0;border:0;border-radius:8px;font-size:15px;cursor:pointer}.service-tag-actions button:first-child{background:#dbeafe;color:#2563eb}.service-tag-actions button:last-child{background:#fee2e2;color:#dc2626}.service-tags-empty{display:grid;place-items:center;min-height:100px;margin:0;color:#64748b;font-size:12px;font-weight:800}.service-tags-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:2px}.service-tags-footer>span{color:#64748b;font-size:11px;font-weight:800}.service-tags-footer>span.dirty{color:#b45309}.service-tags-footer>span.success{color:#15803d}.service-tags-footer>span.error{color:#b91c1c}.service-tags-footer button:disabled{cursor:not-allowed;opacity:.55}.service-tag-modal-backdrop{position:fixed;z-index:3000;inset:0;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.48)}.service-tag-edit-modal{width:min(460px,94vw);padding:20px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.3)}.service-tag-edit-modal header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px}.service-tag-edit-modal header span{color:#2563eb;font-size:11px;font-weight:900}.service-tag-edit-modal h3{margin:5px 0;color:#172554}.service-tag-edit-modal header button{width:32px;height:32px;border:0;border-radius:9px;background:#f1f5f9;font-size:20px;cursor:pointer}.service-tag-edit-modal label{display:grid;gap:7px;margin-top:12px;color:#334155;font-size:12px;font-weight:900}.service-tag-edit-modal label small{color:#94a3b8;font-weight:500}.service-tag-edit-modal input{box-sizing:border-box;width:100%;height:42px;padding:0 11px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-family:inherit}.service-tag-edit-modal footer{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}.service-tag-edit-modal footer button{height:38px;padding:0 14px;border:0;border-radius:9px;background:#e2e8f0;color:#475569;font-family:inherit;font-weight:900;cursor:pointer}.service-tag-edit-modal footer button:last-child{background:#2563eb;color:#fff}.service-tag-edit-error{padding:9px;border-radius:8px;background:#fef2f2;color:#b91c1c;font-size:11px;font-weight:800}@media(max-width:700px){.service-tags-page{min-height:auto;padding:18px}.service-tags-page-head{align-items:stretch;flex-direction:column}.service-tag-add{grid-template-columns:1fr}.service-tag-toolbar{align-items:stretch;flex-direction:column}.service-tag-toolbar strong{width:100%}.service-tags-footer{align-items:stretch;flex-direction:column}.service-tags-footer button{width:100%}}
</style>
