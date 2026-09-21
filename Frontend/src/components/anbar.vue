<template>
  <div class="inventory-page" :class="{ 'movement-page-active': inventoryView === 'movements' || inventoryView === 'service-tags' }">
    <section v-if="false" class="inventory-view-switch">
      <div class="inventory-view-title">
        <span>مدیریت خدمات</span>
        <h2>نمایش خدمات را انتخاب کنید</h2>
        <p>برای کارهای روزمره از جدول خدمات استفاده کنید و برای بررسی سریع، نمای چارت را ببینید.</p>
      </div>

      <div class="inventory-tabs" role="tablist" aria-label="نوع نمایش خدمات">
        <button
          type="button"
          role="tab"
          :aria-selected="inventoryView === 'table'"
          :class="{ active: inventoryView === 'table' }"
          @click="inventoryView = 'table'"
        >
          <span>جدولی</span>
          <strong>نمایش جدولی</strong>
          <small>ثبت، ویرایش، جست‌وجو و پورسانت</small>
        </button>

        <button
          type="button"
          role="tab"
          :aria-selected="inventoryView === 'chart'"
          :class="{ active: inventoryView === 'chart' }"
          @click="inventoryView = 'chart'"
        >
          <span>چارت</span>
          <strong>نمایش به صورت چارت</strong>
          <small>بررسی سریع خدمات بخش انتخاب‌شده</small>
        </button>
      </div>
    </section>

    <aside v-if="inventoryView !== 'movements' && inventoryView !== 'service-tags'" class="section-panel">
      <div class="inventory-structure-head">
        <button class="structure-add-root-btn" type="button" @click="addRootSection">
          <span>+</span>
          خدمت جدید
        </button>
        <div class="structure-title-wrap"><h3>ساختار خدمات</h3></div>
      </div>

      <div class="inventory-tree">
        <div
          v-for="node in inventoryTreeNodes"
          :key="sectionKey(node.section)"
          class="tree-node"
            :class="{ active: activeTreeKey === sectionKey(node.section), root: node.level === 1, leaf: !node.hasChildren }"
          :style="{ '--tree-depth': node.level - 1, '--node-accent': sectionColor(node.section) }"
          @click="selectTreeNode(node.section)"
        >
          <button
            type="button"
            class="tree-toggle-btn"
            :class="{ open: isTreeExpanded(node.section) }"
            :disabled="!node.hasChildren"
            title="باز و بسته کردن"
            aria-label="باز و بسته کردن"
            @click.stop="toggleTreeNode(node.section)"
          ></button>
          <input v-model="node.section.name" :title="node.section.name || treePlaceholder(node.level)" :placeholder="treePlaceholder(node.level)" @click.stop @focus="selectTreeNode(node.section)">
          <div class="tree-color-picker"><button type="button" class="tree-color-btn" title="رنگ فعلی این آیتم؛ برای بازکردن پالت کلیک کنید" @click.stop="toggleSectionColorMenu(sectionKey(node.section))"><span :style="{ background: sectionColor(node.section) }"></span></button><div v-if="colorPickerSectionKey === sectionKey(node.section)" class="section-color-menu tree-color-menu" @click.stop><button v-for="color in sectionColorsList" :key="color" type="button" class="section-color-swatch" :style="{ background: color }" :title="`انتخاب رنگ ${color}`" @click="setSectionColorFor(sectionKey(node.section), color)"></button><button type="button" class="section-color-reset" @click="setSectionColorFor(sectionKey(node.section), '')">حذف رنگ</button></div></div>
          <span class="tree-count">{{ treeNodeCount(node.section).toLocaleString('fa-IR') }}</span>
          <span class="tree-spacer" aria-hidden="true"></span>
          <button
            type="button"
            class="tree-add-btn"
            title="افزودن زیرشاخه"
            aria-label="افزودن زیرشاخه"
            @click.stop="addChildSection(sectionKey(node.section), node.level + 1)"
          >+</button>
          <button type="button" class="tree-more-btn" title="حذف" aria-label="حذف" @click.stop="removeSectionNode(node.section)">×</button>
        </div>

            <small v-if="!inventoryTreeNodes.length" class="tree-empty">اولین خدمت را بسازید</small>
      </div>

      <button v-if="false" class="delete-section-btn" type="button" @click="removeActiveSection">
        <span aria-hidden="true">×</span>
        حذف گروه انتخاب‌شده
      </button>
    </aside>

    <main class="inventory-main" :class="{ 'chart-mode': inventoryView === 'chart', 'movement-mode': inventoryView === 'movements', 'service-tags-mode': inventoryView === 'service-tags' }">
      <section v-if="inventoryView !== 'movements' && inventoryView !== 'service-tags'" class="inventory-toolbar">
        <div class="inventory-search">
          <span class="search-icon" aria-hidden="true">⌕</span>
          <input
            v-model.trim="searchQuery"
            type="search"
            placeholder="جست‌وجو در کل خدمات؛ نام خدمت، بخش، مبلغ یا معرف..."
            aria-label="جست‌وجو در کل خدمات"
          >
          <span v-if="searchQuery" class="search-result-count">
            {{ displayedRows.length }} نتیجه
          </span>
          <button
            v-if="searchQuery"
            class="search-clear"
            type="button"
            aria-label="پاک کردن جست‌وجو"
            @click="searchQuery = ''"
          >×</button>
        </div>

        <div class="inventory-inline-tabs" role="tablist" aria-label="نوع نمایش خدمات">
          <button
            type="button"
            role="tab"
            title="نمایش جدولی"
            aria-label="نمایش جدولی"
            :aria-selected="inventoryView === 'table'"
            :class="{ active: inventoryView === 'table' }"
            @click="inventoryView = 'table'"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M4 10h16M9 5v14M15 5v14"/></svg>
          </button>
          <button
            type="button"
            role="tab"
            title="نمایش چارت"
            aria-label="نمایش چارت"
            :aria-selected="inventoryView === 'chart'"
            :class="{ active: inventoryView === 'chart' }"
            @click="inventoryView = 'chart'"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V10h4v9M10 19V5h4v14M16 19v-7h4v7M3 19h18"/></svg>
          </button>
        </div>

        <div class="inventory-save-actions">
          <button
            class="global-commission-btn"
            type="button"
            title="تعریف پورسانت کلی برای همه خدمات"
            aria-label="تعریف پورسانت کلی برای همه خدمات"
            @click="openCommissionModal(null, 'all')"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M9.2 15.4 14.9 8.6M9.5 9.5h.01M14.5 14.5h.01"/></svg>
            <span>پورسانت کلی</span>
          </button>
          <button class="service-tags-page-btn" type="button" @click="openServiceTagsPage">
            <span>🏷</span>
            تگ‌های خدمات
          </button>
          <button class="inventory-addons-page-btn" type="button" @click="openAddonManager">
            <span>✦</span>
            مدیریت جانبی‌ها
          </button>
          <span class="save-status" :class="saveState">
            {{ saveStatusText }}
          </span>
          <span v-if="hasUnsavedChanges" class="save-reminder">برای ثبت تغییرات روی «ذخیره تغییرات» بزنید</span>
          <button
            class="save-inventory-btn"
            type="button"
            :disabled="isSaving || isFetching || !hasUnsavedChanges"
            @click="saveData(true)"
          >
            {{ isSaving ? 'در حال ذخیره...' : hasUnsavedChanges ? 'ذخیره تغییرات' : 'تغییری برای ذخیره نیست' }}
          </button>
        </div>
      </section>

      <section v-if="inventoryView === 'table'" class="table-section">
        <div v-if="false" class="inventory-search">
          <span class="search-icon" aria-hidden="true">⌕</span>
          <input
            v-model.trim="searchQuery"
            type="search"
            placeholder="جست‌وجو در کل خدمات؛ نام خدمت، بخش، مبلغ یا معرف..."
            aria-label="جست‌وجو در کل خدمات"
          >
          <span v-if="searchQuery" class="search-result-count">
            {{ displayedRows.length }} نتیجه
          </span>
          <button
            v-if="searchQuery"
            class="search-clear"
            type="button"
            aria-label="پاک کردن جست‌وجو"
            @click="searchQuery = ''"
          >×</button>
        </div>

        <div v-if="!needsCompletedHierarchy || isRootSelection || searchQuery" class="panel-head">
          <div>
            <h3>{{ searchQuery ? 'نتایج جست‌وجو در کل خدمات' : isRootSelection ? `گروه کلی ${activeSectionName}` : inventoryTableTitle }}</h3>
            <p>{{ searchQuery ? 'نتایج همه بخش‌ها نمایش داده می‌شوند.' : isRootSelection ? 'پورسانت کلی، روی همه آیتم‌های زیرگروه‌های این گروه اعمال می‌شود.' : inventoryTableSubtitle }}</p>
          </div>
          <div class="panel-actions">
            <button class="text-btn ghost" type="button" @click="openCommissionModal(isRootSelection ? null : selectedRow, isRootSelection ? 'group' : 'section')">
              {{ isRootSelection ? 'ثبت پورسانت کلی گروه' : 'ثبت پورسانت' }}
            </button>
            <button
              v-if="!isRootSelection"
              class="text-btn primary"
              type="button"
              :disabled="needsCompletedHierarchy"
              :title="needsCompletedHierarchy ? 'ابتدا یک زیرشاخه انتخاب کنید' : 'افزودن ردیف جدید'"
              @click="addRow"
            >+ ردیف جدید</button>
          </div>
        </div>

        <div v-if="isRootSelection && !searchQuery" class="inventory-branch-message">
          <strong>گروه کلی انتخاب شده است</strong>
          <span>برای همه آیتم‌های زیرگروه‌های «{{ activeSectionName }}» پورسانت کلی ثبت کنید.</span>
        </div>

        <div v-else-if="needsCompletedHierarchy && !searchQuery" class="inventory-branch-message">
          <strong>لطفا زیرشاخه را انتخاب کنید</strong>
          <span>برای نمایش یا ثبت خدمت‌ها، یک زیرشاخه از خدمات را انتخاب کنید.</span>
        </div>

        <div v-else class="table-wrap">
          <table>
            <colgroup>
              <col class="name-col">
              <col class="tags-col">
              <col class="addons-col">
              <col class="money-col">
              <col class="money-col">
              <col class="stock-col">
              <col class="min-col">
              <col class="followup-col">
              <col class="commission-col">
              <col class="active-col">
              <col class="booking-col">
              <col class="action-col">
            </colgroup>
            <thead>
              <tr>
                <th>نام کالا / خدمت</th>
                <th>تگ‌های خدمات</th>
                <th title="جانبی‌های پیش‌فرض">جانبی‌ها</th>
                <th>قیمت کالا</th>
                <th>هزینه مواد</th>
                <th>موجودی</th>
                <th>حداقل</th>
                <th>دوره پیگیری<br><small>(روز)</small></th>
                <th>پورسانت کلی</th>
                <th>فعال</th>
                <th>وقت‌دهی</th>
                <th></th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(row, index) in displayedRows"
                :key="row.localId"
                :title="searchQuery ? `بخش: ${sectionNameForRow(row)}` : ''"
                :class="{ selected: selectedRow?.localId === row.localId }"
                @click="selectRow(row)"
              >
                <td>
                  <input v-model="row.name" type="text" placeholder="نام کالا">
                  <small v-if="searchQuery" class="row-section-name">{{ sectionNameForRow(row) }}</small>
                </td>
                <td>
                  <div class="service-tags-editor" @click.stop>
                    <span v-for="(tag, tagIndex) in row.serviceTags" :key="`${row.localId}-tag-${tagIndex}`">
                      {{ tag }}
                      <button type="button" title="حذف تگ" @click.stop="removeServiceTag(row, tagIndex)">×</button>
                    </span>
                    <div class="service-tag-picker">
                      <input
                        v-model.trim="row.tagDraft"
                        type="search"
                        placeholder="جست‌وجو و انتخاب تگ"
                        @focus="openServiceTagPicker(row, $event)"
                        @input="openServiceTagPicker(row, $event)"
                      >
                    </div>
                  </div>
                </td>
                <td>
                  <button class="inventory-addons-btn" type="button" @click.stop="openDefaultAddonsModal(row)">
                    <b>{{ row.addonDefinitionIds.length.toLocaleString('fa-IR') }}</b><span>+</span>
                  </button>
                </td>
                <td>
                  <input
                    type="text"
                    :value="formatNumberWithCommas(row.amount)"
                    @input="e => onMoneyInput(e, row, 'amount')"
                  >
                </td>
                <td>
                  <input
                    type="text"
                    :value="formatNumberWithCommas(row.price)"
                    @input="e => onMoneyInput(e, row, 'price')"
                  >
                </td>
                <td>
                  <div class="stock-cell">
                    <strong :class="stockClass(row.stock, row.minStock)">{{ Number(row.stock || 0).toLocaleString('fa-IR') }}</strong>
                    <button type="button" title="افزایش یا کاهش موجودی" @click.stop="openStockMovement(row)">±</button>
                  </div>
                </td>
                <td><input v-model.number="row.minStock" type="number" min="0"></td>
                <td><input v-model.number="row.followupDays" type="number" min="0" placeholder="۰"></td>
                <td>
                  <button
                    class="commission-chip"
                    type="button"
                    title="تنظیم پورسانت پیش‌فرض معرف"
                    @click.stop="openCommissionModal(row, 'item')"
                  >
                    <small>تنظیم پیش‌فرض</small>
                    <strong>{{ commissionLabel(row.defaultCommissionType, row.defaultCommissionValue) }}</strong>
                  </button>
                </td>
                <td><input class="check" type="checkbox" v-model="row.active"></td>
                <td>
                  <button class="booking-table-cell" :class="{ enabled: row.bookingSetting?.booking_enabled }" type="button" title="بازکردن تنظیمات وقت‌دهی" @click.stop="openBookingSettings(row)">
                    <span class="booking-table-icon">{{ row.bookingSetting?.booking_enabled ? '✓' : '◷' }}</span>
                    <span><b>{{ row.bookingSetting?.booking_enabled ? 'فعال' : 'تنظیم نشده' }}</b><small>{{ row.bookingSetting?.booking_enabled ? 'مشاهده تنظیمات' : 'تنظیم وقت‌دهی' }}</small></span>
                  </button>
                </td>
                <td>
                  <button class="row-remove" type="button" @click.stop="removeRow(row, index)">×</button>
                </td>
              </tr>

              <tr v-if="displayedRows.length === 0">
                <td colspan="12" class="empty-cell">
                  {{ inventoryEmptyMessage }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="inventoryView === 'chart'" class="chart-section">
        <div class="panel-head compact chart-head">
          <div class="chart-head-copy">
            <h3>نمای چارت خدمات {{ activeSectionName }}</h3>
            <p>فقط خدمت‌های فعال همین بخش نمایش داده می‌شوند؛ زرد یعنی نزدیک به حداقل و قرمز یعنی موجودی صفر.</p>
          </div>
          <button class="text-btn primary" type="button" @click="inventoryView = 'table'">
            رفتن به جدول
          </button>
          <h3>نمای خدمات بخش انتخاب‌شده</h3>
        </div>
        <div class="chart-box">
          <div v-for="item in chartData" :key="item.localId" class="bar-row">
            <div class="bar-label">{{ item.name || 'بدون نام' }}</div>
            <div class="bar-wrapper">
              <div
                class="bar"
                :class="chartStockClass(item)"
                :style="{ width: getBarWidth(item.chartStock) }"
              >
                {{ item.chartStock }}
              </div>
            </div>
          </div>
          <div v-if="chartData.length === 0" class="empty-state">موجودی فعالی برای نمایش وجود ندارد.</div>
        </div>
      </section>

      <section v-if="inventoryView === 'movements'" class="movement-page">
        <header class="panel-head">
          <div><h3>گردش موجودی {{ movementList.row?.name }}</h3><p>افزایش‌ها و کاهش‌های این آیتم با تاریخ و علت ثبت شده‌اند.</p></div>
          <button class="text-btn ghost" type="button" @click="closeMovementList">بازگشت به خدمات</button>
        </header>
        <div class="movement-filters">
          <label>
            از تاریخ
            <date-picker
              v-model="movementList.dateFrom"
              format="YYYY-MM-DD"
              display-format="jYYYY/jMM/jDD"
              input-class="movement-date-input"
              placeholder="انتخاب تاریخ"
              auto-submit
              color="#2563eb"
              popover-class="movement-date-picker-popover"
              append-to="body"
              @open="raiseMovementDatePicker"
            />
          </label>
          <label>
            تا تاریخ
            <date-picker
              v-model="movementList.dateTo"
              format="YYYY-MM-DD"
              display-format="jYYYY/jMM/jDD"
              input-class="movement-date-input"
              placeholder="انتخاب تاریخ"
              auto-submit
              color="#2563eb"
              popover-class="movement-date-picker-popover"
              append-to="body"
              @open="raiseMovementDatePicker"
            />
          </label>
          <button class="text-btn primary" type="button" @click="loadMovementList">اعمال فیلتر</button>
        </div>
        <div class="movement-summary"><article class="in">افزایش در بازه <strong>+{{ movementIncreaseTotal.toLocaleString('fa-IR') }}</strong></article><article class="out">کاهش در بازه <strong>{{ movementDecreaseTotal.toLocaleString('fa-IR') }}</strong></article><article>موجودی فعلی <strong>{{ Number(movementList.row?.stock || 0).toLocaleString('fa-IR') }}</strong></article></div>
        <div class="movement-table-wrap"><table class="movement-table"><thead><tr><th>تاریخ</th><th>نوع</th><th>تعداد</th><th>علت / توضیحات</th></tr></thead><tbody><tr v-for="item in movementList.items" :key="item.id" :class="Number(item.quantity) < 0 ? 'out' : 'in'"><td>{{ formatMovementDate(item.occurred_at) }}</td><td>{{ Number(item.quantity) < 0 ? 'کاهش موجودی' : 'افزایش موجودی' }}</td><td><b>{{ Number(item.quantity) > 0 ? '+' : '' }}{{ Number(item.quantity).toLocaleString('fa-IR') }}</b></td><td>{{ item.description || '-' }}</td></tr><tr v-if="!movementList.loading && !movementList.items.length"><td colspan="4" class="empty-cell">گردشی در این بازه ثبت نشده است.</td></tr></tbody></table><p v-if="movementList.loading" class="movement-loading">در حال دریافت گردش‌ها...</p></div>
      </section>

      <ServiceTagsManager v-if="inventoryView === 'service-tags'" @back="closeServiceTagsPage" @saved="syncServiceTagOptions" />
    </main>

    <div v-if="showDefaultCommissionModal" class="modal-backdrop" @click.self="closeDefaultCommissionModal">
      <div class="commission-modal" role="dialog" aria-modal="true">
        <div class="modal-head">
          <div>
            <h3>ثبت پورسانت</h3>
            <p>{{ commissionScopeSubtitle }}</p>
          </div>
          <button class="modal-close" type="button" @click="closeDefaultCommissionModal">×</button>
        </div>

        <div class="modal-grid">
          <label>
            نوع واریز پاداش
            <select v-model="defaultCommissionDraft.type">
              <option value="percent">درصدی</option>
              <option value="fixed">مبلغ ثابت</option>
            </select>
          </label>

          <label>
            مقدار
            <input v-model.number="defaultCommissionDraft.value" type="number" min="0" placeholder="مثلا 10">
          </label>
        </div>

        <div class="modal-preview">
          <span>{{ commissionScopeRows.length }} آیتم شامل می‌شود</span>
          <strong>{{ commissionLabel(defaultCommissionDraft.type, defaultCommissionDraft.value) }}</strong>
        </div>

        <section v-if="selectedRow && bulkCommission.target === 'item'" class="person-commission-box">
          <div class="person-commission-head">
            <strong>پورسانت اختصاصی شخص</strong>
            <span>{{ selectedRow.name || 'آیتم انتخاب‌شده' }}</span>
          </div>
          <div class="commission-form">
            <select v-model="commissionPersonKey">
              <option value="">انتخاب پزشک، پرسنل یا کاربر</option>
              <optgroup label="پزشکان">
                <option v-for="doctor in doctors" :key="`doctor-${doctor.id}`" :value="`doctor:${doctor.id}`">{{ doctor.name }}</option>
              </optgroup>
              <optgroup label="پرسنل">
                <option v-for="person in staff" :key="`staff-${person.id}`" :value="`staff:${person.id}`">{{ person.name }}</option>
              </optgroup>
              <optgroup label="کاربران سیستم">
                <option v-for="user in users" :key="`user-${user.id}`" :value="`user:${user.id}`">{{ user.name }}</option>
              </optgroup>
            </select>
            <select v-model="commissionDraft.type">
              <option value="percent">درصد</option>
              <option value="fixed">مبلغ</option>
            </select>
            <input v-model.number="commissionDraft.value" type="number" min="0" placeholder="مقدار">
            <button class="text-btn primary" type="button" @click="addCommission">ثبت شخصی</button>
          </div>
          <div class="commission-list compact">
            <div v-for="(commission, index) in selectedRow.commissions" :key="index" class="commission-item">
              <div><strong>{{ recipientTypeLabel(commission.recipient_type) }}</strong><span>{{ commission.recipient_name }}</span></div>
              <b>{{ commissionLabel(commission.commission_type, commission.commission_value) }}</b>
              <button type="button" @click="removeCommission(index)">×</button>
            </div>
            <div v-if="!selectedRow.commissions.length" class="empty-state">پورسانت اختصاصی ثبت نشده است.</div>
          </div>
        </section>

        <div class="modal-actions">
          <button class="text-btn ghost" type="button" @click="closeDefaultCommissionModal">انصراف</button>
          <button class="text-btn primary" type="button" :disabled="!commissionScopeRows.length" @click="saveDefaultCommissionModal">{{ bulkCommission.target === 'group' ? 'ثبت پورسانت کلی گروه' : 'ثبت پورسانت' }}</button>
        </div>
      </div>
    </div>

    <div v-if="stockMovement.open" class="modal-backdrop" @click.self="closeStockMovement">
      <section class="commission-modal stock-movement-modal" role="dialog" aria-modal="true">
        <div class="modal-head"><div><h3>گردش موجودی</h3><p>{{ stockMovement.row?.name }}</p></div><button class="modal-close" type="button" @click="closeStockMovement">×</button></div>
        <div class="stock-current">موجودی فعلی: <strong>{{ Number(stockMovement.row?.stock || 0).toLocaleString('fa-IR') }}</strong></div>
        <div class="stock-direction"><button type="button" :class="{ active: stockMovement.direction === 'increase' }" @click="stockMovement.direction = 'increase'">افزایش موجودی</button><button type="button" :class="{ active: stockMovement.direction === 'decrease' }" @click="stockMovement.direction = 'decrease'">کاهش موجودی</button></div>
        <div class="modal-grid"><label>تعداد<input v-model.number="stockMovement.quantity" type="number" inputmode="numeric" min="1" step="1"></label><label>توضیحات<textarea v-model.trim="stockMovement.description" placeholder="مثلاً خرید جدید یا اصلاح شمارش"></textarea></label></div>
        <div class="movement-history"><div class="movement-history-head"><strong>۴ گردش آخر</strong><button type="button" @click="openMovementList(stockMovement.row)">لیست کامل</button></div><p v-if="stockMovement.loading">در حال دریافت...</p><p v-else-if="!stockMovement.history.length">هنوز گردشی ثبت نشده است.</p><div v-for="movement in stockMovement.history.slice(0, 4)" :key="movement.id" :class="['movement-row', Number(movement.quantity) < 0 ? 'out' : 'in']"><b>{{ Number(movement.quantity) > 0 ? '+' : '' }}{{ Number(movement.quantity).toLocaleString('fa-IR') }}</b><span>{{ movement.description }}</span><time>{{ formatMovementDate(movement.occurred_at) }}</time></div></div>
        <div class="modal-actions"><button class="text-btn ghost" type="button" @click="closeStockMovement">انصراف</button><button class="text-btn primary" :disabled="stockMovement.saving || !stockMovement.quantity" type="button" @click="saveStockMovement">{{ stockMovement.saving ? 'در حال ثبت...' : 'ثبت گردش' }}</button></div>
      </section>
    </div>

    <div v-if="defaultAddonsModal.open" class="modal-backdrop" @click.self="closeDefaultAddonsModal">
      <section class="commission-modal inventory-addons-modal" role="dialog" aria-modal="true" aria-label="انتخاب جانبی‌ها">
        <div class="modal-head"><div><h3>جانبی‌های پیش‌فرض</h3><p>{{ defaultAddonsModal.row?.name || 'کالا / خدمت' }}</p></div><button class="modal-close" type="button" @click="closeDefaultAddonsModal">×</button></div>
        <select v-if="defaultAddonsModal.globalMode" v-model="defaultAddonsModal.parentLocalId" class="inventory-addons-parent" @change="changeDefaultAddonsParent">
          <option v-for="item in rows.filter(item => item.name)" :key="item.localId" :value="item.localId">{{ item.name }}</option>
        </select>
        <input v-model.trim="defaultAddonsModal.query" class="inventory-addons-search" type="search" placeholder="جست‌وجوی خدمت در خدمات">
        <p class="inventory-addons-help">با انتخاب این کالا/خدمت در وقت‌دهی، موارد انتخاب‌شده به‌صورت پیش‌فرض افزوده می‌شوند.</p>
        <div class="inventory-addons-list">
          <label v-for="item in filteredDefaultAddonOptions" :key="item.id" class="inventory-addon-option">
            <input type="checkbox" :checked="isDefaultAddonSelected(item)" @change="toggleDefaultAddon(item)">
            <span><strong>{{ item.name || 'کالای بدون نام' }}</strong><small>قیمت: {{ formatNumberWithCommas(item.amount) }} تومان · موجودی: {{ Number(item.stock || 0).toLocaleString('fa-IR') }}</small></span>
          </label>
          <p v-if="!filteredDefaultAddonOptions.length" class="inventory-addons-empty">کالای فعال دیگری برای انتخاب پیدا نشد.</p>
        </div>
        <div class="modal-actions"><button class="text-btn ghost" type="button" @click="closeDefaultAddonsModal">انصراف</button><button class="text-btn primary" type="button" @click="saveDefaultAddonsModal">ثبت جانبی‌ها</button></div>
      </section>
    </div>

    <div v-if="addonManager.open" class="modal-backdrop" @click.self="closeAddonManager">
      <section class="commission-modal addon-manager-modal" role="dialog" aria-modal="true"><div class="modal-head"><div><h3>مدیریت جانبی‌ها</h3><p>برای هر جانبی قیمت، هزینه مواد و موجودی مستقل ثبت کنید.</p></div><button class="modal-close" type="button" @click="closeAddonManager">×</button></div><div class="addon-manager-table"><div class="addon-manager-row addon-manager-head"><span>نام جانبی</span><span>قیمت کالا</span><span>هزینه مواد</span><span>موجودی</span><span>حداقل</span><span>فعال</span><span></span></div><div v-for="(item,index) in addonManager.items" :key="item._key" class="addon-manager-row"><input v-model.trim="item.name" placeholder="مثلاً ژل بی‌حسی"><input v-model.number="item.amount" type="number" min="0"><input v-model.number="item.price" type="number" min="0"><input v-model.number="item.stock" type="number" min="0"><input v-model.number="item.min_stock" type="number" min="0"><label class="addon-active"><input v-model="item.active" type="checkbox"><span>فعال</span></label><button class="addon-delete" type="button" title="حذف" @click="addonManager.items.splice(index,1)">×</button></div><p v-if="!addonManager.items.length" class="inventory-addons-empty">هنوز جانبی تعریف نشده است.</p></div><button class="text-btn ghost addon-add-btn" type="button" @click="addAddonDefinition">+ افزودن جانبی</button><div class="modal-actions"><button class="text-btn ghost" type="button" @click="closeAddonManager">انصراف</button><button class="text-btn primary" type="button" @click="saveAddonManager">ذخیره جانبی‌ها</button></div></section>
    </div>

    <div v-if="bookingModal.open" class="modal-backdrop" @click.self="closeBookingSettings">
      <section class="commission-modal booking-settings-modal booking-wizard" role="dialog" aria-modal="true">
        <div class="booking-hero"><div class="booking-hero-icon">◷</div><div><h3>تنظیمات وقت‌دهی خدمت</h3><p><b>خدمت:</b> {{ bookingModal.row?.name || 'بدون نام' }} <span class="booking-status-pill" :class="{on: bookingModal.settings.booking_enabled}">{{ bookingModal.settings.booking_enabled ? 'فعال' : 'غیرفعال' }}</span></p></div><button v-if="bookingModal.settings.booking_enabled" class="booking-disable-btn" type="button" @click="confirmDisableBooking">غیرفعال‌کردن</button><button class="modal-close" type="button" @click="closeBookingSettings">×</button></div>
        <p v-if="bookingModal.loading" class="inventory-addons-empty">در حال دریافت تنظیمات...</p>
        <template v-else-if="!bookingModal.settings.booking_enabled">
          <div class="booking-off-state"><div class="booking-off-art">⏱</div><h4>وقت‌دهی این خدمت هنوز فعال نیست</h4><p>با فعال‌کردن وقت‌دهی، می‌توانید پزشک‌ها، روزهای حضور، ساعت کاری و قوانین رزرو را تنظیم کنید.</p><button class="booking-primary-btn" type="button" @click="bookingModal.settings.booking_enabled = true; bookingModal.step = 1">فعال‌کردن وقت‌دهی</button></div>
          <div class="modal-actions"><button class="text-btn ghost" type="button" @click="closeBookingSettings">بستن</button><button class="text-btn primary" type="button" @click="saveBookingSettings">ذخیره وضعیت</button></div>
        </template>
        <template v-else>
          <div class="booking-steps"><button v-for="item in [{id:1,title:'تنظیمات پایه',icon:'⚙'},{id:2,title:'منابع وقت‌دهی',icon:'♙'},{id:3,title:'روز و ساعت حضور',icon:'▦'},{id:4,title:'رزرو آنلاین',icon:'◎'}]" :key="item.id" type="button" :class="{active: bookingModal.step === item.id, done: bookingModal.step > item.id}" @click="bookingModal.step = item.id"><span>{{ bookingModal.step > item.id ? '✓' : item.icon }}</span><small>{{ item.title }}</small></button></div>
          <div v-if="bookingModal.step === 1" class="booking-pane"><div class="booking-pane-title"><div><h4>قوانین اصلی خدمت</h4><p>مشخص کنید این خدمت چگونه در تقویم مدیریت شود.</p></div><span class="booking-number">۱</span></div><div class="booking-card-grid"><label>فاصله بین نوبت‌ها (دقیقه)<input v-model.number="bookingModal.settings.slot_interval_minutes" type="number" min="5" placeholder="از تنظیمات کلینیک"></label><label>انتخاب پزشک یا اپراتور<select v-model="bookingModal.settings.assignment_mode"><option value="auto">انتخاب خودکار توسط سیستم</option><option value="manual">انتخاب دستی توسط منشی</option></select></label><label class="booking-toggle"><input v-model="bookingModal.settings.use_default_schedule" type="checkbox"><span><b>برنامه پیش‌فرض کلینیک</b><small>اگر برنامه اختصاصی نداشته باشد</small></span></label><label class="booking-toggle"><input v-model="bookingModal.settings.conflict_check_enabled" type="checkbox"><span><b>جلوگیری از تداخل</b><small>نوبت هم‌زمان ثبت نشود</small></span></label></div><div class="booking-info-box">هر خدمتی که اینجا تنظیم کنید، در تقویم نوبت‌دهی با همین قوانین نمایش داده می‌شود.</div></div>
          <div v-if="bookingModal.step === 2" class="booking-pane"><div class="booking-pane-title"><div><h4>پزشکان و اپراتورها</h4><p>یک خدمت می‌تواند هم‌زمان به چند پزشک و اپراتور متصل باشد.</p></div><span class="booking-number">۲</span></div><div class="booking-resource-cards"><article v-for="(resource,index) in bookingModal.resources" :key="resource._key || index" class="booking-resource-card"><div class="resource-card-top"><span class="resource-avatar">{{ resource.role === 'doctor' ? 'پ' : 'ا' }}</span><select v-model="resource.role"><option value="doctor">پزشک</option><option value="operator">اپراتور</option></select><button type="button" class="resource-remove" @click="bookingModal.resources.splice(index,1)">حذف</button></div><select v-if="resource.role === 'doctor'" v-model="resource.doctor_id" @change="applyDoctorSchedule(resource)"><option :value="null">انتخاب پزشک</option><option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option></select><select v-else v-model="resource.staff_id"><option :value="null">انتخاب اپراتور</option><option v-for="person in staff" :key="person.id" :value="person.id">{{ person.name }}</option></select><label class="resource-active"><input v-model="resource.active" type="checkbox"> برای رزرو فعال باشد</label></article><button class="add-resource-card" type="button" @click="addBookingResource">+ افزودن پزشک یا اپراتور</button></div></div>
          <div v-if="bookingModal.step === 3" class="booking-pane"><div class="booking-pane-title"><div><h4>روزها و ساعت حضور</h4><p>ساعت‌ها از منابع و روزهای حضور پزشک نمایش داده می‌شود.</p></div><span class="booking-number">۳</span></div><div v-for="(resource,index) in bookingModal.resources" :key="resource._key || index" class="schedule-resource"><div class="schedule-resource-head"><strong>{{ resource.name || (resource.role === 'doctor' ? 'پزشک انتخاب‌شده' : 'اپراتور انتخاب‌شده') }}</strong><button class="text-btn ghost" type="button" @click="addBookingAvailability(resource)">+ افزودن ساعت‌های پیش‌فرض</button></div><div v-for="(availability,aIndex) in (resource.availabilities || [])" :key="aIndex" class="availability-card"><select v-model.number="availability.weekday"><option v-for="day in [{v:0,t:'شنبه'},{v:1,t:'یکشنبه'},{v:2,t:'دوشنبه'},{v:3,t:'سه‌شنبه'},{v:4,t:'چهارشنبه'},{v:5,t:'پنجشنبه'},{v:6,t:'جمعه'}]" :key="day.v" :value="day.v">{{ day.t }}</option></select><input v-model="availability.start_time" type="time"><span>تا</span><input v-model="availability.end_time" type="time"><button type="button" class="resource-remove" @click="resource.availabilities.splice(aIndex,1)">حذف</button><div class="break-list"><div v-for="(breakItem,bIndex) in (availability.breaks || [])" :key="bIndex" class="break-chip">استراحت <input v-model="breakItem.start_time" type="time"><span>تا</span><input v-model="breakItem.end_time" type="time"><button type="button" @click="availability.breaks.splice(bIndex,1)">×</button></div><button class="break-add" type="button" @click="addBookingBreak(availability)">+ افزودن زمان استراحت</button></div></div><p v-if="!resource.availabilities?.length" class="empty-schedule">برای این منبع هنوز برنامه‌ای تعریف نشده است.</p></div><div v-if="!bookingModal.resources.length" class="booking-info-box">ابتدا در مرحله قبل حداقل یک پزشک یا اپراتور اضافه کنید.</div></div>
          <div v-if="bookingModal.step === 4" class="booking-pane booking-online-pane"><div class="booking-pane-title"><div><h4>تنظیمات رزرو آنلاین</h4><p>مشخص کنید کاربران سایت چه زمانی و با چه شرایطی بتوانند رزرو کنند.</p></div><span class="booking-number">۴</span></div><div class="booking-card-grid"><label class="booking-toggle wide"><input v-model="bookingModal.settings.online_enabled" type="checkbox"><span><b>نمایش این خدمت در سایت</b><small>کاربران بتوانند این خدمت را آنلاین ببینند</small></span></label><label class="booking-toggle"><input v-model="bookingModal.settings.online_payment_enabled" type="checkbox"><span><b>پرداخت آنلاین</b><small>پرداخت هنگام رزرو</small></span></label><label class="booking-toggle"><input v-model="bookingModal.settings.online_cancellation_enabled" type="checkbox"><span><b>لغو توسط کاربر</b><small>اجازه لغو رزرو</small></span></label><label class="booking-field-card"><span>رزرو از چند روز بعد</span><small>کاربر از چند روز بعد بتواند رزرو کند</small><input v-model.number="bookingModal.settings.booking_start_after_days" type="number" min="0"></label><label class="booking-field-card"><span>رزرو تا چند روز آینده</span><small>بازه قابل مشاهده در سایت</small><input v-model.number="bookingModal.settings.booking_available_days" type="number" min="1"></label><label v-if="bookingModal.settings.online_enabled" class="booking-field-card"><span>ساعت شروع سایت</span><small>شروع پذیرش رزرو آنلاین</small><input v-model="bookingModal.settings.online_start_time" type="time"></label><label v-if="bookingModal.settings.online_enabled" class="booking-field-card"><span>ساعت پایان سایت</span><small>پایان پذیرش رزرو آنلاین</small><input v-model="bookingModal.settings.online_end_time" type="time"></label></div></div>
          <div class="booking-wizard-footer"><button class="text-btn ghost" type="button" @click="closeBookingSettings">انصراف</button><div><button v-if="bookingModal.step > 1" class="text-btn ghost" type="button" @click="bookingModal.step--">مرحله قبل</button><button v-if="bookingModal.step < 4" class="booking-primary-btn" type="button" @click="bookingModal.step++">مرحله بعد</button><button v-else class="booking-primary-btn" type="button" :disabled="bookingModal.saving" @click="saveBookingSettings">{{ bookingModal.saving ? 'در حال ذخیره...' : 'ذخیره تنظیمات' }}</button></div></div>
        </template>
      </section>
    </div>

    <div v-if="tagPicker.row" class="service-tag-popover-backdrop" @mousedown="closeServiceTagPicker">
      <div
        class="service-tag-popover"
        :style="{ top: `${tagPicker.top}px`, left: `${tagPicker.left}px` }"
        @mousedown.stop
      >
        <button
          v-for="tag in filteredServiceTagOptions(tagPicker.row)"
          :key="`popover-option-${tag}`"
          type="button"
          @click="selectServiceTag(tagPicker.row, tag)"
        >
          {{ tag }}
        </button>
        <small v-if="!filteredServiceTagOptions(tagPicker.row).length">
          تگی پیدا نشد. تگ‌ها را از صفحه «تگ‌های خدمات» ثبت کنید.
        </small>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios"
import DatePicker from "vue3-persian-datetime-picker"
import Swal from "sweetalert2"
import ServiceTagsManager from "./ServiceTagsManager.vue"

const API = "/api"
const INVENTORY_ZERO_NOTIFICATIONS_KEY = "inventory_zero_stock_notifs_v1"
const SERVICE_SECTION_COLORS_KEY = "service_section_colors_v1"

export default {
  name: "InventoryTable",

  components: {
    DatePicker,
    ServiceTagsManager,
  },

  data() {
    return {
      sections: [],
      serviceTagOptions: [],
      rows: [],
      doctors: [],
      staff: [],
      users: [],
      inventoryView: "table",
      activeRootKey: "",
      activeSubKey: "",
      activeSectionKey: "",
      activeTreeKey: "",
      expandedSectionKeys: [],
      sectionColors: {},
      colorPickerSectionKey: "",
      customSectionColor: "#2563eb",
      sectionIdRedirects: {},
      selectedRow: null,
      tagPicker: {
        row: null,
        top: 0,
        left: 0,
      },
      defaultAddonsModal: { open: false, row: null, draftIds: [], query: '', globalMode: false, parentLocalId: '' },
      addonDefinitions: [],
      addonManager: { open: false, items: [] },
      showDefaultCommissionModal: false,
      defaultCommissionRow: null,
      defaultCommissionDraft: {
        type: "percent",
        value: 0
      },
      commissionPersonKey: "",
      commissionDraft: {
        type: "percent",
        value: 0
      },
      bulkCommission: {
        target: "section",
        sectionKey: "",
        tag: "",
        type: "percent",
        value: 0
      },
      stockMovement: {
        open: false,
        row: null,
        direction: "increase",
        quantity: null,
        description: "",
        history: [],
        loading: false,
        saving: false,
      },
      movementList: { row: null, items: [], dateFrom: "", dateTo: "", loading: false },
      clinicSchedule: { active_days: ["saturday", "monday", "wednesday"], interval_minutes: 15, day_times: {} },
      bookingModal: { open: false, row: null, step: 1, loading: false, saving: false, settings: {}, resources: [], exceptions: [], rules: [], scheduleJson: "[]" },
      searchQuery: "",
      isFetching: true,
      isSaving: false,
      hasUnsavedChanges: false,
      saveState: "idle",
      saveTimer: null,
      originalStockByKey: {}
    }
  },

  computed: {
    sectionColorsList() {
      return ["#1e3a8a", "#2563eb", "#3b82f6", "#60a5fa", "#93c5fd", "#0e7490", "#0891b2", "#06b6d4", "#22d3ee", "#99f6e4", "#047857", "#059669", "#10b981", "#34d399", "#65a30d", "#84cc16", "#a3e635", "#ca8a04", "#d97706", "#f59e0b", "#ea580c", "#f97316", "#be123c", "#dc2626", "#ef4444", "#fb7185", "#db2777", "#ec4899", "#7c3aed", "#8b5cf6", "#a78bfa", "#4338ca", "#475569", "#64748b", "#0f172a"]
    },
    movementIncreaseTotal() {
      return this.movementList.items.reduce((sum, item) => sum + Math.max(0, Number(item.quantity || 0)), 0)
    },
    movementDecreaseTotal() {
      return this.movementList.items.reduce((sum, item) => sum + Math.min(0, Number(item.quantity || 0)), 0)
    },
    activeSectionName() {
      return this.activeSection?.name || this.activeRootSection?.name || "بخش انتخاب‌شده"
    },

    activeRootSection() {
      return this.sections.find(section => this.sectionKey(section) === this.activeRootKey)
    },

    isRootSelection() {
      return Boolean(this.activeRootKey && !this.activeSectionKey && this.activeTreeKey === this.activeRootKey)
    },

    needsCompletedHierarchy() {
      return !this.searchQuery && !this.activeSectionKey
    },

    inventoryTableTitle() {
      return this.needsCompletedHierarchy ? "شاخه‌بندی کامل نشده" : `آیتم‌های ${this.activeSectionName}`
    },

    inventoryTableSubtitle() {
      return this.needsCompletedHierarchy
        ? "برای نمایش و ثبت خدمت، یک زیرشاخه از خدمات را انتخاب کنید."
        : "خدمات این بخش را همراه موجودی و پورسانت معرف مدیریت کنید."
    },

    inventoryEmptyMessage() {
      if (this.searchQuery) return "نتیجه‌ای برای این جست‌وجو در خدمات پیدا نشد."
      if (this.needsCompletedHierarchy) return "لطفا شاخه‌بندی را کامل کنید."
      return "برای این بخش هنوز آیتمی ثبت نشده است."
    },

    rootSections() {
      return this.sections.filter(section => !String(section.parent_id || section.parentId || '').trim())
    },

    activeSubSections() {
      return this.childSections(this.activeRootKey)
    },

    inventoryTreeNodes() {
      const nodes = []
      const walk = (parentKey, level) => {
        this.childSections(parentKey).forEach(section => {
          const children = this.childSections(this.sectionKey(section))
          nodes.push({ section, level, hasChildren: children.length > 0 })
          if (children.length && this.isTreeExpanded(section)) {
            walk(this.sectionKey(section), level + 1)
          }
        })
      }
      walk("", 1)
      return nodes
    },

    activeSection() {
      return this.sections.find(section => this.sectionKey(section) === this.activeSectionKey)
    },

    activeSectionRows() {
      if (!this.activeSectionKey) return []
      return this.rows.filter(row => this.rowSectionKey(row) === this.activeSectionKey)
    },

    displayedRows() {
      const query = this.normalizeSearchText(this.searchQuery)
      if (!query) return this.activeSectionRows

      return this.rows.filter(row => {
        const commissionRecipients = (row.commissions || [])
          .map(commission => commission.recipient_name)
          .join(" ")
        const searchableText = [
          row.name,
          (row.serviceTags || []).join(" "),
          this.sectionNameForRow(row),
          row.amount,
          this.formatNumberWithCommas(row.amount),
          row.price,
          this.formatNumberWithCommas(row.price),
          row.count,
          row.stock,
          row.minStock,
          commissionRecipients
        ].join(" ")

        const normalizedText = this.normalizeSearchText(searchableText)
        return query.split(" ").every(term => normalizedText.includes(term))
      })
    },

    chartData() {
      return this.displayedRows
        .filter(row => row.name !== "" && row.active)
        .map(row => ({
          ...row,
          chartStock: this.inventoryStockValue(row),
          chartMinStock: this.inventoryMinStockValue(row)
        }))
    },

    activeSectionServiceTags() {
      return Array.from(new Set(this.activeSectionRows
        .flatMap(row => row.serviceTags || [])
        .map(tag => String(tag || '').trim())
        .filter(Boolean)))
        .sort((a, b) => a.localeCompare(b, "fa"))
    },

    allServiceTags() {
      return Array.from(new Set(this.rows
        .flatMap(row => row.serviceTags || [])
        .map(tag => String(tag || '').trim())
        .filter(Boolean)))
        .sort((a, b) => a.localeCompare(b, "fa"))
    },

    bulkCommissionRows() {
      return this.commissionScopeRows
    },

    commissionScopeRows() {
      if (this.bulkCommission.target === "all") return this.rows

      if (this.bulkCommission.target === "group") {
        const groupKey = String(this.bulkCommission.sectionKey || this.activeRootKey || "")
        const descendantSectionKeys = this.descendantSectionKeys(groupKey)
        return this.rows.filter(row => descendantSectionKeys.includes(this.rowSectionKey(row)))
      }

      if (this.bulkCommission.target === "item") {
        return this.selectedRow ? [this.selectedRow] : []
      }

      if (this.bulkCommission.target === "tag") {
        if (!this.bulkCommission.tag) return []
        return this.rows.filter(row => (row.serviceTags || []).includes(this.bulkCommission.tag))
      }

      const sectionKey = this.bulkCommission.sectionKey || this.activeSectionKey
      return this.rows.filter(row => this.rowSectionKey(row) === String(sectionKey))
    },

    bulkCommissionTargetLabel() {
      const count = this.bulkCommissionRows.length
      if (this.bulkCommission.target === "tag") {
        return this.bulkCommission.tag ? `${count} آیتم در ${this.bulkCommission.tag}` : "یک تگ را انتخاب کنید"
      }
      return `${count} آیتم در ${this.activeSectionName}`
    },

    commissionScopeSubtitle() {
      if (this.bulkCommission.target === "all") return "پورسانت کلی برای همه خدمات"
      if (this.bulkCommission.target === "group") {
        const group = this.sections.find(item => this.sectionKey(item) === String(this.bulkCommission.sectionKey || this.activeRootKey))
        return `پورسانت کلی همه آیتم‌های گروه ${group?.name || 'انتخاب‌شده'}`
      }
      if (this.bulkCommission.target === "tag") return this.bulkCommission.tag ? `زیر‌بخش ${this.bulkCommission.tag}` : "یک زیر‌بخش را انتخاب کنید"
      if (this.bulkCommission.target === "item") return this.selectedRow ? this.selectedRow.name || "آیتم بدون نام" : "یک آیتم را انتخاب کنید"
      const section = this.sections.find(item => this.sectionKey(item) === String(this.bulkCommission.sectionKey || this.activeSectionKey))
      return `بخش ${section?.name || this.activeSectionName}`
    },

    selectedRowLocalId: {
      get() {
        return this.selectedRow?.localId || ""
      },
      set(localId) {
        const row = this.rows.find(item => item.localId === localId)
        if (row) this.selectRow(row)
      }
    },

    saveStatusText() {
      return {
        idle: this.hasUnsavedChanges ? "تغییرات ذخیره نشده" : "بدون تغییر",
        dirty: "تغییرات ذخیره نشده",
        saving: "در حال ذخیره...",
        saved: "ذخیره شد",
        error: "خطا در ذخیره"
      }[this.saveState] || ""
    }
    ,filteredDefaultAddonOptions() {
      const query = this.normalizeSearchText(this.defaultAddonsModal.query)
      return this.addonDefinitions.filter(item => {
        if (item.active === false) return false
        const text = this.normalizeSearchText(`${item.name} ${item.amount} ${item.stock}`)
        return !query || text.includes(query)
      })
    }
  },

  watch: {
    sections: {
      handler() {
        this.queueSave()
      },
      deep: true
    },

    rows: {
      handler() {
        this.rows.forEach(row => {
          const stock = Number(row.stock)
          const normalizedStock = Number.isFinite(stock) ? Math.trunc(stock) : 0
          if (row.stock !== normalizedStock) row.stock = normalizedStock
        })
        this.queueSave()
      },
      deep: true
    }
  },

  mounted() {
    try { this.sectionColors = JSON.parse(localStorage.getItem(SERVICE_SECTION_COLORS_KEY) || "{}") || {} } catch { this.sectionColors = {} }
    if (localStorage.getItem('inventory-open-service-tags') === '1') {
      localStorage.removeItem('inventory-open-service-tags')
      this.inventoryView = 'service-tags'
    }
    this.fetchData()
  },

  methods: {
    sectionColor(section) {
      let current = section
      const visited = new Set()
      while (current && !visited.has(String(this.sectionKey(current)))) {
        const key = String(this.sectionKey(current))
        visited.add(key)
        if (this.sectionColors[key]) return this.sectionColors[key]
        const parentKey = String(current.parent_id || current.parentId || "")
        current = parentKey ? this.sections.find(item => String(this.sectionKey(item)) === parentKey) : null
      }
      return "#cbd5e1"
    },
    toggleSectionColorMenu(key) {
      this.colorPickerSectionKey = this.colorPickerSectionKey === String(key) ? "" : String(key)
    },
    setSectionColorFor(key, color) {
      if (!key) return
      const section = this.sections.find(item => String(this.sectionKey(item)) === String(key))
      if (section) section.color = color || null
      if (color) this.sectionColors = { ...this.sectionColors, [String(key)]: color }
      else { const next = { ...this.sectionColors }; delete next[String(key)]; this.sectionColors = next }
      localStorage.setItem(SERVICE_SECTION_COLORS_KEY, JSON.stringify(this.sectionColors))
      this.colorPickerSectionKey = ""
    },
    async openBookingSettings(row) {
      const rowName = row?.name
      if (!row?.id) {
        await this.saveData(false)
        await this.fetchData({ keepState: true })
        row = this.rows.find(item => item.name === rowName)
      }
      if (!row?.id) return
      this.bookingModal = { open: true, row, step: 1, loading: true, saving: false, settings: {}, resources: [], exceptions: [], rules: [], scheduleJson: "[]" }
      try {
        const { data } = await axios.get(`${API}/inventory/${row.id}/booking-settings`)
        const payload = data.data || data
        this.bookingModal.settings = { ...(payload.settings || {}) }
        this.bookingModal.resources = (payload.resources || []).map((item, index) => ({ ...item, _key: `booking-resource-${item.id || index}`, availabilities: item.availabilities || [] }))
        this.bookingModal.exceptions = payload.exceptions || []
        this.bookingModal.rules = payload.rules || []
        this.bookingModal.scheduleJson = JSON.stringify(this.bookingModal.resources.flatMap((resource, resourceIndex) => (resource.availabilities || []).map(availability => ({ resource_index: resourceIndex, weekday: availability.weekday, start_time: availability.start_time, end_time: availability.end_time, slot_interval_minutes: availability.slot_interval_minutes, breaks: availability.breaks || [] }))), null, 2)
      } catch (error) {
        Swal.fire({ icon: "error", title: "خطا", text: error?.response?.data?.message || "تنظیمات وقت‌دهی دریافت نشد." })
        this.bookingModal.open = false
      } finally {
        this.bookingModal.loading = false
      }
    },
    closeBookingSettings() {
      if (!this.bookingModal.saving) this.bookingModal.open = false
    },
    async confirmDisableBooking() {
      const result = await Swal.fire({
        icon: "warning",
        title: "وقت‌دهی غیرفعال شود؟",
        text: "اطلاعات پزشکان، اپراتورها و ساعت‌های حضور پاک نمی‌شود و فقط امکان وقت‌دهی این خدمت غیرفعال خواهد شد.",
        showCancelButton: true,
        confirmButtonText: "بله، غیرفعال شود",
        cancelButtonText: "انصراف",
        confirmButtonColor: "#dc2626",
        reverseButtons: true,
      })
      if (!result.isConfirmed) return
      this.bookingModal.settings.booking_enabled = false
      this.bookingModal.step = 1
      await this.saveBookingSettings()
    },
    defaultBookingAvailabilities(resource = null) {
      const schedule = this.clinicSchedule || {}
      const dayMap = { saturday: 0, sunday: 1, monday: 2, tuesday: 3, wednesday: 4, thursday: 5, friday: 6 }
      const doctor = resource?.role === "doctor" ? this.doctors.find(item => Number(item.id) === Number(resource.doctor_id)) : null
      const doctorDays = Array.isArray(doctor?.available_days) ? doctor.available_days : []
      const normalizedDoctorDays = doctorDays.map(day => String(day).trim().toLowerCase()).map(day => ({ "شنبه": "saturday", "یکشنبه": "sunday", "دوشنبه": "monday", "سه شنبه": "tuesday", "سه‌شنبه": "tuesday", "چهارشنبه": "wednesday", "پنجشنبه": "thursday", "جمعه": "friday" }[day] || day).replace(" ", ""))
      const activeDays = normalizedDoctorDays.length ? normalizedDoctorDays.filter(day => dayMap[day] !== undefined) : (Array.isArray(schedule.active_days) && schedule.active_days.length ? schedule.active_days : Object.keys(dayMap))
      return activeDays.map(day => {
        const times = schedule.day_times?.[day] || { start: "09:00", end: "17:00" }
        return { weekday: dayMap[day] ?? 0, start_time: String(times.start || "09:00").slice(0, 5), end_time: String(times.end || "17:00").slice(0, 5), slot_interval_minutes: schedule.interval_minutes || null, active: true, breaks: [] }
      })
    },
    addBookingResource() {
      const resource = { role: "doctor", doctor_id: null, staff_id: null, active: true, sort_order: this.bookingModal.resources.length, availabilities: [], _key: `new-${Date.now()}` }
      this.bookingModal.resources.push(resource)
    },
    applyDoctorSchedule(resource) {
      if (resource?.role !== "doctor" || !resource.doctor_id) return
      resource.availabilities = this.defaultBookingAvailabilities(resource)
    },
    addBookingAvailability(resource) {
      resource.availabilities ||= []
      if (!resource.availabilities.length) {
        resource.availabilities.push(...this.defaultBookingAvailabilities(resource))
        return
      }
      resource.availabilities.push({ weekday: 0, start_time: "09:00", end_time: "17:00", slot_interval_minutes: this.clinicSchedule.interval_minutes || null, active: true, breaks: [] })
    },
    addBookingBreak(availability) {
      availability.breaks ||= []
      availability.breaks.push({ title: "استراحت", start_time: "14:00", end_time: "15:00", active: true })
    },
    async saveBookingSettings() {
      const modal = this.bookingModal
      if (!modal.row?.id) return
      let schedule = []
      try {
        schedule = modal.scheduleJson ? JSON.parse(modal.scheduleJson) : []
        if (!Array.isArray(schedule)) throw new Error("array")
      } catch (error) {
        Swal.fire({ icon: "warning", title: "برنامه نامعتبر است", text: "JSON برنامه حضور و استراحت را اصلاح کنید." })
        return
      }
      const resources = modal.resources.map(resource => ({ ...resource, availabilities: resource.availabilities || [] }))
      // برنامه دیداری مرحله سوم منبع اصلی است؛ JSON فقط برای داده‌های قدیمی/سازگاری نگه داشته شده است.
      if (schedule.length && resources.length && resources.every(resource => !(resource.availabilities || []).length)) {
        schedule.forEach(item => {
          const target = resources[Number(item.resource_index || 0)]
          if (target) (target.availabilities ||= []).push({ weekday: item.weekday, start_time: item.start_time, end_time: item.end_time, slot_interval_minutes: item.slot_interval_minutes || null, breaks: item.breaks || [] })
        })
      }
      modal.saving = true
      try {
        const { data } = await axios.put(`${API}/inventory/${modal.row.id}/booking-settings`, { settings: modal.settings, resources, exceptions: modal.exceptions, rules: modal.rules })
        const payload = data.data || data
        modal.resources = payload.resources || resources
        modal.open = false
        await this.fetchData({ keepState: true })
        Swal.fire({ icon: "success", toast: true, position: "top-end", timer: 2200, showConfirmButton: false, title: "تنظیمات وقت‌دهی ذخیره شد" })
      } catch (error) {
        Swal.fire({ icon: "error", title: "خطا در ذخیره", text: error?.response?.data?.message || "تنظیمات وقت‌دهی ذخیره نشد." })
      } finally {
        modal.saving = false
      }
    },
    async openStockMovement(row) {
      if (!row?.id) {
        await this.saveData(false)
        await this.fetchData({ keepState: true })
        row = this.rows.find(item => item.name === row?.name)
        if (!row?.id) return
      }
      this.stockMovement = { open: true, row, direction: "increase", quantity: null, description: "", history: [], loading: true, saving: false }
      try {
        const { data } = await axios.get(`${API}/inventory/${row.id}/movements`)
        this.stockMovement.history = data.movements || []
        row.stock = Number(data.current_stock || 0)
      } finally {
        this.stockMovement.loading = false
      }
    },
    closeStockMovement() {
      if (!this.stockMovement.saving) this.stockMovement.open = false
    },
    async openMovementList(row) {
      this.closeStockMovement()
      this.movementList = { row, items: [], dateFrom: "", dateTo: "", loading: false }
      this.inventoryView = 'movements'
      await this.loadMovementList()
    },
    closeMovementList() {
      if (this.movementList.loading) return
      this.movementList = { row: null, items: [], dateFrom: "", dateTo: "", loading: false }
      this.inventoryView = 'table'
    },
    openServiceTagsPage() {
      this.closeServiceTagPicker()
      this.inventoryView = 'service-tags'
    },
    closeServiceTagsPage() {
      this.inventoryView = 'table'
    },
    syncServiceTagOptions(tags) {
      this.serviceTagOptions = (tags || []).map(tag => String(tag?.name || '').trim()).filter(Boolean)
    },
    raiseMovementDatePicker(pickerVm = null) {
      requestAnimationFrame(() => {
        const picker = pickerVm?.$refs?.picker
          || document.querySelector('body > .vpd-wrapper:last-of-type')
          || document.querySelector('.movement-date-picker-popover')
        if (!picker) return
        picker.style.setProperty('z-index', '2147483006', 'important')
        const container = picker.querySelector?.('.vpd-container')
        if (container) container.style.setProperty('z-index', '2147483007', 'important')
      })
    },
    async loadMovementList() {
      if (!this.movementList.row?.id) return
      this.movementList.loading = true
      try {
        const { data } = await axios.get(`${API}/inventory/${this.movementList.row.id}/movements`, { params: { date_from: this.movementList.dateFrom || undefined, date_to: this.movementList.dateTo || undefined, limit: 500 } })
        this.movementList.items = data.movements || []
        this.movementList.row.stock = Number(data.current_stock || 0)
      } finally {
        this.movementList.loading = false
      }
    },
    async saveStockMovement() {
      const movement = this.stockMovement
      const quantity = Number(movement.quantity)
      if (!movement.row?.id || movement.saving) return
      if (!Number.isInteger(quantity) || quantity < 1) {
        Swal.fire({ icon: "warning", title: "تعداد نامعتبر است", text: "موجودی کالا فقط باید یک عدد صحیحِ بزرگ‌تر از صفر باشد." })
        return
      }
      movement.saving = true
      try {
        const { data } = await axios.post(`${API}/inventory/adjust-stock`, {
          inventory_id: movement.row.id,
          direction: movement.direction,
          quantity,
          description: movement.description,
        })
        movement.row.stock = Number(data.stock || 0)
        movement.history = [data.movement, ...movement.history]
        movement.quantity = null
        movement.description = ""
        window.dispatchEvent(new CustomEvent("app:notifications-changed"))
      } catch (error) {
        Swal.fire({ icon: "error", title: "ثبت گردش انجام نشد", text: error.response?.data?.message || "موجودی قابل تغییر نیست." })
      } finally {
        movement.saving = false
      }
    },
    formatMovementDate(value) {
      if (!value) return '-'
      return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      }).format(new Date(value))
    },
    async fetchData(options = {}) {
      const previousSectionKey = this.activeSectionKey
      const previousSelectedName = this.selectedRow?.name || ""
      this.isFetching = true

      try {
        const fresh = Date.now()
        const [inventoryRes, contextRes, settingsRes] = await Promise.all([
          axios.get(`${API}/inventory`, { params: { _fresh: fresh }, headers: { "Cache-Control": "no-cache" } }),
          axios.get(`${API}/inventory/context`, { params: { _fresh: fresh }, headers: { "Cache-Control": "no-cache" } }),
          axios.get(`${API}/settings`, { params: { _fresh: fresh }, headers: { "Cache-Control": "no-cache" } }).catch(() => ({ data: {} }))
        ])

        if (settingsRes.data?.clinic_schedule) this.clinicSchedule = { ...this.clinicSchedule, ...settingsRes.data.clinic_schedule }

        this.doctors = contextRes.data.doctors || []
        this.staff = contextRes.data.staff || []
        this.users = contextRes.data.users || []
        this.serviceTagOptions = Array.isArray(contextRes.data.service_tags)
          ? contextRes.data.service_tags
          : []
        this.addonDefinitions = Array.isArray(contextRes.data.addons) ? contextRes.data.addons : []

        const normalizedSections = this.normalizeInventorySections(contextRes.data.sections || [])
        this.sectionIdRedirects = normalizedSections.redirects

        this.sections = normalizedSections.sections.map((section, index) => ({
          id: section.id,
          client_id: null,
          parent_id: section.parent_id || section.parentId || null,
          level: Number(section.level || 1),
            name: section.name,
          color: section.color || null,
          sort_order: section.sort_order ?? index
        }))
        const databaseColors = this.sections.reduce((colors, section) => {
          if (section.color) colors[String(this.sectionKey(section))] = section.color
          return colors
        }, {})
        this.sectionColors = { ...this.sectionColors, ...databaseColors }

        if (!this.sections.length) {
          this.sections = this.defaultSections()
        }

        this.expandedSectionKeys = this.sections
          .filter(section => Number(section.level || 1) < 2)
          .map(section => this.sectionKey(section))

        const requestedSectionKey = this.consumeRequestedSectionKey()
        const restoredSection = this.sections.find(section => this.sectionKey(section) === previousSectionKey)
        const requestedSection = requestedSectionKey
          ? this.selectableSectionForKey(requestedSectionKey)
          : null

        if (requestedSection) {
          this.selectHierarchyForLeaf(requestedSection)
          this.inventoryView = "table"
        } else if (options.keepState && restoredSection) {
          this.selectHierarchyForLeaf(restoredSection)
        } else {
          this.selectFirstLeaf()
        }

        this.rows = (inventoryRes.data || []).map((item, index) => this.normalizeItem(item, index))
        this.originalStockByKey = this.makeStockSnapshot(this.rows)

        if (this.rows.length && !contextRes.data.sections?.length) {
          this.rows.forEach(row => {
            row.section_id = this.activeSectionKey
          })
        }

        this.selectedRow = options.keepState
          ? this.activeSectionRows.find(row => row.name === previousSelectedName) || this.activeSectionRows[0] || null
          : this.activeSectionRows[0] || null
      } catch (error) {
        console.error(error)
      } finally {
        setTimeout(() => {
          this.isFetching = false
          if (options.keepState) {
            this.hasUnsavedChanges = false
            this.saveState = "saved"
          }
        }, 200)
      }
    },

    consumeRequestedSectionKey() {
      const requestedId = localStorage.getItem("inventory-open-section-id")
      if (!requestedId) return ""
      localStorage.removeItem("inventory-open-section-id")
      return this.resolveSectionKey(requestedId)
    },

    selectableSectionForKey(sectionKey) {
      const section = this.sections.find(item => this.sectionKey(item) === String(sectionKey || ""))
      if (!section) return null
      return this.firstLeafInBranch(section)
    },

    normalizeInventorySections(sections = []) {
      return {
        sections: (sections || []).map(section => ({
          ...section,
          level: Math.max(1, Number(section.level || 1)),
          parent_id: section.parent_id || section.parentId || null,
        })),
        redirects: {},
      }
    },

    resolveSectionKey(sectionKey) {
      const key = String(sectionKey || "")
      return this.sectionIdRedirects[key] || key
    },

    firstSelectableSectionKey() {
      const section = this.sections.find(item => !this.childSections(this.sectionKey(item)).length) || this.sections[0]
      return section ? this.sectionKey(section) : ""
    },

    normalizeItem(item, index) {
      const fallbackSection = this.firstSelectableSectionKey()
      const rawSectionKey = item.section_id || item.section?.id || fallbackSection

      return {
        localId: `row-${item.id || Date.now()}-${index}`,
        id: item.id || null,
        section_id: this.resolveSectionKey(rawSectionKey) || fallbackSection,
        name: item.name || "",
        serviceTags: Array.isArray(item.service_tags || item.serviceTags) ? [...(item.service_tags || item.serviceTags)] : [],
        defaultAddonIds: (item.default_addons || item.defaultAddons || []).map(addon => String(addon.id || addon)).filter(Boolean),
        addonDefinitionIds: (item.addon_definitions || item.addonDefinitions || []).map(addon => String(addon.id || addon)).filter(Boolean),
        tagDraft: "",
        tagPickerOpen: false,
        amount: Number(item.amount) || 0,
        price: Number(item.price) || 0,
        count: Number(item.count) || 0,
        stock: Math.trunc(Number(item.stock) || 0),
        minStock: Number(item.min_stock ?? item.minStock ?? 5),
        active: item.active === undefined ? true : Boolean(item.active),
        followupDays: Math.max(0, Number(item.followup_days ?? item.followupDays ?? 0) || 0),
        sort_order: item.sort_order ?? index,
        defaultCommissionType: item.default_commission_type || "percent",
        defaultCommissionValue: Number(item.default_commission_value) || 0,
        bookingSetting: item.booking_setting || item.bookingSetting || null,
        bookingResources: item.booking_resources || item.bookingResources || [],
        commissions: (item.commissions || []).map(commission => ({
          recipient_type: commission.recipient_type,
          recipient_id: commission.recipient_id,
          recipient_name: commission.recipient_name,
          commission_type: commission.commission_type || "percent",
          commission_value: Number(commission.commission_value) || 0
        }))
      }
    },

    queueSave() {
      if (this.isFetching) return
      clearTimeout(this.saveTimer)
      this.hasUnsavedChanges = true
      this.saveState = "dirty"
    },

    inventoryNotificationKey(row) {
      if (row?.id) return `id:${row.id}`
      const name = String(row?.name || "").trim()
      return name ? `name:${name}` : ""
    },

    makeStockSnapshot(rows) {
      return (rows || []).reduce((snapshot, row) => {
        const key = this.inventoryNotificationKey(row)
        if (key) snapshot[key] = Number(row.stock) || 0
        return snapshot
      }, {})
    },

    readInventoryZeroNotifications() {
      try {
        const value = JSON.parse(localStorage.getItem(INVENTORY_ZERO_NOTIFICATIONS_KEY) || "[]")
        return Array.isArray(value) ? value : []
      } catch {
        return []
      }
    },

    writeInventoryZeroNotifications(notifications) {
      localStorage.setItem(INVENTORY_ZERO_NOTIFICATIONS_KEY, JSON.stringify(notifications))
      window.dispatchEvent(new CustomEvent("app:notifications-changed"))
    },

    registerZeroStockNotifications() {
      const currentSnapshot = this.makeStockSnapshot(this.rows)
      const existing = this.readInventoryZeroNotifications()
      const existingEventKeys = new Set(existing.map(item => item.eventKey).filter(Boolean))
      const createdAt = new Date().toISOString()
      const nextNotifications = [...existing]

      this.rows.forEach((row) => {
        const key = this.inventoryNotificationKey(row)
        if (!key) return

        const previousStock = Number(this.originalStockByKey[key] ?? 0)
        const currentStock = Number(row.stock) || 0
        if (previousStock <= 0 || currentStock > 0) return

        const eventKey = `${key}:${createdAt.slice(0, 19)}`
        if (existingEventKeys.has(eventKey)) return

        nextNotifications.push({
          id: eventKey,
          eventKey,
          inventoryKey: key,
          itemId: row.id || null,
          itemName: row.name || "محصول بدون نام",
          createdAt
        })
      })

      if (nextNotifications.length !== existing.length) {
        this.writeInventoryZeroNotifications(nextNotifications)
      }

      this.originalStockByKey = currentSnapshot
    },

    async saveData(showFeedback = false) {
      if (this.isSaving || this.isFetching) return
      clearTimeout(this.saveTimer)
      this.isSaving = true
      this.saveState = "saving"

      try {
        await axios.post(`${API}/inventory`, {
          sections: this.sections.map((section, index) => ({
            id: section.id,
            client_id: section.client_id,
            parent_id: section.parent_id,
            level: section.level,
            name: section.name,
            color: section.color || this.sectionColors[String(this.sectionKey(section))] || null,
            sort_order: index
          })),
          items: this.rows.map((row, index) => ({
            id: row.id,
            client_id: row.localId,
            section_id: row.section_id,
            name: row.name,
            service_tags: this.normalizedServiceTags(row.serviceTags),
            default_addon_ids: row.defaultAddonIds,
            addon_definition_ids: row.addonDefinitionIds,
            amount: row.amount,
            price: row.price,
            count: row.count,
            stock: row.stock,
            min_stock: row.minStock,
            active: row.active,
            followup_days: row.followupDays,
            sort_order: index,
            default_commission_type: row.defaultCommissionType,
            default_commission_value: row.defaultCommissionValue,
            commissions: row.commissions
          }))
        })
        this.registerZeroStockNotifications()
        this.hasUnsavedChanges = false
        this.saveState = "saved"

        if (showFeedback) {
          await this.fetchData({ keepState: true })
        }

        setTimeout(() => {
          if (!this.hasUnsavedChanges && this.saveState === "saved") {
            this.saveState = "idle"
          }
        }, 1800)
      } catch (error) {
        console.error(error)
        this.hasUnsavedChanges = true
        this.saveState = "error"
        Swal.fire({ icon: "error", title: "خطا در ذخیره", text: error?.response?.data?.message || "اطلاعات خدمات ذخیره نشد. دوباره تلاش کنید." })
      } finally {
        this.isSaving = false
      }
    },

    defaultSections() {
      const root = this.makeSection("پوست و زیبایی", null, 1)
      const sub = this.makeSection("ژل", root.client_id, 2)
      return [root, sub]
    },

    makeSection(name = "", parentId = null, level = 1) {
      return {
        id: null,
        client_id: `section-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        parent_id: parentId,
        level,
        name,
        sort_order: this.sections.length
      }
    },

    addRootSection() {
      const section = this.makeSection("خدمات جدید", null, 1)
      this.sections.push(section)
      this.expandedSectionKeys.push(this.sectionKey(section))
      this.selectRoot(section)
    },

    addChildSection(parentKey, level) {
      if (!parentKey) return
      const section = this.makeSection("زیرشاخه جدید", parentKey, level)
      this.sections.push(section)
      if (!this.expandedSectionKeys.includes(String(parentKey))) {
        this.expandedSectionKeys.push(String(parentKey))
      }
      this.selectSub(section)
    },

    selectRoot(section) {
      this.closeServiceTagPicker()
      this.activeRootKey = this.sectionKey(section)
      this.activeTreeKey = this.activeRootKey
      if (!this.expandedSectionKeys.includes(this.activeRootKey)) this.expandedSectionKeys.push(this.activeRootKey)
      this.activeSubKey = ""
      this.activeSectionKey = ""
      this.selectedRow = null
    },

    selectSub(section) {
      this.closeServiceTagPicker()
      this.activeSubKey = this.sectionKey(section)
      this.activeTreeKey = this.activeSubKey
      if (!this.expandedSectionKeys.includes(this.activeSubKey)) this.expandedSectionKeys.push(this.activeSubKey)
      this.activeSectionKey = this.activeSubKey
      this.selectedRow = this.activeSectionRows[0] || null
    },

    selectTreeNode(section) {
      if (this.childSections(this.sectionKey(section)).length) {
        this.selectRoot(section)
        return
      }
      this.selectSub(section)
    },

    selectFirstLeaf() {
      const leaf = this.sections
        .filter(section => !this.childSections(this.sectionKey(section)).length)
        .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))[0]
      if (leaf) {
        this.selectHierarchyForLeaf(leaf)
        return
      }
      const root = this.rootSections[0]
      if (root) this.selectRoot(root)
    },

    selectHierarchyForLeaf(leaf) {
      const lineage = this.sectionLineage(leaf)
      const root = lineage[0]
      this.activeRootKey = root ? this.sectionKey(root) : ""
      this.activeSubKey = this.sectionKey(leaf)
      this.activeSectionKey = this.sectionKey(leaf)
      this.activeTreeKey = this.activeSectionKey
      lineage.map(section => this.sectionKey(section)).filter(Boolean).forEach(key => {
        if (!this.expandedSectionKeys.includes(key)) this.expandedSectionKeys.push(key)
      })
    },

    childSections(parentKey) {
      return this.sections
        .filter(section => String(section.parent_id || "") === String(parentKey || ""))
        .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))
    },

    firstLeafInBranch(section) {
      let current = section
      const visited = new Set()
      while (current && !visited.has(this.sectionKey(current))) {
        visited.add(this.sectionKey(current))
        const child = this.childSections(this.sectionKey(current))[0]
        if (!child) return current
        current = child
      }
      return section
    },

    sectionLineage(section) {
      const lineage = []
      let current = section
      const visited = new Set()
      while (current && !visited.has(this.sectionKey(current))) {
        lineage.unshift(current)
        visited.add(this.sectionKey(current))
        current = this.sections.find(item => this.sectionKey(item) === String(current.parent_id || current.parentId || ''))
      }
      return lineage
    },

    descendantSectionKeys(sectionKey) {
      const result = []
      const walk = key => this.childSections(key).forEach(section => {
        const childKey = this.sectionKey(section)
        result.push(childKey)
        walk(childKey)
      })
      walk(String(sectionKey || ''))
      return result
    },

    isTreeExpanded(section) {
      return this.expandedSectionKeys.includes(this.sectionKey(section))
    },

    toggleTreeNode(section) {
      const key = this.sectionKey(section)
      const index = this.expandedSectionKeys.indexOf(key)
      if (index >= 0) this.expandedSectionKeys.splice(index, 1)
      else this.expandedSectionKeys.push(key)
    },

    treeNodeCount(section) {
      return this.childSections(this.sectionKey(section)).length
        ? this.childSections(this.sectionKey(section)).length
        : this.sectionItemCount(section)
    },

    treePlaceholder(level) {
      if (Number(level) === 1) return "مثلا پوست و زیبایی"
      return "مثلا ژل، بوتاکس یا زیرگروه جدید"
    },

    removeSectionNode(section) {
      this.selectTreeNode(section)
      const key = this.sectionKey(section)
      if (this.childSections(key).length) {
        alert("این شاخه زیرشاخه دارد. ابتدا زیرشاخه‌های داخل آن را حذف کنید.")
        return
      }
      if (!this.childSections(key).length && this.rows.some(row => this.rowSectionKey(row) === key)) {
        alert(`این گروه دارای ${this.sectionItemCount(section)} آیتم است. ابتدا آیتم‌های داخل آن را حذف کنید.`)
        return
      }
      if (this.sections.length <= 1) {
        alert("حداقل یک بخش باید در خدمات باقی بماند.")
        return
      }
      const index = this.sections.findIndex(item => this.sectionKey(item) === key)
      if (index >= 0) this.sections.splice(index, 1)
      this.expandedSectionKeys = this.expandedSectionKeys.filter(item => item !== key)
      this.selectFirstLeaf()
    },

    removeActiveSection() {
      if (!this.activeSection) return
      if (Number(this.activeSection.level || 1) !== 2) {
        alert("برای حذف، ابتدا یک زیرشاخه انتخاب کنید.")
        return
      }

      if (this.activeSectionRows.length > 0) {
        alert(`این گروه دارای ${this.activeSectionRows.length} آیتم است. ابتدا همه آیتم‌های این گروه را حذف کنید تا امکان حذف فراهم شود.`)
        return
      }

      if (this.sections.length <= 1) {
        alert("حداقل یک بخش باید در خدمات باقی بماند.")
        return
      }

      const index = this.sections.findIndex(section => this.sectionKey(section) === this.activeSectionKey)
      this.sections.splice(index, 1)
      this.selectFirstLeaf()
    },

    addRow() {
      if (!this.activeSectionKey) {
        alert("ابتدا یک زیرشاخه از خدمات انتخاب کنید.")
        return
      }
      const row = {
        localId: `row-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        id: null,
        section_id: this.activeSectionKey,
        name: "",
        serviceTags: [],
        defaultAddonIds: [],
        addonDefinitionIds: [],
        tagDraft: "",
        tagPickerOpen: false,
        amount: 0,
        price: 0,
        count: 0,
        stock: 0,
        minStock: 5,
        active: true,
        followupDays: 0,
        sort_order: this.rows.length,
        defaultCommissionType: "percent",
        defaultCommissionValue: 0,
        commissions: []
      }

      this.rows.push(row)
      this.selectRow(row)
    },

    removeRow(row) {
      this.closeServiceTagPicker()
      const index = this.rows.findIndex(item => item.localId === row.localId)
      if (index === -1) return
      this.rows.splice(index, 1)
      if (this.selectedRow?.localId === row.localId) {
        this.selectedRow = this.activeSectionRows[0] || null
      }
    },

    selectRow(row) {
      if (this.selectedRow?.localId !== row.localId) this.closeServiceTagPicker()
      this.selectedRow = row
      this.commissionPersonKey = ""
      this.commissionDraft = { type: "percent", value: 0 }
    },

    normalizedServiceTags(tags) {
      const allowed = new Set((this.serviceTagOptions || []).map(tag => this.normalizeTagText(tag)))
      return Array.from(new Set((tags || [])
        .map(tag => String(tag || '').trim())
        .filter(Boolean)
        .filter(tag => !allowed.size || allowed.has(this.normalizeTagText(tag)))))
    },

    normalizeTagText(value) {
      return String(value || '')
        .trim()
        .replace(/[يى]/g, 'ی')
        .replace(/ك/g, 'ک')
        .replace(/\s+/g, ' ')
    },

    filteredServiceTagOptions(row) {
      const selected = new Set((row.serviceTags || []).map(tag => this.normalizeTagText(tag)))
      const query = this.normalizeTagText(row.tagDraft)
      return (this.serviceTagOptions || [])
        .filter(tag => !selected.has(this.normalizeTagText(tag)))
        .filter(tag => !query || this.normalizeTagText(tag).includes(query))
        .slice(0, 80)
    },

    openServiceTagPicker(row, event) {
      const rect = event.currentTarget.getBoundingClientRect()
      const width = Math.min(320, Math.max(240, rect.width))
      const viewportPadding = 12
      const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - width - viewportPadding))
      const below = rect.bottom + 8
      const top = below + 260 > window.innerHeight
        ? Math.max(viewportPadding, rect.top - 252)
        : below

      this.tagPicker = { row, top, left }
    },

    selectServiceTag(row, tag) {
      row.serviceTags = this.normalizedServiceTags([...(row.serviceTags || []), tag])
      row.tagDraft = ""
      this.closeServiceTagPicker()
      this.queueSave()
    },

    closeServiceTagPicker() {
      if (this.tagPicker.row) this.tagPicker.row.tagDraft = ""
      this.tagPicker = { row: null, top: 0, left: 0 }
    },

    removeServiceTag(row, tagIndex) {
      row.serviceTags.splice(tagIndex, 1)
      this.queueSave()
    },

    defaultAddonKey(item) {
      return String(item?.id || item?.localId || '')
    },

    openDefaultAddonsModal(row) {
      this.closeServiceTagPicker()
      this.defaultAddonsModal = { open: true, row, draftIds: [...(row.addonDefinitionIds || [])].map(String), query: '', globalMode: false, parentLocalId: row.localId }
    },

    openGlobalDefaultAddonsModal() {
      const row = this.selectedRow || this.activeSectionRows.find(item => item.name) || this.rows.find(item => item.name)
      if (!row) {
        Swal.fire({ icon: 'info', title: 'ابتدا یک کالا ثبت کنید', text: 'بعد از ثبت کالا یا خدمت می‌توانید جانبی‌های پیش‌فرض آن را مدیریت کنید.' })
        return
      }
      this.closeServiceTagPicker()
      this.defaultAddonsModal = { open: true, row, draftIds: [...(row.defaultAddonIds || [])].map(String), query: '', globalMode: true, parentLocalId: row.localId }
    },

    openAddonManager() {
      this.addonManager = { open: true, items: this.addonDefinitions.map(item => ({ ...item, _key: `addon-${item.id}` })) }
    },

    closeAddonManager() { this.addonManager.open = false },

    addAddonDefinition() {
      this.addonManager.items.push({ _key: `addon-${Date.now()}-${Math.random()}`, name: '', amount: 0, price: 0, stock: 0, min_stock: 5, active: true })
    },

    async saveAddonManager() {
      await axios.post(`${API}/inventory/addons`, { items: this.addonManager.items.filter(item => String(item.name || '').trim()) })
      await this.fetchData({ keepState: true })
      this.closeAddonManager()
    },

    changeDefaultAddonsParent() {
      const row = this.rows.find(item => item.localId === this.defaultAddonsModal.parentLocalId)
      if (!row) return
      this.defaultAddonsModal.row = row
      this.defaultAddonsModal.draftIds = [...(row.defaultAddonIds || [])].map(String)
      this.defaultAddonsModal.query = ''
    },

    closeDefaultAddonsModal() {
      this.defaultAddonsModal = { open: false, row: null, draftIds: [], query: '', globalMode: false, parentLocalId: '' }
    },

    isDefaultAddonSelected(item) {
      return this.defaultAddonsModal.draftIds.includes(this.defaultAddonKey(item))
    },

    toggleDefaultAddon(item) {
      const id = this.defaultAddonKey(item)
      const selected = new Set(this.defaultAddonsModal.draftIds)
      if (selected.has(id)) selected.delete(id)
      else selected.add(id)
      this.defaultAddonsModal.draftIds = [...selected]
    },

    saveDefaultAddonsModal() {
      if (!this.defaultAddonsModal.row) return
      this.defaultAddonsModal.row.addonDefinitionIds = [...this.defaultAddonsModal.draftIds]
      this.closeDefaultAddonsModal()
      this.queueSave()
    },

    openCommissionModal(row = null, target = "section") {
      if (row) this.selectRow(row)
      const nextTarget = target || (row ? "item" : "section")
      this.bulkCommission.target = nextTarget
      this.bulkCommission.sectionKey = nextTarget === "group" ? this.activeRootKey : this.activeSectionKey
      if (nextTarget !== "tag") this.bulkCommission.tag = ""
      this.defaultCommissionRow = row || (nextTarget === "group" ? this.commissionScopeRows[0] : this.selectedRow)
      this.defaultCommissionDraft = {
        type: this.defaultCommissionRow?.defaultCommissionType || this.bulkCommission.type || "percent",
        value: Number(this.defaultCommissionRow?.defaultCommissionValue ?? this.bulkCommission.value) || 0
      }
      this.showDefaultCommissionModal = true
    },

    openDefaultCommissionModal(row) {
      this.openCommissionModal(row, "item")
    },

    closeDefaultCommissionModal() {
      this.showDefaultCommissionModal = false
      this.defaultCommissionRow = null
    },

    saveDefaultCommissionModal() {
      const rows = this.commissionScopeRows
      if (!rows.length) return
      rows.forEach(row => {
        row.defaultCommissionType = this.defaultCommissionDraft.type
        row.defaultCommissionValue = Number(this.defaultCommissionDraft.value) || 0
      })
      this.bulkCommission.type = this.defaultCommissionDraft.type
      this.bulkCommission.value = Number(this.defaultCommissionDraft.value) || 0
      this.queueSave()
      this.closeDefaultCommissionModal()
    },

    applyBulkCommission() {
      const rows = this.bulkCommissionRows
      if (!rows.length) return

      rows.forEach(row => {
        row.defaultCommissionType = this.bulkCommission.type
        row.defaultCommissionValue = Number(this.bulkCommission.value) || 0
      })
      this.queueSave()
    },

    addCommission() {
      if (!this.selectedRow || !this.commissionPersonKey) return

      const [type, id] = this.commissionPersonKey.split(":")
      const recipient = this.findRecipient(type, Number(id))
      if (!recipient) return

      const existing = this.selectedRow.commissions.find(
        item => item.recipient_type === type && Number(item.recipient_id) === Number(id)
      )

      const payload = {
        recipient_type: type,
        recipient_id: Number(id),
        recipient_name: recipient.name,
        commission_type: this.commissionDraft.type,
        commission_value: Number(this.commissionDraft.value) || 0
      }

      if (existing) {
        Object.assign(existing, payload)
      } else {
        this.selectedRow.commissions.push(payload)
      }

      this.commissionPersonKey = ""
      this.commissionDraft = { type: "percent", value: 0 }
    },

    removeCommission(index) {
      if (!this.selectedRow) return
      this.selectedRow.commissions.splice(index, 1)
    },

    findRecipient(type, id) {
      const source = type === "doctor" ? this.doctors : type === "staff" ? this.staff : this.users
      return source.find(item => Number(item.id) === Number(id))
    },

    sectionKey(section) {
      return String(section.id || section.client_id)
    },

    rowSectionKey(row) {
      return String(row.section_id || "")
    },

    sectionItemCount(section) {
      const key = this.sectionKey(section)
      return this.rows.filter(row => this.rowSectionKey(row) === key).length
    },

    sectionNameForRow(row) {
      const section = this.sections.find(item => this.sectionKey(item) === this.rowSectionKey(row))
      if (!section) return "بدون گروه"
      return this.sectionLineage(section).map(item => item.name).filter(Boolean).join(" / ")
    },

    normalizeSearchText(value) {
      return String(value ?? "")
        .toLocaleLowerCase("fa")
        .replace(/[يى]/g, "ی")
        .replace(/ك/g, "ک")
        .replace(/[۰-۹٠-٩]/g, digit => "۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩".indexOf(digit) % 10)
        .replace(/[،,]/g, "")
        .replace(/\s+/g, " ")
        .trim()
    },

    stockClass(stock, minStock) {
      if (stock <= 0) return "stock-zero"
      if (stock < minStock) return "stock-low"
      return ""
    },

    chartStockClass(item) {
      return this.stockClass(item.chartStock, item.chartMinStock)
    },

    inventoryStockValue(row) {
      const stock = Number(row?.stock)
      if (!Number.isNaN(stock)) return stock

      const count = Number(row?.count)
      return Number.isNaN(count) ? 0 : count
    },

    inventoryMinStockValue(row) {
      const minStock = Number(row?.minStock)
      return Number.isNaN(minStock) ? 0 : minStock
    },

    getBarWidth(stock) {
      const value = Number(stock) || 0
      const maxStock = Math.max(...this.chartData.map(item => Number(item.chartStock) || 0), 1)
      if (value <= 0) return "34px"
      return `${Math.max((value / maxStock) * 100, 8)}%`
    },

    formatNumberWithCommas(value) {
      if (value === null || value === undefined) return ""
      return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
    },

    parseNumber(value) {
      if (!value) return 0
      const number = value.toString().replace(/,/g, "")
      return isNaN(number) ? 0 : Number(number)
    },

    onMoneyInput(event, row, key) {
      const value = this.parseNumber(event.target.value)
      row[key] = value
      event.target.value = this.formatNumberWithCommas(value)
    },

    commissionLabel(type, value) {
      const number = Number(value) || 0
      return type === "fixed" ? `${this.formatNumberWithCommas(number)} تومان` : `${number}%`
    },

    recipientTypeLabel(type) {
      return {
        doctor: "پزشک",
        staff: "پرسنل",
        user: "کاربر"
      }[type] || "معرف"
    }
  }
}
</script>

