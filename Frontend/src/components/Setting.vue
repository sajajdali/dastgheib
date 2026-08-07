<template>
  <div class="settings-page">

    <div class="top-tabs">
      <button
        v-if="canViewSettings"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'internal' }"
        @click="toggleSection('internal')"
      >
        تنظیمات داخلی
      </button>

      <button
        v-if="false"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'access' }"
        @click="toggleSection('access')"
      >
        دسترسی ها
      </button>
      <button
        v-if="canViewSettings"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'sms' }"
        @click="toggleSection('sms')"
      >
        پیامک
      </button>

      <button
        v-if="canViewSettings"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'payments' }"
        @click="toggleSection('payments')"
      >
        پرداخت
      </button>

      <button
        v-if="canViewSettings && featureEnabled('satisfaction')"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'satisfaction' }"
        @click="toggleSection('satisfaction')"
      >
        رضایت‌مندی
      </button>

      <button
        v-if="canViewSettings"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'roles' }"
        @click="toggleSection('roles')"
      >
        نقش‌ها
      </button>

      <button
        v-if="canViewResources"
        type="button"
        class="tab-btn"
        :class="{ active: activeSection === 'resources' }"
        @click="toggleSection('resources')"
      >
        منابع
      </button>
    </div>

    <div v-if="canViewSettings && activeSection === 'internal'" class="content-wrapper">
      <section v-if="isSuperAdmin" class="attendance-master-setting">
        <div>
          <span>دسترسی مدیر کل</span>
          <h3>فعال‌سازی حضور و غیاب</h3>
          <p>در حالت غیرفعال، این بخش از منوی تمام کاربران حذف می‌شود.</p>
        </div>
        <label class="active-switch">
          <input v-model="attendanceEnabled" type="checkbox" :disabled="attendanceSaving" @change="saveAttendanceStatus">
          <span>{{ attendanceEnabled ? 'فعال' : 'غیرفعال' }}</span>
        </label>
      </section>

      <div v-if="featureEnabled('patients')" class="accordion">
        <div class="accordion-header" @click="toggleAccordion('profile')">
          پرونده
        </div>

        <div v-if="openAccordion === 'profile'" class="accordion-body">
          <div class="compact-checks">
            <label><input type="checkbox" v-model="profileFields.city" /> <span>شهر</span></label>
            <label><input type="checkbox" v-model="profileFields.national_id" /> <span>کد ملی</span></label>
            <label><input type="checkbox" v-model="profileFields.marriage_date" /> <span>تاریخ ازدواج</span></label>
            <label><input type="checkbox" v-model="profileFields.education" /> <span>تحصیلات</span></label>
            <label><input type="checkbox" v-model="profileFields.father_name" /> <span>نام پدر</span></label>
            <label><input type="checkbox" v-model="profileFields.second_phone" /> <span>شماره تماس دوم</span></label>
            <label><input type="checkbox" v-model="profileFields.address" /> <span> آدرس</span></label>
          </div>
        </div>
      </div>

      <div class="accordion">
        <div class="accordion-header" @click="toggleAccordion('company')">
          اطلاعات مجموعه
        </div>

        <div v-if="openAccordion === 'company'" class="accordion-body">
          <input v-model="company.name" class="green-input" type="text" placeholder="نام مجموعه" />

          <div class="upload-box">
            <label>آپلود لوگو</label>
            <input class="green-input" type="file" @change="handleLogoUpload" />
            <small v-if="company.logoUrl" style="direction: ltr;">{{ company.logoUrl }}</small>
          </div>

          <textarea v-model="company.about" class="green-input" placeholder="درباره مجموعه"></textarea>
        </div>
      </div>

      <div v-if="featureEnabled('booking')" class="accordion">
        <div class="accordion-header" @click="toggleAccordion('appointmentColumns')">
          ستون‌های نوبت‌دهی
        </div>

        <div v-if="openAccordion === 'appointmentColumns'" class="accordion-body">
          <div class="compact-checks">
            <label><input type="checkbox" v-model="appointmentColumns.payment_method" /> <span>روش پرداخت</span></label>
            <label><input type="checkbox" v-model="appointmentColumns.payment_account" /> <span>حساب واریز</span></label>
            <label><input type="checkbox" v-model="appointmentColumns.payment_link" /> <span>لینک پرداخت</span></label>
            <label><input type="checkbox" v-model="appointmentColumns.best_staff" /> <span>نمایش بهترین پرسنل ماه</span></label>
          </div>
          <div v-if="featureEnabled('patients')" class="required-fields-settings">
            <div class="required-fields-head"><strong>اجباری یا اختیاری بودن اطلاعات تشکیل پرونده</strong><small>فیلد اجباری بدون تکمیل قابل ثبت نیست.</small></div>
            <label v-for="field in patientFieldOptions" :key="field.key">
              <span>{{ field.label }}</span>
              <select v-model="patientRequiredFields[field.key]">
                <option :value="false">اختیاری</option><option :value="true">اجباری</option>
              </select>
            </label>
          </div>
        </div>
      </div>

      <div v-if="featureEnabled('booking')" class="accordion">
        <div class="accordion-header" @click="toggleAccordion('clinicSchedule')">
          برنامه نوبت‌دهی
        </div>

        <div v-if="openAccordion === 'clinicSchedule'" class="accordion-body">
          <label class="schedule-interval-field">
            فاصله هر نوبت
            <input v-model.number="clinicSchedule.interval_minutes" class="green-input" type="number" min="1" step="1" />
          </label>

          <div class="schedule-days-grid">
            <label
              v-for="day in clinicWeekDays"
              :key="day.key"
              class="schedule-day-row"
            >
              <span class="schedule-day-active">
                <input v-model="clinicSchedule.active_days" type="checkbox" :value="day.key" />
                {{ day.label }}
              </span>
              <input v-model="clinicSchedule.day_times[day.key].start" type="time" class="green-input" />
              <input v-model="clinicSchedule.day_times[day.key].end" type="time" class="green-input" />
            </label>
          </div>
        </div>
      </div>

      <div class="accordion">
        <div class="accordion-header" @click="toggleAccordion('password')">
           تعریف کاربر
        </div>

        <div v-if="openAccordion === 'password'" class="accordion-body">
          <div v-for="(item, index) in passwords" :key="item.id || index" class="user-definition-card">
            <div class="user-photo-row">
              <label class="settings-user-avatar">
                <img v-if="userAvatar(item)" :src="userAvatar(item)" alt="" />
                <span v-else>{{ userInitial(item) }}</span>
                <input type="file" accept="image/*" @change="uploadUserPhoto(item, index, $event)" />
              </label>
              <span>عکس کاربر</span>
            </div>
            <div class="row-box user-fields">
              <input class="green-input" type="text" placeholder="نام کاربر" v-model="item.user" />
              <input class="green-input" type="text" placeholder="شماره موبایل" v-model="item.mobile" />
              <input class="green-input" type="password" placeholder="رمز عبور (برای ویرایش خالی بماند)" v-model="item.pass" />
            </div>

            <div class="user-role-title">نقش‌های کاربر</div>
            <div class="user-role-picker">
              <label v-for="role in roles" :key="role.id">
                <input v-model="item.role_ids" type="checkbox" :value="role.id" />
                <span>{{ role.name }}</span>
              </label>
              <small v-if="!roles.length">ابتدا از منوی نقش‌ها، یک نقش تعریف کنید.</small>
            </div>
          </div>

          <div class="btn-group">
            <button class="add-btn" @click="addPassword">+</button>
            <button class="remove-btn" @click="removePassword">-</button>
          </div>
        </div>
      </div>

      <div v-if="featureEnabled('patients')" class="accordion customer-level-settings">
        <div class="accordion-header" @click="toggleAccordion('customerLevels')">دسته‌بندی خودکار مشتری‌ها</div>
        <div v-if="openAccordion === 'customerLevels'" class="accordion-body">
          <p class="level-settings-help">هر سطح بر اساس حداقل و حداکثر پرداخت در بازه خودش یا حداقل مراجعه همان بازه محاسبه می‌شود.</p>
          <div class="level-settings-columns">
            <section v-for="level in customerLevelColumns" :key="level.key" :class="['level-settings-card', level.key]">
              <h4>{{ level.label }}</h4>
              <label>{{ customerLevelPeriodTitle(level.key, 'حداقل پرداخت') }}<input v-model.number="customerLevels[`${level.key}_min_period_amount`]" class="green-input" type="number" min="0"></label>
              <label>{{ customerLevelPeriodTitle(level.key, 'حداکثر پرداخت') }}<input v-model.number="customerLevels[`${level.key}_max_period_amount`]" class="green-input" type="number" min="0"></label>
              <label>{{ customerLevelPeriodTitle(level.key, 'حداقل مراجعه') }}<input v-model.number="customerLevels[`${level.key}_visit_count`]" class="green-input" type="number" min="0"></label>
              <label>
                بازه مراجعه (ماه)
                <select v-model.number="customerLevels[`${level.key}_visit_period_months`]" class="green-input">
                  <option v-for="month in customerLevelMonthOptions" :key="`${level.key}-month-${month}`" :value="month">
                    {{ month.toLocaleString("fa-IR") }}
                  </option>
                </select>
              </label>
            </section>
          </div>
        </div>
      </div>
      <button class="save-all-btn" @click="saveInternalSettings">ذخیره تنظیمات داخلی</button>

    </div>

    <div v-if="canViewSettings && activeSection === 'payments'" class="payment-settings-wrapper">
      <header class="payment-settings-head">
        <div>
          <span class="section-eyebrow">تنظیمات مالی مجموعه</span>
          <h2>روش‌های پرداخت و حساب‌های واریز</h2>
          <p>گزینه‌هایی که هنگام ثبت پرداخت در بخش‌های مختلف سیستم نمایش داده می‌شوند را مدیریت کنید.</p>
        </div>
        <span class="payment-settings-status">ذخیره در تنظیمات مرکزی</span>
      </header>

      <div class="payment-settings-grid">
        <section class="payment-option-card">
          <header>
            <span class="payment-option-icon method">⌁</span>
            <div>
              <h3>روش‌های پرداخت</h3>
              <p>مانند کارتخوان، کارت‌به‌کارت یا پرداخت نقدی</p>
            </div>
          </header>

          <div class="payment-option-list">
            <div v-for="(row, index) in paymentMethodRows" :key="`method-${index}`" class="payment-option-row">
              <span>{{ (index + 1).toLocaleString('fa-IR') }}</span>
              <input v-model.trim="row.name" type="text" placeholder="نام روش پرداخت">
              <button
                type="button"
                title="حذف"
                :disabled="paymentMethodRows.length === 1"
                @click="removePaymentMethodRow(index)"
              >×</button>
            </div>
          </div>

          <button type="button" class="add-payment-option" @click="addPaymentMethodRow">
            + افزودن روش پرداخت
          </button>
        </section>

        <section class="payment-option-card">
          <header>
            <span class="payment-option-icon account">⌂</span>
            <div>
              <h3>حساب‌های واریز</h3>
              <p>حساب یا صندوق مقصد برای ثبت مبالغ دریافتی</p>
            </div>
          </header>

          <div class="payment-option-list">
            <div v-for="(row, index) in paymentAccountRows" :key="`account-${index}`" class="payment-option-row">
              <span>{{ (index + 1).toLocaleString('fa-IR') }}</span>
              <input v-model.trim="row.name" type="text" placeholder="نام حساب واریز">
              <button
                type="button"
                title="حذف"
                :disabled="paymentAccountRows.length === 1"
                @click="removePaymentAccountRow(index)"
              >×</button>
            </div>
          </div>

          <button type="button" class="add-payment-option" @click="addPaymentAccountRow">
            + افزودن حساب واریز
          </button>
        </section>

        <section
          v-for="group in serviceFinderPaymentGroups"
          :key="group.key"
          class="payment-option-card"
        >
          <header>
            <span class="payment-option-icon service">{{ group.icon }}</span>
            <div>
              <h3>{{ group.title }}</h3>
              <p>{{ group.description }}</p>
            </div>
          </header>

          <div class="payment-option-list">
            <div v-for="(row, index) in group.rows.value" :key="`${group.key}-${index}`" class="payment-option-row">
              <span>{{ (index + 1).toLocaleString('fa-IR') }}</span>
              <input v-model.trim="row.name" type="text" :placeholder="group.placeholder">
              <button
                type="button"
                title="حذف"
                :disabled="group.rows.value.length === 1"
                @click="removePaymentOptionRow(group.rows.value, index)"
              >×</button>
            </div>
          </div>

          <button type="button" class="add-payment-option" @click="addPaymentOptionRow(group.rows.value)">
            + افزودن {{ group.shortTitle }}
          </button>
        </section>
      </div>

      <div class="payment-settings-actions">
        <span v-if="paymentSaveMessage" :class="{ error: paymentSaveError }">{{ paymentSaveMessage }}</span>
        <button type="button" :disabled="savingPayments" @click="savePaymentOptions">
          {{ savingPayments ? 'در حال ذخیره...' : 'ذخیره تنظیمات پرداخت' }}
        </button>
      </div>
    </div>

    <div v-if="canViewSettings && activeSection === 'sms'" class="sms-settings-wrapper">
      <section class="sms-provider-card">
        <div>
          <span class="section-eyebrow">سامانه ارسال</span>
          <h3>اتصال پیامک</h3>
          <p>سامانه‌ای را مشخص کنید که پیامک‌های مجموعه از طریق آن ارسال شوند.</p>
        </div>

        <label class="provider-field">
          سامانه پیامکی
          <select v-model="smsSettings.provider">
            <option value="shsms">SHSMS</option>
          </select>
          <small><i></i> سامانه فعال فعلی: SHSMS</small>
        </label>
      </section>

      <section class="sms-templates-section">
        <article class="lead-alert-sms-card">
          <div class="lead-alert-head">
            <div>
              <span class="section-eyebrow">گزارش‌های مدیریتی خودکار</span>
              <h3>ارسال پیامک سرنخ</h3>
              <p>هشدارهای مهم مجموعه را برای شماره‌های مشخص‌شده ارسال کنید.</p>
            </div>
            <label class="active-switch"><input v-model="smsSettings.lead_alerts.enabled" type="checkbox"><span>{{ smsSettings.lead_alerts.enabled ? 'فعال' : 'غیرفعال' }}</span></label>
          </div>

          <label class="lead-recipient-field">
            <span>شماره‌های دریافت‌کننده</span>
            <div>
              <input v-model.trim="leadRecipientDraft" type="tel" maxlength="11" placeholder="مثلاً 09121234567" @keyup.enter="addLeadRecipient">
              <button type="button" @click="addLeadRecipient">افزودن شماره</button>
            </div>
            <small>پیامک‌ها برای تمام شماره‌های این فهرست ارسال می‌شوند.</small>
          </label>
          <div v-if="smsSettings.lead_alerts.recipients.length" class="lead-recipient-list">
            <span v-for="(number, index) in smsSettings.lead_alerts.recipients" :key="number">{{ number }}<button type="button" @click="smsSettings.lead_alerts.recipients.splice(index, 1)">×</button></span>
          </div>

          <div class="lead-alert-options-head">
            <div><strong>کدام سرنخ‌ها پیامک شوند؟</strong><small>موارد موردنظر را انتخاب کنید.</small></div>
            <label class="select-all-leads">
              <input type="checkbox" :checked="allLeadAlertsSelected" @change="toggleAllLeadAlerts($event.target.checked)">
              <span>{{ allLeadAlertsSelected ? 'همه انتخاب شده' : 'انتخاب همه' }}</span>
            </label>
          </div>
          <div class="lead-alert-options">
            <label><input v-model="smsSettings.lead_alerts.inventory_empty" type="checkbox"><span><b>اتمام موجودی انبار</b><small>برای هر کالایی که موجودی آن تمام شود</small></span></label>
            <label><input v-model="smsSettings.lead_alerts.active_tickets" type="checkbox"><span><b>تیکت فعال</b><small>اعلام تعداد تیکت‌های فعال</small></span></label>
            <label><input v-model="smsSettings.lead_alerts.daily_appointments" type="checkbox"><span><b>نوبت‌های امروز، ساعت ۹ صبح</b><small>فقط اگر نوبتی وجود داشته باشد، تعداد کل ارسال می‌شود</small></span></label>
            <label><input v-model="smsSettings.lead_alerts.daily_financial" type="checkbox"><span><b>درآمد و سود روزانه، هر شب</b><small>فقط اگر درآمدی ثبت شده باشد</small></span></label>
          </div>
        </article>
        <article class="birthday-sms-card">
          <div><span class="section-eyebrow">ارسال خودکار ساعت ۹ صبح</span><h3>پیامک تبریک تولد</h3><p>برای بیمارانی که امروز تولدشان است فقط یک پیام در هر سال ارسال می‌شود.</p></div>
          <label class="active-switch"><input v-model="smsSettings.birthday.enabled" type="checkbox"><span>{{ smsSettings.birthday.enabled ? 'فعال' : 'غیرفعال' }}</span></label>
          <label class="template-message-field">نام الگوی SHSMS<input v-model.trim="smsSettings.birthday.content" type="text" maxlength="190" placeholder="مثلاً birthday_message"></label>
          <label class="template-message-field">متن راهنما<textarea v-model="smsSettings.birthday.guide_text" maxlength="1000" placeholder="پارامترها: {name}، {clinic}. متن واقعی داخل پنل SHSMS تعریف می‌شود."></textarea></label>
        </article>
        <div class="sms-section-head">
          <div>
            <span class="section-eyebrow">مدیریت محتوا</span>
            <h3>الگوهای پیامک</h3>
            <p>متن‌های آماده را تعریف کنید و کاربرد هر الگو را مشخص کنید.</p>
          </div>
          <button class="add-template-btn" type="button" @click="addSmsTemplate">+ افزودن الگو</button>
        </div>

        <div v-if="smsSettings.templates.length" class="template-list">
          <article
            v-for="(template, index) in smsSettings.templates"
            :key="template.id"
            class="template-card"
          >
            <div class="template-card-head">
              <strong>الگوی {{ index + 1 }}</strong>
              <label class="active-switch">
                <input v-model="template.active" type="checkbox">
                <span>{{ template.active ? 'فعال' : 'غیرفعال' }}</span>
              </label>
              <button class="delete-template-btn" type="button" @click="removeSmsTemplate(index)">حذف</button>
            </div>

            <div class="template-fields">
              <label>
                عنوان الگو
                <input v-model.trim="template.title" type="text" placeholder="مثلاً یادآوری نوبت">
              </label>
              <label>
                کاربرد الگو
                <select v-model="template.category">
                  <option value="general">عمومی</option>
                  <option value="appointment">یادآوری نوبت</option>
                  <option value="info">اطلاعات مراجعه</option>
                  <option value="welcome">خوش‌آمدگویی</option>
                  <option value="referral_credit">واریز مبلغ برای معرف</option>
                  <option value="treatment_care">توصیه‌های بعد از درمان</option>
                  <option value="payment_link">لینک پرداخت</option>
                </select>
              </label>
            </div>

            <label class="template-message-field">
              نام الگوی SHSMS
              <input
                v-model.trim="template.content"
                type="text"
                maxlength="190"
                placeholder="مثلاً appointment_reminder"
              >
              <small>نام دقیق template تعریف‌شده در پنل SHSMS را وارد کنید.</small>
            </label>

            <label class="template-message-field">
              متن راهنما
              <textarea
                v-model="template.guide_text"
                maxlength="2000"
                placeholder="اینجا فقط راهنمای متن و پارامترهای الگو را بنویسید؛ متن واقعی در SHSMS است."
              ></textarea>
              <small>{{ (template.guide_text || '').length }} / ۲۰۰۰ کاراکتر</small>
            </label>

            <div class="template-variables">
              <span>پارامترهای پیشنهادی:</span>
              <button
                v-for="variable in smsVariables"
                :key="variable"
                type="button"
                @click="appendSmsVariable(template, variable)"
              >{{ variable }}</button>
            </div>
          </article>
        </div>

        <div v-else class="empty-templates">
          هنوز الگویی تعریف نشده است؛ با «افزودن الگو» اولین پیامک را بسازید.
        </div>

        <button class="save-sms-btn" type="button" :disabled="savingSms" @click="saveSmsSettings">
          {{ savingSms ? 'در حال ذخیره...' : 'ذخیره تنظیمات پیامک' }}
        </button>
      </section>
    </div>

    <div v-if="canViewSettings && activeSection === 'roles'" class="roles-settings-wrapper">
      <Roles embedded />
    </div>

    <div v-if="canViewSettings && featureEnabled('satisfaction') && activeSection === 'satisfaction'" class="satisfaction-settings-wrapper">
      <header class="satisfaction-settings-head">
        <div>
          <span>فرم‌ساز رضایت‌مندی</span>
          <h2>صفحه رضایت مندی</h2>
          <p>سوال‌ها را بسازید، نوع پاسخ را مشخص کنید و پیش‌نمایش وسط‌چین فرم را همانجا ببینید.</p>
        </div>
        <button type="button" @click="addSatisfactionQuestion">افزودن سوال</button>
      </header>

      <section class="satisfaction-builder">
        <div class="satisfaction-question-list">
          <article
            v-for="(question, index) in satisfactionQuestions"
            :key="question.id"
            class="satisfaction-question-editor"
            :class="{ dragging: draggedSatisfactionIndex === index }"
            draggable="true"
            @dragstart="startSatisfactionDrag(index, $event)"
            @dragover.prevent
            @drop="dropSatisfactionQuestion(index)"
            @dragend="draggedSatisfactionIndex = null"
          >
            <div class="question-editor-head">
              <span class="drag-handle" title="جابجایی">☰</span>
              <strong>سوال {{ Number(index + 1).toLocaleString('fa-IR') }}</strong>
              <div class="question-actions">
                <button type="button" title="بالا" :disabled="index === 0" @click="moveSatisfactionQuestion(index, -1)">↑</button>
                <button type="button" title="پایین" :disabled="index === satisfactionQuestions.length - 1" @click="moveSatisfactionQuestion(index, 1)">↓</button>
                <button type="button" title="حذف" :disabled="satisfactionQuestions.length === 1" @click="removeSatisfactionQuestion(index)">×</button>
              </div>
            </div>
            <label>
              متن سوال
              <input v-model.trim="question.title" type="text" placeholder="متن سوال را وارد کنید">
            </label>
            <label>
              نوع سوال
              <select v-model="question.type">
                <option value="rating">گزینه‌ای رنگی</option>
                <option value="textarea">توضیحات</option>
                <option value="text">متن کوتاه</option>
              </select>
            </label>
            <label class="satisfaction-required-toggle" :class="{ required: question.required }">
              <input v-model="question.required" type="checkbox">
              <span class="toggle-track"><i></i></span>
              <b>{{ question.required ? 'اجباری' : 'اختیاری' }}</b>
            </label>
          </article>
        </div>

        <aside class="satisfaction-preview">
          <h3>صفحه رضایت مندی</h3>
          <article v-for="question in satisfactionQuestions" :key="`preview-${question.id}`" class="satisfaction-preview-question">
            <strong>{{ question.title || 'سوال بدون عنوان' }}</strong>
            <div v-if="question.type === 'rating'" class="satisfaction-options">
              <button
                v-for="option in satisfactionOptions"
                :key="`${question.id}-${option.value}`"
                type="button"
                :class="['satisfaction-option', option.value, { selected: question.previewValue === option.value }]"
                @click="question.previewValue = option.value"
              >
                {{ option.label }}
              </button>
            </div>
            <textarea v-else-if="question.type === 'textarea'" v-model="question.previewText" placeholder="توضیحات"></textarea>
            <input v-else v-model="question.previewText" type="text" placeholder="پاسخ کوتاه">
          </article>
        </aside>
      </section>

      <footer class="satisfaction-actions">
        <span v-if="satisfactionSaveMessage">{{ satisfactionSaveMessage }}</span>
        <button type="button" @click="resetSatisfactionDefaults">بازگشت به پیش‌فرض</button>
        <button type="button" class="primary" @click="saveSatisfactionSettings">ذخیره فرم رضایت‌مندی</button>
      </footer>
    </div>

    <div v-if="false && canViewSettings && activeSection === 'access'" class="content-wrapper">
      <div v-for="(section, sIndex) in accessSections" :key="sIndex" class="accordion">
        
        <div class="accordion-header" @click="toggleAccordion(section.title)">
          {{ section.title }}
        </div>

        <div v-if="openAccordion === section.title" class="accordion-body">
          <div v-for="(person, pIndex) in section.people" :key="pIndex" class="permission-box">
            
            <input class="green-input" type="text" placeholder="نام پرسنل" v-model="person.name" />

            <div class="compact-checks">
              <label v-for="(perm, permIndex) in section.permissions" :key="permIndex">
                <input 
                  type="checkbox" 
                  :value="perm" 
                  v-model="person.selected_permissions" 
                />
                <span>{{ perm }}</span>
              </label>
            </div>

          </div>

          <div class="btn-group">
            <button class="add-btn" @click="addPerson(sIndex)">+</button>
            <button class="remove-btn" @click="removePerson(sIndex)">-</button>
          </div>
        </div>

      </div>

      <button class="save-all-btn" @click="saveAccessSettings">ذخیره سطوح دسترسی</button>
    </div>

    <Manabe v-if="canViewResources && activeSection === 'resources'" />

  </div>
