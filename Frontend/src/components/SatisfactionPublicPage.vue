<template>
  <main class="survey-root" dir="rtl">
    <section v-if="loading" class="survey-state">در حال دریافت فرم…</section>
    <section v-else-if="error" class="survey-state error">{{ error }}</section>
    <section v-else class="survey-card">
      <template v-if="screen === 'intro'">
        <div class="survey-center">
          <div class="survey-logo"><img v-if="form.logo_url" :src="form.logo_url" :alt="form.clinic_name"></div>
          <span class="survey-chip">{{ form.title || 'نظرسنجی کلینیک' }}</span>
          <h1>{{ form.patient_name || 'کاربر' }} عزیز، سلام</h1>
          <p>{{ form.intro_text }}</p>
          <small>{{ fa(questions.length) }} سؤال · حدود ۱ دقیقه</small>
        </div>
        <button class="survey-primary" @click="screen='questions'">شروع نظرسنجی</button>
      </template>
      <template v-else-if="screen === 'questions'">
        <header><button @click="previous">→ قبلی</button><span>سؤال <b>{{ fa(index + 1) }}</b> از {{ fa(questions.length) }}</span></header>
        <div class="survey-track"><i :style="{width: `${((index + 1) / questions.length) * 100}%`}"></i></div>
        <Transition name="survey-question-slide" mode="out-in">
        <div :key="question.id" class="survey-question">
          <h2>{{ question.title }} <em v-if="question.required">*</em></h2>
          <span v-if="!question.required">اختیاری</span>
          <div v-if="question.type === 'rating'" class="survey-options">
            <button v-for="option in activeOptions" :key="option.value" :disabled="advancing" :class="{selected: answers[question.id] === option.value, advancing: advancing && answers[question.id] === option.value}" @click="selectOption(option.value)">
              <i :class="option.value"></i><b>{{ option.label }}</b><span>✓</span>
            </button>
          </div>
          <textarea v-else-if="question.type === 'textarea'" v-model.trim="answers[question.id]" maxlength="500" rows="7" placeholder="نظر خود را اینجا بنویسید…"></textarea>
          <input v-else v-model.trim="answers[question.id]" type="text" placeholder="پاسخ خود را بنویسید">
        </div>
        </Transition>
        <p v-if="submitError" class="survey-submit-error">{{ submitError }}</p>
        <button class="survey-primary" :disabled="submitting || requiredMissing" @click="next">{{ index === questions.length - 1 ? (submitting ? 'در حال ثبت…' : 'ثبت نظر') : 'بعدی' }}</button>
      </template>
      <template v-else>
        <div class="survey-center"><div class="survey-done">✓</div><h1>ممنونیم {{ form.patient_name || 'کاربر' }} عزیز</h1><p>{{ form.completion_text }}</p></div>
      </template>
    </section>
  </main>
</template>

<script>
import axios from 'axios'
export default {
  name: 'SatisfactionPublicPage', props: { token: {type:String,required:true} },
  data:()=>({loading:true,error:'',form:{},screen:'intro',index:0,answers:{},submitting:false,submitError:'',advancing:false,advanceTimer:null}),
  computed:{
    questions(){return this.form.questions || []}, question(){return this.questions[this.index] || {}},
    activeOptions(){return (this.question.options || []).filter(option=>option.active !== false)},
    requiredMissing(){const value=this.answers[this.question.id];return this.question.required && (value===undefined || value===null || String(value).trim()==='')}
  },
  async mounted(){try{const {data}=await axios.get(`/api/public/satisfaction/${this.token}`);this.form=data.form||{};const title=this.form.title||'نظرسنجی کلینیک';const clinic=this.form.clinic_name||'کلینیک';document.title=`${title} — ${clinic}`;if(data.answered)this.screen='done'}catch(e){document.title='نظرسنجی کلینیک';this.error=e.response?.data?.message||'فرم رضایت‌مندی در دسترس نیست.'}finally{this.loading=false}},
  beforeUnmount(){window.clearTimeout(this.advanceTimer)},
  methods:{
    fa(value){return String(value).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d])},
    previous(){window.clearTimeout(this.advanceTimer);this.advancing=false;if(this.index===0)this.screen='intro';else this.index--},
    selectOption(value){if(this.advancing)return;this.answers[this.question.id]=value;this.advancing=true;window.clearTimeout(this.advanceTimer);this.advanceTimer=window.setTimeout(async()=>{this.advancing=false;await this.next()},520)},
    async next(){if(this.requiredMissing)return;if(this.index<this.questions.length-1){this.index++;return}this.submitting=true;this.submitError='';try{await axios.get('/csrf-cookie');await axios.post(`/api/public/satisfaction/${this.token}`,{answers:this.questions.map(q=>({id:q.id,value:this.answers[q.id]??null}))});this.screen='done'}catch(e){this.submitError=e.response?.data?.message||'ثبت پاسخ انجام نشد.'}finally{this.submitting=false}}
  }
}
</script>

