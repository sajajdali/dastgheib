<?php

namespace App\Services;

use App\Models\CentralModuleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModuleCatalogService
{
    private const PERIOD_LABELS = [
        'one_time' => 'یک‌باره',
        'monthly' => 'ماهانه',
        'quarterly' => '۳ ماهه',
        'semiannual' => '۶ ماهه',
        'annual' => '۱ ساله',
    ];

    public function listForCentral(): array
    {
        return $this->central(function () {
            $this->ensureSeeded();

            return CentralModuleCatalog::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (CentralModuleCatalog $row) => $this->centralPayload($row))
                ->values()
                ->all();
        });
    }

    public function syncFromCentral(array $modules): array
    {
        return $this->central(function () use ($modules) {
            $normalized = [];
            foreach (array_values($modules) as $index => $module) {
                $normalized[] = $this->normalizeIncomingModule($module, $index);
            }

            DB::transaction(function () use ($normalized) {
                $keep = [];
                foreach ($normalized as $module) {
                    CentralModuleCatalog::query()->updateOrCreate(
                        ['module_key' => $module['module_key']],
                        [
                            'name' => $module['name'],
                            'price' => $module['price'],
                            'periods' => $module['periods'],
                            'enabled' => $module['enabled'],
                            'sort_order' => $module['sort_order'],
                        ]
                    );
                    $keep[] = $module['module_key'];
                }

                CentralModuleCatalog::query()
                    ->whereNotIn('module_key', $keep)
                    ->delete();
            });

            return $this->listForCentral();
        });
    }

    public function listForStore(): array
    {
        return $this->central(function () {
            $this->ensureSeeded();
            $rows = CentralModuleCatalog::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->keyBy('module_key');

            $defaults = collect($this->defaultModules())->keyBy('key');
            $items = [];

            foreach ($rows as $key => $row) {
                $meta = $defaults->get($key, []);
                if (($meta['storeVisible'] ?? true) === false) {
                    continue;
                }

                $periods = $this->normalizePeriods($row->periods, (int) $row->price);
                $primary = $periods[0] ?? ['key' => 'one_time', 'label' => 'یک‌باره', 'price' => (int) $row->price];

                $items[] = [
                    'key' => $row->module_key,
                    'title' => $row->name ?: ($meta['title'] ?? $row->module_key),
                    'price' => (int) ($primary['price'] ?? $row->price),
                    'periods' => $periods,
                    'category' => $meta['category'] ?? 'operations',
                    'categoryLabel' => $meta['categoryLabel'] ?? 'سایر',
                    'icon' => $meta['icon'] ?? '📦',
                    'color' => $meta['color'] ?? '#2563eb',
                    'billing' => $primary['label'] ?? ($meta['billing'] ?? 'یک‌باره'),
                    'comingSoon' => ! (bool) $row->enabled,
                    'description' => $meta['description'] ?? '',
                    'longDescription' => $meta['longDescription'] ?? ($meta['description'] ?? ''),
                    'image' => $meta['image'] ?? null,
                ];
            }

            return $items;
        });
    }

    /**
     * @param  array<int, array{key:string,title?:string,price:int,billing_period:string}>  $items
     * @return array<int, array{key:string,title:string,price:int,billing_period:string}>
     */
    public function validateCheckoutItems(array $items): array
    {
        return $this->central(function () use ($items) {
            $this->ensureSeeded();
            $catalog = CentralModuleCatalog::query()->get()->keyBy('module_key');
            $validated = [];

            foreach ($items as $index => $item) {
                $key = (string) ($item['key'] ?? '');
                $period = (string) ($item['billing_period'] ?? 'one_time');
                $row = $catalog->get($key);

                if (! $row || ! $row->enabled) {
                    throw ValidationException::withMessages([
                        "items.$index.key" => "امکان «{$key}» برای خرید فعال نیست.",
                    ]);
                }

                $periods = $this->normalizePeriods($row->periods, (int) $row->price);
                $match = collect($periods)->firstWhere('key', $period);
                if (! $match) {
                    throw ValidationException::withMessages([
                        "items.$index.billing_period" => "دوره فروش «{$period}» برای این امکان تعریف نشده است.",
                    ]);
                }

                $price = (int) $match['price'];
                $clientPrice = (int) ($item['price'] ?? -1);
                if ($clientPrice !== $price) {
                    throw ValidationException::withMessages([
                        "items.$index.price" => 'قیمت ارسالی با تعرفه مرکزی هم‌خوانی ندارد. صفحه را تازه کنید.',
                    ]);
                }

                $validated[] = [
                    'key' => $key,
                    'title' => $row->name ?: (string) ($item['title'] ?? $key),
                    'price' => $price,
                    'billing_period' => $period,
                ];
            }

            return $validated;
        });
    }

    public function ensureSeeded(): void
    {
        if (CentralModuleCatalog::query()->exists()) {
            return;
        }

        foreach ($this->defaultModules() as $index => $module) {
            CentralModuleCatalog::query()->create([
                'module_key' => $module['key'],
                'name' => $module['title'],
                'price' => (int) $module['price'],
                'periods' => $this->normalizePeriods($module['periods'] ?? [], (int) $module['price']),
                'enabled' => ! ($module['comingSoon'] ?? false),
                'sort_order' => $index,
            ]);
        }
    }

    private function normalizeIncomingModule(array $module, int $index): array
    {
        $key = trim((string) ($module['id'] ?? $module['module_key'] ?? $module['key'] ?? ''));
        $name = trim((string) ($module['name'] ?? $module['title'] ?? ''));
        $periods = $this->normalizePeriods($module['periods'] ?? [], (int) ($module['price'] ?? 0));

        if ($key === '' || $name === '' || ! $periods) {
            throw ValidationException::withMessages([
                'modules' => 'هر امکان باید شناسه، نام و حداقل یک دوره قیمت داشته باشد.',
            ]);
        }

        $price = (int) ($periods[0]['price'] ?? $module['price'] ?? 0);

        return [
            'module_key' => $key,
            'name' => $name,
            'price' => $price,
            'periods' => $periods,
            'enabled' => ($module['enabled'] ?? true) !== false,
            'sort_order' => (int) ($module['sort_order'] ?? $index),
        ];
    }

    private function normalizePeriods(mixed $periods, int $fallbackPrice): array
    {
        if (! is_array($periods) || ! $periods) {
            return [[
                'key' => 'one_time',
                'label' => self::PERIOD_LABELS['one_time'],
                'price' => max(0, $fallbackPrice),
            ]];
        }

        $normalized = [];
        foreach ($periods as $period) {
            if (! is_array($period)) {
                continue;
            }
            $key = (string) ($period['key'] ?? 'one_time');
            $normalized[] = [
                'key' => $key,
                'label' => (string) ($period['label'] ?? self::PERIOD_LABELS[$key] ?? $key),
                'price' => max(0, (int) ($period['price'] ?? $fallbackPrice)),
            ];
        }

        return $normalized ?: [[
            'key' => 'one_time',
            'label' => self::PERIOD_LABELS['one_time'],
            'price' => max(0, $fallbackPrice),
        ]];
    }

    private function centralPayload(CentralModuleCatalog $row): array
    {
        $periods = $this->normalizePeriods($row->periods, (int) $row->price);

        return [
            'id' => $row->module_key,
            'name' => $row->name,
            'price' => (int) $row->price,
            'periods' => $periods,
            'enabled' => (bool) $row->enabled,
            'sort_order' => (int) $row->sort_order,
        ];
    }

    private function defaultModules(): array
    {
        return array_values(config('store_modules.modules', []));
    }

    private function central(callable $callback): mixed
    {
        return tenancy()->initialized ? tenancy()->central($callback) : $callback();
    }
}