</template>

<script setup>
import { avatarInitial, avatarUrl } from '@/utils/avatar'
import { computed, ref, onMounted, watch } from "vue";
import Swal from "sweetalert2";
import Roles from "./Roles.vue";
import Manabe from "./manabe.vue";
import axios from "axios";

const props = defineProps({
  currentUser: { type: Object, default: null },
  enabledFeatures: { type: Array, default: null }
});
const isSuperAdmin = computed(() => (props.currentUser?.roles || []).some(role => ['مدیر کل', 'مدیر سیستم', 'super admin', 'super-admin'].includes(String(role).trim().toLowerCase())));
const canViewSettings = computed(() => isSuperAdmin.value);
const canViewResources = computed(() => isSuperAdmin.value);
const featureEnabled = (feature) => {
  if (!feature || !Array.isArray(props.enabledFeatures)) return true;
  const aliases = {
    chat: "patients",
    staffEval: "resources",
    tasks: "followups",
    campaign: "automation",
    aiReport: "beauty",
  };
  const normalized = props.enabledFeatures.map(item => aliases[item] || item);
  return normalized.includes(feature);
};
const attendanceEnabled = ref(false);
const attendanceSaving = ref(false);
const SATISFACTION_SETTINGS_KEY = "satisfaction_form_settings_v1";

