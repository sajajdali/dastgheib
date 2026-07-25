<template>
  <div class="flwup-root" @click="closeAllFilters">
    <div class="page-shell">
<!-- Header -->
      <header class="header">
        <div class="title-wrap">
          <div class="title">تماس‌ها و تبلیغات</div>
          <div class="top-actions" role="toolbar" aria-label="عملیات پیگیری">
            <button type="button" class="followup-action filter-action" title="فیلتر هوشمند" aria-label="فیلتر هوشمند" @click.stop="showDateReportModal = true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span>فیلتر</span></button>
            <button type="button" class="followup-action create-action" title="ایجاد کمپین" aria-label="ایجاد کمپین" @click.stop="openCreateCampaignModal"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>ایجاد کمپین</span></button>
            <button type="button" class="followup-action archive-action" :class="{ active: showArchived }" title="نمایش آرشیوشده‌ها" aria-label="نمایش آرشیوشده‌ها" @click.stop="toggleArchivedView"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 4h18v3H3zM9 11h6"/></svg><span>آرشیو</span><b v-if="archivedCampaigns.length">{{ archivedCampaigns.length }}</b></button>
            <button type="button" class="followup-action missed-action" :class="{ active: showMissedFollowups }" title="نمایش عدم پیگیری‌ها" aria-label="نمایش عدم پیگیری‌ها" @click.stop="showMissedFollowups = !showMissedFollowups"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg><span>عدم پیگیری</span><b v-if="missedFollowups.length">{{ missedFollowups.length }}</b></button>
          </div>
        </div>
      </header>
      <!-- Today Alerts -->
      <div v-if="!showArchived && todayFollowups.length" class="today-alert-box">
        <div class="today-alert-header">
          <div class="today-alert-title">هشدار پیگیری امروز</div>
          <div class="today-alert-badge">{{ todayFollowups.length }} مورد</div>
        </div>

        <div class="today-alert-desc">
          این افراد امروز باید پیگیری شوند. بهتره در اولویت تماس قرار بگیرند.
        </div>

        <div class="today-followup-list">
          <div
            v-for="item in todayFollowups"
            :key="item._localId + '-' + item.campaignId"
            class="today-followup-item"
          >
            <div class="today-followup-main">
              <div class="person-name">{{ item.fullName || 'بدون نام' }}</div>
              <div class="person-meta">
                <span>{{ item.phone || 'بدون شماره' }}</span>
                <span>•</span>
                <span>{{ item.consultant || 'بدون مشاور' }}</span>
                <span>•</span>
                <span>{{ item.campaignTitle }}</span>
              </div>
            </div>

            <button class="quick-open-btn" @click.stop="openCampaign(item.campaignId)">
              باز کردن
            </button>
          </div>
        </div>
      </div>

      <div v-if="showMissedFollowups" class="missed-followups-panel">
        <h3>عدم پیگیری</h3>
        <div v-if="!missedFollowups.length" class="empty-state">موردی وجود ندارد.</div>
        <div v-for="item in missedFollowups" :key="`missed-${item.campaignId}-${item._localId}`" class="missed-followup-row">
          <span>{{ item.fullName || 'بدون نام' }}</span><span>{{ item.phone || 'بدون شماره' }}</span>
          <span>{{ item.campaignTitle }}</span><span>{{ formatDateFa(item.followUpDate) }}</span>
          <button type="button" @click.stop="openCampaign(item.campaignId)">باز کردن</button>
        </div>
      </div>

      <section v-if="showArchived" class="archive-view-banner" aria-live="polite">
        <div class="archive-view-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 4h18v3H3zM9 11h6"/></svg></div>
        <div><small>نمای فعلی</small><strong>کمپین‌های آرشیوشده</strong><span>{{ archivedCampaigns.length.toLocaleString('fa-IR') }} کمپین در آرشیو</span></div>
        <button type="button" @click.stop="toggleArchivedView">بازگشت به کمپین‌های فعال</button>
      </section>

      <div v-if="showArchived && !visibleCampaigns.length" class="archive-empty-state">
        <span>آرشیو خالی است</span>
        <small>هنوز هیچ کمپینی به آرشیو منتقل نشده است.</small>
        <button type="button" @click.stop="toggleArchivedView">بازگشت</button>
      </div>
      <!-- Empty State -->
      <div v-if="!showArchived && !campaigns.length" class="empty-campaigns">
        <div class="empty-icon">📣</div>
        <div class="empty-title">هنوز هیچ تبلیغی ثبت نشده</div>
        <div class="empty-text">
          برای شروع، یک کمپین تبلیغاتی بساز و تماس‌های مربوط به آن را ثبت کن.
        </div>
      </div>

      <!-- Campaign Grid -->
      <draggable
        v-model="visibleCampaigns"
        item-key="id"
        class="campaign-grid"
        animation="200"
        ghost-class="sortable-ghost"
      >

        <template #item="{ element: campaign }">

          <div
            class="campaign-card"
            :class="{ danger: !showArchived && hasTodayFollowups(campaign), archived: isCampaignArchived(campaign) }"
          >

            <div class="campaign-card-top">

              <div class="campaign-main-info">

                <div class="campaign-title-row">

                  <h3 class="campaign-title">
                    کمپین {{ campaignNumber(campaign) }} ـ {{ campaign.title || 'بدون عنوان' }}
                  </h3>
                  <select v-model="campaign.campaignStatus" class="campaign-status-select" :class="campaignStatusClass(campaign.campaignStatus)" @click.stop>
                    <option value="active">فعال</option><option value="paused">متوقف</option>
                    <option value="finished">پایان‌یافته</option><option value="archived">آرشیو</option>
                  </select>

                  <span
                    v-if="hasTodayFollowups(campaign)"
                    class="urgent-pill"
                  >
                    پیگیری امروز
                  </span>

                </div>

                <div class="campaign-meta">

                  <div class="meta-chip">
                    <span class="meta-label">تاریخ</span>
                    <span class="meta-value">
                      {{ formatDateFa(campaign.date) }}
                    </span>
                  </div>
                  <div v-if="canViewCampaignCost" class="meta-chip"><span class="meta-label">CPL</span><span class="meta-value">{{ formatMoney(campaignCpl(campaign)) }}</span></div>

                  <div v-if="canViewCampaignCost" class="meta-chip">
                    <span class="meta-label">هزینه</span>
                    <span class="meta-value">
                      {{ formatMoney(campaign.cost) }}
                    </span>
                  </div>

                  <div class="meta-chip">
                    <span class="meta-label">تعداد لید</span>
                    <span class="meta-value">
                      {{ getLeadCount(campaign) }}
                    </span>
                  </div>

                </div>

              </div>

              <div
                class="score-box"
                :class="scoreClass(calculateCampaignScore(campaign))"
              >

                <div class="score-label">
                  کیفیت تبلیغ
                </div>

                <div class="score-value">
                  {{ calculateCampaignScore(campaign) }}
                </div>

                <div class="score-from">
                  از 100
                </div>

              </div>

            </div>

            <div class="stats-grid">

              <div class="stat-card answered">
                <div class="stat-title">پاسخ دادند</div>
                <div class="stat-value">
                  {{ getAnsweredCount(campaign) }}
                </div>
              </div>

              <div class="stat-card noanswer">
                <div class="stat-title">پاسخ ندادند</div>
                <div class="stat-value">
                  {{ getNoAnswerCount(campaign) }}
                </div>
              </div>

              <div class="stat-card pending">
                <div class="stat-title">هنوز تماس نگرفته</div>
                <div class="stat-value">
                  {{ getUncalledCount(campaign) }}
                </div>
              </div>

              <div class="stat-card oktime">
                <div class="stat-title">وقت داده شد</div>
                <div class="stat-value">
                  {{ getAppointmentCount(campaign) }}
                </div>
              </div>

            </div>

            <div class="chart-card">

              <div class="chart-title">
                گزارش عملکرد تبلیغ
              </div>

              <div class="bar-chart">

                <div class="bar-item">
                  <div class="bar-label">کم</div>
                  <div class="bar-track">
                    <div
                      class="bar-fill level-1"
                      :style="{ width: percent(getInterestCount(campaign, '1'), getLeadCount(campaign)) + '%' }"
                    ></div>
                  </div>
                  <div class="bar-num">
                    {{ getInterestCount(campaign, '1') }}
                  </div>
                </div>

                <div class="bar-item">
                  <div class="bar-label">متوسط</div>
                  <div class="bar-track">
                    <div
                      class="bar-fill level-2"
                      :style="{ width: percent(getInterestCount(campaign, '2'), getLeadCount(campaign)) + '%' }"
                    ></div>
                  </div>
                  <div class="bar-num">
                    {{ getInterestCount(campaign, '2') }}
                  </div>
                </div>

                <div class="bar-item">
                  <div class="bar-label">زیاد</div>
                  <div class="bar-track">
                    <div
                      class="bar-fill level-3"
                      :style="{ width: percent(getInterestCount(campaign, '3'), getLeadCount(campaign)) + '%' }"
                    ></div>
                  </div>
                  <div class="bar-num">
                    {{ getInterestCount(campaign, '3') }}
                  </div>
                </div>

                <div class="bar-item">
                  <div class="bar-label">وقت داده شد</div>
                  <div class="bar-track">
                    <div
                      class="bar-fill level-ok"
                      :style="{ width: percent(getAppointmentCount(campaign), getLeadCount(campaign)) + '%' }"
                    ></div>
                  </div>
                  <div class="bar-num">
                    {{ getAppointmentCount(campaign) }}
                  </div>
                </div>

                <div class="bar-item">
                  <div class="bar-label">بی‌پاسخ</div>
                  <div class="bar-track">
                    <div
                      class="bar-fill level-no"
                      :style="{ width: percent(getNoAnswerCount(campaign), getLeadCount(campaign)) + '%' }"
                    ></div>
                  </div>
                  <div class="bar-num">
                    {{ getNoAnswerCount(campaign) }}
                  </div>
                </div>

              </div>

            </div>

            <div class="campaign-footer">

              <button
                class="open-btn"
                style="text-align:center !important;"
                @click.stop="openCampaign(campaign.id)"
              >
                مشاهده جزئیات و جدول
              </button>

              <button class="archive-btn" @click.stop="toggleCampaignArchive(campaign)">
                {{ campaign.campaignStatus === 'archived' ? 'خروج از آرشیو' : 'آرشیو' }}
              </button>

              <button
                class="delete-btn"
                @click.stop="removeCampaign(campaign.id)"
              >
                حذف
              </button>

            </div>

          </div>

        </template>

      </draggable>
    </div>

    <!-- Create Campaign Modal -->
    <div
      v-if="showCampaignModal"
      class="modal-overlay"
      @click.self="showCampaignModal = false"
    >
      <div class="campaign-modal">
        <div class="modal-title">اطلاعات کمپین</div>
        <div class="modal-subtitle">
      
        </div>

        <div class="form-grid">
          <div class="field">
            <label>موضوع تبلیغات</label>
            <input
              v-model="newCampaign.title"
              type="text"
              placeholder="مثلا تبلیغ اینستاگرام"
            />
          </div>

          <div class="field">
            <label>منبع</label>
            <select v-model="newCampaign.source" :disabled="channelsLoading">
              <option value="">
                {{ channelsLoading ? 'در حال بارگذاری منابع...' : 'انتخاب منبع' }}
              </option>
              <option
                v-for="channel in channelOptions"
                :key="channel.id"
                :value="channel.name"
              >
                {{ channel.name }}
              </option>
            </select>
            <small v-if="channelsLoadError" class="field-error">
              {{ channelsLoadError }}
              <button type="button" @click="loadChannels">تلاش مجدد</button>
            </small>
            <small v-else-if="!channelsLoading && !channelOptions.length" class="field-hint">
              هنوز کانال تبلیغاتی در بخش منابع ثبت نشده است.
            </small>
          </div>

          <div class="field">
            <label>نام منبع</label>
            <input v-model="newCampaign.sourceName" type="text" placeholder="نام صفحه، معرف یا مجموعه" />
          </div>

          <div class="field">
            <label>بنرهای کمپین</label>
            <input type="file" accept="image/*" multiple @change="handleCampaignAttachment" />
            <small v-if="newCampaign.banners.length" class="field-hint">{{ newCampaign.banners.length.toLocaleString('fa-IR') }} بنر انتخاب شده</small>
          </div>

          <div class="field">
            <label>تاریخ</label>
            <date-picker
            v-model="newCampaign.date"
            format="YYYY-MM-DD"
            display-format="jYYYY/jMM/jDD"
           input-class="date-input"
           placeholder="تاریخ را انتخاب کنید"
          />
          </div>

          <div class="field">
            
            <input
              v-model="newCampaign.cost"
              type="number"
              placeholder="هزینه "
            />
          </div>
        </div>

        <div v-if="campaignFormError" class="campaign-form-error" role="alert">
          {{ campaignFormError }}
        </div>

        <div class="modal-actions">
          <button type="button" class="secondary-btn" @click="showCampaignModal = false">
            انصراف
          </button>
          <button type="button" class="primary-btn" @click.stop="createCampaign">
            ایجاد کمپین
          </button>
        </div>
      </div>
    </div>

    <!-- Date Report Modal -->
    <div
      v-if="showDateReportModal"
      class="modal-overlay large"
      @click.self="showDateReportModal = false"
    >
      <div class="campaign-table-modal">
        <div class="campaign-modal-header">
          <div class="campaign-modal-info">
            <div class="campaign-modal-title-row">
              <div class="campaign-modal-title">فیلتر هوشمند پیگیری‌ها</div>
            </div>
            <div class="campaign-modal-meta" style="margin-top: 10px;">
              <div style="display: flex; gap: 15px; align-items: center;">
                <div class="field" style="margin:0; width: 200px;">
                  <label style="display:inline-block; margin-left: 5px;">از تاریخ:</label>
                  <date-picker
  v-model="reportDateFrom"
  format="YYYY-MM-DD"
  display-format="jYYYY/jMM/jDD"
  input-class="date-input"
