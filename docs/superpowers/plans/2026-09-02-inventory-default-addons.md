# Inventory Default Addons Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let each inventory item define default inventory addons and automatically include them in appointment booking.

**Architecture:** A tenant-scoped pivot table stores directed parent-item/addon-item links. `InventoryController` rebuilds those links on the existing bulk save, `anbar.vue` edits them in a modal, and `Time.vue` inserts them in the existing appointment `services[].addons` array, whose prices are already calculated server-side by inventory id.

**Tech Stack:** Laravel, Eloquent, PHPUnit feature tests, Vue 3, Axios, Vue Multiselect.

**Spec:** `docs/superpowers/specs/2026-09-02-inventory-default-addons-design.md`

## Global Constraints

- Store links only in tenant databases.
- Prevent self and duplicate addon links.
- Select only active inventory items in new UI selections.
- Preserve existing appointment addon JSON for historical bookings.

---

### Task 1: Persist default-addon links

**Files:**
- Create: `backend/database/migrations/tenant/2026_09_02_120000_create_inventory_addons_table.php`
- Create: `backend/app/Models/InventoryAddon.php`
- Modify: `backend/app/Models/Inventory.php`, `backend/app/Http/Controllers/InventoryController.php`
- Test: `backend/tests/Feature/InventoryDefaultAddonsTest.php`

**Interfaces:** `Inventory::defaultAddons(): BelongsToMany`; `items.*.default_addon_ids: integer[]`; GET `/api/inventory` exposes `default_addons`.

- [ ] Write a test posting a parent item and active addon id, then assert the unique pivot row and eager-loaded API relation.
- [ ] Run `php artisan test --filter=InventoryDefaultAddonsTest`; expect failure because no pivot exists.
- [ ] Create the migration with foreign keys, cascades, timestamps, and `unique(['inventory_id', 'addon_inventory_id'])`; implement `defaultAddons()` using `belongsToMany(self::class, 'inventory_addons', 'inventory_id', 'addon_inventory_id')->withTimestamps()`.
- [ ] In the inventory bulk save, delete pivot rows before the current wholesale deletion; create items first, map client ids to their new ids, filter zero/self/duplicate addon ids, and call `$inventory->defaultAddons()->sync($ids)`.
- [ ] Eager load `defaultAddons` in `index()` and rerun the focused test to PASS.
- [ ] Commit: `git add backend/database/migrations/tenant/2026_09_02_120000_create_inventory_addons_table.php backend/app/Models/InventoryAddon.php backend/app/Models/Inventory.php backend/app/Http/Controllers/InventoryController.php backend/tests/Feature/InventoryDefaultAddonsTest.php && git commit -m "feat: persist inventory default addons"`.

### Task 2: Edit default addons in a compact inventory modal

**Files:**
- Modify: `Frontend/src/components/anbar.vue`

**Interfaces:** Client rows use `defaultAddonIds: number[]`; save sends `default_addon_ids`; modal methods are `openDefaultAddonsModal`, `toggleDefaultAddon`, and `saveDefaultAddonsModal`.

- [ ] Add a visual baseline: current inventory rows have no addon button and no picker modal.
- [ ] Normalize API `default_addons` to `defaultAddonIds`, and serialize it in `saveData()`.
- [ ] Add a «جانبی‌ها» column immediately after tags. Its count button opens a draft-only modal with searchable active inventory items, excluding the parent item; list name, amount, stock, selected state, and a delete action.
- [ ] Update colgroup, headers, and empty-state colspan. Do not modify chart data: addon products remain normal inventory rows and already appear in the chart.
- [ ] Run `npm run build` from `Frontend`, manually verify select/remove/cancel/save/reload/chart, then commit `Frontend/src/components/anbar.vue` as `feat: add inventory addon picker`.

### Task 3: Auto-apply and edit default addons in booking

**Files:**
- Modify: `Frontend/src/components/Time.vue`, `backend/app/Http/Controllers/AppointmentController.php`
- Create: `backend/tests/Feature/AppointmentDefaultAddonsTest.php`

**Interfaces:** Booking addon records stay `{name, inventory_id, cc, discount, adjustment_mode, surcharge_for_doctor_commission}`. `applyDefaultAddons(service)` inserts active linked items only once after service selection.

- [ ] Write a feature test with an addon id whose submitted name is fake and assert the appointment original amount uses that inventory item’s `amount × cc`.
- [ ] Run `php artisan test --filter=AppointmentDefaultAddonsTest`; adapt only required appointment fixture fields until it locks existing id-first pricing.
- [ ] Add `newAddonFromInventory()` and `applyDefaultAddons()` in `Time.vue`; resolve the selected service by `inventory_id`, skip inactive/duplicate ids, and invoke the helper in both normal and timeline service-name selection handlers.
- [ ] Replace both inline addon panels with one shared modal opened by the existing «جانبی» trigger. The modal supports multiple additions, quantity, price display, removal, and calls `updateRowAmounts(row)` after each edit.
- [ ] In `AppointmentController::normalizeServiceDiscounts`, permit id pricing only for existing active inventory; retain name fallback exclusively for legacy lines without ids.
- [ ] Run `php artisan test --filter=AppointmentDefaultAddonsTest` and `npm run build`; manually verify defaults appear once and totals change by `amount × quantity`.
- [ ] Commit `Time.vue`, `AppointmentController.php`, and the test as `feat: apply default addons during booking`.

### Task 4: Align the service button with referral mobile

**Files:**
- Modify: `Frontend/src/components/Time.vue`

- [ ] Locate the patient-services modal’s full-width «+ افزودن خدمت» control and referral-mobile input.
- [ ] Move the existing button beside the referral input, retaining all event handlers and disabled behavior.
- [ ] Add a scoped flex wrapper that wraps cleanly on narrow screens.
- [ ] Run `npm run build`, inspect the services modal at desktop width, then commit as `fix: align add service button with referral mobile`.

### Task 5: Final verification

**Files:**
- Verify: all files above

- [ ] Run `php artisan tenants:migrate` to apply the tenant pivot migration.
- [ ] Run `php artisan test --filter='InventoryDefaultAddonsTest|AppointmentDefaultAddonsTest'` and expect PASS.
- [ ] Run `npm run build` in `Frontend` and expect PASS.
- [ ] Run `git diff --check HEAD~4..HEAD` and confirm no whitespace errors or unrelated files.
