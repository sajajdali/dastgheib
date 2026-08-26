<template>
  <section class="automatic-sms-page" dir="rtl">
    <header class="page-heading">
      <div>
        <h1>پیامک اتوماتیک</h1>
        <p>سناریوهای پیگیری خودکار برای بیمارانی که پس از خدمت مراجعه نکرده‌اند</p>
      </div>
      <div class="page-heading-actions">
        <div class="active-project-label">پروژه فعال: <b>{{ activeProject?.name || 'هیچ پروژه‌ای فعال نیست' }}</b></div>
        <button type="button" class="new-project-btn" :disabled="savingProject" @click="createProject"><b>+</b> پروژه جدید</button>
      </div>
    </header>

    <nav class="project-tabs" aria-label="پروژه‌های پیامک">
      <button v-for="project in projects" :key="project.id" type="button" :class="{ selected: currentProject?.id === project.id }" @click="selectedProjectId = project.id">
        <i :class="{ active: project.is_active }"></i><span>{{ project.name }}</span><b>{{ fa(project.scenarios?.length || 0) }}</b>
      </button>
      <p v-if="!loading && !projects.length">با «پروژه جدید» نخستین پروژه را بسازید.</p>
    </nav>

    <template v-if="currentProject">
      <section class="project-panel">
        <div class="project-edit">
          <input v-model.trim="currentProject.name" aria-label="نام پروژه" @change="saveProject(currentProject)">
          <div class="project-state">
            <span>وضعیت پروژه</span><button type="button" class="switch" :class="{ on: currentProject.is_active }" :aria-label="currentProject.is_active ? 'غیرفعال کردن پروژه' : 'فعال کردن پروژه'" @click="toggleProject"><i></i></button><b :class="{ on: currentProject.is_active }">{{ currentProject.is_active ? 'فعال' : 'غیرفعال' }}</b>
          </div>
          <small>{{ fa(currentProject.scenarios?.length || 0) }} سناریو · {{ fa(activeScenarioCount) }} فعال</small>
        </div>
        <div class="project-tools">
          <input v-model.trim="search" type="search" placeholder="جستجوی سناریو یا خدمت…">
          <select v-model="tagFilter"><option value="all">همه خدمات</option><option v-for="tag in tags" :key="tag" :value="tag">{{ tag }}</option></select>
          <select v-model="statusFilter"><option value="all">همه وضعیت‌ها</option><option value="on">فعال</option><option value="off">غیرفعال</option></select>
          <button type="button" class="guide-btn" @click="guideOpen = true">؟ راهنمای پارامترها</button>
          <button type="button" class="new-scenario-btn" :disabled="creatingScenario || !tags.length" @click="createScenario"><b>+</b> سناریوی جدید</button>
        </div>
      </section>

      <p v-if="!tags.length" class="tag-warning">برای ساخت سناریو ابتدا یک خدمت در «تنظیمات انبار» تعریف کنید.</p>
      <p v-if="loading" class="empty-state">در حال دریافت اطلاعات…</p>
      <p v-else-if="!filteredScenarios.length" class="empty-state">سناریویی با این فیلترها پیدا نشد؛ یک سناریوی جدید بسازید.</p>

      <section v-else class="scenario-list">
        <article v-for="scenario in filteredScenarios" :key="scenario.id" class="scenario-card" :class="{ inactive: !scenario.is_active }">
          <header class="scenario-head">
            <div class="scenario-main-fields">
              <button type="button" class="switch" :class="{ on: scenario.is_active }" :aria-label="scenario.is_active ? 'غیرفعال کردن سناریو' : 'فعال کردن سناریو'" @click="toggleScenario(scenario)"><i></i></button>
              <select v-model="scenario.inventory_tag" aria-label="خدمت" @change="changeScenarioTag(scenario)"><option v-for="tag in tags" :key="tag" :value="tag">{{ tag }}</option></select>
              <span>{{ fa(scenario.steps.length) }} مرحله پیامک</span>
              <em v-if="savingScenarioIds.includes(scenario.id)">در حال ذخیره…</em>
            </div>
            <div class="scenario-actions"><button type="button" @click="copyScenario(scenario)">کپی سناریو</button><button type="button" class="delete" @click="deleteScenario(scenario)">حذف سناریو</button></div>
          </header>

          <div class="scenario-flow">
            <div class="flow-start"><b>انجام خدمت</b><span>شروع سناریو</span></div>
            <template v-for="(step, index) in scenario.steps" :key="step.id || index">
              <i class="flow-arrow">←</i>
              <div class="step-card">
                <div class="step-timing"><span>اگر نیامد،</span><input v-model.number="step.days_after" type="number" min="1" max="3650" @change="saveScenario(scenario)"><b>روز بعد</b><label>ساعت <input v-model="step.send_at" type="time" @change="saveScenario(scenario)"></label><button v-if="scenario.steps.length > 1" type="button" title="حذف مرحله" @click="removeStep(scenario, index)">×</button></div>
                <label class="template-label">نام الگوی پیامک <small>(انگلیسی، کوتاه)</small><button type="button" title="راهنمای پارامترها" @click="guideOpen = true">i</button></label>
                <input v-model.trim="step.sms_template" dir="ltr" class="template-input" placeholder="first_time_botax" @change="saveScenario(scenario)">
              </div>
              <button type="button" class="add-step" title="افزودن مرحله بعد" @click="addStep(scenario, index)">+</button>
            </template>
          </div>
        </article>
      </section>
    </template>

    <div v-if="guideOpen" class="guide-backdrop" @click.self="guideOpen = false"><section class="guide-modal"><header><div><h2>راهنمای پارامترها</h2><p>این مقادیر هنگام ارسال با اطلاعات بیمار جایگزین می‌شوند.</p></div><button type="button" @click="guideOpen = false">×</button></header><div class="params"><div><b>پارامتر ۱</b><span>نام بیمار</span><small>زهرا محمدی</small></div><div><b>پارامتر ۲</b><span>تاریخ خدمت</span><small>۱۴۰۵/۰۵/۲۵</small></div><div><b>پارامتر ۳</b><span>نام خدمت</span><small>بوتاکس پیشانی</small></div><div><b>پارامتر ۴</b><span>نام کلینیک</span><small>کلینیک</small></div></div></section></div>
  </section>