<style scoped>
.survey-root{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f2ee;color:#1b2a26;font-family:Vazirmatn,sans-serif}.survey-card,.survey-state{width:min(460px,100%);min-height:min(720px,calc(100vh - 48px));display:flex;flex-direction:column;padding:28px 22px 32px;border-radius:28px;background:#f4f2ee}.survey-state{min-height:220px;place-content:center;text-align:center;font-weight:800}.survey-state.error,.survey-submit-error{color:#b91c1c}.survey-center{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;text-align:center}.survey-logo{width:96px;height:96px;display:grid;place-items:center;overflow:hidden;border-radius:26px;background:#fff;box-shadow:0 12px 32px -12px #1b2a2644}.survey-logo img{width:100%;height:100%;object-fit:contain}.survey-chip{padding:6px 14px;border-radius:999px;background:#e3efe9;color:#1f6f54;font-size:13px;font-weight:700}.survey-center h1{margin:0;font-size:27px}.survey-center p{max-width:350px;margin:0;color:#5b6763;line-height:2.1;white-space:pre-line}.survey-center small{color:#7a8581}.survey-primary{width:100%;height:58px;border:0;border-radius:18px;background:#1f6f54;color:#fff;font:700 17px inherit;cursor:pointer;box-shadow:0 10px 24px -10px #1f6f5499}.survey-primary:disabled{background:#b9c7c1;box-shadow:none;cursor:not-allowed}.survey-card header{display:flex;align-items:center;justify-content:space-between}.survey-card header button{height:40px;padding:0 13px;border:0;border-radius:12px;background:#fff;color:#5b6763;font:500 14px inherit;cursor:pointer}.survey-card header span{color:#7a8581;font-size:14px}.survey-track{height:6px;margin-top:14px;overflow:hidden;border-radius:99px;background:#e2dfd9}.survey-track i{display:block;height:100%;border-radius:inherit;background:#1f6f54;transition:.3s}.survey-question{flex:1;padding:42px 0 24px}.survey-question h2{margin:0;font-size:22px;line-height:1.7}.survey-question h2 em{color:#1f6f54;font-style:normal}.survey-question>span{color:#9aa3a0;font-size:13px}.survey-options{display:grid;gap:10px;margin-top:28px}.survey-options button{min-height:58px;display:flex;align-items:center;gap:14px;padding:0 18px;border:1.5px solid #ece9e4;border-radius:16px;background:#fff;color:#1b2a26;font:600 16px inherit;cursor:pointer}.survey-options button>i{width:22px;height:22px;border-radius:50%;background:#bfc4ca}.survey-options button>i.excellent{background:#1e8e4e}.survey-options button>i.good{background:#8ccb9b}.survey-options button>i.bad{background:#efa39b}.survey-options button>i.weak{background:#d2403a}.survey-options button>b{flex:1;text-align:right}.survey-options button>span{opacity:0}.survey-options button.selected{border-color:#1f6f54;background:#e4f4ea;color:#136b39}.survey-options button.selected>span{opacity:1}.survey-question textarea,.survey-question input{width:100%;margin-top:28px;padding:16px 18px;border:1.5px solid #e2dfd9;border-radius:16px;background:#fff;font:inherit;outline:none}.survey-question input{height:58px}.survey-question textarea{resize:none;line-height:1.9}.survey-submit-error{text-align:center;font-size:12px}.survey-done{width:70px;height:70px;display:grid;place-items:center;border-radius:50%;background:#1f6f54;color:#fff;font-size:32px;box-shadow:0 0 0 14px #e3efe9}@media(max-width:520px){.survey-root{padding:0}.survey-card{min-height:100vh;border-radius:0}}
.survey-options button{transition:transform .22s cubic-bezier(.2,.8,.2,1),background .22s,border-color .22s,box-shadow .22s}.survey-options button:disabled{cursor:default}.survey-options button>i{transition:transform .25s,box-shadow .25s}.survey-options button>span{transform:scale(.2) rotate(-35deg);transition:opacity .25s,transform .25s}.survey-options button.selected{box-shadow:0 12px 28px -14px rgba(31,111,84,.65);transform:translateY(-2px) scale(1.012)}.survey-options button.selected>i{transform:scale(1.18);box-shadow:0 0 0 5px rgba(31,111,84,.12)}.survey-options button.selected>span{transform:scale(1) rotate(0)}.survey-options button.advancing{animation:survey-choice-glow .52s ease both}.survey-question-slide-enter-active,.survey-question-slide-leave-active{transition:opacity .28s ease,transform .32s cubic-bezier(.2,.8,.2,1),filter .28s ease}.survey-question-slide-enter-from{opacity:0;transform:translateX(-28px) scale(.985);filter:blur(3px)}.survey-question-slide-leave-to{opacity:0;transform:translateX(28px) scale(.985);filter:blur(3px)}@keyframes survey-choice-glow{0%{box-shadow:0 0 0 0 rgba(31,111,84,.28)}55%{box-shadow:0 0 0 12px rgba(31,111,84,0)}100%{box-shadow:0 12px 28px -14px rgba(31,111,84,.65)}}
</style>
