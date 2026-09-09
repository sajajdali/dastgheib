<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\InventoryCommission;
use App\Models\InventoryAddon;
use App\Models\InventoryAddonDefinition;
use App\Models\InventoryMovement;
use App\Models\InventorySection;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Inventory::with(['section', 'commissions', 'defaultAddons', 'addonDefinitions'])
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
            'doctors' => Doctor::query()->orderBy('name')->get(['id', 'name']),
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
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['nullable'],
            'items.*.price' => ['nullable'],
            'items.*.stock' => ['nullable', 'integer', 'min:0'],
            'items.*.min_stock' => ['nullable', 'integer', 'min:0'],
            'items.*.active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data) {
            InventoryAddonDefinition::query()->delete();
            foreach (array_values($data['items']) as $index => $item) {
                InventoryAddonDefinition::create([
                    'name' => trim($item['name']),
                    'amount' => $item['amount'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'stock' => isset($item['stock']) ? (int) $item['stock'] : 0,
                    'min_stock' => $item['min_stock'] ?? $item['minStock'] ?? 5,
                    'active' => $item['active'] ?? true,
                    'sort_order' => $index,
                ]);
            }
        });

        return response()->json(['message' => 'جانبی‌ها ذخیره شدند.']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => ['nullable', 'array'],
            // موجودی منفی نشان‌دهنده کمبود ثبت‌شده توسط خدمات انجام‌شده است.
            'items.*.stock' => ['nullable', 'integer'],
        ]);
        $items = $request->input('items', []);
        $sections = $request->input('sections', []);

        DB::transaction(function () use ($items, $sections) {
            InventoryAddon::query()->delete();
            InventoryCommission::query()->get()->each->delete();
            Inventory::query()->get()->each->delete();

            $sectionIdMap = [];

            if (is_array($sections) && count($sections)) {
                InventorySection::query()->get()->each->delete();

                foreach (array_values($sections) as $index => $section) {
                    if (empty($section['name'])) {
                        continue;
                    }

                    $created = InventorySection::create([
                        'parent_id' => null,
                        'level' => max(1, (int) ($section['level'] ?? 1)),
                        'name' => $section['name'],
                        'sort_order' => $section['sort_order'] ?? $index,
                    ]);

                    foreach (['id', 'client_id'] as $key) {
                        if (! empty($section[$key])) {
                            $sectionIdMap[(string) $section[$key]] = $created->id;
                        }
                    }
                }

                foreach (array_values($sections) as $section) {
                    $key = (string) ($section['id'] ?? $section['client_id'] ?? '');
                    $parentKey = (string) ($section['parent_id'] ?? $section['parentId'] ?? '');
                    if ($key === '' || $parentKey === '' || ! isset($sectionIdMap[$key], $sectionIdMap[$parentKey])) {
                        continue;
                    }

                    $parentId = $sectionIdMap[$parentKey];
                    $parentLevel = (int) (InventorySection::query()->find($parentId)?->level ?? 1);
                    InventorySection::query()->whereKey($sectionIdMap[$key])->update([
                        'parent_id' => $parentId,
                        'level' => $parentLevel + 1,
                    ]);
                }

                // ذخیرهٔ ساختار انبار شناسهٔ بخش‌ها را بازسازی می‌کند؛
                // اتصال پزشک به بخش خدمات را به شناسه‌های تازه منتقل کن.
                Doctor::query()->get()->each(function (Doctor $doctor) use ($sectionIdMap) {
                    $currentIds = is_array($doctor->service_section_ids) ? $doctor->service_section_ids : [];
                    $remappedIds = collect($currentIds)
                        ->map(fn ($id) => $sectionIdMap[(string) $id] ?? $id)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if ($remappedIds !== $currentIds) {
                        $doctor->update(['service_section_ids' => $remappedIds]);
                    }
                });
            }

            $inventoryIdMap = [];
            $pendingAddonIds = [];
            $pendingAddonDefinitionIds = [];

            foreach (array_values($items) as $index => $item) {
                if (empty($item['name']) && empty($item['amount']) && empty($item['price']) && empty($item['stock'])) {
                    continue;
                }

                $sectionKey = $item['section_id'] ?? $item['sectionId'] ?? null;

                $inventory = Inventory::create([
                    'section_id' => $this->resolveSectionId($sectionKey, $sectionIdMap),
                    'name' => $item['name'] ?? null,
                    // حتی آرایهٔ خالی هم باید صریحاً ذخیره شود تا حذف همهٔ تگ‌ها برنگردد.
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
                ]);

                foreach (['id', 'client_id', 'clientId'] as $key) {
                    if (! empty($item[$key])) {
                        $inventoryIdMap[(string) $item[$key]] = $inventory->id;
                    }
                }
                $pendingAddonIds[$inventory->id] = $item['default_addon_ids'] ?? $item['defaultAddonIds'] ?? [];
                $pendingAddonDefinitionIds[$inventory->id] = $item['addon_definition_ids'] ?? $item['addonDefinitionIds'] ?? [];

                foreach (($item['commissions'] ?? []) as $commission) {
                    if (empty($commission['recipient_type']) || empty($commission['recipient_name'])) {
                        continue;
                    }

                    $inventory->commissions()->create([
                        'recipient_type' => $commission['recipient_type'],
                        'recipient_id' => $commission['recipient_id'] ?? null,
                        'recipient_name' => $commission['recipient_name'],
                        'commission_type' => $commission['commission_type'] ?? 'percent',
                        'commission_value' => $commission['commission_value'] ?? 0,
                    ]);
                }
            }

            $inventories = Inventory::query()->get()->keyBy('id');
            foreach ($pendingAddonIds as $inventoryId => $rawAddonIds) {
                $inventory = $inventories->get($inventoryId);
                if (! $inventory) {
                    continue;
                }
                $addonIds = collect(is_array($rawAddonIds) ? $rawAddonIds : [])
                    ->map(fn ($id) => $inventoryIdMap[(string) $id] ?? (is_numeric($id) ? (int) $id : null))
                    ->filter(fn ($id) => $id && $id !== $inventory->id && ($inventories->get($id)?->active ?? false))
                    ->unique()
                    ->values()
                    ->all();
                $inventory->defaultAddons()->sync($addonIds);
            }

            $activeAddonIds = InventoryAddonDefinition::query()->where('active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($pendingAddonDefinitionIds as $inventoryId => $rawAddonIds) {
                $inventory = $inventories->get($inventoryId);
                if (! $inventory) continue;
                $addonIds = collect(is_array($rawAddonIds) ? $rawAddonIds : [])
                    ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
                    ->filter(fn ($id) => $id && in_array($id, $activeAddonIds, true))
                    ->unique()->values()->all();
                $inventory->addonDefinitions()->sync($addonIds);
            }
        });

        return response()->json(['message' => 'اطلاعات انبار با موفقیت ذخیره شد.']);
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
