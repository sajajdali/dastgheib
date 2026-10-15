<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\InventoryCommission;
use App\Models\InventoryAddon;
use App\Models\InventoryAddonDefinition;
use App\Models\InventoryMovement;
use App\Models\InventoryBookingResource;
use App\Models\InventorySection;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Inventory::with(['section', 'commissions', 'defaultAddons', 'addonDefinitions', 'bookingSetting', 'bookingResources.availabilities.breaks'])
                ->orderBy('section_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function context()
    {
        return response()->json([
            'sections' => InventorySection::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'doctors' => Doctor::query()->orderBy('name')->get(['id', 'name', 'available_days']),
            'staff' => Staff::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'mobile']),
            'service_tags' => app(HumanResourceController::class)->serviceTags(),
            'addons' => InventoryAddonDefinition::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeAddonDefinitions(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.save_version' => ['nullable', 'string'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['nullable'],
            'items.*.price' => ['nullable'],
            'items.*.stock' => ['nullable', 'integer', 'min:0'],
            'items.*.min_stock' => ['nullable', 'integer', 'min:0'],
            'items.*.active' => ['nullable', 'boolean'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer', 'distinct'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['items']) as $index => $item) {
                $record = ! empty($item['id'])
                    ? InventoryAddonDefinition::query()->lockForUpdate()->find($item['id'])
                    : null;
                if (! $record && ! empty($item['id'])) {
                    throw ValidationException::withMessages(['items' => 'یکی از جانبی‌ها دیگر وجود ندارد. صفحه را تازه‌سازی کنید.']);
                }
                if ($record && (empty($item['save_version']) || ! hash_equals($record->save_version, $item['save_version']))) {
                    throw ValidationException::withMessages(['items' => 'این جانبی در صفحهٔ دیگری تغییر کرده است. صفحه را تازه‌سازی کنید.']);
                }
                if (! $record) $record = new InventoryAddonDefinition();
                $record->fill([
                    'name' => trim($item['name']),
                    'amount' => $item['amount'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'stock' => isset($item['stock']) ? (int) $item['stock'] : 0,
                    'min_stock' => $item['min_stock'] ?? $item['minStock'] ?? 5,
                    'active' => $item['active'] ?? true,
                    'sort_order' => $item['sort_order'] ?? $index,
                ]);
                if (! $record->exists || $record->isDirty()) $record->save();
            }
            foreach ($data['deleted_ids'] ?? [] as $id) {
                $record = InventoryAddonDefinition::query()->lockForUpdate()->find($id);
                if (! $record) continue;
                if ($record->inventories()->exists()) {
                    throw ValidationException::withMessages(['items' => "جانبی «{$record->name}» در خدمات استفاده می‌شود؛ ابتدا ارتباط آن را بردارید."]);
                }
                $record->delete();
            }
        });

        return response()->json([
            'message' => 'جانبی‌ها ذخیره شدند.',
            'addons' => InventoryAddonDefinition::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.save_version' => ['nullable', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'integer', 'distinct'],
            'sections.*.save_version' => ['nullable', 'string'],
            'deleted_item_ids' => ['nullable', 'array'],
            'deleted_item_ids.*' => ['integer', 'distinct'],
            'deleted_section_ids' => ['nullable', 'array'],
            'deleted_section_ids.*' => ['integer', 'distinct'],
            // موجودی منفی نشان‌دهنده کمبود ثبت‌شده توسط خدمات انجام‌شده است.
            'items.*.stock' => ['nullable', 'integer'],
        ]);
        $items = $request->input('items', []);
        $sections = $request->input('sections', []);

        $this->ensureItemsBelongToSubsections($items, $sections);

        $savedIds = DB::transaction(function () use ($items, $sections, $request) {
            $sectionIdMap = [];
            foreach (array_values($sections) as $index => $section) {
                if (empty($section['id']) && empty($section['name'])) continue;
                $record = ! empty($section['id'])
                    ? InventorySection::query()->lockForUpdate()->find($section['id'])
                    : null;
                if (! $record && ! empty($section['id'])) {
                    throw ValidationException::withMessages(['sections' => 'یکی از بخش‌ها دیگر وجود ندارد. صفحه را تازه‌سازی کنید.']);
                }
                if ($record && (empty($section['save_version']) || ! hash_equals($record->save_version, $section['save_version']))) {
                    throw ValidationException::withMessages(['sections' => 'این بخش در صفحهٔ دیگری تغییر کرده است. صفحه را تازه‌سازی کنید.']);
                }
                if (! $record) $record = new InventorySection();
                $sectionAttributes = [
                    'name' => trim($section['name'] ?? ''),
                    'color' => $section['color'] ?? null,
                    'sort_order' => $section['sort_order'] ?? $index,
                ];
                if ($record->exists) {
                    $sectionAttributes = array_filter($sectionAttributes,
                        fn ($key) => array_key_exists($key, $section), ARRAY_FILTER_USE_KEY);
                }
                $record->fill($sectionAttributes);
                if (! $record->exists || $record->isDirty()) $record->save();
                foreach (['id', 'client_id'] as $key) {
                    if (! empty($section[$key])) $sectionIdMap[(string) $section[$key]] = $record->id;
                }
            }
            foreach ($sections as $section) {
                $key = (string) ($section['id'] ?? $section['client_id'] ?? '');
                if (! isset($sectionIdMap[$key])) continue;
                $record = InventorySection::query()->findOrFail($sectionIdMap[$key]);
                $parentKey = $section['parent_id'] ?? $section['parentId'] ?? null;
                $parentId = $this->resolveSectionId($parentKey, $sectionIdMap);
                if ($parentId && ! InventorySection::query()->whereKey($parentId)->exists()) {
                    throw ValidationException::withMessages(['sections' => 'بخش والد پیدا نشد. صفحه را تازه‌سازی کنید.']);
                }
                if ($parentId === $record->id) {
                    throw ValidationException::withMessages(['sections' => 'بخش نمی‌تواند والد خودش باشد.']);
                }
                if (array_key_exists('parent_id', $section) || array_key_exists('parentId', $section) || empty($section['id'])) {
                    $record->parent_id = $parentId;
                    $record->level = $parentId ? (int) InventorySection::query()->whereKey($parentId)->value('level') + 1 : 1;
                }
                if ($record->isDirty()) $record->save();
            }

            $inventoryIdMap = [];
            $pendingAddonIds = [];
            $pendingAddonDefinitionIds = [];
            foreach (array_values($items) as $index => $item) {
                if (empty($item['id']) && empty($item['name']) && empty($item['amount']) && empty($item['price']) && empty($item['stock'])) continue;
                $sectionKey = $item['section_id'] ?? $item['sectionId'] ?? null;
                $inventory = ! empty($item['id']) ? Inventory::query()->lockForUpdate()->find($item['id']) : null;
                if (! $inventory && ! empty($item['id'])) {
                    throw ValidationException::withMessages(['items' => 'یکی از خدمات دیگر وجود ندارد. صفحه را تازه‌سازی کنید.']);
                }
                if ($inventory && (empty($item['save_version']) || ! hash_equals($inventory->save_version, $item['save_version']))) {
                    throw ValidationException::withMessages(['items' => 'این خدمت در صفحهٔ دیگری تغییر کرده است. صفحه را تازه‌سازی کنید.']);
                }
                if (! $inventory) $inventory = new Inventory();
                $attributes = [
                    'section_id' => $this->resolveSectionId($sectionKey, $sectionIdMap),
                    'name' => $item['name'] ?? null,
                    'service_tags' => $this->normalizeServiceTags(
                        array_key_exists('service_tags', $item) ? $item['service_tags'] : ($item['serviceTags'] ?? [])
                    ),
                    'amount' => $item['amount'] ?? null,
                    'price' => $item['price'] ?? null,
                    'count' => $item['count'] ?? 0,
                    'stock' => isset($item['stock']) ? (int) $item['stock'] : null,
                    'min_stock' => $item['min_stock'] ?? $item['minStock'] ?? 5,
                    'active' => $item['active'] ?? true,
                    'followup_days' => max(0, (int) ($item['followup_days'] ?? $item['followupDays'] ?? 0)),
                    'sort_order' => $item['sort_order'] ?? $index,
                    'default_commission_type' => $item['default_commission_type'] ?? $item['defaultCommissionType'] ?? 'percent',
                    'default_commission_value' => $item['default_commission_value'] ?? $item['defaultCommissionValue'] ?? 0,
                ];
                if ($inventory->exists) {
                    $aliases = [
                        'section_id' => ['section_id', 'sectionId'], 'name' => ['name'],
                        'service_tags' => ['service_tags', 'serviceTags'], 'amount' => ['amount'],
                        'price' => ['price'], 'count' => ['count'], 'stock' => ['stock'],
                        'min_stock' => ['min_stock', 'minStock'], 'active' => ['active'],
                        'followup_days' => ['followup_days', 'followupDays'], 'sort_order' => ['sort_order'],
                        'default_commission_type' => ['default_commission_type', 'defaultCommissionType'],
                        'default_commission_value' => ['default_commission_value', 'defaultCommissionValue'],
                    ];
                    $attributes = array_filter($attributes, fn ($key) => collect($aliases[$key])
                        ->contains(fn ($alias) => array_key_exists($alias, $item)), ARRAY_FILTER_USE_KEY);
                }
                $inventory->fill($attributes);
                if (! $inventory->exists || $inventory->isDirty()) $inventory->save();

                foreach (['id', 'client_id', 'clientId'] as $key) {
                    if (! empty($item[$key])) {
                        $inventoryIdMap[(string) $item[$key]] = $inventory->id;
                    }
                }
                if (array_key_exists('default_addon_ids', $item) || array_key_exists('defaultAddonIds', $item))
                    $pendingAddonIds[$inventory->id] = $item['default_addon_ids'] ?? $item['defaultAddonIds'] ?? [];
                if (array_key_exists('addon_definition_ids', $item) || array_key_exists('addonDefinitionIds', $item))
                    $pendingAddonDefinitionIds[$inventory->id] = $item['addon_definition_ids'] ?? $item['addonDefinitionIds'] ?? [];
                if (array_key_exists('commissions', $item)) $this->syncCommissions($inventory, $item['commissions'] ?? []);
            }

            $inventories = Inventory::query()->whereKey(array_unique(array_merge(
                array_keys($pendingAddonIds),
                collect($pendingAddonIds)->flatten()->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all(),
                array_values($inventoryIdMap)
            )))->get()->keyBy('id');
            foreach ($pendingAddonIds as $inventoryId => $rawAddonIds) {
                $inventory = $inventories->get($inventoryId);
                if (! $inventory) {
                    continue;
                }
                $addonIds = collect(is_array($rawAddonIds) ? $rawAddonIds : [])
                    ->map(fn ($id) => $inventoryIdMap[(string) $id] ?? (is_numeric($id) ? (int) $id : null))
                    ->filter(fn ($id) => $id && $id !== $inventory->id && $inventories->has($id))
                    ->unique()
                    ->values()
                    ->all();
                if ($inventory->defaultAddons()->pluck('inventories.id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== collect($addonIds)->sort()->values()->all())
                    $inventory->defaultAddons()->sync($addonIds);
            }

            $availableAddonIds = InventoryAddonDefinition::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($pendingAddonDefinitionIds as $inventoryId => $rawAddonIds) {
                $inventory = $inventories->get($inventoryId);
                if (! $inventory) continue;
                $addonIds = collect(is_array($rawAddonIds) ? $rawAddonIds : [])
                    ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
                    ->filter(fn ($id) => $id && in_array($id, $availableAddonIds, true))
                    ->unique()->values()->all();
                if ($inventory->addonDefinitions()->pluck('inventory_addon_definitions.id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== collect($addonIds)->sort()->values()->all())
                    $inventory->addonDefinitions()->sync($addonIds);
            }
            foreach ($request->input('deleted_item_ids', []) as $id) $this->deleteInventoryIfUnused((int) $id);
            $pendingSections = collect($request->input('deleted_section_ids', []))->map(fn ($id) => (int) $id)->all();
            while ($pendingSections) {
                $leafIndex = null;
                foreach ($pendingSections as $index => $id) {
                    if (! InventorySection::query()->where('parent_id', $id)->exists()) {
                        $leafIndex = $index;
                        break;
                    }
                }
                if ($leafIndex === null) {
                    throw ValidationException::withMessages(['sections' => 'برای حذف بخش، ابتدا زیرشاخه‌های آن را حذف کنید.']);
                }
                $id = $pendingSections[$leafIndex];
                array_splice($pendingSections, $leafIndex, 1);
                $section = InventorySection::query()->lockForUpdate()->find($id);
                if (! $section) continue;
                if ($section->inventories()->exists()) {
                    throw ValidationException::withMessages(['sections' => 'برای حذف بخش، ابتدا خدمات آن را حذف کنید.']);
                }
                if (Schema::hasTable('patient_media_folders') && Schema::hasColumn('patient_media_folders', 'inventory_section_id')
                    && DB::table('patient_media_folders')->where('inventory_section_id', $id)->exists()) {
                    throw ValidationException::withMessages(['sections' => 'این بخش به پوشه‌های پرونده متصل است و قابل حذف نیست.']);
                }
                $section->delete();
            }
            return ['section_ids' => $sectionIdMap, 'item_ids' => $inventoryIdMap];
        });

        return response()->json(['message' => 'اطلاعات انبار با موفقیت ذخیره شد.', ...$savedIds]);
    }

    private function syncCommissions(Inventory $inventory, array $commissions): void
    {
        $existing = $inventory->commissions()->get();
        $used = [];
        foreach ($commissions as $commission) {
            if (empty($commission['recipient_type']) || empty($commission['recipient_name'])) continue;
            $match = $existing->first(fn ($record) => ! in_array($record->id, $used, true)
                && $record->recipient_type === $commission['recipient_type']
                && ((! empty($commission['recipient_id']) && (string) $record->recipient_id === (string) $commission['recipient_id'])
                    || $record->recipient_name === $commission['recipient_name']));
            if (! $match) $match = new InventoryCommission(['inventory_id' => $inventory->id]);
            $match->fill([
                'recipient_type' => $commission['recipient_type'],
                'recipient_id' => $commission['recipient_id'] ?? null,
                'recipient_name' => $commission['recipient_name'],
                'commission_type' => $commission['commission_type'] ?? 'percent',
                'commission_value' => $commission['commission_value'] ?? 0,
            ]);
            if (! $match->exists || $match->isDirty()) $match->save();
            $used[] = $match->id;
        }
        $existing->whereNotIn('id', $used)->each->delete();
    }

    private function deleteInventoryIfUnused(int $id): void
    {
        $inventory = Inventory::query()->lockForUpdate()->find($id);
        if (! $inventory) return;
        foreach (['appointment_service_bookings', 'inventory_movements', 'service_followups', 'resource_earning_lines', 'patient_media_folders'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('inventory_id', $id)->exists()) {
                throw ValidationException::withMessages(['items' => "خدمت «{$inventory->name}» سابقه دارد و قابل حذف نیست؛ آن را غیرفعال کنید."]);
            }
        }
        if (InventoryAddon::query()->where('addon_inventory_id', $id)->exists()) {
            throw ValidationException::withMessages(['items' => "خدمت «{$inventory->name}» به‌عنوان جانبی استفاده شده است؛ ابتدا ارتباط جانبی را بردارید."]);
        }
        if ($inventory->bookingSetting()->exists() || $inventory->bookingResources()->exists()
            || $inventory->bookingExceptions()->exists() || $inventory->bookingRules()->exists()) {
            throw ValidationException::withMessages(['items' => "خدمت «{$inventory->name}» تنظیمات نوبت‌دهی دارد؛ برای حفظ آن، خدمت را غیرفعال کنید."]);
        }
        $inventory->delete();
    }

    public function duplicate(Request $request, Inventory $inventory)
    {
        if (! $inventory->section?->parent_id) {
            throw ValidationException::withMessages([
                'section_id' => 'خدمت را نمی‌توان در بخش اصلی تعریف کرد؛ ابتدا آن را به یک زیر‌بخش منتقل کنید.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('inventories', 'name')],
        ], [
            'name.required' => 'نام خدمت جدید را وارد کنید.',
            'name.unique' => 'خدمتی با این نام از قبل وجود دارد.',
        ]);

        $copy = DB::transaction(function () use ($inventory, $data) {
            $inventory->load([
                'commissions', 'defaultAddons', 'addonDefinitions', 'bookingSetting',
                'bookingResources.availabilities.breaks', 'bookingExceptions', 'bookingRules',
            ]);

            $copy = $inventory->replicate();
            $copy->name = trim($data['name']);
            $copy->sort_order = (int) Inventory::query()->max('sort_order') + 1;
            $copy->save();

            foreach ($inventory->commissions as $commission) {
                $copy->commissions()->create(
                    $commission->only(['recipient_type', 'recipient_id', 'recipient_name', 'commission_type', 'commission_value'])
                );
            }

            $copy->defaultAddons()->sync($inventory->defaultAddons->modelKeys());
            $copy->addonDefinitions()->sync($inventory->addonDefinitions->modelKeys());

            if ($inventory->bookingSetting) {
                $copy->bookingSetting()->create(
                    collect($inventory->bookingSetting->getAttributes())
                        ->except(['id', 'inventory_id', 'created_at', 'updated_at'])->all()
                );
            }

            foreach ($inventory->bookingResources as $sourceResource) {
                $resource = $copy->bookingResources()->create(
                    collect($sourceResource->getAttributes())
                        ->except(['id', 'inventory_id', 'created_at', 'updated_at'])->all()
                );
                foreach ($sourceResource->availabilities as $sourceAvailability) {
                    $availability = $resource->availabilities()->create(
                        collect($sourceAvailability->getAttributes())
                            ->except(['id', 'booking_resource_id', 'created_at', 'updated_at'])->all()
                    );
                    foreach ($sourceAvailability->breaks as $sourceBreak) {
                        $availability->breaks()->create(
                            collect($sourceBreak->getAttributes())
                                ->except(['id', 'availability_id', 'created_at', 'updated_at'])->all()
                        );
                    }
                }
            }

            foreach ($inventory->bookingExceptions as $exception) {
                $copy->bookingExceptions()->create(
                    collect($exception->getAttributes())
                        ->except(['id', 'inventory_id', 'created_at', 'updated_at'])->all()
                );
            }
            foreach ($inventory->bookingRules as $rule) {
                $copy->bookingRules()->create(
                    collect($rule->getAttributes())
                        ->except(['id', 'inventory_id', 'created_at', 'updated_at'])->all()
                );
            }

            return $copy;
        });

        return response()->json(
            $copy->load(['section', 'commissions', 'defaultAddons', 'addonDefinitions', 'bookingSetting', 'bookingResources.availabilities.breaks']),
            201,
        );
    }

    private function ensureItemsBelongToSubsections(array $items, array $sections): void
    {
        $sectionParents = [];
        foreach ($sections as $section) {
            $parentKey = (string) ($section['parent_id'] ?? $section['parentId'] ?? '');
            foreach (['id', 'client_id'] as $key) {
                if (! empty($section[$key])) {
                    $sectionParents[(string) $section[$key]] = $parentKey;
                }
            }
        }

        foreach ($items as $index => $item) {
            if (empty($item['name']) && empty($item['amount']) && empty($item['price']) && empty($item['stock'])) {
                continue;
            }

            $sectionKey = (string) ($item['section_id'] ?? $item['sectionId'] ?? '');
            if ($sectionKey === '' && ! empty($item['id'])) {
                $sectionKey = (string) Inventory::query()->whereKey($item['id'])->value('section_id');
            }
            $hasParent = array_key_exists($sectionKey, $sectionParents)
                ? $sectionParents[$sectionKey] !== ''
                : (bool) InventorySection::query()->whereKey($sectionKey)->value('parent_id');

            if (! $hasParent) {
                throw ValidationException::withMessages([
                    "items.$index.section_id" => 'خدمت را نمی‌توان در بخش اصلی تعریف کرد؛ باید یک زیر‌بخش انتخاب کنید.',
                ]);
            }
        }
    }

    public function adjustStock(Request $request)
    {
        $data = $request->validate([
            'inventory_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string'],
            'direction' => ['required', 'in:increase,decrease'],
            'quantity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($data) {
            $item = ! empty($data['inventory_id'])
                ? Inventory::query()->whereKey($data['inventory_id'])->lockForUpdate()->first()
                : Inventory::query()->where('name', $data['name'] ?? '')->lockForUpdate()->first();

            if (! $item) {
                return response()->json(['message' => 'کالا در انبار یافت نشد'], 404);
            }
            $previousStock = (int) ($item->stock ?? 0);
            $quantity = (int) $data['quantity'];
            $change = $data['direction'] === 'increase' ? $quantity : -$quantity;

            if ($change < 0 && $previousStock + $change < 0) {
                return response()->json(['message' => 'موجودی فعلی برای این کاهش کافی نیست.'], 422);
            }

            $item->update(['stock' => $previousStock + $change]);
            $movement = InventoryMovement::create([
                'inventory_id' => $item->id,
                'inventory_name' => $item->name,
                'quantity' => $change,
                'type' => $data['direction'] === 'increase' ? 'manual_increase' : 'manual_decrease',
                'description' => $data['description'] ?: ($change > 0 ? 'افزایش دستی موجودی' : 'کاهش دستی موجودی'),
                'occurred_at' => now(),
            ]);

            return response()->json([
                'message' => 'گردش موجودی ثبت شد.',
                'stock' => (int) $item->stock,
                'item' => $item->fresh(),
                'movement' => $movement,
            ]);
        });
    }

    public function movements(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = InventoryMovement::query()
            ->where(function ($query) use ($inventory) {
                $query->where('inventory_id', $inventory->id)
                    ->orWhere('inventory_name', $inventory->name);
            });
        if (! empty($data['date_from'])) $query->whereDate('occurred_at', '>=', $data['date_from']);
        if (! empty($data['date_to'])) $query->whereDate('occurred_at', '<=', $data['date_to']);

        return response()->json([
            'current_stock' => (float) ($inventory->stock ?? 0),
            'movements' => $query
                ->latest('occurred_at')
                ->latest('id')
                ->limit($data['limit'] ?? 100)
                ->get(),
        ]);
    }
    private function resolveSectionId($sectionKey, array $sectionIdMap): ?int
    {
        if (! $sectionKey) {
            return null;
        }

        if (isset($sectionIdMap[(string) $sectionKey])) {
            return $sectionIdMap[(string) $sectionKey];
        }

        return is_numeric($sectionKey) ? (int) $sectionKey : null;
    }

    private function normalizeServiceTags($tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,،\n]+/u', $tags) ?: [];
        }

        if (! is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
