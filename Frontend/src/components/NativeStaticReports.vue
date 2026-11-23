<template>
  <div class="nsr" dir="rtl" :style="nsrShellStyle">
  
    <div style="height:8px"></div>
    <main style="max-width:1440px;margin:0 auto;padding:8px 24px 0">
  
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px">
        <h1 class="nsr-title">گزارشات</h1>
        <div class="nsr-tabs">
          <button :style="v.tR" @click="v.onTR">داشبورد</button>
          <button :style="v.tA" @click="$emit('open-builder')">گزارش‌ساز</button>
        </div>
      </div>
  
      <template v-if="v.isR">
      <section data-screen-label="تب گزارش">
  
        <div style="background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px" data-screen-label="نوار تاریخ و ابزارها">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:13px;font-weight:600;color:#334155">از تاریخ</span>
            <date-picker
              :model-value="v.fromV"
              format="jYYYY/jMM/jDD"
              display-format="jYYYY/jMM/jDD"
              auto-submit
              popover="bottom-right"
              append-to="body"
              input-class="report-date-input"
              placeholder="انتخاب تاریخ شروع"
              color="#2563eb"
              @update:model-value="updateReportDate('from', $event)"
            />
            <span style="font-size:13px;font-weight:600;color:#334155">تا تاریخ</span>
            <date-picker
              :model-value="v.toV"
              format="jYYYY/jMM/jDD"
              display-format="jYYYY/jMM/jDD"
              auto-submit
              popover="bottom-right"
              append-to="body"
              input-class="report-date-input"
              placeholder="انتخاب تاریخ پایان"
              color="#2563eb"
              @update:model-value="updateReportDate('to', $event)"
            />
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <div style="position:relative">
              <button @click="v.toggleMng" style="display:flex;align-items:center;gap:7px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 14px;font-size:13px;font-weight:600;color:#334155;cursor:pointer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 5h7M4 12h7M4 19h7M15 5h5M15 12h5M15 19h5"></path></svg>
                مدیریت داشبوردها
              </button>
              <div :style="'display:' + (v.mngDsp) + ';position:absolute;top:44px;left:0;z-index:40;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,0.14);padding:12px;width:250px;max-height:340px;overflow:auto;flex-direction:column;gap:2px'">
                <template v-for="(dl, dlI) in v.dashList" :key="dlI">
                  <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:#334155;padding:6px 8px;border-radius:8px;cursor:pointer" data-hover="1">
                    <input type="checkbox" :checked="dl.ck" @change="dl.on" style="width:15px;height:15px;accent-color:#2563eb;cursor:pointer">
                    {{ dl.t }}
                  </label>
                </template>
              </div>
            </div>
          </div>
        </div>
  
        <div>
  
          <section class="kpi-summary-section" data-screen-label="شاخص‌های کلیدی گزارش">
            <div class="kpi-summary-toolbar">
              <div><strong>شاخص‌های کلیدی</strong><small>{{ v.kpiSubtitle }}</small></div>
              <button type="button" :disabled="v.kpiCalculating" @click="overviewLoadingSource = null; calculateDashboardSummary()" :title="v.kpiReady ? 'محاسبه مجدد شاخص‌ها' : 'محاسبه شاخص‌ها'" aria-label="محاسبه شاخص‌های کلیدی گزارش" class="report-refresh-button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
              </button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px" data-screen-label="باکس‌های کلی">
            <template v-for="(kp, kpI) in v.kpis" :key="kpI">
              <div
                :role="kp.open ? 'button' : undefined"
                :tabindex="kp.open ? 0 : undefined"
                :aria-label="kp.open ? ('جزئیات ' + kp.t) : undefined"
                @click="kp.open && kp.open()"
                @keydown.enter.prevent="kp.open && kp.open()"
                @keydown.space.prevent="kp.open && kp.open()"
                :style="'background:' + (kp.bg) + ';border:1px solid ' + (kp.bd) + ';border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:16px;display:flex;flex-direction:column;gap:8px;justify-content:center;grid-column:' + (kp.sp) + (kp.open ? ';cursor:pointer' : '')"
              >
                <span :style="'font-size:' + (kp.ts) + ';font-weight:600;color:' + (kp.tc)">{{ kp.t }}</span>
                <span v-if="kp.sub" class="kpi-card-subtitle">{{ kp.sub }}</span>
                <span class="rpt-money" :style="'font-size:' + (kp.vs) + ';font-weight:800;color:#0f172a;white-space:nowrap'">{{ kp.v }}</span>
                <span :style="'align-self:flex-start;background:' + (kp.gb) + ';color:' + (kp.gc) + ';border-radius:999px;padding:2px 9px;font-size:11px;font-weight:800'">{{ kp.g }}</span>
              </div>
            </template>
            <div style="background:linear-gradient(135deg,#1d4ed8,#2563eb);border-radius:16px;box-shadow:0 4px 14px rgba(37,99,235,0.35);padding:16px;display:flex;flex-direction:column;gap:8px;color:#ffffff">
              <span style="font-size:12px;font-weight:600;opacity:0.85">تخمین درآمد آینده</span>
              <span class="rpt-money" style="font-size:clamp(14px,1.3vw,18px);font-weight:800;white-space:nowrap">{{ v.estV }}</span>
              <span style="font-size:11px;opacity:0.8">{{ v.estNote }}</span>
            </div>
            </div>
            <div v-if="v.kpiCalculating" class="kpi-summary-loading" aria-live="polite">
              <div class="staff-income-loading-card">
                <div class="staff-income-loading-ring"></div>
                <strong>در حال محاسبه شاخص‌های گزارش</strong>
                <small>{{ v.kpiStage || 'در حال آماده‌سازی اطلاعات…' }}</small>
                <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
              </div>
            </div>
  
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px" data-screen-label="ماه‌ها">
            <template v-for="(mo, moI) in v.monthsV" :key="moI">
              <div :style="mo.st" @click="mo.on">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                  <span style="font-size:13.5px;font-weight:800;color:#0f172a">{{ mo.name }}</span>
                  <span :style="'color:' + (mo.col) + ';font-weight:800;font-size:13px;direction:ltr'">{{ mo.arrow }} {{ mo.g }}</span>
                </div>
                <span class="rpt-money" style="font-size:clamp(12px,1vw,14px);font-weight:800;color:#334155">{{ mo.val }}</span>
                <svg width="100%" height="30" viewBox="0 0 60 30" preserveAspectRatio="none" style="display:block">
                  <polyline :points="mo.pts" fill="none" :stroke="mo.col" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></polyline>
                </svg>
                <span style="font-size:10.5px;color:#94a3b8">رشد نسبت به ماه قبل</span>
              </div>
            </template>
          </div>
          </section>
  
          <div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));grid-auto-flow:dense;gap:16px" data-screen-label="داشبوردها">
  
            <div :draggable="true" @dragstart="v.dh.ctype" @dragover="v.dv.ctype" @drop="v.dp.ctype" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.ctype) + ';order:' + (v.o.ctype)" data-screen-label="دسته‌بندی مشتریان">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc" title="جابجایی">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">دسته‌بندی مشتریان (نقره‌ای / آبی / طلایی / دردسرساز)</span>
                <small v-if="v.ctCompletedAt" style="color:#64748b;font-size:10px;white-space:nowrap">محاسبه: {{ v.ctCompletedAt }}</small>
                <button v-if="v.ctReady" type="button" :disabled="v.ctBusy" @click="calculateCustomerSegments" title="محاسبه مجدد" aria-label="محاسبه مجدد گزارش دسته‌بندی مشتریان" class="report-refresh-button">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <button v-else type="button" :disabled="v.ctCalculating" @click="calculateCustomerSegments" title="محاسبه گزارش" aria-label="محاسبه گزارش دسته‌بندی مشتریان" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.ctype" @change="v.hide.ctype" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.ctCalculating" class="staff-income-loading" aria-live="polite">
                <div class="staff-income-loading-card">
                  <div class="staff-income-loading-ring"></div>
                  <strong>در حال محاسبه دسته‌بندی مشتریان</strong>
                  <small>{{ v.ctStage || 'در حال آماده‌سازی اطلاعات…' }}</small>
                  <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
                </div>
              </div>
              <div v-if="v.ctReady" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="position:relative;width:150px;height:150px;flex-shrink:0">
                  <svg width="150" height="150" viewBox="0 0 120 120">
                    <g transform="rotate(-90 60 60)">
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#eef2f7" stroke-width="12"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#94a3b8" stroke-width="12" :stroke-dasharray="v.cs1.da" :stroke-dashoffset="v.cs1.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#93c5fd" stroke-width="12" :stroke-dasharray="v.cs2.da" :stroke-dashoffset="v.cs2.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#f59e0b" stroke-width="12" :stroke-dasharray="v.cs3.da" :stroke-dashoffset="v.cs3.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#dc2626" stroke-width="12" :stroke-dasharray="v.cs4.da" :stroke-dashoffset="v.cs4.of"></circle>
                    </g>
                  </svg>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                    <span style="font-size:19px;font-weight:800">{{ v.ctTotal }}</span>
                    <span style="font-size:10.5px;color:#94a3b8">مشتری</span>
                  </div>
                </div>
                <div style="flex:1;min-width:200px;display:flex;flex-direction:column;gap:10px">
                  <template v-for="(r, rI) in v.ctLegend" :key="rI">
                    <div style="display:flex;flex-direction:column;gap:4px">
                      <div style="display:flex;align-items:center;gap:8px;font-size:12.5px">
                        <span :style="'width:10px;height:10px;border-radius:3px;background:' + (r.c) + ';flex-shrink:0'"></span>
                        <span style="flex:1;color:#334155;font-weight:600">{{ r.n }}</span>
                        <span style="color:#64748b;font-size:11.5px">درآمد: <b class="rpt-money" style="color:#334155">{{ r.rev }}</b></span>
                        <span style="color:#0f172a;font-weight:800">{{ r.cnt }}</span>
                        <span style="color:#94a3b8;width:40px;text-align:left">{{ r.p }}</span>
                      </div>
                      <div style="height:7px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:' + (r.c) + ';border-radius:999px'"></div></div>
                    </div>
                  </template>
                </div>
              </div>
              <div v-else-if="!v.ctCalculating" class="report-widget-empty">برای ایجاد گزارش، دکمه محاسبه را بزنید</div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.loyal" @dragover="v.dv.loyal" @drop="v.dp.loyal" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.loyal) + ';order:' + (v.o.loyal)" data-screen-label="مشتریان وفادار">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">مشتریان وفادار</span>
                <small v-if="v.loyaltyCompletedAt" class="report-calculated-at">محاسبه: {{ v.loyaltyCompletedAt }}</small>
                <button :style="v.l3st" @click="v.onL3">۳ ماه</button>
                <button :style="v.l6st" @click="v.onL6">۶ ماه</button>
                <button type="button" :disabled="v.loyaltyCalculating" @click="calculateLoyalty" :title="v.loyaltyReady ? 'محاسبه مجدد' : 'محاسبه وفاداری'" aria-label="محاسبه مشتریان وفادار" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.loyal" @change="v.hide.loyal" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;gap:12px">
                <div style="flex:1;text-align:center;background:#f8fafc;border-radius:12px;padding:12px">
                  <div style="font-size:20px;font-weight:800;color:#0f172a">{{ v.loyTot }}</div>
                  <div style="font-size:11.5px;color:#64748b">کل مشتریان دوره</div>
                </div>
                <div style="flex:1;text-align:center;background:#f0fdf4;border-radius:12px;padding:12px">
                  <div style="font-size:20px;font-weight:800;color:#15803d">{{ v.loyRet }}</div>
                  <div style="font-size:11.5px;color:#64748b">برگشتند</div>
                </div>
                <div style="flex:1;text-align:center;background:#fef2f2;border-radius:12px;padding:12px">
                  <div style="font-size:20px;font-weight:800;color:#b91c1c">{{ v.loyLost }}</div>
                  <div style="font-size:11.5px;color:#64748b">ریزش</div>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:#475569;font-weight:600"><span>نرخ بازگشت</span><span style="color:#15803d;font-weight:800">{{ v.loyP }}</span></div>
                <div style="height:14px;background:#fee2e2;border-radius:999px;overflow:hidden;display:flex">
                  <div :style="'height:100%;width:' + (v.loyRetW) + ';background:linear-gradient(90deg,#22c55e,#16a34a)'"></div>
                </div>
                <div style="display:flex;gap:16px;font-size:11px;color:#64748b">
                  <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#22c55e;display:inline-block"></i>بازگشت</span>
                  <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#fee2e2;display:inline-block"></i>ریزش</span>
                </div>
              </div>
              <div v-if="v.loyaltyCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه وفاداری مشتریان</strong><small>{{ v.loyaltyStage || 'در حال بررسی انجام‌کارها…' }}</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.cancel" @dragover="v.dv.cancel" @drop="v.dp.cancel" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.cancel) + ';order:' + (v.o.cancel)" data-screen-label="نرخ کنسلی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">نرخ کنسلی</span>
                <small v-if="v.cnCompletedAt" class="report-calculated-at">محاسبه: {{ v.cnCompletedAt }}</small>
                <button type="button" :disabled="v.cnLoading" @click="calculateCancellationRate" :title="v.cnReady ? 'محاسبه مجدد' : 'محاسبه نرخ کنسلی'" aria-label="محاسبه نرخ کنسلی" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.cancel" @change="v.hide.cancel" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="position:relative;width:150px;height:150px;flex-shrink:0">
                  <svg width="150" height="150" viewBox="0 0 120 120">
                    <g transform="rotate(-90 60 60)">
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#f0fdf4" stroke-width="12"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#dc2626" stroke-width="12" stroke-linecap="round" :stroke-dasharray="v.cnDa"></circle>
                    </g>
                  </svg>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                    <span style="font-size:22px;font-weight:800;color:#dc2626">{{ v.cnRate }}</span>
                    <span style="font-size:10.5px;color:#94a3b8">نرخ کنسلی</span>
                  </div>
                </div>
                <div style="flex:1;min-width:180px;display:flex;flex-direction:column;gap:10px">
                  <div style="display:flex;align-items:center;justify-content:space-between;background:#f0fdf4;border-radius:12px;padding:12px 16px">
                    <span style="font-size:12.5px;color:#166534;font-weight:600">آمدند</span>
                    <span style="font-size:18px;font-weight:800;color:#15803d">{{ v.cnCame }}</span>
                  </div>
                  <div style="display:flex;align-items:center;justify-content:space-between;background:#fef2f2;border-radius:12px;padding:12px 16px">
                    <span style="font-size:12.5px;color:#991b1b;font-weight:600">کنسل کردند</span>
                    <span style="font-size:18px;font-weight:800;color:#b91c1c">{{ v.cnCanc }}</span>
                  </div>
                </div>
              </div>
              <transition name="cancel-loading">
                <div v-if="v.cnLoading" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبهٔ نرخ کنسلی</strong><small>نوبت‌های این بازه بررسی می‌شوند</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
              </transition>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.bills" @dragover="v.dv.bills" @drop="v.dp.bills" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;overflow:hidden;grid-column:span 2;display:' + (v.dsp.bills) + ';order:' + (v.o.bills)" data-screen-label="سایر هزینه‌های کلینیک">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <div style="flex:1;min-width:140px;display:flex;flex-direction:column;gap:2px">
                  <span style="font-weight:800;font-size:15px;color:#0f172a">سایر هزینه‌های کلینیک</span>
                  <small style="font-size:10.5px;color:#64748b;line-height:1.45">هزینه‌های ثبت‌شده در ماژول هزینه‌ها، به‌جز هزینه‌های دارای شاخص مستقل</small>
                </div>
                <small v-if="v.billCompletedAt" class="report-calculated-at">محاسبه: {{ v.billCompletedAt }}</small>
                <button type="button" class="report-open-module-button" title="باز کردن مدیریت فاکتورها" aria-label="باز کردن مدیریت فاکتورها" @click="$emit('open-bills')">مدیریت فاکتورها <span aria-hidden="true">←</span></button>
                <button type="button" :disabled="s.expenseLoading" @click="calculateExpenses" :title="v.billReady ? 'محاسبه مجدد سایر هزینه‌ها' : 'محاسبه سایر هزینه‌ها'" aria-label="محاسبه سایر هزینه‌های کلینیک" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.bills" @change="v.hide.bills" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div class="report-card-scroll report-list-stack">
                <p v-if="s.expenseError" role="alert" style="font-size:12px;color:#b91c1c;overflow-wrap:anywhere">{{ s.expenseError }}</p>
                <p v-else-if="v.billReady && !v.billRows.length" style="font-size:12px;color:#64748b">هزینه‌ای در این بازه ثبت نشده است</p>
                <article v-for="b in v.billRows" :key="b.id" class="expense-invoice" :class="{ 'is-open': b.open }">
                  <button type="button" class="expense-invoice__primary" @click="toggleBillRow(b.id)" :aria-expanded="b.open ? 'true' : 'false'">
                    <span class="expense-invoice__name" :title="b.n">{{ b.n }}</span>
                    <span class="expense-money">{{ b.v }}</span>
                    <span class="expense-invoice__no" :title="b.categoryLabel">{{ b.categoryLabel }}</span>
                    <span class="expense-invoice__chevron" aria-hidden="true">{{ b.open ? '▾' : '◂' }}</span>
                  </button>
                  <div v-if="b.open" class="expense-invoice__details">
                    <span><em>تاریخ</em>{{ b.date }}</span>
                    <span><em>دسته‌بندی</em>{{ b.category }}</span>
                    <span><em>طرف حساب</em>{{ b.party }}</span>
                    <span><em>وضعیت پرداخت</em>{{ b.status }}</span>
                    <span v-if="b.note"><em>توضیحات</em>{{ b.note }}</span>
                  </div>
                </article>
              </div>
              <div class="expense-summary-list">
                <div class="expense-summary-row" style="background:#fef2f2">
                  <span style="font-size:11.5px;color:#b91c1c;font-weight:600">جمع سایر هزینه‌ها</span>
                  <span class="expense-summary-value" style="color:#991b1b">{{ v.billSumV }}</span>
                </div>
              </div>
              <div v-if="s.expenseLoading" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه سایر هزینه‌ها</strong><small>فاکتورهای ماژول هزینه‌ها، بدون شاخص‌های مستقل، بررسی می‌شوند</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.staffinc" @dragover="v.dv.staffinc" @drop="v.dp.staffinc" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.staffinc) + ';order:' + (v.o.staffinc)" data-screen-label="درآمد پرسنل">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">درآمد پرسنل و پله‌های تارگت</span>
                <small v-if="v.staffIncomeCompletedAt" class="report-calculated-at">محاسبه: {{ v.staffIncomeCompletedAt }}</small>
                <button type="button" :disabled="v.staffIncomeCalculating" @click="calculateStaffIncome" :title="v.staffIncomeReady ? 'محاسبه مجدد' : 'محاسبه درآمد پرسنل'" aria-label="محاسبه درآمد پرسنل و پله‌های تارگت" class="report-refresh-button">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.staffinc" @change="v.hide.staffinc" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <template v-for="(op, opI) in v.staffChips" :key="opI">
                  <button :style="op.st" @click="op.on">{{ op.t }}</button>
                </template>
              </div>
              <div v-if="v.staffIncomeReady && v.staffBars.length" style="position:relative;height:175px;display:flex;align-items:flex-end;gap:14px;padding:0 6px">
                <template v-for="(line, lineI) in v.staffTierLines" :key="'tier-'+lineI">
                  <div :style="'position:absolute;right:0;left:0;bottom:' + (line.bottom) + ';border-top:2px dashed #f59e0b;z-index:1;pointer-events:none'">
                    <span style="position:absolute;left:0;top:-20px;background:#fffbeb;color:#b45309;font-size:10px;font-weight:800;border-radius:6px;padding:2px 8px;white-space:nowrap">{{ line.label }}</span>
                  </div>
                </template>
                <template v-for="(b, bI) in v.staffBars" :key="b.id || bI">
                  <div style="flex:1;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px;height:100%;z-index:2">
                    <template v-for="(m, mI) in b.tierMarkers" :key="'m-'+mI">
                      <div :style="'position:absolute;left:8%;right:8%;bottom:' + (m.bottom) + ';border-top:2px dashed #f59e0b;z-index:3;pointer-events:none'">
                        <span style="position:absolute;left:50%;transform:translateX(-50%);top:-17px;background:#fffbeb;color:#b45309;font-size:8px;font-weight:800;border-radius:5px;padding:1px 5px;white-space:nowrap;max-width:110px;overflow:hidden;text-overflow:ellipsis">{{ m.label }}</span>
                      </div>
                    </template>
                    <span class="rpt-money" :style="'font-size:9.5px;font-weight:800;color:' + (b.vc)">{{ b.v }}</span>
                    <span v-if="b.reached" style="padding:2px 7px;border-radius:999px;background:#dcfce7;color:#15803d;font-size:9px;font-weight:900">{{ b.reachedLabel }}</span>
                    <img v-if="b.ph && !brokenAvatars[b.avatarKey]" :src="b.ph" @error="markAvatarBroken(b.avatarKey)" :style="'width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid ' + (b.c)" alt="">
                    <span v-else :style="'width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:#e2e8f0;color:#475569;font-size:10px;font-weight:900;border:2px solid ' + (b.c)">{{ b.initial }}</span>
                    <div :style="'width:30px;height:' + (b.h) + ';background:' + (b.c) + ';border-radius:7px 7px 0 0'"></div>
                    <span style="font-size:10.5px;color:#64748b;height:20px;text-align:center;white-space:nowrap">{{ b.n }}</span>
                  </div>
                </template>
              </div>
              <div v-else-if="!v.staffIncomeCalculating" class="report-widget-empty">{{ v.staffIncomeReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              <div v-if="v.staffIncomeReady && v.staffBars.length" style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border-radius:12px;padding:10px 16px;flex-wrap:wrap;gap:8px">
                <span style="font-size:12.5px;color:#475569;font-weight:600">جمع فروش: <b class="rpt-money" style="color:#0f172a;font-size:13.5px">{{ v.staffSum }}</b></span>
                <span style="font-size:12px;color:#b45309;font-weight:700">{{ v.staffOverTxt }}</span>
              </div>
              <div v-if="v.staffIncomeCalculating" class="staff-income-loading" aria-live="polite">
                <div class="staff-income-loading-card">
                  <div class="staff-income-loading-ring"></div>
                  <strong>در حال محاسبه درآمد پرسنل</strong>
                  <small>{{ v.staffIncomeStage || 'در حال آماده‌سازی اطلاعات…' }}</small>
                  <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.qc" @dragover="v.dv.qc" @drop="v.dp.qc" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 4;display:' + (v.dsp.qc) + ';order:' + (v.o.qc)" data-screen-label="رضایتمندی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">گزارش رضایت‌مندی <small v-if="v.qcCompletedAt" class="report-calculated-at">{{ v.qcCompletedAt }}</small></span>
                <button type="button" :disabled="v.qcLoading" @click="calculateSatisfaction" :title="v.qcReady ? 'رفرش گزارش رضایت‌مندی' : 'ایجاد گزارش رضایت‌مندی'" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.qc" @change="v.hide.qc" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="!v.qcLoading" class="satisfaction-report-content">
                <div v-if="v.qcReady" class="satisfaction-report-summary">
                  <div><strong>{{ v.qcPct }}</strong><span>رضایت کلی</span></div>
                  <div><strong>{{ v.qcResponses }}</strong><span>شرکت‌کننده</span></div>
                </div>
                <div v-if="v.qcReady && v.qcQuestions.length" class="satisfaction-report-questions">
                  <div v-for="question in v.qcQuestions" :key="question.key" class="satisfaction-report-row" :title="question.title">
                    <span>{{ question.title }}</span>
                    <b>{{ question.percentage }} رضایت</b>
                  </div>
                </div>
                <div v-else-if="v.qcReady" class="satisfaction-report-empty">سؤال فعالی در فرم رضایت‌مندی تعریف نشده است.</div>
                <div v-else class="satisfaction-report-empty">برای ایجاد گزارش، دکمهٔ محاسبه را بزنید.</div>
              </div>
              <div v-if="v.qcLoading" class="staff-income-loading" aria-live="polite">
                <div class="staff-income-loading-card">
                  <div class="staff-income-loading-ring"></div>
                  <strong>در حال آماده‌سازی گزارش رضایت‌مندی</strong>
                  <small>پاسخ‌های چندگزینه‌ای در حال محاسبه هستند…</small>
                  <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.adch" @dragover="v.dv.adch" @drop="v.dp.adch" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 4;display:' + (v.dsp.adch) + ';order:' + (v.o.adch)" data-screen-label="آمار تبلیغات">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آمار کانال‌های تبلیغاتی <span style="font-size:11.5px;color:#94a3b8;font-weight:500">(درآمد بر اساس انجام کار)</span></span>
                <small v-if="v.adChannelsCompletedAt" class="report-calculated-at">محاسبه: {{ v.adChannelsCompletedAt }}</small>
                <button type="button" :disabled="v.adChannelsCalculating" @click="calculateAdvertisingChannels" :title="v.adChannelsReady ? 'محاسبه مجدد آمار کانال‌ها' : 'ایجاد آمار کانال‌ها'" aria-label="ایجاد آمار کانال‌های تبلیغاتی" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.adch" @change="v.hide.adch" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div class="report-card-scroll report-card-scroll-wide"><div style="min-width:720px;display:flex;flex-direction:column;gap:10px">
              <div style="display:grid;grid-template-columns:140px 85px 115px 115px 80px 1fr;gap:10px;align-items:center;font-size:11.5px;color:#94a3b8;font-weight:700;padding:0 4px">
                <span>کانال</span><span>مراجعین</span><span>هزینه</span><span>درآمد</span><span>بازگشت</span><span></span>
              </div>
              <template v-for="(r, rI) in v.adRows" :key="rI">
                <div style="display:grid;grid-template-columns:140px 85px 115px 115px 80px 1fr;gap:10px;align-items:center;font-size:12.5px;background:#f8fafc;border-radius:12px;padding:12px 4px">
                  <span style="font-weight:800;color:#0f172a;padding-right:8px;display:flex;align-items:center;gap:8px"><img :src="r.lg" style="width:22px;height:22px;object-fit:contain;flex-shrink:0" alt="">{{ r.n }}</span>
                  <span style="color:#334155;font-weight:600">{{ r.cnt }}</span>
                  <span class="rpt-money" style="color:#b91c1c;font-weight:800">{{ r.cost }}</span>
                  <span class="rpt-money" style="color:#15803d;font-weight:800">{{ r.rev }}</span>
                  <span :style="'font-weight:900;color:' + r.rc">{{ r.roi }}</span>
                  <div style="height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-left:8px"><div :style="'height:100%;width:' + (r.w) + ';background:#2563eb;border-radius:999px'"></div></div>
                </div>
              </template>
              <div v-if="!v.adChannelsCalculating && !v.adRows.length" class="report-widget-empty">{{ v.adChannelsReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              </div></div>
              <div v-if="v.adChannelsCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه آمار کانال‌های تبلیغاتی</strong><small>هزینهٔ کمپین‌ها و درآمد هر کانال بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.docs" @dragover="v.dv.docs" @drop="v.dp.docs" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.docs) + ';order:' + (v.o.docs)" data-screen-label="پزشکان">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">پزشکان — آورده، پرداختی و تبدیل مشاوره به انجام کار</span>
                <small v-if="v.doctorPerformanceCompletedAt" class="report-calculated-at">محاسبه: {{ v.doctorPerformanceCompletedAt }}</small>
                <button type="button" :disabled="v.doctorPerformanceCalculating" @click="calculateDoctorPerformance" :title="v.doctorPerformanceReady ? 'محاسبه مجدد عملکرد پزشکان' : 'ایجاد گزارش عملکرد پزشکان'" aria-label="ایجاد گزارش عملکرد پزشکان" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.docs" @change="v.hide.docs" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div class="report-card-scroll report-card-scroll-wide"><div style="min-width:800px;display:flex;flex-direction:column;gap:10px">
              <div style="display:grid;grid-template-columns:130px 90px 80px 80px 90px 70px 70px 1fr;gap:10px;align-items:center;font-size:11.5px;color:#94a3b8;font-weight:700;padding:0 4px">
                <span>پزشک</span><span>آورده</span><span>پورسانت</span><span>حقوق ثابت</span><span>جمع پرداختی</span><span>مشاوره</span><span>انجام کار</span><span>نرخ تبدیل</span>
              </div>
              <template v-for="(r, rI) in v.docRows" :key="r.id || rI">
                <div style="display:grid;grid-template-columns:130px 90px 80px 80px 90px 70px 70px 1fr;gap:10px;align-items:center;font-size:12.5px;background:#f8fafc;border-radius:10px;padding:10px 4px">
                  <span style="font-weight:700;color:#0f172a;padding-right:8px;display:flex;align-items:center;gap:7px"><img v-if="r.ph && !brokenAvatars[r.avatarKey]" :src="r.ph" @error="markAvatarBroken(r.avatarKey)" style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e2e8f0" alt=""><span v-else class="report-avatar-initial" style="width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:#e2e8f0;color:#475569;font-size:12px;font-weight:900;flex-shrink:0;border:2px solid #e2e8f0">{{ r.initial }}</span>{{ r.n }}<span :style="'display:' + (r.bd) + ';background:#dcfce7;color:#15803d;border-radius:999px;padding:1px 8px;font-size:10px;font-weight:800'">برترین</span></span>
                  <span class="rpt-money" style="color:#0f172a;font-weight:700">{{ r.bring }}</span>
                  <span class="rpt-money" style="color:#334155">{{ r.pors }}</span>
                  <span class="rpt-money" style="color:#334155">{{ r.fix }}</span>
                  <span class="rpt-money" style="color:#b91c1c;font-weight:700">{{ r.pay }}</span>
                  <span style="color:#334155">{{ r.cons }}</span>
                  <span style="color:#334155">{{ r.done }}</span>
                  <div style="display:flex;align-items:center;gap:8px;margin-left:8px">
                    <div style="flex:1;height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.convW) + ';background:' + (r.cc) + ';border-radius:999px'"></div></div>
                    <span :style="'font-weight:800;color:' + (r.cc) + ';width:38px;text-align:left'">{{ r.convP }}</span>
                  </div>
                </div>
              </template>
              <div v-if="!v.doctorPerformanceCalculating && !v.docRows.length" class="report-widget-empty">{{ v.doctorPerformanceReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              </div></div>
              <div v-if="v.doctorPerformanceCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه عملکرد پزشکان</strong><small>آورده، پرداختی و نرخ تبدیل پزشکان بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.photo" @dragover="v.dv.photo" @drop="v.dp.photo" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.photo) + ';order:' + (v.o.photo)" data-screen-label="آنالیز عکس">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آنالیز عکس‌ها</span>
                <small v-if="v.photoCompletedAt" class="report-calculated-at">محاسبه: {{ v.photoCompletedAt }}</small>
                <button type="button" :disabled="v.photoCalculating" @click="calculatePhotoAnalysis" :title="v.photoReady ? 'محاسبه مجدد آنالیز عکس‌ها' : 'ایجاد آنالیز عکس‌ها'" aria-label="ایجاد آنالیز عکس‌ها" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <span style="font-size:11.5px;color:#94a3b8">{{ v.phTotal }}</span>
                <input type="checkbox" :checked="v.ck.photo" @change="v.hide.photo" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.phRows.length" class="report-card-scroll report-list-stack">
                <template v-for="(r, rI) in v.phRows" :key="rI">
                  <div style="display:flex;flex-direction:column;gap:5px">
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px">
                      <span style="font-weight:700;color:#0f172a">{{ r.n }}</span>
                      <span style="color:#64748b">{{ r.txt }} <b :style="'color:' + (r.c)">{{ r.p }}</b></span>
                    </div>
                    <div style="height:9px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:' + (r.c) + ';border-radius:999px'"></div></div>
                  </div>
                </template>
              </div>
              <div v-if="!v.photoCalculating && !v.phRows.length" class="report-widget-empty">{{ v.photoReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              <div v-if="v.photoCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه آنالیز عکس‌ها</strong><small>عکس‌ها، نواحی و کیفیت تصاویر بررسی می‌شوند</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.city" @dragover="v.dv.city" @drop="v.dp.city" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.city) + ';order:' + (v.o.city)" data-screen-label="آمار شهرها">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آمار بر اساس شهر</span>
                <small v-if="v.cityCompletedAt" class="report-calculated-at">محاسبه: {{ v.cityCompletedAt }}</small>
                <button type="button" :disabled="v.cityCalculating" @click="calculateCityStatistics" class="report-refresh-button" title="ایجاد یا محاسبه مجدد آمار شهرها" aria-label="ایجاد یا محاسبه مجدد آمار شهرها"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.city" @change="v.hide.city" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.cityCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه آمار شهرها</strong><small>نوبت‌های انجام‌شده و پرداختی‌های این بازه بررسی می‌شوند</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
              <div v-else-if="!v.cityReady || !v.cityRows.length" class="report-widget-empty">{{ v.cityReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              <div v-if="v.cityReady && v.cityRows.length" style="display:flex;gap:28px;flex-wrap:wrap;align-items:flex-start">
                <div style="position:relative;width:340px;max-width:100%;aspect-ratio:400/340;flex-shrink:0">
                  <svg viewBox="0 0 400 340" style="width:100%;height:100%;display:block">
                    <path d="M16,7 L100,36 C112,58 120,70 133,77 L160,75 L203,73 C214,62 220,52 228,48 L273,43 L314,55 L353,77 L351,129 L338,157 L365,204 L365,259 L396,293 L367,336 L340,334 L273,325 L254,297 L215,306 L176,286 L139,252 L113,222 L92,229 L76,204 L43,159 L29,138 L6,66 L8,14 Z" fill="#eff6ff" stroke="#bfdbfe" stroke-width="2.5" stroke-linejoin="round"></path>
                  </svg>
                  <template v-for="(d, dI) in v.cityDots" :key="dI">
                    <div :style="'position:absolute;right:' + (d.x) + ';top:' + (d.y) + ';transform:translate(50%,-50%);display:flex;flex-direction:column;align-items:center;gap:0px'">
                      <span :style="'width:' + (d.r) + ';height:' + (d.r) + ';border-radius:50%;background:' + (d.bg) + ';border:2px solid ' + (d.bc) + ';display:block'"></span>
                      <span :style="'font-size:9px;color:' + (d.tc) + ';font-weight:700;white-space:nowrap'">{{ d.n }}</span>
                    </div>
                  </template>
                  <div style="position:absolute;bottom:0;right:0;display:flex;flex-direction:column;gap:4px;font-size:10px;color:#64748b;background:rgba(255,255,255,0.85);border-radius:8px;padding:6px 9px">
                    <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:50%;background:rgba(37,99,235,0.45);border:2px solid #2563eb;display:inline-block"></i>دارای مراجع</span>
                    <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:50%;background:#ffffff;border:2px solid #cbd5e1;display:inline-block"></i>بدون مراجع</span>
                  </div>
                </div>
                <div class="report-card-scroll city-statistics-scroll" style="flex:1;min-width:320px;display:grid;grid-template-columns:1fr 1fr;gap:7px 24px;align-content:start">
                  <template v-for="(r, rI) in v.cityRows" :key="rI">
                    <div style="display:flex;align-items:center;gap:8px;font-size:12px">
                      <span style="width:62px;color:#334155;font-weight:700">{{ r.n }}</span>
                      <div style="flex:1;height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:#2563eb;border-radius:999px'"></div></div>
                      <span style="width:44px;color:#0f172a;font-weight:800;text-align:left">{{ r.cnt }}</span>
                      <span class="rpt-money" style="min-width:54px;color:#64748b;text-align:left;font-size:11px">{{ r.rev }}</span>
                    </div>
                  </template>
                  <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;font-size:10.5px;color:#94a3b8"><span>تعداد</span><span>درآمد</span></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.age" @dragover="v.dv.age" @drop="v.dp.age" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.age) + ';order:' + (v.o.age)" data-screen-label="آمار سنی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آمار سنی</span>
                <span style="background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:11.5px;font-weight:800">میانگین سن: {{ v.avgAge }}</span>
                <small v-if="v.ageCompletedAt" class="report-calculated-at">محاسبه: {{ v.ageCompletedAt }}</small>
                <button type="button" :disabled="v.ageCalculating" @click="calculateAgeStatistics" :title="v.ageReady ? 'محاسبه مجدد آمار سنی' : 'ایجاد آمار سنی'" aria-label="ایجاد یا محاسبه مجدد آمار سنی" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.age" @change="v.hide.age" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.ageCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه آمار سنی</strong><small>اطلاعات مراجعین و پرداختی‌های این بازه بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
              <div v-else-if="!v.ageReady || !v.ageRows.length" class="report-widget-empty">{{ v.ageReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              <div v-if="v.ageReady && v.ageRows.length" style="display:grid;grid-template-columns:70px 1fr 55px 70px 90px;gap:10px;align-items:center;font-size:11px;color:#94a3b8;font-weight:700">
                <span>بازه سنی</span><span></span><span>تعداد</span><span>پرداختی</span><span>خدمت پرتکرار</span>
              </div>
              <div v-if="v.ageReady && v.ageRows.length" class="report-card-scroll report-list-stack">
                <template v-for="(r, rI) in v.ageRows" :key="rI">
                  <div style="display:grid;grid-template-columns:70px 1fr 55px 70px 90px;gap:10px;align-items:center;font-size:12px">
                    <span style="font-weight:700;color:#0f172a">{{ r.rng }}</span>
                    <div style="height:12px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:linear-gradient(90deg,#60a5fa,#2563eb);border-radius:999px'"></div></div>
                    <span style="font-weight:800;color:#0f172a">{{ r.cnt }}</span>
                    <span class="rpt-money" style="color:#334155">{{ r.pay }}</span>
                    <span style="color:#64748b">{{ r.svc }}</span>
                  </div>
                </template>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.sect" @dragover="v.dv.sect" @drop="v.dp.sect" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.sect) + ';order:' + (v.o.sect)" data-screen-label="درآمد بخش‌ها">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">درآمد و سود بخش‌ها</span>
                <input type="checkbox" :checked="v.ck.sect" @change="v.hide.sect" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.sectRows.length" class="report-card-scroll report-list-stack">
                <template v-for="(r, rI) in v.sectRows" :key="rI">
                  <div style="display:flex;flex-direction:column;gap:5px">
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px">
                      <span style="font-weight:700;color:#0f172a">{{ r.n }} <span style="color:#94a3b8;font-weight:600;font-size:11px">{{ r.p }}</span></span>
                      <span style="color:#64748b">درآمد <b class="rpt-money" style="color:#0f172a">{{ r.rev }}</b> · سود <b class="rpt-money" style="color:#15803d">{{ r.prof }}</b></span>
                    </div>
                    <div style="height:11px;background:#f1f5f9;border-radius:999px;overflow:hidden;display:flex">
                      <div :style="'height:100%;width:' + (r.wp) + ';background:#16a34a'"></div>
                      <div :style="'height:100%;width:' + (r.wr) + ';background:#93c5fd'"></div>
                    </div>
                  </div>
                </template>
              </div>
              <div v-else class="report-widget-empty">برای ایجاد گزارش، دکمه محاسبه «پردرآمدترین خدمات» را بزنید</div>
              <div v-if="v.sectRows.length" style="display:flex;gap:16px;font-size:11px;color:#64748b">
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#16a34a;display:inline-block"></i>سود</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#93c5fd;display:inline-block"></i>سایر درآمد</span>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.topsvc" @dragover="v.dv.topsvc" @drop="v.dp.topsvc" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.topsvc) + ';order:' + (v.o.topsvc)" data-screen-label="پردرآمدترین خدمات">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">پردرآمدترین و پرسودترین خدمات</span>
                <small v-if="v.topServicesCompletedAt" class="report-calculated-at">محاسبه: {{ v.topServicesCompletedAt }}</small>
                <button type="button" :disabled="v.topServicesCalculating" @click="calculateTopServices" :title="v.topServicesReady ? 'رفرش گزارش' : 'ایجاد گزارش'" aria-label="ایجاد یا رفرش گزارش پردرآمدترین و پرسودترین خدمات" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.topsvc" @change="v.hide.topsvc" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.topServicesError" class="report-widget-empty" style="color:#b91c1c;border-color:#fecaca;background:#fff7f7">{{ v.topServicesError }}</div>
              <div v-else-if="!v.topServicesCalculating && !v.svcRows.length" class="report-widget-empty">{{ v.topServicesReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              <div v-if="v.svcRows.length" class="report-card-scroll top-services-scroll">
                <template v-for="r in v.svcRows" :key="r.key">
                  <div style="display:grid;grid-template-columns:26px 130px 1fr 90px 90px;gap:10px;align-items:center;font-size:12.5px">
                    <span style="width:24px;height:24px;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-weight:800;font-size:11.5px;display:flex;align-items:center;justify-content:center">{{ r.i }}</span>
                    <span style="font-weight:700;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.n }}</span>
                    <div style="display:flex;flex-direction:column;gap:4px">
                      <div style="height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + r.wr + ';background:#2563eb;border-radius:999px'"></div></div>
                      <div style="height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + r.wp + ';background:#16a34a;border-radius:999px'"></div></div>
                    </div>
                    <span class="rpt-money" style="color:#1d4ed8;font-weight:700">{{ r.rev }}</span>
                    <span class="rpt-money" style="color:#15803d;font-weight:700">{{ r.prof }}</span>
                  </div>
                </template>
              </div>
              <div v-if="v.svcRows.length" style="display:flex;gap:16px;font-size:11px;color:#64748b;justify-content:flex-end">
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#2563eb;display:inline-block"></i>درآمد</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#16a34a;display:inline-block"></i>سود</span>
              </div>
              <div v-if="v.topServicesCalculating" class="staff-income-loading" aria-live="polite">
                <div class="staff-income-loading-card">
                  <div class="staff-income-loading-ring"></div>
                  <strong>در حال محاسبه خدمات پردرآمد و پرسود</strong>
                  <small>درآمد، هزینه‌ها و پورسانت‌های این بازه بررسی می‌شوند</small>
                  <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.cac" @dragover="v.dv.cac" @drop="v.dp.cac" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.cac) + ';order:' + (v.o.cac)" data-screen-label="هزینه جذب مشتری">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">هزینه جذب هر مشتری</span>
                <small v-if="v.cacCompletedAt" class="report-calculated-at">محاسبه: {{ v.cacCompletedAt }}</small>
                <button type="button" :disabled="v.cacCalculating" @click="calculateCustomerAcquisition" :title="v.cacReady ? 'محاسبه مجدد هزینه جذب' : 'ایجاد گزارش هزینه جذب'" aria-label="ایجاد گزارش هزینه جذب مشتری" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.cac" @change="v.hide.cac" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-radius:14px;padding:18px;text-align:center;display:flex;flex-direction:column;gap:4px">
                <span class="rpt-money" style="font-size:clamp(16px,1.5vw,21px);font-weight:800;color:#1d4ed8">{{ v.cacPer }}</span>
                <span style="font-size:11.5px;color:#94a3b8">هزینهٔ تبلیغات ثبت‌شده در بازه ÷ مشتریان یکتای «وقت داده شد»</span>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap">
                <div style="flex:1;min-width:100px;background:#f8fafc;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#94a3b8">هزینه کمپین‌ها</span><span class="rpt-money" style="font-size:13px;font-weight:800;color:#b91c1c">{{ v.cacCost }}</span></div>
                <div style="flex:1;min-width:110px;background:#f8fafc;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#94a3b8">وقت داده شد</span><span style="font-size:15px;font-weight:800;color:#0f172a">{{ v.cacAppointments }}</span></div>
                <div style="flex:1;min-width:90px;background:#f8fafc;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#94a3b8">نرخ جذب</span><span style="font-size:15px;font-weight:800;color:#15803d">{{ v.cacRate }}</span></div>
              </div>
              <div v-if="v.cacCampaignRows.length" class="report-card-scroll cac-campaign-scroll">
                <div v-for="row in v.cacCampaignRows" :key="row.id" class="cac-campaign-row">
                  <span>{{ row.name }}</span><small>{{ row.cost }} ÷ {{ row.appointments }} · جذب {{ row.rate }}</small><b class="rpt-money">{{ row.per }}</b>
                </div>
              </div>
              <div v-if="v.cacCalculating" class="staff-income-loading" aria-live="polite">
                <div class="staff-income-loading-card">
                  <div class="staff-income-loading-ring"></div>
                  <strong>در حال محاسبه هزینه جذب مشتری</strong>
                  <small>هزینه کمپین‌ها و مشتریان جذب‌شده بررسی می‌شوند</small>
                  <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.campperf" @dragover="v.dv.campperf" @drop="v.dp.campperf" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.campperf) + ';order:' + (v.o.campperf)" data-screen-label="بازدهی کمپین‌ها">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">بازدهی کمپین‌ها</span>
                <small v-if="v.campaignPerformanceCompletedAt" class="report-calculated-at">محاسبه: {{ v.campaignPerformanceCompletedAt }}</small>
                <button type="button" :disabled="v.campaignPerformanceCalculating" @click="calculateCampaignPerformance" :title="v.campaignPerformanceReady ? 'محاسبه مجدد بازدهی کمپین‌ها' : 'ایجاد گزارش بازدهی کمپین‌ها'" aria-label="ایجاد گزارش بازدهی کمپین‌ها" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.campperf" @change="v.hide.campperf" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div v-if="v.cpRows.length" class="report-card-scroll campaign-performance-scroll">
                <template v-for="(r, rI) in v.cpRows" :key="rI">
                  <div style="display:flex;flex-direction:column;gap:7px;background:#f8fafc;border:1px solid #eef2f7;border-radius:12px;padding:11px 14px">
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px;flex-wrap:wrap;gap:7px">
                      <span style="font-weight:800;color:#0f172a">{{ r.n }}</span>
                      <span style="display:flex;align-items:center;gap:10px;color:#64748b;font-size:11.5px"><span>{{ r.leads }} لید</span><span>{{ r.appointments }} وقت داده شد</span><b :style="'color:' + r.c + ';font-size:13px'">{{ r.rate }}</b></span>
                    </div>
                    <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:' + r.bar + ';border-radius:999px;transition:width .25s ease'"></div></div>
                  </div>
                </template>
              </div>
              <div v-if="!v.campaignPerformanceCalculating && !v.cpRows.length" class="report-widget-empty">{{ v.campaignPerformanceReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
              <div v-if="v.campaignPerformanceCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه بازدهی کمپین‌ها</strong><small>سرنخ‌ها، تمایل و نوبت‌های هر کمپین بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div v-if="false">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">کمپین‌ها بر اساس درجه تمایل</span>
                <input type="checkbox" :checked="v.ck.campdes" @change="v.hide.campdes" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:flex-end;gap:26px;height:190px;padding:0 10px">
                <template v-for="(r, rI) in v.cdRows" :key="rI">
                  <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px;height:100%">
                    <div style="display:flex;align-items:flex-end;gap:6px;flex:1">
                      <div :style="'width:20px;height:' + (r.h1) + ';background:#93c5fd;border-radius:6px 6px 0 0'" title="تمایل ۱"></div>
                      <div :style="'width:20px;height:' + (r.h2) + ';background:#3b82f6;border-radius:6px 6px 0 0'" title="تمایل ۲"></div>
                      <div :style="'width:20px;height:' + (r.h3) + ';background:#1e40af;border-radius:6px 6px 0 0'" title="تمایل ۳"></div>
                    </div>
                    <span style="font-size:11.5px;font-weight:700;color:#0f172a;text-align:center">{{ r.n }}</span>
                    <span style="font-size:10.5px;color:#94a3b8">هزینه: {{ r.cost }}</span>
                  </div>
                </template>
              </div>
              <div style="display:flex;gap:16px;font-size:11px;color:#64748b;justify-content:center">
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#93c5fd;display:inline-block"></i>تمایل ۱</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#3b82f6;display:inline-block"></i>تمایل ۲</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#1e40af;display:inline-block"></i>تمایل ۳</span>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.staffapt" @dragover="v.dv.staffapt" @drop="v.dp.staffapt" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.staffapt) + ';order:' + (v.o.staffapt)" data-screen-label="وقت‌دهی پرسنل">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">وقت‌دهی پرسنل</span>
                <small v-if="v.staffAppointmentsCompletedAt" class="report-calculated-at">محاسبه: {{ v.staffAppointmentsCompletedAt }}</small>
                <button type="button" :disabled="v.staffAppointmentsCalculating" @click="calculateStaffAppointments" :title="v.staffAppointmentsReady ? 'رفرش گزارش وقت‌دهی پرسنل' : 'ایجاد گزارش وقت‌دهی پرسنل'" aria-label="ایجاد یا رفرش گزارش وقت‌دهی پرسنل" class="report-refresh-button">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>
                </button>
                <input type="checkbox" :checked="v.ck.staffapt" @change="v.hide.staffapt" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div class="report-card-scroll report-card-scroll-wide"><div style="min-width:760px;display:flex;flex-direction:column;gap:10px">
                <div style="display:grid;grid-template-columns:150px 100px 90px 90px 110px 90px 1fr;gap:10px;align-items:center;font-size:11.5px;color:#94a3b8;font-weight:700;padding:0 4px">
                  <span>پرسنل</span><span>آورده</span><span>پورسانت</span><span>حقوق ثابت</span><span>جمع پرداختی</span><span>تعداد نوبت</span><span>نرخ تبدیل</span>
                </div>
                <template v-for="r in v.aptRows" :key="r.id">
                  <div style="display:grid;grid-template-columns:150px 100px 90px 90px 110px 90px 1fr;gap:10px;align-items:center;font-size:12.5px;background:#f8fafc;border-radius:10px;padding:10px 4px">
                    <span style="font-weight:700;color:#0f172a;padding-right:8px;display:flex;align-items:center;gap:7px"><img v-if="r.ph && !brokenAvatars[r.avatarKey]" :src="r.ph" @error="markAvatarBroken(r.avatarKey)" style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e2e8f0" alt=""><span v-else class="report-avatar-initial" style="width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:#e2e8f0;color:#475569;font-size:12px;font-weight:900;flex-shrink:0;border:2px solid #e2e8f0">{{ r.initial }}</span>{{ r.n }}</span>
                    <span class="rpt-money" style="color:#0f172a;font-weight:700">{{ r.bring }}</span>
                    <span class="rpt-money" style="color:#334155">{{ r.pors }}</span>
                    <span class="rpt-money" style="color:#334155">{{ r.fix }}</span>
                    <span class="rpt-money" style="color:#b91c1c;font-weight:700">{{ r.pay }}</span>
                    <span style="color:#334155;font-weight:700">{{ r.cnt }}</span>
                    <div style="display:flex;align-items:center;gap:8px;margin-left:8px" :title="r.rateTitle">
                      <div style="flex:1;height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + r.w + ';background:' + r.color + ';border-radius:999px'"></div></div>
                      <span :style="'width:45px;text-align:left;font-weight:900;color:' + r.color">{{ r.rate }}</span>
                    </div>
                  </div>
                </template>
                <div v-if="!v.staffAppointmentsCalculating && !v.aptRows.length" class="report-widget-empty">{{ v.staffAppointmentsReady ? 'اطلاعاتی در این بازه ثبت نشده است' : 'برای ایجاد گزارش، دکمه محاسبه را بزنید' }}</div>
                <div v-if="v.staffAppointmentsError" class="report-widget-empty" style="color:#b91c1c;border-color:#fecaca;background:#fff7f7">{{ v.staffAppointmentsError }}</div>
              </div></div>
              <div v-if="v.staffAppointmentsCalculating" class="staff-income-loading" aria-live="polite">
                <div class="staff-income-loading-card">
                  <div class="staff-income-loading-ring"></div>
                  <strong>در حال محاسبه وقت‌دهی پرسنل</strong>
                  <small>نوبت‌های دارای مشاور، مبالغ خدمات و پرداختی پرسنل بررسی می‌شوند</small>
                  <div class="staff-income-loading-track top-services-loading-track"><div></div></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.cap" @dragover="v.dv.cap" @drop="v.dp.cap" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.cap) + ';order:' + (v.o.cap)" data-screen-label="گنجایش مجموعه">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">گنجایش مجموعه</span>
                <small v-if="v.overviewCompletedAt" class="report-calculated-at">محاسبه: {{ v.overviewCompletedAt }}</small>
                <button type="button" :disabled="v.capacityCalculating" @click="calculateCustomerOverview('capacity')" title="ایجاد یا محاسبه مجدد گزارش" aria-label="ایجاد گزارش گنجایش مجموعه" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.cap" @change="v.hide.cap" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;flex-shrink:0;direction:ltr">
                  <div style="position:relative;width:180px;height:92px;border:3px solid #c4b5fd;border-radius:18px;padding:6px;background:linear-gradient(180deg,#faf5ff,#f5f3ff);box-shadow:inset 0 2px 6px rgba(124,58,237,0.08)">
                    <div :style="'height:100%;width:' + (v.capW) + ';min-width:10px;background:linear-gradient(180deg,#a78bfa,#7c3aed);border-radius:11px;box-shadow:0 2px 8px rgba(124,58,237,0.35)'">
                      <div style="height:45%;margin:4px 5px 0;background:linear-gradient(180deg,rgba(255,255,255,0.35),rgba(255,255,255,0));border-radius:8px"></div>
                    </div>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
                      <div style="background:rgba(255,255,255,0.92);border-radius:12px;padding:4px 14px;display:flex;flex-direction:column;align-items:center;gap:0;box-shadow:0 1px 4px rgba(15,23,42,0.15)">
                        <span style="font-size:22px;font-weight:800;color:#5b21b6">{{ v.capPct }}</span>
                        <span style="font-size:10px;font-weight:700;color:#7c3aed">پر شده</span>
                      </div>
                    </div>
                  </div>
                  <div style="width:8px;height:34px;background:#c4b5fd;border-radius:0 6px 6px 0;margin-left:2px"></div>
                </div>
                <div style="flex:1;min-width:190px;display:flex;flex-direction:column;gap:10px">
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px"><span style="color:#64748b">درآمد فعلی</span><b class="rpt-money" style="color:#0f172a">{{ v.capFilled }}</b></div>
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px"><span style="color:#64748b">تا تکمیل گنجایش</span><b class="rpt-money" style="color:#7c3aed">{{ v.capRemain }}</b></div>
                  <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b;background:#f8fafc;border-radius:12px;padding:10px 12px">
                    گنجایش (میلیون تومان):
                    <input type="text" inputmode="numeric" :value="v.capV" @input="v.onCap" @change="v.saveCap" style="flex:1;width:80px;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;font-size:13px;font-weight:700;color:#0f172a;outline:none;direction:ltr;text-align:center">
                  </label>
                </div>
              </div>
              <div v-if="v.capacityCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه گنجایش مجموعه</strong><small>درآمد واقعی بازه با هدف واردشده مقایسه می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.gender" @dragover="v.dv.gender" @drop="v.dp.gender" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.gender) + ';order:' + (v.o.gender)" data-screen-label="ترکیب جنسیتی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">ترکیب جنسیتی مراجعین</span>
                <small v-if="v.overviewCompletedAt" class="report-calculated-at">محاسبه: {{ v.overviewCompletedAt }}</small>
                <button type="button" :disabled="v.genderCalculating" @click="calculateCustomerOverview('gender')" title="ایجاد یا محاسبه مجدد گزارش" aria-label="ایجاد گزارش ترکیب جنسیتی" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.gender" @change="v.hide.gender" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;justify-content:space-around;align-items:flex-end;gap:16px">
                <div style="display:flex;flex-direction:column;align-items:center;gap:8px">
                  <svg width="95" height="160" viewBox="0 0 120 200">
                    <defs><clipPath id="clipF"><rect x="0" :y="v.gF.y" width="120" height="200"></rect></clipPath></defs>
                    <g fill="#f1e5f5" stroke="#a855f7" stroke-width="3">
                      <circle cx="60" cy="24" r="18"></circle>
                      <path d="M60,48 C40,48 34,62 30,86 L18,132 L42,132 L36,168 L52,168 L52,192 L68,192 L68,168 L84,168 L78,132 L102,132 L90,86 C86,62 80,48 60,48 Z"></path>
                    </g>
                    <g fill="#a855f7" clip-path="url(#clipF)">
                      <circle cx="60" cy="24" r="18"></circle>
                      <path d="M60,48 C40,48 34,62 30,86 L18,132 L42,132 L36,168 L52,168 L52,192 L68,192 L68,168 L84,168 L78,132 L102,132 L90,86 C86,62 80,48 60,48 Z"></path>
                    </g>
                  </svg>
                  <span style="font-size:17px;font-weight:800;color:#7e22ce">{{ v.gF.p }}</span>
                  <span style="font-size:11.5px;color:#64748b">خانم · {{ v.gF.cnt }} نفر</span>
                </div>
                <div style="display:flex;flex-direction:column;align-items:center;gap:8px">
                  <svg width="95" height="160" viewBox="0 0 120 200">
                    <defs><clipPath id="clipM"><rect x="0" :y="v.gM.y" width="120" height="200"></rect></clipPath></defs>
                    <g fill="#e8f0e3" stroke="#16a34a" stroke-width="3">
                      <circle cx="60" cy="24" r="18"></circle>
                      <path d="M60,48 C42,48 34,58 34,80 L34,124 L46,124 L46,192 L58,192 L58,136 L62,136 L62,192 L74,192 L74,124 L86,124 L86,80 C86,58 78,48 60,48 Z"></path>
                    </g>
                    <g fill="#16a34a" clip-path="url(#clipM)">
                      <circle cx="60" cy="24" r="18"></circle>
                      <path d="M60,48 C42,48 34,58 34,80 L34,124 L46,124 L46,192 L58,192 L58,136 L62,136 L62,192 L74,192 L74,124 L86,124 L86,80 C86,58 78,48 60,48 Z"></path>
                    </g>
                  </svg>
                  <span style="font-size:17px;font-weight:800;color:#15803d">{{ v.gM.p }}</span>
                  <span style="font-size:11.5px;color:#64748b">آقا · {{ v.gM.cnt }} نفر</span>
                </div>
              </div>
              <small v-if="v.gUnknown" style="text-align:center;color:#94a3b8;font-size:10.5px">جنسیت ثبت‌نشده: {{ v.gUnknown }} نفر</small>
              <div v-if="v.genderCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه ترکیب جنسیتی</strong><small>اطلاعات بیماران ثبت‌شده بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.newold" @dragover="v.dv.newold" @drop="v.dp.newold" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.newold) + ';order:' + (v.o.newold)" data-screen-label="مشتریان جدید و قدیم">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">مشتریان جدید و قدیم</span>
                <small v-if="v.overviewCompletedAt" class="report-calculated-at">محاسبه: {{ v.overviewCompletedAt }}</small>
                <button type="button" :disabled="v.newOldCalculating" @click="calculateCustomerOverview('newold')" title="ایجاد یا محاسبه مجدد گزارش" aria-label="ایجاد گزارش مشتریان جدید و قدیم" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.newold" @change="v.hide.newold" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="position:relative;width:150px;height:150px;flex-shrink:0">
                  <svg width="150" height="150" viewBox="0 0 120 120">
                    <g transform="rotate(-90 60 60)">
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#eef2f7" stroke-width="12"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#0d9488" stroke-width="12" :stroke-dasharray="v.ns1.da" :stroke-dashoffset="v.ns1.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#94a3b8" stroke-width="12" :stroke-dasharray="v.ns2.da" :stroke-dashoffset="v.ns2.of"></circle>
                    </g>
                  </svg>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                    <span style="font-size:19px;font-weight:800">{{ v.noTotal }}</span>
                    <span style="font-size:10.5px;color:#94a3b8">مشتری</span>
                  </div>
                </div>
                <div style="flex:1;min-width:170px;display:flex;flex-direction:column;gap:12px">
                  <div style="display:flex;align-items:center;justify-content:space-between;background:#f0fdfa;border-radius:12px;padding:12px 16px">
                    <span style="font-size:12.5px;color:#0f766e;font-weight:600;display:flex;align-items:center;gap:6px"><i style="width:10px;height:10px;border-radius:3px;background:#0d9488;display:inline-block"></i>جدید</span>
                    <span style="font-size:16px;font-weight:800;color:#0f766e">{{ v.noNew }} <span style="font-size:11px;color:#5eaaa2">{{ v.noNewP }}</span></span>
                  </div>
                  <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border-radius:12px;padding:12px 16px">
                    <span style="font-size:12.5px;color:#475569;font-weight:600;display:flex;align-items:center;gap:6px"><i style="width:10px;height:10px;border-radius:3px;background:#94a3b8;display:inline-block"></i>قدیمی</span>
                    <span style="font-size:16px;font-weight:800;color:#334155">{{ v.noOld }} <span style="font-size:11px;color:#94a3b8">{{ v.noOldP }}</span></span>
                  </div>
                </div>
              </div>
              <div v-if="v.newOldCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه مشتریان جدید و قدیمی</strong><small>سابقهٔ پرداخت خدمت هر مشتری بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.status" @dragover="v.dv.status" @drop="v.dp.status" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.status) + ';order:' + (v.o.status)" data-screen-label="وضعیت مشتری‌ها">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">وضعیت مشتری‌ها</span>
                <small v-if="v.overviewCompletedAt" class="report-calculated-at">محاسبه: {{ v.overviewCompletedAt }}</small>
                <button type="button" :disabled="v.statusCalculating" @click="calculateCustomerOverview('status')" title="ایجاد یا محاسبه مجدد گزارش" aria-label="ایجاد گزارش وضعیت مشتری‌ها" class="report-refresh-button"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg></button>
                <input type="checkbox" :checked="v.ck.status" @change="v.hide.status" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="position:relative;width:160px;height:160px;flex-shrink:0">
                  <svg width="160" height="160" viewBox="0 0 120 120">
                    <g transform="rotate(-90 60 60)">
                      <circle v-for="(seg, segI) in v.stSegs" :key="'st-seg-'+segI" cx="60" cy="60" r="54" fill="none" :stroke="seg.c" stroke-width="12" :stroke-dasharray="seg.da" :stroke-dashoffset="seg.of"></circle>
                    </g>
                  </svg>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                    <span style="font-size:19px;font-weight:800">{{ v.stTotal }}</span>
                    <span style="font-size:10.5px;color:#94a3b8">کل نوبت‌ها</span>
                  </div>
                </div>
                <div style="flex:1;min-width:170px;display:flex;flex-direction:column;gap:7px">
                  <template v-for="(r, rI) in v.stLegend" :key="rI">
                    <div style="display:flex;align-items:center;gap:8px;font-size:12px">
                      <span :style="'width:10px;height:10px;border-radius:50%;background:' + (r.c) + ';flex-shrink:0'"></span>
                      <span style="flex:1;color:#334155;font-weight:600">{{ r.n }}</span>
                      <span style="font-weight:800;color:#0f172a">{{ r.cnt }}</span>
                      <span style="width:40px;text-align:left;color:#94a3b8">{{ r.p }}</span>
                    </div>
                  </template>
                </div>
              </div>
              <div v-if="v.statusCalculating" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال محاسبه وضعیت مشتری‌ها</strong><small>وضعیت نوبت‌های این بازه بررسی می‌شود</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
  
          </div>
        </div>
  
        <template v-if="v.otherExpensesDetailOpen">
          <div class="other-expenses-modal" style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:110;display:flex;align-items:center;justify-content:center;padding:24px" @click="v.closeOtherExpensesDetail" data-screen-label="جزئیات سایر هزینه‌های کلینیک">
            <div dir="rtl" class="other-expenses-modal__panel" style="background:#ffffff;border-radius:20px;max-width:820px;width:100%;max-height:85vh;overflow:auto;padding:22px;display:flex;flex-direction:column;gap:14px;box-shadow:0 24px 64px rgba(15,23,42,0.3)" @click="v.stopProp">
              <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:16px;font-weight:800;color:#0f172a">جزئیات سایر هزینه‌های کلینیک</span>
                  <small style="font-size:11px;color:#64748b;line-height:1.5">هزینه‌های ثبت‌شده در ماژول هزینه‌ها، به‌جز هزینه‌های دارای شاخص مستقل</small>
                </div>
                <button type="button" @click="v.closeOtherExpensesDetail" style="background:#f1f5f9;border:none;border-radius:10px;width:32px;height:32px;font-size:15px;color:#64748b;cursor:pointer;flex-shrink:0" aria-label="بستن">✕</button>
              </div>
              <p v-if="s.expenseError" role="alert" style="font-size:12px;color:#b91c1c;overflow-wrap:anywhere">{{ s.expenseError }}</p>
              <p v-else-if="v.billReady && !v.billRows.length" style="font-size:12px;color:#64748b">هزینه‌ای در این بازه ثبت نشده است</p>
              <div v-else class="report-list-stack" style="max-height:52vh;overflow:auto">
                <article v-for="b in v.billRows" :key="'detail-'+b.id" class="expense-invoice" :class="{ 'is-open': b.open }">
                  <button type="button" class="expense-invoice__primary" @click="toggleBillRow(b.id)" :aria-expanded="b.open ? 'true' : 'false'">
                    <span class="expense-invoice__name" :title="b.n">{{ b.n }}</span>
                    <span class="expense-money">{{ b.v }}</span>
                    <span class="expense-invoice__no" :title="b.categoryLabel">{{ b.categoryLabel }}</span>
                    <span class="expense-invoice__chevron" aria-hidden="true">{{ b.open ? '▾' : '◂' }}</span>
                  </button>
                  <div v-if="b.open" class="expense-invoice__details">
                    <span><em>تاریخ</em>{{ b.date }}</span>
                    <span><em>دسته‌بندی</em>{{ b.category }}</span>
                    <span><em>طرف حساب</em>{{ b.party }}</span>
                    <span><em>وضعیت پرداخت</em>{{ b.status }}</span>
                    <span v-if="b.note"><em>توضیحات</em>{{ b.note }}</span>
                  </div>
                </article>
              </div>
              <div class="expense-summary-list">
                <div class="expense-summary-row" style="background:#fef2f2">
                  <span style="font-size:11.5px;color:#b91c1c;font-weight:600">جمع جزئیات (باید برابر کارت باشد)</span>
                  <span class="expense-summary-value" style="color:#991b1b">{{ v.billSumV }}</span>
                </div>
              </div>
              <div v-if="s.expenseLoading" class="staff-income-loading" aria-live="polite"><div class="staff-income-loading-card"><div class="staff-income-loading-ring"></div><strong>در حال آماده‌سازی جزئیات</strong><small>فاکتورهای مؤثر در کارت در حال بارگذاری‌اند</small><div class="staff-income-loading-track top-services-loading-track"><div></div></div></div></div>
            </div>
          </div>
        </template>

        <template v-if="v.filterOpen">
          <div style="position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:100;display:flex;align-items:center;justify-content:center;padding:24px" @click="v.closeFilter" data-screen-label="پنجره فیلترها">
            <div dir="rtl" style="background:#ffffff;border-radius:20px;max-width:760px;width:100%;max-height:85vh;overflow:auto;padding:24px;display:flex;flex-direction:column;gap:18px;box-shadow:0 24px 64px rgba(15,23,42,0.3)" @click="v.stopProp">
              <div style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:17px;font-weight:800;color:#0f172a">فیلتر آمار</span>
                <button @click="v.closeFilter" style="background:#f1f5f9;border:none;border-radius:10px;width:32px;height:32px;font-size:15px;color:#64748b;cursor:pointer">✕</button>
              </div>
              <div style="display:flex;flex-direction:column;gap:8px">
                <span style="font-size:13px;font-weight:700;color:#334155">تاریخ تولد</span>
                <div style="display:flex;gap:10px;align-items:center">
                  <input :value="v.bdFrom" @input="v.onBdF" placeholder="از: ۱۳۶۰/۰۱/۰۱" style="width:150px;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;text-align:center;outline:none;background:#f8fafc">
                  <input :value="v.bdTo" @input="v.onBdT" placeholder="تا: ۱۳۸۵/۱۲/۲۹" style="width:150px;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;text-align:center;outline:none;background:#f8fafc">
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:12px;background:#f8fafc;border:1px solid #eef2f7;border-radius:14px;padding:16px">
                <span style="font-size:13px;font-weight:800;color:#0f172a">انتخاب از لیست</span>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px 14px">
                  <template v-for="(g, gI) in v.dgroups" :key="gI">
                    <div style="display:flex;flex-direction:column;gap:6px;position:relative">
                      <span style="font-size:12px;font-weight:700;color:#334155">{{ g.label }}</span>
                      <button @click="g.tog" style="display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid #e2e8f0;background:#ffffff;border-radius:10px;padding:9px 12px;font-size:12.5px;cursor:pointer;text-align:right;width:100%">
                        <span :style="'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;color:' + (g.sumC)">{{ g.sum }}</span>
                        <span style="color:#94a3b8;font-size:9px;flex-shrink:0">▼</span>
                      </button>
                      <div :style="'display:' + (g.dsp) + ';position:absolute;top:calc(100% + 4px);right:0;left:0;z-index:60;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 32px rgba(15,23,42,0.16);padding:8px;flex-direction:column;gap:2px;max-height:235px;overflow:auto'">
                        <input :value="g.q" @input="g.onQ" placeholder="جستجو..." style="border:1px solid #eef2f7;border-radius:8px;padding:6px 10px;font-size:12px;outline:none;background:#f8fafc;margin-bottom:4px">
                        <template v-for="(it, itI) in g.items" :key="itI">
                          <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#334155;padding:6px 8px;border-radius:8px;cursor:pointer" data-hover="1">
                            <input type="checkbox" :checked="it.ck" @change="it.on" style="width:14px;height:14px;accent-color:#2563eb;cursor:pointer;flex-shrink:0">
                            {{ it.t }}
                          </label>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>
                <div :style="'display:' + (v.selDsp) + ';flex-wrap:wrap;gap:7px;border-top:1px dashed #e2e8f0;padding-top:12px'">
                  <template v-for="(tg, tgI) in v.selTags" :key="tgI">
                    <span style="display:flex;align-items:center;gap:6px;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:4px 6px 4px 10px;font-size:11.5px;font-weight:700">{{ tg.t }}<button @click="tg.on" style="border:none;background:#dbeafe;border-radius:50%;width:16px;height:16px;color:#1d4ed8;cursor:pointer;font-size:9px;padding:0;display:flex;align-items:center;justify-content:center">✕</button></span>
                  </template>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px 20px">
                <template v-for="(g, gI) in v.fgroups" :key="gI">
                  <div style="display:flex;flex-direction:column;gap:8px">
                    <span style="font-size:13px;font-weight:700;color:#334155">{{ g.label }}</span>
                    <div style="display:flex;flex-wrap:wrap;gap:7px">
                      <template v-for="(op, opI) in g.opts" :key="opI">
                        <button :style="op.st" @click="op.on">{{ op.t }}</button>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid #f1f5f9;padding-top:16px;flex-wrap:wrap">
                <span style="font-size:12.5px;color:#64748b">{{ v.filtCnt }} فیلتر فعال</span>
                <div style="display:flex;gap:10px">
                  <button @click="v.clearF" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:9px 18px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer">پاک کردن همه</button>
                  <button @click="v.closeFilter" style="background:#2563eb;color:#ffffff;border:none;border-radius:10px;padding:9px 24px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 2px 6px rgba(37,99,235,0.3)">اعمال فیلتر</button>
                </div>
              </div>
            </div>
          </div>
        </template>
  
      </section>
      </template>
  
      <template v-if="v.isA">
      <section data-screen-label="تب آمار و تحلیل" style="display:flex;flex-direction:column;gap:16px">
        <div style="background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:18px;padding:28px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
          <div style="display:flex;flex-direction:column;gap:6px;color:#ffffff">
            <span style="font-size:17px;font-weight:800">تحلیل توسط هوش مصنوعی</span>
            <span style="font-size:12.5px;color:#94a3b8;max-width:520px">تحلیل خودکار روند درآمد، کنسلی‌ها و بازدهی کمپین‌ها بر اساس داده‌های بازه انتخابی.</span>
          </div>
          <a href="#" style="background:#2563eb;color:#ffffff;border-radius:12px;padding:12px 26px;font-size:13.5px;font-weight:700;box-shadow:0 4px 14px rgba(37,99,235,0.4)">ورود به تحلیل هوش مصنوعی ←</a>
        </div>
        <div style="background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;display:flex;flex-direction:column;gap:12px">
          <span style="font-weight:800;font-size:15px;color:#0f172a">خلاصه تحلیل دوره</span>
          <div class="report-widget-empty">برای ایجاد گزارش، دکمه محاسبه را بزنید</div>
        </div>
      </section>
      </template>
  
    </main>
  </div>
</template>

<script>
import axios from 'axios'
import DatePicker from 'vue3-persian-datetime-picker'
import { subscribeReportWidgetProgress } from '../services/presence'
import { appointmentStatusChartColor } from '../utils/appointmentStatusColors'

export default {
  name: 'ClinicReport',
  components: { DatePicker },
  emits: ['open-builder', 'open-bills'],
  props: {
    estCancel: { type: Number, default: 30 },
    capacityDefault: { type: Number, default: 2500 },
  },
  data() {
    const KEYS = ['ctype','staffinc','loyal','cancel','bills','qc','cac','adch','campperf','docs','photo','city','age','topsvc','staffapt','sect','cap','gender','newold','status'];
    const faDigits = '۰۱۲۳۴۵۶۷۸۹';
    const toEnglish = value => String(value).replace(/[۰-۹]/g, digit => String(faDigits.indexOf(digit)));
    const currentParts = Object.fromEntries(
      new Intl.DateTimeFormat('fa-IR-u-ca-persian', {year:'numeric',month:'2-digit',day:'2-digit'})
        .formatToParts(new Date())
        .filter(part => ['year','month','day'].includes(part.type))
        .map(part => [part.type, toEnglish(part.value)])
    );
    const currentYear = Number(currentParts.year) || 1405;
    const currentMonth = Number(currentParts.month) || 1;
    const currentMonthText = String(currentMonth).padStart(2, '0');
    // مثل نوبت‌دهی: بازهٔ پیش‌فرض = کل ماه جاری، نه «از اول ماه تا امروز».
    const jalaliLeap = [1, 5, 9, 13, 17, 22, 26, 30].includes((currentYear + 12) % 33);
    const currentMonthLastDay = currentMonth <= 6 ? 31 : (currentMonth <= 11 ? 30 : (jalaliLeap ? 30 : 29));
    const currentMonthLastDayText = String(currentMonthLastDay).padStart(2, '0');
    return {
      isDarkUi: false,
      KEYS: ['ctype','staffinc','loyal','cancel','bills','qc','cac','adch','campperf','docs','photo','city','age','topsvc','staffapt','sect','cap','gender','newold','status'],
      NAMES: {ctype:'دسته‌بندی مشتریان',loyal:'مشتریان وفادار',cancel:'نرخ کنسلی',bills:'سایر هزینه‌های کلینیک',staffinc:'درآمد پرسنل و پله‌های تارگت',qc:'رضایت‌مندی (QC)',adch:'آمار کانال‌های تبلیغاتی',docs:'پزشکان و تبدیل مشاوره',photo:'آنالیز عکس‌ها',city:'آمار بر اساس شهر',age:'آمار سنی',sect:'درآمد و سود بخش‌ها',topsvc:'پردرآمدترین خدمات',cac:'هزینه جذب هر مشتری',campperf:'بازدهی کمپین‌ها',staffapt:'وقت‌دهی پرسنل',cap:'گنجایش مجموعه',gender:'ترکیب جنسیتی',newold:'مشتریان جدید و قدیم',status:'وضعیت مشتری‌ها'},
      s: {
      tab:'r', filterOpen:false, mngOpen:false,
      filters:{}, bdFrom:'', bdTo:'', hidden:{}, order:KEYS.slice(), ddOpen:null, ddQ:'',
      monthSel:3, loyalRange:6, staff:'همه', cap:String(this.capacityDefault ?? 2500),
      reportSummary:null, reportLoading:false, reportError:'', topServicesExpanded:false, campSort:'quality', from:`${currentYear}/${currentMonthText}/01`, to:`${currentYear}/${currentMonthText}/${currentMonthLastDayText}`}
      ,cancellationReport: null, cancellationLoading: false, expenseReport: null, expenseLoading: false, expenseError: '', reportSummary: null, reportLoading: false
      ,customerSegmentSnapshot: null, customerSegmentLoading: false
      ,dashboardSnapshot: null, dashboardLoading: false
      ,staffRoster: [], staffIncomeSnapshot: null, staffIncomeLoading: false
      ,advertisingRoiSnapshot: null, advertisingRoiLoading: false
      ,loyaltySnapshot: null, loyaltyLoading: false
      ,cancellationSnapshot: null
      ,photoSnapshot: null, photoLoading: false
      ,expenseSnapshot: null
      ,billExpandedIds: {}
      ,otherExpensesDetailOpen: false
      ,customerAcquisitionSnapshot: null, customerAcquisitionLoading: false
      ,campaignPerformanceSnapshot: null, campaignPerformanceLoading: false
      ,advertisingChannelsSnapshot: null, advertisingChannelsLoading: false
      ,doctorPerformanceSnapshot: null, doctorPerformanceLoading: false
      ,ageStatisticsSnapshot: null, ageStatisticsLoading: false, ageStatisticsProgress: 0
      ,cityStatisticsSnapshot: null, cityStatisticsLoading: false, cityStatisticsProgress: 0
      ,topServicesSnapshot: null, topServicesLoading: false
      ,staffAppointmentsSnapshot: null, staffAppointmentsLoading: false
      ,satisfactionSnapshot: null, satisfactionLoading: false
      ,overviewLoadingSource: null
      ,brokenAvatars: {}
    };
  },
  computed: {
    nsrShellStyle() {
      return this.isDarkUi
        ? 'min-height:100vh;background:#121316;color:#e8eaed;padding-bottom:60px'
        : 'min-height:100vh;background:#f1f4f9;color:#0f172a;padding-bottom:60px';
    },
    v() {
    const S = this.s;
    const dark = this.isDarkUi;
    const fa = n => Math.round(n).toLocaleString('fa-IR');
    // همهٔ مبلغ‌های گزارش کامل و با «تومان» نوشته می‌شوند (نه «۲۴۸٫۴ میلیون»).
    const toman = value => (value === null || value === undefined || value === '' || Number.isNaN(Number(value)))
      ? '--' : Math.round(Number(value)).toLocaleString('fa-IR') + ' تومان';
    const mm = n => toman(Number(n || 0) * 1000000);
    const pc = n => fa(n) + '٪';
    const C = 2 * Math.PI * 54;

    // chip style helper
    const chip = a => 'padding:5px 13px;border-radius:999px;border:1px solid ' + (a?(dark?'#4f8cff':'#2563eb'):(dark?'#3a3f4a':'#e2e8f0')) + ';background:' + (a?(dark?'#1e3a5f':'#eff6ff'):(dark?'#22252d':'#ffffff')) + ';color:' + (a?(dark?'#93c5fd':'#1d4ed8'):(dark?'#c5cad3':'#475569')) + ';font-size:12px;cursor:pointer;font-weight:' + (a?'700':'500');

    // filters
    const fCount = Object.values(S.filters).filter(Boolean).length + (S.bdFrom?1:0) + (S.bdTo?1:0);

    const togF = key => () => this.set(s => ({filters: {...s.filters, [key]: !s.filters[key]}}));
    const mkGroups = defs => defs.map(g => ({
      label: g.label,
      opts: g.opts.map(t => {
        const key = g.id + ':' + t;
        return {t, st: chip(!!S.filters[key]), on: togF(key)};
      })
    }));

    const fgroups = mkGroups([
      {id:'fin', label:'وضعیت مالی', opts:['خوب','متوسط','ضعیف']},
      {id:'gender', label:'جنسیت', opts:['خانم','آقا']},
      {id:'inactive', label:'مشتریان غیرفعال', opts:['فقط غیرفعال‌ها']},
      {id:'grade', label:'درجه‌بندی مشتری', opts:['VIP','نقره‌ای','قرمز']},
      {id:'age', label:'آمار سنی', opts:['زیر ۳۰','۳۰ تا ۴۰','۴۰ تا ۵۰','بالای ۵۰']}
    ]);

    const DD = [
      {id:'city', label:'شهر', items:['تهران','کرج','اصفهان','مشهد','شیراز','تبریز','قم','اهواز','رشت','ساری','کرمان','یزد','همدان','ارومیه','قزوین']},
      {id:'svc', label:'خدمات', items:['ژل لب','بوتاکس','لیفت با نخ','لیزر موهای زائد','هایفو','مزوتراپی','فیلر گونه','میکرونیدلینگ','پلاسما جت','کاشت مو','پی‌آر‌پی','جوانسازی','لاغری موضعی','پاکسازی پوست','تزریق چربی']},
      {id:'adsrc', label:'منبع تبلیغات', items:['اینستاگرام','یوتیوب','گوگل','تلگرام','معرفی دوستان','بیلبورد','سایت','دیوار','مشتری بازگشتی','همکاری بلاگر']},
      {id:'cons', label:'نام مشاور', items:['سارا احمدی','مریم رضایی','نگار موسوی','الهام کریمی','رویا شریفی','آیدا محسنی','شیوا رستمی','پریسا نادری']},
      {id:'face', label:'مشکلات چهره', items:['افتادگی پلک','خط خنده','چین پیشانی','غبغب','گودی زیر چشم','لک صورت','منافذ باز','جای جوش','افتادگی گونه','خط اخم','چروک دور چشم','عدم تقارن لب']},
      {id:'tags', label:'نواحی', items:['تزریقات','جوانسازی','لیزر','پوست','مو','لاغری','زیبایی لب','ضدپیری','درمانی','مراقبتی','دستگاهی','ترکیبی']},
      {id:'channel', label:'کانال تبلیغاتی', items:['اینستاگرام','یوتیوب','تلگرام','گوگل','واتساپ','تیک‌تاک','آپارات','بیلبورد','رادیو','پیامک']}
    ];
    const dgroups = DD.map(g => {
      const open = S.ddOpen === g.id;
      const sel = g.items.filter(t => S.filters[g.id + ':' + t]);
      const q = open ? (S.ddQ || '') : '';
      return {
        label: g.label,
        sum: sel.length ? fa(sel.length) + ' مورد انتخاب شده' : 'انتخاب کنید',
        sumC: sel.length ? '#1d4ed8' : '#94a3b8',
        dsp: open ? 'flex' : 'none',
        tog: () => this.set(s => ({ddOpen: s.ddOpen === g.id ? null : g.id, ddQ: ''})),
        q, onQ: e => this.set({ddQ: e.target.value}),
        items: g.items.filter(t => !q || t.includes(q)).map(t => {
          const key = g.id + ':' + t;
          return {t, ck: !!S.filters[key], on: togF(key)};
        })
      };
    });
    const selTags = [];
    DD.forEach(g => g.items.forEach(t => {
      if (S.filters[g.id + ':' + t]) selTags.push({t: g.label + ': ' + t, on: togF(g.id + ':' + t)});
    }));

    const qgroups = mkGroups([
      {id:'doc', label:'پزشک', opts:['دکتر محمدی','دکتر افشار','دکتر سلطانی']},
      {id:'cst', label:'وضعیت مشتری', opts:['کنسل شده','آماده','نیامده']},
      {id:'typ', label:'نوع', opts:['انجام کار','مشاوره']},
      {id:'cage', label:'مشتریان', opts:['جدید','قدیمی']}
    ]);

    // dashboards visibility / order / drag
    if (S.order.indexOf('bills') === -1) S.order.splice(S.order.indexOf('cancel') + 1, 0, 'bills');
    const o = {}, dsp = {}, ck = {}, hide = {}, dh = {}, dv = {}, dp = {};
    this.KEYS.forEach(key => {
      o[key] = S.order.indexOf(key);
      dsp[key] = S.hidden[key] ? 'none' : 'flex';
      ck[key] = !S.hidden[key];
      hide[key] = () => this.set(s => ({hidden: {...s.hidden, [key]: !s.hidden[key]}}));
      dh[key] = e => { e.preventDefault(); };
      dv[key] = () => {};
      dp[key] = () => {};
    });
    const dashList = this.KEYS.map(key => ({t: this.NAMES[key], ck: !S.hidden[key], on: hide[key]}));

    // KPIs
    const gUp = dark
      ? {gb:'#14532d', gc:'#86efac'}
      : {gb:'#dcfce7', gc:'#15803d'};
    const gDn = dark
      ? {gb:'#7f1d1d', gc:'#fecaca'}
      : {gb:'#fee2e2', gc:'#b91c1c'};
    // مبلغ کامل طولانی‌تر است؛ اندازهٔ عدد کارت‌ها متناسب کوچک شده تا در یک خط بماند.
    const sm = {sp:'auto', vs:'clamp(13px,1.15vw,16px)', ts:'12px'}, lg = {sp:'span 2', vs:'clamp(16px,1.6vw,22px)', ts:'13.5px'};
    const dashboardSnapshot = this.dashboardSnapshot;
    // هنگام محاسبهٔ مجدد، نتیجهٔ قبلی تا رسیدن نتیجهٔ تازه نمایش داده می‌شود و باکس‌های دیگر خالی نمی‌شوند.
    const dashboardReady = (dashboardSnapshot?.status === 'completed' || (['queued','processing'].includes(dashboardSnapshot?.status) && !!S.reportSummary))
      && Number((dashboardSnapshot?.result || S.reportSummary)?.schema_version || 0) >= 4;
    const actualRevenue = dashboardReady ? Number(S.reportSummary?.kpis?.recognized_revenue || 0) / 1000000 : null;
    const actualRevenueText = actualRevenue === null ? '—' : toman(S.reportSummary?.kpis?.recognized_revenue || 0);
    const reportMoney = value => toman(Number(value || 0) * 1000000);
    const advertisingCost = S.reportSummary?.expenses?.advertising_total;
    const netProfit = S.reportSummary?.kpis?.net_profit;
    const dashboardKpis = S.reportSummary?.kpis || {};
    const kpiMoney = value => dashboardReady ? reportMoney(Number(value || 0) / 1000000) : '--';
    const kpis = [
      {t:'درآمد کل', v:dashboardReady ? actualRevenueText : '--', g:dashboardReady ? `نوبت‌های انجام‌شده · ${String(S.from || '').replaceAll('-', '/')} تا ${String(S.to || '').replaceAll('-', '/')}` : '--', ...gUp, bg:dark?'#1e3a5f':'#eff6ff', bd:dark?'#3b82f6':'#bfdbfe', tc:dark?'#93c5fd':'#1d4ed8', ...lg},
      {t:'سود خالص', v:kpiMoney(netProfit), g:dashboardReady ? 'درآمد − سایر هزینه‌ها − تبلیغات − پزشک و پرسنل − مواد مصرفی' : '--', ...gUp, bg:dark?'#14352a':'#f0fdf4', bd:dark?'#22c55e':'#bbf7d0', tc:dark?'#86efac':'#15803d', ...lg},
      {t:'بدهی بیماران', v:kpiMoney(dashboardKpis.receivables), g:dashboardReady ? 'ماندهٔ نوبت‌های انجام‌شده' : '--', ...gDn, bg:dark?'#3a2e14':'#fefce8', bd:dark?'#eab308':'#fde047', tc:dark?'#fde68a':'#a16207', ...sm},
      {t:'تبلیغات', v:kpiMoney(advertisingCost), g:dashboardReady ? 'هزینه تبلیغات ثبت‌شده' : '--', ...gDn, bg:dark?'#3a2414':'#fff7ed', bd:dark?'#fb923c':'#fed7aa', tc:dark?'#fdba74':'#c2410c', ...sm},
      {t:'هزینه پزشک', v:kpiMoney(dashboardKpis.doctor_cost), g:dashboardReady ? 'حقوق و پورسانت پزشکان' : '--', ...gDn, bg:dark?'#0c4a6e':'#f0f9ff', bd:dark?'#38bdf8':'#bae6fd', tc:dark?'#7dd3fc':'#0369a1', ...sm},
      {t:'حقوق پرسنل', v:kpiMoney(dashboardKpis.staff_cost), g:dashboardReady ? 'حقوق و پورسانت پرسنل' : '--', gb:dark?'#2a2f3a':'#f1f5f9', gc:dark?'#9aa0a6':'#64748b', bg:dark?'#2e1065':'#faf5ff', bd:dark?'#a78bfa':'#e9d5ff', tc:dark?'#c4b5fd':'#7c3aed', ...sm},
      {t:'مواد مصرفی', v:kpiMoney(dashboardKpis.materials_cost), g:dashboardReady ? 'مواد خدمات انجام‌شده (از انبار)' : '--', ...gUp, bg:dark?'#3a2e14':'#fffbeb', bd:dark?'#fbbf24':'#fde68a', tc:dark?'#fcd34d':'#b45309', ...sm},
      {
        t:'سایر هزینه‌های کلینیک',
        sub: dashboardReady ? 'هزینه‌های ثبت‌شده در ماژول هزینه‌ها، به‌جز هزینه‌های دارای شاخص مستقل' : '',
        v:kpiMoney(dashboardKpis.expenses),
        g: dashboardReady ? 'کلیک برای جزئیات فاکتورها' : '--',
        open: dashboardReady ? () => this.openOtherExpensesDetail() : null,
        ...gDn, bg:dark?'#3a1d1d':'#fff1f2', bd:dark?'#fb7185':'#fecdd3', tc:dark?'#fda4af':'#be123c', ...sm
      },
      {t:'میزان تخفیف‌ها', v:kpiMoney(dashboardKpis.discount_total), g:dashboardReady ? 'تخفیف نوبت‌های انجام‌شده' : '--', ...gDn, bg:dark?'#4a044e':'#fdf2f8', bd:dark?'#f472b6':'#fbcfe8', tc:dark?'#f9a8d4':'#be185d', ...sm},
      {t:'تعداد مراجعین', v:dashboardReady ? fa(dashboardKpis.visitors_count || 0)+' نفر' : '--', g:dashboardReady ? 'نوبت‌های انجام‌شده' : '--', ...gUp, bg:dark?'#134e4a':'#f0fdfa', bd:dark?'#2dd4bf':'#99f6e4', tc:dark?'#5eead4':'#0d9488', ...sm}
    ];

    // فاکتورهای type=expense از ماژول هزینه‌ها؛ فیلتر تاریخ روی occurred_on.
    const expenses = this.expenseSnapshot?.status === 'completed' ? this.expenseSnapshot.result : null;
    const billItems = expenses?.items || [];
    const billMoney = value => toman(value || 0);
    const paymentStatusLabel = status => ({
      paid: 'پرداخت شد',
      unpaid: 'پرداخت نشد',
      pending: 'در انتظار',
      'پرداخت شد': 'پرداخت شد',
      'پرداخت نشد': 'پرداخت نشد',
      'در انتظار': 'در انتظار',
    }[String(status || '').trim()] || (status ? String(status) : '—'));
    const billDateLabel = value => {
      const raw = String(value || '').trim();
      if (!raw) return '—';
      return raw.replaceAll('-', '/').replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
    };
    const billRows = billItems.map(item => {
      const id = item.id ?? `${item.title || ''}-${item.occurred_on || ''}-${item.amount || 0}`;
      const category = item.category || 'بدون دسته‌بندی';
      const note = String(item.note || '').trim();
      return {
        id,
        n: item.title || 'بدون عنوان',
        v: billMoney(item.amount),
        categoryLabel: category,
        invoiceLabel: category,
        date: billDateLabel(item.occurred_on),
        party: item.party || '—',
        category,
        status: paymentStatusLabel(item.payment_status),
        note: note || '',
        open: !!this.billExpandedIds[id],
      };
    });
    const billSum = Number(expenses?.total || 0);
    const billSumV = expenses ? billMoney(billSum) : '—';
    const cancelP = this.estCancel ?? 30;
    const forecastCancellation = S.reportSummary?.forecast?.previous_month_cancellation_rate;

    // months
    const jalaliDigits = value => Number(String(value).replace(/[۰-۹]/g, digit => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)));
    const todayParts = Object.fromEntries(new Intl.DateTimeFormat('fa-IR-u-ca-persian', {year:'numeric',month:'numeric'}).formatToParts(new Date()).map(part => [part.type, part.value]));
    const currentJalaliYear = jalaliDigits(todayParts.year);
    const currentJalaliMonth = jalaliDigits(todayParts.month);
    const latestMonthKeys = Array.from({length:4}, (_, index) => {
      const absolute = currentJalaliYear * 12 + currentJalaliMonth - 1 - (3 - index);
      return `${Math.floor(absolute / 12)}-${String((absolute % 12) + 1).padStart(2, '0')}`;
    });
    const selectedReportMonth = String(S.from || '').replaceAll('/','-').slice(0,7);
    const isLatestReportMonth = selectedReportMonth === latestMonthKeys[latestMonthKeys.length - 1];
    const estV = dashboardReady && isLatestReportMonth ? kpiMoney(dashboardKpis.forecast_revenue) : '--';
    const estNote = dashboardReady && isLatestReportMonth ? 'بر اساس وقت‌های آینده با ' + fa(forecastCancellation ?? cancelP) + '٪ کنسلی' : '--';
    const monthlyTrendRows = dashboardReady && Array.isArray(S.reportSummary?.monthly_trends)
      ? S.reportSummary.monthly_trends
      : latestMonthKeys.map(month => ({month,revenue:null,growth_percent:null}));
    const MONTH_NAMES = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    const box = a => 'flex:1;min-width:160px;background:' + (a?(dark?'#1e3a5f':'#eff6ff'):(dark?'#1a1c22':'#ffffff')) + ';border:1.5px solid ' + (a?(dark?'#4f8cff':'#2563eb'):(dark?'#2c3038':'#e2e8f0')) + ';border-radius:14px;padding:14px 16px;cursor:pointer;display:flex;flex-direction:column;gap:6px;box-shadow:0 1px 3px ' + (dark?'rgba(0,0,0,0.32)':'rgba(15,23,42,0.05)');
    const monthsV = monthlyTrendRows.map(row => {
      const [year, month] = String(row.month).split('-').map(Number);
      const growth = row.growth_percent == null ? null : Number(row.growth_percent);
      return {
        name: `${MONTH_NAMES[month - 1] || row.month} ${fa(year)}`,
        val: dashboardReady ? reportMoney(Number(row.revenue || 0) / 1000000) : '--',
        g: dashboardReady ? (growth == null ? 'بدون مبنای مقایسه' : `${fa(Math.abs(growth))}٪`) : '--',
        arrow: !dashboardReady || growth == null ? '' : (growth >= 0 ? '↑' : '↓'),
        col: !dashboardReady || growth == null ? (dark?'#9aa0a6':'#94a3b8') : (growth >= 0 ? (dark?'#86efac':'#16a34a') : (dark?'#fca5a5':'#dc2626')),
        pts: !dashboardReady ? '0,20 60,20' : (growth == null ? '0,18 60,18' : (growth >= 0 ? '0,24 15,22 30,18 45,14 60,7' : '0,7 15,11 30,15 45,19 60,24')),
        st: box(String(S.from || '').replaceAll('/','-').startsWith(row.month)),
        on: () => this.selectReportMonthKey(row.month)
      };
    });

    // donut segment helper
    const segs = fracs => {
      let acc = 0;
      return fracs.map(f => {
        const s = {da: (f * C) + ' ' + C, of: String(-acc * C)};
        acc += f;
        return s;
      });
    };
    const arc = p => (p * C) + ' ' + C;

    // Customer segmentation is calculated by the report API for the selected date range.
    const segmentSnapshot = this.customerSegmentSnapshot;
    const ctReady = segmentSnapshot?.status === 'completed' && Array.isArray(segmentSnapshot?.result);
    const segmentRows = segmentSnapshot?.result || [];
    const segmentByKey = Object.fromEntries(segmentRows.map(row => [row.key, row]));
    const orderedSegments = ['silver', 'blue', 'gold', 'problematic'].map(key => segmentByKey[key] || {key, count: 0, revenue: 0});
    const previewSegments = [
      {key:'silver', count:null, revenue:null},
      {key:'blue', count:null, revenue:null},
      {key:'gold', count:null, revenue:null},
      {key:'problematic', count:null, revenue:null}
    ];
    const displayedSegments = ctReady ? orderedSegments : previewSegments;
    const ctT = ctReady ? displayedSegments.reduce((sum, row) => sum + Number(row.count || 0), 0) : null;
    const ctP = ctReady
      ? displayedSegments.map(row => ctT > 0 ? Number(row.count || 0) / ctT : 0)
      : [0, 0, 0, 0];
    const [cs1, cs2, cs3, cs4] = segs(ctP);
    const ctCols = ['#94a3b8','#93c5fd','#f59e0b','#dc2626'];
    const ctNames = ['نقره‌ای','آبی','طلایی (CIP)','دردسرساز'];
    const ctLegend = ctP.map((p, i) => ({
      n: ctNames[i],
      c: ctCols[i],
      cnt: ctReady ? fa(displayedSegments[i].count)+' نفر' : '-- نفر',
      p: ctReady ? pc(p*100) : '--٪',
      w: (p*100)+'%',
      rev: ctReady ? Number(displayedSegments[i].revenue || 0).toLocaleString('fa-IR') + ' تومان' : '--- تومان'
    }));

    // Advertising ROI: costs are advertising expenses and returned revenue is
    // revenue from completed appointments attributed to a campaign.
    const advertisingRoiSnapshot = this.advertisingRoiSnapshot;
    const roiReady = advertisingRoiSnapshot?.status === 'completed' && advertisingRoiSnapshot?.result;
    const roiData = roiReady ? advertisingRoiSnapshot.result : null;
    const jalaliMonthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    const roiTimeline = roiReady ? (roiData?.timeline || []) : [];
    const roiMax = Math.max(0, ...roiTimeline.flatMap(row => [Number(row.cost || 0), Number(row.revenue || 0)]));
    const roiBars = roiTimeline.map((row) => ({
      n: jalaliMonthNames[Math.max(0, Number(String(row.month || '').slice(5, 7)) - 1)] || row.month,
      ch: (roiMax > 0 ? Math.max(3, Math.round(Number(row.cost || 0) / roiMax * 100)) : 0) + 'px',
      rh: (roiMax > 0 ? Math.max(3, Math.round(Number(row.revenue || 0) / roiMax * 100)) : 0) + 'px',
      cc: '#fca5a5',
      rc: '#22c55e'
    }));
    const roiMoney = value => {
      return toman(value || 0);
    };

    // loyal
    const loyaltySnapshot = this.loyaltySnapshot;
    const loyaltyReady = loyaltySnapshot?.status === 'completed' && loyaltySnapshot?.result;
    const loyaltyPeriod = loyaltyReady ? loyaltySnapshot.result[String(S.loyalRange)] : null;
    const L = {tot:Number(loyaltyPeriod?.total || 0),ret:Number(loyaltyPeriod?.returned || 0),lost:Number(loyaltyPeriod?.churned || 0)};
    const loyP = loyaltyReady && L.tot ? L.ret / L.tot : 0;

    // cancel
    const cancellationSnapshot = this.cancellationSnapshot;
    const cancellationReady = cancellationSnapshot?.status === 'completed' && cancellationSnapshot?.result;
    const cancellationResult = cancellationReady ? cancellationSnapshot.result : null;

    // staff income + sales-tier targets from staff settings (sales_bonus_tiers)
    const staffIncomeSnapshot = this.staffIncomeSnapshot;
    const staffIncomeReady = staffIncomeSnapshot?.status === 'completed' && staffIncomeSnapshot?.result;
    const staffResultRows = staffIncomeReady && Array.isArray(staffIncomeSnapshot.result.staff) ? staffIncomeSnapshot.result.staff : [];
    const staffResultById = Object.fromEntries(staffResultRows.map(row => [String(row.id), row]));
    const staffColors = ['#2563eb','#0d9488','#7c3aed','#db2777','#f59e0b','#0891b2','#4f46e5'];
    const formatTierLabel = (tier, staffName = '') => {
      const salesTxt = mm(Number(tier.sales_from || 0) / 1000000);
      const bonusTxt = mm(Number(tier.salary_addition || 0) / 1000000);
      const title = tier.title || ('پله ' + fa(Number(tier.index || 1)));
      const base = title + ' · فروش ' + salesTxt + ' · پاداش ' + bonusTxt;
      return staffName ? (String(staffName).split(' ')[0] + ' · ' + base) : base;
    };
    const STAFF = this.staffRoster.map((staff, index) => {
      const result = staffResultById[String(staff.id)] || {};
      const tiers = Array.isArray(result.sales_bonus_tiers) ? result.sales_bonus_tiers : [];
      const sales = staffIncomeReady ? Number(result.sales ?? result.income ?? 0) : null;
      const reachedTier = result.reached_tier || null;
      return {
        id: staff.id,
        n: staff.name,
        ph: staff.avatar_url,
        ac: staffColors[index % staffColors.length],
        v: sales,
        reached: !!result.target_reached,
        reachedTier,
        tiers: (result.sales_bonus_enabled && tiers.length) ? tiers : [],
        salesBonus: Number(result.sales_bonus || 0),
      };
    });
    const staffChips = ['همه'].concat(STAFF.map(s => s.n)).map(n => ({t: n, st: chip(S.staff === n), on: () => this.set({staff: n})}));
    const shown = STAFF.filter(s => S.staff === 'همه' || s.n === S.staff);
    const singleStaffMode = S.staff !== 'همه' && shown.length === 1;
    const visibleTier = singleStaffMode
      ? shown[0].tiers
      : shown.flatMap(s => s.tiers.map(tier => ({...tier, staffName: s.n})));
    const chartMaximum = staffIncomeReady
      ? Math.max(1, ...shown.map(s => Number(s.v || 0)), ...visibleTier.map(t => Number(t.sales_from || 0)))
      : 1;
    const tierBottom = (amount) => (22 + Math.min(125, Math.round(Number(amount || 0) / chartMaximum * 125))) + 'px';
    const staffTierLines = (staffIncomeReady && singleStaffMode)
      ? shown[0].tiers.map(tier => ({
          bottom: tierBottom(tier.sales_from),
          label: formatTierLabel(tier),
        }))
      : [];
    const staffBars = staffIncomeReady ? shown.map(s => {
      const reached = !!s.reached;
      const height = Math.max(4, Math.round(Number(s.v || 0) / chartMaximum * 125));
      const reachedLabel = reached
        ? (s.reachedTier ? ('رسیده به ' + (s.tiers.find(t => Number(t.sales_from) === Number(s.reachedTier.sales_from))?.title || 'تارگت')) : 'رسیده به تارگت')
        : '';
      const tierMarkers = (!singleStaffMode)
        ? s.tiers.map(tier => ({
            bottom: tierBottom(tier.sales_from),
            label: formatTierLabel(tier, s.n),
          }))
        : [];
      const avatarKey = 'staff-income-' + String(s.id);
      return {
        id: s.id,
        avatarKey,
        n: s.n.split(' ')[0],
        initial: String(s.n || '؟').trim().charAt(0) || '؟',
        ph: s.ph || null,
        v: mm(Number(s.v || 0) / 1000000),
        h: height + 'px',
        c: reached ? '#16a34a' : s.ac,
        vc: reached ? '#15803d' : '#334155',
        reached,
        reachedLabel,
        tierMarkers,
      };
    }) : [];
    const overCnt = staffIncomeReady ? shown.filter(s => s.reached).length : null;
    let staffOverTxt = '-- نفر به تارگت رسیدند';
    if (staffIncomeReady) {
      if (singleStaffMode) {
        const person = shown[0];
        if (person.reached && person.reachedTier) {
          const title = person.tiers.find(t => Number(t.sales_from) === Number(person.reachedTier.sales_from))?.title || 'پله';
          staffOverTxt = '⭐ ' + title + ' · پاداش ' + mm(Number(person.salesBonus || person.reachedTier.salary_addition || 0) / 1000000);
        } else if (person.tiers.length) {
          staffOverTxt = 'هنوز به پله فروش نرسیده است';
        } else {
          staffOverTxt = 'پله فروشی برای این پرسنل تعریف نشده';
        }
      } else {
        staffOverTxt = overCnt > 0 ? ('⭐ ' + fa(overCnt) + ' نفر به تارگت رسیدند') : 'هنوز کسی به تارگت نرسیده است';
      }
    }

    // satisfaction: one row per active rating question (title + % via avg score / 5 × 100)
    const satisfactionReady = this.satisfactionSnapshot?.status === 'completed';
    const satisfactionResult = satisfactionReady ? (this.satisfactionSnapshot.result || {}) : {};
    const qcQuestions = (satisfactionResult.questions || []).map(question => ({
      key:question.key,
      title:question.question,
      percentage:pc(Number(question.percentage || 0))
    }));

    // ad channels
    const adChannelsReady = this.advertisingChannelsSnapshot?.status === 'completed';
    const ADS = adChannelsReady ? (this.advertisingChannelsSnapshot?.result?.channels || []) : [];
    const adMaxCompleted = Math.max(1,...ADS.map(row => Number(row.completed || 0)));
    const adMoney = value => {
      const amount = Number(value || 0);
      return toman(amount);
    };
    const channelIcon = name => String(name || '').includes('اینستاگرام') ? 'https://cdn.simpleicons.org/instagram/E4405F'
      : String(name || '').includes('گوگل') ? 'https://cdn.simpleicons.org/google'
      : String(name || '').includes('یوتیوب') ? 'https://cdn.simpleicons.org/youtube/FF0000'
      : 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><circle cx="12" cy="12" r="9"/><path fill="white" d="M7 11h10v2H7z"/></svg>';
    const adRows = ADS.map(row => {
      const ratio = row.ratio == null ? null : Number(row.ratio);
      return {n:row.name,lg:row.icon_url || channelIcon(row.name),cnt:fa(row.completed || 0),cost:(Number(row.cost || 0)>0?adMoney(row.cost):'—'),rev:adMoney(row.revenue || 0),
        roi:(ratio == null?'—':ratio.toLocaleString('fa-IR',{maximumFractionDigits:2})+'x'),
        rc:ratio == null?'#64748b':(ratio>=1?'#15803d':'#b91c1c'),w:Math.round(Number(row.completed || 0)/adMaxCompleted*100)+'%'};
    });

    // docs — avatar_url comes from Doctor profile (منابع) keyed by doctor_id
    const doctorPerformanceReady = this.doctorPerformanceSnapshot?.status === 'completed';
    const DOCS = doctorPerformanceReady ? (this.doctorPerformanceSnapshot?.result?.doctors || []) : [];
    const realConversions = DOCS.filter(d => d.conversion_rate != null).map(d => Number(d.conversion_rate));
    const bestConv = realConversions.length ? Math.max(...realConversions) : null;
    const docRows = DOCS.map(d => {
      const conv = Math.max(0, Math.min(100, Number(d.conversion_rate || 0)));
      const id = d.doctor_id;
      const avatarKey = 'doctor-' + String(id);
      return {id, avatarKey, n:d.name, initial:String(d.name || '؟').trim().charAt(0) || '؟', ph:d.avatar_url || null, bring:reportMoney(Number(d.revenue || 0)/1000000),pors:reportMoney(Number(d.commission || 0)/1000000),fix:reportMoney(Number(d.salary || 0)/1000000),pay:reportMoney(Number(d.payment_total || 0)/1000000),
        cons:fa(d.consultations || 0),done:fa(d.converted || 0),convP:(d.conversion_rate==null?'--٪':pc(conv)),convW:conv+'%',
        cc:conv>=60?'#15803d':conv>=40?'#f59e0b':'#dc2626',bd:bestConv!==null && conv===bestConv?'inline-block':'none'};
    });

    // photos
    const photoReady = this.photoSnapshot?.status === 'completed';
    const PH = photoReady ? (this.photoSnapshot?.result?.photo_quality || []) : [];
    const phRows = PH.map(row => {
      const ratio = Number(row.percent || 0) / 100;
      return {n:row.tag,txt:'از '+fa(row.total || 0)+' عکس، '+fa(row.qualified || 0)+' برترین —',p:pc(row.percent || 0),
        w:Math.max(0,Math.min(100,Number(row.percent || 0)))+'%',c:ratio>=0.3?'#16a34a':ratio>=0.15?'#f59e0b':'#dc2626'};
    });
    const phTotal = photoReady ? 'مجموع: '+fa(PH.reduce((sum,row)=>sum+Number(row.total || 0),0))+' ثبت ناحیه عکس' : '';

    // cities
    const CT = [
      {n:'تهران', cnt:268, rev:920, x:'62%', y:'29%'},
      {n:'مشهد', cnt:74, rev:240, x:'20%', y:'25%'},
      {n:'اصفهان', cnt:61, rev:205, x:'60%', y:'49%'},
      {n:'شیراز', cnt:48, rev:160, x:'56%', y:'69%'},
      {n:'تبریز', cnt:42, rev:140, x:'88%', y:'13%'},
      {n:'کرج', cnt:39, rev:128, x:'67%', y:'24%'},
      {n:'اهواز', cnt:31, rev:102, x:'76%', y:'58%'},
      {n:'قم', cnt:27, rev:89, x:'65%', y:'36%'},
      {n:'رشت', cnt:24, rev:80, x:'71%', y:'18%'},
      {n:'ساری', cnt:21, rev:70, x:'53%', y:'23%'},
      {n:'کرمان', cnt:19, rev:63, x:'33%', y:'65%'},
      {n:'یزد', cnt:17, rev:56, x:'47%', y:'54%'},
      {n:'همدان', cnt:14, rev:47, x:'77%', y:'35%'},
      {n:'ارومیه', cnt:12, rev:40, x:'94%', y:'17%'},
      {n:'بندرعباس', cnt:10, rev:34, x:'37%', y:'85%'}
    ];
    const CT0 = [
      {n:'اردبیل', x:'78%', y:'12%'},
      {n:'زنجان', x:'77%', y:'22%'},
      {n:'قزوین', x:'69%', y:'25%'},
      {n:'گرگان', x:'47%', y:'21%'},
      {n:'بجنورد', x:'32%', y:'17%'},
      {n:'سمنان', x:'52%', y:'29%'},
      {n:'سنندج', x:'85%', y:'31%'},
      {n:'کرمانشاه', x:'84%', y:'38%'},
      {n:'اراک', x:'71%', y:'39%'},
      {n:'ایلام', x:'88%', y:'43%'},
      {n:'خرم‌آباد', x:'77%', y:'43%'},
      {n:'بیرجند', x:'22%', y:'47%'},
      {n:'شهرکرد', x:'65%', y:'51%'},
      {n:'یاسوج', x:'61%', y:'62%'},
      {n:'زاهدان', x:'13%', y:'70%'},
      {n:'بوشهر', x:'65%', y:'74%'}
    ];
    const cityReady = this.cityStatisticsSnapshot?.status === 'completed';
    const cityData = cityReady ? (this.cityStatisticsSnapshot?.result?.rows || []) : [];
    const cityRows = cityReady
      ? cityData.map(c=>({n:c.city,cnt:fa(c.count),rev:mm(c.payment/1000000),w:c.percent+'%'}))
      : [];
    const cityMax = Math.max(1,...cityData.map(c=>c.count||0));
    const cityDots = cityReady
      ? CT.concat(CT0).filter(c=>cityData.some(x=>x.city===c.n)).map(c=>{const d=cityData.find(x=>x.city===c.n);return {n:c.n,x:c.x,y:c.y,r:Math.max(9,Math.round((d?.count||0)/cityMax*28))+'px',bg:'rgba(37,99,235,0.45)',bc:'#2563eb',tc:'#334155'}})
      : [];

    // ages
    const ageReady = this.ageStatisticsSnapshot?.status === 'completed';
    const ageData = ageReady ? (this.ageStatisticsSnapshot?.result?.rows || []) : [];
    const ageMax = Math.max(1, ...ageData.map(a => a.count || 0));
    const ageRows = ageData.map(a => ({rng: a.range || a.rng, cnt: fa(a.count || 0), pay: mm((a.payment || 0) / 1000000), svc: a.top_service || '—', w: Math.round((a.count || 0) / ageMax * 100)+'%'}));

    // sections: درآمد و سود واقعی هر بخش اصلی، از همان محاسبهٔ «پردرآمدترین خدمات»
    const sectionsReady = this.topServicesSnapshot?.status === 'completed' && Array.isArray(this.topServicesSnapshot?.result?.sections);
    const SE = sectionsReady ? this.topServicesSnapshot.result.sections : [];
    const seSum = SE.reduce((total, section) => total + Math.max(0, Number(section.revenue) || 0), 0) || 1;
    const seMax = Math.max(1, ...SE.map(section => Math.max(0, Number(section.revenue) || 0)));
    const sectRows = sectionsReady
      ? SE.map(section => {
          const revenue = Number(section.revenue) || 0;
          const profit = Number(section.profit) || 0;
          return {n: section.name, rev: reportMoney(revenue / 1000000), prof: reportMoney(profit / 1000000), p: pc(Math.round(Math.max(0, revenue) / seSum * 100)),
            wp: Math.round(Math.max(0, profit) / seMax * 100) + '%', wr: Math.round(Math.max(0, revenue - Math.max(0, profit)) / seMax * 100) + '%'};
        })
      : [];

    // top services
    const topServicesReady = this.topServicesSnapshot?.status === 'completed';
    const SV = topServicesReady ? (this.topServicesSnapshot?.result?.rows || []) : [];
    const maxServiceValue = Math.max(1, ...SV.flatMap(s => [Number(s.revenue)||0, Math.max(0,Number(s.profit)||0)]));
    const serviceRow = (s, i) => ({key:s.name, i:fa(i+1), n:s.name, rev:mm((Number(s.revenue)||0)/1000000), prof:mm((Number(s.profit)||0)/1000000),
      wr:Math.round((Number(s.revenue)||0)/maxServiceValue*100)+'%', wp:Math.round(Math.max(0,Number(s.profit)||0)/maxServiceValue*100)+'%'});
    const allServiceRows = SV.slice().sort((a,b)=>Number(b.revenue)-Number(a.revenue)).map(serviceRow);
    const svcRows = allServiceRows;

    // cac
    const acquisitionReady = this.customerAcquisitionSnapshot?.status === 'completed';
    const acquisition = acquisitionReady ? this.customerAcquisitionSnapshot.result : null;
    const cacMoney = value => value == null ? '--' : toman(value);
    const cacPer = acquisition ? cacMoney(acquisition.cost_per_appointment) : '--';
    const cacCampaignRows = (acquisition?.campaigns || []).map(row => ({
      id: row.campaign_id,
      name: row.name,
      cost: cacMoney(row.advertising_cost),
      appointments: fa(row.appointments || 0) + ' نوبت',
      per: row.cost_per_appointment == null ? 'بدون نوبت' : cacMoney(row.cost_per_appointment),
      rate: row.acquisition_rate == null ? '--٪' : pc(row.acquisition_rate)
    }));

    // campaigns
    const CAMPS = dashboardReady ? (S.reportSummary?.campaigns || []).map(row => ({
      n:row.name,date:row.date || '',dateN:String(row.date || ''),cost:Number(row.cost || 0),quality:Number(row.quality || 0),qualityLabel:row.quality_label || 'ضعیف',leads:Number(row.leads || 0),d1:Number(row.interest_counts?.['1'] || 0),d2:Number(row.interest_counts?.['2'] || 0),d3:Number(row.interest_counts?.['3'] || 0)
    })) : [];
    const campaignPerformanceReady = this.campaignPerformanceSnapshot?.status === 'completed';
    const campaignPerformanceRows = campaignPerformanceReady
      ? (this.campaignPerformanceSnapshot?.result?.campaigns || [])
      : [];
    const cpRows = campaignPerformanceRows.map(row => {
      const rate = Math.max(0, Math.min(100, Number(row.performance_rate || 0)));
      return {id:row.campaign_id,n:row.name,leads:fa(row.leads || 0),appointments:fa(row.appointments || 0),rate:pc(rate),w:rate+'%',c:rate>=50?'#15803d':rate>=25?'#d97706':'#dc2626',bar:rate>=50?'linear-gradient(90deg,#4ade80,#16a34a)':rate>=25?'linear-gradient(90deg,#fbbf24,#f59e0b)':'linear-gradient(90deg,#fca5a5,#ef4444)'};
    });
    const interestMax = Math.max(1,...CAMPS.flatMap(c => [c.d1,c.d2,c.d3]));
    const cdRows = CAMPS.map(c => ({n: c.n, cost: dashboardReady ? cacMoney(c.cost) : '--',
      h1: Math.round(c.d1/interestMax*120)+'px', h2: Math.round(c.d2/interestMax*120)+'px', h3: Math.round(c.d3/interestMax*120)+'px'}));

    // staff appointments — avatar_url from Staff profile (منابع) keyed by staff_id
    const staffAppointmentsReady = this.staffAppointmentsSnapshot?.status === 'completed';
    const staffById = Object.fromEntries(STAFF.map(s => [String(s.id), s]));
    const staffAppointmentRows = staffAppointmentsReady ? (this.staffAppointmentsSnapshot?.result?.staff || []) : [];
    const aptRows = staffAppointmentRows.map(row => {
      const rate = Math.max(0, Math.min(100, Number(row.conversion_rate || 0)));
      const color = rate >= 60 ? '#15803d' : rate >= 40 ? '#f59e0b' : '#dc2626';
      const staffId = row.staff_id;
      const roster = staffById[String(staffId)] || null;
      // Prefer snapshot avatar for that staff_id; fall back to roster by the same id only.
      const photo = row.avatar_url || roster?.ph || null;
      const avatarKey = 'staff-apt-' + String(staffId ?? row.row_id);
      return {id:row.row_id || staffId, staffId, avatarKey, n:row.name, initial:String(row.name || '؟').trim().charAt(0) || '؟', ph:photo,
        bring:reportMoney(Number(row.revenue || 0)/1000000),pors:reportMoney(Number(row.commission || 0)/1000000),
        fix:reportMoney(Number(row.salary || 0)/1000000),pay:reportMoney(Number(row.payment_total || 0)/1000000),
        cnt:fa(row.appointments || 0),rate:(row.conversion_rate==null?'--٪':pc(rate)),w:rate+'%',color,
        rateTitle:`${fa(row.completed || 0)} نوبت انجام‌شده از ${fa(row.appointments || 0)} نوبت مشاور`};
    });

    // capacity
    const normalizedCapacity = String(S.cap ?? '').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٬,\s]/g,'');
    const capN = Math.max(1, parseFloat(normalizedCapacity) || 2500);
    const filled = dashboardReady ? Math.max(0, Number(actualRevenue || 0)) : 0;
    const capF = dashboardReady ? Math.min(1, filled / capN) : 0;

    // gender
    const customerOverview = dashboardReady ? (S.reportSummary?.customer_overview || {}) : {};
    const genderData = customerOverview.gender || {};
    const femaleCount = dashboardReady ? Number(genderData.female || 0) : 0;
    const maleCount = dashboardReady ? Number(genderData.male || 0) : 0;
    const gTot = femaleCount + maleCount;
    const fP = gTot ? femaleCount / gTot : 0;
    const mP = gTot ? maleCount / gTot : 0;
    const unknownGenderCount = dashboardReady ? Number(genderData.unknown || 0) : 0;
    const gF = {p:dashboardReady?pc(Math.round(fP*100)):'—',cnt:dashboardReady?fa(femaleCount):'—',y:String(Math.round(200*(1-fP)))};
    const gM = {p:dashboardReady?pc(Math.round(mP*100)):'—',cnt:dashboardReady?fa(maleCount):'—',y:String(Math.round(200*(1-mP)))};

    // new/old
    const customerFrequency = customerOverview.customers || {};
    const nNew = dashboardReady ? Number(customerFrequency.new || 0) : 0;
    const nOld = dashboardReady ? Number(customerFrequency.old || 0) : 0;
    const nT = nNew + nOld;
    const [ns1, ns2] = segs(nT ? [nNew/nT, nOld/nT] : [0,0]);

    // status — color by stable status name/key (same source as booking table + Timeline), never by array index
    const ST = (dashboardReady ? (customerOverview.statuses || []) : [])
      .slice(0, 6)
      .map(row => ({
        n: row.name,
        v: Number(row.count || 0),
        c: appointmentStatusChartColor(row.name),
      }));
    while (dashboardReady && ST.length < 6) ST.push({ n: '—', v: 0, c: appointmentStatusChartColor('') });
    const stT = ST.reduce((a, s) => a + s.v, 0);
    const stSegs = segs(ST.map(s => (stT ? s.v / stT : 0))).map((seg, i) => ({ ...seg, c: ST[i].c }));
    const stLegend = ST.filter(s => s.v > 0).map(s => ({
      n: s.n,
      c: s.c,
      cnt: dashboardReady ? fa(s.v) : '—',
      p: dashboardReady ? pc(Math.round(s.v / stT * 100)) : '—',
    }));

    const tabSt = a => 'border:none;border-radius:999px;padding:8px 22px;font-size:13px;cursor:pointer;font-weight:' + (a?'800':'600') + ';background:' + (a?(dark?'#1a1c22':'#ffffff'):'transparent') + ';color:' + (a?(dark?'#4f8cff':'#1d4ed8'):(dark?'#9aa0a6':'#64748b')) + ';box-shadow:' + (a?(dark?'0 1px 4px rgba(0,0,0,0.35)':'0 1px 4px rgba(15,23,42,0.1)'):'none');

    return {
      // tabs
      isR: S.tab === 'r', isA: S.tab === 'a',
      tR: tabSt(S.tab === 'r'), tA: tabSt(S.tab === 'a'),
      onTR: () => this.set({tab:'r'}), onTA: () => this.set({tab:'a'}),
      // date bar
      fromV: S.from, toV: S.to,
      onFrom: e => this.updateReportDate('from', e.target.value), onTo: e => this.updateReportDate('to', e.target.value),
      // filters
      filterOpen: S.filterOpen,
      openFilter: () => this.set({filterOpen: true}),
      closeFilter: () => this.set({filterOpen: false}),
      stopProp: e => e.stopPropagation(),
      clearF: () => this.set({filters:{}, bdFrom:'', bdTo:''}),
      filtCnt: fa(fCount), filtBadge: fCount ? 'inline-flex' : 'none',
      bdFrom: S.bdFrom, bdTo: S.bdTo,
      onBdF: e => this.set({bdFrom: e.target.value}), onBdT: e => this.set({bdTo: e.target.value}),
      fgroups, qgroups, dgroups, selTags, selDsp: selTags.length ? 'flex' : 'none',
      exportX: () => this.exportExcel(),
      // manage
      toggleMng: () => this.set(s => ({mngOpen: !s.mngOpen})),
      mngDsp: S.mngOpen ? 'flex' : 'none',
      dashList,
      // kpis & months
      kpis, estV, estNote, monthsV,
      kpiReady: !!dashboardReady,
      // وقتی یکی از باکس‌های مشتریان (گنجایش، جنسیت، ...) محاسبه را شروع کرده، فقط همان باکس لودینگ می‌گیرد.
      kpiCalculating: !this.overviewLoadingSource && ['queued','processing'].includes(dashboardSnapshot?.status),
      kpiProgress: Number(dashboardSnapshot?.progress || 0),
      kpiStage: dashboardSnapshot?.stage || '',
      kpiCompletedAt: this.formatSnapshotDate(dashboardSnapshot?.completed_at),
      kpiSubtitle: dashboardSnapshot?.status === 'failed'
        ? (dashboardSnapshot.error || 'محاسبه ناموفق بود؛ دوباره تلاش کنید')
        : (dashboardReady
          ? `بازه ${String(S.from || '').replaceAll('-', '/')} تا ${String(S.to || '').replaceAll('-', '/')}${dashboardSnapshot?.completed_at ? ` · ${this.formatSnapshotDate(dashboardSnapshot.completed_at)}` : ''}`
          : 'برای محاسبه داده‌های واقعی، روی آیکن بزنید'),
      billRows, billSumV, billReady: !!expenses, billCompletedAt: this.formatSnapshotDate(this.expenseSnapshot?.completed_at),
      otherExpensesDetailOpen: this.otherExpensesDetailOpen,
      closeOtherExpensesDetail: () => { this.otherExpensesDetailOpen = false; },
      // dash maps
      o, dsp, ck, hide, dh, dv, dp,
      // ctype
      cs1, cs2, cs3, cs4, ctLegend, ctTotal: ctReady ? fa(ctT) : '--',
      ctReady,
      ctChecking: this.customerSegmentLoading && !['queued','processing'].includes(segmentSnapshot?.status),
      ctCalculating: ['queued','processing'].includes(segmentSnapshot?.status),
      ctBusy: ['queued','processing'].includes(segmentSnapshot?.status),
      ctProgress: Number(segmentSnapshot?.progress || 0),
      ctStage: segmentSnapshot?.stage || (this.customerSegmentLoading ? 'در حال بررسی نتیجه ذخیره‌شده…' : 'برای این بازه هنوز گزارشی محاسبه نشده است.'),
      ctError: segmentSnapshot?.error || '',
      ctCompletedAt: this.formatSnapshotDate(segmentSnapshot?.completed_at),
      ctActionLabel: ['queued','processing'].includes(segmentSnapshot?.status) ? `${fa(segmentSnapshot?.progress || 0)}٪` : (segmentSnapshot?.status === 'completed' ? 'به‌روزرسانی' : 'محاسبه'),
      // roi
      roiCost: roiReady ? roiMoney(roiData.cost) : '--',
      roiRev: roiReady ? roiMoney(roiData.revenue) : '--',
      roiX: roiReady && roiData?.ratio != null ? Number(roiData.ratio).toLocaleString('fa-IR', {maximumFractionDigits: 2}) : '--',
      roiOkTxt: roiReady ? (roiData.cost <= 0 ? 'بدون هزینه ثبت‌شده' : (roiData.returned ? '✓ هزینه برگشته' : 'هزینه برنگشته')) : '--',
      roiBadgeStyle: 'border-radius:999px;padding:3px 12px;font-size:11.5px;font-weight:800;background:' + (!roiReady || roiData.cost <= 0 ? '#f1f5f9' : (roiData.returned ? '#dcfce7' : '#fee2e2')) + ';color:' + (!roiReady || roiData.cost <= 0 ? '#64748b' : (roiData.returned ? '#15803d' : '#b91c1c')),
      roiReady: !!roiReady,
      roiCalculating: ['queued','processing'].includes(advertisingRoiSnapshot?.status),
      roiProgress: Number(advertisingRoiSnapshot?.progress || 0),
      roiStage: advertisingRoiSnapshot?.stage || '',
      roiBars,
      // loyal
      l3st: chip(S.loyalRange === 3), l6st: chip(S.loyalRange === 6),
      onL3: () => this.set({loyalRange: 3}), onL6: () => this.set({loyalRange: 6}),
      loyTot: loyaltyReady ? fa(L.tot) : '--', loyRet: loyaltyReady ? fa(L.ret) : '--', loyLost: loyaltyReady ? fa(L.lost) : '--',
      loyP: loyaltyReady ? pc(Number(loyaltyPeriod?.return_rate || 0)) : '--٪', loyRetW: loyaltyReady ? Math.round(loyP*100)+'%' : '0%',
      loyaltyReady: !!loyaltyReady,
      loyaltyCalculating: ['queued','processing'].includes(loyaltySnapshot?.status),
      loyaltyProgress: Number(loyaltySnapshot?.progress || 0),
      loyaltyStage: loyaltySnapshot?.stage || '',
      loyaltyCompletedAt: this.formatSnapshotDate(loyaltySnapshot?.completed_at),
      // cancel
      cnCame: cancellationReady ? fa(cancellationResult.attended || 0) + ' نفر' : '-- نفر',
      cnCanc: cancellationReady ? fa(cancellationResult.cancelled || 0) + ' نفر' : '-- نفر',
      cnRate: cancellationReady ? pc(cancellationResult.rate || 0) : '--٪',
      cnDa: arc(cancellationReady ? Number(cancellationResult.rate || 0) / 100 : 0),
      cnLoading: S.cancellationLoading,
      cnReady: cancellationReady,
      cnCompletedAt: this.formatSnapshotDate(cancellationSnapshot?.completed_at),
      // staff
      staffChips, staffBars, staffTierLines,
      staffSum: staffIncomeReady ? mm(shown.reduce((sum,staff) => sum + Number(staff.v || 0), 0) / 1000000) : '--', staffOverTxt,
      staffIncomeReady: !!staffIncomeReady,
      staffIncomeCalculating: ['queued','processing'].includes(staffIncomeSnapshot?.status),
      staffIncomeCompletedAt: this.formatSnapshotDate(staffIncomeSnapshot?.completed_at),
      staffIncomeProgress: Number(staffIncomeSnapshot?.progress || 0),
      staffIncomeStage: staffIncomeSnapshot?.stage || '',
      // qc
      qcPct: satisfactionReady ? pc(Number(satisfactionResult.percentage || 0)) : '—',
      qcResponses:satisfactionReady ? fa(Number(satisfactionResult.responses_count || 0)) : '—',
      qcQuestions,
      qcReady:satisfactionReady,
      qcLoading:this.satisfactionLoading,
      qcCompletedAt:this.formatSnapshotDate(this.satisfactionSnapshot?.completed_at),
      // others
      adRows, adChannelsReady, adChannelsCalculating:this.advertisingChannelsLoading,
      adChannelsCompletedAt:this.formatSnapshotDate(this.advertisingChannelsSnapshot?.completed_at),
      docRows, doctorPerformanceReady, doctorPerformanceCalculating:this.doctorPerformanceLoading,
      doctorPerformanceCompletedAt:this.formatSnapshotDate(this.doctorPerformanceSnapshot?.completed_at),
      phRows, phTotal,
      photoReady, photoCalculating: this.photoLoading,
      photoCompletedAt: this.formatSnapshotDate(this.photoSnapshot?.completed_at),
      cityRows, cityDots, cityReady, cityCalculating:this.cityStatisticsLoading, cityProgress:this.cityStatisticsProgress, cityCompletedAt:this.formatSnapshotDate(this.cityStatisticsSnapshot?.completed_at), ageRows, ageReady, ageCalculating:this.ageStatisticsLoading, ageProgress:this.ageStatisticsProgress, ageCompletedAt:this.formatSnapshotDate(this.ageStatisticsSnapshot?.completed_at), avgAge: ageReady ? Number(this.ageStatisticsSnapshot?.result?.average_age || 0).toLocaleString('fa-IR') + ' سال' : '—',
      sectRows, svcRows, topServicesReady, topServicesCalculating:this.topServicesLoading,
      topServicesCompletedAt:this.formatSnapshotDate(this.topServicesSnapshot?.completed_at),
      topServicesError:this.topServicesSnapshot?.status === 'failed' ? (this.topServicesSnapshot.error || 'محاسبه گزارش خدمات ناموفق بود.') : '',
      hasMoreServices:allServiceRows.length>6, servicesExpanded:S.topServicesExpanded, servicesMoreLabel:S.topServicesExpanded?'نمایش کمتر':'نمایش بیشتر',
      toggleServices:()=>this.set({topServicesExpanded:!S.topServicesExpanded}),
      cacPer, cacCost: acquisition ? cacMoney(acquisition.advertising_cost) : '--', cacAppointments: acquisition ? fa(acquisition.appointments || 0) + ' نفر' : '--', cacCampaignRows,
      cacRate: acquisition?.acquisition_rate == null ? '--٪' : pc(acquisition.acquisition_rate),
      cacReady: acquisitionReady, cacCalculating: this.customerAcquisitionLoading,
      cacCompletedAt: this.formatSnapshotDate(this.customerAcquisitionSnapshot?.completed_at),
      cpRows, cdRows, aptRows, staffAppointmentsReady, staffAppointmentsCalculating:this.staffAppointmentsLoading,
      staffAppointmentsCompletedAt:this.formatSnapshotDate(this.staffAppointmentsSnapshot?.completed_at),
      staffAppointmentsError:this.staffAppointmentsSnapshot?.status === 'failed' ? (this.staffAppointmentsSnapshot.error || 'محاسبه گزارش وقت‌دهی پرسنل ناموفق بود.') : '',
      campaignPerformanceReady,
      campaignPerformanceCalculating: this.campaignPerformanceLoading,
      campaignPerformanceCompletedAt: this.formatSnapshotDate(this.campaignPerformanceSnapshot?.completed_at),
      // capacity
      capDa:arc(capF),capW:Math.round(capF*100)+'%',capPct:dashboardReady?pc(Math.round(capF*100)):'—',capFilled:dashboardReady?mm(filled):'—',
      capRemain:dashboardReady?mm(Math.max(0,capN-filled)):'—',capV:capN.toLocaleString('fa-IR'),onCap:e=>this.set({cap:e.target.value}),saveCap:()=>this.saveMonthlyCapacity(),
      // gender / newold / status
      gF, gM, gUnknown:dashboardReady&&unknownGenderCount?fa(unknownGenderCount):'',
      ns1,ns2,noTotal:dashboardReady?fa(nT):'—',noNew:dashboardReady?fa(nNew):'—',noOld:dashboardReady?fa(nOld):'—',
      noNewP:dashboardReady&&nT?pc(Math.round(nNew/nT*100)):'—',noOldP:dashboardReady&&nT?pc(Math.round(nOld/nT*100)):'—',
      stSegs,
      stLegend,stTotal:dashboardReady?fa(stT):'—',overviewReady:dashboardReady,
      overviewBusy:this.dashboardLoading || ['queued','processing'].includes(dashboardSnapshot?.status),
      capacityCalculating:this.overviewLoadingSource==='capacity' && (this.dashboardLoading || ['queued','processing'].includes(dashboardSnapshot?.status)),
      genderCalculating:this.overviewLoadingSource==='gender' && (this.dashboardLoading || ['queued','processing'].includes(dashboardSnapshot?.status)),
      newOldCalculating:this.overviewLoadingSource==='newold' && (this.dashboardLoading || ['queued','processing'].includes(dashboardSnapshot?.status)),
      statusCalculating:this.overviewLoadingSource==='status' && (this.dashboardLoading || ['queued','processing'].includes(dashboardSnapshot?.status)),
      overviewCompletedAt:dashboardReady?this.formatSnapshotDate(dashboardSnapshot?.completed_at):''
    };
    }
  },
  mounted() {
    this.syncDarkUi();
    this._darkMo = new MutationObserver(() => this.syncDarkUi());
    this._darkMo.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    const appEl = document.getElementById('app');
    if (appEl) this._darkMo.observe(appEl, { attributes: true, attributeFilter: ['class'] });
    this.loadDashboardSnapshot();
    this.loadCustomerSegmentSnapshot();
    this.loadStaffRoster();
    this.loadStaffIncomeSnapshot();
    this.loadAdvertisingRoiSnapshot();
    this.loadLoyaltySnapshot();
    this.loadCancellationSnapshot();
    this.loadPhotoAnalysisSnapshot();
    this.loadExpenseSnapshot();
    this.loadCustomerAcquisitionSnapshot();
    this.loadCampaignPerformanceSnapshot();
    this.loadAdvertisingChannelsSnapshot();
    this.loadDoctorPerformanceSnapshot();
    this.loadAgeStatisticsSnapshot(); this.loadCityStatisticsSnapshot(); this.loadTopServicesSnapshot();
    this.loadStaffAppointmentsSnapshot();
    this.loadSatisfactionSnapshot();
    this.loadStaffTarget();
    this._onPointerDown = e => {
      const h = e.target && e.target.closest && e.target.closest('[data-drag-handle]');
      if (!h) return;
      const card = h.closest('[draggable]');
      if (!card) return;
      e.preventDefault();
      const idx = parseInt(getComputedStyle(card).order || '0', 10);
      const dragKey = this.s.order[idx];
      if (!dragKey) return;
      this._drag = dragKey;
      card.setAttribute('data-drag', '1');
      document.body.style.userSelect = 'none';
      document.body.style.cursor = 'grabbing';
      const move = ev => {
        const el = document.elementFromPoint(ev.clientX, ev.clientY);
        if (!el || !el.closest) return;
        const over = el.closest('[draggable]');
        if (!over || over === card) return;
        const cur = this.s.order;
        const ti = parseInt(getComputedStyle(over).order || '0', 10);
        const tKey = cur[ti];
        if (!tKey || tKey === this._drag) return;
        const ci = cur.indexOf(this._drag);
        const ord = cur.filter(x => x !== this._drag);
        const ins = ord.indexOf(tKey);
        ord.splice(ci < ti ? ins + 1 : ins, 0, this._drag);
        if (ord.join(',') !== cur.join(',')) this.set({order: ord});
      };
      const up = () => {
        document.removeEventListener('pointermove', move);
        document.removeEventListener('pointerup', up);
        document.removeEventListener('pointercancel', up);
        document.body.style.userSelect = '';
        document.body.style.cursor = '';
        this._drag = null;
        document.querySelectorAll('[data-drag]').forEach(x => x.removeAttribute('data-drag'));
      };
      document.addEventListener('pointermove', move);
      document.addEventListener('pointerup', up);
      document.addEventListener('pointercancel', up);
    };
    document.addEventListener('pointerdown', this._onPointerDown, true);
  },
  unmounted() {
    if (this._darkMo) this._darkMo.disconnect();
    clearTimeout(this.customerSegmentPollingTimer);
    clearTimeout(this.dashboardPollingTimer);
    clearTimeout(this.staffIncomePollingTimer);
    clearTimeout(this.advertisingRoiPollingTimer);
    clearTimeout(this.loyaltyPollingTimer);
    this.stopCustomerSegmentRealtime();
    this.stopDashboardRealtime();
    this.stopStaffIncomeRealtime();
    this.stopAdvertisingRoiRealtime();
    this.stopLoyaltyRealtime();
    document.removeEventListener('pointerdown', this._onPointerDown, true);
  },
  beforeDestroy() {
    clearTimeout(this.customerSegmentPollingTimer);
    clearTimeout(this.dashboardPollingTimer);
    clearTimeout(this.staffIncomePollingTimer);
    clearTimeout(this.advertisingRoiPollingTimer);
    clearTimeout(this.loyaltyPollingTimer);
    this.stopCustomerSegmentRealtime();
    this.stopDashboardRealtime();
    this.stopStaffIncomeRealtime();
    this.stopAdvertisingRoiRealtime();
    this.stopLoyaltyRealtime();
    document.removeEventListener('pointerdown', this._onPointerDown, true);
  },
  methods: {
    syncDarkUi() {
      const appDark = !!document.getElementById('app')?.classList.contains('dark');
      const bodyDark = document.body.classList.contains('dark');
      this.isDarkUi = appDark || bodyDark || localStorage.getItem('darkMode') === 'true';
    },

    async selectReportMonthKey(monthKey) {
      const [year, month] = String(monthKey).split('-').map(Number);
      const jalaliLeap = [1, 5, 9, 13, 17, 22, 26, 30].includes((year + 12) % 33);
      const lastDay = month <= 6 ? 31 : (month <= 11 ? 30 : (jalaliLeap ? 30 : 29));
      this._customerSegmentRequestId = (this._customerSegmentRequestId || 0) + 1;
      this._dashboardRequestId = (this._dashboardRequestId || 0) + 1;
      this._advertisingRoiRequestId = (this._advertisingRoiRequestId || 0) + 1;
      this._loyaltyRequestId = (this._loyaltyRequestId || 0) + 1;
      this._cancellationRequestId = (this._cancellationRequestId || 0) + 1;
      this._expenseRequestId = (this._expenseRequestId || 0) + 1;
      clearTimeout(this.customerSegmentPollingTimer);
      clearTimeout(this.dashboardPollingTimer);
      clearTimeout(this.staffIncomePollingTimer);
      clearTimeout(this.advertisingRoiPollingTimer);
      clearTimeout(this.loyaltyPollingTimer);
      this.stopCustomerSegmentRealtime();
      this.stopDashboardRealtime();
      this.stopStaffIncomeRealtime();
      this.stopAdvertisingRoiRealtime();
      this.stopLoyaltyRealtime();
      this.customerSegmentSnapshot = null;
      this.dashboardSnapshot = null;
      this.staffIncomeSnapshot = null;
      this.advertisingRoiSnapshot = null;
      this.loyaltySnapshot = null;
      this.cancellationSnapshot = null;
      this.photoSnapshot = null;
      this.expenseSnapshot = null;
      this.billExpandedIds = {};
      this.customerAcquisitionSnapshot = null;
      this.campaignPerformanceSnapshot = null;
      this.advertisingChannelsSnapshot = null;
      this.doctorPerformanceSnapshot = null;
      this.ageStatisticsSnapshot = null;
      this.cityStatisticsSnapshot = null;
      this.topServicesSnapshot = null;
      this.staffAppointmentsSnapshot = null;
      this.satisfactionSnapshot = null;
      this.brokenAvatars = {};
      this.set({from:`${year}/${String(month).padStart(2,'0')}/01`,to:`${year}/${String(month).padStart(2,'0')}/${lastDay}`,cancellationReport:null,cancellationLoading:false,expenseReport:null,expenseLoading:false,expenseError:'',reportSummary:null,reportLoading:false,reportError:''});
      await this.loadDashboardSnapshot();
      if (!['completed','queued','processing'].includes(this.dashboardSnapshot?.status)) await this.calculateDashboardSummary();
      this.loadCustomerSegmentSnapshot();
      this.loadStaffIncomeSnapshot();
      this.loadAdvertisingRoiSnapshot();
      this.loadLoyaltySnapshot();
      this.loadCancellationSnapshot();
      this.loadPhotoAnalysisSnapshot();
      this.loadExpenseSnapshot();
      this.loadCustomerAcquisitionSnapshot();
      this.loadCampaignPerformanceSnapshot();
      this.loadAdvertisingChannelsSnapshot();
      this.loadDoctorPerformanceSnapshot();
      this.loadAgeStatisticsSnapshot(); this.loadCityStatisticsSnapshot(); this.loadTopServicesSnapshot();
      this.loadStaffAppointmentsSnapshot();
      this.loadSatisfactionSnapshot();
    },
    selectReportMonth(index) {
      this._customerSegmentRequestId = (this._customerSegmentRequestId || 0) + 1;
      this._cancellationRequestId = (this._cancellationRequestId || 0) + 1;
      this._expenseRequestId = (this._expenseRequestId || 0) + 1;
      clearTimeout(this.customerSegmentPollingTimer);
      clearTimeout(this.dashboardPollingTimer);
      clearTimeout(this.staffIncomePollingTimer);
      clearTimeout(this.advertisingRoiPollingTimer);
      clearTimeout(this.loyaltyPollingTimer);
      this.stopCustomerSegmentRealtime();
      this.stopDashboardRealtime();
      this.stopStaffIncomeRealtime();
      this.stopAdvertisingRoiRealtime();
      this.stopLoyaltyRealtime();
      const month = String(index + 1).padStart(2, '0');
      this.set({
        monthSel: index,
        from: `1405/${month}/01`,
        to: `1405/${month}/31`,
        cancellationReport: null,
        expenseReport: null,
        expenseLoading: false,
        expenseError: '',
      });
      this.customerSegmentSnapshot = null;
      this.dashboardSnapshot = null;
      this.staffIncomeSnapshot = null;
      this.advertisingRoiSnapshot = null;
      this.loyaltySnapshot = null;
      this.cancellationSnapshot = null;
      this.photoSnapshot = null;
      this.expenseSnapshot = null;
      this.billExpandedIds = {};
      this.customerAcquisitionSnapshot = null;
      this.campaignPerformanceSnapshot = null;
      this.advertisingChannelsSnapshot = null;
      this.doctorPerformanceSnapshot = null;
      this.ageStatisticsSnapshot = null;
      this.cityStatisticsSnapshot = null;
      this.topServicesSnapshot = null;
      this.staffAppointmentsSnapshot = null;
      this.satisfactionSnapshot = null;
      this.brokenAvatars = {};
      this.set({reportSummary:null, reportLoading:false, reportError:''});
      this.loadDashboardSnapshot();
      this.loadCustomerSegmentSnapshot();
      this.loadStaffIncomeSnapshot();
      this.loadAdvertisingRoiSnapshot();
      this.loadLoyaltySnapshot();
      this.loadCancellationSnapshot();
      this.loadPhotoAnalysisSnapshot();
      this.loadExpenseSnapshot();
      this.loadCustomerAcquisitionSnapshot();
      this.loadCampaignPerformanceSnapshot();
      this.loadAdvertisingChannelsSnapshot();
      this.loadDoctorPerformanceSnapshot();
      this.loadAgeStatisticsSnapshot(); this.loadCityStatisticsSnapshot(); this.loadTopServicesSnapshot();
      this.loadStaffAppointmentsSnapshot();
      this.loadSatisfactionSnapshot();
    },
    updateReportDate(key, value) {
      this._reportRequestId = (this._reportRequestId || 0) + 1;
      this._customerSegmentRequestId = (this._customerSegmentRequestId || 0) + 1;
      this._dashboardRequestId = (this._dashboardRequestId || 0) + 1;
      this._staffIncomeRequestId = (this._staffIncomeRequestId || 0) + 1;
      this._advertisingRoiRequestId = (this._advertisingRoiRequestId || 0) + 1;
      this._loyaltyRequestId = (this._loyaltyRequestId || 0) + 1;
      this._cancellationRequestId = (this._cancellationRequestId || 0) + 1;
      this._expenseRequestId = (this._expenseRequestId || 0) + 1;
      clearTimeout(this.customerSegmentPollingTimer);
      clearTimeout(this.dashboardPollingTimer);
      clearTimeout(this.staffIncomePollingTimer);
      clearTimeout(this.advertisingRoiPollingTimer);
      clearTimeout(this.loyaltyPollingTimer);
      this.stopCustomerSegmentRealtime();
      this.stopDashboardRealtime();
      this.stopStaffIncomeRealtime();
      this.stopAdvertisingRoiRealtime();
      this.stopLoyaltyRealtime();
      this.customerSegmentSnapshot = null;
      this.dashboardSnapshot = null;
      this.staffIncomeSnapshot = null;
      this.advertisingRoiSnapshot = null;
      this.loyaltySnapshot = null;
      this.cancellationSnapshot = null;
      this.photoSnapshot = null;
      this.expenseSnapshot = null;
      this.billExpandedIds = {};
      this.customerAcquisitionSnapshot = null;
      this.campaignPerformanceSnapshot = null;
      this.advertisingChannelsSnapshot = null;
      this.doctorPerformanceSnapshot = null;
      this.ageStatisticsSnapshot = null;
      this.cityStatisticsSnapshot = null;
      this.topServicesSnapshot = null;
      this.staffAppointmentsSnapshot = null;
      this.satisfactionSnapshot = null;
      this.brokenAvatars = {};
      this.set({[key]: value, cancellationReport: null, cancellationLoading: false, expenseReport: null, expenseLoading: false, expenseError: '', reportSummary: null, reportLoading: false, reportError: ''});
      clearTimeout(this._cancellationTimer);
      this._cancellationTimer = setTimeout(() => {
        this.loadDashboardSnapshot();
        this.loadCustomerSegmentSnapshot();
        this.loadStaffIncomeSnapshot();
        this.loadAdvertisingRoiSnapshot();
        this.loadLoyaltySnapshot();
        this.loadCancellationSnapshot();
        this.loadPhotoAnalysisSnapshot();
        this.loadExpenseSnapshot();
        this.loadCustomerAcquisitionSnapshot();
        this.loadCampaignPerformanceSnapshot();
        this.loadAdvertisingChannelsSnapshot();
        this.loadDoctorPerformanceSnapshot();
        this.loadAgeStatisticsSnapshot(); this.loadCityStatisticsSnapshot(); this.loadTopServicesSnapshot();
        this.loadStaffAppointmentsSnapshot();
        this.loadSatisfactionSnapshot();
      }, 350);
    },
    formatSnapshotDate(value) {
      if (!value) return '';
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? '' : date.toLocaleString('fa-IR', {dateStyle:'short', timeStyle:'short'});
    },
    markAvatarBroken(key) {
      if (!key || this.brokenAvatars[key]) return;
      this.brokenAvatars = {...this.brokenAvatars, [key]: true};
    },
    resetBrokenAvatars(prefix) {
      if (!prefix) {
        this.brokenAvatars = {};
        return;
      }
      const next = {...this.brokenAvatars};
      Object.keys(next).forEach(key => {
        if (String(key).startsWith(prefix)) delete next[key];
      });
      this.brokenAvatars = next;
    },
    async loadPhotoAnalysisSnapshot() {
      const requestId = (this._photoRequestId || 0) + 1;
      this._photoRequestId = requestId;
      this.photoLoading = true;
      try {
        const { data } = await axios.get('/api/reports/widgets/photo-analysis', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._photoRequestId) this.photoSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._photoRequestId) this.photoSnapshot = {status:'failed', error:error.response?.data?.message || 'دریافت آنالیز عکس‌ها انجام نشد.'};
      } finally {
        if (requestId === this._photoRequestId) this.photoLoading = false;
      }
    },
    async calculatePhotoAnalysis() {
      if (this.photoLoading) return;
      const requestId = (this._photoRequestId || 0) + 1;
      this._photoRequestId = requestId;
      this.photoLoading = true;
      try {
        const { data } = await axios.post('/api/reports/widgets/photo-analysis/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._photoRequestId) this.photoSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._photoRequestId) this.photoSnapshot = {status:'failed', error:error.response?.data?.message || 'ایجاد آنالیز عکس‌ها انجام نشد.'};
      } finally {
        if (requestId === this._photoRequestId) this.photoLoading = false;
      }
    },
    async loadCustomerSegmentSnapshot() {
      clearTimeout(this.customerSegmentPollingTimer);
      const requestId = (this._customerSegmentRequestId || 0) + 1;
      this._customerSegmentRequestId = requestId;
      this.customerSegmentLoading = true;
      try {
        const { data } = await axios.get('/api/reports/widgets/customer-segments', {params:{from:this.s.from,to:this.s.to}});
        if (requestId !== this._customerSegmentRequestId) return;
        this.customerSegmentSnapshot = data.snapshot || null;
        if (['queued','processing'].includes(this.customerSegmentSnapshot?.status)) {
          this.watchCustomerSegmentSnapshot(this.customerSegmentSnapshot.id);
          this.customerSegmentPollingTimer = setTimeout(() => this.loadCustomerSegmentSnapshot(), 10000);
        } else {
          this.stopCustomerSegmentRealtime();
        }
      } catch (error) {
        if (requestId !== this._customerSegmentRequestId) return;
        this.customerSegmentSnapshot = {status:'failed', error:error.response?.data?.message || 'دریافت گزارش انجام نشد.'};
      } finally {
        if (requestId === this._customerSegmentRequestId) this.customerSegmentLoading = false;
      }
    },
    async calculateCustomerSegments() {
      if (['queued','processing'].includes(this.customerSegmentSnapshot?.status)) return;
      this._customerSegmentRequestId = (this._customerSegmentRequestId || 0) + 1;
      // محاسبه روی سرور داخل همین درخواست انجام می‌شود؛ وضعیت همان لحظه «در حال محاسبه» می‌شود تا لودینگ دیده شود.
      this.customerSegmentSnapshot = {...(this.customerSegmentSnapshot || {}), status:'processing', progress:0, stage:'در حال محاسبه…'};
      clearTimeout(this.customerSegmentPollingTimer);
      this.customerSegmentLoading = true;
      try {
        const { data } = await axios.post('/api/reports/widgets/customer-segments/calculate', {from:this.s.from,to:this.s.to});
        this.customerSegmentSnapshot = data.snapshot;
        this.watchCustomerSegmentSnapshot(data.snapshot?.id);
        this.customerSegmentPollingTimer = setTimeout(() => this.loadCustomerSegmentSnapshot(), 10000);
      } catch (error) {
        this.customerSegmentSnapshot = {status:'failed', error:error.response?.data?.message || 'شروع محاسبه انجام نشد.'};
      } finally {
        this.customerSegmentLoading = false;
      }
    },
    watchCustomerSegmentSnapshot(snapshotId) {
      if (!snapshotId || this._customerSegmentRealtimeId === String(snapshotId)) return;
      this.stopCustomerSegmentRealtime();
      this._customerSegmentRealtimeId = String(snapshotId);
      this._stopCustomerSegmentRealtime = subscribeReportWidgetProgress(snapshotId, (snapshot) => {
        if (String(snapshot?.id || '') !== this._customerSegmentRealtimeId) return;
        this.customerSegmentSnapshot = {...(this.customerSegmentSnapshot || {}), ...snapshot};
        if (['completed','failed'].includes(snapshot.status)) {
          clearTimeout(this.customerSegmentPollingTimer);
          this.stopCustomerSegmentRealtime();
        }
      });
    },
    stopCustomerSegmentRealtime() {
      if (typeof this._stopCustomerSegmentRealtime === 'function') this._stopCustomerSegmentRealtime();
      this._stopCustomerSegmentRealtime = null;
      this._customerSegmentRealtimeId = null;
    },
    async loadDashboardSnapshot() {
      clearTimeout(this.dashboardPollingTimer);
      const requestId = (this._dashboardRequestId || 0) + 1;
      this._dashboardRequestId = requestId;
      this.dashboardLoading = true;
      try {
        const { data } = await axios.get('/api/reports/widgets/dashboard-summary', {params:{from:this.s.from,to:this.s.to}});
        if (requestId !== this._dashboardRequestId) return;
        this.dashboardSnapshot = data.snapshot || null;
        this.set({reportSummary: this.dashboardSnapshot?.status === 'completed' ? this.dashboardSnapshot.result : null, reportError:''});
        if (['queued','processing'].includes(this.dashboardSnapshot?.status)) {
          this.watchDashboardSnapshot(this.dashboardSnapshot.id);
          this.dashboardPollingTimer = setTimeout(() => this.loadDashboardSnapshot(), 10000);
        } else {
          this.overviewLoadingSource = null;
          this.stopDashboardRealtime();
        }
      } catch (error) {
        if (requestId !== this._dashboardRequestId) return;
        this.dashboardSnapshot = {status:'failed', error:error.response?.data?.message || 'دریافت شاخص‌های گزارش انجام نشد.'};
        this.set({reportSummary:null, reportError:this.dashboardSnapshot.error});
      } finally {
        if (requestId === this._dashboardRequestId) this.dashboardLoading = false;
      }
    },
    async calculateCustomerOverview(source) {
      if (this.dashboardLoading || ['queued','processing'].includes(this.dashboardSnapshot?.status)) return;
      this.overviewLoadingSource = source;
      await this.calculateDashboardSummary();
      if (!['queued','processing'].includes(this.dashboardSnapshot?.status)) this.overviewLoadingSource = null;
    },
    async calculateDashboardSummary() {
      if (['queued','processing'].includes(this.dashboardSnapshot?.status)) return;
      this._dashboardRequestId = (this._dashboardRequestId || 0) + 1;
      // محاسبه روی سرور داخل همین درخواست انجام می‌شود؛ وضعیت همان لحظه «در حال محاسبه» می‌شود تا لودینگ دیده شود.
      this.dashboardSnapshot = {...(this.dashboardSnapshot || {}), status:'processing', progress:0, stage:'در حال محاسبه شاخص‌ها…'};
      clearTimeout(this.dashboardPollingTimer);
      this.dashboardLoading = true;
      try {
        const { data } = await axios.post('/api/reports/widgets/dashboard-summary/calculate', {from:this.s.from,to:this.s.to});
        this.dashboardSnapshot = data.snapshot;
        // سرور نتیجهٔ کامل را همین‌جا برمی‌گرداند؛ تا ۱۰ ثانیه منتظر بررسی دوره‌ای نمی‌مانیم.
        if (data.snapshot?.status === 'completed' && data.snapshot?.result) {
          this.set({reportSummary:data.snapshot.result, reportError:''});
          this.overviewLoadingSource = null;
        } else {
          this.set({reportSummary:null, reportError:''});
          this.watchDashboardSnapshot(data.snapshot?.id);
          this.dashboardPollingTimer = setTimeout(() => this.loadDashboardSnapshot(), 10000);
        }
      } catch (error) {
        this.dashboardSnapshot = {status:'failed', error:error.response?.data?.message || 'شروع محاسبه شاخص‌ها انجام نشد.'};
        this.set({reportError:this.dashboardSnapshot.error});
      } finally {
        this.dashboardLoading = false;
      }
    },
    watchDashboardSnapshot(snapshotId) {
      if (!snapshotId || this._dashboardRealtimeId === String(snapshotId)) return;
      this.stopDashboardRealtime();
      this._dashboardRealtimeId = String(snapshotId);
      this._stopDashboardRealtime = subscribeReportWidgetProgress(snapshotId, (snapshot) => {
        if (String(snapshot?.id || '') !== this._dashboardRealtimeId) return;
        this.dashboardSnapshot = {...(this.dashboardSnapshot || {}), ...snapshot};
        if (snapshot.status === 'completed') this.set({reportSummary:snapshot.result, reportError:''});
        if (snapshot.status === 'failed') this.set({reportSummary:null, reportError:snapshot.error || 'محاسبه شاخص‌ها ناموفق بود.'});
        if (['completed','failed'].includes(snapshot.status)) {
          this.overviewLoadingSource = null;
          clearTimeout(this.dashboardPollingTimer);
          this.stopDashboardRealtime();
        }
      });
    },
    stopDashboardRealtime() {
      if (typeof this._stopDashboardRealtime === 'function') this._stopDashboardRealtime();
      this._stopDashboardRealtime = null;
      this._dashboardRealtimeId = null;
    },
    async loadStaffRoster() {
      try {
        const { data } = await axios.get('/api/reports/widgets/staff-income/roster');
        this.staffRoster = Array.isArray(data.staff) ? data.staff : [];
        if (this.s.staff !== 'همه' && !this.staffRoster.some(staff => staff.name === this.s.staff)) this.set({staff:'همه'});
      } catch (error) {
        console.warn('Staff report roster could not be loaded.', error);
      }
    },
    async loadStaffIncomeSnapshot() {
      clearTimeout(this.staffIncomePollingTimer);
      const requestId = (this._staffIncomeRequestId || 0) + 1;
      this._staffIncomeRequestId = requestId;
      this.staffIncomeLoading = true;
      try {
        const { data } = await axios.get('/api/reports/widgets/staff-income', {params:{from:this.s.from,to:this.s.to}});
        if (requestId !== this._staffIncomeRequestId) return;
        this.staffIncomeSnapshot = data.snapshot || null;
        if (['queued','processing'].includes(this.staffIncomeSnapshot?.status)) {
          this.watchStaffIncomeSnapshot(this.staffIncomeSnapshot.id);
          this.staffIncomePollingTimer = setTimeout(() => this.loadStaffIncomeSnapshot(), 10000);
        } else {
          this.stopStaffIncomeRealtime();
        }
      } catch (error) {
        if (requestId !== this._staffIncomeRequestId) return;
        this.staffIncomeSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت گزارش پرسنل انجام نشد.'};
      } finally {
        if (requestId === this._staffIncomeRequestId) this.staffIncomeLoading = false;
      }
    },
    async calculateStaffIncome() {
      if (['queued','processing'].includes(this.staffIncomeSnapshot?.status)) return;
      this._staffIncomeRequestId = (this._staffIncomeRequestId || 0) + 1;
      // محاسبه روی سرور داخل همین درخواست انجام می‌شود؛ وضعیت همان لحظه «در حال محاسبه» می‌شود تا لودینگ دیده شود.
      this.resetBrokenAvatars('staff-income-');
      await this.loadStaffRoster();
      this.staffIncomeSnapshot = {...(this.staffIncomeSnapshot || {}), status:'processing', progress:0, stage:'در حال محاسبه…'};
      clearTimeout(this.staffIncomePollingTimer);
      this.staffIncomeLoading = true;
      try {
        const { data } = await axios.post('/api/reports/widgets/staff-income/calculate', {from:this.s.from,to:this.s.to});
        this.staffIncomeSnapshot = data.snapshot;
        this.watchStaffIncomeSnapshot(data.snapshot?.id);
        this.staffIncomePollingTimer = setTimeout(() => this.loadStaffIncomeSnapshot(), 10000);
      } catch (error) {
        this.staffIncomeSnapshot = {status:'failed',error:error.response?.data?.message || 'شروع محاسبه گزارش پرسنل انجام نشد.'};
      } finally {
        this.staffIncomeLoading = false;
      }
    },
    watchStaffIncomeSnapshot(snapshotId) {
      if (!snapshotId || this._staffIncomeRealtimeId === String(snapshotId)) return;
      this.stopStaffIncomeRealtime();
      this._staffIncomeRealtimeId = String(snapshotId);
      this._stopStaffIncomeRealtime = subscribeReportWidgetProgress(snapshotId, snapshot => {
        if (String(snapshot?.id || '') !== this._staffIncomeRealtimeId) return;
        this.staffIncomeSnapshot = {...(this.staffIncomeSnapshot || {}),...snapshot};
        if (['completed','failed'].includes(snapshot.status)) {
          clearTimeout(this.staffIncomePollingTimer);
          this.stopStaffIncomeRealtime();
        }
      });
    },
    stopStaffIncomeRealtime() {
      if (typeof this._stopStaffIncomeRealtime === 'function') this._stopStaffIncomeRealtime();
      this._stopStaffIncomeRealtime = null;
      this._staffIncomeRealtimeId = null;
    },
    async loadAdvertisingRoiSnapshot() {
      clearTimeout(this.advertisingRoiPollingTimer);
      const requestId = (this._advertisingRoiRequestId || 0) + 1;
      this._advertisingRoiRequestId = requestId;
      this.advertisingRoiLoading = true;
      try {
        const { data } = await axios.get('/api/reports/widgets/advertising-roi', {params:{from:this.s.from,to:this.s.to}});
        if (requestId !== this._advertisingRoiRequestId) return;
        this.advertisingRoiSnapshot = data.snapshot || null;
        if (['queued','processing'].includes(this.advertisingRoiSnapshot?.status)) {
          this.watchAdvertisingRoiSnapshot(this.advertisingRoiSnapshot.id);
          this.advertisingRoiPollingTimer = setTimeout(() => this.loadAdvertisingRoiSnapshot(), 10000);
        } else {
          this.stopAdvertisingRoiRealtime();
        }
      } catch (error) {
        if (requestId !== this._advertisingRoiRequestId) return;
        this.advertisingRoiSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت گزارش تبلیغات انجام نشد.'};
      } finally {
        if (requestId === this._advertisingRoiRequestId) this.advertisingRoiLoading = false;
      }
    },
    async calculateAdvertisingRoi() {
      if (['queued','processing'].includes(this.advertisingRoiSnapshot?.status)) return;
      this._advertisingRoiRequestId = (this._advertisingRoiRequestId || 0) + 1;
      clearTimeout(this.advertisingRoiPollingTimer);
      this.advertisingRoiLoading = true;
      try {
        const { data } = await axios.post('/api/reports/widgets/advertising-roi/calculate', {from:this.s.from,to:this.s.to});
        this.advertisingRoiSnapshot = data.snapshot;
        this.watchAdvertisingRoiSnapshot(data.snapshot?.id);
        this.advertisingRoiPollingTimer = setTimeout(() => this.loadAdvertisingRoiSnapshot(), 10000);
      } catch (error) {
        this.advertisingRoiSnapshot = {status:'failed',error:error.response?.data?.message || 'شروع محاسبه گزارش تبلیغات انجام نشد.'};
      } finally {
        this.advertisingRoiLoading = false;
      }
    },
    watchAdvertisingRoiSnapshot(snapshotId) {
      if (!snapshotId || this._advertisingRoiRealtimeId === String(snapshotId)) return;
      this.stopAdvertisingRoiRealtime();
      this._advertisingRoiRealtimeId = String(snapshotId);
      this._stopAdvertisingRoiRealtime = subscribeReportWidgetProgress(snapshotId, snapshot => {
        if (String(snapshot?.id || '') !== this._advertisingRoiRealtimeId) return;
        this.advertisingRoiSnapshot = {...(this.advertisingRoiSnapshot || {}),...snapshot};
        if (['completed','failed'].includes(snapshot.status)) {
          clearTimeout(this.advertisingRoiPollingTimer);
          this.stopAdvertisingRoiRealtime();
        }
      });
    },
    stopAdvertisingRoiRealtime() {
      if (typeof this._stopAdvertisingRoiRealtime === 'function') this._stopAdvertisingRoiRealtime();
      this._stopAdvertisingRoiRealtime = null;
      this._advertisingRoiRealtimeId = null;
    },
    async loadLoyaltySnapshot() {
      clearTimeout(this.loyaltyPollingTimer);
      const requestId = (this._loyaltyRequestId || 0) + 1;
      this._loyaltyRequestId = requestId;
      this.loyaltyLoading = true;
      try {
        const { data } = await axios.get('/api/reports/widgets/loyalty', {params:{from:this.s.from,to:this.s.to}});
        if (requestId !== this._loyaltyRequestId) return;
        this.loyaltySnapshot = data.snapshot || null;
        if (['queued','processing'].includes(this.loyaltySnapshot?.status)) {
          this.watchLoyaltySnapshot(this.loyaltySnapshot.id);
          this.loyaltyPollingTimer = setTimeout(() => this.loadLoyaltySnapshot(),10000);
        } else this.stopLoyaltyRealtime();
      } catch (error) {
        if (requestId === this._loyaltyRequestId) this.loyaltySnapshot={status:'failed',error:error.response?.data?.message || 'دریافت گزارش وفاداری انجام نشد.'};
      } finally { if (requestId === this._loyaltyRequestId) this.loyaltyLoading=false; }
    },
    async calculateLoyalty() {
      if (['queued','processing'].includes(this.loyaltySnapshot?.status)) return;
      this._loyaltyRequestId=(this._loyaltyRequestId||0)+1;
      // محاسبه روی سرور داخل همین درخواست انجام می‌شود؛ وضعیت همان لحظه «در حال محاسبه» می‌شود تا لودینگ دیده شود.
      this.loyaltySnapshot = {...(this.loyaltySnapshot || {}), status:'processing', progress:0, stage:'در حال محاسبه…'};
      clearTimeout(this.loyaltyPollingTimer);
      this.loyaltyLoading=true;
      try {
        const {data}=await axios.post('/api/reports/widgets/loyalty/calculate',{from:this.s.from,to:this.s.to});
        this.loyaltySnapshot=data.snapshot;
        this.watchLoyaltySnapshot(data.snapshot?.id);
        this.loyaltyPollingTimer=setTimeout(()=>this.loadLoyaltySnapshot(),10000);
      } catch(error) { this.loyaltySnapshot={status:'failed',error:error.response?.data?.message || 'شروع محاسبه وفاداری انجام نشد.'}; }
      finally { this.loyaltyLoading=false; }
    },
    watchLoyaltySnapshot(snapshotId) {
      if (!snapshotId || this._loyaltyRealtimeId===String(snapshotId)) return;
      this.stopLoyaltyRealtime();
      this._loyaltyRealtimeId=String(snapshotId);
      this._stopLoyaltyRealtime=subscribeReportWidgetProgress(snapshotId,snapshot=>{
        if (String(snapshot?.id||'')!==this._loyaltyRealtimeId) return;
        this.loyaltySnapshot={...(this.loyaltySnapshot||{}),...snapshot};
        if (['completed','failed'].includes(snapshot.status)) { clearTimeout(this.loyaltyPollingTimer); this.stopLoyaltyRealtime(); }
      });
    },
    stopLoyaltyRealtime() {
      if (typeof this._stopLoyaltyRealtime==='function') this._stopLoyaltyRealtime();
      this._stopLoyaltyRealtime=null; this._loyaltyRealtimeId=null;
    },
    toggleBillRow(id) {
      const next = { ...this.billExpandedIds };
      if (next[id]) delete next[id];
      else next[id] = true;
      this.billExpandedIds = next;
    },
    async openOtherExpensesDetail() {
      this.otherExpensesDetailOpen = true;
      const kpiTotal = Math.round(Number(this.s.reportSummary?.kpis?.expenses || 0));
      const detailTotal = this.expenseSnapshot?.status === 'completed'
        ? Math.round(Number(this.expenseSnapshot?.result?.total || 0))
        : null;
      if (detailTotal !== kpiTotal) await this.calculateExpenses();
    },
    async calculateExpenses() {
      const requestId = (this._expenseRequestId || 0) + 1;
      this._expenseRequestId = requestId;
      this.set({expenseLoading:true,expenseError:''});
      try {
        const {data} = await axios.post('/api/reports/widgets/expenses/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._expenseRequestId) {
          this.expenseSnapshot = data.snapshot || null;
          this.billExpandedIds = {};
          this.set({expenseReport:this.expenseSnapshot?.result || null});
        }
      } catch (error) {
        if (requestId === this._expenseRequestId) this.set({expenseError:error.response?.data?.message || 'محاسبه هزینه‌ها انجام نشد.'});
      } finally {
        if (requestId === this._expenseRequestId) this.set({expenseLoading:false});
      }
    },
    async loadExpenseSnapshot() {
      const requestId = (this._expenseRequestId || 0) + 1;
      this._expenseRequestId = requestId;
      try {
        const {data} = await axios.get('/api/reports/widgets/expenses', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._expenseRequestId) {
          this.expenseSnapshot = data.snapshot || null;
          this.set({expenseReport:this.expenseSnapshot?.result || null, expenseError:''});
        }
      } catch (error) {
        if (requestId === this._expenseRequestId) this.set({expenseError:error.response?.data?.message || 'دریافت گزارش هزینه‌ها انجام نشد.'});
      }
    },
    async loadCustomerAcquisitionSnapshot() {
      const requestId = (this._customerAcquisitionRequestId || 0) + 1;
      this._customerAcquisitionRequestId = requestId;
      this.customerAcquisitionLoading = true;
      try {
        const {data} = await axios.get('/api/reports/widgets/customer-acquisition', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._customerAcquisitionRequestId) this.customerAcquisitionSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._customerAcquisitionRequestId) this.customerAcquisitionSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت هزینه جذب انجام نشد.'};
      } finally {
        if (requestId === this._customerAcquisitionRequestId) this.customerAcquisitionLoading = false;
      }
    },
    async calculateCustomerAcquisition() {
      if (this.customerAcquisitionLoading) return;
      const requestId = (this._customerAcquisitionRequestId || 0) + 1;
      this._customerAcquisitionRequestId = requestId;
      this.customerAcquisitionLoading = true;
      try {
        const {data} = await axios.post('/api/reports/widgets/customer-acquisition/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._customerAcquisitionRequestId) this.customerAcquisitionSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._customerAcquisitionRequestId) this.customerAcquisitionSnapshot = {status:'failed',error:error.response?.data?.message || 'محاسبه هزینه جذب انجام نشد.'};
      } finally {
        if (requestId === this._customerAcquisitionRequestId) this.customerAcquisitionLoading = false;
      }
    },
    async loadCampaignPerformanceSnapshot() {
      const requestId = (this._campaignPerformanceRequestId || 0) + 1;
      this._campaignPerformanceRequestId = requestId;
      this.campaignPerformanceLoading = true;
      try {
        const {data} = await axios.get('/api/reports/widgets/campaign-performance', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._campaignPerformanceRequestId) this.campaignPerformanceSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._campaignPerformanceRequestId) this.campaignPerformanceSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت بازدهی کمپین‌ها انجام نشد.'};
      } finally {
        if (requestId === this._campaignPerformanceRequestId) this.campaignPerformanceLoading = false;
      }
    },
    async calculateCampaignPerformance() {
      if (this.campaignPerformanceLoading) return;
      const requestId = (this._campaignPerformanceRequestId || 0) + 1;
      this._campaignPerformanceRequestId = requestId;
      this.campaignPerformanceLoading = true;
      try {
        const {data} = await axios.post('/api/reports/widgets/campaign-performance/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._campaignPerformanceRequestId) this.campaignPerformanceSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._campaignPerformanceRequestId) this.campaignPerformanceSnapshot = {status:'failed',error:error.response?.data?.message || 'محاسبه بازدهی کمپین‌ها انجام نشد.'};
      } finally {
        if (requestId === this._campaignPerformanceRequestId) this.campaignPerformanceLoading = false;
      }
    },
    async loadAdvertisingChannelsSnapshot() {
      const requestId = (this._advertisingChannelsRequestId || 0) + 1;
      this._advertisingChannelsRequestId = requestId;
      this.advertisingChannelsLoading = true;
      try {
        const {data} = await axios.get('/api/reports/widgets/advertising-channels', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._advertisingChannelsRequestId) this.advertisingChannelsSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._advertisingChannelsRequestId) this.advertisingChannelsSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت آمار کانال‌ها انجام نشد.'};
      } finally {
        if (requestId === this._advertisingChannelsRequestId) this.advertisingChannelsLoading = false;
      }
    },
    async calculateAdvertisingChannels() {
      if (this.advertisingChannelsLoading) return;
      const requestId = (this._advertisingChannelsRequestId || 0) + 1;
      this._advertisingChannelsRequestId = requestId;
      this.advertisingChannelsLoading = true;
      try {
        const {data} = await axios.post('/api/reports/widgets/advertising-channels/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._advertisingChannelsRequestId) this.advertisingChannelsSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._advertisingChannelsRequestId) this.advertisingChannelsSnapshot = {status:'failed',error:error.response?.data?.message || 'محاسبه آمار کانال‌ها انجام نشد.'};
      } finally {
        if (requestId === this._advertisingChannelsRequestId) this.advertisingChannelsLoading = false;
      }
    },
    async loadDoctorPerformanceSnapshot() {
      const requestId = (this._doctorPerformanceRequestId || 0) + 1;
      this._doctorPerformanceRequestId = requestId;
      this.doctorPerformanceLoading = true;
      try {
        const {data} = await axios.get('/api/reports/widgets/doctor-performance', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._doctorPerformanceRequestId) this.doctorPerformanceSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._doctorPerformanceRequestId) this.doctorPerformanceSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت گزارش پزشکان انجام نشد.'};
      } finally {
        if (requestId === this._doctorPerformanceRequestId) this.doctorPerformanceLoading = false;
      }
    },
    async calculateDoctorPerformance() {
      if (this.doctorPerformanceLoading) return;
      const requestId = (this._doctorPerformanceRequestId || 0) + 1;
      this._doctorPerformanceRequestId = requestId;
      this.resetBrokenAvatars('doctor-');
      this.doctorPerformanceLoading = true;
      try {
        const {data} = await axios.post('/api/reports/widgets/doctor-performance/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._doctorPerformanceRequestId) this.doctorPerformanceSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._doctorPerformanceRequestId) this.doctorPerformanceSnapshot = {status:'failed',error:error.response?.data?.message || 'محاسبه گزارش پزشکان انجام نشد.'};
      } finally {
        if (requestId === this._doctorPerformanceRequestId) this.doctorPerformanceLoading = false;
      }
    },
    async loadAgeStatisticsSnapshot() {
      try { const {data}=await axios.get('/api/reports/widgets/age-statistics',{params:{from:this.s.from,to:this.s.to}}); this.ageStatisticsSnapshot=data.snapshot||null; }
      catch(error) { this.ageStatisticsSnapshot={status:'failed',error:error.response?.data?.message||'دریافت آمار سنی انجام نشد.'}; }
    },
    async loadCityStatisticsSnapshot(){this.cityStatisticsLoading=false;clearInterval(this.cityStatisticsProgressTimer);try{const {data}=await axios.get('/api/reports/widgets/city-statistics',{params:{from:this.s.from,to:this.s.to}});this.cityStatisticsSnapshot=data.snapshot||null;this.cityStatisticsProgress=data.snapshot?.status==='completed'?100:0}catch(e){this.cityStatisticsSnapshot=null;this.cityStatisticsProgress=0}},
    async calculateCityStatistics(){if(this.cityStatisticsLoading)return;this.cityStatisticsLoading=true;this.cityStatisticsProgress=8;clearInterval(this.cityStatisticsProgressTimer);this.cityStatisticsProgressTimer=setInterval(()=>{if(this.cityStatisticsProgress<92)this.cityStatisticsProgress=Math.min(92,this.cityStatisticsProgress+Math.ceil((100-this.cityStatisticsProgress)/6))},450);try{const {data}=await axios.post('/api/reports/widgets/city-statistics/calculate',{from:this.s.from,to:this.s.to});this.cityStatisticsProgress=100;this.cityStatisticsSnapshot=data.snapshot||null}finally{clearInterval(this.cityStatisticsProgressTimer);this.cityStatisticsLoading=false}},
    async loadTopServicesSnapshot(){
      const requestId=(this._topServicesRequestId||0)+1;this._topServicesRequestId=requestId;this.topServicesLoading=true;
      try{const {data}=await axios.get('/api/reports/widgets/top-services',{params:{from:this.s.from,to:this.s.to}});if(requestId===this._topServicesRequestId)this.topServicesSnapshot=data.snapshot||null}
      catch(error){if(requestId===this._topServicesRequestId)this.topServicesSnapshot={status:'failed',error:error.response?.data?.message||'دریافت گزارش خدمات انجام نشد.'}}
      finally{if(requestId===this._topServicesRequestId)this.topServicesLoading=false}
    },
    async calculateTopServices(){
      if(this.topServicesLoading)return;const requestId=(this._topServicesRequestId||0)+1;this._topServicesRequestId=requestId;this.topServicesLoading=true;
      try{const {data}=await axios.post('/api/reports/widgets/top-services/calculate',{from:this.s.from,to:this.s.to});if(requestId===this._topServicesRequestId)this.topServicesSnapshot=data.snapshot||null}
      catch(error){if(requestId===this._topServicesRequestId)this.topServicesSnapshot={status:'failed',error:error.response?.data?.message||'محاسبه گزارش خدمات انجام نشد.'}}
      finally{if(requestId===this._topServicesRequestId)this.topServicesLoading=false}
    },
    async loadStaffAppointmentsSnapshot(){
      const requestId=(this._staffAppointmentsRequestId||0)+1;this._staffAppointmentsRequestId=requestId;this.staffAppointmentsLoading=true;
      try{const {data}=await axios.get('/api/reports/widgets/staff-appointments',{params:{from:this.s.from,to:this.s.to}});if(requestId===this._staffAppointmentsRequestId)this.staffAppointmentsSnapshot=data.snapshot||null}
      catch(error){if(requestId===this._staffAppointmentsRequestId)this.staffAppointmentsSnapshot={status:'failed',error:error.response?.data?.message||'دریافت گزارش وقت‌دهی پرسنل انجام نشد.'}}
      finally{if(requestId===this._staffAppointmentsRequestId)this.staffAppointmentsLoading=false}
    },
    async calculateStaffAppointments(){
      if(this.staffAppointmentsLoading)return;const requestId=(this._staffAppointmentsRequestId||0)+1;this._staffAppointmentsRequestId=requestId;this.resetBrokenAvatars('staff-apt-');await this.loadStaffRoster();this.staffAppointmentsLoading=true;
      try{const {data}=await axios.post('/api/reports/widgets/staff-appointments/calculate',{from:this.s.from,to:this.s.to});if(requestId===this._staffAppointmentsRequestId)this.staffAppointmentsSnapshot=data.snapshot||null}
      catch(error){if(requestId===this._staffAppointmentsRequestId)this.staffAppointmentsSnapshot={status:'failed',error:error.response?.data?.message||'محاسبه گزارش وقت‌دهی پرسنل انجام نشد.'}}
      finally{if(requestId===this._staffAppointmentsRequestId)this.staffAppointmentsLoading=false}
    },
    async loadSatisfactionSnapshot(){
      const requestId=(this._satisfactionRequestId||0)+1;this._satisfactionRequestId=requestId;this.satisfactionLoading=true;
      try{const {data}=await axios.get('/api/reports/widgets/satisfaction',{params:{from:this.s.from,to:this.s.to}});if(requestId===this._satisfactionRequestId)this.satisfactionSnapshot=data.snapshot||null}
      catch(error){if(requestId===this._satisfactionRequestId)this.satisfactionSnapshot={status:'failed',error:error.response?.data?.message||'دریافت گزارش رضایت‌مندی انجام نشد.'}}
      finally{if(requestId===this._satisfactionRequestId)this.satisfactionLoading=false}
    },
    async calculateSatisfaction(){
      if(this.satisfactionLoading)return;const requestId=(this._satisfactionRequestId||0)+1;this._satisfactionRequestId=requestId;this.satisfactionLoading=true;
      try{const {data}=await axios.post('/api/reports/widgets/satisfaction/calculate',{from:this.s.from,to:this.s.to});if(requestId===this._satisfactionRequestId)this.satisfactionSnapshot=data.snapshot||null}
      catch(error){if(requestId===this._satisfactionRequestId)this.satisfactionSnapshot={status:'failed',error:error.response?.data?.message||'محاسبه گزارش رضایت‌مندی انجام نشد.'}}
      finally{if(requestId===this._satisfactionRequestId)this.satisfactionLoading=false}
    },
    async calculateAgeStatistics() {
      if (this.ageStatisticsLoading) return;
      this.ageStatisticsLoading=true;
      this.ageStatisticsProgress=8;
      clearInterval(this.ageStatisticsProgressTimer);
      this.ageStatisticsProgressTimer=setInterval(()=>{ if(this.ageStatisticsProgress<92) this.ageStatisticsProgress=Math.min(92,this.ageStatisticsProgress+Math.ceil((100-this.ageStatisticsProgress)/6)); }, 450);
      try { const {data}=await axios.post('/api/reports/widgets/age-statistics/calculate',{from:this.s.from,to:this.s.to}); this.ageStatisticsProgress=100; this.ageStatisticsSnapshot=data.snapshot||null; }
      catch(error) { this.ageStatisticsSnapshot={status:'failed',error:error.response?.data?.message||'محاسبه آمار سنی انجام نشد.'}; }
      finally { clearInterval(this.ageStatisticsProgressTimer); this.ageStatisticsLoading=false; if(this.ageStatisticsSnapshot?.status!=='completed') this.ageStatisticsProgress=0; }
    },
    async loadCancellationSnapshot() {
      const requestId = (this._cancellationRequestId || 0) + 1;
      this._cancellationRequestId = requestId;
      try {
        const {data} = await axios.get('/api/reports/widgets/cancellation-rate', {params:{from:this.s.from,to:this.s.to}});
        if (requestId === this._cancellationRequestId) this.cancellationSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._cancellationRequestId) this.cancellationSnapshot = {status:'failed',error:error.response?.data?.message || 'دریافت نرخ کنسلی انجام نشد.'};
      }
    },
    async calculateCancellationRate() {
      if (this.s.cancellationLoading) return;
      const requestId = (this._cancellationRequestId || 0) + 1;
      this._cancellationRequestId = requestId;
      this.set({cancellationLoading:true});
      try {
        const {data} = await axios.post('/api/reports/widgets/cancellation-rate/calculate', {from:this.s.from,to:this.s.to});
        if (requestId === this._cancellationRequestId) this.cancellationSnapshot = data.snapshot || null;
      } catch (error) {
        if (requestId === this._cancellationRequestId) this.cancellationSnapshot = {status:'failed',error:error.response?.data?.message || 'محاسبه نرخ کنسلی انجام نشد.'};
      } finally {
        if (requestId === this._cancellationRequestId) this.set({cancellationLoading:false});
      }
    },
    async loadReportSummary() {
      const requestId = (this._reportRequestId || 0) + 1;
      this._reportRequestId = requestId;
      this.set({reportLoading: true, reportSummary: null, reportError: ''});
      try {
        const { data } = await axios.get('/api/reports/dashboard', {
          params: { from: this.s.from, to: this.s.to },
        });
        if (requestId === this._reportRequestId) this.set({reportSummary: data});
      } catch (error) {
        if (requestId === this._reportRequestId) this.set({reportError: 'بارگذاری گزارش ناموفق بود. بازهٔ تاریخ و اتصال را بررسی کنید.'});
        console.warn('Clinic revenue report could not be loaded.', error);
      } finally {
        if (requestId === this._reportRequestId) this.set({reportLoading: false});
      }
    },
    async loadStaffTarget() {
      try {
        const { data } = await axios.get('/api/settings');
        const capacityToman = Math.max(0, Number(data.report_monthly_capacity) || 0);
        const capMillions = capacityToman > 0 ? capacityToman / 1000000 : Number(this.capacityDefault ?? 2500);
        this.set({cap: String(capMillions)});
      } catch (error) {
        console.warn('Report monthly capacity could not be loaded.', error);
      }
    },
    parseFaNumber(value) {
      const fa = '۰۱۲۳۴۵۶۷۸۹';
      const normalized = String(value ?? '').replace(/[۰-۹]/g, d => String(fa.indexOf(d))).replace(/[٬,\s]/g, '');
      return Math.max(0, Number(normalized) || 0);
    },
    async saveMonthlyCapacity() {
      const millions = this.parseFaNumber(this.s.cap);
      this.set({cap: String(millions || this.capacityDefault || 2500)});
      try {
        await axios.post('/api/settings/report-targets', {report_monthly_capacity: millions * 1000000});
      } catch (error) {
        console.warn('Report monthly capacity could not be saved.', error);
      }
    },
    set(patch) {
      const p = typeof patch === 'function' ? patch(this.s) : patch;
      this.s = Object.assign({}, this.s, p);
    },
    exportExcel() {
      const rows = [
        ['گزارش کلینیک', 'از ' + this.s.from + ' تا ' + this.s.to],
        [],
        ['شاخص', 'مقدار (میلیون تومان)'],
        ['سود خالص', 486], ['هزینه پزشک', 512], ['حقوق پرسنل', 238], ['مواد مصرفی', 174], ['میزان تخفیف‌ها', 96], ['سایر هزینه‌های کلینیک', this.s.reportSummary ? Number(this.s.reportSummary.kpis?.expenses || 0) / 1000000 : '—'], ['تعداد مراجعین', 512],
        [],
        ['کانال تبلیغاتی', 'مراجعین', 'هزینه', 'درآمد'],
        ['اینستاگرام', 210, 45, 168], ['معرفی دوستان', 90, 0, 85], ['گوگل', 60, 18, 52], ['یوتیوب', 25, 12, 20],
        [],
        ['پزشک', 'آورده', 'پورسانت', 'حقوق ثابت', 'مشاوره', 'انجام کار'],
        ['دکتر محمدی', 620, 93, 40, 120, 78], ['دکتر افشار', 480, 72, 40, 95, 52], ['دکتر سلطانی', 390, 58, 35, 80, 36]
      ];
      const csv = rows.map(r => r.join(',')).join('\n');
      const blob = new Blob(['\uFEFF' + csv], {type: 'text/csv;charset=utf-8'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'clinic-report.csv';
      a.click();
    }
  }
};
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap');
html,body{margin:0;padding:0;background:#f1f4f9}
*{box-sizing:border-box;font-family:'Vazirmatn',sans-serif}
a{color:#2563eb;text-decoration:none}
a:hover{color:#1d4ed8}
input,button,select{font-family:'Vazirmatn',sans-serif}
.report-date-input{width:130px!important;height:38px!important;border:1px solid #e2e8f0!important;border-radius:10px!important;padding:8px 12px!important;background:#f8fafc!important;color:#0f172a!important;font-size:13px!important;text-align:center!important;outline:none!important;cursor:pointer!important;box-shadow:none!important}.report-date-input:focus{border-color:#60a5fa!important;box-shadow:0 0 0 3px rgba(37,99,235,.1)!important}
.report-refresh-button{width:34px;height:34px;min-width:34px;padding:0;border:1px solid #93c5fd;border-radius:10px;background:#eff6ff;color:#1d4ed8;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 2px 7px rgba(37,99,235,.13);transition:background .16s,border-color .16s,transform .16s,box-shadow .16s}.report-refresh-button:hover:not(:disabled){background:#dbeafe;border-color:#60a5fa;box-shadow:0 4px 10px rgba(37,99,235,.18);transform:translateY(-1px)}.report-refresh-button:disabled{cursor:wait;opacity:.65}.report-refresh-button--labeled{width:auto;min-width:34px;padding:0 10px;font-size:10.5px;font-weight:900;white-space:nowrap}.report-calculated-at{color:#64748b;font-size:10px;white-space:nowrap}
.satisfaction-report-content{display:grid;gap:12px}.satisfaction-report-summary{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.satisfaction-report-summary div{display:grid;gap:2px;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;text-align:center}.satisfaction-report-summary strong{color:#15803d;font-size:17px}.satisfaction-report-summary span{color:#94a3b8;font-size:9.5px}.satisfaction-report-questions{display:flex;flex-direction:column;gap:7px;max-height:280px;overflow-y:auto;overscroll-behavior:auto;scrollbar-gutter:stable;padding-left:3px}.satisfaction-report-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:12px;min-width:0;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;color:#334155;font-size:11.5px}.satisfaction-report-row>span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700;color:#0f172a;line-height:1.7}.satisfaction-report-row b{flex:0 0 auto;color:#15803d;font-size:12px;font-weight:800;white-space:nowrap;font-variant-numeric:tabular-nums}.satisfaction-report-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:12px;color:#94a3b8;font-size:11px;text-align:center}
.report-widget-empty{min-height:82px;display:grid;place-items:center;padding:14px;border:1px dashed #bfdbfe;border-radius:12px;background:#f8fbff;color:#64748b;font-size:11px;font-weight:800;text-align:center;line-height:1.8}
.report-avatar-initial{background:#e2e8f0;color:#475569}
.report-open-module-button{appearance:none;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#334155;padding:0 10px;height:34px;font:inherit;font-size:11px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}.report-open-module-button:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}.expense-invoice{min-width:0;border-radius:10px;background:#f8fafc;overflow:hidden}.expense-invoice__primary{width:100%;appearance:none;border:0;background:transparent;padding:9px 12px;display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,auto) minmax(64px,88px) 14px;align-items:center;gap:8px;cursor:pointer;text-align:right;font:inherit;color:inherit}.expense-invoice__name{min-width:0;font-size:12.5px;font-weight:700;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.expense-invoice__no{min-width:0;font-size:10.5px;font-weight:700;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left}.expense-invoice__chevron{color:#94a3b8;font-size:11px;line-height:1}.expense-invoice__details{display:grid;grid-template-columns:1fr 1fr;gap:6px 10px;padding:0 12px 10px;border-top:1px solid #e2e8f0}.expense-invoice__details>span{min-width:0;display:grid;gap:1px;font-size:11px;font-weight:700;color:#0f172a;overflow-wrap:anywhere}.expense-invoice__details em{font-style:normal;font-size:9.5px;font-weight:600;color:#94a3b8}.expense-money{min-width:0;max-width:145px;text-align:left;font-weight:800;color:#0f172a;white-space:normal;overflow-wrap:anywhere;line-height:1.5}.expense-summary-list{display:flex;flex-direction:column;gap:7px;min-width:0}.expense-summary-row{min-width:0;min-height:42px;border-radius:10px;padding:8px 12px;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px}.expense-summary-value{min-width:0;max-width:100%;font-size:clamp(11px,1.05vw,15px);font-weight:800;line-height:1.4;white-space:nowrap;text-align:left;direction:rtl;font-variant-numeric:tabular-nums}
.cac-campaign-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:8px;min-width:0;padding:8px 10px;border-radius:10px;background:#f8fafc;font-size:11px;color:#334155}.cac-campaign-row>span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700}.cac-campaign-row>small{color:#64748b;white-space:nowrap}.cac-campaign-row>b{color:#1d4ed8;white-space:nowrap;font-size:11px}
.cac-campaign-scroll{display:flex;flex-direction:column;gap:7px}
.top-services-more{align-self:center;display:inline-flex;align-items:center;gap:4px;margin-top:3px;padding:2px 4px;border:0;background:transparent;color:#2563eb;font:inherit;font-size:10px;font-weight:800;line-height:1.5;cursor:pointer}
.top-services-more:hover{color:#1d4ed8;text-decoration:underline;text-underline-offset:3px}
.top-services-more:focus-visible{outline:1px solid #60a5fa;outline-offset:3px;border-radius:3px}
.top-services-more-arrow{font-size:11px;line-height:1;transition:transform .15s ease}
.report-card-scroll{max-height:310px;overflow-y:auto;overscroll-behavior:auto;scrollbar-gutter:stable;padding-left:5px}.report-card-scroll-wide{overflow-x:auto}.report-list-stack,.top-services-scroll,.campaign-performance-scroll,.cac-campaign-scroll{display:flex;flex-direction:column;gap:10px}.city-statistics-scroll{padding-bottom:3px}.report-card-scroll::-webkit-scrollbar{width:6px;height:6px}.report-card-scroll::-webkit-scrollbar-track{background:#f1f5f9;border-radius:999px}.report-card-scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px}.report-card-scroll::-webkit-scrollbar-thumb:hover{background:#94a3b8}
.top-services-loading-track>div{width:42%;animation:top-services-loading-slide 1.15s ease-in-out infinite}
@keyframes top-services-loading-slide{0%{transform:translateX(145%)}50%{transform:translateX(0)}100%{transform:translateX(-145%)}}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
[data-drag="1"]{opacity:0.45;outline:2px dashed #2563eb;outline-offset:-3px}
[data-drag-handle]{transition:background 0.15s,color 0.15s;touch-action:none;user-select:none}
[data-drag-handle]:hover{background:#e2e8f0 !important;color:#475569 !important}
@media (max-width:1280px){
  [data-screen-label="داشبوردها"] > div{grid-column:span 3 !important}
  [data-screen-label="داشبوردها"] > [data-screen-label="آمار تبلیغات"],[data-screen-label="داشبوردها"] > [data-screen-label="پزشکان"],[data-screen-label="داشبوردها"] > [data-screen-label="آمار شهرها"],[data-screen-label="داشبوردها"] > [data-screen-label="پردرآمدترین خدمات"],[data-screen-label="داشبوردها"] > [data-screen-label="کمپین‌ها بر اساس درجه تمایل"]{grid-column:1/-1 !important}
}
@media (max-width:880px){
  [data-screen-label="داشبوردها"] > div{grid-column:1/-1 !important}
  main{padding:8px 14px 0 !important}
  header{padding:12px 14px !important}
}
[data-hover="1"]:hover{background:#f1f5f9}
.cancel-rate-loading{position:absolute;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;gap:12px;border-radius:16px;background:linear-gradient(110deg,rgba(255,255,255,.91),rgba(239,246,255,.95),rgba(255,255,255,.91));backdrop-filter:blur(3px);overflow:hidden}.cancel-rate-loading::before{content:'';position:absolute;inset:0;background:linear-gradient(100deg,transparent 25%,rgba(255,255,255,.85) 50%,transparent 75%);animation:cancel-rate-shimmer 1.35s ease-in-out infinite}.cancel-rate-loading>div{position:relative;display:grid;gap:4px}.cancel-rate-loading strong{color:#1d4ed8;font-size:12px}.cancel-rate-loading small{color:#64748b;font-size:10.5px}.cancel-rate-loading-ring{position:relative;width:34px;height:34px;border:3px solid #bfdbfe;border-top-color:#2563eb;border-right-color:#14b8a6;border-radius:50%;animation:cancel-rate-spin .72s linear infinite}.cancel-loading-enter-active,.cancel-loading-leave-active{transition:opacity .2s ease}.cancel-loading-enter-from,.cancel-loading-leave-to{opacity:0}@keyframes cancel-rate-spin{to{transform:rotate(360deg)}}@keyframes cancel-rate-shimmer{from{transform:translateX(110%)}to{transform:translateX(-110%)}}
.cancellation-calculate-button{width:32px;height:32px;padding:0;border:1px solid #fecaca;border-radius:9px;background:#fef2f2;color:#dc2626;cursor:pointer;display:grid;place-items:center;box-shadow:0 2px 7px rgba(220,38,38,.1)}.cancellation-calculate-button:disabled{cursor:wait;opacity:.65}
.customer-segment-loading{position:absolute;z-index:6;inset:55px 0 0;display:grid;place-items:center;border-radius:0 0 16px 16px;background:rgba(248,250,252,.76);backdrop-filter:blur(4px)}
.customer-segment-loading-card{width:min(320px,calc(100% - 32px));padding:18px 20px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(145deg,#fff,#eff6ff);box-shadow:0 16px 38px rgba(30,64,175,.2);display:grid;justify-items:center;gap:9px;text-align:center}
.customer-segment-loading-card strong{color:#172554;font-size:12.5px}.customer-segment-loading-card small{min-height:17px;color:#64748b;font-size:9.5px;font-weight:700}
.customer-segment-loading-ring{width:62px;height:62px;border:5px solid #dbeafe;border-top-color:#2563eb;border-right-color:#38bdf8;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}
.customer-segment-loading-ring span{color:#1d4ed8;font-size:11px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}
.customer-segment-loading-track{width:100%;height:8px;border-radius:999px;background:#dbeafe;overflow:hidden}.customer-segment-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#2563eb,#38bdf8);transition:width .45s ease;box-shadow:0 0 9px rgba(37,99,235,.3)}
@keyframes customer-segment-spin{to{transform:rotate(360deg)}}@keyframes customer-segment-unspin{to{transform:rotate(-360deg)}}
.kpi-card-subtitle{display:block;font-size:10px;font-weight:600;line-height:1.45;color:#64748b}
.kpi-summary-section{position:relative;margin-bottom:16px;padding-top:42px}.kpi-summary-toolbar{position:absolute;z-index:3;top:0;right:0;left:0;height:36px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 4px}.kpi-summary-toolbar>div{display:flex;align-items:center;gap:9px;min-width:0}.kpi-summary-toolbar strong{font-size:13px;color:#1e293b;white-space:nowrap}.kpi-summary-toolbar small{font-size:9.5px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.kpi-calculate-button{min-width:35px;height:35px;padding:0 10px;border:1px solid #93c5fd;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:10.5px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 2px 8px rgba(37,99,235,.14)}.kpi-calculate-button:disabled{cursor:wait;opacity:.78}
.kpi-summary-loading{position:absolute;z-index:7;inset:42px 0 0;display:grid;place-items:center;border-radius:16px;background:rgba(248,250,252,.76);backdrop-filter:blur(4px)}.kpi-summary-loading-card{width:min(350px,calc(100% - 32px));padding:20px 22px;border:1px solid #bfdbfe;border-radius:20px;background:linear-gradient(145deg,#fff,#eff6ff);box-shadow:0 18px 42px rgba(30,64,175,.2);display:grid;justify-items:center;gap:10px;text-align:center}.kpi-summary-loading-card strong{color:#172554;font-size:13px}.kpi-summary-loading-card small{min-height:17px;color:#64748b;font-size:10px;font-weight:700}.kpi-summary-loading-ring{width:66px;height:66px;border:5px solid #dbeafe;border-top-color:#2563eb;border-right-color:#14b8a6;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}.kpi-summary-loading-ring span{color:#1d4ed8;font-size:11px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}.kpi-summary-loading-track{width:100%;height:8px;border-radius:999px;background:#dbeafe;overflow:hidden}.kpi-summary-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#2563eb,#14b8a6);transition:width .45s ease;box-shadow:0 0 9px rgba(37,99,235,.3)}
.staff-income-calculate-button{min-width:34px;height:34px;padding:0 10px;border:1px solid #93c5fd;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:5px;box-shadow:0 2px 7px rgba(37,99,235,.12)}.staff-income-calculate-button:disabled{cursor:wait;opacity:.78}
.staff-income-loading{position:absolute;z-index:7;inset:55px 0 0;display:grid;place-items:center;border-radius:0 0 16px 16px;background:rgba(248,250,252,.78);backdrop-filter:blur(4px)}.staff-income-loading-card{width:min(320px,calc(100% - 32px));padding:18px 20px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(145deg,#fff,#eff6ff);box-shadow:0 16px 38px rgba(30,64,175,.2);display:grid;justify-items:center;gap:9px;text-align:center}.staff-income-loading-card strong{color:#172554;font-size:12.5px}.staff-income-loading-card small{min-height:17px;color:#64748b;font-size:9.5px;font-weight:700}.staff-income-loading-ring{width:62px;height:62px;border:5px solid #dbeafe;border-top-color:#2563eb;border-right-color:#7c3aed;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}.staff-income-loading-ring span{color:#1d4ed8;font-size:11px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}.staff-income-loading-track{width:100%;height:8px;border-radius:999px;background:#dbeafe;overflow:hidden}.staff-income-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#2563eb,#7c3aed);transition:width .45s ease}
.roi-calculate-button{min-width:34px;height:34px;padding:0 10px;border:1px solid #93c5fd;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:5px;box-shadow:0 2px 7px rgba(37,99,235,.12)}.roi-calculate-button:disabled{cursor:wait;opacity:.78}.roi-loading{position:absolute;z-index:7;inset:55px 0 0;display:grid;place-items:center;border-radius:0 0 16px 16px;background:rgba(248,250,252,.78);backdrop-filter:blur(4px)}.roi-loading-card{width:min(320px,calc(100% - 32px));padding:18px 20px;border:1px solid #bbf7d0;border-radius:18px;background:linear-gradient(145deg,#fff,#f0fdf4);box-shadow:0 16px 38px rgba(21,128,61,.17);display:grid;justify-items:center;gap:9px;text-align:center}.roi-loading-card strong{color:#14532d;font-size:12.5px}.roi-loading-card small{min-height:17px;color:#64748b;font-size:9.5px;font-weight:700}.roi-loading-ring{width:62px;height:62px;border:5px solid #dcfce7;border-top-color:#22c55e;border-right-color:#2563eb;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}.roi-loading-ring span{color:#15803d;font-size:11px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}.roi-loading-track{width:100%;height:8px;border-radius:999px;background:#dcfce7;overflow:hidden}.roi-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#22c55e,#2563eb);transition:width .45s ease}
.loyalty-calculate-button{width:32px;height:32px;padding:0;border:1px solid #bbf7d0;border-radius:9px;background:#f0fdf4;color:#15803d;cursor:pointer;display:grid;place-items:center}.loyalty-loading{position:absolute;z-index:7;inset:55px 0 0;display:grid;place-items:center;border-radius:0 0 16px 16px;background:rgba(248,250,252,.8);backdrop-filter:blur(4px)}.loyalty-loading-card{width:min(290px,calc(100% - 28px));padding:17px 18px;border:1px solid #bbf7d0;border-radius:17px;background:linear-gradient(145deg,#fff,#f0fdf4);box-shadow:0 15px 34px rgba(21,128,61,.17);display:grid;justify-items:center;gap:8px;text-align:center}.loyalty-loading-card strong{color:#14532d;font-size:12px}.loyalty-loading-card small{color:#64748b;font-size:9.5px;font-weight:700}.loyalty-loading-ring{width:58px;height:58px;border:5px solid #dcfce7;border-top-color:#22c55e;border-right-color:#0d9488;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}.loyalty-loading-ring span{color:#15803d;font-size:10.5px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}.loyalty-loading-track{width:100%;height:8px;border-radius:999px;background:#dcfce7;overflow:hidden}.loyalty-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#22c55e,#0d9488);transition:width .45s ease}
.age-statistics-loading{position:absolute;z-index:7;inset:55px 0 0;display:grid;place-items:center;border-radius:0 0 16px 16px;background:rgba(248,250,252,.78);backdrop-filter:blur(4px)}.age-statistics-loading-card{width:min(320px,calc(100% - 32px));padding:18px 20px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(145deg,#fff,#eff6ff);box-shadow:0 16px 38px rgba(30,64,175,.2);display:grid;justify-items:center;gap:9px;text-align:center}.age-statistics-loading-card strong{color:#172554;font-size:12.5px}.age-statistics-loading-card small{min-height:17px;color:#64748b;font-size:9.5px;font-weight:700}.age-statistics-loading-ring{width:62px;height:62px;border:5px solid #dbeafe;border-top-color:#2563eb;border-right-color:#14b8a6;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}.age-statistics-loading-ring span{color:#1d4ed8;font-size:11px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}.age-statistics-loading-track{width:100%;height:8px;border-radius:999px;background:#dbeafe;overflow:hidden}.age-statistics-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#2563eb,#14b8a6);transition:width .45s ease}
.city-statistics-loading{position:absolute;z-index:7;inset:55px 0 0;display:grid;place-items:center;border-radius:0 0 16px 16px;background:rgba(248,250,252,.78);backdrop-filter:blur(4px)}.city-statistics-loading-card{width:min(320px,calc(100% - 32px));padding:18px 20px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(145deg,#fff,#eff6ff);box-shadow:0 16px 38px rgba(30,64,175,.2);display:grid;justify-items:center;gap:9px;text-align:center}.city-statistics-loading-card strong{color:#172554;font-size:12.5px}.city-statistics-loading-card small{min-height:17px;color:#64748b;font-size:9.5px;font-weight:700}.city-statistics-loading-ring{width:62px;height:62px;border:5px solid #dbeafe;border-top-color:#2563eb;border-right-color:#14b8a6;border-radius:50%;display:grid;place-items:center;animation:customer-segment-spin .9s linear infinite}.city-statistics-loading-ring span{color:#1d4ed8;font-size:11px;font-weight:900;animation:customer-segment-unspin .9s linear infinite}.city-statistics-loading-track{width:100%;height:8px;border-radius:999px;background:#dbeafe;overflow:hidden}.city-statistics-loading-track>div{height:100%;border-radius:inherit;background:linear-gradient(90deg,#2563eb,#14b8a6);transition:width .45s ease}
.rpt-money{white-space:nowrap;font-variant-numeric:tabular-nums;letter-spacing:-.15px}
.expense-money{font-size:11.5px;white-space:nowrap;max-width:none;font-variant-numeric:tabular-nums}
.expense-summary-value{font-size:clamp(11px,.95vw,13.5px)}
@media (max-width:640px){
  .expense-invoice__primary{grid-template-columns:minmax(0,1fr) minmax(0,auto) 14px}
  .expense-invoice__no{display:none}
  .expense-invoice__details{grid-template-columns:1fr}
}
.report-refresh-button:disabled svg{animation:report-refresh-spin .8s linear infinite}
@keyframes report-refresh-spin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion: reduce){.report-refresh-button:disabled svg{animation:none}}
</style>

<style>
/* NativeStaticReports dark mode — nsr root + inline white surfaces */
html body.dark .nsr,
html #app.dark .nsr {
  background: #121316 !important;
  color: #e8eaed !important;
}
html body.dark .nsr h1,
html #app.dark .nsr h1 { color: #f1f3f4 !important; }
html body.dark .nsr .report-date-input,
html #app.dark .nsr .report-date-input {
  background: #121417 !important;
  border-color: #3a3f4a !important;
  color: #f1f3f4 !important;
  -webkit-text-fill-color: #f1f3f4 !important;
}
html body.dark .nsr [style*="background: rgb(255, 255, 255)"],
html body.dark .nsr [style*="background:#ffffff"],
html body.dark .nsr [style*="background:#FFFFFF"],
html body.dark .nsr [style*="background:#fff;"],
html body.dark .nsr [style*="background:#fff,"],
html body.dark .nsr [style*="background:#fff "],
html body.dark .nsr [style*="background: rgb(248, 250, 252)"],
html body.dark .nsr [style*="background:#f8fafc"],
html body.dark .nsr [style*="background: rgb(241, 244, 249)"],
html body.dark .nsr [style*="background:#f1f4f9"],
html body.dark .nsr [style*="background: rgb(241, 245, 249)"],
html body.dark .nsr [style*="background:#f1f5f9"],
html body.dark .nsr [style*="background: rgb(230, 235, 243)"],
html body.dark .nsr [style*="background:#e6ebf3"],
html body.dark .nsr [style*="background: rgb(239, 246, 255)"],
html body.dark .nsr [style*="background:#eff6ff"],
html body.dark .nsr [style*="background: rgb(248, 251, 255)"],
html body.dark .nsr [style*="background:#f8fbff"],
html body.dark .nsr [style*="background: rgb(236, 254, 255)"],
html body.dark .nsr [style*="background:#ecfeff"],
html body.dark .nsr [style*="background: rgb(240, 253, 244)"],
html body.dark .nsr [style*="background:#f0fdf4"],
html body.dark .nsr [style*="background: rgb(254, 252, 232)"],
html body.dark .nsr [style*="background:#fefce8"],
html body.dark .nsr [style*="background: rgb(255, 247, 237)"],
html body.dark .nsr [style*="background:#fff7ed"],
html body.dark .nsr [style*="background: rgb(240, 249, 255)"],
html body.dark .nsr [style*="background:#f0f9ff"],
html body.dark .nsr [style*="background: rgb(250, 245, 255)"],
html body.dark .nsr [style*="background:#faf5ff"],
html body.dark .nsr [style*="background: rgb(255, 251, 235)"],
html body.dark .nsr [style*="background:#fffbeb"],
html body.dark .nsr [style*="background: rgb(255, 241, 242)"],
html body.dark .nsr [style*="background:#fff1f2"],
html body.dark .nsr [style*="background: rgb(253, 242, 248)"],
html body.dark .nsr [style*="background:#fdf2f8"],
html body.dark .nsr [style*="background: rgb(240, 253, 250)"],
html body.dark .nsr [style*="background:#f0fdfa"],
html body.dark .nsr [style*="background: rgb(236, 253, 245)"],
html body.dark .nsr [style*="background:#ecfdf5"],
html body.dark .nsr [style*="background: rgb(254, 242, 242)"],
html body.dark .nsr [style*="background:#fef2f2"],
html body.dark .nsr [style*="background: rgb(255, 247, 247)"],
html body.dark .nsr [style*="background:#fff7f7"],
html body.dark .nsr [style*="background: rgb(220, 252, 231)"],
html body.dark .nsr [style*="background:#dcfce7"],
html body.dark .nsr [style*="background: rgb(254, 226, 226)"],
html body.dark .nsr [style*="background:#fee2e2"],
html body.dark .nsr [style*="background: rgb(226, 232, 240)"],
html body.dark .nsr [style*="background:#e2e8f0"],
html body.dark .nsr [style*="background: rgb(245, 243, 255)"],
html body.dark .nsr [style*="background:#f5f3ff"],
html #app.dark .nsr [style*="background:#ffffff"],
html #app.dark .nsr [style*="background:#FFFFFF"],
html #app.dark .nsr [style*="background:#fff;"],
html #app.dark .nsr [style*="background:#fff,"],
html #app.dark .nsr [style*="background:#fff "],
html #app.dark .nsr [style*="background:#f8fafc"],
html #app.dark .nsr [style*="background:#f1f4f9"],
html #app.dark .nsr [style*="background:#f1f5f9"],
html #app.dark .nsr [style*="background:#e6ebf3"],
html #app.dark .nsr [style*="background:#eff6ff"],
html #app.dark .nsr [style*="background:#f8fbff"],
html #app.dark .nsr [style*="background:#ecfeff"],
html #app.dark .nsr [style*="background:#f0fdf4"],
html #app.dark .nsr [style*="background:#fefce8"],
html #app.dark .nsr [style*="background:#fff7ed"],
html #app.dark .nsr [style*="background:#f0f9ff"],
html #app.dark .nsr [style*="background:#faf5ff"],
html #app.dark .nsr [style*="background:#fffbeb"],
html #app.dark .nsr [style*="background:#fff1f2"],
html #app.dark .nsr [style*="background:#fdf2f8"],
html #app.dark .nsr [style*="background:#f0fdfa"],
html #app.dark .nsr [style*="background:#ecfdf5"],
html #app.dark .nsr [style*="background:#fef2f2"],
html #app.dark .nsr [style*="background:#fff7f7"],
html #app.dark .nsr [style*="background:#dcfce7"],
html #app.dark .nsr [style*="background:#fee2e2"],
html #app.dark .nsr [style*="background:#e2e8f0"],
html #app.dark .nsr [style*="background:#f5f3ff"] {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
  box-shadow: 0 8px 24px rgba(0,0,0,.32) !important;
}
html body.dark .nsr [style*="color: rgb(15, 23, 42)"],
html body.dark .nsr [style*="color:#0f172a"],
html body.dark .nsr [style*="color: rgb(30, 41, 59)"],
html body.dark .nsr [style*="color:#1e293b"],
html body.dark .nsr [style*="color: rgb(51, 65, 85)"],
html body.dark .nsr [style*="color:#334155"],
html body.dark .nsr [style*="color: rgb(23, 37, 84)"],
html body.dark .nsr [style*="color:#172554"],
html body.dark .nsr [style*="color: rgb(71, 85, 105)"],
html body.dark .nsr [style*="color:#475569"],
html #app.dark .nsr [style*="color:#0f172a"],
html #app.dark .nsr [style*="color:#1e293b"],
html #app.dark .nsr [style*="color:#334155"],
html #app.dark .nsr [style*="color:#172554"],
html #app.dark .nsr [style*="color:#475569"] {
  color: #e8eaed !important;
}
html body.dark .nsr [style*="color: rgb(100, 116, 139)"],
html body.dark .nsr [style*="color:#64748b"],
html body.dark .nsr [style*="color: rgb(148, 163, 184)"],
html body.dark .nsr [style*="color:#94a3b8"],
html #app.dark .nsr [style*="color:#64748b"],
html #app.dark .nsr [style*="color:#94a3b8"] {
  color: #9aa0a6 !important;
}
html body.dark .nsr [style*="border: 1px solid rgb(226, 232, 240)"],
html body.dark .nsr [style*="border:1px solid #e2e8f0"],
html body.dark .nsr [style*="border: 1px solid rgb(203, 213, 225)"],
html body.dark .nsr [style*="border:1px solid #cbd5e1"],
html body.dark .nsr [style*="border: 1.5px solid rgb(226, 232, 240)"],
html body.dark .nsr [style*="border:1.5px solid #e2e8f0"],
html #app.dark .nsr [style*="border:1px solid #e2e8f0"],
html #app.dark .nsr [style*="border:1px solid #cbd5e1"],
html #app.dark .nsr [style*="border:1.5px solid #e2e8f0"] {
  border-color: #2c3038 !important;
}
html body.dark .nsr .kpi-summary-toolbar strong,
html body.dark .nsr .rpt-money,
html body.dark .nsr .expense-money,
html body.dark .nsr .expense-summary-value,
html body.dark .nsr .expense-invoice__name,
html body.dark .nsr .expense-invoice__details > span,
html body.dark .nsr .cac-campaign-row > span,
html body.dark .nsr .cac-campaign-row > b,
html body.dark .nsr .satisfaction-report-row > span,
html #app.dark .nsr .kpi-summary-toolbar strong,
html #app.dark .nsr .rpt-money,
html #app.dark .nsr .expense-money,
html #app.dark .nsr .expense-summary-value,
html #app.dark .nsr .expense-invoice__name,
html #app.dark .nsr .expense-invoice__details > span,
html #app.dark .nsr .cac-campaign-row > span,
html #app.dark .nsr .cac-campaign-row > b,
html #app.dark .nsr .satisfaction-report-row > span {
  color: #f1f3f4 !important;
}
html body.dark .nsr .kpi-summary-toolbar small,
html body.dark .nsr .kpi-card-subtitle,
html body.dark .nsr .report-calculated-at,
html body.dark .nsr .report-widget-empty,
html body.dark .nsr .satisfaction-report-empty,
html body.dark .nsr .cac-campaign-row > small,
html #app.dark .nsr .kpi-summary-toolbar small,
html #app.dark .nsr .kpi-card-subtitle,
html #app.dark .nsr .report-calculated-at,
html #app.dark .nsr .report-widget-empty,
html #app.dark .nsr .satisfaction-report-empty,
html #app.dark .nsr .cac-campaign-row > small {
  color: #9aa0a6 !important;
}
html body.dark .nsr .expense-invoice,
html body.dark .nsr .cac-campaign-row,
html body.dark .nsr .satisfaction-report-summary div,
html body.dark .nsr .satisfaction-report-row,
html body.dark .nsr .report-widget-empty,
html body.dark .nsr .report-avatar-initial,
html #app.dark .nsr .expense-invoice,
html #app.dark .nsr .cac-campaign-row,
html #app.dark .nsr .satisfaction-report-summary div,
html #app.dark .nsr .satisfaction-report-row,
html #app.dark .nsr .report-widget-empty,
html #app.dark .nsr .report-avatar-initial {
  background: #22252d !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
}
html body.dark .nsr .report-refresh-button,
html body.dark .nsr .report-open-module-button,
html body.dark .nsr .kpi-calculate-button,
html body.dark .nsr .staff-income-calculate-button,
html body.dark .nsr .roi-calculate-button,
html #app.dark .nsr .report-refresh-button,
html #app.dark .nsr .report-open-module-button,
html #app.dark .nsr .kpi-calculate-button,
html #app.dark .nsr .staff-income-calculate-button,
html #app.dark .nsr .roi-calculate-button {
  background: #22252d !important;
  border-color: #3a3f4a !important;
  color: #4f8cff !important;
  box-shadow: none !important;
}
html body.dark .nsr .cancellation-calculate-button,
html #app.dark .nsr .cancellation-calculate-button {
  background: #3a2426 !important;
  border-color: #6b3a3a !important;
  color: #f5a8a0 !important;
}
html body.dark .nsr .loyalty-calculate-button,
html #app.dark .nsr .loyalty-calculate-button {
  background: #1e2a28 !important;
  border-color: #3d6b5f !important;
  color: #86efac !important;
}
html body.dark .nsr .kpi-summary-loading,
html body.dark .nsr .staff-income-loading,
html body.dark .nsr .customer-segment-loading,
html body.dark .nsr .cancel-rate-loading,
html body.dark .nsr .roi-loading,
html body.dark .nsr .loyalty-loading,
html body.dark .nsr .age-statistics-loading,
html body.dark .nsr .city-statistics-loading,
html #app.dark .nsr .kpi-summary-loading,
html #app.dark .nsr .staff-income-loading,
html #app.dark .nsr .customer-segment-loading,
html #app.dark .nsr .cancel-rate-loading,
html #app.dark .nsr .roi-loading,
html #app.dark .nsr .loyalty-loading,
html #app.dark .nsr .age-statistics-loading,
html #app.dark .nsr .city-statistics-loading {
  background: rgba(18,19,22,.88) !important;
}
html body.dark .nsr .staff-income-loading-card,
html body.dark .nsr .customer-segment-loading-card,
html body.dark .nsr .kpi-summary-loading-card,
html body.dark .nsr .roi-loading-card,
html body.dark .nsr .loyalty-loading-card,
html body.dark .nsr .age-statistics-loading-card,
html body.dark .nsr .city-statistics-loading-card,
html #app.dark .nsr .staff-income-loading-card,
html #app.dark .nsr .customer-segment-loading-card,
html #app.dark .nsr .kpi-summary-loading-card,
html #app.dark .nsr .roi-loading-card,
html #app.dark .nsr .loyalty-loading-card,
html #app.dark .nsr .age-statistics-loading-card,
html #app.dark .nsr .city-statistics-loading-card {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
}
html body.dark .nsr .staff-income-loading-card strong,
html body.dark .nsr .customer-segment-loading-card strong,
html body.dark .nsr .kpi-summary-loading-card strong,
html body.dark .nsr .roi-loading-card strong,
html body.dark .nsr .loyalty-loading-card strong,
html body.dark .nsr .age-statistics-loading-card strong,
html body.dark .nsr .city-statistics-loading-card strong,
html #app.dark .nsr .staff-income-loading-card strong,
html #app.dark .nsr .customer-segment-loading-card strong,
html #app.dark .nsr .kpi-summary-loading-card strong,
html #app.dark .nsr .roi-loading-card strong,
html #app.dark .nsr .loyalty-loading-card strong,
html #app.dark .nsr .age-statistics-loading-card strong,
html #app.dark .nsr .city-statistics-loading-card strong {
  color: #f1f3f4 !important;
}
html body.dark .nsr [data-hover="1"]:hover,
html body.dark .nsr [data-drag-handle]:hover,
html #app.dark .nsr [data-hover="1"]:hover,
html #app.dark .nsr [data-drag-handle]:hover {
  background: #2a2f3a !important;
  color: #e8eaed !important;
}
html body.dark .nsr .top-services-more,
html #app.dark .nsr .top-services-more { color: #4f8cff !important; }
html body.dark .nsr .expense-invoice__details,
html body.dark .nsr .satisfaction-report-row > div,
html body.dark .nsr .report-card-scroll::-webkit-scrollbar-track,
html #app.dark .nsr .expense-invoice__details,
html #app.dark .nsr .satisfaction-report-row > div,
html #app.dark .nsr .report-card-scroll::-webkit-scrollbar-track {
  background: #121417 !important;
  border-color: #2c3038 !important;
}
html body.dark .nsr .report-card-scroll::-webkit-scrollbar-thumb,
html #app.dark .nsr .report-card-scroll::-webkit-scrollbar-thumb {
  background: #3a3f4a !important;
}

/* سطح‌های گرادیانی و نیمه‌شفاف سفید (هزینه جذب هر مشتری، باتری گنجایش، راهنمای نقشه) و خانه‌های ورودی */
html body.dark .nsr [style*="linear-gradient(135deg,#f8fafc,#eff6ff)"],
html body.dark .nsr [style*="linear-gradient(135deg, rgb(248, 250, 252), rgb(239, 246, 255))"] {
  background: linear-gradient(135deg, #1c2230, #1e2a3f) !important;
}
html body.dark .nsr [style*="linear-gradient(180deg,#faf5ff,#f5f3ff)"],
html body.dark .nsr [style*="linear-gradient(rgb(250, 245, 255), rgb(245, 243, 255))"] {
  background: linear-gradient(180deg, #221a36, #1c1630) !important;
  border-color: #6d28d9 !important;
}
html body.dark .nsr [style*="background:#c4b5fd"],
html body.dark .nsr [style*="background: rgb(196, 181, 253)"] { background: #6d28d9 !important; }
html body.dark .nsr [style*="background:rgba(255,255,255,0.9"],
html body.dark .nsr [style*="background:rgba(255,255,255,0.8"],
html body.dark .nsr [style*="background: rgba(255, 255, 255, 0.9"],
html body.dark .nsr [style*="background: rgba(255, 255, 255, 0.8"] {
  background: rgba(26, 28, 34, .92) !important;
  color: #e8eaed !important;
  box-shadow: 0 1px 4px rgba(0, 0, 0, .45) !important;
}
html body.dark .nsr input:not([type="checkbox"]):not([type="radio"]),
html body.dark .nsr select,
html body.dark .nsr textarea {
  background: #22252d !important;
  color: #e8eaed !important;
  border-color: #3a3f4a !important;
}
html body.dark .nsr .customer-segment-loading-track,
html body.dark .nsr .kpi-summary-loading-track,
html body.dark .nsr .staff-income-loading-track,
html body.dark .nsr .roi-loading-track,
html body.dark .nsr .loyalty-loading-track,
html body.dark .nsr .age-statistics-loading-track,
html body.dark .nsr .city-statistics-loading-track { background: #2a2f3a !important; }
html body.dark .nsr input::placeholder,
html body.dark .nsr textarea::placeholder { color: #7b818b !important; }
</style>
<style>
.nsr-title { margin:0; font-size:22px; font-weight:800; color:#0f172a; }
.nsr-tabs { display:flex; gap:8px; background:#e6ebf3; border-radius:999px; padding:4px; }
html body.dark .nsr .nsr-title,
html #app.dark .nsr .nsr-title { color:#f1f3f4 !important; }
html body.dark .nsr .nsr-tabs,
html #app.dark .nsr .nsr-tabs { background:#22252d !important; }
html body.dark .nsr [data-screen-label="نوار تاریخ و ابزارها"],
html #app.dark .nsr [data-screen-label="نوار تاریخ و ابزارها"],
html body.dark .nsr [data-screen-label^=""],
html #app.dark .nsr [data-screen-label] {
  /* dashboard cards tagged with data-screen-label */
}
html body.dark .nsr [data-screen-label]:not([data-screen-label="تب گزارش"]):not([data-screen-label="شاخص‌های کلیدی گزارش"]):not([data-screen-label="باکس‌های کلی"]):not([data-screen-label="ماه‌ها"]):not([data-screen-label="داشبوردها"]),
html #app.dark .nsr [data-screen-label]:not([data-screen-label="تب گزارش"]):not([data-screen-label="شاخص‌های کلیدی گزارش"]):not([data-screen-label="باکس‌های کلی"]):not([data-screen-label="ماه‌ها"]):not([data-screen-label="داشبوردها"]) {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
  box-shadow: 0 8px 24px rgba(0,0,0,.32) !important;
}
html body.dark .nsr [data-screen-label="نوار تاریخ و ابزارها"],
html #app.dark .nsr [data-screen-label="نوار تاریخ و ابزارها"] {
  background: #1a1c22 !important;
  border-color: #2c3038 !important;
  color: #e8eaed !important;
}
</style>

<style>
/* Reports text+tint remap */
html body.dark .nsr [style*="color: #0"],
html body.dark .nsr [style*="color:#0"],
html body.dark .nsr [style*="color: #1"],
html body.dark .nsr [style*="color:#1"],
html body.dark .nsr [style*="color: #2"],
html body.dark .nsr [style*="color:#2"],
html body.dark .nsr [style*="color: #3"],
html body.dark .nsr [style*="color:#3"],
html body.dark .nsr [style*="color: #4"],
html body.dark .nsr [style*="color:#4"],
html body.dark .nsr [style*="color: #5"],
html body.dark .nsr [style*="color:#5"],
html #app.dark .nsr [style*="color:#0"],
html #app.dark .nsr [style*="color:#1"],
html #app.dark .nsr [style*="color:#2"],
html #app.dark .nsr [style*="color:#3"],
html #app.dark .nsr [style*="color:#4"],
html #app.dark .nsr [style*="color:#5"] {
  color: #e8eaed !important;
}
html body.dark .nsr [style*="color: #6"],
html body.dark .nsr [style*="color:#6"],
html body.dark .nsr [style*="color: #7"],
html body.dark .nsr [style*="color:#7"],
html body.dark .nsr [style*="color: #8"],
html body.dark .nsr [style*="color:#8"],
html body.dark .nsr [style*="color: #9"],
html body.dark .nsr [style*="color:#9"],
html #app.dark .nsr [style*="color:#6"],
html #app.dark .nsr [style*="color:#7"],
html #app.dark .nsr [style*="color:#8"],
html #app.dark .nsr [style*="color:#9"] {
  color: #9aa0a6 !important;
}
html body.dark .nsr [style*="color: rgb(21, 128, 61)"],
html body.dark .nsr [style*="color:#15803d"],
html body.dark .nsr [style*="color: rgb(22, 101, 52)"],
html body.dark .nsr [style*="color:#166534"],
html body.dark .nsr [style*="color: rgb(22, 163, 74)"],
html body.dark .nsr [style*="color:#16a34a"],
html body.dark .nsr [style*="color: rgb(4, 120, 87)"],
html body.dark .nsr [style*="color:#047857"],
html body.dark .nsr [style*="color: rgb(13, 148, 136)"],
html body.dark .nsr [style*="color:#0d9488"],
html body.dark .nsr [style*="color: rgb(15, 118, 110)"],
html body.dark .nsr [style*="color:#0f766e"],
html body.dark .nsr .rpt-money[style*="color: rgb(21, 128, 61)"],
html body.dark .nsr .rpt-money[style*="color:#15803d"],
html #app.dark .nsr [style*="color:#15803d"],
html #app.dark .nsr [style*="color:#166534"],
html #app.dark .nsr [style*="color:#16a34a"],
html #app.dark .nsr [style*="color:#0d9488"],
html #app.dark .nsr [style*="color:#0f766e"] {
  color: #86efac !important;
}
html body.dark .nsr [style*="color: rgb(185, 28, 28)"],
html body.dark .nsr [style*="color:#b91c1c"],
html body.dark .nsr [style*="color: rgb(220, 38, 38)"],
html body.dark .nsr [style*="color:#dc2626"],
html body.dark .nsr [style*="color: rgb(153, 27, 27)"],
html body.dark .nsr [style*="color:#991b1b"],
html body.dark .nsr [style*="color: rgb(190, 18, 60)"],
html body.dark .nsr [style*="color:#be123c"],
html body.dark .nsr [style*="color: rgb(224, 36, 36)"],
html body.dark .nsr [style*="color:#e02424"],
html body.dark .nsr .rpt-money[style*="color: rgb(185, 28, 28)"],
html body.dark .nsr .rpt-money[style*="color:#b91c1c"],
html body.dark .nsr .rpt-money[style*="color: rgb(220, 38, 38)"],
html body.dark .nsr .rpt-money[style*="color:#dc2626"],
html #app.dark .nsr [style*="color:#b91c1c"],
html #app.dark .nsr [style*="color:#dc2626"],
html #app.dark .nsr [style*="color:#991b1b"] {
  color: #fca5a5 !important;
}
html body.dark .nsr [style*="color: rgb(180, 83, 9)"],
html body.dark .nsr [style*="color:#b45309"],
html body.dark .nsr [style*="color: rgb(194, 65, 12)"],
html body.dark .nsr [style*="color:#c2410c"],
html body.dark .nsr [style*="color: rgb(161, 98, 7)"],
html body.dark .nsr [style*="color:#a16207"],
html body.dark .nsr [style*="color: rgb(146, 64, 14)"],
html body.dark .nsr [style*="color:#92400e"],
html #app.dark .nsr [style*="color:#b45309"],
html #app.dark .nsr [style*="color:#c2410c"],
html #app.dark .nsr [style*="color:#a16207"] {
  color: #fdba74 !important;
}
html body.dark .nsr [style*="color: rgb(29, 78, 216)"],
html body.dark .nsr [style*="color:#1d4ed8"],
html body.dark .nsr [style*="color: rgb(37, 99, 235)"],
html body.dark .nsr [style*="color:#2563eb"],
html body.dark .nsr [style*="color: rgb(30, 64, 175)"],
html body.dark .nsr [style*="color:#1e40af"],
html body.dark .nsr [style*="color: rgb(30, 58, 138)"],
html body.dark .nsr [style*="color:#1e3a8a"],
html #app.dark .nsr [style*="color:#1d4ed8"],
html #app.dark .nsr [style*="color:#2563eb"],
html #app.dark .nsr [style*="color:#1e40af"],
html #app.dark .nsr [style*="color:#1e3a8a"] {
  color: #93c5fd !important;
}
html body.dark .nsr [style*="color: rgb(124, 58, 237)"],
html body.dark .nsr [style*="color:#7c3aed"],
html body.dark .nsr [style*="color: rgb(109, 40, 217)"],
html body.dark .nsr [style*="color:#6d28d9"],
html body.dark .nsr [style*="color: rgb(91, 33, 182)"],
html body.dark .nsr [style*="color:#5b21b6"],
html body.dark .nsr [style*="color: rgb(126, 34, 206)"],
html body.dark .nsr [style*="color:#7e22ce"],
html #app.dark .nsr [style*="color:#7c3aed"],
html #app.dark .nsr [style*="color:#5b21b6"],
html #app.dark .nsr [style*="color:#7e22ce"] {
  color: #c4b5fd !important;
}
html body.dark .nsr [style*="background: #f"],
html body.dark .nsr [style*="background:#f"],
html body.dark .nsr [style*="background: #e"],
html body.dark .nsr [style*="background:#e"],
html body.dark .nsr [style*="background: #d"],
html body.dark .nsr [style*="background:#d"],
html #app.dark .nsr [style*="background:#f"],
html #app.dark .nsr [style*="background:#e"],
html #app.dark .nsr [style*="background:#d"] {
  background-color: #1a1c22 !important;
  border-color: #2c3038 !important;
}
html body.dark .nsr [style*="background:#eff6ff"],
html body.dark .nsr [style*="background: rgb(219, 234, 254)"],
html body.dark .nsr [style*="background:#dbeafe"],
html body.dark .nsr [style*="background:#f0f9ff"],
html #app.dark .nsr [style*="background:#eff6ff"],
html #app.dark .nsr [style*="background:#dbeafe"] {
  background-color: #1e3a5f !important;
  border-color: #2f4668 !important;
  color: #93c5fd !important;
}
html body.dark .nsr [style*="background:#f0fdf4"],
html body.dark .nsr [style*="background:#dcfce7"],
html body.dark .nsr [style*="background:#ecfdf5"],
html body.dark .nsr [style*="background:#f0fdfa"],
html #app.dark .nsr [style*="background:#f0fdf4"],
html #app.dark .nsr [style*="background:#dcfce7"],
html #app.dark .nsr [style*="background:#ecfdf5"] {
  background-color: #14352a !important;
  border-color: #166534 !important;
  color: #86efac !important;
}
html body.dark .nsr [style*="background:#fef2f2"],
html body.dark .nsr [style*="background:#fee2e2"],
html body.dark .nsr [style*="background:#fff1f2"],
html body.dark .nsr [style*="background:#fff7f7"],
html body.dark .nsr [style*="background: rgb(254, 202, 202)"],
html body.dark .nsr [style*="background:#fecaca"],
html #app.dark .nsr [style*="background:#fef2f2"],
html #app.dark .nsr [style*="background:#fee2e2"],
html #app.dark .nsr [style*="background:#fff7f7"] {
  background-color: #3a2426 !important;
  border-color: #6b3a3a !important;
  color: #fca5a5 !important;
}
html body.dark .nsr [style*="background:#fffbeb"],
html body.dark .nsr [style*="background:#fff7ed"],
html body.dark .nsr [style*="background:#fefce8"],
html #app.dark .nsr [style*="background:#fffbeb"],
html #app.dark .nsr [style*="background:#fff7ed"] {
  background-color: #3a3220 !important;
  border-color: #6b5a2e !important;
  color: #fde68a !important;
}
html body.dark .nsr [style*="background:#faf5ff"],
html body.dark .nsr [style*="background:#f5f3ff"],
html body.dark .nsr [style*="background: rgb(241, 229, 245)"],
html body.dark .nsr [style*="background:#f1e5f5"],
html #app.dark .nsr [style*="background:#faf5ff"],
html #app.dark .nsr [style*="background:#f5f3ff"] {
  background-color: #2e1065 !important;
  border-color: #6d28d9 !important;
  color: #e9d5ff !important;
}
html body.dark .nsr [style*="linear-gradient(135deg,#1d4ed8"],
html #app.dark .nsr [style*="linear-gradient(135deg,#1d4ed8"] {
  background: linear-gradient(135deg,#1e3a5f,#16324f) !important;
  box-shadow: none !important;
}
html body.dark .nsr svg path[fill="#eff6ff"],
html #app.dark .nsr svg path[fill="#eff6ff"] {
  fill: #243044 !important;
  stroke: #3a4a63 !important;
}
html body.dark .nsr input,
html body.dark .nsr select,
html body.dark .nsr textarea,
html #app.dark .nsr input,
html #app.dark .nsr select,
html #app.dark .nsr textarea {
  background: #121417 !important;
  border-color: #3a3f4a !important;
  color: #f1f3f4 !important;
  -webkit-text-fill-color: #f1f3f4 !important;
}
</style>