/>
                </div>
                <div class="field" style="margin:0; width: 200px;">
                  <label style="display:inline-block; margin-left: 5px;">تا تاریخ:</label>
                  <date-picker
  v-model="reportDateTo"
  format="YYYY-MM-DD"
  display-format="jYYYY/jMM/jDD"
  input-class="date-input"
/>
                </div>
              </div>
            </div>
          </div>

          <button class="close-btn" style="    text-align: center !important;" @click="showDateReportModal = false">×</button>
        </div>

        <div class="report-filter-tabs">
          <button :class="{ active: reportFilterTab === 'campaigns' }" @click="reportFilterTab = 'campaigns'">فیلتر کمپین</button>
          <button :class="{ active: reportFilterTab === 'customers' }" @click="reportFilterTab = 'customers'">فیلتر مشتری‌ها</button>
          <span>{{ reportFilterTab === 'campaigns' ? filteredReportCampaigns.length : reportFilteredRows.length }} نتیجه</span>
        </div>

        <section v-if="reportFilterTab === 'campaigns'" class="report-filter-panel">
          <div class="report-filter-group wide"><b>جست‌وجوی کمپین</b><input v-model="reportSearch" placeholder="نام کمپین یا منبع تبلیغ..." /></div>
          <div class="report-filter-group"><b>وضعیت کمپین</b><div class="report-filter-chips"><label v-for="item in campaignStatusFilterOptions" :key="item.value" :class="{ selected: reportCampaignStatuses.includes(item.value) }"><input v-model="reportCampaignStatuses" type="checkbox" :value="item.value">{{ item.label }}</label></div></div>
          <div class="report-filter-group"><b>منبع کمپین</b><div class="report-filter-chips"><label v-for="source in reportCampaignSourceOptions" :key="source" :class="{ selected: reportCampaignSources.includes(source) }"><input v-model="reportCampaignSources" type="checkbox" :value="source">{{ source }}</label><small v-if="!reportCampaignSourceOptions.length">منبعی ثبت نشده است</small></div></div>
          <div class="report-filter-group"><b>کیفیت تبلیغ</b><div class="report-filter-chips"><label v-for="quality in campaignQualityOptions" :key="quality" :class="{ selected: reportCampaignQualities.includes(quality) }"><input v-model="reportCampaignQualities" type="checkbox" :value="quality">{{ quality }}</label></div></div>
          <div class="report-filter-group report-cost-range"><b>بازه هزینه</b><div><input v-model.number="reportCostMin" type="number" min="0" placeholder="از مبلغ"><input v-model.number="reportCostMax" type="number" min="0" placeholder="تا مبلغ"></div></div>
        </section>

        <section v-else class="report-filter-panel customer-filters">
          <div class="report-filter-group wide"><b>جست‌وجوی مشتری</b><input v-model="reportSearch" placeholder="نام، شماره تماس، مشاور یا توضیحات..." /></div>
          <div class="report-filter-group"><b>نام مشاور</b><div class="report-filter-chips"><label v-for="staff in staffOptions" :key="staff.id" :class="{ selected: selectedReportFilters.consultant.includes(staff.name) }"><input v-model="selectedReportFilters.consultant" type="checkbox" :value="staff.name">{{ staff.name }}</label></div></div>
          <div class="report-filter-group"><b>وضعیت پاسخ</b><div class="report-filter-chips"><label v-for="status in getStatusOptions()" :key="status" :class="{ selected: selectedReportFilters.status.includes(status) }"><input v-model="selectedReportFilters.status" type="checkbox" :value="status">{{ status }}</label></div></div>
          <div class="report-filter-group"><b>درجه تمایل</b><div class="report-filter-chips"><label v-for="interest in ['1','2','3','ok']" :key="interest" :class="{ selected: selectedReportFilters.interest.includes(interest) }"><input v-model="selectedReportFilters.interest" type="checkbox" :value="interest">{{ interest }}</label></div></div>
          <div class="report-filter-group wide"><b>علت عدم تبدیل</b><div class="report-filter-chips"><label v-for="reason in reasonOptions" :key="reason" :class="{ selected: selectedReportFilters.reason.includes(reason) }"><input v-model="selectedReportFilters.reason" type="checkbox" :value="reason">{{ reason }}</label></div></div>
        </section>

        <div class="report-filter-actions"><button @click="resetAdvancedReportFilters">پاک کردن همه فیلترها</button></div>

        <section v-if="reportFilterTab === 'campaigns'" class="filtered-campaign-results">
          <article v-for="campaign in filteredReportCampaigns" :key="campaign.id" @click="showDateReportModal=false; openCampaign(campaign.id)">
            <header><strong>{{ campaign.title || 'کمپین بدون عنوان' }}</strong><span :class="campaignStatusClass(campaign.campaignStatus)">{{ campaignStatusLabel(campaign.campaignStatus) }}</span></header>
            <div><span>تاریخ <b>{{ formatDateFa(campaign.date) }}</b></span><span>کیفیت <b>{{ campaignQualityLabel(campaign) }}</b></span><span>هزینه <b>{{ formatMoney(campaign.cost) }}</b></span><span>لید <b>{{ getLeadCount(campaign) }}</b></span></div>
          </article>
          <div v-if="!filteredReportCampaigns.length" class="empty-state">کمپینی مطابق فیلترها پیدا نشد.</div>
        </section>

        <div v-if="reportFilterTab === 'customers'" class="modal-content-grid">
          <div class="table-panel">
            <div class="toolbar">
              <input
                v-model="reportSearch"
                type="text"
                class="toolbar-input"
                placeholder="جستجو در نام، شماره، مشاور، توضیحات..."
                @click.stop
              />
            </div>

            <div class="table-scroll">
              <table class="contacts-table" :style="{ tableLayout: 'fixed' }">
                <thead>
                  <tr>
                    <!-- کمپین -->
                    <th class="center resizable" :style="{ width: colWidths.campaignTitle + 'px' }">
                      <div class="th-content">نام کمپین</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'campaignTitle')" @dblclick.stop="autoFitFollowupColumn('campaignTitle')"></div>
                    </th>

                    <!-- نام -->
                    <th class="center resizable" :style="{ width: colWidths.fullName + 'px' }">
                      <div class="th-content">نام کامل</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'fullName')" @dblclick.stop="autoFitFollowupColumn('fullName')"></div>
                    </th>

                    <!-- شماره -->
                    <th class="center resizable" :style="{ width: colWidths.phone + 'px' }">
                      <div class="th-content">شماره تماس</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'phone')" @dblclick.stop="autoFitFollowupColumn('phone')"></div>
                    </th>

                    <!-- تاریخ تماس -->
                   <th class="center resizable" :style="{ width: colWidths.contactDate + 'px' }">
                    <div class="th-content">تاریخ تماس</div>
                       <div class="resizer" @mousedown.stop.prevent="initResize($event, 'contactDate')" @dblclick.stop="autoFitFollowupColumn('contactDate')"></div>
                   </th>

                    <!-- تاریخ پیگیری -->
                    <th class="center resizable" :style="{ width: colWidths.followUpDate + 'px' }">
                      <div class="th-content">تاریخ پیگیری</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'followUpDate')" @dblclick.stop="autoFitFollowupColumn('followUpDate')"></div>
                    </th>

                    <!-- جنسیت -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isReportFiltered('gender') }"
                      :style="{ width: colWidths.gender + 'px' }"
                    >
                      <div class="th-content">
                        جنسیت
                        <button class="filter-btn" @click.stop="toggleReportFilterMenu('gender')">⚙</button>
                      </div>

                      <div v-if="activeReportFilter === 'gender'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getReportUniqueValues('gender')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedReportFilters.gender.includes(val)"
                            @change="toggleReportValue('gender', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'gender')" @dblclick.stop="autoFitFollowupColumn('gender')"></div>
                    </th>

                    <!-- مشاور -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isReportFiltered('consultant') }"
                      :style="{ width: colWidths.consultant + 'px' }"
                    >
                      <div class="th-content">
                        مشاور
                        <button class="filter-btn" @click.stop="toggleReportFilterMenu('consultant')">⚙</button>
                      </div>

                      <div v-if="activeReportFilter === 'consultant'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getReportUniqueValues('consultant')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedReportFilters.consultant.includes(val)"
                            @change="toggleReportValue('consultant', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'consultant')" @dblclick.stop="autoFitFollowupColumn('consultant')"></div>
                    </th>

                    <!-- منبع -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isReportFiltered('source') }"
                      :style="{ width: colWidths.source + 'px' }"
                    >
                      <div class="th-content">
                        منبع
                        <button class="filter-btn" @click.stop="toggleReportFilterMenu('source')">⚙</button>
                      </div>

                      <div v-if="activeReportFilter === 'source'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getReportUniqueValues('source')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedReportFilters.source.includes(val)"
                            @change="toggleReportValue('source', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'source')" @dblclick.stop="autoFitFollowupColumn('source')"></div>
                    </th>

                    <!-- وضعیت -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isReportFiltered('status') }"
                      :style="{ width: colWidths.status + 'px' }"
                    >
                      <div class="th-content">
                        وضعیت
                        <button class="filter-btn" @click.stop="toggleReportFilterMenu('status')">⚙</button>
                      </div>

                      <div v-if="activeReportFilter === 'status'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getStatusOptions()" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedReportFilters.status.includes(val)"
                            @change="toggleReportValue('status', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'status')" @dblclick.stop="autoFitFollowupColumn('status')"></div>
                    </th>

                    <!-- توضیحات -->
                    <th class="desc-col resizable" :style="{ width: colWidths.description + 'px' }">
                      <div class="th-content">توضیحات</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'description')" @dblclick.stop="autoFitFollowupColumn('description')"></div>
                    </th>

                    <th class="center resizable" :style="{ width: colWidths.reason + 'px' }">
                      <div class="th-content">علت</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'reason')" @dblclick.stop="autoFitFollowupColumn('reason')"></div>
                    </th>

                    <!-- پیامک لندینگ -->
<th class="resizable" :style="{ width: colWidths.landingSms + 'px' }">
  <div class="th-content">پیامک لندینگ</div>
  <div class="resizer" @mousedown.stop.prevent="initResize($event, 'landingSms')" @dblclick.stop="autoFitFollowupColumn('landingSms')"></div>
</th>

                    <!-- تمایل -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isReportFiltered('interest') }"
                      :style="{ width: colWidths.interest + 'px' }"
                    >
                      <div class="th-content">
                        تمایل
                        <button class="filter-btn" @click.stop="toggleReportFilterMenu('interest')">⚙</button>
                      </div>

                      <div v-if="activeReportFilter === 'interest'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getReportUniqueValues('interest')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedReportFilters.interest.includes(val)"
                            @change="toggleReportValue('interest', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'interest')" @dblclick.stop="autoFitFollowupColumn('interest')"></div>
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="row in reportFilteredRows" :key="row._localId">
                    <td class="center">{{ row.campaignTitle }}</td>
                    <td><input v-model="row.fullName" disabled /></td>
                    <td><input v-model="row.phone" disabled /></td>
                    <td>
                      <date-picker
                        v-model="row.contactDate"
                        format="YYYY-MM-DD"
                        display-format="jYYYY/jMM/jDD"
                        input-class="table-date-input"
                        placeholder="تاریخ تماس"
                      />
                    </td>
                    <td>
  <date-picker
    v-model="row.followUpDate"
    format="YYYY-MM-DD"
    display-format="jYYYY/jMM/jDD"
    input-class="table-date-input"
    placeholder="تاریخ"
  />
