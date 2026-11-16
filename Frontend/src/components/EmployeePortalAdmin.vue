<template>
  <section class="epa" dir="rtl">
    <header><div><small>مدیریت پنل شخصی</small><h3>درخواست‌ها و ارتباط {{ resource.name }}</h3></div><button type="button" @click="loadAll">↻ تازه‌سازی</button></header>
    <nav><button v-for="item in tabs" :key="item.key" type="button" :class="{active:tab===item.key}" @click="selectTab(item.key)">{{ item.label }}<b v-if="item.count">{{ Number(item.count).toLocaleString('fa-IR') }}</b></button></nav>

    <div v-if="tab==='adjustments'" class="epa-pane">
      <form class="epa-form" @submit.prevent="saveAdjustment">
        <label>نوع<select v-model="adjustment.kind"><option value="bonus">پاداش</option><option value="deduction">کسری</option><option value="adjustment">تعدیل</option></select></label>
        <label class="epa-date-field">تاریخ<jalali-date-input v-model="adjustment.occurred_on" format="jYYYY-jMM-jDD" display-format="jYYYY/jMM/jDD" input-class="epa-date-input" placeholder="تاریخ را انتخاب کنید" auto-submit append-to="body" color="#2563eb" /></label>
        <label>عنوان<input v-model.trim="adjustment.title" required></label>
        <label>مبلغ<input v-model="adjustment.amount" type="number" min="1" required></label>
        <label class="wide">توضیحات<textarea v-model.trim="adjustment.description" rows="2"></textarea></label>
        <div class="epa-actions"><button type="submit" :disabled="saving">{{ adjustment.id?'ذخیره تغییرات':'ثبت در حقوق' }}</button><button v-if="adjustment.id" class="ghost" type="button" @click="resetAdjustment">انصراف</button></div>
      </form>
      <div class="epa-list"><article v-for="item in adjustments" :key="item.id"><i :class="Number(item.amount)>=0?'plus':'minus'">{{ Number(item.amount)>=0?'＋':'−' }}</i><div><b>{{ item.title||item.reason }}</b><span>{{ item.occurred_on }} · {{ item.description||'بدون توضیح' }}</span></div><strong>{{ money(item.amount) }}</strong><button type="button" @click="editAdjustment(item)">ویرایش</button><button class="danger" type="button" @click="deleteAdjustment(item)">حذف</button></article><p v-if="!adjustments.length">ردیفی برای این ماه ثبت نشده است.</p></div>
    </div>

    <div v-else-if="tab==='leaves'" class="epa-pane epa-list"><article v-for="item in leaves" :key="item.id"><i>☂</i><div><b>{{ item.leave_type==='hourly'?'مرخصی ساعتی':'مرخصی روزانه' }}</b><span>{{ leaveRange(item) }}</span><small>{{ item.reason||'بدون توضیح' }}</small><small v-if="item.manager_note">پاسخ: {{ item.manager_note }}</small></div><em :class="`s-${item.status}`">{{ status(item.status) }}</em><template v-if="item.status==='pending'"><button class="approve" type="button" @click="reviewLeave(item,'approved')">تأیید</button><button class="danger" type="button" @click="reviewLeave(item,'rejected')">رد</button></template></article><p v-if="!leaves.length">درخواست مرخصی وجود ندارد.</p></div>

    <div v-else-if="tab==='advances'" class="epa-pane epa-list"><article v-for="item in advances" :key="item.id"><i>﷼</i><div><b>{{ money(item.requested_amount) }} تومان</b><span>{{ item.reason||'بدون توضیح' }}</span><small v-if="item.approved_amount">تأییدشده: {{ money(item.approved_amount) }} تومان · {{ item.installment_count }} قسط از {{ item.deduction_start_month }}</small><small v-if="item.manager_note">پاسخ: {{ item.manager_note }}</small></div><em :class="`s-${item.status}`">{{ status(item.status) }}</em><button v-if="!['rejected','cancelled','deducted'].includes(item.status)" type="button" @click="openAdvance(item)">بررسی</button></article><p v-if="!advances.length">درخواست مساعده وجود ندارد.</p></div>

    <div v-else class="epa-pane epa-chat">
      <div ref="messages" class="epa-messages"><article v-for="m in messages" :key="m.id" :class="{mine:m.sender_user_id===userId}"><small>{{ m.sender_name }} · {{ dt(m.created_at) }}</small><p v-if="m.body">{{ m.body }}</p><img v-if="m.image_url" :src="m.image_url" alt=""><audio v-if="m.voice_url" :src="m.voice_url" controls></audio></article><p v-if="!messages.length">هنوز پیامی ثبت نشده است.</p></div>
      <form class="epa-send" @submit.prevent="sendMessage"><textarea v-model.trim="message" rows="2" placeholder="پاسخ مدیریت..."></textarea><label>تصویر<input ref="image" type="file" accept="image/*" @change="image=$event.target.files[0]"></label><label>صوت<input ref="voice" type="file" accept="audio/*" @change="voice=$event.target.files[0]"></label><button type="submit" :disabled="saving">ارسال</button></form>
    </div>

    <div v-if="advanceDraft" class="epa-modal" @click.self="advanceDraft=null"><form @submit.prevent="reviewAdvance"><header><h3>بررسی مساعده</h3><button type="button" @click="advanceDraft=null">×</button></header><label>وضعیت<select v-model="advanceDraft.status"><option value="approved">تأییدشده</option><option value="rejected">ردشده</option><option value="ready">آماده پرداخت</option><option value="paid">پرداخت‌شده</option><option value="deducted">کسرشده</option></select></label><label>مبلغ نهایی<input v-model="advanceDraft.approved_amount" type="number" min="1"></label><label class="check"><input v-model="advanceDraft.deduct_from_salary" type="checkbox"> از حقوق کسر شود</label><label v-if="advanceDraft.deduct_from_salary">تعداد اقساط<input v-model="advanceDraft.installment_count" type="number" min="1" max="36"></label><label v-if="advanceDraft.deduct_from_salary">شروع کسر<input v-model.trim="advanceDraft.deduction_start_month" placeholder="۱۴۰۵-۰۱"></label><label>توضیح مدیر<textarea v-model.trim="advanceDraft.manager_note" rows="3"></textarea></label><button type="submit" :disabled="saving">ثبت تصمیم</button></form></div>
  </section>