const activeSection = ref(canViewSettings.value ? "internal" : "resources");
const openAccordion = ref("");
const paymentMethodRows = ref([{ name: "کارتخوان" }, { name: "کارت به کارت" }, { name: "شبا" }]);
const paymentAccountRows = ref([{ name: "حساب اصلی" }]);
const serviceCategoryRows = ref([{ name: "زیبایی" }, { name: "درمانی" }, { name: "لیزر" }, { name: "پوست و مو" }]);
const serviceTypeRows = ref([{ name: "خدمت اصلی" }, { name: "خدمت جانبی" }, { name: "مشاوره" }]);
const serviceStatusRows = ref([{ name: "فعال" }, { name: "غیرفعال" }, { name: "نیازمند بررسی" }]);
const savingPayments = ref(false);
const paymentSaveMessage = ref("");
const paymentSaveError = ref(false);
const serviceFinderPaymentGroups = computed(() => [
  {
    key: "service-category",
    icon: "◫",
    title: "دسته‌بندی خدمت‌یاب",
    shortTitle: "دسته‌بندی",
    description: "لیست دسته‌بندی‌های قابل انتخاب در خدمت‌یاب",
    placeholder: "نام دسته‌بندی",
    rows: serviceCategoryRows
  },
  {
    key: "service-type",
    icon: "◈",
    title: "نوع خدمت‌یاب",
    shortTitle: "نوع",
    description: "نوع خدمت یا ردیف قابل انتخاب در خدمت‌یاب",
    placeholder: "نام نوع",
    rows: serviceTypeRows
  },
  {
    key: "service-status",
    icon: "●",
    title: "وضعیت خدمت‌یاب",
    shortTitle: "وضعیت",
    description: "وضعیت‌های قابل انتخاب برای ردیف‌های خدمت‌یاب",
    placeholder: "نام وضعیت",
    rows: serviceStatusRows
  }
]);

