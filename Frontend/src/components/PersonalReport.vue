<template>
  <div class="pr-shell" dir="rtl">
    <header class="pr-head">
      <div class="pr-identity">
        <img v-if="report?.resource?.avatar_url" :src="report.resource.avatar_url" alt="">
        <span v-else>{{ initial }}</span>
        <div><small>پنل کارکرد شخصی</small><h1>{{ report?.resource?.name || 'گزارش شخصی' }}</h1></div>
      </div>
      <div class="pr-head-actions">
        <button class="pr-notif-btn" type="button" title="اعلان‌ها" @click="notificationOpen = !notificationOpen"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><b v-if="unreadCount">{{ fa(unreadCount) }}</b></button>
        <button class="pr-close" type="button" title="بستن" @click="$emit('close')"><svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
      </div>
    </header>

    <aside v-if="notificationOpen" class="pr-notifications">
      <header><strong>اعلان‌ها</strong><button type="button" @click="readAllNotifications">خواندن همه</button></header>
      <button v-for="item in notifications" :key="item.id" type="button" :class="{ unread: !item.read_at }" @click="openNotification(item)">
        <b>{{ item.title }}</b><span>{{ item.body || 'برای مشاهده جزئیات کلیک کنید.' }}</span><small>{{ dateTime(item.created_at) }}</small>
      </button>
      <p v-if="!notifications.length">اعلان جدیدی ندارید.</p>
    </aside>

    <section class="pr-filter">
      <div class="pr-presets">
        <button v-for="item in presets" :key="item.key" type="button" :class="{ active: preset === item.key }" @click="applyPreset(item.key)">{{ item.label }}</button>
      </div>
      <div v-if="preset === 'custom'" class="pr-custom-range">
        <label>از<input v-model.trim="from" type="text" placeholder="۱۴۰۵-۰۱-۰۱"></label>
        <label>تا<input v-model.trim="to" type="text" placeholder="۱۴۰۵-۰۱-۳۱"></label>
        <button type="button" @click="loadReport">اعمال بازه</button>
      </div>
      <div v-else-if="preset === 'month' || preset === 'previous'" class="pr-month-nav">
        <button type="button" @click="shiftMonth(-1)">‹</button><strong>{{ monthLabel(reportMonth) }}</strong><button type="button" :disabled="reportMonth >= currentMonth" @click="shiftMonth(1)">›</button>
      </div>
      <small v-if="report?.range">{{ report.range.from }} تا {{ report.range.to }}</small>
    </section>

    <nav class="pr-tabs">
      <button v-for="item in tabs" :key="item.key" type="button" :class="{ active: tab === item.key }" @click="selectTab(item.key)"><i><svg viewBox="0 0 24 24"><path :d="iconPath(item.key)"/></svg></i><span>{{ item.label }}</span></button>
    </nav>

    <main class="pr-main">
      <div v-if="loading" class="pr-state"><i></i><span>در حال محاسبه گزارش...</span></div>
      <div v-else-if="error" class="pr-state error"><b>!</b><span>{{ error }}</span><button type="button" @click="loadAll">تلاش دوباره</button></div>
      <template v-else-if="report">
        <section v-if="tab === 'summary'" class="pr-summary">
          <article class="pr-total"><span>مبلغ نهایی قابل پرداخت</span><strong>{{ money(report.summary.total_earned) }}</strong><small>تومان</small></article>
          <div class="pr-card-grid">
            <article v-for="card in summaryCards" :key="card.key" :class="cardClass(card.value)"><i><svg viewBox="0 0 24 24"><path :d="iconPath(card.key)"/></svg></i><span>{{ card.label }}</span><strong>{{ signedMoney(card.value) }}</strong></article>
          </div>
          <div class="pr-mini-stats">
            <article><span>ساعت کارکرد</span><b>{{ fa(report.attendance.totals.worked_hours) }}</b></article>
            <article><span>اضافه‌کاری</span><b>{{ fa(report.attendance.totals.overtime_hours) }}</b></article>
            <article><span>کسری ساعت</span><b>{{ fa(report.attendance.totals.shortage_hours) }}</b></article>
            <article><span>غیبت غیرمجاز</span><b>{{ fa(report.attendance.totals.absence_days) }}</b></article>
          </div>
        </section>

        <section v-else-if="tab === 'commissions'" class="pr-panel">
          <header><div><small>ریز کارکرد</small><h2>پورسانت و خدمات</h2></div><b>{{ fa(report.commission_lines.length) }} ردیف</b></header>
          <div class="pr-table-wrap"><table><thead><tr><th v-for="key in report.columns" :key="key">{{ report.column_options[key] || key }}</th><th>جزئیات</th></tr></thead>
            <tbody><tr v-for="line in report.commission_lines" :key="line.id"><td v-for="key in report.columns" :key="key">{{ lineValue(line, key) }}</td><td><button class="pr-info" type="button" @click="detailLine = line">i</button></td></tr><tr v-if="!report.commission_lines.length"><td :colspan="report.columns.length + 1">در این بازه پورسانتی ثبت نشده است.</td></tr></tbody>
            <tfoot v-if="report.commission_lines.length"><tr><td v-for="key in report.columns" :key="key">{{ columnTotal(key) }}</td><td></td></tr></tfoot>
          </table></div>
        </section>

        <section v-else-if="tab === 'attendance'" class="pr-panel">
          <header><div><small>حضور و غیاب</small><h2>کارکرد روزانه</h2></div></header>
          <div class="pr-attendance-totals"><article><span>کارکرد</span><b>{{ fa(report.attendance.totals.worked_hours) }} ساعت</b></article><article><span>اضافه‌کاری</span><b>{{ fa(report.attendance.totals.overtime_hours) }} ساعت</b></article><article><span>کسری</span><b>{{ fa(report.attendance.totals.shortage_hours) }} ساعت</b></article></div>
          <div class="pr-table-wrap"><table><thead><tr><th>تاریخ</th><th>ورود</th><th>خروج</th><th>موظفی</th><th>کارکرد</th><th>اختلاف</th><th>وضعیت</th><th>نرخ</th><th>اثر مالی</th></tr></thead><tbody>
            <tr v-for="day in report.attendance.days" :key="day.date"><td>{{ day.date }}</td><td>{{ day.in || '—' }}</td><td>{{ day.out || '—' }}</td><td>{{ fa(day.daily_hours) }}</td><td>{{ fa(day.worked_hours) }}</td><td :class="cardClass(day.diff)">{{ signed(day.diff) }}</td><td>{{ attendanceStatus(day) }}</td><td>{{ day.rate ? `${money(day.rate)} تومان` : '—' }}</td><td :class="cardClass(day.amount)">{{ signedMoney(day.amount) }}</td></tr>
            <tr v-if="!report.attendance.days.length"><td colspan="9">اطلاعات حضوری برای این بازه ثبت نشده است.</td></tr></tbody></table></div>
        </section>

        <section v-else-if="tab === 'adjustments'" class="pr-panel">
          <header><div><small>پاداش و کسورات</small><h2>تعدیلات ثبت‌شده</h2></div></header>
          <div class="pr-list"><article v-for="item in report.adjustments" :key="item.id" :class="cardClass(item.amount)"><i>{{ Number(item.amount) >= 0 ? '＋' : '−' }}</i><div><b>{{ item.title || item.reason }}</b><span>{{ item.occurred_on || item.month }} · {{ item.description || 'بدون توضیح' }}</span><small>ثبت‌کننده: {{ item.created_by_name || 'سیستم' }} · {{ item.status === 'active' ? 'اعمال‌شده در حقوق' : item.status }}</small></div><strong>{{ signedMoney(item.amount) }}</strong></article><p v-if="!report.adjustments.length">پاداش یا کسری دستی در این بازه ثبت نشده است.</p></div>
        </section>

        <section v-else-if="tab === 'leaves'" class="pr-panel">
          <header><div><small>درخواست‌ها</small><h2>مرخصی‌های من</h2></div><button class="pr-primary" type="button" @click="leaveFormOpen = !leaveFormOpen">+ درخواست مرخصی</button></header>
          <form v-if="leaveFormOpen" class="pr-form" @submit.prevent="submitLeave">
            <label>نوع<select v-model="leaveForm.leave_type"><option value="daily">روزانه</option><option value="hourly">ساعتی</option></select></label>
            <label>تاریخ شروع<input v-model.trim="leaveForm.start_date" required placeholder="۱۴۰۵-۰۱-۰۱"></label>
            <label v-if="leaveForm.leave_type === 'daily'">تاریخ پایان<input v-model.trim="leaveForm.end_date" required placeholder="۱۴۰۵-۰۱-۰۱"></label>
            <template v-else><label>از ساعت<input v-model="leaveForm.start_time" type="time" required></label><label>تا ساعت<input v-model="leaveForm.end_time" type="time" required></label></template>
            <label class="wide">علت<textarea v-model.trim="leaveForm.reason" rows="2"></textarea></label><button class="pr-primary" :disabled="saving" type="submit">ارسال درخواست</button>
          </form>
          <div class="pr-list"><article v-for="item in leaves" :key="item.id"><i>◷</i><div><b>{{ item.leave_type === 'hourly' ? 'مرخصی ساعتی' : 'مرخصی روزانه' }}</b><span>{{ leaveRange(item) }} · {{ item.reason || 'بدون توضیح' }}</span><small v-if="item.manager_note">نظر مدیر: {{ item.manager_note }}</small></div><em :class="`status-${item.status}`">{{ statusLabel(item.status) }}</em><button v-if="item.status === 'pending'" type="button" @click="cancelLeave(item)">لغو</button></article><p v-if="!leaves.length">درخواست مرخصی ثبت نشده است.</p></div>
        </section>

        <section v-else-if="tab === 'advances'" class="pr-panel">
          <header><div><small>درخواست‌ها</small><h2>مساعده‌های من</h2></div><button class="pr-primary" type="button" @click="advanceFormOpen = !advanceFormOpen">+ درخواست مساعده</button></header>
          <form v-if="advanceFormOpen" class="pr-form" @submit.prevent="submitAdvance"><label>مبلغ درخواستی<input v-model="advanceForm.requested_amount" type="number" min="1" required></label><label class="wide">توضیحات<textarea v-model.trim="advanceForm.reason" rows="2"></textarea></label><button class="pr-primary" :disabled="saving" type="submit">ارسال درخواست</button></form>
          <div class="pr-list"><article v-for="item in advances" :key="item.id"><i>﷼</i><div><b>{{ money(item.approved_amount || item.requested_amount) }} تومان</b><span>{{ item.reason || 'بدون توضیح' }}</span><small v-if="item.installments?.length">{{ fa(item.installments.length) }} قسط از {{ item.deduction_start_month }}</small><small v-if="item.manager_note">نظر مدیر: {{ item.manager_note }}</small></div><em :class="`status-${item.status}`">{{ statusLabel(item.status) }}</em><button v-if="item.status === 'pending'" type="button" @click="cancelAdvance(item)">لغو</button></article><p v-if="!advances.length">درخواست مساعده ثبت نشده است.</p></div>
        </section>

        <section v-else-if="tab === 'messages'" class="pr-panel pr-chat">
          <header><div><small>ارتباط با مدیریت</small><h2>پیام‌ها</h2></div></header>
          <div ref="messageList" class="pr-message-list"><article v-for="message in messages" :key="message.id" :class="{ mine: message.sender_user_id === currentUserId }"><small>{{ message.sender_name }} · {{ dateTime(message.created_at) }}</small><p v-if="message.body">{{ message.body }}</p><img v-if="message.image_url" :src="message.image_url" alt="تصویر پیام"><audio v-if="message.voice_url" :src="message.voice_url" controls></audio></article><p v-if="!messages.length" class="pr-empty-chat">هنوز پیامی ردوبدل نشده است.</p></div>
          <form class="pr-message-form" @submit.prevent="sendMessage"><textarea v-model.trim="messageText" rows="2" placeholder="پیام خود را بنویسید..."></textarea><label title="ارسال تصویر">🖼<input ref="imageInput" type="file" accept="image/*" @change="messageImage = $event.target.files[0]"></label><label title="انتخاب فایل صوتی">🎵<input ref="voiceInput" type="file" accept="audio/*" @change="messageVoice = $event.target.files[0]"></label><button type="button" :class="{ recording }" title="ضبط صدا" @click="toggleRecording">{{ recording ? '■' : '🎙' }}</button><button class="pr-primary" :disabled="saving" type="submit">ارسال</button><small v-if="messageImage || messageVoice">{{ messageImage?.name || messageVoice?.name }}</small></form>
        </section>
      </template>
    </main>

    <div v-if="detailLine" class="pr-detail-backdrop" @click.self="detailLine = null"><section><header><div><small>جزئیات محاسبه</small><h3>{{ detailLine.service }}</h3></div><button type="button" @click="detailLine = null">×</button></header><div class="pr-detail-grid"><article><span>تاریخ</span><b>{{ detailLine.date }}</b></article><article><span>مبلغ ناخالص</span><b>{{ money(detailLine.gross_amount) }}</b></article><article><span>تخفیف</span><b>{{ money(detailLine.discount_amount) }}</b></article><article><span>مبنای پورسانت</span><b>{{ money(detailLine.commission_base) }}</b></article><article><span>نرخ</span><b>{{ detailLine.commission_rate }}</b></article><article><span>پورسانت نهایی</span><b>{{ money(detailLine.commission_amount) }}</b></article></div><p>{{ detailLine.description || 'توضیح دیگری ثبت نشده است.' }}</p></section></div>
  </div>