</template>

<script>
import axios from 'axios'
import Swal from 'sweetalert2'
import JalaliDateInput from './JalaliDateInput.vue'
export default {
  name:'EmployeePortalAdmin', components:{JalaliDateInput}, props:{resource:{type:Object,required:true},month:{type:String,required:true},userId:{type:Number,default:null}},
  data(){return{tab:'adjustments',saving:false,adjustments:[],leaves:[],advances:[],messages:[],message:'',image:null,voice:null,advanceDraft:null,adjustment:{id:null,kind:'bonus',occurred_on:`${this.month}-01`,title:'',description:'',amount:''}}},
  computed:{params(){return{resource_type:this.resource.type,resource_id:this.resource.id}},tabs(){return[{key:'adjustments',label:'پاداش و کسری',count:this.adjustments.length},{key:'leaves',label:'مرخصی',count:this.leaves.filter(i=>i.status==='pending').length},{key:'advances',label:'مساعده',count:this.advances.filter(i=>i.status==='pending').length},{key:'messages',label:'پیام‌ها',count:this.messages.filter(i=>!i.read_by_manager_at&&i.sender_user_id!==this.userId).length}]}},
  watch:{resource:{deep:true,handler(){this.loadAll()}},month(){this.adjustment.occurred_on=`${this.month}-01`;this.loadAdjustments()}},mounted(){this.adjustment=this.blankAdjustment();this.loadAll()},methods:{
    blankAdjustment(){return{id:null,kind:'bonus',occurred_on:`${this.month}-01`,title:'',description:'',amount:''}},money(v){return Number(v||0).toLocaleString('fa-IR')},dt(v){return v?new Date(v).toLocaleString('fa-IR'):'—'},
    async loadAll(){await Promise.all([this.loadAdjustments(),this.loadLeaves(),this.loadAdvances(),this.loadMessages(false)])},
    async loadAdjustments(){try{this.adjustments=(await axios.get('/api/employee-portal/adjustments',{params:{...this.params,month:this.month}})).data.adjustments||[]}catch{}},
    async loadLeaves(){try{this.leaves=(await axios.get('/api/employee-portal/leaves',{params:this.params})).data.requests||[]}catch{}},
    async loadAdvances(){try{this.advances=(await axios.get('/api/employee-portal/advances',{params:this.params})).data.advances||[]}catch{}},
    async loadMessages(scroll=true){try{this.messages=(await axios.get('/api/employee-portal/messages',{params:this.params})).data.messages||[];if(scroll)this.$nextTick(()=>{const e=this.$refs.messages;if(e)e.scrollTop=e.scrollHeight})}catch{}},
    selectTab(t){this.tab=t;if(t==='messages')this.loadMessages()},
    async saveAdjustment(){this.saving=true;try{const p={...this.adjustment,...this.params,month:this.adjustment.occurred_on.slice(0,7)};if(p.id)await axios.patch(`/api/employee-portal/adjustments/${p.id}`,p);else await axios.post('/api/employee-portal/adjustments',p);this.resetAdjustment();await this.loadAdjustments();this.$emit('changed');this.toast('در حقوق ثبت شد.')}catch(e){this.fail(e)}finally{this.saving=false}},
    editAdjustment(i){this.adjustment={id:i.id,kind:i.kind,occurred_on:i.occurred_on,title:i.title||i.reason,description:i.description||'',amount:Math.abs(Number(i.amount))}},resetAdjustment(){this.adjustment=this.blankAdjustment()},
    async deleteAdjustment(i){if(!(await this.confirm('این ردیف از تسویه حذف شود؟')))return;await axios.delete(`/api/employee-portal/adjustments/${i.id}`);await this.loadAdjustments();this.$emit('changed')},
    async reviewLeave(i,status){const r=await Swal.fire({title:status==='approved'?'تأیید مرخصی':'رد مرخصی',input:'textarea',inputLabel:'توضیح مدیر (اختیاری)',showCancelButton:true,confirmButtonText:'ثبت',cancelButtonText:'انصراف'});if(!r.isConfirmed)return;await axios.patch(`/api/employee-portal/leaves/${i.id}/review`,{status,manager_note:r.value||''});await this.loadLeaves();this.$emit('changed')},
    openAdvance(i){this.advanceDraft={id:i.id,status:i.status==='pending'?'approved':i.status,approved_amount:Number(i.approved_amount||i.requested_amount),manager_note:i.manager_note||'',deduct_from_salary:i.deduct_from_salary!==false,installment_count:Number(i.installment_count||1),deduction_start_month:i.deduction_start_month||this.month}},
    async reviewAdvance(){this.saving=true;try{await axios.patch(`/api/employee-portal/advances/${this.advanceDraft.id}/review`,this.advanceDraft);this.advanceDraft=null;await this.loadAdvances();this.$emit('changed');this.toast('وضعیت مساعده ذخیره شد.')}catch(e){this.fail(e)}finally{this.saving=false}},
    async sendMessage(){this.saving=true;try{const fd=new FormData();fd.append('resource_type',this.resource.type);fd.append('resource_id',this.resource.id);if(this.message)fd.append('body',this.message);if(this.image)fd.append('image',this.image);if(this.voice)fd.append('voice',this.voice);await axios.post('/api/employee-portal/messages',fd);this.message='';this.image=null;this.voice=null;await this.loadMessages()}catch(e){this.fail(e)}finally{this.saving=false}},
    leaveRange(i){return i.leave_type==='hourly'?`${i.start_date} · ${String(i.start_time).slice(0,5)} تا ${String(i.end_time).slice(0,5)}`:`${i.start_date} تا ${i.end_date}`},status(s){return{pending:'در انتظار',approved:'تأییدشده',rejected:'ردشده',cancelled:'لغوشده',ready:'آماده پرداخت',paid:'پرداخت‌شده',deducted:'کسرشده'}[s]||s},
    toast(t){Swal.fire({icon:'success',title:t,timer:1500,showConfirmButton:false})},fail(e){const x=e.response?.data?.errors;Swal.fire({icon:'error',title:x?Object.values(x).flat()[0]:(e.response?.data?.message||'عملیات انجام نشد.')})},async confirm(t){return(await Swal.fire({icon:'warning',title:t,showCancelButton:true,confirmButtonText:'بله',cancelButtonText:'انصراف'})).isConfirmed}
  }}
