<template>
  <main class="beauty-flow" dir="rtl">
    <section v-if="!activePatient" class="beauty-flow-card">
      <div class="beauty-flow-main-toolbar">
        <button type="button" class="beauty-flow-settings-btn" title="تنظیم مشکلات چهره" aria-label="تنظیم مشکلات چهره" @click="openProblemsSettings">⚙</button>
        <button
          type="button"
          class="beauty-flow-create-btn"
          title="ایجاد برنامه زیبایی"
          aria-label="ایجاد برنامه زیبایی"
          @click="showCreatePanel = !showCreatePanel"
        >+</button>
        <div class="beauty-flow-search-grid beauty-flow-search-grid-compact">
          <input v-model.trim="search.file_number" type="text" placeholder="شماره پرونده" @keydown.enter.prevent="searchPatients">
          <input v-model.trim="search.phone" type="text" placeholder="شماره تماس" @keydown.enter.prevent="searchPatients">
          <button type="button" :disabled="searching" @click="searchPatients">
            {{ searching ? 'در حال جستجو...' : 'جستجو' }}
          </button>
        </div>
      </div>

      <div v-if="showCreatePanel || searched || searching || searchError" class="beauty-flow-create-panel">
        <div v-if="patients.length" class="beauty-flow-create-results">
          <button v-for="patient in patients" :key="patient.id" type="button" class="beauty-flow-search-result" @click="openRecord(patient)">
            <span :class="['beauty-flow-avatar', `beauty-flow-avatar-${patient.customer_level || 'silver'}`]">
              <img v-if="patient.avatar_url" :src="patient.avatar_url" alt="">
              <b v-else>{{ patientInitial(patient) }}</b>
            </span>
            <span class="beauty-flow-search-result-info"><strong>{{ patientName(patient) }}</strong><small>پرونده {{ patient.file_number || '-' }} · {{ displayPatientPhone(patient.phone) || '-' }}</small><span v-if="patient.search_media_loading" class="beauty-flow-search-media-loading">در حال دریافت عکس‌ها...</span><span v-else-if="patient.search_media?.length" class="beauty-flow-search-media"><img v-for="media in patient.search_media" :key="media.id" :src="media.url" :alt="media.original_name || 'عکس مراجعه‌کننده'" loading="lazy"><small>{{ patient.search_media.length.toLocaleString('fa-IR') }} عکس آپلودشده</small></span><small v-else-if="patient.search_media_loaded">عکس آپلودشده‌ای ثبت نشده است</small></span>
            <b>ساخت برنامه</b>
          </button>
        </div>
        <p v-if="searched && !patients.length" class="beauty-flow-muted">پرونده‌ای با این مشخصات پیدا نشد.</p>
        <p v-if="searching" class="beauty-flow-muted">در حال دریافت اطلاعات پرونده...</p>
        <p v-if="searchError" class="beauty-flow-error">{{ searchError }}</p>
      </div>

      <header class="beauty-flow-card-header">
        <div><h2>پرونده‌های در انتظار</h2></div>
        <button type="button" class="beauty-flow-light-btn beauty-flow-refresh-btn" :disabled="worklistLoading" @click="loadWorklist">
          {{ worklistLoading ? 'در حال دریافت...' : 'به‌روزرسانی' }}
        </button>
      </header>

      <div class="beauty-flow-worklist-filters">
        <date-picker v-model="worklistFilters.date_from" format="YYYY-MM-DD" display-format="jYYYY/jMM/jDD" placeholder="از تاریخ" auto-submit @change="loadWorklist" />
        <date-picker v-model="worklistFilters.date_to" format="YYYY-MM-DD" display-format="jYYYY/jMM/jDD" placeholder="تا تاریخ" auto-submit @change="loadWorklist" />
      </div>

      <div class="beauty-flow-table-wrap">
        <table class="beauty-flow-table beauty-flow-worklist-table">
          <thead><tr><th>نام</th><th>شماره پرونده</th><th>شماره تماس</th><th>ناحیه / مشکل</th><th>توضیح</th><th>تاریخ</th><th>وضعیت</th><th>پرونده</th></tr></thead>
          <tbody>
            <tr v-if="worklistLoading"><td colspan="8" class="beauty-flow-table-empty">در حال دریافت پرونده‌ها...</td></tr>
            <tr v-else-if="!worklist.length"><td colspan="8" class="beauty-flow-table-empty">پرونده در انتظاری برای این بازه پیدا نشد.</td></tr>
            <template v-else>
              <tr v-for="item in worklist" :key="item.patient_id">
                <td class="bf-cell-name" data-label="نام"><div class="beauty-flow-patient-cell"><span :class="['beauty-flow-avatar', `beauty-flow-avatar-${item.patient?.customer_level || 'silver'}`]"><img v-if="item.patient?.avatar_url" :src="item.patient.avatar_url" alt=""><b v-else>{{ patientInitial(item.patient) }}</b></span><span>{{ patientName(item.patient) }}</span></div></td>
                <td data-label="شماره پرونده">{{ item.patient?.file_number || '-' }}</td>
                <td data-label="شماره تماس">{{ displayPatientPhone(item.patient?.phone) || '-' }}</td>
                <td data-label="ناحیه / مشکل">{{ annotationCategory(item) }}</td>
                <td data-label="توضیح">{{ annotationNoteSummary(item) }}</td>
                <td data-label="تاریخ">{{ formatDate(item.annotation_date || item.created_at) }}</td>
                <td class="bf-cell-status" data-label="وضعیت"><span class="beauty-flow-status beauty-flow-status-pending">در انتظار</span></td>
                <td class="bf-cell-action"><button type="button" class="beauty-flow-open-btn" @click="openWorklistRecord(item)">باز کردن پرونده</button></td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>
    <section v-if="false" class="beauty-flow-card">
      <header class="beauty-flow-card-header">
        <h2>نتایج جستجو</h2>
        <span>{{ patients.length }} مورد</span>
      </header>

      <div class="beauty-flow-table-wrap">
        <table class="beauty-flow-table">
          <thead>
            <tr>
              <th>نام</th>
              <th>نام خانوادگی</th>
              <th>شماره پرونده</th>
              <th>شماره تماس</th>
              <th>نوع مشتری</th>
              <th>پرونده</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="patient in patients" :key="patient.id">
              <td>
                <div class="beauty-flow-patient-cell">
                  <span :class="['beauty-flow-avatar', `beauty-flow-avatar-${patient.customer_level || 'silver'}`]">
                    <img v-if="patient.avatar_url" :src="patient.avatar_url" alt="">
                    <b v-else>{{ patientInitial(patient) }}</b>
                  </span>
                  <span>{{ patient.first_name || '-' }}</span>
                </div>
              </td>
              <td>{{ patient.last_name || '-' }}</td>
              <td>{{ patient.file_number || '-' }}</td>
              <td>{{ displayPatientPhone(patient.phone) || '-' }}</td>
              <td>
                <span :class="['beauty-flow-level', `beauty-flow-level-${patient.customer_level || 'normal'}`]">
                  {{ customerLevelLabel(patient.customer_level) }}
                </span>
              </td>
              <td>
                <button type="button" class="beauty-flow-open-btn" @click="openRecord(patient)">باز کردن پرونده</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="false" class="beauty-flow-card beauty-flow-empty">
      پرونده‌ای با این مشخصات پیدا نشد.
    </section>

    <section v-if="activePatient" class="beauty-flow-record">
      <PatientProfilePhotoUploader
        ref="profileUploader"
        :patient-id="activePatient.id"
        @uploaded="onProfilePhotoUploaded"
        @error="message => searchError = message"
      />
      <header class="beauty-flow-record-header">
        <div class="beauty-flow-record-title">
          <span class="beauty-flow-record-badge" aria-hidden="true">✦</span>
          <div>
            <h2>زیبایار پرونده</h2>
            <p>{{ patientName(activePatient) }} · پرونده {{ activePatient.file_number || '-' }}</p>
          </div>
        </div>
        <div class="beauty-flow-record-actions">
          <button type="button" class="beauty-flow-back beauty-flow-problems-settings-btn" @click="openProblemsSettings">مشکلات چهره</button>
          <button type="button" class="beauty-flow-back" @click="$emit('back-to-patient-profile', activePatient)">بازگشت به پرونده</button>
          <button type="button" class="beauty-flow-back" @click="closeRecord">بازگشت</button>
        </div>
      </header>

      <div
        v-if="draftPoint || selectedAnnotation"
        class="beauty-flow-sheet-backdrop"
        aria-hidden="true"
        @click="draftPoint = null; selectedAnnotation = null"
      ></div>
      <div class="beauty-flow-workspace">
        <aside class="beauty-flow-sidebar">
          <section class="beauty-flow-patient-box">
            <span :class="['beauty-flow-avatar beauty-flow-avatar-large', `beauty-flow-avatar-${activePatient.customer_level || 'silver'}`]">
              <img v-if="activePatient.avatar_url" :src="activePatient.avatar_url" alt="">
              <b v-else>{{ patientInitial(activePatient) }}</b>
            </span>
            <strong>{{ patientName(activePatient) }}</strong>
            <span>{{ displayPatientPhone(activePatient.phone) || '-' }}</span>
            <span :class="['beauty-flow-level', `beauty-flow-level-${activePatient.customer_level || 'normal'}`]">
              {{ customerLevelLabel(activePatient.customer_level) }}
            </span>
          </section>

          <section class="beauty-flow-side-block">
            <label>عکس برنامه زیبایی</label>
            <button
              v-if="profilePhoto"
              type="button"
              :class="['beauty-flow-profile-pick', { 'beauty-flow-profile-pick-active': isProfileSelected }]"
              :aria-pressed="isProfileSelected"
              @click="selectProfilePhoto"
            >
              <img :src="profilePhoto.thumbnail_url || profilePhoto.url" alt="عکس پروفایل بیمار">
              <span>
                <strong>عکس پروفایل</strong>
                <small>{{ isProfileSelected ? 'انتخاب شده برای نقطه‌گذاری' : 'برای نقطه‌گذاری انتخاب کنید' }}</small>
              </span>
            </button>
            <div v-else class="beauty-flow-profile-missing">
              <strong>عکس پروفایل ثبت نشده است.</strong>
              <span>برای نقطه‌گذاری در زیبایار، ابتدا عکس پروفایل بیمار را آپلود کنید.</span>
              <button type="button" class="beauty-flow-upload-profile-btn" :disabled="recordLoading" @click="openProfileUpload">آپلود عکس پروفایل</button>
            </div>
            <small class="beauty-flow-default-photo-note">
              نقاط زیبایار فقط روی عکس پروفایل بیمار ثبت می‌شوند.
            </small>
          </section>

          <section v-if="legacyPhotos.length" class="beauty-flow-side-block beauty-flow-legacy-block">
            <label>نقاط ثبت‌شده روی عکس‌های قبلی</label>
            <button
              v-for="photo in legacyPhotos"
              :key="photo.id"
              type="button"
              :class="['beauty-flow-legacy-pick', { 'beauty-flow-legacy-pick-active': String(selectedPhotoId) === String(photo.id) }]"
              @click="selectLegacyPhoto(photo)"
            >
              {{ photoLabel(photo) }}
            </button>
            <small class="beauty-flow-default-photo-note">
              این عکس‌ها فقط برای مشاهده و پیگیری نقاط قبلی نمایش داده می‌شوند.
            </small>
          </section>

          <section v-if="draftPoint" class="beauty-flow-editor">
            <h3>ثبت نقطه جدید</h3>

            <label>تاریخ نقطه</label>
            <date-picker
              v-model="draftPoint.annotation_date"
              format="YYYY-MM-DD"
              display-format="jYYYY/jMM/jDD"
              placeholder="انتخاب تاریخ"
              auto-submit
            />

            <label>نواحی</label>
            <select v-model="draftPoint.area">
              <option value="">انتخاب ناحیه</option>
              <option v-for="area in areas" :key="area" :value="area">{{ area }}</option>
            </select>

            <label>مشکل صورت</label>
            <select v-model="draftPoint.problem">
              <option value="">انتخاب مشکل</option>
              <option v-for="problem in problems" :key="problem" :value="problem">{{ problem }}</option>
            </select>

            <label>توضیح</label>
            <textarea v-model.trim="draftPoint.note" rows="4" placeholder="توضیح پزشک"></textarea>

            <div class="beauty-flow-voice-box">
              <div>
                <strong>ویس توضیح</strong>
                <small>{{ draftVoiceBlob ? 'ویس آماده ثبت است' : 'به جای تایپ می‌توانید ویس ضبط کنید.' }}</small>
              </div>
              <div class="beauty-flow-voice-actions">
                <button v-if="!voiceRecording" type="button" class="beauty-flow-light-btn" @click="startVoiceRecording">ضبط ویس</button>
                <button v-else type="button" class="beauty-flow-recording-btn" @click="stopVoiceRecording">توقف ضبط</button>
                <button v-if="draftVoiceBlob" type="button" class="beauty-flow-light-btn" @click="clearDraftVoice">حذف ویس</button>
              </div>
              <audio v-if="draftVoiceUrl" :src="draftVoiceUrl" controls></audio>
            </div>

            <div class="beauty-flow-editor-actions">
              <button type="button" class="beauty-flow-light-btn" @click="draftPoint = null">لغو</button>
              <button type="button" :disabled="savingPoint" @click="savePoint">
                {{ savingPoint ? 'در حال ذخیره...' : 'ثبت نقطه' }}
              </button>
            </div>
          </section>

          <section v-if="selectedAnnotation" class="beauty-flow-editor beauty-flow-editor-selected">
            <div class="beauty-flow-editor-title">
              <h3>جزئیات نقطه</h3>
              <button
                type="button"
                class="beauty-flow-delete-icon"
                title="حذف نقطه"
                :disabled="savingPoint"
                @click="deleteAnnotation(selectedAnnotation)"
              >
                ×
              </button>
            </div>
            <p><b>ناحیه:</b> {{ selectedAnnotation.area || '-' }}</p>
            <p><b>مشکل:</b> {{ selectedAnnotation.problem || '-' }}</p>
            <p><b>توضیح:</b> {{ selectedAnnotation.note || '-' }}</p>
            <div v-if="selectedAnnotation.voice_url" class="beauty-flow-voice-player">
              <b>ویس:</b>
              <audio :src="selectedAnnotation.voice_url" controls></audio>
            </div>
            <p><b>تاریخ:</b> {{ formatDate(selectedAnnotation.annotation_date || selectedAnnotation.created_at) }}</p>
            <div class="beauty-flow-editor-actions">
              <button type="button" class="beauty-flow-light-btn" @click="selectedAnnotation = null">بستن</button>
              <button v-if="selectedAnnotation.status !== 'done'" type="button" :disabled="savingPoint" @click="markDone(selectedAnnotation)">انجام شد</button>
              <button
                v-else
                type="button"
                class="beauty-flow-light-btn"
                :disabled="savingPoint"
                @click="markPending(selectedAnnotation)"
              >
                انجام نشد
              </button>
            </div>
          </section>
        </aside>

        <section class="beauty-flow-photo-panel">
          <p v-if="searchError && !recordLoading" class="beauty-flow-error">{{ searchError }}</p>
          <div v-if="recordLoading" class="beauty-flow-photo-empty">در حال دریافت پرونده...</div>

          <div v-else-if="!selectedPhoto" class="beauty-flow-photo-empty">
            <strong>عکس پروفایل برای این بیمار ثبت نشده است.</strong>
            <span>نقاط زیبایار فقط روی عکس پروفایل ثبت می‌شوند؛ ابتدا عکس پروفایل بیمار را آپلود کنید.</span>
            <button type="button" class="beauty-flow-add-photo-btn" @click="openProfileUpload">آپلود عکس پروفایل</button>
          </div>

          <template v-else>
            <header class="beauty-flow-photo-header">
              <div>
                <h3>{{ isProfileSelected ? 'عکس پروفایل' : (selectedPhoto.photo_angle_label || 'عکس قبلی') }}</h3>
                <p>{{ isProfileSelected ? 'برای ثبت نقطه روی عکس کلیک کنید' : 'عکس قبلی · فقط مشاهده و پیگیری نقاط ثبت‌شده' }}</p>
              </div>
              <div class="beauty-flow-point-count">
                <span>{{ annotations.length }} نقطه</span>
                <span>{{ doneCount }} انجام شده</span>
              </div>
              <button
                v-if="annotations.some(item => item.status !== 'done')"
                type="button"
                class="beauty-flow-mark-all"
                :disabled="savingPoint"
                @click="markAllDone"
              >
                همه نقاط انجام شد
              </button>
            </header>

            <div :class="['beauty-flow-photo-stage', { 'beauty-flow-photo-stage-readonly': !isProfileSelected }]" @click="startPoint">
              <img :src="selectedPhoto.url" :alt="isProfileSelected ? 'عکس پروفایل بیمار' : 'عکس قبلی بیمار'">
              <button
                v-for="point in annotations"
                :key="point.id"
                type="button"
                class="beauty-flow-point"
                :class="{ 'beauty-flow-point-done': point.status === 'done' }"
                :style="{ left: `${point.x_percent}%`, top: `${point.y_percent}%` }"
                @click.stop="selectedAnnotation = point; draftPoint = null"
              ></button>
              <div
                v-for="point in annotations"
                :key="`label-${point.id}`"
                class="beauty-flow-point-note"
                :class="{ 'beauty-flow-point-note-done': point.status === 'done' }"
                :style="{ left: `${point.x_percent}%`, top: `${point.y_percent}%` }"
                @click.stop="selectedAnnotation = point; draftPoint = null"
              >
                <strong>{{ annotationCategory(point) }}</strong>
                <span>{{ point.voice_url ? 'دارای ویس' : annotationNoteSummary(point) }}</span>
                <button
                  v-if="hasMoreAnnotationText(point) || point.voice_url"
                  type="button"
                  @click.stop="selectedAnnotation = point; draftPoint = null"
                >
                  ادامه
                </button>
              </div>
              <span
                v-if="draftPoint"
                class="beauty-flow-point beauty-flow-point-draft"
                :style="{ left: `${draftPoint.x_percent}%`, top: `${draftPoint.y_percent}%` }"
              ></span>
            </div>
          </template>

          <!-- اولین عکس قبل و آخرین عکس بعد؛ با «بعدی/قبلی» هر دو با هم به زاویهٔ دیگر می‌روند. -->
          <section v-if="!recordLoading && compareAngles.length" class="beauty-compare">
            <header class="beauty-compare-head">
              <div>
                <h3>مقایسهٔ قبل و بعد</h3>
                <p>اولین عکس قبل و آخرین عکس بعد · {{ compareAngle.label }}</p>
              </div>
              <div class="beauty-compare-nav">
                <button type="button" class="beauty-compare-arrow" title="زاویهٔ قبلی" aria-label="زاویهٔ قبلی" :disabled="compareAngles.length < 2" @click="moveCompareAngle(-1)"><svg class="bf-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
                <span>{{ (compareAngleIndex + 1).toLocaleString('fa-IR') }} از {{ compareAngles.length.toLocaleString('fa-IR') }}</span>
                <button type="button" class="beauty-compare-arrow" title="زاویهٔ بعدی" aria-label="زاویهٔ بعدی" :disabled="compareAngles.length < 2" @click="moveCompareAngle(1)"><svg class="bf-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
              </div>
            </header>
            <div class="beauty-compare-grid">
              <figure v-for="side in compareSides" :key="side.key" :class="['beauty-compare-photo', `is-${side.key}`]">
                <button v-if="side.photo" type="button" :title="`نمایش بزرگ عکس ${side.label}`" @click="openLightbox(side.key)">
                  <img :src="side.photo.url" :alt="`${side.label} · ${compareAngle.label}`" loading="lazy">
                  <span class="beauty-compare-zoom-hint" aria-hidden="true">⤢</span>
                </button>
                <div v-else class="beauty-compare-empty">عکس {{ side.label }} برای این زاویه ثبت نشده است</div>
                <figcaption>
                  <b>{{ side.label }}</b>
                  <span>{{ side.photo ? formatJalaliFolderDate(side.photo.date) || formatDate(side.photo.created_at) : '—' }}</span>
                  <button v-if="side.photo?.folder_id" type="button" class="beauty-compare-folder-link" title="باز کردن پوشهٔ این عکس" @click="openPhotoFolder(side.photo.folder_id)">پوشه ↗</button>
                </figcaption>
              </figure>
            </div>
            <div class="beauty-compare-angles" role="tablist" aria-label="زوایای عکس">
              <button
                v-for="(angle, index) in compareAngles"
                :key="angle.key"
                type="button"
                role="tab"
                :aria-selected="index === compareAngleIndex"
                :class="{ on: index === compareAngleIndex }"
                @click="compareAngleIndex = index"
              >{{ angle.label }}</button>
            </div>
          </section>

          <!-- پوشه‌های عکس بیمار بر اساس تاریخ؛ با کلیک همان پوشه در عکس‌های پرونده باز می‌شود. -->
          <section v-if="!recordLoading && activePatient" class="beauty-folders">
            <header class="beauty-compare-head">
              <div>
                <h3>پوشه‌های عکس</h3>
                <p>{{ photoFolders.length ? `${photoFolders.length.toLocaleString('fa-IR')} تاریخ · برای باز کردن روی پوشه بزنید` : 'هنوز پوشهٔ عکسی برای این بیمار ساخته نشده است.' }}</p>
              </div>
            </header>
            <div v-if="photoFolders.length" class="beauty-folders-grid">
              <button v-for="folder in photoFolders" :key="folder.id" type="button" class="beauty-folder" @click="openPhotoFolder(folder.id)">
                <span class="beauty-folder-cover">
                  <img v-if="folder.cover_url" :src="folder.cover_url" alt="" loading="lazy">
                  <i v-else aria-hidden="true">▤</i>
                </span>
                <span class="beauty-folder-info">
                  <strong>{{ formatJalaliFolderDate(folder.date) || folder.name }}</strong>
                  <small>{{ folder.services.length ? folder.services.join('، ') : 'بدون خدمت' }}</small>
                  <small>{{ folder.photo_count.toLocaleString('fa-IR') }} عکس<template v-if="folder.before_count || folder.after_count"> · قبل {{ folder.before_count.toLocaleString('fa-IR') }} · بعد {{ folder.after_count.toLocaleString('fa-IR') }}</template></small>
                </span>
              </button>
            </div>
          </section>
        </section>
      </div>
    </section>

    <!-- نمایش بزرگ عکس قبل/بعد با بزرگ‌نمایی (چرخ ماوس، دوبار کلیک، دو انگشت) و جابه‌جایی -->
    <div v-if="lightbox && lightboxPhoto" class="bf-lightbox" role="dialog" aria-modal="true" :aria-label="`عکس ${lightboxSide.label} · ${compareAngle.label}`" @click.self="closeLightbox">
      <header class="bf-lightbox-bar">
        <div class="bf-lightbox-title">
          <strong>{{ lightboxSide.label }} · {{ compareAngle.label }}</strong>
          <small>{{ formatJalaliFolderDate(lightboxPhoto.date) || formatDate(lightboxPhoto.created_at) }}</small>
        </div>
        <div class="bf-lightbox-tabs" role="tablist" aria-label="قبل یا بعد">
          <button v-for="side in compareSides" :key="side.key" type="button" role="tab" :aria-selected="lightbox.side === side.key" :class="{ on: lightbox.side === side.key }" :disabled="!side.photo" @click="switchLightboxSide(side.key)">{{ side.label }}</button>
        </div>
        <button type="button" class="bf-lightbox-close" aria-label="بستن" title="بستن (Esc)" @click="closeLightbox">×</button>
      </header>

      <div
        ref="lightboxStage"
        class="bf-lightbox-stage"
        :class="{ 'is-zoomed': zoom > 1, 'is-dragging': dragging }"
        @wheel.prevent="onLightboxWheel"
        @dblclick="toggleLightboxZoom($event)"
        @pointerdown="onLightboxPointerDown"
        @pointermove="onLightboxPointerMove"
        @pointerup="onLightboxPointerUp"
        @pointercancel="onLightboxPointerUp"
        @pointerleave="onLightboxPointerUp"
      >
        <img
          :src="lightboxPhoto.url"
          :alt="`${lightboxSide.label} · ${compareAngle.label}`"
          draggable="false"
          :style="{ transform: `translate(${panX}px, ${panY}px) scale(${zoom})` }"
        >
      </div>

      <footer class="bf-lightbox-bar bf-lightbox-tools">
        <div class="bf-lightbox-angle">
          <button type="button" title="زاویهٔ قبلی" aria-label="زاویهٔ قبلی" :disabled="compareAngles.length < 2" @click="moveLightboxAngle(-1)"><svg class="bf-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
          <span>{{ compareAngle.label }}</span>
          <button type="button" title="زاویهٔ بعدی" aria-label="زاویهٔ بعدی" :disabled="compareAngles.length < 2" @click="moveLightboxAngle(1)"><svg class="bf-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
        </div>
        <div class="bf-lightbox-zoom">
          <button type="button" title="کوچک‌نمایی" aria-label="کوچک‌نمایی" :disabled="zoom <= 1" @click="setLightboxZoom(zoom - 0.5)">−</button>
          <button type="button" class="bf-lightbox-zoom-value" title="اندازهٔ اصلی" @click="resetLightboxZoom">{{ Math.round(zoom * 100).toLocaleString('fa-IR') }}٪</button>
          <button type="button" title="بزرگ‌نمایی" aria-label="بزرگ‌نمایی" :disabled="zoom >= 5" @click="setLightboxZoom(zoom + 0.5)">+</button>
        </div>
      </footer>
    </div>

    <div v-if="problemsSettingsOpen" class="beauty-problems-backdrop" @click.self="closeProblemsSettings">
      <section class="beauty-problems-modal" role="dialog" aria-modal="true" aria-labelledby="beauty-problems-title">
        <header>
          <div><small>تنظیمات زیبایار</small><h3 id="beauty-problems-title">مشکلات چهره</h3><p>موارد این فهرست هنگام ثبت نقطه روی چهره نمایش داده می‌شوند.</p></div>
          <button type="button" aria-label="بستن" @click="closeProblemsSettings">×</button>
        </header>
        <form @submit.prevent="addFaceProblem">
          <input v-model.trim="problemDraft" type="text" maxlength="160" placeholder="مثلاً لک یا افتادگی">
          <button type="submit">افزودن</button>
        </form>
        <div class="beauty-problems-list">
          <span v-for="(problem, index) in problemsSettingsDraft" :key="problem">
            {{ problem }}
            <button type="button" :aria-label="`حذف ${problem}`" @click="removeFaceProblem(index)">×</button>
          </span>
          <p v-if="!problemsSettingsDraft.length">هنوز مشکلی تعریف نشده است.</p>
        </div>
        <p v-if="problemsSettingsError" class="beauty-problems-error">{{ problemsSettingsError }}</p>
        <footer>
          <button type="button" @click="closeProblemsSettings">انصراف</button>
          <button type="button" :disabled="problemsSettingsSaving" @click="saveFaceProblems">{{ problemsSettingsSaving ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}</button>
        </footer>
      </section>
    </div>
  </main>
</template>

<script>
import DatePicker from 'vue3-persian-datetime-picker'
import PatientProfilePhotoUploader from './PatientProfilePhotoUploader.vue'

const API = '/api'

export default {
  name: 'Dermatracker',
  components: { DatePicker, PatientProfilePhotoUploader },
  props: {
    permissions: {
      type: Array,
      default: () => []
    },
    openPatientRequest: {
      type: Object,
      default: null
    }
  },
  data: () => ({
    search: { file_number: '', phone: '', q: '' },
    searching: false,
    searched: false,
    searchError: '',
    showCreatePanel: false,
    patients: [],
    activePatient: null,
    recordLoading: false,
    profilePhoto: null,
    legacyPhotos: [],
    photoTimeline: { angles: [], folders: [] },
    compareAngleIndex: 0,
    lightbox: null,
    zoom: 1,
    panX: 0,
    panY: 0,
    dragging: false,
    selectedPhotoId: '',
    selectedPhoto: null,
    annotations: [],
    serviceHistory: [],
    servicesLoading: false,
    servicesExpanded: false,
    worklist: [],
    worklistLoading: false,
    worklistFilters: { date_from: '', date_to: '' },
    areas: [],
    problems: [],
    problemsSettingsOpen: false,
    problemsSettingsDraft: [],
    problemsSettingsSaving: false,
    problemsSettingsError: '',
    problemDraft: '',
    draftPoint: null,
    selectedAnnotation: null,
    savingPoint: false,
    voiceRecording: false,
    voiceRecorder: null,
    voiceChunks: [],
    voiceStartedAt: 0,
    draftVoiceBlob: null,
    draftVoiceUrl: '',
    draftVoiceDuration: 0,
    annotationFilters: { date_from: '', date_to: '' }
  }),
  computed: {
    compareAngles() {
      return this.photoTimeline?.angles || []
    },
    compareAngle() {
      return this.compareAngles[this.compareAngleIndex] || this.compareAngles[0] || { label: '' }
    },
    compareSides() {
      return [
        { key: 'before', label: 'قبل', photo: this.compareAngle.before || null },
        { key: 'after', label: 'بعد', photo: this.compareAngle.after || null }
      ]
    },
    lightboxSide() {
      return this.compareSides.find(side => side.key === this.lightbox?.side) || this.compareSides[0]
    },
    lightboxPhoto() {
      return this.lightboxSide?.photo || null
    },
    photoFolders() {
      return this.photoTimeline?.folders || []
    },
    canViewPatientPhone() {
      return this.permissions.includes('patients.view_phone') && !this.permissions.includes('patients.hide_phone')
    },

    doneCount() {
      return this.annotations.filter(item => item.status === 'done').length
    },
    isProfileSelected() {
      return this.selectedPhoto?.source === 'profile'
    },
    visibleServiceHistory() {
      return this.servicesExpanded ? this.serviceHistory : this.serviceHistory.slice(0, 3)
    }
  },
  watch: {
    openPatientRequest: {
      deep: true,
      handler(patient) {
        if (patient?.id) this.openRecord(patient, patient.mediaId || '')
      }
    }
  },
  mounted() {
    this.loadContext()
    this.loadWorklist()
    if (this.openPatientRequest?.id) this.openRecord(this.openPatientRequest, this.openPatientRequest.mediaId || '')
  },
  beforeUnmount() {
    this.clearDraftVoice()
    window.removeEventListener('keydown', this.onLightboxKey)
  },
  methods: {
    displayPatientPhone(value) {
      const text = String(value || '').trim()
      if (!text) return ''
      if (this.canViewPatientPhone || text.includes('•') || text.includes('*')) return text
      const digits = text.replace(/\D/g, '')
      if (digits.length <= 4) return '••••'
      return `${digits.slice(0, 3)}••••${digits.slice(-2)}`
    },

    patientName(patient) {
      return [patient?.first_name, patient?.last_name].filter(Boolean).join(' ') || 'بیمار بدون نام'
    },
    patientInitial(patient) {
      return this.patientName(patient).trim().slice(0, 1) || 'ب'
    },
    customerLevelLabel(level) {
      return { problematic: 'مشتری دردسرساز', blue: 'آبی', silver: 'نقره‌ای', gold: 'طلایی (CIP)' }[level] || 'عادی'
    },
    statusLabel(status) {
      return status === 'done' ? 'انجام شده' : 'در انتظار'
    },
    statusClass(status) {
      return status === 'done' ? 'beauty-flow-status-done' : 'beauty-flow-status-pending'
    },
    moveCompareAngle(step) {
      const count = this.compareAngles.length
      if (!count) return
      this.compareAngleIndex = (this.compareAngleIndex + step + count) % count
    },
    // «1405-08-08» → «۸ آبان ۱۴۰۵»
    formatJalaliFolderDate(value) {
      const match = String(value || '').match(/^(1[34]\d{2})[-/](\d{1,2})[-/](\d{1,2})$/)
      if (!match) return ''
      const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
      const fa = n => Number(n).toLocaleString('fa-IR', { useGrouping: false })
      return `${fa(match[3])} ${months[Number(match[2]) - 1] || ''} ${fa(match[1])}`
    },
    openLightbox(side) {
      this.lightbox = { side }
      this.resetLightboxZoom()
      this.lightboxPointers = new Map()
      window.addEventListener('keydown', this.onLightboxKey)
    },
    closeLightbox() {
      this.lightbox = null
      this.dragging = false
      window.removeEventListener('keydown', this.onLightboxKey)
    },
    switchLightboxSide(side) {
      if (this.lightbox?.side === side) return
      this.lightbox = { side }
      this.resetLightboxZoom()
    },
    moveLightboxAngle(step) {
      this.moveCompareAngle(step)
      // اگر زاویهٔ تازه برای این طرف عکس ندارد، طرف دیگر نمایش داده می‌شود.
      if (!this.lightboxPhoto) {
        const other = this.compareSides.find(side => side.photo)
        if (other) this.lightbox = { side: other.key }
      }
      this.resetLightboxZoom()
    },
    onLightboxKey(event) {
      if (!this.lightbox) return
      if (event.key === 'Escape') this.closeLightbox()
      else if (event.key === '+' || event.key === '=') this.setLightboxZoom(this.zoom + 0.5)
      else if (event.key === '-') this.setLightboxZoom(this.zoom - 0.5)
      else if (event.key === '0') this.resetLightboxZoom()
      else if (event.key === 'ArrowLeft') this.moveLightboxAngle(1)
      else if (event.key === 'ArrowRight') this.moveLightboxAngle(-1)
    },
    resetLightboxZoom() {
      this.zoom = 1
      this.panX = 0
      this.panY = 0
    },
    // بزرگ‌نمایی حول یک نقطه (محل ماوس یا وسط دو انگشت) تا همان نقطه زیر دست بماند.
    setLightboxZoom(value, originX = null, originY = null) {
      const next = Math.min(5, Math.max(1, Math.round(value * 100) / 100))
      if (next === 1) return this.resetLightboxZoom()
      const stage = this.$refs.lightboxStage?.getBoundingClientRect()
      if (stage && originX !== null && originY !== null) {
        const dx = originX - (stage.left + stage.width / 2)
        const dy = originY - (stage.top + stage.height / 2)
        const ratio = next / this.zoom
        this.panX = dx - (dx - this.panX) * ratio
        this.panY = dy - (dy - this.panY) * ratio
      }
      this.zoom = next
      this.clampLightboxPan()
    },
    clampLightboxPan() {
      const stage = this.$refs.lightboxStage?.getBoundingClientRect()
      if (!stage) return
      const maxX = (stage.width * (this.zoom - 1)) / 2
      const maxY = (stage.height * (this.zoom - 1)) / 2
      this.panX = Math.max(-maxX, Math.min(maxX, this.panX))
      this.panY = Math.max(-maxY, Math.min(maxY, this.panY))
    },
    onLightboxWheel(event) {
      const factor = event.deltaY < 0 ? 1.15 : 1 / 1.15
      this.setLightboxZoom(this.zoom * factor, event.clientX, event.clientY)
    },
    toggleLightboxZoom(event) {
      if (this.zoom > 1) this.resetLightboxZoom()
      else this.setLightboxZoom(2.5, event.clientX, event.clientY)
    },
    onLightboxPointerDown(event) {
      if (!this.lightboxPointers) this.lightboxPointers = new Map()
      event.currentTarget.setPointerCapture?.(event.pointerId)
      this.lightboxPointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
      this.lightboxGesture = null
      this.dragging = this.zoom > 1 && this.lightboxPointers.size === 1
    },
    onLightboxPointerMove(event) {
      const pointers = this.lightboxPointers
      if (!pointers?.has(event.pointerId)) return
      const previous = pointers.get(event.pointerId)
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
      if (pointers.size >= 2) {
        // دو انگشت: فاصلهٔ انگشت‌ها اندازهٔ بزرگ‌نمایی را تعیین می‌کند.
        const [a, b] = [...pointers.values()]
        const distance = Math.hypot(a.x - b.x, a.y - b.y)
        const centerX = (a.x + b.x) / 2
        const centerY = (a.y + b.y) / 2
        if (this.lightboxGesture) this.setLightboxZoom(this.zoom * (distance / this.lightboxGesture.distance), centerX, centerY)
        this.lightboxGesture = { distance }
        this.dragging = false
        return
      }
      if (this.zoom <= 1) return
      this.panX += event.clientX - previous.x
      this.panY += event.clientY - previous.y
      this.clampLightboxPan()
    },
    onLightboxPointerUp(event) {
      this.lightboxPointers?.delete(event.pointerId)
      if ((this.lightboxPointers?.size || 0) < 2) this.lightboxGesture = null
      this.dragging = this.zoom > 1 && (this.lightboxPointers?.size || 0) === 1
    },
    openPhotoFolder(folderId) {
      if (!this.activePatient?.id || !folderId) return
      this.$emit('open-patient-media', { ...this.activePatient, folder_id: folderId })
    },
    formatDate(raw) {
      if (!raw) return '-'
      try {
        return new Date(raw).toLocaleDateString('fa-IR')
      } catch {
        return String(raw)
      }
    },
    photoLabel(photo) {
      const stage = photo.comparison_stage === 'before' ? 'قبل' : photo.comparison_stage === 'after' ? 'بعد' : 'پرونده'
      const date = photo.created_at ? new Date(photo.created_at).toLocaleDateString('fa-IR') : 'بدون تاریخ'
      return `${photo.photo_angle_label || 'سایر'} · ${stage} · ${date}`
    },
    openProfileUpload() {
      this.searchError = ''
      this.$refs.profileUploader?.pick()
    },
    async onProfilePhotoUploaded(data) {
      if (data?.patient) this.activePatient = { ...this.activePatient, ...data.patient }
      // بعد از آپلود، عکس پروفایل مستقیم برای نقطه‌گذاری باز می‌شود.
      this.selectedPhotoId = ''
      await this.loadRecord()
    },
    async selectProfilePhoto() {
      if (!this.profilePhoto || this.isProfileSelected) return
      this.selectedPhotoId = ''
      await this.loadRecord()
    },
    async selectLegacyPhoto(photo) {
      if (!photo?.id || String(this.selectedPhotoId) === String(photo.id)) return
      this.selectedPhotoId = photo.id
      await this.loadRecord()
    },
    annotationFullText(point) {
      return [point?.area, point?.problem, point?.note].filter(Boolean).join(' - ') || 'بدون توضیح'
    },
    annotationCategory(point) {
      return [point?.area, point?.problem].filter(Boolean).join(' / ') || 'بدون دسته‌بندی'
    },
    annotationNoteSummary(point) {
      const text = point?.note || 'بدون توضیح'
      return text.length > 46 ? `${text.slice(0, 46)}...` : text
    },
    hasMoreAnnotationText(point) {
      return String(point?.note || '').length > 46
    },
    normalizeFaceProblem(value) {
      return String(value || '').trim().replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ')
    },
    openProblemsSettings() {
      this.problemsSettingsDraft = [...this.problems]
      this.problemDraft = ''
      this.problemsSettingsError = ''
      this.problemsSettingsOpen = true
    },
    closeProblemsSettings() {
      if (this.problemsSettingsSaving) return
      this.problemsSettingsOpen = false
      this.problemDraft = ''
      this.problemsSettingsError = ''
    },
    addFaceProblem() {
      const problem = this.normalizeFaceProblem(this.problemDraft)
      if (!problem) return
      if (this.problemsSettingsDraft.some(item => this.normalizeFaceProblem(item) === problem)) {
        this.problemsSettingsError = 'این مشکل قبلاً اضافه شده است.'
        return
      }
      this.problemsSettingsDraft.push(problem)
      this.problemDraft = ''
      this.problemsSettingsError = ''
    },
    removeFaceProblem(index) {
      this.problemsSettingsDraft.splice(index, 1)
      this.problemsSettingsError = ''
    },
    async saveFaceProblems() {
      this.problemsSettingsSaving = true
      this.problemsSettingsError = ''
      try {
        const res = await fetch(`${API}/beauty/problems`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ problems: this.problemsSettingsDraft })
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'ذخیره مشکلات چهره انجام نشد.')
        this.problems = Array.isArray(data.problems) ? data.problems : []
        this.problemsSettingsDraft = [...this.problems]
        this.problemsSettingsOpen = false
      } catch (error) {
        this.problemsSettingsError = error.message || 'ذخیره مشکلات چهره انجام نشد.'
      } finally {
        this.problemsSettingsSaving = false
      }
    },
    async loadContext() {
      try {
        const res = await fetch(`${API}/beauty/context`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت اطلاعات خدمتیار انجام نشد.')
        this.areas = data.areas || []
        this.problems = data.problems || []
      } catch (error) {
        this.searchError = error.message || 'دریافت اطلاعات خدمتیار انجام نشد.'
      }
    },
    async loadWorklist() {
      this.worklistLoading = true
      try {
        const params = new URLSearchParams()
        Object.entries(this.worklistFilters).forEach(([key, value]) => {
          if (value) params.set(key, value)
        })
        const res = await fetch(`${API}/beauty/annotations?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت لیست نقاط انجام نشد.')
        this.worklist = data.annotations || []
      } catch (error) {
        this.searchError = error.message || 'دریافت لیست نقاط انجام نشد.'
      } finally {
        this.worklistLoading = false
      }
    },
    async searchPatients() {
      if (this.searching) return
      if (!this.search.file_number && !this.search.phone && !this.search.q) {
        this.searchError = 'شماره پرونده، شماره تماس یا نام بیمار را وارد کنید.'
        return
      }

      this.searching = true
      this.searched = false
      this.searchError = ''
      this.patients = []

      try {
        const params = new URLSearchParams()
        if (this.search.file_number) params.append('file_number', this.search.file_number)
        if (this.search.phone) params.append('phone', this.search.phone)
        if (this.search.q) params.append('q', this.search.q)
        const res = await fetch(`${API}/patients/search?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'جستجو انجام نشد.')
        this.patients = Array.isArray(data) ? data.map(patient => ({ ...patient, search_media: [], search_media_loading: true, search_media_loaded: false })) : []
        this.searched = true
        await Promise.all(this.patients.map(async patient => {
          try {
            const mediaRes = await fetch(`${API}/patients/${patient.id}/media?all=1`)
            const mediaData = await mediaRes.json()
            patient.search_media = (Array.isArray(mediaData.media) ? mediaData.media : []).filter(item => item.media_type === 'image' || String(item.mime_type || '').startsWith('image/')).slice(0, 6)
          } catch (_) {
            patient.search_media = []
          } finally {
            patient.search_media_loading = false
            patient.search_media_loaded = true
          }
        }))
      } catch (error) {
        this.searchError = error.message || 'جستجو انجام نشد.'
      } finally {
        this.searching = false
      }
    },
    async openRecord(patient, mediaId = '') {
      this.activePatient = patient
      this.showCreatePanel = false
      this.selectedPhotoId = mediaId || ''
      this.draftPoint = null
      this.selectedAnnotation = null
      this.clearDraftVoice()
      this.serviceHistory = []
      this.servicesExpanded = false
      await this.loadRecord()
    },
    async openWorklistRecord(item) {
      if (!item?.patient) return
      await this.openRecord(item.patient, item.patient_media_id || '')
    },
    async loadServiceHistory() {
      if (!this.activePatient?.id) return
      this.servicesLoading = true
      try {
        const params = new URLSearchParams()
        if (this.activePatient.file_number) params.append('file_number', this.activePatient.file_number)
        if (this.activePatient.phone) params.append('phone', this.activePatient.phone)
        const res = await fetch(`${API}/appointments/patient-history?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت خدمات انجام نشد.')
        this.serviceHistory = (Array.isArray(data) ? data : [])
          .filter(item => this.appointmentServicesText(item) !== '-')
          .slice(0, 12)
      } catch (error) {
        this.searchError = error.message || 'دریافت خدمات انجام نشد.'
      } finally {
        this.servicesLoading = false
      }
    },
    appointmentServicesText(item) {
      const services = Array.isArray(item?.services) ? item.services : []
      const names = services
        .flatMap(service => [
          service?.name,
          ...(Array.isArray(service?.addons) ? service.addons.map(addon => addon?.name) : [])
        ])
        .filter(Boolean)
      return names.length ? names.join('، ') : (item?.done || '-')
    },
    appointmentDateText(item) {
      const raw = item?.created_at || item?.month || ''
      if (!raw) return 'بدون تاریخ'
      try {
        return new Date(raw).toLocaleDateString('fa-IR')
      } catch {
        return String(raw)
      }
    },
    async loadRecord() {
      if (!this.activePatient?.id) return
      this.recordLoading = true
      this.searchError = ''
      try {
        const params = new URLSearchParams()
        // فقط عکس‌های قبلیِ دارای نقطه شناسه عددی دارند؛ پیش‌فرض عکس پروفایل است.
        if (/^\d+$/.test(String(this.selectedPhotoId || ''))) params.set('media_id', this.selectedPhotoId)
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty?${params}`)
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'دریافت پرونده زیبایار انجام نشد.')
        this.activePatient = data.patient || this.activePatient
        this.profilePhoto = data.profile_photo || null
        this.legacyPhotos = Array.isArray(data.legacy_photos) ? data.legacy_photos : []
        this.selectedPhoto = data.selected_photo || null
        this.selectedPhotoId = this.selectedPhoto?.source === 'media' ? this.selectedPhoto.id : ''
        this.annotations = data.annotations || []
        this.photoTimeline = {
          angles: Array.isArray(data.photo_timeline?.angles) ? data.photo_timeline.angles : [],
          folders: Array.isArray(data.photo_timeline?.folders) ? data.photo_timeline.folders : []
        }
        // پیش‌فرض تمام‌رخ اگر عکسش هست؛ وگرنه اولین زاویه‌ای که عکس دارد.
        const frontIndex = this.photoTimeline.angles.findIndex(angle => angle.key === 'front')
        this.compareAngleIndex = frontIndex >= 0 ? frontIndex : 0
        this.draftPoint = null
        this.selectedAnnotation = null
      } catch (error) {
        this.searchError = error.message || 'دریافت پرونده زیبایار انجام نشد.'
      } finally {
        this.recordLoading = false
      }
    },
    startPoint(event) {
      if (!this.selectedPhoto) return
      if (!this.isProfileSelected) {
        this.searchError = this.profilePhoto
          ? 'نقطه جدید فقط روی عکس پروفایل ثبت می‌شود. عکس پروفایل را انتخاب کنید.'
          : 'برای ثبت نقطه جدید، ابتدا عکس پروفایل بیمار را آپلود کنید.'
        return
      }
      const rect = event.currentTarget.getBoundingClientRect()
      this.selectedAnnotation = null
      this.searchError = ''
      this.draftPoint = {
        x_percent: Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100)),
        y_percent: Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100)),
        area: '',
        problem: '',
        note: '',
        annotation_date: new Date().toISOString().slice(0, 10)
      }
      this.clearDraftVoice()
    },
    async savePoint() {
      if (!this.activePatient?.id || !this.isProfileSelected || !this.draftPoint) return
      this.savingPoint = true
      try {
        const form = new FormData()
        form.append('photo_source', 'profile')
        Object.entries(this.draftPoint).forEach(([key, value]) => {
          form.append(key, value ?? '')
        })
        if (this.draftVoiceBlob) {
          form.append('voice', this.draftVoiceBlob, `beauty-voice-${Date.now()}.webm`)
          form.append('voice_duration', String(this.draftVoiceDuration || 0))
        }
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations`, {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: form
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'ثبت نقطه انجام نشد.')
        this.annotations.unshift(data)
        this.selectedAnnotation = data
        this.draftPoint = null
        this.clearDraftVoice()
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'ثبت نقطه انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async markDone(point) {
      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ status: 'done' })
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'ثبت وضعیت انجام نشد.')
        this.annotations = this.annotations.map(item => item.id === point.id ? data : item)
        this.selectedAnnotation = data
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'ثبت وضعیت انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async markPending(point) {
      if (!point?.id || !this.activePatient?.id) return
      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ status: 'pending' })
        })
        const data = await res.json()
        if (!res.ok) throw new Error(data.message || 'برگرداندن وضعیت نقطه انجام نشد.')
        this.annotations = this.annotations.map(item => item.id === point.id ? data : item)
        this.selectedAnnotation = data
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'برگرداندن وضعیت نقطه انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async markAllDone() {
      const pending = this.annotations.filter(item => item.status !== 'done')
      if (!pending.length || !this.activePatient?.id) return

      const confirmed = window.confirm('با زدن این کلید همه نقاط به حالت انجام شده تغییر پیدا می‌کنند. اطمینان دارید؟')
      if (!confirmed) return

      this.savingPoint = true
      try {
        const updated = await Promise.all(pending.map(async (point) => {
          const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ status: 'done' })
          })
          const data = await res.json()
          if (!res.ok) throw new Error(data.message || 'ثبت وضعیت نقاط انجام نشد.')
          return data
        }))

        const updatedById = new Map(updated.map(item => [item.id, item]))
        this.annotations = this.annotations.map(item => updatedById.get(item.id) || item)
        if (this.selectedAnnotation && updatedById.has(this.selectedAnnotation.id)) {
          this.selectedAnnotation = updatedById.get(this.selectedAnnotation.id)
        }
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'ثبت وضعیت نقاط انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    async deleteAnnotation(point) {
      if (!point?.id || !this.activePatient?.id) return
      const confirmed = window.confirm('این نقطه از روی عکس حذف شود؟')
      if (!confirmed) return

      this.savingPoint = true
      try {
        const res = await fetch(`${API}/patients/${this.activePatient.id}/beauty/annotations/${point.id}`, {
          method: 'DELETE',
          headers: { Accept: 'application/json' }
        })
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message || 'حذف نقطه انجام نشد.')
        this.annotations = this.annotations.filter(item => item.id !== point.id)
        this.selectedAnnotation = null
        this.loadWorklist()
      } catch (error) {
        this.searchError = error.message || 'حذف نقطه انجام نشد.'
      } finally {
        this.savingPoint = false
      }
    },
    clearAnnotationFilters() {
      this.annotationFilters = { date_from: '', date_to: '' }
      this.loadRecord()
    },
    async startVoiceRecording() {
      if (this.voiceRecording) return
      if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
        this.searchError = 'مرورگر شما ضبط ویس را پشتیبانی نمی‌کند.'
        return
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
        this.voiceChunks = []
        const recorder = new MediaRecorder(stream)
        recorder.ondataavailable = event => {
          if (event.data?.size) this.voiceChunks.push(event.data)
        }
        recorder.onstop = () => {
          const blob = new Blob(this.voiceChunks, { type: recorder.mimeType || 'audio/webm' })
          stream.getTracks().forEach(track => track.stop())
          this.setDraftVoice(blob)
        }
        this.voiceRecorder = recorder
        this.voiceStartedAt = Date.now()
        this.voiceRecording = true
        recorder.start()
      } catch {
        this.searchError = 'دسترسی به میکروفون داده نشد.'
      }
    },
    stopVoiceRecording() {
      if (!this.voiceRecorder || this.voiceRecorder.state === 'inactive') return
      this.draftVoiceDuration = Math.max(1, Math.round((Date.now() - this.voiceStartedAt) / 1000))
      this.voiceRecording = false
      this.voiceRecorder.stop()
      this.voiceRecorder = null
    },
    setDraftVoice(blob) {
      if (this.draftVoiceUrl) URL.revokeObjectURL(this.draftVoiceUrl)
      this.draftVoiceBlob = blob
      this.draftVoiceUrl = URL.createObjectURL(blob)
    },
    clearDraftVoice() {
      if (this.voiceRecorder && this.voiceRecorder.state !== 'inactive') {
        this.voiceRecorder.stop()
      }
      this.voiceRecording = false
      this.voiceRecorder = null
      this.voiceChunks = []
      this.voiceStartedAt = 0
      this.draftVoiceBlob = null
      this.draftVoiceDuration = 0
      if (this.draftVoiceUrl) URL.revokeObjectURL(this.draftVoiceUrl)
      this.draftVoiceUrl = ''
    },
    closeRecord() {
      this.clearDraftVoice()
      this.activePatient = null
      this.profilePhoto = null
      this.legacyPhotos = []
      this.selectedPhotoId = ''
      this.selectedPhoto = null
      this.annotations = []
      this.serviceHistory = []
      this.servicesExpanded = false
      this.draftPoint = null
      this.selectedAnnotation = null
    }
  }
}
</script>

<style scoped>
.beauty-flow {
  min-height: calc(100vh - 120px);
  padding: 18px;
  background: #f6f8fa;
  color: #0f172a;
  font-family: "Vazir", Tahoma, sans-serif;
}
.beauty-flow-card {
  margin-bottom: 18px;
  padding: 18px;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
}

.beauty-flow-create-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 40px;
  width: 40px;
  min-width: 40px;
  padding: 0;
  font-size: 22px !important;
  line-height: 1;
}
.beauty-flow-main-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.beauty-flow-create-panel {
  margin-top: 18px;
  padding: 20px;
  border: 1px solid #dbeafe;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(30, 64, 175, .08);
}

.beauty-flow-search-grid-compact {
  flex: 1;
  grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) 90px;
}
.beauty-flow-create-results { display: grid; gap: 8px; margin-top: 14px; }
.beauty-flow-create-results > button {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 12px;
  color: #172554;
  text-align: right;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}
.beauty-flow-create-results > button:hover { background: #eff6ff; border-color: #93c5fd; }
.beauty-flow-search-result { align-items: flex-start; }
.beauty-flow-search-result-info { min-width: 0; align-items: flex-start !important; }
.beauty-flow-search-media { display: flex !important; align-items: center; gap: 5px; flex-wrap: wrap; margin-top: 3px; }
.beauty-flow-search-media img { width: 32px; height: 32px; object-fit: cover; border-radius: 7px; border: 1px solid #dbeafe; }
.beauty-flow-search-media small, .beauty-flow-search-media-loading { color: #64748b; font-size: 10px; }
.beauty-flow-create-results > button > span:nth-child(2) { display: flex; flex-direction: column; gap: 3px; }
.beauty-flow-create-results small { color: #64748b; font-weight: 500; }
.beauty-flow-card-header,
.beauty-flow-record-header,
.beauty-flow-photo-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.beauty-flow-card-header {
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #edf2f7;
}
.beauty-flow-card-header h2,
.beauty-flow-record-header h2,
.beauty-flow-photo-header h3 {
  margin: 0;
  color: #0f172a;
  font-size: 16px;
  font-weight: 900;
}
.beauty-flow-card-header span,
.beauty-flow-record-header p,
.beauty-flow-photo-header p,
.beauty-flow-muted {
  margin: 0;
  color: #64748b;
  font-size: 12px;
}
.beauty-flow-search-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr)) 120px;
  gap: 12px;
  align-items: center;
}
.beauty-flow input,
.beauty-flow select,
.beauty-flow textarea {
  width: 100%;
  border: 1px solid #dbe3ea;
  border-radius: 12px;
  background: #fff;
  color: #111827;
  font-family: inherit;
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
}
.beauty-flow input,
.beauty-flow select {
  height: 42px;
  padding: 0 12px;
}
.beauty-flow textarea {
  min-height: 92px;
  padding: 10px 12px;
  resize: vertical;
}
.beauty-flow button {
  height: 40px;
  border: 0;
  border-radius: 12px;
  background: #0f766e;
  color: #fff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}
.beauty-flow button:disabled {
  opacity: .65;
  cursor: wait;
}

/* نتیجهٔ جست‌وجو یک کارت چندخطی است، نه دکمهٔ کوتاهِ عمومی صفحه. */
.beauty-flow .beauty-flow-create-results > button {
  height: auto;
  min-height: 68px;
  padding: 10px 12px;
  background: #f8fafc;
  color: #172554;
  border: 1px solid #e2e8f0;
}

.beauty-flow .beauty-flow-create-results > button:hover {
  background: #eff6ff;
  border-color: #93c5fd;
}

.beauty-flow-create-results > button > span:nth-child(2) {
  min-width: 0;
  overflow: hidden;
}

.beauty-flow-create-results > button > span:nth-child(2) strong,
.beauty-flow-create-results > button > span:nth-child(2) small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.beauty-flow-error {
  margin: 12px 0 0;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fff1f2;
  color: #be123c;
  font-size: 12px;
  font-weight: 800;
}
.beauty-flow-table-wrap {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
}
.beauty-flow-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  table-layout: fixed;
}
.beauty-flow-table th {
  padding: 13px 14px;
  background: #f8fafc;
  color: #475569;
  text-align: right;
  font-size: 12px;
  font-weight: 900;
}
.beauty-flow-table td {
  padding: 12px 14px;
  border-top: 1px solid #eef2f7;
  color: #334155;
  font-size: 13px;
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.beauty-flow-worklist-filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(150px, 190px));
  gap: 10px;
  margin-bottom: 12px;
}
.beauty-flow-worklist-table {
  min-width: 1040px;
}
.beauty-flow-table-empty {
  height: 72px;
  color: #64748b !important;
  text-align: center;
  font-weight: 900;
}
.beauty-flow-status {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 78px;
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
}
.beauty-flow-status-pending {
  border: 1px solid #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}
.beauty-flow-status-done {
  border: 1px solid #bbf7d0;
  background: #ecfdf5;
  color: #047857;
}
.beauty-flow-refresh-btn {
  width: auto;
  min-width: 98px;
  padding: 0 12px;
}
.beauty-flow-patient-cell {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}
.beauty-flow-patient-cell > span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
}
.beauty-flow-avatar {
  position: relative;
  width: 38px;
  height: 38px;
  flex: none;
  display: grid;
  place-items: center;
  overflow: visible;
  border: 2px solid #dbe3ea;
  border-radius: 50%;
  background: #f8fafc;
  color: #64748b;
  font-weight: 900;
  box-shadow: 0 0 0 3px rgba(148, 163, 184, .12);
}
.beauty-flow-avatar-large {
  width: 86px;
  height: 86px;
  justify-self: center;
  margin-bottom: 4px;
  font-size: 28px;
}
.beauty-flow-avatar img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: inherit;
}
.beauty-flow-avatar-blue {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, .16);
}
.beauty-flow-avatar-problematic {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, .16);
}
.beauty-flow-avatar-silver {
  border-color: #94a3b8;
  box-shadow: 0 0 0 3px rgba(148, 163, 184, .2);
}
.beauty-flow-avatar-gold {
  border-color: #d4a72c;
  box-shadow: 0 0 0 3px rgba(212, 167, 44, .2);
}
.beauty-flow-avatar-gold::before {
  content: "♛";
  position: absolute;
  top: -18px;
  left: 50%;
  transform: translateX(-50%);
  color: #d4a72c;
  font-size: 24px;
  line-height: 1;
  text-shadow: 0 1px 0 #fff, 0 2px 6px rgba(146, 64, 14, .3);
}
.beauty-flow-open-btn {
  width: 118px;
}
.beauty-flow-level {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 58px;
  padding: 5px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
}
.beauty-flow-level-blue { background: #dbeafe; color: #1d4ed8; }
.beauty-flow-level-problematic { background: #fee2e2; color: #b91c1c; }
.beauty-flow-level-silver { background: #e2e8f0; color: #475569; }
.beauty-flow-level-gold { background: #fef3c7; color: #92400e; }
.beauty-flow-patient-cell:has(.beauty-flow-avatar-problematic) span,
.beauty-flow-patient-box:has(.beauty-flow-avatar-problematic) strong {
  color: #dc2626;
  font-weight: 1000;
}
.beauty-flow-empty {
  display: grid;
  min-height: 120px;
  place-items: center;
  color: #64748b;
  font-weight: 900;
}
.beauty-flow-record {
  display: grid;
  gap: 14px;
}
.beauty-flow-record-header {
  justify-content: space-between;
  padding: 14px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
}
.beauty-flow-back,
.beauty-flow-light-btn {
  border: 1px solid #dbe3ea !important;
  background: #fff !important;
  color: #475569 !important;
}
.beauty-flow-record-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.beauty-flow-back {
  width: auto;
  min-width: 86px;
  padding: 0 14px;
}
.beauty-flow-workspace {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  gap: 14px;
}
.beauty-flow-sidebar,
.beauty-flow-photo-panel {
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
}
.beauty-flow-sidebar {
  align-self: start;
  padding: 16px;
}
.beauty-flow-patient-box {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding-bottom: 14px;
  border-bottom: 1px solid #edf2f7;
  text-align: center;
}
.beauty-flow-patient-box strong {
  color: #0f172a;
  font-size: 16px;
}
.beauty-flow-patient-box span:not(.beauty-flow-level),
.beauty-flow-side-block small {
  color: #64748b;
  font-size: 12px;
}
.beauty-flow-side-block,
.beauty-flow-editor {
  display: grid;
  gap: 9px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #edf2f7;
}
.beauty-flow-side-block label,
.beauty-flow-editor label {
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}
.beauty-flow-side-block .beauty-flow-default-photo-note {
  padding: 9px 10px;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 10px;
  font-weight: 800;
  line-height: 1.8;
}
/* انتخاب عکس پروفایل به‌عنوان تنها هدف نقطه‌گذاری */
.beauty-flow .beauty-flow-profile-pick {
  height: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border: 2px solid #e2e8f0;
  background: #fff;
  color: #0f172a;
  text-align: right;
}
.beauty-flow .beauty-flow-profile-pick-active {
  border-color: #0f766e;
  background: #f0fdfa;
  cursor: default;
}
.beauty-flow-profile-pick img {
  width: 54px;
  height: 54px;
  flex: 0 0 54px;
  border-radius: 10px;
  object-fit: cover;
}
.beauty-flow-profile-pick span {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.beauty-flow-profile-pick strong {
  font-size: 12px;
}
.beauty-flow-profile-pick-active small {
  color: #0f766e !important;
}
.beauty-flow-profile-missing {
  display: grid;
  gap: 4px;
  padding: 10px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 11px;
  line-height: 1.8;
}
.beauty-flow .beauty-flow-upload-profile-btn {
  height: 36px;
  margin-top: 4px;
  background: #2563eb;
}
.beauty-flow .beauty-flow-legacy-pick {
  height: auto;
  min-height: 34px;
  padding: 6px 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  font-size: 11px;
  text-align: right;
}
.beauty-flow .beauty-flow-legacy-pick-active {
  border-color: #64748b;
  background: #e2e8f0;
}
.beauty-flow-photo-stage-readonly {
  cursor: default !important;
}
.beauty-flow-services-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.beauty-flow-mini-btn {
  width: auto;
  height: 28px !important;
  padding: 0 9px;
  border: 1px solid #dbe3ea !important;
  background: #fff !important;
  color: #475569 !important;
  border-radius: 9px !important;
  font-size: 10px !important;
}
.beauty-flow-services-list {
  display: grid;
  gap: 7px;
}
.beauty-flow-services-list article {
  display: grid;
  gap: 3px;
  padding: 8px 9px;
  border: 1px solid #edf2f7;
  border-radius: 10px;
  background: #f8fafc;
}
.beauty-flow-services-list article strong {
  overflow: hidden;
  color: #0f172a;
  font-size: 11px;
  font-weight: 900;
  line-height: 1.7;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.beauty-flow-services-list article span {
  color: #64748b;
  font-size: 10px;
  line-height: 1.6;
}
.beauty-flow-editor {
  padding: 12px;
  border: 1px solid #ccfbf1;
  border-radius: 14px;
  background: #f0fdfa;
}
.beauty-flow-editor-selected {
  border-color: #bbf7d0;
  background: #f0fdf4;
}
.beauty-flow-editor h3,
.beauty-flow-editor p {
  margin: 0;
}
.beauty-flow-editor-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.beauty-flow-editor h3 {
  color: #0f766e;
  font-size: 14px;
}
.beauty-flow-delete-icon {
  width: 26px !important;
  height: 26px !important;
  min-width: 26px;
  padding: 0 !important;
  border: 1px solid #fecaca !important;
  border-radius: 8px !important;
  background: #fff1f2 !important;
  color: #be123c !important;
  font-size: 18px !important;
  line-height: 1 !important;
}
.beauty-flow-editor p {
  color: #334155;
  font-size: 12px;
  line-height: 1.9;
}
.beauty-flow-editor-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.beauty-flow-photo-panel {
  min-height: 640px;
  padding: 16px;
}
.beauty-flow-photo-empty {
  min-height: 560px;
  display: grid;
  place-items: center;
  gap: 8px;
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  background: #f8fafc;
  color: #64748b;
  text-align: center;
  font-weight: 900;
}
.beauty-flow-photo-empty span {
  color: #94a3b8;
  font-size: 12px;
}
.beauty-flow-photo-empty .beauty-flow-add-photo-btn {
  margin-top: 8px;
  padding: 0 18px;
  background: #2563eb;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .2);
}
.beauty-flow-photo-header {
  margin-bottom: 12px;
}
.beauty-flow-photo-navigation {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 4px;
  border: 1px solid #dbeafe;
  border-radius: 11px;
  background: #f8fbff;
  direction: rtl;
}
.beauty-flow-photo-navigation button {
  width: 30px !important;
  height: 30px !important;
  min-width: 30px;
  padding: 0 !important;
  border: 1px solid #bfdbfe !important;
  border-radius: 8px !important;
  background: #fff !important;
  color: #2563eb !important;
  font-family: inherit;
  font-size: 18px !important;
  font-weight: 900;
  line-height: 1;
  cursor: pointer;
}
.beauty-flow-photo-navigation button:hover {
  border-color: #2563eb !important;
  background: #2563eb !important;
  color: #fff !important;
}
.beauty-flow-photo-navigation span {
  min-width: 44px;
  color: #475569;
  font-size: 10px;
  font-weight: 900;
  text-align: center;
  white-space: nowrap;
}
.beauty-flow-point-count {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.beauty-flow-point-count span {
  padding: 6px 10px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
  font-size: 11px;
  font-weight: 900;
}
.beauty-flow-mark-all {
  width: auto;
  height: 32px !important;
  padding: 0 10px;
  border-radius: 999px !important;
  background: #0f766e !important;
  color: #fff !important;
  font-size: 11px !important;
}
.beauty-flow-photo-stage {
  position: relative;
  min-height: 570px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border-radius: 14px;
  background: #111827;
  cursor: crosshair;
}
.beauty-flow-photo-stage img {
  display: block;
  width: 100%;
  max-width: 100%;
  max-height: 72vh;
  object-fit: contain;
  user-select: none;
}
.beauty-flow-point {
  position: absolute;
  width: 18px !important;
  height: 18px !important;
  min-width: 18px;
  padding: 0 !important;
  border: 3px solid #111827 !important;
  border-radius: 50% !important;
  background: #fff !important;
  box-shadow: 0 0 0 2px rgba(255,255,255,.9);
  transform: translate(-50%, -50%);
}
.beauty-flow-point-done {
  background: #22c55e !important;
  box-shadow: 0 0 0 2px rgba(34,197,94,.4);
}
.beauty-flow-point-draft {
  border-color: #0f766e !important;
  background: #99f6e4 !important;
}
.beauty-flow-point-note {
  position: absolute;
  z-index: 3;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  column-gap: 6px;
  row-gap: 3px;
  width: max-content;
  min-width: 90px;
  max-width: 220px;
  min-height: 36px;
  padding: 5px 8px;
  border: 1px solid rgba(15, 23, 42, .08);
  border-radius: 10px;
  background: rgba(255, 255, 255, .96);
  color: #1f2937;
  box-shadow: 0 10px 24px rgba(15, 23, 42, .16);
  transform: translate(calc(-100% - 18px), -50%);
  cursor: pointer;
}
.beauty-flow-point-note strong,
.beauty-flow-point-note span {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.beauty-flow-point-note strong {
  grid-column: 1 / -1;
  color: #0f172a;
  font-size: 11px;
  font-weight: 900;
  line-height: 1.4;
}
.beauty-flow-point-note span {
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  line-height: 1.4;
}
.beauty-flow-point-note button {
  width: auto;
  height: 22px;
  flex: none;
  padding: 0 7px;
  border-radius: 8px;
  background: #e0f2fe;
  color: #0369a1;
  font-size: 10px;
}
.beauty-flow-point-note-done {
  border-color: rgba(34, 197, 94, .25);
  background: rgba(240, 253, 244, .98);
}
.beauty-flow-point-note-done button {
  background: #dcfce7;
  color: #047857;
}
.beauty-flow-voice-box,
.beauty-flow-voice-player {
  display: grid;
  gap: 8px;
  padding: 10px;
  border: 1px solid #dbeafe;
  border-radius: 12px;
  background: #f8fbff;
}
.beauty-flow-voice-box > div:first-child {
  display: grid;
  gap: 3px;
}
.beauty-flow-voice-box strong {
  color: #0f172a;
  font-size: 12px;
}
.beauty-flow-voice-box small {
  color: #64748b;
  font-size: 10px;
}
.beauty-flow-voice-actions {
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
}
.beauty-flow-recording-btn {
  background: #dc2626 !important;
  color: #fff !important;
  animation: beautyVoicePulse 1.1s ease-in-out infinite;
}
.beauty-flow-voice-box audio,
.beauty-flow-voice-player audio {
  width: 100%;
  height: 34px;
}
@keyframes beautyVoicePulse {
  50% { box-shadow: 0 0 0 5px rgba(220, 38, 38, .14); }
}
.beauty-flow-settings-btn{width:42px;height:42px;flex:0 0 42px;padding:0!important;border:1px solid #bfdbfe!important;border-radius:12px!important;background:#eff6ff!important;color:#2563eb!important;font-size:18px!important;cursor:pointer}.beauty-flow-problems-settings-btn{border-color:#bfdbfe!important;background:#eff6ff!important;color:#2563eb!important}.beauty-problems-backdrop{position:fixed;z-index:4000;inset:0;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.52);backdrop-filter:blur(4px)}.beauty-problems-modal{width:min(620px,95vw);max-height:88vh;overflow:auto;box-sizing:border-box;padding:20px;border:1px solid #dbeafe;border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.3)}.beauty-problems-modal>header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px}.beauty-problems-modal>header small{color:#2563eb;font-size:10px;font-weight:900}.beauty-problems-modal h3{margin:4px 0;color:#172554;font-size:22px}.beauty-problems-modal>header p{margin:0;color:#64748b;font-size:11px}.beauty-problems-modal>header>button{width:32px;height:32px;padding:0;border:0;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:20px}.beauty-problems-modal>form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;margin-top:18px}.beauty-problems-modal>form input{height:43px;padding:0 12px;border:1px solid #cbd5e1;border-radius:11px;background:#f8fafc;font-family:inherit}.beauty-problems-modal>form button{padding:0 18px;border:0;border-radius:11px;background:#2563eb;color:#fff;font-family:inherit;font-weight:900}.beauty-problems-list{min-height:100px;display:flex;align-content:flex-start;gap:8px;flex-wrap:wrap;margin-top:14px;padding:13px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.beauty-problems-list>span{height:34px;display:inline-flex;align-items:center;gap:7px;padding:0 10px;border:1px solid #bfdbfe;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:900}.beauty-problems-list>span button{width:19px;height:19px;display:grid;place-items:center;padding:0;border:0;border-radius:50%;background:#dbeafe;color:#2563eb;font-size:14px;cursor:pointer}.beauty-problems-list>p{width:100%;margin:auto;color:#94a3b8;text-align:center;font-size:11px}.beauty-problems-error{margin:10px 0 0;padding:9px;border-radius:9px;background:#fef2f2;color:#b91c1c;font-size:11px}.beauty-problems-modal>footer{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}.beauty-problems-modal>footer button{height:40px;padding:0 15px;border:0;border-radius:10px;background:#e2e8f0;color:#475569;font-family:inherit;font-weight:900}.beauty-problems-modal>footer button:last-child{background:#2563eb;color:#fff}.beauty-problems-modal button:disabled{opacity:.55;cursor:wait}
@media (max-width: 1100px) {
  .beauty-flow-search-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .beauty-flow-worklist-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .beauty-flow-workspace {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 700px) {
  .beauty-flow {
    padding: 12px;
  }
  .beauty-flow-search-grid {
    grid-template-columns: 1fr;
  }
  .beauty-flow-worklist-filters {
    grid-template-columns: 1fr;
  }
  .beauty-flow-card-header,
  .beauty-flow-record-header,
  .beauty-flow-photo-header {
    align-items: stretch;
    flex-direction: column;
  }
}

/* مقایسهٔ قبل/بعد و پوشه‌های عکس زیر عکس پروفایل */
.beauty-compare, .beauty-folders { margin-top: 16px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; }
.beauty-compare-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.beauty-compare-head h3 { margin: 0; color: #0f172a; font-size: 15px; font-weight: 900; }
.beauty-compare-head p { margin: 3px 0 0; color: #64748b; font-size: 12px; }
.beauty-compare-nav { display: flex; align-items: center; gap: 8px; color: #475569; font-size: 12px; font-weight: 800; white-space: nowrap; }
.beauty-compare .beauty-compare-arrow { width: 34px !important; height: 34px !important; min-width: 34px; padding: 0 !important; border: 1px solid #cbd5e1 !important; border-radius: 10px !important; background: #fff !important; color: #0f172a !important; font-size: 20px !important; line-height: 1; cursor: pointer; }
.beauty-compare .beauty-compare-arrow:hover:not(:disabled) { border-color: #0f766e !important; color: #0f766e !important; }
.beauty-compare .beauty-compare-arrow:disabled { opacity: .4; cursor: default; }
.beauty-compare-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.beauty-compare-photo { margin: 0; display: grid; gap: 8px; }
.beauty-compare-photo > button { display: block; width: 100%; height: auto !important; padding: 0 !important; border: 0 !important; border-radius: 12px !important; overflow: hidden; background: #111827 !important; cursor: zoom-in; }
.beauty-compare-photo img { display: block; width: 100%; aspect-ratio: 3 / 4; object-fit: contain; }
.beauty-compare-empty { display: grid; place-items: center; aspect-ratio: 3 / 4; padding: 16px; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; color: #94a3b8; font-size: 12px; font-weight: 800; text-align: center; }
.beauty-compare-photo figcaption { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; color: #64748b; }
.beauty-compare-photo figcaption b { padding: 3px 10px; border-radius: 999px; font-size: 11px; }
.beauty-compare-photo.is-before figcaption b { background: #fef3c7; color: #92400e; }
.beauty-compare-photo.is-after figcaption b { background: #dcfce7; color: #166534; }
.beauty-compare-angles { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.beauty-compare-angles button { width: auto !important; height: 30px !important; padding: 0 11px !important; border: 1px solid #e2e8f0 !important; border-radius: 999px !important; background: #f8fafc !important; color: #475569 !important; font-size: 11px !important; font-weight: 800; cursor: pointer; }
.beauty-compare-angles button.on { border-color: #0f766e !important; background: #0f766e !important; color: #fff !important; }
.beauty-folders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.beauty-folders .beauty-folder { display: flex; align-items: center; gap: 10px; width: 100%; height: auto !important; padding: 8px !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; background: #fff !important; color: #0f172a !important; text-align: right; cursor: pointer; transition: border-color .15s, background .15s; }
.beauty-folders .beauty-folder:hover { border-color: #0f766e !important; background: #f0fdfa !important; }
.beauty-folder-cover { flex: 0 0 56px; width: 56px; height: 56px; display: grid; place-items: center; overflow: hidden; border-radius: 10px; background: #e2e8f0; color: #64748b; font-size: 22px; font-style: normal; }
.beauty-folder-cover img { width: 100%; height: 100%; object-fit: cover; }
.beauty-folder-info { display: grid; gap: 3px; min-width: 0; }
.beauty-folder-info strong { font-size: 13px; font-weight: 900; }
.beauty-folder-info small { overflow: hidden; color: #64748b; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
@media (max-width: 560px) {
  .beauty-compare-head { flex-wrap: wrap; }
  .beauty-compare-grid { gap: 8px; }
}

/* ===== ظاهر تازه و واکنش‌گرای زیبایار ===== */
.beauty-flow {
  --bf-accent: #0f766e;
  --bf-accent-soft: #f0fdfa;
  --bf-border: #e6ebf0;
  --bf-muted: #64748b;
  --bf-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 10px 28px rgba(15, 23, 42, .05);
  max-width: 1440px;
  margin: 0 auto;
  background: transparent;
  box-sizing: border-box;
}
.beauty-flow *, .beauty-flow *::before, .beauty-flow *::after { box-sizing: border-box; }
.beauty-flow-card,
.beauty-flow-sidebar,
.beauty-flow-photo-panel,
.beauty-flow-record-header,
.beauty-compare,
.beauty-folders {
  border-color: var(--bf-border);
  border-radius: 20px;
  box-shadow: var(--bf-shadow);
}
.beauty-flow-main-toolbar { flex-wrap: wrap; }
.beauty-flow-search-grid-compact { min-width: 0; }
.beauty-flow input:focus,
.beauty-flow select:focus,
.beauty-flow textarea:focus { border-color: #5eead4; box-shadow: 0 0 0 3px rgba(20, 184, 166, .14); }
.beauty-flow button { transition: background-color .15s, border-color .15s, color .15s, box-shadow .15s, transform .15s; }
.beauty-flow button:not(:disabled):active { transform: translateY(1px); }

/* هدر پرونده */
.beauty-flow-record-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  overflow: hidden;
  padding: 16px 18px;
  background: linear-gradient(120deg, #ffffff 55%, #ecfdf5 100%);
}
.beauty-flow-record-header::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 3px;
  background: linear-gradient(90deg, #14b8a6, #0f766e 60%, #2563eb);
}
.beauty-flow-record-title { display: flex; align-items: center; gap: 12px; min-width: 0; }
.beauty-flow-record-title h2 { margin: 0; color: #0f172a; font-size: 18px; font-weight: 900; }
.beauty-flow-record-title p { margin: 3px 0 0; color: var(--bf-muted); font-size: 12px; }
.beauty-flow-record-badge {
  flex: 0 0 42px;
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: linear-gradient(135deg, #14b8a6, #0f766e);
  color: #fff;
  font-size: 18px;
  box-shadow: 0 8px 18px rgba(15, 118, 110, .25);
}
.beauty-flow-record-actions { flex-wrap: wrap; justify-content: flex-end; }
.beauty-flow-back { white-space: nowrap; }
.beauty-flow-back:hover { border-color: #99f6e4 !important; color: var(--bf-accent) !important; }

/* فضای کار: ستون کناری کنار عکس می‌چسبد تا فرم نقطه هنگام اسکرول دیده بماند */
.beauty-flow-workspace { grid-template-columns: 300px minmax(0, 1fr); gap: 16px; align-items: start; }
.beauty-flow-sidebar { position: sticky; top: 84px; max-height: calc(100vh - 100px); overflow: auto; scrollbar-width: thin; }
.beauty-flow-patient-box { padding: 4px 0 16px; }
.beauty-flow-photo-panel { min-height: 0; padding: 16px; }
.beauty-flow-photo-header { margin-bottom: 12px; }
.beauty-flow-photo-stage {
  min-height: 420px;
  background: radial-gradient(circle at 50% 35%, #1f2937, #0b1220 70%);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .04);
}
.beauty-flow-point { transition: transform .15s; }
.beauty-flow-point:hover { transform: translate(-50%, -50%) scale(1.18); }
.beauty-flow-sheet-backdrop { display: none; }

/* فهرست پرونده‌های در انتظار */
.beauty-flow-table tbody tr { transition: background .15s; }
.beauty-flow-table tbody tr:hover { background: #f8fafc; }

@media (max-width: 1100px) {
  .beauty-flow-workspace { grid-template-columns: 1fr; }
  .beauty-flow-sidebar { position: static; max-height: none; overflow: visible; }
  /* کارت بیمار فشرده و افقی تا عکس نقطه‌گذاری زودتر دیده شود */
  .beauty-flow-sidebar { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px; align-items: start; }
  .beauty-flow-patient-box {
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas: "avatar name" "avatar phone" "avatar level";
    justify-items: start;
    align-items: center;
    column-gap: 12px;
    row-gap: 2px;
    padding: 0;
    border-bottom: 0;
    text-align: right;
  }
  .beauty-flow-patient-box > .beauty-flow-avatar { grid-area: avatar; width: 64px; height: 64px; }
  .beauty-flow-patient-box > strong { grid-area: name; }
  .beauty-flow-patient-box > span:not(.beauty-flow-avatar):not(.beauty-flow-level) { grid-area: phone; }
  .beauty-flow-patient-box > .beauty-flow-level { grid-area: level; }
  .beauty-flow-side-block { margin-top: 0 !important; }
  .beauty-flow-legacy-block { grid-column: 1 / -1; }

  /* فرم ثبت یا جزئیات نقطه به شکل برگهٔ پایین صفحه */
  .beauty-flow-sheet-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    /* بالاتر از منوی سراسری و دکمهٔ دستیار (حدود 2147483010) */
    z-index: 2147483020;
    background: rgba(15, 23, 42, .42);
    backdrop-filter: blur(2px);
    animation: bf-fade .18s ease-out;
  }
  .beauty-flow .beauty-flow-editor {
    position: fixed;
    inset: auto 0 0 0;
    z-index: 2147483021;
    max-height: min(82vh, 640px);
    overflow: auto;
    margin: 0;
    padding: 22px 18px calc(18px + env(safe-area-inset-bottom));
    border: 0;
    border-radius: 22px 22px 0 0;
    background: #fff;
    box-shadow: 0 -18px 50px rgba(15, 23, 42, .22);
    animation: bf-sheet .22s ease-out;
  }
  .beauty-flow .beauty-flow-editor::before {
    content: "";
    position: absolute;
    top: 8px;
    left: 50%;
    width: 44px;
    height: 5px;
    border-radius: 999px;
    background: #cbd5e1;
    transform: translateX(-50%);
  }
  .beauty-flow .beauty-flow-editor-selected { background: #fff; }
}

@media (max-width: 700px) {
  .beauty-flow { padding: 10px; }
  .beauty-flow-card, .beauty-flow-sidebar { padding: 14px; border-radius: 18px; }
  .beauty-flow-photo-panel { padding: 12px 8px 8px; border-radius: 18px; }
  .beauty-flow-photo-panel > .beauty-flow-photo-header { padding: 0 6px; }
  .beauty-flow-side-block .beauty-flow-default-photo-note { display: none; }
  .beauty-flow-main-toolbar { display: grid; grid-template-columns: 42px 42px minmax(0, 1fr); }
  .beauty-flow-main-toolbar .beauty-flow-search-grid { grid-column: 1 / -1; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .beauty-flow-main-toolbar .beauty-flow-search-grid > button { grid-column: 1 / -1; }
  .beauty-flow-record-header { flex-direction: column; align-items: stretch; padding: 14px; }
  .beauty-flow-record-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
  .beauty-flow-back { min-width: 0; padding: 0 6px; font-size: 11px !important; }
  .beauty-flow-sidebar { grid-template-columns: 1fr; }
  .beauty-flow-photo-stage { min-height: 0; border-radius: 14px; }
  .beauty-flow-photo-stage img { max-height: 68vh; }
  .beauty-flow-point { width: 16px !important; height: 16px !important; min-width: 16px; }
  .beauty-flow-point-note { min-width: 0; max-width: 132px; min-height: 0; padding: 4px 6px; border-radius: 8px; transform: translate(calc(-100% - 12px), -50%); }
  .beauty-flow-point-note button { display: none; }
  .beauty-flow-point-note strong { font-size: 10px; }
  .beauty-flow-point-note span { font-size: 9px; }
  .beauty-flow-point-count span { padding: 5px 9px; font-size: 10.5px; }

  /* پرونده‌های در انتظار: هر ردیف یک کارت */
  .beauty-flow-table-wrap { overflow: visible; border: 0; }
  .beauty-flow-worklist-table, .beauty-flow-worklist-table tbody { display: block; min-width: 0; width: 100%; }
  .beauty-flow-worklist-table thead { display: none; }
  .beauty-flow-worklist-table tbody tr {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px 12px;
    margin-bottom: 10px;
    padding: 12px;
    border: 1px solid var(--bf-border);
    border-radius: 16px;
    background: #fff;
    box-shadow: var(--bf-shadow);
  }
  .beauty-flow-worklist-table td { display: grid; gap: 2px; padding: 0; border: 0; white-space: normal; font-size: 12.5px; }
  .beauty-flow-worklist-table td[data-label]::before { content: attr(data-label); color: #94a3b8; font-size: 10px; font-weight: 800; }
  .beauty-flow-worklist-table td.bf-cell-name { grid-column: 1 / -1; }
  .beauty-flow-worklist-table td.bf-cell-name::before { display: none; }
  .beauty-flow-worklist-table td.bf-cell-action { grid-column: 1 / -1; }
  .beauty-flow-worklist-table td.bf-cell-action button { width: 100%; }
  .beauty-flow-worklist-table td.beauty-flow-table-empty { display: block; padding: 26px 12px; color: var(--bf-muted); text-align: center; }
  .beauty-flow-worklist-table tr:has(> .beauty-flow-table-empty) { display: block; border-style: dashed; box-shadow: none; background: #f8fafc; }

  .beauty-compare, .beauty-folders { padding: 12px; }
  .beauty-folders-grid { grid-template-columns: 1fr; }
}

@keyframes bf-sheet { from { transform: translateY(100%); } to { transform: translateY(0); } }
@keyframes bf-fade { from { opacity: 0; } to { opacity: 1; } }
@media (prefers-reduced-motion: reduce) {
  .beauty-flow-sheet-backdrop, .beauty-flow .beauty-flow-editor { animation: none; }
}

/* نمایش بزرگ عکس قبل/بعد */
.beauty-compare-photo > button { position: relative; }
.beauty-compare-zoom-hint {
  position: absolute;
  top: 8px;
  left: 8px;
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: rgba(15, 23, 42, .55);
  color: #fff;
  font-size: 15px;
  opacity: .85;
  transition: opacity .15s, transform .15s;
}
.beauty-compare-photo > button:hover .beauty-compare-zoom-hint { opacity: 1; transform: scale(1.08); }
.beauty-compare-photo figcaption span { flex: 1; }
.beauty-compare .beauty-compare-folder-link {
  width: auto !important;
  height: 26px !important;
  padding: 0 9px !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 8px !important;
  background: #fff !important;
  color: #0f766e !important;
  font-size: 11px !important;
}
.bf-lightbox {
  position: fixed;
  inset: 0;
  z-index: 2147483030;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  background: #020617;
  color: #e2e8f0;
  animation: bf-fade .15s ease-out;
}
.bf-lightbox-bar { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #020617; }
.bf-lightbox-title { display: grid; gap: 2px; min-width: 0; }
.bf-lightbox-title strong { color: #fff; font-size: 14px; font-weight: 900; }
.bf-lightbox-title small { color: #94a3b8; font-size: 12px; }
.bf-lightbox button {
  height: 38px;
  min-width: 38px;
  padding: 0 12px;
  border: 1px solid rgba(148, 163, 184, .28) !important;
  border-radius: 11px !important;
  background: rgba(30, 41, 59, .85) !important;
  color: #e2e8f0 !important;
  font-size: 13px !important;
  font-weight: 800;
}
.bf-lightbox button:hover:not(:disabled) { background: rgba(51, 65, 85, .95) !important; color: #fff !important; }
.bf-lightbox button:disabled { opacity: .35; cursor: default; }
.bf-lightbox-tabs { display: flex; gap: 4px; padding: 3px; border-radius: 13px; background: rgba(15, 23, 42, .7); }
.bf-lightbox-tabs button { height: 32px; border-color: transparent !important; background: transparent !important; }
.bf-lightbox-tabs button.on { background: #0f766e !important; color: #fff !important; }
.bf-lightbox .bf-lightbox-close { width: 40px; padding: 0; font-size: 24px !important; line-height: 1; }
.bf-lightbox-stage {
  position: relative;
  display: grid;
  place-items: center;
  overflow: hidden;
  touch-action: none;
  cursor: zoom-in;
  user-select: none;
}
.bf-lightbox-stage.is-zoomed { cursor: grab; }
.bf-lightbox-stage.is-dragging { cursor: grabbing; }
.bf-lightbox-stage img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transform-origin: center center;
  transition: transform .12s ease-out;
  will-change: transform;
  -webkit-user-drag: none;
}
.bf-lightbox-stage.is-dragging img { transition: none; }
.bf-lightbox-tools { justify-content: center; gap: 18px; padding-bottom: calc(14px + env(safe-area-inset-bottom)); }
.bf-lightbox-angle, .bf-lightbox-zoom { display: flex; align-items: center; gap: 6px; }
.bf-lightbox-angle span { min-width: 104px; color: #cbd5e1; font-size: 12px; font-weight: 800; text-align: center; }
.bf-lightbox-angle button, .bf-lightbox-zoom button { font-size: 18px !important; padding: 0; }
.bf-lightbox .bf-lightbox-zoom-value { min-width: 64px; font-size: 12px !important; }
@media (max-width: 560px) {
  .bf-lightbox-bar { padding: 10px; gap: 8px; }
  .bf-lightbox-title strong { font-size: 13px; }
  .bf-lightbox-tools { flex-wrap: wrap; gap: 10px; }
  .bf-lightbox-angle span { min-width: 84px; }
}

.bf-chevron { width: 18px; height: 18px; display: block; margin: auto; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
</style>