</template>

<script>
import Swal from 'sweetalert2'

const defaultStep = (days = 3) => ({ days_after: days, send_at: '10:00', sms_template: '' })

export default {
  name: 'AutomaticSmsScenarios',
  data: () => ({ projects: [], tags: [], selectedProjectId: null, search: '', tagFilter: 'all', statusFilter: 'all', loading: true, creatingScenario: false, savingProject: false, savingScenarioIds: [], guideOpen: false }),
  computed: {
    currentProject() { return this.projects.find(project => project.id === this.selectedProjectId) || this.projects[0] || null },
    activeProject() { return this.projects.find(project => project.is_active) || null },
    activeScenarioCount() { return (this.currentProject?.scenarios || []).filter(scenario => scenario.is_active).length },
    filteredScenarios() {
      const query = this.search.toLocaleLowerCase('fa')
      return (this.currentProject?.scenarios || []).filter(scenario => {
        const matchesSearch = !query || `${scenario.inventory_tag} ${scenario.name} ${scenario.steps.map(step => step.sms_template).join(' ')}`.toLocaleLowerCase('fa').includes(query)
        const matchesTag = this.tagFilter === 'all' || scenario.inventory_tag === this.tagFilter
        const matchesStatus = this.statusFilter === 'all' || (this.statusFilter === 'on') === Boolean(scenario.is_active)
        return matchesSearch && matchesTag && matchesStatus
      })
    }
  },
  mounted() { this.load() },
  methods: {
    fa(value) { return Number(value || 0).toLocaleString('fa-IR') },
    async request(url, options = {}) { const response = await fetch(url, { headers: { Accept: 'application/json', ...(options.headers || {}) }, ...options }); if (response.status === 204) return null; const payload = await response.json(); if (!response.ok) throw new Error(payload.message || 'ذخیره اطلاعات انجام نشد.'); return payload },
    async load(preferredId = this.selectedProjectId) {
      this.loading = true
      try { const data = await this.request('/api/automatic-sms-scenarios'); this.projects = (data.projects || []).map(this.decorateProject); this.tags = data.tags || []; this.selectedProjectId = this.projects.some(p => p.id === preferredId) ? preferredId : this.projects[0]?.id || null } catch (error) { await Swal.fire('خطا', error.message || 'دریافت اطلاعات انجام نشد.', 'error') } finally { this.loading = false }
    },
    async createProject() {
      this.savingProject = true
      try { const name = `پروژه ${this.fa(this.projects.length + 1)}`; const data = await this.request('/api/automatic-sms-projects', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, is_active: false }) }); this.projects.push(this.decorateProject(data.project)); this.selectedProjectId = data.project.id } catch (error) { await Swal.fire('خطا', error.message, 'error') } finally { this.savingProject = false }
    },
    async saveProject(project) {
      if (!project?.name) return
      this.savingProject = true
      try { const data = await this.request(`/api/automatic-sms-projects/${project.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: project.name, is_active: Boolean(project.is_active) }) }); if (data.project.is_active) this.projects.forEach(item => { if (item.id !== data.project.id) item.is_active = false }); this.replaceProject(data.project) } catch (error) { await Swal.fire('خطا', error.message, 'error'); await this.load(project.id) } finally { this.savingProject = false }
    },
    async toggleProject() {
      const project = this.currentProject
      if (project.is_active) {
        project.is_active = false
        await this.saveProject(project)
        return
      }
      const activeName = this.activeProject?.name
      if (!activeName) {
        project.is_active = true
        await this.saveProject(project)
        return
      }
      const answer = await Swal.fire({
        icon: 'warning',
        title: `پروژه «${activeName}» فعال است`,
        text: 'با فعال‌سازی این پروژه، سایر پروژه‌ها متوقف می‌شوند. تأیید می‌کنید؟',
        showCancelButton: true,
        confirmButtonText: 'تأیید و فعال‌سازی',
        cancelButtonText: 'انصراف',
        confirmButtonColor: '#2563eb'
      })
      if (!answer.isConfirmed) return
      project.is_active = true
      await this.saveProject(project)
    },
    decorateProject(project) { return { ...project, scenarios: (project.scenarios || []).map(scenario => ({ ...scenario, _savedInventoryTag: scenario.inventory_tag })) } },
    decorateScenario(scenario) { return { ...scenario, _savedInventoryTag: scenario.inventory_tag } },
    replaceProject(project) { const index = this.projects.findIndex(item => item.id === project.id); if (index >= 0) this.projects.splice(index, 1, this.decorateProject(project)) },
    scenarioPayload(scenario) { return { project_id: this.currentProject.id, name: scenario.name || scenario.inventory_tag, inventory_tag: scenario.inventory_tag, is_active: Boolean(scenario.is_active), steps: scenario.steps.map(step => ({ days_after: Math.max(1, Number(step.days_after) || 1), send_at: String(step.send_at || '10:00').slice(0, 5), sms_template: step.sms_template || '' })) } },
    async createScenario() {
      this.creatingScenario = true
      try {
        const usedTags = new Set(this.currentProject.scenarios.map(scenario => scenario.inventory_tag))
        const firstAvailableTag = this.tags.find(tag => !usedTags.has(tag)) || this.tags[0]
        const options = Object.fromEntries(this.tags.map(tag => [tag, usedTags.has(tag) ? `${tag} — قبلاً اضافه شده` : tag]))
        const choice = await Swal.fire({
          title: 'انتخاب خدمت',
          text: 'برای این خدمت یک سناریوی پیامک تعریف می‌شود.',
          input: 'select',
          inputOptions: options,
          inputValue: firstAvailableTag,
          showCancelButton: true,
          confirmButtonText: 'ساخت سناریو',
          cancelButtonText: 'انصراف',
          confirmButtonColor: '#2563eb'
        })
        if (!choice.isConfirmed) return
        const tag = choice.value
        if (usedTags.has(tag)) {
          await Swal.fire('سناریوی تکراری', `خدمت «${tag}» قبلاً در این پروژه اضافه شده است و نمی‌تواند دوباره اضافه شود.`, 'warning')
          return
        }
        const data = await this.request('/api/automatic-sms-scenarios', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.scenarioPayload({ name: tag, inventory_tag: tag, is_active: true, steps: [defaultStep()] })) })
        this.currentProject.scenarios.unshift(this.decorateScenario(data.scenario))
      } catch (error) { await Swal.fire('خطا', error.message, 'error') } finally { this.creatingScenario = false }
    },
    async saveScenario(scenario) {
      if (this.savingScenarioIds.includes(scenario.id)) return
      this.savingScenarioIds.push(scenario.id)
      try { const data = await this.request(`/api/automatic-sms-scenarios/${scenario.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.scenarioPayload(scenario)) }); const index = this.currentProject.scenarios.findIndex(item => item.id === scenario.id); if (index >= 0) this.currentProject.scenarios.splice(index, 1, this.decorateScenario(data.scenario)) } catch (error) { await Swal.fire('خطا', error.message, 'error'); await this.load(this.currentProject.id) } finally { this.savingScenarioIds = this.savingScenarioIds.filter(id => id !== scenario.id) }
    },
    async toggleScenario(scenario) { scenario.is_active = !scenario.is_active; await this.saveScenario(scenario) },
    addStep(scenario, index) { const days = Number(scenario.steps[index].days_after || 0) + 3; scenario.steps.splice(index + 1, 0, defaultStep(days)); this.saveScenario(scenario) },
    removeStep(scenario, index) { scenario.steps.splice(index, 1); this.saveScenario(scenario) },
    async changeScenarioTag(scenario) { const duplicate = this.currentProject.scenarios.some(item => item.id !== scenario.id && item.inventory_tag === scenario.inventory_tag); if (duplicate) { scenario.inventory_tag = scenario._savedInventoryTag; await Swal.fire('سناریوی تکراری', 'برای این خدمت در همین پروژه قبلاً سناریو تعریف شده است.', 'warning'); return } scenario.name = scenario.inventory_tag; await this.saveScenario(scenario) },
    async copyScenario(scenario) { await Swal.fire('کپی ممکن نیست', `برای خدمت «${scenario.inventory_tag}» در همین پروژه یک سناریو وجود دارد. هر خدمت فقط یک‌بار می‌تواند سناریو داشته باشد.`, 'info') },
    async deleteScenario(scenario) { const answer = await Swal.fire({ title: 'حذف سناریو؟', text: `سناریوی خدمت «${scenario.inventory_tag}» حذف می‌شود.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'حذف سناریو', cancelButtonText: 'انصراف', confirmButtonColor: '#dc2626' }); if (!answer.isConfirmed) return; try { await this.request(`/api/automatic-sms-scenarios/${scenario.id}`, { method: 'DELETE' }); this.currentProject.scenarios = this.currentProject.scenarios.filter(item => item.id !== scenario.id) } catch (error) { await Swal.fire('خطا', error.message, 'error') } }
  }
}
</script>

<style scoped>
.automatic-sms-page{min-height:calc(100vh - 128px);padding:24px 28px 64px;background:#f1f5f9;color:#0f172a}.page-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;flex-wrap:wrap;margin-bottom:21px}.page-heading h1{margin:0;font-size:30px;font-weight:900;letter-spacing:-.5px}.page-heading p{margin:7px 0 0;color:#64748b;font-size:14px}.page-heading-actions{display:flex;align-items:center;gap:10px}.active-project-label{padding:11px 16px;border-radius:16px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.06);color:#64748b;font-size:13px}.active-project-label b{color:#0f172a}.new-project-btn,.new-scenario-btn{display:flex;align-items:center;gap:8px;border:0;border-radius:16px;padding:12px 18px;background:#0f172a;color:#fff;font:800 14px inherit;cursor:pointer}.new-project-btn b,.new-scenario-btn b{font-size:18px;line-height:1}.project-tabs{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:18px}.project-tabs button{display:flex;align-items:center;gap:9px;border:0;border-radius:16px;padding:11px 18px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.06);color:#334155;font:700 14px inherit;cursor:pointer;transition:.15s}.project-tabs button.selected{background:#2563eb;box-shadow:0 6px 16px rgba(37,99,235,.25);color:#fff}.project-tabs i{width:8px;height:8px;border-radius:50%;background:#cbd5e1}.project-tabs i.active{background:#22c55e}.project-tabs button.selected i:not(.active){background:rgba(255,255,255,.45)}.project-tabs b{padding:2px 8px;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:12px}.project-tabs button.selected b{background:rgba(255,255,255,.2);color:#fff}.project-tabs p{margin:4px 0;color:#94a3b8;font-size:13px}.project-panel{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:20px;padding:20px 22px;border-radius:24px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.06)}.project-edit{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.project-edit>input{width:220px;max-width:100%;padding:4px 2px 6px;border:0;border-bottom:2px dashed #e2e8f0;background:transparent;color:#0f172a;font:900 20px inherit;outline:0}.project-edit small{color:#94a3b8;font-size:13px}.project-state{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px}.project-state>b{color:#94a3b8}.project-state>b.on{color:#16a34a}.switch{display:flex;justify-content:flex-end;width:46px;height:26px;padding:3px;border:0;border-radius:14px;background:#cbd5e1;cursor:pointer;transition:.15s}.switch.on{justify-content:flex-start;background:#22c55e}.switch i{display:block;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.25)}.project-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.project-tools input,.project-tools select,.scenario-main-fields select{height:43px;border:1px solid #e2e8f0;border-radius:14px;padding:0 13px;background:#f8fafc;color:#0f172a;font:inherit;outline:0}.project-tools input{min-width:210px;flex:1 1 210px}.project-tools select{cursor:pointer}.guide-btn{border:1px solid #e2e8f0;border-radius:14px;padding:11px 16px;background:#f8fafc;color:#334155;font:800 13px inherit;cursor:pointer}.new-scenario-btn{border-radius:14px;background:#2563eb}.new-project-btn:disabled,.new-scenario-btn:disabled{opacity:.55;cursor:wait}.tag-warning{margin:0 0 16px;padding:12px 15px;border:1px solid #fde68a;border-radius:15px;background:#fffbeb;color:#92400e;font-size:13px;font-weight:700}.scenario-list{display:grid;gap:18px}.scenario-card{padding:20px 22px;border:1px solid #dbeafe;border-radius:24px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.06);transition:opacity .15s}.scenario-card.inactive{border-color:#eef2f7;opacity:.68}.scenario-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding-bottom:16px;border-bottom:1px solid #f1f5f9}.scenario-main-fields{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.scenario-main-fields select{font-weight:800}.scenario-main-fields span{color:#94a3b8;font-size:13px}.scenario-main-fields em{color:#2563eb;font-size:11px;font-style:normal}.scenario-actions{display:flex;gap:8px}.scenario-actions button{border:1px solid #e2e8f0;border-radius:12px;padding:9px 14px;background:#f8fafc;color:#334155;font:700 13px inherit;cursor:pointer}.scenario-actions button.delete{border-color:#fecaca;background:#fff;color:#dc2626}.scenario-flow{display:flex;align-items:stretch;gap:12px;overflow-x:auto;padding:18px 2px 4px}.flow-start{display:flex;flex-direction:column;justify-content:center;min-width:108px;padding:0 6px}.flow-start b{font-size:13px}.flow-start span{margin-top:4px;color:#94a3b8;font-size:12px}.flow-arrow{display:flex;align-items:center;color:#cbd5e1;font-size:18px;font-style:normal}.step-card{min-width:292px;max-width:292px;padding:14px;border:1px solid #e8eef6;border-radius:18px;background:#f8fafc}.step-timing{display:flex;align-items:center;gap:6px}.step-timing span{color:#64748b;font-size:12px}.step-timing>input{width:52px;padding:6px 8px;border:1px solid #dbe3ee;border-radius:10px;background:#fff;color:#1d4ed8;text-align:center;font:800 14px inherit;outline:0}.step-timing>b{font-size:12px}.step-timing label{display:flex;align-items:center;gap:3px;margin-right:auto;color:#64748b;font-size:11px}.step-timing label input{width:68px;padding:5px 3px;border:1px solid #dbe3ee;border-radius:8px;background:#fff;font:700 11px inherit;outline:0}.step-timing>button{margin-right:auto;border:0;background:transparent;color:#cbd5e1;font-size:17px;cursor:pointer}.template-label{display:flex;align-items:center;gap:5px;margin:12px 0 6px;color:#94a3b8;font-size:11px}.template-label button{display:grid;place-items:center;width:16px;height:16px;padding:0;border:1px solid #cbd5e1;border-radius:50%;background:#fff;color:#64748b;font:800 10px Arial;cursor:pointer}.template-input{width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;color:#0f172a;font:600 13px inherit;outline:0;text-align:left}.add-step{align-self:center;width:34px;height:34px;border:1px dashed #bfdbfe;border-radius:50%;background:#eff6ff;color:#2563eb;font:800 18px inherit;cursor:pointer}.empty-state{display:grid;min-height:160px;place-items:center;margin:0;border-radius:24px;background:#fff;color:#64748b;font-size:14px;font-weight:700;box-shadow:0 1px 2px rgba(15,23,42,.06)}.guide-backdrop{position:fixed;z-index:3000;inset:0;display:grid;place-items:center;padding:30px;background:rgba(15,23,42,.45)}.guide-modal{width:min(560px,100%);overflow:auto;border-radius:26px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.28)}.guide-modal header{display:flex;justify-content:space-between;gap:16px;padding:24px 26px 18px;border-bottom:1px solid #f1f5f9}.guide-modal h2{margin:0;font-size:20px}.guide-modal p{margin:6px 0 0;color:#64748b;font-size:13px}.guide-modal header>button{width:34px;height:34px;border:0;border-radius:12px;background:#f1f5f9;color:#475569;font-size:16px;cursor:pointer}.params{padding:18px 26px 26px}.params>div{display:grid;grid-template-columns:110px 1fr 1.2fr;padding:13px 12px;border-radius:14px;background:#f8fafc;font-size:13px}.params>div:nth-child(even){background:#fff}.params b{color:#1d4ed8}.params span{font-weight:700}.params small{color:#64748b;font-size:13px}@media(max-width:760px){.automatic-sms-page{padding:18px}.page-heading,.page-heading-actions,.project-panel{align-items:stretch;flex-direction:column}.project-edit{align-items:stretch;flex-direction:column}.project-edit>input{width:100%}.project-tools{align-items:stretch;flex-direction:column}.project-tools input,.project-tools select,.guide-btn,.new-scenario-btn{width:100%}.scenario-head{align-items:stretch;flex-direction:column}.scenario-actions button{flex:1}.step-card{min-width:270px;max-width:270px}.params>div{grid-template-columns:1fr;gap:4px}}
</style>

<style scoped>
/* کارت سناریو هرگز با تعداد مراحل عریض نمی‌شود؛ فقط نوار مراحل اسکرول افقی دارد. */
.automatic-sms-page{width:100%;min-width:0;overflow-x:clip}.project-tabs,.scenario-flow{max-width:100%;scrollbar-width:thin;scrollbar-color:#bfdbfe transparent}.project-tabs::-webkit-scrollbar,.scenario-flow::-webkit-scrollbar{height:6px}.project-tabs::-webkit-scrollbar-thumb,.scenario-flow::-webkit-scrollbar-thumb{background:#bfdbfe;border-radius:99px}.scenario-card{min-width:0;overflow:hidden}.scenario-flow{min-width:0;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch;padding-bottom:12px}.step-card{flex:0 0 292px}.flow-start,.flow-arrow,.add-step{flex:none}

@media (max-width:900px){
  .automatic-sms-page{padding:20px 18px 48px}.page-heading{align-items:stretch}.page-heading-actions{margin-right:auto}.project-panel{align-items:stretch;flex-direction:column}.project-tools{width:100%}.project-tools input{min-width:180px}.scenario-head{align-items:stretch;flex-direction:column}.scenario-actions{justify-content:flex-end}
}

@media (max-width:600px){
  .automatic-sms-page{min-height:calc(100vh - 96px);padding:14px 12px 34px}.page-heading{gap:14px;margin-bottom:16px}.page-heading h1{font-size:24px}.page-heading p{font-size:12px;line-height:1.9}.page-heading-actions{width:100%;gap:8px}.active-project-label{flex:1;min-width:0;padding:10px 12px;font-size:11px;line-height:1.8}.active-project-label b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.new-project-btn{flex:none;min-height:44px;padding:10px 13px;border-radius:13px;font-size:12px;white-space:nowrap}.project-tabs{flex-wrap:nowrap;overflow-x:auto;margin:0 -12px 14px;padding:0 12px 7px}.project-tabs button{flex:none;padding:10px 13px;border-radius:13px;font-size:12px;white-space:nowrap}.project-panel{gap:14px;margin-bottom:14px;padding:15px;border-radius:18px}.project-edit{gap:10px}.project-edit>input{font-size:18px}.project-edit small{font-size:11px}.project-state{align-self:flex-start;padding:7px 10px;font-size:11px}.project-tools{display:grid;grid-template-columns:1fr 1fr;gap:8px}.project-tools input{grid-column:1/-1;width:100%;min-width:0;height:41px}.project-tools select{width:100%;min-width:0;height:41px;padding:0 8px;font-size:11px}.guide-btn{padding:9px 8px;font-size:11px;white-space:nowrap}.new-scenario-btn{grid-column:1/-1;justify-content:center;width:100%;min-height:43px;padding:10px 14px;font-size:13px}.tag-warning{margin-bottom:14px;padding:10px 12px;border-radius:12px;font-size:11px;line-height:1.9}.scenario-list{gap:13px}.scenario-card{padding:14px 12px;border-radius:18px}.scenario-head{gap:12px;padding-bottom:12px}.scenario-main-fields{gap:8px}.scenario-main-fields select{flex:1;min-width:0;max-width:100%;height:40px;padding:0 9px;font-size:12px}.scenario-main-fields span{width:100%;font-size:11px}.scenario-main-fields em{font-size:10px}.scenario-actions{display:grid;grid-template-columns:1fr 1fr;width:100%;gap:7px}.scenario-actions button{min-height:38px;padding:8px 6px;border-radius:10px;font-size:11px}.scenario-flow{gap:9px;margin:0 -2px;padding:14px 2px 12px}.flow-start{min-width:82px;padding:0 2px}.flow-start b{font-size:12px}.flow-start span{font-size:10px}.flow-arrow{font-size:16px}.step-card{flex-basis:min(272px,calc(100vw - 126px));min-width:min(272px,calc(100vw - 126px));max-width:min(272px,calc(100vw - 126px));padding:12px;border-radius:15px}.step-timing{flex-wrap:wrap;gap:5px}.step-timing label{margin-right:0}.step-timing>button{margin-right:auto}.template-label{margin-top:10px;font-size:10px}.template-input{min-height:40px;padding:8px 10px;font-size:12px}.add-step{width:31px;height:31px;font-size:17px}.empty-state{min-height:130px;border-radius:18px;padding:18px;text-align:center;font-size:12px}.guide-backdrop{align-items:end;padding:0}.guide-modal{width:100%;max-height:85vh;border-radius:22px 22px 0 0}.guide-modal header{padding:18px}.guide-modal h2{font-size:18px}.guide-modal p{font-size:11px;line-height:1.8}.params{padding:12px 18px 22px}.params>div{gap:3px;padding:10px;font-size:12px}.params small{font-size:11px}
}

@media (max-width:370px){
  .page-heading-actions{align-items:stretch;flex-direction:column}.new-project-btn{justify-content:center;width:100%}.project-tools{grid-template-columns:1fr}.new-scenario-btn{grid-column:auto}.step-card{flex-basis:220px;min-width:220px;max-width:220px}.step-timing label{width:100%;justify-content:flex-start}.step-timing>button{position:absolute}.step-card{position:relative}.step-timing>button{left:8px;top:7px}.template-label small{display:none}
}
</style>