</script>

<style scoped>
.epa{margin-top:18px;padding:18px;border:1px solid #dfe7f1;border-radius:20px;background:#fff}.epa>header{display:flex;justify-content:space-between;align-items:center}.epa h3{margin:3px 0}.epa>header small{color:#64748b}.epa>header button{border:0;background:#eff6ff;color:#2563eb;padding:8px 12px;border-radius:9px}.epa>nav{display:flex;gap:7px;margin:16px 0;border-bottom:1px solid #e5eaf1}.epa>nav button{padding:10px 13px;border:0;border-bottom:2px solid transparent;background:none;font-family:inherit}.epa>nav button.active{border-color:#2563eb;color:#2563eb}.epa>nav b{margin-right:5px;padding:1px 5px;border-radius:8px;background:#fee2e2;color:#dc2626}.epa-form{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;padding:13px;border-radius:14px;background:#f5f8fc}.epa-form label,.epa-modal label{display:grid;gap:5px;font-size:12px}.epa-form input,.epa-form select,.epa-form textarea,.epa-modal input,.epa-modal select,.epa-modal textarea{height:36px;box-sizing:border-box;padding:0 9px;border:1px solid #d7e0eb;border-radius:9px;font-family:inherit;line-height:1.3}.epa-form textarea,.epa-modal textarea{height:auto;padding:9px;line-height:1.5}.epa-form .wide{grid-column:span 2}.epa-date-field :deep(.vpd-main){width:100%;height:36px}.epa-date-field :deep(.vpd-input-group){width:100%;height:36px!important;min-height:36px!important;max-height:36px!important;box-sizing:border-box;display:flex!important;align-items:stretch!important;overflow:hidden;border:1px solid #d7e0eb;border-radius:9px;background:#fff}.epa-date-field :deep(.vpd-input-group>input),.epa-date-field :deep(.epa-date-input){flex:1 1 auto!important;width:auto!important;height:100%!important;min-height:0!important;max-height:none!important;box-sizing:border-box;padding:0 9px!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;font-family:inherit;line-height:1.3}.epa-date-field :deep(.vpd-icon-btn){width:36px!important;min-width:36px!important;height:auto!important;min-height:0!important;flex:0 0 36px!important;align-self:stretch!important;display:grid!important;place-items:center!important;border:0!important;border-radius:0!important;padding:0!important;background:#2563eb!important;color:#fff!important}.epa-date-field :deep(.vpd-icon-btn svg){width:15px!important;height:15px!important;display:block}.epa-date-field :deep(.vpd-input-group:focus-within){border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.epa-actions{display:flex;align-items:flex-end;gap:7px;align-self:end}.epa-form .epa-actions button,.epa-modal form>button,.epa-send button{padding:9px 13px;border:0;border-radius:9px;background:#2563eb;color:#fff;width:auto;height:auto}.epa-form .epa-actions button.ghost{background:#e2e8f0;color:#475569}.epa-list{display:grid;gap:8px;margin-top:12px}.epa-list article{display:flex;align-items:center;gap:9px;padding:11px;border:1px solid #e4eaf2;border-radius:12px}.epa-list article>i{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:#eef2ff;font-style:normal}.epa-list i.plus{color:#15803d}.epa-list i.minus{color:#b91c1c}.epa-list article>div{flex:1;display:grid;gap:2px}.epa-list span,.epa-list small{color:#64748b}.epa-list em{padding:4px 8px;border-radius:8px;background:#f1f5f9;font-style:normal}.epa-list button{border:0;background:#eff6ff;color:#2563eb;padding:6px 8px;border-radius:8px}.epa-list button.danger{background:#fee2e2;color:#b91c1c}.epa-list button.approve,.s-approved,.s-paid,.s-deducted{background:#dcfce7!important;color:#15803d!important}.s-rejected,.s-cancelled{background:#fee2e2!important;color:#b91c1c!important}.epa-chat{height:530px;display:grid;grid-template-rows:1fr auto}.epa-messages{overflow:auto;display:flex;flex-direction:column;gap:8px;padding:12px;border-radius:13px;background:#f4f7fb}.epa-messages article{align-self:flex-start;max-width:75%;padding:9px 11px;border-radius:13px;background:#fff}.epa-messages article.mine{align-self:flex-end;background:#dbeafe}.epa-messages img{max-width:260px;border-radius:9px}.epa-messages p{margin:5px 0}.epa-send{display:flex;gap:7px;padding-top:9px}.epa-send textarea{flex:1;padding:9px;border:1px solid #d7e0eb;border-radius:9px;font-family:inherit}.epa-send label{padding:9px;border-radius:9px;background:#eef2f7}.epa-send input{display:none}.epa-modal{position:fixed;inset:0;z-index:1000002;display:grid;place-items:center;background:rgba(15,23,42,.6)}.epa-modal form{width:min(480px,94vw);display:grid;gap:10px;padding:20px;border-radius:18px;background:#fff}.epa-modal header{display:flex;justify-content:space-between}.epa-modal header button{border:0;background:none;font-size:24px}.epa-modal .check{display:flex;align-items:center;gap:7px}@media(max-width:800px){.epa-form{grid-template-columns:1fr 1fr}.epa-form .wide{grid-column:span 2}.epa>nav{overflow:auto}.epa-list article{flex-wrap:wrap}}@media(max-width:520px){.epa-form{grid-template-columns:1fr}.epa-form .wide{grid-column:auto}}
:global(body.dark) .epa,:global(#app.dark) .epa{background:#172033;border-color:#334155;color:#e2e8f0}
:global(body.dark) .epa>header small,:global(#app.dark) .epa>header small,:global(body.dark) .epa-list span,:global(body.dark) .epa-list small,:global(#app.dark) .epa-list span,:global(#app.dark) .epa-list small{color:#94a3b8}
:global(body.dark) .epa>header button,:global(#app.dark) .epa>header button,:global(body.dark) .epa-list button,:global(#app.dark) .epa-list button{background:#1e3a5f;color:#93c5fd}
:global(body.dark) .epa>nav,:global(#app.dark) .epa>nav{border-color:#334155}
:global(body.dark) .epa>nav button.active,:global(#app.dark) .epa>nav button.active{color:#60a5fa;border-color:#60a5fa}
:global(body.dark) .epa-form,:global(#app.dark) .epa-form,:global(body.dark) .epa-list article,:global(#app.dark) .epa-list article,:global(body.dark) .epa-messages,:global(#app.dark) .epa-messages,:global(body.dark) .epa-modal form,:global(#app.dark) .epa-modal form{background:#1e293b;border-color:#334155;color:#e2e8f0}
:global(body.dark) .epa-form input,:global(body.dark) .epa-form select,:global(body.dark) .epa-form textarea,:global(body.dark) .epa-modal input,:global(body.dark) .epa-modal select,:global(body.dark) .epa-modal textarea,:global(body.dark) .epa-send textarea,:global(#app.dark) .epa-form input,:global(#app.dark) .epa-form select,:global(#app.dark) .epa-form textarea,:global(#app.dark) .epa-modal input,:global(#app.dark) .epa-modal select,:global(#app.dark) .epa-modal textarea,:global(#app.dark) .epa-send textarea{background:#0f172a;border-color:#334155;color:#e2e8f0}
:global(body.dark) .epa-date-field :deep(.vpd-input-group),:global(#app.dark) .epa-date-field :deep(.vpd-input-group){background:#0f172a;border-color:#334155}
:global(body.dark) .epa-form .epa-actions button.ghost,:global(#app.dark) .epa-form .epa-actions button.ghost{background:#334155;color:#e2e8f0}
:global(body.dark) .epa-list article>i,:global(#app.dark) .epa-list article>i,:global(body.dark) .epa-list em,:global(#app.dark) .epa-list em,:global(body.dark) .epa-send label,:global(#app.dark) .epa-send label{background:#0f172a;color:#94a3b8}
:global(body.dark) .epa-messages article,:global(#app.dark) .epa-messages article{background:#0f172a}
:global(body.dark) .epa-messages article.mine,:global(#app.dark) .epa-messages article.mine{background:#1e3a5f}
</style>

<style>
html body.dark .epa,
html #app.dark .epa {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
}
html body.dark .epa h3,
html #app.dark .epa h3 { color: #f1f3f4 !important; }
html body.dark .epa > header small,
html body.dark .epa > nav button,
html body.dark .epa-form label,
html body.dark .epa-modal label,
html #app.dark .epa > header small,
html #app.dark .epa > nav button,
html #app.dark .epa-form label,
html #app.dark .epa-modal label { color: #c5cad3 !important; }
html body.dark .epa > nav,
html #app.dark .epa > nav { background: transparent !important; border-color: #2c3038 !important; }
html body.dark .epa > nav button.active,
html #app.dark .epa > nav button.active { color: #93c5fd !important; border-color: #4f8cff !important; }
html body.dark .epa-form,
html body.dark .epa-list article,
html body.dark .epa-messages,
html body.dark .epa-modal form,
html #app.dark .epa-form,
html #app.dark .epa-list article,
html #app.dark .epa-messages,
html #app.dark .epa-modal form {
  background: #22252d !important;
  border-color: #3a3f4a !important;
  color: #e8eaed !important;
}
html body.dark .epa-form input:not(.epa-date-input),
html body.dark .epa-form select,
html body.dark .epa-form textarea,
html body.dark .epa-modal input,
html body.dark .epa-modal select,
html body.dark .epa-modal textarea,
html body.dark .epa-send textarea,
html #app.dark .epa-form input:not(.epa-date-input),
html #app.dark .epa-form select,
html #app.dark .epa-form textarea,
html #app.dark .epa-modal input,
html #app.dark .epa-modal select,
html #app.dark .epa-modal textarea,
html #app.dark .epa-send textarea {
  background: #121417 !important;
  border: 1px solid #3a3f4a !important;
  color: #f1f3f4 !important;
  -webkit-text-fill-color: #f1f3f4 !important;
  box-shadow: none !important;
}
html body.dark .epa-date-field .vpd-input-group,
html #app.dark .epa-date-field .vpd-input-group {
  background: #121417 !important;
  border: 1px solid #3a3f4a !important;
  box-shadow: none !important;
}
html body.dark .epa-date-field .vpd-input-group input,
html #app.dark .epa-date-field .vpd-input-group input {
  background: transparent !important;
  color: #f1f3f4 !important;
  -webkit-text-fill-color: #f1f3f4 !important;
}
html body.dark .epa-form input::placeholder,
html body.dark .epa-form textarea::placeholder,
html #app.dark .epa-form input::placeholder,
html #app.dark .epa-form textarea::placeholder {
  color: #9aa0a6 !important;
  -webkit-text-fill-color: #9aa0a6 !important;
  opacity: 1 !important;
}
</style>
