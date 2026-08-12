<template>
  <div
    class="time-page"
    :class="{ 'table-view-active': appointmentView === 'table' }"
    @click="closeAllPopupsAndFilters"
  >
    <div
      v-if="isFetching || categoryFilterLoading"
      class="schedule-loading"
      role="status"
      aria-live="polite"
      @click.stop
    >
      <div class="schedule-loading-card">
        <span class="schedule-loading-spinner" aria-hidden="true"></span>
        <span>{{ categoryFilterLoading ? 'در حال اعمال فیلتر بخش‌ها...' : 'در حال بارگذاری نوبت‌ها...' }}</span>
      </div>
    </div>

    <div v-if="appointmentView === 'table'" class="top-actions">
      <button
        v-if="appointmentView === 'table'"
        class="collapse-all-btn search-collapse-btn"
        type="button"
        :title="allCollapsed ? 'باز کردن همه روزها' : 'بستن همه روزها'"
        :aria-label="allCollapsed ? 'باز کردن همه روزها' : 'بستن همه روزها'"
        @click.stop="toggleAllDays"
      >
        <span :class="['arrow-all', { collapsed: allCollapsed }]">▼</span>
      </button>
      <input 
        type="text" 
        v-model="searchQuery" 
        @input="searchTable" 
        class="global-search-box" 
        placeholder="جستجو در کل جدول..." 
      />

      <template v-if="appointmentView === 'table'">

      <button 
        class="add-day-btn icon-add-day-btn"
        type="button"
        title="افزودن روز"
        aria-label="افزودن روز"
        @click.stop="addDay"
        :disabled="days.length >= 31"
      >
        +
      </button>

      <button 
        class="sms-send-btn icon-sms-btn"
        type="button"
        title="ارسال پیامک"
        aria-label="ارسال پیامک"
        @click.stop="openSmsPanel"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h18v11H3z"/><path d="m4 7 8 6 8-6"/></svg>
      </button>
      <div class="appointment-view-switch" role="tablist" aria-label="حالت نمایش نوبت‌ها">
        <button type="button" role="tab" title="نمایش جدولی" aria-label="نمایش جدولی" :aria-selected="appointmentView === 'table'" :class="{ active: appointmentView === 'table' }" @click="appointmentView = 'table'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M4 10h16M9 5v14M15 5v14"/></svg></button>
        <button type="button" role="tab" title="نمایش تایم‌لاین" aria-label="نمایش تایم‌لاین" :aria-selected="appointmentView === 'timeline'" :class="{ active: appointmentView === 'timeline' }" @click="appointmentView = 'timeline'"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></button>
      </div>
      <div v-if="showBestStaffCard" class="best-staff-month-card">
        <img v-if="bestStaffOfMonth.image" :src="bestStaffOfMonth.image" alt="">
        <span v-else class="best-staff-avatar">{{ bestStaffOfMonth.name.charAt(0) }}</span>
        <span class="best-staff-copy"><small>بهترین پرسنل ماه</small><strong>{{ bestStaffOfMonth.name }}</strong></span>
        <b>{{ bestStaffOfMonth.count.toLocaleString('fa-IR') }} نوبت</b>
      </div>
      </template>
    </div>

    <div v-if="appointmentView === 'table'" class="table-container">
      <table class="main-schedule-table">

        <thead>
          <tr>

            <th
              class="sticky-header resizable-th"
              :style="{ width: columnWidths.lastname + 'px' }"
            >
               نام و نام خانوادگی
              <div
                class="resize-handle"
                @mousedown="startResize($event, 'lastname')"
                @dblclick.stop="autoFitColumn($event, 'lastname')"
              ></div>
            </th>

            <th
              class="sticky-header resizable-th"
              :style="{ width: columnWidths.gender + 'px' }"
            >
              جنسیت
              <div
                class="resize-handle"
                @mousedown="startResize($event, 'gender')"
                @dblclick.stop="autoFitColumn($event, 'gender')"
              ></div>
            </th>

            <th
              class="sticky-header resizable-th"
              :style="{ width: columnWidths.phone + 'px' }"
            >
              شماره تماس
              <div
                class="resize-handle"
                @mousedown="startResize($event, 'phone')"
                @dblclick.stop="autoFitColumn($event, 'phone')"
              ></div>
            </th>

            <th
              class="sticky-header file-col resizable-th"
              :style="{ width: columnWidths.fileNumber + 'px' }"
            >
              شماره پرونده
              <div
                class="resize-handle"
                @mousedown="startResize($event, 'fileNumber')"
                @dblclick.stop="autoFitColumn($event, 'fileNumber')"
              ></div>
            </th>

            <th
              class="sticky-header time-col resizable-th"
              :style="{ width: columnWidths.time + 'px' }"
            >
              ساعت
              <div
                class="resize-handle"
                @mousedown="startResize($event, 'time')"
                @dblclick.stop="autoFitColumn($event, 'time')"
              ></div>
            </th>

            <th
              class="sticky-header resizable-th"
              :style="{ width: columnWidths.status + 'px' }"
              :class="{ 'filtered-header': selectedStatuses.length > 0 }"
            >
              <div class="header-with-filter">
                <span>وضعیت</span>

                <button
                  class="filter-btn"
                  @click.stop="toggleStatusFilter"
                >
                  ⚙
                </button>
              </div>

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'status')"
                @dblclick.stop="autoFitColumn($event, 'status')"
              ></div>

              <div
                v-if="showStatusFilter"
                class="filter-dropdown"
                @click.stop
              >
                <label>
                  <input
                    type="checkbox"
                    value="وقت داده شد"
                    v-model="selectedStatuses"
                  />
                  وقت داده شد
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="آمد"
                    v-model="selectedStatuses"
                  />
                  آمد
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="کنسل شد"
                    v-model="selectedStatuses"
                  />
                  کنسل شد
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="پاسخ نداد"
                    v-model="selectedStatuses"
                  />
                  پاسخ نداد
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="پیگیری"
                    v-model="selectedStatuses"
                  />
                  پیگیری
                </label>
              </div>
            </th>

            <th class="sticky-header service-type-col resizable-th" :class="{ 'filtered-header': selectedServiceSections.length > 0 }" :style="{ width: columnWidths.serviceType + 'px' }">
              <div class="header-with-filter service-section-header-filter">
                <span>بخش</span>
                <button type="button" class="filter-btn service-section-filter-dot" :class="{ active: selectedServiceSections.length }" title="فیلتر بر اساس بخش خدمات" aria-label="فیلتر بر اساس بخش خدمات" @click.stop="toggleServiceSectionFilter"><span v-if="selectedServiceSections.length">{{ selectedServiceSections.length }}</span></button>
                <div v-if="showServiceSectionFilter" class="section-filter-menu service-section-header-menu" @click.stop>
                  <div class="section-filter-title">فیلتر بر اساس بخش خدمات</div>
                  <label v-for="section in sortedServiceSections" :key="section.id"><input v-model="selectedServiceSections" type="checkbox" :value="section.id" @change="applyServiceSectionFilter"><span>{{ section.name }}</span></label>
                  <div v-if="!serviceSections.length" class="section-filter-empty">بخشی تعریف نشده است.</div>
                  <button v-if="selectedServiceSections.length" type="button" class="section-filter-clear" @click.stop="clearServiceSectionFilter">پاک کردن فیلتر</button>
                </div>
              </div>
              <div class="resize-handle" @mousedown="startResize($event, 'serviceType')" @dblclick.stop="autoFitColumn($event, 'serviceType')"></div>
            </th>

            <th
              class="sticky-header resizable-th"
              :style="{ width: columnWidths.source + 'px' }"
              :class="{ 'filtered-header': selectedSources.length > 0 }"
            >
              <div class="header-with-filter">
                <span>منبع</span>

                <button
                  class="filter-btn"
                  @click.stop="toggleSourceFilter"
                >
                  ⚙
                </button>
              </div>

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'source')"
                @dblclick.stop="autoFitColumn($event, 'source')"
              ></div>

              <div
                v-if="showSourceFilter"
                class="filter-dropdown"
                @click.stop
              >
                <label
                  v-for="src in sourceOptions"
                  :key="src.id"
                >
                  <input
                    type="checkbox"
                    :value="src.name"
                    v-model="selectedSources"
                  />

                  <span class="source-option-content"><span v-if="src.icon" class="source-icon">{{ src.icon }}</span><span>{{ src.name }}</span></span>
                </label>
              </div>
            </th>

            <th
              class="sticky-header desc-col resizable-th"
              :style="{ width: columnWidths.description + 'px' }"
            >
              توضیحات

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'description')"
                @dblclick.stop="autoFitColumn($event, 'description')"
              ></div>
            </th>

            <th
              class="sticky-header resizable-th"
              :style="{ width: columnWidths.done + 'px' }"
              :class="{ 'filtered-header': selectedDone.length > 0 }"
            >
              <div class="header-with-filter">
                <span>انجام کار</span>

                <button
                  class="filter-btn"
                  @click.stop="toggleDoneFilter"
                >
                  ⚙
                </button>
              </div>

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'done')"
                @dblclick.stop="autoFitColumn($event, 'done')"
              ></div>

              <div
                v-if="showDoneFilter"
                class="filter-dropdown"
                @click.stop
              >
                <label>
                  <input
                    type="checkbox"
                    value="انجام شد"
                    v-model="selectedDone"
                  />
                  انجام شد
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="انجام نشد"
                    v-model="selectedDone"
                  />
                  انجام نشد
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="ترمیم"
                    v-model="selectedDone"
                  />
                  ترمیم
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="انتقال"
                    v-model="selectedDone"
                  />
                  انتقال
                </label>

                <label>
                  <input
                    type="checkbox"
                    value="مشاوره"
                    v-model="selectedDone"
                  />
                  مشاوره
                </label>
              </div>
            </th>

            <th
              class="sticky-header amount-col resizable-th"
              :style="{ width: columnWidths.amount + 'px' }"
            >
              مبلغ

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'amount')"
                @dblclick.stop="autoFitColumn($event, 'amount')"
              ></div>
            </th>

            <th
              v-if="appointmentColumns.payment_link"
              class="sticky-header payment-link-col resizable-th"
              :style="{ width: columnWidths.paymentLink + 'px' }"
            >
              لینک پرداخت

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'paymentLink')"
                @dblclick.stop="autoFitColumn($event, 'paymentLink')"
              ></div>
            </th>



            <th
              class="sticky-header sms-col resizable-th"
              :style="{ width: columnWidths.appointmentSms + 'px' }"
            >
              پیامک وقت دهی

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'appointmentSms')"
                @dblclick.stop="autoFitColumn($event, 'appointmentSms')"
              ></div>
            </th>

            <th
              class="sticky-header sms-col resizable-th"
              :style="{ width: columnWidths.infoSms + 'px' }"
            >
              پیامک اطلاعات

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'infoSms')"
                @dblclick.stop="autoFitColumn($event, 'infoSms')"
              ></div>
            </th>

            <th
              class="sticky-header service-col resizable-th"
              :style="{ width: columnWidths.service + 'px' }"
            >
              خدمات

              <div
                class="resize-handle"
                @mousedown="startResize($event, 'service')"
                @dblclick.stop="autoFitColumn($event, 'service')"
              ></div>
            </th>

            <th class="sticky-header row-action-col">
              عملیات
            </th>

          </tr>
        </thead>

        <tbody
          v-for="day in days"
          :key="day.id"
          :data-day-number="day.dayNum"
        >

          <tr
            class="day-separator-row"
            :class="{ 'holiday-day': day.isHoliday, 'today-day': isToday(day) }"
          >
            <td :colspan="appointmentTableColspan">

              <div class="day-row-content">

                <div class="day-btns-left">

                  <button
                    class="collapse-btn"
                    @click.stop="toggleDay(day)"
                  >
                    <span
                      :class="['arrow', { collapsed: day.collapsed }]"
                    >
                      ▼
                    </span>
                  </button>

                  <button
                    class="mini-btn"
                    @click.stop="removeRow(day)"
                  >
                    -
                  </button>

                  <button
                    class="mini-btn delete-all-day-btn"
                    title="حذف این روز و تمام ردیف‌های آن"
                    @click.stop="clearDayRows(day)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M3 6h18" />
                      <path d="M8 6V4h8v2" />
                      <path d="M6 6l1 15h10l1-15" />
                      <path d="M10 11v6" />
                      <path d="M14 11v6" />
                    </svg>
                  </button>

                  <button
                    class="mini-btn"
                    title="افزودن روز بعد"
                    aria-label="افزودن روز بعد"
                    @click.stop="addNextDayAfter(day)"
                  >
                    +
                  </button>

                  <button
                    class="mini-btn day-chart-trigger"
                    title="گزارش روز"
                    aria-label="گزارش روز"
                    @click.stop="openDailyReport(day)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M5 19V5" />
                      <path d="M5 19h14" />
                      <path d="M9 16v-5" />
                      <path d="M13 16V8" />
                      <path d="M17 16v-7" />
                    </svg>
                  </button>

                  <div class="day-summary-left">
                    <span>
                      جمع صندوق:
                      {{ formatDisplayMoney(getDayTotalAmount(day)) }}
                    </span>

                    <span>
                      سود کلینیک:
                      {{ formatDisplayMoney(getDayClinicProfit(day)) }}
                    </span>
                  </div>

                </div>

                <span class="day-label">
                  {{ day.dateLabel }}
                  <small v-if="isToday(day)" class="today-badge">امروز</small>
                </span>

                <div
                  v-if="day.holidayTitle"
                  class="holiday-event"
                >
                  {{ day.holidayTitle }}
                </div>

              </div>

            </td>
          </tr>

          <template v-if="!day.collapsed">

          <tr
            v-for="row in getFilteredRows(day)"
            :key="row._rowId"
            :id="'row-' + row._rowId"
            :class="[
              'data-row',
              row._visibleIndex % 2 === 1 ? 'stripe' : '',
              highlightedRowId === row._rowId ? 'search-highlight-row' : '',
              isDebtor(row) ? 'debtor-row' : '',
              isCreditor(row) ? 'creditor-row' : '',
              isProblematicCustomer(row) ? 'problematic-customer-row' : ''
            ]"
          >

              <td :style="{ width: columnWidths.lastname + 'px' }" style="text-align: center !important;">
                <div class="appointment-patient-name">
                  <PatientAvatar
                    v-if="row.hasPatientFile"
                    :src="row.profileThumbnailUrl"
                    :level="row.customerLevel"
                    :size="24"
                    clickable
                    title="باز کردن پرونده"
                    @mouseenter="showAvatarPreview($event, row.profilePhotoUrl || row.profileThumbnailUrl)"
                    @mouseleave="hideAvatarPreview"
                    @click.stop="openPatientProfileFromRow(row)"
                  />
                  <span v-else-if="row.lastname" class="no-patient-file" title="برای این شخص پرونده تشکیل نشده است">×</span>
                  <span v-if="isDebtor(row)" class="debtor-warning-icon" :title="`هشدار بدهکاری: ${formatDisplayMoney(patientDebtAmount(row))} تومان`">!</span>
                  <span v-if="isCreditor(row)" class="creditor-warning-icon" :title="`طلبکار: ${formatDisplayMoney(Math.abs(appointmentBalanceAmount(row)))} تومان`">ط</span>
                  <input
                    v-model="row.lastname"
                    :class="{ 'problematic-customer-name': isProblematicCustomer(row) }"
                    title="باز کردن پرونده"
                    @click.stop="openPatientProfileFromRow(row)"
                    @input="autoSetAppointmentStatus(row)"
                  />
                </div>
              </td>

              <td :style="{ width: columnWidths.gender + 'px' }">
                <select v-model="row.gender">
                  <option value="">-</option>
                  <option>زن</option>
                  <option>مرد</option>
                </select>
              </td>

              <td :style="{ width: columnWidths.phone + 'px' }">
                <input
                  v-if="canViewPatientPhone"
                  v-model="row.phone"
                  @input="autoSetAppointmentStatus(row)"
                  @blur="fillPatientByPhone(row)"
                />
                <input
                  v-else
                  :value="displayPatientPhone(row.phone)"
                  readonly
                  class="masked-phone-input"
                  title="نمایش شماره تماس برای این نقش غیرفعال است"
                />
              </td>

              <td
                class="file-col"
                :style="{ width: columnWidths.fileNumber + 'px' }"
              >
                <input
                  v-model="row.fileNumber"
                  @blur="fillPatientByFileNumber(row)"
                  @keyup.enter="fillPatientByFileNumber(row)"
                />
              </td>

              <td
                class="time-col"
                :style="{ width: columnWidths.time + 'px' }"
              >

                <date-picker
                  v-model="row.time"
                  type="time"
                  format="HH:mm"
                  display-format="HH:mm"
                  :auto-submit="true"
                  :editable="false"
                  :clearable="false"
                  :jump-minute="appointmentMinuteStep"
                  :round-minute="appointmentMinuteStep > 1"
                  input-class="time-picker-input"
                  popover-class="time-picker-popover"
                  @update:model-value="sortDayRowsByTime(day)"
                />

              </td>

              <td
                :style="{ width: columnWidths.status + 'px' }"
              >
                <select
                  v-model="row.status"
                  :class="statusColor(row.status)"
                  @change="onStatusChanged(row)"
                >
                  <option value="">-</option>
                  <option value="وقت داده شد">وقت داده شد</option>
                  <option value="آمد">آمد</option>
                  <option value="کنسل شد">کنسل شد</option>
                  <option value="پاسخ نداد">پاسخ نداد</option>
                  <option value="پیگیری">پیگیری</option>
                </select>
              </td>

              <td class="service-type-col" :style="{ width: columnWidths.serviceType + 'px' }" @click.stop>
                <details class="service-type-picker" @toggle="onServiceTypePickerToggle($event)">
                  <summary :title="serviceTypeSummary(row)">
                    {{ serviceTypeSummary(row) }}
                  </summary>
                  <div class="service-type-options">
                    <label v-for="section in sortedServiceSections" :key="section.id">
                      <input v-model="row.serviceTypes" type="checkbox" :value="String(section.id)" @change="onRowServiceTypesChanged(row)">
                      <span>{{ section.name }}</span>
                    </label>
                    <small v-if="!serviceSections.length">ابتدا بخش خدمات را در انبار تعریف کنید.</small>
                  </div>
                </details>
              </td>

              <td
                class="wide-col"
                :style="{ width: columnWidths.source + 'px' }"
              >

                <select v-model="row.source">

                  <option value="">-</option>

                  <option
                    v-for="src in sourceOptions"
                    :key="src.id"
                    :value="src.name"
                  >
                    {{ src.icon ? `${src.icon} ${src.name}` : src.name }}
                  </option>

                </select>

              </td>

              <td
                class="desc-col"
                :style="{ width: columnWidths.description + 'px' }"
              >
                <div class="description-with-doctor-note">
                  <input
                    style="text-align: center !important;"
                    v-model="row.description"
                    @dblclick="showDescription(row.description)"
                  />
                  <button
                    type="button"
                    class="doctor-note-trigger"
                    :class="{ 'has-note': hasDoctorNote(row), 'unread-note': row.doctorNoteUnread, 'seen-note': hasDoctorNote(row) && !row.doctorNoteUnread }"
                    :title="hasDoctorNote(row) ? 'مشاهده گفت‌وگوی نوبت' : 'شروع گفت‌وگوی نوبت'"
                    @click.stop="openDoctorNote(row)"
                  >
                    <span class="doctor-note-icon">💬</span>
                    <span v-if="hasDoctorNote(row)" class="doctor-note-check">✓</span>
                  </button>
                </div>
              </td>

              <td
  :style="{ width: columnWidths.done + 'px' }"
  :class="doneColor(row.done)"