</td>

                    <td
                      :class="[
                        { 'filtered-cell': isReportFiltered('gender') },
                        genderClass(row.gender)
                      ]"
                    >
                      <select v-model="row.gender" disabled>
                        <option value=""></option>
                        <option value="زن">زن</option>
                        <option value="مرد">مرد</option>
                      </select>
                    </td>

                    <td :class="{ 'filtered-cell': isReportFiltered('consultant') }">
                      <select v-model="row.consultant" disabled>
                        <option value=""></option>
                        <option
                          v-for="staff in staffOptions"
                          :key="staff.id"
                          :value="staff.name"
                        >
                          {{ staff.name }}
                        </option>
                      </select>
                    </td>

                    <td :class="{ 'filtered-cell': isReportFiltered('source') }">
                      <select v-model="row.source" disabled>
                        <option value=""></option>
                        <option
                          v-for="channel in channelOptions"
                          :key="channel.id"
                          :value="channel.name"
                        >
                          {{ channel.name }}
                        </option>
                      </select>
                    </td>

                    <td
                      :class="[
                        { 'filtered-cell': isReportFiltered('status') },
                        statusClass(row.status)
                      ]"
                    >
                      <select v-model="row.status" disabled>
                        <option value=""></option>
                        <option value="پاسخ داد">پاسخ داد</option>
                        <option value="پاسخ نداد">پاسخ نداد</option>
                        <option value="اشتباه">اشتباه</option>
                        <option value="پیگیری">پیگیری</option>
                      </select>
                    </td>

                    <td class="desc-col">
                      <input v-model="row.description" disabled />
                    </td>

                    <td><input v-model="row.reason" disabled /></td>

                    <td><input :value="landingSmsText(row.landingSms)" disabled /></td>

                    <td
                      :class="[
                        { 'filtered-cell': isReportFiltered('interest') },
                        interestClass(row.interest)
                      ]"
                    >
                      <select v-model="row.interest" disabled>
                        <option value=""></option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="ok">ok</option>
                      </select>
                    </td>
                  </tr>

                  <tr v-if="!reportFilteredRows.length">
                    <td colspan="13" class="empty-state">
                      موردی در این بازه تاریخی یافت نشد.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Campaign Details Modal -->
    <div
      v-if="activeCampaign"
      class="modal-overlay large"
      @click.self="closeCampaignModal"
    >
      <div class="campaign-table-modal" :class="{ danger: hasTodayFollowups(activeCampaign) }">
        <div class="campaign-modal-header">
          <div class="campaign-modal-info">
            <div class="campaign-modal-title-row">
              <div class="campaign-modal-title">{{ activeCampaign.title }}</div>
              <span
                v-if="hasTodayFollowups(activeCampaign)"
                class="urgent-pill"
              >
                نیازمند پیگیری امروز
              </span>
            </div>

            <div class="campaign-modal-meta">
              <span>تاریخ: {{ formatDateFa(activeCampaign.date) }}</span>
              <span>•</span>
              <span v-if="activeCampaign.source">منبع: {{ activeCampaign.source }}</span>
              <span v-if="activeCampaign.source">•</span>
              <span v-if="activeCampaign.sourceName">نام منبع: {{ activeCampaign.sourceName }}</span>
              <span v-if="activeCampaign.sourceName">•</span>
              <template v-if="canViewCampaignCost"><span>هزینه: {{ formatMoney(activeCampaign.cost) }}</span><span>•</span><span>CPL: {{ formatMoney(campaignCpl(activeCampaign)) }}</span></template>
              <span>کیفیت: {{ calculateCampaignScore(activeCampaign) }}/100</span>
            </div>
          </div>

          <div class="campaign-header-actions">
            <button
              type="button"
              class="campaign-icon-action banner-gallery-trigger"
              title="گالری بنرهای کمپین"
              aria-label="گالری بنرهای کمپین"
              @click.stop="openBannerGallery(activeCampaign)"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m5 17 4.5-4 3.2 2.7 2.5-2.2L19 17"/></svg>
              <span v-if="campaignBanners(activeCampaign).length">{{ campaignBanners(activeCampaign).length.toLocaleString('fa-IR') }}</span>
            </button>
            <button class="close-btn" title="بستن" aria-label="بستن" @click="closeCampaignModal">×</button>
          </div>
        </div>

        <div class="modal-content-grid">
          <!-- Right / Table -->
          <div class="table-panel">
            <div class="toolbar">
              <input
                v-model="campaignSearch"
                type="text"
                class="toolbar-input"
                placeholder="جستجو در نام، شماره، مشاور، توضیحات..."
                @click.stop
              />

              <div v-if="canOpenAppointments" class="timeline-date-field" @click.stop>
                <date-picker
                  v-model="appointmentTimelineDate"
                  format="YYYY-MM-DD"
                  display-format="jYYYY/jMM/jDD"
                  input-class="timeline-date-input"
                  placeholder="تاریخ نوبت"
                />
                <span v-if="appointmentTimelineDay">{{ appointmentTimelineDay }}</span>
              </div>

              <button
                v-if="canOpenAppointments"
                type="button"
                class="open-timeline-btn"
                :disabled="!appointmentTimelineDate"
                @click.stop="openAppointmentsTimeline"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="8"></circle>
                  <path d="M12 8v5l3 2"></path>
                </svg>
                تایم‌لاین وقت‌دهی
              </button>

              <button 
                class="sms-send-btn"
                @click.stop="openSmsPanel"
                style="margin-right: 10px;"
              >
                ارسال پیامک
              </button>
            </div>

            <div class="table-scroll">
              <table class="contacts-table" :style="{ tableLayout: 'fixed' }">
                <thead>
                  <tr>
                    <!-- نام -->
                    <th class="center resizable" :style="{ width: colWidths.fullName + 'px' }">
                      <div class="th-content">نام کامل</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'fullName')" @dblclick.stop="autoFitFollowupColumn('fullName')"></div>
                    </th>

                    <!-- شماره -->
                    <th class="center resizable" :style="{ width: colWidths.phone + 'px' }">
                      <div class="th-content">شماره تماس</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'phone')" @dblclick.stop="autoFitFollowupColumn('phone')"></div>
                    </th>

                    <!-- تاریخ پیگیری -->
                    <th class="center resizable" :style="{ width: colWidths.contactDate + 'px' }">
                      <div class="th-content">تاریخ تماس</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'contactDate')" @dblclick.stop="autoFitFollowupColumn('contactDate')"></div>
                    </th>

                    <!-- تاریخ پیگیری -->
                    <th class="center resizable" :style="{ width: colWidths.followUpDate + 'px' }">
                      <div class="th-content">تاریخ پیگیری</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'followUpDate')" @dblclick.stop="autoFitFollowupColumn('followUpDate')"></div>
                    </th>

                    <!-- جنسیت -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isFiltered('gender') }"
                      :style="{ width: colWidths.gender + 'px' }"
                    >
                      <div class="th-content">
                        جنسیت
                        <button class="filter-btn" @click.stop="toggleFilterMenu('gender')">⚙</button>
                      </div>

                      <div v-if="activeFilter === 'gender'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getUniqueValues('gender')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedFilters.gender.includes(val)"
                            @change="toggleValue('gender', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'gender')" @dblclick.stop="autoFitFollowupColumn('gender')"></div>
                    </th>

                    <!-- مشاور -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isFiltered('consultant') }"
                      :style="{ width: colWidths.consultant + 'px' }"
                    >
                      <div class="th-content">
                        مشاور
                        <button class="filter-btn" @click.stop="toggleFilterMenu('consultant')">⚙</button>
                      </div>

                      <div v-if="activeFilter === 'consultant'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getUniqueValues('consultant')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedFilters.consultant.includes(val)"
                            @change="toggleValue('consultant', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'consultant')" @dblclick.stop="autoFitFollowupColumn('consultant')"></div>
                    </th>
                    <!-- وضعیت -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isFiltered('status') }"
                      :style="{ width: colWidths.status + 'px' }"
                    >
                      <div class="th-content">
                        وضعیت
                        <button class="filter-btn" @click.stop="toggleFilterMenu('status')">⚙</button>
                      </div>

                      <div v-if="activeFilter === 'status'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getStatusOptions()" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedFilters.status.includes(val)"
                            @change="toggleValue('status', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'status')" @dblclick.stop="autoFitFollowupColumn('status')"></div>
                    </th>

                    <!-- توضیحات -->
                    <th class="desc-col resizable" :style="{ width: colWidths.description + 'px' }">
                      <div class="th-content">توضیحات</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'description')" @dblclick.stop="autoFitFollowupColumn('description')"></div>
                    </th>

                    <th class="center resizable" :style="{ width: colWidths.reason + 'px' }">
                      <div class="th-content">علت</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'reason')" @dblclick.stop="autoFitFollowupColumn('reason')"></div>
                    </th>

                    <!-- پیامک لندینگ -->
                    <th class="center resizable" :style="{ width: colWidths.landingSms + 'px' }">
                      <div class="th-content">پیامک لندینگ</div>
                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'landingSms')" @dblclick.stop="autoFitFollowupColumn('landingSms')"></div>
                    </th>

                    <!-- تمایل -->
                    <th
                      class="filterable resizable"
                      :class="{ 'filtered-cell': isFiltered('interest') }"
                      :style="{ width: colWidths.interest + 'px' }"
                    >
                      <div class="th-content">
                        تمایل
                        <button class="filter-btn" @click.stop="toggleFilterMenu('interest')">⚙</button>
                      </div>

                      <div v-if="activeFilter === 'interest'" class="filter-dropdown" @click.stop>
                        <label v-for="val in getUniqueValues('interest')" :key="val" class="filter-option">
                          <input
                            type="checkbox"
                            :checked="selectedFilters.interest.includes(val)"
                            @change="toggleValue('interest', val)"
                          />
                          <span>{{ val }}</span>
                        </label>
                      </div>

                      <div class="resizer" @mousedown.stop.prevent="initResize($event, 'interest')" @dblclick.stop="autoFitFollowupColumn('interest')"></div>
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="row in activeFilteredRows" :key="row._localId">
                    <td>
                      <div class="campaign-patient-cell">
                        <img v-if="patientAvatar(row)" :src="patientAvatar(row)" class="campaign-patient-avatar" alt="" />
                        <span v-else class="campaign-patient-avatar fallback">{{ patientInitial(row) }}</span>
                        <input v-model="row.fullName" />
                      </div>
                    </td>
                    <td>
                      <input
                        v-model="row.phone"
                        @input="lookupPatientByPhone(row)"
                        @blur="fillPatientByPhone(row)"
                      />
                    </td>
                    <td>
  <DatePicker
    v-model="row.contactDate"
    format="YYYY-MM-DD"
    display-format="jYYYY/jMM/jDD"
    input-class="table-date-input"
    placeholder="تاریخ تماس"
  />
</td>
                    <td>
  <DatePicker
    v-model="row.followUpDate"
    format="YYYY-MM-DD"
    display-format="jYYYY/jMM/jDD"
    input-class="table-date-input"
    placeholder="تاریخ"
  />
</td>

                    <td
                      :class="[
                        { 'filtered-cell': isFiltered('gender') },
                        genderClass(row.gender)
                      ]"
                    >
                      <select v-model="row.gender">
                        <option value=""></option>
                        <option value="زن">زن</option>
                        <option value="مرد">مرد</option>
                      </select>
                    </td>

                    <td :class="{ 'filtered-cell': isFiltered('consultant') }">
                      <div class="resource-select-with-avatar">
                        <img v-if="consultantAvatar(row.consultant)" :src="consultantAvatar(row.consultant)" alt="" />
                        <span v-else>{{ consultantInitial(row.consultant) }}</span>
                      <select v-model="row.consultant">
                        <option value=""></option>
                        <option
                          v-for="staff in staffOptions"
                          :key="staff.id"
                          :value="staff.name"
                        >
                          {{ staff.name }}
                        </option>
                      </select>
                      </div>
                    </td>

                    <td
                      :class="[
                        { 'filtered-cell': isFiltered('status') },
                        statusClass(row.status)
                      ]"
                    >
                      <select v-model="row.status">
                        <option value=""></option>
                        <option value="پاسخ داد">پاسخ داد</option>
                        <option value="پاسخ نداد">پاسخ نداد</option>
                        <option value="اشتباه">اشتباه</option>
                        <option value="پیگیری">پیگیری</option>
                      </select>
                    </td>

                    <td class="desc-col">
                      <input v-model="row.description" />
                    </td>

                    <td>
                      <select v-model="row.reason">
                        <option value=""></option><option v-for="reason in reasonOptions" :key="reason" :value="reason">{{ reason }}</option>
                      </select>
                    </td>

                    <td class="landing-multi-cell" @click.stop>
                      <button type="button" class="landing-multi-trigger" :class="{ active: landingSmsValues(row).length }" @click="toggleLandingMenu(row)">
                        <span>{{ landingSmsValues(row).length ? `${landingSmsValues(row).length} لندینگ انتخاب شد` : 'انتخاب لندینگ‌ها' }}</span>
                        <b>⌄</b>
                      </button>
                      <div v-if="activeLandingRowId === row._localId" class="landing-multi-menu">
                        <div class="landing-multi-title">ارسال هم‌زمان لندینگ‌ها</div>
                        <label v-for="item in landingSmsOptions" :key="item" :class="{ selected: landingSmsValues(row).includes(item) }">
                          <input type="checkbox" :checked="landingSmsValues(row).includes(item)" @change="toggleLandingSms(row, item)">
                          <span>{{ item }}</span>
                          <i v-if="landingSmsValues(row).includes(item)">✓</i>
                        </label>
                        <button v-if="landingSmsValues(row).length" type="button" class="landing-clear-btn" @click="row.landingSms = []">پاک کردن انتخاب‌ها</button>
                      </div>
                    </td>

                    <td
                      :class="[
                        { 'filtered-cell': isFiltered('interest') },
                        interestClass(row.interest)
                      ]"
                    >
                      <select v-model="row.interest">
                        <option value=""></option>
                        <option value="1">کم</option><option value="2">متوسط</option>
                        <option value="3">زیاد</option><option value="ok">وقت داده شد (در نوبت‌دهی)</option>
                      </select>
                    </td>
                  </tr>

                  <tr v-if="!activeFilteredRows.length">
                    <td colspan="11" class="empty-state">
                      موردی یافت نشد.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="row-controls" @click.stop>
              <button class="btn plus" style="    text-align: center !important;" @click="addMultipleRowsToActive">+</button>
              <input
                type="number"
                min="1"
                v-model.number="rowCount"
                class="row-input"
              />
              <button class="btn minus" style="    text-align: center !important;" @click="removeRowsFromActive">-</button>
            </div>
          </div>


        </div>

      </div>
    </div>

    <div v-if="bannerGalleryCampaign" class="banner-gallery-page" @click.self="closeBannerGallery">
      <section class="banner-gallery-shell">
        <header>
          <div>
            <small>گالری کمپین</small>
            <h2>{{ bannerGalleryCampaign.title || 'کمپین بدون عنوان' }}</h2>
            <p>{{ campaignBanners(bannerGalleryCampaign).length.toLocaleString('fa-IR') }} بنر ذخیره شده</p>
          </div>
          <div class="banner-gallery-actions">
            <label class="campaign-icon-action banner-upload-action" title="افزودن بنر" aria-label="افزودن بنر">
              <input type="file" accept="image/*" multiple @change="handleGalleryBannerUpload">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </label>
            <button type="button" class="campaign-icon-action" title="بستن گالری" aria-label="بستن گالری" @click="closeBannerGallery">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
          </div>
        </header>

        <div v-if="campaignBanners(bannerGalleryCampaign).length" class="banner-gallery-grid">
          <article v-for="(banner, index) in campaignBanners(bannerGalleryCampaign)" :key="banner.id || `${banner.name}-${index}`">
            <a :href="banner.data" target="_blank" rel="noopener" :title="banner.name || `بنر ${index + 1}`">
              <img :src="banner.data" :alt="banner.name || `بنر ${index + 1}`">
            </a>
            <footer>
              <span>{{ banner.name || `بنر ${index + 1}` }}</span>
              <button type="button" title="حذف بنر" aria-label="حذف بنر" @click="removeCampaignBanner(index)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15M10 11v6M14 11v6"/></svg>
              </button>
            </footer>
          </article>
        </div>
        <div v-else class="banner-gallery-empty">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m5 17 5-5 4 4 2-2 3 3"/></svg>
          <strong>هنوز بنری ثبت نشده است</strong>
          <small>از دکمه افزودن بالای صفحه استفاده کنید.</small>
        </div>
      </section>
    </div>
  </div>