// داده‌های بخش تنظیمات داخلی
const sms = ref({ appointment: "", info: "", welcome: "" });
const smsSettings = ref({ provider: "shsms", templates: [], birthday: { enabled: false, content: "", guide_text: "پارامترها: {name}، {clinic}" }, lead_alerts: { enabled: false, recipients: [], inventory_empty: true, active_tickets: true, daily_appointments: true, daily_financial: true } });
const leadRecipientDraft = ref("");
const leadAlertKeys = ["inventory_empty", "active_tickets", "daily_appointments", "daily_financial"];
const allLeadAlertsSelected = computed(() => leadAlertKeys.every(key => smsSettings.value.lead_alerts[key]));
const smsVariables = ["{name}", "{date}", "{time}", "{doctor}", "{clinic}", "{code}", "{amount}", "{balance}", "{link}"];
const savingSms = ref(false);
const profileFields = ref({ national_id: false, marriage_date: false, education: false, father_name: false, second_phone: false, address: false, city: false });
const patientFieldOptions = [
  ['first_name','نام'],['last_name','نام خانوادگی'],['phone','شماره تماس'],['file_number','شماره پرونده'],['gender','جنسیت'],['birth_date','تاریخ تولد'],['area','محدوده سکونت'],['city','شهر'],['financial_status','وضعیت مالی'],['national_id','کد ملی'],['father_name','نام پدر'],['marriage_date','تاریخ ازدواج'],['education','تحصیلات'],['second_phone','شماره تماس دوم'],['patient_history','تیپ شخصیتی'],['medical_history','سوابق پزشکی'],['address','آدرس']
].map(([key,label]) => ({ key,label }));
const patientRequiredFields = ref(Object.fromEntries(patientFieldOptions.map(field => [field.key, false])));
const customerLevelColumns = [
  { key: "blue", label: "آبی" },
  { key: "silver", label: "نقره‌ای" },
  { key: "gold", label: "طلایی" }
];
const customerLevelMonthOptions = Array.from({ length: 12 }, (_, index) => index + 1);
const customerLevels = ref({
  blue_min_period_amount: 0,
  blue_max_period_amount: 0,
  blue_visit_count: 1,
  blue_visit_period_months: 3,
  silver_min_period_amount: 10000000,
  silver_max_period_amount: 30000000,
  silver_visit_count: 2,
  silver_visit_period_months: 3,
  gold_min_period_amount: 100000000,
  gold_max_period_amount: 200000000,
  gold_visit_count: 3,
  gold_visit_period_months: 3
});
const appointmentColumns = ref({ payment_method: true, payment_account: true, payment_link: false, best_staff: false });
const clinicWeekDays = [
  { key: "saturday", label: "شنبه" },
  { key: "sunday", label: "یکشنبه" },
  { key: "monday", label: "دوشنبه" },
  { key: "tuesday", label: "سه‌شنبه" },
  { key: "wednesday", label: "چهارشنبه" },
  { key: "thursday", label: "پنجشنبه" },
  { key: "friday", label: "جمعه" }
];
const defaultDayTimes = () => Object.fromEntries(clinicWeekDays.map(day => [day.key, { start: "09:00", end: "17:00" }]));
const clinicSchedule = ref({
  active_days: ["saturday", "monday", "wednesday"],
  interval_minutes: 15,
  day_times: defaultDayTimes()
});
const company = ref({ name: "", about: "", logoFile: null, logoUrl: "" });
const makePasswordRow = () => ({
  id: null,
  user: "",
  mobile: "",
  pass: "",
  role_ids: [],
  profile_photo_path: null,
  profile_thumbnail_path: null,
  profile_photo_url: null,
  profile_thumbnail_url: null,
  avatar_url: null
});
const passwords = ref([makePasswordRow()]);
const roles = ref([]);

const satisfactionOptions = [
  { value: "excellent", label: "عالی" },
  { value: "good", label: "خوب" },
  { value: "average", label: "متوسط" },
  { value: "bad", label: "بد" },
  { value: "weak", label: "ضعیف" }
];
const makeSatisfactionQuestion = (title = "", type = "rating") => ({
  id: `satisfaction-${Date.now()}-${Math.random().toString(16).slice(2)}`,
  title,
  type,
  required: type !== "textarea",
  previewValue: "",
  previewText: ""
});
const defaultSatisfactionQuestions = () => [
  makeSatisfactionQuestion("طرز برخورد پرسنل چطور بود ؟"),
  makeSatisfactionQuestion("طرز برخورد پزشک چطور بود ؟"),
  makeSatisfactionQuestion("نظافت مجموعه چطور بود ؟"),
  makeSatisfactionQuestion("آیا محیط مجموعه آرامبخش بود ؟"),
  makeSatisfactionQuestion("آیا مجدد ما را انتخاب میکنید ؟"),
  makeSatisfactionQuestion("توضیحات:", "textarea")
];
const satisfactionQuestions = ref(defaultSatisfactionQuestions());
const satisfactionSaveMessage = ref("");
const draggedSatisfactionIndex = ref(null);