>

  <select v-model="row.done" @change="onDoneChanged(row)">
                  <option value="">-</option>
                  <option>انجام شد</option>
                  <option>انجام نشد</option>
                  <option>ترمیم</option>
                  <option>مشاوره</option>
                </select>

              </td>

              <td
                class="amount-col"
                :style="{ width: columnWidths.amount + 'px' }"
              >
                <div class="amount-finance-cell">
                  <input
                    :value="row.amount"
                    disabled
                    class="auto-amount-input"
                  />
                  <button
                    type="button"
                    class="finance-chat-trigger"
                    :class="{
                      danger: patientDebtAmount(row) > 0,
                      credit: Number(row.walletBalance || 0) > 0 && patientDebtAmount(row) <= 0,
                      'has-financial-balance': patientDebtAmount(row) > 0 || Number(row.walletBalance || 0) > 0
                    }"
                    :title="financialTriggerTitle(row)"
                    :aria-label="financialTriggerTitle(row)"
                    @click.stop="openFinancialPanel(row)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v10H9l-4 4V5z"/><path d="M9 9h6M9 12h4"/></svg>
                    <em v-if="patientDebtAmount(row) > 0 || Number(row.walletBalance || 0) > 0 || hasPaymentDetails(row)">پرداخت</em>
                    <span v-if="patientDebtAmount(row) > 0">!</span>
                  </button>
                </div>

              </td>

              <td
                v-if="appointmentColumns.payment_link"
                class="payment-link-col"
                :style="{ width: columnWidths.paymentLink + 'px' }"
              >
                <button
                  type="button"
                  class="payment-link-btn"
                  :class="{ sent: Number(row.paymentLinkSentCount || 0) > 0 }"
                  @click.stop="openPaymentLinkModal(day, row)"
                >
                  لینک
                  <span v-if="Number(row.paymentLinkSentCount || 0)">{{ row.paymentLinkSentCount }}</span>
                </button>
              </td>

              <td
                class="sms-col"
                :style="{ width: columnWidths.appointmentSms + 'px' }"
              >

                <select
                  v-model="row.appointmentSms"
                  :class="smsColor(row.appointmentSms)"
                >
                  <option value="">-</option>
                  <option>انتظار</option>
                  <option>ارسال شد</option>
                </select>

              </td>

              <td
                class="sms-col"
                :style="{ width: columnWidths.infoSms + 'px' }"
              >

                <select
                  v-model="row.infoSms"
                  :class="smsColor(row.infoSms)"
                >
                  <option value="">-</option>
                  <option>انتظار</option>
                  <option>ارسال شد</option>
                </select>

              </td>

              <td
                class="service-col service-cell"
                :style="{ width: columnWidths.service + 'px' }"
                @click.stop
              >

                <button
                  class="service-mini-btn"
                  :class="{ 'service-active': hasService(row) }"
                  @click.stop="toggleServicePopup(row._rowId)"
                >
                  <span class="service-mini-icon">✦</span><span>خدمات</span>
                </button>

                <div
                  v-if="activeServicePopup === row._rowId"
                  class="service-popup"
                  @click.stop
                >

                  <div class="service-popup-header">

                    <div class="service-popup-title">
                      خدمات {{ row.lastname || 'بیمار' }}
                    </div>

                    <button
                      class="add-service-line-btn"
                      :disabled="!row.serviceTypes?.length"
                      @click.stop="addService(row)"
                    >
                      + افزودن خدمت
                    </button>

                  </div>

                  <div class="referral-section">

                    <input
                      v-model="row.referrerPhone"
                      type="number"
                      placeholder="شماره موبایل معرف"
                      class="service-select"
                      @input="calculateReferralRewardForRow(row)"
                    />

                    <div class="money-input-wrap">

                      <input
                        :value="formatDisplayMoney(row.referralScore || 0)"
                        type="text"
                        placeholder="مبلغ امتیاز"
                        class="service-select money-input score-disabled"
                        disabled
                      />

                      <span class="money-suffix">
                        تومان
                      </span>

                      <button
                        class="pay-score-btn"
                        title="کسر از مبلغ"
                        @click.stop="applyReferralScore(row)"
                      >
                        💳
                      </button>

                    </div>

                    <div class="wallet-payment-box">
                      <button type="button" :disabled="!moneyToNumber(row.walletBalance)" @click.stop="applyWalletBalance(row)">
                        {{ moneyToNumber(row.walletApplied) ? `اعمال شد: ${formatDisplayMoney(row.walletApplied)} تومان` : 'پرداخت از کیف پول' }}
                      </button>
                    </div>

                  </div>

                  <div class="service-popup-meta">

                    <div
                      class="service-item"
                      v-for="(service, sIndex) in row.services"
                      :key="sIndex"
                    >

                      <div class="service-main-row">


                    <Multiselect
                      v-model="service.name"
                      :options="serviceOptionsFor(service, row)"
                      :multiple="false"
                      :searchable="true"
                      :internal-search="true"
                      :close-on-select="true"
                      :clear-on-select="false"
                      :allow-empty="true"
                      :disabled="!row.serviceTypes?.length"
                      :placeholder="row.serviceTypes?.length ? 'جستجو و انتخاب خدمت' : 'ابتدا بخش را انتخاب کنید'"
                      select-label=""
                      selected-label="انتخاب شده"
                      deselect-label="حذف"
                      class="service-multiselect"
                      @select="onServiceNameChanged(service, row)"
                      @remove="onServiceNameChanged(service, row)"
                    />

                      <select
                        v-model="service.doctor"
                        class="service-select"
                        :disabled="!row.serviceTypes?.length || !service.sectionId"
                        @change="calculateRowAmount(row)"
                      >

                        <option value="">
                          انتخاب پزشک
                        </option>

                        <option
                          v-for="doc in doctorsForService(row, service)"
                          :key="doc.id"
                          :value="doc.name"
                        >
                          {{ doc.name }}
                        </option>

                      </select>

                      <select
                        v-model="service.consultant"
                        class="service-select"
                        @change="calculateRowAmount(row)"
                      >

                        <option value="">
                          انتخاب مشاور
                        </option>

                        <option
                          v-for="cons in consultantOptions"
                          :key="cons"
                          :value="cons"
                        >
                          {{ cons }}
                        </option>

                      </select>

                      <input
                        v-model="service.cc"
                        type="text"
                        placeholder="تعداد سی‌سی"
                        class="cc-input"
                        @input="updateRowAmounts(row)"
                      />

                      <span class="service-price-chip">{{ service.name ? `${formatDisplayMoney(serviceLinePrice(service))} تومان` : 'قیمت' }}</span>
                  <div class="service-discount-wrap">
                    <input v-model="service.discount" type="text" inputmode="numeric" placeholder="مبلغ" class="service-discount-input" @input="handleServiceDiscountInput(service, row)">
                    <span>تخفیف</span>
                  </div>

                      <button type="button" class="service-addon-toggle" :class="{ active: service.addons?.length }" :disabled="!service.name" @click.stop="addServiceAddon(service)">
                        جانبی <span v-if="service.addons?.length">{{ service.addons.length }}</span><b>+</b>
                      </button>

                      <button
                        v-if="row.services.length > 1"
                        class="remove-service-btn"
                        @click.stop="removeService(row, sIndex)"
                      >
                        -
                      </button>

                      </div>

                      <div v-if="service.addons?.length" class="service-addons-panel">
                        <div class="service-addons-title"><span>جانبی‌های {{ service.name }}</span><small>امکان افزودن چند مورد</small></div>
                        <div v-for="(addon, addonIndex) in service.addons" :key="addon._key || addonIndex" class="service-addon-row">
                          <Multiselect v-model="addon.name" :options="serviceAddonOptions(service, addon)" :multiple="false" :searchable="true" :close-on-select="true" :allow-empty="true" placeholder="جستجو و انتخاب جانبی" select-label="" selected-label="انتخاب شد" deselect-label="حذف" class="service-multiselect service-addon-multiselect" @select="calculateRowAmount(row)" @remove="calculateRowAmount(row)" />
                          <input v-model="addon.cc" type="text" inputmode="numeric" placeholder="تعداد/سی‌سی" class="cc-input addon-cc-input" @input="updateRowAmounts(row)">
                          <span class="service-price-chip addon-price-chip">{{ formatDisplayMoney(serviceLinePrice(addon)) }} تومان</span>
                          <div class="service-discount-wrap addon-discount-wrap">
                            <input v-model="addon.discount" type="text" inputmode="numeric" placeholder="مبلغ" class="service-discount-input" @input="handleServiceDiscountInput(addon, row)">
                            <span>تخفیف</span>
                          </div>
                          <button type="button" class="remove-addon-btn" title="حذف جانبی" @click.stop="removeServiceAddon(service, addonIndex, row)">×</button>
                        </div>
                        <button type="button" class="add-another-addon-btn" @click.stop="addServiceAddon(service)">+ افزودن جانبی دیگر</button>
                      </div>

                    </div>

                  </div>

                </div>

              </td>

              <td class="row-action-col">
                <button
                  type="button"
                  class="insert-appointment-row-btn"
                  title="افزودن وقت بعد از این ساعت"
                  aria-label="افزودن وقت بعد از این ساعت"
                  @click.stop="insertRowAfter(day, row)"
                >
                  +
                </button>

                <button
                  type="button"
                  class="row-tracking-btn"
                  title="گزارش زمان نوبت"
                  aria-label="گزارش زمان نوبت"
                  @click.stop="openTrackingModal(day, row)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="8" />
                    <path d="M12 8v5l3 2" />
                  </svg>
                </button>

                <button
                  type="button"
                  class="row-delete-btn"
                  title="حذف این ردیف"
                  @click.stop="deleteAppointmentRow(day, row)"
                >
                  ×
                </button>
              </td>

            </tr>

          </template>

        </tbody>

      </table>
    </div>

    <div v-if="appointmentView === 'timeline'" class="timeline-actions" @click.stop>
      <button
        class="add-day-btn icon-add-day-btn"
        type="button"
        title="افزودن روز"
        aria-label="افزودن روز"
        @click.stop="addDay"
        :disabled="days.length >= 31"
      >
        +
      </button>

      <button
        class="sms-send-btn icon-sms-btn"
        type="button"
        title="ارسال پیامک"
        aria-label="ارسال پیامک"
        @click.stop="openSmsPanel"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h18v11H3z"/><path d="m4 7 8 6 8-6"/></svg>
      </button>
      <div class="appointment-view-switch" role="tablist" aria-label="حالت نمایش نوبت‌ها">
        <button type="button" role="tab" title="نمایش جدولی" aria-label="نمایش جدولی" :aria-selected="appointmentView === 'table'" :class="{ active: appointmentView === 'table' }" @click="appointmentView = 'table'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M4 10h16M9 5v14M15 5v14"/></svg></button>
        <button type="button" role="tab" title="نمایش تایم‌لاین" aria-label="نمایش تایم‌لاین" :aria-selected="appointmentView === 'timeline'" :class="{ active: appointmentView === 'timeline' }" @click="appointmentView = 'timeline'"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></button>
      </div>
      <div v-if="showBestStaffCard" class="best-staff-month-card">
        <img v-if="bestStaffOfMonth.image" :src="bestStaffOfMonth.image" alt="">
        <span v-else class="best-staff-avatar">{{ bestStaffOfMonth.name.charAt(0) }}</span>
        <span class="best-staff-copy"><small>بهترین پرسنل ماه</small><strong>{{ bestStaffOfMonth.name }}</strong></span>
        <b>{{ bestStaffOfMonth.count.toLocaleString('fa-IR') }} نوبت</b>
      </div>
      <input
        v-model="searchQuery"
        type="text"
        class="global-search-box timeline-global-search"
        placeholder="جستجو در کل نوبت‌ها..."
        aria-label="جستجو در کل نوبت‌ها"
        @input="searchTable"
      />
    </div>

    <section v-if="appointmentView === 'timeline'" class="appointment-timeline" @click.stop>
      <div v-if="!timelineDays.length" class="timeline-empty-state">
        نوبتی برای نمایش وجود ندارد.
      </div>

      <div
        v-for="day in timelineDays"
        :key="`timeline-${day.id}`"
        class="timeline-day-row"
        :data-day-number="day.dayNum"
        :class="{ 'holiday-day': day.isHoliday, 'today-day': isToday(day) }"
      >
        <aside class="timeline-day-label">
          <strong>{{ timelineDayName(day) }}</strong>
          <span>{{ timelineDayDate(day) }}</span>
          <small v-if="isToday(day)">امروز</small>
          <div class="timeline-day-actions">
            <button type="button" class="timeline-add-next-day-btn" title="افزودن روز بعد" aria-label="افزودن روز بعد" @click.stop="addNextDayAfter(day)">+</button>
            <button type="button" title="افزودن نوبت" @click.stop="openNewTimelineAppointment(day)">نوبت</button>
          </div>
        </aside>

        <div class="timeline-slots" :aria-label="`نوبت‌های ${day.dateLabel}`">
          <template v-for="(row, index) in day.timelineRows" :key="row._rowId">
            <article
              class="timeline-card"
              :class="[
                timelineCardClass(row),
                highlightedRowId === row._rowId ? 'is-highlighted' : ''
              ]"
              @click.stop="openTimelineAppointmentModal(day, row)"
            >
              <div class="timeline-time-chip">{{ timelineTimeLabel(row) }}</div>
              <span v-if="normalizeCustomerLevel(row.customerLevel) === 'gold'" class="timeline-card-crown" aria-label="مشتری طلایی">♛</span>
              <button
                v-if="!isEmptyAppointmentRow(row)"
                type="button"
                class="timeline-delete-appointment-btn"
                title="حذف این نوبت"
                aria-label="حذف این نوبت"
                @click.stop="deleteAppointmentRow(day, row)"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M6 6l1 15h10l1-15" /><path d="M10 11v6" /><path d="M14 11v6" />
                </svg>
              </button>

              <div v-if="!isEmptyAppointmentRow(row)" class="timeline-card-body">
                <div
                  class="timeline-avatar-hitbox"
                  @mouseenter="showAvatarPreview($event, row.profilePhotoUrl || row.profileThumbnailUrl)"
                  @mouseleave="hideAvatarPreview"
                  @click.stop="openPatientProfileFromRow(row)"
                >
                  <PatientAvatar
                    :src="row.profileThumbnailUrl"
                    :level="row.customerLevel"
                    :patient="{ first_name: row.lastname }"
                    :size="48"
                    clickable
                    title="باز کردن پرونده"
                  />
                </div>
                <strong title="باز کردن پرونده" @click.stop="openPatientProfileFromRow(row)">{{ timelinePatientName(row) }}</strong>
                <em class="timeline-status-label">{{ row.status || 'نوبت ثبت‌شده' }}</em>
                <span>{{ timelineServiceText(row) }}</span>
                <small>{{ timelineCareTeam(row) }}</small>
              </div>

              <div v-else class="timeline-card-empty">
                <span>+</span>
                <strong>خالی</strong>
              </div>
            </article>
          </template>

          <article
            class="timeline-card timeline-add-card is-empty"
            role="button"
            tabindex="0"
            title="ثبت نوبت جدید در انتهای این روز"
            @click.stop="openNewTimelineAppointment(day)"
            @keydown.enter.prevent="openNewTimelineAppointment(day)"
          >
            <div class="timeline-time-chip">بدون ساعت</div>
            <div class="timeline-card-empty">
              <span>+</span>
              <strong>ثبت نوبت</strong>
            </div>
          </article>
        </div>
      </div>
    </section>

    <div v-if="timelineModalOpen" class="timeline-modal-overlay" @click.self="closeTimelineModal()">
      <section class="timeline-modal" role="dialog" aria-modal="true" aria-labelledby="timeline-modal-title" @click.stop>
        <header class="timeline-modal-header">
          <div>
            <span>{{ activeTimelineDay?.dateLabel || 'نوبت‌دهی' }}</span>
            <h3 id="timeline-modal-title">{{ timelineModalTitle }}</h3>
          </div>
          <button type="button" title="بستن" @click="closeTimelineModal()">×</button>
        </header>

        <div v-if="activeTimelineDraft" class="timeline-modal-body">
          <div v-if="timelineValidationSummary" class="timeline-validation-alert" role="alert">
            {{ timelineValidationSummary }}
          </div>

          <div class="timeline-form-grid compact">
            <label :class="{ 'timeline-field-error': timelineValidationErrors.time }">
              ساعت نوبت
              <date-picker
                v-model="activeTimelineDraft.time"
                type="time"
                format="HH:mm"
                display-format="HH:mm"
                :auto-submit="true"
                :editable="false"
                :clearable="false"
                :jump-minute="appointmentMinuteStep"
                :round-minute="appointmentMinuteStep > 1"
                input-class="timeline-modal-input"
                popover-class="time-picker-popover"
                append-to="body"
                @open="raiseTimelineTimePicker"
                @change="clearTimelineValidationError('time')"
              />
              <small v-if="timelineValidationErrors.time" class="timeline-error-text">{{ timelineValidationErrors.time }}</small>
            </label>

            <label v-if="false">
              وضعیت
              <select v-model="activeTimelineDraft.status" :class="statusColor(activeTimelineDraft.status)">
                <option value="">-</option>
                <option value="ÙˆÙ‚Øª Ø¯Ø§Ø¯Ù‡ Ø´Ø¯">وقت داده شد</option>
                <option value="آمد">آمد</option>
                <option value="Ú©Ù†Ø³Ù„ Ø´Ø¯">کنسل شد</option>
                <option value="Ù¾Ø§Ø³Ø® Ù†Ø¯Ø§Ø¯">پاسخ نداد</option>
                <option value="Ù¾ÛŒÚ¯ÛŒØ±ÛŒ">پیگیری</option>
              </select>
            </label>

            <label v-if="false">
              منبع
              <select v-model="activeTimelineDraft.source">
                <option value="">-</option>
                <option v-for="src in sourceOptions" :key="src.id" :value="src.name">
                  {{ src.icon ? `${src.icon} ${src.name}` : src.name }}
                </option>
              </select>
            </label>
          </div>

          <div class="timeline-form-grid">
            <label class="timeline-patient-search-field" :class="{ 'timeline-field-error': timelineValidationErrors.lastname }">
              نام و نام خانوادگی
              <input
                v-model="activeTimelineDraft.lastname"
                type="text"
                autocomplete="off"
                placeholder="نام، شماره پرونده یا موبایل..."
                @input="onTimelinePatientSearch"
                @focus="onTimelinePatientSearch"
                @blur="closeTimelinePatientSearch"
              >
              <small v-if="timelineValidationErrors.lastname" class="timeline-error-text">{{ timelineValidationErrors.lastname }}</small>
              <div v-if="timelinePatientSearchOpen" class="timeline-patient-results" @mousedown.prevent>
                <div v-if="timelinePatientSearchLoading" class="timeline-patient-search-state">در حال جست‌وجو...</div>
                <button
                  v-for="patient in timelinePatientSearchResults"
                  v-else
                  :key="patient.id || patient.file_number"
                  type="button"
                  class="timeline-patient-result"
                  @click="selectTimelinePatient(patient)"
                >
                  <PatientAvatar :patient="patient" :level="normalizeCustomerLevel(patient.customer_level)" :size="44" />
                  <span class="timeline-patient-result-info">
                    <strong>{{ `${patient.first_name || ''} ${patient.last_name || ''}`.trim() || 'بدون نام' }}</strong>
                    <small>پرونده {{ patient.file_number || '—' }} <b>•</b> {{ displayPatientPhone(patient.phone) || 'بدون موبایل' }}</small>
                  </span>
                  <em :class="`level-${normalizeCustomerLevel(patient.customer_level)}`">{{ customerLevelLabel(patient.customer_level) }}</em>
                </button>
                <div v-if="!timelinePatientSearchLoading && !timelinePatientSearchResults.length" class="timeline-patient-search-state">پرونده‌ای با این مشخصات پیدا نشد.</div>
              </div>
            </label>

            <label :class="{ 'timeline-field-error': timelineValidationErrors.phone }">
              شماره تماس
              <input
                v-if="canViewPatientPhone"
                v-model="activeTimelineDraft.phone"
                type="text"
                @input="handleTimelinePhoneInput"
                @blur="fillPatientByPhone(activeTimelineDraft)"
              >
              <input
                v-else
                :value="displayPatientPhone(activeTimelineDraft.phone)"
                type="text"
                readonly
                class="masked-phone-input"
              >
              <small v-if="timelineValidationErrors.phone" class="timeline-error-text">{{ timelineValidationErrors.phone }}</small>
            </label>

            <label>
              شماره پرونده
              <input
                v-model="activeTimelineDraft.fileNumber"
                type="text"
                @blur="fillPatientByFileNumber(activeTimelineDraft)"
                @keyup.enter="fillPatientByFileNumber(activeTimelineDraft)"
              >
            </label>

            <label>
              جنسیت
              <select v-model="activeTimelineDraft.gender">
                <option value="">-</option>
                <option>زن</option>
                <option>مرد</option>
              </select>
            </label>
          </div>

          <section class="timeline-quick-team">
            <label>
              انتخاب پزشک (حداکثر دو نفر)
              <Multiselect
                v-model="activeTimelineDraft.timelineDoctors"
                :options="doctorOptions.map(item => item.name)"
                :multiple="true"
                :max="2"
                :searchable="true"
                :close-on-select="false"
                :clear-on-select="false"
                placeholder="یک یا دو پزشک را انتخاب کنید"
                select-label="انتخاب"
                selected-label="انتخاب شد"
                deselect-label="حذف"
                class="timeline-doctor-multiselect"
              />
            </label>
            <label>
              نام مشاور
              <select v-model="activeTimelineDraft.timelineConsultant">
                <option value="">بدون مشاور</option>
                <option v-for="consultant in consultantOptions" :key="consultant" :value="consultant">{{ consultant }}</option>
              </select>
            </label>
          </section>

          <section class="timeline-sms-options">
            <label :class="{ active: activeTimelineDraft.sendAppointmentSms }">
              <input v-model="activeTimelineDraft.sendAppointmentSms" type="checkbox">
              <span><b>پیامک وقت‌دهی</b><small>تاریخ و ساعت نوبت برای بیمار ارسال شود</small></span>
            </label>
            <label :class="{ active: activeTimelineDraft.sendInfoSms }">
              <input v-model="activeTimelineDraft.sendInfoSms" type="checkbox">
              <span><b>پیامک اطلاعات</b><small>اطلاعات مراجعه برای بیمار ارسال شود</small></span>
            </label>
          </section>

          <section v-if="false" class="timeline-service-panel">
            <div class="service-popup-header">
              <div class="service-popup-title">
                خدمات {{ activeTimelineDraft.lastname || 'بیمار' }}
              </div>

              <button type="button" class="add-service-line-btn" :disabled="!activeTimelineDraft.serviceTypes?.length" @click.stop="addService(activeTimelineDraft)">
                + افزودن خدمت
              </button>
            </div>

            <div v-if="!activeTimelineDraft.serviceTypes?.length" class="service-prerequisite">
              <span>۱</span>
              <div>
                <strong>ابتدا بخش را انتخاب کنید</strong>
                <p>یک یا چند بخش را از ستون «بخش» انتخاب کنید تا خدمات، محصولات و پزشکان مرتبط نمایش داده شوند.</p>
              </div>
            </div>

            <div class="referral-section">
              <input v-model="activeTimelineDraft.referrerPhone" type="number" placeholder="شماره موبایل معرف" class="service-select" @input="calculateReferralRewardForRow(activeTimelineDraft)" />
              <div class="money-input-wrap">
                <input :value="formatDisplayMoney(activeTimelineDraft.referralScore || 0)" type="text" placeholder="مبلغ امتیاز" class="service-select money-input score-disabled" disabled />
                <span class="money-suffix">تومان</span>
                <button type="button" class="pay-score-btn" title="کسر از مبلغ" @click.stop="applyReferralScore(activeTimelineDraft)">کارت</button>
              </div>
              <div class="wallet-payment-box">
                <button type="button" :disabled="!moneyToNumber(activeTimelineDraft.walletBalance)" @click.stop="applyWalletBalance(activeTimelineDraft)">
                  {{ moneyToNumber(activeTimelineDraft.walletApplied) ? `اعمال شد: ${formatDisplayMoney(activeTimelineDraft.walletApplied)} تومان` : 'پرداخت از کیف پول' }}
                </button>
              </div>
            </div>

            <div class="service-popup-meta timeline-service-list">
              <div class="service-item" v-for="(service, sIndex) in activeTimelineDraft.services" :key="sIndex">
                <div class="service-main-row">
                  <Multiselect
                    v-model="service.sectionId"
                    :options="serviceSectionOptionsForRow(activeTimelineDraft)"
                    :custom-label="serviceSectionLabel"
                    :multiple="false"
                    :searchable="true"
                    :close-on-select="true"
                    :clear-on-select="false"
                    :allow-empty="true"
                    :disabled="!activeTimelineDraft.serviceTypes?.length"
                    :placeholder="activeTimelineDraft.serviceTypes?.length ? 'انتخاب بخش' : 'ابتدا بخش را انتخاب کنید'"
                    select-label=""
                    selected-label="انتخاب شد"
                    deselect-label="حذف"
                    class="service-multiselect service-section-multiselect"
                    @select="onServiceSectionChanged(service, activeTimelineDraft)"
                    @remove="onServiceSectionChanged(service, activeTimelineDraft)"
                  />

                  <Multiselect
                    v-model="service.name"
                    :options="serviceOptionsFor(service, activeTimelineDraft)"
                    :multiple="false"
                    :searchable="true"
                    :internal-search="true"
                    :close-on-select="true"
                    :clear-on-select="false"
                    :allow-empty="true"
                    :disabled="!activeTimelineDraft.serviceTypes?.length"
                    :placeholder="activeTimelineDraft.serviceTypes?.length ? 'جستجو و انتخاب خدمت' : 'ابتدا بخش را انتخاب کنید'"
                    select-label=""
                    selected-label="انتخاب شده"
                    deselect-label="حذف"
                    class="service-multiselect"
                    @select="onServiceNameChanged(service, activeTimelineDraft)"
                    @remove="onServiceNameChanged(service, activeTimelineDraft)"
                  />

                  <select v-model="service.doctor" class="service-select" :disabled="!activeTimelineDraft.serviceTypes?.length || !service.sectionId" @change="calculateRowAmount(activeTimelineDraft)">
                    <option value="">انتخاب پزشک</option>
                    <option v-for="doc in doctorsForService(activeTimelineDraft, service)" :key="doc.id" :value="doc.name">
                      {{ doc.name }}
                    </option>
                  </select>

                  <select v-model="service.consultant" class="service-select" @change="calculateRowAmount(activeTimelineDraft)">
                    <option value="">انتخاب مشاور</option>
                    <option v-for="cons in consultantOptions" :key="cons" :value="cons">
                      {{ cons }}
                    </option>
                  </select>

                  <input v-model="service.cc" type="text" placeholder="تعداد سی‌سی" class="cc-input" @input="updateRowAmounts(activeTimelineDraft)" />
                  <span class="service-price-chip">{{ service.name ? `${formatDisplayMoney(serviceLinePrice(service))} تومان` : 'قیمت' }}</span>
                  <div class="service-discount-wrap">
                    <input v-model="service.discount" type="text" inputmode="numeric" placeholder="مبلغ" class="service-discount-input" @input="handleServiceDiscountInput(service, activeTimelineDraft)">
                    <span>تخفیف</span>
                  </div>

                  <button type="button" class="service-addon-toggle" :class="{ active: service.addons?.length }" :disabled="!service.name" @click.stop="addServiceAddon(service)">
                    جانبی <span v-if="service.addons?.length">{{ service.addons.length }}</span><b>+</b>
                  </button>

                  <button v-if="activeTimelineDraft.services.length > 1" type="button" class="remove-service-btn" @click.stop="removeService(activeTimelineDraft, sIndex)">
                    -
                  </button>
                </div>

                <div v-if="service.addons?.length" class="service-addons-panel">
                  <div class="service-addons-title"><span>جانبی‌های {{ service.name }}</span><small>امکان افزودن چند مورد</small></div>
                  <div v-for="(addon, addonIndex) in service.addons" :key="addon._key || addonIndex" class="service-addon-row">
                    <Multiselect v-model="addon.name" :options="serviceAddonOptions(service, addon)" :multiple="false" :searchable="true" :close-on-select="true" :allow-empty="true" placeholder="جستجو و انتخاب جانبی" select-label="" selected-label="انتخاب شد" deselect-label="حذف" class="service-multiselect service-addon-multiselect" @select="calculateRowAmount(activeTimelineDraft)" @remove="calculateRowAmount(activeTimelineDraft)" />
                    <input v-model="addon.cc" type="text" inputmode="numeric" placeholder="تعداد/سی‌سی" class="cc-input addon-cc-input" @input="updateRowAmounts(activeTimelineDraft)">
                    <span class="service-price-chip addon-price-chip">{{ formatDisplayMoney(serviceLinePrice(addon)) }} تومان</span>
                    <div class="service-discount-wrap addon-discount-wrap">
                      <input v-model="addon.discount" type="text" inputmode="numeric" placeholder="مبلغ" class="service-discount-input" @input="handleServiceDiscountInput(addon, activeTimelineDraft)">
                      <span>تخفیف</span>
                    </div>
                    <button type="button" class="remove-addon-btn" title="حذف جانبی" @click.stop="removeServiceAddon(service, addonIndex, activeTimelineDraft)">×</button>
                  </div>

                  <div v-if="!row.serviceTypes?.length" class="service-prerequisite">
                    <span>۱</span>
                    <div>
                      <strong>ابتدا بخش را مشخص کنید</strong>
                      <p>از ستون «بخش»، یک یا چند بخش را انتخاب کنید؛ سپس فقط خدمات، محصولات و پزشکان مرتبط با همان بخش‌ها اینجا نمایش داده می‌شوند.</p>
                    </div>
                  </div>
                  <button type="button" class="add-another-addon-btn" @click.stop="addServiceAddon(service)">+ افزودن جانبی دیگر</button>
                </div>
              </div>
            </div>
          </section>

          <div v-if="false" class="timeline-form-grid compact">
            <label>
              مبلغ
              <input :value="activeTimelineDraft.amount" type="text" disabled>
            </label>

            <label>
              بدهی
              <input
                v-model="activeTimelineDraft.debt"
                type="text"
                inputmode="numeric"
                :class="appointmentBalanceClass(activeTimelineDraft)"
                @input="formatSignedMoney(activeTimelineDraft, 'debt')"
              >
            </label>

            <label>
              انجام کار
              <select v-model="activeTimelineDraft.done" :class="doneColor(activeTimelineDraft.done)">
                <option value="">-</option>
                <option>انجام شد</option>
                <option>انجام نشد</option>
                <option>ترمیم</option>
                <option>مشاوره</option>
              </select>
            </label>
          </div>

          <label v-if="false" class="timeline-wide-field">
            توضیحات
            <textarea v-model="activeTimelineDraft.description" rows="3"></textarea>
          </label>
        </div>

        <footer class="timeline-modal-footer">
          <button type="button" class="timeline-modal-cancel" @click="closeTimelineModal()">انصراف</button>
          <button type="button" class="timeline-modal-save" @click="saveTimelineModal">ثبت نوبت</button>
        </footer>
      </section>
    </div>

    <div v-if="trackingModalOpen" class="tracking-modal-overlay" @click.self="closeTrackingModal">
      <section class="tracking-modal" role="dialog" aria-modal="true" aria-labelledby="tracking-modal-title" @click.stop>
        <header>
          <div>
            <span>گزارش زمان‌بندی</span>
            <h3 id="tracking-modal-title">{{ activeTrackingRow?.row?.lastname || 'مراجعه‌کننده' }}</h3>
            <p>{{ activeTrackingRow?.day?.dateLabel || '-' }}</p>
          </div>
          <button type="button" title="بستن" @click="closeTrackingModal">×</button>
        </header>

        <div class="tracking-grid">
          <article>
            <small>نوبت ثبت‌شده</small>
            <strong>{{ trackingReport.scheduledTime }}</strong>
          </article>
          <article v-if="trackingReport.hasArrived">
            <small>زمان آمدن</small>
            <button type="button" class="tracking-time-value" @click="openTrackingTimeEditor('arrivedAt')">
              {{ trackingReport.arrivedTime }}
            </button>
          </article>
          <article v-if="trackingReport.hasCompleted">
            <small>زمان انجام‌شدن</small>
            <button type="button" class="tracking-time-value" @click="openTrackingTimeEditor('completedAt')">
              {{ trackingReport.completedTime }}
            </button>
          </article>
          <article v-if="trackingReport.hasDelay" :class="{ late: trackingReport.delayMinutes > 0, good: trackingReport.delayMinutes <= 0 && trackingReport.delayMinutes !== null }">
            <small>معطلی / تأخیر نسبت به نوبت</small>
            <strong>{{ trackingReport.delayText }}</strong>
          </article>
          <article v-if="trackingReport.hasVisitDuration">
            <small>مدت حضور تا انجام</small>
            <strong>{{ trackingReport.visitDurationText }}</strong>
          </article>
          <article v-if="trackingReport.hasTotalDuration">
            <small>از نوبت تا پایان کار</small>
            <strong>{{ trackingReport.totalDurationText }}</strong>
          </article>
        </div>

        <div class="tracking-financial-grid">
          <article>
            <small>هزینه مواد مصرفی</small>
            <strong>{{ formatDisplayMoney(trackingReport.financial.materialCost) }} تومان</strong>
          </article>
          <article>
            <small>دستمزد پزشک</small>
            <strong>{{ formatDisplayMoney(trackingReport.financial.doctorWage) }} تومان</strong>
          </article>
          <article>
            <small>پورسانت پرسنل</small>
            <strong>{{ formatDisplayMoney(trackingReport.financial.staffCommission) }} تومان</strong>
          </article>
          <article>
            <small>سود کلینیک</small>
            <strong>{{ formatDisplayMoney(trackingReport.financial.clinicProfit) }} تومان</strong>
          </article>
        </div>

        <form v-if="trackingTimeEditorOpen" class="tracking-edit-panel" @submit.prevent="saveTrackingTimeEdit">
          <label>
            <span>{{ trackingTimeDraft.label }}</span>
            <input v-model="trackingTimeDraft.value" type="time" step="60" required>
          </label>
          <div>
            <button type="button" class="tracking-edit-cancel" @click="closeTrackingTimeEditor">انصراف</button>
            <button type="submit" class="tracking-edit-save" :disabled="trackingTimeSaving">
              {{ trackingTimeSaving ? 'در حال ذخیره...' : 'ثبت تغییر' }}
            </button>
          </div>
        </form>
      </section>
    </div>

    <div v-if="paymentLinkModalOpen" class="payment-link-overlay" @click.self="closePaymentLinkModal">
      <section class="payment-link-modal" role="dialog" aria-modal="true" aria-labelledby="payment-link-title" @click.stop>
        <header>
          <div>
            <span>لینک پرداخت</span>
            <h3 id="payment-link-title">{{ activePaymentLinkRow?.lastname || 'مراجعه‌کننده' }}</h3>
            <p>{{ displayPatientPhone(activePaymentLinkRow?.phone) || 'بدون شماره' }}</p>
          </div>
          <button type="button" title="بستن" @click="closePaymentLinkModal">×</button>
        </header>

        <div class="payment-link-body">
          <label>
            لینک
            <input :value="activePaymentLinkRow?.paymentLink || ''" readonly>
          </label>
          <div class="payment-link-meta">
            <span>تعداد ارسال: <b>{{ activePaymentLinkRow?.paymentLinkSentCount || 0 }}</b></span>
            <span>آخرین ارسال: <b>{{ formatPaymentLinkSentAt(activePaymentLinkRow?.paymentLinkLastSentAt) }}</b></span>
          </div>
        </div>

        <footer>
          <button type="button" class="payment-copy-btn" @click="copyPaymentLink">کپی لینک</button>
          <button type="button" class="payment-send-btn" :disabled="paymentLinkSending" @click="confirmSendPaymentLink">
            {{ paymentLinkSending ? 'در حال ارسال...' : (activePaymentLinkRow?.paymentLinkSentCount ? 'ارسال مجدد پیامک' : 'ارسال پیامک') }}
          </button>
        </footer>
      </section>
    </div>

    <div v-if="doctorNoteModalOpen" class="doctor-note-overlay" @click.self="closeDoctorNoteModal">
      <section class="doctor-note-modal" role="dialog" aria-modal="true" aria-labelledby="doctor-note-title" @click.stop>
        <header class="doctor-note-modal-header">
          <div class="doctor-note-patient-head">
            <PatientAvatar
              :src="activeDoctorNoteRow?.profilePhotoUrl || activeDoctorNoteRow?.profileThumbnailUrl"
              :patient="{ first_name: activeDoctorNoteRow?.lastname || '' }"
              :level="activeDoctorNoteRow?.customerLevel"
              :size="58"
            />
            <div>
            <span class="doctor-note-eyebrow">گفت‌وگوی داخلی تیم</span>
            <h3 id="doctor-note-title">یادداشت‌های نوبت</h3>
            <p>{{ activeDoctorNoteRow?.lastname || 'مراجعه‌کننده' }} <span v-if="activeDoctorNoteRow?.fileNumber">— پرونده {{ activeDoctorNoteRow.fileNumber }}</span></p>
            </div>
          </div>
          <button type="button" class="doctor-note-close" title="بستن" @click="closeDoctorNoteModal">×</button>
        </header>

        <div ref="doctorNoteChat" class="doctor-note-chat">
          <div v-if="doctorNoteLoading" class="doctor-note-chat-empty">در حال دریافت گفت‌وگو...</div>
          <div v-else-if="!doctorNoteMessages.length" class="doctor-note-chat-empty">
            <b>هنوز پیامی ثبت نشده است</b>
            <span>اولین یادداشت متنی یا صوتی این نوبت را ارسال کنید.</span>
          </div>
          <article v-for="message in doctorNoteMessages" :key="message.id" class="doctor-note-message" :class="{ own: message.can_delete }">
            <span class="doctor-note-author-avatar">
              <img v-if="message.author?.avatar_url" :src="message.author.avatar_url" alt="">
              <b v-else>{{ (message.author?.name || 'ک').charAt(0) }}</b>
            </span>
            <div class="doctor-note-bubble">
              <header>
                <strong>{{ message.author?.name || 'کاربر' }}</strong>
                <span class="doctor-note-message-meta">
                  <time>{{ formatDoctorNoteTime(message.created_at) }}</time>
                  <button v-if="message.can_delete" type="button" class="doctor-note-delete" :disabled="doctorNoteDeletingId === message.id" title="حذف پیام" @click="deleteDoctorNoteMessage(message)">🗑</button>
                </span>
              </header>
              <p v-if="message.message_type === 'text'">{{ message.message }}</p>
              <audio v-else-if="message.message_type === 'audio'" :src="message.audio_url" controls preload="metadata"></audio>
              <a v-else-if="message.message_type === 'image'" class="doctor-note-image-link" :href="message.image_url" target="_blank" rel="noopener">
                <img :src="message.image_url" alt="تصویر نسخه یا پیوست پزشک">
                <span v-if="message.message">{{ message.message }}</span>
              </a>
            </div>
          </article>
        </div>

        <footer class="doctor-note-composer">
          <textarea ref="doctorNoteEditor" v-model="doctorNoteDraft" maxlength="20000" rows="2" placeholder="پیام بنویسید؛ Enter ارسال، Shift + Enter خط جدید" aria-label="پیام نوت پزشک" @keydown="handleDoctorNoteKeydown"></textarea>
          <label class="doctor-note-image-upload" :class="{ disabled: doctorNoteSending }" title="آپلود عکس نسخه">
            <input type="file" accept="image/*" :disabled="doctorNoteSending" @change="uploadDoctorNoteImage">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m8 14 2.5-3 2.5 3 2-2 3 4H6z"/><circle cx="16" cy="9" r="1.5"/></svg>
          </label>
          <button type="button" class="doctor-note-record" :class="{ recording: doctorNoteRecording }" :disabled="doctorNoteSending" @click="toggleDoctorNoteRecording">
            {{ doctorNoteRecording ? `■ توقف ${formatRecordingTime(doctorNoteRecordingSeconds)}` : '🎙 ضبط ویس' }}
          </button>
          <button type="button" class="doctor-note-send" :disabled="doctorNoteSending || !doctorNoteDraft.trim()" @click="saveDoctorNote">
            {{ doctorNoteSending ? 'در حال ارسال...' : 'ارسال' }}
          </button>
        </footer>
      </section>
    </div>

    <div v-if="completionSmsModalOpen" class="completion-sms-overlay" @click.self="closeCompletionSmsModal">
      <section class="completion-sms-modal" @click.stop>
        <header><div><span>پس از انجام درمان</span><h3>ارسال پیامک‌ها</h3><p>{{ activeCompletionSmsRow?.lastname || 'مراجعه‌کننده' }} — {{ displayPatientPhone(activeCompletionSmsRow?.phone) || 'بدون شماره' }}</p></div><button @click="closeCompletionSmsModal">×</button></header>
        <div class="completion-sms-list">
          <label v-for="option in completionSmsOptions" :key="option.key" :class="['completion-sms-card', { sent: completionSmsWasSent(option.key), failed: completionSmsErrors[option.key] }]">
            <input v-if="!completionSmsWasSent(option.key)" v-model="selectedCompletionSms" type="checkbox" :value="option.key">
            <span v-else class="completion-sms-success">✓</span>
            <span class="completion-sms-card-icon">{{ option.icon }}</span>
            <span class="completion-sms-card-content"><strong>{{ option.title }}</strong><small>{{ option.description }}</small><em v-if="completionSmsWasSent(option.key)">ارسال شده</em><em v-else-if="completionSmsErrors[option.key]" class="error">{{ completionSmsErrors[option.key] }}</em></span>
          </label>
        </div>
        <div v-if="activeCompletionSmsRow?.referrerPhone" class="completion-referral-info">معرف: {{ displayPatientPhone(activeCompletionSmsRow.referrerPhone) }} — مبلغ: {{ formatDisplayMoney(activeCompletionSmsRow.referralScore || 0) }} تومان</div>
        <footer><button class="completion-sms-cancel" @click="closeCompletionSmsModal">بعداً</button><button class="completion-sms-send" :disabled="completionSmsSending || !selectedCompletionSms.length" @click="sendSelectedCompletionSms">{{ completionSmsSending ? 'در حال ارسال...' : `ارسال ${selectedCompletionSms.length || ''} پیامک` }}</button></footer>
      </section>
    </div>

    <!-- نوار ماه‌ها در پایین صفحه -->
    <div v-if="dailyReportModalOpen" class="daily-report-overlay" @click.self="closeDailyReport">
      <section class="daily-report-modal" @click.stop>
        <header>
          <div>
            <span>{{ activeDailyReport?.day?.dateLabel || 'گزارش روز' }}</span>
            <h3>نمودار وضعیت نوبت‌ها</h3>
          </div>
          <button type="button" @click="closeDailyReport">×</button>
        </header>

        <div v-if="activeDailyReport" class="daily-report-body">
          <div class="daily-report-kpis">
            <article>
              <span>وقت داده شده</span>
              <strong>{{ activeDailyReport.stats.scheduledCount }}</strong>
            </article>
            <article>
              <span>کنسل شده</span>
              <strong>{{ activeDailyReport.stats.canceledCount }}</strong>
            </article>
            <article>
              <span>کار انجام شده</span>
              <strong>{{ activeDailyReport.stats.doneCount }}</strong>
            </article>
            <article>
              <span>کل ردیف‌های فعال</span>
              <strong>{{ activeDailyReport.stats.totalAppointments }}</strong>
            </article>
          </div>

          <div class="daily-report-grid">
            <section class="daily-report-panel">
              <h4>وضعیت‌ها</h4>
              <div class="daily-chart-list">
                <div v-for="item in activeDailyReport.stats.statusItems" :key="item.label" class="daily-chart-row">
                  <span>{{ item.label }}</span>
                  <div><i :style="{ width: chartPercent(item.count, activeDailyReport.stats.totalAppointments) + '%' }"></i></div>
                  <b>{{ item.count }}</b>
                </div>
              </div>
            </section>

            <section class="daily-report-panel">
              <h4>منبع تبلیغاتی</h4>
              <div class="daily-chart-list">
                <div v-for="item in activeDailyReport.stats.sourceItems" :key="item.label" class="daily-chart-row">
                  <span>{{ item.label }}</span>
                  <div><i :style="{ width: chartPercent(item.count, activeDailyReport.stats.maxSourceCount) + '%' }"></i></div>
                  <b>{{ item.count }}</b>
                </div>
              </div>
            </section>

            <section class="daily-report-panel">
              <h4>پزشک‌ها</h4>
              <table class="daily-report-table">
                <thead><tr><th>نام</th><th>تعداد</th><th>مبلغ</th></tr></thead>
                <tbody>
                  <tr v-for="item in activeDailyReport.stats.doctorItems" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td>{{ item.count }}</td>
                    <td>{{ formatDisplayMoney(item.amount) }}</td>
                  </tr>
                  <tr v-if="!activeDailyReport.stats.doctorItems.length"><td colspan="3">موردی ثبت نشده</td></tr>
                </tbody>
              </table>
            </section>

            <section class="daily-report-panel">
              <h4>مشاورها</h4>
              <table class="daily-report-table">
                <thead><tr><th>نام</th><th>تعداد</th><th>مبلغ</th></tr></thead>
                <tbody>
                  <tr v-for="item in activeDailyReport.stats.consultantItems" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td>{{ item.count }}</td>
                    <td>{{ formatDisplayMoney(item.amount) }}</td>
                  </tr>
                  <tr v-if="!activeDailyReport.stats.consultantItems.length"><td colspan="3">موردی ثبت نشده</td></tr>
                </tbody>
              </table>
            </section>
          </div>
        </div>
      </section>
    </div>

    <div v-if="balanceAuditModalOpen" class="balance-audit-overlay" @click.self="closeBalanceAuditModal">
      <section class="balance-audit-modal" @click.stop>
        <header>
          <div>
            <span>ردیابی تغییرات طلب و بدهی</span>
            <h3>گزارش تغییرات مالی نوبت‌دهی</h3>
          </div>
          <button type="button" @click="closeBalanceAuditModal">×</button>
        </header>

        <div v-if="balanceAuditLoading" class="balance-audit-empty">در حال دریافت گزارش...</div>
        <div v-else-if="!balanceAuditRows.length" class="balance-audit-empty">تغییری ثبت نشده است.</div>
        <div v-else class="balance-audit-table-wrap">
          <table class="balance-audit-table">
            <thead>
              <tr>
                <th>زمان</th>
                <th>کاربر</th>
                <th>بیمار</th>
                <th>پرونده</th>
                <th>قبلی</th>
                <th>جدید</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in balanceAuditRows" :key="item.id">
                <td>{{ formatAuditDate(item.created_at) }}</td>
                <td>{{ item.changed_by_name || 'نامشخص' }}</td>
                <td>{{ item.patient_name || '-' }}</td>
                <td>{{ item.file_number || displayPatientPhone(item.phone) || '-' }}</td>
                <td :class="balanceAuditValueClass(item.old_debt)">{{ formatSignedDisplayMoney(item.old_debt) }}</td>
                <td :class="balanceAuditValueClass(item.new_debt)">{{ formatSignedDisplayMoney(item.new_debt) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div v-if="financialPanelOpen" class="financial-panel-overlay" @click.self="closeFinancialPanel">
      <section class="financial-panel" @click.stop>
        <header>
          <div>
            <small>وضعیت مالی بیمار</small>
            <h3>{{ activeFinancialRow?.lastname || 'مراجع' }}</h3>
          </div>
          <button type="button" title="بستن" aria-label="بستن" @click="closeFinancialPanel">×</button>
        </header>
        <div class="financial-summary">
          <article class="debt"><span>بدهکاری کل</span><strong>{{ formatDisplayMoney(patientDebtAmount(activeFinancialRow)) }}</strong><small>تومان</small></article>
          <article class="deposit"><span>بیعانه / اعتبار</span><strong>{{ formatDisplayMoney(activeFinancialRow?.walletBalance || 0) }}</strong><small>تومان</small></article>
        </div>
        <button
          v-if="walletSettlementAmount(activeFinancialRow) > 0"
          type="button"
          class="financial-wallet-settle"
          :disabled="financialSaving"
          @click="settleDebtWithWallet"
        >
          <span>تسویه بدهی با بیعانه</span>
          <strong>{{ formatDisplayMoney(walletSettlementAmount(activeFinancialRow)) }} تومان</strong>
          <small>با یک کلیک از اعتبار بیمار کسر می‌شود</small>
        </button>
        <label>
          بدهکاری این جلسه
          <input v-model="financialDebtDraft" type="text" inputmode="numeric" placeholder="۰" @input="formatFinancialDraft('financialDebtDraft')">
        </label>
        <label>
          ثبت بیعانه جدید
          <input v-model="financialDepositDraft" type="text" inputmode="numeric" placeholder="۰" @input="formatFinancialDraft('financialDepositDraft')">
          <small>بیعانه در کیف پول بیمار ذخیره و در جلسات بعد قابل استفاده است.</small>
        </label>
        <div class="financial-payment-grid">
          <label>
            روش پرداخت
            <select v-model="financialPaymentMethodDraft">
              <option value="">انتخاب نشده</option>
              <option v-for="method in paymentOptions.methods" :key="method" :value="method">{{ method }}</option>
            </select>
          </label>
          <label>
            حساب واریز
            <select v-model="financialPaymentAccountDraft">
              <option value="">انتخاب نشده</option>
              <option v-for="account in paymentOptions.accounts" :key="account" :value="account">{{ account }}</option>
            </select>
          </label>
          <label>
            مبلغ نقدی
            <input v-model="financialCashDraft" type="text" inputmode="numeric" placeholder="۰" @input="formatFinancialDraft('financialCashDraft')">
          </label>
          <label>
            مبلغ کارت / کارتخوان
            <input v-model="financialCardDraft" type="text" inputmode="numeric" placeholder="۰" @input="formatFinancialDraft('financialCardDraft')">
          </label>
        </div>
        <button type="button" class="financial-advanced-toggle" :class="{ active: financialCheckOpen }" @click="financialCheckOpen = !financialCheckOpen">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v10H4z"/><path d="M7 11h5M16 11h1M7 14h10"/></svg>
          <span>جزئیات چک</span>
          <b>{{ financialCheckOpen ? 'بستن' : 'باز کردن' }}</b>
        </button>
        <div v-if="financialCheckOpen" class="financial-check-grid">
          <label>
            مبلغ چک
            <input v-model="financialCheckAmountDraft" type="text" inputmode="numeric" placeholder="۰" @input="formatFinancialDraft('financialCheckAmountDraft')">
          </label>
          <label>
            شماره چک
            <input v-model.trim="financialCheckNumberDraft" type="text" placeholder="مثلا ۱۲۳۴۵۶">
          </label>
          <label>
            تاریخ سررسید
            <date-picker
              v-model="financialCheckDueDateDraft"
              format="YYYY-MM-DD"
              display-format="jYYYY/jMM/jDD"
              input-class="financial-date-input"
              placeholder="انتخاب تاریخ"
              auto-submit
              color="#2563eb"
            />
          </label>
        </div>
        <div v-if="!activeFinancialRow?.patientId" class="financial-patient-warning">برای ثبت بیعانه، ابتدا بیمار را از پرونده‌های موجود انتخاب کنید.</div>
        <footer>
          <button type="button" class="financial-cancel" @click="closeFinancialPanel">انصراف</button>
          <button type="button" class="financial-save" :disabled="financialSaving" @click="saveFinancialPanel">{{ financialSaving ? 'در حال ثبت...' : 'ثبت وضعیت مالی' }}</button>
        </footer>
      </section>
    </div>

    <div v-if="patientProfileModalOpen" class="time-profile-overlay" @click.self="closePatientProfileModal">
      <section class="time-profile-modal" role="dialog" aria-modal="true" aria-labelledby="time-profile-title">
        <header>
          <div class="time-profile-head">
            <PatientAvatar
              :src="patientProfilePhotoUrl(activePatientProfile)"
              :patient="activePatientProfile"
              :level="normalizeCustomerLevel(activePatientProfile?.customer_level)"
              :size="58"
            />
            <div>
              <small>پرونده مراجعه‌کننده</small>
              <h3 id="time-profile-title">{{ patientProfileName(activePatientProfile) }}</h3>
              <p>پرونده {{ activePatientProfile?.file_number || '-' }} · {{ displayPatientPhone(activePatientProfile?.phone) || '-' }}</p>
            </div>
          </div>
          <button type="button" title="بستن" aria-label="بستن" @click="closePatientProfileModal">×</button>
        </header>

        <div v-if="patientProfileLoading" class="time-profile-loading">
          <span class="btn-spinner dark"></span>
          <b>در حال دریافت پرونده...</b>
        </div>

        <div v-else-if="patientProfileError" class="time-profile-error">{{ patientProfileError }}</div>

        <div v-else-if="activePatientProfile" class="time-profile-body">
          <div class="time-profile-stats">
            <article><span>جنسیت</span><strong>{{ activePatientProfile.gender || '-' }}</strong></article>
            <article><span>اعتبار</span><strong>{{ formatDisplayMoney(activePatientProfile.wallet_balance || 0) }}</strong></article>
            <article :class="{ danger: Number(activePatientProfile.outstanding_debt || 0) > 0 }"><span>بدهکاری</span><strong>{{ formatDisplayMoney(activePatientProfile.outstanding_debt || 0) }}</strong></article>
            <article><span>تعداد سوابق</span><strong>{{ patientProfileTotalAppointments.toLocaleString('fa-IR') }}</strong></article>
          </div>

          <div class="time-profile-notes">
            <article>
              <span>تیپ شخصیتی</span>
              <p>{{ activePatientProfile.patient_history || '-' }}</p>
            </article>
            <article>
              <span>سوابق پزشکی</span>
              <p>{{ activePatientProfile.medical_history || '-' }}</p>
            </article>
          </div>

          <section class="time-profile-history">
            <div>
              <h4>سوابق نوبت</h4>
              <span>{{ patientProfileAppointments.length.toLocaleString('fa-IR') }} از {{ patientProfileTotalAppointments.toLocaleString('fa-IR') }} مورد</span>
            </div>
            <div v-if="patientProfileHistoryLoading && !patientProfileAppointments.length" class="time-profile-empty">در حال دریافت سوابق...</div>
            <div v-else-if="!patientProfileAppointments.length" class="time-profile-empty">سابقه‌ای برای این پرونده ثبت نشده است.</div>
            <table v-else>
              <thead>
                <tr>
                  <th>تاریخ</th>
                  <th>ساعت</th>
                  <th>خدمات</th>
                  <th>وضعیت</th>
                  <th>مبلغ</th>
                  <th>بدهی</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in patientProfileAppointments" :key="item.id">
                  <td>{{ item.month || '-' }} / {{ item.day_num || '-' }}</td>
                  <td>{{ item.time || '-' }}</td>
                  <td>{{ profileServiceText(item) }}</td>
                  <td>{{ item.status || '-' }}</td>
                  <td>{{ formatDisplayMoney(item.amount || 0) }}</td>
                  <td>{{ formatDisplayMoney(item.debt || 0) }}</td>
                </tr>
              </tbody>
            </table>
            <button
              v-if="patientProfileHasMore"
              type="button"
              class="time-profile-more"
              :disabled="patientProfileHistoryLoading"
              @click="loadPatientProfileAppointments(patientProfileHistoryPage + 1)"
            >
              {{ patientProfileHistoryLoading ? 'در حال دریافت...' : 'نمایش بیشتر' }}
            </button>
          </section>
        </div>
      </section>
    </div>

    <div class="fixed-bottom-bar">

      <button
        class="month-action-btn"
        @click.stop="removeMonth"
      >
        -
      </button>

      <div class="months-scroll-area">

        <div
          v-for="(m, mIdx) in months"
          :key="mIdx"
          class="month-pill"
          :class="{ active: currentMonth === mIdx }"
          @click.stop="currentMonth = mIdx"
        >
          {{ m }}
        </div>

      </div>

      <button
        class="month-action-btn"
        @click.stop="addMonth"
      >
        +
      </button>

    </div>

    <Teleport to="body">
      <Transition name="avatar-preview">
        <div
          v-if="avatarPreview"
          class="appointment-avatar-preview"
          :style="{ left: avatarPreview.left + 'px', top: avatarPreview.top + 'px' }"
        >
          <img :src="avatarPreview.url" alt="پیش‌نمایش عکس بیمار">
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script>
import axios from "axios";
import DatePicker from "vue3-persian-datetime-picker";
import Swal from "sweetalert2";
import moment from "moment-jalaali";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import PatientAvatar from './PatientAvatar.vue';

export default {
  props: {
    permissions: { type: Array, default: () => [] },
    openViewRequest: { type: Object, default: null }
  },

  components: {
    DatePicker,
    Multiselect,
    PatientAvatar
  },

  data() {
    return {
      appointmentView: "table",
      appointmentReady: false,
      handledOpenViewRequestAt: null,
      searchQuery: "",
      holidays: {},

      months: ["1405-01"],
      avatarPreview: null,
      patientProfileModalOpen: false,
      patientProfileLoading: false,
      patientProfileError: "",
      activePatientProfile: null,
      patientProfileAppointments: [],
      patientProfileHistoryLoading: false,
      patientProfileHistoryPage: 1,
      patientProfileHistoryPerPage: 15,
      patientProfileTotalAppointments: 0,
      patientProfileHasMore: false,
      patientProfileHistoryParams: "",
      currentMonth: 0,

      days: [],

      _idCounter: 0,
      _rowCounter: 0,

      allCollapsed: false,
      activeServicePopup: null,
      highlightedRowId: null,
      timelineModalOpen: false,
      activeTimelineDay: null,
      activeTimelineRow: null,
      activeTimelineDraft: null,
      activeTimelineFollowup: null,
      pendingTimelineFollowup: null,
      timelineValidationErrors: {},
      timelinePatientSearchResults: [],
      timelinePatientSearchLoading: false,
      timelinePatientSearchOpen: false,
      timelinePatientSearchTimer: null,
      activeTimelineCreatedInModal: false,

      doctorOptions: [],
      doctors: [],
      staff: [],
      consultantOptions: [],
      sourceOptions: [],
      serviceOptions: [],
      serviceSections: [],
      paymentOptions: {
        methods: ["کارتخوان", "کارت به کارت", "شبا"],
        accounts: ["حساب اصلی"]
      },
      appointmentColumns: {
        payment_method: false,
        payment_account: false,
        payment_link: false,
        best_staff: false
      },
      clinicSchedule: {
        active_days: ["saturday", "monday", "wednesday"],
        interval_minutes: 15,
        day_times: {
          saturday: { start: "09:00", end: "17:00" },
          sunday: { start: "09:00", end: "17:00" },
          monday: { start: "09:00", end: "17:00" },
          tuesday: { start: "09:00", end: "17:00" },
          wednesday: { start: "09:00", end: "17:00" },
          thursday: { start: "09:00", end: "17:00" },
          friday: { start: "09:00", end: "17:00" }
        }
      },
      selectedServiceSections: [],
      showServiceSectionFilter: false,
      categoryFilterLoading: false,
      doctorNoteModalOpen: false,
      activeDoctorNoteRow: null,
      doctorNoteDraft: "",
      doctorNoteMessages: [],
      doctorNoteLoading: false,
      doctorNoteSending: false,
      doctorNoteDeletingId: null,
      doctorNoteRecording: false,
      doctorNoteRecordingSeconds: 0,
      doctorNoteRecorder: null,
      doctorNoteRecordingTimer: null,
      doctorNoteAudioChunks: [],
      doctorNoteAudioStream: null,
      doctorNoteUnreadSnapshot: new Set(),
      doctorNoteUnreadInitialized: false,
      doctorNoteBellLastPlayedAt: 0,
      trackingModalOpen: false,
      activeTrackingRow: null,
      trackingTimeEditorOpen: false,
      trackingTimeSaving: false,
      trackingTimeDraft: {
        field: "",
        label: "",
        value: ""
      },
      paymentLinkModalOpen: false,
      activePaymentLinkRow: null,
      paymentLinkSending: false,
      completionSmsModalOpen: false,
      activeCompletionSmsRow: null,
      selectedCompletionSms: [],
      completionSmsSending: false,
      completionSmsErrors: {},
      balanceAuditModalOpen: false,
      balanceAuditLoading: false,
      balanceAuditRows: [],
      financialPanelOpen: false,
      activeFinancialRow: null,
      financialDebtDraft: "",
      financialDepositDraft: "",
      financialPaymentMethodDraft: "",
      financialPaymentAccountDraft: "",
      financialCashDraft: "",
      financialCardDraft: "",
      financialCheckOpen: false,
      financialCheckAmountDraft: "",
      financialCheckNumberDraft: "",
      financialCheckDueDateDraft: "",
      financialSaving: false,
      dailyReportModalOpen: false,
      activeDailyReport: null,
      completionSmsOptions: [
        { key: 'referral_credit', icon: '💳', title: 'واریز مبلغ برای معرف', description: 'اعلام مبلغ واریزی و موجودی جدید کیف پول معرف' },
        { key: 'treatment_care', icon: '🩺', title: 'توصیه‌های بعد از درمان', description: 'ارسال لینک راهنمای مراقبت و توصیه‌های درمان' },
        { key: 'payment_link', icon: '🔗', title: 'لینک پرداخت', description: 'ارسال لینک و مبلغ پرداخت برای مراجعه‌کننده' },
        { key: 'welcome', icon: '🌿', title: 'پیام خوش‌آمدگویی', description: 'تشکر از مراجعه و خوش‌آمدگویی به مشتری' }
      ],
      inventoryItems: [],

      inventoryStock: {},

      selectedStatuses: [],
      selectedDoctors: [],
      selectedConsultants: [],
      selectedSources: [],
      selectedDone: [],

      showStatusFilter: false,
      showDoctorFilter: false,
      showConsultantFilter: false,
      showSourceFilter: false,
      showDoneFilter: false,

      saveTimeout: null,
      isFetching: true,
      generatingNewMonth: false,

      columnWidths: {
        lastname: 145,
        gender: 58,
        phone: 112,
        fileNumber: 92,
        time: 92,
        status: 105,
        doctor: 120,
        consultant: 120,
        source: 105,
        description: 170,
        done: 105,
        amount: 180,
        debt: 120,
        paymentMethod: 110,
        paymentAccount: 120,
        paymentLink: 96,
        service: 76,
        serviceType: 120,

        newCustomer: 100,
        appointmentSms: 100,
        infoSms: 100
      },

      resizingColumn: null,
      startX: 0,
      startWidth: 0
    };
  },

  computed: {
    canViewPatientPhone() {
      return this.permissions.includes("patients.view_phone");
    },

    bestStaffOfMonth() {
      const counts = {};
      this.days.forEach(day => {
        (day.rows || []).forEach(row => {
          if (this.isEmptyAppointmentRow(row)) return;
          const consultant = String(row.consultant || row.services?.find(service => service?.consultant)?.consultant || '').trim();
          if (consultant) counts[consultant] = (counts[consultant] || 0) + 1;
        });
      });
      const winner = Object.entries(counts).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0], 'fa'))[0];
      if (!winner) return null;
      const staff = this.staff.find(item => String(item.name).trim() === winner[0]);
      return {
        name: winner[0],
        count: winner[1],
        image: staff?.avatar_url || staff?.profile_thumbnail_url || staff?.profile_photo_url || ''
      };
    },

    showBestStaffCard() {
      if (!this.appointmentColumns.best_staff || !this.bestStaffOfMonth) return false;
      const viewedMonth = this.months[this.currentMonth] || '';
      const realMonth = this.getCurrentJalaliMonth();
      if (viewedMonth < realMonth) return true;
      if (viewedMonth > realMonth) return false;
      const [year, month] = viewedMonth.split('-').map(Number);
      const monthDays = month <= 6 ? 31 : month <= 11 ? 30 : (moment(`${year}/12/30`, 'jYYYY/jMM/jDD', true).isValid() ? 30 : 29);
      return Number(moment().jDate()) >= monthDays - 2;
    },

    timelineDays() {
      return this.days
        .map(day => {
          day.timelineRows = this.getFilteredRows(day);
          return day;
        })
        .filter(day => day.timelineRows.length);
    },

    timelineModalTitle() {
      if (!this.activeTimelineDraft) return "ثبت نوبت";
      return this.isEmptyAppointmentRow(this.activeTimelineDraft)
        ? "ثبت نوبت"
        : `ویرایش نوبت ${this.activeTimelineDraft.lastname || ""}`.trim();
    },

    timelineValidationSummary() {
      const messages = Object.values(this.timelineValidationErrors || {}).filter(Boolean);
      return messages.length ? messages.join("، ") : "";
    },

    canViewBalanceAudits() {
      return this.permissions.includes("reports.financial");
    },

    primaryTimelineService() {
      if (!this.activeTimelineDraft) return {};
      if (!Array.isArray(this.activeTimelineDraft.services) || !this.activeTimelineDraft.services.length) {
        this.activeTimelineDraft.services = [{
          name: "",
          sectionId: "",
          cc: "",
          doctor: "",
          consultant: "",
          discount: "",
          _lastSavedCc: 0,
          addons: []
        }];
      }
      return this.activeTimelineDraft.services[0];
    },

    sortedServiceSections() {
      return [...(this.serviceSections || [])].sort((a, b) =>
        String(a?.name || '').localeCompare(String(b?.name || ''), 'fa', { sensitivity: 'base' })
      );
    },
    appointmentTableColspan() {
      return 16 +
        (this.appointmentColumns.payment_link ? 1 : 0);
    },

    appointmentMinuteStep() {
      const interval = Number(this.clinicSchedule?.interval_minutes || 0);
      return interval > 0 ? interval : 1;
    },

    allServiceTags() {
      return [...new Set((this.inventoryItems || []).flatMap(item => item.service_tags || item.serviceTags || []))]
        .map(tag => String(tag).trim()).filter(Boolean);
    },

    trackingReport() {
      if (!this.activeTrackingRow) {
        return this.emptyTrackingReport();
      }

      return this.buildTrackingReport(this.activeTrackingRow.day, this.activeTrackingRow.row);
    }
  },

  mounted() {
    document.addEventListener('pointerdown', this.handleAppointmentOutsideClick, true);

    const savedMonths =
      localStorage.getItem("schedule_months");

    if (savedMonths) {
      this.months = JSON.parse(savedMonths);
    }

    const realCurrentMonth =
      this.getCurrentJalaliMonth();

    if (!this.months.includes(realCurrentMonth)) {
      this.months.push(realCurrentMonth);
    }

    this.months = this.fillMissingMonths(this.months);

    this.currentMonth =
      this.months.indexOf(realCurrentMonth);

    localStorage.setItem(
      "schedule_current_month",
      this.currentMonth
    );

    this.init();
  },

  beforeUnmount() {
    document.removeEventListener('pointerdown', this.handleAppointmentOutsideClick, true);
  },

  methods: {
    displayPatientPhone(value) {
      const text = String(value || "").trim();
      if (!text) return "";
      if (this.canViewPatientPhone || text.includes("•") || text.includes("*")) return text;
      const digits = text.replace(/\D/g, "");
      if (digits.length <= 4) return "••••";
      return `${digits.slice(0, 3)}••••${digits.slice(-2)}`;
    },

    emptyPaymentDetails() {
      return {
        cash: 0,
        card: 0,
        check: {
          amount: 0,
          number: "",
          dueDate: "",
        },
      };
    },

    normalizePaymentDetails(details = {}) {
      const check = details?.check || {};
      return {
        cash: Math.max(0, this.moneyToNumber(details.cash || 0)),
        card: Math.max(0, this.moneyToNumber(details.card || 0)),
        check: {
          amount: Math.max(0, this.moneyToNumber(check.amount || 0)),
          number: String(check.number || ""),
          dueDate: String(check.dueDate || check.due_date || ""),
        },
      };
    },

    activateScheduleDay(day, smooth = true) {
      if (!day) return;
      this.days.forEach(item => {
        item.collapsed = Number(item.dayNum) !== Number(day.dayNum);
      });
      day.collapsed = false;
      this.allCollapsed = false;

      this.$nextTick(() => {
        window.requestAnimationFrame(() => {
          const element = this.$el?.querySelector(`[data-day-number="${Number(day.dayNum)}"]`);
          element?.scrollIntoView({
            behavior: smooth ? "smooth" : "auto",
            block: "start"
          });
        });
      });
    },

    ensureScheduleDay(date) {
      const dayNumber = Number(date.format("jD"));
      let day = this.days.find(item => Number(item.dayNum) === dayNumber);
      if (day) return day;

      day = this.createScheduleDay(dayNumber, date);
      day.rows = this.createRowsForAddedDay(date);
      this.sortDayRowsByTime(day);
      this.days.push(day);
      this.days.sort((a, b) => Number(a.dayNum) - Number(b.dayNum));
      return day;
    },

    focusTodayAfterLoad() {
      const today = moment();
      if ((this.months[this.currentMonth] || "") !== today.format("jYYYY-jMM")) return;
      const todayDay = this.ensureScheduleDay(today);
      this.activateScheduleDay(todayDay);
    },

    async applyOpenViewRequest(request) {
      if (!request || request.view !== "timeline") return;
      this.appointmentView = "timeline";

      const requestKey = request.requestedAt || request.date || "timeline";
      if (this.handledOpenViewRequestAt === requestKey) return;
      if (!request.date) {
        this.handledOpenViewRequestAt = requestKey;
        return;
      }

      const selectedDate = moment(request.date, "YYYY-MM-DD", true);
      if (!selectedDate.isValid()) return;

      const targetMonth = selectedDate.format("jYYYY-jMM");
      if (!this.months.includes(targetMonth)) {
        this.months.push(targetMonth);
        this.months = this.fillMissingMonths(this.months);
      }

      const targetMonthIndex = this.months.indexOf(targetMonth);
      if (targetMonthIndex !== this.currentMonth) {
        this.currentMonth = targetMonthIndex;
        localStorage.setItem("schedule_current_month", this.currentMonth);
        await this.fetchMonthEvents();
        await this.fetchData();
      }

      const day = this.ensureScheduleDay(selectedDate);
      this.activateScheduleDay(day);
      this.handledOpenViewRequestAt = requestKey;
      this.pendingTimelineFollowup = request.followup || null;
      this.$nextTick(() => this.openNewTimelineAppointment(day));
    },

    showAvatarPreview(event, url) {
      if (!url) return;
      const rect = event.currentTarget.getBoundingClientRect();
      const size = 116;
      const gap = 14;
      let left = rect.left - size - gap;
      if (left < 10) left = Math.min(window.innerWidth - size - 10, rect.right + gap);
      const top = Math.max(10, Math.min(window.innerHeight - size - 10, rect.top + (rect.height - size) / 2));
      this.avatarPreview = { url, left, top };
    },
    hideAvatarPreview() {
      this.avatarPreview = null;
    },
    patientOriginalPhotoUrl(patient) {
      return patient?.profile_photo_url || patient?.avatar_url || patient?.profile_thumbnail_url || '';
    },
    fillMissingMonths(months) {
      const valid = [...new Set((months || []).filter(item => /^\d{4}-\d{2}$/.test(String(item))))].sort();
      if (valid.length < 2) return valid.length ? valid : [this.getCurrentJalaliMonth()];
      const result = [];
      let [year, month] = valid[0].split('-').map(Number);
      const [endYear, endMonth] = valid[valid.length - 1].split('-').map(Number);
      while (year < endYear || (year === endYear && month <= endMonth)) {
        result.push(`${year}-${String(month).padStart(2, '0')}`);
        month += 1;
        if (month > 12) { year += 1; month = 1; }
      }
      return result;
    },
    autoSetAppointmentStatus(row) {
      if (!row || String(row.status || '').trim()) return;
      const hasName = String(row.lastname || '').trim().length > 0;
      const hasPhone = String(row.phone || '').replace(/\D/g, '').length >= 10;
      if (hasName && hasPhone) row.status = 'وقت داده شد';
    },
    async openPatientProfileFromRow(row) {
      const fileNumber = String(row?.fileNumber || "").trim();
      const phone = String(row?.phone || "").trim();

      if (!fileNumber && !phone) {
        Swal.fire({
          icon: "warning",
          title: "پرونده قابل باز شدن نیست",
          text: "برای باز کردن پرونده، شماره پرونده یا شماره تماس بیمار لازم است.",
          timer: 2200,
          showConfirmButton: false
        });
        return;
      }

      this.patientProfileModalOpen = true;
      this.patientProfileLoading = true;
      this.patientProfileError = "";
      this.activePatientProfile = this.patientProfileFallbackFromRow(row);
      this.patientProfileAppointments = [];
      this.patientProfileHistoryPage = 1;
      this.patientProfileTotalAppointments = 0;
      this.patientProfileHasMore = false;
      this.patientProfileHistoryParams = "";

      try {
        const params = new URLSearchParams();
        if (fileNumber) params.append("file_number", fileNumber);
        if (phone) params.append("phone", phone);

        let patient = null;
        try {
          const { data: patients } = await axios.get(`/api/patients/search?${params.toString()}`);
          patient = Array.isArray(patients) ? patients[0] : patients;
        } catch (searchError) {
          if (searchError.response?.status !== 403) throw searchError;
        }

        if (!patient) {
          patient = this.activePatientProfile;
        }

        this.activePatientProfile = patient;
        const historyParams = new URLSearchParams();
        if (patient.file_number || fileNumber) historyParams.append("file_number", patient.file_number || fileNumber);
        if (patient.phone || phone) historyParams.append("phone", patient.phone || phone);
        this.patientProfileHistoryParams = historyParams.toString();
        this.patientProfileLoading = false;
        await this.loadPatientProfileAppointments(1);
      } catch (error) {
        console.error(error);
        this.patientProfileError = "باز کردن پرونده انجام نشد.";
      } finally {
        this.patientProfileLoading = false;
      }
    },

    async loadPatientProfileAppointments(page = 1) {
      if (!this.patientProfileHistoryParams || this.patientProfileHistoryLoading) return;
      this.patientProfileHistoryLoading = true;

      try {
        const params = new URLSearchParams(this.patientProfileHistoryParams);
        params.set("page", String(page));
        params.set("per_page", String(this.patientProfileHistoryPerPage));
        const { data } = await axios.get(`/api/appointments/patient-history?${params.toString()}`);
        const rows = Array.isArray(data?.data) ? data.data : [];
        this.patientProfileAppointments = page === 1
          ? rows
          : [...this.patientProfileAppointments, ...rows];
        this.patientProfileHistoryPage = Number(data?.page || page);
        this.patientProfileTotalAppointments = Number(data?.total || this.patientProfileAppointments.length);
        this.patientProfileHasMore = Boolean(data?.has_more);
      } catch (error) {
        console.error(error);
        if (!this.patientProfileAppointments.length) {
          this.patientProfileError = "دریافت سوابق پرونده انجام نشد.";
        }
      } finally {
        this.patientProfileHistoryLoading = false;
      }
    },

    patientProfileFallbackFromRow(row) {
      const [firstName, ...lastNameParts] = String(row?.lastname || "").trim().split(/\s+/).filter(Boolean);
      return {
        id: row?.patientId || null,
        first_name: firstName || row?.lastname || "",
        last_name: lastNameParts.join(" "),
        name: row?.lastname || "",
        phone: row?.phone || "",
        file_number: row?.fileNumber || "",
        gender: row?.gender || "",
        profile_thumbnail_url: row?.profileThumbnailUrl || "",
        profile_photo_url: row?.profilePhotoUrl || "",
        avatar_url: row?.profileThumbnailUrl || row?.profilePhotoUrl || "",
        wallet_balance: row?.walletBalance || 0,
        outstanding_debt: row?.patientOutstandingDebt || 0,
        customer_level: row?.customerLevel || "silver",
        patient_history: "",
        medical_history: "",
      };
    },

    closePatientProfileModal() {
      this.patientProfileModalOpen = false;
      this.patientProfileLoading = false;
      this.patientProfileError = "";
      this.patientProfileHistoryLoading = false;
    },

    patientProfileName(patient) {
      return [patient?.first_name, patient?.last_name].filter(Boolean).join(" ").trim() || patient?.name || "مراجعه‌کننده";
    },

    patientProfilePhotoUrl(patient) {
      return patient?.avatar_url || patient?.profile_thumbnail_url || patient?.profile_photo_url || "";
    },

    profileServiceText(item) {
      const services = Array.isArray(item?.services) ? item.services : [];
      const names = services.flatMap(service => [
        service?.name,
        ...(Array.isArray(service?.addons) ? service.addons.map(addon => addon?.name) : [])
      ]).filter(Boolean);
      return names.join("، ") || "-";
    },

    isEmptyAppointmentRow(row) {
      return !this.rowHasAppointment(row);
    },

    openDailyReport(day) {
      this.activeDailyReport = {
        day,
        stats: this.buildDailyReportStats(day)
      };
      this.dailyReportModalOpen = true;
    },

    closeDailyReport() {
      this.dailyReportModalOpen = false;
      this.activeDailyReport = null;
    },

    buildDailyReportStats(day) {
      const rows = (day?.rows || []).filter(row => !this.isEmptyAppointmentRow(row));
      const statusMap = new Map();
      const sourceMap = new Map();
      const doctorMap = new Map();
      const consultantMap = new Map();

      const increment = (map, label, amount = 0) => {
        const key = String(label || "").trim() || "نامشخص";
        const current = map.get(key) || { label: key, count: 0, amount: 0 };
        current.count += 1;
        current.amount += amount;
        map.set(key, current);
      };

      rows.forEach(row => {
        increment(statusMap, row.status || "بدون وضعیت");
        increment(sourceMap, row.source || "بدون منبع");

        if (this.isCompletedRow(row)) {
          this.expandedServices(row)
            .filter(service => service.name)
            .forEach(service => {
              const amount = this.serviceLinePrice(service);
              if (service.doctor) increment(doctorMap, service.doctor, amount);
              if (service.consultant) increment(consultantMap, service.consultant, amount);
            });
        }
      });

      const sortByCount = items => [...items.values()].sort((a, b) => b.count - a.count || b.amount - a.amount);
      const statusItems = sortByCount(statusMap);
      const sourceItems = sortByCount(sourceMap);

      return {
        totalAppointments: rows.length,
        scheduledCount: rows.filter(row => this.isScheduledRow(row)).length,
        canceledCount: rows.filter(row => this.isCanceledRow(row)).length,
        doneCount: rows.filter(row => this.isCompletedRow(row)).length,
        statusItems,
        sourceItems,
        doctorItems: sortByCount(doctorMap),
        consultantItems: sortByCount(consultantMap),
        maxSourceCount: Math.max(1, ...sourceItems.map(item => item.count))
      };
    },

    isScheduledRow(row) {
      const status = String(row?.status || "").trim();
      return status.includes("وقت") || status.includes("داده") || status.includes("ÙˆÙ‚Øª") || (!!row?.time && !this.isCanceledRow(row));
    },

    isCanceledRow(row) {
      const status = String(row?.status || "").trim();
      return status.includes("کنسل") || status.includes("لغو") || status.includes("Ú©Ù†Ø³Ù„");
    },

    isCompletedRow(row) {
      const done = String(row?.done || "").trim();
      return done.includes("انجام شد") || done.includes("Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯");
    },

    chartPercent(count, total) {
      const safeTotal = Math.max(Number(total || 0), 1);
      return Math.max(6, Math.round((Number(count || 0) / safeTotal) * 100));
    },

    timelineDayName(day) {
      return String(day?.dateLabel || "").split(" ")[0] || "-";
    },

    timelineDayDate(day) {
      const parts = String(day?.dateLabel || "").split(" ").slice(1).join(" ");
      return parts || day?.dayNum || "-";
    },

    timelineTimeLabel(row) {
      return row?.time || "بدون ساعت";
    },

    timelinePatientName(row) {
      if (String(row?.lastname || "").trim()) return row.lastname;
      if (this.isEmptyAppointmentRow(row)) return "خالی";
      return "نوبت ثبت‌شده";
    },

    timelineServiceText(row) {
      const names = this.expandedServices(row)
        .flatMap(service => this.serviceTagsFor(service.name))
        .filter(Boolean);

      return names.length ? names.slice(0, 2).join("، ") : (row?.description || "بدون خدمت");
    },

    serviceTagsFor(serviceName) {
      const item = this.getServiceData(serviceName);
      const tags = item?.service_tags || item?.serviceTags || [];

      return Array.isArray(tags) && tags.length ? tags : [];
    },

    timelineCareTeam(row) {
      const names = this.expandedServices(row)
        .flatMap(service => [service.doctor, service.consultant])
        .filter(Boolean);

      return names.length ? names.slice(0, 2).join("، ") : "تیم درمان مشخص نشده";
    },

    timelineCardClass(row) {
      if (this.isEmptyAppointmentRow(row)) return "is-empty";

      const status = String(row?.status || "").trim();
      if (status.includes("کنسل")) return "is-canceled";
      if (status.includes("آمد")) return "is-arrived";
      if (status.includes("پاسخ نداد")) return "is-no-answer";
      if (status.includes("پیگیری")) return "is-follow";
      if (status.includes("وقت") || status.includes("داده")) return "is-booked";

      if (this.isCreditor(row)) return "is-creditor";
      if (this.isDebtor(row)) return "is-debtor";
      return "is-booked";
    },

    focusTimelineRow(day, row) {
      if (!row?._rowId) return;

      this.appointmentView = "table";
      day.collapsed = false;
      this.highlightedRowId = row._rowId;

      this.$nextTick(() => {
        const element = document.getElementById(`row-${row._rowId}`);
        element?.scrollIntoView({ behavior: "smooth", block: "center" });
        element?.querySelector("input, select, button")?.focus();
      });
    },

    cloneTimelineRow(row) {
      const draft = JSON.parse(JSON.stringify(row || this.createEmptyAppointmentRow()));
      if (!Array.isArray(draft.services) || !draft.services.length) {
        draft.services = [{
          name: "",
          sectionId: "",
          cc: "",
          doctor: "",
          consultant: "",
          discount: "",
          _lastSavedCc: 0,
          addons: []
        }];
      }
      draft.services = draft.services.map(service => ({
        name: service.name || "",
        sectionId: service.sectionId || service.section_id || this.sectionIdForService(service.name),
        cc: service.cc || "",
        doctor: service.doctor || "",
        consultant: service.consultant || "",
        discount: service.discount ? this.formatDisplayMoney(service.discount) : "",
        _lastSavedCc: service._lastSavedCc || 0,
        addons: Array.isArray(service.addons) ? service.addons.map(addon => ({ ...addon, discount: addon.discount ? this.formatDisplayMoney(addon.discount) : "" })) : []
      }));
      draft.timelineDoctors = Array.isArray(draft.timelineDoctors)
        ? draft.timelineDoctors.slice(0, 2)
        : [...new Set(draft.services.map(service => service.doctor).filter(Boolean))].slice(0, 2);
      draft.timelineConsultant = draft.timelineConsultant
        || draft.services.find(service => service.consultant)?.consultant
        || draft.consultant
        || '';
      draft.sendAppointmentSms = false;
      draft.sendInfoSms = false;
      return draft;
    },

    openTimelineAppointmentModal(day, row) {
      this.activeTimelineDay = day;
      this.activeTimelineRow = row;
      this.activeTimelineDraft = this.cloneTimelineRow(row);
      if (this.pendingTimelineFollowup && !this.rowHasAppointment(row)) {
        this.applyFollowupPrefillToDraft(this.activeTimelineDraft, this.pendingTimelineFollowup);
        this.activeTimelineFollowup = this.pendingTimelineFollowup;
        this.pendingTimelineFollowup = null;
      } else {
        this.activeTimelineFollowup = null;
      }
      this.activeTimelineCreatedInModal = false;
      this.timelineModalOpen = true;
    },

    openNewTimelineAppointment(day, afterRow = null) {
      const newRow = this.createEmptyAppointmentRow();
      const index = afterRow ? day.rows.findIndex(item => item._rowId === afterRow._rowId) : -1;
      day.rows.splice(index >= 0 ? index + 1 : day.rows.length, 0, newRow);
      this.openTimelineAppointmentModal(day, newRow);
      this.activeTimelineCreatedInModal = true;
    },

    closeTimelineModal(keepRow = false) {
      if (!keepRow && this.activeTimelineCreatedInModal && this.activeTimelineDay && this.activeTimelineRow) {
        const index = this.activeTimelineDay.rows.findIndex(row => row._rowId === this.activeTimelineRow._rowId);
        if (index >= 0 && !this.rowHasAppointment(this.activeTimelineRow)) {
          this.activeTimelineDay.rows.splice(index, 1);
        }
      }
      this.timelineModalOpen = false;
      this.activeTimelineDay = null;
      this.activeTimelineRow = null;
      this.activeTimelineDraft = null;
      this.activeTimelineFollowup = null;
      this.activeTimelineCreatedInModal = false;
      this.timelineValidationErrors = {};
    },

    raiseTimelineTimePicker(pickerVm = null) {
      const raise = () => {
        const picker = pickerVm?.$refs?.picker || document.querySelector("body > .vpd-wrapper:last-of-type") || document.querySelector(".vpd-wrapper");
        if (!picker) return;
        picker.classList.add("timeline-time-picker-layer");
        picker.style.setProperty("z-index", "2147483006", "important");
        picker.style.setProperty("position", "fixed", "important");
        const container = picker.querySelector(".vpd-container");
        if (container) {
          container.style.setProperty("z-index", "2147483007", "important");
        }
      };
      this.$nextTick(raise);
      setTimeout(raise, 0);
      setTimeout(raise, 50);
    },

    applyFollowupPrefillToDraft(draft, followup) {
      if (!draft || !followup) return;
      draft.lastname = followup.fullName || draft.lastname || "";
      draft.phone = followup.phone || draft.phone || "";
      draft.gender = followup.gender || draft.gender || "";
      draft.source = followup.source || followup.campaignSource || followup.sourceName || draft.source || "";
      draft.consultant = followup.consultant || draft.consultant || "";
      draft.timelineConsultant = followup.consultant || draft.timelineConsultant || "";
      if (followup.avatarUrl && !draft.profileThumbnailUrl && !draft.profilePhotoUrl) {
        draft.profileThumbnailUrl = followup.avatarUrl;
        draft.profilePhotoUrl = followup.avatarUrl;
      }
      const interestLabels = { 1: "کم", 2: "متوسط", 3: "زیاد", ok: "وقت داده شد" };
      const followupDetails = [
        followup.description,
        followup.campaignTitle ? `کمپین: ${followup.campaignTitle}` : "",
        followup.campaignSource || followup.sourceName ? `منبع کمپین: ${followup.campaignSource || followup.sourceName}` : "",
        followup.contactDate ? `تاریخ تماس: ${followup.contactDate}` : "",
        followup.followUpDate ? `تاریخ پیگیری: ${followup.followUpDate}` : "",
        followup.status ? `وضعیت پیگیری: ${followup.status}` : "",
        followup.interest ? `درجه تمایل: ${interestLabels[followup.interest] || followup.interest}` : "",
        followup.reason ? `علت عدم تبدیل: ${followup.reason}` : "",
        Array.isArray(followup.landingSms) && followup.landingSms.length ? `لندینگ‌ها: ${followup.landingSms.join("، ")}` : "",
      ].filter(Boolean);
      draft.description = [draft.description, ...followupDetails]
        .filter(Boolean)
        .filter((item, index, items) => items.indexOf(item) === index)
        .join(" | ");
      draft.status = draft.status || "وقت داده شد";
      draft.newCustomer = true;
    },

    timelineDayGregorianDate(day) {
      const month = this.months[this.currentMonth] || moment().format("jYYYY-jMM");
      const date = moment(`${month}-${String(day?.dayNum || 1).padStart(2, "0")}`, "jYYYY-jMM-jDD", true);
      return date.isValid() ? date.format("YYYY-MM-DD") : "";
    },

    onTimelineServiceSectionChanged() {
      const service = this.primaryTimelineService;
      if (service.name && !this.serviceOptionsFor(service, this.activeTimelineDraft).includes(service.name)) {
        service.name = "";
        service.cc = "";
        service.addons = [];
      }
      this.calculateRowAmount(this.activeTimelineDraft);
    },

    async saveTimelineModal() {
      if (!this.activeTimelineDay || !this.activeTimelineRow || !this.activeTimelineDraft) return;
      const draft = this.activeTimelineDraft;
      if (!this.validateTimelineDraft(draft)) {
        await Swal.fire({ icon:'warning', title:'اطلاعات نوبت کامل نیست', text:this.timelineValidationSummary || 'لطفا فیلدهای اجباری را کامل کنید.' });
        return;
      }
      const doctors = [...new Set((draft.timelineDoctors || []).filter(Boolean))].slice(0, 2);
      const consultant = draft.timelineConsultant || '';
      draft.status = 'وقت داده شد';
      draft.doctor = doctors.join('، ');
      draft.consultant = consultant;
      draft.amount = '';
      draft.originalAmount = '';
      draft.debt = '';
      draft.done = '';
      draft.serviceTypes = [];
      draft.services = (doctors.length ? doctors : ['']).map((doctor, index) => ({
        name: '', sectionId: '', cc: '', doctor,
        consultant: index === 0 ? consultant : '', discount: '', _lastSavedCc: 0, addons: []
      }));

      const smsTypes = [];
      if (draft.sendAppointmentSms) smsTypes.push('appointment');
      if (draft.sendInfoSms) smsTypes.push('info');
      let smsResults = {};
      if (smsTypes.length) {
        if (!String(draft.phone || '').trim()) {
          smsResults = Object.fromEntries(smsTypes.map(type => [type, { success:false, message:'شماره موبایل بیمار وارد نشده است.' }]));
        } else try {
          const { data } = await axios.post('/api/sms/appointment', {
            types: smsTypes,
            patient_phone: draft.phone,
            patient_name: draft.lastname,
            date: this.activeTimelineDay.dateLabel || '',
            time: draft.time,
            doctors,
            consultant
          });
          smsResults = data.results || {};
          if (draft.sendAppointmentSms) draft.appointmentSms = smsResults.appointment?.success ? 'ارسال شد' : 'انتظار';
          if (draft.sendInfoSms) draft.infoSms = smsResults.info?.success ? 'ارسال شد' : 'انتظار';
        } catch (error) {
          const message = error.response?.data?.message || 'ارتباط با سامانه پیامک برقرار نشد.';
          smsResults = Object.fromEntries(smsTypes.map(type => [type, { success:false, message }]));
        }
        if (draft.sendAppointmentSms) draft.appointmentSms = smsResults.appointment?.success ? 'ارسال شد' : 'انتظار';
        if (draft.sendInfoSms) draft.infoSms = smsResults.info?.success ? 'ارسال شد' : 'انتظار';
      }
      Object.keys(this.activeTimelineRow).forEach(key => {
        if (!(key in this.activeTimelineDraft) && key !== "_rowId") {
          delete this.activeTimelineRow[key];
        }
      });
      Object.assign(this.activeTimelineRow, this.cloneTimelineRow(this.activeTimelineDraft));
      this.activeTimelineRow._rowId = this.activeTimelineRow._rowId || this.activeTimelineDraft._rowId;

      this.sortDayRowsByTime(this.activeTimelineDay);
      this.highlightedRowId = this.activeTimelineRow._rowId;
      this.saveData();
      const followupResult = this.activeTimelineFollowup
        ? {
            followup: this.activeTimelineFollowup,
            date: this.timelineDayGregorianDate(this.activeTimelineDay),
            time: draft.time || "",
            patientName: draft.lastname || "",
            phone: draft.phone || "",
          }
        : null;
      this.closeTimelineModal(true);

      const failedSms = smsTypes.filter(type => !smsResults[type]?.success);
      await Swal.fire({
        icon: failedSms.length ? "warning" : "success",
        title: failedSms.length ? "نوبت ثبت شد؛ بعضی پیامک‌ها ارسال نشد" : "نوبت ثبت شد",
        text: failedSms.map(type => smsResults[type]?.message).filter(Boolean).join('\n'),
        timer: 1000,
        showConfirmButton: false
      });
      if (followupResult) {
        this.$emit("followup-appointment-created", followupResult);
      }
    },

    async init() {
      this.isFetching = true;

      try {
        await Promise.all([
          this.fetchDoctorsAndStaff(),
          this.fetchChannels(),
          this.fetchPaymentConfig()
        ]);

        await this.fetchMonthEvents();
        await this.fetchData();
      } catch (e) {
        console.error("خطا در بارگذاری نوبت‌دهی", e);
      } finally {
        this.isFetching = false;
        this.appointmentReady = true;
        if (this.openViewRequest?.date) {
          await this.applyOpenViewRequest(this.openViewRequest);
        } else {
          this.focusTodayAfterLoad();
        }
      }
    },

    patientPhotoUrl(patient) {
      return patient?.avatar_url || patient?.profile_thumbnail_url || patient?.profile_photo_url || "";
    },

    normalizeCustomerLevel(level) {
      return ["problematic", "blue", "silver", "gold"].includes(level) ? level : "silver";
    },

    customerLevelLabel(level) {
      return ({ problematic: 'دردسرساز', blue: 'آبی', silver: 'نقره‌ای', gold: 'طلایی' })[this.normalizeCustomerLevel(level)];
    },

    applyPatientToAppointment(row, patient) {
      if (!row || !patient) return;
      row.lastname = `${patient.first_name || ""} ${patient.last_name || ""}`.trim();
      row.gender = patient.gender || "";
      row.phone = patient.phone || "";
      row.fileNumber = patient.file_number || "";
      row.profileThumbnailUrl = this.patientPhotoUrl(patient);
      row.profilePhotoUrl = this.patientOriginalPhotoUrl(patient);
      row.hasPatientFile = true;
      row.customerLevel = this.normalizeCustomerLevel(patient.customer_level);
      row.walletBalance = Number(patient.wallet_balance || 0);
      row.patientId = patient.id || null;
      row.patientOutstandingDebt = Number(patient.outstanding_debt || 0);
      this.autoSetAppointmentStatus(row);
      this.clearTimelineValidationError("lastname");
      this.clearTimelineValidationError("phone");
    },

    handleTimelinePhoneInput() {
      if (!this.activeTimelineDraft) return;
      this.autoSetAppointmentStatus(this.activeTimelineDraft);
      this.clearTimelineValidationError("phone");
    },

    onTimelinePatientSearch() {
      if (!this.activeTimelineDraft) return;
      this.autoSetAppointmentStatus(this.activeTimelineDraft);
      this.clearTimelineValidationError("lastname");
      clearTimeout(this.timelinePatientSearchTimer);
      const query = String(this.activeTimelineDraft.lastname || '').trim();
      if (query.length < 2) {
        this.timelinePatientSearchResults = [];
        this.timelinePatientSearchOpen = false;
        return;
      }
      this.timelinePatientSearchOpen = true;
      this.timelinePatientSearchLoading = true;
      this.timelinePatientSearchTimer = setTimeout(async () => {
        try {
          const { data } = await axios.get('/api/patients/search', { params: { q: query } });
          this.timelinePatientSearchResults = Array.isArray(data) ? data : [];
        } catch (error) {
          console.error('خطا در جست‌وجوی بیمار تایم‌لاین', error);
          this.timelinePatientSearchResults = [];
        } finally {
          this.timelinePatientSearchLoading = false;
        }
      }, 250);
    },

    closeTimelinePatientSearch() {
      setTimeout(() => { this.timelinePatientSearchOpen = false; }, 120);
    },

    selectTimelinePatient(patient) {
      this.applyPatientToAppointment(this.activeTimelineDraft, patient);
      this.timelinePatientSearchOpen = false;
      this.timelinePatientSearchResults = [];
    },

    clearTimelineValidationError(field) {
      if (!this.timelineValidationErrors?.[field]) return;
      const nextErrors = { ...this.timelineValidationErrors };
      delete nextErrors[field];
      this.timelineValidationErrors = nextErrors;
    },

    validateTimelineDraft(draft) {
      const errors = {};
      const time = String(draft?.time || "").trim();
      const name = String(draft?.lastname || "").trim();
      const phone = String(draft?.phone || "").trim();

      if (!time) {
        errors.time = "ساعت نوبت را وارد کنید.";
      } else if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) {
        errors.time = "ساعت نوبت باید با فرمت ۲۴ ساعته مثل 14:30 باشد.";
      }

      if (!name) {
        errors.lastname = "نام و نام خانوادگی بیمار را وارد کنید.";
      }

      if (this.canViewPatientPhone && !phone) {
        errors.phone = "شماره تماس بیمار را وارد کنید.";
      } else if ((draft.sendAppointmentSms || draft.sendInfoSms) && this.canViewPatientPhone && !/^09\d{9}$/.test(this.normalizePhoneDigits(phone))) {
        errors.phone = "برای ارسال پیامک، شماره موبایل معتبر وارد کنید.";
      }

      this.timelineValidationErrors = errors;
      return !Object.keys(errors).length;
    },

    normalizePhoneDigits(value) {
      const persian = "۰۱۲۳۴۵۶۷۸۹";
      const arabic = "٠١٢٣٤٥٦٧٨٩";
      return String(value || "")
        .replace(/[۰-۹]/g, digit => persian.indexOf(digit))
        .replace(/[٠-٩]/g, digit => arabic.indexOf(digit))
        .replace(/\D/g, "");
    },

    isProblematicCustomer(row) {
      return row?.customerLevel === "problematic" || row?.customer_level === "problematic";
    },

    getStatusStyle(status) {
      const s = (status || "").trim();

      switch (s) {
        case "وقت داده شد":
          return { backgroundColor: "#fff3cd" };

        case "آمد":
          return { backgroundColor: "#d1e7dd" };

        case "کنسل شد":
          return { backgroundColor: "#f8d7da" };

        case "پاسخ نداد":
          return { backgroundColor: "#e2e3e5" };

        case "پیگیری":
          return { backgroundColor: "#cff4fc" };

        default:
          return {};
      }
    },

    async fillPatientByPhone(row) {
      const phone = (row.phone || "").trim();

      if (!phone) return;

      try {
        const res = await axios.get(
          `/api/patients/find-by-phone/${phone}`
        );

        const patient = res.data;

        if (!patient) return;
        this.applyPatientToAppointment(row, patient);

        // اگر خواستی نام هم داخل توضیحات بیاد:
        // row.description = patient.first_name || "";

      } catch (e) {
        if (e.response?.status !== 404) {
          console.error("خطا در دریافت اطلاعات بیمار", e);
        }
      }
    },

    async fillPatientByFileNumber(row) {
      const fileNumber = String(row.fileNumber || "").trim();

      if (!fileNumber) return;

      try {
        const res = await axios.get(
          "/api/patients/search",
          { params: { file_number: fileNumber } }
        );

        const patient = Array.isArray(res.data) ? res.data[0] : res.data;

        if (!patient) return;

        this.applyPatientToAppointment(row, patient);
      } catch (e) {
        console.error("خطا در دریافت اطلاعات بیمار با شماره پرونده", e);
      }
    },

    async fetchMonthEvents() {
      const { year, month } = this.parseJalaliMonth();

      try {
        const res = await axios.get(
          `https://pnldev.com/api/calender?year=${year}&month=${month}`
        );

        const result = res.data?.result || {};
        this.holidays = {};

        Object.keys(result).forEach(day => {
          const item = result[day];

          this.holidays[Number(day)] = {
            title: Array.isArray(item.event)
              ? item.event.join("، ")
              : "",
            holiday: item.holiday === true
          };
        });

      } catch (e) {
        console.error("خطا در دریافت مناسبت‌ها", e);
        this.holidays = {};
      }
    },

    showDescription(text) {

      if (!text?.trim()) return;

      Swal.fire({
        title: 'توضیحات بیمار',
        html: `
          <div style="
            text-align:right;
            max-height:300px;
            overflow:auto;
            line-height:2;
            white-space:pre-wrap;
          ">
            ${text}
          </div>
        `,
        width: 700,
        confirmButtonText: 'بستن'
      });

    },

    currentDatabaseDateTime() {
      return moment().format("YYYY-MM-DD HH:mm:ss");
    },

    onStatusChanged(row) {
      const status = String(row.status || "").trim();
      if ((status === "آمد" || status === "Ø¢Ù…Ø¯") && !row.arrivedAt) {
        row.arrivedAt = this.currentDatabaseDateTime();
      }
    },

    async onDoneChanged(row) {
      const done = String(row.done || '').trim();
      if (done !== 'انجام شد' && done !== 'Ø§Ù†Ø¬Ø§Ù… Ø´Ø¯') return;
      if (!row.completedAt) {
        row.completedAt = this.currentDatabaseDateTime();
      }
      const appointmentDay = this.days.find(day => (day.rows || []).includes(row));
      if (appointmentDay) this.ensurePaymentLink(appointmentDay, row);
      this.activeCompletionSmsRow = row;
      this.completionSmsErrors = {};
      const alreadySent = row.completionSmsStatuses || {};
      this.selectedCompletionSms = ['treatment_care','payment_link','welcome'].filter(type => !alreadySent[type]);
      if (row.referrerPhone && this.moneyToNumber(row.referralScore) > 0 && !alreadySent.referral_credit) this.selectedCompletionSms.unshift('referral_credit');
      if (!this.canViewPatientPhone) {
        this.selectedCompletionSms = [];
        return;
      }
      this.completionSmsModalOpen = true;
    },

    completionSmsWasSent(type) {
      return Boolean(this.activeCompletionSmsRow?.completionSmsStatuses?.[type]);
    },

    closeCompletionSmsModal() {
      if (this.completionSmsSending) return;
      this.completionSmsModalOpen = false;
      this.activeCompletionSmsRow = null;
      this.selectedCompletionSms = [];
      this.completionSmsErrors = {};
    },

    async openBalanceAuditModal() {
      this.balanceAuditModalOpen = true;
      this.balanceAuditLoading = true;

      try {
        const { data } = await axios.get("/api/appointments/balance-audits", {
          params: { month: this.months[this.currentMonth] || "" }
        });
        this.balanceAuditRows = Array.isArray(data) ? data : [];
      } catch (error) {
        this.balanceAuditRows = [];
        await Swal.fire({
          icon: "error",
          title: "دریافت گزارش انجام نشد",
          text: error.response?.data?.message || "دسترسی یا ارتباط با سرور بررسی شود."
        });
      } finally {
        this.balanceAuditLoading = false;
      }
    },

    closeBalanceAuditModal() {
      this.balanceAuditModalOpen = false;
    },

    formatAuditDate(value) {
      const parsed = moment(value);
      return parsed.isValid() ? parsed.format("YYYY/MM/DD HH:mm") : "-";
    },

    formatSignedDisplayMoney(value) {
      const amount = Number(value || 0);
      if (!amount) return "0";
      return `${amount < 0 ? "-" : ""}${Math.abs(amount).toLocaleString()}`;
    },

    balanceAuditValueClass(value) {
      const amount = Number(value || 0);
      return amount === 0 ? "" : "balance-audit-danger";
    },

    async sendSelectedCompletionSms() {
      const row = this.activeCompletionSmsRow;
      if (!row || !this.selectedCompletionSms.length) return;
      if (!this.canViewPatientPhone) {
        await Swal.fire({ icon:'info', title:'ارسال پیامک برای این نقش غیرفعال است' });
        return;
      }
      if (!row.phone) {
        await Swal.fire({ icon:'warning', title:'شماره موبایل وارد نشده است' });
        return;
      }
      this.completionSmsSending = true;
      this.completionSmsErrors = {};
      try {
        const reference = [this.months[this.currentMonth], row.fileNumber || row.phone, row.time || 'no-time'].join(':');
        const { data } = await axios.post('/api/sms/completion', {
          types: this.selectedCompletionSms,
          patient_phone: row.phone,
          patient_name: row.lastname,
          referrer_phone: row.referrerPhone || null,
          referral_amount: this.moneyToNumber(row.referralScore),
          payment_link: row.paymentLink || null,
          payment_amount: this.moneyToNumber(row.amount),
          reference
        });
        row.completionSmsStatuses = { ...(row.completionSmsStatuses || {}) };
        Object.entries(data.results || {}).forEach(([type,result]) => {
          if (result.success) {
            row.completionSmsStatuses[type] = new Date().toISOString();
            if (type === 'payment_link') {
              row.paymentLinkSentCount = Number(row.paymentLinkSentCount || 0) + 1;
              row.paymentLinkLastSentAt = result.sent_at || this.currentDatabaseDateTime();
            }
          }
          else this.completionSmsErrors[type] = result.message || 'ارسال ناموفق بود';
        });
        this.selectedCompletionSms = this.selectedCompletionSms.filter(type => !row.completionSmsStatuses[type]);
        if (!Object.keys(this.completionSmsErrors).length) {
          await Swal.fire({ icon:'success', title:'پیامک‌های انتخاب‌شده ارسال شدند', timer:1500, showConfirmButton:false });
          this.completionSmsSending = false;
          this.closeCompletionSmsModal();
        }
      } catch (error) {
        await Swal.fire({ icon:'error', title:'ارسال انجام نشد', text:error.response?.data?.message || 'ارتباط با سامانه پیامک برقرار نشد.' });
      } finally {
        this.completionSmsSending = false;
      }
    },

    emptyTrackingReport() {
      return {
        scheduledTime: "-",
        arrivedTime: "-",
        completedTime: "-",
        hasArrived: false,
        hasCompleted: false,
        hasDelay: false,
        hasVisitDuration: false,
        hasTotalDuration: false,
        delayMinutes: null,
        delayText: "-",
        visitDurationMinutes: null,
        visitDurationText: "-",
        totalDurationMinutes: null,
        totalDurationText: "-",
        financial: {
          materialCost: 0,
          doctorWage: 0,
          staffCommission: 0,
          clinicProfit: 0
        }
      };
    },

    openTrackingModal(day, row) {
      this.activeTrackingRow = { day, row };
      this.trackingModalOpen = true;
    },

    closeTrackingModal() {
      this.trackingModalOpen = false;
      this.activeTrackingRow = null;
      this.closeTrackingTimeEditor();
    },

    openTrackingTimeEditor(field) {
      const row = this.activeTrackingRow?.row;
      if (!row) return;

      const labels = {
        arrivedAt: "زمان آمدن",
        completedAt: "زمان انجام‌شدن"
      };

      if (!labels[field]) return;

      const parsed = this.parseTrackingMoment(row[field]);
      if (!parsed) return;

      this.trackingTimeDraft = {
        field,
        label: labels[field],
        value: parsed.format("HH:mm")
      };
      this.trackingTimeEditorOpen = true;
    },

    closeTrackingTimeEditor() {
      if (this.trackingTimeSaving) return;

      this.trackingTimeEditorOpen = false;
      this.trackingTimeDraft = {
        field: "",
        label: "",
        value: ""
      };
    },

    trackingDateForValue(value) {
      const parsed = this.parseTrackingMoment(value);
      if (parsed) return parsed.format("YYYY-MM-DD");

      return moment().format("YYYY-MM-DD");
    },

    trackingDateTimeFromTime(time, previousValue) {
      const value = String(time || "").trim();
      if (!/^\d{2}:\d{2}$/.test(value)) return "";

      return `${this.trackingDateForValue(previousValue)} ${value}:00`;
    },

    async saveTrackingTimeEdit() {
      const row = this.activeTrackingRow?.row;
      const field = this.trackingTimeDraft.field;
      if (!row || !["arrivedAt", "completedAt"].includes(field)) return;

      const nextValue = this.trackingDateTimeFromTime(this.trackingTimeDraft.value, row[field]);
      if (!nextValue) {
        await Swal.fire({
          icon: "warning",
          title: "ساعت معتبر نیست",
          text: "ساعت را با فرمت درست انتخاب کنید."
        });
        return;
      }

      this.trackingTimeSaving = true;
      row[field] = nextValue;

      try {
        this.saveData(0);
        this.trackingTimeSaving = false;
        this.closeTrackingTimeEditor();
        await Swal.fire({
          icon: "success",
          title: "ساعت ثبت شد",
          timer: 1000,
          showConfirmButton: false
        });
      } finally {
        this.trackingTimeSaving = false;
      }
    },

    appointmentMomentForRow(day, row) {
      if (!day?.dayNum || !row?.time) return null;

      const { year, month } = this.parseJalaliMonth();
      const scheduled = moment(`${year}/${month}/${day.dayNum} ${row.time}`, "jYYYY/jM/jD HH:mm");

      return scheduled.isValid() ? scheduled : null;
    },

    parseTrackingMoment(value) {
      if (!value) return null;

      const parsed = moment(value);
      return parsed.isValid() ? parsed : null;
    },

    formatTrackingMoment(value) {
      const parsed = this.parseTrackingMoment(value);
      return parsed ? parsed.format("HH:mm") : "-";
    },

    formatTrackingDuration(minutes) {
      if (minutes === null || minutes === undefined || Number.isNaN(minutes)) return "-";

      const sign = minutes < 0 ? "-" : "";
      const absolute = Math.abs(Math.round(minutes));
      const hours = Math.floor(absolute / 60);
      const mins = absolute % 60;
      const parts = [];

      if (hours) parts.push(`${hours} ساعت`);
      if (mins || !parts.length) parts.push(`${mins} دقیقه`);

      return `${sign}${parts.join(" و ")}`;
    },

    calculateTrackingFinancialRow(row) {
      const financial = {
        totalAmount: 0,
        materialCost: 0,
        doctorWage: 0,
        staffCommission: 0,
        clinicProfit: 0
      };

      if (!row?.services?.length) return financial;

      this.expandedServices(row).forEach(service => {
        const item = this.getServiceData(service.name);
        if (!item) return;

        const cc = Number(service.cc || 0);
        if (cc <= 0) return;

        const grossServiceAmount = Number(item.amount || 0) * cc;
        const serviceAmount = Math.max(
          grossServiceAmount - Math.min(this.moneyToNumber(service.discount), grossServiceAmount),
          0
        );
        const materialCost = Number(item.price || 0) * cc;
        const doctor = this.doctors.find(d => d.name === service.doctor);
        const doctorPercent = Number(doctor?.bonus || 0);
        const doctorWage = this.commissionBase(doctor, row, serviceAmount, materialCost) * (doctorPercent / 100);

        const consultant = this.staff.find(s => s.name === service.consultant);
        const staffPercent = Number(consultant?.bonus || 0);
        const staffCommission = this.commissionBase(consultant, row, serviceAmount, materialCost) * (staffPercent / 100);

        financial.totalAmount += serviceAmount;
        financial.materialCost += materialCost;
        financial.doctorWage += doctorWage;
        financial.staffCommission += staffCommission;
      });

      financial.clinicProfit =
        financial.totalAmount -
        financial.materialCost -
        financial.doctorWage -
        financial.staffCommission;

      return financial;
    },

    buildTrackingReport(day, row) {
      const report = this.emptyTrackingReport();
      const scheduled = this.appointmentMomentForRow(day, row);
      const arrived = this.parseTrackingMoment(row?.arrivedAt);
      const completed = this.parseTrackingMoment(row?.completedAt);
      report.financial = this.calculateTrackingFinancialRow(row);

      report.scheduledTime = scheduled ? scheduled.format("HH:mm") : (row?.time || "-");
      report.hasArrived = Boolean(arrived);
      report.hasCompleted = Boolean(completed);
      report.arrivedTime = this.formatTrackingMoment(row?.arrivedAt);
      report.completedTime = this.formatTrackingMoment(row?.completedAt);

      if (scheduled && arrived) {
        report.hasDelay = true;
        report.delayMinutes = arrived.diff(scheduled, "minutes");
        report.delayText = report.delayMinutes > 0
          ? `${this.formatTrackingDuration(report.delayMinutes)} تأخیر`
          : report.delayMinutes < 0
          ? `${this.formatTrackingDuration(report.delayMinutes)} زودتر`
          : "بدون تأخیر";
      }

      if (arrived && completed) {
        report.hasVisitDuration = true;
        report.visitDurationMinutes = completed.diff(arrived, "minutes");
        report.visitDurationText = this.formatTrackingDuration(report.visitDurationMinutes);
      }

      if (scheduled && completed) {
        report.hasTotalDuration = true;
        report.totalDurationMinutes = completed.diff(scheduled, "minutes");
        report.totalDurationText = this.formatTrackingDuration(report.totalDurationMinutes);
      }

      return report;
    },

    paymentReference(day, row) {
      const parts = [
        this.months[this.currentMonth] || "",
        day?.dayNum || "",
        row.fileNumber || row.phone || "no-file",
        row.time || "no-time"
      ];

      return encodeURIComponent(parts.join("-"));
    },

    ensurePaymentLink(day, row) {
      if (row.paymentLink) return row.paymentLink;

      const base = `${window.location.origin}/payment`;
      row.paymentLink = `${base}/${this.paymentReference(day, row)}?amount=${this.moneyToNumber(row.amount)}`;
      return row.paymentLink;
    },

    openPaymentLinkModal(day, row) {
      if (!this.canViewPatientPhone) {
        Swal.fire({ icon: "info", title: "نمایش و ارسال شماره تماس برای این نقش غیرفعال است" });
        return;
      }
      this.ensurePaymentLink(day, row);
      this.activePaymentLinkRow = row;
      this.paymentLinkModalOpen = true;
    },

    closePaymentLinkModal() {
      if (this.paymentLinkSending) return;
      this.paymentLinkModalOpen = false;
      this.activePaymentLinkRow = null;
    },

    formatPaymentLinkSentAt(value) {
      if (!value) return "-";
      const parsed = moment(value);
      return parsed.isValid() ? parsed.format("YYYY/MM/DD HH:mm") : String(value);
    },

    async copyPaymentLink() {
      const link = this.activePaymentLinkRow?.paymentLink;
      if (!link) return;

      try {
        await navigator.clipboard.writeText(link);
        Swal.fire({ icon: "success", title: "لینک کپی شد", timer: 1100, showConfirmButton: false });
      } catch (e) {
        Swal.fire({ icon: "info", title: "لینک پرداخت", text: link });
      }
    },

    async confirmSendPaymentLink() {
      const row = this.activePaymentLinkRow;
      if (!row) return;

      if (!row.phone) {
        await Swal.fire({ icon: "warning", title: "شماره موبایل وارد نشده است" });
        return;
      }

      const result = await Swal.fire({
        icon: "question",
        title: row.paymentLinkSentCount ? "ارسال مجدد لینک پرداخت؟" : "ارسال لینک پرداخت؟",
        text: `لینک پرداخت برای ${row.lastname || "مراجعه‌کننده"} پیامک شود؟`,
        showCancelButton: true,
        confirmButtonText: "بله، ارسال شود",
        cancelButtonText: "انصراف",
        reverseButtons: true
      });

      if (!result.isConfirmed) return;

      await this.sendPaymentLinkSms(row);
    },

    async sendPaymentLinkSms(row) {
      if (!this.canViewPatientPhone) {
        await Swal.fire({ icon: "info", title: "ارسال پیامک برای این نقش غیرفعال است" });
        return;
      }
      this.paymentLinkSending = true;

      try {
        const { data } = await axios.post("/api/sms/payment-link", {
          patient_phone: row.phone,
          patient_name: row.lastname,
          payment_link: row.paymentLink,
          amount: this.moneyToNumber(row.amount)
        });

        row.paymentLinkSentCount = Number(row.paymentLinkSentCount || 0) + 1;
        row.paymentLinkLastSentAt = data.sent_at || this.currentDatabaseDateTime();
        this.saveData();

        await Swal.fire({ icon: "success", title: "لینک پرداخت ارسال شد", timer: 1300, showConfirmButton: false });
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "ارسال انجام نشد",
          text: error.response?.data?.message || "ارتباط با سامانه پیامک برقرار نشد."
        });
      } finally {
        this.paymentLinkSending = false;
      }
    },


    generateMonthDays() {
      const { year, month } = this.parseJalaliMonth();

      const daysInMonth = moment.jDaysInMonth(year, month - 1);

      this.days = [];

      for (let i = 1; i <= daysInMonth; i++) {
        const date = moment(`${year}/${month}/${i}`, "jYYYY/jM/jD");
        const event = this.holidays[i + 1] || null;

        this.days.push({
          id: this._idCounter++,
          dayNum: i,
          dateLabel: date.format("dddd jD jMMMM"),
          holidayTitle: event?.title || "",
          isHoliday: date.day() === 5 || event?.holiday === true,
          rows: [],
          collapsed: this.shouldCollapseScheduleDay(i)
        });
      }
    },

    clinicDayKey(date) {
      const map = {
        0: "sunday",
        1: "monday",
        2: "tuesday",
        3: "wednesday",
        4: "thursday",
        5: "friday",
        6: "saturday"
      };

      return map[date.day()];
    },

    normalizeTimeValue(value, fallback) {
      const text = String(value || fallback || "").trim();
      return /^\d{2}:\d{2}$/.test(text) ? text : fallback;
    },

    minutesFromTime(value) {
      const [hour, minute] = value.split(":").map(Number);
      return (hour * 60) + minute;
    },

    timeFromMinutes(totalMinutes) {
      const hour = Math.floor(totalMinutes / 60);
      const minute = totalMinutes % 60;
      return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
    },

    generateClinicTimeSlots(dayKey) {
      const interval = Math.max(Number(this.clinicSchedule.interval_minutes || 15), 1);
      const dayTimes = this.clinicSchedule.day_times?.[dayKey] || {};
      const start = this.normalizeTimeValue(dayTimes.start, "09:00");
      const end = this.normalizeTimeValue(dayTimes.end, "17:00");
      const startMinutes = this.minutesFromTime(start);
      const endMinutes = this.minutesFromTime(end);

      if (endMinutes <= startMinutes) return [];

      const slots = [];
      for (let minute = startMinutes; minute < endMinutes; minute += interval) {
        slots.push(this.timeFromMinutes(minute));
      }

      return slots;
    },

    createRowsForAddedDay(date) {
      const dayKey = this.clinicDayKey(date);
      const activeDays = new Set(this.clinicSchedule.active_days || []);

      if (!activeDays.has(dayKey)) {
        return [this.createEmptyAppointmentRow()];
      }

      const slots = this.generateClinicTimeSlots(dayKey);

      if (!slots.length) {
        return [this.createEmptyAppointmentRow()];
      }

      return slots.map(time => ({
        ...this.createEmptyAppointmentRow(),
        time
      }));
    },

    createScheduleDay(dayNum, date) {
      const event = this.holidays[dayNum] || null;

      return {
        id: this._idCounter++,
        dayNum,
        dateLabel: date.format("dddd jD jMMMM"),
        holidayTitle: event?.title || "",
        isHoliday: date.day() === 5 || event?.holiday === true,
        rows: [],
        collapsed: this.shouldCollapseScheduleDay(dayNum)
      };
    },

    generateClinicScheduleForCurrentMonth() {
      const { year, month } = this.parseJalaliMonth();
      const daysInMonth = moment.jDaysInMonth(year, month - 1);
      const activeDays = new Set(this.clinicSchedule.active_days || []);
      let changed = false;

      for (let i = 1; i <= daysInMonth; i++) {
        const date = moment(`${year}/${month}/${i}`, "jYYYY/jM/jD");
        const dayKey = this.clinicDayKey(date);

        if (!activeDays.has(dayKey)) continue;

        const slots = this.generateClinicTimeSlots(dayKey);
        if (!slots.length) continue;

        let day = this.days.find(item => Number(item.dayNum) === i);
        if (!day) {
          day = this.createScheduleDay(i, date);
          this.days.push(day);
          changed = true;
        }

        if (!Array.isArray(day.rows)) day.rows = [];

        const existingTimes = new Set(day.rows.map(row => String(row.time || "").trim()).filter(Boolean));
        slots.forEach(time => {
          if (existingTimes.has(time)) return;
          day.rows.push({
            ...this.createEmptyAppointmentRow(),
            time
          });
          changed = true;
        });

        this.sortDayRowsByTime(day);
      }

      this.days.sort((a, b) => Number(a.dayNum) - Number(b.dayNum));
      return changed;
    },

    

    async fetchDoctorsAndStaff() {
      const [doctors, staff, inventory, inventoryContext] =
        await Promise.all([
          axios.get("/api/doctors"),
          axios.get("/api/staff"),
          axios.get("/api/inventory"),
          axios.get("/api/inventory/context")
        ]);

      this.doctors = doctors.data;

      this.doctorOptions = doctors.data;

      this.staff = staff.data;

      this.consultantOptions =
        staff.data.map(s => s.name);

      this.inventoryItems = inventory.data;

      this.serviceSections = (inventoryContext.data.sections || []).slice();

      this.serviceOptions =
        inventory.data.map(i => i.name);

      this.inventoryStock = {};

      inventory.data.forEach(i => {
        this.inventoryStock[i.name] = i.stock;
      });
    },

    async fetchChannels() {
      const res =
        await axios.get("/api/channels");

      this.sourceOptions = res.data;
    },

    async fetchPaymentConfig() {
      const [optionsRes, settingsRes] = await Promise.all([
        axios.get("/api/payment-options"),
        axios.get("/api/settings")
      ]);

      this.paymentOptions = {
        methods: optionsRes.data.methods?.length ? optionsRes.data.methods : this.paymentOptions.methods,
        accounts: optionsRes.data.accounts?.length ? optionsRes.data.accounts : this.paymentOptions.accounts
      };

      if (settingsRes.data.appointment_columns) {
        this.appointmentColumns = {
          ...this.appointmentColumns,
          ...settingsRes.data.appointment_columns
        };
      }

      if (settingsRes.data.clinic_schedule) {
        this.clinicSchedule = {
          ...this.clinicSchedule,
          ...settingsRes.data.clinic_schedule,
          day_times: {
            ...this.clinicSchedule.day_times,
            ...(settingsRes.data.clinic_schedule.day_times || {})
          }
        };
      }
    },

    async fetchData() {
      this.isFetching = true;

      try {
        const previousUnreadKeys = new Set(this.doctorNoteUnreadSnapshot || []);
        const res = await axios.get("/api/appointments");

        const month = this.months[this.currentMonth];
        const data = res.data.filter(a => a.month === month);

        this.days = [];

        data.forEach(item => {
          let day = this.days.find(d => Number(d.dayNum) === Number(item.day_num));

          if (!day) {
            const { year, month: m } = this.parseJalaliMonth();
            const date = moment(`${year}/${m}/${item.day_num}`, "jYYYY/jM/jD");
            const event = this.holidays[Number(item.day_num)] || null;

            day = {
              id: this._idCounter++,
              dayNum: Number(item.day_num),
              dateLabel: date.format("dddd jD jMMMM"),
              holidayTitle: event?.title || "",
              isHoliday: date.day() === 5 || event?.holiday === true,
              rows: [],
              collapsed: this.shouldCollapseScheduleDay(Number(item.day_num))
            };

            this.days.push(day);
          }

          const services = item.services?.length
            ? item.services.map((s, serviceIndex) => ({
                name: s.name || "",
                sectionId: s.sectionId || s.section_id || this.sectionIdForService(s.name),
                cc: s.cc || "",
                doctor: s.doctor || "",
                consultant: s.consultant || "",
                discount: (s.discount || (serviceIndex === 0 ? item.discount : 0)) ? this.formatDisplayMoney(s.discount || item.discount) : "",
                _lastSavedCc: parseInt(s.cc) || 0
                ,addons: (s.addons || []).map((addon, index) => ({ name:addon.name || '', cc:addon.cc || '', discount:addon.discount ? this.formatDisplayMoney(addon.discount) : '', _key:`addon-loaded-${index}-${Date.now()}` }))
              }))
            : [{
                name: "",
                sectionId: "",
                cc: "",
                doctor: "",
                consultant: "",
                discount: ""
                ,_lastSavedCc: 0
                ,addons: []
              }];

          day.rows.push({
            _rowId: `row-${this._rowCounter++}`,
            appointmentId: item.id || null,
            patientId: item.patient_id || null,
            lastname: item.lastname || "",
            gender: item.gender || "",
            phone: item.phone || "",
            fileNumber: item.file_number || "",
            profileThumbnailUrl: this.patientPhotoUrl(item),
            profilePhotoUrl: this.patientOriginalPhotoUrl(item),
            hasPatientFile: !!item.has_patient_file,
            time: item.time || "",
            status: item.status || "",
            arrivedAt: item.arrived_at || "",
            doctor: item.doctor || "",
            consultant: item.consultant || "",
            source: item.source || "",
            description: item.description || "",
            doctorNote: item.doctor_note || "",
            noteMessageCount: Number(item.note_message_count || 0),
            doctorNoteUnread: Boolean(item.doctor_note_unread),
            done: item.done || "",
            completedAt: item.completed_at || "",
            amount: item.amount ? this.formatDisplayMoney(item.amount) : "",
            originalAmount: item.original_amount ? this.formatDisplayMoney(item.original_amount) : "",
            debt: item.debt ? this.formatDisplayMoney(item.debt) : "",
            originalDebt: Number(item.debt || 0),
            patientOutstandingDebt: Number(item.patient_outstanding_debt || 0),
            paymentMethod: item.payment_method || "",
            paymentAccount: item.payment_account || "",
            paymentDetails: this.normalizePaymentDetails(item.payment_details || {}),
            paymentLink: item.payment_link || "",
            paymentLinkSentCount: Number(item.payment_link_sent_count || 0),
            paymentLinkLastSentAt: item.payment_link_last_sent_at || "",
            referrerPhone: item.referrer_phone || "",
            referralScore: item.referral_score ? this.formatDisplayMoney(item.referral_score) : "",
            referralCommissionType: item.referral_commission_type || "",
            referralCommissionValue: Number(item.referral_commission_value || 0),
            walletBalance: Number(item.wallet_balance || 0),
            walletApplied: item.wallet_applied ? this.formatDisplayMoney(item.wallet_applied) : "",
            discount: item.discount ? this.formatDisplayMoney(item.discount) : "",
            newCustomer: !!item.new_customer,
            customerLevel: this.normalizeCustomerLevel(item.customer_level),
            appointmentSms: item.appointment_sms || "",
            infoSms: item.info_sms || "",
            completionSmsStatuses: item.completion_sms_statuses || {},
            serviceTypes: this.normalizeServiceSectionIds(item.service_types),
            services
          });
        });

        if (data.length === 0) {
          this.generateClinicScheduleForCurrentMonth();
        }
        this.days.forEach(day => this.sortDayRowsByTime(day));
        this.days.sort((a, b) => a.dayNum - b.dayNum);
        const nextUnreadKeys = new Set();
        this.days.forEach(day => {
          day.rows.forEach(row => {
            if (row.doctorNoteUnread) nextUnreadKeys.add(this.appointmentNoteKey(row));
          });
        });
        const hasNewUnreadDoctorNote = [...nextUnreadKeys].some(key => !previousUnreadKeys.has(key));
        if (this.doctorNoteUnreadInitialized && hasNewUnreadDoctorNote) this.playDoctorNoteBell();
        this.doctorNoteUnreadSnapshot = nextUnreadKeys;
        this.doctorNoteUnreadInitialized = true;

      } catch (e) {
        console.error(e);
        this.days = [];
      }

      this.isFetching = false;
    },

    updateRowAmounts(row) {

      let totalAmount = 0;

      this.expandedServices(row).forEach(service => {

        const item =
          this.getServiceData(service.name);

        if (!item) return;

        const cc =
          Number(service.cc || 0);

        if (cc <= 0) return;

        totalAmount +=
          Number(item.amount || 0) * cc;

      });

      row.originalAmount =
        this.formatDisplayMoney(totalAmount);

      this.calculateReferralRewardForRow(row);
      this.calculateFinalAmount(row);
    },

    calculateFinalAmount(row) {

      const original =
        this.moneyToNumber(row.originalAmount);

      const discount = this.totalServiceDiscount(row);
      row.discount = discount ? this.formatDisplayMoney(discount) : "";

      const walletApplied = Math.min(
        this.moneyToNumber(row.walletApplied),
        Math.max(0, original - discount)
      );
      row.walletApplied = walletApplied ? this.formatDisplayMoney(walletApplied) : "";

      const finalAmount =
        Math.max(
          original - discount - walletApplied,
          0
        );

      row.amount =
        this.formatDisplayMoney(finalAmount);
    },

    getServiceData(serviceName) {
      return this.inventoryItems.find(
        i => i.name === serviceName
      ) || null;
    },

    calculateClinicProfitRow(row) {

      if (!row.services || !row.services.length)
        return 0;

      let totalAmount = 0;
      let totalCost = 0;
      let doctorShare = 0;
      let consultantShare = 0;

      this.expandedServices(row).forEach(service => {

        const item =
          this.getServiceData(service.name);

        if (!item) return;

        const cc =
          Number(service.cc || 0);

        if (cc <= 0) return;

        const grossServiceAmount = Number(item.amount || 0) * cc;
        const serviceAmount = Math.max(
          grossServiceAmount - Math.min(this.moneyToNumber(service.discount), grossServiceAmount),
          0
        );

        const materialCost =
          Number(item.price || 0) * cc;

        totalAmount += serviceAmount;

        totalCost += materialCost;

        const doctor =
          this.doctors.find(
            d => d.name === service.doctor
          );

        const doctorPercent =
          Number(doctor?.bonus || 0);

        doctorShare += this.commissionBase(doctor, row, serviceAmount, materialCost) * (doctorPercent / 100);

        const consultant =
          this.staff.find(
            s => s.name === service.consultant
          );

        const consultantPercent =
          Number(consultant?.bonus || 0);

        consultantShare += this.commissionBase(consultant, row, serviceAmount, materialCost) * (consultantPercent / 100);

      });

      return (
        totalAmount -
        totalCost -
        doctorShare -
        consultantShare
      );
    },

    moneyToNumber(value) {

      if (
        value === null ||
        value === undefined
      ) return 0;

      const clean =
        value
          .toString()
          .replace(/,/g, "")
          .replace(/[^\d.-]/g, "");

      return Number(clean) || 0;
    },

    formatDisplayMoney(value) {
      return Number(value || 0)
        .toLocaleString();
    },

    formatMoney(row, field) {

      if (!row[field]) {
        row[field] = "";
        return;
      }

      let val =
        row[field]
          .toString()
          .replace(/,/g, "");

      row[field] =
        Number(val || 0).toLocaleString();
    },

    commissionAppliesToCustomer(resource, row) {
      if (!resource) return false;
      const scope = resource.commission_customer_scope || "both";
      if (scope === "new") return Boolean(row?.newCustomer);
      if (scope === "existing") return !Boolean(row?.newCustomer);
      return true;
    },

    commissionBase(resource, row, serviceAmount, materialCost) {
      if (!this.commissionAppliesToCustomer(resource, row)) return 0;
      return resource?.commission_after_materials
        ? Math.max(serviceAmount - materialCost, 0)
        : Math.max(serviceAmount, 0);
    },

    salesSalaryBonus(resource, totalSales) {
      if (!resource?.sales_bonus_enabled) return 0;
      const tier = (resource.sales_bonus_tiers || [])
        .filter(item => Number(totalSales) > Number(item.sales_from || 0))
        .sort((a, b) => Number(b.sales_from) - Number(a.sales_from))[0];
      return Number(tier?.salary_addition || 0);
    },

    formatSignedMoney(row, field) {
      const raw = String(row[field] ?? "").replace(/,/g, "").trim();

      if (!raw || raw === "-") {
        row[field] = raw;
        return;
      }

      const isNegative = raw.startsWith("-");
      const digits = raw.replace(/[^\d]/g, "");

      if (!digits) {
        row[field] = isNegative ? "-" : "";
        return;
      }

      row[field] = `${isNegative ? "-" : ""}${Number(digits).toLocaleString()}`;
    },

    saveData(delay = 1200, force = false) {
      if (this.isFetching && !force) return;

      clearTimeout(this.saveTimeout);

      this.saveTimeout =
        setTimeout(async () => {

          try {

            const month =
              this.months[this.currentMonth];

            const payload = [];

            this.days.forEach(day => {

              day.rows.forEach((row, rowIndex) => {
                if (!this.rowShouldPersist(row)) {
                  return;
                }
                // محاسبه مبلغ کل خدمات
let totalAmount = 0

this.expandedServices(row).forEach(service => {

  const item = this.getServiceData(service.name)

  if(!item) return

  const cc = Number(service.cc || 0)

  // اگر تعداد سی سی خالی بود حساب نکن
  if(cc <= 0) return

  const serviceAmount =
    Number(item.amount || 0) * cc

  totalAmount += serviceAmount

})



// ثبت مبلغ نهایی داخل ستون مبلغ
row.originalAmount =
  this.formatDisplayMoney(totalAmount)

this.calculateFinalAmount(row)

                payload.push({

                  month,
                  appointment_id:
                    row.appointmentId || null,

                  day_num:
                    day.dayNum,

                  sort_order:
                    rowIndex,
                    
                  lastname:
                    row.lastname,

                  gender:
                    row.gender,

                  phone:
                    row.phone,

                  file_number:
                    row.fileNumber,

                  time:
                    row.time,

                  status:
                    row.status,

                  arrived_at:
                    row.arrivedAt || null,

                  doctor:
                    row.doctor,

                  consultant:
                    row.consultant,

                  source:
                    row.source,

                  description:
                    row.description,

                  doctor_note:
                    row.doctorNote || null,

                  done:
                    row.done,

                  completed_at:
                    row.completedAt || null,

                  amount:
                    this.moneyToNumber(row.amount),

                  original_amount:
                    this.moneyToNumber(row.originalAmount),

                  debt:
                    this.moneyToNumber(row.debt),

                  payment_method:
                    row.paymentMethod,

                  payment_account:
                    row.paymentAccount,

                  payment_details:
                    this.normalizePaymentDetails(row.paymentDetails || {}),

                  payment_link:
                    row.paymentLink || null,

                  payment_link_sent_count:
                    Number(row.paymentLinkSentCount || 0),

                  payment_link_last_sent_at:
                    row.paymentLinkLastSentAt || null,

                  referrer_phone:
                    row.referrerPhone,

                  referral_score:
                    this.moneyToNumber(row.referralScore),

                  wallet_applied:
                    this.moneyToNumber(row.walletApplied),

                  referral_commission_type:
                    row.referralCommissionType || null,

                  referral_commission_value:
                    Number(row.referralCommissionValue || 0),

                  discount:
                    this.moneyToNumber(row.discount),

                  new_customer:
                    row.newCustomer,

                  appointment_sms:
                    row.appointmentSms,

                  info_sms:
                    row.infoSms,

                  completion_sms_statuses:
                    row.completionSmsStatuses || {},

                  service_types: row.serviceTypes || [],

                  services:
                    row.services.map(s => ({
                      name: s.name || "",
                      section_id: s.sectionId || this.sectionIdForService(s.name) || null,
                      cc: s.cc || "",
                      doctor: s.doctor || "",
                      consultant: s.consultant || "",
                      discount: this.moneyToNumber(s.discount)
                      ,addons: (s.addons || []).map(addon => ({ name:addon.name || '', cc:addon.cc || '', discount:this.moneyToNumber(addon.discount) }))
                    }))

                });

              });

            });

            await axios.post(
              "/api/appointments",
              {
                month,
                appointments: payload
              }
            );

          } catch (e) {

            console.error(e);

            if (e.isAuthExpired || [401, 419].includes(e.response?.status)) return;

            Swal.fire({
              icon: "error",
              title: "خطا",
              text: "ذخیره انجام نشد"
            });

          }

        }, delay);
    },

    createEmptyAppointmentRow() {
      return {

        _rowId:
          `row-${this._rowCounter++}`,

        appointmentId: null,
        lastname: "",
        gender: "",
        phone: "",
        fileNumber: "",
        profileThumbnailUrl: "",
        profilePhotoUrl: "",
        hasPatientFile: false,
        customerLevel: "silver",
        time: "",
        status: "",
        arrivedAt: "",
        doctor: "",
        consultant: "",
        source: "",
        description: "",
        doctorNote: "",
        done: "",
        completedAt: "",
        amount: "",
        debt: "",
        paymentMethod: "",
        paymentAccount: "",
        paymentDetails: this.emptyPaymentDetails(),
        paymentLink: "",
        paymentLinkSentCount: 0,
        paymentLinkLastSentAt: "",

        newCustomer: false,

        appointmentSms: "",
        infoSms: "",
        completionSmsStatuses: {},
        serviceTypes: [],

        referrerPhone: "",
        referralScore: "",
        referralCommissionType: "",
        referralCommissionValue: 0,
        patientId: null,
        patientOutstandingDebt: 0,
        originalDebt: 0,
        walletBalance: 0,
        walletApplied: "",
        discount: "",
        originalAmount: "",

        services: [{
          name: "",
          sectionId: "",
          cc: "",
          doctor: "",
          consultant: "",
          discount: ""
          ,_lastSavedCc: 0
          ,addons: []
        }]
      };
    },

    expandedServices(row) {
      return (row.services || []).flatMap(service => [service, ...(service.addons || []).map(addon => ({ ...addon, doctor:addon.doctor || service.doctor, consultant:addon.consultant || service.consultant, isAddon:true }))]);
    },

    serviceLinePrice(service) {
      const item = this.getServiceData(service?.name);
      return item ? Number(item.amount || 0) * Math.max(Number(service?.cc || 0), 0) : 0;
    },

    isDebtor(row) {
      return this.patientDebtAmount(row) > 0 || this.appointmentBalanceAmount(row) > 0;
    },

    isCreditor(row) {
      return this.appointmentBalanceAmount(row) < 0;
    },

    appointmentBalanceAmount(row) {
      return this.moneyToNumber(row?.debt);
    },

    appointmentBalanceClass(row) {
      if (this.isCreditor(row)) return "creditor-balance-input";
      if (this.isDebtor(row)) return "debtor-balance-input";
      return "";
    },

    patientDebtAmount(row) {
      return Math.max(0, Number(row?.patientOutstandingDebt || 0));
    },

    financialTriggerTitle(row) {
      const debt = this.patientDebtAmount(row);
      const deposit = Number(row?.walletBalance || 0);
      const payment = this.paymentDetailsSummary(row);
      if (debt > 0) return `هشدار بدهکاری: ${this.formatDisplayMoney(debt)} تومان`;
      if (deposit > 0) return `بیعانه موجود: ${this.formatDisplayMoney(deposit)} تومان`;
      if (payment) return payment;
      return "ثبت بدهکاری یا بیعانه";
    },

    hasPaymentDetails(row) {
      return Boolean(this.paymentDetailsSummary(row));
    },

    paymentDetailsSummary(row) {
      const details = this.normalizePaymentDetails(row?.paymentDetails || {});
      const parts = [];
      if (details.cash > 0) parts.push(`نقدی ${this.formatDisplayMoney(details.cash)}`);
      if (details.card > 0) parts.push(`کارت ${this.formatDisplayMoney(details.card)}`);
      if (details.check.amount > 0 || details.check.number || details.check.dueDate) {
        parts.push(`چک ${details.check.amount ? this.formatDisplayMoney(details.check.amount) : ""}`.trim());
      }
      if (row?.paymentMethod) parts.unshift(row.paymentMethod);
      return parts.join("، ");
    },

    walletSettlementAmount(row) {
      const appointmentDebt = Math.max(0, this.moneyToNumber(row?.debt));
      const walletBalance = Math.max(0, this.moneyToNumber(row?.walletBalance));
      return Math.min(appointmentDebt, walletBalance);
    },

    async settleDebtWithWallet() {
      const row = this.activeFinancialRow;
      const amount = this.walletSettlementAmount(row);
      if (!row?.patientId || amount <= 0 || this.financialSaving) return;

      const confirmation = await Swal.fire({
        icon: "question",
        title: "تسویه بدهی با بیعانه؟",
        html: `مبلغ <b>${this.formatDisplayMoney(amount)} تومان</b> از اعتبار بیمار کسر و از بدهی این نوبت کم می‌شود.`,
        showCancelButton: true,
        confirmButtonText: "بله، پرداخت شود",
        cancelButtonText: "انصراف",
        confirmButtonColor: "#16a34a",
        reverseButtons: true
      });
      if (!confirmation.isConfirmed) return;

      this.financialSaving = true;
      try {
        const { data } = await axios.post(`/api/patients/${row.patientId}/wallet/withdraw`, {
          amount,
          description: `تسویه بدهی نوبت ${row.lastname || "بیمار"} از محل بیعانه`
        });

        const currentDebt = Math.max(0, this.moneyToNumber(row.debt));
        const remainingDebt = Math.max(0, currentDebt - amount);
        row.walletBalance = Number(data.wallet_balance || 0);
        row.debt = remainingDebt ? this.formatDisplayMoney(remainingDebt) : "";
        row.originalDebt = remainingDebt;
        row.patientOutstandingDebt = Math.max(0, Number(row.patientOutstandingDebt || 0) - amount);
        this.financialDebtDraft = remainingDebt ? this.formatDisplayMoney(remainingDebt) : "";

        await this.saveData();
        this.closeFinancialPanel();
        await Swal.fire({
          icon: "success",
          title: "پرداخت انجام شد",
          text: `${this.formatDisplayMoney(amount)} تومان از بیعانه کسر شد.`,
          timer: 1500,
          showConfirmButton: false
        });
      } catch (error) {
        await Swal.fire({
          icon: "error",
          title: "پرداخت انجام نشد",
          text: error.response?.data?.message || "خطا در تسویه بدهی با بیعانه"
        });
      } finally {
        this.financialSaving = false;
      }
    },

    openFinancialPanel(row) {
      this.activeFinancialRow = row;
      this.financialDebtDraft = this.moneyToNumber(row?.debt)
        ? this.formatDisplayMoney(this.moneyToNumber(row.debt))
        : "";
      this.financialDepositDraft = "";
      const details = this.normalizePaymentDetails(row?.paymentDetails || {});
      this.financialPaymentMethodDraft = row?.paymentMethod || "";
      this.financialPaymentAccountDraft = row?.paymentAccount || "";
      this.financialCashDraft = details.cash ? this.formatDisplayMoney(details.cash) : "";
      this.financialCardDraft = details.card ? this.formatDisplayMoney(details.card) : "";
      this.financialCheckAmountDraft = details.check.amount ? this.formatDisplayMoney(details.check.amount) : "";
      this.financialCheckNumberDraft = details.check.number || "";
      this.financialCheckDueDateDraft = details.check.dueDate || "";
      this.financialCheckOpen = Boolean(details.check.amount || details.check.number || details.check.dueDate);
      this.financialPanelOpen = true;
    },

    closeFinancialPanel() {
      this.financialPanelOpen = false;
      this.activeFinancialRow = null;
      this.financialDebtDraft = "";
      this.financialDepositDraft = "";
      this.financialPaymentMethodDraft = "";
      this.financialPaymentAccountDraft = "";
      this.financialCashDraft = "";
      this.financialCardDraft = "";
      this.financialCheckOpen = false;
      this.financialCheckAmountDraft = "";
      this.financialCheckNumberDraft = "";
      this.financialCheckDueDateDraft = "";
    },

    formatFinancialDraft(field) {
      const amount = this.moneyToNumber(this[field]);
      this[field] = amount ? this.formatDisplayMoney(amount) : "";
    },

    async saveFinancialPanel() {
      const row = this.activeFinancialRow;
      if (!row) return;

      const newDebt = Math.max(0, this.moneyToNumber(this.financialDebtDraft));
      const deposit = Math.max(0, this.moneyToNumber(this.financialDepositDraft));
      const cash = Math.max(0, this.moneyToNumber(this.financialCashDraft));
      const card = Math.max(0, this.moneyToNumber(this.financialCardDraft));
      const checkAmount = Math.max(0, this.moneyToNumber(this.financialCheckAmountDraft));
      if (deposit > 0 && !row.patientId) {
        await Swal.fire({ icon: "warning", title: "پرونده بیمار مشخص نیست", text: "ابتدا بیمار را از پرونده‌های موجود انتخاب کنید." });
        return;
      }

      this.financialSaving = true;
      try {
        const previousDebt = Number(row.originalDebt || 0);
        row.debt = newDebt ? this.formatDisplayMoney(newDebt) : "";
        row.patientOutstandingDebt = Math.max(0, Number(row.patientOutstandingDebt || 0) - previousDebt + newDebt);
        row.originalDebt = newDebt;
        row.paymentMethod = this.financialPaymentMethodDraft;
        row.paymentAccount = this.financialPaymentAccountDraft;
        row.paymentDetails = this.normalizePaymentDetails({
          cash,
          card,
          check: {
            amount: checkAmount,
            number: this.financialCheckNumberDraft,
            dueDate: this.financialCheckDueDateDraft,
          },
        });

        if (deposit > 0) {
          const { data } = await axios.post(`/api/patients/${row.patientId}/wallet/deposit`, {
            amount: deposit,
            description: `ثبت بیعانه از نوبت‌دهی برای ${row.lastname || "بیمار"}`
          });
          row.walletBalance = Number(data.wallet_balance || 0);
        }

        await this.saveData();
        this.closeFinancialPanel();
        await Swal.fire({ icon: "success", title: "وضعیت مالی ثبت شد", timer: 1100, showConfirmButton: false });
      } catch (error) {
        await Swal.fire({ icon: "error", title: "ثبت انجام نشد", text: error.response?.data?.message || "خطا در ثبت وضعیت مالی بیمار" });
      } finally {
        this.financialSaving = false;
      }
    },

    hasDoctorNote(row) {
      return String(row?.doctorNote || "").trim().length > 0 || Number(row?.noteMessageCount || 0) > 0;
    },

    async openDoctorNote(row) {
      this.activeDoctorNoteRow = row;
      this.doctorNoteDraft = "";
      this.doctorNoteMessages = [];
      this.doctorNoteModalOpen = true;
      await this.loadDoctorNoteMessages();
      this.$nextTick(() => this.$refs.doctorNoteEditor?.focus());
    },

    closeDoctorNoteModal() {
      if (this.doctorNoteDraft.trim()) {
        Swal.fire({
          icon: "warning",
          title: "تغییرات ذخیره نشده",
          text: "بدون ارسال از گفت‌وگوی نوبت خارج می‌شوید؟",
          showCancelButton: true,
          confirmButtonText: "خروج بدون ذخیره",
          cancelButtonText: "ادامه ویرایش",
          confirmButtonColor: "#dc2626"
        }).then(result => { if (result.isConfirmed) this.resetDoctorNoteModal(); });
        return;
      }
      this.resetDoctorNoteModal();
    },

    resetDoctorNoteModal() {
      this.doctorNoteModalOpen = false;
      this.stopDoctorNoteRecorder(true);
      this.activeDoctorNoteRow = null;
      this.doctorNoteDraft = "";
      this.doctorNoteMessages = [];
    },

    appointmentNoteKey(row = this.activeDoctorNoteRow) {
      const day = this.days.find(item => (item.rows || []).includes(row));
      return [this.months[this.currentMonth] || '', day?.dayNum || '', row?.fileNumber || row?.phone || '', row?.time || ''].join('|');
    },

    async loadDoctorNoteMessages() {
      if (!this.activeDoctorNoteRow) return;
      this.doctorNoteLoading = true;
      try {
        const { data } = await axios.get('/api/appointment-notes', { params: { appointment_key: this.appointmentNoteKey() } });
        this.doctorNoteMessages = data.messages || [];
        this.activeDoctorNoteRow.noteMessageCount = this.doctorNoteMessages.length;
        this.activeDoctorNoteRow.doctorNoteUnread = Boolean(data.has_unread_doctor_note);
        this.scrollDoctorNoteChat();
      } catch (error) {
        console.error(error);
        Swal.fire({ icon:'error', title:'خطا', text:'دریافت گفت‌وگوی نوبت انجام نشد' });
      } finally {
        this.doctorNoteLoading = false;
      }
    },

    handleDoctorNoteKeydown(event) {
      if (event.isComposing || event.key !== 'Enter' || event.shiftKey) return;
      event.preventDefault();
      if (!this.doctorNoteSending && this.doctorNoteDraft.trim()) {
        this.saveDoctorNote();
      }
    },
    appendSentDoctorNoteMessage(data, fallbackText = '') {
      this.doctorNoteMessages.push(data);
      if (fallbackText || !String(this.activeDoctorNoteRow.doctorNote || '').trim()) {
        this.activeDoctorNoteRow.doctorNote = fallbackText || data.message || '[پیوست]';
      }
      this.activeDoctorNoteRow.noteMessageCount = this.doctorNoteMessages.length;
      if (data.requires_secretary_attention && !data.secretary_seen_at) this.activeDoctorNoteRow.doctorNoteUnread = true;
      this.saveData();
      this.scrollDoctorNoteChat();
    },
    async saveDoctorNote() {
      const text = this.doctorNoteDraft.trim();
      if (!this.activeDoctorNoteRow || !text || this.doctorNoteSending) return;
      this.doctorNoteSending = true;
      try {
        const { data } = await axios.post('/api/appointment-notes', {
          appointment_key: this.appointmentNoteKey(),
          message_type: 'text', message: text
        });
        this.appendSentDoctorNoteMessage(data, text);
        this.doctorNoteDraft = '';
        this.$nextTick(() => this.$refs.doctorNoteEditor?.focus());
      } catch (error) {
        Swal.fire({ icon:'error', title:'خطا', text:error.response?.data?.message || 'ارسال یادداشت انجام نشد' });
      } finally {
        this.doctorNoteSending = false;
      }
    },

    async uploadDoctorNoteImage(event) {
      const file = event.target.files?.[0];
      event.target.value = '';
      if (!file || !this.activeDoctorNoteRow || this.doctorNoteSending) return;
      if (!String(file.type || '').startsWith('image/')) {
        Swal.fire({ icon:'warning', title:'فایل نامعتبر', text:'برای پیوست نسخه، فقط تصویر انتخاب کنید.' });
        return;
      }
      const form = new FormData();
      form.append('appointment_key', this.appointmentNoteKey());
      form.append('message_type', 'image');
      form.append('image', file);
      if (this.doctorNoteDraft.trim()) form.append('message', this.doctorNoteDraft.trim());
      this.doctorNoteSending = true;
      try {
        const { data } = await axios.post('/api/appointment-notes', form);
        this.appendSentDoctorNoteMessage(data, '[تصویر نسخه]');
        this.doctorNoteDraft = '';
      } catch (error) {
        Swal.fire({ icon:'error', title:'خطا در ارسال عکس', text:error.response?.data?.message || 'تصویر نسخه ذخیره نشد' });
      } finally {
        this.doctorNoteSending = false;
      }
    },

    async toggleDoctorNoteRecording() {
      if (this.doctorNoteRecording) { this.stopDoctorNoteRecorder(false); return; }
      if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
        Swal.fire({ icon:'warning', title:'ضبط صدا پشتیبانی نمی‌شود', text:'مرورگر یا دسترسی میکروفن را بررسی کنید.' }); return;
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        this.doctorNoteAudioStream = stream;
        this.doctorNoteAudioChunks = [];
        const recorder = new MediaRecorder(stream);
        this.doctorNoteRecorder = recorder;
        recorder.ondataavailable = event => { if (event.data.size) this.doctorNoteAudioChunks.push(event.data); };
        recorder.onstop = () => this.sendDoctorNoteAudio();
        recorder.start();
        this.doctorNoteRecording = true;
        this.doctorNoteRecordingSeconds = 0;
        this.doctorNoteRecordingTimer = setInterval(() => { this.doctorNoteRecordingSeconds += 1; }, 1000);
      } catch {
        Swal.fire({ icon:'warning', title:'دسترسی میکروفن داده نشد', text:'برای ارسال ویس، دسترسی میکروفن مرورگر را فعال کنید.' });
      }
    },

    stopDoctorNoteRecorder(cancel = false) {
      clearInterval(this.doctorNoteRecordingTimer);
      this.doctorNoteRecordingTimer = null;
      if (cancel) this.doctorNoteAudioChunks = [];
      if (this.doctorNoteRecorder?.state === 'recording') this.doctorNoteRecorder.stop();
      this.doctorNoteAudioStream?.getTracks().forEach(track => track.stop());
      this.doctorNoteAudioStream = null;
      this.doctorNoteRecording = false;
    },

    async sendDoctorNoteAudio() {
      if (!this.doctorNoteAudioChunks.length || !this.activeDoctorNoteRow) return;
      const blob = new Blob(this.doctorNoteAudioChunks, { type: this.doctorNoteRecorder?.mimeType || 'audio/webm' });
      const form = new FormData();
      form.append('appointment_key', this.appointmentNoteKey());
      form.append('message_type', 'audio');
      form.append('audio_duration', String(this.doctorNoteRecordingSeconds));
      form.append('audio', blob, `voice-${Date.now()}.webm`);
      this.doctorNoteSending = true;
      try {
        const { data } = await axios.post('/api/appointment-notes', form);
        this.appendSentDoctorNoteMessage(data, '[پیام صوتی]');
      } catch (error) {
        Swal.fire({ icon:'error', title:'خطا در ارسال ویس', text:error.response?.data?.message || 'فایل صوتی ذخیره نشد' });
      } finally {
        this.doctorNoteSending = false;
        this.doctorNoteAudioChunks = [];
        this.doctorNoteRecorder = null;
      }
    },

    scrollDoctorNoteChat() { this.$nextTick(() => { const el = this.$refs.doctorNoteChat; if (el) el.scrollTop = el.scrollHeight; }); },
    formatDoctorNoteTime(value) { return value ? new Date(value).toLocaleString('fa-IR', { dateStyle:'short', timeStyle:'short' }) : ''; },
    formatRecordingTime(seconds) { return `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}`; },
    playDoctorNoteBell() {
      const now = Date.now();
      if (now - this.doctorNoteBellLastPlayedAt < 3000) return;
      this.doctorNoteBellLastPlayedAt = now;
      try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const ctx = new AudioContextClass();
        const playTone = (frequency, start, duration) => {
          const oscillator = ctx.createOscillator();
          const gain = ctx.createGain();
          oscillator.type = 'sine';
          oscillator.frequency.value = frequency;
          gain.gain.setValueAtTime(0.0001, ctx.currentTime + start);
          gain.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + start + 0.02);
          gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + duration);
          oscillator.connect(gain);
          gain.connect(ctx.destination);
          oscillator.start(ctx.currentTime + start);
          oscillator.stop(ctx.currentTime + start + duration + 0.02);
        };
        playTone(880, 0, 0.16);
        playTone(1175, 0.18, 0.2);
        setTimeout(() => ctx.close?.(), 700);
      } catch {
        // Browser audio can be blocked before user interaction; the visual alert still works.
      }
    },

    async deleteDoctorNoteMessage(message) {
      if (!message?.can_delete || this.doctorNoteDeletingId) return;
      const result = await Swal.fire({
        icon: 'warning',
        title: 'حذف این پیام؟',
        text: message.message_type === 'audio'
          ? 'پیام صوتی و فایل آن برای همیشه حذف می‌شود.'
          : message.message_type === 'image'
            ? 'تصویر پیوست‌شده برای همیشه حذف می‌شود.'
            : 'این یادداشت برای همیشه از گفت‌وگو حذف می‌شود.',
        showCancelButton: true,
        confirmButtonText: 'بله، حذف شود',
        cancelButtonText: 'انصراف',
        confirmButtonColor: '#dc2626'
      });
      if (!result.isConfirmed) return;
      this.doctorNoteDeletingId = message.id;
      try {
        await axios.delete(`/api/appointment-notes/${message.id}`);
        this.doctorNoteMessages = this.doctorNoteMessages.filter(item => item.id !== message.id);
        this.activeDoctorNoteRow.noteMessageCount = this.doctorNoteMessages.length;
        this.activeDoctorNoteRow.doctorNoteUnread = this.doctorNoteMessages.some(item => item.requires_secretary_attention && !item.secretary_seen_at);
        if (!this.doctorNoteMessages.length) this.activeDoctorNoteRow.doctorNote = '';
        this.saveData();
      } catch (error) {
        Swal.fire({ icon:'error', title:'حذف انجام نشد', text:error.response?.data?.message || 'امکان حذف این پیام وجود ندارد.' });
      } finally {
        this.doctorNoteDeletingId = null;
      }
    },

    insertDoctorNoteText(text) {
      const editor = this.$refs.doctorNoteEditor;
      if (!editor) return;
      const start = editor.selectionStart;
      const end = editor.selectionEnd;
      const prefix = start > 0 && this.doctorNoteDraft[start - 1] !== "\n" ? "\n" : "";
      this.doctorNoteDraft = `${this.doctorNoteDraft.slice(0, start)}${prefix}${text}${this.doctorNoteDraft.slice(end)}`;
      this.$nextTick(() => {
        const cursor = start + prefix.length + text.length;
        editor.focus();
        editor.setSelectionRange(cursor, cursor);
      });
    },

    insertDoctorNoteDate() {
      this.insertDoctorNoteText(`تاریخ: ${new Date().toLocaleDateString('fa-IR')}\n`);
    },

    addRow(day) {

      day.rows.push(this.createEmptyAppointmentRow());

    },

    nextTimeForInsertedRow(row) {
      const time = String(row?.time || "").trim();
      if (!/^\d{1,2}:\d{2}$/.test(time)) return "";
      const nextMinutes = this.timeSortValue(time) + this.appointmentMinuteStep;
      if (!Number.isFinite(nextMinutes)) return "";
      return this.timeFromMinutes(nextMinutes);
    },

    insertRowAfter(day, row) {
      const index = day.rows.findIndex(item => item._rowId === row._rowId);
      const newRow = this.createEmptyAppointmentRow();
      newRow.time = this.nextTimeForInsertedRow(row);
      day.rows.splice(index >= 0 ? index + 1 : day.rows.length, 0, newRow);
      this.$nextTick(() => {
        const element = document.getElementById(`row-${newRow._rowId}`);
        element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element?.querySelector('input')?.focus();
      });
    },

    timeSortValue(value) {
      const time = String(value || "").trim();
      if (!/^\d{1,2}:\d{2}$/.test(time)) return Number.POSITIVE_INFINITY;

      const [hour, minute] = time.split(":").map(part => Number(part));
      if (Number.isNaN(hour) || Number.isNaN(minute)) return Number.POSITIVE_INFINITY;
      if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return Number.POSITIVE_INFINITY;

      return (hour * 60) + minute;
    },

    compareAppointmentRowsByTime(a, b) {
      const diff = this.timeSortValue(a.row.time) - this.timeSortValue(b.row.time);
      return diff || a.index - b.index;
    },

    sortDayRowsByTime(day) {
      if (!day?.rows?.length) return;

      day.rows = day.rows
        .map((row, index) => ({ row, index }))
        .sort(this.compareAppointmentRowsByTime)
        .map(item => item.row);
    },

    addService(row) {
      const allowedSections = this.normalizeServiceSectionIds(row?.serviceTypes);
      if (!allowedSections.length) return;
      row.services.push({
        name: "",
        sectionId: allowedSections.length === 1 ? allowedSections[0] : "",
        cc: "",
        doctor: "",
        consultant: "",
        discount: ""
        ,_lastSavedCc: 0
        ,addons: []
      });

    },

    removeService(row, i) {

      row.services.splice(i, 1);

      if (!row.services.length) {

        row.services.push({
          name: "",
          sectionId: "",
          cc: "",
          doctor: "",
          consultant: "",
          discount: ""
          ,_lastSavedCc: 0
          ,addons: []
        });

      }

    },

    initMonth() {
      this.generateMonthDays();

      this.days.forEach(day => {
        this.addRow(day);
      });
    },

    addDay() {
      const { year, month } = this.parseJalaliMonth();
      const maxDays = moment.jDaysInMonth(year, month - 1);

      if (this.days.length >= maxDays) return;

      const usedDays = this.days.map(d => Number(d.dayNum));
      let n = 1;

      while (usedDays.includes(n) && n <= maxDays) {
        n++;
      }

      if (n > maxDays) return;

      const date = moment(`${year}/${month}/${n}`, "jYYYY/jM/jD");
      const event = this.holidays[n] || null;

      const day = {
        id: this._idCounter++,
        dayNum: n,
        dateLabel: date.format("dddd jD jMMMM"),
        holidayTitle: event?.title || "",
        isHoliday: date.day() === 5 || event?.holiday === true,
        rows: [],
        collapsed: this.shouldCollapseScheduleDay(n)
      };

      day.rows = this.createRowsForAddedDay(date);
      this.sortDayRowsByTime(day);
      this.days.push(day);
      this.days.sort((a, b) => a.dayNum - b.dayNum);
    },

    async addNextDayAfter(day) {
      const { year, month } = this.parseJalaliMonth();
      const maxDays = moment.jDaysInMonth(year, month - 1);
      const nextDayNum = Number(day.dayNum) + 1;

      if (nextDayNum > maxDays) {
        await Swal.fire({
          icon: "info",
          title: "روز بعدی در این ماه وجود ندارد",
          text: "برای ادامه، ماه بعد را از پایین جدول اضافه کنید.",
          confirmButtonText: "باشه"
        });
        return;
      }

      const nextDate = moment(`${year}/${month}/${nextDayNum}`, "jYYYY/jM/jD");
      const nextDateLabel = nextDate.format("dddd jD jMMMM");
      const existingDay = this.days.find(item => Number(item.dayNum) === nextDayNum);

      if (existingDay) {
        await Swal.fire({
          icon: "warning",
          title: "این روز قبلاً اضافه شده",
          text: `${nextDateLabel} قبلاً در جدول وجود دارد؛ برای ثبت نوبت از همان روز استفاده کنید.`,
          confirmButtonText: "باشه"
        });
        return;
      }

      const newDay = this.createScheduleDay(nextDayNum, nextDate);
      newDay.rows = this.createRowsForAddedDay(nextDate);
      this.sortDayRowsByTime(newDay);
      this.days.push(newDay);
      this.days.sort((a, b) => Number(a.dayNum) - Number(b.dayNum));
    },

    toggleDay(day) {
      day.collapsed = !day.collapsed;
    },

    toggleAllDays() {

      this.allCollapsed =
        !this.allCollapsed;

      this.days.forEach(d => {
        d.collapsed =
          this.allCollapsed;
      });

    },

    closeAllPopupsAndFilters() {

      this.activeServicePopup = null;

      this.showStatusFilter = false;
      this.showDoctorFilter = false;
      this.showConsultantFilter = false;
      this.showSourceFilter = false;
      this.showDoneFilter = false;
      this.showServiceSectionFilter = false;
      document.querySelectorAll('.service-type-picker[open]').forEach(details => { details.open = false; });
    },

    handleAppointmentOutsideClick(event) {
      const target = event.target;
      if (!(target instanceof Element)) return;

      const isInsideOpenControl = target.closest([
        '.filter-dropdown',
        '.filter-btn',
        '.service-popup',
        '.service-mini-btn',
        '.section-filter-menu',
        '.section-filter-toggle',
        '.service-type-picker'
      ].join(','));

      if (!isInsideOpenControl) {
        this.closeAllPopupsAndFilters();
      }
    },

    onServiceTypePickerToggle(event) {
      const current = event.currentTarget;
      if (!current?.open) return;
      document.querySelectorAll('.service-type-picker[open]').forEach(details => {
        if (details !== current) details.open = false;
      });
    },
    toggleServiceSectionFilter() {
      const shouldOpen = !this.showServiceSectionFilter;
      this.closeAllPopupsAndFilters();
      this.showServiceSectionFilter = shouldOpen;
    },

    searchTable() {
      const search = (this.searchQuery || "").trim().toLowerCase();

      this.highlightedRowId = null;

      if (!search) return;

      let foundRow = null;
      let foundDay = null;

      for (const day of this.days) {
        for (const row of day.rows) {

          const servicesText =
            (row.services || [])
              .map(s =>
                `${s.name || ''} ${s.doctor || ''} ${s.consultant || ''} ${(s.addons || []).map(addon => addon.name).join(' ')}`
              )
              .join(' ');

          const rowText = [
            row.lastname,
            row.gender,
            row.phone,
            row.fileNumber,
            row.time,
            row.status,
            row.source,
            row.description,
            row.doctorNote,
            row.done,
            row.amount,
            row.debt,
            row.appointmentSms,
            row.infoSms,
            servicesText
          ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

          if (rowText.includes(search)) {
            foundRow = row;
            foundDay = day;
            break;
          }
        }

        if (foundRow) break;
      }

      if (!foundRow) return;

      foundDay.collapsed = false;
      this.highlightedRowId = foundRow._rowId;

      this.$nextTick(() => {
        const el = document.getElementById('row-' + foundRow._rowId);

        if (el) {
          el.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }
      });
    },

    openSmsPanel() {},

    startResize(event, column) {
      if (event.button !== 0) return;

      event.preventDefault();
      const startX = event.clientX;
      const startWidth = Number(this.columnWidths[column] || 80);
      const minimums = {
        lastname: 110,
        gender: 52,
        phone: 96,
        fileNumber: 78,
        time: 82,
        status: 88,
        serviceType: 95,
        source: 84,
        description: 120,
        done: 90,
        amount: 165,
        paymentMethod: 95,
        paymentAccount: 100,
        paymentLink: 88,
        appointmentSms: 92,
        infoSms: 92,
        service: 70
      };

      const onMove = (moveEvent) => {
        const width = startWidth + (startX - moveEvent.clientX);
        this.columnWidths[column] = Math.min(360, Math.max(minimums[column] || 60, width));
      };
      const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      };

      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    },

    autoFitColumn(event, column) {
      const header = event.currentTarget.closest('th');
      const table = header?.closest('table');
      if (!header || !table) return;

      const index = header.cellIndex;
      const values = [header.innerText.trim()];
      table.querySelectorAll('tr.data-row').forEach((row) => {
        const cell = row.children[index];
        if (!cell) return;
        const field = cell.querySelector('input, select');
        const summary = cell.querySelector('summary');
        const button = cell.querySelector('button');
        if (field?.tagName === 'SELECT') {
          values.push(field.selectedOptions?.[0]?.text || '');
        } else {
          values.push(field?.value || summary?.innerText || button?.innerText || cell.innerText || '');
        }
      });

      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d');
      context.font = '12px Vazir, sans-serif';
      const contentWidth = Math.max(...values.map((value) => context.measureText(String(value).trim()).width));
      const minimums = { lastname: 110, gender: 52, time: 82, amount: 165, service: 70 };
      const extras = { lastname: 48, amount: 62, description: 48, serviceType: 30 };
      this.columnWidths[column] = Math.ceil(
        Math.min(280, Math.max(minimums[column] || 64, contentWidth + (extras[column] || 24)))
      );
    },

    toggleStatusFilter() {
      const shouldOpen = !this.showStatusFilter;
      this.closeAllPopupsAndFilters();
      this.showStatusFilter = shouldOpen;
    },

    toggleSourceFilter() {
      const shouldOpen = !this.showSourceFilter;
      this.closeAllPopupsAndFilters();
      this.showSourceFilter = shouldOpen;
    },

    toggleDoneFilter() {
      const shouldOpen = !this.showDoneFilter;
      this.closeAllPopupsAndFilters();
      this.showDoneFilter = shouldOpen;
    },

    rowHasAppointment(row) {
      if (!row) return false;

      const meaningfulTextFields = [
        "lastname",
        "phone",
        "fileNumber",
        "status",
        "arrivedAt",
        "description",
        "doctorNote",
        "done",
        "completedAt",
        "paymentMethod",
        "paymentAccount",
        "paymentLink",
        "paymentLinkLastSentAt",
        "referrerPhone"
      ];

      const meaningfulMoneyFields = [
        "amount",
        "debt",
        "referralScore",
        "discount",
        "originalAmount",
        "paymentLinkSentCount"
      ];

      const hasTextValue = meaningfulTextFields.some(field =>
        String(row[field] ?? "").trim() !== ""
      );

      const hasMoneyValue = meaningfulMoneyFields.some(field =>
        Math.abs(this.moneyToNumber(row[field])) > 0
      );

      const hasServiceValue = (row.services || []).some(service => {
        const hasServiceName = String(service.name ?? "").trim() !== "";
        const hasServiceCc = Number(service.cc || 0) > 0;
        const hasAddon = (service.addons || []).some(addon => String(addon.name || '').trim() || Number(addon.cc || 0) > 0);

        return hasServiceName || hasServiceCc || hasAddon;
      });

      return hasTextValue || hasMoneyValue || hasServiceValue;
    },

    rowShouldPersist(row) {
      // ردیف‌های خالیِ ساخته‌شده از ساعات کاری نیز باید بعد از بازگشت
      // به صفحه باقی بمانند، حتی اگر هنوز بیماری برایشان ثبت نشده باشد.
      return this.rowHasAppointment(row) || String(row?.time || "").trim() !== "";
    },

    addServiceAddon(service) {
      if (!service.name) return;
      if (!Array.isArray(service.addons)) service.addons = [];
      service.addons.push({ name:"", cc:"1", discount:"", _key:`addon-${Date.now()}-${Math.random()}` });
    },

    removeServiceAddon(service, index, row) {
      service.addons.splice(index, 1);
      this.updateRowAmounts(row);
    },

    serviceAddonOptions(service, currentAddon) {
      const selected = new Set((service.addons || []).filter(addon => addon !== currentAddon).map(addon => addon.name));
      return this.inventoryItems.filter(item => item.active !== false && String(item.section_id) === String(service.sectionId)).map(item => item.name).filter(name => name && name !== service.name && !selected.has(name));
    },

    sectionIdForService(serviceName) {
      if (!serviceName) return "";
      return this.inventoryItems.find(item => item.name === serviceName)?.section_id || "";
    },

    serviceSectionLabel(sectionId) {
      return this.serviceSections.find(section => String(section.id) === String(sectionId))?.name || "";
    },

    normalizeServiceSectionIds(values) {
      return (Array.isArray(values) ? values : []).map(value => {
        const direct = this.serviceSections.find(section => String(section.id) === String(value));
        if (direct) return String(direct.id);
        const byName = this.serviceSections.find(section => String(section.name).trim() === String(value).trim());
        return byName ? String(byName.id) : null;
      }).filter(Boolean);
    },

    serviceTypeSummary(row) {
      const names = this.normalizeServiceSectionIds(row?.serviceTypes)
        .map(id => this.serviceSectionLabel(id)).filter(Boolean);
      if (!names.length) return 'انتخاب چند بخش';
      return names.length <= 2 ? names.join('، ') : `${names.length} بخش انتخاب شده`;
    },

    serviceSectionOptionsForRow(row) {
      return this.normalizeServiceSectionIds(row?.serviceTypes).map(id => {
        const section = this.serviceSections.find(item => String(item.id) === String(id));
        return section?.id;
      }).filter(value => value !== undefined);
    },

    onRowServiceTypesChanged(row) {
      row.serviceTypes = this.normalizeServiceSectionIds(row.serviceTypes);
      const allowed = new Set(row.serviceTypes.map(String));
      (row.services || []).forEach(service => {
        if (!service.sectionId && allowed.size === 1) service.sectionId = [...allowed][0];
        if (service.sectionId && !allowed.has(String(service.sectionId))) {
          service.sectionId = '';
          service.name = '';
          service.doctor = '';
          service.cc = '';
          service.addons = [];
        }
        if (service.doctor && !this.doctorsForService(row, service).some(doctor => doctor.name === service.doctor)) {
          service.doctor = '';
        }
      });
      this.calculateRowAmount(row);
    },

    serviceOptionsFor(service, row = null) {
      const allowedSections = new Set(this.normalizeServiceSectionIds(row?.serviceTypes).map(String));
      return this.inventoryItems
        .filter(item => item.active !== false)
        .filter(item => allowedSections.size > 0 && allowedSections.has(String(item.section_id)))
        .filter(item => !service.sectionId || String(item.section_id) === String(service.sectionId))
        .map(item => item.name)
        .filter(Boolean);
    },

    onServiceSectionChanged(service, row) {
      this.$nextTick(() => {
        if (service.name && !this.serviceOptionsFor(service, row).includes(service.name)) {
          service.name = "";
          service.cc = "";
          service.addons = [];
        }
        this.calculateRowAmount(row);
      });
    },

    onServiceNameChanged(service, row) {
      this.$nextTick(() => {
        const sectionId = this.sectionIdForService(service.name);
        if (sectionId && String(service.sectionId || "") !== String(sectionId)) {
          service.sectionId = sectionId;
          service.addons = [];
        }
        if (!service.name) {
          service.cc = "";
          service.addons = [];
        }
        this.calculateRowAmount(row);
      });
    },

    applyServiceSectionFilter() {
      this.categoryFilterLoading = true;
      clearTimeout(this.categoryFilterTimeout);
      this.categoryFilterTimeout = setTimeout(() => { this.categoryFilterLoading = false; }, 250);
    },

    clearServiceSectionFilter() {
      this.selectedServiceSections = [];
      this.applyServiceSectionFilter();
    },

    async confirmDeleteFilledRows(count = 1, deleteWholeDay = false) {
      const result = await Swal.fire({
        icon: "warning",
        title: deleteWholeDay
          ? "حذف کامل روز"
          : count > 1 ? "حذف کلی نوبت‌ها" : "حذف ردیف نوبت",
        text: deleteWholeDay
          ? `این روز همراه با ${count} ردیف دارای نوبت یا اطلاعات حذف می‌شود. مطمئنید؟`
          : count > 1
          ? `در این بخش ${count} ردیف دارای نوبت یا اطلاعات است. مطمئنید حذف شود؟`
          : "این ردیف دارای نوبت یا اطلاعات است. مطمئنید حذف شود؟",
        showCancelButton: true,
        confirmButtonText: "بله، حذف شود",
        cancelButtonText: "انصراف",
        confirmButtonColor: "#dc2626",
        reverseButtons: true
      });

      return result.isConfirmed;
    },

    async deleteAppointmentRow(day, row) {
      const index = day.rows.findIndex(item => item._rowId === row._rowId);
      if (index === -1) return;

      if (this.rowHasAppointment(row)) {
        const confirmed = await this.confirmDeleteFilledRows(1);
        if (!confirmed) return;
      }

      day.rows.splice(index, 1);
      if (this.activeServicePopup === row._rowId) {
        this.activeServicePopup = null;
      }
      this.saveData();
    },

    async removeRow(day) {
      if (!day.rows.length) return;
      await this.deleteAppointmentRow(day, day.rows[day.rows.length - 1]);
    },

    async clearDayRows(day) {
      const filledRows = day.rows.filter(row => this.rowHasAppointment(row));
      if (filledRows.length) {
        const confirmed = await this.confirmDeleteFilledRows(filledRows.length, true);
        if (!confirmed) return;
      }

      const dayIndex = this.days.findIndex(item => item.id === day.id);
      if (dayIndex === -1) return;

      this.days.splice(dayIndex, 1);
      this.activeServicePopup = null;
      this.highlightedRowId = null;
    },

    getDayTotalAmount(day) {

      return day.rows.reduce((sum, row) => {
        return sum + this.moneyToNumber(row.amount);
      }, 0);

    },

    getDayClinicProfit(day) {

      return day.rows.reduce((sum, row) => {
        return sum + this.calculateClinicProfitRow(row);
      }, 0);

    },

    getFilteredRows(day) {
      let rows = day.rows.filter(row => {
        const statusOk =
          !this.selectedStatuses.length ||
          this.selectedStatuses.includes(row.status);

        const sourceOk =
          !this.selectedSources.length ||
          this.selectedSources.includes(row.source);

        const doneOk =
          !this.selectedDone.length ||
          this.selectedDone.includes(row.done);

        const sectionOk = !this.selectedServiceSections.length || (row.services || []).some(service => {
          const sectionId = service.sectionId || this.sectionIdForService(service.name);
          return this.selectedServiceSections.some(selected => String(selected) === String(sectionId));
        });

        return statusOk && sourceOk && doneOk && sectionOk;
      });

      rows = rows
        .map((row, index) => ({ row, index }))
        .sort(this.compareAppointmentRowsByTime)
        .map(item => item.row);

      return rows.map((r, i) => {
        r._visibleIndex = i;
        return r;
      });
    },

    statusColor(status) {
      console.log("status=", status);

      const s = (status || "").trim();

      switch (s) {
        case "وقت داده شد":
          return "st-given";
        case "آمد":
          return "st-arrived";
        case "کنسل شد":
          return "st-cancel";
        case "پاسخ نداد":
          return "st-noans";
        case "پیگیری":
          return "st-follow";
        default:
          return "";
      }
    },

