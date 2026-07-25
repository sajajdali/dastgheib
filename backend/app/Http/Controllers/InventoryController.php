<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\InventoryCommission;
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
            Inventory::with(['section', 'commissions'])
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
        ]);
    }

    public function store(Request $request)
    {
        $items = $request->input('items', []);
        $sections = $request->input('sections', []);

        DB::transaction(function () use ($items, $sections) {
            InventoryCommission::query()->delete();
            Inventory::query()->delete();

            $sectionIdMap = [];

            if (is_array($sections) && count($sections)) {
                InventorySection::query()->delete();

                foreach (array_values($sections) as $index => $section) {
                    if (empty($section['name'])) {
                        continue;
                    }

                    $created = InventorySection::create([
                        'name' => $section['name'],
                        'sort_order' => $section['sort_order'] ?? $index,
                    ]);

                    foreach (['id', 'client_id'] as $key) {
                        if (! empty($section[$key])) {
                            $sectionIdMap[(string) $section[$key]] = $created->id;
                        }
                    }
                }
            }

            foreach (array_values($items) as $index => $item) {
                if (empty($item['name']) && empty($item['amount']) && empty($item['price']) && empty($item['stock'])) {
                    continue;
                }

                $sectionKey = $item['section_id'] ?? $item['sectionId'] ?? null;

                $inventory = Inventory::create([
                    'section_id' => $this->resolveSectionId($sectionKey, $sectionIdMap),
                    'name' => $item['name'] ?? null,
                    'service_tags' => $this->normalizeServiceTags($item['service_tags'] ?? $item['serviceTags'] ?? []),
                    'amount' => $item['amount'] ?? null,
                    'price' => $item['price'] ?? null,
                    'count' => $item['count'] ?? 0,
                    'stock' => $item['stock'] ?? null,
                    'min_stock' => $item['min_stock'] ?? $item['minStock'] ?? 5,
                    'active' => $item['active'] ?? true,
                    'sort_order' => $item['sort_order'] ?? $index,
                    'default_commission_type' => $item['default_commission_type'] ?? $item['defaultCommissionType'] ?? 'percent',
                    'default_commission_value' => $item['default_commission_value'] ?? $item['defaultCommissionValue'] ?? 0,
                ]);

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
        });

        return response()->json(['message' => 'اطلاعات انبار با موفقیت ذخیره شد.']);
    }

    public function adjustStock(Request $request)
    {
        $name = $request->input('name');
        $amount = (int) $request->input('amount', 0);

        if (! $name || $amount === 0) {
            return response()->json(['message' => 'نام کالا یا مقدار نامعتبر است'], 422);
        }

        $item = Inventory::where('name', $name)->first();

        if (! $item) {
            return response()->json(['message' => 'کالا در انبار یافت نشد'], 404);
        }

        $previousStock = (int) ($item->stock ?? 0);
        $newStock = max($previousStock + $amount, 0);

        $item->stock = $newStock;
        $item->save();

        return response()->json([
            'message' => 'موجودی با موفقیت به روزرسانی شد',
            'stock' => $newStock,
            'depleted' => $previousStock > 0 && $newStock === 0,
            'item' => $item,
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
