<template>
  <div dir="rtl" style="min-height:100vh;background:#f1f4f9;color:#0f172a;padding-bottom:60px">
  
    <div style="height:8px"></div>
    <main style="max-width:1440px;margin:0 auto;padding:8px 24px 0">
  
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px">
        <h1 style="margin:0;font-size:22px;font-weight:800;color:#0f172a">گزارشات</h1>
        <div style="display:flex;gap:8px;background:#e6ebf3;border-radius:999px;padding:4px">
          <button :style="v.tR" @click="v.onTR">گزارش</button>
          <button :style="v.tA" @click="v.onTA">آمار و تحلیل</button>
        </div>
      </div>
  
      <template v-if="v.isR">
      <section data-screen-label="تب گزارش">
  
        <div style="background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px" data-screen-label="نوار تاریخ و ابزارها">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:13px;font-weight:600;color:#334155">از تاریخ</span>
            <input :value="v.fromV" @input="v.onFrom" style="width:110px;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;color:#0f172a;text-align:center;outline:none;background:#f8fafc">
            <span style="font-size:13px;font-weight:600;color:#334155">تا تاریخ</span>
            <input :value="v.toV" @input="v.onTo" style="width:110px;border:1px solid #e2e8f0;border-radius:10px;padding:8px 12px;font-size:13px;color:#0f172a;text-align:center;outline:none;background:#f8fafc">
            <button @click="v.toggleOpen" style="background:#2563eb;color:#ffffff;border:none;border-radius:10px;padding:9px 20px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 2px 6px rgba(37,99,235,0.3)">{{ v.openLbl }}</button>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <button @click="v.openFilter" style="display:flex;align-items:center;gap:7px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 14px;font-size:13px;font-weight:600;color:#334155;cursor:pointer">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"></path></svg>
              فیلترها
              <span :style="'display:' + (v.filtBadge) + ';background:#2563eb;color:#ffffff;border-radius:999px;min-width:18px;height:18px;align-items:center;justify-content:center;font-size:10.5px;font-weight:800;padding:0 5px'">{{ v.filtCnt }}</span>
            </button>
            <button @click="v.exportX" style="display:flex;align-items:center;gap:7px;background:#0d9488;color:#ffffff;border:none;border-radius:10px;padding:9px 14px;font-size:13px;font-weight:700;cursor:pointer">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16"></path></svg>
              خروجی اکسل
            </button>
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
  
        <template v-if="v.showDash">
        <div>
  
          <div style="background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:14px 20px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:16px" data-screen-label="فیلترهای سریع">
            <template v-for="(g, gI) in v.qgroups" :key="gI">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-size:12px;font-weight:700;color:#64748b">{{ g.label }}:</span>
                <template v-for="(op, opI) in g.opts" :key="opI">
                  <button :style="op.st" @click="op.on">{{ op.t }}</button>
                </template>
              </div>
            </template>
          </div>
  
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:16px" data-screen-label="باکس‌های کلی">
            <template v-for="(kp, kpI) in v.kpis" :key="kpI">
              <div :style="'background:' + (kp.bg) + ';border:1px solid ' + (kp.bd) + ';border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:16px;display:flex;flex-direction:column;gap:8px;justify-content:center;grid-column:' + (kp.sp)">
                <span :style="'font-size:' + (kp.ts) + ';font-weight:600;color:' + (kp.tc)">{{ kp.t }}</span>
                <span :style="'font-size:' + (kp.vs) + ';font-weight:800;color:#0f172a;white-space:nowrap'">{{ kp.v }}</span>
                <span :style="'align-self:flex-start;background:' + (kp.gb) + ';color:' + (kp.gc) + ';border-radius:999px;padding:2px 9px;font-size:11px;font-weight:800'">{{ kp.g }}</span>
              </div>
            </template>
            <div style="background:linear-gradient(135deg,#1d4ed8,#2563eb);border-radius:16px;box-shadow:0 4px 14px rgba(37,99,235,0.35);padding:16px;display:flex;flex-direction:column;gap:8px;color:#ffffff">
              <span style="font-size:12px;font-weight:600;opacity:0.85">تخمین درآمد آینده</span>
              <span style="font-size:21px;font-weight:800;white-space:nowrap">{{ v.estV }}</span>
              <span style="font-size:11px;opacity:0.8">{{ v.estNote }}</span>
            </div>
          </div>
  
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px" data-screen-label="ماه‌ها">
            <template v-for="(mo, moI) in v.monthsV" :key="moI">
              <div :style="mo.st" @click="mo.on">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                  <span style="font-size:13.5px;font-weight:800;color:#0f172a">{{ mo.name }}</span>
                  <span :style="'color:' + (mo.col) + ';font-weight:800;font-size:13px;direction:ltr'">{{ mo.arrow }} {{ mo.g }}</span>
                </div>
                <span style="font-size:16px;font-weight:800;color:#334155">{{ mo.val }}</span>
                <svg width="100%" height="30" viewBox="0 0 60 30" preserveAspectRatio="none" style="display:block">
                  <polyline :points="mo.pts" fill="none" :stroke="mo.col" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></polyline>
                </svg>
                <span style="font-size:10.5px;color:#94a3b8">رشد نسبت به ماه قبل</span>
              </div>
            </template>
          </div>
  
          <div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));grid-auto-flow:dense;gap:16px" data-screen-label="داشبوردها">
  
            <div :draggable="true" @dragstart="v.dh.ctype" @dragover="v.dv.ctype" @drop="v.dp.ctype" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.ctype) + ';order:' + (v.o.ctype)" data-screen-label="دسته‌بندی مشتریان">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc" title="جابجایی">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">دسته‌بندی مشتریان (معمولی / خوب / CIP / مشکل‌ساز)</span>
                <input type="checkbox" :checked="v.ck.ctype" @change="v.hide.ctype" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
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
                        <span style="color:#64748b;font-size:11.5px">درآمد: <b style="color:#334155">{{ r.rev }}</b></span>
                        <span style="color:#0f172a;font-weight:800">{{ r.cnt }}</span>
                        <span style="color:#94a3b8;width:40px;text-align:left">{{ r.p }}</span>
                      </div>
                      <div style="height:7px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:' + (r.c) + ';border-radius:999px'"></div></div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.roi" @dragover="v.dv.roi" @drop="v.dp.roi" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.roi) + ';order:' + (v.o.roi)" data-screen-label="بازگشت هزینه تبلیغات">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">بازگشت هزینه تبلیغات</span>
                <span style="background:#dcfce7;color:#15803d;border-radius:999px;padding:3px 12px;font-size:11.5px;font-weight:800">{{ v.roiOkTxt }}</span>
                <input type="checkbox" :checked="v.ck.roi" @change="v.hide.roi" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;gap:12px">
                <div style="flex:1;background:#fef2f2;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:11.5px;color:#b91c1c;font-weight:600">هزینه تبلیغات</span>
                  <span style="font-size:18px;font-weight:800;color:#991b1b">{{ v.roiCost }}</span>
                </div>
                <div style="flex:1;background:#f0fdf4;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:11.5px;color:#15803d;font-weight:600">درآمد حاصل</span>
                  <span style="font-size:18px;font-weight:800;color:#166534">{{ v.roiRev }}</span>
                </div>
                <div style="flex:1;background:#eff6ff;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:11.5px;color:#1d4ed8;font-weight:600">نسبت بازگشت</span>
                  <span style="font-size:18px;font-weight:800;color:#1e40af;direction:ltr;text-align:right">{{ v.roiX }}x</span>
                </div>
              </div>
              <div style="display:flex;align-items:flex-end;gap:22px;height:130px;padding:0 8px">
                <template v-for="(b, bI) in v.roiBars" :key="bI">
                  <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:5px;height:100%">
                    <div style="display:flex;align-items:flex-end;gap:5px;flex:1">
                      <div :style="'width:15px;height:' + (b.ch) + ';background:#fca5a5;border-radius:5px 5px 0 0'"></div>
                      <div :style="'width:15px;height:' + (b.rh) + ';background:#22c55e;border-radius:5px 5px 0 0'"></div>
                    </div>
                    <span style="font-size:11px;color:#64748b">{{ b.n }}</span>
                  </div>
                </template>
              </div>
              <div style="display:flex;gap:16px;font-size:11px;color:#64748b">
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#fca5a5;display:inline-block"></i>هزینه</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#22c55e;display:inline-block"></i>درآمد برگشتی</span>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.loyal" @dragover="v.dv.loyal" @drop="v.dp.loyal" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.loyal) + ';order:' + (v.o.loyal)" data-screen-label="مشتریان وفادار">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">مشتریان وفادار</span>
                <button :style="v.l3st" @click="v.onL3">۳ ماه</button>
                <button :style="v.l6st" @click="v.onL6">۶ ماه</button>
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
            </div>
  
            <div :draggable="true" @dragstart="v.dh.cancel" @dragover="v.dv.cancel" @drop="v.dp.cancel" :style="'position:relative;background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.cancel) + ';order:' + (v.o.cancel)" data-screen-label="نرخ کنسلی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">نرخ کنسلی</span>
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
                <div v-if="v.cnLoading" class="cancel-rate-loading" aria-live="polite">
                  <span class="cancel-rate-loading-ring"></span>
                  <div><strong>در حال محاسبهٔ نرخ کنسلی</strong><small>نوبت‌های این بازه بررسی می‌شوند</small></div>
                </div>
              </transition>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.bills" @dragover="v.dv.bills" @drop="v.dp.bills" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.bills) + ';order:' + (v.o.bills)" data-screen-label="هزینه‌های جاری">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">هزینه‌ها <span style="font-size:11.5px;color:#94a3b8;font-weight:500">(هزینه‌های ثبت‌شده در بازهٔ انتخابی)</span></span>
                <input type="checkbox" :checked="v.ck.bills" @change="v.hide.bills" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;flex-direction:column;gap:8px">
                <p v-if="s.reportLoading" role="status" style="font-size:12px;color:#64748b">در حال بارگذاری هزینه‌ها…</p>
                <p v-else-if="s.reportError" role="alert" style="font-size:12px;color:#b91c1c">{{ s.reportError }} <button type="button" @click="loadReportSummary">تلاش مجدد</button></p>
                <p v-else-if="!v.billRows.length" style="font-size:12px;color:#64748b">هزینه‌ای در این بازه ثبت نشده است.</p>
                <template v-for="(b, bI) in v.billRows" :key="bI">
                  <div style="display:flex;align-items:center;gap:10px;font-size:12.5px;background:#f8fafc;border-radius:10px;padding:9px 12px">
                    <span style="flex:1;font-weight:600;color:#334155">{{ b.n }}</span>
                    <div style="width:90px;height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (b.w) + ';background:#f59e0b;border-radius:999px'"></div></div>
                    <span style="min-width:60px;text-align:left;font-weight:800;color:#0f172a;white-space:nowrap">{{ b.v }}</span>
                  </div>
                </template>
              </div>
              <div style="display:flex;gap:12px;flex-wrap:wrap">
                <div style="flex:1;min-width:110px;background:#eff6ff;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:11.5px;color:#1d4ed8;font-weight:600">درآمد کل دوره</span>
                  <span style="font-size:17px;font-weight:800;color:#1e40af;white-space:nowrap">{{ v.billRevV }}</span>
                </div>
                <div style="flex:1;min-width:110px;background:#fef2f2;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:11.5px;color:#b91c1c;font-weight:600">جمع هزینه‌ها</span>
                  <span style="font-size:17px;font-weight:800;color:#991b1b;white-space:nowrap">{{ v.billSumV }}</span>
                </div>
                <div style="flex:1;min-width:110px;background:#f0fdf4;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:4px">
                  <span style="font-size:11.5px;color:#15803d;font-weight:600">درآمد پس از کسر</span>
                  <span style="font-size:17px;font-weight:800;color:#166534;white-space:nowrap">{{ v.billNetV }}</span>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.staffinc" @dragover="v.dv.staffinc" @drop="v.dp.staffinc" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.staffinc) + ';order:' + (v.o.staffinc)" data-screen-label="درآمد پرسنل">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">درآمد پرسنل و سقف (تارگت)</span>
                <input type="checkbox" :checked="v.ck.staffinc" @change="v.hide.staffinc" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <template v-for="(op, opI) in v.staffChips" :key="opI">
                  <button :style="op.st" @click="op.on">{{ op.t }}</button>
                </template>
              </div>
              <div style="position:relative;height:175px;display:flex;align-items:flex-end;gap:14px;padding:0 6px">
                <div :style="'position:absolute;right:0;left:0;bottom:' + (v.targetB) + ';border-top:2px dashed #f59e0b;z-index:1'">
                  <span style="position:absolute;left:0;top:-20px;background:#fffbeb;color:#b45309;font-size:10.5px;font-weight:800;border-radius:6px;padding:2px 8px">{{ v.targetTxt }}</span>
                </div>
                <template v-for="(b, bI) in v.staffBars" :key="bI">
                  <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px;height:100%;z-index:2">
                    <span :style="'font-size:11.5px;font-weight:800;color:' + (b.vc)">{{ b.v }}</span>
                    <span v-if="b.reached" style="padding:2px 7px;border-radius:999px;background:#dcfce7;color:#15803d;font-size:9px;font-weight:900">رسیده به تارگت</span>
                    <img :src="b.ph" :style="'width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid ' + (b.c)" alt="">
                    <div :style="'width:30px;height:' + (b.h) + ';background:' + (b.c) + ';border-radius:7px 7px 0 0'"></div>
                    <span style="font-size:10.5px;color:#64748b;height:20px;text-align:center;white-space:nowrap">{{ b.n }}</span>
                  </div>
                </template>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;border-radius:12px;padding:10px 16px;flex-wrap:wrap;gap:8px">
                <span style="font-size:12.5px;color:#475569;font-weight:600">جمع کل: <b style="color:#0f172a;font-size:15px">{{ v.staffSum }}</b></span>
                <span style="font-size:12px;color:#b45309;font-weight:700">{{ v.staffOverTxt }}</span>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.qc" @dragover="v.dv.qc" @drop="v.dp.qc" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.qc) + ';order:' + (v.o.qc)" data-screen-label="رضایتمندی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">رضایت‌مندی (QC)</span>
                <input type="checkbox" :checked="v.ck.qc" @change="v.hide.qc" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="position:relative;width:150px;height:150px;flex-shrink:0">
                  <svg width="150" height="150" viewBox="0 0 120 120">
                    <g transform="rotate(-90 60 60)">
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#eef2f7" stroke-width="12"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#16a34a" stroke-width="12" stroke-linecap="round" :stroke-dasharray="v.qcDa"></circle>
                    </g>
                  </svg>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                    <span style="font-size:22px;font-weight:800;color:#16a34a">{{ v.qcPct }}</span>
                    <span style="font-size:10.5px;color:#94a3b8">رضایت کلی</span>
                  </div>
                </div>
                <div style="flex:1;min-width:200px;display:flex;flex-direction:column;gap:10px">
                  <template v-for="(r, rI) in v.qcRows" :key="rI">
                    <div style="display:flex;align-items:center;gap:10px;font-size:12.5px">
                      <span style="width:52px;color:#334155;font-weight:600">{{ r.n }}</span>
                      <div style="flex:1;height:9px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:' + (r.c) + ';border-radius:999px'"></div></div>
                      <span style="width:38px;text-align:left;font-weight:800;color:#0f172a">{{ r.p }}</span>
                    </div>
                  </template>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.adch" @dragover="v.dv.adch" @drop="v.dp.adch" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 4;display:' + (v.dsp.adch) + ';order:' + (v.o.adch)" data-screen-label="آمار تبلیغات">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آمار کانال‌های تبلیغاتی <span style="font-size:11.5px;color:#94a3b8;font-weight:500">(درآمد بر اساس انجام کار)</span></span>
                <input type="checkbox" :checked="v.ck.adch" @change="v.hide.adch" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="overflow-x:auto"><div style="min-width:560px;display:flex;flex-direction:column;gap:10px">
              <div style="display:grid;grid-template-columns:110px 70px 90px 90px 70px 1fr;gap:10px;align-items:center;font-size:11.5px;color:#94a3b8;font-weight:700;padding:0 4px">
                <span>کانال</span><span>مراجعین</span><span>هزینه</span><span>درآمد</span><span>بازگشت</span><span></span>
              </div>
              <template v-for="(r, rI) in v.adRows" :key="rI">
                <div style="display:grid;grid-template-columns:110px 70px 90px 90px 70px 1fr;gap:10px;align-items:center;font-size:12.5px;background:#f8fafc;border-radius:10px;padding:10px 4px">
                  <span style="font-weight:700;color:#0f172a;padding-right:8px;display:flex;align-items:center;gap:7px"><img :src="r.lg" style="width:18px;height:18px;object-fit:contain;flex-shrink:0" alt="">{{ r.n }}</span>
                  <span style="color:#334155;font-weight:600">{{ r.cnt }}</span>
                  <span style="color:#b91c1c;font-weight:600">{{ r.cost }}</span>
                  <span style="color:#15803d;font-weight:700">{{ r.rev }}</span>
                  <span :style="'font-weight:800;color:' + (r.rc) + ';direction:ltr;text-align:right'">{{ r.roi }}</span>
                  <div style="height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-left:8px"><div :style="'height:100%;width:' + (r.w) + ';background:#2563eb;border-radius:999px'"></div></div>
                </div>
              </template>
              </div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.docs" @dragover="v.dv.docs" @drop="v.dp.docs" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.docs) + ';order:' + (v.o.docs)" data-screen-label="پزشکان">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">پزشکان — آورده، پرداختی و تبدیل مشاوره به انجام کار</span>
                <input type="checkbox" :checked="v.ck.docs" @change="v.hide.docs" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="overflow-x:auto"><div style="min-width:800px;display:flex;flex-direction:column;gap:10px">
              <div style="display:grid;grid-template-columns:130px 90px 80px 80px 90px 70px 70px 1fr;gap:10px;align-items:center;font-size:11.5px;color:#94a3b8;font-weight:700;padding:0 4px">
                <span>پزشک</span><span>آورده</span><span>پورسانت</span><span>حقوق ثابت</span><span>جمع پرداختی</span><span>مشاوره</span><span>انجام کار</span><span>نرخ تبدیل</span>
              </div>
              <template v-for="(r, rI) in v.docRows" :key="rI">
                <div style="display:grid;grid-template-columns:130px 90px 80px 80px 90px 70px 70px 1fr;gap:10px;align-items:center;font-size:12.5px;background:#f8fafc;border-radius:10px;padding:10px 4px">
                  <span style="font-weight:700;color:#0f172a;padding-right:8px;display:flex;align-items:center;gap:7px"><img :src="r.ph" style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e2e8f0" alt="">{{ r.n }}<span :style="'display:' + (r.bd) + ';background:#dcfce7;color:#15803d;border-radius:999px;padding:1px 8px;font-size:10px;font-weight:800'">برترین</span></span>
                  <span style="color:#0f172a;font-weight:700">{{ r.bring }}</span>
                  <span style="color:#334155">{{ r.pors }}</span>
                  <span style="color:#334155">{{ r.fix }}</span>
                  <span style="color:#b91c1c;font-weight:700">{{ r.pay }}</span>
                  <span style="color:#334155">{{ r.cons }}</span>
                  <span style="color:#334155">{{ r.done }}</span>
                  <div style="display:flex;align-items:center;gap:8px;margin-left:8px">
                    <div style="flex:1;height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.convW) + ';background:' + (r.cc) + ';border-radius:999px'"></div></div>
                    <span :style="'font-weight:800;color:' + (r.cc) + ';width:38px;text-align:left'">{{ r.convP }}</span>
                  </div>
                </div>
              </template>
              </div></div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.photo" @dragover="v.dv.photo" @drop="v.dp.photo" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.photo) + ';order:' + (v.o.photo)" data-screen-label="آنالیز عکس">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آنالیز عکس‌ها</span>
                <span style="font-size:11.5px;color:#94a3b8">{{ v.phTotal }}</span>
                <input type="checkbox" :checked="v.ck.photo" @change="v.hide.photo" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
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
  
            <div :draggable="true" @dragstart="v.dh.city" @dragover="v.dv.city" @drop="v.dp.city" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.city) + ';order:' + (v.o.city)" data-screen-label="آمار شهرها">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آمار بر اساس شهر</span>
                <input type="checkbox" :checked="v.ck.city" @change="v.hide.city" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;gap:28px;flex-wrap:wrap;align-items:flex-start">
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
                <div style="flex:1;min-width:320px;display:grid;grid-template-columns:1fr 1fr;gap:7px 24px;align-content:start">
                  <template v-for="(r, rI) in v.cityRows" :key="rI">
                    <div style="display:flex;align-items:center;gap:8px;font-size:12px">
                      <span style="width:62px;color:#334155;font-weight:700">{{ r.n }}</span>
                      <div style="flex:1;height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:#2563eb;border-radius:999px'"></div></div>
                      <span style="width:44px;color:#0f172a;font-weight:800;text-align:left">{{ r.cnt }}</span>
                      <span style="width:54px;color:#64748b;text-align:left">{{ r.rev }}</span>
                    </div>
                  </template>
                  <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;font-size:10.5px;color:#94a3b8"><span>تعداد</span><span>درآمد</span></div>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.age" @dragover="v.dv.age" @drop="v.dp.age" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.age) + ';order:' + (v.o.age)" data-screen-label="آمار سنی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">آمار سنی</span>
                <span style="background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:3px 12px;font-size:11.5px;font-weight:800">میانگین سن: {{ v.avgAge }}</span>
                <input type="checkbox" :checked="v.ck.age" @change="v.hide.age" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:grid;grid-template-columns:70px 1fr 55px 70px 90px;gap:10px;align-items:center;font-size:11px;color:#94a3b8;font-weight:700">
                <span>بازه سنی</span><span></span><span>تعداد</span><span>پرداختی</span><span>خدمت پرتکرار</span>
              </div>
              <template v-for="(r, rI) in v.ageRows" :key="rI">
                <div style="display:grid;grid-template-columns:70px 1fr 55px 70px 90px;gap:10px;align-items:center;font-size:12px">
                  <span style="font-weight:700;color:#0f172a">{{ r.rng }}</span>
                  <div style="height:12px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:linear-gradient(90deg,#60a5fa,#2563eb);border-radius:999px'"></div></div>
                  <span style="font-weight:800;color:#0f172a">{{ r.cnt }}</span>
                  <span style="color:#334155">{{ r.pay }}</span>
                  <span style="color:#64748b">{{ r.svc }}</span>
                </div>
              </template>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.sect" @dragover="v.dv.sect" @drop="v.dp.sect" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.sect) + ';order:' + (v.o.sect)" data-screen-label="درآمد بخش‌ها">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">درآمد و سود بخش‌ها</span>
                <input type="checkbox" :checked="v.ck.sect" @change="v.hide.sect" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <template v-for="(r, rI) in v.sectRows" :key="rI">
                <div style="display:flex;flex-direction:column;gap:5px">
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px">
                    <span style="font-weight:700;color:#0f172a">{{ r.n }} <span style="color:#94a3b8;font-weight:600;font-size:11px">{{ r.p }}</span></span>
                    <span style="color:#64748b">درآمد <b style="color:#0f172a">{{ r.rev }}</b> · سود <b style="color:#15803d">{{ r.prof }}</b></span>
                  </div>
                  <div style="height:11px;background:#f1f5f9;border-radius:999px;overflow:hidden;display:flex">
                    <div :style="'height:100%;width:' + (r.wp) + ';background:#16a34a'"></div>
                    <div :style="'height:100%;width:' + (r.wr) + ';background:#93c5fd'"></div>
                  </div>
                </div>
              </template>
              <div style="display:flex;gap:16px;font-size:11px;color:#64748b">
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#16a34a;display:inline-block"></i>سود</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#93c5fd;display:inline-block"></i>سایر درآمد</span>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.topsvc" @dragover="v.dv.topsvc" @drop="v.dp.topsvc" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.topsvc) + ';order:' + (v.o.topsvc)" data-screen-label="پردرآمدترین خدمات">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">پردرآمدترین و پرسودترین خدمات</span>
                <span style="font-size:11.5px;color:#94a3b8">مرتب‌سازی:</span>
                <button :style="v.srtRevSt" @click="v.onSrtRev">بیشترین درآمد</button>
                <button :style="v.srtProfSt" @click="v.onSrtProf">بیشترین سود</button>
                <input type="checkbox" :checked="v.ck.topsvc" @change="v.hide.topsvc" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <template v-for="(r, rI) in v.svcRows" :key="rI">
                <div style="display:grid;grid-template-columns:26px 130px 1fr 90px 90px;gap:10px;align-items:center;font-size:12.5px">
                  <span style="width:24px;height:24px;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-weight:800;font-size:11.5px;display:flex;align-items:center;justify-content:center">{{ r.i }}</span>
                  <span style="font-weight:700;color:#0f172a">{{ r.n }}</span>
                  <div style="display:flex;flex-direction:column;gap:4px">
                    <div style="height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.wr) + ';background:#2563eb;border-radius:999px'"></div></div>
                    <div style="height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.wp) + ';background:#16a34a;border-radius:999px'"></div></div>
                  </div>
                  <span style="color:#1d4ed8;font-weight:700">{{ r.rev }}</span>
                  <span style="color:#15803d;font-weight:700">{{ r.prof }}</span>
                </div>
              </template>
              <div style="display:flex;gap:16px;font-size:11px;color:#64748b;justify-content:flex-end">
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#2563eb;display:inline-block"></i>درآمد</span>
                <span style="display:flex;align-items:center;gap:5px"><i style="width:9px;height:9px;border-radius:3px;background:#16a34a;display:inline-block"></i>سود</span>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.cac" @dragover="v.dv.cac" @drop="v.dp.cac" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.cac) + ';order:' + (v.o.cac)" data-screen-label="هزینه جذب مشتری">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">هزینه جذب هر مشتری</span>
                <input type="checkbox" :checked="v.ck.cac" @change="v.hide.cac" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-radius:14px;padding:18px;text-align:center;display:flex;flex-direction:column;gap:4px">
                <span style="font-size:28px;font-weight:800;color:#1d4ed8">{{ v.cacPer }}</span>
                <span style="font-size:11.5px;color:#94a3b8">به ازای هر مشتری جدید</span>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap">
                <div style="flex:1;min-width:110px;background:#f8fafc;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#94a3b8">هزینه تبلیغات</span><span style="font-size:15px;font-weight:800;color:#b91c1c">{{ v.cacCost }}</span></div>
                <div style="flex:1;min-width:110px;background:#f8fafc;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#94a3b8">مشتریان جدید</span><span style="font-size:15px;font-weight:800;color:#0f172a">{{ v.cacNew }}</span></div>
                <div style="flex:1;min-width:110px;background:#f8fafc;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#94a3b8">میانگین درآمد هر مشتری</span><span style="font-size:15px;font-weight:800;color:#15803d">{{ v.cacAvg }}</span></div>
                <div style="flex:1;min-width:110px;background:#f0fdf4;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;color:#15803d">نسبت درآمد به هزینه جذب</span><span style="font-size:15px;font-weight:800;color:#15803d;direction:ltr;text-align:right">{{ v.cacRatio }}</span></div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.campperf" @dragover="v.dv.campperf" @drop="v.dp.campperf" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 2;display:' + (v.dsp.campperf) + ';order:' + (v.o.campperf)" data-screen-label="بازدهی کمپین‌ها">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">بازدهی کمپین‌ها</span>
                <template v-for="(op, opI) in v.cpChips" :key="opI">
                  <button :style="op.st" @click="op.on">{{ op.t }}</button>
                </template>
                <input type="checkbox" :checked="v.ck.campperf" @change="v.hide.campperf" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <template v-for="(r, rI) in v.cpRows" :key="rI">
                <div style="display:flex;flex-direction:column;gap:5px;background:#f8fafc;border-radius:12px;padding:10px 14px">
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px;flex-wrap:wrap;gap:4px">
                    <span style="font-weight:700;color:#0f172a">{{ r.n }} <span style="color:#94a3b8;font-size:10.5px;font-weight:500">{{ r.date }}</span></span>
                    <span style="color:#64748b;font-size:11.5px">هزینه {{ r.cost }} · تمایل {{ r.des }} · بازدهی <b style="color:#1d4ed8;direction:ltr;display:inline-block">{{ r.eff }}x</b></span>
                  </div>
                  <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:linear-gradient(90deg,#60a5fa,#1d4ed8);border-radius:999px'"></div></div>
                </div>
              </template>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.campdes" @dragover="v.dv.campdes" @drop="v.dp.campdes" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:1/-1;display:' + (v.dsp.campdes) + ';order:' + (v.o.campdes)" data-screen-label="کمپین‌ها بر اساس درجه تمایل">
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
  
            <div :draggable="true" @dragstart="v.dh.staffapt" @dragover="v.dv.staffapt" @drop="v.dp.staffapt" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.staffapt) + ';order:' + (v.o.staffapt)" data-screen-label="وقت‌دهی پرسنل">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">وقت‌دهی پرسنل</span>
                <input type="checkbox" :checked="v.ck.staffapt" @change="v.hide.staffapt" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <template v-for="(r, rI) in v.aptRows" :key="rI">
                <div style="display:flex;align-items:center;gap:10px;font-size:12.5px">
                  <img :src="r.ph" :style="'width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid ' + (r.ac) + ';flex-shrink:0'" alt="">
                  <span style="width:90px;font-weight:700;color:#0f172a">{{ r.n }}</span>
                  <div style="flex:1;height:10px;background:#f1f5f9;border-radius:999px;overflow:hidden"><div :style="'height:100%;width:' + (r.w) + ';background:' + (r.ac) + ';border-radius:999px'"></div></div>
                  <span style="width:64px;text-align:left;font-weight:800;color:#0f172a">{{ r.cnt }} وقت</span>
                </div>
              </template>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.cap" @dragover="v.dv.cap" @drop="v.dp.cap" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.cap) + ';order:' + (v.o.cap)" data-screen-label="گنجایش مجموعه">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">گنجایش مجموعه</span>
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
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px"><span style="color:#64748b">درآمد فعلی</span><b style="color:#0f172a">{{ v.capFilled }}</b></div>
                  <div style="display:flex;align-items:center;justify-content:space-between;font-size:12.5px"><span style="color:#64748b">تا تکمیل گنجایش</span><b style="color:#7c3aed">{{ v.capRemain }}</b></div>
                  <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b;background:#f8fafc;border-radius:12px;padding:10px 12px">
                    گنجایش (میلیون تومان):
                    <input type="number" :value="v.capV" @input="v.onCap" style="flex:1;width:80px;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;font-size:13px;font-weight:700;color:#0f172a;outline:none;direction:ltr;text-align:center">
                  </label>
                </div>
              </div>
            </div>
  
            <div :draggable="true" @dragstart="v.dh.gender" @dragover="v.dv.gender" @drop="v.dp.gender" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.gender) + ';order:' + (v.o.gender)" data-screen-label="ترکیب جنسیتی">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">ترکیب جنسیتی مراجعین</span>
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
            </div>
  
            <div :draggable="true" @dragstart="v.dh.newold" @dragover="v.dv.newold" @drop="v.dp.newold" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.newold) + ';order:' + (v.o.newold)" data-screen-label="مشتریان جدید و قدیم">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">مشتریان جدید و قدیم</span>
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
            </div>
  
            <div :draggable="true" @dragstart="v.dh.status" @dragover="v.dv.status" @drop="v.dp.status" :style="'background:#ffffff;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,0.06);padding:20px;flex-direction:column;gap:14px;min-width:0;grid-column:span 3;display:' + (v.dsp.status) + ';order:' + (v.o.status)" data-screen-label="وضعیت مشتری‌ها">
              <div style="display:flex;align-items:center;gap:10px">
                <span data-drag-handle="1" style="cursor:grab;color:#94a3b8;font-size:16px;line-height:1;padding:4px 7px;margin:-4px -7px;border-radius:8px;background:#f8fafc">⠿</span>
                <span style="font-weight:800;font-size:15px;color:#0f172a;flex:1">وضعیت مشتری‌ها</span>
                <input type="checkbox" :checked="v.ck.status" @change="v.hide.status" style="width:16px;height:16px;accent-color:#2563eb;cursor:pointer">
              </div>
              <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                <div style="position:relative;width:160px;height:160px;flex-shrink:0">
                  <svg width="160" height="160" viewBox="0 0 120 120">
                    <g transform="rotate(-90 60 60)">
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#8bc97b" stroke-width="12" :stroke-dasharray="v.ss1.da" :stroke-dashoffset="v.ss1.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#2e7d32" stroke-width="12" :stroke-dasharray="v.ss2.da" :stroke-dashoffset="v.ss2.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#e02424" stroke-width="12" :stroke-dasharray="v.ss3.da" :stroke-dashoffset="v.ss3.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#3b82f6" stroke-width="12" :stroke-dasharray="v.ss4.da" :stroke-dashoffset="v.ss4.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#f2a0a0" stroke-width="12" :stroke-dasharray="v.ss5.da" :stroke-dashoffset="v.ss5.of"></circle>
                      <circle cx="60" cy="60" r="54" fill="none" stroke="#f6d5d5" stroke-width="12" :stroke-dasharray="v.ss6.da" :stroke-dashoffset="v.ss6.of"></circle>
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
            </div>
  
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
          <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155;background:#f0fdf4;border-radius:12px;padding:12px 16px"><span style="color:#16a34a;font-weight:800">↑</span>درآمد تیرماه نسبت به خرداد ۱۴٪ رشد داشته است.</div>
          <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155;background:#fef2f2;border-radius:12px;padding:12px 16px"><span style="color:#dc2626;font-weight:800">!</span>نرخ کنسلی از میانگین ۳ ماه گذشته بالاتر است؛ پیگیری یادآوری نوبت‌ها توصیه می‌شود.</div>
          <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:#334155;background:#eff6ff;border-radius:12px;padding:12px 16px"><span style="color:#2563eb;font-weight:800">★</span>کانال اینستاگرام بیشترین بازگشت هزینه تبلیغات را در این دوره داشته است.</div>
        </div>
      </section>
      </template>
  
    </main>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ClinicReport',
  props: {
    estCancel: { type: Number, default: 30 },
    capacityDefault: { type: Number, default: 2500 },
    defaultOpen: { type: Boolean, default: true }
  },
  data() {
    const KEYS = ['ctype','staffinc','roi','loyal','cancel','bills','qc','cac','adch','campperf','docs','photo','city','age','topsvc','campdes','staffapt','sect','cap','gender','newold','status'];
    return {
      KEYS: ['ctype','staffinc','roi','loyal','cancel','bills','qc','cac','adch','campperf','docs','photo','city','age','topsvc','campdes','staffapt','sect','cap','gender','newold','status'],
      NAMES: {ctype:'دسته‌بندی مشتریان',roi:'بازگشت هزینه تبلیغات',loyal:'مشتریان وفادار',cancel:'نرخ کنسلی',bills:'هزینه‌ها',staffinc:'درآمد پرسنل و سقف',qc:'رضایت‌مندی (QC)',adch:'آمار کانال‌های تبلیغاتی',docs:'پزشکان و تبدیل مشاوره',photo:'آنالیز عکس‌ها',city:'آمار بر اساس شهر',age:'آمار سنی',sect:'درآمد و سود بخش‌ها',topsvc:'پردرآمدترین خدمات',cac:'هزینه جذب هر مشتری',campperf:'بازدهی کمپین‌ها',campdes:'کمپین‌ها بر اساس درجه تمایل',staffapt:'وقت‌دهی پرسنل',cap:'گنجایش مجموعه',gender:'ترکیب جنسیتی',newold:'مشتریان جدید و قدیم',status:'وضعیت مشتری‌ها'},
      s: {
      tab:'r', open:(this.defaultOpen===false?false:true), filterOpen:false, mngOpen:false,
      filters:{}, bdFrom:'', bdTo:'', hidden:{}, order:KEYS.slice(), ddOpen:null, ddQ:'',
      monthSel:3, loyalRange:6, staff:'همه', cap:String(this.capacityDefault ?? 2500),
      reportSummary:null, reportLoading:false, reportError:'', svcSort:'rev', campSort:'perf', from:'۱۴۰۵/۰۲/۰۱', to:'۱۴۰۵/۰۴/۳۱'}
      ,cancellationReport: null, cancellationLoading: false, reportSummary: null, reportLoading: false, staffTarget: 120
    };
  },
  computed: {
    v() {
    const S = this.s;
    const fa = n => Math.round(n).toLocaleString('fa-IR');
    const mm = n => fa(n) + ' میلیون';
    const pc = n => fa(n) + '٪';
    const C = 2 * Math.PI * 54;

    // chip style helper
    const chip = a => 'padding:5px 13px;border-radius:999px;border:1px solid ' + (a?'#2563eb':'#e2e8f0') + ';background:' + (a?'#eff6ff':'#ffffff') + ';color:' + (a?'#1d4ed8':'#475569') + ';font-size:12px;cursor:pointer;font-weight:' + (a?'700':'500');

    // filters
    const fCount = Object.values(S.filters).filter(Boolean).length + (S.bdFrom?1:0) + (S.bdTo?1:0);
    const ff = Math.max(0.45, 1 - 0.05 * fCount);
    const MF = [0.85, 0.95, 0.9, 1];
    const k = MF[S.monthSel] * ff;

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
      {id:'tags', label:'تگ‌های خدمات', items:['تزریقات','جوانسازی','لیزر','پوست','مو','لاغری','زیبایی لب','ضدپیری','درمانی','مراقبتی','دستگاهی','ترکیبی']},
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
    const gUp = {gb:'#dcfce7', gc:'#15803d'}, gDn = {gb:'#fee2e2', gc:'#b91c1c'};
    const sm = {sp:'auto', vs:'21px', ts:'12px'}, lg = {sp:'span 2', vs:'30px', ts:'13.5px'};
    const actualRevenue = S.reportSummary ? Number(S.reportSummary.kpis?.recognized_revenue || 0) / 1000000 : null;
    const actualRevenueText = actualRevenue === null ? '—' : actualRevenue.toLocaleString('fa-IR', {minimumFractionDigits: actualRevenue % 1 ? 1 : 0, maximumFractionDigits: 1}) + ' میلیون';
    const reportMoney = value => Number(value || 0).toLocaleString('fa-IR', {minimumFractionDigits: Number(value || 0) % 1000000 ? 1 : 0, maximumFractionDigits: 1}) + ' میلیون';
    const advertisingCost = S.reportSummary?.expenses?.advertising_total;
    const netProfit = S.reportSummary?.kpis?.net_profit;
    const kpis = [
      {t:'درآمد کل', v:actualRevenueText, g:S.reportLoading ? 'در حال بروزرسانی…' : 'از نوبت‌های انجام‌شده', ...gUp, bg:'#eff6ff', bd:'#bfdbfe', tc:'#1d4ed8', ...lg},
      {t:'سود خالص', v:S.reportSummary ? reportMoney(netProfit / 1000000) : '—', g:S.reportLoading ? 'در حال بروزرسانی…' : 'درآمد پس از کسر همه هزینه‌ها', ...gUp, bg:'#f0fdf4', bd:'#bbf7d0', tc:'#15803d', ...lg},
      {t:'تبلیغات', v:S.reportSummary ? reportMoney(advertisingCost / 1000000) : '—', g:S.reportLoading ? 'در حال بروزرسانی…' : 'هزینه تبلیغات ثبت‌شده', ...gDn, bg:'#fff7ed', bd:'#fed7aa', tc:'#c2410c', ...sm},
      {t:'هزینه پزشک', v:mm(512*k), g:'↑ +۶٪', ...gDn, bg:'#f0f9ff', bd:'#bae6fd', tc:'#0369a1', ...sm},
      {t:'حقوق پرسنل', v:mm(238*k), g:'— ۰٪', gb:'#f1f5f9', gc:'#64748b', bg:'#faf5ff', bd:'#e9d5ff', tc:'#7c3aed', ...sm},
      {t:'مواد مصرفی', v:mm(174*k), g:'↓ −۳٪', ...gUp, bg:'#fffbeb', bd:'#fde68a', tc:'#b45309', ...sm},
      {t:'میزان تخفیف‌ها', v:mm(96*k), g:'↑ +۹٪', ...gDn, bg:'#fdf2f8', bd:'#fbcfe8', tc:'#be185d', ...sm},
      {t:'تعداد مراجعین', v:fa(512*k)+' نفر', g:'↑ +۱۱٪', ...gUp, bg:'#f0fdfa', bd:'#99f6e4', tc:'#0d9488', ...sm}
    ];

    // Expenses are already grouped and date-filtered by the report API.
    const expenses = S.reportSummary?.expenses;
    const billItems = expenses?.items || [];
    const billMax = Math.max(0, ...billItems.map(item => Number(item.amount) || 0));
    const billMoney = value => Number(value || 0).toLocaleString('fa-IR') + ' تومان';
    const billRows = billItems.map(item => ({
      n: item.category || 'بدون دسته‌بندی',
      v: billMoney(item.amount),
      w: (billMax > 0 ? Math.round(Number(item.amount) / billMax * 100) : 0) + '%',
    }));
    const billSum = Number(expenses?.total || 0);
    const billRev = Number(S.reportSummary?.kpis?.recognized_revenue || 0);
    const billRevV = expenses ? billMoney(billRev) : '—';
    const billSumV = expenses ? billMoney(billSum) : '—';
    const billNetV = expenses ? billMoney(billRev - billSum) : '—';
    const cancelP = this.estCancel ?? 30;
    const estV = mm(730 * k * (1 - cancelP/100));
    const estNote = 'بر اساس وقت‌های آینده با ' + fa(cancelP) + '٪ کنسلی';

    // months
    const MONTHS = [
      {name:'فروردین', g:12, pts:'0,24 12,20 24,22 36,14 48,15 60,6'},
      {name:'اردیبهشت', g:8, pts:'0,22 12,23 24,18 36,19 48,12 60,8'},
      {name:'خرداد', g:-5, pts:'0,10 12,13 24,11 36,17 48,16 60,23'},
      {name:'تیر', g:14, pts:'0,26 12,21 24,23 36,15 48,12 60,5'}
    ];
    const box = a => 'flex:1;min-width:160px;background:' + (a?'#eff6ff':'#ffffff') + ';border:1.5px solid ' + (a?'#2563eb':'#e2e8f0') + ';border-radius:14px;padding:14px 16px;cursor:pointer;display:flex;flex-direction:column;gap:6px;box-shadow:0 1px 3px rgba(15,23,42,0.05)';
    const monthsV = MONTHS.map((m, i) => ({
      name: m.name + ' ۱۴۰۵',
      val: mm(1840 * MF[i] * ff),
      g: fa(Math.abs(m.g)) + '٪' + (m.g > 0 ? '+' : '−'),
      arrow: m.g > 0 ? '↑' : '↓',
      col: m.g > 0 ? '#16a34a' : '#dc2626',
      pts: m.pts,
      st: box(i === S.monthSel),
      on: () => this.selectReportMonth(i)
    }));

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

    // ctype
    const ctT = Math.round(512 * k);
    const ctP = [0.52, 0.16, 0.22, 0.10];
    const [cs1, cs2, cs3, cs4] = segs(ctP);
    const ctCols = ['#94a3b8','#93c5fd','#f59e0b','#dc2626'];
    const ctNames = ['معمولی','خوب','CIP','مشکل‌ساز'];
    const ctRev = [415, 320, 590, 68].map(v => mm(v*k));
    const ctLegend = ctP.map((p, i) => ({n: ctNames[i], c: ctCols[i], cnt: fa(ctT*p)+' نفر', p: pc(p*100), w: (p*100)+'%', rev: ctRev[i]}));

    // roi
    const roiBars = MONTHS.map((m, i) => ({n: m.name, ch: Math.round(85*MF[i]/240*100)+'px', rh: Math.round(240*MF[i]/240*100)+'px'}));

    // loyal
    const L = S.loyalRange === 6 ? {tot:940, ret:611} : {tot:480, ret:300};
    const loyP = L.ret / L.tot;

    // cancel
    const came = Math.round(512*k), canc = Math.round(62*k);
    const cnR = canc / (came + canc);

    // staff income
    const STAFF = [
      {n:'سارا احمدی', v:148, apt:142, ac:'#2563eb', ph:'https://i.pravatar.cc/72?img=47'},
      {n:'مریم رضایی', v:112, apt:128, ac:'#0d9488', ph:'https://i.pravatar.cc/72?img=45'},
      {n:'نگار موسوی', v:96, apt:117, ac:'#7c3aed', ph:'https://i.pravatar.cc/72?img=44'},
      {n:'الهام کریمی', v:131, apt:96, ac:'#db2777', ph:'https://i.pravatar.cc/72?img=43'},
      {n:'رویا شریفی', v:88, apt:84, ac:'#f59e0b', ph:'https://i.pravatar.cc/72?img=41'}
    ];
    const target = Math.max(0, Number(S.staffTarget) || 0);
    const staffChips = ['همه'].concat(STAFF.map(s => s.n)).map(n => ({t: n, st: chip(S.staff === n), on: () => this.set({staff: n})}));
    const shown = STAFF.filter(s => S.staff === 'همه' || s.n === S.staff);
    const staffBars = shown.map(s => {
      const v = Math.round(s.v * k);
      const reached = v >= target;
      return {n: s.n.split(' ')[0], ph: s.ph, v: mm(v), h: Math.round(v * 0.75) + 'px', c: reached ? '#16a34a' : '#2563eb', vc: reached ? '#15803d' : '#334155', reached};
    });
    const overCnt = shown.filter(s => Math.round(s.v*k) >= target).length;
    const staffOverTxt = overCnt > 0 ? '⭐ ' + fa(overCnt) + ' نفر به تارگت رسیدند' : 'هنوز کسی به تارگت نرسیده است';
    const targetB = (22 + Math.round(target * 0.75)) + 'px';

    // qc
    const qcRows = [
      {n:'عالی', p:pc(58), w:'58%', c:'#16a34a'},
      {n:'خوب', p:pc(29), w:'29%', c:'#4ade80'},
      {n:'متوسط', p:pc(9), w:'9%', c:'#f59e0b'},
      {n:'ناراضی', p:pc(4), w:'4%', c:'#dc2626'}
    ];

    // ad channels
    const ADS = [
      {n:'اینستاگرام', lg:'https://cdn.simpleicons.org/instagram/E4405F', cnt:210, cost:45, rev:168},
      {n:'معرفی دوستان', lg:'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>', cnt:90, cost:0, rev:85},
      {n:'گوگل', lg:'https://cdn.simpleicons.org/google', cnt:60, cost:18, rev:52},
      {n:'یوتیوب', lg:'https://cdn.simpleicons.org/youtube/FF0000', cnt:25, cost:12, rev:20}
    ];
    const adRows = ADS.map(a => {
      const r = a.cost ? a.rev / a.cost : 0;
      return {n: a.n, lg: a.lg, cnt: fa(a.cnt*k), cost: a.cost ? mm(a.cost*k) : '—', rev: mm(a.rev*k),
        roi: a.cost ? (Math.round(r*10)/10).toLocaleString('fa-IR') + 'x' : '∞',
        rc: (!a.cost || r >= 1) ? '#15803d' : '#b91c1c', w: Math.round(a.rev/168*100)+'%'};
    });

    // docs
    const DOCS = [
      {n:'دکتر محمدی', ph:'https://i.pravatar.cc/72?img=12', bring:620, pors:93, fix:40, cons:120, done:78},
      {n:'دکتر افشار', ph:'https://i.pravatar.cc/72?img=13', bring:480, pors:72, fix:40, cons:95, done:52},
      {n:'دکتر سلطانی', ph:'https://i.pravatar.cc/72?img=59', bring:390, pors:58, fix:35, cons:80, done:36}
    ];
    const bestConv = Math.max(...DOCS.map(d => d.done/d.cons));
    const docRows = DOCS.map(d => {
      const conv = d.done / d.cons;
      return {n: d.n, ph: d.ph, bring: mm(d.bring*k), pors: mm(d.pors*k), fix: mm(d.fix), pay: mm(d.pors*k + d.fix),
        cons: fa(d.cons*k), done: fa(d.done*k), convP: pc(conv*100), convW: Math.round(conv*100)+'%',
        cc: conv >= 0.6 ? '#15803d' : conv >= 0.5 ? '#f59e0b' : '#dc2626',
        bd: conv === bestConv ? 'inline-block' : 'none'};
    });

    // photos
    const PH = [
      {n:'ژل لب', up:20, ok:5},
      {n:'لیفت با نخ', up:100, ok:4},
      {n:'بوتاکس', up:64, ok:22},
      {n:'فیلر گونه', up:41, ok:18}
    ];
    const phRows = PH.map(p => {
      const r = p.ok / p.up;
      return {n: p.n, txt: 'از ' + fa(p.up*k) + ' عکس، ' + fa(p.ok*k) + ' با کیفیت —', p: pc(r*100),
        w: Math.round(r*100)+'%', c: r >= 0.3 ? '#16a34a' : r >= 0.15 ? '#f59e0b' : '#dc2626'};
    });
    const phTotal = 'مجموع: ' + fa(225*k) + ' عکس';

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
    const cityRows = CT.map(c => ({n: c.n, cnt: fa(c.cnt*k), rev: mm(c.rev*k), w: Math.round(c.cnt/268*100)+'%'}));
    const cityDots = CT.map(c => ({n: c.n, x: c.x, y: c.y, r: Math.max(9, Math.round(c.cnt/268*30)) + 'px',
      bg: 'rgba(37,99,235,0.45)', bc: '#2563eb', tc: '#334155'
    })).concat(CT0.map(c => ({n: c.n, x: c.x, y: c.y, r: '8px',
      bg: '#ffffff', bc: '#cbd5e1', tc: '#b6c2d1'
    })));

    // ages
    const AG = [
      {rng:'زیر ۳۰', cnt:142, pay:310, svc:'ژل لب'},
      {rng:'۳۰ تا ۴۰', cnt:198, pay:560, svc:'بوتاکس'},
      {rng:'۴۰ تا ۵۰', cnt:118, pay:420, svc:'لیفت با نخ'},
      {rng:'بالای ۵۰', cnt:54, pay:230, svc:'فیلر گونه'}
    ];
    const ageRows = AG.map(a => ({rng: a.rng, cnt: fa(a.cnt*k), pay: mm(a.pay*k), svc: a.svc, w: Math.round(a.cnt/198*100)+'%'}));

    // sections
    const SE = [
      {n:'لیفت با نخ', rev:780, prof:340},
      {n:'ژل لب', rev:420, prof:160},
      {n:'هایفو', rev:360, prof:130},
      {n:'کرایو', rev:290, prof:95}
    ];
    const seTot = 1850;
    const sectRows = SE.map(s => ({n: s.n, rev: mm(s.rev*k), prof: mm(s.prof*k), p: pc(Math.round(s.rev/seTot*100)),
      wp: Math.round(s.prof/seTot*100)+'%', wr: Math.round((s.rev-s.prof)/seTot*100)+'%'}));

    // top services
    const SV = [
      {n:'بوتاکس', rev:420, prof:210},
      {n:'ژل لب', rev:380, prof:190},
      {n:'لیفت با نخ', rev:310, prof:140},
      {n:'لیزر موهای زائد', rev:280, prof:120},
      {n:'هایفو', rev:190, prof:95},
      {n:'مزوتراپی', rev:140, prof:60}
    ];
    const sorted = SV.slice().sort((a,b) => S.svcSort === 'rev' ? b.rev - a.rev : b.prof - a.prof);
    const svcRows = sorted.map((s, i) => ({i: fa(i+1), n: s.n, rev: mm(s.rev*k), prof: mm(s.prof*k),
      wr: Math.round(s.rev/420*100)+'%', wp: Math.round(s.prof/420*100)+'%'}));

    // cac
    const adCost = 75, newC = Math.round(176*k);
    const cacPer = fa(adCost*1000000*k/newC/1000) + ' هزار تومان';

    // campaigns
    const CAMPS = [
      {n:'جشنواره تابستانه', eff:3.2, des:2.6, date:'۱۴۰۵/۰۳', dateN:3, cost:38, d1:45, d2:78, d3:120},
      {n:'کمپین عید نوروز', eff:2.7, des:2.9, date:'۱۴۰۵/۰۱', dateN:2, cost:52, d1:60, d2:95, d3:88},
      {n:'همکاری بلاگر', eff:1.9, des:2.1, date:'۱۴۰۵/۰۲', dateN:1, cost:30, d1:70, d2:40, d3:25},
      {n:'کمپین یلدا', eff:1.2, des:1.5, date:'۱۴۰۴/۰۹', dateN:0, cost:24, d1:55, d2:28, d3:12}
    ];
    const cSorted = CAMPS.slice().sort((a,b) => S.campSort === 'perf' ? b.eff - a.eff : S.campSort === 'des' ? b.des - a.des : b.dateN - a.dateN);
    const cpRows = cSorted.map(c => ({n: c.n, date: c.date, cost: mm(c.cost), des: c.des.toLocaleString('fa-IR'),
      eff: c.eff.toLocaleString('fa-IR'), w: Math.round(c.eff/3.2*100)+'%'}));
    const cpChips = [['perf','بازدهی'],['des','درجه تمایل'],['date','تاریخ']].map(([id,t]) => ({t, st: chip(S.campSort === id), on: () => this.set({campSort: id})}));
    const cdRows = CAMPS.map(c => ({n: c.n, cost: mm(c.cost),
      h1: Math.round(c.d1/120*120)+'px', h2: Math.round(c.d2/120*120)+'px', h3: Math.round(c.d3/120*120)+'px'}));

    // staff appointments
    const aptRows = STAFF.map(s => ({n: s.n, init: s.n[0], ph: s.ph, ac: s.ac, cnt: fa(s.apt*k), w: Math.round(s.apt/142*100)+'%'}));

    // capacity
    const capN = Math.max(1, parseFloat(S.cap) || 2500);
    const filled = 1840 * k;
    const capF = Math.min(1, filled / capN);

    // gender
    const gTot = Math.round(512*k);
    const fP = 0.78;
    const gF = {p: pc(78), cnt: fa(gTot*fP), y: String(Math.round(200*(1-fP)))};
    const gM = {p: pc(22), cnt: fa(gTot*(1-fP)), y: String(Math.round(200*fP))};

    // new/old
    const nNew = Math.round(176*k), nOld = Math.round(336*k), nT = nNew + nOld;
    const [ns1, ns2] = segs([nNew/nT, nOld/nT]);

    // status
    const ST = [
      {n:'آمد', v:512, c:'#8bc97b'},
      {n:'وقت داده شد', v:31, c:'#2e7d32'},
      {n:'کنسل شد', v:62, c:'#e02424'},
      {n:'انتقال روز', v:25, c:'#3b82f6'},
      {n:'پاسخ نداد', v:20, c:'#f2a0a0'},
      {n:'پیگیری', v:12, c:'#f6d5d5'}
    ];
    const stT = ST.reduce((a,s) => a + s.v, 0);
    const stSegs = segs(ST.map(s => s.v/stT));
    const stLegend = ST.map(s => ({n: s.n, c: s.c, cnt: fa(s.v*k), p: pc(Math.round(s.v/stT*100))}));

    const tabSt = a => 'border:none;border-radius:999px;padding:8px 22px;font-size:13px;cursor:pointer;font-weight:' + (a?'800':'600') + ';background:' + (a?'#ffffff':'transparent') + ';color:' + (a?'#1d4ed8':'#64748b') + ';box-shadow:' + (a?'0 1px 4px rgba(15,23,42,0.1)':'none');

    return {
      // tabs
      isR: S.tab === 'r', isA: S.tab === 'a',
      tR: tabSt(S.tab === 'r'), tA: tabSt(S.tab === 'a'),
      onTR: () => this.set({tab:'r'}), onTA: () => this.set({tab:'a'}),
      // date bar
      fromV: S.from, toV: S.to,
      onFrom: e => this.updateReportDate('from', e.target.value), onTo: e => this.updateReportDate('to', e.target.value),
      showDash: S.open, toggleOpen: () => this.set(s => ({open: !s.open})),
      openLbl: S.open ? 'بستن داشبوردها' : 'نمایش داشبوردها',
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
      billRows, billRevV, billSumV, billNetV,
      // dash maps
      o, dsp, ck, hide, dh, dv, dp,
      // ctype
      cs1, cs2, cs3, cs4, ctLegend, ctTotal: fa(ctT),
      // roi
      roiCost: mm(85*k), roiRev: mm(240*k), roiX: (2.8).toLocaleString('fa-IR'), roiOkTxt: '✓ هزینه برگشته', roiBars,
      // loyal
      l3st: chip(S.loyalRange === 3), l6st: chip(S.loyalRange === 6),
      onL3: () => this.set({loyalRange: 3}), onL6: () => this.set({loyalRange: 6}),
      loyTot: fa(L.tot*ff), loyRet: fa(L.ret*ff), loyLost: fa((L.tot-L.ret)*ff),
      loyP: pc(Math.round(loyP*100)), loyRetW: Math.round(loyP*100)+'%',
      // cancel
      cnCame: fa(S.cancellationReport?.attended ?? came) + ' نفر',
      cnCanc: fa(S.cancellationReport?.cancelled ?? canc) + ' نفر',
      cnRate: pc(S.cancellationReport?.rate ?? Math.round(cnR*1000)/10),
      cnDa: arc((S.cancellationReport?.rate ?? (cnR * 100)) / 100),
      cnLoading: S.cancellationLoading,
      // staff
      staffChips, staffBars, targetB, targetTxt: 'سقف: ' + mm(target),
      staffSum: mm(shown.reduce((a,s) => a + s.v*k, 0)), staffOverTxt,
      // qc
      qcDa: arc(0.87), qcPct: pc(87), qcRows,
      // others
      adRows, docRows, phRows, phTotal, cityRows, cityDots, ageRows, avgAge: (36.4).toLocaleString('fa-IR') + ' سال',
      sectRows, svcRows,
      srtRevSt: chip(S.svcSort === 'rev'), srtProfSt: chip(S.svcSort === 'prof'),
      onSrtRev: () => this.set({svcSort:'rev'}), onSrtProf: () => this.set({svcSort:'prof'}),
      cacPer, cacCost: mm(adCost*k), cacNew: fa(newC) + ' نفر', cacAvg: (3.6).toLocaleString('fa-IR') + ' میلیون', cacRatio: (7.4).toLocaleString('fa-IR') + 'x',
      cpRows, cpChips, cdRows, aptRows,
      // capacity
      capDa: arc(capF), capW: Math.round(capF*100)+'%', capPct: pc(Math.round(capF*100)), capFilled: mm(filled),
      capRemain: mm(Math.max(0, capN - filled)), capV: S.cap, onCap: e => this.set({cap: e.target.value}),
      // gender / newold / status
      gF, gM,
      ns1, ns2, noTotal: fa(nT), noNew: fa(nNew), noOld: fa(nOld),
      noNewP: pc(Math.round(nNew/nT*100)), noOldP: pc(Math.round(nOld/nT*100)),
      ss1: stSegs[0], ss2: stSegs[1], ss3: stSegs[2], ss4: stSegs[3], ss5: stSegs[4], ss6: stSegs[5],
      stLegend, stTotal: fa(stT*k)
    };
    }
  },
  mounted() {
    this.loadCancellationRate();
    this.loadReportSummary();
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
    document.removeEventListener('pointerdown', this._onPointerDown, true);
  },
  beforeDestroy() {
    document.removeEventListener('pointerdown', this._onPointerDown, true);
  },
  methods: {
    selectReportMonth(index) {
      const month = String(index + 1).padStart(2, '0');
      this.set({
        monthSel: index,
        from: `1405/${month}/01`,
        to: `1405/${month}/31`,
        cancellationReport: null,
      });
      this.loadCancellationRate();
      this.loadReportSummary();
    },
    updateReportDate(key, value) {
      this._reportRequestId = (this._reportRequestId || 0) + 1;
      this.set({[key]: value, reportSummary: null, reportLoading: true, reportError: ''});
      clearTimeout(this._cancellationTimer);
      this._cancellationTimer = setTimeout(() => {
        this.loadCancellationRate();
        this.loadReportSummary();
      }, 350);
    },
    async loadCancellationRate() {
      const requestId = (this._cancellationRequestId || 0) + 1;
      this._cancellationRequestId = requestId;
      this.set({cancellationLoading: true});
      try {
        const { data } = await axios.get('/api/clinic-report/cancellation-rate', {
          params: { from: this.s.from, to: this.s.to },
        });
        if (requestId === this._cancellationRequestId) this.set({cancellationReport: data});
      } catch (error) {
        // Until a valid date range is entered, retain the last successful value.
        console.warn('Clinic cancellation report could not be loaded.', error);
      } finally {
        if (requestId === this._cancellationRequestId) this.set({cancellationLoading: false});
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
        this.set({staffTarget: Math.max(0, Number(data.report_staff_target) || 0)});
      } catch (error) {
        console.warn('Report staff target could not be loaded.', error);
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
        ['سود خالص', 486], ['هزینه پزشک', 512], ['حقوق پرسنل', 238], ['مواد مصرفی', 174], ['میزان تخفیف‌ها', 96], ['هزینه‌ها', this.s.reportSummary ? Number(this.s.reportSummary.expenses?.total || 0) / 1000000 : '—'], ['تعداد مراجعین', 512],
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
</style>