</template>

<script>
import axios from "axios";
import moment from "moment-jalaali";
import DatePicker from 'vue3-persian-datetime-picker'
import draggable from "vuedraggable";
import { avatarInitial, avatarUrl, findResourceByName } from '@/utils/avatar';

function debounce(fn, delay = 800) {
  let timer = null;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

function normalizeStatus(val) {
  const text = String(val || "").trim();
  if (text === "پاسخ داد") return "پاسخ داد";
  if (text === "پاسخ نداد") return "پاسخ نداد";
  if (text === "اشتباه") return "اشتباه";
  if (text === "پیگیری") return "پیگیری";
  return "";
}

function normalizeInterest(val) {
  const text = String(val || "").trim();
  if (["1", "کم", "low"].includes(text)) return "1";
  if (["2", "متوسط", "medium"].includes(text)) return "2";
  if (["3", "زیاد", "high"].includes(text)) return "3";
  if (["ok", "وقت داده شد", "وقت داده‌شد", "نوبت داده شد"].includes(text)) return "ok";
  return "";
}

export default {
  name: "FlwUpCampaigns",

  props: {
    permissions: { type: Array, default: () => [] }
  },

  components: {
  DatePicker,
  draggable
},

  data() {
    return {
      campaigns: [],
      showCampaignModal: false,
      activeCampaignId: null,
      appointmentTimelineDate: "",

      newCampaign: {
        title: "",
        source: "",
        sourceName: "",
        date: "",
        cost: "",
        attachmentName: "",
        attachmentData: "",
        banners: [],
      },
      showArchived: false,
      showMissedFollowups: false,
      patientLookupTimers: {},
      reasonOptions: ["هزینه", "کمبود وقت", "تصمیم‌گیری", "ترس", "مسیر", "تردید", "غیره"],

      showDateReportModal: false,
      reportFilterTab: "campaigns",
      reportDateFrom: "",
      reportDateTo: "",
      reportSearch: "",
      reportCampaignStatuses: [],
      reportCampaignSources: [],
      reportCampaignQualities: [],
      reportCostMin: "",
      reportCostMax: "",
      campaignStatusFilterOptions: [
        { value: "active", label: "فعال" }, { value: "paused", label: "متوقف" },
        { value: "finished", label: "پایان‌یافته" }, { value: "archived", label: "آرشیو شده" },
      ],
      campaignQualityOptions: ["عالی", "خوب", "متوسط", "ضعیف"],
      activeReportFilter: null,
      selectedReportFilters: {
        gender: [],
        consultant: [],
        source: [],
        status: [],
        interest: [],
        reason: [],
      },

      rowCount: 1,
      campaignSearch: "",
      activeFilter: null,
      activeLandingRowId: null,

      staffOptions: [],
      channelOptions: [],
      channelsLoading: false,
      channelsLoadError: "",
      campaignFormError: "",
      bannerGalleryCampaignId: null,
      landingSmsOptions: [
  "پیامک شماره 1",
  "پیامک شماره 2",
  "پیامک شماره 3",
],

      selectedFilters: {
        gender: [],
        consultant: [],
        source: [],
        status: [],
        interest: [],
      },

      colWidths: {
        campaignTitle: 128,
        fullName: 136,
        phone: 104,
        contactDate: 96,
        followUpDate: 96,
        gender: 64,
        consultant: 104,
        source: 92,
        status: 90,
        description: 132,
        interest: 76,
        reason: 82,
        landingSms: 116,
      },

      debouncedSaveLocal: null,
    };
  },

  computed: {
    canViewCampaignCost() { return this.permissions.includes('followups.campaign_cost'); },
    archivedCampaigns() { return this.campaigns.filter(c => this.isCampaignArchived(c)); },
    visibleCampaigns: {
      get() { return this.campaigns.filter(c => this.showArchived ? this.isCampaignArchived(c) : !this.isCampaignArchived(c)); },
      set(value) {
        const hidden = this.campaigns.filter(c => this.showArchived ? !this.isCampaignArchived(c) : this.isCampaignArchived(c));
        this.campaigns = [...value, ...hidden];
      },
    },
    missedFollowups() {
      const today = this.getTodayString();
      return this.campaigns.flatMap(campaign => campaign.rows
        .filter(row => row.followUpDate && row.followUpDate < today && (!row.contactDate || row.contactDate < row.followUpDate))
        .map(row => ({ ...row, campaignId: campaign.id, campaignTitle: campaign.title })))
        .sort((a, b) => String(a.followUpDate).localeCompare(String(b.followUpDate)));
    },
    activeCampaign() {
      return this.campaigns.find(c => c.id === this.activeCampaignId) || null;
    },

    bannerGalleryCampaign() {
      return this.campaigns.find(c => c.id === this.bannerGalleryCampaignId) || null;
    },

    canOpenAppointments() {
      return this.permissions.includes("appointments.view");
    },

    appointmentTimelineDay() {
      if (!this.appointmentTimelineDate) return "";
      const date = new Date(`${this.appointmentTimelineDate}T12:00:00`);
      if (Number.isNaN(date.getTime())) return "";
      return new Intl.DateTimeFormat("fa-IR", { weekday: "long" }).format(date);
    },

    activeFilteredRows() {
      if (!this.activeCampaign) return [];

      const term = this.campaignSearch.trim().toLowerCase();

      return this.activeCampaign.rows.filter((r) => {
        const inSearch =
          !term ||
          [r.fullName, r.phone, r.consultant, r.description]
            .filter(Boolean)
            .some((f) => String(f).toLowerCase().includes(term));

        const keys = ["gender", "consultant", "source", "status", "interest"];

        const colMatch = keys.every((key) => {
          const selected = this.selectedFilters[key];
          if (!selected.length) return true;
          return selected.includes(r[key]);
        });

        return inSearch && colMatch;
      });
    },

    reportAllFilteredRows() {
      let allRows = [];
      
      this.campaigns.forEach(camp => {
        camp.rows.forEach(r => {
          const rowDate = r.contactDate || r.followUpDate || camp.date || "";
          let inDateRange = true;
          if (this.reportDateFrom && rowDate < this.reportDateFrom) inDateRange = false;
          if (this.reportDateTo && rowDate > this.reportDateTo) inDateRange = false;
          if (inDateRange) {
            allRows.push({
              ...r,
              campaignTitle: camp.title
            });
          }
        });
      });
      return allRows;
    },

    reportFilteredRows() {
      const term = this.reportSearch.trim().toLowerCase();
      let rows = this.reportAllFilteredRows;

      return rows.filter((r) => {
        const inSearch =
          !term ||
          [r.fullName, r.phone, r.consultant, r.description, r.campaignTitle]
            .filter(Boolean)
            .some((f) => String(f).toLowerCase().includes(term));

        const keys = ["gender", "consultant", "source", "status", "interest", "reason"];

        const colMatch = keys.every((key) => {
          const selected = this.selectedReportFilters[key];
          if (!selected.length) return true;
          return selected.includes(r[key]);
        });

        return inSearch && colMatch;
      });
    },

    filteredReportCampaigns() {
      const term = this.reportSearch.trim().toLowerCase();
      return this.campaigns.filter(campaign => {
        const searchMatch = !term || [campaign.title, campaign.sourceName, campaign.source]
          .filter(Boolean).some(value => String(value).toLowerCase().includes(term));
        const statusMatch = !this.reportCampaignStatuses.length || this.reportCampaignStatuses.includes(campaign.campaignStatus || 'active');
        const sourceMatch = !this.reportCampaignSources.length || this.reportCampaignSources.includes(campaign.source || campaign.sourceName || '');
        const qualityMatch = !this.reportCampaignQualities.length || this.reportCampaignQualities.includes(this.campaignQualityLabel(campaign));
        const cost = Number(campaign.cost || 0);
        const costMatch = (this.reportCostMin === '' || cost >= Number(this.reportCostMin)) && (this.reportCostMax === '' || cost <= Number(this.reportCostMax));
        const date = campaign.date || '';
        const dateMatch = (!this.reportDateFrom || date >= this.reportDateFrom) && (!this.reportDateTo || date <= this.reportDateTo);
        return searchMatch && statusMatch && sourceMatch && qualityMatch && costMatch && dateMatch;
      });
    },

    reportCampaignSourceOptions() {
      return [...new Set([
        ...this.channelOptions.map(item => item.name),
        ...this.campaigns.map(item => item.source || item.sourceName)
      ].filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b), 'fa'));
    },

    todayFollowups() {
      const today = this.getTodayString();

      return this.campaigns.flatMap((campaign) =>
        campaign.rows
          .filter((r) => r.followUpDate === today)
          .map((r) => ({
            ...r,
            campaignId: campaign.id,
            campaignTitle: campaign.title,
          }))
      );
    },

    activeTodayRows() {
      if (!this.activeCampaign) return [];
      const today = this.getTodayString();
      return this.activeCampaign.rows.filter((r) => r.followUpDate === today);
    },
  },

  created() {
    this.debouncedSaveLocal = debounce(() => {
      this.saveCampaignsToLocal();
    }, 700);
  },

  mounted() {
    this.loadStaff();
    this.loadChannels();
    this.loadCampaignsFromLocal();
    window.addEventListener("beforeunload", this.handleBeforeUnload);
  },

  beforeUnmount() {
    window.removeEventListener("beforeunload", this.handleBeforeUnload);
  },

  watch: {
    campaigns: {
      handler() {
        this.debouncedSaveLocal();
      },
      deep: true,
    },
    showDateReportModal(val) {
      if (val) {
        this.reportDateFrom = "";
        this.reportDateTo = "";
        this.reportSearch = "";
        this.activeReportFilter = null;
        this.selectedReportFilters = {
          gender: [],
          consultant: [],
          source: [],
          status: [],
          interest: [],
          reason: [],
        };
        this.reportFilterTab = "campaigns";
        this.reportCampaignStatuses = [];
        this.reportCampaignSources = [];
        this.reportCampaignQualities = [];
        this.reportCostMin = "";
        this.reportCostMax = "";
      }
    }
  },

  methods: {

    consultantResource(name) { return findResourceByName(this.staffOptions, name); },
    consultantAvatar(name) { return avatarUrl(this.consultantResource(name)); },
    consultantInitial(name) { return avatarInitial(this.consultantResource(name) || { name }); },
    patientAvatar(row) { return avatarUrl(row); },
    patientInitial(row) { return avatarInitial(row); },

    lookupPatientByPhone(row) {
      const key = row._localId;
      clearTimeout(this.patientLookupTimers[key]);
      const phone = String(row.phone || "").replace(/\D/g, "");
      if (phone.length < 10) return;
      this.patientLookupTimers[key] = setTimeout(() => this.fillPatientByPhone(row), 350);
    },

    async filesToCampaignBanners(files) {
      const selectedFiles = Array.from(files || []);
      const invalid = selectedFiles.find(file => !file.type.startsWith("image/") || file.size > 2 * 1024 * 1024);
      if (invalid) {
        this.campaignFormError = "هر بنر باید تصویر و کمتر از ۲ مگابایت باشد.";
        return [];
      }

      return Promise.all(selectedFiles.map(file => new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = () => resolve({
          id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
          name: file.name,
          data: String(reader.result || "")
        });
        reader.readAsDataURL(file);
      })));
    },

    async handleCampaignAttachment(event) {
      const banners = await this.filesToCampaignBanners(event.target.files);
      if (banners.length) {
        this.newCampaign.banners.push(...banners);
        this.newCampaign.attachmentName = this.newCampaign.banners[0]?.name || "";
        this.newCampaign.attachmentData = this.newCampaign.banners[0]?.data || "";
      }
      event.target.value = "";
    },

    campaignBanners(campaign) {
      if (!campaign) return [];
      if (!Array.isArray(campaign.banners)) campaign.banners = [];
      if (!campaign.banners.length && campaign.attachmentData) {
        campaign.banners.push({
          id: `legacy-${campaign.id}`,
          name: campaign.attachmentName || "بنر کمپین",
          data: campaign.attachmentData
        });
      }
      return campaign.banners;
    },

    openBannerGallery(campaign) {
      this.campaignBanners(campaign);
      this.bannerGalleryCampaignId = campaign.id;
    },

    closeBannerGallery() {
      this.bannerGalleryCampaignId = null;
    },

    async handleGalleryBannerUpload(event) {
      const banners = await this.filesToCampaignBanners(event.target.files);
      if (banners.length && this.bannerGalleryCampaign) {
        this.campaignBanners(this.bannerGalleryCampaign).push(...banners);
        this.saveCampaignsToLocal();
      }
      event.target.value = "";
    },

    removeCampaignBanner(index) {
      if (!this.bannerGalleryCampaign) return;
      const banners = this.campaignBanners(this.bannerGalleryCampaign);
      banners.splice(index, 1);
      const first = banners[0] || null;
      this.bannerGalleryCampaign.attachmentName = first?.name || "";
      this.bannerGalleryCampaign.attachmentData = first?.data || "";
      this.saveCampaignsToLocal();
    },

    campaignNumber(campaign) { return this.campaigns.indexOf(campaign) + 1; },
    campaignStatusClass(status) { return `campaign-status-${status || "active"}`; },
    campaignStatusLabel(status) { return ({ active:'فعال', paused:'متوقف', finished:'پایان‌یافته', archived:'آرشیو شده' })[status || 'active'] || status; },
    campaignQualityLabel(campaign) {
      const score = Number(this.calculateCampaignScore(campaign) || 0);
      if (score >= 75) return 'عالی';
      if (score >= 50) return 'خوب';
      if (score >= 25) return 'متوسط';
      return 'ضعیف';
    },
    resetAdvancedReportFilters() {
      this.reportDateFrom = '';
      this.reportDateTo = '';
      this.reportSearch = '';
      this.reportCampaignStatuses = [];
      this.reportCampaignSources = [];
      this.reportCampaignQualities = [];
      this.reportCostMin = '';
      this.reportCostMax = '';
      this.selectedReportFilters = { gender:[], consultant:[], source:[], status:[], interest:[], reason:[] };
    },
    isCampaignArchived(campaign) {
      const status = String(campaign?.campaignStatus || '').trim().toLowerCase();
      return ['archived', 'archive', 'آرشیو', 'آرشیو شده', 'آرشیوشده'].includes(status);
    },
    toggleArchivedView() {
      this.showArchived = !this.showArchived;
      this.showMissedFollowups = false;
      this.$nextTick(() => {
        document.querySelector('.archive-view-banner, .campaign-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    },
    toggleCampaignArchive(campaign) {
      campaign.campaignStatus = this.isCampaignArchived(campaign) ? 'active' : 'archived';
    },
    campaignCpl(campaign) {
      const appointments = this.getAppointmentCount(campaign);
      return appointments ? Math.round((Number(campaign.cost) || 0) / appointments) : "";
    },

    async openCreateCampaignModal() {
      this.campaignFormError = "";
      this.showCampaignModal = true;
      await this.loadChannels();
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

        row.fullName =
          `${patient.first_name || ""} ${patient.last_name || ""}`.trim();

        row.gender =
          patient.gender || "";

        // اگر دوست داشتی تاریخ تماس هم خودکار ست شود
        row.profile_thumbnail_url = patient.profile_thumbnail_url || "";
        row.profile_photo_url = patient.profile_photo_url || "";
        row.avatar_url = patient.avatar_url || "";
        row.avatarUrl = avatarUrl(patient);

      } catch (e) {

        if (e.response?.status !== 404) {
          console.error(e);
        }

      }
    },

    handleBeforeUnload() {
      this.saveCampaignsToLocal();
    },

    getTodayString() {
      const now = new Date();
      const tzOffset = now.getTimezoneOffset() * 60000;
      return new Date(now - tzOffset).toISOString().split("T")[0];
    },

    closeAllFilters() {
      this.activeFilter = null;
      this.activeReportFilter = null;
      this.activeLandingRowId = null;
    },

    toggleFilterMenu(key) {
      this.activeFilter = this.activeFilter === key ? null : key;
    },
    toggleReportFilterMenu(key) {
      this.activeReportFilter = this.activeReportFilter === key ? null : key;
    },

    toggleValue(key, val) {
      const index = this.selectedFilters[key].indexOf(val);
      if (index > -1) {
        this.selectedFilters[key].splice(index, 1);
      } else {
        this.selectedFilters[key].push(val);
      }
    },
    toggleReportValue(key, val) {
      const index = this.selectedReportFilters[key].indexOf(val);
      if (index > -1) {
        this.selectedReportFilters[key].splice(index, 1);
      } else {
        this.selectedReportFilters[key].push(val);
      }
    },

    isFiltered(key) {
      return this.selectedFilters[key] && this.selectedFilters[key].length > 0;
    },
    isReportFiltered(key) {
      return this.selectedReportFilters[key] && this.selectedReportFilters[key].length > 0;
    },

    autoFitFollowupColumn(key) {
      const fixed = { contactDate:108, followUpDate:108, gender:72, interest:88, landingSms:145 };
      if (fixed[key]) {
        this.colWidths[key] = fixed[key];
        return;
      }
      const fieldMap = { campaignTitle:'campaignTitle', fullName:'fullName', phone:'phone', consultant:'consultant', source:'source', status:'status', description:'description', reason:'reason' };
      const field = fieldMap[key];
      if (!field) return;
      const rows = this.activeCampaign ? this.activeCampaign.rows : this.reportFilteredRows;
      const longest = Math.max(String({ campaignTitle:'نام کمپین', fullName:'نام کامل', phone:'شماره تماس', consultant:'مشاور', source:'منبع', status:'وضعیت', description:'توضیحات', reason:'علت' }[key] || '').length, ...(rows || []).map(row => String(row?.[field] || '').length));
      const extra = key === 'fullName' ? 54 : 26;
      const minimum = { campaignTitle:120, fullName:145, phone:110, consultant:110, source:95, status:95, description:135, reason:90 }[key] || 80;
      const maximum = { description:240, fullName:220, campaignTitle:220 }[key] || 170;
      this.colWidths[key] = Math.max(minimum, Math.min(maximum, longest * 7 + extra));
    },
    initResize(event, key) {
      const startX = event.pageX;
      const startWidth = this.colWidths[key];

      const doDrag = (e) => {
        const delta = startX - e.pageX;
        const newWidth = Math.max(50, startWidth + delta);
        this.colWidths[key] = newWidth;
      };

      const stopDrag = () => {
        window.removeEventListener("mousemove", doDrag);
        window.removeEventListener("mouseup", stopDrag);
        document.body.style.cursor = "default";
      };

      window.addEventListener("mousemove", doDrag);
      window.addEventListener("mouseup", stopDrag);
      document.body.style.cursor = "col-resize";
    },

    createCampaign() {
      this.campaignFormError = "";

      const title = String(this.newCampaign.title || "").trim();
      if (!title) {
        this.campaignFormError = "موضوع تبلیغات را وارد کنید.";
        return;
      }

      if (!this.newCampaign.date) {
        this.campaignFormError = "تاریخ کمپین را انتخاب کنید.";
        return;
      }

      const campaignDate = this.normalizeDateValue(this.newCampaign.date);

      this.campaigns.unshift({
        id: Date.now() + Math.floor(Math.random() * 1000),
        title,
        source: this.newCampaign.source,
        sourceName: this.newCampaign.sourceName,
        date: campaignDate,
        cost: this.newCampaign.cost || "",
        attachmentName: this.newCampaign.attachmentName,
        attachmentData: this.newCampaign.attachmentData,
        banners: [...this.newCampaign.banners],
        campaignStatus: "active",
        rows: [],
      });

      this.saveCampaignsToLocal();

      this.newCampaign = {
        title: "",
        source: "",
        sourceName: "",
        date: "",
        cost: "",
        attachmentName: "",
        attachmentData: "",
        banners: [],
      };

      this.campaignFormError = "";
      this.showCampaignModal = false;
    },

    removeCampaign(id) {
      const ok = window.confirm("این تبلیغ حذف شود؟");
      if (!ok) return;

      this.campaigns = this.campaigns.filter((c) => c.id !== id);

      if (this.activeCampaignId === id) {
        this.activeCampaignId = null;
      }
    },

    openCampaign(id) {
      this.activeCampaignId = id;
      const now = new Date();
      this.appointmentTimelineDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
      this.campaignSearch = "";
      this.activeFilter = null;
      this.selectedFilters = {
        gender: [],
        consultant: [],
        source: [],
        status: [],
        interest: [],
      };
    },

    closeCampaignModal() {
      this.activeCampaignId = null;
      this.campaignSearch = "";
      this.activeFilter = null;
    },

    openAppointmentsTimeline() {
      if (!this.appointmentTimelineDate) return;
      const requestedDate = this.appointmentTimelineDate;
      this.closeCampaignModal();
      this.$emit("open-appointments-timeline", { date: requestedDate });
    },
    
    setTodayContactDate(row) {
  if (!row.fullName) return;

  if (!row.contactDate) {
    row.contactDate = this.getTodayString();
  }
},

    createEmptyRow() {
      return {
        _localId: `new-${Date.now()}-${Math.random()}`,
        fullName: "",
        phone: "",
        contactDate: "",
        followUpDate: "",
        gender: "",
        consultant: "",
        description: "",
        source: "",
        status: "",
        landingSms: [],
        interest: "",
        reason: "",
        avatarUrl: "",
      };
    },

    addMultipleRowsToActive() {
      if (!this.activeCampaign) return;
      const count = Math.max(1, Number(this.rowCount) || 1);
      for (let i = 0; i < count; i++) {
        this.activeCampaign.rows.push(this.createEmptyRow());
      }
    },

    removeRowsFromActive() {
      if (!this.activeCampaign) return;
      const count = Math.max(0, Number(this.rowCount) || 0);
      if (!count) return;
      this.activeCampaign.rows.splice(-count);
    },

    getUniqueValues(key) {
      if (!this.activeCampaign) return [];
      const vals = this.activeCampaign.rows
        .map((r) => r[key])
        .filter((v) => v !== "" && v !== null && v !== undefined);
      return [...new Set(vals)].sort();
    },

    getReportUniqueValues(key) {
      const vals = this.reportAllFilteredRows
        .map((r) => r[key])
        .filter((v) => v !== "" && v !== null && v !== undefined);
      return [...new Set(vals)].sort();
    },

    getStatusOptions() {
      return ["پاسخ داد", "پاسخ نداد", "اشتباه", "پیگیری"];
    },

    landingSmsValues(rowOrValue) {
      const value = rowOrValue && typeof rowOrValue === 'object' && !Array.isArray(rowOrValue)
        ? rowOrValue.landingSms
        : rowOrValue;
      if (Array.isArray(value)) return value.filter(Boolean);
      return String(value || '').split('،').map(item => item.trim()).filter(Boolean);
    },

    landingSmsText(value) {
      return this.landingSmsValues(value).join('، ');
    },

    toggleLandingMenu(row) {
      this.activeLandingRowId = this.activeLandingRowId === row._localId ? null : row._localId;
    },

    toggleLandingSms(row, item) {
      const selected = this.landingSmsValues(row);
      row.landingSms = selected.includes(item) ? selected.filter(value => value !== item) : [...selected, item];
    },

    genderClass(val) {
      if (val === "زن") return "gender-female";
      if (val === "مرد") return "gender-male";
      return "";
    },

    statusClass(val) {
      if (val === "پاسخ داد") return "status-ok";
      if (val === "پاسخ نداد") return "status-no";
      if (val === "اشتباه") return "status-wrong";
      if (val === "پیگیری") return "status-follow";
      return "";
    },

    interestClass(val) {
      if (val === "1") return "interest-1";
      if (val === "2") return "interest-2";
      if (val === "3") return "interest-3";
      if (val === "ok") return "interest-ok";
      return "";
    },

    isMeaningfulLead(row) {
      return [
        row?.fullName,
        row?.phone,
        row?.contactDate,
        row?.followUpDate,
        row?.gender,
        row?.consultant,
        row?.description,
        row?.source,
        row?.status,
        row?.interest,
        row?.reason,
      ].some(value => String(value || "").trim());
    },

    campaignLeadRows(campaign) {
      return Array.isArray(campaign?.rows)
        ? campaign.rows.filter(row => this.isMeaningfulLead(row))
        : [];
    },

    getLeadCount(campaign) {
      return this.campaignLeadRows(campaign).length;
    },

    getComputedRows(campaign) {
      return this.campaignLeadRows(campaign).filter((r) => !!r.status || !!r.interest);
    },

    getComputedCount(campaign) {
      return this.getComputedRows(campaign).length;
    },

    getAnsweredCount(campaign) {
      return this.campaignLeadRows(campaign).filter((r) => r.status === "پاسخ داد").length;
    },

    getNoAnswerCount(campaign) {
      return this.campaignLeadRows(campaign).filter((r) => r.status === "پاسخ نداد").length;
    },

    getUncalledCount(campaign) {
      return this.campaignLeadRows(campaign).filter((r) => !r.status).length;
    },

    getInterestCount(campaign, value) {
      return this.campaignLeadRows(campaign).filter((r) => r.interest === value).length;
    },

    getAppointmentCount(campaign) {
      return this.getInterestCount(campaign, "ok");
    },

    calculateCampaignScore(campaign) {
      const validRows = this.getComputedRows(campaign);

      if (!validRows.length) return 0;

      let score = 0;

      validRows.forEach((r) => {
        if (r.status === "پاسخ داد") score += 20;
        if (r.status === "پاسخ نداد") score += 0;
        if (r.status === "پیگیری") score += 10;
        if (r.status === "اشتباه") score -= 5;

        if (r.interest === "1") score += 15;
        if (r.interest === "2") score += 35;
        if (r.interest === "3") score += 60;
        if (r.interest === "ok") score += 80;
      });

      const finalScore = Math.round(score / validRows.length);
      return Math.max(0, Math.min(100, finalScore));
    },

    scoreClass(score) {
      if (score >= 75) return "score-excellent";
      if (score >= 50) return "score-good";
      if (score >= 25) return "score-medium";
      return "score-low";
    },

    hasTodayFollowups(campaign) {
      const today = this.getTodayString();
      return campaign.rows.some((r) => r.followUpDate === today);
    },

    percent(value, total) {
      if (!total) return 0;
      return Math.round((value / total) * 100);
    },

    dashArray(value, total) {
      if (!total || !value) return "0 100";
      const p = (value / total) * 100;
      return `${p} ${100 - p}`;
    },

    segmentOffset(previousValues, total) {
      if (!total) return 25;
      const sum = previousValues.reduce((a, b) => a + b, 0);
      return 25 - ((sum / total) * 100);
    },

    formatMoney(val) {
      if (val === "" || val === null || val === undefined) return "—";
      const num = Number(val);
      if (Number.isNaN(num)) return val;
      return num.toLocaleString("fa-IR") + " تومان";
    },

    normalizeDateValue(value) {
      if (!value) return "";
      if (value instanceof Date) return value.toISOString().slice(0, 10);
      if (typeof value === "object") {
        const candidate = value.format || value.date || value.value || value.display || value._d;
        if (candidate && candidate !== value) return this.normalizeDateValue(candidate);
      }

      return String(value).trim();
    },

    formatDateFa(dateStr) {
      const raw = this.normalizeDateValue(dateStr);
      if (!raw) return "—";

      const normalized = raw.replace(/\//g, "-");
      const parsed = moment(normalized, ["YYYY-MM-DD", "YYYY-M-D"], true);
      if (parsed.isValid()) return parsed.format("jYYYY/jMM/jDD");

      const looseParsed = moment(normalized);
      return looseParsed.isValid() ? looseParsed.format("jYYYY/jMM/jDD") : raw;
    },

    saveCampaignsToLocal() {
      const now = new Date().toISOString();
      this.campaigns.forEach(campaign => {
        ;(Array.isArray(campaign.rows) ? campaign.rows : []).forEach(row => {
          if (row.status === "پاسخ داد" && !normalizeInterest(row.interest)) {
            row.answeredWithoutInterestAt = row.answeredWithoutInterestAt || now;
          } else {
            row.answeredWithoutInterestAt = "";
          }
        });
      });
      localStorage.setItem("campaigns_flwup_v1", JSON.stringify(this.campaigns));
      window.dispatchEvent(new CustomEvent("app:notifications-changed"));
    },

    loadCampaignsFromLocal() {
      try {
        const raw = localStorage.getItem("campaigns_flwup_v1");
        if (!raw) return;

        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return;

        this.campaigns = parsed.map((campaign) => ({
          id: campaign.id || Date.now() + Math.random(),
          title: campaign.title || "",
          source: campaign.source || "",
          sourceName: campaign.sourceName || "",
          date: campaign.date || "",
          cost: campaign.cost || "",
          attachmentName: campaign.attachmentName || "",
          attachmentData: campaign.attachmentData || "",
          banners: Array.isArray(campaign.banners)
            ? campaign.banners
            : (campaign.attachmentData ? [{
                id: `legacy-${campaign.id || Date.now()}`,
                name: campaign.attachmentName || "بنر کمپین",
                data: campaign.attachmentData
              }] : []),
          campaignStatus: ['archived', 'archive', 'آرشیو', 'آرشیو شده', 'آرشیوشده'].includes(String(campaign.campaignStatus || '').trim().toLowerCase()) ? 'archived' : (campaign.campaignStatus || 'active'),
          rows: Array.isArray(campaign.rows)
            ? campaign.rows.map((r, idx) => ({
                _localId: r._localId || `local-${Date.now()}-${idx}`,
                fullName: r.fullName ?? "",
                phone: r.phone ?? "",
                contactDate: r.contactDate ?? "",
                followUpDate: r.followUpDate ?? "",
                gender: r.gender ?? "",
                consultant: r.consultant ?? "",
                description: r.description ?? "",
                source: r.source ?? "",
                status: normalizeStatus(r.status ?? ""),
                landingSms: Array.isArray(r.landingSms)
                  ? r.landingSms.filter(Boolean)
                  : String(r.landingSms || '').split('،').map(item => item.trim()).filter(Boolean),
                interest: normalizeInterest(r.interest ?? ""),
                reason: r.reason ?? "",
                avatarUrl: r.avatarUrl ?? "",
                answeredWithoutInterestAt: r.answeredWithoutInterestAt ?? "",
              }))
            : [],
        }));
      } catch (e) {
        console.error("Local campaigns load error", e);
      }
    },

    async loadStaff() {
      try {
        const res = await axios.get("/api/staff");
        this.staffOptions = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        console.error("Staff load error", e);
      }
    },

    async loadChannels() {
      this.channelsLoading = true;
      this.channelsLoadError = "";

      try {
        const res = await axios.get("/api/channels");
        const channels = Array.isArray(res.data)
          ? res.data
          : (Array.isArray(res.data?.data) ? res.data.data : []);

        const seen = new Set();
        this.channelOptions = channels
          .map((channel, index) => ({
            id: channel?.id ?? `channel-${index}`,
            name: String(channel?.name || channel?.title || channel?.label || "").trim(),
          }))
          .filter((channel) => {
            if (!channel.name || seen.has(channel.name)) return false;
            seen.add(channel.name);
            return true;
          });
      } catch (e) {
        this.channelOptions = [];
        const status = e.response?.status;
        this.channelsLoadError = status === 401
          ? "نشست ورود منقضی شده است؛ دوباره وارد شوید."
          : "بارگذاری منابع انجام نشد.";
        console.error("Channels load error", e);
      } finally {
        this.channelsLoading = false;
      }
    },
  },
};
</script>


<style scoped>
@font-face {
  font-family: "Vazir";
  src: url("assets/fonts/vazir-vazir-medium-FD.woff") format("woff");
  font-weight: 500;
  font-style: normal;
}

* {
  box-sizing: border-box;
}

.flwup-root {
  width: 1600px;
  min-height: 100vh;
  background:
    radial-gradient(circle at top right, rgba(99, 102, 241, 0.08), transparent 22%),
    radial-gradient(circle at top left, rgba(16, 185, 129, 0.08), transparent 18%),
    #f6f8fc;
  direction: rtl;
  font-family: "Vazir", sans-serif;
  color: #111827;
  padding: 28px;
}

.page-shell {
  max-width: 1600px;
  margin: 0 auto;
}

.header {
  margin-bottom: 22px;
}

.title-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.title {
  font-size: 28px;
  font-weight: 800;
  color: #111827;
}

.subtitle {
  margin-top: 6px;
  color: #6b7280;
  font-size: 14px;
}

.brand {
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  color: white;
  padding: 12px 20px;
  border-radius: 18px;
  font-size: 16px;
  font-weight: 700;
  box-shadow: 0 12px 30px rgba(59, 130, 246, 0.18);
}

.top-actions {
  display: flex;
  justify-content: flex-start;
  margin-bottom: 22px;
}

.add-campaign-btn {
  border: none;
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  color: white;
  border-radius: 16px;
  padding: 14px 22px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
  transition: 0.25s ease;
}

.add-campaign-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 28px rgba(37, 99, 235, 0.24);
}

.today-alert-box {
  margin-bottom: 22px;
  background: linear-gradient(180deg, #fff5f5, #fffafb);
  border: 1px solid #fecaca;
  border-radius: 24px;
  padding: 20px;
  box-shadow: 0 10px 30px rgba(239, 68, 68, 0.06);
}

.today-alert-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.today-alert-title {
  font-size: 18px;
  font-weight: 800;
  color: #b91c1c;
}

.today-alert-badge {
  background: #ef4444;
  color: white;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.today-alert-desc {
  color: #7f1d1d;
  font-size: 14px;
  margin-bottom: 14px;
}

.today-followup-list {
  display: grid;
  gap: 10px;
}

.today-followup-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #fee2e2;
  padding: 14px;
  border-radius: 16px;
}

.today-followup-main {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.person-name {
  font-weight: 700;
  color: #111827;
}

.person-meta {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  color: #6b7280;
  font-size: 13px;
}

.quick-open-btn {
  border: none;
  background: #fff;
  color: #dc2626;
  border: 1px solid #fca5a5;
  padding: 10px 14px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 700;
}

.empty-campaigns {
  background: rgba(255, 255, 255, 0.8);
  border: 1px dashed #d1d5db;
  border-radius: 26px;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 46px;
  margin-bottom: 12px;
}

.empty-title {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 8px;
}

.empty-text {
  color: #6b7280;
  font-size: 14px;
}

.campaign-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

.campaign-card {
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid #e9edf5;
  border-radius: 18px;
  padding: 14px;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
  backdrop-filter: blur(10px);
  transition: 0.28s ease;
}

.campaign-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
}

.campaign-card.danger {
  border: 1px solid rgb(255, 0, 0);
  background: linear-gradient(180deg, #ff7171, #ff4141);
}

.campaign-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.campaign-title-row {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
  margin-bottom: 7px;
}

.campaign-title {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: #111827;
  line-height: 1.55;
}

.urgent-pill {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.campaign-meta {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.meta-chip {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  padding: 5px 8px;
  border-radius: 10px;
  display: flex;
  gap: 8px;
  align-items: center;
}

.meta-label {
  color: #6b7280;
  font-size: 12px;
}

.meta-value {
  color: #111827;
  font-weight: 700;
  font-size: 13px;
}

.score-box {
  min-width: 74px;
  border-radius: 14px;
  padding: 8px 7px;
  text-align: center;
  border: 1px solid transparent;
}

.score-label {
  font-size: 9px;
  margin-bottom: 2px;
  color: #6b7280;
}

.score-value {
  font-size: 20px;
  font-weight: 900;
}

.score-from {
  font-size: 11px;
  color: #6b7280;
}

.score-excellent {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #047857;
}

.score-good {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1d4ed8;
}

.score-medium {
  background: #fffbeb;
  border-color: #fde68a;
  color: #b45309;
}

.score-low {
  background: #fff1f2;
  border-color: #fecdd3;
  color: #be123c;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 7px;
  margin-bottom: 12px;
}

.stat-card {
  border-radius: 12px;
  padding: 8px;
  min-height: 58px;
  border: 1px solid transparent;
}

.stat-title {
  font-size: 9px;
  color: #6b7280;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 18px;
  font-weight: 800;
}

.stat-card.answered {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #047857;
}

.stat-card.noanswer {
  background: #fff1f2;
  border-color: #fecdd3;
  color: #be123c;
}

.stat-card.pending {
  background: #f8fafc;
  border-color: #e5e7eb;
  color: #334155;
}

.stat-card.oktime {
  background: #eefdf3;
  border-color: #86efac;
  color: #15803d;
}

.chart-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 10px;
  margin-bottom: 12px;
}

.chart-title {
  font-size: 11px;
  font-weight: 800;
  margin-bottom: 9px;
}

.bar-chart {
  display: grid;
  gap: 8px;
}

.bar-item {
  display: grid;
  grid-template-columns: 42px 1fr 28px;
  align-items: center;
  gap: 6px;
}

.bar-label {
  font-size: 10px;
  color: #374151;
  font-weight: 700;
}

.bar-track {
  width: 100%;
  height: 7px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
}

.bar-fill {
  height: 100%;
  border-radius: 999px;
}

.level-1 {
  background: #fca5a5;
}

.level-2 {
  background: #fcd34d;
}

.level-3 {
  background: #93c5fd;
}

.level-ok {
  background: #4ade80;
}

.level-no {
  background: #cbd5e1;
}

.bar-num {
  text-align: left;
  font-weight: 800;
  color: #111827;
}

.campaign-footer {
  display: flex;
  gap: 10px;
}

.open-btn,
.delete-btn,
.primary-btn,
.secondary-btn,
.close-btn {
  font-family: inherit;
}

.open-btn {
  flex: 1;
  border: none;
  background: linear-gradient(135deg, #111827, #374151);
  color: white;
  border-radius: 16px;
  padding: 14px 16px;
  font-weight: 800;
  cursor: pointer;
}

.delete-btn {
  border: 1px solid #fecaca;
  background: #fff;
  color: #dc2626;
  border-radius: 16px;
  padding: 14px 16px;
  font-weight: 800;
  cursor: pointer;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.modal-overlay.large {
  align-items: stretch;
}

.campaign-modal {
  width: 100%;
  max-width: 620px;
  background: #fff;
  border-radius: 28px;
  padding: 28px;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.18);
}

.modal-title {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
}

.modal-subtitle {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 20px;
  line-height: 1.9;
}

.form-grid {
  display: grid;
  gap: 16px;
}

.field {
  display: grid;
  gap: 8px;
}

.field label {
  font-size: 13px;
  font-weight: 700;
  color: #374151;
}

.field input, .field select {
  border: 1px solid #dbe2ea;
  border-radius: 16px;
  padding: 14px 16px;
  outline: none;
  font-family: inherit;
  background: #fbfdff;
  -webkit-appearance: none; /* برای ظاهر بهتر در مرورگرها */
  -moz-appearance: none;
  appearance: none;
}

.field input:focus, .field select:focus {
  border-color: #60a5fa;
  box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.12);
}

.field-error,
.field-hint {
  color: #64748b;
  font-size: 11px;
}

.field-error {
  color: #b91c1c;
}

.field-error button {
  margin-right: 6px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #2563eb;
  font-family: inherit;
  font-weight: 800;
  cursor: pointer;
}

.campaign-form-error {
  margin-top: 14px;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 12px;
  font-weight: 800;
}

.campaign-patient-cell { display:flex; align-items:center; gap:7px; }
.resource-select-with-avatar { display:flex; align-items:center; gap:6px; }
.resource-select-with-avatar img,.resource-select-with-avatar>span { width:30px; height:30px; flex:0 0 30px; border:2px solid #60a5fa; border-radius:50%; object-fit:cover; }
.resource-select-with-avatar>span { display:grid; place-items:center; background:#eff6ff; color:#1d4ed8; font-weight:900; }
.resource-select-with-avatar select { min-width:0; flex:1; }
.campaign-patient-cell input { min-width:0; flex:1; }
.campaign-patient-avatar { width:34px; height:34px; flex:0 0 34px; border:3px solid #3b82f6; border-radius:50%; object-fit:cover; background:#eff6ff; }
.campaign-patient-avatar.fallback { display:grid; place-items:center; color:#1d4ed8; font-weight:900; }
.campaign-status-select { padding:7px 10px; border:2px solid transparent; border-radius:10px; font-family:inherit; font-weight:900; }
.campaign-status-active { color:#15803d; background:#dcfce7; border-color:#86efac; }
.campaign-status-paused { color:#b45309; background:#fef3c7; border-color:#fcd34d; }
.campaign-status-finished { color:#1d4ed8; background:#dbeafe; border-color:#93c5fd; }
.campaign-status-archived { color:#475569; background:#e2e8f0; border-color:#94a3b8; }
.archive-btn { border:0; border-radius:10px; padding:10px 14px; background:#e2e8f0; color:#334155; font-family:inherit; font-weight:900; cursor:pointer; }
.archive-toggle-btn { background:#64748b !important; }
.missed-toggle-btn { background:#dc2626 !important; }
.missed-followups-panel { margin:14px 0 20px; padding:16px; border:1px solid #fecaca; border-radius:16px; background:#fff7f7; }
.missed-followups-panel h3 { margin:0 0 12px; color:#b91c1c; }
.missed-followup-row { display:grid; grid-template-columns:1fr 140px 1fr 120px auto; gap:10px; align-items:center; padding:10px; border-bottom:1px solid #fee2e2; }
.missed-followup-row button { border:0; border-radius:8px; padding:7px 10px; background:#dc2626; color:#fff; font-family:inherit; cursor:pointer; }
.campaign-attachment-link { padding:5px 9px; border-radius:8px; background:#eef2ff; color:#4338ca; font-weight:800; text-decoration:none; }
.campaign-header-actions{display:flex;align-items:center;gap:8px}.campaign-icon-action{position:relative;width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;padding:0;border:1px solid #dbeafe;border-radius:11px;background:#eff6ff;color:#2563eb;box-sizing:border-box;cursor:pointer;transition:.16s ease}.campaign-icon-action:hover{border-color:#93c5fd;background:#dbeafe;transform:translateY(-1px)}.campaign-icon-action svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.campaign-icon-action>span{position:absolute;top:-7px;left:-7px;min-width:19px;height:19px;display:grid;place-items:center;padding:0 4px;border:2px solid #fff;border-radius:999px;background:#2563eb;color:#fff;font-size:9px;font-weight:900}.banner-upload-action input{display:none}
.banner-gallery-page{position:fixed;inset:0;z-index:1000002;padding:24px;background:rgba(15,23,42,.56);backdrop-filter:blur(7px);overflow:auto;direction:rtl}.banner-gallery-shell{width:min(1180px,96vw);min-height:calc(100vh - 48px);box-sizing:border-box;margin:0 auto;padding:22px;border:1px solid rgba(255,255,255,.75);border-radius:26px;background:#f8fafc;box-shadow:0 30px 90px rgba(15,23,42,.34)}.banner-gallery-shell>header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px;padding:4px 3px 18px;border-bottom:1px solid #e2e8f0}.banner-gallery-shell header small{color:#2563eb;font-size:10px;font-weight:900}.banner-gallery-shell h2{margin:4px 0;color:#0f172a;font-size:23px}.banner-gallery-shell header p{margin:0;color:#64748b;font-size:11px}.banner-gallery-actions{display:flex;gap:8px}.banner-gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.banner-gallery-grid article{overflow:hidden;border:1px solid #dbe3ed;border-radius:18px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.07);transition:.18s}.banner-gallery-grid article:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(15,23,42,.11)}.banner-gallery-grid article>a{height:230px;display:block;background:#e2e8f0}.banner-gallery-grid img{width:100%;height:100%;display:block;object-fit:contain}.banner-gallery-grid footer{display:flex;align-items:center;gap:10px;padding:11px 13px}.banner-gallery-grid footer span{min-width:0;flex:1;overflow:hidden;color:#334155;font-size:11px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.banner-gallery-grid footer button{width:34px;height:34px;display:grid;place-items:center;padding:0;border:1px solid #fecaca;border-radius:9px;background:#fff1f2;color:#dc2626}.banner-gallery-grid footer button:hover{background:#fee2e2}.banner-gallery-grid footer svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.banner-gallery-empty{min-height:420px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;border:1px dashed #bfdbfe;border-radius:20px;background:#fff;color:#64748b}.banner-gallery-empty svg{width:55px;height:55px;fill:none;stroke:#93c5fd;stroke-width:1.4}.banner-gallery-empty strong{color:#334155}.banner-gallery-empty small{font-size:10px}@media(max-width:850px){.banner-gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.banner-gallery-page{padding:8px}.banner-gallery-shell{min-height:calc(100vh - 16px);padding:14px;border-radius:18px}.banner-gallery-grid{grid-template-columns:1fr}.banner-gallery-shell>header{align-items:flex-start}.banner-gallery-grid article>a{height:210px}}


.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 24px;
}

.primary-btn {
  border: none;
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  color: white;
  padding: 12px 18px;
  border-radius: 14px;
  cursor: pointer;
  font-weight: 800;
}

.secondary-btn {
  border: 1px solid #d1d5db;
  background: white;
  color: #374151;
  padding: 12px 18px;
  border-radius: 14px;
  cursor: pointer;
  font-weight: 800;
}

.campaign-table-modal {
  width: 100%;
  height: 100%;
  background: #f8fafc;
  border-radius: 28px;
  padding: 20px;
  overflow: auto;
  border: 1px solid #e5e7eb;
}

.campaign-table-modal.danger {
  border-color: #fca5a5;
  background: linear-gradient(180deg, #fffdfd, #fefafa);
}

.campaign-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 18px;
}

.campaign-modal-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.campaign-modal-title {
  font-size: 24px;
  font-weight: 900;
  color: #111827;
}

.campaign-modal-meta {
  margin-top: 8px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  color: #6b7280;
  font-size: 13px;
}

.close-btn {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  border: none;
  background: #fff;
  color: #111827;
  font-size: 28px;
  line-height: 1;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.modal-content-grid {

  grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.9fr);
  gap: 18px;
}

.table-panel,
.side-panel {
  min-width: 0;
}

.table-panel {
  background: #fff;
  border: 1px solid #e7edf5;
  border-radius: 24px;
  padding: 18px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
}

.side-panel {
  display: grid;
  gap: 16px;
  align-content: start;
}

.side-card {
  background: #fff;
  border: 1px solid #e7edf5;
  border-radius: 22px;
  padding: 18px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
}

.side-card-title {
  font-size: 16px;
  font-weight: 800;
  margin-bottom: 14px;
}

.alert-card {
  border-color: #fecaca;
  background: #fff8f8;
}

.analytics-list {
  display: grid;
  gap: 10px;
}

.analytics-item {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 14px;
  background: #f8fafc;
  border: 1px solid #edf2f7;
}

.analytics-item span {
  color: #475569;
  font-size: 14px;
}

.analytics-item strong {
  color: #111827;
  font-size: 15px;
}

.donut-wrap {
  display: grid;
  justify-items: center;
  gap: 16px;
}

.donut-chart {
  width: 210px;
  height: 210px;
  position: relative;
}

.donut-svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.donut-bg {
  fill: none;
  stroke: #e5e7eb;
  stroke-width: 3.8;
}

.donut-segment {
  fill: none;
  stroke-width: 3.8;
  stroke-linecap: butt;
}

.seg-1 { stroke: #fca5a5; }
.seg-2 { stroke: #fcd34d; }
.seg-3 { stroke: #93c5fd; }
.seg-ok { stroke: #4ade80; }
.seg-no { stroke: #cbd5e1; }

.donut-center {
  position: absolute;
  inset: 0;
  display: grid;
  place-content: center;
  text-align: center;
}

.donut-score {
  font-size: 30px;
  font-weight: 900;
  color: #111827;
}

.donut-score-label {
  font-size: 12px;
  color: #6b7280;
}

.chart-legend {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #374151;
  font-size: 13px;
}

.dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.dot-1 { background: #fca5a5; }
.dot-2 { background: #fcd34d; }
.dot-3 { background: #93c5fd; }
.dot-ok { background: #4ade80; }
.dot-no { background: #cbd5e1; }

.mini-follow-list {
  display: grid;
  gap: 10px;
}

.mini-follow-item {
  padding: 12px;
  border-radius: 14px;
  background: white;
  border: 1px solid #fee2e2;
}

.mini-follow-item small {
  color: #6b7280;
}

.toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 9px;
  justify-content: flex-start;
  margin-bottom: 12px;
}

.toolbar-input {
  width: 360px;
  max-width: 100%;
  border: 1px solid #dbe2ea;
  border-radius: 14px;
  padding: 10px 14px;
  background: #fff;
  outline: none;
  font-family: "Vazir", sans-serif;
  font-size: 14px;
}

.toolbar-input:focus {
  border-color: #60a5fa;
  box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.12);
}

.table-scroll {
  overflow: auto;
  border-radius: 18px;
  border: 1px solid #e5eaf1;
}

.open-timeline-btn {
  height: var(--ui-action-height);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 15px;
  border: 1px solid #2563eb;
  border-radius: var(--ui-action-radius);
  background: #2563eb;
  color: #fff;
  font-family: inherit;
  font-size: var(--ui-action-font-size);
  font-weight: 900;
  box-shadow: var(--ui-action-shadow);
  transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.open-timeline-btn:hover {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.open-timeline-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.timeline-date-field {
  position: relative;
  min-width: 165px;
}

.timeline-date-field :deep(input) {
  width: 165px;
  height: var(--ui-action-height);
  box-sizing: border-box;
  padding: 0 12px 0 58px;
  border: 1px solid #cbd5e1;
  border-radius: var(--ui-action-radius);
  background: #fff;
  color: #334155;
  font-family: inherit;
  font-size: 11px;
  font-weight: 800;
  text-align: center !important;
}

.timeline-date-field > span {
  position: absolute;
  top: 50%;
  left: 8px;
  padding: 3px 7px;
  border-radius: 7px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 9px;
  font-weight: 900;
  pointer-events: none;
  transform: translateY(-50%);
}

.contacts-table {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  overflow: visible;
  background: #fff;
}

.contacts-table th,
.contacts-table td {
  padding: 8px;
  border-bottom: 1px solid #eef2f7;
  position: relative;
  overflow: visible;
  text-overflow: ellipsis;
  white-space: nowrap;
  box-sizing: border-box;
  vertical-align: middle;
}

.contacts-table thead th {
  background: #f8fafc;
  font-weight: 800;
  color: #374151;
  z-index: 2;
  height: 48px;
}

.contacts-table tbody tr:hover td {
  background-color: #f8fbff;
}

.contacts-table .th-content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 0;
}

.contacts-table .vpd-input-group {
  width: 100%;
  min-width: 0;
}

.filtered-cell {
  background: #dbeafe !important;
}

.resizer {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  cursor: col-resize;
  z-index: 10;
}

.resizer:hover {
  background: rgba(59, 130, 246, 0.5);
}

.center {
  text-align: center !important;
}

.contacts-table input,
.contacts-table select {
  width: 100%;
  border: 1px solid transparent;
  text-align: center;
  padding: 8px 6px;
  background: transparent;
  font-family: inherit;
  outline: none;
  border-radius: 8px;
}

.contacts-table input:focus,
.contacts-table select:focus {
  border-color: #93c5fd;
  background: #f8fbff;
}

.gender-female {
  background: #f8dfbd !important;
}

::v-deep(input#vpd-173743) {
    width: 90px !important;
}

.gender-male {
  background: #dbeafe !important;
}

.status-ok {
  background: #5ac68e !important;
}

.status-no {
  background: #fcb0b0 !important;
}

.status-wrong {
  background: #cfcfcf !important;
}

.status-follow {
  background: #d1cbec !important;
}

.interest-1 {
  background: #fee2e2 !important;
}

.interest-2 {
  background: #fef3c7 !important;
}

.interest-3 {
  background: #dbeafe !important;
}

.interest-ok {
  background: #32bb62 !important;
  color: #166534;
  font-weight: 700;
}

.row-controls {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 18px;
}

.btn {
  width: 38px;
  height: 38px;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  color: white;
  font-size: 22px;
  line-height: 1;
}

.plus {
  background: #3b82f6;
}

.minus {
  background: #ef4444;
}

.row-input {
  width: 64px;
  text-align: center;
  border: 1px solid #ddd;
  border-radius: 10px;
  padding: 5px;
  font-family: inherit;
}

.th-content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 100%;
  overflow: hidden;
}

.filter-btn {
  background: none;
  border: none;
  cursor: pointer;
  flex-shrink: 0;
}

.filter-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  background: #ffffff;
  border: 1px solid #ddd;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.1);
  z-index: 9999;
  min-width: 140px;
  max-height: 180px;
  overflow-y: auto;
  border-radius: 10px;
  padding: 6px 0;
}

.filter-option {
  display: flex;
  align-items: center;
  flex-direction: row;
  gap: 5px;
  padding: 6px 10px;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  user-select: none;
}

.filter-option:hover {
  background: #f3f4f6;
}

.filter-option input {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
}

.empty-state {
  text-align: center;
  padding: 24px;
  color: #6b7280;
}

/* ===== Persian Date Picker ===== */

.date-input,
.table-date-input {
  width: 100%;
  height: 42px;
  border: 1px solid #dbe2ea;
  border-radius: 16px;
  padding: 0 14px;
  outline: none;
  font-family: "Vazir", sans-serif;
  font-size: 14px;
  background: #fbfdff;
  transition: .2s;
}

.date-input:focus,
.table-date-input:focus {
  border-color: #60a5fa;
  box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.12);
  background: #fff;
}

/* popup calendar */

.vpd-container {
  font-family: "Vazir", sans-serif !important;
}

.vpd-input-group input {
  font-family: "Vazir", sans-serif !important;
}

.vpd-day-effect:hover,
.vpd-selected {
  background: #4f46e5 !important;
  color: #fff !important;
}

.vpd-actions button {
  font-family: "Vazir", sans-serif !important;
}

@media (max-width: 1200px) {
  .campaign-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .modal-content-grid {
    grid-template-columns: 1fr;
  }

  .side-panel {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  }
}

@media (max-width: 900px) {
  .campaign-grid {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .title-wrap {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 640px) {
  .flwup-root {
    padding: 16px;
  }

  .campaign-modal,
  .campaign-table-modal {
    border-radius: 20px;
    padding: 14px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .campaign-footer {
    flex-direction: column;
  }

  .today-followup-item {
    flex-direction: column;
    align-items: flex-start;
  }
}
.sortable-ghost {
  opacity: 0.4;
}

.sortable-chosen {
  cursor: grabbing;
}

.campaign-card {
  cursor: grab;
}
.report-filter-tabs{display:flex;align-items:center;gap:8px;margin:14px 0 10px;padding:6px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.report-filter-tabs button{height:40px;padding:0 18px;border:0;border-radius:10px;background:transparent;color:#64748b;font-family:inherit;font-weight:900;cursor:pointer}.report-filter-tabs button.active{background:#2563eb;color:#fff;box-shadow:0 7px 18px rgba(37,99,235,.2)}.report-filter-tabs>span{margin-right:auto;padding:5px 10px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:11px;font-weight:900}.report-filter-panel{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:16px;border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(135deg,#f8fbff,#f0fdf4)}.report-filter-group{min-width:0;display:flex;flex-direction:column;gap:8px}.report-filter-group.wide{grid-column:span 2}.report-filter-group>b{color:#334155;font-size:12px}.report-filter-group>input,.report-cost-range input{height:40px;padding:0 11px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-family:inherit;outline:none}.report-filter-group>input:focus,.report-cost-range input:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.13)}.report-filter-chips{display:flex;gap:6px;flex-wrap:wrap}.report-filter-chips label{display:flex;align-items:center;gap:5px;padding:7px 10px;border:1px solid #dbe3ed;border-radius:999px;background:#fff;color:#475569;font-size:10px;font-weight:900;cursor:pointer}.report-filter-chips label.selected{border-color:#60a5fa;background:#dbeafe;color:#1d4ed8}.report-filter-chips input{display:none}.report-cost-range>div{display:grid;grid-template-columns:1fr 1fr;gap:7px}.report-filter-actions{display:flex;justify-content:flex-end;padding:8px 0}.report-filter-actions button{padding:8px 13px;border:1px solid #fecaca;border-radius:9px;background:#fff7f7;color:#b91c1c;font-family:inherit;font-size:11px;font-weight:900;cursor:pointer}.filtered-campaign-results{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;max-height:52vh;overflow:auto;padding:5px}.filtered-campaign-results article{padding:15px;border:1px solid #e2e8f0;border-radius:15px;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.06);cursor:pointer;transition:.18s}.filtered-campaign-results article:hover{transform:translateY(-2px);border-color:#93c5fd;box-shadow:0 12px 25px rgba(15,23,42,.1)}.filtered-campaign-results header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:13px}.filtered-campaign-results header strong{overflow:hidden;color:#0f172a;text-overflow:ellipsis;white-space:nowrap}.filtered-campaign-results header span{padding:4px 7px;border-radius:999px;font-size:9px;font-weight:900}.filtered-campaign-results article>div{display:grid;grid-template-columns:1fr 1fr;gap:8px}.filtered-campaign-results article>div span{display:flex;justify-content:space-between;padding:7px;border-radius:8px;background:#f8fafc;color:#64748b;font-size:10px}.filtered-campaign-results article>div b{color:#1e293b}@media(max-width:900px){.report-filter-panel,.filtered-campaign-results{grid-template-columns:1fr 1fr}}@media(max-width:640px){.report-filter-panel,.filtered-campaign-results{grid-template-columns:1fr}.report-filter-group.wide{grid-column:auto}.report-filter-tabs button{padding:0 10px}.report-filter-tabs>span{display:none}}
.landing-multi-cell{position:relative;overflow:visible!important}.landing-multi-trigger{width:100%;height:36px;display:flex;align-items:center;justify-content:space-between;gap:6px;padding:0 9px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#64748b;font-family:inherit;font-size:10px;font-weight:800;cursor:pointer}.landing-multi-trigger.active{border-color:#60a5fa;background:#eff6ff;color:#1d4ed8}.landing-multi-trigger span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.landing-multi-menu{position:absolute;z-index:500;top:calc(100% + 5px);right:4px;width:230px;padding:8px;border:1px solid #dbeafe;border-radius:13px;background:#fff;box-shadow:0 18px 42px rgba(15,23,42,.2)}.landing-multi-title{padding:5px 7px 9px;border-bottom:1px solid #eef2f7;color:#334155;font-size:11px;font-weight:1000}.landing-multi-menu label{display:flex;align-items:center;gap:8px;padding:9px 7px;border-radius:8px;color:#475569;font-size:11px;font-weight:800;cursor:pointer}.landing-multi-menu label:hover,.landing-multi-menu label.selected{background:#eff6ff;color:#1d4ed8}.landing-multi-menu input{width:16px!important;height:16px!important;accent-color:#2563eb}.landing-multi-menu label span{flex:1}.landing-multi-menu label i{color:#16a34a;font-style:normal;font-weight:1000}.landing-clear-btn{width:100%;margin-top:5px;padding:8px;border:0;border-radius:8px;background:#fee2e2;color:#b91c1c;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer}

/* Compact responsive follow-up header */
.flwup-root{width:100%;max-width:100%;min-width:0;padding:clamp(12px,1.5vw,24px);overflow-x:hidden}
.page-shell{width:100%;max-width:1600px;min-width:0}
.header{margin-bottom:16px;padding:12px 14px;border:1px solid #e5eaf2;border-radius:16px;background:rgba(255,255,255,.82);box-shadow:0 7px 22px rgba(15,23,42,.05);backdrop-filter:blur(8px)}
.title-wrap{min-width:0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.title{flex:0 0 auto;font-size:20px;line-height:1.5;font-weight:1000;color:#172033;white-space:nowrap}
.header .top-actions{min-width:0;display:flex;align-items:center;justify-content:flex-start;gap:6px;flex-wrap:wrap;margin:0}
.followup-action{position:relative;height:36px;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:0 10px;border:1px solid #dbe3ed;border-radius:10px;background:#fff;color:#475569;font-family:inherit;font-size:10px;font-weight:900;line-height:1;white-space:nowrap;cursor:pointer;box-shadow:none;transition:.16s ease}
.followup-action:hover{border-color:#93c5fd;background:#f8fbff;color:#1d4ed8;transform:translateY(-1px)}
.followup-action svg{width:15px;height:15px;flex:0 0 15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.followup-action b{min-width:17px;height:17px;display:grid;place-items:center;padding:0 4px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:8px}
.followup-action.create-action{border-color:#bfdbfe;background:#eff6ff;color:#1d4ed8}
.followup-action.create-action:hover{border-color:#60a5fa;background:#dbeafe}
.followup-action.archive-action.active{border-color:#94a3b8;background:#e2e8f0;color:#334155}
.followup-action.missed-action{color:#b91c1c}
.followup-action.missed-action.active{border-color:#fca5a5;background:#fee2e2;color:#b91c1c}
.followup-action.missed-action b{background:#fee2e2;color:#b91c1c}
@media(min-width:1500px){.header{padding:10px 14px}.title{font-size:19px}.followup-action{height:34px;padding:0 9px}}
@media(max-width:760px){.header{padding:11px}.title-wrap{align-items:flex-start;flex-direction:column}.header .top-actions{width:100%;display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.followup-action{width:100%}}
@media(max-width:420px){.header .top-actions{grid-template-columns:1fr}.title{font-size:18px}}
/* Explicit archived-campaign view */
.followup-action.archive-action.active{border-color:#8b5cf6;background:#7c3aed;color:#fff;box-shadow:0 5px 14px rgba(124,58,237,.2)}
.followup-action.archive-action.active b{background:#fff;color:#6d28d9}
.archive-view-banner{scroll-margin-top:18px;display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:12px;margin:0 0 16px;padding:12px 14px;border:1px solid #c4b5fd;border-radius:15px;background:linear-gradient(135deg,#faf5ff,#f5f3ff);box-shadow:0 8px 22px rgba(109,40,217,.08);direction:rtl}
.archive-view-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#7c3aed;color:#fff}
.archive-view-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.archive-view-banner>div:nth-child(2){min-width:0;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.archive-view-banner small{color:#7c3aed;font-size:9px;font-weight:900}
.archive-view-banner strong{color:#3b0764;font-size:13px}
.archive-view-banner span{padding:4px 8px;border-radius:999px;background:#ede9fe;color:#6d28d9;font-size:9px;font-weight:900}
.archive-view-banner button,.archive-empty-state button{height:34px;padding:0 11px;border:1px solid #c4b5fd;border-radius:9px;background:#fff;color:#6d28d9;font-family:inherit;font-size:9px;font-weight:900;cursor:pointer}
.archive-view-banner button:hover,.archive-empty-state button:hover{border-color:#8b5cf6;background:#ede9fe}
.campaign-card.archived{border-color:#ddd6fe;background:linear-gradient(180deg,#fff,#faf5ff)}
.archive-empty-state{min-height:220px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;border:1px dashed #c4b5fd;border-radius:18px;background:#faf5ff;color:#6d28d9}
.archive-empty-state>span{color:#3b0764;font-size:15px;font-weight:1000}.archive-empty-state small{color:#7c3aed;font-size:10px}
@media(max-width:650px){.archive-view-banner{grid-template-columns:38px 1fr}.archive-view-banner>button{grid-column:1/-1;width:100%}.archive-view-banner>div:nth-child(2){align-items:flex-start;flex-direction:column;gap:3px}}
/* Dense, fitted follow-up data table */
.campaign-table-modal .table-scroll{border-radius:11px;scrollbar-width:thin}
.campaign-table-modal .contacts-table{width:100%;min-width:1018px;border-collapse:separate;border-spacing:0;table-layout:fixed;font-size:10px}
.campaign-table-modal .contacts-table thead th{height:42px;padding:0 4px;border-left:1px solid #edf1f5;border-bottom:1px solid #dbe3ed;background:#f8fafc;color:#475569;font-size:10px;font-weight:1000;line-height:1.35}
.campaign-table-modal .contacts-table tbody tr{height:40px}
.campaign-table-modal .contacts-table tbody td{height:40px;padding:0 3px;border-left:1px solid #edf1f5;border-bottom:1px solid #e5e7eb;background:#fff;line-height:1.2}
.campaign-table-modal .contacts-table th:last-child,.campaign-table-modal .contacts-table td:last-child{border-left:0}
.campaign-table-modal .contacts-table input,.campaign-table-modal .contacts-table select{width:100%;height:36px;min-width:0;margin:0;padding:0 5px;border:1px solid transparent;border-radius:6px;background:transparent;font-size:10px;line-height:34px;text-align:center;text-overflow:ellipsis;white-space:nowrap}
.campaign-table-modal .contacts-table input:focus,.campaign-table-modal .contacts-table select:focus{border-color:#93c5fd;background:#eff6ff;box-shadow:0 0 0 2px rgba(96,165,250,.12)}
.campaign-table-modal .contacts-table .vpd-input-group,.campaign-table-modal .contacts-table .vpd-input-group input,.campaign-table-modal .contacts-table .table-date-input{width:100%!important;min-width:0!important;height:36px!important;margin:0!important;padding:0 3px!important;font-size:9px!important}
.campaign-table-modal .contacts-table .th-content{width:100%;min-width:0;gap:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.campaign-table-modal .filter-btn{width:19px;height:19px;flex:0 0 19px;display:grid;place-items:center;padding:0;color:#64748b;font-size:10px}
.campaign-table-modal .resizer{width:5px}.campaign-table-modal .resizer:hover{background:#60a5fa}
.campaign-patient-cell{height:38px;min-width:0;gap:4px;overflow:visible}
.campaign-patient-cell input{min-width:0;flex:1}
.campaign-patient-avatar{position:relative;z-index:2;width:28px;height:28px;flex:0 0 28px;border:2px solid #60a5fa;box-shadow:0 2px 6px rgba(37,99,235,.12);transition:transform .18s ease,box-shadow .18s ease;transform-origin:right center;cursor:zoom-in}
.campaign-patient-avatar:hover{z-index:500;transform:scale(3);box-shadow:0 10px 28px rgba(15,23,42,.28);cursor:zoom-out}
.contacts-table td:has(.campaign-patient-avatar:hover),.contacts-table tr:has(.campaign-patient-avatar:hover){z-index:450}
.resource-select-with-avatar{height:38px;min-width:0;gap:3px}.resource-select-with-avatar img,.resource-select-with-avatar>span{width:24px;height:24px;flex:0 0 24px;border-width:2px;font-size:9px}.resource-select-with-avatar select{min-width:0}
.campaign-table-modal .landing-multi-trigger{height:34px;padding:0 6px;border-radius:7px;font-size:9px}
.campaign-table-modal .landing-multi-trigger span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media(max-width:900px){.campaign-table-modal .contacts-table{min-width:1018px}}</style>
