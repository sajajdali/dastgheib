<template>
  <div class="patient-page">

    <section v-if="openingRequestedProfile && !profileViewOpen" class="direct-profile-loading">
      <span class="direct-profile-spinner"></span>
      <strong>در حال باز کردن مستقیم پرونده...</strong>
    </section>

    <section v-if="!profileViewOpen && !openingRequestedProfile" class="card create-card">
      <div class="section-header">
        <h3>تشکیل پرونده</h3>
      </div>

      <div class="create-grid">
        <input
          v-model="form.first_name"
          name="patient_first_name"
          type="text"
          autocomplete="given-name"
          placeholder="نام *"
        />
        <input
          v-model="form.last_name"
          name="patient_last_name"
          type="text"
          autocomplete="family-name"
          placeholder="نام خانوادگی *"
        />
        
        <input 
          v-model="form.phone" 
          type="text" 
          placeholder="شماره تماس * (۱۱ رقم)" 
          maxlength="11"
        />
        <input
          v-model="form.file_number"
          type="text"
          name="patient_file_number"
          autocomplete="off"
          placeholder="شماره پرونده (خودکار) *"
          readonly
          required
        />

        <select v-model="form.gender">
          <option value="" disabled>جنسیت *</option>
          <option>زن</option>
          <option>مرد</option>
        </select>

        <date-picker
          v-if="!showMediaModal"
          v-model="form.birth_date"
          format="jYYYY-jMM-jDD"
          display-format="jYYYY-jMM-jDD"
          input-class="birthdate-picker"
          placeholder="تاریخ تولد *"
          auto-submit
          color="#0f766e"
        />

        <Multiselect
          v-if="activeProfileFields.city"
          v-model="selectedCity"
          class="city-select"
          :options="cityOptions"
          label="displayName"
          track-by="id"
          placeholder="انتخاب شهر"
          :searchable="true"
          :allow-empty="false"
          :show-labels="false"
          @select="selectCity"
        />

        <select v-model="form.financial_status">
          <option value="" disabled>وضعیت مالی *</option>
          <option>ضعیف</option>
          <option>متوسط</option>
          <option>خوب</option>
          <option>عالی</option>
        </select>

        <input 
          v-if="activeProfileFields.national_id" 
          v-model="form.national_id" 
          type="text" 
          placeholder="کد ملی" 
        />

        <input 
          v-if="activeProfileFields.father_name" 
          v-model="form.father_name" 
          type="text" 
          placeholder="نام پدر" 
        />

        <date-picker
          v-if="activeProfileFields.marriage_date && !showMediaModal"
          v-model="form.marriage_date"
          format="jYYYY-jMM-jDD"
          display-format="jYYYY-jMM-jDD"
          input-class="birthdate-picker"
          placeholder="تاریخ ازدواج"
          auto-submit
          color="#0f766e"
        />

        <input 
          v-if="activeProfileFields.education" 
          v-model="form.education" 
          type="text" 
          placeholder="تحصیلات" 
        />

        <input 
          v-if="activeProfileFields.second_phone" 
          v-model="form.second_phone" 
          type="text" 
          placeholder="شماره تماس دوم" 
          maxlength="11"
        />

        <textarea v-model="form.patient_history" placeholder="تیپ شخصیتی "></textarea>
        <textarea v-model="form.medical_history" placeholder="سوابق پزشکی"></textarea>
        
        <textarea 
          v-if="activeProfileFields.address" 
          v-model="form.address" 
          placeholder="آدرس محل سکونت"
        ></textarea>

        <button class="primary-btn" @click="submitForm">
          ثبت پرونده
        </button>
      </div>
    </section>

    <section v-if="!profileViewOpen" class="card search-card">
      <div class="section-header">
        <h3>جستجو</h3>
      </div>

      <div class="search-grid">
        <input
          v-model.trim="search.q"
          type="text"
          placeholder="نام یا نام خانوادگی"
          @keydown.enter.prevent="performSearch"
        />
        <input
          v-model="search.file_number"
          type="text"
          placeholder="شماره پرونده"
          @keydown.enter.prevent="performSearch"
        />
        <input
          v-model="search.phone"
          type="text"
          placeholder="شماره تماس"
          @keydown.enter.prevent="performSearch"
        />
        <input
          v-if="activeProfileFields.national_id"
          v-model.trim="search.national_id"
          type="text"
          placeholder="کد ملی"
          @keydown.enter.prevent="performSearch"
        />

        <button type="button" class="secondary-btn search-btn" :disabled="searchLoading" @click="performSearch">
          <span v-if="searchLoading" class="btn-spinner"></span>
          <span>{{ searchLoading ? 'در حال جستجو...' : 'جستجو' }}</span>
        </button>
      </div>

      <div v-if="searchLoading" class="search-loading-line">
        <span class="btn-spinner dark"></span>
        <span>در حال دریافت اطلاعات پرونده...</span>
      </div>
    </section>

    <section v-if="searchResults.length && !profileViewOpen" class="card result-card">
      <div class="section-header result-header">
        <h3>
          نتایج جستجو
          <span class="result-count">
            {{ searchResults.length }} مورد
          </span>
        </h3>

        <div class="action-buttons-wrap" style="display: flex; gap: 8px;">
          <button
            v-if="canUseGallery && searchResults.length === 1"
            class="media-btn"
            @click="openMediaModal(searchResults[0])"
          >
            درج عکس
          </button>

          <button
            v-if="searchResults.length === 1"
            class="wallet-btn"
            @click="openWalletModal(searchResults[0])"
          >
            💼 کیف پول
          </button>

          <button
            v-if="searchResults.length === 1"
            class="edit-btn"
            @click="openEditModal(searchResults[0])"
          >
            ✏️ ویرایش پرونده
          </button>
        </div>
      </div>

      <div class="result-table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th v-for="key in patientResultColumns" :key="key">
                {{ columnLabels[key] }}
              </th>
              <th>نوع مشتری</th>
              <th>پرونده</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(row, index) in searchResults" :key="index">
              <td v-for="key in patientResultColumns" :key="key">
                <div v-if="key === 'first_name' || key === 'last_name'" class="patient-name-cell">
                  <PatientAvatar
                    v-if="key === 'first_name'"
                    :patient="row"
                    :size="38"
                    :clickable="canUseGallery"
                    @click.stop="openMediaModal(row)"
                  />
                  <button
                    type="button"
                    class="patient-name-link"
                    title="باز کردن پرونده"
                    @click.stop="openPatientProfile(row)"
                  >
                    {{ formatCellValue(key, row[key]) }}
                  </button>
                </div>
                <template v-else>
                  {{ formatCellValue(key, row[key]) }}
                </template>
              </td>
              <td>
                <span :class="['customer-level-badge', customerLevelClass(row.customer_level)]">
                  {{ customerLevelLabel(row.customer_level) }}
                </span>
              </td>
              <td>
                <button type="button" class="open-profile-btn" @click.stop="openPatientProfile(row)">
                  باز کردن پرونده
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="profileViewOpen && activePatientProfile" class="patient-profile-view">
      <div class="profile-topbar">
        <button type="button" class="profile-back-btn" @click="closePatientProfile">
          ‹ بازگشت
        </button>
        <h2>پروفایل مراجعه کننده</h2>
      </div>

      <div class="profile-shell">
        <section class="profile-main-card">
          <div class="profile-main-layout">
          <div class="profile-hero">
            <PatientAvatar
              :patient="activePatientProfile"
              :size="112"
              original
              :clickable="canUseGallery"
              @click="openMediaModal(activePatientProfile)"
            />
            <h1>{{ patientFullName(activePatientProfile) }}</h1>
            <div class="profile-customer-level">
              <span :class="['customer-level-badge', customerLevelClass(activePatientProfile.customer_level)]">
                {{ customerLevelLabel(activePatientProfile.customer_level) }}
              </span>
              <button v-if="false" type="button" class="customer-level-change-btn" @click="openCustomerLevelModal(activePatientProfile)">
                تغییر نوع مشتری
              </button>
            </div>
            <div class="profile-quick-actions">
              <button
                v-if="canUseGallery"
                type="button"
                class="profile-gallery-action"
                title="مشاهده عکس‌ها و گالری پرونده"
                aria-label="مشاهده عکس‌ها و گالری پرونده"
                @click="openMediaModal(activePatientProfile)"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="16" rx="2" />
                  <circle cx="9" cy="10" r="2" />
                  <path d="m4 17 5-4 4 3 3-2 4 3" />
                </svg>
              </button>
              <button
                v-if="canUseBeauty"
                type="button"
                class="profile-beauty-action"
                title="باز کردن پرونده زیبایار"
                aria-label="باز کردن پرونده زیبایار"
                @click="$emit('open-beauty-record', activePatientProfile)"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 3c1.2 3.7 2.3 4.8 6 6-3.7 1.2-4.8 2.3-6 6-1.2-3.7-2.3-4.8-6-6 3.7-1.2 4.8-2.3 6-6Z" />
                  <path d="M18.5 15.5c.5 1.6 1 2.1 2.5 2.5-1.5.5-2 1-2.5 2.5-.5-1.5-1-2-2.5-2.5 1.5-.4 2-1 2.5-2.5Z" />
                </svg>
              </button>
              <button
                type="button"
                class="profile-followup-action"
                title="مشاهده پیگیری‌های این مراجعه‌کننده"
                aria-label="مشاهده پیگیری‌های این مراجعه‌کننده"
                @click="$emit('open-followups', activePatientProfile)"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4 5h16v10H8l-4 4V5Z" />
                  <path d="M8 9h8M8 12h5" />
                </svg>
              </button>
            </div>
            <div v-if="canUseGallery && (latestProfilePhotosLoading || latestProfilePhotos.length)" class="profile-latest-photos">
              <button
                v-for="photo in latestProfilePhotos"
                :key="photo.id"
                type="button"
                :title="photo.original_name || 'عکس پرونده'"
                @click="openMediaModal(activePatientProfile)"
              >
                <img :src="photo.url" :alt="photo.original_name || 'عکس پرونده'">
              </button>
              <span v-if="latestProfilePhotosLoading"></span>
            </div>
            <button
              type="button"
              class="problematic-profile-toggle"
              :class="{ active: activePatientProfile.customer_level === 'problematic' }"
              :disabled="customerLevelSaving"
              @click="toggleProblematicCustomer"
            >
              <span aria-hidden="true">{{ activePatientProfile.customer_level === 'problematic' ? '✓' : '!' }}</span>
              {{
                customerLevelSaving
                  ? 'در حال ذخیره...'
                  : activePatientProfile.customer_level === 'problematic'
                    ? 'مشتری دردسر ساز'
                    : 'مشتری دردسر ساز'
              }}
            </button>
            <p>شماره پرونده {{ activePatientProfile.file_number || '-' }}</p>
          </div>

          <div class="profile-info-grid">
            <div class="profile-info-row">
              <span class="profile-info-icon">☎</span>
              <b>شماره تماس</b>
              <strong>{{ displayPatientPhone(activePatientProfile.phone) || '-' }}</strong>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-icon">📅</span>
              <b>سن</b>
              <strong>{{ patientAge(activePatientProfile.birth_date) }}</strong>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-icon">📍</span>
              <b>شهر / محدوده</b>
              <strong>{{ activePatientProfile.city || activePatientProfile.area || '-' }}</strong>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-icon">👥</span>
              <b>تعداد مراجعه</b>
              <strong>{{ appointmentResults.length }} مرتبه</strong>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-icon">💳</span>
              <b>جمع مبلغ خدمات</b>
              <strong class="green-value">{{ formatMoneyValue(profileTotalAmount) }}</strong>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-icon">💼</span>
              <b>بیعانه / اعتبار</b>
              <strong>{{ formatMoneyValue(activePatientProfile.wallet_balance) }}</strong>
            </div>
            <div class="profile-info-row" :class="{ 'profile-debt-warning': Number(activePatientProfile.outstanding_debt || 0) > 0 }">
              <span class="profile-info-icon">⚠</span>
              <b>بدهکاری کل</b>
              <strong>{{ formatMoneyValue(activePatientProfile.outstanding_debt) }}</strong>
            </div>
          </div>
          </div>
        </section>

        <section class="profile-report-card">
          <div class="profile-card-title">
            <h3>گزارش کلی مراجعه‌کننده</h3>
            <span>خلاصه عملکرد مالی و رفتاری پرونده</span>
          </div>

          <div class="profile-stats-grid">
            <article
              v-for="card in profileStatCards"
              :key="card.key"
              class="profile-stat-box"
              :style="{ '--stat-accent': card.accent, '--stat-percent': card.percent }"
            >
              <div class="stat-chart" :aria-label="card.title">
                <span>{{ Math.round(card.percent) }}%</span>
              </div>
              <div class="stat-body">
                <small>{{ card.title }}</small>
                <strong>{{ card.value }}</strong>
                <em>{{ card.hint }}</em>
                <div class="stat-line">
                  <i></i>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section class="profile-history-card">
          <div class="profile-card-title">
            <h3>سوابق خدمات</h3>
            <span>{{ filteredAppointmentResults.length }} / {{ appointmentResults.length }} مورد</span>
          </div>

          <div v-if="appointmentLoading" class="profile-loading-line">
            <span class="btn-spinner dark"></span>
            <span>در حال دریافت سوابق...</span>
          </div>

          <div v-else-if="appointmentResults.length" class="profile-table-wrap">
            <div v-if="dueCheckRows.length" class="profile-check-alert">
              <strong>هشدار چک</strong>
              <span>{{ dueCheckRows.length }} چک سررسید گذشته، امروز یا فردا دارد.</span>
            </div>
            <table class="profile-services-table">
              <thead>
                <tr>
                  <th v-for="column in appointmentFilterColumns" :key="column.key" :class="{ 'filtered-cell': isAppointmentFiltered(column.key) }">
                    <div class="profile-filter-head">
                      <span>{{ column.label }}</span>
                      <button type="button" title="فیلتر" @click.stop="toggleAppointmentFilter(column.key)">⚙</button>
                    </div>
                    <div v-if="activeAppointmentFilter === column.key" class="profile-filter-menu" @click.stop>
                      <label v-for="value in appointmentFilterValues(column.key)" :key="value">
                        <input
                          type="checkbox"
                          :checked="selectedAppointmentFilters[column.key]?.includes(value)"
                          @change="toggleAppointmentFilterValue(column.key, value)"
                        >
                        <span>{{ value }}</span>
                      </label>
                      <button v-if="isAppointmentFiltered(column.key)" type="button" @click="clearAppointmentFilter(column.key)">پاک کردن</button>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(item, index) in filteredAppointmentResults"
                  :key="`profile-history-${item.id || index}`"
                  :class="{ 'profile-check-warning-row': appointmentCheckAlert(item) }"
                  :title="appointmentRegistrationTooltip(item)"
                >
                  <td>{{ formatServices(item.services) }}</td>
                  <td>{{ appointmentDoctors(item) }}</td>
                  <td>{{ appointmentConsultants(item) }}</td>
                  <td>{{ formatMoneyValue(item.amount) }}</td>
                  <td>
                    <span class="profile-payment-summary">{{ paymentSummary(item) }}</span>
                    <span
                      v-if="hasAppointmentCheck(item)"
                      class="profile-check-icon"
                      :class="{ urgent: appointmentCheckAlert(item) }"
                      :title="appointmentCheckText(item)"
                    >
                      چک
                    </span>
                  </td>
                  <td>{{ item.status || '-' }}</td>
                  <td>{{ formatAppointmentTrackingTime(item.arrived_at) }}</td>
                  <td>{{ formatDoneWork(item) }}</td>
                  <td>{{ formatAppointmentTrackingTime(item.completed_at) }}</td>
                  <td>{{ formatDebtValue(item.debt) }}</td>
                </tr>
                <tr v-if="!filteredAppointmentResults.length">
                  <td :colspan="appointmentFilterColumns.length" class="profile-empty-row">موردی با این فیلترها پیدا نشد.</td>
                </tr>
              </tbody>
            </table>
            <table v-if="false" class="profile-services-table">
              <thead>
                <tr>
                  <th>ردیف</th>
                  <th>خدمت انجام شده</th>
                  <th>مبلغ</th>
                  <th>تاریخ</th>
                  <th>پزشک / مشاور</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in appointmentResults" :key="item.id || index">
                  <td>{{ index + 1 }}</td>
                  <td>{{ formatServices(item.services) }}</td>
                  <td>{{ formatMoneyValue(item.amount) }}</td>
                  <td>{{ formatAppointmentDate(item) }}</td>
                  <td>{{ appointmentDoctors(item) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="profile-empty-history">
            هنوز خدمتی برای این پرونده ثبت نشده است.
          </div>
        </section>

        <section class="profile-rating-card">
          <div class="rating-bars">
            <div v-for="rate in [5, 4, 3, 2, 1]" :key="rate" class="rating-bar-row">
              <span>{{ rate }} ستاره</span>
              <div><i :style="{ width: ratingWidth(rate) }"></i></div>
              <b>{{ ratingCount(rate) }}</b>
            </div>
          </div>
          <div class="rating-summary">
            <h3>میزان رضایت مندی</h3>
            <strong>4.6</strong>
            <div class="rating-stars">★ ★ ★ ★ ☆</div>
            <p>بر اساس {{ Math.max(appointmentResults.length, 1) }} نظر ثبت شده</p>
          </div>
        </section>
      </div>
    </section>

    <section v-if="false" class="card result-card services-history-card">
      <div class="section-header result-header">
        <h3>
          خدمات انجام شده
          <span class="result-count">
            {{ appointmentResults.length }} مورد
          </span>
        </h3>
      </div>

      <div class="result-table-wrap">
        <table class="result-table">
          <thead>
            <tr>
              <th>ماه</th>
              <th>روز</th>
              <th>نام بیمار</th>
              <th>شماره تماس</th>
              <th>شماره پرونده</th>
              <th>ساعت</th>
              <th>وضعیت</th>
              <th>انجام کار</th>
              <th>منبع</th>
              <th>خدمات</th>
              <th>مبلغ</th>
              <th>بدهکار</th>
              <th>توضیحات</th>
              <th>تاریخ ثبت</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in appointmentResults" :key="item.id">
              <td>{{ item.month || '-' }}</td>
              <td>{{ item.day_num || '-' }}</td>
              <td>{{ item.lastname || '-' }}</td>
              <td>{{ displayPatientPhone(item.phone) || '-' }}</td>
              <td>{{ item.file_number || '-' }}</td>
              <td>{{ item.time || '-' }}</td>
              <td>{{ item.status || '-' }}</td>
              <td>{{ item.done || '-' }}</td>
              <td>{{ item.source || '-' }}</td>
              <td>{{ formatServices(item.services) }}</td>
              <td>{{ formatMoneyValue(item.amount) }}</td>
              <td>{{ formatMoneyValue(item.debt) }}</td>
              <td>{{ item.description || '-' }}</td>
              <td>{{ formatCellValue('created_at', item.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="!searchResults.length && searchSearched && !profileViewOpen" class="card result-card">
      <div class="empty-result">
        موردی یافت نشد
      </div>
    </section>

    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="edit-modal" @click.stop>
        <h3>ویرایش پرونده</h3>

        <div class="edit-grid">
          <Multiselect
            v-if="activeProfileFields.city"
            v-model="selectedEditCity"
            class="city-select edit-city-select"
            :options="cityOptions"
            label="displayName"
            track-by="id"
            placeholder="انتخاب شهر"
            :searchable="true"
            :allow-empty="false"
            :show-labels="false"
            @select="selectEditCity"
          />
          <input v-model="editPatient.first_name" name="edit_patient_first_name" autocomplete="given-name" placeholder="نام" />
          <input v-model="editPatient.last_name" name="edit_patient_last_name" autocomplete="family-name" placeholder="نام خانوادگی" />
          <input
            v-if="canViewPatientPhone"
            v-model="editPatient.phone"
            placeholder="شماره تماس"
            maxlength="11"
          />
          <input
            v-else
            :value="displayPatientPhone(editPatient.phone)"
            placeholder="شماره تماس"
            readonly
          />
          <input v-model="editPatient.file_number" placeholder="شماره پرونده" readonly />

          <select v-model="editPatient.gender">
            <option value="">انتخاب جنسیت</option>
            <option value="زن">زن</option>
            <option value="مرد">مرد</option>
          </select>

          <input v-model="editPatient.birth_date" placeholder="تاریخ تولد" />
          <input v-model="editPatient.area" placeholder="محدوده سکونت" />
          <input v-model="editPatient.financial_status" placeholder="وضعیت مالی" />

          <input v-if="activeProfileFields.national_id" v-model="editPatient.national_id" placeholder="کد ملی" />
          <input v-if="activeProfileFields.father_name" v-model="editPatient.father_name" placeholder="نام پدر" />
          <input v-if="activeProfileFields.marriage_date" v-model="editPatient.marriage_date" placeholder="تاریخ ازدواج" />
          <input v-if="activeProfileFields.education" v-model="editPatient.education" placeholder="تحصیلات" />
          <input v-if="activeProfileFields.second_phone && canViewPatientPhone" v-model="editPatient.second_phone" placeholder="شماره تماس دوم" maxlength="11" />
          <input v-else-if="activeProfileFields.second_phone" :value="displayPatientPhone(editPatient.second_phone)" placeholder="شماره تماس دوم" readonly />

          <textarea v-model="editPatient.patient_history" placeholder="تیپ شخصیتی"></textarea>
          <textarea v-model="editPatient.medical_history" placeholder="سوابق پزشکی"></textarea>
          <textarea v-if="activeProfileFields.address" v-model="editPatient.address" placeholder="آدرس محل سکونت"></textarea>
        </div>

        <div class="modal-actions">
          <button class="secondary-btn" @click="showEditModal = false">انصراف</button>
          <button class="primary-btn" @click="updatePatient">ذخیره تغییرات</button>
        </div>
      </div>
    </div>

    <div v-if="showCustomerLevelModal" class="modal-overlay" @click.self="closeCustomerLevelModal">
      <div class="customer-level-modal" @click.stop>
        <div class="wallet-modal-header">
          <h3>تعیین نوع مشتری</h3>
          <button class="close-btn" @click="closeCustomerLevelModal">×</button>
        </div>

        <div class="customer-level-help">
          <strong>{{ patientFullName(activeCustomerLevelPatient) }}</strong>
          <p>
            از این قسمت می‌توانید سطح مشتری را برای تشخیص سریع در پرونده و جدول جستجو مشخص کنید.
            رنگ قرمز برای مشتری مشکل‌ساز، آبی برای مشتری آبی و سبز برای مشتری CIP نمایش داده می‌شود.
          </p>
        </div>

        <div class="customer-level-options">
          <button
            v-for="option in customerLevelOptions"
            :key="option.value"
            type="button"
            :class="['customer-level-option', option.class, { active: selectedCustomerLevel === option.value }]"
            @click="selectedCustomerLevel = option.value"
          >
            <span>{{ option.label }}</span>
            <small>{{ option.description }}</small>
          </button>
        </div>

        <div class="modal-actions">
          <button class="secondary-btn" :disabled="customerLevelSaving" @click="closeCustomerLevelModal">انصراف</button>
          <button class="primary-btn" :disabled="customerLevelSaving" @click="saveCustomerLevel">
            {{ customerLevelSaving ? 'در حال ذخیره...' : 'ذخیره نوع مشتری' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showWalletModal" class="modal-overlay" @click.self="showWalletModal = false">
      <div class="wallet-modal" @click.stop>
        <div class="wallet-modal-header">
          <h3>کیف پول بیمار</h3>
          <button class="close-btn" @click="showWalletModal = false">×</button>
        </div>

        <div class="wallet-content">
          <p class="patient-name">بیمار: <strong>{{ activeWalletPatient.first_name }} {{ activeWalletPatient.last_name }}</strong></p>
          
          <div class="balance-box">
            <span>موجوزی کیف پول:</span>
            <strong class="balance-amount">{{ formatMoneyValue(walletBalance) }}</strong>
          </div>

          <div class="wallet-input-group">
            <label>مبلغ تراکنش (تومان):</label>
            <input 
              v-model.number="walletAmount" 
              type="number" 
              placeholder="مبلغ مورد نظر را وارد کنید" 
              min="1"
            />
          </div>
        </div>

        <div class="modal-actions wallet-actions">
          <button class="withdraw-btn" @click="handleWithdraw">برداشت از کیف پول</button>
          <button class="deposit-btn" @click="handleDeposit">واریز به کیف پول</button>
        </div>
      </div>
    </div>

    <div v-if="profileCrop.open" class="profile-crop-overlay" @click.self="cancelProfileCrop">
      <section class="profile-crop-modal" dir="rtl" @click.stop>
        <header class="profile-crop-header">
          <div>
            <h3>تنظیم عکس پرونده</h3>
            <p>صورت را داخل محدوده دایره‌ای قرار دهید</p>
          </div>
          <button type="button" class="profile-crop-close" title="بستن" aria-label="بستن" @click="cancelProfileCrop">×</button>
        </header>

        <div
          class="profile-crop-stage"
          :class="{ dragging: profileCrop.dragging }"
          @pointerdown="startProfileCropDrag"
          @pointermove="moveProfileCrop"
          @pointerup="endProfileCropDrag"
          @pointercancel="endProfileCropDrag"
        >
          <img
            v-if="profileCrop.sourceUrl"
            :src="profileCrop.sourceUrl"
            alt="پیش‌نمایش عکس بیمار"
            :class="{ portrait: profileCrop.image && profileCrop.image.naturalHeight > profileCrop.image.naturalWidth }"
            :style="{ transform: `translate(calc(-50% + ${profileCrop.x}px), calc(-50% + ${profileCrop.y}px)) scale(${profileCrop.zoom})` }"
            draggable="false"
          >
          <div class="profile-face-guide" aria-hidden="true">
            <span class="face-head"></span>
            <span class="face-shoulders"></span>
          </div>
        </div>

        <p class="profile-crop-help">عکس را بکشید تا چهره وسط قرار بگیرد. بهتر است سر و شانه‌ها داخل دایره باشند.</p>
        <label class="profile-crop-zoom">
          <span>کوچک</span>
          <input v-model.number="profileCrop.zoom" type="range" min="1" max="3" step="0.01" @input="clampProfileCropPosition">
          <span>بزرگ</span>
        </label>
        <div class="profile-crop-actions">
          <button type="button" class="secondary-btn" :disabled="profilePhotoUploading" @click="cancelProfileCrop">انصراف</button>
          <button type="button" class="primary-btn" :disabled="profilePhotoUploading" @click="confirmProfileCrop">
            {{ profilePhotoUploading ? 'در حال ذخیره...' : 'تأیید و ذخیره عکس' }}
          </button>
        </div>
      </section>
    </div>
    <div v-if="showMediaModal" class="modal-overlay media-overlay" @click.self="closeMediaModal">
      <div class="media-modal" @click.stop>
        <div class="media-header">
          <div class="media-patient-head">
            <label class="profile-photo-picker" title="انتخاب عکس پروفایل">
              <input type="file" accept="image/*" :disabled="profilePhotoUploading" @change="uploadPatientProfilePhoto">
              <PatientAvatar :patient="activeMediaPatient" :size="64" />
              <small v-if="profilePhotoUploading">...</small>
            </label>
            <div>
            <h3>گالری پرونده</h3>
            <p>{{ activeMediaPatient.first_name }} {{ activeMediaPatient.last_name }} - پرونده {{ activeMediaPatient.file_number }}</p>
            </div>
          </div>
          <button class="close-btn" @click="closeMediaModal">×</button>
        </div>

        <div class="media-toolbar">
          <button class="secondary-btn media-back" :disabled="!mediaBreadcrumbs.length" @click="goMediaBack">بازگشت</button>
          <div class="media-path">
            <button type="button" @click="openMediaFolder(null)">ریشه</button>
            <span v-for="crumb in mediaBreadcrumbs" :key="crumb.id">
              /
              <button type="button" @click="openMediaFolder(crumb.id)">{{ crumb.name }}</button>
            </span>
          </div>
          <button
            type="button"
            class="media-all-btn"
            :class="{ active: mediaShowAll }"
            @click="toggleAllMedia"
          >
            {{ mediaShowAll ? 'نمایش پوشه فعلی' : 'مشاهده همه عکس‌ها' }}
          </button>
          <button
            type="button"
            class="media-compare-btn"
            :disabled="beforeAfterCompareLoading"
            @click="openBeforeAfterCompare"
          >
            {{ beforeAfterCompareLoading ? '...' : 'مقایسه قبل/بعد' }}
          </button>
        </div>

        <div class="media-layout" :class="{ 'comparison-layout': isComparisonPhotoFolder && !mediaShowAll }">
          <aside v-if="!isComparisonPhotoFolder || mediaShowAll" class="media-side">
            <div v-if="mediaFolderLevel === 'root'" class="folder-create guided-folder-create">
              <strong>ساخت فولدر تاریخ</strong>
              <button class="primary-btn" :disabled="mediaLoading" @click="createMediaDateFolder(mediaTodayJalaliDate())">
                ساخت فولدر به تاریخ امروز
              </button>
              <button class="secondary-btn" :disabled="mediaLoading" @click="showSpecificMediaDatePicker = !showSpecificMediaDatePicker">
                ساخت فولدر در تاریخ مشخص
              </button>
              <div v-if="showSpecificMediaDatePicker" class="specific-date-create">
                <date-picker
                  v-model="specificMediaDate"
                  format="jYYYY-jMM-jDD"
                  display-format="jYYYY-jMM-jDD"
                  input-class="birthdate-picker"
                  placeholder="انتخاب تاریخ"
                  auto-submit
                  color="#0f766e"
                />
                <button class="primary-btn" :disabled="mediaLoading || !specificMediaDate" @click="createMediaDateFolder(specificMediaDate)">
                  ایجاد فولدر تاریخ
                </button>
              </div>
            </div>

            <div v-else-if="mediaFolderLevel === 'date'" class="folder-create service-folder-create">
              <strong>انتخاب تگ‌های خدمات</strong>
              <p>تگ‌های موردنظر را انتخاب کنید؛ این تگ‌ها برای همه عکس‌ها و ویدئوهای این تاریخ ثبت می‌شوند.</p>
                <div class="shared-upload-setup">
                  <div class="shared-upload-head">
                    <div>
                      <strong>تنظیمات مشترک فایل‌ها</strong>
                      <small>این اطلاعات و تگ‌ها روی همه عکس‌ها و ویدئوهایی که در ادامه آپلود می‌کنید اعمال می‌شود.</small>
                    </div>
                  </div>

                  <div class="angle-tags-head shared-tags-head">
                    <div>
                      <strong>تگ‌ها را انتخاب کنید <b>*</b></strong>
                      <small>می‌توانید چند تگ را هم‌زمان انتخاب کنید.</small>
                    </div>
                    <input v-model.trim="serviceTagSearch" type="search" placeholder="جست‌وجوی تگ...">
                  </div>
                  <div class="shared-tag-toolbar">
                    <span>
                      <b>{{ mediaUpload.services.length }}</b>
                      تگ انتخاب شده
                    </span>
                    <div>
                      <button type="button" :disabled="!filteredMediaServiceTags.length" @click="selectAllVisibleMediaTags">
                        انتخاب همه
                      </button>
                      <button type="button" :disabled="!mediaUpload.services.length" @click="mediaUpload.services = []">
                        پاک کردن
                      </button>
                    </div>
                  </div>
                  <div class="angle-tag-options shared-tag-options">
                    <label
                      v-for="tag in filteredMediaServiceTags"
                      :key="`setup-tag-${tag.id}`"
                      :class="{ selected: mediaUpload.services.some(item => item.id === tag.id) }"
                    >
                      <input v-model="mediaUpload.services" type="checkbox" :value="tag">
                      <span>{{ tag.name }}</span>
                    </label>
                    <div v-if="!filteredMediaServiceTags.length" class="angle-tags-empty">
                      <span>تگی هنوز وارد نشده</span>
                      <button type="button" @click="openInventoryForTags"><b>+</b> تعریف تگ</button>
                    </div>
                  </div>

                  <div class="shared-meta-grid">
                    <textarea v-model="mediaUpload.description" placeholder="توضیحات مشترک همه عکس‌ها"></textarea>
                  </div>
                  <label class="feature-check no-consent-check">
                    <input v-model="mediaUpload.no_usage_consent" type="checkbox">
                    <span>عدم رضایت استفاده از تصاویر</span>
                  </label>

                  <button
                    type="button"
                    class="primary-btn shared-setup-submit"
                    :disabled="mediaLoading || !mediaUpload.services.length"
                    @click="createMediaServiceFolder()"
                  >
                    ساخت فولدر و ادامه آپلود
                  </button>
                  <small v-if="!mediaUpload.services.length" class="shared-tags-required">حداقل یک تگ انتخاب کنید.</small>
                </div>
            </div>

            <div v-else-if="!isComparisonPhotoFolder" class="folder-create guided-folder-note">
              <strong>ساخت فولدر دستی غیرفعال است</strong>
              <p>ساخت فولدر فقط از مسیر تاریخ و انتخاب تگ‌ها انجام می‌شود.</p>
            </div>

            <div v-if="canUploadInCurrentFolder && !isComparisonPhotoFolder" class="upload-card">
              <label>آپلود عکس یا ویدیو</label>
              <input type="file" multiple accept="image/*,video/*" @change="handleMediaFiles">
              <textarea v-model="mediaUpload.description" placeholder="توضیحات مشترک همه فایل‌ها"></textarea>
              <label class="feature-check no-consent-check">
                <input type="checkbox" v-model="mediaUpload.no_usage_consent">
                <span>عدم رضایت استفاده از تصاویر</span>
              </label>

              <div class="service-picker">
                <strong>تگ‌های مشترک عکس‌ها و ویدئوها</strong>
                <small>تگ‌های انتخابی روی همه فایل‌های این آپلود اعمال می‌شوند.</small>
                <div v-for="group in mediaServiceGroups" :key="group.section" class="service-group">
                  <span>{{ group.section }}</span>
                  <label v-for="service in group.items" :key="service.id">
                    <input type="checkbox" :value="service" v-model="mediaUpload.services">
                    {{ service.name }}
                  </label>
                </div>
              </div>

              <button class="primary-btn upload-btn" :disabled="mediaLoading || !selectedMediaFiles.length" @click="uploadMediaFiles">
                {{ mediaLoading ? 'در حال آپلود...' : `آپلود ${selectedMediaFiles.length || ''}` }}
              </button>
            </div>
          </aside>

          <main class="media-content">
            <div v-if="mediaLoading" class="media-loading">
              <span class="btn-spinner dark"></span>
              <span>در حال بارگذاری گالری...</span>
            </div>

            <section v-if="isComparisonPhotoFolder && !mediaShowAll" class="angle-upload-panel">
              <div v-if="angleUploadLoading" class="angle-upload-overlay" role="status" aria-live="polite">
                <div class="angle-upload-loader-card">
                  <span class="angle-upload-spinner"></span>
                  <strong>در حال آپلود عکس...</strong>
                  <small>لطفاً تا پایان ذخیره‌سازی صبر کنید</small>
                </div>
              </div>
              <div class="angle-panel-head">
                <div>
                  <h4>{{ currentComparisonStageLabel }}</h4>
                  <p>{{ completedAngleCount }} زاویه ثبت شده است؛ تکمیل همه زاویه‌ها الزامی نیست.</p>
                </div>
                <div class="angle-progress">
                  <span>{{ angleCompletionPercent }}%</span>
                  <div><i :style="{ width: `${angleCompletionPercent}%` }"></i></div>
                </div>
              </div>

              <div class="angle-service-tags" :class="{ collapsed: !showAngleCommonSettings }">
                <button type="button" class="angle-settings-summary" @click="showAngleCommonSettings = !showAngleCommonSettings">
                  <span class="angle-settings-icon">⚙</span>
                  <span class="angle-settings-summary-text">
                    <strong>تنظیمات مشترک عکس‌ها و ویدئوها</strong>
                    <small v-if="mediaUpload.services.length">
                      {{ mediaUpload.services.length }} تگ انتخاب شده
                      <template v-if="mediaUpload.no_usage_consent"> · عدم رضایت استفاده از تصاویر</template>
                    </small>
                    <small v-else class="settings-needed">برای آپلود، تنظیمات را تکمیل کنید</small>
                  </span>
                  <span class="angle-settings-action">{{ showAngleCommonSettings ? 'بستن' : 'ویرایش تنظیمات' }}</span>
                  <b :class="{ open: showAngleCommonSettings }">⌄</b>
                </button>

                <div v-if="showAngleCommonSettings" class="angle-settings-content">
                <section class="angle-settings-block angle-tags-block">
                <div class="angle-tags-head">
                  <div>
                    <strong>تگ خدمات <b>*</b></strong>
                    <small>برای جست‌وجو و مقایسه دقیق، حداقل یک خدمت را انتخاب کنید.</small>
                  </div>
                  <input v-model.trim="serviceTagSearch" type="search" placeholder="جست‌وجوی خدمت...">
                </div>
                <div class="angle-tag-options">
                  <label v-for="service in filteredMediaServiceTags" :key="`angle-tag-${service.id}`" :class="{ selected: mediaUpload.services.some(item => item.id === service.id) }">
                    <input v-model="mediaUpload.services" type="checkbox" :value="service">
                    <span>{{ service.name }}</span>
                  </label>
                  <div v-if="!filteredMediaServiceTags.length" class="angle-tags-empty">
                    <span>تگی هنوز وارد نشده</span>
                    <button type="button" @click="openInventoryForTags"><b>+</b> تعریف تگ</button>
                  </div>
                </div>
                </section>

                <section class="angle-common-meta angle-settings-block">
                  <strong>اطلاعات مشترک همه فایل‌ها</strong>
                  <small>این موارد را یک‌بار تنظیم کنید؛ برای تمام فایل‌های قبل، بعد و ویدئو ثبت می‌شوند.</small>
                  <div class="shared-meta-grid">
                    <textarea v-model="mediaUpload.description" placeholder="توضیحات مشترک همه عکس‌ها"></textarea>
                  </div>
                  <div class="angle-common-checks">
                    <label class="feature-check no-consent-check">
                      <input v-model="mediaUpload.no_usage_consent" type="checkbox">
                      <span>عدم رضایت استفاده از تصاویر</span>
                    </label>
                  </div>
                </section>
                <button type="button" class="angle-settings-done" :disabled="!mediaUpload.services.length" @click="showAngleCommonSettings = false">
                  اعمال و بستن تنظیمات
                </button>
                </div>
              </div>

              <div class="angle-workspace">
                <div class="angle-list">
                  <button
                    v-for="angle in facePhotoAngles"
                    :key="angle.key"
                    type="button"
                    class="angle-row"
                    :class="{ active: activePhotoAngleKey === angle.key, done: mediaForAngle(angle.key) }"
                    @click="activePhotoAngleKey = angle.key"
                  >
                    <span class="angle-mini" :style="{ transform: `rotate(${angle.rotate}deg)` }"></span>
                    <strong>{{ angle.label }}</strong>
                    <small>{{ angleHint(angle) }}</small>
                    <em>{{ mediaForAngle(angle.key) ? 'ثبت شده' : 'خالی' }}</em>
                  </button>
                </div>

                <div class="angle-stage-card">
                  <div class="angle-guide">
                    <div class="angle-guide-tabs">
                      <button type="button" :class="{ active: angleGuideMode === 'person' }" @click="angleGuideMode = 'person'">نمای شخص</button>
                      <button type="button" :class="{ active: angleGuideMode === 'top' }" @click="angleGuideMode = 'top'">نمای از بالا</button>
                    </div>
                    <div class="face-orbit">
                      <div v-if="angleGuideMode === 'person'" class="face-model" :style="{ transform: `rotateY(${activePhotoAngle.face}deg)` }">
                        <span class="hair"></span>
                        <span class="head"></span>
                        <span class="ear left"></span>
                        <span class="ear right"></span>
                        <span class="eye left"></span>
                        <span class="eye right"></span>
                        <span class="nose"></span>
                        <span class="mouth"></span>
                      </div>
                      <div v-else class="top-angle-guide">
                        <span class="camera-icon">▣</span>
                        <span class="camera-beam"></span>
                        <span class="head-top" :style="{ transform: `translate(-50%, -50%) rotate(${activePhotoAngle.rotate}deg)` }"></span>
                      </div>
                    </div>
                    <div class="angle-current">
                      <strong>{{ activePhotoAngle.label }}</strong>
                      <span>{{ angleInstruction(activePhotoAngle) }}</span>
                    </div>
                  </div>

                  <label
                    class="angle-dropzone"
                    :class="{ filled: activeAngleMedia, dragging: angleDragging, 'no-consent-photo': activeAngleMedia?.usage_consent === false }"
                    @dragover.prevent="angleDragging = true"
                    @dragleave.prevent="angleDragging = false"
                    @drop.prevent="handleAngleDrop($event, activePhotoAngle)"
                  >
                    <input type="file" accept="image/*" @change="uploadAnglePhoto($event, activePhotoAngle)">
                    <template v-if="activeAngleMedia">
                      <img :src="activeAngleMedia.url" :alt="activeAngleMedia.photo_angle_label || activePhotoAngle.label">
                      <b class="angle-check">✓</b>
                      <span class="angle-replace">تغییر عکس</span>
                      <button type="button" class="angle-remove" @click.prevent.stop="deleteMediaItem(activeAngleMedia)">حذف</button>
                    </template>
                    <template v-else>
                      <b>↑</b>
                      <strong>آپلود عکس این زاویه</strong>
                      <small>عکس «{{ activePhotoAngle.label }}» را رها کنید یا برای انتخاب فایل کلیک کنید</small>
                      <small>JPG · PNG · WEBP · حداکثر ۵۰ مگابایت</small>
                    </template>
                  </label>

                  <div class="angle-actions">
                    <div class="angle-nav-actions">
                      <button
                        type="button"
                        class="secondary-btn angle-nav-btn"
                        title="زاویه قبلی"
                        aria-label="زاویه قبلی"
                        :disabled="!previousPhotoAngle"
                        @click="selectAdjacentPhotoAngle(-1)"
                      >
                        →
                      </button>
                      <button
                        type="button"
                        class="secondary-btn angle-nav-btn"
                        title="زاویه بعدی"
                        aria-label="زاویه بعدی"
                        :disabled="!nextPhotoAngle"
                        @click="selectAdjacentPhotoAngle(1)"
                      >
                        ←
                      </button>
                    </div>
                    <button v-if="activeAngleMedia" type="button" class="media-edit-btn" @click="openMediaEdit(activeAngleMedia)">ویرایش اطلاعات</button>
                    <button type="button" class="angle-finish-btn" @click="finishAnglePhotos">ثبت</button>
                  </div>
                </div>
              </div>
            </section>

            <div class="media-list-search">
              <span aria-hidden="true">⌕</span>
              <input
                v-model.trim="mediaSearchQuery"
                type="search"
                placeholder="جست‌وجو در گالری؛ نام فایل، توضیحات، مسیر یا خدمات..."
              >
              <button v-if="mediaSearchQuery" type="button" @click="mediaSearchQuery = ''">×</button>
            </div>

            <div v-if="!mediaShowAll" class="folder-grid">
              <button
                v-for="folder in mediaFolders"
                :key="folder.id"
                class="folder-tile"
                :class="{ 'no-consent-folder': folder.has_no_usage_consent }"
                @click="openMediaFolder(folder.id)"
                @contextmenu.prevent.stop="openFolderContextMenu($event, folder)"
              >
                <span class="folder-icon"></span>
                <strong>{{ folder.name }}</strong>
                <small v-if="folder.has_no_usage_consent" class="folder-consent-warning">عدم رضایت استفاده از تصاویر</small>
              </button>
            </div>

            <div class="media-grid">
              <div v-for="item in filteredMediaItems" :key="item.id" class="media-item" :class="{ 'no-consent-media': item.usage_consent === false }">
                <div class="media-preview">
                  <img v-if="item.media_type === 'image'" :src="item.url" :alt="item.original_name">
                  <span v-if="item.usage_consent === false" class="no-consent-badge">عدم رضایت استفاده از تصاویر</span>
                  <video v-else :src="item.url" controls></video>
                  <button
                    class="star-btn"
                    :class="{ active: item.is_featured, loading: item.starLoading }"
                    :disabled="item.starLoading"
                    @click.stop="toggleMediaStar(item)"
                  >
                    {{ item.starLoading ? '...' : '★' }}
                  </button>
                </div>
                <div class="media-info">
                  <strong>{{ item.original_name || item.file_name }}</strong>
                  <em v-if="mediaShowAll || item.folder_path" class="media-path-label">
                    مسیر: {{ item.folder_path || 'ریشه' }}
                  </em>
                  <span v-if="mediaComparisonLabel(item)" class="media-angle-badge">
                    {{ mediaComparisonLabel(item) }}
                  </span>
                  <p>{{ item.description || 'بدون توضیح' }}</p>
                  <small>{{ formatMediaServices(item.services) }}</small>
                  <small class="media-audit">
                    ثبت: {{ item.uploaded_by_name || 'نامشخص' }} - {{ formatMediaDate(item.created_at) }}
                  </small>
                  <button type="button" class="media-edit-btn" @click="openMediaEdit(item)">
                    ویرایش
                  </button>
                </div>
              </div>
            </div>

            <div v-if="!mediaLoading && (!mediaFolders.length || mediaShowAll) && !filteredMediaItems.length" class="media-empty">
              هنوز فولدر یا فایلی برای این پرونده ثبت نشده است.
            </div>
          </main>
        </div>

        <div
          v-if="folderContextMenu.visible"
          class="folder-context-backdrop"
          @click="closeFolderContextMenu"
          @contextmenu.prevent="closeFolderContextMenu"
        >
          <div
            class="folder-context-menu"
            :style="{ left: `${folderContextMenu.x}px`, top: `${folderContextMenu.y}px` }"
            @click.stop
          >
            <strong>{{ folderContextMenu.folder?.name }}</strong>
            <button type="button" @click="deleteMediaFolder(folderContextMenu.folder)">
              حذف فولدر
            </button>
          </div>

          <section class="wallet-report">
            <div class="wallet-report-head">
              <strong>گزارش تراکنش‌ها</strong>
              <button type="button" :disabled="walletTransactionsLoading" @click="loadWalletTransactions">
                {{ walletTransactionsLoading ? 'در حال دریافت...' : 'به‌روزرسانی' }}
              </button>
            </div>
            <div class="wallet-report-list">
              <p v-if="walletTransactionsLoading">در حال دریافت گزارش کیف پول...</p>
              <p v-else-if="!walletTransactions.length">هنوز تراکنشی ثبت نشده است.</p>
              <article v-for="transaction in walletTransactions" :key="transaction.id" :class="transaction.type">
                <div>
                  <b>{{ transaction.type === 'deposit' ? 'واریز' : 'برداشت' }}</b>
                  <strong>{{ formatMoneyValue(transaction.amount) }}</strong>
                </div>
                <span>{{ transaction.description || '-' }}</span>
                <small>
                  {{ walletSourceLabel(transaction.source_type) }} · {{ formatMediaDate(transaction.created_at) }}
                  <template v-if="transaction.created_by_name"> · توسط {{ transaction.created_by_name }}</template>
                </small>
                <details v-if="transaction.metadata?.services?.length">
                  <summary>جزئیات خدمات و محاسبه</summary>
                  <div v-for="(service, index) in transaction.metadata.services" :key="index">
                    {{ service.service || service }}
                    <template v-if="service.commission_type">
                      — {{ service.commission_type === 'percent' ? `${service.commission_value}٪` : `${formatMoneyValue(service.commission_value)} ثابت` }}
                      — پاداش {{ formatMoneyValue(service.reward_amount) }}
                    </template>
                  </div>
                </details>
              </article>
            </div>
          </section>
        </div>
      </div>
    </div>

    <div v-if="showBeforeAfterCompare" class="modal-overlay compare-overlay" @click.self="closeBeforeAfterCompare">
      <section class="compare-modal" @click.stop>
        <header class="compare-head">
          <div>
            <h3>مقایسه قبل و بعد</h3>
            <p>{{ beforeAfterCompareScopeLabel }}</p>
          </div>
          <button class="close-btn" type="button" @click="closeBeforeAfterCompare">×</button>
        </header>

        <div v-if="beforeAfterCompareLoading" class="compare-state">
          <span class="btn-spinner dark"></span>
          <b>در حال آماده‌سازی مقایسه...</b>
        </div>

        <div v-else-if="activeBeforeAfterPair" class="compare-single-view">
          <article class="compare-pair-card">
            <div class="compare-pair-title">
              <strong>{{ activeBeforeAfterPair.angle || 'زاویه بدون عنوان' }}</strong>
              <span>{{ activeBeforeAfterPair.path || 'ریشه' }}</span>
            </div>
            <div class="compare-photo-grid">
              <figure :class="{ empty: !activeBeforeAfterPair.before }">
                <b>قبل</b>
                <img v-if="activeBeforeAfterPair.before" :src="activeBeforeAfterPair.before.url" :alt="activeBeforeAfterPair.before.original_name || 'عکس قبل'">
                <button v-else type="button" class="compare-upload-missing" @click="goUploadMissingComparePhoto(activeBeforeAfterPair, 'before')">آپلود</button>
              </figure>
              <figure :class="{ empty: !activeBeforeAfterPair.after }">
                <b>بعد</b>
                <img v-if="activeBeforeAfterPair.after" :src="activeBeforeAfterPair.after.url" :alt="activeBeforeAfterPair.after.original_name || 'عکس بعد'">
                <button v-else type="button" class="compare-upload-missing" @click="goUploadMissingComparePhoto(activeBeforeAfterPair, 'after')">آپلود</button>
              </figure>
            </div>
          </article>
          <div class="compare-nav">
            <button type="button" :disabled="beforeAfterCompareIndex <= 0" @click="beforeAfterCompareIndex -= 1">قبلی</button>
            <span>{{ beforeAfterCompareIndex + 1 }} / {{ beforeAfterPairs.length }}</span>
            <button type="button" :disabled="beforeAfterCompareIndex >= beforeAfterPairs.length - 1" @click="beforeAfterCompareIndex += 1">بعدی</button>
          </div>
        </div>

        <div v-else class="compare-state">
          <b>جفت عکس قبل و بعدی برای این مسیر پیدا نشد.</b>
        </div>
      </section>
    </div>

    <div v-if="showMediaEditModal" class="modal-overlay media-edit-overlay" @click.self="closeMediaEdit">
      <div class="media-edit-modal" @click.stop>
        <div class="media-header">
          <div>
            <h3>ویرایش فایل</h3>
            <p>
              ثبت شده توسط {{ editMediaForm.uploaded_by_name || 'نامشخص' }}
              - {{ formatMediaDate(editMediaForm.created_at) }}
            </p>
          </div>
          <button class="close-btn" @click="closeMediaEdit">×</button>
        </div>

        <div class="media-edit-body">
          <div class="media-edit-preview">
            <img v-if="editMediaForm.media_type === 'image'" :src="editMediaForm.url" :alt="editMediaForm.original_name">
            <video v-else :src="editMediaForm.url" controls></video>
          </div>

          <div class="media-edit-form">
            <label>
              جایگزینی عکس یا ویدیو
              <input type="file" accept="image/*,video/*" @change="handleEditMediaFile">
            </label>

            <div class="media-meta-grid">
              <select v-model="editMediaForm.comparison_stage">
                <option value="">مرحله مقایسه</option>
                <option value="before">قبل</option>
                <option value="after">بعد</option>
              </select>

            </div>

            <textarea v-model="editMediaForm.description" placeholder="توضیحات"></textarea>

            <label class="feature-check">
              <input type="checkbox" v-model="editMediaForm.is_featured">
              <span>ستاره‌دار / بهترین‌ها</span>
            </label>
            <label class="feature-check no-consent-check"><input type="checkbox" v-model="editMediaForm.no_usage_consent"><span>عدم رضایت استفاده از تصاویر</span></label>

            <div v-if="false" class="service-picker">
              <strong>خدمات مرتبط</strong>
              <div v-for="group in mediaServiceGroups" :key="`edit-${group.section}`" class="service-group">
                <span>{{ group.section }}</span>
                <label v-for="service in group.items" :key="`edit-${service.id}`">
                  <input type="checkbox" :value="service.key" v-model="editMediaForm.service_keys">
                  {{ service.name }}
                </label>
              </div>
            </div>

            <div class="modal-actions">
              <button class="secondary-btn" @click="closeMediaEdit">انصراف</button>
              <button class="primary-btn" :disabled="mediaEditLoading" @click="saveMediaEdit">
                {{ mediaEditLoading ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import Swal from 'sweetalert2'
import DatePicker from 'vue3-persian-datetime-picker'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import dayjs from 'dayjs'
import jalaliday from 'jalaliday'
import moment from 'moment-jalaali'
import iranCitiesData from '@/data/iran-cities.json'
import PatientAvatar from './PatientAvatar.vue'

const normalizePersianText = value => String(value || '')
  .replace(/ي/g, 'ی')
  .replace(/ك/g, 'ک')

const iranCities = iranCitiesData
  .map(item => ({
    id: `${item.provinceId}-${item.cityId}`,
    name: normalizePersianText(item.cityName),
    province: normalizePersianText(item.provinceName),
    displayName: `${normalizePersianText(item.cityName)} - ${normalizePersianText(item.provinceName)}`
  }))
  .sort((a, b) => a.name.localeCompare(b.name, 'fa'))

dayjs.extend(jalaliday)

const FACE_PHOTO_ANGLES = [
  { key: 'left_profile', label: 'نیم‌رخ چپ', degrees: 90, side: 'چپ', rotate: -90, face: -72 },
  { key: 'left_three_quarter_60', label: 'سه‌رخ اول چپ', degrees: 60, side: 'چپ', rotate: -60, face: -60 },
  { key: 'left_three_quarter_30', label: 'سه‌رخ دوم چپ', degrees: 30, side: 'چپ', rotate: -30, face: -30 },
  { key: 'front', label: 'تمام‌رخ', degrees: 0, side: '', rotate: 0, face: 0 },
  { key: 'right_three_quarter_30', label: 'سه‌رخ اول راست', degrees: 30, side: 'راست', rotate: 30, face: 30 },
  { key: 'right_three_quarter_60', label: 'سه‌رخ دوم راست', degrees: 60, side: 'راست', rotate: 60, face: 60 },
  { key: 'right_profile', label: 'نیم‌رخ راست', degrees: 90, side: 'راست', rotate: 90, face: 72 },
  { key: 'other', label: 'سایر', degrees: 0, side: '', rotate: 0, face: 0 },
  { key: 'body_shape', label: 'شیپ بدن', degrees: 0, side: '', rotate: 0, face: 0 }
]

export default {
  components:{
    DatePicker,
    Multiselect,
    PatientAvatar
  },

  props: {
    permissions: {
      type: Array,
      default: () => []
    },
    openPatientRequest: {
      type: Object,
      default: null
    },
    enabledFeatures: {
      type: Array,
      default: null
    }
  },

  data() {
    return {
      openingRequestedProfile: Boolean(this.openPatientRequest),
      // آبجکت فیلدهای انتخابی که از سمت دیتابیس ست می‌شود
      activeProfileFields: {
        national_id: false,
        marriage_date: false,
        education: false,
        father_name: false,
        second_phone: false,
        address: false,
        city: false
      },
      patientRequiredFields: {},

      cityOptions: iranCities,
      selectedCity: iranCities.find(city => city.name === 'تهران' && city.province === 'تهران') || null,
      selectedEditCity: null,

      form: {
        first_name: '',
        last_name: '',
        phone: '',
        file_number: '',
        gender: '',
        birth_date: '',
        area: '',
        city: 'تهران',
        financial_status: '',
        customer_level: '',
        patient_history: '',
        medical_history: '',
        // فیلدهای تکمیلی اضافه شده برای v-model فرم
        national_id: '',
        father_name: '',
        marriage_date: '',
        education: '',
        second_phone: '',
        address: ''
      },

      search: {
        q: '',
        file_number: '',
        phone: '',
        national_id: ''
      },

      columnLabels: {
        city: 'شهر',
        id: 'شناسه',
        file_number: 'شماره پرونده',
        first_name: 'نام',
        last_name: 'نام خانوادگی',
        phone: 'شماره تماس',
        gender: 'جنسیت',
        birth_date: 'تاریخ تولد',
        area: 'محدوده سکونت',
        financial_status: 'وضعیت مالی',
        patient_history: 'تیپ شخصیتی',
        medical_history: 'سوابق پزشکی',
        national_id: 'کد ملی',
        father_name: 'نام پدر',
        marriage_date: 'تاریخ ازدواج',
        education: 'تحصیلات',
        second_phone: 'شماره تماس دوم',
        address: 'آدرس',
        wallet_balance: 'موجودی کیف پول',
        outstanding_debt: 'بدهکاری کل',
        created_at: 'تاریخ تشکیل پرونده',
        updated_at: 'آخرین ویرایش'
      },

      searchResults: [],
      searchLoading: false,
      searchSearched: false,
      showEditModal: false,
      editPatient: {},
      showCustomerLevelModal: false,
      activeCustomerLevelPatient: {},
      selectedCustomerLevel: '',
      customerLevelSaving: false,
      customerLevelOptions: [
        {
          value: 'problematic',
          label: 'مشتری دردسرساز',
          description: 'برای مراجعه‌کننده‌هایی که نیاز به دقت و پیگیری بیشتری دارند.',
          class: 'is-problematic'
        },
        {
          value: 'blue',
          label: 'مشتری آبی',
          description: 'مشتری عادی/قابل توجه با برچسب آبی در پرونده.',
          class: 'is-blue'
        },
        {
          value: 'cip',
          label: 'مشتری CIP',
          description: 'مشتری ویژه و با اولویت بالاتر در پیگیری‌ها.',
          class: 'is-cip'
        }
      ],
      appointmentResults: [],
      appointmentLoading: false,
      activeAppointmentFilter: null,
      selectedAppointmentFilters: {
        services: [],
        doctors: [],
        consultants: [],
        amount: [],
        payment: [],
        status: [],
        arrived_at: [],
        done: [],
        completed_at: [],
        debt: [],
      },
      profileViewOpen: false,
      activePatientProfile: null,
      latestProfilePhotos: [],
      latestProfilePhotosLoading: false,

      showWalletModal: false,
      activeWalletPatient: {},
      walletBalance: 0, 
      walletAmount: null,
      walletTransactions: [],
      walletTransactionsLoading: false,

      showMediaModal: false,
      activeMediaPatient: {},
      currentMediaFolderId: null,
      mediaBreadcrumbs: [],
      mediaFolders: [],
      mediaItems: [],
      mediaSearchQuery: '',
      mediaShowAll: false,
      folderContextMenu: { visible: false, x: 0, y: 0, folder: null },
      mediaSections: [],
      mediaServiceGroups: [],
      expandedMediaSectionKeys: [],
      selectedMediaTreeKey: '',
      mediaLoading: false,
      showBeforeAfterCompare: false,
      beforeAfterCompareLoading: false,
      beforeAfterCompareItems: [],
      beforeAfterCompareIndex: 0,
      profilePhotoUploading: false,
      profileCrop: {
        open: false,
        sourceUrl: '',
        image: null,
        zoom: 1,
        x: 0,
        y: 0,
        dragging: false,
        pointerX: 0,
        pointerY: 0
      },
      showMediaEditModal: false,
      mediaEditLoading: false,
      editMediaFile: null,
      editMediaForm: {},
      newFolderName: '',
      specificMediaDate: '',
      showSpecificMediaDatePicker: false,
      selectedMediaFiles: [],
      selectedMediaFolderService: null,
      activePhotoAngleKey: FACE_PHOTO_ANGLES[3].key,
      angleGuideMode: 'top',
      angleDragging: false,
      angleUploadLoading: false,
      showAngleCommonSettings: false,
      serviceTagSearch: '',
      mediaUpload: {
        description: '',
        no_usage_consent: false,
        services: []
      }
    }
  },

  computed: {
    canViewPatientPhone() {
      return this.permissions.includes("patients.view_phone");
    },

    canUseGallery() {
      return this.featureEnabled('gallery')
    },

    canUseBeauty() {
      return this.featureEnabled('beauty')
    },

    patientResultColumns() {
      if (!this.searchResults.length) return []

      const hidden = new Set([
        'profile_photo_path',
        'profile_photo_url',
        'profile_thumbnail_path',
        'profile_thumbnail_url',
        'avatar_url',
        'customer_level'
      ])

      return Object.keys(this.searchResults[0]).filter(
        key => !hidden.has(key) && Boolean(this.columnLabels[key])
      )
    },

    mediaFolderLevel() {
      if (!this.currentMediaFolderId || !this.mediaBreadcrumbs.length) return 'root'
      const current = this.mediaBreadcrumbs[this.mediaBreadcrumbs.length - 1] || {}
      if (current.folder_type === 'date' || this.mediaBreadcrumbs.length === 1) return 'date'
      if (current.folder_type === 'service' || this.mediaBreadcrumbs.length === 2) return 'service'
      return 'final'
    },

    canUploadInCurrentFolder() {
      return this.mediaFolderLevel === 'final'
    },

    currentMediaFolderType() {
      const current = this.mediaBreadcrumbs[this.mediaBreadcrumbs.length - 1] || {}
      return current.folder_type || ''
    },

    currentComparisonStage() {
      if (this.currentMediaFolderType === 'before_photo') return 'before'
      if (this.currentMediaFolderType === 'after_photo') return 'after'
      return ''
    },

    currentComparisonStageLabel() {
      return this.currentComparisonStage === 'before' ? 'عکس‌های قبل' : 'عکس‌های بعد'
    },

    isComparisonPhotoFolder() {
      return ['before_photo', 'after_photo'].includes(this.currentMediaFolderType)
    },

    beforeAfterCompareScopeLabel() {
      return this.currentBeforeAfterBasePath
        ? this.currentBeforeAfterBasePath
        : 'همه عکس‌های پرونده'
    },

    currentBeforeAfterBasePath() {
      const crumbs = [...this.mediaBreadcrumbs]
      const currentType = crumbs[crumbs.length - 1]?.folder_type
      if (['before_photo', 'after_photo'].includes(currentType)) {
        crumbs.pop()
      }
      return crumbs.map(item => item.name).join(' / ')
    },

    beforeAfterPairs() {
      const currentPath = this.currentBeforeAfterBasePath
      const scopedItems = (this.beforeAfterCompareItems || [])
        .filter(item => item.media_type === 'image' && item.url && ['before', 'after'].includes(item.comparison_stage))
        .filter(item => {
          if (!currentPath) return true
          return this.beforeAfterBasePath(item.folder_path).startsWith(currentPath)
        })

      const pairs = new Map()
      scopedItems.forEach(item => {
        const path = this.beforeAfterBasePath(item.folder_path) || 'ریشه'
        const angle = item.photo_angle_label || this.comparisonStageLabel(item.comparison_stage) || 'بدون زاویه'
        const angleKey = item.photo_angle_key || angle
        const key = `${path}|${angleKey}`
        if (!pairs.has(key)) {
          pairs.set(key, { key, path, angle, angleKey, before: null, after: null })
        }
        const pair = pairs.get(key)
        const stage = item.comparison_stage
        const existing = pair[stage]
        if (!existing || new Date(item.created_at || 0) > new Date(existing.created_at || 0)) {
          pair[stage] = item
        }
      })

      return Array.from(pairs.values())
        .sort((a, b) => String(a.path).localeCompare(String(b.path), 'fa') || String(a.angle).localeCompare(String(b.angle), 'fa'))
    },

    activeBeforeAfterPair() {
      return this.beforeAfterPairs[this.beforeAfterCompareIndex] || null
    },

    facePhotoAngles() {
      return FACE_PHOTO_ANGLES
    },

    filteredMediaServiceTags() {
      const query = this.normalizeMediaSearch(this.serviceTagSearch)
      const services = this.mediaServiceGroups.flatMap(group => group.items || [])
      if (!query) return services
      return services.filter(service => this.normalizeMediaSearch(service.name).includes(query))
    },

    mediaLeafSections() {
      return (this.mediaSections || []).filter(section => Number(section.level || 2) === 2)
    },

    mediaNeedsLeafSection() {
      return !this.selectedMediaFolderService
    },

    mediaInventoryTreeNodes() {
      const nodes = []
      const roots = this.mediaRootSections.length
        ? this.mediaRootSections
        : this.mediaLeafSections

      const walk = (sections, level) => {
        sections.forEach(section => {
          const key = this.mediaSectionKey(section)
          const children = this.mediaChildSections(key)
          nodes.push({ section, level: Number(section.level || level), hasChildren: children.length > 0 })
          if (children.length && this.isMediaSectionExpanded(section)) {
            walk(children, Number(section.level || level) + 1)
          }
        })
      }

      walk(roots, 1)
      return nodes
    },

    mediaRootSections() {
      return (this.mediaSections || [])
        .filter(section => Number(section.level || 2) === 1)
        .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0) || Number(a.id || 0) - Number(b.id || 0))
    },

    activePhotoAngle() {
      return this.facePhotoAngles.find(angle => angle.key === this.activePhotoAngleKey) || this.facePhotoAngles[3]
    },

    activeAngleMedia() {
      return this.mediaForAngle(this.activePhotoAngle.key)
    },

    completedAngleCount() {
      return this.facePhotoAngles.filter(angle => this.mediaForAngle(angle.key)).length
    },

    angleCompletionPercent() {
      return Math.round((this.completedAngleCount / this.facePhotoAngles.length) * 100)
    },

    previousPhotoAngle() {
      const index = this.facePhotoAngles.findIndex(angle => angle.key === this.activePhotoAngleKey)
      return index > 0 ? this.facePhotoAngles[index - 1] : null
    },

    nextPhotoAngle() {
      const index = this.facePhotoAngles.findIndex(angle => angle.key === this.activePhotoAngleKey)
      return index >= 0 && index < this.facePhotoAngles.length - 1 ? this.facePhotoAngles[index + 1] : null
    },

    profileTotalAmount() {
      return this.appointmentResults.reduce((sum, item) => {
        return sum + this.numberValue(item.amount)
      }, 0)
    },

    appointmentFilterColumns() {
      return [
        { key: 'services', label: 'خدمات' },
        { key: 'doctors', label: 'پزشک' },
        { key: 'consultants', label: 'مشاور' },
        { key: 'amount', label: 'مبلغ' },
        { key: 'payment', label: 'پرداخت' },
        { key: 'status', label: 'وضعیت' },
        { key: 'arrived_at', label: 'زمان آمدن' },
        { key: 'done', label: 'کار انجام‌شده' },
        { key: 'completed_at', label: 'زمان انجام' },
        { key: 'debt', label: 'بدهکاری' },
      ]
    },

    filteredAppointmentResults() {
      return (this.appointmentResults || []).filter(item => {
        return this.appointmentFilterColumns.every(column => {
          const selected = this.selectedAppointmentFilters[column.key] || []
          if (!selected.length) return true
          return selected.includes(this.appointmentFilterText(item, column.key))
        })
      })
    },

    dueCheckRows() {
      return this.filteredAppointmentResults.filter(item => this.appointmentCheckAlert(item))
    },

    profileStats() {
      const rows = this.appointmentResults || []
      const totalVisits = rows.length
      const paidAmount = rows.reduce((sum, item) => sum + this.numberValue(item.amount), 0)
      const referrals = rows.filter(item =>
        String(item.referrer_phone || '').trim() ||
        this.numberValue(item.referral_score) > 0
      ).length
      const commission = rows.reduce((sum, item) => sum + this.numberValue(item.referral_score), 0)
      const canceled = rows.filter(item => this.isCanceledStatus(item.status)).length
      const discountTotal = rows.reduce((sum, item) => sum + this.numberValue(item.discount), 0)
      const originalTotal = rows.reduce((sum, item) => {
        const original = this.numberValue(item.original_amount)
        return sum + (original || this.numberValue(item.amount) + this.numberValue(item.discount))
      }, 0)
      const avgDiscount = originalTotal > 0 ? (discountTotal / originalTotal) * 100 : 0

      const leadHours = rows
        .map(item => this.appointmentLeadHours(item))
        .filter(value => Number.isFinite(value) && value >= 0)
      const avgLeadHours = leadHours.length
        ? leadHours.reduce((sum, value) => sum + value, 0) / leadHours.length
        : null

      return {
        totalVisits,
        paidAmount,
        referrals,
        commission,
        canceled,
        cancelRate: totalVisits ? (canceled / totalVisits) * 100 : 0,
        avgLeadHours,
        avgDiscount
      }
    },

    profileStatCards() {
      const stats = this.profileStats

      return [
        {
          key: 'visits',
          title: 'تعداد دفعات مراجعه',
          value: `${stats.totalVisits} مرتبه`,
          hint: 'کل نوبت‌های ثبت‌شده',
          accent: '#2563eb',
          percent: this.statPercent(stats.totalVisits, Math.max(stats.totalVisits, 1))
        },
        {
          key: 'paid',
          title: 'مبلغ پرداختی تا امروز',
          value: this.formatMoneyValue(stats.paidAmount),
          hint: 'جمع مبلغ خدمات',
          accent: '#10b981',
          percent: this.statPercent(stats.paidAmount, Math.max(stats.paidAmount + this.totalDebtAmount(), 1))
        },
        {
          key: 'referrals',
          title: 'تعداد معرفی‌ها',
          value: `${stats.referrals} نفر`,
          hint: 'براساس ردیف‌های دارای معرف/پورسانت',
          accent: '#8b5cf6',
          percent: this.statPercent(stats.referrals, Math.max(stats.totalVisits, 1))
        },
        {
          key: 'commission',
          title: 'پورسانت گرفته',
          value: this.formatMoneyValue(stats.commission),
          hint: 'جمع امتیاز/پورسانت معرفی',
          accent: '#f59e0b',
          percent: this.statPercent(stats.commission, Math.max(stats.paidAmount, 1))
        },
        {
          key: 'canceled',
          title: 'دفعات کنسلی',
          value: `${stats.canceled} بار`,
          hint: `${this.formatPercent(stats.cancelRate)} از کل نوبت‌ها`,
          accent: '#ef4444',
          percent: this.statPercent(stats.canceled, Math.max(stats.totalVisits, 1))
        },
        {
          key: 'lead',
          title: 'نرخ دیرکرد / فاصله نوبت',
          value: this.formatLeadTime(stats.avgLeadHours),
          hint: 'میانگین فاصله ثبت تا زمان نوبت',
          accent: '#06b6d4',
          percent: this.statPercent(stats.avgLeadHours || 0, 72)
        },
        {
          key: 'discount',
          title: 'میانگین تخفیف',
          value: this.formatPercent(stats.avgDiscount),
          hint: 'درصد تخفیف نسبت به مبلغ اولیه',
          accent: '#ec4899',
          percent: this.statPercent(stats.avgDiscount, 100)
        }
      ]
    },

    filteredMediaItems() {
      const query = this.normalizeMediaSearch(this.mediaSearchQuery)
      if (!query) return this.mediaItems

      return this.mediaItems.filter(item => {
        const services = this.formatMediaServices(item.services || [])
        const searchable = [
          item.original_name,
          item.file_name,
          item.folder_path,
          item.description,
          item.media_type,
          item.comparison_stage,
          item.photo_angle_label,
          services,
          item.uploaded_by_name,
          this.formatMediaDate(item.created_at)
        ].join(' ')

        const normalized = this.normalizeMediaSearch(searchable)
        return query.split(' ').every(term => normalized.includes(term))
      })
    }
  },

  mounted(){
    this.fetchNextFileNumber()
    this.fetchActiveProfileFields() // لود فیلدهای فعال در بدو ورود به صفحه
  },

  watch: {
    openPatientRequest: {
      immediate: true,
      deep: true,
      handler(request) {
        if (request) this.openRequestedPatientProfile(request)
      }
    }
  },

  methods: {
    openInventoryForTags() {
      localStorage.setItem('inventory-open-service-tags', '1')
      this.showMediaModal = false
      this.$emit('open-page', 'Anbar')
    },

    displayPatientPhone(value) {
      const text = String(value || "").trim();
      if (!text) return "";
      if (this.canViewPatientPhone || text.includes("•") || text.includes("*")) return text;
      const digits = text.replace(/\D/g, "");
      if (digits.length <= 4) return "••••";
      return `${digits.slice(0, 3)}••••${digits.slice(-2)}`;
    },

    featureEnabled(feature) {
      if (!feature || !Array.isArray(this.enabledFeatures)) return true
      const aliases = {
        chat: 'patients',
        staffEval: 'resources',
        tasks: 'followups',
        campaign: 'automation',
        aiReport: 'beauty'
      }
      return this.enabledFeatures.map(item => aliases[item] || item).includes(feature)
    },

    selectCity(city) {
      this.form.city = city.name
    },

    selectEditCity(city) {
      this.editPatient.city = city.name
    },

    // متد گرفتن وضعیت چک‌باکس‌ها از API لاراول
    async fetchActiveProfileFields() {
      try {
        const res = await fetch('/api/settings')
        if (res.ok) {
          const data = await res.json()
          if (data.profile_fields) {
            this.activeProfileFields = data.profile_fields
          }
          this.patientRequiredFields = data.patient_required_fields || {}
        }
      } catch (e) {
        console.error('خطا در دریافت وضعیت فیلدهای پرونده:', e)
      }
    },

    resetForm(){
      const tehran = this.cityOptions.find(city => city.name === 'تهران' && city.province === 'تهران') || null
      this.form = {
        first_name: '',
        last_name: '',
        phone: '',
        file_number: '',
        gender: '',
        birth_date: '',
        area: '',
        city: 'تهران',
        financial_status: '',
        customer_level: '',
        patient_history: '',
        medical_history: '',
        national_id: '',
        father_name: '',
        marriage_date: '',
        education: '',
        second_phone: '',
        address: ''
      }
      this.selectedCity = tehran
    },

    openWalletModal(patient) {
      this.activeWalletPatient = patient;
      this.walletBalance = patient.wallet_balance || 0; 
      this.walletAmount = null; 
      this.showWalletModal = true;
      this.loadWalletTransactions();
    },

    walletSourceLabel(source) {
      return { referral_reward: 'پاداش معرفی', appointment_payment: 'پرداخت نوبت', reversal: 'تراکنش برگشتی', manual: 'ثبت دستی' }[source] || 'کیف پول'
    },

    async loadWalletTransactions() {
      if (!this.activeWalletPatient?.id) return
      this.walletTransactionsLoading = true
      try {
        const res = await fetch(`/api/patients/${this.activeWalletPatient.id}/wallet/transactions`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت تراکنش‌ها انجام نشد')
        this.walletTransactions = data.transactions || []
        this.walletBalance = Number(data.wallet_balance || 0)
      } catch (error) {
        console.error(error)
      } finally {
        this.walletTransactionsLoading = false
      }
    },

    customerLevelLabel(value) {
      const automaticLabels = { blue: 'مشتری آبی', silver: 'مشتری نقره‌ای', gold: 'مشتری طلایی' }
      if (automaticLabels[value]) return automaticLabels[value]
      const option = this.customerLevelOptions.find(item => item.value === value)
      return option ? option.label : 'بدون سطح'
    },

    customerLevelClass(value) {
      return value ? `level-${value}` : 'level-empty'
    },

    openCustomerLevelModal(patient) {
      this.activeCustomerLevelPatient = patient || {}
      this.selectedCustomerLevel = patient?.customer_level || 'silver'
      this.showCustomerLevelModal = true
    },

    closeCustomerLevelModal() {
      if (this.customerLevelSaving) return
      this.showCustomerLevelModal = false
      this.activeCustomerLevelPatient = {}
      this.selectedCustomerLevel = ''
    },

    async toggleProblematicCustomer() {
      const patient = this.activePatientProfile
      if (!patient?.id || this.customerLevelSaving) return

      const isProblematic = patient.customer_level === 'problematic'
      const confirmation = await Swal.fire({
        icon: isProblematic ? 'question' : 'warning',
        title: isProblematic ? 'برداشتن برچسب مشتری دردسرساز؟' : 'مشتری دردسرساز است؟',
        text: isProblematic
          ? 'این برچسب از پرونده برداشته می‌شود و بعداً می‌توانید دوباره آن را فعال کنید.'
          : 'پس از تأیید، این مراجعه‌کننده در پرونده و جستجو به‌عنوان مشتری دردسرساز مشخص می‌شود.',
        showCancelButton: true,
        confirmButtonText: isProblematic ? 'بله، برداشته شود' : 'بله، ثبت شود',
        cancelButtonText: 'انصراف',
        confirmButtonColor: isProblematic ? '#2563eb' : '#dc2626',
        reverseButtons: true
      })
      if (!confirmation.isConfirmed) return

      this.customerLevelSaving = true
      try {
        const nextLevel = isProblematic ? null : 'problematic'
        const res = await fetch(`/api/patients/${patient.id}/customer-level`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ customer_level: nextLevel })
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'تغییر وضعیت مشتری ذخیره نشد')

        const updated = data.patient || { ...patient, customer_level: nextLevel }
        this.activePatientProfile = { ...this.activePatientProfile, ...updated }
        const resultPatient = this.searchResults.find(item => item.id === updated.id)
        if (resultPatient) Object.assign(resultPatient, updated)

        Swal.fire({
          icon: 'success',
          title: isProblematic ? 'برچسب برداشته شد' : 'مشتری دردسرساز ثبت شد',
          timer: 1500,
          showConfirmButton: false
        })
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'تغییر وضعیت مشتری انجام نشد' })
      } finally {
        this.customerLevelSaving = false
      }
    },

    async saveCustomerLevel() {
      if (!this.activeCustomerLevelPatient?.id) return

      this.customerLevelSaving = true
      try {
        const res = await fetch(`/api/patients/${this.activeCustomerLevelPatient.id}/customer-level`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ customer_level: this.selectedCustomerLevel })
        })

        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'نوع مشتری ذخیره نشد')

        const updated = data.patient || { ...this.activeCustomerLevelPatient, customer_level: this.selectedCustomerLevel }
        const resultPatient = this.searchResults.find(item => item.id === updated.id)
        if (resultPatient) Object.assign(resultPatient, updated)
        if (this.activePatientProfile?.id === updated.id) {
          this.activePatientProfile = { ...this.activePatientProfile, ...updated }
        }

        Swal.fire({ icon: 'success', title: 'ذخیره شد', text: 'نوع مشتری با موفقیت بروزرسانی شد.', timer: 1600, showConfirmButton: false })
        this.showCustomerLevelModal = false
        this.activeCustomerLevelPatient = {}
        this.selectedCustomerLevel = ''
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'ذخیره نوع مشتری انجام نشد' })
      } finally {
        this.customerLevelSaving = false
      }
    },

    openPatientProfile(patient) {
      this.activePatientProfile = patient
      this.profileViewOpen = true
      this.fetchLatestProfilePhotos(patient)
      this.$nextTick(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' })
      })
      this.fetchPatientAppointmentsFor(patient)
    },

    async fetchLatestProfilePhotos(patient) {
      this.latestProfilePhotos = []
      if (!patient?.id) return

      this.latestProfilePhotosLoading = true
      try {
        const res = await fetch(`/api/patients/${patient.id}/media?all=1`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت عکس‌های پرونده انجام نشد')

        this.latestProfilePhotos = (data.media || [])
          .filter(item => item.media_type === 'image' && item.url)
          .sort((a, b) => new Date(b.created_at || b.updated_at || 0) - new Date(a.created_at || a.updated_at || 0))
          .slice(0, 6)
      } catch (error) {
        console.error(error)
      } finally {
        this.latestProfilePhotosLoading = false
      }
    },

    async openRequestedPatientProfile(request) {
      const fileNumber = String(request?.file_number || request?.fileNumber || '').trim()
      const phone = String(request?.phone || '').trim()

      if (!fileNumber && !phone) return

      this.openingRequestedProfile = true

      this.search.file_number = fileNumber
      this.search.phone = phone
      this.searchSearched = false
      this.searchResults = []

      try {
        const params = new URLSearchParams()
        if (fileNumber) params.append('file_number', fileNumber)
        if (phone) params.append('phone', phone)

        const res = await fetch(`/api/patients/search?${params.toString()}`)
        const data = await res.json()
        const rows = Array.isArray(data) ? data : []

        this.searchResults = rows.map(this.sanitizePatientSearchRow)
        this.searchSearched = true

        if (rows.length) {
          this.openPatientProfile(rows[0])
          if (request?.open_media) {
            await this.$nextTick()
            await this.openMediaModal(rows[0])
          }
        } else {
          Swal.fire({
            icon: 'warning',
            title: 'پرونده پیدا نشد',
            text: 'برای این نوبت پرونده‌ای با شماره پرونده یا موبایل ثبت نشده است.',
            timer: 2600,
            showConfirmButton: false
          })
        }
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: 'باز کردن پرونده انجام نشد', timer: 2600, showConfirmButton: false })
      } finally {
        this.openingRequestedProfile = false
      }
    },

    closePatientProfile() {
      this.profileViewOpen = false
      this.activePatientProfile = null
      this.latestProfilePhotos = []
      this.$nextTick(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' })
      })
    },

    patientFullName(patient) {
      return `${patient?.first_name || ''} ${patient?.last_name || ''}`.trim() || 'مراجعه کننده'
    },

    patientAge(birthDate) {
      if (!birthDate) return '-'

      let parsed
      try {
        const normalized = this.toEnglishDigits(String(birthDate).trim()).replace(/-/g, '/')
        parsed = moment(normalized, ['jYYYY/jMM/jDD', 'jYYYY/jM/jD', 'YYYY/MM/DD', 'YYYY/M/D'], true)
        if (!parsed.isValid()) parsed = moment(normalized, ['YYYY/MM/DD', 'YYYY/M/D'], true)
      } catch (error) {
        return '-'
      }

      if (!parsed?.isValid?.()) return '-'

      const age = moment().diff(parsed, 'years')
      return age > 0 ? `${age} سال` : '-'
    },

    toEnglishDigits(value = '') {
      const fa = '۰۱۲۳۴۵۶۷۸۹'
      const ar = '٠١٢٣٤٥٦٧٨٩'
      return String(value).replace(/[۰-۹٠-٩]/g, digit => {
        const faIndex = fa.indexOf(digit)
        if (faIndex >= 0) return String(faIndex)
        const arIndex = ar.indexOf(digit)
        return arIndex >= 0 ? String(arIndex) : digit
      })
    },

    formatAppointmentDate(item) {
      if (item?.created_at) return this.formatCellValue('created_at', item.created_at)
      const month = item?.month || ''
      const day = item?.day_num || ''
      return month || day ? `${month}${day ? ` / روز ${day}` : ''}` : '-'
    },

    formatAppointmentTrackingTime(value) {
      if (!value) return '-'

      const parsed = moment(value)
      return parsed.isValid() ? parsed.format('HH:mm') : String(value).slice(11, 16) || '-'
    },

    appointmentRegistrationTooltip(item) {
      const recordedBy = String(item?.registered_by || '').trim() || 'نامشخص'
      const recordedAt = moment(item?.registered_at || item?.created_at)
      const dateTime = recordedAt.isValid()
        ? recordedAt.format('jYYYY/jMM/jDD ساعت HH:mm')
        : 'زمان ثبت نامشخص'

      return `ثبت‌شده توسط: ${recordedBy}\nتاریخ و ساعت ثبت: ${dateTime}`
    },

    appointmentDoctors(item) {
      let services = item?.services || []
      if (typeof services === 'string') {
        try { services = JSON.parse(services) } catch { services = [] }
      }

      const names = Array.isArray(services)
        ? services.map(service => service.doctor).filter(Boolean)
        : []

      return [...new Set(names)].join('، ') || '-'
    },

    appointmentConsultants(item) {
      let services = item?.services || []
      if (typeof services === 'string') {
        try { services = JSON.parse(services) } catch { services = [] }
      }

      const names = Array.isArray(services)
        ? services.map(service => service.consultant).filter(Boolean)
        : []

      return [...new Set(names)].join('، ') || '-'
    },

    numberValue(value) {
      if (value === null || value === undefined || value === '') return 0
      const clean = String(value).replace(/,/g, '').replace(/[^\d.-]/g, '')
      return Number(clean) || 0
    },

    statPercent(value, max) {
      if (!max || max <= 0) return 0
      return Math.max(0, Math.min(100, (Number(value || 0) / max) * 100))
    },

    totalDebtAmount() {
      return (this.appointmentResults || []).reduce((sum, item) => {
        return sum + this.numberValue(item.debt)
      }, 0)
    },

    isCanceledStatus(status) {
      const normalized = String(status || '').trim()
      return normalized.includes('کنسل') || normalized.includes('لغو') || normalized.includes('canceled') || normalized.includes('cancel')
    },

    formatPercent(value) {
      return `${Number(value || 0).toLocaleString('fa-IR', { maximumFractionDigits: 1 })}%`
    },

    appointmentLeadHours(item) {
      if (!item?.created_at || !item?.month || !item?.day_num) return null

      try {
        const month = String(item.month).replace(/-/g, '/')
        const [jy, jm] = month.split('/').map(part => Number(part))
        const jd = Number(item.day_num)
        if (!jy || !jm || !jd) return null

        const time = String(item.time || '00:00')
        const [hour = 0, minute = 0] = time.split(':').map(part => Number(part) || 0)
        const appointment = moment(`${jy}/${jm}/${jd} ${hour}:${minute}`, 'jYYYY/jM/jD H:m')
        const created = moment(item.created_at)

        if (!appointment.isValid() || !created.isValid()) return null
        return appointment.diff(created, 'hours', true)
      } catch (error) {
        return null
      }
    },

    formatLeadTime(hours) {
      if (hours === null || hours === undefined || !Number.isFinite(hours)) return 'نامشخص'
      if (hours < 24) return `${Math.max(0, Math.round(hours)).toLocaleString('fa-IR')} ساعت`

      const days = hours / 24
      return `${days.toLocaleString('fa-IR', { maximumFractionDigits: 1 })} روز`
    },

    formatDoneWork(item) {
      const value = item?.done
      if (!value) return '-'

      if (Array.isArray(value)) {
        return value.filter(Boolean).join('، ') || '-'
      }

      if (typeof value === 'string') {
        const trimmed = value.trim()
        if (!trimmed) return '-'

        try {
          const parsed = JSON.parse(trimmed)
          if (Array.isArray(parsed)) return parsed.filter(Boolean).join('، ') || '-'
        } catch (error) {
          // plain text value
        }

        return trimmed
          .split(/[,،|]+/)
          .map(item => item.trim())
          .filter(Boolean)
          .join('، ') || '-'
      }

      return String(value)
    },

    formatDebtValue(value) {
      const amount = Number(value || 0)
      return amount > 0 ? this.formatMoneyValue(amount) : '-'
    },

    ratingCount(rate) {
      const base = Math.max(this.appointmentResults.length, 1)
      const defaults = { 5: base, 4: Math.max(Math.floor(base / 3), 0), 3: 1, 2: 0, 1: 0 }
      return defaults[rate] || 0
    },

    ratingWidth(rate) {
      const max = Math.max(...[5, 4, 3, 2, 1].map(item => this.ratingCount(item)), 1)
      return `${Math.round((this.ratingCount(rate) / max) * 100)}%`
    },

    async openMediaModal(patient) {
      if (!this.canUseGallery) return
      document.activeElement?.blur?.()
      this.removeDetachedDatePickers()
      this.activeMediaPatient = patient
      this.currentMediaFolderId = null
      this.mediaBreadcrumbs = []
      this.mediaFolders = []
      this.mediaItems = []
      this.mediaShowAll = false
      this.newFolderName = ''
      this.specificMediaDate = ''
      this.showSpecificMediaDatePicker = false
      this.selectedMediaFiles = []
      this.selectedMediaFolderService = null
      this.showAngleCommonSettings = false
      this.resetMediaUpload()
      this.showMediaModal = true
      await this.loadPatientMedia()
    },

    closeMediaModal() {
      this.closeFolderContextMenu()
      this.showMediaModal = false
      this.activeMediaPatient = {}
      this.closeMediaEdit()
    },

    removeDetachedDatePickers() {
      document
        .querySelectorAll('body > .vpd-wrapper, body > .vpd-container, body > .vpd-main')
        .forEach(element => element.remove())
    },

    patientInitials(patient = {}) {
      const first = String(patient.first_name || '').trim().charAt(0)
      const last = String(patient.last_name || '').trim().charAt(0)
      return `${first}${last}` || 'پ'
    },

    async createProfileThumbnail(file) {
      const imageUrl = URL.createObjectURL(file)

      try {
        const image = await new Promise((resolve, reject) => {
          const element = new Image()
          element.onload = () => resolve(element)
          element.onerror = () => reject(new Error('خواندن تصویر انتخاب‌شده ممکن نیست'))
          element.src = imageUrl
        })
        const size = Math.min(image.naturalWidth, image.naturalHeight)
        const canvas = document.createElement('canvas')
        canvas.width = 50
        canvas.height = 50
        canvas.getContext('2d').drawImage(
          image,
          (image.naturalWidth - size) / 2,
          (image.naturalHeight - size) / 2,
          size,
          size,
          0,
          0,
          50,
          50
        )
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', 0.48))
        if (!blob) throw new Error('ساخت تصویر کوچک ممکن نیست')
        return new File([blob], 'thumbnail.webp', { type: 'image/webp' })
      } finally {
        URL.revokeObjectURL(imageUrl)
      }
    },
    async uploadPatientProfilePhoto(event) {
      const file = (event.target.files || [])[0]
      event.target.value = ''
      if (!file || !this.activeMediaPatient.id) return

      if (!file.type.startsWith('image/')) {
        Swal.fire({ icon: 'warning', title: 'فایل نامعتبر', text: 'لطفاً یک فایل تصویری انتخاب کنید.' })
        return
      }

      const sourceUrl = URL.createObjectURL(file)
      try {
        const image = await new Promise((resolve, reject) => {
          const element = new Image()
          element.onload = () => resolve(element)
          element.onerror = () => reject(new Error('خواندن تصویر انتخاب‌شده ممکن نیست'))
          element.src = sourceUrl
        })
        this.profileCrop = {
          open: true,
          sourceUrl,
          image,
          zoom: 1,
          x: 0,
          y: 0,
          dragging: false,
          pointerX: 0,
          pointerY: 0
        }
      } catch (error) {
        URL.revokeObjectURL(sourceUrl)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message })
      }
    },

    cancelProfileCrop() {
      if (this.profilePhotoUploading) return
      if (this.profileCrop.sourceUrl) URL.revokeObjectURL(this.profileCrop.sourceUrl)
      this.profileCrop = {
        open: false,
        sourceUrl: '',
        image: null,
        zoom: 1,
        x: 0,
        y: 0,
        dragging: false,
        pointerX: 0,
        pointerY: 0
      }
    },

    startProfileCropDrag(event) {
      if (!this.profileCrop.image) return
      event.currentTarget.setPointerCapture?.(event.pointerId)
      this.profileCrop.dragging = true
      this.profileCrop.pointerX = event.clientX
      this.profileCrop.pointerY = event.clientY
    },

    moveProfileCrop(event) {
      if (!this.profileCrop.dragging) return
      this.profileCrop.x += event.clientX - this.profileCrop.pointerX
      this.profileCrop.y += event.clientY - this.profileCrop.pointerY
      this.profileCrop.pointerX = event.clientX
      this.profileCrop.pointerY = event.clientY
      this.clampProfileCropPosition()
    },

    endProfileCropDrag() {
      this.profileCrop.dragging = false
    },

    clampProfileCropPosition() {
      const image = this.profileCrop.image
      if (!image) return
      const frame = document.querySelector('.profile-crop-stage')?.clientWidth || 320
      const baseScale = Math.max(frame / image.naturalWidth, frame / image.naturalHeight)
      const scale = baseScale * Number(this.profileCrop.zoom || 1)
      const maxX = Math.max(0, (image.naturalWidth * scale - frame) / 2)
      const maxY = Math.max(0, (image.naturalHeight * scale - frame) / 2)
      this.profileCrop.x = Math.max(-maxX, Math.min(maxX, this.profileCrop.x))
      this.profileCrop.y = Math.max(-maxY, Math.min(maxY, this.profileCrop.y))
    },

    async createCroppedProfileFile(size, quality, filename) {
      const image = this.profileCrop.image
      if (!image) throw new Error('تصویری برای برش انتخاب نشده است')

      const frame = document.querySelector('.profile-crop-stage')?.clientWidth || 320
      const baseScale = Math.max(frame / image.naturalWidth, frame / image.naturalHeight)
      const previewScale = baseScale * Number(this.profileCrop.zoom || 1)
      const outputScale = size / frame

      const canvas = document.createElement('canvas')
      canvas.width = size
      canvas.height = size
      const context = canvas.getContext('2d')
      context.imageSmoothingEnabled = true
      context.imageSmoothingQuality = 'high'
      context.setTransform(
        previewScale * outputScale,
        0,
        0,
        previewScale * outputScale,
        size / 2 + this.profileCrop.x * outputScale,
        size / 2 + this.profileCrop.y * outputScale
      )
      context.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2)
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', quality))
      if (!blob) throw new Error('ساخت تصویر برش‌خورده ممکن نیست')
      return new File([blob], filename, { type: 'image/webp' })
    },

    async confirmProfileCrop() {
      if (!this.activeMediaPatient.id || this.profilePhotoUploading) return
      this.profilePhotoUploading = true

      try {
        const formData = new FormData()
        formData.append('photo', await this.createCroppedProfileFile(800, 0.86, 'profile.webp'))
        formData.append('thumbnail', await this.createCroppedProfileFile(50, 0.48, 'thumbnail.webp'))

        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/profile-photo`, {
          method: 'POST',
          body: formData
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'عکس پروفایل ذخیره نشد')

        const updatedPatient = data.patient || {}
        this.activeMediaPatient = {
          ...this.activeMediaPatient,
          ...updatedPatient,
          profile_photo_url: data.profile_photo_url || updatedPatient.profile_photo_url,
          profile_thumbnail_url: data.profile_thumbnail_url || updatedPatient.profile_thumbnail_url
        }
        const result = this.searchResults.find(item => item.id === this.activeMediaPatient.id)
        if (result) Object.assign(result, this.activeMediaPatient)
        if (this.activePatientProfile?.id === this.activeMediaPatient.id) {
          this.activePatientProfile = {
            ...this.activePatientProfile,
            ...this.activeMediaPatient
          }
        }

        const sourceUrl = this.profileCrop.sourceUrl
        this.profileCrop.open = false
        this.profileCrop.sourceUrl = ''
        if (sourceUrl) URL.revokeObjectURL(sourceUrl)
        Swal.fire({ icon: 'success', title: 'عکس ذخیره شد', timer: 1500, showConfirmButton: false })
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'عکس پروفایل ذخیره نشد' })
      } finally {
        this.profilePhotoUploading = false
      }
    },
    resetMediaUpload() {
      this.mediaUpload = {
        description: '',
        no_usage_consent: false,
        services: []
      }
    },

    mediaForAngle(angleKey) {
      if (!angleKey) return null
      return (this.mediaItems || [])
        .filter(item => item.media_type === 'image' && item.photo_angle_key === angleKey)
        .sort((a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0))[0] || null
    },

    angleHint(angle = {}) {
      if (!angle.degrees) return 'روبه‌روی دوربین'
      return `${angle.degrees} درجه به ${angle.side}`
    },

    angleInstruction(angle = {}) {
      if (!angle.degrees) return 'صورت کاملاً روبه‌روی دوربین و در مرکز کادر قرار بگیرد.'
      const amount = angle.degrees === 90 ? 'کامل' : angle.degrees === 60 ? 'حدود دو‌سوم' : 'کمی'
      const kind = angle.degrees === 90 ? 'نیم‌رخ' : 'سه‌رخ'
      return `سر بیمار را ${amount} به سمت ${angle.side} بچرخانید تا ${kind} در کادر دیده شود.`
    },

    selectAdjacentPhotoAngle(step) {
      const index = this.facePhotoAngles.findIndex(angle => angle.key === this.activePhotoAngleKey)
      const next = this.facePhotoAngles[index + step]
      if (next) this.activePhotoAngleKey = next.key
    },

    comparisonStageLabel(value) {
      return value === 'before' ? 'قبل' : value === 'after' ? 'بعد' : ''
    },

    beforeAfterBasePath(path = '') {
      return String(path || '')
        .split(' / ')
        .filter(Boolean)
        .filter(part => !['عکس قبل', 'عکس بعد'].includes(part))
        .join(' / ')
    },

    mediaComparisonLabel(item = {}) {
      const parts = [
        this.comparisonStageLabel(item.comparison_stage),
        item.photo_angle_label
      ].filter(Boolean)

      return parts.join(' - ')
    },

    syncEditAngleLabel() {
      const angle = this.facePhotoAngles.find(item => item.key === this.editMediaForm.photo_angle_key)
      this.editMediaForm.photo_angle_label = angle?.label || ''
      this.editMediaForm.photo_angle_degrees = angle?.degrees ?? null
    },

    async loadPatientMedia(folderId = this.currentMediaFolderId, showAll = this.mediaShowAll) {
      if (!this.activeMediaPatient.id) return
      this.mediaLoading = true

      try {
        const params = new URLSearchParams()
        if (folderId) params.append('folder_id', folderId)
        if (showAll) params.append('all', '1')

        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media?${params.toString()}`)
        const data = await res.json()

        this.currentMediaFolderId = data.current_folder_id || null
        this.mediaShowAll = !!data.all
        this.mediaBreadcrumbs = data.breadcrumbs || []
        this.mediaFolders = data.folders || []
        this.mediaItems = data.media || []
        this.mediaSections = data.sections || []
        this.mediaServiceGroups = data.service_groups || []
        this.expandedMediaSectionKeys = this.mediaSections
          .filter(section => Number(section.level || 2) < 2)
          .map(section => this.mediaSectionKey(section))
        if (this.activePatientProfile?.id === this.activeMediaPatient.id && showAll) {
          this.latestProfilePhotos = (this.mediaItems || [])
            .filter(item => item.media_type === 'image' && item.url)
            .sort((a, b) => new Date(b.created_at || b.updated_at || 0) - new Date(a.created_at || a.updated_at || 0))
            .slice(0, 6)
        }
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: 'گالری پرونده بارگذاری نشد' })
      } finally {
        this.mediaLoading = false
      }
    },

    openMediaFolder(folderId) {
      this.closeFolderContextMenu()
      this.selectedMediaFolderService = null
      this.selectedMediaTreeKey = ''
      this.showAngleCommonSettings = false
      this.mediaShowAll = false
      this.loadPatientMedia(folderId, false)
    },

    async openBeforeAfterCompare() {
      if (!this.activeMediaPatient?.id || this.beforeAfterCompareLoading) return

      this.showBeforeAfterCompare = true
      this.beforeAfterCompareLoading = true
      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media?all=1`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت عکس‌ها انجام نشد')
        this.beforeAfterCompareItems = data.media || []
        this.beforeAfterCompareIndex = 0
      } catch (error) {
        console.error(error)
        this.beforeAfterCompareItems = []
        Swal.fire({ icon: 'error', title: 'خطا', text: 'مقایسه عکس‌ها آماده نشد' })
      } finally {
        this.beforeAfterCompareLoading = false
      }
    },

    closeBeforeAfterCompare() {
      this.showBeforeAfterCompare = false
    },

    goUploadMissingComparePhoto(pair, stage) {
      const targetType = stage === 'before' ? 'before_photo' : 'after_photo'
      const targetFolder = (this.mediaFolders || []).find(folder => folder.folder_type === targetType)
      const baseFolderId = this.currentComparisonBaseFolderId()

      if (pair?.angleKey) {
        this.activePhotoAngleKey = pair.angleKey
      }
      this.closeBeforeAfterCompare()
      if (targetFolder?.id) {
        this.openMediaFolder(targetFolder.id)
        return
      }

      if (baseFolderId) {
        this.openMediaFolder(baseFolderId)
        Swal.fire({
          icon: 'info',
          title: stage === 'before' ? 'عکس قبل را آپلود کنید' : 'عکس بعد را آپلود کنید',
          text: `برای زاویه ${pair?.angle || ''} وارد فولدر ${stage === 'before' ? 'عکس قبل' : 'عکس بعد'} شوید.`,
          timer: 2200,
          showConfirmButton: false
        })
      }
    },

    currentComparisonBaseFolderId() {
      const crumbs = [...this.mediaBreadcrumbs]
      const currentType = crumbs[crumbs.length - 1]?.folder_type
      if (['before_photo', 'after_photo'].includes(currentType)) {
        crumbs.pop()
      }
      return crumbs[crumbs.length - 1]?.id || null
    },

    openFolderContextMenu(event, folder) {
      const menuWidth = 190
      const menuHeight = 96
      this.folderContextMenu = {
        visible: true,
        x: Math.max(8, Math.min(event.clientX, window.innerWidth - menuWidth - 8)),
        y: Math.max(8, Math.min(event.clientY, window.innerHeight - menuHeight - 8)),
        folder
      }
    },

    closeFolderContextMenu() {
      this.folderContextMenu = { visible: false, x: 0, y: 0, folder: null }
    },

    async deleteMediaFolder(folder) {
      if (!folder?.id || !this.activeMediaPatient.id || folder.deleteLoading) return
      this.closeFolderContextMenu()

      const result = await Swal.fire({
        icon: 'warning',
        title: 'حذف فولدر',
        html: `آیا از حذف فولدر «<b>${this.escapeHtml(folder.name)}</b>» مطمئن هستید؟<br><br><strong>با حذف فولدر، تمام عکس‌ها و همه زیرپوشه‌های داخل آن نیز برای همیشه حذف می‌شوند.</strong>`,
        showCancelButton: true,
        confirmButtonText: 'بله، حذف شود',
        cancelButtonText: 'انصراف',
        confirmButtonColor: '#dc2626',
        reverseButtons: true
      })

      if (!result.isConfirmed) return

      folder.deleteLoading = true
      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/folders/${folder.id}`, {
          method: 'DELETE',
          headers: { 'Accept': 'application/json' }
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'فولدر حذف نشد')

        await this.loadPatientMedia(this.currentMediaFolderId, false)
        Swal.fire({
          icon: 'success',
          title: 'حذف شد',
          text: data.message || 'فولدر و تمام محتویات آن حذف شدند.',
          timer: 1800,
          showConfirmButton: false
        })
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'فولدر حذف نشد' })
      } finally {
        folder.deleteLoading = false
      }
    },

    escapeHtml(value = '') {
      return String(value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
      })[character])
    },

    toggleAllMedia() {
      const nextState = !this.mediaShowAll
      this.loadPatientMedia(this.currentMediaFolderId, nextState)
    },

    goMediaBack() {
      const previous = this.mediaBreadcrumbs.length > 1
        ? this.mediaBreadcrumbs[this.mediaBreadcrumbs.length - 2].id
        : null
      this.mediaShowAll = false
      this.loadPatientMedia(previous, false)
    },

    mediaTodayJalaliDate() {
      return dayjs().calendar('jalali').format('YYYY-MM-DD')
    },

    async readMediaFolderError(res, fallback = 'فولدر ساخته نشد') {
      try {
        const data = await res.json()
        return data.message || fallback
      } catch (error) {
        return fallback
      }
    },

    async createMediaDateFolder(dateValue) {
      if (!dateValue || !this.activeMediaPatient.id) return
      this.mediaLoading = true

      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/folders`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            type: 'date',
            date: dateValue
          })
        })

        if (!res.ok) {
          throw new Error(await this.readMediaFolderError(res, 'فولدر تاریخ ساخته نشد'))
        }

        const data = await res.json()
        const folder = data.folder || data
        this.specificMediaDate = ''
        this.showSpecificMediaDatePicker = false
        await this.loadPatientMedia(folder.id, false)

        if (data.already_exists) {
          Swal.fire({
            icon: 'info',
            title: 'فولدر موجود بود',
            text: data.message || 'این فولدر از قبل موجود بود و برای شما باز شد.',
            timer: 2200,
            showConfirmButton: false
          })
        }
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'فولدر تاریخ ساخته نشد' })
      } finally {
        this.mediaLoading = false
      }
    },

    async createMediaServiceFolder() {
      if (!this.currentMediaFolderId || !this.activeMediaPatient.id) return
      if (!this.mediaUpload.services.length) {
        Swal.fire({ icon: 'warning', title: 'تگ انتخاب نشده', text: 'قبل از ادامه، حداقل یک تگ برای عکس‌ها انتخاب کنید.' })
        return
      }
      this.mediaLoading = true

      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/folders`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            type: 'service',
            parent_id: this.currentMediaFolderId
          })
        })

        if (!res.ok) {
          throw new Error(await this.readMediaFolderError(res, 'فولدر خدمت ساخته نشد'))
        }

        const data = await res.json()
        const folder = data.folder || data
        this.selectedMediaFolderService = null
        await this.loadPatientMedia(folder.id, false)
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'فولدر خدمت ساخته نشد' })
      } finally {
        this.mediaLoading = false
      }
    },

    selectMediaFolderService(service) {
      if (!service?.id) return
      this.selectedMediaFolderService = service
      this.selectedMediaTreeKey = this.mediaSectionKey(service)
      this.serviceTagSearch = ''
      this.mediaUpload.services = []
    },

    cancelMediaFolderService() {
      this.selectedMediaFolderService = null
      this.selectedMediaTreeKey = ''
      this.serviceTagSearch = ''
      this.resetMediaUpload()
    },

    mediaSectionKey(section) {
      return String(section?.id || '')
    },

    mediaChildSections(parentKey) {
      return (this.mediaSections || [])
        .filter(section => String(section.parent_id || '') === String(parentKey || ''))
        .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0) || Number(a.id || 0) - Number(b.id || 0))
    },

    isMediaSectionExpanded(section) {
      return this.expandedMediaSectionKeys.includes(this.mediaSectionKey(section))
    },

    toggleMediaSection(section) {
      const key = this.mediaSectionKey(section)
      if (!this.mediaChildSections(key).length) return
      const index = this.expandedMediaSectionKeys.indexOf(key)
      if (index >= 0) this.expandedMediaSectionKeys.splice(index, 1)
      else this.expandedMediaSectionKeys.push(key)
    },

    mediaTreeNodeCount(section) {
      const key = this.mediaSectionKey(section)
      return Number(section?.level || 2) === 2
        ? this.mediaServiceGroups.filter(group => Number(group.section_id) === Number(section.id)).flatMap(group => group.items || []).length
        : this.mediaChildSections(key).length
    },

    selectMediaTreeNode(section) {
      const key = this.mediaSectionKey(section)
      this.selectedMediaTreeKey = key
      if (this.mediaChildSections(key).length && !this.isMediaSectionExpanded(section)) {
        this.expandedMediaSectionKeys.push(key)
      }
      if (Number(section.level || 2) !== 2) {
        this.selectedMediaFolderService = null
        this.serviceTagSearch = ''
        this.mediaUpload.services = []
        return
      }
      this.selectMediaFolderService(section)
    },

    selectAllVisibleMediaTags() {
      const selected = new Map((this.mediaUpload.services || []).map(tag => [tag.id, tag]))
      this.filteredMediaServiceTags.forEach(tag => selected.set(tag.id, tag))
      this.mediaUpload.services = Array.from(selected.values())
    },

    async createMediaFolder() {
      if (!this.newFolderName.trim() || !this.activeMediaPatient.id) return
      this.mediaLoading = true

      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/folders`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            name: this.newFolderName.trim(),
            parent_id: this.currentMediaFolderId
          })
        })

        if (!res.ok) throw new Error()
        this.newFolderName = ''
        await this.loadPatientMedia(this.currentMediaFolderId, false)
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: 'فولدر ساخته نشد' })
      } finally {
        this.mediaLoading = false
      }
    },

    handleMediaFiles(event) {
      this.selectedMediaFiles = Array.from(event.target.files || [])
    },

    appendMediaUploadMetadata(formData) {
      formData.append('description', this.mediaUpload.description || '')
      formData.append('usage_consent', this.mediaUpload.no_usage_consent ? '0' : '1')
      formData.append('services', JSON.stringify(this.mediaUpload.services || []))
    },

    async uploadMediaFiles() {
      if (!this.selectedMediaFiles.length || !this.activeMediaPatient.id) return
      this.mediaLoading = true

      try {
        const formData = new FormData()
        this.selectedMediaFiles.forEach(file => formData.append('files[]', file))
        if (this.currentMediaFolderId) formData.append('folder_id', this.currentMediaFolderId)
        this.appendMediaUploadMetadata(formData)

        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/files`, {
          method: 'POST',
          body: formData
        })

        if (!res.ok) throw new Error()
        this.selectedMediaFiles = []
        await this.loadPatientMedia(this.currentMediaFolderId, false)
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: 'آپلود انجام نشد' })
      } finally {
        this.mediaLoading = false
      }
    },

    async uploadAnglePhoto(event, angle) {
      const file = (event.target.files || [])[0]
      event.target.value = ''
      await this.saveAnglePhoto(file, angle)
    },

    async handleAngleDrop(event, angle) {
      this.angleDragging = false
      const file = (event.dataTransfer?.files || [])[0]
      await this.saveAnglePhoto(file, angle)
    },

    async saveAnglePhoto(file, angle) {
      if (!file || !this.activeMediaPatient.id || !this.currentMediaFolderId || !angle?.key) return

      if (!this.mediaUpload.services.length) {
        this.showAngleCommonSettings = true
        Swal.fire({ icon: 'warning', title: 'تگ خدمت انتخاب نشده', text: 'قبل از آپلود، حداقل یک تگ خدمات برای این عکس انتخاب کنید.' })
        return
      }

      if (!String(file.type || '').startsWith('image/')) {
        Swal.fire({ icon: 'warning', title: 'فایل نامعتبر', text: 'برای این زاویه فقط فایل تصویری انتخاب کنید.' })
        return
      }

      const existing = this.mediaForAngle(angle.key)
      this.mediaLoading = true
      this.angleUploadLoading = true

      try {
        const formData = new FormData()
        formData.append(existing ? 'file' : 'files[]', file)
        if (existing) formData.append('_method', 'PATCH')
        if (!existing) formData.append('folder_id', this.currentMediaFolderId)
        this.appendMediaUploadMetadata(formData)
        formData.append('comparison_stage', this.currentComparisonStage)
        formData.append('photo_angle_key', angle.key)
        formData.append('photo_angle_label', angle.label)
        formData.append('photo_angle_degrees', String(angle.degrees))

        const url = existing
          ? `/api/patients/${this.activeMediaPatient.id}/media/files/${existing.id}`
          : `/api/patients/${this.activeMediaPatient.id}/media/files`

        const res = await fetch(url, {
          method: 'POST',
          body: formData
        })

        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'آپلود عکس زاویه انجام نشد')
        await this.loadPatientMedia(this.currentMediaFolderId, false)
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'آپلود عکس زاویه انجام نشد' })
      } finally {
        this.mediaLoading = false
        this.angleUploadLoading = false
      }
    },

    finishAnglePhotos() {
      Swal.fire({
        icon: 'success',
        title: 'تصاویر ثبت شدند',
        text: this.completedAngleCount
          ? `${this.completedAngleCount} زاویه برای این بخش در پرونده ذخیره شده است.`
          : 'هر زمان نیاز بود می‌توانید یک یا چند زاویه عکس اضافه کنید.',
        confirmButtonText: 'متوجه شدم'
      })
    },

    openMediaEdit(item) {
      this.editMediaFile = null
      this.editMediaForm = {
        ...item,
        description: item.description || '',
        is_featured: !!item.is_featured,
        no_usage_consent: item.usage_consent === false,
        comparison_stage: item.comparison_stage || '',
        photo_angle_key: item.photo_angle_key || '',
        photo_angle_label: item.photo_angle_label || '',
        photo_angle_degrees: item.photo_angle_degrees ?? null,
        service_keys: this.mediaServiceKeysFromStored(item.services || [])
      }
      this.showMediaEditModal = true
    },

    closeMediaEdit() {
      this.showMediaEditModal = false
      this.mediaEditLoading = false
      this.editMediaFile = null
      this.editMediaForm = {}
    },

    handleEditMediaFile(event) {
      this.editMediaFile = (event.target.files || [])[0] || null
    },

    selectedEditServices() {
      const selectedKeys = new Set(this.editMediaForm.service_keys || [])
      return this.mediaServiceGroups
        .flatMap(group => group.items || [])
        .filter(service => selectedKeys.has(service.key))
    },

    async saveMediaEdit() {
      if (!this.editMediaForm.id || !this.activeMediaPatient.id) return
      this.mediaEditLoading = true

      try {
        const formData = new FormData()
        formData.append('_method', 'PATCH')
        if (this.editMediaFile) formData.append('file', this.editMediaFile)
        formData.append('description', this.editMediaForm.description || '')
        formData.append('is_featured', this.editMediaForm.is_featured ? '1' : '0')
        formData.append('usage_consent', this.editMediaForm.no_usage_consent ? '0' : '1')
        formData.append('services', JSON.stringify(this.selectedEditServices()))
        formData.append('comparison_stage', this.editMediaForm.comparison_stage || '')
        formData.append('photo_angle_key', this.editMediaForm.photo_angle_key || '')
        formData.append('photo_angle_label', this.editMediaForm.photo_angle_label || '')
        formData.append('photo_angle_degrees', this.editMediaForm.photo_angle_degrees === null || this.editMediaForm.photo_angle_degrees === undefined ? '' : String(this.editMediaForm.photo_angle_degrees))

        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/files/${this.editMediaForm.id}`, {
          method: 'POST',
          body: formData
        })
        if (!res.ok) throw new Error()

        const updated = await res.json()
        const target = this.mediaItems.find(item => item.id === updated.id)
        if (target) Object.assign(target, updated)

        this.closeMediaEdit()
        Swal.fire({ icon: 'success', title: 'ذخیره شد', text: 'اطلاعات فایل بروزرسانی شد', timer: 1800, showConfirmButton: false })
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: 'ویرایش فایل ذخیره نشد' })
      } finally {
        this.mediaEditLoading = false
      }
    },

    async toggleMediaStar(item) {
      if (item.starLoading) return
      item.starLoading = true

      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/files/${item.id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ is_featured: !item.is_featured })
        })
        if (!res.ok) throw new Error()
        const updated = await res.json()
        Object.assign(item, updated)
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: 'وضعیت ستاره ذخیره نشد' })
      } finally {
        item.starLoading = false
      }
    },

    async deleteMediaItem(item) {
      if (!item?.id || !this.activeMediaPatient.id || item.deleteLoading) return

      const result = await Swal.fire({
        icon: 'warning',
        title: 'حذف فایل گالری',
        text: 'این عکس یا ویدیو حذف می‌شود و امکان برگشت ندارد. مطمئنید؟',
        showCancelButton: true,
        confirmButtonText: 'بله، حذف شود',
        cancelButtonText: 'انصراف',
        confirmButtonColor: '#dc2626',
        reverseButtons: true
      })

      if (!result.isConfirmed) return

      item.deleteLoading = true
      try {
        const res = await fetch(`/api/patients/${this.activeMediaPatient.id}/media/files/${item.id}`, {
          method: 'DELETE',
          headers: { 'Accept': 'application/json' }
        })

        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'فایل حذف نشد')

        this.mediaItems = this.mediaItems.filter(media => media.id !== item.id)
        if (this.editMediaForm.id === item.id) this.closeMediaEdit()

        Swal.fire({
          icon: 'success',
          title: 'حذف شد',
          text: data.message || 'فایل با موفقیت حذف شد.',
          timer: 1600,
          showConfirmButton: false
        })
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطا', text: error.message || 'فایل حذف نشد' })
      } finally {
        item.deleteLoading = false
      }
    },

    formatMediaServices(services = []) {
      const names = services
        .map(service => service.section || service.name)
        .filter(Boolean)

      return [...new Set(names)].join('، ')
    },

    normalizeMediaSearch(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/[ي]/g, 'ی')
        .replace(/[ك]/g, 'ک')
        .replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
        .replace(/[٠-٩]/g, digit => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))
        .replace(/\s+/g, ' ')
        .trim()
    },

    mediaServiceKeysFromStored(services = []) {
      const selectedNames = new Set(
        services
          .map(service => service.section || service.name)
          .filter(Boolean)
      )

      return this.mediaServiceGroups
        .flatMap(group => group.items || [])
        .filter(service => selectedNames.has(service.name))
        .map(service => service.key)
    },

    formatMediaDate(value) {
      if (!value) return 'نامشخص'
      return dayjs(value).calendar('jalali').locale('fa').format('YYYY/MM/DD HH:mm')
    },

    async handleDeposit() {
      if (!this.walletAmount || this.walletAmount <= 0) {
        Swal.fire({ icon: 'warning', title: 'خطای مبلغ', text: 'لطفاً یک مبلغ معتبر برای واریز وارد کنید.', confirmButtonText: 'تایید' });
        return;
      }
      try {
        const res = await fetch(`/api/patients/${this.activeWalletPatient.id}/wallet/deposit`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ amount: this.walletAmount, description: 'واریز از طریق پنل مدیریت پرونده' })
        });
        const data = await res.json();
        if (res.ok) {
          this.walletBalance = data.wallet_balance;
          this.activeWalletPatient.wallet_balance = data.wallet_balance;
          Swal.fire({ icon: 'success', title: 'موفقیت‌آمیز', text: data.message || 'مبلغ با موفقیت به کیف پول واریز شد.', timer: 2500, showConfirmButton: false });
          this.walletAmount = null;
          this.loadWalletTransactions();
        } else {
          Swal.fire({ icon: 'error', title: 'خطا', text: data.message || 'مشکلی در سیستم رخ داده است.', confirmButtonText: 'تایید' });
        }
      } catch (error) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'خطای ارتباطی', text: 'اتصال با سرور لاراول برقرار نشد.', confirmButtonText: 'تایید' });
      }
    },

    async handleWithdraw() {
      if (!this.walletAmount || this.walletAmount <= 0) {
        Swal.fire({ icon: 'warning', title: 'خطای مبلغ', text: 'لطفاً یک مبلغ معتبر برای برداشت وارد کنید.', confirmButtonText: 'تایید' });
        return;
      }
      if (this.walletAmount > this.walletBalance) {
        Swal.fire({ icon: 'error', title: 'موجودی ناکافی', text: 'مبلغ درخواستی بیشتر از موجودی فعلی کیف پول است.', confirmButtonText: 'تایید' });
        return;
      }
      try {
        const res = await fetch(`/api/patients/${this.activeWalletPatient.id}/wallet/withdraw`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ amount: this.walletAmount, description: 'برداشت از طریق پنل مدیریت پرونده' })
        });
        const data = await res.json();
        if (res.ok) {
          this.walletBalance = data.wallet_balance;
          this.activeWalletPatient.wallet_balance = data.wallet_balance;
          Swal.fire({ icon: 'success', title: 'موفقیت‌آمیز', text: data.message || 'مبلغ با موفقیت از کیف پول کسر شد.', timer: 2500, showConfirmButton: false });
          this.walletAmount = null;
          this.loadWalletTransactions();
        } else {
          Swal.fire({ icon: 'error', title: 'خطای تراکنش', text: data.message || 'انجام تراکنش مقدور نیست.', confirmButtonText: 'تایید' });
        }
      } catch (error) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'خطای ارتباطی', text: 'ارتباط با سرور برقرار نشد.', confirmButtonText: 'تایید' });
      }
    },

    async fetchPatientAppointments() {
      const fallbackPatient = this.searchResults[0] || {}
      await this.fetchPatientAppointmentsFor({
        file_number: this.search.file_number || fallbackPatient.file_number || '',
        phone: this.search.phone || fallbackPatient.phone || ''
      })
    },

    async fetchPatientAppointmentsFor(patient = {}) {
      this.appointmentResults = []
      const fileNumber = patient.file_number || ''
      const phone = patient.phone || ''

      if (!fileNumber && !phone) return

      try {
        this.appointmentLoading = true
        const params = new URLSearchParams()
        if (fileNumber) params.append('file_number', fileNumber)
        if (phone) params.append('phone', phone)

        const res = await fetch(`/api/appointments/patient-history?${params.toString()}`)
        const data = await res.json()
        this.appointmentResults = Array.isArray(data) ? data : []
      } catch (e) {
        console.error('Appointments history error:', e)
      } finally {
        this.appointmentLoading = false
      }
    },

    formatServices(services) {
      if (!services) return '-'
      if (typeof services === 'string') {
        try { services = JSON.parse(services) } catch { return services }
      }
      if (!Array.isArray(services)) return '-'
      return services
        .filter(s => s.name)
        .map(s => {
          const cc = s.cc ? ` - ${s.cc}cc` : ''
          return `${s.name}${cc}`
        })
        .join('، ')
    },

    appointmentFilterText(item, key) {
      const map = {
        services: this.formatServices(item.services),
        doctors: this.appointmentDoctors(item),
        consultants: this.appointmentConsultants(item),
        amount: this.formatMoneyValue(item.amount),
        payment: this.paymentSummary(item),
        status: item.status || '-',
        arrived_at: this.formatAppointmentTrackingTime(item.arrived_at),
        done: this.formatDoneWork(item),
        completed_at: this.formatAppointmentTrackingTime(item.completed_at),
        debt: this.formatDebtValue(item.debt),
      }
      return map[key] || '-'
    },

    appointmentFilterValues(key) {
      return [...new Set((this.appointmentResults || []).map(item => this.appointmentFilterText(item, key)).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b), 'fa'))
    },

    toggleAppointmentFilter(key) {
      this.activeAppointmentFilter = this.activeAppointmentFilter === key ? null : key
    },

    toggleAppointmentFilterValue(key, value) {
      const list = this.selectedAppointmentFilters[key] || []
      const index = list.indexOf(value)
      if (index >= 0) list.splice(index, 1)
      else list.push(value)
      this.selectedAppointmentFilters[key] = list
    },

    clearAppointmentFilter(key) {
      this.selectedAppointmentFilters[key] = []
    },

    isAppointmentFiltered(key) {
      return Boolean(this.selectedAppointmentFilters[key]?.length)
    },

    paymentDetails(item) {
      const details = item?.payment_details || item?.paymentDetails || {}
      const check = details.check || {}
      return {
        cash: this.numberValue(details.cash),
        card: this.numberValue(details.card),
        check: {
          amount: this.numberValue(check.amount),
          number: String(check.number || ''),
          dueDate: String(check.dueDate || check.due_date || ''),
        },
      }
    },

    paymentSummary(item) {
      const details = this.paymentDetails(item)
      const parts = []
      if (item.payment_method) parts.push(item.payment_method)
      if (item.payment_account) parts.push(item.payment_account)
      if (details.cash > 0) parts.push(`نقدی ${this.formatMoneyValue(details.cash)}`)
      if (details.card > 0) parts.push(`کارت ${this.formatMoneyValue(details.card)}`)
      return parts.length ? parts.join('، ') : '-'
    },

    hasAppointmentCheck(item) {
      const check = this.paymentDetails(item).check
      return Boolean(check.amount || check.number || check.dueDate)
    },

    appointmentCheckText(item) {
      const check = this.paymentDetails(item).check
      if (!check.amount && !check.number && !check.dueDate) return ''
      const parts = []
      if (check.amount) parts.push(this.formatMoneyValue(check.amount))
      if (check.number) parts.push(`شماره ${check.number}`)
      if (check.dueDate) parts.push(`سررسید ${this.formatGregorianDateFa(check.dueDate)}`)
      const alert = this.appointmentCheckAlert(item)
      return alert ? `${alert} - ${parts.join('، ')}` : parts.join('، ')
    },

    appointmentCheckAlert(item) {
      const dueDate = this.paymentDetails(item).check.dueDate
      if (!dueDate) return ''
      const today = new Date()
      const todayIso = this.localIsoDate(today)
      const tomorrow = this.localIsoDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1))
      if (dueDate < todayIso) return 'سررسید گذشته'
      if (dueDate === todayIso) return 'سررسید امروز'
      if (dueDate === tomorrow) return 'سررسید فردا'
      return ''
    },

    localIsoDate(date) {
      return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    },

    formatGregorianDateFa(value) {
      if (!value) return '-'
      const date = new Date(`${value}T12:00:00`)
      if (Number.isNaN(date.getTime())) return value
      return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(date)
    },

    formatMoneyValue(value) {
      if (value === 0) return '۰ تومان'
      if (!value) return '-'
      return Number(value || 0).toLocaleString('fa-IR') + ' تومان'
    },

    async updatePatient() {
      if (
        !this.editPatient.first_name || 
        !this.editPatient.last_name || 
        !this.editPatient.phone || 
        !this.editPatient.file_number || 
        !this.editPatient.gender || 
        !this.editPatient.birth_date || 
        !this.editPatient.area || 
        !this.editPatient.financial_status
      ) {
        Swal.fire({ icon: 'warning', title: 'خطای اعتبار سنجی', text: 'لطفاً تمامی فیلدهای ستاره‌دار را تکمیل کنید.', confirmButtonText: 'تایید' })
        return
      }

      if (this.editPatient.phone.length !== 11) {
        Swal.fire({ icon: 'warning', title: 'خطای شماره تماس', text: 'شماره تماس باید دقیقاً ۱۱ رقم باشد.', confirmButtonText: 'تایید' })
        return
      }

      try {
        const res = await fetch(
          `/api/patients/${this.editPatient.id}`,
          {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.editPatient)
          }
        )
        if (!res.ok) throw new Error()

        Swal.fire({ icon: 'success', title: 'موفق', text: 'اطلاعات پرونده ویرایش شد' })
        this.showEditModal = false
        this.performSearch()
      } catch (e) {
        Swal.fire({ icon: 'error', title: 'خطا', text: 'ویرایش انجام نشد' })
      }
    },

    openEditModal(patient) {
      this.editPatient = { ...patient, city: patient.city || 'تهران' }
      this.selectedEditCity = this.cityOptions.find(item => item.name === this.editPatient.city) || null
      this.showEditModal = true
    },

    sanitizePatientSearchRow(row = {}) {
      const sanitized = { ...row }
      ;[
        'profile_thumbnail_path',
        'profile_photo_path'
      ].forEach(key => delete sanitized[key])
      return sanitized
    },
    formatCellValue(key, value) {
      if (key === 'wallet_balance' || key === 'outstanding_debt') {
        return `${this.formatMoneyValue(value || 0)} تومان`
      }
      if (key === 'phone' || key === 'second_phone') {
        return this.displayPatientPhone(value) || '-'
      }
      if (!value) return '-'
      if (key === 'created_at' || key === 'updated_at') {
        return moment(value).format('jYYYY/jMM/jDD')
      }
      return value
    },

    async submitForm() {
      const requiredFields = { ...this.patientRequiredFields, file_number: true }
      const missingFields = Object.entries(requiredFields)
        .filter(([field, required]) => required && !String(this.form[field] ?? '').trim())
        .map(([field]) => this.columnLabels[field] || field)
      if (missingFields.length) {
        Swal.fire({ icon: 'warning', title: 'اطلاعات ناقص', text: `فیلدهای اجباری را تکمیل کنید: ${missingFields.join('، ')}`, confirmButtonText: 'متوجه شدم' })
        return
      }

      if (this.form.phone.trim() && this.form.phone.trim().length !== 11) {
        Swal.fire({ icon: 'warning', title: 'فرمت اشتباه شماره تماس', text: 'شماره تماس وارد شده باید حتماً ۱۱ رقم باشد.', confirmButtonText: 'اصلاح شماره' })
        return
      }

      try {
        const duplicate = await this.checkDuplicatePatient()
        if(duplicate.phone_exists){
          Swal.fire({ icon: 'error', title: 'شماره موبایل تکراری', text: 'این شماره موبایل قبلاً ثبت شده است', timer: 3000, showConfirmButton: false })
          return
        }

        const res = await fetch('/api/patients', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(this.form)
        })
        const data = await res.json()

        if (res.ok) {
          const createdPatient = data.patient || { ...this.form }
          const createdSearch = {
            q: '',
            file_number: String(createdPatient.file_number || this.form.file_number || '').trim(),
            phone: String(createdPatient.phone || this.form.phone || '').trim(),
            national_id: String(createdPatient.national_id || this.form.national_id || '').trim()
          }

          Swal.fire({ icon: 'success', title: 'موفق', text: 'پرونده با موفقیت ثبت شد', timer: 3000, showConfirmButton: false })
          this.resetForm()
          await this.fetchNextFileNumber()
          this.search = createdSearch
          if (createdSearch.file_number || createdSearch.phone || createdSearch.national_id) {
            await this.performSearch()
          } else {
            this.searchResults = [this.sanitizePatientSearchRow(createdPatient)]
            this.searchSearched = true
          }
          this.$nextTick(() => {
            const results = document.querySelector('.result-card')
            if (results) results.scrollIntoView({ behavior: 'smooth', block: 'start' })
          })
        } else {
          Swal.fire({ icon: 'error', title: 'خطا', text: data.message || 'خطا در ثبت پرونده', timer: 3000, showConfirmButton: false })
        }
      } catch (error) {
        Swal.fire({ icon: 'error', title: 'خطای سرور', text: 'ارتباط با سرور برقرار نشد', timer: 3000, showConfirmButton: false })
      }
    },

    async fetchNextFileNumber(){
      try{
        const res = await fetch('/api/patients/next-file-number')
        const data = await res.json()
        this.form.file_number = data.file_number || ''
      }catch(e){ console.error(e) }
    },

    async checkDuplicatePatient(){
      const params = new URLSearchParams()
      if(this.form.phone) params.append('phone', this.form.phone)

      const res = await fetch(`/api/patients/check-duplicate?${params.toString()}`)
      return await res.json()
    },

    async performSearch() {
      if (this.searchLoading) return

      this.searchSearched = false
      this.searchResults = []
      this.activePatientProfile = null
      this.profileViewOpen = false

      if (!this.search.q && !this.search.file_number && !this.search.phone && !this.search.national_id) {
        Swal.fire({ icon: 'warning', title: 'اطلاعات ناقص', text: 'نام، شماره پرونده، شماره تماس یا کد ملی را وارد کنید', timer: 3000, showConfirmButton: false })
        return
      }

      const startedAt = Date.now()
      this.searchLoading = true

      try {
        const params = new URLSearchParams()
        if (this.search.q) params.append('q', this.search.q)
        if (this.search.file_number) params.append('file_number', this.search.file_number)
        if (this.search.phone) params.append('phone', this.search.phone)
        if (this.search.national_id) params.append('national_id', this.search.national_id)

        const res = await fetch(`/api/patients/search?${params.toString()}`)
        const data = await res.json()

        this.searchResults = Array.isArray(data) ? data.map(this.sanitizePatientSearchRow) : []
        this.searchSearched = true
        await this.fetchPatientAppointments()
      } catch (error) {
        console.error(error)
        Swal.fire({ icon: 'error', title: 'خطای سرور', text: 'جستجو انجام نشد', timer: 3000, showConfirmButton: false })
      } finally {
        const elapsed = Date.now() - startedAt
        if (elapsed < 450) {
          await new Promise(resolve => setTimeout(resolve, 450 - elapsed))
        }
        this.searchLoading = false
      }
    },
  }
}
</script>

<style scoped>
.patient-page {
  direction: rtl;
  min-height: 100vh;
  padding: 18px;
  background: #f6f8fa;
  font-family: "Vazir", sans-serif;
}

.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  padding: 18px;
  margin-bottom: 18px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  border-bottom: 1px solid #edf2f7;
  padding-bottom: 12px;
}

.section-header h3 {
  margin: 0;
  font-size: 16px;
  color: #0f172a;
  font-weight: 800;
}

.section-header span {
  font-size: 12px;
  color: #64748b;
}

.create-grid,
.search-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(140px, 1fr));
  gap: 12px;
  align-items: center;
}

.search-grid {
  grid-template-columns: repeat(4, minmax(170px, 1fr));
}

input,
select {
  width: 100%;
  height: 42px;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  padding: 0 12px;
  background: #fff;
  color: #111827;
  outline: none;
  font-family: inherit;
  font-size: 13px;
  transition: 0.2s;
}

input:focus,
select:focus {
  border-color: #0f766e;
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

.city-select {
  width: 100% !important;
  flex: initial;
  min-width: 0 !important;
  font-family: inherit;
  min-height: 42px;
  overflow: visible;
}

::v-deep(.city-select .multiselect__tags) {
  min-height: 42px;
  height: 42px;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  padding: 9px 12px 0 38px;
  font-family: inherit;
  font-size: 12px;
  overflow: hidden;
  box-sizing: border-box;
}

::v-deep(.city-select .multiselect__single),
::v-deep(.city-select .multiselect__input),
::v-deep(.city-select .multiselect__placeholder) {
  font-family: inherit;
  font-size: 12px;
  text-align: right;
  direction: rtl;
  margin: 0;
  padding: 0;
  line-height: 22px;
  white-space: nowrap;
}

::v-deep(.city-select .multiselect__single) {
  overflow: hidden;
  text-overflow: ellipsis;
  padding-left: 22px;
  padding-right: 0;
  color: #111827;
  background: transparent;
}

::v-deep(.city-select .multiselect__input) {
  width: 1px !important;
  height: 22px !important;
  min-height: 22px !important;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
  margin: 0 !important;
  color: #111827;
  outline: 0 !important;
}

::v-deep(.city-select .multiselect__select) {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 42px;
  width: 34px;
  left: 0;
  right: auto;
  top: 0;
  padding: 0;
  transform: none !important;
  transition: none !important;
}

::v-deep(.city-select .multiselect__select::before) {
  position: static;
  top: auto;
  left: auto;
  right: auto;
  transform: none !important;
  margin: 2px 0 0;
  border-color: #64748b transparent transparent;
}

::v-deep(.city-select.multiselect--active .multiselect__select) {
  transform: none !important;
}

::v-deep(.city-select.multiselect--active .multiselect__select::before) {
  transform: none !important;
  border-color: #0f766e transparent transparent;
}

::v-deep(.city-select.multiselect--active .multiselect__tags) {
  border-color: #0f766e;
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

::v-deep(.city-select.multiselect--active .multiselect__input) {
  width: 100% !important;
}

::v-deep(.city-select .multiselect__content-wrapper) {
  direction: rtl;
  text-align: right;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  box-shadow: 0 18px 42px rgba(15, 23, 42, .16);
  margin-top: 6px;
  min-width: 220px;
  max-height: 260px !important;
  overflow-y: auto;
}

::v-deep(.city-select .multiselect__option) {
  font-family: inherit;
  font-size: 12px;
  text-align: right;
  min-height: 36px;
  line-height: 20px;
}

::v-deep(.city-select .multiselect__option--highlight) {
  background: #0f766e;
}

.primary-btn,
.secondary-btn {
  height: 42px;
  width: 150px;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  font-family: inherit;
  font-weight: 700;
  transition: 0.2s;
}

.primary-btn {
  background: #0f766e;
  color: #fff;
}

.secondary-btn {
  background: #334155;
  color: #fff;
}

.secondary-btn:disabled {
  opacity: .72;
  cursor: wait;
  transform: none;
}

.search-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-spinner {
  width: 15px;
  height: 15px;
  border: 2px solid rgba(255,255,255,.45);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .75s linear infinite;
  flex: 0 0 auto;
}

.btn-spinner.dark {
  border-color: rgba(15, 118, 110, .22);
  border-top-color: #0f766e;
}

.search-loading-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  color: #0f766e;
  font-size: 12px;
  font-weight: 800;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.primary-btn:hover,
.secondary-btn:not(:disabled):hover {
  opacity: 0.92;
  transform: translateY(-1px);
}

@media (max-width: 1000px) {
  .create-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .search-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .create-grid,
  .search-grid {
    grid-template-columns: 1fr;
  }

  .section-header {
    align-items: flex-start;
    flex-direction: column;
  }
}

.create-card {
  overflow: visible;
}

.create-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(180px, 1fr));
  gap: 12px;
  align-items: center;
  width: 100%;
}

.create-grid input,
.create-grid select,
.create-grid textarea,
.birthdate-picker {
  width: 100% !important;
  min-width: 0 !important;
  height: 42px !important;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  padding: 0 10px;
  background: #fff;
  color: #111827;
  outline: none;
  font-family: inherit;
  font-size: 12px;
  box-sizing: border-box;
}

.create-grid textarea {
  grid-column: span 2;
  min-height: 86px;
  height: 86px !important;
  padding-top: 10px;
  resize: vertical;
  overflow: auto;
  line-height: 1.6;
}

.create-grid .primary-btn {
  width: 100%;
  min-width: 0;
  justify-self: stretch;
}

/* wrapper خود date-picker */
.create-grid .vpd-input-group {
  min-width: 0 !important;
  width: 100% !important;
}

.create-grid .vpd-input-group input {
  width: 100% !important;
  height: 42px !important;
  border-radius: 12px !important;
  border-radius: 12px 0 0 12px;
}

@media (max-width: 1200px) {
  .create-card {
    overflow: visible;
  }

  .create-grid {
    grid-template-columns: repeat(3, minmax(170px, 1fr));
  }

  .create-grid input,
  .create-grid select,
  .create-grid textarea,
  .birthdate-picker,
  .create-grid .vpd-input-group {
    width: 100% !important;
    border-radius: 12px !important;
  }

  .create-grid .city-select {
    width: 100% !important;
    border-radius: 12px !important;
  }
}

@media (max-width: 820px) {
  .create-grid {
    grid-template-columns: repeat(2, minmax(160px, 1fr));
  }
}

@media (max-width: 560px) {
  .create-grid {
    grid-template-columns: 1fr;
  }

  .create-grid textarea {
    grid-column: span 1;
  }
}

::v-deep(.vpd-input-group label){
  border-radius: 0px 12px 12px 0 !important;
}

::v-deep(.birthdate-picker){
      border-radius: 12px 0 0 12px !important;
          height: 42px !important;
}

.result-card {
  margin-top: 24px;
  padding: 20px;
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 12px 30px rgba(15, 118, 110, 0.08);
  border: 1px solid #e5e7eb;
}

.result-table-wrap {
  width: 100%;
  overflow-x: auto;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  background: #fff;
}

.result-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 900px;
  font-size: 14px;
  direction: rtl;
}

.result-table thead th {
  position: sticky;
  top: 0;
  background: #878787dd;
  color: #fff;
  padding: 14px 12px;
  text-align: center;
  font-weight: 700;
  white-space: nowrap;
  border-left: 1px solid rgba(255,255,255,0.25);
}

.result-table thead th:first-child {
  border-top-right-radius: 14px;
}

.result-table thead th:last-child {
  border-top-left-radius: 14px;
  border-left: none;
}

.result-table tbody td {
  padding: 13px 12px;
  text-align: center;
  color: #334155;
  border-bottom: 1px solid #eef2f7;
  border-left: 1px solid #f1f5f9;
  white-space: nowrap;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.patient-name-cell {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  width: 100%;
}

.patient-name-cell:has(.patient-avatar.level-problematic) .patient-name-link,
.profile-hero:has(.patient-avatar.level-problematic) h1,
.patient-name:has(.level-problematic),
.problematic-customer-name {
  color: #dc2626 !important;
  font-weight: 1000 !important;
}

.patient-name-link {
  padding: 0;
  border: 0;
  background: transparent;
  color: #0f766e;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 4px;
}

.patient-name-link:hover,
.patient-name-link:focus-visible {
  color: #0891b2;
}

.patient-name-link:focus-visible {
  outline: 2px solid #22d3ee;
  outline-offset: 3px;
  border-radius: 4px;
}

.problematic-customer-check {
  width: 100%;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 0 13px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fff7f7;
  color: #b91c1c;
  font-size: 13px;
  font-weight: 900;
  line-height: 1;
  cursor: pointer;
  box-sizing: border-box;
  transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
}

.problematic-customer-check input,
.create-grid .problematic-customer-check input,
.edit-grid .problematic-customer-check input {
  appearance: none;
  -webkit-appearance: none;
  width: 18px !important;
  height: 18px !important;
  min-width: 18px;
  min-height: 18px;
  max-width: 18px;
  max-height: 18px;
  flex: 0 0 18px;
  margin: 0;
  padding: 0;
  border: 2px solid #f87171;
  border-radius: 6px;
  background: #fff;
  accent-color: #dc2626;
  cursor: pointer;
  box-shadow: none;
  position: relative;
  display: inline-grid;
  place-items: center;
}

.problematic-customer-check input:checked,
.create-grid .problematic-customer-check input:checked,
.edit-grid .problematic-customer-check input:checked {
  background: #dc2626;
  border-color: #dc2626;
}

.problematic-customer-check input:checked::after {
  content: "";
  width: 5px;
  height: 9px;
  border: solid #fff;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg) translateY(-1px);
}

.problematic-customer-check span {
  flex: 1;
  overflow: hidden;
  text-align: right;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.problematic-customer-check:has(input:checked) {
  border-color: #f87171;
  background: #fee2e2;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, .08);
}

.patient-result-avatar {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: inline-grid;
  place-items: center;
  padding: 0;
  border: 2px solid #dbeafe;
  border-radius: 50%;
  overflow: hidden;
  background: linear-gradient(135deg, #eff6ff, #f5f3ff);
  color: #2563eb;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
  transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
}

.patient-result-avatar:hover {
  transform: translateY(-1px);
  border-color: #60a5fa;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .18);
}
.patient-result-avatar.level-blue, .profile-photo-large.level-blue { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.16); }
.patient-result-avatar.level-silver, .profile-photo-large.level-silver { border-color:#94a3b8; box-shadow:0 0 0 3px rgba(148,163,184,.2); }
.patient-result-avatar.level-gold, .profile-photo-large.level-gold { border-color:#d4a72c; box-shadow:0 0 0 3px rgba(212,167,44,.2); }

.patient-result-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.patient-default-photo-icon {
  font-size: 18px;
  line-height: 1;
}

.customer-level-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 92px;
  padding: 7px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  border: 1px solid transparent;
  white-space: nowrap;
}

.customer-level-badge.level-silver {
  color: #475569;
  background: #f1f5f9;
  border-color: #94a3b8;
}

.customer-level-badge.level-blue {
  color: #1d4ed8;
  background: #dbeafe;
  border-color: #bfdbfe;
}

.customer-level-badge.level-gold {
  color: #92400e;
  background: #fef3c7;
  border-color: #d4a72c;
}

.customer-level-badge.level-problematic {
  color: #b91c1c;
  background: #fee2e2;
  border-color: #fca5a5;
}

.customer-level-badge.level-empty {
  color: #64748b;
  background: #f1f5f9;
  border-color: #e2e8f0;
}

.profile-customer-level {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
  margin: 10px 0 6px;
}

.customer-level-change-btn {
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  color: #0f172a;
  padding: 7px 12px;
  font-family: inherit;
  font-size: 12px;
  font-weight: 850;
  cursor: pointer;
  transition: .15s ease;
}

.customer-level-change-btn:hover {
  border-color: #60a5fa;
  color: #1d4ed8;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .14);
}

.problematic-profile-toggle {
  width: auto;
  min-height: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin: 8px auto 4px;
  padding: 6px 10px;
  border: 1px solid #fecaca;
  border-radius: 999px;
  background: #fff7f7;
  color: #b91c1c;
  font-family: inherit;
  font-size: 10px;
  font-weight: 850;
  cursor: pointer;
  transition: .18s ease;
}

.problematic-profile-toggle > span {
  width: 16px;
  height: 16px;
  display: grid;
  place-items: center;
  flex: 0 0 16px;
  border-radius: 50%;
  background: #dc2626;
  color: #fff;
  font-size: 10px;
}

.problematic-profile-toggle:hover {
  border-color: #f87171;
  background: #fee2e2;
  transform: translateY(-1px);
}

.problematic-profile-toggle.active {
  border-color: #fca5a5;
  background: #fee2e2;
  color: #991b1b;
}

.problematic-profile-toggle:disabled {
  opacity: .6;
  cursor: wait;
  transform: none;
}

.open-profile-btn {
  border: 0;
  border-radius: 999px;
  padding: 9px 14px;
  background: linear-gradient(135deg, #0f766e, #0891b2);
  color: #fff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 8px 18px rgba(8, 145, 178, .22);
  transition: transform .15s ease, box-shadow .15s ease;
}

.open-profile-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(8, 145, 178, .28);
}

.patient-profile-view {
  margin-bottom: 18px;
}

.direct-profile-loading {
  min-height: calc(100vh - 170px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  color: #1e3a5f;
  background: linear-gradient(145deg, #f8fbff, #eef6ff);
  border: 1px solid #dbeafe;
  border-radius: 24px;
}

.direct-profile-spinner {
  width: 42px;
  height: 42px;
  border: 4px solid #dbeafe;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: direct-profile-spin .7s linear infinite;
}

@keyframes direct-profile-spin { to { transform: rotate(360deg); } }

.profile-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.profile-topbar h2 {
  margin: 0;
  color: #0f172a;
  font-size: 20px;
  font-weight: 900;
}

.profile-back-btn {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff;
  color: #0f172a;
  padding: 10px 16px;
  font-family: inherit;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
}

.profile-shell {
  display: grid;
  gap: 16px;
}

.profile-main-card,
.profile-history-card,
.profile-rating-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 22px;
  box-shadow: 0 14px 36px rgba(15, 23, 42, .06);
  overflow: hidden;
}

.profile-main-card {
  padding: 26px;
  background:
    radial-gradient(circle at top right, rgba(37, 99, 235, .08), transparent 30%),
    #fff;
}

.profile-main-layout {
  display: grid;
  grid-template-columns: minmax(230px, 290px) minmax(0, 1fr);
  align-items: center;
  gap: 28px;
  direction: rtl;
}

.profile-hero {
  position: relative;
  text-align: center;
  padding: 22px 18px;
  border: 1px solid #dbeafe;
  border-radius: 20px;
  background: rgba(248, 251, 255, .9);
}

.profile-quick-actions {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin: 12px 0 8px;
}

.profile-beauty-action,
.profile-followup-action,
.profile-gallery-action {
  width: 40px;
  min-width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid #dbeafe;
  border-radius: 11px;
  background: #eff6ff;
  color: #2563eb;
  cursor: pointer;
  transition: background-color .18s ease, border-color .18s ease, transform .18s ease;
}
.profile-beauty-action svg,
.profile-followup-action svg,
.profile-gallery-action svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.profile-beauty-action:hover,
.profile-followup-action:hover,
.profile-gallery-action:hover {
  border-color: #93c5fd;
  background: #dbeafe;
  transform: translateY(-1px);
}
.profile-beauty-action:focus-visible,
.profile-followup-action:focus-visible,
.profile-gallery-action:focus-visible {
  outline: 3px solid rgba(37, 99, 235, .2);
  outline-offset: 2px;
}

.profile-latest-photos {
  display: flex;
  justify-content: center;
  gap: 5px;
  margin: -2px 0 9px;
  min-height: 34px;
}

.profile-latest-photos button,
.profile-latest-photos span {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  padding: 0;
  overflow: hidden;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #eef6ff;
}

.profile-latest-photos button {
  cursor: pointer;
  transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
}

.profile-latest-photos button:hover {
  border-color: #60a5fa;
  box-shadow: 0 5px 12px rgba(37, 99, 235, .18);
  transform: translateY(-1px);
}

.profile-latest-photos img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

.profile-latest-photos span {
  background: linear-gradient(90deg, #eef6ff, #f8fbff, #eef6ff);
  background-size: 200% 100%;
  animation: latest-photo-shimmer 1s linear infinite;
}

@keyframes latest-photo-shimmer {
  to { background-position: -200% 0; }
}

.profile-photo-large {
  width: 148px;
  height: 148px;
  border-radius: 50%;
  border: 5px solid #eff6ff;
  background: linear-gradient(135deg, #e0f2fe, #f5f3ff);
  display: inline-grid;
  place-items: center;
  overflow: hidden;
  padding: 0;
  cursor: pointer;
  box-shadow: 0 18px 34px rgba(15, 23, 42, .14);
}

.profile-photo-large img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-photo-large span {
  font-size: 44px;
}

.profile-hero h1 {
  margin: 14px 0 4px;
  color: #0f172a;
  font-size: 24px;
  font-weight: 950;
}

.profile-hero p {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.profile-info-grid {
  width: 100%;
  max-width: none;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 0;
}

.profile-info-row {
  display: grid;
  grid-template-columns: 38px minmax(88px, .85fr) minmax(96px, 1.15fr);
  align-items: center;
  gap: 9px;
  min-height: 72px;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 15px;
  background: #fff;
  color: #334155;
  transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.profile-info-row:hover {
  z-index: 1;
  border-color: #bfdbfe;
  box-shadow: 0 10px 24px rgba(15, 23, 42, .07);
  transform: translateY(-1px);
}

.profile-info-row:last-child:nth-child(odd) {
  grid-column: 1 / -1;
}

.profile-info-icon {
  width: 32px;
  height: 32px;
  display: inline-grid;
  place-items: center;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0;
}

.profile-info-icon::before {
  content: "";
  width: 18px;
  height: 18px;
  display: block;
  background: #173f70;
  -webkit-mask: var(--profile-icon) center / contain no-repeat;
  mask: var(--profile-icon) center / contain no-repeat;
}

.profile-info-row:nth-child(1) .profile-info-icon {
  --profile-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.4 2.1L8 9.7a16 16 0 0 0 6.3 6.3l1.3-1.3a2 2 0 0 1 2.1-.4c.8.3 1.7.5 2.6.6A2 2 0 0 1 22 16.9z'/%3E%3C/svg%3E");
}

.profile-info-row:nth-child(2) .profile-info-icon {
  --profile-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='17' rx='2'/%3E%3Cpath d='M8 2v4M16 2v4M3 10h18'/%3E%3C/svg%3E");
}

.profile-info-row:nth-child(3) .profile-info-icon {
  --profile-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1 1 18 0z'/%3E%3Ccircle cx='12' cy='10' r='3'/%3E%3C/svg%3E");
}

.profile-info-row:nth-child(4) .profile-info-icon {
  --profile-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'/%3E%3C/svg%3E");
}

.profile-info-row:nth-child(5) .profile-info-icon {
  --profile-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='2' y='5' width='20' height='14' rx='2'/%3E%3Cpath d='M2 10h20M6 15h4'/%3E%3C/svg%3E");
}

.profile-info-row:nth-child(6) .profile-info-icon {
  --profile-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z'/%3E%3Cpath d='M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M18 13h.01'/%3E%3C/svg%3E");
}

.profile-info-row b {
  font-size: 13px;
  color: #64748b;
  line-height: 1.6;
}

.profile-info-row strong {
  color: #0f172a;
  font-size: 14px;
  font-weight: 900;
  line-height: 1.7;
  overflow-wrap: anywhere;
}

.profile-info-row .green-value {
  color: #10b981;
}

.profile-report-card {
  position: relative;
  padding: 18px;
  border-radius: 24px;
  border: 1px solid rgba(59, 130, 246, .14);
  background:
    radial-gradient(circle at top right, rgba(59, 130, 246, .14), transparent 34%),
    linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
  box-shadow: 0 18px 42px rgba(15, 23, 42, .07);
  overflow: hidden;
}

.profile-report-card::before {
  content: "";
  position: absolute;
  inset: 0;
  pointer-events: none;
  background-image:
    linear-gradient(rgba(148, 163, 184, .10) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, .10) 1px, transparent 1px);
  background-size: 28px 28px;
  opacity: .45;
}

.profile-report-card > * {
  position: relative;
  z-index: 1;
}

.profile-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
}

.profile-stat-box {
  display: grid;
  grid-template-columns: 58px 1fr;
  align-items: center;
  gap: 13px;
  min-height: 126px;
  padding: 16px;
  border: 1px solid rgba(226, 232, 240, .9);
  border-radius: 20px;
  background: rgba(255, 255, 255, .88);
  box-shadow: 0 10px 24px rgba(15, 23, 42, .055);
  backdrop-filter: blur(8px);
  transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.profile-stat-box:hover {
  transform: translateY(-2px);
  border-color: color-mix(in srgb, var(--stat-accent) 32%, #e2e8f0);
  box-shadow: 0 18px 32px rgba(15, 23, 42, .09);
}

.stat-chart {
  position: relative;
  width: 58px;
  height: 58px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background:
    conic-gradient(var(--stat-accent) calc(var(--stat-percent) * 1%), #e5e7eb 0);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .75);
}

.stat-chart::before {
  content: "";
  position: absolute;
}

.stat-chart span {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #fff;
  color: #0f172a;
  font-size: 11px;
  font-weight: 900;
}

.stat-body {
  min-width: 0;
}

.stat-body small,
.stat-body em {
  display: block;
  color: #64748b;
  font-style: normal;
}

.stat-body small {
  font-size: 12px;
  font-weight: 800;
}

.stat-body strong {
  display: block;
  margin: 5px 0 4px;
  color: #0f172a;
  font-size: 18px;
  font-weight: 950;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.stat-body em {
  min-height: 18px;
  font-size: 11px;
}

.stat-line {
  height: 6px;
  margin-top: 10px;
  border-radius: 999px;
  background: #eef2f7;
  overflow: hidden;
}

.stat-line i {
  display: block;
  width: calc(var(--stat-percent) * 1%);
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--stat-accent), color-mix(in srgb, var(--stat-accent) 62%, white));
}

.profile-history-card {
  padding: 18px;
}

.profile-card-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.profile-card-title h3 {
  margin: 0;
  color: #0f172a;
  font-size: 17px;
  font-weight: 900;
}

.profile-card-title span {
  color: #64748b;
  font-size: 12px;
}

.profile-table-wrap {
  overflow-x: auto;
}

.profile-check-alert {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 10px;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fff1f2;
  color: #991b1b;
  font-size: 12px;
  font-weight: 900;
}

.profile-check-alert strong {
  padding: 4px 8px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: 10px;
}

.profile-services-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 1220px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
}

.profile-services-table th {
  position: relative;
  background: #143b67;
  color: #fff;
  padding: 10px 9px;
  font-size: 13px;
  font-weight: 900;
}

.profile-filter-head {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.profile-filter-head button {
  width: 22px;
  height: 22px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(255,255,255,.35);
  border-radius: 6px;
  background: rgba(255,255,255,.12);
  color: #fff;
  cursor: pointer;
  font-size: 11px;
}

.profile-services-table th.filtered-cell {
  background: #1d4ed8;
}

.profile-filter-menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + 6px);
  right: 8px;
  width: 210px;
  max-height: 260px;
  overflow: auto;
  padding: 8px;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  background: #fff;
  color: #0f172a;
  box-shadow: 0 18px 42px rgba(15, 23, 42, .2);
}

.profile-filter-menu label {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 6px;
  border-radius: 8px;
  color: #475569;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}

.profile-filter-menu label:hover {
  background: #eff6ff;
}

.profile-filter-menu input {
  width: 14px;
  height: 14px;
  accent-color: #2563eb;
}

.profile-filter-menu button {
  width: 100%;
  margin-top: 6px;
  padding: 7px;
  border: 0;
  border-radius: 8px;
  background: #fee2e2;
  color: #b91c1c;
  font-family: inherit;
  font-size: 10px;
  font-weight: 900;
  cursor: pointer;
}

.profile-services-table td {
  padding: 14px 12px;
  text-align: center;
  border-bottom: 1px solid #eef2f7;
  border-left: 1px solid #f1f5f9;
  color: #334155;
  font-size: 13px;
}

.profile-check-warning-row td {
  background: #fff1f2;
  color: #7f1d1d;
}

.profile-payment-summary {
  display: inline;
}

.profile-check-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 22px;
  margin-right: 6px;
  padding: 0 7px;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #f8fafc;
  color: #475569;
  font-size: 9px;
  font-weight: 1000;
  line-height: 1;
  vertical-align: middle;
}

.profile-check-icon.urgent {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(220, 38, 38, .12), 0 8px 18px rgba(220, 38, 38, .22);
}

.profile-empty-row {
  padding: 22px !important;
  color: #64748b !important;
  background: #f8fafc;
}

.profile-services-table tr:last-child td {
  border-bottom: 0;
}

.profile-loading-line,
.profile-empty-history {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 88px;
  color: #64748b;
}

.profile-rating-card {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 18px;
  padding: 18px;
}

.rating-bars {
  display: grid;
  gap: 10px;
}

.rating-bar-row {
  display: grid;
  grid-template-columns: 64px 1fr 32px;
  align-items: center;
  gap: 10px;
  color: #64748b;
  font-size: 12px;
}

.rating-bar-row div {
  height: 8px;
  background: #eef2f7;
  border-radius: 999px;
  overflow: hidden;
}

.rating-bar-row i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #22c55e, #14b8a6);
}

.rating-bar-row:nth-child(3) i {
  background: #facc15;
}

.rating-bar-row:nth-child(n+4) i {
  background: #ef4444;
}

.rating-summary {
  text-align: center;
  border-right: 1px solid #eef2f7;
  padding-right: 18px;
}

.rating-summary h3 {
  margin: 0 0 8px;
  color: #0f172a;
  font-size: 16px;
  font-weight: 900;
}

.rating-summary strong {
  display: block;
  color: #143b67;
  font-size: 46px;
  line-height: 1;
  font-weight: 950;
}

.rating-stars {
  color: #facc15;
  font-size: 26px;
  letter-spacing: 2px;
  margin: 8px 0;
}

.rating-summary p {
  margin: 0;
  color: #94a3b8;
  font-size: 12px;
}

@media (max-width: 900px) {
  .profile-main-layout {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .profile-hero {
    padding: 18px;
  }

  .profile-info-grid {
    max-width: none;
    margin-right: 0;
    margin-left: 0;
  }

  .profile-rating-card {
    grid-template-columns: 1fr;
  }

  .rating-summary {
    border-right: 0;
    border-top: 1px solid #eef2f7;
    padding-right: 0;
    padding-top: 16px;
  }
}

@media (max-width: 620px) {
  .profile-main-card {
    padding: 14px;
  }

  .profile-info-grid {
    grid-template-columns: 1fr;
  }

  .profile-info-row:last-child:nth-child(odd) {
    grid-column: auto;
  }
}

.result-table tbody tr:nth-child(even) {
  background: #f8fafc;
}

.result-table tbody tr:hover {
  background: #ecfeff;
}

.result-table tbody tr:last-child td {
  border-bottom: none;
}

.empty-result {
  padding: 26px;
  text-align: center;
  color: #64748b;
  background: #f8fafc;
  border-radius: 14px;
  border: 1px dashed #cbd5e1;
  font-size: 15px;
}


.result-header{
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.edit-btn{
  background:#0f766e;
  color:#fff;
  border:none;
  border-radius:10px;
  padding:10px 18px;
  cursor:pointer;
  font-weight:600;
}

.edit-btn:hover{
  background:#115e59;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.customer-level-modal {
  width: min(560px, 94vw);
  background: #fff;
  border-radius: 22px;
  padding: 18px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
  direction: rtl;
}

.customer-level-help {
  padding: 14px;
  border-radius: 18px;
  background: linear-gradient(135deg, #f8fafc, #eff6ff);
  border: 1px solid #e2e8f0;
  color: #334155;
  line-height: 1.9;
  margin-bottom: 14px;
}

.customer-level-help strong {
  display: block;
  color: #0f172a;
  font-weight: 950;
  margin-bottom: 4px;
}

.customer-level-help p {
  margin: 0;
  font-size: 13px;
}

.customer-level-options {
  display: grid;
  gap: 10px;
  margin-bottom: 16px;
}

.customer-level-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  border: 2px solid #e2e8f0;
  border-radius: 18px;
  background: #fff;
  padding: 14px;
  font-family: inherit;
  text-align: right;
  cursor: pointer;
  transition: .15s ease;
}

.customer-level-option span {
  font-weight: 950;
  color: #0f172a;
}

.customer-level-option small {
  color: #64748b;
  font-size: 12px;
}

.customer-level-option::before {
  content: "";
  width: 18px;
  height: 18px;
  border-radius: 50%;
  flex: 0 0 18px;
  background: var(--level-color);
  box-shadow: 0 0 0 5px color-mix(in srgb, var(--level-color) 16%, transparent);
}

.customer-level-option.is-problematic {
  --level-color: #ef4444;
}

.customer-level-option.is-blue {
  --level-color: #3b82f6;
}

.customer-level-option.is-cip {
  --level-color: #10b981;
}

.customer-level-option.active {
  border-color: var(--level-color);
  background: color-mix(in srgb, var(--level-color) 8%, white);
  box-shadow: 0 12px 28px color-mix(in srgb, var(--level-color) 18%, transparent);
}

.edit-modal {
  width: min(760px, 95vw);
  max-height: 90vh;
  overflow-y: auto;
  background: #ffffff;
  border-radius: 22px;
  padding: 24px;
  box-shadow: 0 25px 70px rgba(15, 23, 42, 0.25);
  direction: rtl;
}

.edit-modal h3 {
  margin: 0 0 20px;
  color: #0f172a;
  font-size: 20px;
  font-weight: 800;
}

.edit-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.edit-grid input,
.edit-grid select,
.edit-grid textarea {
  width: 100%;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 14px;
  background: #f8fafc;
  outline: none;
}

.edit-grid .city-select {
  width: 100% !important;
  flex: initial;
  min-height: 42px;
}

::v-deep(.edit-grid .city-select .multiselect__tags) {
  background: #f8fafc;
}

.edit-grid textarea {
  min-height: 100px;
  resize: vertical;
  grid-column: span 2;
}

.edit-grid input:focus,
.edit-grid select:focus,
.edit-grid textarea:focus {
  border-color: #0f766e;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 22px;
}

@media (max-width: 700px) {
  .edit-grid {
    grid-template-columns: 1fr;
  }

  .edit-grid textarea {
    grid-column: span 1;
  }
}

input::placeholder,
select option:first-child {
  color: #6b7280;
}
input::-webkit-input-placeholder { color: currentColor; opacity: 0.6; }

/* استایل‌های اختصاصی پاپ‌آپ کیف پول */
.wallet-btn {
  background-color: #0284c7;
  color: #ffffff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  transition: background 0.2s;
}
.wallet-btn:hover {
  background-color: #0369a1;
}

.profile-info-row.profile-debt-warning {
  border: 1px solid #fca5a5;
  background: #fff1f2;
  box-shadow: 0 7px 18px rgba(220, 38, 38, .12);
}
.profile-info-row.profile-debt-warning b,
.profile-info-row.profile-debt-warning strong {
  color: #b91c1c;
}

.media-btn {
  background: #7c3aed;
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 10px 16px;
  cursor: pointer;
  font-weight: 800;
  font-family: inherit;
}

.media-btn:hover {
  background: #6d28d9;
}

.media-overlay {
  align-items: stretch;
  z-index: 2147483200;
}

.media-modal {
  width: min(1180px, 96vw);
  max-height: 92vh;
  overflow: hidden;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
  display: flex;
  flex-direction: column;
  direction: rtl;
}

.media-header,
.media-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 16px 18px;
  border-bottom: 1px solid #e5e7eb;
}

.media-header h3,
.media-header p {
  margin: 0;
}

.media-header p {
  margin-top: 5px;
  color: #64748b;
  font-size: 12px;
}

.media-patient-head {
  display: flex;
  align-items: center;
  gap: 14px;
}

.profile-crop-overlay {
  position: fixed;
  /* This picker opens from the media gallery, so it must sit above the
     gallery overlay rather than behind it. */
  z-index: 2147483201;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, .78);
  backdrop-filter: blur(8px);
}

.profile-crop-modal {
  width: min(470px, 100%);
  padding: 20px;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 30px 80px rgba(0, 0, 0, .32);
}

.profile-crop-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 16px; }
.profile-crop-header h3 { margin: 0 0 5px; color: #0f172a; font-size: 18px; }
.profile-crop-header p { margin: 0; color: #64748b; font-size: 11px; }
.profile-crop-close { width: 40px; height: 40px; border: 0; border-radius: 11px; background: #f1f5f9; color: #475569; font-size: 25px; cursor: pointer; }

.profile-crop-stage {
  position: relative;
  width: min(320px, 78vw);
  aspect-ratio: 1;
  margin: 0 auto;
  overflow: hidden;
  border: 2px solid #fff;
  border-radius: 50%;
  box-shadow: 0 0 0 1px rgba(15, 23, 42, .12);
  background: #111827;
  touch-action: none;
  cursor: grab;
  user-select: none;
}
.profile-crop-stage.dragging { cursor: grabbing; }
.profile-crop-stage > img { position: absolute; top: 50%; left: 50%; height: 100%; width: auto; max-width: none; pointer-events: none; transform-origin: center; }
.profile-crop-stage > img.portrait { width: 100%; height: auto; }
.profile-face-guide { position: absolute; inset: 0; opacity: .34; pointer-events: none; }
.face-head { position: absolute; top: 20%; left: 38%; width: 24%; height: 31%; border: 1px dashed #fff; border-radius: 48%; }
.face-shoulders { position: absolute; left: 24%; bottom: 14%; width: 52%; height: 29%; border: 1px dashed #fff; border-bottom: 0; border-radius: 50% 50% 0 0; }
.profile-crop-help { margin: 13px 0 10px; color: #64748b; font-size: 11px; line-height: 1.8; text-align: center; }
.profile-crop-zoom { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; color: #64748b; font-size: 10px; }
.profile-crop-zoom input { width: 100%; accent-color: #2563eb; }
.profile-crop-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
.profile-crop-actions button { min-height: 40px; border: 0; border-radius: 11px; padding: 0 16px; font-family: inherit; font-size: 11px; font-weight: 800; cursor: pointer; }
.profile-crop-actions .primary-btn { background: #2563eb; color: #fff; }
.profile-crop-actions .secondary-btn { background: #eaf2ff; color: #1d4ed8; }
.profile-crop-actions button:disabled { opacity: .55; cursor: wait; }
.profile-photo-picker {
  position: relative;
  width: 72px;
  height: 72px;
  flex: 0 0 72px;
  display: grid;
  place-items: center;
  border: 3px solid #e0f2fe;
  border-radius: 50%;
  overflow: hidden;
  background: linear-gradient(135deg, #eff6ff, #f5f3ff);
  color: #2563eb;
  font-size: 22px;
  font-weight: 900;
  cursor: pointer;
  box-shadow: 0 12px 24px rgba(37, 99, 235, .14);
}

.profile-photo-picker input {
  display: none;
}

.profile-photo-picker img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-avatar-placeholder {
  width: 100%;
  height: 100%;
  display: grid;
  place-items: center;
  gap: 2px;
}

.avatar-icon {
  font-size: 30px;
  line-height: 1;
}

.profile-avatar-placeholder em {
  border-radius: 999px;
  padding: 2px 8px;
  background: #2563eb;
  color: #fff;
  font-size: 10px;
  font-style: normal;
  font-weight: 900;
}

.profile-photo-picker small {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, .45);
  color: #fff;
}

.media-toolbar {
  padding: 10px 18px;
  background: #f8fafc;
}

.media-back {
  width: auto;
  padding: 0 16px;
}

.media-all-btn {
  width: auto;
  height: 38px;
  border: 1px solid #c7d2fe;
  border-radius: 12px;
  background: #eef2ff;
  color: #4338ca;
  padding: 0 14px;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
  transition: .2s;
}

.media-all-btn.active {
  background: #4338ca;
  border-color: #4338ca;
  color: #fff;
}

.media-all-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(67, 56, 202, .18);
}

.media-compare-btn {
  width: auto;
  height: 38px;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  background: linear-gradient(135deg, #2563eb, #38bdf8);
  color: #fff;
  padding: 0 14px;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
  box-shadow: 0 9px 20px rgba(37, 99, 235, .2);
  transition: .2s ease;
}

.media-compare-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(37, 99, 235, .26);
}

.media-compare-btn:disabled {
  opacity: .65;
  cursor: wait;
  transform: none;
}

.media-path {
  flex: 1;
  color: #64748b;
  font-size: 12px;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.media-path button {
  border: 0;
  background: transparent;
  color: #0f766e;
  cursor: pointer;
  font-family: inherit;
  font-weight: 800;
}

.media-layout {
  display: grid;
  grid-template-columns: 390px minmax(0, 1fr);
  min-height: 0;
  flex: 1;
}

.media-layout.comparison-layout {
  grid-template-columns: minmax(0, 1fr);
}

.comparison-layout .media-content {
  padding: 22px;
}

.comparison-layout .angle-upload-panel {
  padding: 22px;
}

.comparison-layout .angle-guide,
.comparison-layout .angle-dropzone,
.comparison-layout .angle-dropzone.filled img {
  min-height: 380px;
}

.media-side {
  border-left: 1px solid #e5e7eb;
  background: #fbfdff;
  padding: 16px;
  overflow-y: auto;
  overflow-x: hidden;
}

.folder-create,
.upload-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid #e5edf7;
  border-radius: 14px;
  background: #fff;
  margin-bottom: 14px;
}

.upload-card label,
.service-picker strong {
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.media-meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.upload-card textarea {
  width: 100%;
  min-height: 82px;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  padding: 10px;
  resize: vertical;
  font-family: inherit;
}

.feature-check {
  display: flex;
  align-items: center;
  gap: 8px;
}

.feature-check input,
.service-group input {
  width: auto;
  height: auto;
  accent-color: #7c3aed;
}

.service-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 210px;
  overflow-y: auto;
  border: 1px solid #edf2f7;
  border-radius: 12px;
  padding: 10px;
}

.service-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.service-group > span {
  color: #0f766e;
  font-size: 12px;
  font-weight: 900;
}

.service-group label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #475569;
}

.guided-folder-create strong,
.service-folder-create strong,
.guided-folder-note strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 900;
}

.guided-folder-create {
  position: relative;
  gap: 11px;
  padding: 16px;
  overflow: hidden;
  border-color: #dbeafe;
  border-radius: 18px;
  background:
    radial-gradient(circle at 100% 0, rgba(20, 184, 166, .12), transparent 42%),
    linear-gradient(145deg, #ffffff, #f8fbff);
  box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
}

.guided-folder-create::after {
  content: '';
  position: absolute;
  top: -34px;
  left: -34px;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: rgba(59, 130, 246, .07);
  pointer-events: none;
}

.guided-folder-create > strong {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 2px;
  font-size: 14px;
}

.guided-folder-create > strong::before {
  content: '▣';
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: 11px;
  background: linear-gradient(135deg, #0f766e, #14b8a6);
  color: #fff;
  font-size: 17px;
  box-shadow: 0 7px 16px rgba(15, 118, 110, .22);
}

.guided-folder-create > button {
  position: relative;
  width: 100%;
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 11px;
  padding: 0 14px;
  border-radius: 14px;
  text-align: right;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.guided-folder-create > button::before {
  width: 28px;
  height: 28px;
  flex: 0 0 28px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  font-size: 15px;
}

.guided-folder-create > button:first-of-type {
  border: 1px solid #0f766e;
  background: linear-gradient(135deg, #0f766e, #0d9488);
  color: #fff;
  box-shadow: 0 8px 18px rgba(15, 118, 110, .18);
}

.guided-folder-create > button:first-of-type::before {
  content: '✓';
  background: rgba(255, 255, 255, .18);
  color: #fff;
}

.guided-folder-create > button:nth-of-type(2) {
  border: 1px solid #bfdbfe;
  background: linear-gradient(135deg, #eff6ff, #f8fbff);
  color: #1e3a8a;
  box-shadow: 0 7px 18px rgba(37, 99, 235, .09);
}

.guided-folder-create > button:nth-of-type(2)::before {
  content: '▦';
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  color: #fff;
  font-size: 17px;
  box-shadow: 0 5px 12px rgba(37, 99, 235, .22);
}

.guided-folder-create > button:nth-of-type(2):hover:not(:disabled) {
  border-color: #93c5fd;
  background: linear-gradient(135deg, #dbeafe, #eff6ff);
  color: #1d4ed8;
}

.guided-folder-create > button:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 11px 22px rgba(15, 23, 42, .13);
}

.guided-folder-create > button:focus-visible {
  outline: 3px solid rgba(59, 130, 246, .2);
  outline-offset: 2px;
}

.guided-folder-create > button:disabled {
  cursor: wait;
  opacity: .55;
}

.guided-folder-create p,
.service-folder-create p,
.guided-folder-note p {
  margin: 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.8;
}

.specific-date-create {
  display: grid;
  gap: 8px;
  margin-top: 2px;
  padding: 11px;
  border: 1px solid #dbeafe;
  border-radius: 13px;
  background: rgba(239, 246, 255, .7);
}

.service-folder-picker {
  display: grid;
  gap: 9px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
}

.media-inventory-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.media-inventory-head strong{color:#0f172a;font-size:12px;font-weight:1000}.media-inventory-head span{padding:3px 8px;border-radius:999px;background:#e2e8f0;color:#64748b;font-size:10px;font-weight:900}.media-inventory-tree{display:flex;flex-direction:column;gap:5px;max-height:260px;overflow:auto;padding:3px}.media-tree-node{min-height:40px;display:flex;align-items:center;gap:7px;padding:0 9px;padding-right:calc(9px + (var(--tree-depth) * 14px));border:1px solid transparent;border-radius:10px;background:#fff;color:#334155;font-family:inherit;text-align:right;cursor:pointer;transition:.16s}.media-tree-node:hover{background:#f1f5f9}.media-tree-node.active{background:#eaf3ff;color:#1d4ed8}.media-tree-toggle{width:15px;height:15px;flex:0 0 15px;position:relative}.media-tree-toggle::before{content:"";position:absolute;inset:5px;border-top:4px solid #94a3b8;border-right:3px solid transparent;border-left:3px solid transparent;transition:.16s}.media-tree-toggle.open::before{transform:rotate(90deg)}.media-tree-node.leaf .media-tree-toggle::before{opacity:0}.media-tree-node b{min-width:0;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:1000}.media-tree-node i{width:7px;height:7px;flex:0 0 7px;border-radius:50%;background:#cbd5e1}.media-tree-node em{min-width:28px;height:28px;display:grid;place-items:center;margin-right:auto;border-radius:999px;background:#eef2f7;color:#64748b;font-size:11px;font-style:normal;font-weight:900}.media-section-required{display:grid;gap:5px;padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#fff;color:#64748b;text-align:center}.media-section-required strong{color:#0f172a;font-size:12px}.media-section-required span{font-size:10px;line-height:1.8}

.setup-step-title {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.setup-step-title b {
  width: 24px;
  height: 24px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #2563eb;
  color: #fff;
}

.section-choice-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.service-folder-btn {
  width: 100%;
  border: 1px solid #dbeafe;
  border-radius: 10px;
  padding: 9px 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-family: inherit;
  font-size: 12px;
  font-weight: 800;
  text-align: right;
  cursor: pointer;
}

.service-folder-btn:hover {
  background: #dbeafe;
}

.service-folder-btn.active {
  border-color: #2563eb;
  background: #dbeafe;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
}

.shared-upload-setup {
  display: grid;
  gap: 12px;
  margin-top: 6px;
  padding: 16px;
  border: 1px solid #c7d2fe;
  border-radius: 16px;
  background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
  min-width: 0;
  box-shadow: 0 10px 26px rgba(37, 99, 235, .08);
}

.shared-upload-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  padding-bottom: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.shared-upload-head > div,
.angle-common-meta {
  display: grid;
  gap: 5px;
}

.shared-upload-head strong,
.angle-common-meta > strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 900;
}

.shared-upload-head small,
.angle-common-meta > small {
  color: #64748b;
  font-size: 11px;
  line-height: 1.7;
}

.shared-setup-close {
  width: 28px;
  height: 28px;
  flex: 0 0 28px;
  border: 0;
  border-radius: 50%;
  background: #fee2e2;
  color: #dc2626;
  cursor: pointer;
  font-size: 18px;
  transition: transform .15s ease, background .15s ease;
}

.shared-setup-close:hover {
  background: #fecaca;
  transform: rotate(90deg);
}

.shared-tags-head {
  align-items: stretch;
  flex-direction: column;
}

.shared-tags-head input {
  width: 100%;
  box-sizing: border-box;
}

.shared-tag-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 34px;
}

.shared-tag-toolbar > span {
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
}

.shared-tag-toolbar > span b {
  display: inline-grid;
  min-width: 22px;
  height: 22px;
  place-items: center;
  margin-left: 4px;
  border-radius: 999px;
  background: #2563eb;
  color: #fff;
}

.shared-tag-toolbar > div {
  display: flex;
  gap: 6px;
}

.shared-tag-toolbar button {
  padding: 6px 9px;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  cursor: pointer;
  font-family: inherit;
  font-size: 10px;
  font-weight: 900;
}

.shared-tag-toolbar button:last-child {
  border-color: #fee2e2;
  background: #fff1f2;
  color: #dc2626;
}

.shared-tag-toolbar button:disabled {
  opacity: .45;
  cursor: default;
}

.angle-tag-options.shared-tag-options {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  max-height: 230px;
  padding: 4px 3px 4px 6px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #93c5fd #eff6ff;
}

.angle-tag-options.shared-tag-options::-webkit-scrollbar {
  width: 7px;
}

.angle-tag-options.shared-tag-options::-webkit-scrollbar-track {
  border-radius: 999px;
  background: #eff6ff;
}

.angle-tag-options.shared-tag-options::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: #93c5fd;
}

.angle-tag-options.shared-tag-options label {
  min-width: 0;
  min-height: 42px;
  justify-content: flex-start;
  padding: 8px 10px;
  border-radius: 10px;
  overflow: hidden;
}

.angle-tag-options.shared-tag-options label span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.angle-tag-options.shared-tag-options label input {
  width: 16px;
  height: 16px;
  flex: 0 0 16px;
}

.shared-meta-grid {
  display: grid;
  grid-template-columns: minmax(120px, .45fr) minmax(200px, 1fr);
  gap: 10px;
}

.shared-meta-grid select,
.shared-meta-grid textarea {
  width: 100%;
  min-height: 42px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 9px 11px;
  background: #fff;
  font-family: inherit;
  box-sizing: border-box;
}

.shared-meta-grid textarea {
  min-height: 70px;
  resize: vertical;
}

.shared-upload-setup .shared-meta-grid {
  grid-template-columns: 1fr;
}

.shared-upload-setup > .no-consent-check {
  align-items: flex-start;
  padding: 10px 11px;
  border: 1px solid #fecaca !important;
  border-radius: 10px;
  background: #fff7f7 !important;
  color: #b91c1c !important;
  font-size: 11px;
  font-weight: 800;
  line-height: 1.7;
}

.shared-upload-setup > .no-consent-check input {
  margin-top: 3px;
}

.shared-setup-submit {
  width: 100%;
}

.shared-tags-required {
  color: #dc2626;
  font-size: 11px;
  font-weight: 800;
  text-align: center;
}

.empty-sections-guide {
  display: grid;
  gap: 10px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.8;
}

.upload-btn {
  width: 100%;
}

.angle-upload-panel {
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
}

.angle-upload-overlay {
  position: fixed;
  inset: 0;
  z-index: 100000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, .58);
  backdrop-filter: blur(4px);
}

.angle-upload-loader-card {
  min-width: 260px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 28px 32px;
  border-radius: 20px;
  background: #fff;
  color: #0f172a;
  box-shadow: 0 24px 60px rgba(15, 23, 42, .3);
}

.angle-upload-loader-card strong {
  font-size: 16px;
  font-weight: 900;
}

.angle-upload-loader-card small {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.angle-upload-spinner {
  width: 48px;
  height: 48px;
  border: 5px solid #dbeafe;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin .75s linear infinite;
}

.angle-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid #eef2f7;
}

.angle-panel-head h4 {
  margin: 0 0 6px;
  color: #0f172a;
  font-size: 18px;
  font-weight: 900;
}

.angle-panel-head p {
  margin: 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.angle-progress {
  min-width: 150px;
  display: grid;
  gap: 7px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 900;
  text-align: left;
}

.angle-progress div {
  height: 8px;
  border-radius: 999px;
  background: #e2e8f0;
  overflow: hidden;
}

.angle-progress i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #22c55e;
}

.angle-service-tags {
  display: grid;
  gap: 12px;
  margin-top: 14px;
  padding: 14px;
  border: 1px solid #dbeafe;
  border-radius: 14px;
  background: #f8fbff;
}

.angle-service-tags.collapsed {
  padding: 0;
  border-color: #dbeafe;
  background: #fff;
  overflow: hidden;
}

.angle-settings-summary {
  width: 100%;
  min-height: 68px;
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto 24px;
  align-items: center;
  gap: 11px;
  padding: 11px 13px;
  border: 0;
  border-radius: 12px;
  background: linear-gradient(135deg, #eff6ff, #f8fafc);
  color: #334155;
  cursor: pointer;
  font-family: inherit;
  text-align: right;
}

.angle-settings-icon {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  font-size: 19px;
  box-shadow: 0 7px 16px rgba(37, 99, 235, .24);
}

.angle-settings-summary-text {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.angle-settings-summary-text strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 900;
}

.angle-settings-summary-text small {
  overflow: hidden;
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.angle-settings-summary-text .settings-needed {
  color: #dc2626;
}

.angle-settings-action {
  padding: 7px 10px;
  border: 1px solid #bfdbfe;
  border-radius: 9px;
  background: #fff;
  color: #1d4ed8;
  font-size: 10px;
  font-weight: 900;
}

.angle-settings-summary > b {
  color: #64748b;
  font-size: 18px;
  transition: transform .2s ease;
}

.angle-settings-summary > b.open {
  transform: rotate(180deg);
}

.angle-settings-content {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
  align-items: stretch;
  gap: 14px;
  padding-top: 6px;
  animation: angleSettingsIn .18s ease-out;
}

.angle-settings-block {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 15px;
  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: #fff;
}

.angle-settings-content .angle-tags-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(190px, 260px);
  align-items: center;
}

.angle-settings-content .angle-tags-head input {
  width: 100%;
  box-sizing: border-box;
}

.angle-settings-content .angle-tag-options {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-content: start;
  gap: 8px;
  max-height: 180px;
  overflow-y: auto;
  padding: 3px;
}

.angle-settings-content .angle-tag-options label {
  min-width: 0;
  min-height: 42px;
  justify-content: flex-start;
  padding: 8px 11px;
  border-radius: 10px;
}

.angle-settings-content .angle-tag-options label span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.angle-settings-content .angle-tag-options input {
  width: 16px;
  height: 16px;
  flex: 0 0 16px;
}

.angle-settings-content .angle-common-meta {
  padding-top: 15px;
  border-top: 1px solid #e2e8f0;
}

.angle-settings-content .shared-meta-grid {
  grid-template-columns: 1fr;
}

.angle-settings-content .angle-common-checks {
  margin-top: auto;
}

.angle-settings-content .no-consent-check {
  width: 100%;
  padding: 10px 11px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fff7f7;
  color: #b91c1c;
  line-height: 1.6;
}

.angle-settings-done {
  grid-column: 1 / -1;
  justify-self: end;
  padding: 9px 16px;
  border: 0;
  border-radius: 9px;
  background: #2563eb;
  color: #fff;
  cursor: pointer;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
}

@media (max-width: 900px) {
  .angle-settings-content {
    grid-template-columns: 1fr;
  }

  .angle-settings-content .angle-tags-head {
    grid-template-columns: 1fr;
  }

  .angle-settings-done {
    width: 100%;
  }
}

.angle-settings-done:disabled {
  opacity: .5;
  cursor: not-allowed;
}

@keyframes angleSettingsIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.angle-tags-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}

.angle-tags-head > div {
  display: grid;
  gap: 4px;
}

.angle-tags-head strong { color: #0f172a; font-size: 13px; font-weight: 900; }
.angle-tags-head strong b { color: #dc2626; }
.angle-tags-head small { color: #64748b; font-size: 11px; font-weight: 700; }

.angle-tags-head input {
  width: 240px;
  height: 38px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 0 11px;
  background: #fff;
  font-family: inherit;
  outline: none;
}

.angle-tag-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  max-height: 118px;
  overflow-y: auto;
}

.angle-tag-options label {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 11px;
  border: 1px solid #dbe3ea;
  border-radius: 999px;
  background: #fff;
  color: #475569;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}

.angle-tag-options label.selected {
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
}

.angle-tag-options input { accent-color: #2563eb; }
.angle-tags-empty {
  width: 100%;
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 12px;
  color: #94a3b8;
  text-align: center;
}

.angle-tags-empty span {
  font-size: 11px;
  font-weight: 900;
}

.angle-tags-empty button {
  min-height: 28px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #2563eb;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
}

.angle-tags-empty button b {
  width: 18px;
  height: 18px;
  display: inline-grid;
  place-items: center;
  border-radius: 50%;
  background: #eff6ff;
  color: #2563eb;
  font-size: 15px;
  line-height: 1;
}

.angle-tags-empty button:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.angle-common-meta {
  padding-top: 12px;
  border-top: 1px dashed #bfdbfe;
}

.angle-common-checks {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 18px;
}

.angle-common-checks .feature-check {
  color: #334155;
  font-size: 12px;
  font-weight: 800;
}

.angle-workspace {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: 16px;
  padding-top: 16px;
}

.angle-list {
  display: grid;
  gap: 8px;
}

.angle-row {
  width: 100%;
  min-height: 64px;
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  padding: 9px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  color: #334155;
  font-family: inherit;
  text-align: right;
  cursor: pointer;
}

.angle-row.active {
  border-color: #2563eb;
  background: #eff6ff;
}

.angle-row.done {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.angle-row strong,
.angle-row small {
  grid-column: 2;
  min-width: 0;
}

.angle-row strong {
  color: #0f172a;
  font-size: 12px;
  font-weight: 900;
}

.angle-row small {
  color: #64748b;
  font-size: 11px;
}

.angle-row em {
  grid-column: 3;
  grid-row: 1 / span 2;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 10px;
  font-style: normal;
  font-weight: 900;
}

.angle-row.done em {
  background: #dcfce7;
  color: #15803d;
}

.angle-mini {
  grid-row: 1 / span 2;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #c7d2fe, #eff6ff);
  border: 2px solid #2563eb;
  position: relative;
}

.angle-mini::after {
  content: "";
  position: absolute;
  right: 5px;
  top: 7px;
  width: 8px;
  height: 12px;
  border-radius: 50%;
  background: #94a3b8;
}

.angle-stage-card {
  display: grid;
  grid-template-columns: minmax(240px, .9fr) minmax(260px, 1fr);
  gap: 14px;
  align-items: stretch;
}

.angle-guide {
  min-height: 310px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: linear-gradient(180deg, #f8fafc, #fff);
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 16px;
  padding: 16px;
}

.angle-guide-tabs {
  display: flex;
  align-self: center;
  gap: 5px;
  padding: 4px;
  border: 1px solid #e2e8f0;
  border-radius: 11px;
  background: #fff;
}

.angle-guide-tabs button {
  border: 0;
  border-radius: 8px;
  padding: 7px 12px;
  background: transparent;
  color: #64748b;
  font-family: inherit;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}

.angle-guide-tabs button.active {
  background: #eff6ff;
  color: #2563eb;
}

.face-orbit {
  height: 210px;
  display: flex;
  align-items: center;
  justify-content: center;
  perspective: 680px;
  border-radius: 50%;
  background: radial-gradient(circle, #eaf1ff, #fff 68%);
}

.face-model {
  width: 112px;
  height: 148px;
  position: relative;
  transform-style: preserve-3d;
  transition: transform .35s ease;
}

.face-model span {
  position: absolute;
  display: block;
}

.face-model .head {
  inset: 10px 0 0;
  border-radius: 48% 48% 44% 44%;
  background: linear-gradient(#fde3cf, #f9d3ba);
  box-shadow: 0 10px 26px rgba(15, 23, 42, .14), inset -8px 0 14px rgba(15, 23, 42, .05);
}

.face-model .hair {
  right: -3px;
  left: -3px;
  top: 0;
  height: 58px;
  border-radius: 60px 60px 28px 28px;
  background: linear-gradient(#5b3b28, #43291b);
  z-index: 2;
}

.face-model .ear {
  top: 64px;
  width: 14px;
  height: 24px;
  border-radius: 50%;
  background: #f2c2a3;
  z-index: 0;
}

.face-model .ear.left {
  left: -8px;
}

.face-model .ear.right {
  right: -8px;
}

.face-model .eye {
  top: 70px;
  width: 14px;
  height: 9px;
  border-radius: 50%;
  background: #fff;
  z-index: 3;
}

.face-model .eye.left {
  left: 28px;
}

.face-model .eye.right {
  right: 28px;
}

.face-model .nose {
  left: 50%;
  top: 82px;
  width: 10px;
  height: 22px;
  transform: translateX(-50%) translateZ(18px);
  border-radius: 7px;
  background: #efc09e;
  z-index: 4;
}

.face-model .mouth {
  left: 50%;
  top: 116px;
  width: 28px;
  height: 8px;
  transform: translateX(-50%);
  border-radius: 0 0 14px 14px;
  background: #d98a72;
  z-index: 4;
}

.top-angle-guide {
  position: relative;
  width: 100%;
  height: 100%;
}

.head-top {
  position: absolute;
  left: 50%;
  top: 42%;
  width: 105px;
  height: 105px;
  border-radius: 50%;
  background: radial-gradient(circle at 50% 72%, #cbd5e1 0 24%, #64748b 25% 100%);
  box-shadow: 0 8px 20px rgba(15, 23, 42, .16);
  transition: transform .35s ease;
}

.head-top::after {
  content: "";
  position: absolute;
  left: 50%;
  bottom: -15px;
  transform: translateX(-50%);
  border-right: 11px solid transparent;
  border-left: 11px solid transparent;
  border-top: 18px solid #94a3b8;
}

.camera-icon {
  position: absolute;
  left: 50%;
  bottom: 4px;
  z-index: 2;
  transform: translateX(-50%);
  color: #2563eb;
  font-size: 31px;
}

.camera-beam {
  position: absolute;
  left: 50%;
  bottom: 24px;
  width: 0;
  height: 0;
  transform: translateX(-50%);
  border-right: 58px solid transparent;
  border-left: 58px solid transparent;
  border-bottom: 132px solid rgba(37, 99, 235, .08);
}

.angle-current {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 7px;
  text-align: center;
}

.angle-current strong {
  color: #0f172a;
  font-size: 15px;
  font-weight: 900;
}

.angle-current span {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.angle-dropzone {
  min-height: 310px;
  border: 2px dashed #cbd5e1;
  border-radius: 16px;
  background: #fbfdff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 18px;
  text-align: center;
  cursor: pointer;
  color: #64748b;
  overflow: hidden;
  position: relative;
}

.angle-dropzone input {
  display: none;
}

.angle-dropzone > b:first-of-type {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  border-radius: 16px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 28px;
}

.angle-dropzone strong {
  color: #0f172a;
  font-size: 15px;
  font-weight: 900;
}

.angle-dropzone small {
  color: #94a3b8;
  font-size: 12px;
  font-weight: 700;
}

.angle-dropzone.filled {
  border: 0;
  padding: 0;
  background: #0f172a;
}

.angle-dropzone.dragging {
  border-color: #2563eb;
  background: #eff6ff;
}

.angle-dropzone.filled img {
  width: 100%;
  height: 100%;
  min-height: 310px;
  object-fit: cover;
  display: block;
}

.angle-check {
  position: absolute;
  top: 12px;
  left: 12px;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #22c55e;
  color: #fff;
  font-size: 18px;
  box-shadow: 0 8px 18px rgba(22, 163, 74, .3);
}

.angle-replace {
  position: absolute;
  right: 12px;
  bottom: 12px;
  left: 12px;
  min-height: 44px;
  display: grid;
  place-items: center;
  padding: 10px 78px 10px 12px;
  border-radius: 12px;
  background: rgba(255, 255, 255, .94);
  color: #0f172a;
  font-size: 12px;
  font-weight: 900;
}

.angle-remove {
  position: absolute;
  left: 12px;
  bottom: 18px;
  z-index: 3;
  min-width: 58px;
  height: 32px;
  border: 0;
  border-radius: 10px;
  padding: 0 12px;
  background: rgba(220, 38, 38, .94);
  color: #fff;
  font-family: inherit;
  font-size: 10px;
  font-weight: 900;
  cursor: pointer;
}

.angle-finish-btn {
  margin-right: auto;
  border: 0;
  border-radius: 11px;
  padding: 10px 18px;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  box-shadow: 0 6px 16px rgba(22, 163, 74, .24);
  cursor: pointer;
}

.angle-actions {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 2px;
}

.angle-nav-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.angle-nav-btn {
  width: 46px;
  min-width: 46px;
  height: 42px;
  padding: 0;
  font-family: Arial, sans-serif;
  font-size: 25px;
  font-weight: 900;
  line-height: 1;
}

.media-angle-badge {
  width: fit-content;
  max-width: 100%;
  padding: 4px 8px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
  font-size: 11px;
  font-weight: 900;
}

.media-content {
  position: relative;
  padding: 16px;
  overflow-y: auto;
  background: #f6f8fb;
}

.media-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  color: #0f766e;
  font-size: 12px;
  font-weight: 800;
}

.media-list-search {
  position: sticky;
  top: 0;
  z-index: 4;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: -2px 0 14px;
  padding: 10px;
  border: 1px solid #dbeafe;
  border-radius: 14px;
  background: rgba(255, 255, 255, .96);
  box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
}

.media-list-search span {
  color: #2563eb;
  font-size: 21px;
  font-weight: 900;
}

.media-list-search input {
  flex: 1;
  min-height: 38px;
  border: 0;
  outline: 0;
  background: transparent;
  font-family: inherit;
}

.media-list-search button {
  width: 30px;
  height: 30px;
  border: 0;
  border-radius: 50%;
  background: #fee2e2;
  color: #dc2626;
  cursor: pointer;
  font-size: 18px;
  font-weight: 900;
}

.folder-grid,
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.folder-tile {
  min-height: 86px;
  border: 1px solid #dbeafe;
  border-radius: 14px;
  background: #eff6ff;
  color: #1e3a8a;
  cursor: pointer;
  font-family: inherit;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.folder-icon {
  width: 34px;
  height: 24px;
  border-radius: 5px;
  background: #60a5fa;
  position: relative;
}

.folder-icon::before {
  content: "";
  position: absolute;
  right: 5px;
  top: -6px;
  width: 16px;
  height: 8px;
  border-radius: 4px 4px 0 0;
  background: #93c5fd;
}

.folder-tile.no-consent-folder {
  border: 2px solid #ef4444;
  background: #fff1f2;
  color: #b91c1c;
  box-shadow: 0 8px 20px rgba(220, 38, 38, .12);
}

.folder-tile.no-consent-folder .folder-icon,
.folder-tile.no-consent-folder .folder-icon::before {
  background: #ef4444;
}

.folder-consent-warning {
  padding: 3px 7px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: 9px;
  font-weight: 900;
}

.folder-context-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10020;
}

.folder-context-menu {
  position: fixed;
  width: 190px;
  padding: 8px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, .22);
}

.folder-context-menu strong {
  display: block;
  padding: 5px 8px 9px;
  overflow: hidden;
  color: #475569;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.folder-context-menu button {
  width: 100%;
  padding: 10px 12px;
  border: 0;
  border-radius: 8px;
  background: #fff1f2;
  color: #dc2626;
  cursor: pointer;
  font-family: inherit;
  font-weight: 900;
  text-align: right;
}

.folder-context-menu button:hover {
  background: #fee2e2;
}

.compare-overlay {
  z-index: 10030;
  padding: 22px;
  background: rgba(15, 23, 42, .64);
  backdrop-filter: blur(8px);
}

.compare-modal {
  width: min(1120px, 96vw);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, .72);
  border-radius: 22px;
  background: #f8fafc;
  direction: rtl;
  box-shadow: 0 30px 90px rgba(15, 23, 42, .34);
}

.compare-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 16px 18px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
}

.compare-head h3 {
  margin: 0 0 4px;
  color: #0f172a;
  font-size: 18px;
  font-weight: 1000;
}

.compare-head p {
  margin: 0;
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
}

.compare-single-view {
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  overflow: hidden;
}

.compare-pair-card {
  min-height: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #dbeafe;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
}

.compare-pair-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 11px 13px;
  border-bottom: 1px solid #eef2f7;
}

.compare-pair-title strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 1000;
}

.compare-pair-title span {
  overflow: hidden;
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.compare-photo-grid {
  min-height: 0;
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;
  background: #e2e8f0;
}

.compare-photo-grid figure {
  position: relative;
  min-height: 420px;
  margin: 0;
  overflow: hidden;
  background: #0f172a;
}

.compare-photo-grid figure b {
  position: absolute;
  z-index: 2;
  top: 12px;
  right: 12px;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, .92);
  color: #0f172a;
  font-size: 11px;
  font-weight: 1000;
  box-shadow: 0 6px 16px rgba(15, 23, 42, .18);
}

.compare-photo-grid img {
  width: 100%;
  height: 100%;
  max-height: min(62vh, 620px);
  min-height: 420px;
  display: block;
  object-fit: contain;
}

.compare-photo-grid figure.empty {
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}

.compare-upload-missing {
  height: 42px;
  padding: 0 22px;
  border: 0;
  border-radius: 12px;
  background: #2563eb;
  color: #fff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 1000;
  cursor: pointer;
  box-shadow: 0 10px 22px rgba(37, 99, 235, .24);
}

.compare-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  flex: 0 0 auto;
}

.compare-nav button {
  height: 38px;
  min-width: 86px;
  border: 1px solid #dbeafe;
  border-radius: 12px;
  background: #fff;
  color: #1d4ed8;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}

.compare-nav button:disabled {
  opacity: .45;
  cursor: not-allowed;
}

.compare-nav span {
  min-width: 58px;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  text-align: center;
}

.compare-state {
  min-height: 260px;
  display: grid;
  place-items: center;
  gap: 10px;
  padding: 24px;
  color: #64748b;
  text-align: center;
}

.media-item {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  transition: .2s;
}

.media-item:hover {
  border-color: #c7d2fe;
  box-shadow: 0 12px 26px rgba(67, 56, 202, .1);
}

.media-item.no-consent-media {
  border: 3px solid #ef4444;
  box-shadow: 0 10px 28px rgba(220, 38, 38, .2);
}

.angle-dropzone.no-consent-photo {
  border: 3px solid #ef4444;
  box-shadow: 0 0 0 5px rgba(239, 68, 68, .12);
}

.no-consent-check{border-color:#fecaca!important;background:#fff1f2!important;color:#b91c1c!important}.no-consent-badge{position:absolute;right:8px;bottom:8px;z-index:5;padding:5px 8px;border:1px solid #fecaca;border-radius:8px;background:rgba(153,27,27,.92);color:#fff;font-size:9px;font-weight:900;box-shadow:0 3px 12px rgba(127,29,29,.3)}

.media-preview {
  position: relative;
  aspect-ratio: 4 / 3;
  background: #e2e8f0;
}

.media-preview img,
.media-preview video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.star-btn {
  position: absolute;
  top: 8px;
  left: 8px;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 50%;
  background: rgba(15, 23, 42, .55);
  color: #fff;
  cursor: pointer;
  font-size: 18px;
  line-height: 34px;
}

.star-btn.active {
  background: #f59e0b;
  color: #fff;
}

.star-btn:disabled {
  cursor: wait;
  opacity: .82;
}

.star-btn.loading {
  font-size: 12px;
  letter-spacing: 0;
}

.media-delete-btn {
  position: absolute;
  right: 8px;
  top: 8px;
  min-width: 46px;
  height: 30px;
  border: 0;
  border-radius: 999px;
  background: rgba(220, 38, 38, .92);
  color: #fff;
  cursor: pointer;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  box-shadow: 0 8px 18px rgba(220, 38, 38, .24);
}

.media-delete-btn:hover {
  background: #b91c1c;
}

.media-delete-btn:disabled {
  cursor: wait;
  opacity: .8;
}

.media-info {
  padding: 11px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.media-info strong {
  color: #111827;
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.media-info span,
.media-info small {
  color: #64748b;
  font-size: 11px;
}

.media-path-label {
  width: fit-content;
  max-width: 100%;
  padding: 4px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-style: normal;
  line-height: 1.6;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.media-info p {
  color: #334155;
  font-size: 12px;
  line-height: 1.7;
  margin: 0;
}

.media-audit {
  padding-top: 6px;
  border-top: 1px solid #f1f5f9;
}

.media-edit-btn {
  height: 34px;
  border: 1px solid #dbeafe;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  transition: .2s;
}

.media-edit-btn:hover {
  background: #dbeafe;
}

.media-edit-overlay {
  z-index: 10000;
}

.media-edit-modal {
  width: min(820px, 94vw);
  max-height: 90vh;
  overflow: hidden;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 28px 80px rgba(15, 23, 42, .3);
  direction: rtl;
  display: flex;
  flex-direction: column;
}

.media-edit-body {
  display: grid;
  grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
  gap: 16px;
  padding: 16px;
  overflow-y: auto;
  background: #f8fafc;
}

.media-edit-preview {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  background: #e2e8f0;
  aspect-ratio: 4 / 3;
  align-self: start;
}

.media-edit-preview img,
.media-edit-preview video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.media-edit-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.media-edit-form label {
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.media-edit-form textarea {
  width: 100%;
  min-height: 100px;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  padding: 10px;
  resize: vertical;
  font-family: inherit;
}

.media-edit-form .modal-actions {
  margin-top: 8px;
}

.media-empty {
  padding: 36px;
  text-align: center;
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  color: #64748b;
  background: #fff;
}

@media (max-width: 900px) {
  .media-layout {
    grid-template-columns: 1fr;
  }

  .media-side {
    border-left: 0;
    border-bottom: 1px solid #e5e7eb;
    max-height: 360px;
  }

  .angle-workspace,
  .angle-stage-card {
    grid-template-columns: 1fr;
  }

  .angle-panel-head,
  .angle-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .angle-progress {
    min-width: 0;
    width: 100%;
  }

  .media-toolbar {
    flex-wrap: wrap;
  }

  .media-path {
    order: 3;
    flex-basis: 100%;
  }

  .compare-overlay {
    padding: 10px;
  }

  .compare-photo-grid {
    grid-template-columns: 1fr;
  }

  .compare-photo-grid figure,
  .compare-photo-grid img {
    min-height: 240px;
  }
}

.wallet-modal {
  background: #fff;
  padding: 24px;
  border-radius: 12px;
  width: 100%;
  max-width: 450px;
  box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}
.wallet-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 12px;
  margin-bottom: 16px;
}
.wallet-modal-header h3 {
  margin: 0;
  color: #1f2937;
}
.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #9ca3af;
  cursor: pointer;
}
.close-btn:hover {
  color: #ef4444;
}
.balance-box {
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 12px;
  border-radius: 8px;
  margin: 16px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.balance-amount {
  color: #16a34a;
  font-size: 1.1rem;
}
.wallet-input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 20px;
}
.wallet-input-group input {
  padding: 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 1rem;
}
.wallet-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.wallet-report{margin-top:18px;border-top:1px solid #e2e8f0;padding-top:14px}.wallet-report-head{display:flex;align-items:center;justify-content:space-between;gap:10px}.wallet-report-head button{padding:6px 10px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-weight:800;cursor:pointer}.wallet-report-list{max-height:310px;display:grid;gap:8px;margin-top:10px;overflow:auto}.wallet-report-list>p{padding:16px;color:#64748b;text-align:center}.wallet-report-list article{display:grid;gap:5px;padding:10px;border:1px solid #e2e8f0;border-right:4px solid #ef4444;border-radius:10px;background:#fff}.wallet-report-list article.deposit{border-right-color:#22c55e}.wallet-report-list article>div{display:flex;align-items:center;justify-content:space-between}.wallet-report-list article.deposit>div strong{color:#15803d}.wallet-report-list article.withdraw>div strong{color:#b91c1c}.wallet-report-list article span{color:#334155;font-size:12px}.wallet-report-list article small{color:#94a3b8;font-size:11px}.wallet-report-list details{padding-top:5px;border-top:1px dashed #cbd5e1;color:#475569;font-size:11px}.wallet-report-list summary{cursor:pointer;font-weight:900}.wallet-report-list details div{padding:4px 0}
.deposit-btn {
  background-color: #16a34a;
  color: white;
  border: none;
  padding: 10px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
}
.deposit-btn:hover { background-color: #15803d; }
.withdraw-btn {
  background-color: #dc2626;
  color: white;
  border: none;
  padding: 10px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
}
.withdraw-btn:hover { background-color: #b91c1c; }
</style>