<style scoped>
@import '@/scss/main.scss';

/* تفکیک بصری گردش‌های افزایش و کاهش */
.movement-table tr.in td { background: #f0fdf4; }
.movement-table tr.out td { background: #fff5f5; }
.movement-table tr.in td:first-child { box-shadow: inset -4px 0 0 #86efac; }
.movement-table tr.out td:first-child { box-shadow: inset -4px 0 0 #fca5a5; }

.stock-cell{display:flex;align-items:center;justify-content:center;gap:7px}.stock-cell strong{min-width:34px;text-align:center}.stock-cell button{width:28px;height:28px;border:0;border-radius:8px;background:#dbeafe;color:#1d4ed8;font-size:18px;font-weight:900;cursor:pointer}.stock-movement-modal{width:min(570px,94vw)}.stock-current{margin:14px 0;padding:13px;border-radius:11px;background:#eff6ff;color:#1e40af;font-size:13px}.stock-current strong{font-size:19px}.stock-direction{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}.stock-direction button{height:40px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#475569;font-family:inherit;font-weight:800;cursor:pointer}.stock-direction button.active{border-color:#2563eb;background:#2563eb;color:#fff}.stock-movement-modal textarea{min-height:66px;padding:9px;resize:vertical}.movement-history{display:grid;gap:8px;margin-top:18px}.movement-history>strong{color:#334155;font-size:13px}.movement-history>p{margin:0;color:#94a3b8;font-size:12px}.movement-row{display:grid;grid-template-columns:58px 1fr auto;gap:8px;align-items:center;padding:9px;border-radius:9px;background:#f8fafc;font-size:11px}.movement-row.in b{color:#15803d}.movement-row.out b{color:#dc2626}.movement-row span{color:#475569}.movement-row time{color:#94a3b8;font-size:10px}
.inventory-addons-btn{display:inline-flex;align-items:center;justify-content:center;gap:4px;min-height:32px;padding:0 8px;border:1px solid #c4b5fd;border-radius:8px;background:#faf5ff;color:#6d28d9;font-family:inherit;font-size:10px;font-weight:900;cursor:pointer;white-space:nowrap}.inventory-addons-btn:hover{border-color:#8b5cf6;background:#f3e8ff}.inventory-addons-btn b{display:grid;place-items:center;min-width:17px;height:17px;border-radius:9px;background:#7c3aed;color:#fff}.inventory-addons-btn span{font-size:16px;line-height:1}.inventory-addons-modal{width:min(590px,94vw)}.inventory-addons-search{width:100%;box-sizing:border-box;margin-top:14px;padding:11px;border:1px solid #cbd5e1;border-radius:9px;font-family:inherit;text-align:right}.inventory-addons-help{margin:10px 0;color:#64748b;font-size:11px;line-height:1.8}.inventory-addons-list{max-height:360px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px}.inventory-addon-option{display:flex;align-items:center;gap:10px;padding:11px;border-bottom:1px solid #edf2f7;cursor:pointer}.inventory-addon-option:last-child{border-bottom:0}.inventory-addon-option input{width:17px;height:17px;accent-color:#7c3aed}.inventory-addon-option span{display:grid;gap:3px;min-width:0}.inventory-addon-option strong{color:#1e293b;font-size:12px}.inventory-addon-option small{color:#64748b;font-size:10px}.inventory-addons-empty{margin:0;padding:20px;color:#94a3b8;text-align:center;font-size:12px}
.movement-history-head{display:flex;align-items:center;justify-content:space-between}.movement-history-head button{border:0;background:transparent;color:#2563eb;font-family:inherit;font-size:11px;font-weight:900;cursor:pointer}.movement-page{display:grid;gap:16px;padding:18px;border:1px solid #dbeafe;border-radius:18px;background:#fff}.movement-filters{display:flex;align-items:end;gap:10px;flex-wrap:wrap;padding:14px;border-radius:13px;background:#f8fafc}.movement-filters label{display:grid;gap:5px;color:#64748b;font-size:11px;font-weight:800}.movement-filters input{height:37px;border:1px solid #cbd5e1;border-radius:8px;padding:0 9px;font-family:inherit}.movement-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.movement-summary article{display:grid;gap:5px;padding:14px;border:1px solid #e2e8f0;border-radius:12px;color:#64748b;font-size:11px}.movement-summary strong{font-size:20px;color:#0f172a}.movement-summary .in{border-color:#bbf7d0;background:#f0fdf4}.movement-summary .in strong{color:#15803d}.movement-summary .out{border-color:#fecaca;background:#fff7f7}.movement-summary .out strong{color:#dc2626}.movement-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}.movement-table{width:100%;border-collapse:collapse}.movement-table th,.movement-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:right;font-size:12px}.movement-table th{background:#f8fafc;color:#475569}.movement-table tr.in b{color:#15803d}.movement-table tr.out b{color:#dc2626}.movement-loading{padding:14px;color:#64748b;font-size:12px}@media(max-width:700px){.movement-summary{grid-template-columns:1fr}.movement-table{min-width:620px}}

.inventory-addons-page-btn{display:inline-flex;align-items:center;gap:6px;min-height:38px;padding:0 11px;border:1px solid #c4b5fd;border-radius:10px;background:#faf5ff;color:#6d28d9;font-family:inherit;font-size:11px;font-weight:900;cursor:pointer}.inventory-addons-page-btn:hover{border-color:#8b5cf6;background:#f3e8ff}.inventory-addons-btn{gap:2px;width:38px;height:32px;min-height:32px;padding:0}.inventory-addons-btn span{font-size:14px}.inventory-addons-parent{width:100%;box-sizing:border-box;margin-top:14px;padding:11px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;font-family:inherit;text-align:right}
.addon-manager-modal{width:min(980px,96vw)}.addon-manager-table{margin-top:16px;overflow:auto;border:1px solid #e2e8f0;border-radius:11px}.addon-manager-row{display:grid;grid-template-columns:minmax(170px,1.7fr) repeat(4,minmax(90px,1fr)) 72px 34px;gap:8px;align-items:center;padding:9px;border-bottom:1px solid #edf2f7}.addon-manager-row:last-child{border-bottom:0}.addon-manager-head{background:#f8fafc;color:#475569;font-size:11px;font-weight:900}.addon-manager-row input[type="number"],.addon-manager-row input[type="text"]{width:100%;box-sizing:border-box;height:35px;border:1px solid #cbd5e1;border-radius:7px;padding:0 8px;font-family:inherit;text-align:right}.addon-active{display:flex;align-items:center;justify-content:center;gap:5px;color:#475569;font-size:11px;font-weight:800}.addon-active input{width:16px;height:16px;accent-color:#2563eb}.addon-delete{width:30px;height:30px;border:0;border-radius:7px;background:#fee2e2;color:#dc2626;font-size:18px;cursor:pointer}.addon-add-btn{margin-top:12px}@media(max-width:760px){.addon-manager-row{min-width:760px}}

.inventory-page {
  display: grid;
  grid-template-columns: minmax(420px, 520px) minmax(0, 1fr);
  direction: rtl;
  gap: 18px;
  padding: 18px 26px 28px;
  font-family: "Vazir";
  width: 100%;
  box-sizing: border-box;
  color: #172033;
}

.inventory-page.movement-page-active {
  grid-template-columns: minmax(0, 1fr);
}

.inventory-main.movement-mode {
  width: 100%;
}

.inventory-main.service-tags-mode {
  width: 100%;
}

.movement-page .panel-head {
  align-items: center;
}

::v-deep(.movement-filters .vpd-input-group) {
  width: 190px;
}

::v-deep(.movement-date-input) {
  width: 100% !important;
  height: 37px !important;
  border: 1px solid #cbd5e1 !important;
  border-radius: 8px !important;
  padding: 0 9px !important;
  background: #fff !important;
  color: #334155 !important;
  font-family: inherit !important;
  text-align: right !important;
}

:global(.movement-date-picker-popover),
:global(.movement-date-picker-popover .vpd-container) {
  z-index: 2147483006 !important;
}

.inventory-view-switch {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(260px, 1fr) auto;
  gap: 18px;
  align-items: center;
  padding: 18px;
  border: 1px solid #dbeafe;
  border-radius: 18px;
  background:
    radial-gradient(circle at top right, rgba(37, 99, 235, .12), transparent 34%),
    linear-gradient(135deg, #ffffff, #f8fbff);
  box-shadow: 0 18px 45px rgba(15, 23, 42, .07);
}

.inventory-view-title span {
  display: inline-flex;
  margin-bottom: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: #eff6ff;
  color: #2563eb;
  font-size: 11px;
  font-weight: 900;
}

.inventory-view-title h2 {
  margin: 0 0 6px;
  color: #0f172a;
  font-size: 22px;
}

.inventory-view-title p {
  margin: 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.8;
}

.inventory-tabs {
  display: grid;
  grid-template-columns: repeat(2, minmax(170px, 1fr));
  gap: 10px;
}

.inventory-tabs button {
  min-height: 82px;
  border: 1px solid #dfe7f2;
  border-radius: 16px;
  padding: 12px;
  background: #fff;
  color: #475569;
  font-family: inherit;
  text-align: right;
  cursor: pointer;
  transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease, background .15s ease;
}

.inventory-tabs button:hover {
  transform: translateY(-1px);
  border-color: #93c5fd;
  box-shadow: 0 12px 28px rgba(37, 99, 235, .11);
}

.inventory-tabs button.active {
  border-color: #2563eb;
  background: linear-gradient(135deg, #2563eb, #0f766e);
  color: #fff;
  box-shadow: 0 16px 34px rgba(37, 99, 235, .22);
}

.inventory-tabs span,
.inventory-tabs strong,
.inventory-tabs small {
  display: block;
}

.inventory-tabs span {
  margin-bottom: 5px;
  font-size: 11px;
  font-weight: 900;
  opacity: .84;
}

.inventory-tabs strong {
  font-size: 14px;
}

.inventory-tabs small {
  margin-top: 5px;
  font-size: 11px;
  line-height: 1.6;
  opacity: .8;
}

.section-panel,
.table-section,
.commission-panel,
.chart-section {
  background: #fff;
  border: 1px solid #dfe7f2;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
}

.section-panel {
  border-radius: 18px;
  padding: 18px 18px 16px;
  direction: rtl;
  text-align: right;
}

.inventory-main {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}

.inventory-main.chart-mode {
  grid-template-columns: minmax(0, 1fr);
}

.inventory-toolbar {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(280px, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
  border: 1px solid #dfe7f2;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 12px 28px rgba(15, 23, 42, .05);
}

.inventory-toolbar .inventory-search {
  margin-bottom: 0;
}

.inventory-inline-tabs {
  display: inline-flex;
  gap: 6px;
  padding: 5px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
}

.inventory-inline-tabs button {
  border: 0;
  border-radius: 9px;
  width: 40px;
  height: 38px;
  display: grid;
  place-items: center;
  padding: 0;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
  transition: background .15s ease, color .15s ease, box-shadow .15s ease;
}

.inventory-inline-tabs button svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.inventory-inline-tabs button.active {
  background: linear-gradient(135deg, #2563eb, #0f766e);
  color: #fff;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .2);
}

.inventory-save-actions {
  grid-column: 1 / -1;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  justify-content: flex-start;
  padding-top: 10px;
  border-top: 1px solid #edf2f7;
}

.global-commission-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 11px;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.global-commission-btn:hover {
  border-color: #60a5fa;
  background: #dbeafe;
}

.global-commission-btn svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}

.service-tags-page-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 11px;
  border: 1px solid #c4b5fd;
  border-radius: 10px;
  background: #f5f3ff;
  color: #6d28d9;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.service-tags-page-btn:hover { background: #ede9fe; border-color: #a78bfa; }

.save-status {
  min-width: 112px;
  border-radius: 999px;
  padding: 7px 10px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  text-align: center;
  white-space: nowrap;
}

.save-status.dirty,
.save-status.error {
  background: #fff7ed;
  color: #ea580c;
}

.save-status.saving {
  background: #eff6ff;
  color: #2563eb;
}

.save-status.saved {
  background: #dcfce7;
  color: #15803d;
}

.save-reminder {
  color: #b45309;
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
}

.save-inventory-btn {
  border: 0;
  border-radius: 11px;
  padding: 11px 15px;
  background: linear-gradient(135deg, #16a34a, #0f766e);
  color: #fff;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  box-shadow: 0 10px 22px rgba(22, 163, 74, .2);
  white-space: nowrap;
}

.save-inventory-btn:not(:disabled) {
  animation: inventory-save-attention 1.7s ease-in-out infinite;
}

@keyframes inventory-save-attention {
  50% { box-shadow: 0 0 0 5px rgba(22, 163, 74, .13), 0 10px 22px rgba(22, 163, 74, .25); }
}

.save-inventory-btn:disabled {
  cursor: not-allowed;
  opacity: .55;
  box-shadow: none;
}

.table-section,
.chart-section {
  grid-column: 1;
}

.inventory-main.chart-mode .chart-section {
  grid-column: 1;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.panel-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.panel-head.compact {
  margin-bottom: 10px;
}

.chart-head {
  align-items: flex-start;
  border-bottom: 1px solid #edf2f7;
  padding-bottom: 12px;
}

.chart-head > h3 {
  display: none;
}

.chart-head-copy h3 {
  margin: 0 0 6px;
  color: #0f172a;
  font-size: 20px;
}

.chart-head-copy p {
  margin: 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.8;
}

.inventory-search {
  position: relative;
  display: flex;
  align-items: center;
  margin-bottom: 16px;
}

.inventory-search input {
  min-height: 44px;
  padding: 8px 42px 8px 116px;
  border-color: #bfcee0;
  background: #f8fbff;
  text-align: right;
}

.inventory-search input::-webkit-search-cancel-button {
  display: none;
}

.search-icon {
  position: absolute;
  right: 14px;
  z-index: 1;
  color: #64748b;
  font-size: 24px;
  line-height: 1;
  pointer-events: none;
}

.search-result-count {
  position: absolute;
  left: 43px;
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.search-clear {
  position: absolute;
  left: 10px;
  width: 26px;
  height: 26px;
  border: 0;
  border-radius: 6px;
  background: #e8eef6;
  color: #475569;
  cursor: pointer;
  font-size: 18px;
  line-height: 24px;
}

.row-section-name {
  display: block;
  margin-top: 4px;
  color: #64748b;
  font-size: 10px;
  font-weight: 700;
  text-align: right;
}

h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: #111827;
}

p {
  margin: 6px 0 0;
  color: #6b7890;
  font-size: 12px;
  line-height: 1.8;
}

.inventory-structure-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-direction: row-reverse;
  gap: 12px;
  margin-bottom: 18px;
}

.inventory-structure-head h3 {
  margin: 0;
  color: #1f2937;
  font-size: 17px;
  font-weight: 1000;
  text-align: right;
}

.structure-add-root-btn {
  height: 40px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 0 14px;
  border: 0;
  border-radius: 10px;
  background: #2f6df3;
  color: #fff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  direction: rtl;
  cursor: pointer;
  box-shadow: 0 12px 24px rgba(47, 109, 243, .22);
}

.structure-add-root-btn span {
  font-size: 16px;
  line-height: 1;
}

.inventory-tree {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.tree-node {
  min-height: 42px;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 10px;
  padding-right: calc(10px + (var(--tree-depth) * 16px));
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  cursor: pointer;
  transition: background-color .16s ease, border-color .16s ease;
}

.tree-node:hover {
  background: #f8fafc;
}

.tree-node.active {
  background: #eaf3ff;
}

.tree-more-btn,
.tree-add-btn,
.tree-toggle-btn {
  flex: 0 0 auto;
  border: 0;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.tree-more-btn {
  color: #cbd5e1;
  font-size: 16px;
  font-weight: 600;
  line-height: 1;
}

.tree-add-btn {
  color: #2563eb;
  font-size: 16px;
  font-weight: 1000;
}

.tree-add-btn:disabled {
  color: transparent;
  cursor: default;
}

.tree-toggle-btn {
  width: 16px;
  height: 16px;
  position: relative;
}

.tree-toggle-btn::before {
  content: "";
  position: absolute;
  inset: 5px 5px;
  border-top: 4px solid #94a3b8;
  border-right: 3.5px solid transparent;
  border-left: 3.5px solid transparent;
  transition: transform .16s ease;
}

.tree-toggle-btn.open::before {
  transform: rotate(90deg);
}

.tree-toggle-btn:disabled::before {
  opacity: 0;
}

.tree-dot {
  flex: 0 0 7px;
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: #cbd5e1;
}

.tree-node input {
  flex: 0 1 auto;
  width: auto;
  min-width: 70px;
  max-width: 150px;
  height: 32px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #374151;
  font-family: inherit;
  font-size: 13px;
  font-weight: 900;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
  outline: none;
}


.tree-node.leaf input {
  font-size: 12px;
}

.tree-node.active input {
  color: #1d4ed8;
}

.tree-count {
  flex: 0 0 28px;
  display: grid;
  place-items: center;
  min-width: 28px;
  height: 28px;
  border-radius: 999px;
  background: #eef2f7;
  color: #64748b;
  font-size: 12px;
  font-weight: 900;
}

.tree-spacer {
  flex: 1 1 auto;
  min-width: 8px;
}

.tree-empty {
  display: grid;
  place-items: center;
  min-height: 46px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  color: #94a3b8;
  font-size: 10px;
  font-weight: 900;
}

.inventory-branch-message {
  min-height: 120px;
  display: grid;
  place-items: center;
  gap: 8px;
  padding: 22px;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  background: #f8fbff;
  color: #64748b;
  text-align: center;
}

.inventory-branch-message strong {
  color: #1f2937;
  font-size: 14px;
  font-weight: 1000;
}

.inventory-branch-message span {
  max-width: 520px;
  font-size: 12px;
  font-weight: 800;
  line-height: 1.9;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid #e7edf5;
  border-radius: 8px;
  background: #fff;
}

table {
  width: 100%;
  min-width: 1320px;
  border-collapse: collapse;
  table-layout: fixed;
}

.name-col {
  width: 15%;
}

.tags-col {
  width: 20%;
}

.addons-col {
  width: 72px;
}

.money-col {
  width: 10%;
}

.min-col {
  width: 7%;
}

.stock-col {
  width: 9%;
}

.followup-col {
  width: 8%;
}

.commission-col {
  width: 13%;
}

.active-col {
  width: 58px;
}

.booking-col {
  width: 116px;
}

.action-col {
  width: 44px;
}

.booking-table-cell {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 5px 6px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  background: #f8fafc;
  color: #64748b;
  cursor: pointer;
  text-align: right;
}

.booking-table-cell:hover { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
.booking-table-cell.enabled { border-color: #bbf7d0; background: #f0fdf4; color: #15803d; }
.booking-table-icon { width: 22px; height: 22px; display: grid; place-items: center; flex: 0 0 22px; border-radius: 7px; background: #e2e8f0; font-size: 12px; font-weight: 900; }
.booking-table-cell.enabled .booking-table-icon { background: #22c55e; color: #fff; }
.booking-table-cell b, .booking-table-cell small { display: block; white-space: nowrap; }
.booking-table-cell b { font-size: 10px; }
.booking-table-cell small { margin-top: 2px; font-size: 8px; color: inherit; opacity: .75; }

th,
td {
  border-bottom: 1px solid #e8eef6;
  padding: 10px 8px;
  text-align: center;
  vertical-align: middle;
}

th {
  color: #415066;
  font-size: 12px;
  font-weight: 800;
  background: #f6f9fd;
  white-space: nowrap;
}

tbody tr {
  cursor: pointer;
  transition: background .16s ease;
}

tbody tr:hover td {
  background: #fafcff;
}

tr.selected td {
  background: #edf6ff;
}

input,
select {
  font-family: inherit !important;
  width: 100%;
  min-height: 34px;
  padding: 6px 9px;
  font-size: 12px;
  text-align: center;
  border: 1px solid #cbd8e8;
  border-radius: 6px;
  background: #fff;
  box-sizing: border-box;
  color: #111827;
  outline: none;
  transition: border-color .15s ease, box-shadow .15s ease;
}

td:first-child input {
  text-align: center;
}

input:focus,
select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

.service-tags-editor {
  min-height: 54px;
  display: flex;
  justify-content: center;
  align-content: flex-start;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;
  padding: 7px;
  border: 1px solid #d8e3f3;
  border-radius: 12px;
  background: linear-gradient(145deg, #fbfdff, #f1f6ff);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
  transition: border-color .18s ease, box-shadow .18s ease;
}

.service-tags-editor:focus-within{border-color:#7aa7f8;box-shadow:0 0 0 3px rgba(37,99,235,.10),inset 0 1px 0 #fff}
.service-tags-editor span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  padding: 5px 7px 5px 9px;
  border-radius: 999px;
  border:1px solid #d9e7ff;
  background: linear-gradient(135deg,#fff,#eaf3ff);
  color: #1e4fbf;
  font-size: 10px;
  font-weight: 900;
  white-space: nowrap;
  box-shadow:0 2px 5px rgba(37,99,235,.08);
}

.service-tags-editor > span button {
  width: 18px;
  height: 18px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: #d7e7ff;
  color: #2563eb;
  cursor: pointer;
  font-family: inherit;
  line-height: 1;
}
.service-tags-editor > span button:hover{background:#fee2e2;color:#dc2626}

.service-tags-editor input {
  flex: 1 1 86px;
  min-width: 80px;
  min-height: 30px;
  padding: 0 9px;
  border: 1px dashed #b8cbea;
  border-radius: 8px;
  background:rgba(255,255,255,.82);
  color:#475569;
  font-size:10px;
  box-shadow: none;
}

.service-tags-editor input:focus {
  box-shadow: none;
}

.service-tag-picker {
  position: relative;
  flex: 1 1 154px;
  min-width: 140px;
}

.service-tag-picker input {
  width: 100%;
  min-width: 0;
  text-align: right;
}

.service-tag-popover-backdrop {
  position: fixed;
  z-index: 1000;
  inset: 0;
  background: transparent;
}

.service-tag-popover {
  position: fixed;
  z-index: 1001;
  width: 320px;
  max-width: calc(100vw - 24px);
  max-height: 240px;
  overflow: auto;
  padding: 7px;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 20px 48px rgba(15, 23, 42, .2);
}

.service-tag-popover button {
  width: 100%;
  min-height: 34px;
  padding: 7px 10px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: #334155;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 900;
  text-align: right;
}

.service-tag-popover button:hover {
  background: #eff6ff;
  color: #1d4ed8;
}

.service-tag-popover small {
  display: block;
  padding: 10px;
  color: #64748b;
  font-size: 11px;
  line-height: 1.8;
  text-align: center;
}

.check {
  width: 18px;
  min-height: 18px;
  accent-color: #2563eb;
}

.commission-chip {
  font-family: inherit !important;
  width: 100%;
  min-height: 38px;
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  background: #eff6ff;
  color: #1d4ed8;
  cursor: pointer;
  font-weight: 900;
  font-size: 11px;
  transition: background .15s ease, border-color .15s ease, transform .12s ease;
}

.commission-chip small,
.commission-chip strong {
  display: block;
}

.commission-chip small {
  margin-bottom: 2px;
  color: #64748b;
  font-size: 8px;
  font-weight: 800;
}

.commission-chip strong {
  color: #1d4ed8;
  font-size: 11px;
}

.bulk-commission-bar {
  display: grid;
  grid-template-columns: minmax(170px, 1.15fr) 150px minmax(150px, .95fr) 110px 110px auto;
  align-items: center;
  gap: 8px;
  margin: 12px 0;
  padding: 10px;
  border: 1px solid #dbe7f5;
  border-radius: 10px;
  background: #f8fbff;
}

.bulk-commission-copy {
  display: grid;
  gap: 2px;
  min-width: 0;
  text-align: right;
}

.bulk-commission-copy strong {
  color: #172033;
  font-size: 13px;
  font-weight: 900;
}

.bulk-commission-copy span {
  overflow: hidden;
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bulk-commission-bar select,
.bulk-commission-bar input {
  min-height: 36px;
}

.bulk-commission-bar .text-btn {
  height: 36px;
  white-space: nowrap;
}

.bulk-commission-bar > select:nth-of-type(2) {
  grid-column: auto;
}

.commission-chip:hover {
  background: #dbeafe;
  border-color: #93c5fd;
}

.commission-form {
  display: grid;
  grid-template-columns: 90px minmax(0, 1fr);
  gap: 8px;
  direction: rtl;
}

.commission-form select:first-child {
  grid-column: 1 / -1;
  text-align: right;
}

.commission-form select:nth-of-type(2) {
  grid-column: 1;
}

.commission-form input {
  grid-column: 2;
}

.commission-form .text-btn {
  grid-column: 1 / -1;
}

.commission-panel {
  text-align: right;
}

.commission-panel input,
.commission-panel select {
  text-align: right;
  font-size: 12px;
  font-weight: 700;
}

.default-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
  border: 1px solid #e0e8f3;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 14px;
}

.default-box > div {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.default-box span {
  color: #66758d;
  font-size: 12px;
  font-weight: 800;
}

.default-box strong {
  color: #0f172a;
  font-size: 15px;
}

.edit-default-btn {
  min-height: 34px;
  padding: 0 11px;
  border: 1px solid #93c5fd;
  border-radius: 7px;
  background: #eff6ff;
  color: #1d4ed8;
  cursor: pointer;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
}

.edit-default-btn:hover {
  border-color: #60a5fa;
  background: #dbeafe;
}

.default-help {
  margin: -5px 0 14px;
  padding: 8px 10px;
  border-right: 3px solid #60a5fa;
  border-radius: 5px;
  background: #f8fbff;
  color: #64748b;
  font-size: 11px;
}

.commission-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}

.commission-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto 30px;
  align-items: center;
  gap: 10px;
  border: 1px solid #e5ecf5;
  border-radius: 8px;
  padding: 11px;
  background: #fbfdff;
  text-align: right;
}

.commission-item div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.commission-item span,
.empty-state,
.empty-cell {
  color: #64748b;
  font-size: 12px;
  line-height: 1.8;
}

.commission-item strong {
  color: #1f2937;
  font-size: 12px;
  font-weight: 900;
}

.commission-item b {
  color: #2563eb;
  background: #eff6ff;
  border-radius: 999px;
  padding: 5px 9px;
  font-size: 12px;
  white-space: nowrap;
}

.commission-item button,
.row-remove {
  border: 0;
  background: #fee2e2;
  color: #dc2626;
  border-radius: 6px;
  width: 28px;
  height: 28px;
  cursor: pointer;
}

.icon-btn,
.text-btn {
  border: 0;
  border-radius: 7px;
  min-height: 34px;
  cursor: pointer;
  font-weight: 800;
  transition: transform .12s ease, box-shadow .16s ease, background .16s ease;
}

.icon-btn {
  width: 34px;
  font-size: 17px;
}

.text-btn {
  padding: 0 12px;
}

.primary {
  background: #2563eb;
  color: #fff;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .24);
}

.primary:hover {
  background: #1d4ed8;
}

.ghost {
  background: #f1f5f9;
  color: #475569;
}

.ghost:hover {
  background: #e2e8f0;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
  background: rgba(15, 23, 42, .34);
  backdrop-filter: blur(3px);
}

.commission-modal {
  width: min(760px, 100%);
  max-height: min(86vh, 780px);
  overflow: auto;
  background: #fff;
  border: 1px solid #dbe6f3;
  border-radius: 10px;
  box-shadow: 0 28px 70px rgba(15, 23, 42, .24);
  padding: 18px;
  direction: rtl;
  text-align: right;
}

.commission-level-tabs {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 7px;
  margin-top: 14px;
  padding: 6px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
}

.commission-level-tabs button {
  min-height: 36px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: #64748b;
  font-family: inherit;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.commission-level-tabs button.active {
  background: #2563eb;
  color: #fff;
  box-shadow: 0 8px 18px rgba(37, 99, 235, .18);
}

.commission-level-tabs button:disabled {
  cursor: not-allowed;
  opacity: .45;
}

.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e7edf5;
}

.modal-close {
  font-family: inherit !important;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: #f1f5f9;
  color: #64748b;
  cursor: pointer;
  font-size: 20px;
  line-height: 34px;
}

.modal-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}

.modal-grid label {
  display: flex;
  flex-direction: column;
  gap: 7px;
  color: #475569;
  font-size: 12px;
  font-weight: 900;
}

.modal-grid input,
.modal-grid select {
  text-align: right;
}

.modal-preview {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 14px;
  padding: 12px;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #eff6ff;
}

.modal-preview span {
  color: #475569;
  font-size: 12px;
  font-weight: 800;
}

.modal-preview strong {
  color: #1d4ed8;
  font-size: 16px;
  font-weight: 900;
}

.person-commission-box {
  margin-top: 14px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fbfdff;
}

.person-commission-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.person-commission-head strong {
  color: #172033;
  font-size: 13px;
  font-weight: 900;
}

.person-commission-head span {
  min-width: 0;
  overflow: hidden;
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.commission-list.compact {
  max-height: 170px;
  overflow: auto;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
}

.delete-section-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
  min-height: 40px;
  margin-top: 16px;
  border: 1px solid #fecaca;
  border-radius: 7px;
  background: #fff1f2;
  color: #dc2626;
  cursor: pointer;
  font-size: 12px;
  font-family: inherit;
  font-weight: 900;
  transition: background .15s ease, border-color .15s ease, transform .12s ease;
}

.delete-section-btn:hover {
  border-color: #fca5a5;
  background: #fee2e2;
}

.delete-section-btn span {
  font-size: 18px;
  line-height: 1;
}

.stock-low {
  background: #fff7ed;
}

.stock-zero {
  background: #fee2e2;
  font-weight: 800;
}

.chart-box {
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-x: auto;
  padding: 8px 2px 2px;
}

.bar-row {
  display: grid;
  grid-template-columns: 190px minmax(220px, 1fr);
  align-items: center;
  gap: 14px;
  padding: 10px;
  border: 1px solid #edf2f7;
  border-radius: 14px;
  background: #fbfdff;
}

.bar-label {
  color: #475569;
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bar-wrapper {
  background: #eef3f9;
  border-radius: 999px;
  min-height: 34px;
  overflow: hidden;
}

.bar {
  height: 34px;
  line-height: 34px;
  background: linear-gradient(90deg, #16a34a, #22c55e);
  color: #fff;
  text-align: center;
  border-radius: 999px;
  min-width: 34px;
  font-weight: 800;
  box-shadow: inset 0 -8px 16px rgba(15, 23, 42, .12);
}

.bar.stock-low,
.bar.yellow {
  background: #facc15;
  color: #1f2937;
}

.bar.stock-zero,
.bar.red {
  background: #dc2626;
  color: #fff;
}

.booking-settings-modal { max-width: 820px; width: min(92vw, 820px); max-height: 88vh; overflow: auto; padding: 0; border-radius: 18px; font-size: 12px; }
.booking-settings-btn { border:1px solid #bfdbfe; border-radius:7px; min-height:28px; padding:0 9px; background:#eff6ff; color:#1d4ed8; font-size:11px; font-weight:800; cursor:pointer; white-space:nowrap; }
.booking-settings-btn:hover { background:#dbeafe; }
.structure-title-wrap { display:flex; align-items:center; justify-content:flex-start; gap:8px; }
.inventory-structure-head { position:relative; border-bottom:2px solid color-mix(in srgb, var(--section-accent, #2563eb) 25%, #e2e8f0); }
.tree-node { border-right:3px solid transparent; }.tree-node:hover, .tree-node.active { border-right-color:var(--node-accent, #2563eb); }
.tree-color-picker { position:relative; display:flex; align-items:center; flex:0 0 22px; margin-right:1px; }.tree-color-btn { width:22px; height:22px; display:grid; place-items:center; padding:0; border:0; border-radius:6px; background:transparent; cursor:pointer; }.tree-color-btn span { width:10px; height:10px; border-radius:50%; box-shadow:0 0 0 1px #cbd5e1; }.tree-color-btn:hover { background:#eef2f7; }.tree-color-menu { top:27px; right:auto; left:-4px; width:190px; padding:8px; }
.section-color-picker { position:relative; }
.section-color-btn { width:27px; height:27px; display:grid; place-items:center; padding:0; border:1px solid #dbe4ef; border-radius:8px; background:#fff; cursor:pointer; box-shadow:0 2px 7px #1e293b0d; }
.section-color-btn span { width:14px; height:14px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 0 1px #cbd5e1; }
.section-color-menu { position:absolute; top:34px; right:0; z-index:40; display:flex; align-items:center; flex-wrap:wrap; gap:6px; width:156px; padding:9px; border:1px solid #e2e8f0; border-radius:11px; background:#fff; box-shadow:0 12px 28px #0f172a20; }
.section-color-swatch { width:21px; height:21px; padding:0; border:2px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #cbd5e1; cursor:pointer; }
.section-color-custom { width:21px; height:21px; display:grid; place-items:center; overflow:hidden; border:1px dashed #94a3b8; border-radius:50%; color:#64748b; cursor:pointer; font-size:15px; }.section-color-custom input { position:absolute; width:1px; height:1px; opacity:0; }
.section-color-reset { width:100%; margin-top:3px; padding:4px; border:0; border-top:1px solid #eef2f7; background:transparent; color:#64748b; cursor:pointer; font-family:inherit; font-size:10px; }
.booking-wizard .text-btn { min-height:30px; padding:0 10px; border-radius:8px; font-size:11px; box-shadow:none; }
.booking-hero { display:flex; align-items:center; gap:11px; padding:16px 20px; color:#fff; background:linear-gradient(135deg,#1e3a8a,#2563eb 55%,#38bdf8); }
.booking-hero h3 { margin:0 0 3px; font-size:16px; color:#fff !important; }.booking-hero p { margin:0; opacity:1; color:#eaf4ff !important; font-size:11px; }.booking-hero p b { color:#fff !important; }.booking-hero .modal-close { color:#fff !important; margin-right:auto; width:28px; height:28px; font-size:19px; line-height:25px; border:1px solid rgba(255,255,255,.65); background:rgba(255,255,255,.12) !important; }.booking-hero .modal-close:hover { background:rgba(255,255,255,.25) !important; }
.booking-disable-btn { border:1px solid rgba(255,255,255,.45); border-radius:7px; padding:5px 9px; color:#fff; background:rgba(127,29,29,.28); cursor:pointer; font-family:inherit; font-size:10px; font-weight:800; }.booking-disable-btn:hover { background:rgba(127,29,29,.5); }
.booking-hero-icon { width:36px; height:36px; display:grid; place-items:center; border-radius:11px; background:rgba(255,255,255,.18); font-size:20px; }
.booking-status-pill { display:inline-block; margin-right:8px; padding:3px 9px; border-radius:20px; background:#fee2e2; color:#b91c1c; font-size:11px; }.booking-status-pill.on { background:#dcfce7; color:#15803d; }
.booking-off-state { margin:28px auto 20px; max-width:420px; text-align:center; }.booking-off-art { width:58px; height:58px; margin:0 auto 12px; display:grid; place-items:center; border-radius:18px; background:#eff6ff; color:#2563eb; font-size:31px; }.booking-off-state h4 { margin:0 0 6px; font-size:15px; color:#1e293b; }.booking-off-state p { color:#64748b; line-height:1.8; margin-bottom:17px; font-size:11px; }
.booking-primary-btn { border:0; border-radius:9px; padding:9px 15px; color:#fff; background:linear-gradient(135deg,#2563eb,#1d4ed8); cursor:pointer; font-size:12px; font-weight:800; box-shadow:0 5px 12px #2563eb2b; }.booking-primary-btn:disabled { opacity:.6; cursor:not-allowed; }
.booking-steps { display:grid; grid-template-columns:repeat(4,1fr); gap:2px; padding:10px 20px 7px; border-bottom:1px solid #e8eef7; }.booking-steps button { border:0; background:transparent; color:#94a3b8; padding:4px; cursor:pointer; }.booking-steps button span { width:25px; height:25px; display:grid; place-items:center; margin:auto; border-radius:50%; background:#f1f5f9; font-size:12px; }.booking-steps button small { display:block; margin-top:4px; font-size:10px; }.booking-steps button.active { color:#2563eb; font-weight:800; }.booking-steps button.active span,.booking-steps button.done span { color:#fff; background:#2563eb; }
.booking-pane { padding:16px 20px 6px; min-height:250px; }.booking-pane-title { display:flex; justify-content:space-between; align-items:start; margin-bottom:14px; }.booking-pane-title h4 { margin:0 0 4px; font-size:14px; color:#1e293b; }.booking-pane-title p { margin:0; color:#64748b; font-size:11px; }.booking-number { display:grid; place-items:center; width:27px; height:27px; border-radius:9px; color:#2563eb; background:#eff6ff; font-weight:900; }
.booking-card-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }.booking-card-grid label { display:flex; flex-direction:column; gap:5px; color:#475569; font-size:11px; font-weight:700; }.booking-card-grid input,.booking-card-grid select,.schedule-resource select,.availability-card input { min-height:34px; box-sizing:border-box; border:1px solid #dbe4ef; border-radius:8px; padding:7px 9px; background:#fff; font-size:11px; }.booking-card-grid small { color:#94a3b8; font-weight:500; }.booking-toggle { flex-direction:row!important; align-items:center; gap:8px; padding:10px; border:1px solid #e6edf5; border-radius:10px; background:#fbfdff; }.booking-toggle input,.resource-active input { width:16px!important; height:16px!important; min-height:16px!important; margin:0!important; accent-color:#2563eb; }.booking-toggle span { display:flex; flex-direction:column; gap:3px; }.booking-toggle.wide { grid-column:1/-1; }.booking-info-box { margin-top:13px; padding:10px 12px; border-radius:9px; color:#1d4ed8; background:#eff6ff; font-size:11px; }
.booking-online-pane .booking-card-grid { gap:12px; }.booking-online-pane .booking-toggle { min-height:55px; box-sizing:border-box; }.booking-field-card { min-height:79px; box-sizing:border-box; padding:10px 11px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; }.booking-field-card > span { color:#334155; font-size:11px; font-weight:800; }.booking-field-card small { display:block; min-height:25px; margin-top:3px; line-height:1.6; }.booking-field-card input { width:100%; margin-top:5px; }
.booking-resource-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }.booking-resource-card { padding:11px; border:1px solid #e4ebf4; border-radius:11px; background:#fff; box-shadow:0 3px 10px #1e293b08; }.resource-card-top { display:flex; align-items:center; gap:6px; margin-bottom:8px; }.resource-avatar { width:25px; height:25px; display:grid; place-items:center; border-radius:8px; background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:900; }.resource-card-top select { flex:1; border:0; font-size:11px; font-weight:800; color:#334155; background:transparent; }.booking-resource-card > select { width:100%; min-height:32px; border:1px solid #dbe4ef; border-radius:8px; padding:7px; font-size:11px; }.resource-remove { border:0; background:transparent; color:#ef4444; cursor:pointer; font-size:10px; }.resource-active { display:block; margin-top:7px; color:#64748b; font-size:10px; }.add-resource-card { min-height:105px; border:1px dashed #93c5fd; border-radius:11px; color:#2563eb; background:#f8fbff; cursor:pointer; font-size:11px; font-weight:800; }
.schedule-resource { margin-bottom:10px; padding:10px; border:1px solid #e4ebf4; border-radius:11px; }.schedule-resource-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:7px; font-size:11px; }.availability-card { display:grid; grid-template-columns:1.1fr 1fr auto 1fr auto; gap:6px; align-items:center; padding:7px; margin-top:6px; border-radius:8px; background:#f8fafc; }.break-list { grid-column:1/-1; display:flex; flex-wrap:wrap; gap:5px; padding-top:3px; }.break-chip { display:flex; align-items:center; gap:3px; padding:4px 5px; border-radius:6px; color:#92400e; background:#fef3c7; font-size:10px; }.break-chip input { min-height:25px; padding:3px; border-color:#fcd34d; }.break-chip button { border:0; background:none; color:#b45309; cursor:pointer; }.break-add { border:0; background:transparent; color:#b45309; cursor:pointer; font-size:10px; }.empty-schedule { margin:6px 0 0; color:#94a3b8; font-size:10px; }
.booking-wizard-footer { display:flex; justify-content:space-between; align-items:center; padding:12px 20px 16px; border-top:1px solid #e8eef7; }.booking-wizard-footer > div { display:flex; gap:6px; }
.booking-settings-grid label, .booking-json-label { display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 700; color: #475569; }
.booking-settings-grid input, .booking-settings-grid select, .booking-json-label textarea, .booking-resource-row select { border: 1px solid #dbe4ef; border-radius: 10px; padding: 9px; background: #fff; }
.check-label { flex-direction: row !important; align-items: center; gap: 8px !important; padding: 10px; border: 1px solid #e5e7eb; border-radius: 10px; }
.booking-resources { margin-top: 16px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 12px; }
.booking-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.booking-resource-row { display: grid; grid-template-columns: 1fr 2fr auto auto; gap: 8px; align-items: center; margin-bottom: 8px; }
.booking-json-label { margin-top: 14px; }
.booking-json-label textarea { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; direction: ltr; text-align: left; }

@media (max-width: 1100px) {
  .inventory-page,
  .inventory-main {
    grid-template-columns: 1fr;
  }

  .inventory-view-switch {
    grid-template-columns: 1fr;
  }

  .inventory-tabs {
    grid-template-columns: 1fr;
  }

  .inventory-tree {
    grid-template-columns: 1fr;
  }

  .inventory-toolbar {
    grid-template-columns: 1fr;
  }

  .inventory-inline-tabs {
    width: max-content;
    box-sizing: border-box;
  }

  .inventory-inline-tabs button {
    flex: 0 0 40px;
  }

  .inventory-save-actions {
    width: 100%;
  }

  .save-status,
  .save-inventory-btn {
    flex: 1;
  }

  .commission-panel,
  .table-section,
  .chart-section {
    grid-column: 1;
    grid-row: auto;
    position: static;
  }

  .booking-settings-grid { grid-template-columns: 1fr; }
  .booking-resource-row { grid-template-columns: 1fr; }
  .booking-card-grid, .booking-resource-cards { grid-template-columns: 1fr; }
  .availability-card { grid-template-columns: 1fr 1fr; }
  .booking-pane { padding-inline: 16px; }
  .booking-wizard-footer { padding-inline: 16px; }
}
</style>