</template>

<script>
import axios from 'axios'
import Swal from 'sweetalert2'

export default {
  name: 'PersonalReport',
  props: {
    currentMonth: { type: String, required: true },
    currentUserId: { type: Number, default: null },
    initialTab: { type: String, default: 'summary' },
  },
  emits: ['close'],
  data() { return {
    report: null, loading: true, saving: false, error: '', tab: this.initialTab, preset: 'month', reportMonth: this.currentMonth,
    from: '', to: '', detailLine: null, leaves: [], advances: [], messages: [], notifications: [], unreadCount: 0,
    notificationOpen: false, leaveFormOpen: false, advanceFormOpen: false,
    leaveForm: { leave_type: 'daily', start_date: '', end_date: '', start_time: '09:00', end_time: '12:00', reason: '' },
    advanceForm: { requested_amount: '', reason: '' }, messageText: '', messageImage: null, messageVoice: null,
    recording: false, mediaRecorder: null, recordedChunks: [], messageTimer: null,
  }},
  computed: {
    initial() { return (this.report?.resource?.name || 'ک').trim().charAt(0) },
    tabs() { return [
      { key:'summary', label:'خلاصه' }, { key:'commissions', label:'پورسانت' },
      { key:'attendance', label:'حضور' }, { key:'adjustments', label:'پاداش‌ها' },
      { key:'leaves', label:'مرخصی' }, { key:'advances', label:'مساعده' },
      { key:'messages', label:'پیام‌ها' },
    ]},
    presets() { return [{key:'month',label:'ماه جاری'},{key:'previous',label:'ماه قبل'},{key:'three',label:'۳ ماه اخیر'},{key:'six',label:'۶ ماه اخیر'},{key:'custom',label:'بازه دلخواه'}] },
    summaryCards() { const s=this.report.summary; return [
      {key:'salary',label:'حقوق ثابت',icon:'▣',value:s.salary},{key:'service_commission',label:'پورسانت خدمات',icon:'٪',value:s.service_commission},
      {key:'inventory_commission',label:'پورسانت آیتم‌ها',icon:'◇',value:s.inventory_commission},{key:'referral_commission',label:'پورسانت معرفی',icon:'↗',value:s.referral_commission},
      {key:'sales_bonus',label:'پاداش فروش',icon:'★',value:s.sales_bonus},{key:'manual_bonus',label:'پاداش دستی',icon:'＋',value:s.manual_bonus},
      {key:'overtime',label:'اضافه‌کاری',icon:'↑',value:s.overtime},{key:'shortage',label:'کسری ساعت',icon:'↓',value:s.shortage},
      {key:'absence',label:'کسر غیبت',icon:'!',value:s.absence},{key:'manual_deduction',label:'کسورات دیگر',icon:'−',value:s.manual_deduction},
      {key:'advance_deduction',label:'کسر مساعده',icon:'﷼',value:s.advance_deduction},
    ]},
  },
  mounted() { this.loadAll(); if (this.tab === 'messages') this.loadMessages(); this.messageTimer = window.setInterval(() => { if (this.tab === 'messages') this.loadMessages(false); this.loadNotifications() }, 20000) },
  beforeUnmount() { window.clearInterval(this.messageTimer); if (this.recording) this.mediaRecorder?.stop() },
  methods: {
    fa(v){ return Number(v||0).toLocaleString('fa-IR') }, money(v){ return Number(v||0).toLocaleString('fa-IR') },
    signed(v){ const n=Number(v||0); return `${n>0?'+':''}${this.fa(n)}` }, signedMoney(v){ return `${this.signed(v)} تومان` },
    cardClass(v){ return Number(v||0)>0?'positive':Number(v||0)<0?'negative':'neutral' },
    monthLabel(month){ if(!month)return ''; const names=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند']; const [y,m]=month.split('-').map(Number); return `${names[m-1]} ${this.fa(y)}` },
    shiftMonth(delta){ const [y,m]=this.reportMonth.split('-').map(Number); const idx=y*12+m-1+delta; const next=`${Math.floor(idx/12)}-${String(idx%12+1).padStart(2,'0')}`; if(next>this.currentMonth)return; this.reportMonth=next; this.preset='month'; this.loadReport() },
    monthOffset(month,delta){ const [y,m]=month.split('-').map(Number), idx=y*12+m-1+delta; return `${Math.floor(idx/12)}-${String(idx%12+1).padStart(2,'0')}` },
    daysInMonth(month){ const [y,m]=month.split('-').map(Number); if(m<=6)return 31;if(m<=11)return 30;return [1,5,9,13,17,22,26,30].includes((y+12)%33)?30:29 },
    applyPreset(key){ this.preset=key; if(key==='previous')this.reportMonth=this.monthOffset(this.currentMonth,-1); else if(key==='month')this.reportMonth=this.currentMonth; else if(key==='three'){this.from=`${this.monthOffset(this.currentMonth,-2)}-01`;this.to=`${this.currentMonth}-${this.daysInMonth(this.currentMonth)}`} else if(key==='six'){this.from=`${this.monthOffset(this.currentMonth,-5)}-01`;this.to=`${this.currentMonth}-${this.daysInMonth(this.currentMonth)}`} if(key!=='custom')this.loadReport() },
    reportParams(){ return ['month','previous'].includes(this.preset)?{month:this.reportMonth}:{from:this.from,to:this.to} },
    async loadAll(){ await Promise.all([this.loadReport(),this.loadLeaves(),this.loadAdvances(),this.loadNotifications()]) },
    async loadReport(){ this.loading=true;this.error='';try{this.report=(await axios.get('/api/personal-report',{params:this.reportParams()})).data}catch(e){this.error=this.err(e,'دریافت گزارش ممکن نشد.')}finally{this.loading=false} },
    async loadLeaves(){ try{this.leaves=(await axios.get('/api/employee-portal/leaves')).data.requests||[]}catch{} },
    async loadAdvances(){ try{this.advances=(await axios.get('/api/employee-portal/advances')).data.advances||[]}catch{} },
    async loadMessages(scroll=true){ try{this.messages=(await axios.get('/api/employee-portal/messages')).data.messages||[];if(scroll)this.$nextTick(()=>{const el=this.$refs.messageList;if(el)el.scrollTop=el.scrollHeight})}catch(e){if(scroll)this.toast(this.err(e,'دریافت پیام‌ها ممکن نشد.'),'error')} },
    async loadNotifications(){ try{const d=(await axios.get('/api/employee-portal/notifications')).data;this.notifications=d.notifications||[];this.unreadCount=d.unread_count||0}catch{} },
    selectTab(tab){ this.tab=tab;if(tab==='messages')this.loadMessages() },
    lineValue(line,key){ const moneyKeys=['gross_amount','discount_amount','net_amount','commission_base','commission_amount']; if(moneyKeys.includes(key))return `${this.money(line[key])} تومان`;return line[key]??'—' },
    columnTotal(key){ const sumKeys=['quantity','gross_amount','discount_amount','net_amount','commission_base','commission_amount']; if(!sumKeys.includes(key))return key===this.report.columns[0]?'جمع کل':'';const n=this.report.commission_lines.reduce((s,l)=>s+Number(l[key]||0),0);return key==='quantity'?this.fa(n):`${this.money(n)} تومان` },
    attendanceStatus(day){ if(day.leave_approved)return 'مرخصی تأییدشده';if(day.absent)return 'غیبت';if(day.diff>0)return day.employer_approved?'اضافه‌کاری تأییدشده':'اضافه‌کاری در انتظار';if(day.diff<0)return 'کسری ساعت';return 'عادی' },
    iconPath(key){return ({
      summary:'M4 13h6V4H4zm10 7h6V11h-6zM4 20h6v-3H4zm10-13h6V4h-6z',
      commissions:'M12 2v20M17 6.5c0-1.4-2.2-2.5-5-2.5S7 5.1 7 6.5 9.2 9 12 9s5 1.1 5 2.5S14.8 14 12 14s-5 1.1-5 2.5S9.2 19 12 19s5-1.1 5-2.5',
      attendance:'M12 7v5l3 2M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9',
      adjustments:'m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z',
      leaves:'M6 3v3M18 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1m4 9 2 2 4-4',
      advances:'M3 7h18v12H3zM16 11h5M6 7V5h12v2',
      messages:'M4 5h16v12H8l-4 4z',
      salary:'M3 7h18v12H3zM16 11h5M6 7V5h12v2',service_commission:'M12 2v20M7 7h10M7 17h10',inventory_commission:'M4 7 12 3l8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10',referral_commission:'M5 12h12M13 7l5 5-5 5',sales_bonus:'m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z',manual_bonus:'M12 5v14M5 12h14',overtime:'M12 19V5m-5 5 5-5 5 5',shortage:'M12 5v14m5-5-5 5-5-5',absence:'M12 8v5m0 4h.01M10 3h4l8 17H2z',manual_deduction:'M5 12h14',advance_deduction:'M3 7h18v12H3zM16 11h5'
    }[key]||'M5 4h14v16H5z')},
    async submitLeave(){ this.saving=true;try{const p={...this.leaveForm};if(p.leave_type==='hourly')p.end_date=p.start_date;await axios.post('/api/employee-portal/leaves',p);this.leaveFormOpen=false;this.leaveForm={leave_type:'daily',start_date:'',end_date:'',start_time:'09:00',end_time:'12:00',reason:''};await this.loadLeaves();this.toast('درخواست مرخصی ارسال شد.')}catch(e){this.toast(this.err(e,'ثبت درخواست ممکن نشد.'),'error')}finally{this.saving=false} },
    async cancelLeave(item){ if(!(await this.confirm('درخواست مرخصی لغو شود؟')))return;await axios.patch(`/api/employee-portal/leaves/${item.id}/cancel`);await this.loadLeaves() },
    async submitAdvance(){ this.saving=true;try{await axios.post('/api/employee-portal/advances',this.advanceForm);this.advanceFormOpen=false;this.advanceForm={requested_amount:'',reason:''};await this.loadAdvances();this.toast('درخواست مساعده ارسال شد.')}catch(e){this.toast(this.err(e,'ثبت درخواست ممکن نشد.'),'error')}finally{this.saving=false} },
    async cancelAdvance(item){ if(!(await this.confirm('درخواست مساعده لغو شود؟')))return;await axios.patch(`/api/employee-portal/advances/${item.id}/cancel`);await this.loadAdvances() },
    async sendMessage(){ this.saving=true;try{const fd=new FormData();if(this.messageText)fd.append('body',this.messageText);if(this.messageImage)fd.append('image',this.messageImage);if(this.messageVoice)fd.append('voice',this.messageVoice);await axios.post('/api/employee-portal/messages',fd);this.messageText='';this.messageImage=null;this.messageVoice=null;if(this.$refs.imageInput)this.$refs.imageInput.value='';if(this.$refs.voiceInput)this.$refs.voiceInput.value='';await this.loadMessages()}catch(e){this.toast(this.err(e,'ارسال پیام ممکن نشد.'),'error')}finally{this.saving=false} },
    async toggleRecording(){ if(this.recording){this.mediaRecorder?.stop();return}try{const stream=await navigator.mediaDevices.getUserMedia({audio:true});this.recordedChunks=[];this.mediaRecorder=new MediaRecorder(stream);this.mediaRecorder.ondataavailable=e=>{if(e.data.size)this.recordedChunks.push(e.data)};this.mediaRecorder.onstop=()=>{this.messageVoice=new File([new Blob(this.recordedChunks,{type:'audio/webm'})],`voice-${Date.now()}.webm`,{type:'audio/webm'});stream.getTracks().forEach(t=>t.stop());this.recording=false};this.mediaRecorder.start();this.recording=true}catch{this.toast('دسترسی میکروفن داده نشد.','error')} },
    async openNotification(item){ if(!item.read_at)await axios.patch(`/api/employee-portal/notifications/${item.id}/read`);this.tab=item.action_tab||'summary';this.notificationOpen=false;await this.loadNotifications();if(this.tab==='messages')this.loadMessages() },
    async readAllNotifications(){ await axios.patch('/api/employee-portal/notifications/read-all');await this.loadNotifications() },
    leaveRange(i){ return i.leave_type==='hourly'?`${i.start_date}، ${String(i.start_time||'').slice(0,5)} تا ${String(i.end_time||'').slice(0,5)}`:`${i.start_date} تا ${i.end_date}` },
    statusLabel(s){ return {pending:'در انتظار',approved:'تأییدشده',rejected:'ردشده',cancelled:'لغوشده',ready:'آماده پرداخت',paid:'پرداخت‌شده',deducted:'کسرشده'}[s]||s },
    dateTime(v){ return v?new Date(v).toLocaleString('fa-IR'):'—' },
    err(e,f){const x=e.response?.data?.errors;return x?Object.values(x).flat()[0]:(e.response?.data?.message||f)},
    toast(title,icon='success'){Swal.fire({icon,title,timer:1800,showConfirmButton:false})},
    async confirm(title){return (await Swal.fire({icon:'question',title,showCancelButton:true,confirmButtonText:'بله',cancelButtonText:'انصراف'})).isConfirmed},
  },
}
</script>

<style scoped>
/* Above global nav chrome (.menu 2147483000, utility toggle 2147483007, .epn 2147483008). */
.pr-shell{position:fixed;inset:0;z-index:2147483100;overflow:auto;background:#f4f7fb;color:#172033;font-family:Vazir,sans-serif}.pr-head{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:space-between;padding:14px 24px;border-bottom:1px solid #e3e9f2;background:rgba(255,255,255,.94);backdrop-filter:blur(12px)}.pr-identity{display:flex;align-items:center;gap:12px}.pr-identity>img,.pr-identity>span{width:48px;height:48px;border-radius:15px;object-fit:cover;display:grid;place-items:center;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;font-size:20px;font-weight:900}.pr-identity small,.pr-panel header small{color:#64748b}.pr-identity h1{margin:2px 0 0;font-size:20px}.pr-head-actions{display:flex;gap:8px}.pr-head-actions button{position:relative;width:42px;height:42px;border:0;border-radius:12px;background:#edf3fa;cursor:pointer;font-size:20px}.pr-head-actions b{position:absolute;top:-4px;right:-4px;min-width:19px;padding:2px;border-radius:9px;background:#dc2626;color:#fff;font-size:10px}.pr-close{color:#64748b}.pr-notifications{position:fixed;z-index:20;top:72px;left:18px;width:min(390px,calc(100vw - 36px));max-height:70vh;overflow:auto;padding:12px;border:1px solid #dce5f1;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.25)}.pr-notifications header{display:flex;justify-content:space-between;padding:7px}.pr-notifications header button{border:0;background:none;color:#2563eb}.pr-notifications>button{width:100%;display:grid;gap:3px;margin-top:6px;padding:11px;border:0;border-radius:12px;background:#f8fafc;text-align:right}.pr-notifications>button.unread{background:#eff6ff;box-shadow:inset -3px 0 #2563eb}.pr-notifications span,.pr-notifications small{color:#64748b}.pr-filter{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px;padding:16px 24px;background:#fff}.pr-presets{display:flex;flex-wrap:wrap;gap:6px}.pr-presets button,.pr-month-nav button{padding:8px 13px;border:1px solid #dbe4ef;border-radius:10px;background:#f8fafc;color:#475569;font-family:inherit;cursor:pointer}.pr-presets button.active{border-color:#2563eb;background:#2563eb;color:#fff}.pr-month-nav{display:flex;align-items:center;gap:10px}.pr-month-nav button{padding:6px 13px;font-size:18px}.pr-custom-range{display:flex;align-items:end;gap:7px}.pr-custom-range label{display:grid;gap:3px;color:#64748b;font-size:11px}.pr-custom-range input,.pr-custom-range button{height:36px;padding:0 10px;border:1px solid #d6e0ec;border-radius:9px;font-family:inherit}.pr-custom-range button{background:#2563eb;color:#fff}.pr-tabs{position:sticky;top:77px;z-index:9;display:flex;justify-content:center;gap:7px;padding:9px 18px;border-bottom:1px solid #e2e8f0;background:#f8fafc}.pr-tabs button{min-width:86px;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 12px;border:0;border-radius:11px;background:transparent;color:#64748b;font-family:inherit;font-weight:800;cursor:pointer}.pr-tabs button.active{background:#fff;color:#2563eb;box-shadow:0 5px 18px rgba(15,23,42,.08)}.pr-tabs i{font-style:normal;font-size:16px}.pr-main{max-width:1440px;margin:auto;padding:22px}.pr-state{min-height:360px;display:grid;place-items:center;align-content:center;gap:12px}.pr-state>i{width:34px;height:34px;border:4px solid #dbeafe;border-top-color:#2563eb;border-radius:50%;animation:spin .8s linear infinite}.pr-state.error b{font-size:36px;color:#dc2626}.pr-state button,.pr-primary{padding:9px 15px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-family:inherit;font-weight:800;cursor:pointer}.pr-total{display:grid;place-items:center;padding:28px;border-radius:24px;background:linear-gradient(135deg,#172554,#2563eb 65%,#0891b2);color:#fff;box-shadow:0 22px 45px rgba(37,99,235,.23)}.pr-total span{opacity:.8}.pr-total strong{margin:7px 0;font-size:38px}.pr-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:15px}.pr-card-grid article{display:grid;grid-template-columns:36px 1fr;gap:3px 9px;padding:16px;border:1px solid #e1e8f1;border-radius:17px;background:#fff}.pr-card-grid i{grid-row:1/3;width:36px;height:36px;display:grid;place-items:center;border-radius:11px;background:#eff6ff;color:#2563eb;font-style:normal;font-size:18px}.pr-card-grid span{color:#64748b;font-size:12px}.pr-card-grid strong{font-size:16px}.positive{color:#15803d!important}.negative{color:#b91c1c!important}.neutral{color:#475569!important}.pr-card-grid article.positive{border-color:#bbf7d0}.pr-card-grid article.negative{border-color:#fecaca}.pr-mini-stats,.pr-attendance-totals{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:15px}.pr-mini-stats article,.pr-attendance-totals article{display:flex;justify-content:space-between;padding:14px;border-radius:14px;background:#eaf1fa}.pr-panel{padding:19px;border:1px solid #e0e8f1;border-radius:22px;background:#fff;box-shadow:0 12px 34px rgba(15,23,42,.06)}.pr-panel>header{display:flex;align-items:center;justify-content:space-between;margin-bottom:17px}.pr-panel h2{margin:2px 0 0;font-size:19px}.pr-table-wrap{overflow:auto;border:1px solid #e5ebf3;border-radius:14px}.pr-table-wrap table{width:100%;border-collapse:collapse;white-space:nowrap}.pr-table-wrap th,.pr-table-wrap td{padding:11px 12px;border-bottom:1px solid #edf1f6;text-align:right;font-size:12px}.pr-table-wrap th{background:#f8fafc;color:#475569}.pr-table-wrap tfoot td{background:#eff6ff;font-weight:900}.pr-info{width:26px;height:26px;border:0;border-radius:50%;background:#dbeafe;color:#2563eb;font-weight:900}.pr-list{display:grid;gap:9px}.pr-list article{display:flex;align-items:center;gap:11px;padding:13px;border:1px solid #e5ebf3;border-radius:14px;background:#fbfdff}.pr-list article>i{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:#eef2ff;color:#4f46e5;font-style:normal}.pr-list article>div{flex:1;display:grid;gap:3px}.pr-list article span,.pr-list article small{color:#64748b}.pr-list article>em{padding:5px 9px;border-radius:9px;background:#f1f5f9;font-style:normal;font-size:11px}.pr-list article>button{border:0;background:none;color:#dc2626}.status-approved,.status-paid,.status-deducted{background:#dcfce7!important;color:#15803d}.status-rejected,.status-cancelled{background:#fee2e2!important;color:#b91c1c}.status-pending,.status-ready{background:#fef3c7!important;color:#a16207}.pr-form{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;padding:14px;border-radius:16px;background:#f4f7fb}.pr-form label{display:grid;gap:5px;color:#475569;font-size:12px}.pr-form input,.pr-form select,.pr-form textarea{width:100%;padding:9px;border:1px solid #d6e0ec;border-radius:9px;background:#fff;font-family:inherit}.pr-form .wide{grid-column:span 2}.pr-chat{height:calc(100vh - 260px);min-height:520px;display:grid;grid-template-rows:auto 1fr auto}.pr-message-list{display:flex;flex-direction:column;gap:9px;overflow:auto;padding:12px;background:#f4f7fb;border-radius:16px}.pr-message-list article{align-self:flex-start;max-width:min(72%,620px);padding:10px 12px;border-radius:15px 15px 15px 4px;background:#fff;box-shadow:0 3px 10px rgba(15,23,42,.07)}.pr-message-list article.mine{align-self:flex-end;border-radius:15px 15px 4px 15px;background:#dbeafe}.pr-message-list small{color:#64748b}.pr-message-list p{margin:5px 0;white-space:pre-wrap}.pr-message-list img{max-width:320px;border-radius:10px}.pr-message-list audio{max-width:300px}.pr-empty-chat{margin:auto;color:#94a3b8}.pr-message-form{display:flex;align-items:center;gap:7px;padding-top:10px}.pr-message-form textarea{flex:1;padding:10px;border:1px solid #d7e0eb;border-radius:12px;font-family:inherit}.pr-message-form label,.pr-message-form>button:not(.pr-primary){width:38px;height:38px;display:grid;place-items:center;border:0;border-radius:10px;background:#eef2f7;cursor:pointer}.pr-message-form input{display:none}.pr-message-form .recording{background:#fee2e2!important;color:#dc2626;animation:pulse 1s infinite}.pr-detail-backdrop{position:fixed;inset:0;z-index:30;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58)}.pr-detail-backdrop>section{width:min(620px,96vw);padding:20px;border-radius:20px;background:#fff}.pr-detail-backdrop header{display:flex;justify-content:space-between}.pr-detail-backdrop h3{margin:4px 0}.pr-detail-backdrop header button{border:0;background:none;font-size:25px}.pr-detail-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin:17px 0}.pr-detail-grid article{display:grid;gap:4px;padding:11px;border-radius:12px;background:#f1f5f9}.pr-detail-grid span{color:#64748b;font-size:11px}@keyframes spin{to{transform:rotate(360deg)}}@keyframes pulse{50%{opacity:.5}}@media(max-width:900px){.pr-tabs{justify-content:flex-start;overflow:auto}.pr-tabs button{min-width:74px}.pr-mini-stats,.pr-attendance-totals{grid-template-columns:repeat(2,1fr)}.pr-form{grid-template-columns:repeat(2,1fr)}.pr-head{padding:10px 13px}.pr-main{padding:13px}.pr-filter{padding:12px}.pr-tabs{top:68px}.pr-total strong{font-size:28px}}@media(max-width:560px){.pr-tabs span{display:none}.pr-tabs button{min-width:43px}.pr-card-grid{grid-template-columns:1fr 1fr}.pr-card-grid article{grid-template-columns:1fr;padding:12px}.pr-card-grid i{display:none}.pr-card-grid strong{font-size:13px}.pr-form{grid-template-columns:1fr}.pr-form .wide{grid-column:auto}.pr-message-list article{max-width:88%}.pr-detail-grid{grid-template-columns:1fr 1fr}}
.pr-shell{font-size:12px}.pr-shell svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.pr-head{padding:10px 20px}.pr-identity{gap:10px}.pr-identity>img,.pr-identity>span{width:42px;height:42px;border-radius:13px;font-size:17px}.pr-identity small,.pr-panel header small{font-size:9px}.pr-identity h1{font-size:16px}.pr-head-actions{gap:7px}.pr-head-actions button{width:38px;height:38px;display:grid;place-items:center;border:1px solid #e2e8f0;border-radius:11px;background:#f8fafc;color:#64748b;font-size:inherit}.pr-head-actions button:hover{border-color:#bfdbfe;color:#2563eb}.pr-head-actions b{min-width:18px;height:18px;display:grid;place-items:center;padding:0 3px;border:2px solid #fff;border-radius:10px;background:#ef4444;font-size:8px}.pr-notifications{top:62px;width:min(360px,calc(100vw - 36px));padding:9px;border-radius:16px}.pr-tabs{top:63px;gap:5px;padding:7px 16px}.pr-tabs button{min-width:78px;gap:5px;padding:7px 10px;border-radius:10px;font-size:11px}.pr-tabs i{width:25px;height:25px;display:grid;place-items:center;border-radius:8px;background:#edf2f8}.pr-tabs button.active i{background:#e8f1ff}.pr-tabs svg{width:15px;height:15px}.pr-main{max-width:1380px;padding:16px}.pr-total{padding:20px;border-radius:20px}.pr-total span{font-size:10px}.pr-total strong{margin:5px 0;font-size:29px}.pr-card-grid{grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:9px;margin-top:11px}.pr-card-grid article{grid-template-columns:32px 1fr;gap:2px 8px;padding:12px;border-radius:14px}.pr-card-grid i{width:32px;height:32px;border-radius:10px}.pr-card-grid i svg{width:16px;height:16px}.pr-card-grid span{font-size:10px}.pr-card-grid strong{font-size:13px}.pr-mini-stats article,.pr-attendance-totals article{padding:11px;border-radius:11px}.pr-panel{padding:15px;border-radius:17px}.pr-panel h2{font-size:15px}.pr-table-wrap th,.pr-table-wrap td{padding:9px 10px;font-size:10px}.pr-list article{padding:10px}.pr-list article>i{width:32px;height:32px;border-radius:10px}.pr-form{padding:11px}.pr-chat{height:calc(100vh - 220px)}@media(max-width:900px){.pr-tabs{top:63px}.pr-head{padding:9px 12px}.pr-main{padding:10px}}@media(max-width:560px){.pr-card-grid{grid-template-columns:1fr 1fr}.pr-total strong{font-size:24px}.pr-tabs button{min-width:41px;padding:6px}}

body.dark .pr-shell,#app.dark .pr-shell{background:#0f172a;color:#e2e8f0}
body.dark .pr-head,#app.dark .pr-head{background:rgba(23,32,51,.94);border-color:#334155}
body.dark .pr-identity h1,body.dark .pr-panel h2,#app.dark .pr-identity h1,#app.dark .pr-panel h2{color:#f8fafc}
body.dark .pr-head-actions button,#app.dark .pr-head-actions button{background:#1e293b;border-color:#334155;color:#94a3b8}
body.dark .pr-notifications,#app.dark .pr-notifications{background:#172033;border-color:#334155}
body.dark .pr-notifications>button,#app.dark .pr-notifications>button{background:#1e293b;color:#e2e8f0}
body.dark .pr-notifications>button.unread,#app.dark .pr-notifications>button.unread{background:#1e3a5f}
body.dark .pr-filter,#app.dark .pr-filter{background:#172033}
body.dark .pr-presets button:not(.active),body.dark .pr-month-nav button,#app.dark .pr-presets button:not(.active),#app.dark .pr-month-nav button{background:#1e293b;border-color:#334155;color:#94a3b8}
body.dark .pr-custom-range input,#app.dark .pr-custom-range input{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .pr-tabs,#app.dark .pr-tabs{background:#172033;border-color:#334155}
body.dark .pr-tabs button:not(.active),#app.dark .pr-tabs button:not(.active){color:#94a3b8}
body.dark .pr-tabs button.active,#app.dark .pr-tabs button.active{background:#1e293b;color:#93c5fd;box-shadow:0 5px 18px rgba(0,0,0,.25)}
body.dark .pr-tabs i,#app.dark .pr-tabs i{background:#253349}
body.dark .pr-tabs button.active i,#app.dark .pr-tabs button.active i{background:#1e3a5f}
body.dark .pr-card-grid article,body.dark .pr-panel,body.dark .pr-list article,#app.dark .pr-card-grid article,#app.dark .pr-panel,#app.dark .pr-list article{background:#172033;border-color:#334155;color:#e2e8f0}
body.dark .pr-card-grid i,#app.dark .pr-card-grid i{background:#1e3a5f;color:#93c5fd}
body.dark .pr-card-grid article.positive,#app.dark .pr-card-grid article.positive{border-color:#14532d}
body.dark .pr-card-grid article.negative,#app.dark .pr-card-grid article.negative{border-color:#7f1d1d}
body.dark .positive,#app.dark .positive{color:#4ade80!important}
body.dark .negative,#app.dark .negative{color:#f87171!important}
body.dark .neutral,#app.dark .neutral{color:#94a3b8!important}
body.dark .pr-mini-stats article,body.dark .pr-attendance-totals article,#app.dark .pr-mini-stats article,#app.dark .pr-attendance-totals article{background:#1e293b;color:#e2e8f0}
body.dark .pr-table-wrap,body.dark .pr-table-wrap th,#app.dark .pr-table-wrap,#app.dark .pr-table-wrap th{background:#1e293b;border-color:#334155;color:#94a3b8}
body.dark .pr-table-wrap td,#app.dark .pr-table-wrap td{border-color:#334155;color:#e2e8f0}
body.dark .pr-table-wrap tfoot td,#app.dark .pr-table-wrap tfoot td{background:#1e3a5f}
body.dark .pr-info,#app.dark .pr-info{background:#1e3a5f;color:#93c5fd}
body.dark .pr-list article>i,#app.dark .pr-list article>i{background:#1e3a5f;color:#a5b4fc}
body.dark .status-approved,body.dark .status-paid,body.dark .status-deducted,#app.dark .status-approved,#app.dark .status-paid,#app.dark .status-deducted{background:#052e16!important;color:#4ade80}
body.dark .status-rejected,body.dark .status-cancelled,#app.dark .status-rejected,#app.dark .status-cancelled{background:#450a0a!important;color:#fca5a5}
body.dark .status-pending,body.dark .status-ready,#app.dark .status-pending,#app.dark .status-ready{background:#422006!important;color:#fbbf24}
body.dark .pr-form,#app.dark .pr-form{background:#172033}
body.dark .pr-form input,body.dark .pr-form select,body.dark .pr-form textarea,#app.dark .pr-form input,#app.dark .pr-form select,#app.dark .pr-form textarea{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .pr-message-list,#app.dark .pr-message-list{background:#172033}
body.dark .pr-message-list article,#app.dark .pr-message-list article{background:#1e293b;color:#e2e8f0}
body.dark .pr-message-list article.mine,#app.dark .pr-message-list article.mine{background:#1e3a5f}
body.dark .pr-message-form textarea,#app.dark .pr-message-form textarea{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .pr-message-form label,body.dark .pr-message-form>button:not(.pr-primary),#app.dark .pr-message-form label,#app.dark .pr-message-form>button:not(.pr-primary){background:#253349}
body.dark .pr-detail-backdrop>section,#app.dark .pr-detail-backdrop>section{background:#172033;color:#e2e8f0}
body.dark .pr-detail-grid article,#app.dark .pr-detail-grid article{background:#1e293b}
body.dark .pr-state i,#app.dark .pr-state i{border-color:#334155;border-top-color:#3b82f6}
body.dark .pr-head-actions b,#app.dark .pr-head-actions b{border-color:#1e293b}
</style>