doneColor(done) {
  const d = (done || "").trim();

  switch (d) {
    case "انجام شد":
      return "dn-yes";
    case "انجام نشد":
      return "dn-no";
    case "ترمیم":
      return "dn-rep";
    case "انتقال":
      return "dn-trans";
    case "مشاوره":
      return "dn-cons";
    default:
      return "";
  }
},

smsColor(val) {
  const v = (val || "").trim();

  switch (v) {
    case "انتظار":
      return "sms-wait";
    case "ارسال شد":
      return "sms-sent";
    default:
      return "";
  }
},

    hasService(row) {
      return row.services?.some(s => s.name);
    },

    toggleServicePopup(id) {
      const shouldOpen = this.activeServicePopup !== id;
      this.closeAllPopupsAndFilters();
      this.activeServicePopup = shouldOpen ? id : null;

    },

    applyReferralScore(row) {
      this.calculateReferralRewardForRow(row);
    },

    calculateReferralRewardForRow(row) {
      if (!row) return 0;
      if (!String(row.referrerPhone || '').trim()) {
        row.referralScore = '';
        row.referralCommissionType = '';
        row.referralCommissionValue = 0;
        return 0;
      }
      const rules = [];
      let total = 0;
      this.expandedServices(row).forEach(service => {
        const item = this.getServiceData(service.name);
        if (!item) return;
        const quantity = Math.max(Number(service.cc || 1), 1);
        const type = item.default_commission_type === 'fixed' ? 'fixed' : 'percent';
        const value = Number(item.default_commission_value || 0);
        const base = Number(item.amount || 0) * quantity;
        total += type === 'fixed' ? value * quantity : base * value / 100;
        rules.push({ type, value });
      });
      const types = [...new Set(rules.map(rule => rule.type))];
      const values = [...new Set(rules.map(rule => rule.value))];
      row.referralCommissionType = types.length === 1 ? types[0] : (types.length ? 'mixed' : '');
      row.referralCommissionValue = values.length === 1 ? values[0] : 0;
      row.referralScore = total > 0 ? this.formatDisplayMoney(Math.round(total)) : '';
      return total;
    },

    referralRuleLabel(row) {
      const type = row?.referralCommissionType;
      const value = Number(row?.referralCommissionValue || 0);
      if (type === 'percent') return `پاداش معرف: ${value.toLocaleString('fa-IR')} درصد از خدمات`;
      if (type === 'fixed') return `پاداش معرف: ${this.formatDisplayMoney(value)} تومان ثابت`;
      if (type === 'mixed') return 'پاداش معرف: ترکیبی از مبلغ ثابت و درصدی';
      return 'نوع پاداش از تنظیمات باشگاه مشتریان هر خدمت خوانده می‌شود.';
    },

    applyWalletBalance(row) {
      if (this.moneyToNumber(row.walletApplied) > 0) {
        row.walletApplied = '';
      } else {
        const payable = Math.max(0, this.moneyToNumber(row.originalAmount) - this.totalServiceDiscount(row));
        const amount = Math.min(payable, this.moneyToNumber(row.walletBalance));
        row.walletApplied = amount > 0 ? this.formatDisplayMoney(amount) : '';
      }
      this.calculateFinalAmount(row);
      this.saveData();
    },

    handleServiceDiscountInput(service, row) {
      const price = this.serviceLinePrice(service);
      const discount = Math.min(this.moneyToNumber(service.discount), price);
      service.discount = discount ? this.formatDisplayMoney(discount) : "";
      this.calculateFinalAmount(row);
    },

    totalServiceDiscount(row) {
      return this.expandedServices(row).reduce((total, service) =>
        total + Math.min(this.moneyToNumber(service.discount), this.serviceLinePrice(service)), 0);
    },

    calculateRowAmount(row) {
      this.updateRowAmounts(row);
    },

    serviceOutOfStock(serviceName) {
      return this.inventoryStock[serviceName] <= 0;
    },

    getAvailableDoctors() {
      return this.doctors;
    },

    doctorsForService(row, service) {
      const selectedSections = service?.sectionId
        ? [String(service.sectionId)]
        : this.normalizeServiceSectionIds(row?.serviceTypes).map(String);
      if (!selectedSections.length) return [];
      return (this.doctors || []).filter(doctor => {
        const doctorSections = (doctor.service_section_ids || []).map(String);
        return doctorSections.some(sectionId => selectedSections.includes(sectionId));
      });
    },

    removeMonth() {

      if (this.months.length <= 1)
        return;

      clearTimeout(this.saveTimeout);

      this.months.splice(this.currentMonth, 1);

      if (this.currentMonth > 0) {
        this.currentMonth--;
      }

    },

    async addMonth() {
      const next = this.nextJalaliMonth();

      if (!this.months.includes(next)) {
        this.months.push(next);
        this.months.sort();
      }

      this.generatingNewMonth = true;
      this.currentMonth = this.months.indexOf(next);

      try {
        await this.fetchMonthEvents();
        await this.fetchData();
      } finally {
        this.isFetching = false;
        this.$nextTick(() => {
          this.generatingNewMonth = false;
        });
      }
    },

    nextJalaliMonth() {
      const currentMonthText = this.months[this.currentMonth] || this.getCurrentJalaliMonth();
      const nextOfCurrent = this.shiftJalaliMonth(currentMonthText, 1);

      if (!this.months.includes(nextOfCurrent)) {
        return nextOfCurrent;
      }

      const sortedMonths = [...this.months].filter(month => /^\d{4}-\d{2}$/.test(String(month))).sort();
      const lastMonthText = sortedMonths[sortedMonths.length - 1] || currentMonthText;

      return this.shiftJalaliMonth(lastMonthText, 1);
    },

    shiftJalaliMonth(monthText, step = 1) {
      let [year, month] = String(monthText || this.getCurrentJalaliMonth()).split("-").map(Number);

      if (!Number.isFinite(year) || !Number.isFinite(month)) {
        [year, month] = this.getCurrentJalaliMonth().split("-").map(Number);
      }

      month += step;
      if (month > 12) {
        year += Math.floor((month - 1) / 12);
        month = ((month - 1) % 12) + 1;
      } else if (month < 1) {
        const yearsBack = Math.ceil(Math.abs(month - 1) / 12);
        year -= yearsBack;
        month += yearsBack * 12;
      }

      return `${year}-${String(month).padStart(2, "0")}`;
    },

    parseJalaliMonth() {

      const monthText =
        this.months[this.currentMonth] || "1405-01";

      const [year, month] =
        monthText.split("-").map(Number);

      return { year, month };
    },

    isToday(day) {
      const today = moment();
      const currentMonth = this.months[this.currentMonth] || '';
      return currentMonth === today.format('jYYYY-jMM') && Number(day?.dayNum) === Number(today.format('jD'));
    },

    shouldCollapseScheduleDay(dayNum) {
      const today = moment();
      const currentMonth = this.months[this.currentMonth] || '';
      const isRealTodayInCurrentMonth =
        currentMonth === today.format('jYYYY-jMM') &&
        Number(dayNum) === Number(today.format('jD'));

      return !isRealTodayInCurrentMonth;
    },

    getCurrentJalaliMonth() {
      return moment().format("jYYYY-jMM");
    },

    jalaliToGregorian(jy, jm, jd) {

      return {
        gy: 2026,
        gm: 1,
        gd: jd
      };

    },
  },

  watch: {

    openViewRequest: {
      immediate: true,
      deep: true,
      handler(request) {
        if (request?.view === "timeline") {
          this.appointmentView = "timeline";
          if (this.appointmentReady) this.applyOpenViewRequest(request);
        }
      }
    },

    days: {

      handler() {
        this.saveData();
      },

      deep: true

    },

    months: {

      handler(val) {

        localStorage.setItem(
          "schedule_months",
          JSON.stringify(val)
        );

      },

      deep: true
    },

    async currentMonth(val) {
      localStorage.setItem(
        "schedule_current_month",
        val
      );

      if (this.generatingNewMonth) return;

      await this.fetchMonthEvents();
      await this.fetchData();
    }

  }

};
</script>


