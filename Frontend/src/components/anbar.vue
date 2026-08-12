<template>
  <div class="inventory-page">
    <section v-if="false" class="inventory-view-switch">
      <div class="inventory-view-title">
        <span>مدیریت انبار</span>
        <h2>نمایش انبار را انتخاب کنید</h2>
        <p>برای کارهای روزمره از جدول استفاده کنید و برای بررسی سریع موجودی، نمای چارت را ببینید.</p>
      </div>

      <div class="inventory-tabs" role="tablist" aria-label="نوع نمایش انبار">
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
          <small>بررسی سریع موجودی بخش انتخاب‌شده</small>
        </button>
      </div>
    </section>

    <aside class="section-panel">
      <div class="inventory-structure-head">
        <button class="structure-add-root-btn" type="button" @click="addRootSection">
          <span>+</span>
          انبار جدید
        </button>
        <h3>ساختار انبار</h3>
      </div>

      <div class="inventory-tree">
        <div
          v-for="node in inventoryTreeNodes"
          :key="sectionKey(node.section)"
          class="tree-node"
          :class="{ active: activeTreeKey === sectionKey(node.section), root: node.level === 1, leaf: node.level === 2 }"
          :style="{ '--tree-depth': node.level - 1 }"
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
          <input v-model="node.section.name" :placeholder="treePlaceholder(node.level)" @click.stop @focus="selectTreeNode(node.section)">
          <span class="tree-dot" aria-hidden="true"></span>
          <span class="tree-count">{{ treeNodeCount(node.section).toLocaleString('fa-IR') }}</span>
          <span class="tree-spacer" aria-hidden="true"></span>
          <button
            type="button"
            class="tree-add-btn"
            :disabled="node.level >= 2"
            :title="node.level >= 2 ? 'زیرشاخه سطح آخر است' : 'افزودن زیرشاخه'"
            aria-label="افزودن زیرشاخه"
            @click.stop="addChildSection(sectionKey(node.section), node.level + 1)"
          >+</button>
          <button type="button" class="tree-more-btn" title="حذف" aria-label="حذف" @click.stop="removeSectionNode(node.section)">⋮</button>
        </div>

        <small v-if="!inventoryTreeNodes.length" class="tree-empty">اولین انبار را بسازید</small>
      </div>

      <button v-if="false" class="delete-section-btn" type="button" @click="removeActiveSection">
        <span aria-hidden="true">×</span>
        حذف گروه انتخاب‌شده
      </button>
    </aside>

    <main class="inventory-main" :class="{ 'chart-mode': inventoryView === 'chart' }">
      <section class="inventory-toolbar">
        <div class="inventory-search">
          <span class="search-icon" aria-hidden="true">⌕</span>
          <input
            v-model.trim="searchQuery"
            type="search"
            placeholder="جست‌وجو در کل انبار؛ نام کالا، بخش، مبلغ، موجودی یا معرف..."
            aria-label="جست‌وجو در کل انبار"
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

        <div class="inventory-inline-tabs" role="tablist" aria-label="نوع نمایش انبار">
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
          <span class="save-status" :class="saveState">
            {{ saveStatusText }}
          </span>
          <button
            class="save-inventory-btn"
            type="button"
            :disabled="isSaving || isFetching || !hasUnsavedChanges"
            @click="saveData(true)"
          >
            {{ isSaving ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
          </button>
        </div>
      </section>

      <section v-if="inventoryView === 'table'" class="table-section">
        <div v-if="false" class="inventory-search">
          <span class="search-icon" aria-hidden="true">⌕</span>
          <input
            v-model.trim="searchQuery"
            type="search"
            placeholder="جست‌وجو در کل انبار؛ نام کالا، بخش، مبلغ، موجودی یا معرف..."
            aria-label="جست‌وجو در کل انبار"
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

        <div v-if="!needsCompletedHierarchy || searchQuery" class="panel-head">
          <div>
            <h3>{{ searchQuery ? 'نتایج جست‌وجو در کل انبار' : inventoryTableTitle }}</h3>
            <p>{{ searchQuery ? 'نتایج همه بخش‌ها نمایش داده می‌شوند.' : inventoryTableSubtitle }}</p>
          </div>
          <div class="panel-actions">
            <button class="text-btn ghost" type="button" @click="openCommissionModal(selectedRow)">
              ثبت پورسانت
            </button>
            <button
              class="text-btn primary"
              type="button"
              :disabled="needsCompletedHierarchy"
              :title="needsCompletedHierarchy ? 'ابتدا یک زیرشاخه انتخاب کنید' : 'افزودن ردیف جدید'"
              @click="addRow"
            >+ ردیف جدید</button>
          </div>
        </div>

        <div v-if="needsCompletedHierarchy && !searchQuery" class="inventory-branch-message">
          <strong>لطفا زیرشاخه را انتخاب کنید</strong>
          <span>برای نمایش یا ثبت آیتم‌ها، یک زیرشاخه از انبار را انتخاب کنید.</span>
        </div>

        <div v-else class="table-wrap">
          <table>
            <colgroup>
              <col class="name-col">
              <col class="tags-col">
              <col>
              <col>
              <col class="small-col">
              <col class="small-col">
              <col class="small-col">
              <col class="commission-col">
              <col class="active-col">
              <col class="action-col">
            </colgroup>
            <thead>
              <tr>
                <th>نام کالا / خدمت</th>
                <th>تگ‌های خدمات</th>
                <th>هزینه</th>
                <th>قیمت</th>
                <th>تعداد</th>
                <th>حداقل</th>
                <th>موجودی</th>
                <th>پورسانت کلی</th>
                <th>فعال</th>
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
                  <input v-model="row.name" type="text" placeholder="مثلا سرنگ">
                  <small v-if="searchQuery" class="row-section-name">{{ sectionNameForRow(row) }}</small>
                </td>
                <td>
                  <div class="service-tags-editor" @click.stop>
                    <span v-for="(tag, tagIndex) in row.serviceTags" :key="`${row.localId}-tag-${tagIndex}`">
                      {{ tag }}
                      <button type="button" title="حذف تگ" @click.stop="removeServiceTag(row, tagIndex)">×</button>
                    </span>
                    <input
                      v-model="row.tagDraft"
                      type="text"
                      placeholder="تگ + Enter"
                      @keydown.enter.prevent="addServiceTag(row)"
                      @keydown.tab="addServiceTag(row)"
                      @blur="addServiceTag(row)"
                    >
                  </div>
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
                <td><input v-model.number="row.count" type="number"></td>
                <td><input v-model.number="row.minStock" type="number" min="0"></td>
                <td>
                  <input
                    v-model.number="row.stock"
                    type="number"
                    :class="stockClass(row.stock, row.minStock)"
                  >
                </td>
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
                  <button class="row-remove" type="button" @click.stop="removeRow(row, index)">×</button>
                </td>
              </tr>

              <tr v-if="displayedRows.length === 0">
                <td colspan="10" class="empty-cell">
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
            <h3>نمای چارت موجودی {{ activeSectionName }}</h3>
            <p>فقط آیتم‌های فعال همین بخش نمایش داده می‌شوند؛ زرد یعنی نزدیک به حداقل و قرمز یعنی موجودی صفر.</p>
          </div>
          <button class="text-btn primary" type="button" @click="inventoryView = 'table'">
            رفتن به جدول
          </button>
          <h3>نمای موجودی بخش انتخاب‌شده</h3>
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

        <div class="commission-level-tabs">
          <button type="button" :class="{ active: bulkCommission.target === 'all' }" @click="bulkCommission.target = 'all'">کلی</button>
          <button type="button" :class="{ active: bulkCommission.target === 'section' }" @click="bulkCommission.target = 'section'">بخش</button>
          <button type="button" :class="{ active: bulkCommission.target === 'tag' }" @click="bulkCommission.target = 'tag'">زیر‌بخش</button>
          <button type="button" :class="{ active: bulkCommission.target === 'item' }" :disabled="!selectedRow" @click="bulkCommission.target = 'item'">آیتم</button>
        </div>

        <div class="modal-grid">
          <label v-if="bulkCommission.target === 'section'">
            بخش
            <select v-model="bulkCommission.sectionKey">
              <option v-for="section in sections" :key="sectionKey(section)" :value="sectionKey(section)">
                {{ section.name }}
              </option>
            </select>
          </label>

          <label v-if="bulkCommission.target === 'tag'">
            زیر‌بخش / تگ خدمات
            <select v-model="bulkCommission.tag">
              <option value="">انتخاب تگ</option>
              <option v-for="tag in allServiceTags" :key="tag" :value="tag">{{ tag }}</option>
            </select>
          </label>

          <label v-if="bulkCommission.target === 'item'">
            آیتم
            <select v-model="selectedRowLocalId">
              <option value="">انتخاب آیتم</option>
              <option v-for="row in rows" :key="row.localId" :value="row.localId">
                {{ row.name || 'آیتم بدون نام' }} - {{ sectionNameForRow(row) }}
              </option>
            </select>
          </label>

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

        <section v-if="selectedRow" class="person-commission-box">
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
          <button class="text-btn primary" type="button" :disabled="!commissionScopeRows.length" @click="saveDefaultCommissionModal">ثبت پورسانت</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "axios"

const API = "/api"
const INVENTORY_ZERO_NOTIFICATIONS_KEY = "inventory_zero_stock_notifs_v1"

export default {
  name: "InventoryTable",

  data() {
    return {
      sections: [],
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
      sectionIdRedirects: {},
      selectedRow: null,
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
    activeSectionName() {
      return this.activeSection?.name || "بخش انتخاب‌شده"
    },

    needsCompletedHierarchy() {
      return !this.searchQuery && !this.activeSectionKey
    },

    inventoryTableTitle() {
      return this.needsCompletedHierarchy ? "شاخه‌بندی کامل نشده" : `آیتم‌های ${this.activeSectionName}`
    },

    inventoryTableSubtitle() {
      return this.needsCompletedHierarchy
        ? "برای نمایش و ثبت آیتم، یک زیرشاخه از انبار را انتخاب کنید."
        : "کالاها و خدمات این بخش را همراه موجودی و پورسانت معرف مدیریت کنید."
    },

    inventoryEmptyMessage() {
      if (this.searchQuery) return "نتیجه‌ای برای این جست‌وجو در انبار پیدا نشد."
      if (this.needsCompletedHierarchy) return "لطفا شاخه‌بندی را کامل کنید."
      return "برای این بخش هنوز آیتمی ثبت نشده است."
    },

    rootSections() {
      return this.sections.filter(section => Number(section.level || 1) === 1)
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
      if (this.bulkCommission.target === "all") return "پورسانت کلی برای همه انبار"
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
          if (isNaN(stock)) row.stock = 0
        })
        this.queueSave()
      },
      deep: true
    }
  },

  mounted() {
    this.fetchData()
  },

  methods: {
    async fetchData(options = {}) {
      const previousSectionKey = this.activeSectionKey
      const previousSelectedName = this.selectedRow?.name || ""
      this.isFetching = true

      try {
        const [inventoryRes, contextRes] = await Promise.all([
          axios.get(`${API}/inventory`),
          axios.get(`${API}/inventory/context`)
        ])

        this.doctors = contextRes.data.doctors || []
        this.staff = contextRes.data.staff || []
        this.users = contextRes.data.users || []

        const normalizedSections = this.normalizeInventorySections(contextRes.data.sections || [])
        this.sectionIdRedirects = normalizedSections.redirects

        this.sections = normalizedSections.sections.map((section, index) => ({
          id: section.id,
          client_id: null,
          parent_id: section.parent_id || section.parentId || null,
          level: Number(section.level || 1),
          name: section.name,
          sort_order: section.sort_order ?? index
        }))

        if (!this.sections.length) {
          this.sections = this.defaultSections()
        }

        this.expandedSectionKeys = this.sections
          .filter(section => Number(section.level || 1) < 2)
          .map(section => this.sectionKey(section))

        const restoredSection = this.sections.find(section => this.sectionKey(section) === previousSectionKey)
        if (options.keepState && restoredSection) {
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

    normalizeInventorySections(sections = []) {
      const redirects = {}
      const byKey = new Map((sections || []).map(section => [String(section.id || section.client_id || ""), section]))
      const normalized = []

      ;(sections || []).forEach(section => {
        const key = String(section.id || section.client_id || "")
        const level = Number(section.level || 1)

        if (level <= 2) {
          normalized.push({
            ...section,
            level,
            parent_id: level === 1 ? null : section.parent_id || section.parentId || null,
          })
          return
        }

        const parent = byKey.get(String(section.parent_id || section.parentId || ""))
        if (key && parent) {
          redirects[key] = String(parent.id || parent.client_id || "")
        }
      })

      return { sections: normalized, redirects }
    },

    resolveSectionKey(sectionKey) {
      const key = String(sectionKey || "")
      return this.sectionIdRedirects[key] || key
    },

    firstSelectableSectionKey() {
      const section = this.sections.find(item => Number(item.level || 1) === 2) || this.sections[0]
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
        tagDraft: "",
        amount: Number(item.amount) || 0,
        price: Number(item.price) || 0,
        count: Number(item.count) || 0,
        stock: Number(item.stock) || 0,
        minStock: Number(item.min_stock ?? item.minStock ?? 5),
        active: item.active === undefined ? true : Boolean(item.active),
        sort_order: item.sort_order ?? index,
        defaultCommissionType: item.default_commission_type || "percent",
        defaultCommissionValue: Number(item.default_commission_value) || 0,
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
            sort_order: index
          })),
          items: this.rows.map((row, index) => ({
            id: row.id,
            section_id: row.section_id,
            name: row.name,
            service_tags: this.normalizedServiceTags(row.serviceTags),
            amount: row.amount,
            price: row.price,
            count: row.count,
            stock: row.stock,
            min_stock: row.minStock,
            active: row.active,
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
      const section = this.makeSection("انبار جدید", null, 1)
      this.sections.push(section)
      this.expandedSectionKeys.push(this.sectionKey(section))
      this.selectRoot(section)
    },

    addChildSection(parentKey, level) {
      if (!parentKey || Number(level) > 2) return
      const section = this.makeSection("زیرشاخه جدید", parentKey, level)
      this.sections.push(section)
      if (!this.expandedSectionKeys.includes(String(parentKey))) {
        this.expandedSectionKeys.push(String(parentKey))
      }
      this.selectSub(section)
    },

    selectRoot(section) {
      this.activeRootKey = this.sectionKey(section)
      this.activeTreeKey = this.activeRootKey
      if (!this.expandedSectionKeys.includes(this.activeRootKey)) this.expandedSectionKeys.push(this.activeRootKey)
      this.activeSubKey = ""
      this.activeSectionKey = ""
      this.selectedRow = null
    },

    selectSub(section) {
      this.activeSubKey = this.sectionKey(section)
      this.activeTreeKey = this.activeSubKey
      if (!this.expandedSectionKeys.includes(this.activeSubKey)) this.expandedSectionKeys.push(this.activeSubKey)
      this.activeSectionKey = this.activeSubKey
      this.selectedRow = this.activeSectionRows[0] || null
    },

    selectTreeNode(section) {
      const level = Number(section.level || 1)
      if (level === 1) {
        this.selectRoot(section)
        return
      }
      if (level === 2) {
        this.selectSub(section)
        return
      }
      this.selectSub(section)
    },

    selectFirstLeaf() {
      const leaf = this.sections
        .filter(section => Number(section.level || 1) === 2)
        .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))[0]
      if (leaf) {
        this.selectHierarchyForLeaf(leaf)
        return
      }
      const root = this.rootSections[0]
      if (root) this.selectRoot(root)
    },

    selectHierarchyForLeaf(leaf) {
      const root = this.sections.find(section => this.sectionKey(section) === String(leaf.parent_id || ""))
      this.activeRootKey = root ? this.sectionKey(root) : ""
      this.activeSubKey = this.sectionKey(leaf)
      this.activeSectionKey = this.sectionKey(leaf)
      this.activeTreeKey = this.activeSectionKey
      ;[this.activeRootKey].filter(Boolean).forEach(key => {
        if (!this.expandedSectionKeys.includes(key)) this.expandedSectionKeys.push(key)
      })
    },

    childSections(parentKey) {
      return this.sections
        .filter(section => String(section.parent_id || "") === String(parentKey || ""))
        .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))
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
      return Number(section.level || 1) === 2
        ? this.sectionItemCount(section)
        : this.childSections(this.sectionKey(section)).length
    },

    treePlaceholder(level) {
      if (Number(level) === 1) return "مثلا پوست و زیبایی"
      return "مثلا ژل یا بوتاکس"
    },

    removeSectionNode(section) {
      this.selectTreeNode(section)
      const key = this.sectionKey(section)
      if (this.childSections(key).length) {
        alert("این شاخه زیرشاخه دارد. ابتدا زیرشاخه‌های داخل آن را حذف کنید.")
        return
      }
      if (Number(section.level || 1) === 2 && this.rows.some(row => this.rowSectionKey(row) === key)) {
        alert(`این گروه دارای ${this.sectionItemCount(section)} آیتم است. ابتدا آیتم‌های داخل آن را حذف کنید.`)
        return
      }
      if (this.sections.length <= 1) {
        alert("حداقل یک بخش باید در انبار باقی بماند.")
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
        alert("حداقل یک بخش باید در انبار باقی بماند.")
        return
      }

      const index = this.sections.findIndex(section => this.sectionKey(section) === this.activeSectionKey)
      this.sections.splice(index, 1)
      this.selectFirstLeaf()
    },

    addRow() {
      if (!this.activeSectionKey) {
        alert("ابتدا یک زیرشاخه از انبار انتخاب کنید.")
        return
      }
      const row = {
        localId: `row-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        id: null,
        section_id: this.activeSectionKey,
        name: "",
        serviceTags: [],
        tagDraft: "",
        amount: 0,
        price: 0,
        count: 0,
        stock: 0,
        minStock: 5,
        active: true,
        sort_order: this.rows.length,
        defaultCommissionType: "percent",
        defaultCommissionValue: 0,
        commissions: []
      }

      this.rows.push(row)
      this.selectRow(row)
    },

    removeRow(row) {
      const index = this.rows.findIndex(item => item.localId === row.localId)
      if (index === -1) return
      this.rows.splice(index, 1)
      if (this.selectedRow?.localId === row.localId) {
        this.selectedRow = this.activeSectionRows[0] || null
      }
    },

    selectRow(row) {
      this.selectedRow = row
      this.commissionPersonKey = ""
      this.commissionDraft = { type: "percent", value: 0 }
    },

    normalizedServiceTags(tags) {
      return Array.from(new Set((tags || [])
        .flatMap(tag => String(tag || '').split(/[,،\n]+/u))
        .map(tag => tag.trim())
        .filter(Boolean)))
    },

    addServiceTag(row) {
      const tags = this.normalizedServiceTags([...(row.serviceTags || []), row.tagDraft])
      row.serviceTags = tags
      row.tagDraft = ""
      this.queueSave()
    },

    removeServiceTag(row, tagIndex) {
      row.serviceTags.splice(tagIndex, 1)
      this.queueSave()
    },

    openCommissionModal(row = null, target = "section") {
      if (row) this.selectRow(row)
      const nextTarget = target || (row ? "item" : "section")
      this.bulkCommission.target = nextTarget
      this.bulkCommission.sectionKey = this.activeSectionKey
      if (nextTarget !== "tag") this.bulkCommission.tag = ""
      this.defaultCommissionRow = row || this.selectedRow
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
      const parent = this.sections.find(item => this.sectionKey(item) === String(section.parent_id || ""))
      return [parent?.name, section.name].filter(Boolean).join(" / ")
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
  color: #94a3b8;
  font-size: 18px;
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
  min-width: 1080px;
  border-collapse: collapse;
  table-layout: fixed;
}

.name-col {
  width: 17%;
}

.tags-col {
  width: 22%;
}

.small-col {
  width: 8%;
}

.commission-col {
  width: 13%;
}

.active-col {
  width: 58px;
}

.action-col {
  width: 44px;
}

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
  text-align: right;
}

input:focus,
select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

.service-tags-editor {
  min-height: 40px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 5px;
  padding: 6px;
  border: 1px solid #cbd8e8;
  border-radius: 6px;
  background: #fff;
}

.service-tags-editor span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
  padding: 3px 7px;
  border-radius: 999px;
  background: #eef6ff;
  color: #1d4ed8;
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
}

.service-tags-editor button {
  width: 16px;
  height: 16px;
  display: grid;
  place-items: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: #dbeafe;
  color: #1d4ed8;
  cursor: pointer;
  font-family: inherit;
  line-height: 1;
}

.service-tags-editor input {
  flex: 1 1 86px;
  min-width: 80px;
  min-height: 24px;
  padding: 2px 4px;
  border: 0;
  box-shadow: none;
}

.service-tags-editor input:focus {
  box-shadow: none;
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
}
</style>