const loadSatisfactionSettings = () => {
  try {
    const saved = JSON.parse(localStorage.getItem(SATISFACTION_SETTINGS_KEY) || "[]");
    if (Array.isArray(saved) && saved.length) {
      satisfactionQuestions.value = saved.map(question => ({
        ...makeSatisfactionQuestion(),
        ...question,
        id: question.id || `satisfaction-loaded-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        type: ["rating", "textarea", "text"].includes(question.type) ? question.type : "rating"
      }));
    }
  } catch {
    satisfactionQuestions.value = defaultSatisfactionQuestions();
  }
};
const persistSatisfactionSettings = () => {
  const payload = satisfactionQuestions.value.map(({ id, title, type, required }) => ({ id, title, type, required }));
  localStorage.setItem(SATISFACTION_SETTINGS_KEY, JSON.stringify(payload));
};
const addSatisfactionQuestion = () => {
  satisfactionQuestions.value.push(makeSatisfactionQuestion("سوال جدید", "rating"));
};
const removeSatisfactionQuestion = (index) => {
  if (satisfactionQuestions.value.length === 1) return;
  satisfactionQuestions.value.splice(index, 1);
};
const moveSatisfactionQuestion = (index, direction) => {
  const nextIndex = index + direction;
  if (nextIndex < 0 || nextIndex >= satisfactionQuestions.value.length) return;
  const rows = [...satisfactionQuestions.value];
  const [item] = rows.splice(index, 1);
  rows.splice(nextIndex, 0, item);
  satisfactionQuestions.value = rows;
};
const startSatisfactionDrag = (index, event) => {
  draggedSatisfactionIndex.value = index;
  if (event?.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", String(index));
  }
};
const dropSatisfactionQuestion = (targetIndex) => {
  const fromIndex = draggedSatisfactionIndex.value;
  if (fromIndex === null || fromIndex === targetIndex) return;
  const rows = [...satisfactionQuestions.value];
  const [item] = rows.splice(fromIndex, 1);
  rows.splice(targetIndex, 0, item);
  satisfactionQuestions.value = rows;
  draggedSatisfactionIndex.value = null;
};
const resetSatisfactionDefaults = () => {
  satisfactionQuestions.value = defaultSatisfactionQuestions();
  persistSatisfactionSettings();
  satisfactionSaveMessage.value = "فرم به سوال‌های پیش‌فرض برگشت.";
};
const saveSatisfactionSettings = () => {
  persistSatisfactionSettings();
  satisfactionSaveMessage.value = "فرم رضایت‌مندی ذخیره شد.";
};

// بخش دسترسی‌ها
const accessSections = ref([
  { title: "پرونده ها", permissions: ["کل پرونده", "تشکیل پرونده", "ثبت خدمات", "جستجو"], people: [{ name: "", selected_permissions: [] }] },
  { title: "وقت دهی", permissions: ["کل وقت دهی", "گزارش درآمد", "مشاور", "پیامک", "خدمات", "افزودن روز"], people: [{ name: "", selected_permissions: [] }] },
  { title: "پیگیری", permissions: ["کل پیگیری", "ایجاد کمپین", "مشاهده جدول"], people: [{ name: "", selected_permissions: [] }] },
  { title: "گزارش", permissions: ["کل گزارش", "سود و هزینه ها", "وقت دهی", "تبلیغات", "پرسنل", "پزشک", "مجموعه", "بدهکاران"], people: [{ name: "", selected_permissions: [] }] },
  { title: "انبار", permissions: ["کل انبار", "هزینه", "قیمت", "تعداد", "حداقل موجودی", "موجودی", "جدول"], people: [{ name: "", selected_permissions: [] }] },
  { title: "زیبایار", permissions: ["کل زیبایار", "ایجاد برنامه زیبایی", "فیلتر تاریخ", "مشاهده پرونده"], people: [{ name: "", selected_permissions: [] }] },
  { title: "منابع", permissions: ["کل منابع", "پزشک", "پرسنل", "کانال ها"], people: [{ name: "", selected_permissions: [] }] },
  { title: "تیکت", permissions: ["کل تیکت", "افزودن تیکت", "تیکت های فعال", "تیکت های انجام شده", "تیکت های انجام نشده", "حذف تیکت"], people: [{ name: "", selected_permissions: [] }] },
  { title: "قبوض", permissions: [" کل قبوض"], people: [{ name: "", selected_permissions: [] }] },
  { title: "حضور غیاب", permissions: [" کل حضور غیاب", " افزودن پرسنل", " افزودن ماه", " تنظیمات ماه", " حذف ماه", " حذف مبلغ"], people: [{ name: "", selected_permissions: [] }] },
  { title: "تنظیمات", permissions: ["کل تنظیمات"], people: [{ name: "", selected_permissions: [] }] }
]);

const toggleSection = (section) => {
  if (section === "satisfaction" && !featureEnabled("satisfaction")) return;
  activeSection.value = activeSection.value === section ? "" : section;
  openAccordion.value = "";
};

const toggleAccordion = (name) => {
  openAccordion.value = openAccordion.value === name ? "" : name;
};

const addPassword = () => { passwords.value.push(makePasswordRow()); };
const removePassword = () => { if (passwords.value.length > 1) passwords.value.pop(); };
const customerLevelPeriodTitle = (levelKey, title) => {
  const months = Math.max(1, Number(customerLevels.value[`${levelKey}_visit_period_months`] || 3));
  return `${title} در ${months.toLocaleString("fa-IR")} ماه`;
};

watch(
  () => props.enabledFeatures,
  () => {
    if (activeSection.value === "satisfaction" && !featureEnabled("satisfaction")) {
      activeSection.value = canViewSettings.value ? "internal" : "resources";
    }
  },
  { deep: true },
);

const addPaymentMethodRow = () => paymentMethodRows.value.push({ name: "" });
const removePaymentMethodRow = (index) => {
  if (paymentMethodRows.value.length > 1) paymentMethodRows.value.splice(index, 1);
};
const addPaymentAccountRow = () => paymentAccountRows.value.push({ name: "" });
const removePaymentAccountRow = (index) => {
  if (paymentAccountRows.value.length > 1) paymentAccountRows.value.splice(index, 1);
};
const addPaymentOptionRow = (rows) => rows.push({ name: "" });
const removePaymentOptionRow = (rows, index) => {
  if (rows.length > 1) rows.splice(index, 1);
};
const paymentRowNames = (rows) => rows.map(row => row.name.trim()).filter(Boolean);

const fetchPaymentOptions = async () => {
  try {
    const response = await fetch("/api/payment-options");
    if (!response.ok) throw new Error();
    const data = await response.json();
    if (Array.isArray(data.methods) && data.methods.length) {
      paymentMethodRows.value = data.methods.map(name => ({ name }));
    }
    if (Array.isArray(data.accounts) && data.accounts.length) {
      paymentAccountRows.value = data.accounts.map(name => ({ name }));
    }
    if (Array.isArray(data.service_categories) && data.service_categories.length) {
      serviceCategoryRows.value = data.service_categories.map(name => ({ name }));
    }
    if (Array.isArray(data.service_types) && data.service_types.length) {
      serviceTypeRows.value = data.service_types.map(name => ({ name }));
    }
    if (Array.isArray(data.service_statuses) && data.service_statuses.length) {
      serviceStatusRows.value = data.service_statuses.map(name => ({ name }));
    }
  } catch {
    paymentSaveError.value = true;
    paymentSaveMessage.value = "دریافت تنظیمات پرداخت انجام نشد.";
  }
};

const savePaymentOptions = async () => {
  const methods = paymentRowNames(paymentMethodRows.value);
  const accounts = paymentRowNames(paymentAccountRows.value);
  const service_categories = paymentRowNames(serviceCategoryRows.value);
  const service_types = paymentRowNames(serviceTypeRows.value);
  const service_statuses = paymentRowNames(serviceStatusRows.value);
  if (!methods.length || !accounts.length) {
    paymentSaveError.value = true;
    paymentSaveMessage.value = "حداقل یک روش پرداخت و یک حساب واریز وارد کنید.";
    return;
  }

  savingPayments.value = true;
  paymentSaveMessage.value = "";
  paymentSaveError.value = false;
  try {
    const response = await fetch("/api/payment-options", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ methods, accounts, service_categories, service_types, service_statuses })
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "ذخیره انجام نشد.");
    paymentSaveMessage.value = "تنظیمات پرداخت با موفقیت ذخیره شد.";
    await fetchPaymentOptions();
  } catch (error) {
    paymentSaveError.value = true;
    paymentSaveMessage.value = error.message || "ذخیره تنظیمات پرداخت انجام نشد.";
  } finally {
    savingPayments.value = false;
  }
};

const addPerson = (index) => { accessSections.value[index].people.push({ name: "", selected_permissions: [] }); };
const removePerson = (index) => { if (accessSections.value[index].people.length > 1) accessSections.value[index].people.pop(); };

const handleLogoUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    company.value.logoFile = file;
  }
};

const userAvatar = avatarUrl;
const userInitial = (item) => avatarInitial(item);

const makeSquareWebp = async (file, size, quality, fileName) => {
  const imageUrl = URL.createObjectURL(file);
  const image = new Image();
  image.src = imageUrl;
  await new Promise((resolve, reject) => {
    image.onload = resolve;
    image.onerror = reject;
  });

  const canvas = document.createElement("canvas");
  canvas.width = size;
  canvas.height = size;
  const context = canvas.getContext("2d");
  const side = Math.min(image.width, image.height);
  const sourceX = (image.width - side) / 2;
  const sourceY = (image.height - side) / 2;
  context.drawImage(image, sourceX, sourceY, side, side, 0, 0, size, size);
  URL.revokeObjectURL(imageUrl);

  const blob = await new Promise(resolve => canvas.toBlob(resolve, "image/webp", quality));
  return new File([blob], fileName, { type: "image/webp" });
};

const makeSmsTemplate = () => ({
  id: `template-${Date.now()}-${Math.random().toString(16).slice(2)}`,
  title: "",
  category: "general",
  content: "",
  guide_text: "",
  active: true
});

const addSmsTemplate = () => {
  smsSettings.value.templates.push(makeSmsTemplate());
};
const addLeadRecipient = () => {
  const number = leadRecipientDraft.value.replace(/\D/g, "");
  if (!/^09\d{9}$/.test(number)) {
    Swal.fire({ icon: "warning", title: "شماره معتبر نیست", text: "شماره موبایل را به‌صورت 11 رقمی و با 09 وارد کنید." });
    return;
  }
  if (!smsSettings.value.lead_alerts.recipients.includes(number)) smsSettings.value.lead_alerts.recipients.push(number);
  leadRecipientDraft.value = "";
};
const toggleAllLeadAlerts = (checked) => {
  leadAlertKeys.forEach(key => {
    smsSettings.value.lead_alerts[key] = checked;
  });
};

const removeSmsTemplate = async (index) => {
  const template = smsSettings.value.templates[index];
  const result = await Swal.fire({
    icon: "warning",
    title: "حذف الگوی پیامک",
    text: `الگوی «${template.title || "بدون عنوان"}» حذف شود؟`,
    showCancelButton: true,
    confirmButtonText: "بله، حذف شود",
    cancelButtonText: "انصراف",
    confirmButtonColor: "#dc2626",
    reverseButtons: true
  });

  if (result.isConfirmed) smsSettings.value.templates.splice(index, 1);
};

const appendSmsVariable = (template, variable) => {
  const guide = template.guide_text || "";
  const separator = guide && !guide.endsWith(" ") ? " " : "";
  template.guide_text = `${guide}${separator}${variable}`;
};

// ۱. دریافت اطلاعات از لاراول هنگام لود صفحه
const fetchSettings = async () => {
  try {
    const res = await fetch("/api/settings");
    if (!res.ok) throw new Error();
    const data = await res.json();
    
    if (data.sms) sms.value = data.sms;
    if (data.sms_settings) {
      smsSettings.value.provider = data.sms_settings.provider || "shsms";
      smsSettings.value.birthday = data.sms_settings.birthday || { enabled: false, content: "", guide_text: "پارامترها: {name}، {clinic}" };
      smsSettings.value.lead_alerts = data.sms_settings.lead_alerts || { enabled: false, recipients: [], inventory_empty: true, active_tickets: true, daily_appointments: true, daily_financial: true };
      smsSettings.value.templates = (data.sms_settings.templates || []).map((template, index) => ({
        id: template.id || `template-loaded-${index}`,
        title: template.title || "",
        category: template.category || "general",
        content: template.content || "",
        guide_text: template.guide_text || "",
        active: template.active !== false
      }));
    }
    if (data.profile_fields) profileFields.value = data.profile_fields;
    if (data.patient_required_fields) patientRequiredFields.value = { ...patientRequiredFields.value, ...data.patient_required_fields };
    if (data.customer_levels) customerLevels.value = { ...customerLevels.value, ...data.customer_levels };
    if (data.appointment_columns) appointmentColumns.value = { ...appointmentColumns.value, ...data.appointment_columns };
    attendanceEnabled.value = Boolean(data.attendance_enabled);
    if (data.clinic_schedule) {
      clinicSchedule.value = {
        ...clinicSchedule.value,
        ...data.clinic_schedule,
        day_times: { ...defaultDayTimes(), ...(data.clinic_schedule.day_times || {}) }
      };
    }
    if (data.company) {
      company.value.name = data.company.name;
      company.value.about = data.company.about;
      company.value.logoUrl = data.company.logo;
    }
    if (data.users && data.users.length) {
      passwords.value = data.users.map(u => ({
        id: u.id,
        user: u.user,
        mobile: u.mobile || "",
        pass: "",
        role_ids: [...(u.role_ids || [])],
        profile_photo_path: u.profile_photo_path || null,
        profile_thumbnail_path: u.profile_thumbnail_path || null,
        profile_photo_url: u.profile_photo_url || null,
        profile_thumbnail_url: u.profile_thumbnail_url || null,
        avatar_url: u.avatar_url || null
      }));
    }
    if (data.roles) roles.value = data.roles;
    if (data.access_sections && data.access_sections.length) {
      accessSections.value = data.access_sections;
    }
  } catch (e) {
    console.error("خطا در دریافت تنظیمات:", e);
  }
};

// ۲. ذخیره تنظیمات داخلی به لاراول
const saveInternalSettings = async (showMessage = true) => {
  try {
    // پpayload کاملاً تمیز و ساختاریافته به صورت JSON
    const payload = {
      profile_fields: profileFields.value,
      patient_required_fields: patientRequiredFields.value,
      customer_levels: customerLevels.value,
      appointment_columns: appointmentColumns.value,
      clinic_schedule: clinicSchedule.value,
      company: {
        name: company.value.name || "",
        about: company.value.about || ""
      },
      passwords: passwords.value
    };

    const res = await fetch("/api/settings/internal", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify(payload)
    });
    
    const data = await res.json();

    if (res.ok) {
      if (showMessage) {
        Swal.fire({ icon: "success", title: "موفقیت آمیز", text: data.message, timer: 2000, showConfirmButton: false });
      }
      await fetchSettings();
      return true;
    } else {
      const validationMessage = data.errors
        ? Object.values(data.errors).flat()[0]
        : data.message;
      throw new Error(validationMessage || 'ذخیره اطلاعات انجام نشد.');
    }
  } catch (e) {
    if (showMessage) {
      Swal.fire({ icon: 'error', title: 'خطا', text: e.message || 'ذخیره اطلاعات انجام نشد.' });
      return false;
      Swal.fire({ icon: "error", title: "خطا", text: "ذخیره اطلاعات انجام نشد." });
    }
    return false;
  }
};

const uploadUserPhoto = async (item, index, event) => {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) return;

  if (!item.user || !item.user.trim()) {
    await Swal.fire({ icon: "warning", title: "نام کاربر را وارد کنید" });
    return;
  }

  const requestedName = item.user.trim();
  const requestedMobile = item.mobile || "";

  if (!item.id) {
    const saved = await saveInternalSettings(false);
    if (!saved) {
      await Swal.fire({ icon: "error", title: "خطا", text: "اول ذخیره کاربر انجام نشد." });
      return;
    }
    item = passwords.value.find(row => row.user === requestedName && (row.mobile || "") === requestedMobile)
      || passwords.value.find(row => row.user === requestedName)
      || passwords.value[index];
    index = passwords.value.findIndex(row => row.id === item?.id);
  }

  if (!item?.id) {
    await Swal.fire({ icon: "error", title: "خطا", text: "کاربر ذخیره نشد. دوباره تلاش کنید." });
    return;
  }

  try {
    const formData = new FormData();
    formData.append("photo", await makeSquareWebp(file, 512, 0.72, "photo.webp"));
    formData.append("thumbnail", await makeSquareWebp(file, 50, 0.48, "thumbnail.webp"));

    const { data } = await axios.post(
      `/api/settings/users/${item.id}/photo`,
      formData,
      { headers: { "Accept": "application/json" } }
    );

    if (data.user) {
      const targetIndex = index >= 0 ? index : passwords.value.findIndex(row => row.id === data.user.id);
      if (targetIndex >= 0) {
        passwords.value[targetIndex] = {
          ...passwords.value[targetIndex],
          profile_photo_path: data.user.profile_photo_path,
          profile_thumbnail_path: data.user.profile_thumbnail_path,
          profile_photo_url: data.user.profile_photo_url,
          profile_thumbnail_url: data.user.profile_thumbnail_url,
          avatar_url: data.user.avatar_url
        };
      }
    }

    await Swal.fire({ icon: "success", title: "عکس ذخیره شد", timer: 1400, showConfirmButton: false });
  } catch (error) {
    await Swal.fire({ icon: "error", title: "خطا", text: "ذخیره عکس انجام نشد." });
  }
};

const saveAttendanceStatus = async () => {
  if (!isSuperAdmin.value) return;
  attendanceSaving.value = true;
  try {
    const { data } = await axios.post("/api/settings/attendance-status", { enabled: attendanceEnabled.value });
    window.dispatchEvent(new CustomEvent("app:attendance-status-changed", { detail: { enabled: data.enabled } }));
    await Swal.fire({ icon: "success", title: data.enabled ? "حضور و غیاب فعال شد" : "حضور و غیاب غیرفعال شد", timer: 1400, showConfirmButton: false });
  } catch (error) {
    attendanceEnabled.value = !attendanceEnabled.value;
    await Swal.fire({ icon: "error", title: "تغییر انجام نشد", text: error.response?.data?.message || "فقط مدیر کل اجازه تغییر این تنظیم را دارد." });
  } finally {
    attendanceSaving.value = false;
  }
};

const saveSmsSettings = async () => {
  const invalidTemplate = smsSettings.value.templates.find(
    template => !template.title.trim() || !template.content.trim()
  );

  if (invalidTemplate) {
    await Swal.fire({
      icon: "warning",
      title: "الگوی ناقص",
      text: "عنوان و نام الگوی SHSMS را برای همه الگوها کامل کنید."
    });
    return;
  }

  savingSms.value = true;
  try {
    const { data } = await axios.post("/api/settings/sms", {
      provider: smsSettings.value.provider,
      templates: smsSettings.value.templates
      ,birthday: smsSettings.value.birthday
      ,lead_alerts: smsSettings.value.lead_alerts
    });

    if (data.sms_settings) {
      smsSettings.value = data.sms_settings;
    }

    await Swal.fire({
      icon: "success",
      title: "ذخیره شد",
      text: data.message,
      timer: 2000,
      showConfirmButton: false
    });
  } catch (error) {
    const validationErrors = error.response?.data?.errors;
    const message = validationErrors
      ? Object.values(validationErrors).flat()[0]
      : "ذخیره تنظیمات پیامک انجام نشد.";
    await Swal.fire({ icon: "error", title: "خطا", text: message });
  } finally {
    savingSms.value = false;
  }
};

// ۳. ذخیره کاربران و سطوح دسترسی به لاراول
const saveAccessSettings = async () => {
  try {
    const res = await fetch("/api/settings/access", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        passwords: passwords.value,
        access_sections: accessSections.value
      })
    });
    const data = await res.json();

    if (res.ok) {
      Swal.fire({ icon: "success", title: "موفقیت آمیز", text: data.message, timer: 2000, showConfirmButton: false });
    }
  } catch (e) {
    Swal.fire({ icon: "error", title: "خطا", text: "ذخیره دسترسی‌ها انجام نشد." });
  }
};

onMounted(() => {
  fetchSettings();
  fetchPaymentOptions();
  loadSatisfactionSettings();
});
</script>

<style scoped>
/* کدهای استایل قبلی شما پابرجا بماند... فقط این استایل دکمه ذخیره را ته استایلت اضافه کن */
.save-all-btn {
  background: #22c55e;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 14px;
  font-weight: bold;
  cursor: pointer;
  margin-top: 10px;
  align-self: flex-start;
  transition: 0.3s;
}
.save-all-btn:hover {
  background: #16a34a;
  transform: translateY(-2px);
}
.settings-page{ direction:rtl; padding:24px; }
.top-tabs{ display:flex; justify-content:flex-start; flex-wrap:wrap; gap:8px; margin-bottom:18px; }
.tab-btn{ min-height:36px; display:flex; align-items:center; justify-content:center; background:#eef5ff; color:#2563eb; padding:8px 15px; border-radius:11px; font-size:11px; font-weight:800; cursor:pointer; transition:0.3s; border:1px solid #dbeafe; }
.tab-btn:hover{ transform:translateY(-2px); }
.tab-btn.active{ background:#2563eb; color:white; box-shadow:0 6px 14px rgba(37,99,235,.2); }
.attendance-master-setting{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:14px;padding:16px 18px;border:1px solid #bfdbfe;border-radius:16px;background:linear-gradient(135deg,#eff6ff,#f8fafc)}.attendance-master-setting>div>span{color:#2563eb;font-size:9px;font-weight:900}.attendance-master-setting h3{margin:4px 0;color:#1e3a8a;font-size:15px}.attendance-master-setting p{margin:0;color:#64748b;font-size:10px}
.content-wrapper{ background:#eef5ff; border-radius:28px; padding:22px; display:flex; flex-direction:column; gap:18px; align-items:flex-end; }
.roles-settings-wrapper{ width:100%; }
.payment-settings-wrapper{width:100%;box-sizing:border-box;padding:24px;border:1px solid #dbeafe;border-radius:26px;background:linear-gradient(145deg,#f8fbff,#eef5ff);box-shadow:0 14px 38px rgba(37,99,235,.08)}
.payment-settings-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:0 3px}.payment-settings-head h2{margin:5px 0;color:#172033;font-size:21px}.payment-settings-head p{margin:0;color:#64748b;font-size:12px}.payment-settings-status{padding:8px 12px;border:1px solid #bbf7d0;border-radius:999px;background:#f0fdf4;color:#15803d;font-size:10px;font-weight:900;white-space:nowrap}
.payment-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.payment-option-card{padding:18px;border:1px solid #e2e8f0;border-radius:20px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.05)}.payment-option-card>header{display:flex;align-items:center;gap:11px;padding-bottom:14px;border-bottom:1px solid #edf2f7}.payment-option-card h3{margin:0 0 4px;color:#1e293b;font-size:15px}.payment-option-card p{margin:0;color:#94a3b8;font-size:10px}.payment-option-icon{width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;border-radius:13px;color:#fff;font-size:22px;font-weight:900}.payment-option-icon.method{background:linear-gradient(135deg,#2563eb,#60a5fa)}.payment-option-icon.account{background:linear-gradient(135deg,#059669,#34d399)}.payment-option-icon.service{background:linear-gradient(135deg,#7c3aed,#06b6d4)}
.payment-option-list{display:grid;gap:9px;margin:15px 0}.payment-option-row{display:grid;grid-template-columns:28px 1fr 34px;align-items:center;gap:8px}.payment-option-row>span{width:26px;height:26px;display:grid;place-items:center;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:10px;font-weight:900}.payment-option-row input{box-sizing:border-box;height:42px;padding:0 12px;border:1px solid #dbe3ed;border-radius:11px;background:#f8fafc}.payment-option-row input:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.13)}.payment-option-row button{width:34px;height:34px;border:0;border-radius:9px;background:#fee2e2;color:#dc2626;font-size:20px;cursor:pointer}.payment-option-row button:disabled{cursor:not-allowed;opacity:.35}
.add-payment-option{width:100%;height:40px;border:1px dashed #93c5fd;border-radius:11px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:11px;font-weight:900;cursor:pointer}.add-payment-option:hover{border-style:solid;background:#dbeafe}
.payment-settings-actions{display:flex;align-items:center;justify-content:flex-end;gap:14px;margin-top:18px}.payment-settings-actions span{margin-left:auto;color:#15803d;font-size:11px;font-weight:800}.payment-settings-actions span.error{color:#dc2626}.payment-settings-actions button{min-width:190px;height:44px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900;cursor:pointer;box-shadow:0 8px 18px rgba(37,99,235,.2)}.payment-settings-actions button:disabled{cursor:wait;opacity:.65}
.accordion{ background:white; border-radius:22px; overflow:hidden; border:1px solid #e8eefc; box-shadow: 0 4px 18px rgba(0,0,0,0.03); width:50%; margin-right:0; margin-left:auto; }
.accordion-header{ padding:18px 22px; font-weight:700; text-align:right; cursor:pointer; transition:0.3s; }
.accordion-header:hover{ background:#f8fbff; }
.accordion-body{ padding:20px; display:flex; flex-direction:column; gap:14px; border-top:1px solid #eef2ff; }
.level-settings-help{ margin:0; color:#64748b; font-size:13px; line-height:1.9; }
.level-settings-columns{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.level-settings-card{display:grid;gap:10px;padding:13px;border:1px solid #dbe3ed;border-radius:14px;background:#fff}
.level-settings-card h4{margin:0;padding-bottom:8px;border-bottom:1px solid #edf2f7;font-size:14px;font-weight:900}
.level-settings-card label{display:flex;flex-direction:column;gap:7px;color:#334155;font-size:11px;font-weight:800}
.level-settings-card input{direction:ltr;text-align:left}
.level-settings-card.blue{border-color:#bfdbfe;background:#f8fbff}.level-settings-card.blue h4{color:#1d4ed8}
.level-settings-card.silver{border-color:#cbd5e1;background:#f8fafc}.level-settings-card.silver h4{color:#475569}
.level-settings-card.gold{border-color:#fde68a;background:#fffdf3}.level-settings-card.gold h4{color:#92400e}
input, textarea{ width:100%; border:none; padding:14px 16px; border-radius:16px; outline:none; transition:0.3s; font-size:14px; text-align:right; }
.green-input{ background:#effcf3 !important; }
.green-input:focus{ background:#e3f9e8 !important; box-shadow: 0 0 0 4px rgba(34,197,94,0.12) !important; }
textarea{ min-height:120px; resize:none; }
.compact-checks{ display:flex; flex-wrap:wrap; justify-content:flex-start; direction:rtl; gap:6px 10px; align-items:center; }
.compact-checks label{ display:flex; flex-direction:row-reverse; align-items:center; gap:5px; background:#f8fbff; padding:7px 10px; border-radius:12px; font-size:13px; width:fit-content; white-space:nowrap; }
.compact-checks span{ white-space:nowrap; }
.row-box{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.user-definition-card{ display:flex; flex-direction:column; gap:12px; padding:16px; border:1px solid #dcfce7; border-radius:18px; background:#fbfffc; }
.user-photo-row{ display:flex; align-items:center; justify-content:flex-start; gap:10px; color:#334155; font-size:13px; font-weight:800; }
.settings-user-avatar{ width:58px; height:58px; border-radius:14px; border:1px solid #dbeafe; background:#eef5ff; color:#2563eb; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer; font-weight:900; }
.settings-user-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
.settings-user-avatar input{ display:none; }
.user-fields{ grid-template-columns:repeat(3, minmax(0, 1fr)); }
.user-role-title{ color:#374151; font-size:13px; font-weight:800; }
.user-role-picker{ display:flex; flex-wrap:wrap; gap:8px; }
.user-role-picker label{ display:flex; align-items:center; gap:6px; padding:8px 11px; border:1px solid #dbeafe; border-radius:11px; background:#f8fbff; cursor:pointer; font-size:12px; }
.user-role-picker input{ width:auto; padding:0; accent-color:#2563eb; }
.user-role-picker small{ color:#94a3b8; }
.permission-box{ display:flex; flex-direction:column; gap:10px; background:#fcfdff; padding:14px; border-radius:18px; border:1px solid #edf2ff; }
.btn-group{ display:flex; justify-content:flex-end; gap:10px; }
.add-btn, .remove-btn{ width:44px; height:44px; border:none; border-radius:14px; cursor:pointer; color:white; font-size:22px; transition:0.3s; }
.add-btn{ background:#2563eb; } .remove-btn{ background:#ef4444; }
.add-btn:hover, .remove-btn:hover{ transform:translateY(-2px); }
.upload-box{ display:flex; flex-direction:column; gap:10px; background:#f8fbff; padding:16px; border-radius:16px; }
.schedule-interval-field{ display:flex; flex-direction:column; gap:7px; color:#334155; font-size:12px; font-weight:800; }
.schedule-interval-field input{ max-width:170px; }
.schedule-days-grid{ display:grid; gap:10px; }
.schedule-day-row{ display:grid; grid-template-columns:120px 1fr 1fr; gap:10px; align-items:center; padding:10px; border:1px solid #e2e8f0; border-radius:14px; background:#f8fbff; }
.schedule-day-active{ display:flex; align-items:center; gap:7px; font-size:13px; font-weight:800; color:#334155; white-space:nowrap; }
.schedule-day-active input{ width:auto; padding:0; accent-color:#2563eb; }
.schedule-day-row input[type="time"]{ min-width:0; padding:10px 12px; }

.sms-settings-wrapper {
  display: flex;
  flex-direction: column;
  gap: 18px;
  width: 100%;
}

.sms-provider-card,
.sms-templates-section {
  box-sizing: border-box;
  border: 1px solid #dfe8f5;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 12px 32px rgba(15, 23, 42, .06);
}

.birthday-sms-card{display:grid;grid-template-columns:1fr auto;gap:14px;margin-bottom:20px;padding:20px;border:1px solid #fde68a;border-radius:18px;background:linear-gradient(135deg,#fffbeb,#fff7ed);box-shadow:0 10px 30px rgba(245,158,11,.08)}.birthday-sms-card h3{margin:4px 0;color:#92400e}.birthday-sms-card p{margin:0;color:#78716c;font-size:12px}.birthday-sms-card .template-message-field,.birthday-sms-card .template-variables{grid-column:1/-1}
.lead-alert-sms-card{margin-bottom:20px;padding:20px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(135deg,#eff6ff,#f8fafc);box-shadow:0 10px 30px rgba(37,99,235,.08)}.lead-alert-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px}.lead-alert-head h3{margin:4px 0;color:#1e3a8a}.lead-alert-head p{margin:0;color:#64748b;font-size:12px}.lead-recipient-field{display:grid;gap:7px;margin-top:18px;color:#334155;font-size:12px;font-weight:900}.lead-recipient-field>div{display:flex;gap:8px}.lead-recipient-field input{flex:1;height:44px;padding:0 13px;border:1px solid #bfdbfe;border-radius:11px;background:#fff;font-family:inherit}.lead-recipient-field button{padding:0 16px;border:0;border-radius:11px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900;cursor:pointer}.lead-recipient-field small{color:#64748b;font-weight:500}.lead-recipient-list{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.lead-recipient-list>span{display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:20px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:900;direction:ltr}.lead-recipient-list button{width:20px;height:20px;padding:0;border:0;border-radius:50%;background:#fff;color:#dc2626;cursor:pointer}.lead-alert-options{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.lead-alert-options>label{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #dbeafe;border-radius:13px;background:#fff;cursor:pointer}.lead-alert-options input{width:18px;height:18px;accent-color:#2563eb}.lead-alert-options span{display:grid;gap:3px}.lead-alert-options b{color:#1e293b;font-size:11px}.lead-alert-options small{color:#64748b;font-size:9px}
.lead-alert-options-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid #dbeafe}.lead-alert-options-head>div{display:grid;gap:3px}.lead-alert-options-head strong{color:#1e3a8a;font-size:12px}.lead-alert-options-head small{color:#64748b;font-size:10px}.select-all-leads{display:flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #93c5fd;border-radius:10px;background:#fff;color:#1d4ed8;font-size:10px;font-weight:900;cursor:pointer}.select-all-leads input{width:17px;height:17px;accent-color:#2563eb}
.required-fields-settings{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:16px;padding:16px;border:1px solid #dbeafe;border-radius:16px;background:#f8fbff}.required-fields-head{grid-column:1/-1;display:flex;justify-content:space-between;align-items:center}.required-fields-head small{color:#64748b}.required-fields-settings label{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px;border-radius:10px;background:#fff;color:#334155;font-size:11px;font-weight:800}.required-fields-settings select{height:34px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-family:inherit}@media(max-width:800px){.required-fields-settings{grid-template-columns:1fr}.required-fields-head{align-items:flex-start;flex-direction:column}}

.sms-provider-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 22px;
}

.sms-provider-card h3,
.sms-section-head h3 {
  margin: 5px 0 0;
  color: #172033;
  font-size: 19px;
}

.sms-provider-card p,
.sms-section-head p {
  margin: 7px 0 0;
  color: #64748b;
  font-size: 12px;
}

.section-eyebrow {
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}

.provider-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  width: min(320px, 100%);
  color: #475569;
  font-size: 12px;
  font-weight: 800;
}

.provider-field select,
.template-fields select {
  min-height: 44px;
  padding: 0 13px;
  border: 1px solid #bfcee0;
  border-radius: 12px;
  background: #f8fbff;
  color: #172033;
  font-family: inherit;
  outline: none;
}

.provider-field small {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #16a34a;
  font-weight: 700;
}

.provider-field small i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 4px rgba(34, 197, 94, .12);
}

.sms-templates-section {
  padding: 22px;
}

.sms-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 18px;
  border-bottom: 1px solid #e8eef6;
}

.add-template-btn,
.save-sms-btn {
  min-height: 42px;
  padding: 0 16px;
  border: 0;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  cursor: pointer;
  font-family: inherit;
  font-weight: 900;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .2);
}

.template-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}

.template-card {
  padding: 16px;
  border: 1px solid #dfe8f5;
  border-radius: 16px;
  background: #fbfdff;
}

.template-card-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}

.template-card-head > strong {
  margin-left: auto;
  color: #334155;
  font-size: 13px;
}

.active-switch {
  display: flex;
  align-items: center;
  gap: 5px;
  color: #16a34a;
  font-size: 11px;
  font-weight: 800;
}

.active-switch input {
  width: auto;
  accent-color: #22c55e;
}

.delete-template-btn {
  padding: 6px 10px;
  border: 1px solid #fecaca;
  border-radius: 8px;
  background: #fff1f2;
  color: #dc2626;
  cursor: pointer;
  font-family: inherit;
  font-size: 11px;
  font-weight: 800;
}

.template-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.template-fields label,
.template-message-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  color: #475569;
  font-size: 12px;
  font-weight: 800;
}

.template-fields input,
.template-message-field textarea {
  box-sizing: border-box;
  border: 1px solid #cbd8e8;
  border-radius: 12px;
  background: #fff;
}

.template-message-field {
  position: relative;
  margin-top: 11px;
}

.template-message-field textarea {
  min-height: 105px;
  padding-bottom: 27px;
}

.template-message-field > small {
  position: absolute;
  bottom: 8px;
  left: 10px;
  color: #94a3b8;
  font-size: 9px;
}

.template-variables {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
}

.template-variables span {
  color: #64748b;
  font-size: 10px;
}

.template-variables button {
  padding: 4px 7px;
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  background: #eff6ff;
  color: #1d4ed8;
  cursor: pointer;
  direction: ltr;
  font-size: 10px;
}

.empty-templates {
  margin-top: 18px;
  padding: 32px;
  border: 1px dashed #bfcee0;
  border-radius: 14px;
  background: #f8fbff;
  color: #64748b;
  text-align: center;
  font-size: 12px;
}

.save-sms-btn {
  display: block;
  margin: 18px 0 0 auto;
  background: #16a34a;
  box-shadow: 0 8px 18px rgba(22, 163, 74, .2);
}

.save-sms-btn:disabled {
  cursor: wait;
  opacity: .65;
}

@media(max-width:900px){ .template-list{ grid-template-columns:1fr; } }
@media(max-width:900px){.payment-settings-grid{grid-template-columns:1fr}}

.satisfaction-settings-wrapper{width:100%;box-sizing:border-box;display:grid;gap:18px;padding:24px;border:1px solid #dbeafe;border-radius:26px;background:#f8fbff;box-shadow:0 14px 38px rgba(37,99,235,.08);text-align:center}.satisfaction-settings-head{display:flex;align-items:center;justify-content:space-between;gap:18px;text-align:right}.satisfaction-settings-head span{color:#2563eb;font-size:11px;font-weight:900}.satisfaction-settings-head h2{margin:5px 0;color:#172033}.satisfaction-settings-head p{margin:0;color:#64748b;font-size:12px;line-height:1.9}.satisfaction-settings-head button,.satisfaction-actions button{height:42px;padding:0 16px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900;cursor:pointer}.satisfaction-builder{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.9fr);gap:16px;align-items:start}.satisfaction-question-list{display:grid;gap:10px}.satisfaction-question-editor{display:grid;grid-template-columns:1.2fr .7fr auto;align-items:end;gap:10px;padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;text-align:right}.question-editor-head{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between}.question-editor-head strong{color:#1e293b}.question-editor-head button{width:32px;height:32px;border:0;border-radius:9px;background:#fee2e2;color:#dc2626;font-size:19px;cursor:pointer}.question-editor-head button:disabled{opacity:.35;cursor:not-allowed}.satisfaction-question-editor label{display:grid;gap:7px;color:#334155;font-size:11px;font-weight:900}.satisfaction-question-editor input,.satisfaction-question-editor select,.satisfaction-preview input,.satisfaction-preview textarea{box-sizing:border-box;width:100%;min-height:40px;padding:0 11px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-family:inherit;text-align:center}.satisfaction-switch{align-self:center;justify-self:center}.satisfaction-preview{display:grid;gap:15px;padding:20px;border:1px solid #e2e8f0;border-radius:22px;background:#fff;text-align:center}.satisfaction-preview h3{margin:0;color:#0f172a;font-size:22px}.satisfaction-preview-question{display:grid;gap:10px;justify-items:center;padding:15px;border:1px solid #eef2f7;border-radius:16px;background:#fbfdff}.satisfaction-preview-question strong{color:#1e293b;font-size:14px}.satisfaction-options{display:flex;justify-content:center;gap:7px;flex-wrap:wrap}.satisfaction-option{min-width:72px;height:36px;padding:0 12px;border:2px solid transparent;border-radius:999px;background:#f8fafc;font-family:inherit;font-size:11px;font-weight:1000;cursor:pointer;transition:.18s}.satisfaction-option.excellent{color:#166534;background:#dcfce7}.satisfaction-option.good{color:#15803d;background:#f0fdf4}.satisfaction-option.average{color:#475569;background:#f1f5f9}.satisfaction-option.bad{color:#b91c1c;background:#fee2e2}.satisfaction-option.weak{color:#fff;background:#dc2626}.satisfaction-option.selected{transform:translateY(-2px);box-shadow:0 8px 18px rgba(15,23,42,.16)}.satisfaction-option.excellent.selected{border-color:#166534;background:#15803d;color:#fff}.satisfaction-option.good.selected{border-color:#22c55e;background:#bbf7d0;color:#14532d}.satisfaction-option.average.selected{border-color:#64748b;background:#cbd5e1;color:#1e293b}.satisfaction-option.bad.selected{border-color:#f87171;background:#fecaca;color:#7f1d1d}.satisfaction-option.weak.selected{border-color:#7f1d1d;background:#991b1b;color:#fff}.satisfaction-preview textarea{min-height:92px;padding:12px;resize:vertical}.satisfaction-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px}.satisfaction-actions span{margin-left:auto;color:#15803d;font-size:12px;font-weight:900}.satisfaction-actions button{background:#64748b}.satisfaction-actions button.primary{background:#16a34a}
.satisfaction-question-editor{cursor:grab}.satisfaction-question-editor.dragging{opacity:.55;border-color:#60a5fa;background:#eff6ff;cursor:grabbing}.drag-handle{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:16px;cursor:grab}.question-actions{display:flex;align-items:center;gap:6px}.question-actions button{background:#f1f5f9!important;color:#475569!important;font-size:14px!important}.question-actions button:last-child{background:#fee2e2!important;color:#dc2626!important;font-size:19px!important}
.satisfaction-required-toggle{height:40px;align-self:end;justify-self:start;display:inline-flex!important;grid-template-columns:none!important;align-items:center;gap:8px;padding:0 10px!important;border:1px solid #e2e8f0;border-radius:999px;background:#f8fafc;color:#64748b;font-size:11px;font-weight:900;cursor:pointer;transition:border-color .18s ease,background-color .18s ease,color .18s ease,box-shadow .18s ease}.satisfaction-required-toggle input{position:absolute;opacity:0;pointer-events:none}.satisfaction-required-toggle .toggle-track{position:relative;width:30px;height:18px;flex:0 0 30px;border-radius:999px;background:#cbd5e1;transition:background-color .18s ease}.satisfaction-required-toggle .toggle-track i{position:absolute;top:3px;right:3px;width:12px;height:12px;border-radius:999px;background:#fff;box-shadow:0 1px 4px rgba(15,23,42,.18);transition:transform .18s ease}.satisfaction-required-toggle b{line-height:1;color:inherit}.satisfaction-required-toggle.required{border-color:#bbf7d0;background:#f0fdf4;color:#15803d;box-shadow:0 8px 18px rgba(34,197,94,.08)}.satisfaction-required-toggle.required .toggle-track{background:#22c55e}.satisfaction-required-toggle.required .toggle-track i{transform:translateX(-12px)}.satisfaction-required-toggle:hover{border-color:#bfdbfe;background:#fff}

@media(max-width:768px){ .top-tabs{ align-items:center; gap:6px; } .tab-btn{flex:0 0 auto;padding:7px 12px;text-align:center}.row-box,.user-fields,.template-fields,.lead-alert-options{ grid-template-columns:1fr; } .accordion{ width:100%; } .sms-provider-card,.sms-section-head,.lead-alert-head,.satisfaction-settings-head{ align-items:stretch; flex-direction:column; } .provider-field{ width:100%; }.payment-settings-wrapper,.satisfaction-settings-wrapper{padding:16px}.payment-settings-head{align-items:flex-start;flex-direction:column}.payment-settings-actions,.satisfaction-actions{align-items:stretch;flex-direction:column}.payment-settings-actions button,.satisfaction-actions button{width:100%}.lead-recipient-field>div{flex-direction:column}.lead-recipient-field button{height:42px}.satisfaction-builder,.satisfaction-question-editor{grid-template-columns:1fr}.satisfaction-preview{padding:14px}.satisfaction-actions span{margin-left:0} }

/* Unified settings actions */
.tab-btn,
.save-all-btn,
.add-payment-option,
.payment-settings-actions button,
.lead-recipient-field button,
.add-template-btn,
.save-sms-btn,
.delete-template-btn {
  min-height: var(--ui-action-height);
  border-radius: var(--ui-action-radius);
  font-size: var(--ui-action-font-size);
  font-weight: 900;
  transition: transform 160ms ease, background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.tab-btn {
  height: var(--ui-action-height);
  padding: 0 15px;
  border: 1px solid var(--ui-action-primary-border);
  background: var(--ui-action-primary-soft);
  color: var(--ui-action-primary);
}

.tab-btn:hover,
.save-all-btn:hover,
.payment-settings-actions button:hover,
.lead-recipient-field button:hover,
.add-template-btn:hover,
.save-sms-btn:hover {
  transform: translateY(-1px);
}

.tab-btn.active {
  background: var(--ui-action-primary);
  color: #fff;
  box-shadow: var(--ui-action-shadow);
}

.save-all-btn,
.payment-settings-actions button,
.lead-recipient-field button,
.add-template-btn,
.save-sms-btn {
  min-width: 150px;
  height: var(--ui-action-height);
  padding: 0 16px;
  border: 1px solid var(--ui-action-primary);
  background: var(--ui-action-primary);
  color: #fff;
  box-shadow: var(--ui-action-shadow);
}

.save-all-btn:hover,
.payment-settings-actions button:hover,
.lead-recipient-field button:hover,
.add-template-btn:hover,
.save-sms-btn:hover {
  background: var(--ui-action-primary-hover);
  border-color: var(--ui-action-primary-hover);
}

.add-payment-option {
  height: var(--ui-action-height);
  border: 1px solid var(--ui-action-primary-border);
  background: var(--ui-action-primary-soft);
  color: var(--ui-action-primary);
}

.delete-template-btn {
  height: var(--ui-action-height);
  padding: 0 13px;
  border: 1px solid var(--ui-action-danger-border);
  background: var(--ui-action-danger-soft);
  color: var(--ui-action-danger);
}

.add-btn,
.remove-btn,
.payment-option-row button {
  width: var(--ui-action-height);
  height: var(--ui-action-height);
  border-radius: var(--ui-action-radius);
}
</style>