<style>
@import '@/scss/main.scss';

.main-schedule-table tbody[data-day-number],
.timeline-day-row[data-day-number] {
  scroll-margin-top: 92px;
}

.amount-finance-cell{display:flex;align-items:center;justify-content:center;gap:6px}.amount-finance-cell .auto-amount-input{min-width:0;flex:1}.finance-chat-trigger{position:relative;width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;padding:0;border:1px solid #bfdbfe;border-radius:50%;background:#eff6ff;color:#2563eb;box-shadow:0 5px 13px rgba(37,99,235,.13);transition:.16s}.finance-chat-trigger:hover{background:#dbeafe;transform:translateY(-1px)}.finance-chat-trigger svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.finance-chat-trigger>span{position:absolute;top:-6px;left:-5px;width:18px;height:18px;display:grid;place-items:center;border:2px solid #fff;border-radius:50%;background:#dc2626;color:#fff;font-size:11px;font-weight:1000;animation:debtorPulse 1.8s infinite}.finance-chat-trigger.danger{border-color:#fca5a5;background:#fee2e2;color:#dc2626;box-shadow:0 5px 15px rgba(220,38,38,.2)}.finance-chat-trigger.credit{border-color:#86efac;background:#dcfce7;color:#15803d}
.financial-panel-overlay{position:fixed;inset:0;z-index:1000003;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58);backdrop-filter:blur(5px)}.financial-panel{width:min(620px,96vw);max-height:92vh;overflow:auto;box-sizing:border-box;padding:20px;border:1px solid rgba(255,255,255,.75);border-radius:23px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.34);direction:rtl}.financial-panel>header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px}.financial-panel header small{color:#2563eb;font-size:10px;font-weight:900}.financial-panel h3{margin:4px 0;color:#0f172a}.financial-panel header button{width:36px;height:36px;border:0;border-radius:11px;background:#f1f5f9;color:#64748b;font-size:23px}.financial-summary{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}.financial-summary article{display:grid;gap:4px;padding:13px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.financial-summary article.debt{border-color:#fecaca;background:#fff7f7}.financial-summary article.deposit{border-color:#bbf7d0;background:#f0fdf4}.financial-summary span{color:#64748b;font-size:10px;font-weight:900}.financial-summary strong{font-size:20px}.financial-summary .debt strong{color:#dc2626}.financial-summary .deposit strong{color:#15803d}.financial-summary small{color:#94a3b8;font-size:9px}.financial-panel>label,.financial-payment-grid label,.financial-check-grid label{display:grid;gap:7px;margin-top:11px;color:#334155;font-size:12px;font-weight:900}.financial-panel input,.financial-panel select{height:43px;box-sizing:border-box;padding:0 12px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;text-align:right;font-family:inherit}.financial-panel>label small{color:#64748b;font-size:9px;font-weight:600}.financial-payment-grid,.financial-check-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 10px;margin-top:6px}.financial-advanced-toggle{width:100%;height:42px;display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:13px;padding:0 12px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff;color:#2563eb;font-family:inherit;font-size:11px;font-weight:900;cursor:pointer}.financial-advanced-toggle svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.financial-advanced-toggle span{margin-left:auto}.financial-advanced-toggle b{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:9px}.financial-advanced-toggle.active{border-color:#93c5fd;background:#eff6ff}.financial-check-grid{padding:10px;margin-top:8px;border:1px dashed #bfdbfe;border-radius:13px;background:#f8fbff}.financial-patient-warning{margin-top:12px;padding:10px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;color:#92400e;font-size:10px;font-weight:900}.financial-panel>footer{display:flex;justify-content:flex-end;gap:8px;margin-top:18px}.financial-panel>footer button{height:40px;padding:0 16px;border-radius:11px;font-family:inherit;font-size:11px;font-weight:900}.financial-cancel{border:1px solid #e2e8f0;background:#f8fafc;color:#64748b}.financial-save{border:1px solid #2563eb;background:#2563eb;color:#fff;box-shadow:var(--ui-action-shadow)}@media(max-width:520px){.financial-summary,.financial-payment-grid,.financial-check-grid{grid-template-columns:1fr}}
.finance-chat-trigger.has-financial-balance{width:auto;min-width:76px;flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;gap:5px;padding:0 9px;border-radius:10px}.finance-chat-trigger.has-financial-balance svg{width:15px;height:15px}.finance-chat-trigger>em{font-size:10px;font-style:normal;font-weight:1000;white-space:nowrap}.financial-wallet-settle{width:100%;display:grid;grid-template-columns:1fr auto;align-items:center;gap:4px 12px;margin:0 0 14px;padding:12px 14px;border:1px solid #86efac;border-radius:14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#166534;font-family:inherit;text-align:right;cursor:pointer;transition:.18s ease}.financial-wallet-settle:hover{border-color:#22c55e;transform:translateY(-1px);box-shadow:0 8px 20px rgba(34,197,94,.14)}.financial-wallet-settle span{font-size:12px;font-weight:1000}.financial-wallet-settle strong{font-size:14px}.financial-wallet-settle small{grid-column:1/-1;color:#15803d;font-size:9px}.financial-wallet-settle:disabled{opacity:.55;cursor:wait;transform:none}

.appointment-view-switch {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px;
  border: 1px solid #e7edf5;
  border-radius: 16px;
  background: #f1f5f9;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
}

.appointment-view-switch button {
  min-width: 112px;
  height: 45px;
  padding: 0 18px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: #475569;
  font-family: inherit;
  font-size: 15px;
  font-weight: 1000;
  cursor: pointer;
  transition: .18s ease;
}

.appointment-view-switch button:hover {
  color: #1d4ed8;
}

.appointment-view-switch button.active {
  background: #fff;
  color: #2563eb;
  box-shadow: 0 8px 20px rgba(15, 23, 42, .10);
}

.timeline-actions {
  position: sticky;
  top: 0;
  right: 0;
  left: 0;
  z-index: 1390;
  width: 100vw;
  max-width: none;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  flex-wrap: wrap;
  padding: 12px;
  margin-right: calc(50% - 50vw);
  margin-left: calc(50% - 50vw);
  margin-bottom: 10px;
  background: #f4f7fb;
  direction: rtl;
}

.appointment-timeline {
  direction: rtl;
  display: flex;
  flex-direction: column;
  gap: 0;
  width: 100%;
  max-width: 100%;
  margin: 0 0 74px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f8fafc;
  overflow: hidden;
}

.timeline-empty-state {
  margin: 26px;
  padding: 28px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #64748b;
  text-align: center;
  font-weight: 900;
}

.timeline-day-row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  width: 100%;
  min-width: 0;
  min-height: 186px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
  overflow: hidden;
}

.timeline-day-label {
  position: sticky;
  right: 0;
  z-index: 5;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  gap: 5px;
  padding: 18px 20px;
  border-left: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
}

.timeline-day-label strong {
  font-size: 14px;
  font-weight: 1000;
}

.timeline-day-label span {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.timeline-day-label small {
  width: fit-content;
  padding: 2px 8px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 10px;
  font-weight: 1000;
}

.timeline-day-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
}

.timeline-day-actions button {
  height: 28px;
  min-width: 28px;
  padding: 0 9px;
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  background: #eff6ff;
  color: #1d4ed8;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.timeline-day-actions button:hover {
  background: #2563eb;
  color: #fff;
}

.timeline-day-actions .timeline-add-next-day-btn {
  width: 31px;
  min-width: 31px;
  padding: 0;
  border-color: #86efac;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 18px;
  font-weight: 1000;
}

.timeline-day-actions .timeline-add-next-day-btn:hover {
  background: #22c55e;
  color: #fff;
}

.timeline-day-actions button svg,
.delete-all-day-btn svg {
  width: 15px;
  height: 15px;
  display: block;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.timeline-day-actions .timeline-delete-day-btn {
  width: 28px;
  min-width: 28px;
  padding: 0;
  display: inline-grid;
  place-items: center;
  border-color: #fecaca;
  background: #fef2f2;
  color: #dc2626;
}

.timeline-day-actions .timeline-delete-day-btn:hover {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
}

.timeline-day-row.holiday-day .timeline-day-label {
  box-shadow: inset -4px 0 0 #881337;
}

.timeline-day-row.today-day .timeline-day-label {
  box-shadow: inset -4px 0 0 #2563eb;
}

.timeline-slots {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 22px 16px 24px;
  overflow-x: auto;
  overflow-y: hidden;
  scroll-snap-type: x proximity;
  background: #fff;
}

.timeline-slots::-webkit-scrollbar {
  height: 10px;
}

.timeline-slots::-webkit-scrollbar-track {
  background: #edf2f7;
  border-radius: 999px;
}

.timeline-slots::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}

.timeline-card {
  position: relative;
  flex: 0 0 148px;
  width: 148px;
  height: 154px;
  scroll-snap-align: start;
  border: 1px solid #087a34;
  border-radius: 12px;
  background: #0c8f3d;
  box-shadow: 0 6px 16px rgba(12, 143, 61, .16);
  cursor: pointer;
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.timeline-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 22px rgba(15, 23, 42, .12);
}

.timeline-delete-appointment-btn {
  position: absolute;
  z-index: 12;
  top: 8px;
  left: 8px;
  width: 27px;
  height: 27px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  opacity: .7;
  transition: .16s ease;
}

.timeline-delete-appointment-btn svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.timeline-card:hover .timeline-delete-appointment-btn { opacity: 1; }
.timeline-delete-appointment-btn:hover { border-color: transparent; background: transparent; color: #dc2626; transform: scale(1.08); }

.timeline-card.is-empty {
  border: 1px dashed #d1d5db;
  background: #f3f4f6;
  box-shadow: none;
}

.timeline-card.is-arrived {
  background: #b9f6ca;
  border-color: #86efac;
}

.timeline-card.is-follow {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.timeline-card.is-canceled {
  background: #d50000;
  border-color: #b91c1c;
}

.timeline-card.is-no-answer {
  background: #ffcdd2;
  border-color: #fca5a5;
}

.timeline-card.is-debtor {
  background: #fff7ed;
  border-color: #fed7aa;
}

.timeline-card.is-creditor {
  background: #fff7f7;
  border-color: #fca5a5;
  box-shadow: 0 6px 16px rgba(220, 38, 38, .12);
}

.timeline-card.is-highlighted {
  border-color: #f59e0b;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, .20), 0 12px 22px rgba(15, 23, 42, .12);
}

.timeline-time-chip {
  position: absolute;
  top: -13px;
  right: 50%;
  transform: translateX(50%);
  min-width: 58px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 10px;
  border-radius: 6px 6px 3px 3px;
  background: #16a34a;
  color: #fff;
  font-size: 11px;
  font-weight: 1000;
  box-shadow: 0 5px 12px rgba(22, 163, 74, .18);
}

.timeline-card.is-empty .timeline-time-chip {
  background: #e2e8f0;
  color: #94a3b8;
  box-shadow: none;
}

.timeline-card.is-arrived .timeline-time-chip {
  background: #69d98c;
  color: #14532d;
  box-shadow: none;
}

.timeline-card.is-canceled .timeline-time-chip {
  background: #b91c1c;
  color: #fff;
}

.timeline-card.is-no-answer .timeline-time-chip {
  background: #ffcdd2;
  color: #991b1b;
  box-shadow: none;
}

.timeline-card.is-follow .timeline-time-chip {
  background: #bbdefb;
  color: #1e40af;
  box-shadow: none;
}

.timeline-card-body,
.timeline-card-empty {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 3px;
  padding: 18px 10px 8px;
  text-align: center;
}

.timeline-card-body strong {
  min-height: 20px;
  max-width: 100%;
  overflow: hidden;
  color: #fff;
  font-size: 12px;
  font-weight: 1000;
  line-height: 1.65;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.timeline-card:has(.patient-avatar.level-problematic) .timeline-card-body strong {
  color: #dc2626;
}

.timeline-card-body span,
.timeline-card-body small {
  max-width: 100%;
  overflow: hidden;
  color: rgba(255, 255, 255, .9);
  font-size: 10px;
  font-weight: 800;
  line-height: 1.65;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  white-space: normal;
  text-overflow: ellipsis;
}

.timeline-card-body>span{min-height:26px}.timeline-card-body>small{min-height:16px}.timeline-avatar-hitbox{position:relative;z-index:4;width:52px;height:46px;display:grid;place-items:center;flex:0 0 46px;cursor:pointer}.timeline-avatar-hitbox .patient-avatar.has-crown::before{display:none!important}.timeline-avatar-hitbox:hover .patient-avatar{transform:scale(1.08)!important;filter:brightness(1.03)}.timeline-card-crown{position:absolute;z-index:8;top:-15px;left:13px;width:28px;height:28px;display:grid;place-items:center;color:#d4a72c;font-size:21px;font-weight:1000;line-height:1;text-shadow:0 1px 0 #fff,0 3px 7px rgba(146,64,14,.28);pointer-events:none}
.timeline-add-card{flex:0 0 148px!important;width:148px!important;background:#f3f4f6!important;border:1.5px dashed #d1d5db!important;box-shadow:none!important}.timeline-add-card:hover{border-color:#94a3b8!important;background:#e5e7eb!important;box-shadow:none!important}.timeline-add-card .timeline-time-chip{min-width:68px;background:#e2e8f0!important;color:#64748b!important;font-size:9px}.timeline-add-card .timeline-card-empty span{border-color:#d1d5db;background:#fff;color:#64748b;transition:.18s}.timeline-add-card:hover .timeline-card-empty span{transform:scale(1.06);background:#e2e8f0;color:#334155}.timeline-add-card .timeline-card-empty strong{color:#64748b;font-size:12px}.timeline-add-card:hover .timeline-card-empty strong{color:#334155}

.timeline-card-body small {
  color: rgba(255, 255, 255, .82);
  -webkit-line-clamp: 1;
}

.timeline-status-label {
  max-width: 100%;
  overflow: hidden;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(255, 255, 255, .2);
  color: #fff;
  font-size: 8px;
  font-style: normal;
  font-weight: 1000;
  line-height: 1.5;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.timeline-card.is-arrived .timeline-card-body strong,
.timeline-card.is-arrived .timeline-card-body span,
.timeline-card.is-arrived .timeline-card-body small,
.timeline-card.is-arrived .timeline-status-label,
.timeline-card.is-no-answer .timeline-card-body strong,
.timeline-card.is-no-answer .timeline-card-body span,
.timeline-card.is-no-answer .timeline-card-body small,
.timeline-card.is-no-answer .timeline-status-label,
.timeline-card.is-follow .timeline-card-body strong,
.timeline-card.is-follow .timeline-card-body span,
.timeline-card.is-follow .timeline-card-body small,
.timeline-card.is-follow .timeline-status-label,
.timeline-card.is-debtor .timeline-card-body strong,
.timeline-card.is-debtor .timeline-card-body span,
.timeline-card.is-debtor .timeline-card-body small,
.timeline-card.is-debtor .timeline-status-label,
.timeline-card.is-creditor .timeline-card-body strong,
.timeline-card.is-creditor .timeline-card-body span,
.timeline-card.is-creditor .timeline-card-body small,
.timeline-card.is-creditor .timeline-status-label {
  color: #1e293b;
}

.timeline-card.is-arrived .timeline-status-label,
.timeline-card.is-no-answer .timeline-status-label,
.timeline-card.is-follow .timeline-status-label,
.timeline-card.is-debtor .timeline-status-label,
.timeline-card.is-creditor .timeline-status-label {
  background: rgba(255, 255, 255, .58);
}

.timeline-card-empty span {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border: 1px solid #dbe3ed;
  border-radius: 50%;
  background: #f8fafc;
  color: #94a3b8;
  font-size: 19px;
  font-weight: 900;
}

.timeline-card-empty strong {
  color: #94a3b8;
  font-size: 11px;
  font-weight: 900;
}

.timeline-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15,23,42,.58);
  backdrop-filter: blur(5px);
}

.timeline-modal {
  width: min(1180px, 98vw);
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15,23,42,.38);
  direction: rtl;
}

.timeline-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 22px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.timeline-modal-header span {
  color: #2563eb;
  font-size: 12px;
  font-weight: 900;
}

.timeline-modal-header h3 {
  margin: 4px 0 0;
  color: #0f172a;
  font-size: 22px;
  font-weight: 1000;
}

.timeline-modal-header button {
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 50%;
  background: #e2e8f0;
  color: #475569;
  font-family: inherit;
  font-size: 24px;
  cursor: pointer;
}

.timeline-modal-body {
  display: grid;
  gap: 14px;
  padding: 18px;
  overflow: auto;
  background: #fff;
}

.timeline-validation-alert {
  padding: 11px 13px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fff7f7;
  color: #b91c1c;
  font-size: 12px;
  font-weight: 900;
  line-height: 1.8;
}

.timeline-form-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.timeline-form-grid.compact {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.timeline-form-grid.service-grid {
  grid-template-columns: repeat(5, minmax(0, 1fr));
  padding: 14px;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #f8fbff;
}

.timeline-service-panel {
  min-width: 0;
  padding: 14px;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #f8fbff;
}

.timeline-service-panel .service-popup-header {
  margin-bottom: 12px;
}

.timeline-service-panel .referral-section {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.timeline-service-list {
  gap: 10px;
  margin-bottom: 0;
}

.timeline-service-list .service-item {
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}

.timeline-service-list .service-main-row,
.timeline-service-list .service-addon-row {
  flex-wrap: wrap;
}

.timeline-service-list .service-main-row .service-multiselect {
  flex: 1.25 1 210px;
  min-width: 210px;
}

.timeline-service-list .service-main-row .service-select {
  flex: 0 1 120px;
  min-width: 112px;
}

.timeline-service-list .cc-input {
  flex: 0 0 118px;
}

.timeline-service-panel .pay-score-btn {
  font-size: 10px;
  font-weight: 900;
}

.timeline-modal label,
.timeline-wide-field {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 7px;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.timeline-modal input,
.timeline-modal select,
.timeline-modal textarea,
.timeline-modal-input {
  width: 100%;
  min-width: 0;
  min-height: 38px;
  box-sizing: border-box;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font-family: inherit;
  font-size: 13px;
  direction: rtl;
  text-align: right;
  outline: none;
}

.timeline-modal textarea {
  min-height: 82px;
  padding: 10px 12px;
  resize: vertical;
  text-align: right;
  line-height: 1.8;
}

.timeline-modal input:focus,
.timeline-modal select:focus,
.timeline-modal textarea:focus,
.timeline-modal-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

.timeline-field-error {
  color: #b91c1c !important;
}

.timeline-field-error input,
.timeline-field-error select,
.timeline-field-error textarea,
.timeline-field-error .timeline-modal-input,
.timeline-field-error .multiselect__tags {
  border-color: #ef4444 !important;
  background: #fff7f7 !important;
}

.timeline-error-text {
  color: #dc2626;
  font-size: 11px;
  font-weight: 900;
  line-height: 1.6;
}

.timeline-modal input:disabled {
  background: #f1f5f9;
  color: #64748b;
}

.timeline-patient-search-field{position:relative;z-index:30}.timeline-patient-results{position:absolute;z-index:200;top:calc(100% + 7px);right:0;left:0;max-height:310px;overflow:auto;padding:7px;border:1px solid #dbeafe;border-radius:14px;background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.2)}.timeline-patient-result{width:100%;display:flex;align-items:center;gap:11px;padding:9px;border:0;border-radius:11px;background:#fff;color:#0f172a;font-family:inherit;text-align:right;cursor:pointer}.timeline-patient-result:hover{background:#eff6ff}.timeline-patient-result-info{min-width:0;flex:1;display:flex;flex-direction:column;gap:3px}.timeline-patient-result-info strong{overflow:hidden;font-size:13px;font-weight:1000;text-overflow:ellipsis;white-space:nowrap}.timeline-patient-result-info small{color:#64748b;font-size:10px;font-weight:800}.timeline-patient-result-info small b{padding:0 3px;color:#cbd5e1}.timeline-patient-result em{flex:0 0 auto;padding:3px 7px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:9px;font-style:normal;font-weight:1000}.timeline-patient-result em.level-problematic{background:#fee2e2;color:#dc2626}.timeline-patient-result em.level-blue{background:#dbeafe;color:#2563eb}.timeline-patient-result em.level-gold{background:#fef3c7;color:#a16207}.timeline-patient-search-state{padding:18px;color:#64748b;font-size:12px;font-weight:800;text-align:center}
.timeline-quick-team{display:grid;grid-template-columns:1.35fr 1fr;gap:14px;padding:18px;border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(135deg,#f8fbff,#f0fdf4)}.timeline-quick-team label{min-width:0;display:flex;flex-direction:column;gap:8px;color:#334155;font-size:12px;font-weight:1000}.timeline-quick-team select{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-family:inherit}.timeline-sms-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.timeline-sms-options>label{display:flex;align-items:center;gap:11px;padding:13px 14px;border:1px solid #e2e8f0;border-radius:13px;background:#fff;cursor:pointer;transition:.18s}.timeline-sms-options>label:hover,.timeline-sms-options>label.active{border-color:#60a5fa;background:#eff6ff;box-shadow:0 5px 14px rgba(37,99,235,.08)}.timeline-sms-options input{width:18px!important;height:18px!important;flex:0 0 18px;accent-color:#2563eb}.timeline-sms-options span{display:flex;flex-direction:column;gap:3px}.timeline-sms-options b{color:#1e293b;font-size:12px}.timeline-sms-options small{color:#64748b;font-size:10px;font-weight:700}@media(max-width:700px){.timeline-quick-team,.timeline-sms-options{grid-template-columns:1fr}}
.timeline-doctor-multiselect{position:relative;z-index:80;min-height:44px;color:#1e293b;font-family:inherit;font-size:12px;direction:rtl}.timeline-doctor-multiselect .multiselect__select{right:auto;left:1px;width:38px;height:42px}.timeline-doctor-multiselect .multiselect__tags{min-height:44px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:6px 10px 5px 40px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;text-align:right}.timeline-doctor-multiselect.multiselect--active .multiselect__tags{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}.timeline-doctor-multiselect .multiselect__tag{display:inline-flex;align-items:center;gap:6px;margin:0;padding:6px 9px 6px 27px;border-radius:8px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:900;line-height:1.2}.timeline-doctor-multiselect .multiselect__tag-icon{right:auto;left:0;width:24px;border-radius:8px 0 0 8px}.timeline-doctor-multiselect .multiselect__tag-icon::after{color:#1d4ed8}.timeline-doctor-multiselect .multiselect__tag-icon:hover{background:#bfdbfe}.timeline-doctor-multiselect .multiselect__input,.timeline-doctor-multiselect .multiselect__placeholder{width:auto!important;min-width:150px!important;height:28px!important;min-height:28px!important;margin:0!important;padding:4px 2px!important;border:0!important;background:transparent!important;color:#64748b!important;font-family:inherit!important;font-size:11px!important;text-align:right!important;direction:rtl!important;box-shadow:none!important}.timeline-doctor-multiselect .multiselect__content-wrapper{top:calc(100% + 6px);max-height:230px!important;overflow:auto;border:1px solid #dbeafe;border-radius:11px;background:#fff;box-shadow:0 16px 38px rgba(15,23,42,.18);direction:rtl;text-align:right}.timeline-doctor-multiselect .multiselect__content{width:100%;padding:5px}.timeline-doctor-multiselect .multiselect__option{min-height:40px;padding:10px 12px;border-radius:8px;color:#334155;font-size:12px;font-weight:800;text-align:right}.timeline-doctor-multiselect .multiselect__option--highlight,.timeline-doctor-multiselect .multiselect__option--selected.multiselect__option--highlight{background:#eff6ff;color:#1d4ed8}.timeline-doctor-multiselect .multiselect__option--selected{background:#dbeafe;color:#1d4ed8;font-weight:1000}.timeline-doctor-multiselect .multiselect__option::after{left:10px;right:auto;background:transparent!important;color:inherit!important;font-size:10px}
.timeline-doctor-multiselect .multiselect__tags{min-height:46px!important;display:flex!important;align-items:center!important;gap:6px!important;flex-wrap:wrap!important;padding:6px 10px 6px 42px!important;overflow:visible!important}
.timeline-doctor-multiselect .multiselect__tags-wrap{display:flex!important;align-items:center!important;gap:6px!important;flex-wrap:wrap!important;min-width:0!important}
.timeline-doctor-multiselect .multiselect__tag{position:relative!important;max-width:calc(100% - 8px)!important;margin:0!important;padding:7px 10px 7px 28px!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;line-height:1.2!important}
.timeline-doctor-multiselect .multiselect__input,.timeline-doctor-multiselect .multiselect__placeholder{position:static!important;width:auto!important;min-width:0!important;max-width:100%!important;height:26px!important;min-height:26px!important;margin:0!important;padding:3px 2px!important;line-height:20px!important;text-align:right!important;direction:rtl!important;box-shadow:none!important}
.timeline-doctor-multiselect .multiselect__single{position:static!important;margin:0!important;text-align:right!important}

.timeline-wide-field {
  display: flex;
}

.timeline-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 14px 18px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.timeline-modal-footer button {
  min-width: 110px;
  height: 40px;
  border: 0;
  border-radius: 8px;
  font-family: inherit;
  font-weight: 900;
  cursor: pointer;
}

.timeline-modal-cancel {
  background: #e2e8f0;
  color: #334155;
}

.timeline-modal-save {
  background: #2563eb;
  color: #fff;
}

@media (max-width: 760px) {
  .appointment-view-switch {
    width: 100%;
  }

  .appointment-view-switch button {
    min-width: 0;
    flex: 1;
  }

  .timeline-day-row {
    grid-template-columns: 92px minmax(0, 1fr);
  }

  .timeline-day-label {
    padding: 14px 12px;
  }

  .timeline-card {
    flex-basis: 134px;
    width: 134px;
  }

  .timeline-form-grid,
  .timeline-form-grid.compact,
  .timeline-form-grid.service-grid {
    grid-template-columns: 1fr;
  }

  .timeline-service-panel .referral-section {
    grid-template-columns: 1fr;
  }

  .timeline-modal {
    max-height: 96vh;
    border-radius: 14px;
  }
}

.swal2-container {
  z-index: 1100000 !important;
}
.completion-sms-overlay{position:fixed;inset:0;z-index:1000000;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.6);backdrop-filter:blur(5px)}.completion-sms-modal{width:min(680px,96vw);overflow:hidden;border-radius:24px;background:#f8fafc;box-shadow:0 28px 80px rgba(15,23,42,.4);direction:rtl}.completion-sms-modal>header{display:flex;justify-content:space-between;align-items:flex-start;padding:22px 24px;background:linear-gradient(135deg,#eff6ff,#ecfdf5);border-bottom:1px solid #dbeafe}.completion-sms-modal header span{font-size:11px;font-weight:900;color:#2563eb}.completion-sms-modal header h3{margin:4px 0;font-size:22px;color:#0f172a}.completion-sms-modal header p{margin:0;color:#64748b;font-size:13px}.completion-sms-modal header button{width:36px;height:36px;border:0;border-radius:50%;background:#fff;color:#64748b;font-size:25px;cursor:pointer}.completion-sms-list{display:grid;gap:10px;padding:18px}.completion-sms-card{position:relative;display:flex;align-items:center;gap:12px;padding:14px;border:2px solid #e2e8f0;border-radius:15px;background:#fff;cursor:pointer;transition:.18s}.completion-sms-card:hover{border-color:#93c5fd;transform:translateY(-1px)}.completion-sms-card.sent{border-color:#4ade80;background:#f0fdf4;cursor:default}.completion-sms-card.failed{border-color:#fca5a5;background:#fff7f7}.completion-sms-card input{width:18px;height:18px;accent-color:#2563eb}.completion-sms-card-icon{font-size:25px}.completion-sms-card-content{display:flex;flex-direction:column;gap:3px;flex:1}.completion-sms-card-content strong{color:#1e293b;font-size:14px}.completion-sms-card-content small{color:#64748b}.completion-sms-card-content em{color:#15803d;font-style:normal;font-size:11px;font-weight:900}.completion-sms-card-content em.error{color:#dc2626}.completion-sms-success{width:21px;height:21px;display:grid;place-items:center;border-radius:50%;background:#22c55e;color:#fff;font-weight:900}.completion-referral-info{margin:0 18px 15px;padding:10px 12px;border-radius:10px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:800}.completion-sms-modal>footer{display:flex;justify-content:flex-end;gap:9px;padding:14px 18px;border-top:1px solid #e2e8f0;background:#fff}.completion-sms-modal footer button{padding:10px 17px;border:0;border-radius:10px;font-family:inherit;font-weight:900;cursor:pointer}.completion-sms-cancel{background:#e2e8f0;color:#475569}.completion-sms-send{background:#2563eb;color:#fff}.completion-sms-send:disabled{opacity:.55;cursor:not-allowed}
.tracking-modal-overlay{position:fixed;inset:0;z-index:1000000;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.58);backdrop-filter:blur(5px)}.tracking-modal{width:min(720px,96vw);overflow:hidden;border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.38);direction:rtl}.tracking-modal>header{display:flex;align-items:flex-start;justify-content:space-between;padding:22px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0}.tracking-modal header span{color:#2563eb;font-size:11px;font-weight:900}.tracking-modal header h3{margin:4px 0;color:#0f172a;font-size:21px}.tracking-modal header p{margin:0;color:#64748b;font-size:13px}.tracking-modal header button{width:34px;height:34px;border:0;border-radius:50%;background:#e2e8f0;color:#475569;font-size:24px;cursor:pointer}.tracking-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:18px}.tracking-grid article{min-height:92px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.tracking-grid article small{color:#64748b;font-size:12px;font-weight:800}.tracking-grid article strong{color:#0f172a;font-size:18px}.tracking-grid article.late{border-color:#fecaca;background:#fff7f7}.tracking-grid article.late strong{color:#b91c1c}.tracking-grid article.good{border-color:#bbf7d0;background:#f0fdf4}.tracking-grid article.good strong{color:#15803d}@media(max-width:700px){.tracking-grid{grid-template-columns:1fr}.tracking-modal>header{padding:18px}}
.tracking-time-value{width:fit-content;padding:0;border:0;background:transparent;color:#0f172a;font-family:inherit;font-size:18px;font-weight:1000;line-height:1.4;cursor:pointer}.tracking-time-value:hover{color:#2563eb;text-decoration:underline;text-underline-offset:4px}.tracking-edit-panel{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin:0 18px 18px;padding:14px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff}.tracking-edit-panel label{display:flex;flex-direction:column;gap:7px;color:#1e40af;font-size:12px;font-weight:900}.tracking-edit-panel input{width:150px;height:40px;border:1px solid #93c5fd;border-radius:10px;background:#fff;color:#0f172a;font-family:Tahoma,sans-serif;font-size:16px;font-weight:900;text-align:center;direction:ltr}.tracking-edit-panel div{display:flex;gap:8px}.tracking-edit-panel button{height:38px;padding:0 14px;border:0;border-radius:10px;font-family:inherit;font-weight:900;cursor:pointer}.tracking-edit-cancel{background:#dbeafe;color:#1e40af}.tracking-edit-save{background:#2563eb;color:#fff}.tracking-edit-save:disabled{opacity:.55;cursor:not-allowed}
.tracking-financial-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:0 18px 18px}.tracking-financial-grid article{min-height:82px;display:flex;flex-direction:column;justify-content:center;gap:7px;padding:13px;border:1px solid #d1fae5;border-radius:13px;background:#f0fdf4}.tracking-financial-grid small{color:#047857;font-size:11px;font-weight:900}.tracking-financial-grid strong{color:#064e3b;font-size:15px;font-weight:1000}@media(max-width:900px){.tracking-financial-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.tracking-financial-grid{grid-template-columns:1fr}}
.payment-link-overlay{position:fixed;inset:0;z-index:1000000;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.58);backdrop-filter:blur(5px)}.payment-link-modal{width:min(680px,96vw);overflow:hidden;border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.38);direction:rtl}.payment-link-modal>header{display:flex;align-items:flex-start;justify-content:space-between;padding:22px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0}.payment-link-modal header span{color:#2563eb;font-size:11px;font-weight:900}.payment-link-modal header h3{margin:4px 0;color:#0f172a;font-size:21px}.payment-link-modal header p{margin:0;color:#64748b;font-size:13px}.payment-link-modal header button{width:34px;height:34px;border:0;border-radius:50%;background:#e2e8f0;color:#475569;font-size:24px;cursor:pointer}.payment-link-body{display:grid;gap:13px;padding:18px}.payment-link-body label{display:flex;flex-direction:column;gap:7px;color:#334155;font-size:12px;font-weight:900}.payment-link-body input{direction:ltr;text-align:left;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;color:#0f172a;font-family:Tahoma,sans-serif;font-size:13px}.payment-link-meta{display:flex;gap:10px;flex-wrap:wrap;color:#475569;font-size:12px}.payment-link-meta span{padding:8px 10px;border-radius:10px;background:#f1f5f9}.payment-link-modal footer{display:flex;justify-content:flex-end;gap:9px;padding:14px 18px;border-top:1px solid #e2e8f0;background:#fff}.payment-link-modal footer button{padding:10px 16px;border:0;border-radius:10px;font-family:inherit;font-weight:900;cursor:pointer}.payment-copy-btn{background:#e2e8f0;color:#334155}.payment-send-btn{background:#2563eb;color:#fff}.payment-send-btn:disabled{opacity:.55;cursor:not-allowed}

* {
  box-sizing: border-box;
}

.time-page {
  --appointment-toolbar-height: 58px;
  position: relative;
  min-height: 320px;
  direction: rtl;
  font-family: "Vazir", sans-serif;
  padding: 10px;
  padding-bottom: 80px;
  background: transparent;
  overflow: visible;
}

.time-page.table-view-active {
  height: auto;
  min-height: 320px;
  display: block;
  padding-bottom: 80px;
  overflow: visible;
}

.appointment-patient-name {
  display: flex;
  align-items: center;
  gap: 5px;
  min-width: 0;
}

/* پیش‌نمایش ظریف عکس بیمار فقط در صفحه نوبت‌دهی */
.time-page .appointment-patient-name .patient-avatar,
.time-page .timeline-card-body .patient-avatar {
  position: relative;
  z-index: 3;
  transform-origin: center;
  transition: transform .24s cubic-bezier(.2, .8, .2, 1), box-shadow .24s ease, filter .24s ease;
  will-change: transform;
}

.main-schedule-table td:has(.appointment-patient-name),
.main-schedule-table .appointment-patient-name {
  overflow: visible !important;
}

.main-schedule-table tr:has(.patient-avatar:hover),
.main-schedule-table td:has(.patient-avatar:hover) {
  position: relative;
  z-index: 1000 !important;
  overflow: visible !important;
}

.main-schedule-table tbody tr.data-row:has(.patient-avatar:hover) > td {
  position: relative;
  z-index: 1;
  overflow: visible !important;
}

.main-schedule-table tbody tr.data-row:has(.patient-avatar:hover) > td:has(.patient-avatar:hover) {
  z-index: 1001 !important;
}

.time-page .appointment-patient-name .patient-avatar:hover {
  transform: none !important;
  filter: none;
}

.time-page .timeline-card-body .patient-avatar:hover {
  transform: scale(1.08) !important;
  filter: brightness(1.03);
}

@media (prefers-reduced-motion: reduce) {
  .time-page .patient-avatar { transition: none !important; }
}

.appointment-avatar-preview {
  position: fixed;
  z-index: 2147483000;
  width: 116px;
  height: 116px;
  padding: 5px;
  pointer-events: none;
  border: 1px solid rgba(147, 197, 253, .9);
  border-radius: 50%;
  background: rgba(255, 255, 255, .98);
  box-shadow: 0 0 0 5px rgba(37, 99, 235, .16), 0 18px 45px rgba(15, 23, 42, .35);
  backdrop-filter: blur(8px);
}

.appointment-avatar-preview img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  border-radius: 50%;
}

.avatar-preview-enter-active,
.avatar-preview-leave-active { transition: opacity .18s ease, transform .2s cubic-bezier(.2,.8,.2,1); }
.avatar-preview-enter-from,
.avatar-preview-leave-to { opacity: 0; transform: scale(.72) translateY(8px); }

.appointment-patient-name input {
  min-width: 0;
  flex: 1;
}
.appointment-patient-name input.problematic-customer-name,
.problematic-customer-name {
  color: #dc2626 !important;
  font-weight: 1000 !important;
}
.problematic-customer-row > td {
  background-color: #fff7f7;
}
.insert-appointment-row-btn{width:26px;height:26px;display:inline-grid;place-items:center;vertical-align:middle;margin-left:5px;padding:0;border:1px solid #93c5fd;border-radius:8px;background:#eff6ff;color:#2563eb;font-family:inherit;font-size:18px;font-weight:900;line-height:1;cursor:pointer;transition:.16s ease}.insert-appointment-row-btn:hover{background:#2563eb;color:#fff;border-color:#2563eb;transform:scale(1.04)}
.debtor-row,.creditor-row{box-shadow:inset -4px 0 0 #dc2626}.debtor-row>td,.creditor-row>td{background-color:#fff7f7}.debtor-row .appointment-patient-name input,.creditor-row .appointment-patient-name input{color:#b91c1c!important;background:#fee2e2!important;border:1px solid #fca5a5!important;font-weight:900}.debtor-warning-icon,.creditor-warning-icon{width:20px;height:20px;flex:0 0 20px;display:grid;place-items:center;border:2px solid #fff;border-radius:50%;background:#dc2626;color:#fff;font-size:12px;font-weight:1000;line-height:1;box-shadow:0 2px 7px rgba(220,38,38,.35);cursor:help;animation:debtorPulse 1.8s ease-in-out infinite}.creditor-warning-icon{font-size:10px}.debtor-balance-input,.creditor-balance-input{color:#b91c1c!important;background:#fee2e2!important;border:1px solid #fca5a5!important;font-weight:1000!important}@keyframes debtorPulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.25)}50%{box-shadow:0 0 0 5px rgba(220,38,38,0)}}

.appointment-patient-thumbnail,
.appointment-patient-thumbnail-placeholder {
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  border-radius: 50%;
}
.appointment-patient-thumbnail.level-blue, .appointment-patient-thumbnail-placeholder.level-blue { border:2px solid #3b82f6; }
.appointment-patient-thumbnail.level-silver, .appointment-patient-thumbnail-placeholder.level-silver { border:2px solid #94a3b8; }
.appointment-patient-thumbnail.level-gold, .appointment-patient-thumbnail-placeholder.level-gold { border:2px solid #d4a72c; }

.appointment-patient-thumbnail {
  object-fit: cover;
  border: 1px solid #e5e7eb;
}

.appointment-patient-thumbnail-placeholder {
  display: grid;
  place-items: center;
  background: #f1f5f9;
  color: #94a3b8;
  font-size: 12px;
}

.schedule-loading {
  position: absolute;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 120px;
  background: rgba(255, 255, 255, 0.82);
  backdrop-filter: blur(2px);
}

.schedule-loading-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  color: #374151;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  font-weight: 600;
}

.schedule-loading-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #d1d5db;
  border-top-color: #4a5664;
  border-radius: 50%;
  animation: schedule-loading-spin 0.75s linear infinite;
}

@keyframes schedule-loading-spin {
  to {
    transform: rotate(360deg);
  }
}

.top-actions {
  position: sticky;
  top: 0;
  right: 0;
  left: 0;
  z-index: 1400;
  width: 100vw;
  max-width: none;
  min-height: var(--appointment-toolbar-height);
  padding: 10px;
  margin-right: calc(50% - 50vw);
  margin-left: calc(50% - 50vw);
  border: 0;
  background: #f4f7fb;
  box-shadow: none;
  backdrop-filter: none;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.table-view-active .top-actions {
  position: sticky;
  top: 0;
  margin-bottom: 0;
}

.top-actions .appointment-view-switch {
  gap: 4px;
  padding: 4px;
  border-radius: 10px;
}

.top-actions .appointment-view-switch button {
  min-width: 78px;
  height: 34px;
  padding: 0 11px;
  border-radius: 8px;
  font-size: 12px;
}

.top-actions .appointment-view-switch button,
.timeline-actions .appointment-view-switch button {
  min-width: 38px;
  width: 38px;
  height: 34px;
  padding: 0;
  display: grid;
  place-items: center;
}

.appointment-view-switch button svg,
.icon-sms-btn svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* استایل کادر جستجو */
.global-search-box {
  padding: 8px 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  outline: none;
  font-family: inherit;
  width: 250px;
  transition: 0.3s;
}
.global-search-box:focus {
  border-color: #4a5664;
  box-shadow: 0 0 5px rgba(74, 86, 100, 0.3);
}

/* استایل هایلایت جستجو */
.search-highlight {
  background-color: #ff0000 !important;
  color: white !important;
  transition: background-color 0.3s;
}

.add-day-btn {
  background: #4a4a4a;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: inherit;
  position: relative;
  padding-right: 35px;
}

.add-day-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.add-day-btn::before {
  content: '+';
  position: absolute;
  right: 12px;
  font-size: 16px;
  font-weight: bold;
}

.collapse-all-btn {
  background: #4a4a4a;
  border: none;
  color: white;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.arrow-all {
  font-size: 14px;
  transition: transform 0.3s;
  display: inline-block;
}

.arrow-all.collapsed {
  transform: rotate(-90deg);
}

.table-container {
  width: calc(100% + 40px);
  position: relative;
  min-height: 0;
  margin: 0 -30px 0 -10px;
  overflow: visible;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}

.table-view-active .table-container {
  width: calc(100% + 40px);
  min-height: 0;
  margin: 0 -30px 0 -10px;
  overflow: visible;
  border: 0;
  background: transparent;
}

.icon-add-day-btn {
  width: 38px;
  height: 38px;
  min-width: 38px;
  padding: 0 !important;
  display: grid;
  place-items: center;
  font-size: 23px;
  font-weight: 500;
  line-height: 1;
}

.icon-add-day-btn::before { display: none; }

.icon-sms-btn {
  width: 40px;
  height: 38px;
  min-width: 40px;
  padding: 0 !important;
  display: grid;
  place-items: center;
}

.main-schedule-table {
  width: max-content;
  min-width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 13px;
  table-layout: fixed;
  overflow: visible;
}

.main-schedule-table thead {
  position: sticky;
  top: var(--appointment-toolbar-height);
  z-index: 1300;
  background: #f8fafc;
}

.main-schedule-table thead tr {
  position: relative;
  z-index: 1000;
}

.sticky-header {
  position: relative;
  top: auto;
  z-index: 1301;
  height: 46px;
  background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
  border: 0;
  border-left: 1px solid #dbe3ec;
  border-bottom: 2px solid #cbd5e1;
  padding: 9px 7px;
  color: #1e293b;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
  box-shadow: 0 4px 10px rgba(15, 23, 42, .06);
}

.sticky-header.filtered-header {
  background: #bbdefb !important;
}

.header-with-filter {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  position: relative;
}

.filter-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 12px;
  padding: 2px 4px;
  color: #666;
}

.filter-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  right: 4px;
  left: auto;
  background: white;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 99999;
  min-width: 120px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 250px;
  overflow-y: auto;
}

.filter-dropdown label {
  display: flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  font-size: 11px;
  white-space: nowrap;
  padding: 2px 4px;
  border-radius: 3px;
  transition: background 0.2s;
}

.filter-dropdown label:hover {
  background: #f5f5f5;
}

.filter-dropdown input[type="checkbox"] {
  width: 12px;
  height: 12px;
  cursor: pointer;
}

.day-separator-row td {
  background-color: #4a4a4a;
  padding: 0;
  border: none;
}

.day-row-content {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  height: 36px;
  color: white;
  font-weight: bold;
}

.day-btns-left {
  position: absolute;
  right: 40px;
  display: flex;
  gap: 10px;
  align-items: center;
}

.mini-btn {
  background: transparent;
  border: none;
  color: white;
  font-size: 20px;
  cursor: pointer;
  line-height: 1;
}
.description-with-doctor-note{display:flex;align-items:center;gap:5px;width:100%}.description-with-doctor-note input{min-width:0;flex:1}.doctor-note-trigger{position:relative;width:30px;height:30px;flex:0 0 30px;display:grid;place-items:center;padding:0;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#64748b;cursor:pointer;transition:.18s ease}.doctor-note-trigger:hover{transform:translateY(-1px);border-color:#60a5fa;color:#2563eb;background:#eff6ff}.doctor-note-trigger.has-note{border-color:#22c55e;background:#dcfce7;color:#15803d;box-shadow:0 0 0 2px rgba(34,197,94,.12)}.doctor-note-icon{font-size:17px;font-weight:900}.doctor-note-check{position:absolute;left:-4px;top:-5px;width:14px;height:14px;display:grid;place-items:center;border:2px solid #fff;border-radius:50%;background:#16a34a;color:#fff;font-size:8px;font-weight:900}
.doctor-note-trigger.unread-note{border-color:#dc2626!important;background:#fee2e2!important;color:#b91c1c!important;box-shadow:0 0 0 3px rgba(220,38,38,.18),0 0 18px rgba(220,38,38,.3)!important;animation:doctorNoteUnreadPulse 1s ease-in-out infinite}.doctor-note-trigger.unread-note .doctor-note-check{background:#dc2626}.doctor-note-trigger.seen-note{border-color:#86efac;background:#dcfce7;color:#15803d}@keyframes doctorNoteUnreadPulse{0%,100%{transform:scale(1);box-shadow:0 0 0 2px rgba(220,38,38,.14),0 0 8px rgba(220,38,38,.18)}50%{transform:scale(1.12);box-shadow:0 0 0 6px rgba(220,38,38,.08),0 0 22px rgba(220,38,38,.48)}}
.doctor-note-overlay{position:fixed;inset:0;z-index:1000000;display:grid;place-items:center;padding:24px;background:rgba(15,23,42,.58);backdrop-filter:blur(5px)}.doctor-note-modal{width:min(900px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.7);border-radius:24px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.35);direction:rtl}.doctor-note-modal-header{display:flex;align-items:flex-start;justify-content:space-between;padding:22px 24px 17px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);border-bottom:1px solid #e2e8f0}.doctor-note-modal-header h3{margin:3px 0 5px;color:#0f172a;font-size:22px}.doctor-note-modal-header p{margin:0;color:#64748b;font-size:13px}.doctor-note-eyebrow{color:#2563eb;font-size:11px;font-weight:900}.doctor-note-close{width:36px;height:36px;border:0;border-radius:50%;background:rgba(255,255,255,.8);color:#475569;font-size:25px;cursor:pointer}.doctor-note-toolbar{display:flex;gap:7px;flex-wrap:wrap;padding:12px 22px;border-bottom:1px solid #e2e8f0;background:#fff}.doctor-note-toolbar button{padding:7px 11px;border:1px solid #dbeafe;border-radius:8px;background:#f8fbff;color:#334155;font-family:inherit;font-size:12px;font-weight:800;cursor:pointer}.doctor-note-toolbar button:hover{border-color:#60a5fa;background:#eff6ff;color:#1d4ed8}.doctor-note-editor{width:auto;min-height:360px;margin:18px 22px;padding:18px;border:1px solid #cbd5e1;border-radius:15px;background:#fbfdff;color:#0f172a;font-family:inherit;font-size:15px;line-height:2.1;resize:vertical;outline:none}.doctor-note-editor:focus{border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.12);background:#fff}.doctor-note-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 22px;border-top:1px solid #e2e8f0;background:#f8fafc}.doctor-note-meta{display:flex;flex-direction:column;color:#475569;font-size:12px}.doctor-note-meta small{color:#94a3b8}.doctor-note-actions{display:flex;gap:8px}.doctor-note-actions button{padding:10px 15px;border:0;border-radius:9px;font-family:inherit;font-weight:900;cursor:pointer}.doctor-note-save{background:#2563eb;color:#fff}.doctor-note-cancel{background:#e2e8f0;color:#334155}.doctor-note-clear{background:#fee2e2;color:#b91c1c}@media(max-width:700px){.doctor-note-overlay{padding:8px}.doctor-note-modal{max-height:97vh;border-radius:16px}.doctor-note-editor{min-height:280px;margin:12px}.doctor-note-footer{align-items:stretch;flex-direction:column}.doctor-note-actions{justify-content:stretch}.doctor-note-actions button{flex:1}}
.doctor-note-patient-head{display:flex;align-items:center;gap:13px}.doctor-note-chat{min-height:270px;max-height:48vh;display:flex;flex-direction:column;gap:13px;padding:18px 22px;overflow:auto;background:linear-gradient(rgba(248,250,252,.94),rgba(241,245,249,.94)),radial-gradient(circle at 20% 20%,#dbeafe 0,transparent 35%)}.doctor-note-chat-empty{min-height:220px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#94a3b8}.doctor-note-chat-empty b{color:#475569}.doctor-note-message{display:flex;align-items:flex-end;gap:9px;max-width:78%;align-self:flex-start;direction:rtl}.doctor-note-author-avatar{width:36px;height:36px;flex:0 0 36px;display:grid;place-items:center;overflow:hidden;border:2px solid #fff;border-radius:50%;background:#dbeafe;color:#1d4ed8;box-shadow:0 3px 10px rgba(15,23,42,.14)}.doctor-note-author-avatar img{width:100%;height:100%;object-fit:cover}.doctor-note-bubble{min-width:170px;padding:10px 13px;border:1px solid #dbeafe;border-radius:16px 16px 5px 16px;background:#fff;box-shadow:0 5px 15px rgba(15,23,42,.07)}.doctor-note-bubble header{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:5px}.doctor-note-bubble header strong{color:#1d4ed8;font-size:11px}.doctor-note-bubble time{color:#94a3b8;font-size:9px}.doctor-note-bubble p{margin:0;color:#1e293b;font-size:13px;line-height:1.9;white-space:pre-wrap}.doctor-note-bubble audio{width:min(320px,55vw);height:38px}.doctor-note-image-link{display:grid;gap:7px;color:#1d4ed8;text-decoration:none}.doctor-note-image-link img{width:min(260px,58vw);max-height:260px;display:block;border-radius:12px;object-fit:cover;background:#e2e8f0}.doctor-note-image-link span{color:#334155;font-size:12px;font-weight:800;line-height:1.8}.doctor-note-composer{display:grid;grid-template-columns:minmax(0,1fr) auto auto auto;align-items:end;gap:9px;padding:13px 18px;border-top:1px solid #e2e8f0;background:#fff}.doctor-note-composer textarea{min-height:54px;max-height:130px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:13px;font-family:inherit;resize:vertical;outline:none}.doctor-note-composer textarea:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.12)}.doctor-note-composer button,.doctor-note-image-upload{height:42px;display:inline-grid;place-items:center;padding:0 14px;border:0;border-radius:11px;font-family:inherit;font-weight:900;cursor:pointer}.doctor-note-image-upload{width:44px;padding:0;background:#eff6ff;color:#1d4ed8}.doctor-note-image-upload input{display:none}.doctor-note-image-upload svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.doctor-note-image-upload.disabled{opacity:.5;cursor:not-allowed}.doctor-note-record{background:#f1f5f9;color:#475569}.doctor-note-record.recording{background:#fee2e2;color:#dc2626;animation:doctorVoicePulse 1.2s infinite}.doctor-note-send{background:#2563eb;color:#fff}.doctor-note-composer button:disabled{opacity:.5;cursor:not-allowed}@keyframes doctorVoicePulse{50%{box-shadow:0 0 0 6px rgba(239,68,68,.12)}}@media(max-width:700px){.doctor-note-message{max-width:92%}.doctor-note-composer{grid-template-columns:1fr auto auto}.doctor-note-composer textarea{grid-column:1/-1}.doctor-note-patient-head .patient-avatar{--avatar-size:48px!important}}
.doctor-note-message.own{align-self:flex-end}.doctor-note-message.own .doctor-note-bubble{border-color:#bfdbfe;background:#eff6ff;border-radius:16px 16px 16px 5px}.doctor-note-message-meta{display:flex;align-items:center;gap:7px}.doctor-note-delete{width:25px;height:25px;display:grid;place-items:center;padding:0!important;border:0;border-radius:7px;background:transparent;color:#94a3b8;font-size:12px;cursor:pointer}.doctor-note-delete:hover{background:#fee2e2;color:#dc2626}.doctor-note-delete:disabled{opacity:.45;cursor:wait}
.section-filter{position:relative}.section-filter-toggle{height:38px;display:flex;align-items:center;gap:8px;padding:0 13px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#334155;font-family:inherit;font-weight:800;cursor:pointer}.section-filter-toggle.active{color:#1d4ed8;border-color:#60a5fa;background:#eff6ff}.section-filter-toggle span{display:grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#2563eb;color:#fff;font-size:11px}.section-filter-menu{position:absolute;right:0;top:44px;z-index:1000;width:250px;max-height:330px;overflow:auto;padding:10px;background:#fff;border:1px solid #dbeafe;border-radius:12px;box-shadow:0 14px 35px rgba(15,23,42,.18)}.section-filter-title{padding:4px 5px 9px;font-size:12px;font-weight:900;color:#334155;border-bottom:1px solid #eef2f7;margin-bottom:5px}.section-filter-menu label{display:flex;align-items:center;gap:8px;padding:8px 6px;border-radius:7px;cursor:pointer;font-size:13px}.section-filter-menu label:hover{background:#f8fafc}.section-filter-menu input{width:16px;height:16px;accent-color:#2563eb}.section-filter-empty{padding:14px 6px;color:#94a3b8;font-size:12px}.section-filter-clear{width:100%;margin-top:6px;padding:8px;border:0;border-radius:7px;background:#fee2e2;color:#b91c1c;font-family:inherit;font-weight:800;cursor:pointer}

.delete-all-day-btn {
  width: 24px;
  min-width: 24px;
  height: 24px;
  display: inline-grid;
  place-items: center;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
  box-shadow: none;
}

.delete-all-day-btn:hover {
  background: transparent;
  color: #fff;
  opacity: .72;
}

.collapse-btn {
  background: transparent;
  border: none;
  color: white;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
}

.arrow {
  font-size: 12px;
  transition: transform 0.3s;
  display: inline-block;
}

.arrow.collapsed {
  transform: rotate(-90deg);
}

.main-schedule-table td {
  height: 38px;
  border: 0;
  border-left: 1px solid #edf1f5;
  border-bottom: 1px solid #e5e7eb;
  padding: 0;
  text-align: center;
  position: relative;
  vertical-align: middle;
  overflow: visible;
}

.main-schedule-table tbody tr.data-row:hover > td {
  background-color: #f0f7ff !important;
}

.main-schedule-table th,
.main-schedule-table td {
  box-sizing: border-box;
}

.main-schedule-table th,
.main-schedule-table td,
.main-schedule-table th > *,
.main-schedule-table td > * {
  text-align: center !important;
}

.main-schedule-table .appointment-patient-name,
.main-schedule-table .header-with-filter,
.main-schedule-table .money-input-wrap {
  justify-content: center;
}

.search-collapse-btn {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  padding: 0;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  background: #f8fafc;
  color: #475569;
}

.search-collapse-btn:hover {
  border-color: #94a3b8;
  background: #eef2f7;
  color: #1e293b;
}

.main-schedule-table .service-multiselect .multiselect__tags,
.main-schedule-table .service-multiselect .multiselect__input,
.main-schedule-table .service-multiselect .multiselect__single,
.main-schedule-table .service-type-picker summary {
  text-align: center !important;
}

.main-schedule-table input,
.main-schedule-table select {
  width: 100%;
  height: 36px;
  min-width: 0;
  padding: 0 4px;
  border: none;
  outline: none;
  background: transparent;
  text-align: center;
  font-family: inherit;
  color: #494949;
  font-size: 12px;
  text-overflow: ellipsis;
}

.main-schedule-table select option {
  color: #000;
  background: #fff;
}

.main-schedule-table .auto-amount-input {
  text-align: center !important;
  direction: ltr;
}

th.sticky-header.time-col {
  background: #f8f9fa !important;
  color: inherit !important;
}

.time-col {
  color: #334155 !important;
  text-align: center !important;
}

.time-col input {
  color: #1e293b !important;
  font-size: 13px !important;
  font-weight: 900;
  direction: ltr;
  letter-spacing: .3px;
}

.debt-col {
  max-width: 90px;
  min-width: 90px;
}

.no-patient-file{width:24px;height:24px;flex:0 0 24px;display:grid;place-items:center;border:2px solid #ef4444;border-radius:6px;background:#fff1f2;color:#dc2626;font-size:20px;font-weight:900;line-height:1}
.service-type-picker{position:relative;min-width:0}.service-type-picker summary{display:block;overflow:hidden;list-style:none;cursor:pointer;border:1px solid #cbd5e1;border-radius:7px;background:#fff;padding:7px 5px;color:#334155;font-size:12px;white-space:nowrap;text-overflow:ellipsis}.service-type-picker summary::-webkit-details-marker{display:none}.service-type-options{position:absolute;z-index:80;top:calc(100% + 5px);right:0;width:220px;max-height:240px;overflow:auto;padding:8px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;box-shadow:0 12px 30px rgba(15,23,42,.18)}.service-type-options label{display:flex;align-items:center;gap:7px;padding:6px;border-radius:6px;cursor:pointer;text-align:right}.service-type-options label:hover{background:#f1f5f9}.service-type-options input{width:16px!important;height:16px!important;accent-color:#2563eb}.service-type-options small{display:block;padding:8px;color:#64748b;line-height:1.7}

.payment-col {
  white-space: nowrap;
}

.payment-link-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-width: 72px;
  height: 30px;
  border: 1px solid #bfdbfe;
  border-radius: 9px;
  background: #eff6ff;
  color: #1d4ed8;
  font-family: inherit;
  font-weight: 900;
  cursor: pointer;
}

.payment-link-btn.sent {
  border-color: #86efac;
  background: #f0fdf4;
  color: #15803d;
}

.payment-link-btn span {
  display: grid;
  place-items: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: currentColor;
  color: #fff;
  font-size: 10px;
}

.service-col {
  white-space: nowrap;
}

.row-action-col {
  width: 108px;
  min-width: 108px;
  max-width: 108px;
  text-align: center !important;
}

td.row-action-col {
  white-space: nowrap;
}

.row-tracking-btn {
  width: 26px;
  height: 26px;
  display: inline-grid;
  place-items: center;
  vertical-align: middle;
  margin-left: 5px;
  border: 1px solid #cbd5e1;
  border-radius: 50%;
  background: #f8fafc;
  color: #64748b;
  cursor: pointer;
  transition: .2s;
}

.row-tracking-btn svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.row-tracking-btn:hover {
  border-color: #93c5fd;
  background: #eff6ff;
  color: #2563eb;
}

.row-delete-btn {
  width: 28px;
  height: 28px;
  display: inline-grid;
  place-items: center;
  vertical-align: middle;
  border: 0;
  border-radius: 8px;
  background: #fee2e2;
  color: #b91c1c;
  cursor: pointer;
  font-size: 18px;
  font-weight: 900;
  line-height: 1;
  font-family: inherit;
  transition: .2s;
}

.row-delete-btn:hover {
  background: #dc2626;
  color: #fff;
}

.data-row.stripe td {
  background-color: #f9f9f9;
}

/* رنگ‌بندی وضعیت */
.st-given {
  background-color: #0c8f3d !important;
  color: white !important;
}

.st-arrived {
  background-color: #b9f6ca !important;
}

.st-cancel {
  background-color: #d50000 !important;
  color: white !important;
}

.st-noans {
  background-color: #ffcdd2 !important;
}

.st-follow {
  background-color: #bbdefb !important;
}

/* رنگ‌بندی انجام کار */
.dn-yes {
  background-color: #c8e6c9 !important;
}

.dn-no {
  background-color: #ffcdd2 !important;
}

.dn-rep {
  background-color: #e1bee7 !important;
}

.dn-trans {
  background-color: #bbdefb !important;
}

.dn-cons {
  background-color: #eeeeee !important;
}

/* رنگ‌بندی پیامک */
.sms-wait {
  background-color: #ffcdd2 !important;
}

.sms-sent {
  background-color: #c8e6c9 !important;
}
/* وضعیت روی TD (خیلی مهم برای درست نمایش دادن) */
.main-schedule-table td.st-given {
  background-color: #0c8f3d !important;
  color: white !important;
}

.main-schedule-table td.st-arrived {
  background-color: #b9f6ca !important;
}

.main-schedule-table td.st-cancel {
  background-color: #d50000 !important;
  color: white !important;
}

.main-schedule-table td.st-noans {
  background-color: #ffcdd2 !important;
}

.main-schedule-table td.st-follow {
  background-color: #bbdefb !important;
}

/* ستون خدمات */
.service-cell {
  position: relative;
  overflow: visible !important;
  min-height: 30px;
  text-align: center !important;
}

.service-multiselect {
  min-width: 165px;
  direction: rtl;
  font-size: 12px;
}

.service-multiselect .multiselect__tags {
  min-height: 34px;
  border-radius: 8px;
  border: 1px solid #ddd;
      height: 13px;
    margin-top: 3px;
}

.service-multiselect .multiselect__input,
.service-multiselect .multiselect__single {
  font-size: 12px;
  direction: rtl;
  text-align: right;
}

.service-multiselect .multiselect__content-wrapper {
  direction: rtl;
  text-align: right;
  z-index: 999999;
}

.service-mini-btn {
  width: auto;
  min-width: 70px;
  height: 30px;
  gap: 5px;
  padding: 0 10px;
  border: 1px solid #bfdbfe;
  border-radius: 9px;
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  color: #1d4ed8;
  cursor: pointer;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  line-height: 1;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: 0.2s;
}

.service-mini-btn:hover {
  border-color: #60a5fa;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(37,99,235,.22);
}

.service-mini-btn.service-active {
  border-color: #86efac;
  background: linear-gradient(135deg, #dcfce7, #bbf7d0);
  color: #15803d;
}

.service-mini-icon{font-size:13px;line-height:1}.add-service-line-btn:disabled{opacity:.42;cursor:not-allowed;transform:none!important}.service-prerequisite{display:flex;align-items:flex-start;gap:11px;margin:8px 0 12px;padding:12px 14px;border:1px solid #bfdbfe;border-radius:12px;background:linear-gradient(135deg,#eff6ff,#f8fbff);text-align:right;direction:rtl}.service-prerequisite>span{width:28px;height:28px;flex:0 0 28px;display:grid;place-items:center;border-radius:9px;background:#2563eb;color:#fff;font-size:13px;font-weight:1000;box-shadow:0 5px 12px rgba(37,99,235,.2)}.service-prerequisite div{display:grid;gap:4px}.service-prerequisite strong{color:#1e3a8a;font-size:12px}.service-prerequisite p{margin:0;color:#475569;font-size:11px;line-height:1.8}.service-main-row select:disabled{background:#f1f5f9!important;color:#94a3b8!important;cursor:not-allowed}.service-multiselect.multiselect--disabled{opacity:.58;background:#f8fafc}

.service-popup {
  position: absolute;
  left: 5%;
  top: 0;
  margin-right: 10px;
  width: min(940px, 92vw);
  background: #fff;
  border: 1px solid #dcdcdc;
  border-radius: 10px;
  box-shadow: 0 8px 18px rgba(0,0,0,0.15);
  padding: 10px;
  z-index: 99999;
}

.service-popup-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-bottom:10px;
}

.service-popup-title{
  font-weight:700;
  font-size:14px;
}

.service-item {
  display: block;
  margin-bottom: 8px;
}
.service-main-row{display:flex;align-items:center;gap:6px;direction:rtl;width:100%;flex-wrap:wrap}.service-main-row .service-multiselect{flex:1.35 1 220px;min-width:205px;text-align:right;direction:rtl}.service-main-row .service-select{flex:0 1 118px;min-width:108px;text-align:right;direction:rtl}.service-main-row input{text-align:right;direction:rtl}.service-price-chip{flex:0 0 96px;min-width:96px;max-width:96px;height:32px;display:flex;align-items:center;justify-content:center;padding:0 6px;border:1px solid #bfdbfe;border-radius:7px;background:#eff6ff;color:#1d4ed8;font-size:9.5px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.addon-price-chip{min-width:95px;background:#f0fdf4;border-color:#bbf7d0;color:#15803d}
.service-discount-wrap{position:relative;flex:0 0 112px;height:32px}.service-discount-input{width:100%!important;height:32px!important;padding:0 7px 0 43px!important;border:1px solid #fecaca!important;border-radius:7px!important;background:#fff7f7!important;color:#b91c1c!important;font-family:inherit;font-size:10px!important;font-weight:900}.service-discount-wrap>span{position:absolute;left:6px;top:50%;transform:translateY(-50%);padding:2px 5px;border-radius:5px;background:#fee2e2;color:#b91c1c;font-size:8px;font-weight:1000;pointer-events:none}.addon-discount-wrap{flex-basis:112px}
.service-addon-toggle{height:32px;display:flex;align-items:center;gap:4px;padding:0 8px;border:1px solid #c4b5fd;border-radius:7px;background:#f5f3ff;color:#6d28d9;font-family:inherit;font-size:10px;font-weight:900;white-space:nowrap;cursor:pointer}.service-addon-toggle:disabled{opacity:.45;cursor:not-allowed}.service-addon-toggle.active{background:#ede9fe;border-color:#8b5cf6}.service-addon-toggle span{display:grid;place-items:center;min-width:17px;height:17px;border-radius:9px;background:#7c3aed;color:#fff}.service-addons-panel{flex:0 0 100%;width:100%;margin-top:4px;padding:10px;border:1px dashed #c4b5fd;border-radius:11px;background:#faf8ff}.service-addons-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;color:#5b21b6;font-size:11px;font-weight:900}.service-addons-title small{color:#8b5cf6;font-weight:500}.service-addon-row{display:flex;align-items:center;gap:7px;margin-top:6px}.service-addon-multiselect{flex:1;min-width:220px}.addon-cc-input{width:95px!important}.remove-addon-btn{width:29px;height:29px;border:0;border-radius:7px;background:#fee2e2;color:#dc2626;font-size:18px;cursor:pointer}.add-another-addon-btn{margin-top:8px;padding:6px 10px;border:1px solid #ddd6fe;border-radius:7px;background:#fff;color:#6d28d9;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}
.service-addons-panel{margin-top:7px;direction:rtl;text-align:right}.service-addon-row{direction:rtl}.service-addon-multiselect,.service-addon-multiselect .multiselect__input,.service-addon-multiselect .multiselect__single,.service-addon-multiselect .multiselect__placeholder{text-align:right!important;direction:rtl}.service-addon-multiselect .multiselect__tags{padding-right:9px;padding-left:35px}
.service-section-select{flex:0 0 145px!important;max-width:145px}
.service-section-multiselect{flex:0 0 124px;max-width:124px;min-width:124px;direction:rtl;text-align:right;font-size:11px}
.service-section-multiselect .multiselect__tags{height:34px;min-height:34px;padding:7px 9px 0 32px;overflow:hidden;text-align:right;direction:rtl}
.service-section-multiselect .multiselect__placeholder,.service-section-multiselect .multiselect__single,.service-section-multiselect .multiselect__input{display:block;width:100%;max-width:84px;margin:0;padding:0;overflow:hidden;color:#64748b;font-family:inherit;font-size:10.5px!important;line-height:18px;text-align:right!important;direction:rtl;white-space:nowrap;text-overflow:ellipsis}
.service-section-multiselect .multiselect__single{color:#334155;font-weight:700}
.service-section-multiselect .multiselect__select{right:auto;left:1px;width:30px;height:32px}
.service-section-multiselect .multiselect__content-wrapper{font-family:inherit;font-size:11px;text-align:right;direction:rtl}
.service-section-multiselect .multiselect__option{min-height:34px;padding:8px 10px;text-align:right;white-space:normal}

td.st-given select,
td.st-given {
  background: #fff3cd !important;
}

td.st-arrived select,
td.st-arrived {
  background: #d1e7dd !important;
}

td.st-cancel select,
td.st-cancel {
  background: #f8d7da !important;
}

td.st-noans select,
td.st-noans {
  background: #e2e3e5 !important;
}

td.st-follow select,
td.st-follow {
  background: #cff4fc !important;
}

.service-select {
  flex: 1;
  border: 1px solid #ddd !important;
  border-radius: 6px;
  background: #fff !important;
  height: 32px !important;
  min-width: 105px !important;
      padding: 0px 5px;
}

.cc-input {
  width: 85px !important;
  min-width: 85px;
  border: 1px solid #ddd !important;
  border-radius: 6px;
  background: #fff !important;
  height: 32px !important;
  padding: 0 6px;
}

.add-service-line-btn,
.remove-service-btn {
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-family: inherit;
}

.add-service-line-btn {
    width: 33%;
    background: #0077ff;
    color: #fff;
    padding: 7px 10px;
}

.remove-service-btn {
  background: #c62828;
  color: #fff;
  width: 28px;
  height: 32px;
  flex: 0 0 28px;
}

/* نوار پایین */
.fixed-bottom-bar {
  position: fixed;
  bottom: 10px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 15px;
  background: rgba(255, 255, 255, 0.95);
  padding: 8px 20px;
  border-radius: 50px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  z-index: 1000;
  max-width: 90%;
  width: auto;
}

.months-scroll-area {
  display: flex;
  gap: 8px;
      width: 100%;
    overflow: auto;
}

.month-pill {
  padding: 5px 15px;
  background: #f0f0f0;
  border-radius: 20px;
  cursor: pointer;
  font-size: 13px;
  transition: 0.3s;
  white-space: nowrap;
}

.month-pill.active {
  background: #0077ff;
  color: white;
}

.holiday-title {
  margin-right: 10px;
  color: #881337;
  font-size: 12px;
  font-weight: bold;
}

.month-action-btn {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  color: #666;
}

.service-red {
  color: #e53935;
  font-weight: bold;
}

.time-input {
  width: 100%;
  padding: 8px 12px;
  font-size: 14px;
  font-family: inherit;
  color: #333;
  background-color: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px; /* گوشه‌های گرد و مدرن */
  box-sizing: border-box;
  transition: all 0.2s ease-in-out;
  text-align: center;
  cursor: pointer;
}

/* افکت هنگام رفتن موس روی فیلد */
.time-input:hover {
  background-color: #ffffff;
  border-color: #d1d5db;
}

/* افکت هنگام کلیک روی فیلد (فوکوس) */
.time-input:focus {
  outline: none;
  background-color: #ffffff;
  border-color: #3b82f6; /* رنگ آبی مدرن برای حاشیه */
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); /* سایه ملایم آبی */
}

/* زیباتر کردن آیکون ساعت در مرورگرهای کروم و سافاری */
.time-input::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0.6;
  transition: opacity 0.2s;
}

.holiday-event{
  color:#fff;
  font-size:12px;
  font-weight:700;
  margin-right: 10px;
}

.time-input::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
}

.day-separator-row.holiday-day td {
    background: linear-gradient(135deg, #701a36, #881337) !important;
}

.day-separator-row.holiday-day .day-row-content {
  color: #fff !important;
}
.day-separator-row.today-day td{background:linear-gradient(135deg,#2563eb,#0284c7)!important}.day-separator-row.today-day .day-row-content{color:#fff!important}.today-badge{display:inline-flex;align-items:center;margin-right:8px;padding:2px 7px;border:1px solid rgba(255,255,255,.65);border-radius:999px;background:rgba(255,255,255,.18);color:#fff;font-family:inherit;font-size:9px;font-weight:900;vertical-align:middle}

.day-summary-left {
  display: flex;
  align-items: center;
  gap: 14px;
  height: 26px;
  margin-right: 2px;
  padding: 0 11px;
  border-right: 1px solid rgba(255,255,255,.28);
  border-radius: 7px;
  background: rgba(255,255,255,.08);
  font-size: 12px;
  font-weight: bold;
  color: #fff;
}

.day-summary-left span {
  white-space: nowrap;
}


.sms-send-btn{
  background: #4a4a4a;
  color: #fff;
  border: none;
  padding: 8px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-family: inherit;
  transition: 0.2s;
}

.sms-send-btn:hover{
  opacity: 0.9;
  transform: translateY(-1px);
}

.best-staff-month-card{min-height:44px;display:flex;align-items:center;gap:8px;padding:5px 9px;border:1px solid #fde68a;border-radius:13px;background:linear-gradient(135deg,#fffbeb,#fff7ed);box-shadow:0 7px 18px rgba(245,158,11,.12);white-space:nowrap}.best-staff-month-card img,.best-staff-avatar{width:34px;height:34px;flex:0 0 34px;border:2px solid #fbbf24;border-radius:50%;object-fit:cover}.best-staff-avatar{display:grid;place-items:center;background:#fef3c7;color:#92400e;font-size:13px;font-weight:900}.best-staff-copy{display:grid;gap:1px}.best-staff-copy small{color:#b45309;font-size:8px;font-weight:800}.best-staff-copy strong{color:#78350f;font-size:10px}.best-staff-month-card>b{padding:4px 7px;border-radius:8px;background:#f59e0b;color:#fff;font-size:9px}

.balance-audit-btn {
  height: 36px;
  padding: 0 14px;
  border: 1px solid #fecaca;
  border-radius: 7px;
  background: #fef2f2;
  color: #b91c1c;
  font-family: inherit;
  font-size: 12px;
  font-weight: 1000;
  cursor: pointer;
}

.balance-audit-btn:hover {
  background: #dc2626;
  color: #fff;
  border-color: #dc2626;
}

.balance-audit-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, .58);
  backdrop-filter: blur(5px);
}

.balance-audit-modal {
  width: min(920px, 96vw);
  max-height: 86vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .25);
  direction: rtl;
}

.balance-audit-modal header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  border-bottom: 1px solid #e5e7eb;
}

.balance-audit-modal header span {
  color: #b91c1c;
  font-size: 12px;
  font-weight: 900;
}

.balance-audit-modal header h3 {
  margin: 4px 0 0;
  color: #111827;
  font-size: 18px;
}

.balance-audit-modal header button {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  font-size: 20px;
  cursor: pointer;
}

.balance-audit-table-wrap {
  overflow: auto;
}

.balance-audit-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.balance-audit-table th,
.balance-audit-table td {
  padding: 11px 10px;
  border-bottom: 1px solid #eef2f7;
  text-align: center;
  white-space: nowrap;
}

.balance-audit-table th {
  position: sticky;
  top: 0;
  background: #f8fafc;
  color: #334155;
  font-weight: 1000;
}

.balance-audit-empty {
  padding: 34px;
  color: #64748b;
  text-align: center;
  font-weight: 900;
}

.balance-audit-danger {
  color: #b91c1c;
  font-weight: 1000;
}

.day-chart-trigger {
  position: relative;
  width: 24px !important;
  height: 24px !important;
  flex: 0 0 24px;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 0 !important;
  border-radius: 0;
  background: transparent !important;
  color: #fff !important;
  font-size: 0 !important;
  box-shadow: none;
  transition: opacity .18s ease, transform .18s ease;
}

.day-chart-trigger svg {
  width: 16px;
  height: 16px;
  display: block;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.day-chart-trigger:hover {
  background: transparent !important;
  border-color: transparent !important;
  box-shadow: none;
  opacity: .72;
  transform: translateY(-1px);
}

.day-chart-trigger:active {
  transform: translateY(0);
}

.timeline-report-day-btn {
  position: relative;
  width: 28px !important;
  height: 28px !important;
  flex: 0 0 28px;
  display: inline-grid !important;
  place-items: center;
  overflow: hidden;
  border: 0 !important;
  border-radius: 0 !important;
  background: transparent !important;
  color: transparent !important;
  font-size: 0 !important;
  box-shadow: none;
}

.timeline-report-day-btn::before {
  content: "";
  width: 15px;
  height: 12px;
  border-left: 1.8px solid #64748b;
  border-bottom: 1.8px solid #64748b;
  border-radius: 0 0 0 2px;
  background:
    linear-gradient(to top, #64748b 0 100%) 3px 6px / 2px 5px no-repeat,
    linear-gradient(to top, #64748b 0 100%) 7px 3px / 2px 8px no-repeat,
    linear-gradient(to top, #64748b 0 100%) 11px 1px / 2px 10px no-repeat;
}

.timeline-report-day-btn:hover {
  background: transparent !important;
  border-color: transparent !important;
  box-shadow: none;
  opacity: .72;
  transform: translateY(-1px);
}

.timeline-report-day-btn:hover::before {
  border-color: #334155;
  background:
    linear-gradient(to top, #334155 0 100%) 3px 6px / 2px 5px no-repeat,
    linear-gradient(to top, #334155 0 100%) 7px 3px / 2px 8px no-repeat,
    linear-gradient(to top, #334155 0 100%) 11px 1px / 2px 10px no-repeat;
}

.daily-report-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, .62);
  backdrop-filter: blur(7px);
}

.daily-report-modal {
  width: min(1080px, 97vw);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(226, 232, 240, .95);
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
  direction: rtl;
}

.daily-report-modal header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid #dbeafe;
  background: linear-gradient(135deg, #f8fafc, #ecfdf5);
}

.daily-report-modal header span {
  display: inline-flex;
  width: fit-content;
  padding: 4px 9px;
  border-radius: 999px;
  background: #ccfbf1;
  color: #0f766e;
  font-size: 12px;
  font-weight: 900;
}

.daily-report-modal header h3 {
  margin: 7px 0 0;
  color: #0f172a;
  font-size: 20px;
  font-weight: 1000;
}

.daily-report-modal header button {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: #fff;
  color: #475569;
  font-size: 20px;
  cursor: pointer;
}

.daily-report-body {
  overflow: auto;
  padding: 18px;
  background: #f8fafc;
}

.daily-report-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}

.daily-report-kpis article {
  position: relative;
  overflow: hidden;
  padding: 15px;
  border: 1px solid #d1fae5;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
}

.daily-report-kpis article::after {
  content: "";
  position: absolute;
  inset: auto 0 0;
  height: 4px;
  background: linear-gradient(90deg, #14b8a6, #22c55e);
}

.daily-report-kpis span {
  display: block;
  color: #047857;
  font-size: 11px;
  font-weight: 900;
}

.daily-report-kpis strong {
  display: block;
  margin-top: 5px;
  color: #064e3b;
  font-size: 26px;
  line-height: 1;
}

.daily-report-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.daily-report-panel {
  min-width: 0;
  padding: 15px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
}

.daily-report-panel h4 {
  margin: 0 0 13px;
  color: #0f172a;
  font-size: 14px;
  font-weight: 1000;
}

.daily-chart-list {
  display: grid;
  gap: 9px;
}

.daily-chart-row {
  display: grid;
  grid-template-columns: minmax(95px, 135px) minmax(0, 1fr) 42px;
  align-items: center;
  gap: 8px;
  padding: 7px;
  border-radius: 8px;
  background: #f8fafc;
}

.daily-chart-row span {
  overflow: hidden;
  color: #334155;
  font-size: 12px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.daily-chart-row div {
  height: 12px;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}

.daily-chart-row i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #14b8a6, #22c55e);
  box-shadow: 0 0 0 1px rgba(255,255,255,.35) inset;
}

.daily-chart-row b {
  color: #047857;
  font-size: 12px;
  text-align: left;
}

.daily-report-table {
  width: 100%;
  border-collapse: collapse;
  overflow: hidden;
  border-radius: 9px;
  font-size: 12px;
}

.daily-report-table th,
.daily-report-table td {
  padding: 10px 8px;
  border-bottom: 1px solid #eef2f7;
  text-align: center;
}

.daily-report-table th {
  background: #f1f5f9;
  color: #334155;
  font-weight: 1000;
}

@media (max-width: 850px) {
  .daily-report-kpis,
  .daily-report-grid {
    grid-template-columns: 1fr;
  }
}


.resizable-th{
  position: relative;
      text-align: center !important;
}

tr.data-row td {
    text-align: center !important;
}

.resize-handle{
  position: absolute;
  left: 0;
  top: 0;
  width: 6px;
  height: 100%;
  cursor: col-resize;
  background: transparent;
  z-index: 20;
}

.resize-handle:hover{
  background: rgba(0,119,255,0.25);
}

.time-picker-input {
  width: 100%;
  height: 34px;
  border: none;
  outline: none;
  background: transparent;
  text-align: center;
  font-family: inherit;
  font-size: 13px;
  color: #fff;
  cursor: pointer;
}

.vpd-wrapper,
.vpd-container,
.vpd-main,
.time-picker-popover {
  z-index: 2147483004 !important;
}

.vpd-wrapper .vpd-container,
.time-picker-popover .vpd-container {
  z-index: 2147483005 !important;
}

.timeline-time-picker-layer {
  position: fixed !important;
  inset: 0 !important;
  z-index: 2147483006 !important;
}

.timeline-time-picker-layer .vpd-container {
  z-index: 2147483007 !important;
}

.service-popup-meta {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
    flex-direction: column;
}

.service-popup-field {
  flex: 1;
}

.service-popup-field .service-select {
  width: 100% !important;
}

.referral-section{
  display:grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap:8px;
  padding-bottom:10px;
  margin-bottom:10px;
  border-bottom:1px solid rgba(0,0,0,0.12);
}

.referral-rule-label{display:none}.wallet-payment-box{grid-column:1/-1;display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:0;border:0;background:transparent;color:#166534;font-size:11px;font-weight:800}.wallet-payment-box button{min-height:30px;padding:6px 10px;border:0;border-radius:7px;background:#16a34a;color:#fff;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}.wallet-payment-box button:disabled{opacity:.45;cursor:not-allowed}

.money-input-wrap{
  position:relative;
}

.money-input{
  padding-left:72px;
}

.money-suffix{
  position:absolute;
  left:34px;
  top:50%;
  transform:translateY(-50%);
  font-size:11px;
  color:#777;
  pointer-events:none;
}

.pay-score-btn{
  position:absolute;
  left:6px;
  top:50%;
  transform:translateY(-50%);
  width:24px;
  height:24px;
  border:0;
  border-radius:7px;
  cursor:pointer;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:13px;
}

.score-disabled{
  opacity:.8;
  cursor:not-allowed;
  background:#f5f5f5;
}

.search-highlight-row {
  background: #ffcd07 !important;
  box-shadow: inset 0 0 0 2px #f59e0b;
}

.search-highlight-row td {
  background: #ffd52e !important;
}

/* رنگ نهایی وضعیت «آمد»؛ روشن و کم‌رنگ برای خوانایی بهتر جدول */
.main-schedule-table td.st-arrived,
.main-schedule-table td.st-arrived select,
td.st-arrived,
td.st-arrived select {
  background: #ecfdf5 !important;
  color: #166534 !important;
  border-color: #bbf7d0 !important;
}

/* بدهکاری: بدون رنگ‌آمیزی ردیف یا ستون؛ فقط خود مبلغ قرمز است */
.main-schedule-table tr.debtor-row,
.main-schedule-table tr.creditor-row {
  box-shadow: none !important;
}

.main-schedule-table tr.debtor-row > td:not([class*="st-"]):not([class*="dn-"]):not([class*="sms-"]),
.main-schedule-table tr.creditor-row > td:not([class*="st-"]):not([class*="dn-"]):not([class*="sms-"]) {
  background-color: #fff !important;
}

.debtor-row .appointment-patient-name input,
.creditor-row .appointment-patient-name input {
  color: inherit !important;
  background: #fff !important;
  border-color: #e5e7eb !important;
  font-weight: inherit !important;
}

.debtor-warning-icon,
.creditor-warning-icon {
  display: grid !important;
}

.main-schedule-table .debtor-balance-input,
.main-schedule-table .creditor-balance-input {
  color: #dc2626 !important;
  background: transparent !important;
  border-color: #e5e7eb !important;
  font-weight: 1000 !important;
}

/* تنظیم نهایی تایم‌لاین: تراکم بیشتر و رنگ‌بندی دقیقاً بر اساس وضعیت جدول */
.appointment-timeline .timeline-day-row {
  grid-template-columns: 100px minmax(0, 1fr);
  min-height: 166px;
}

.appointment-timeline .timeline-day-label { padding: 14px 12px; }
.appointment-timeline .timeline-slots { gap: 8px; padding: 20px 10px 18px; }

.appointment-timeline .timeline-card,
.appointment-timeline .timeline-add-card {
  flex: 0 0 126px !important;
  width: 126px !important;
  height: 142px;
  border-radius: 10px;
}

.appointment-timeline .timeline-card-body { padding: 16px 7px 6px; gap: 2px; }
.appointment-timeline .timeline-avatar-hitbox { width: 44px; height: 40px; flex-basis: 40px; }
.appointment-timeline .timeline-avatar-hitbox .patient-avatar { width: 40px !important; height: 40px !important; }
.appointment-timeline .timeline-card-body strong { font-size: 11px; line-height: 1.5; }
.appointment-timeline .timeline-card-body span,
.appointment-timeline .timeline-card-body small { font-size: 9px; line-height: 1.45; }
.appointment-timeline .timeline-card-body > span { min-height: 22px; }
.appointment-timeline .timeline-card-body > small { min-height: 14px; }

.appointment-timeline .timeline-card.is-empty {
  border: 1px dashed #d1d5db !important;
  background: #f8fafc !important;
  box-shadow: none !important;
}

.appointment-timeline .timeline-card.is-booked {
  border-color: #86efac !important;
  background: linear-gradient(145deg, #f0fdf4, #ecfdf5) !important;
  box-shadow: inset -4px 0 0 #16a34a, 0 5px 14px rgba(22, 163, 74, .10) !important;
}

.appointment-timeline .timeline-card.is-arrived {
  border-color: #bbf7d0 !important;
  background: #ecfdf5 !important;
  box-shadow: none !important;
}

.appointment-timeline .timeline-card.is-canceled {
  border-color: #b91c1c !important;
  background: #d50000 !important;
  box-shadow: 0 5px 14px rgba(213, 0, 0, .14) !important;
}

.appointment-timeline .timeline-card.is-booked .timeline-time-chip {
  border: 1px solid #86efac;
  background: #fff !important;
  color: #166534 !important;
  box-shadow: 0 5px 12px rgba(22, 101, 52, .12);
}
.appointment-timeline .timeline-card.is-arrived .timeline-time-chip { background: #bbf7d0 !important; color: #166534 !important; }
.appointment-timeline .timeline-card.is-canceled .timeline-time-chip { background: #b91c1c !important; color: #fff !important; }
.appointment-timeline .timeline-card.is-empty .timeline-time-chip { background: #e5e7eb !important; color: #64748b !important; }

.appointment-timeline .timeline-card.is-arrived .timeline-card-body strong,
.appointment-timeline .timeline-card.is-arrived .timeline-card-body span,
.appointment-timeline .timeline-card.is-arrived .timeline-card-body small,
.appointment-timeline .timeline-card.is-arrived .timeline-status-label { color: #166534 !important; }

.appointment-timeline .timeline-card.is-booked .timeline-card-body strong {
  color: #14532d !important;
}

.appointment-timeline .timeline-card.is-booked .timeline-card-body span,
.appointment-timeline .timeline-card.is-booked .timeline-card-body small {
  color: #334155 !important;
}

.appointment-timeline .timeline-card.is-booked .timeline-status-label {
  border: 1px solid #bbf7d0;
  background: #dcfce7 !important;
  color: #166534 !important;
}

.appointment-timeline .timeline-card.is-canceled .timeline-card-body strong,
.appointment-timeline .timeline-card.is-canceled .timeline-card-body span,
.appointment-timeline .timeline-card.is-canceled .timeline-card-body small,
.appointment-timeline .timeline-card.is-canceled .timeline-status-label { color: #fff !important; }

.appointment-timeline .timeline-card.is-arrived .timeline-status-label { background: #d1fae5 !important; }
.appointment-timeline .timeline-card.is-canceled .timeline-status-label { background: rgba(255, 255, 255, .2) !important; }

.appointment-timeline .timeline-delete-appointment-btn {
  color: #64748b;
  opacity: .82;
}

.appointment-timeline .timeline-delete-appointment-btn:hover {
  color: #dc2626;
  opacity: 1;
}

.appointment-timeline .timeline-delete-appointment-btn:focus-visible {
  outline: 2px solid rgba(220, 38, 38, .25);
  outline-offset: 2px;
}

.appointment-timeline .timeline-card.is-canceled .timeline-delete-appointment-btn {
  color: rgba(255, 255, 255, .82);
}

.appointment-timeline .timeline-card.is-canceled .timeline-delete-appointment-btn:hover {
  color: #fff;
}

.timeline-actions .timeline-global-search {
  width: min(290px, 34vw);
  min-width: 210px;
  margin: 0;
}

@media (max-width: 760px) {
  .appointment-timeline .timeline-card,
  .appointment-timeline .timeline-add-card { flex-basis: 118px !important; width: 118px !important; }
  .timeline-actions .timeline-global-search { width: 100%; min-width: 0; }
}
/* Ordered and stable service-section menus */
.section-filter{z-index:1505;isolation:isolate}
.section-filter-menu{right:0;top:calc(100% + 8px);z-index:1510;width:min(310px,calc(100vw - 24px));max-height:min(420px,calc(100vh - 90px));padding:8px;overflow-x:hidden;overflow-y:auto;border:1px solid #cbd5e1;border-radius:14px;background:#fff;box-shadow:0 20px 48px rgba(15,23,42,.24);direction:rtl;text-align:right;overscroll-behavior:contain}
.section-filter-title{position:sticky;top:-8px;z-index:2;margin:-8px -8px 6px;padding:13px 14px 11px;border-bottom:1px solid #e2e8f0;background:#fff;color:#1e293b;text-align:right}
.section-filter-menu label{width:100%;min-height:40px;display:grid;grid-template-columns:20px minmax(0,1fr);align-items:center;gap:10px;margin:2px 0;padding:8px 10px;border:1px solid transparent;border-radius:10px;color:#334155;text-align:right;direction:rtl}
.section-filter-menu label:hover{border-color:#dbeafe;background:#eff6ff}
.section-filter-menu label:has(input:checked){border-color:#93c5fd;background:#eff6ff;color:#1d4ed8;font-weight:900}
.section-filter-menu label span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right}
.section-filter-menu input{width:17px!important;height:17px!important;margin:0;justify-self:center}
.section-filter-clear{position:sticky;bottom:-8px;height:38px;margin:7px -2px -2px;width:calc(100% + 4px);border-radius:9px}
.service-type-col:has(.service-type-picker[open]){z-index:2200!important}
.service-type-picker{z-index:1;width:100%;direction:rtl;text-align:right}
.service-type-picker[open]{z-index:2200}
.service-type-picker summary{position:relative;min-height:34px;display:flex;align-items:center;padding:7px 9px 7px 27px!important;text-align:right!important;direction:rtl}
.service-type-picker summary::after{content:'⌄';position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#64748b;font-size:13px;transition:.18s}
.service-type-picker[open] summary{border-color:#60a5fa;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 3px rgba(96,165,250,.12)}
.service-type-picker[open] summary::after{transform:translateY(-50%) rotate(180deg)}
.service-type-options{top:calc(100% + 7px);right:0;z-index:2210;width:min(280px,calc(100vw - 24px));max-height:300px;padding:8px;overflow-x:hidden;overflow-y:auto;border:1px solid #bfdbfe;border-radius:13px;background:#fff;box-shadow:0 20px 48px rgba(15,23,42,.26);direction:rtl;text-align:right;overscroll-behavior:contain}
.service-type-options label{width:100%;min-height:38px;display:grid;grid-template-columns:18px minmax(0,1fr);align-items:center;gap:9px;margin:2px 0;padding:7px 9px;border:1px solid transparent;border-radius:9px;text-align:right!important;direction:rtl}
.service-type-options label:hover{border-color:#dbeafe;background:#eff6ff}
.service-type-options label:has(input:checked){border-color:#93c5fd;background:#eff6ff;color:#1d4ed8;font-weight:900}
.service-type-options label span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right!important}
.service-type-options input{width:16px!important;height:16px!important;margin:0!important}
@media(max-width:600px){.section-filter-menu{position:fixed;top:70px;right:12px;left:12px;width:auto;max-height:calc(100vh - 90px)}.service-type-options{right:auto;left:0;width:min(280px,calc(100vw - 18px))}}
/* Service-section filter lives in the column header */
.service-section-header-filter{position:relative;overflow:visible!important}
.service-section-filter-dot{width:12px!important;height:12px!important;min-width:12px!important;flex:0 0 12px!important;display:grid!important;place-items:center!important;padding:0!important;border:0!important;border-radius:50%!important;background:#111827!important;color:#fff!important;box-shadow:none!important;font-size:0!important;cursor:pointer;transition:.16s ease}
.service-section-filter-dot:hover{background:#2563eb!important;box-shadow:none!important}
.service-section-filter-dot.active{width:18px!important;height:18px!important;min-width:18px!important;flex-basis:18px!important;background:#2563eb!important;box-shadow:none!important;font-size:9px!important}
.service-section-filter-dot>span{display:grid;place-items:center;width:100%;height:100%;color:#fff;font-size:9px;font-weight:1000;line-height:1}
.service-section-header-menu{top:calc(100% + 12px)!important;right:0!important;z-index:6000!important;width:min(310px,calc(100vw - 24px))!important;text-align:right!important}
.service-type-col:has(.service-section-header-menu){z-index:5900!important;overflow:visible!important}
@media(max-width:600px){.service-section-header-menu{position:fixed!important;top:70px!important;right:12px!important;left:12px!important;width:auto!important}}
/* Unified appointment toolbar icon actions */
.top-actions .collapse-all-btn,.top-actions .icon-sms-btn,.top-actions .icon-add-day-btn,.timeline-actions .icon-sms-btn,.timeline-actions .icon-add-day-btn{width:40px!important;height:38px!important;min-width:40px!important;display:grid!important;place-items:center!important;padding:0!important;border:1px solid #bfdbfe!important;border-radius:11px!important;background:#eff6ff!important;color:#2563eb!important;box-shadow:0 5px 13px rgba(37,99,235,.09)!important;transition:background-color .16s ease,border-color .16s ease,transform .16s ease,box-shadow .16s ease!important}
.top-actions .collapse-all-btn:hover,.top-actions .icon-sms-btn:hover,.top-actions .icon-add-day-btn:hover,.timeline-actions .icon-sms-btn:hover,.timeline-actions .icon-add-day-btn:hover{border-color:#60a5fa!important;background:#dbeafe!important;color:#1d4ed8!important;opacity:1!important;transform:translateY(-1px)!important;box-shadow:0 8px 17px rgba(37,99,235,.14)!important}
.top-actions .collapse-all-btn:focus-visible,.top-actions .icon-sms-btn:focus-visible,.top-actions .icon-add-day-btn:focus-visible,.timeline-actions .icon-sms-btn:focus-visible,.timeline-actions .icon-add-day-btn:focus-visible{outline:0;box-shadow:0 0 0 3px rgba(59,130,246,.2)!important}
.top-actions .collapse-all-btn .arrow-all{color:currentColor;font-size:13px;font-weight:1000;line-height:1}
.top-actions .collapse-all-btn:active,.top-actions .icon-sms-btn:active,.top-actions .icon-add-day-btn:active,.timeline-actions .icon-sms-btn:active,.timeline-actions .icon-add-day-btn:active{transform:translateY(0)!important;box-shadow:none!important}
.top-actions .icon-add-day-btn::before,.timeline-actions .icon-add-day-btn::before{content:none!important}
.appointment-timeline .timeline-card.is-empty,.appointment-timeline .timeline-add-card.is-empty{border-color:#cbd5e1!important;background:#e5e7eb!important;color:#64748b!important;box-shadow:none!important}
.appointment-timeline .timeline-card.is-empty .timeline-card-empty span,.appointment-timeline .timeline-add-card.is-empty .timeline-card-empty span{background:#cbd5e1!important;color:#475569!important}
.filter-btn{color:#111827!important}
.time-profile-overlay{position:fixed;inset:0;z-index:1000004;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.55);backdrop-filter:blur(5px);direction:rtl}.time-profile-modal{width:min(860px,96vw);max-height:88vh;display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.72);border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.34)}.time-profile-modal>header{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border-bottom:1px solid #e2e8f0;background:#f8fafc}.time-profile-head{min-width:0;display:flex;align-items:center;gap:12px}.time-profile-head div{min-width:0}.time-profile-head small{color:#2563eb;font-size:10px;font-weight:1000}.time-profile-head h3{margin:3px 0;color:#0f172a;font-size:20px}.time-profile-head p{margin:0;color:#64748b;font-size:11px;font-weight:800}.time-profile-modal>header>button{width:36px;height:36px;border:0;border-radius:11px;background:#e2e8f0;color:#475569;font-size:22px;cursor:pointer}.time-profile-loading,.time-profile-error,.time-profile-empty{min-height:170px;display:grid;place-items:center;gap:8px;color:#64748b;font-size:13px;font-weight:900}.time-profile-error{color:#b91c1c}.time-profile-body{display:grid;gap:14px;padding:16px 18px;overflow:auto}.time-profile-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.time-profile-stats article{padding:12px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.time-profile-stats article.danger{border-color:#fecaca;background:#fff7f7}.time-profile-stats span,.time-profile-notes span{display:block;margin-bottom:5px;color:#64748b;font-size:10px;font-weight:1000}.time-profile-stats strong{color:#0f172a;font-size:16px}.time-profile-stats .danger strong{color:#dc2626}.time-profile-notes{display:grid;grid-template-columns:1fr 1fr;gap:10px}.time-profile-notes article{padding:12px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.time-profile-notes p{margin:0;color:#334155;font-size:12px;line-height:1.9;white-space:pre-wrap}.time-profile-history{display:grid;gap:10px}.time-profile-history>div:first-child{display:flex;align-items:center;justify-content:space-between}.time-profile-history h4{margin:0;color:#0f172a;font-size:15px}.time-profile-history>div:first-child span{color:#64748b;font-size:11px;font-weight:900}.time-profile-history table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;font-size:11px}.time-profile-history th,.time-profile-history td{padding:9px 10px;border-bottom:1px solid #e2e8f0;text-align:center}.time-profile-history th{background:#f8fafc;color:#475569;font-weight:1000}.time-profile-history td{color:#334155}.time-profile-history tr:last-child td{border-bottom:0}.time-profile-more{height:38px;justify-self:center;padding:0 18px;border:1px solid #bfdbfe;border-radius:11px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:11px;font-weight:1000;cursor:pointer}.time-profile-more:disabled{opacity:.6;cursor:wait}@media(max-width:700px){.time-profile-stats,.time-profile-notes{grid-template-columns:1fr}.time-profile-history{overflow:auto}.time-profile-history table{min-width:640px}}
</style>
