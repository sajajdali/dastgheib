<template>
  <section class="payroll-page" dir="rtl">
    <header class="payroll-header">
      <div>
        <small>حقوق و تسویه</small>
        <h1>تسویه حقوق پزشکان و پرسنل</h1>
      </div>
      <div class="payroll-month">
        <button type="button" title="ماه قبل" @click="shiftMonth(-1)">‹</button>
        <input v-model.trim="selectedMonth" type="text" inputmode="numeric" placeholder="1405-05" @change="loadReport">
        <button type="button" title="ماه بعد" :disabled="selectedMonth >= currentJalaliMonth" @click="shiftMonth(1)">›</button>
      </div>
    </header>

    <div class="payroll-layout">
      <aside class="payroll-sidebar">
        <label class="payroll-search">
          <span>جستجوی کاربر</span>
          <input v-model.trim="search" type="search" placeholder="نام پزشک یا پرسنل">
        </label>
        <div class="payroll-segments">
          <button type="button" :class="{ active: filterType === 'all' }" @click="filterType = 'all'">همه</button>
          <button type="button" :class="{ active: filterType === 'doctor' }" @click="filterType = 'doctor'">پزشک</button>
          <button type="button" :class="{ active: filterType === 'staff' }" @click="filterType = 'staff'">پرسنل</button>
        </div>
        <div class="payroll-user-list">
          <button
            v-for="resource in filteredResources"
            :key="`${resource.type}-${resource.id}`"
            type="button"
            :class="{ active: selectedKey === `${resource.type}-${resource.id}` }"
            @click="selectResource(resource)"
          >
            <img v-if="resource.avatar_url" :src="resource.avatar_url" :alt="resource.name">
            <b v-else>{{ initial(resource.name) }}</b>
            <span>
              <strong>{{ resource.name }}</strong>
              <small>{{ resource.type === 'doctor' ? 'پزشک' : 'پرسنل' }}</small>
            </span>
          </button>
          <p v-if="!filteredResources.length" class="payroll-empty">کاربری پیدا نشد.</p>
        </div>
      </aside>

      <main class="payroll-content">
        <div v-if="loading" class="payroll-state"><i></i><span>در حال محاسبه گزارش...</span></div>
        <div v-else-if="error" class="payroll-state error">{{ error }}</div>
        <div v-else-if="!report" class="payroll-state">یک پزشک یا پرسنل را انتخاب کنید.</div>
        <template v-else>
          <section class="payroll-person">
            <div>
              <small>{{ report.resource.type === 'doctor' ? 'پزشک' : 'پرسنل' }}</small>
              <h2>{{ report.resource.name }}</h2>
            </div>
            <strong>{{ monthLabel(selectedMonth) }}</strong>
          </section>

          <section class="payroll-summary">
            <article class="total"><span>قابل پرداخت</span><strong>{{ money(report.summary.total_earned) }}</strong></article>
            <article><span>حقوق ثابت</span><strong>{{ money(report.summary.salary) }}</strong></article>
            <article><span>پورسانت خدمات</span><strong>{{ money(report.summary.service_commission) }}</strong></article>
            <article><span>پورسانت انبار</span><strong>{{ money(report.summary.inventory_commission) }}</strong></article>
            <article v-if="Number(report.summary.referral_commission || 0)"><span>پورسانت معرفی مشتری</span><strong>{{ money(report.summary.referral_commission) }}</strong></article>
            <article><span>پاداش پلکانی</span><strong>{{ money(report.summary.sales_bonus) }}</strong></article>
            <article v-if="report.resource.sales_bonus_enabled" :title="salesTargetHint">
              <span>فروش تارگت (پس از کسر مواد)</span><strong>{{ money(report.summary.target_sales) }}</strong>
            </article>
            <article><span>اضافه‌کاری</span><strong>{{ money(report.summary.overtime) }}</strong></article>
            <article><span>کسری ساعت</span><strong>{{ money(report.summary.shortage) }}</strong></article>
            <article><span>غیبت</span><strong>{{ money(report.summary.absence) }}</strong></article>
            <article><span>پاداش دستی</span><strong>{{ money(report.summary.manual_bonus) }}</strong></article>
            <article><span>کسورات دستی</span><strong>{{ money(report.summary.manual_deduction) }}</strong></article>
            <article v-if="Number(report.summary.task_impact_total || 0)"><span>تأثیر وظایف</span><strong :class="{ positive: Number(report.summary.task_impact_total) > 0, negative: Number(report.summary.task_impact_total) < 0 }">{{ money(report.summary.task_impact_total) }}</strong></article>
            <article><span>کسر مساعده</span><strong>{{ money(report.summary.advance_deduction) }}</strong></article>
          </section>

          <section v-if="(report.task_impacts || []).length" class="payroll-table-wrap" style="margin-bottom:14px">
            <h3 style="margin:0 0 10px;font-size:14px">تأثیر وظایف</h3>
            <table class="payroll-table">
              <thead>
                <tr>
                  <th>عنوان</th>
                  <th>وضعیت</th>
                  <th>تاریخ ثبت</th>
                  <th>مبلغ</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in report.task_impacts" :key="item.id">
                  <td>{{ item.title || item.reason }}</td>
                  <td>{{ item.description || '—' }}</td>
                  <td>{{ item.occurred_on || item.month }}</td>
                  <td :class="{ positive: Number(item.amount) > 0, negative: Number(item.amount) < 0 }">{{ money(item.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="payroll-tabs">
            <button type="button" :class="{ active: activeTab === 'lines' }" @click="activeTab = 'lines'">ریز محاسبات</button>
            <button type="button" :class="{ active: activeTab === 'patients' }" @click="activeTab = 'patients'">مشاهده بر اساس کاربر</button>
            <button type="button" :class="{ active: activeTab === 'attendance' }" @click="activeTab = 'attendance'">حضور و غیاب</button>
            <button type="button" :class="{ active: activeTab === 'settings' }" @click="activeTab = 'settings'">تنظیمات ذخیره‌شده</button>
          </section>

          <section v-if="activeTab === 'lines'" class="payroll-table-wrap">
            <table class="payroll-table">
              <thead>
                <tr>
                  <th>روز</th>
                  <th>نوع</th>
                  <th>شرح</th>
                  <th>خدمت/آیتم</th>
                  <th>مبلغ خام</th>
                  <th>تخفیف</th>
                  <th>اضافه مبلغ</th>
                  <th>مبنای % (سهم پرداخت)</th>
                  <th>نرخ</th>
                  <th>مبلغ</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in report.lines" :key="line.id" :class="{ edited: line.manually_edited && line.status !== 'deleted', deleted: line.status === 'deleted' }">
                  <td>{{ line.day_num ? Number(line.day_num).toLocaleString('fa-IR') : '-' }}</td>
                  <td>{{ lineSourceLabel(line) }}</td>
                  <td>
                    <span>{{ line.description || '-' }}</span>
                    <small v-if="line.status === 'deleted'" class="line-status">حذف‌شده</small>
                    <small v-else-if="line.manually_edited" class="line-status edited">ویرایش‌شده</small>
                  </td>
                  <td>{{ line.service_name || line.inventory_name || '-' }}</td>
                  <td>{{ isCommissionMoneyLine(line) ? money(line.gross_amount) : '-' }}</td>
                  <td :class="{ 'adj-discount': lineDiscount(line) > 0 }">{{ isCommissionMoneyLine(line) ? money(lineDiscount(line)) : '-' }}</td>
                  <td :class="{ 'adj-surcharge': lineSurcharge(line) > 0 }">{{ isCommissionMoneyLine(line) ? money(lineSurcharge(line)) : '-' }}</td>
                  <td>{{ money(line.commission_base) }}</td>
                  <td>{{ rateText(line) }}</td>
                  <td :class="{ positive: Number(line.amount) > 0, negative: Number(line.amount) < 0 }">{{ money(line.amount) }}</td>
                  <td>
                    <div class="line-actions">
                      <button type="button" class="info-btn" title="جزئیات محاسبه" @click="openLineDetail(line)">i</button>
                      <button type="button" class="delete-line-btn" title="حذف از تسویه" :disabled="deletingLineId === line.id || line.status === 'deleted'" @click="deleteLine(line)">×</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!report.lines.length">
                  <td colspan="11">برای این ماه ریزمحاسبه‌ای ثبت نشده است.</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-else-if="activeTab === 'patients'" class="payroll-table-wrap">
            <table class="payroll-table patient-view-table">
              <thead>
                <tr>
                  <th>ردیف</th>
                  <th>روز</th>
                  <th>نام بیمار</th>
                  <th>مبلغ پرداخت‌شده</th>
                  <th>تخفیف</th>
                  <th>تعداد خدمات</th>
                  <th>پورسانت این نیرو</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in patientRows" :key="row.key">
                  <td>{{ Number(index + 1).toLocaleString('fa-IR') }}</td>
                  <td>{{ row.day_num ? Number(row.day_num).toLocaleString('fa-IR') : '-' }}</td>
                  <td>
                    <strong>{{ row.patient_name }}</strong>
                    <small v-if="row.time" class="patient-row-meta">{{ row.time }}</small>
                  </td>
                  <td>{{ money(row.paid_amount) }}</td>
                  <td :class="{ 'adj-discount': row.discount_amount > 0 }">{{ money(row.discount_amount) }}</td>
                  <td>
                    <button
                      type="button"
                      class="service-count-btn"
                      :title="`مشاهده ${Number(row.service_count).toLocaleString('fa-IR')} خدمت`"
                      @click="openPatientDetail(row)"
                    >
                      {{ Number(row.service_count).toLocaleString('fa-IR') }}
                    </button>
                  </td>
                  <td class="positive">{{ money(row.commission_total) }}</td>
                </tr>
                <tr v-if="!patientRows.length">
                  <td colspan="7">برای این ماه بیماری با پورسانت خدمات ثبت نشده است.</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-else-if="activeTab === 'attendance'" class="payroll-table-wrap">
            <table class="payroll-table attendance-ledger-table">
              <thead>
                <tr>
                  <th>روز</th>
                  <th>نوع</th>
                  <th>شرح</th>
                  <th>ساعت/تعداد</th>
                  <th>نرخ</th>
                  <th>مبلغ</th>
                  <th>وضعیت</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in attendanceLines" :key="line.id" :class="{ edited: line.manually_edited && line.status !== 'deleted', deleted: line.status === 'deleted' }">
                  <td>{{ line.day_num ? Number(line.day_num).toLocaleString('fa-IR') : '-' }}</td>
                  <td>{{ lineType(line.earning_type) }}</td>
                  <td>{{ line.description || '-' }}</td>
                  <td>{{ Number(line.quantity || 0).toLocaleString('fa-IR') }}</td>
                  <td>{{ rateText(line) }}</td>
                  <td :class="{ positive: Number(line.amount) > 0, negative: Number(line.amount) < 0 }">{{ money(line.amount) }}</td>
                  <td>
                    <small v-if="line.status === 'deleted'" class="line-status">حذف‌شده</small>
                    <small v-else-if="line.manually_edited" class="line-status edited">ویرایش‌شده</small>
                    <small v-else>-</small>
                  </td>
                  <td>
                    <div class="line-actions">
                      <button type="button" class="info-btn" title="جزئیات محاسبه" @click="openLineDetail(line)">i</button>
                      <button type="button" class="delete-line-btn" title="حذف از تسویه" :disabled="deletingLineId === line.id || line.status === 'deleted'" @click="deleteLine(line)">×</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!attendanceLines.length">
                  <td colspan="8">برای حضور و غیاب این ماه ردیف مالی ثبت نشده است.</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-else class="payroll-settings">
            <article>
              <span>پورسانت مشتری جدید</span>
              <strong>{{ customerPercentLabel(report.resource.new_customer_bonus) }}</strong>
            </article>
            <article>
              <span>پورسانت مشتری قدیمی</span>
              <strong>{{ customerPercentLabel(report.resource.existing_customer_bonus) }}</strong>
            </article>
            <article>
              <span>مبنای پورسانت</span>
              <strong>{{ report.resource.commission_after_materials ? 'مبلغ پرداخت‌شده پس از کسر مواد' : 'مبلغ پرداخت‌شده بیمار' }}</strong>
            </article>
            <article>
              <span>نرخ اضافه‌کاری</span>
              <strong>{{ money(report.resource.overtime_hourly_rate) }}</strong>
            </article>
            <article>
              <span>کسر کسری ساعت</span>
              <strong>{{ money(report.resource.shortage_hourly_deduction) }}</strong>
            </article>
            <article>
              <span>کسر غیبت</span>
              <strong>{{ money(report.resource.absence_deduction) }}</strong>
            </article>
          </section>
          <EmployeePortalAdmin
            v-if="canManageEmployeePortal"
            :resource="report.resource"
            :month="selectedMonth"
            :user-id="currentUserId"
            @changed="loadReport"
          />
        </template>
      </main>
    </div>

    <div v-if="detailLine" class="line-detail-overlay" @click.self="closeLineDetail">
      <section class="line-detail-modal">
        <header>
          <div>
            <small>{{ lineSourceLabel(detailLine) }}</small>
            <h2>{{ editingLine ? 'ویرایش محاسبه' : (detailLine.description || 'جزئیات محاسبه') }}</h2>
          </div>
          <button type="button" @click="closeLineDetail">×</button>
        </header>
        <div class="line-detail-grid">
          <article><span>روز نوبت/کارکرد</span><strong>{{ detailLine.day_num ? Number(detailLine.day_num).toLocaleString('fa-IR') : '-' }}</strong></article>
          <article><span>نوبت</span><strong>{{ appointmentTitle(detailLine) }}</strong></article>
          <article><span>خدمات نوبت</span><strong>{{ appointmentServices(detailLine) }}</strong></article>
          <article v-if="!isAttendanceLine(detailLine)"><span>مبلغ پرداخت‌شده نوبت</span><strong>{{ money(lineAppointmentPaidTotal(detailLine)) }}</strong></article>
          <article v-if="!isAttendanceLine(detailLine)"><span>خالص کاتالوگ نوبت</span><strong>{{ appointmentNetTotal(detailLine) }}</strong></article>
          <article v-if="appointmentDiscount(detailLine) > 0" class="adj-discount"><span>تخفیف کل نوبت</span><strong>{{ money(appointmentDiscount(detailLine)) }}</strong></article>
          <article v-if="appointmentSurcharge(detailLine) > 0" class="adj-surcharge"><span>اضافه مبلغ کل نوبت</span><strong>{{ money(appointmentSurcharge(detailLine)) }}</strong></article>
          <article v-if="isAttendanceLine(detailLine)"><span>ورود / خروج</span><strong>{{ attendanceClockText(detailLine) }}</strong></article>
          <article v-if="isAttendanceLine(detailLine)"><span>ساعت کارکرد</span><strong>{{ Number(detailLine.calculation_snapshot?.worked_hours || 0).toLocaleString('fa-IR') }}</strong></article>
          <article v-if="isAttendanceLine(detailLine)"><span>اختلاف ساعت</span><strong>{{ Number(detailLine.calculation_snapshot?.diff_hours || 0).toLocaleString('fa-IR') }}</strong></article>
          <article v-if="!editingLine"><span>منبع قانون</span><strong>{{ lineSourceLabel(detailLine) }}</strong></article>
          <article v-if="!editingLine"><span>خدمت/آیتم موثر</span><strong>{{ detailLine.service_name || detailLine.inventory_name || '-' }}</strong></article>
          <article v-if="!editingLine"><span>مبلغ خام همین خط</span><strong>{{ money(detailLine.gross_amount) }}</strong></article>
          <article v-if="!editingLine" :class="{ 'adj-discount': lineDiscount(detailLine) > 0 }"><span>تخفیف همین خط</span><strong>{{ money(lineDiscount(detailLine)) }}</strong></article>
          <article v-if="!editingLine && lineSurcharge(detailLine) > 0" class="adj-surcharge"><span>اضافه مبلغ همین خط</span><strong>{{ money(lineSurcharge(detailLine)) }}</strong></article>
          <article v-if="!editingLine"><span>خالص کاتالوگ همین خط</span><strong>{{ money(lineCatalogNet(detailLine)) }}</strong></article>
          <article v-if="!editingLine"><span>سهم پرداخت همین خط</span><strong>{{ money(linePaidShare(detailLine)) }}</strong></article>
          <article v-if="!editingLine"><span>هزینه مواد</span><strong>{{ money(detailLine.material_cost) }}</strong></article>
          <article v-if="!editingLine" class="base-highlight"><span>مبنای درصد (سهم پرداخت)</span><strong>{{ money(detailLine.commission_base) }}</strong></article>
          <article v-if="!editingLine"><span>نرخ/نوع</span><strong>{{ rateText(detailLine) }}</strong></article>
          <article v-if="!editingLine" class="earned"><span>مبلغ بابت این مورد</span><strong>{{ money(detailLine.amount) }}</strong></article>
          <article v-if="!editingLine && detailLine.manually_edited" class="audit"><span>آخرین ویرایش</span><strong>{{ auditText(detailLine, 'edited') }}</strong></article>
          <article v-if="!editingLine && detailLine.status === 'deleted'" class="audit deleted"><span>حذف شده توسط</span><strong>{{ auditText(detailLine, 'deleted') }}</strong></article>
        </div>
        <div v-if="editingLine" class="line-edit-form">
          <label><span>شرح</span><input v-model.trim="lineForm.description" type="text"></label>
          <label><span>خدمت/آیتم موثر</span><input v-model.trim="lineForm.service_name" type="text"></label>
          <label><span>تعداد / ساعت</span><input v-model.number="lineForm.quantity" type="number" step="0.001" @input="recalculateLineAmount"></label>
          <label><span>مبلغ خام همین خط</span><input v-model.number="lineForm.gross_amount" type="number"></label>
          <label><span>تخفیف همین خط</span><input v-model.number="lineForm.discount_amount" type="number"></label>
          <label><span>سهم پرداخت همین خط</span><input v-model.number="lineForm.net_amount" type="number"></label>
          <label><span>هزینه مواد</span><input v-model.number="lineForm.material_cost" type="number"></label>
          <label><span>مبنای درصد (سهم پرداخت)</span><input v-model.number="lineForm.commission_base" type="number" @input="recalculateLineAmount"></label>
          <label>
            <span>نوع نرخ</span>
            <select v-model="lineForm.commission_type" @change="recalculateLineAmount">
              <option value="percent">درصدی</option>
              <option value="fixed">ثابت</option>
              <option value="hourly">ساعتی</option>
              <option value="tier">پلکانی</option>
              <option value="absence">غیبت</option>
            </select>
          </label>
          <label><span>مقدار نرخ</span><input v-model.number="lineForm.commission_value" type="number" @input="recalculateLineAmount"></label>
          <label class="line-final-amount"><span>مبلغ نهایی این ردیف</span><input v-model.number="lineForm.amount" type="number"></label>
        </div>
        <footer class="line-detail-actions">
          <button type="button" class="delete-line-btn wide" :disabled="savingLine || detailLine.status === 'deleted'" @click="deleteLine(detailLine)">حذف ردیف</button>
          <button v-if="detailLine.status === 'deleted'" type="button" class="restore-line-btn wide" :disabled="savingLine || restoringLineId === detailLine.id" @click="restoreLine(detailLine)">بازگردانی ردیف</button>
          <button v-if="editingLine" type="button" class="cancel-line-btn" :disabled="savingLine" @click="editingLine = false">انصراف</button>
          <button v-if="!editingLine && detailLine.status !== 'deleted'" type="button" class="edit-line-btn" @click="editingLine = true">ویرایش</button>
          <button v-if="editingLine" type="button" class="save-line-btn" :disabled="savingLine" @click="saveLine">{{ savingLine ? 'در حال ذخیره...' : 'ذخیره و محاسبه مجدد' }}</button>
        </footer>
        <section v-if="auditEvents(detailLine).length" class="line-audit-list">
          <h3>سوابق تغییرات</h3>
          <div class="audit-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>زمان</th>
                  <th>کاربر</th>
                  <th>عملیات</th>
                  <th>مبلغ قبل</th>
                  <th>مبلغ بعد</th>
                  <th>مبنا قبل</th>
                  <th>مبنا بعد</th>
                  <th>نرخ قبل</th>
                  <th>نرخ بعد</th>
                  <th>شرح قبل</th>
                  <th>شرح بعد</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(event, index) in auditEvents(detailLine)" :key="index">
                  <td>{{ formatDateTime(event.at) }}</td>
                  <td>{{ event.user_name || 'کاربر نامشخص' }}</td>
                  <td>{{ auditActionLabel(event.action) }}</td>
                  <td>{{ auditMoney(event.before?.amount) }}</td>
                  <td>{{ auditMoney(event.after?.amount) }}</td>
                  <td>{{ auditMoney(event.before?.commission_base) }}</td>
                  <td>{{ auditMoney(event.after?.commission_base) }}</td>
                  <td>{{ auditRate(event.before) }}</td>
                  <td>{{ auditRate(event.after) }}</td>
                  <td>{{ event.before?.description || '-' }}</td>
                  <td>{{ event.after?.description || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </div>

    <div v-if="patientDetail" class="line-detail-overlay" @click.self="closePatientDetail">
      <section class="line-detail-modal patient-detail-modal">
        <header>
          <div>
            <small>مشاهده بر اساس کاربر</small>
            <h2>{{ patientDetail.patient_name }}</h2>
          </div>
          <button type="button" @click="closePatientDetail">×</button>
        </header>
        <div class="line-detail-grid">
          <article><span>روز</span><strong>{{ patientDetail.day_num ? Number(patientDetail.day_num).toLocaleString('fa-IR') : '-' }}</strong></article>
          <article><span>ساعت نوبت</span><strong>{{ patientDetail.time || '-' }}</strong></article>
          <article><span>مبلغ پرداخت‌شده</span><strong>{{ money(patientDetail.paid_amount) }}</strong></article>
          <article :class="{ 'adj-discount': patientDetail.discount_amount > 0 }"><span>تخفیف کل نوبت</span><strong>{{ money(patientDetail.discount_amount) }}</strong></article>
          <article><span>تعداد خدمات</span><strong>{{ Number(patientDetail.service_count).toLocaleString('fa-IR') }}</strong></article>
          <article class="base-highlight"><span>جمع مبنای پرداخت</span><strong>{{ money(patientDetail.commission_base_total) }}</strong></article>
          <article><span>نرخ کلی</span><strong>{{ patientOverallRate(patientDetail) }}</strong></article>
          <article class="earned"><span>پورسانت این نیرو</span><strong>{{ money(patientDetail.commission_total) }}</strong></article>
        </div>
        <div class="audit-table-wrap patient-services-wrap">
          <table>
            <thead>
              <tr>
                <th>خدمت</th>
                <th>قیمت / خام</th>
                <th>تخفیف</th>
                <th>خالص کاتالوگ</th>
                <th>سهم پرداخت</th>
                <th>مبنای % (سهم پرداخت)</th>
                <th>نرخ</th>
                <th>پورسانت</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="service in patientDetail.services" :key="service.key">
                <td>{{ service.name }}</td>
                <td>{{ service.gross_amount == null ? '-' : money(service.gross_amount) }}</td>
                <td :class="{ 'adj-discount': Number(service.discount_amount || 0) > 0 }">{{ money(service.discount_amount) }}</td>
                <td>{{ service.catalog_net == null ? '-' : money(service.catalog_net) }}</td>
                <td>{{ service.paid_share == null ? '-' : money(service.paid_share) }}</td>
                <td>{{ service.commission_base == null ? '-' : money(service.commission_base) }}</td>
                <td>{{ service.rate_text }}</td>
                <td :class="{ positive: Number(service.amount || 0) > 0 }">{{ money(service.amount) }}</td>
              </tr>
              <tr v-if="!patientDetail.services.length">
                <td colspan="8">خدمتی برای این نوبت ثبت نشده است.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="line-detail-actions">
          <button type="button" class="cancel-line-btn" @click="closePatientDetail">بستن</button>
        </footer>
      </section>
    </div>
  </section>
</template>

<script>
import EmployeePortalAdmin from './EmployeePortalAdmin.vue'

export default {
  name: 'PayrollSettlement',
  components: { EmployeePortalAdmin },
  props: {
    currentUserId: { type: Number, default: null },
    initialResource: { type: Object, default: null },
    canManageEmployeePortal: { type: Boolean, default: false },
  },
  data() {
    return {
      resources: [],
      selected: null,
      selectedMonth: this.previousJalaliMonth(),
      search: '',
      filterType: 'all',
      report: null,
      loading: false,
      error: '',
      activeTab: 'lines',
      detailLine: null,
      patientDetail: null,
      lineForm: {},
      editingLine: false,
      deletingLineId: null,
      restoringLineId: null,
      savingLine: false
    }
  },
  computed: {
    currentJalaliMonth() {
      const parts = this.jalaliParts(new Date())
      return `${parts.year}-${String(parts.month).padStart(2, '0')}`
    },
    selectedKey() {
      return this.selected ? `${this.selected.type}-${this.selected.id}` : ''
    },
    salesTargetHint() {
      const tier = this.report?.summary?.reached_tier
      const next = (this.report?.resource?.sales_bonus_tiers || [])
        .map(item => Number(item.sales_from) || 0)
        .filter(from => from > Number(this.report?.summary?.target_sales || 0))
        .sort((a, b) => a - b)[0]
      return [
        tier ? `پلهٔ رسیده: ${this.money(tier.sales_from)} → ${this.money(tier.salary_addition)}` : 'هنوز به پله‌ای نرسیده',
        next ? `پلهٔ بعدی: ${this.money(next)}` : '',
      ].filter(Boolean).join(' · ')
    },
    filteredResources() {
      const query = this.search.toLowerCase()
      return this.resources.filter(resource => {
        if (this.filterType !== 'all' && resource.type !== this.filterType) return false
        return !query || String(resource.name || '').toLowerCase().includes(query)
      })
    },
    attendanceLines() {
      const types = ['attendance_overtime', 'attendance_shortage', 'attendance_absence']
      return Array.isArray(this.report?.lines)
        ? this.report.lines.filter(line => types.includes(line.earning_type))
        : []
    },
    // گروه‌بندی ریزپورسانت‌ها بر اساس نوبت/بیمار — مثل وضوح نوبت‌دهی برای مبلغ و تخفیف.
    patientRows() {
      const lines = Array.isArray(this.report?.lines) ? this.report.lines : []
      const groups = new Map()
      lines.forEach(line => {
        if (line.status === 'deleted' || this.isAttendanceLine(line) || !this.isCommissionMoneyLine(line)) return
        const appointment = line.appointment || null
        const appointmentId = appointment?.id || line.appointment_id || null
        const key = appointmentId
          ? `apt-${appointmentId}`
          : `line-${line.id}`
        if (!groups.has(key)) {
          groups.set(key, {
            key,
            appointment_id: appointmentId,
            day_num: line.day_num || appointment?.day_num || null,
            time: appointment?.time || '',
            patient_name: appointment?.lastname || 'بیمار نامشخص',
            // مثل نوبت‌دهی: مبلغ واقعاً دریافت‌شده (دفتر پرداخت + کیف‌پول)، نه amount خالص بعد از تخفیف.
            paid_amount: this.appointmentPaidAmount(appointment),
            discount_amount: Math.max(0, Number(appointment?.discount || 0)),
            original_amount: Number(appointment?.original_amount || 0),
            appointment_services: Array.isArray(appointment?.services) ? appointment.services : [],
            lines: []
          })
        }
        const group = groups.get(key)
        group.lines.push(line)
        if (!group.day_num && line.day_num) group.day_num = line.day_num
        if (!group.time && appointment?.time) group.time = appointment.time
        if ((!group.paid_amount || !group.discount_amount) && appointment) {
          group.paid_amount = this.appointmentPaidAmount(appointment)
          group.discount_amount = Math.max(0, Number(appointment.discount || 0))
          group.original_amount = Number(appointment.original_amount || 0)
          group.appointment_services = Array.isArray(appointment.services) ? appointment.services : group.appointment_services
        }
      })
      return Array.from(groups.values())
        .map(group => {
          const services = this.buildPatientServices(group)
          return {
            ...group,
            services,
            service_count: services.length,
            commission_total: group.lines.reduce((sum, line) => sum + Number(line.amount || 0), 0),
            commission_base_total: group.lines.reduce((sum, line) => sum + Number(line.commission_base || 0), 0)
          }
        })
        .sort((a, b) => {
          const dayA = Number(a.day_num || 99)
          const dayB = Number(b.day_num || 99)
          if (dayA !== dayB) return dayA - dayB
          return String(a.patient_name).localeCompare(String(b.patient_name), 'fa')
        })
    }
  },
  async mounted() {
    await this.loadResources()
    const requested = this.initialResource && this.resources.find(item => item.type === this.initialResource.type && Number(item.id) === Number(this.initialResource.id))
    if (requested || this.resources.length) this.selectResource(requested || this.resources[0])
  },
  methods: {
    async loadResources() {
      try {
        const response = await fetch('/api/payroll/resources', { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('دریافت کاربران انجام نشد.')
        const data = await response.json()
        this.resources = Array.isArray(data.resources) ? data.resources : []
      } catch (error) {
        this.error = error.message || 'خطا در دریافت کاربران'
      }
    },
    async loadReport() {
      if (!this.selected) return
      this.loading = true
      this.error = ''
      this.closePatientDetail()
      try {
        const params = new URLSearchParams({
          resource_type: this.selected.type,
          resource_id: this.selected.id,
          month: this.selectedMonth
        })
        const response = await fetch(`/api/payroll/report?${params}`, { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('گزارش این ماه قابل دریافت نیست.')
        this.report = await response.json()
      } catch (error) {
        this.report = null
        this.error = error.message || 'خطا در محاسبه گزارش'
      } finally {
        this.loading = false
      }
    },
    selectResource(resource) {
      this.selected = resource
      this.activeTab = 'lines'
      this.closePatientDetail()
      this.loadReport()
    },
    openLineDetail(line) {
      this.closePatientDetail()
      this.detailLine = line
      this.editingLine = false
      this.lineForm = {
        description: line.description || '',
        service_name: line.service_name || line.inventory_name || '',
        inventory_name: line.inventory_name || '',
        quantity: Number(line.quantity || 0),
        gross_amount: Number(line.gross_amount || 0),
        discount_amount: Number(line.discount_amount || 0),
        net_amount: Number(line.net_amount || 0),
        material_cost: Number(line.material_cost || 0),
        commission_base: Number(line.commission_base || 0),
        commission_type: line.commission_type || 'fixed',
        commission_value: Number(line.commission_value || 0),
        amount: Number(line.amount || 0)
      }
    },
    openPatientDetail(row) {
      this.closeLineDetail()
      this.patientDetail = row
    },
    closePatientDetail() {
      this.patientDetail = null
    },
    // خدمت‌های نوبت + ردیف‌های پورسانت؛ خدمت بدون خط پورسانت (مثل تخفیف کامل) هم دیده می‌شود.
    buildPatientServices(group) {
      const lines = Array.isArray(group.lines) ? [...group.lines] : []
      const used = new Set()
      const appointmentServices = Array.isArray(group.appointment_services) ? group.appointment_services : []
      const services = []

      const matchLine = (service) => {
        const inventoryId = service?.inventory_id != null ? String(service.inventory_id) : ''
        const name = String(service?.name || '').trim()
        const index = lines.findIndex((line, i) => {
          if (used.has(i)) return false
          const lineInventory = line.inventory_id != null
            ? String(line.inventory_id)
            : String(line.calculation_snapshot?.inventory_id || '')
          if (inventoryId && lineInventory && lineInventory === inventoryId) return true
          return name && String(line.service_name || line.inventory_name || '').trim() === name
        })
        if (index < 0) return null
        used.add(index)
        return lines[index]
      }

      appointmentServices.forEach((service, index) => {
        const line = matchLine(service)
        const discount = line
          ? this.lineDiscount(line)
          : Math.max(0, Number(service.discount || 0))
        const gross = line ? Number(line.gross_amount || 0) : null
        const catalogNet = line
          ? this.lineCatalogNet(line)
          : (gross == null ? null : Math.max(0, gross - discount))
        const paidShare = line ? this.linePaidShare(line) : null
        services.push({
          key: `svc-${service.line_id || service.inventory_id || index}`,
          name: service.name || line?.service_name || 'خدمت',
          gross_amount: gross,
          discount_amount: discount,
          catalog_net: catalogNet,
          paid_share: paidShare,
          net_amount: paidShare,
          commission_base: line ? Number(line.commission_base || 0) : 0,
          rate_text: line ? this.rateText(line) : '۰٪',
          amount: line ? Number(line.amount || 0) : 0,
          line
        })
      })

      lines.forEach((line, index) => {
        if (used.has(index)) return
        services.push({
          key: `earn-${line.id}`,
          name: line.service_name || line.inventory_name || 'خدمت',
          gross_amount: Number(line.gross_amount || 0),
          discount_amount: this.lineDiscount(line),
          catalog_net: this.lineCatalogNet(line),
          paid_share: this.linePaidShare(line),
          net_amount: this.linePaidShare(line),
          commission_base: Number(line.commission_base || 0),
          rate_text: this.rateText(line),
          amount: Number(line.amount || 0),
          line
        })
      })

      return services
    },
    patientOverallRate(row) {
      if (!row) return '-'
      const lines = Array.isArray(row.lines) ? row.lines.filter(line => line.commission_type === 'percent') : []
      if (!lines.length) {
        const any = Array.isArray(row.lines) ? row.lines[0] : null
        return any ? this.rateText(any) : '-'
      }
      const values = [...new Set(lines.map(line => Number(line.commission_value || 0)))]
      if (values.length === 1) return `${values[0].toLocaleString('fa-IR')}٪`
      return values.map(value => `${value.toLocaleString('fa-IR')}٪`).join(' / ')
    },
    // همان فرمول سمت سرور برای پورسانت درصدی و ثابت؛ مبلغ نهایی بعد از آن هنوز دستی قابل تغییر است.
    recalculateLineAmount() {
      const form = this.lineForm
      const value = Math.max(0, Number(form.commission_value) || 0)
      if (form.commission_type === 'percent') {
        form.amount = Math.round(Math.max(0, Number(form.commission_base) || 0) * value / 100)
      } else if (form.commission_type === 'fixed') {
        form.amount = Math.round(value * Math.max(1, Number(form.quantity) || 0))
      }
    },
    closeLineDetail() {
      this.detailLine = null
      this.lineForm = {}
      this.editingLine = false
    },
    async saveLine() {
      if (!this.detailLine) return
      this.savingLine = true
      try {
        const response = await fetch(`/api/payroll/lines/${this.detailLine.id}`, {
          method: 'PATCH',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify(this.lineForm)
        })
        if (!response.ok) throw new Error('ویرایش ریزمحاسبه انجام نشد.')
        this.closeLineDetail()
        await this.loadReport()
      } catch (error) {
        window.alert(error.message || 'خطا در ویرایش ریزمحاسبه')
      } finally {
        this.savingLine = false
      }
    },
    async deleteLine(line) {
      if (line.status === 'deleted') return
      if (!window.confirm('این ریزمحاسبه از تسویه حذف شود؟ جمع حقوق دوباره محاسبه می‌شود.')) return
      this.deletingLineId = line.id
      try {
        const response = await fetch(`/api/payroll/lines/${line.id}`, {
          method: 'DELETE',
          headers: { Accept: 'application/json' }
        })
        if (!response.ok) throw new Error('حذف ریزمحاسبه انجام نشد.')
        if (this.detailLine?.id === line.id) this.closeLineDetail()
        await this.loadReport()
      } catch (error) {
        window.alert(error.message || 'خطا در حذف ریزمحاسبه')
      } finally {
        this.deletingLineId = null
      }
    },
    async restoreLine(line) {
      if (!window.confirm('این ردیف حذف‌شده به تسویه برگردانده شود؟ جمع حقوق دوباره محاسبه می‌شود.')) return
      this.restoringLineId = line.id
      try {
        const response = await fetch(`/api/payroll/lines/${line.id}/restore`, {
          method: 'POST',
          headers: { Accept: 'application/json' }
        })
        if (!response.ok) throw new Error('بازگردانی ردیف انجام نشد.')
        if (this.detailLine?.id === line.id) this.closeLineDetail()
        await this.loadReport()
      } catch (error) {
        window.alert(error.message || 'خطا در بازگردانی ردیف')
      } finally {
        this.restoringLineId = null
      }
    },
    shiftMonth(delta) {
      const [year, month] = this.selectedMonth.split('-').map(Number)
      const date = { year, month: month + delta }
      if (date.month < 1) { date.year -= 1; date.month = 12 }
      if (date.month > 12) { date.year += 1; date.month = 1 }
      const next = `${date.year}-${String(date.month).padStart(2, '0')}`
      if (next > this.currentJalaliMonth) return
      this.selectedMonth = next
      this.loadReport()
    },
    jalaliParts(date) {
      const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
        year: 'numeric', month: '2-digit'
      }).formatToParts(date).filter(part => ['year', 'month'].includes(part.type)).map(part => [part.type, part.value]))
      return { year: Number(parts.year), month: Number(parts.month) }
    },
    previousJalaliMonth() {
      const parts = this.jalaliParts(new Date())
      const month = parts.month === 1 ? 12 : parts.month - 1
      const year = parts.month === 1 ? parts.year - 1 : parts.year
      return `${year}-${String(month).padStart(2, '0')}`
    },
    monthLabel(month) {
      const [year, value] = String(month || '').split('-')
      return `${this.monthNames[Number(value) - 1] || value} ${year}`
    },
    money(value) {
      return `${Math.round(Number(value || 0)).toLocaleString('fa-IR')} تومان`
    },
    initial(name) {
      return String(name || '؟').trim().slice(0, 1)
    },
    lineType(type) {
      return {
        base_commission: 'پورسانت پیش‌فرض پزشک',
        inventory_commission: 'استثنای اختصاصی خدمت',
        inventory_default_commission: 'پورسانت پیش‌فرض انبار',
        section_commission: 'پورسانت بخش/زیربخش',
        sales_bonus: 'پاداش فروش',
        referral_commission: 'پورسانت معرفی مشتری',
        attendance_overtime: 'اضافه‌کاری',
        attendance_shortage: 'کسری ساعت',
        attendance_absence: 'غیبت'
      }[type] || type || '-'
    },
    lineSourceLabel(line) {
      return line?.calculation_snapshot?.calculation_source_label || this.lineType(line?.earning_type)
    },
    isAttendanceLine(line) {
      return ['attendance_overtime', 'attendance_shortage', 'attendance_absence'].includes(line?.earning_type)
    },
    attendanceClockText(line) {
      const snapshot = line?.calculation_snapshot || {}
      if (snapshot.absent) return 'غیبت'
      return `${snapshot.in || '-'} تا ${snapshot.out || '-'}`
    },
    rateText(line) {
      if (line.commission_type === 'percent') return `${Number(line.commission_value || 0).toLocaleString('fa-IR')}٪`
      if (line.commission_type === 'hourly') return `${this.money(line.commission_value)} / ساعت`
      if (line.commission_type === 'tier') return `از ${this.money(line.commission_value)}`
      return this.money(line.commission_value)
    },
    appointmentTitle(line) {
      if (this.isAttendanceLine(line)) return 'حضور و غیاب'
      const appointment = line.appointment
      if (!appointment) return 'بدون نوبت مستقیم'
      return `${appointment.lastname || 'نوبت'} - ${appointment.month || ''}/${appointment.day_num || ''} ${appointment.time || ''}`
    },
    appointmentServices(line) {
      if (this.isAttendanceLine(line)) return line.description || this.lineType(line.earning_type)
      const services = line.appointment?.services
      if (!Array.isArray(services) || !services.length) return line.service_name || '-'
      return services.map(service => {
        const addons = Array.isArray(service.addons) ? service.addons.map(addon => addon.name).filter(Boolean) : []
        return [service.name, ...addons].filter(Boolean).join(' + ')
      }).filter(Boolean).join('، ') || '-'
    },
    isCommissionMoneyLine(line) {
      if (!line || this.isAttendanceLine(line)) return false
      return ['base_commission', 'inventory_commission', 'inventory_default_commission', 'section_commission', 'referral_commission', 'sales_bonus']
        .includes(line.earning_type)
        || Number(line.gross_amount || 0) > 0
        || Number(line.discount_amount || 0) > 0
        || Number(line.net_amount || 0) > 0
    },
    lineDiscount(line) {
      return Math.max(0, Number(line?.discount_amount || 0))
    },
    // خالص کاتالوگ خط (قیمت×تعداد − تخفیف + اضافه)؛ از snapshot، نه از net_amount که سهم پرداخت است.
    lineCatalogNet(line) {
      if (!line || this.isAttendanceLine(line)) return 0
      const snap = line.calculation_snapshot || {}
      if (snap.catalog_net != null && snap.catalog_net !== '') {
        return Math.max(0, Number(snap.catalog_net || 0))
      }
      const gross = Number(line.gross_amount || 0)
      return Math.max(0, gross - this.lineDiscount(line))
    },
    // سهم همین خط از مبلغ پرداخت‌شده بیمار.
    linePaidShare(line) {
      if (!line || this.isAttendanceLine(line)) return 0
      const snap = line.calculation_snapshot || {}
      if (snap.paid_share != null && snap.paid_share !== '') {
        return Math.max(0, Number(snap.paid_share || 0))
      }
      return Math.max(0, Number(line.net_amount || 0))
    },
    lineAppointmentPaidTotal(line) {
      if (!line || this.isAttendanceLine(line)) return 0
      const snap = line.calculation_snapshot || {}
      if (snap.appointment_paid_total != null && snap.appointment_paid_total !== '') {
        return Math.max(0, Number(snap.appointment_paid_total || 0))
      }
      return this.appointmentPaidAmount(line.appointment)
    },
    // اضافه مبلغ از خالص کاتالوگ − خام + تخفیف؛ نباید از net_amount (سهم پرداخت) حساب شود.
    lineSurcharge(line) {
      if (!line || this.isAttendanceLine(line)) return 0
      const gross = Number(line.gross_amount || 0)
      const discount = this.lineDiscount(line)
      return Math.max(0, Math.round(this.lineCatalogNet(line) - gross + discount))
    },
    appointmentDiscount(line) {
      if (this.isAttendanceLine(line)) return 0
      return Math.max(0, Number(line?.appointment?.discount || 0))
    },
    // مبلغ پرداخت‌شده = ledger_total (یا نقد/کارت/چک) + تسویه بدهی + کیف‌پول؛ هم‌راستا با Time.vue.
    appointmentPaidAmount(appointment) {
      if (!appointment) return 0
      const details = appointment.payment_details && typeof appointment.payment_details === 'object'
        ? appointment.payment_details
        : {}
      const settledDebt = (Array.isArray(details.debt_payments) ? details.debt_payments : [])
        .reduce((total, payment) => total + Math.max(0, Number(payment?.amount || 0)), 0)
      let recorded = 0
      if (details.ledger_total != null && details.ledger_total !== '') {
        recorded = Number(details.ledger_total || 0)
      } else {
        const breakdownTotal = Object.values(details.payment_breakdown || {})
          .reduce((total, amount) => total + Math.max(0, Number(amount || 0)), 0)
        recorded = breakdownTotal > 0
          ? breakdownTotal
          : Number(details.cash || 0) + Number(details.card || 0) + Number(details.check?.amount || 0)
      }
      const wallet = Math.max(0, Number(appointment.wallet_applied || 0))
      const paid = Math.max(0, recorded) + settledDebt + wallet
      // نوبت‌های قدیمی بدون دفتر مالی: fallback به amount ذخیره‌شده.
      if (paid > 0) return paid
      return Math.max(0, Number(appointment.amount || 0))
    },
    appointmentSurcharge(line) {
      if (this.isAttendanceLine(line)) return 0
      const appointment = line?.appointment
      if (!appointment) return 0
      const original = Number(appointment.original_amount || 0)
      const amount = Number(appointment.amount || 0)
      const discount = Number(appointment.discount || 0)
      if (!original && !amount) return 0
      return Math.max(0, Math.round(amount - original + discount))
    },
    // خالص قابل‌پرداخت نوبت؛ دیگر مبلغ کاتالوگ قبل از تخفیف نشان داده نمی‌شود تا با مبنای پورسانت اشتباه نشود.
    appointmentNetTotal(line) {
      if (this.isAttendanceLine(line)) return '-'
      const appointment = line?.appointment
      if (!appointment) return '-'
      const amount = Number(appointment.amount || 0)
      const original = Number(appointment.original_amount || 0)
      const discount = Number(appointment.discount || 0)
      const net = amount || Math.max(0, original - discount)
      return this.money(net)
    },
    auditEvents(line) {
      return Array.isArray(line?.audit_events) ? line.audit_events : []
    },
    auditText(line, type) {
      const name = type === 'deleted' ? line.deleted_by_name : line.edited_by_name
      const at = type === 'deleted' ? line.deleted_at : line.edited_at
      return `${name || 'کاربر نامشخص'} - ${this.formatDateTime(at)}`
    },
    auditMoney(value) {
      if (value === undefined || value === null || value === '') return '-'
      return this.money(value)
    },
    auditRate(record = {}) {
      if (!record || (!record.commission_type && record.commission_value === undefined)) return '-'
      if (record.commission_type === 'percent') return `${Number(record.commission_value || 0).toLocaleString('fa-IR')}٪`
      if (record.commission_type === 'hourly') return `${this.money(record.commission_value)} / ساعت`
      return this.money(record.commission_value)
    },
    auditActionLabel(action) {
      return { edit: 'ویرایش', delete: 'حذف', restore: 'بازگردانی' }[action] || action || '-'
    },
    formatDateTime(value) {
      if (!value) return '-'
      try {
        return new Intl.DateTimeFormat('fa-IR', {
          dateStyle: 'short',
          timeStyle: 'short'
        }).format(new Date(value))
      } catch {
        return String(value)
      }
    },
    customerPercentLabel(value) {
      return value === null || value === undefined || value === '' ? 'تعریف نشده' : `${Number(value).toLocaleString('fa-IR')}٪`
    }
  },
  created() {
    this.monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
  }
}
</script>

<style scoped>
.payroll-page{min-height:100vh;padding:24px;background:#f4f7fb;color:#172033}
.payroll-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
.payroll-header small{font-size:12px;font-weight:900;color:#2563eb}.payroll-header h1{margin:4px 0 0;font-size:24px}
.payroll-month{display:flex;align-items:center;gap:8px}.payroll-month button{width:36px;height:36px;border:1px solid #dbe3ed;border-radius:8px;background:#fff;color:#2563eb;font-size:24px;cursor:pointer}.payroll-month button:disabled{opacity:.4}.payroll-month input{height:36px;border:1px solid #dbe3ed;border-radius:8px;padding:0 10px;background:#fff}
.payroll-layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px}.payroll-sidebar,.payroll-content{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
.payroll-search{display:grid;gap:6px}.payroll-search span{font-size:12px;font-weight:800;color:#64748b}.payroll-search input{height:40px;border:1px solid #dbe3ed;border-radius:8px;padding:0 12px;outline:none}
.payroll-segments{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:12px 0}.payroll-segments button,.payroll-tabs button{height:34px;border:1px solid #dbe3ed;border-radius:8px;background:#f8fafc;color:#475569;cursor:pointer;font-weight:800}.payroll-segments button.active,.payroll-tabs button.active{background:#2563eb;color:#fff;border-color:#2563eb}
.payroll-user-list{display:grid;gap:8px;max-height:calc(100vh - 205px);overflow:auto}.payroll-user-list button{display:flex;align-items:center;gap:10px;width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;text-align:right;cursor:pointer}.payroll-user-list button.active{border-color:#2563eb;background:#eff6ff}.payroll-user-list img,.payroll-user-list b{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#dbeafe;color:#1d4ed8}.payroll-user-list img{object-fit:cover}.payroll-user-list span{display:grid}.payroll-user-list strong{font-size:13px}.payroll-user-list small{color:#64748b;font-size:11px}
.payroll-state{min-height:420px;display:flex;align-items:center;justify-content:center;gap:10px;color:#64748b}.payroll-state.error{color:#b91c1c}.payroll-state i{width:24px;height:24px;border:3px solid #dbeafe;border-top-color:#2563eb;border-radius:50%;animation:spin .8s linear infinite}
.payroll-person{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.payroll-person small{font-weight:900;color:#2563eb}.payroll-person h2{margin:3px 0 0;font-size:21px}.payroll-person>strong{padding:8px 12px;border-radius:8px;background:#f1f5f9}
.payroll-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.payroll-summary article{display:grid;gap:6px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.payroll-summary .total{grid-column:span 2;background:#ecfdf5;border-color:#bbf7d0}.payroll-summary span{font-size:11px;font-weight:900;color:#64748b}.payroll-summary strong{font-size:19px;color:#0f172a}.payroll-summary .total strong{font-size:26px;color:#047857}
.payroll-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}.payroll-tabs button{padding:0 16px}
.payroll-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:8px}.payroll-table{width:100%;border-collapse:collapse;min-width:1100px}.payroll-table.patient-view-table{min-width:760px}.payroll-table th,.payroll-table td{padding:11px;border-bottom:1px solid #edf2f7;text-align:right;font-size:12px}.payroll-table th{background:#f8fafc;color:#64748b;font-weight:900}.payroll-table td.positive{color:#047857;font-weight:900}.payroll-table td.negative{color:#b91c1c;font-weight:900}.payroll-table td.adj-discount,.line-detail-grid article.adj-discount{color:#b45309;font-weight:900}.line-detail-grid article.adj-discount{background:#fff7ed;border-color:#fed7aa}.payroll-table td.adj-surcharge,.line-detail-grid article.adj-surcharge{color:#0369a1;font-weight:900}.line-detail-grid article.adj-surcharge{background:#f0f9ff;border-color:#bae6fd}.line-detail-grid article.base-highlight{background:#eff6ff;border-color:#bfdbfe}.line-detail-grid article.base-highlight strong{color:#1d4ed8;font-size:15px}
.patient-row-meta{display:block;margin-top:3px;color:#64748b;font-size:11px;font-weight:700}
.service-count-btn{min-width:42px;height:32px;padding:0 12px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-weight:900;cursor:pointer}
.service-count-btn:hover{background:#dbeafe}
.patient-detail-modal{width:min(980px,96vw)}.patient-services-wrap{margin-top:4px}.patient-services-wrap table{width:100%;min-width:720px;border-collapse:collapse}.patient-services-wrap th,.patient-services-wrap td{padding:10px;border-bottom:1px solid #edf2f7;text-align:right;font-size:12px}.patient-services-wrap th{background:#f8fafc;color:#64748b;font-weight:900}
.payroll-table tr.edited td{background:#fffbeb}.payroll-table tr.deleted td{background:#f8fafc;color:#94a3b8;text-decoration:line-through}.line-status{display:inline-block;margin-right:7px;padding:2px 6px;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:900;text-decoration:none}.line-status.edited{background:#fef3c7;color:#b45309}
.line-actions{display:flex;align-items:center;gap:6px;min-width:66px}.line-actions button{width:28px;height:28px;border-radius:8px;cursor:pointer;font-weight:900}.info-btn{border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb}.delete-line-btn{border:1px solid #fecaca;background:#fff1f2;color:#dc2626;font-size:18px;line-height:1}.restore-line-btn{border:1px solid #bbf7d0;background:#ecfdf5;color:#047857}.delete-line-btn:disabled,.restore-line-btn:disabled{opacity:.45;cursor:not-allowed}.delete-line-btn.wide,.restore-line-btn.wide{width:auto;height:40px;padding:0 16px;font-size:13px}
.payroll-attendance{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}.payroll-attendance article{display:grid;gap:5px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff}.payroll-attendance article.overtime{background:#ecfdf5}.payroll-attendance article.shortage,.payroll-attendance article.absent{background:#fef2f2}.payroll-attendance b{color:#2563eb}.payroll-attendance span,.payroll-attendance small{color:#64748b;font-size:11px}
.payroll-settings{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.payroll-settings article{display:grid;gap:7px;padding:13px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.payroll-settings span{color:#64748b;font-size:12px;font-weight:900}
.payroll-empty{padding:20px;text-align:center;color:#64748b}
.line-detail-overlay{position:fixed;inset:0;z-index:1000002;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58);backdrop-filter:blur(4px)}.line-detail-modal{width:min(880px,96vw);max-height:90vh;overflow:auto;padding:18px;border-radius:10px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.35)}.line-detail-modal header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}.line-detail-modal header small{font-weight:900;color:#2563eb}.line-detail-modal h2{margin:3px 0 0;font-size:18px}.line-detail-modal header button{width:34px;height:34px;border:0;border-radius:8px;background:#f1f5f9;color:#64748b;font-size:22px;cursor:pointer}.line-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}.line-detail-grid article{display:grid;gap:6px;padding:11px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc}.line-detail-grid article.earned{background:#ecfdf5;border-color:#bbf7d0}.line-detail-grid article.audit{background:#fffbeb;border-color:#fde68a}.line-detail-grid article.deleted{background:#fef2f2;border-color:#fecaca}.line-detail-grid article.earned strong{color:#047857;font-size:17px}.line-detail-grid span{font-size:11px;font-weight:900;color:#64748b}.line-detail-grid strong{font-size:13px;color:#0f172a;line-height:1.8}.line-edit-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.line-edit-form label{display:grid;gap:6px}.line-edit-form span{font-size:11px;font-weight:900;color:#64748b}.line-edit-form input,.line-edit-form select{height:40px;border:1px solid #dbe3ed;border-radius:8px;padding:0 10px;background:#fff;color:#172033;outline:none}.line-edit-form input:focus,.line-edit-form select:focus{border-color:#2563eb;box-shadow:0 0 0 3px #dbeafe}.line-final-amount{grid-column:span 2}.line-final-amount input{font-weight:900;color:#047857}.line-detail-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.save-line-btn,.edit-line-btn,.cancel-line-btn{height:40px;border:0;border-radius:8px;padding:0 18px;font-weight:900;cursor:pointer}.save-line-btn,.edit-line-btn{background:#2563eb;color:#fff}.cancel-line-btn{background:#f1f5f9;color:#475569}.save-line-btn:disabled,.cancel-line-btn:disabled{opacity:.6;cursor:wait}.line-audit-list{margin-top:14px;padding-top:12px;border-top:1px solid #e2e8f0}.line-audit-list h3{margin:0 0 8px;font-size:14px}.audit-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:8px}.line-audit-list table{width:100%;min-width:980px;border-collapse:collapse}.line-audit-list th,.line-audit-list td{padding:9px;border-bottom:1px solid #edf2f7;text-align:right;font-size:11px;white-space:nowrap}.line-audit-list th{background:#f8fafc;color:#64748b;font-weight:900}.line-audit-list td:nth-child(3){font-weight:900;color:#2563eb}
@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:980px){.payroll-layout{grid-template-columns:1fr}.payroll-summary{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.payroll-page{padding:12px}.payroll-header{align-items:stretch;flex-direction:column}.payroll-summary,.payroll-settings{grid-template-columns:1fr}.payroll-summary .total{grid-column:auto}}

body.dark .payroll-page,#app.dark .payroll-page{background:#0f172a;color:#e2e8f0}
body.dark .payroll-header h1,#app.dark .payroll-header h1{color:#f8fafc}
body.dark .payroll-sidebar,body.dark .payroll-content,#app.dark .payroll-sidebar,#app.dark .payroll-content{background:#172033;border-color:#334155;box-shadow:0 1px 3px rgba(0,0,0,.25)}
body.dark .payroll-month button,body.dark .payroll-month input,body.dark .payroll-search input,#app.dark .payroll-month button,#app.dark .payroll-month input,#app.dark .payroll-search input{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .payroll-segments button:not(.active),body.dark .payroll-tabs button:not(.active),#app.dark .payroll-segments button:not(.active),#app.dark .payroll-tabs button:not(.active){background:#1e293b;border-color:#334155;color:#94a3b8}
body.dark .payroll-user-list button,#app.dark .payroll-user-list button{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .payroll-user-list button.active,#app.dark .payroll-user-list button.active{background:#1e3a5f;border-color:#3b82f6}
body.dark .payroll-user-list small,body.dark .payroll-state,body.dark .payroll-empty,#app.dark .payroll-user-list small,#app.dark .payroll-state,#app.dark .payroll-empty{color:#94a3b8}
body.dark .payroll-person>strong,body.dark .payroll-summary article,body.dark .payroll-settings article,#app.dark .payroll-person>strong,#app.dark .payroll-summary article,#app.dark .payroll-settings article{background:#1e293b;border-color:#334155}
body.dark .payroll-summary span,body.dark .payroll-settings span,#app.dark .payroll-summary span,#app.dark .payroll-settings span{color:#94a3b8}
body.dark .payroll-summary strong,#app.dark .payroll-summary strong{color:#f8fafc}
body.dark .payroll-summary .total,#app.dark .payroll-summary .total{background:#052e16;border-color:#14532d}
body.dark .payroll-summary .total strong,#app.dark .payroll-summary .total strong{color:#4ade80}
body.dark .payroll-table-wrap,body.dark .payroll-table th,#app.dark .payroll-table-wrap,#app.dark .payroll-table th{background:#1e293b;border-color:#334155;color:#94a3b8}
body.dark .payroll-table td,#app.dark .payroll-table td{border-color:#334155;color:#e2e8f0}
body.dark .payroll-table td.positive,#app.dark .payroll-table td.positive{color:#4ade80}
body.dark .payroll-table td.negative,#app.dark .payroll-table td.negative{color:#f87171}
body.dark .payroll-table tr.edited td,#app.dark .payroll-table tr.edited td{background:#422006}
body.dark .payroll-table tr.deleted td,#app.dark .payroll-table tr.deleted td{background:#1e293b;color:#64748b}
body.dark .payroll-attendance article,#app.dark .payroll-attendance article{background:#1e293b;border-color:#334155}
body.dark .payroll-attendance article.overtime,#app.dark .payroll-attendance article.overtime{background:#052e16}
body.dark .payroll-attendance article.shortage,body.dark .payroll-attendance article.absent,#app.dark .payroll-attendance article.shortage,#app.dark .payroll-attendance article.absent{background:#450a0a}
body.dark .line-detail-modal,#app.dark .line-detail-modal{background:#172033;color:#e2e8f0}
body.dark .line-detail-modal header button,body.dark .cancel-line-btn,#app.dark .line-detail-modal header button,#app.dark .cancel-line-btn{background:#1e293b;color:#94a3b8}
body.dark .line-detail-grid article,#app.dark .line-detail-grid article{background:#1e293b;border-color:#334155}
body.dark .line-detail-grid article.earned,#app.dark .line-detail-grid article.earned{background:#052e16;border-color:#14532d}
body.dark .line-detail-grid article.adj-discount,#app.dark .line-detail-grid article.adj-discount{background:#431407;border-color:#9a3412}
body.dark .line-detail-grid article.adj-surcharge,#app.dark .line-detail-grid article.adj-surcharge{background:#0c4a6e;border-color:#0369a1}
body.dark .line-detail-grid article.base-highlight,#app.dark .line-detail-grid article.base-highlight{background:#1e3a5f;border-color:#3b82f6}
body.dark .line-detail-grid article.base-highlight strong,#app.dark .line-detail-grid article.base-highlight strong{color:#93c5fd}
body.dark .payroll-table td.adj-discount,#app.dark .payroll-table td.adj-discount{color:#fdba74}
body.dark .payroll-table td.adj-surcharge,#app.dark .payroll-table td.adj-surcharge{color:#7dd3fc}
body.dark .line-detail-grid article.audit,#app.dark .line-detail-grid article.audit{background:#422006;border-color:#713f12}
body.dark .line-detail-grid article.deleted,#app.dark .line-detail-grid article.deleted{background:#450a0a;border-color:#7f1d1d}
body.dark .line-detail-grid strong,#app.dark .line-detail-grid strong{color:#f8fafc}
body.dark .line-edit-form input,body.dark .line-edit-form select,#app.dark .line-edit-form input,#app.dark .line-edit-form select{background:#1e293b;border-color:#334155;color:#e2e8f0}
body.dark .line-audit-list,#app.dark .line-audit-list{border-color:#334155}
body.dark .line-audit-list th,#app.dark .line-audit-list th{background:#1e293b;color:#94a3b8}
body.dark .info-btn,#app.dark .info-btn{background:#1e3a5f;border-color:#334155;color:#93c5fd}
body.dark .service-count-btn,#app.dark .service-count-btn{background:#1e3a5f;border-color:#334155;color:#93c5fd}
body.dark .service-count-btn:hover,#app.dark .service-count-btn:hover{background:#1e40af}
body.dark .patient-row-meta,#app.dark .patient-row-meta{color:#94a3b8}
body.dark .patient-services-wrap th,#app.dark .patient-services-wrap th{background:#1e293b;color:#94a3b8}
body.dark .patient-services-wrap td,#app.dark .patient-services-wrap td{border-color:#334155;color:#e2e8f0}
body.dark .delete-line-btn,#app.dark .delete-line-btn{background:#450a0a;border-color:#7f1d1d;color:#fca5a5}
body.dark .restore-line-btn,#app.dark .restore-line-btn{background:#052e16;border-color:#14532d;color:#4ade80}
body.dark .line-status.edited,#app.dark .line-status.edited{background:#422006;color:#fbbf24}
body.dark .line-status:not(.edited),#app.dark .line-status:not(.edited){background:#450a0a;color:#fca5a5}
body.dark .payroll-state i,#app.dark .payroll-state i{border-color:#334155;border-top-color:#3b82f6}
</style>

<style>
html body.dark .payroll-page,
html #app.dark .payroll-page { background: transparent !important; color: #e8eaed !important; }
html body.dark .payroll-header h1,
html body.dark .payroll-person h2,
html #app.dark .payroll-header h1,
html #app.dark .payroll-person h2 { color: #f1f3f4 !important; }
html body.dark .payroll-header small,
html body.dark .payroll-person small,
html body.dark .payroll-search span,
html #app.dark .payroll-header small,
html #app.dark .payroll-person small,
html #app.dark .payroll-search span { color: #9aa0a6 !important; }
html body.dark .payroll-sidebar,
html body.dark .payroll-content,
html #app.dark .payroll-sidebar,
html #app.dark .payroll-content {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
  box-shadow: none !important;
}
html body.dark .payroll-month button,
html body.dark .payroll-month input,
html body.dark .payroll-search input,
html #app.dark .payroll-month button,
html #app.dark .payroll-month input,
html #app.dark .payroll-search input {
  background: #121417 !important;
  border: 1px solid #3a3f4a !important;
  color: #f1f3f4 !important;
}
html body.dark .payroll-segments button:not(.active),
html body.dark .payroll-tabs button:not(.active),
html body.dark .payroll-user-list button,
html body.dark .payroll-person > strong,
html body.dark .payroll-summary article,
html body.dark .payroll-settings article,
html body.dark .payroll-table-wrap,
html body.dark .payroll-table th,
html #app.dark .payroll-segments button:not(.active),
html #app.dark .payroll-tabs button:not(.active),
html #app.dark .payroll-user-list button,
html #app.dark .payroll-person > strong,
html #app.dark .payroll-summary article,
html #app.dark .payroll-settings article,
html #app.dark .payroll-table-wrap,
html #app.dark .payroll-table th {
  background: #22252d !important;
  border-color: #3a3f4a !important;
  color: #c5cad3 !important;
}
html body.dark .payroll-segments button.active,
html body.dark .payroll-tabs button.active,
html #app.dark .payroll-segments button.active,
html #app.dark .payroll-tabs button.active {
  background: #4f8cff !important;
  border-color: #4f8cff !important;
  color: #fff !important;
}
html body.dark .payroll-user-list button.active,
html #app.dark .payroll-user-list button.active {
  background: #1e3a5f !important;
  border-color: #4f8cff !important;
  color: #f1f3f4 !important;
}
html body.dark .payroll-user-list strong,
html body.dark .payroll-summary strong,
html body.dark .payroll-settings strong,
html body.dark .payroll-table td,
html #app.dark .payroll-user-list strong,
html #app.dark .payroll-summary strong,
html #app.dark .payroll-settings strong,
html #app.dark .payroll-table td { color: #f1f3f4 !important; }
html body.dark .payroll-user-list small,
html body.dark .payroll-summary span,
html body.dark .payroll-settings span,
html body.dark .payroll-table th,
html body.dark .payroll-empty,
html #app.dark .payroll-user-list small,
html #app.dark .payroll-summary span,
html #app.dark .payroll-settings span,
html #app.dark .payroll-table th,
html #app.dark .payroll-empty { color: #9aa0a6 !important; }
html body.dark .payroll-user-list b,
html #app.dark .payroll-user-list b { background: #2a2f3a !important; color: #93c5fd !important; }
html body.dark .payroll-summary .total,
html #app.dark .payroll-summary .total { background: #14352a !important; border-color: #166534 !important; }
html body.dark .payroll-summary .total strong,
html body.dark .payroll-table td.positive,
html #app.dark .payroll-summary .total strong,
html #app.dark .payroll-table td.positive { color: #86efac !important; }
html body.dark .payroll-table td.negative,
html #app.dark .payroll-table td.negative { color: #fca5a5 !important; }
html body.dark .payroll-table td,
html body.dark .payroll-table th,
html #app.dark .payroll-table td,
html #app.dark .payroll-table th { border-color: #2c3038 !important; }
</style>
