<?php

namespace App\Http\Controllers;

use App\Models\CentralStoreTerm;
use App\Models\CentralStoreTermAcceptance;
use App\Models\Tenant;
use App\Services\ModuleSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StoreCheckoutController extends Controller
{
    public function __construct(private readonly ModuleSubscriptionService $subscriptions)
    {
    }

    public function terms(): JsonResponse
    {
        return response()->json([
            'terms' => $this->activeTermsData(),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer'],
            'accepted' => ['accepted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'max:120'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.billing_period' => ['required', 'string', 'in:one_time,monthly,quarterly,semiannual,annual'],
            'subtotal' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['sometimes', 'integer', 'min:0'],
            'payable_total' => ['required', 'integer', 'min:0'],
        ]);

        $term = tenancy()->central(fn () => CentralStoreTerm::query()
            ->whereKey($data['term_id'])
            ->where('is_active', true)
            ->first());
        if (! $term) {
            throw ValidationException::withMessages([
                'term_id' => 'متن قوانین فعال یافت نشد.',
            ]);
        }

        $now = now();
        $tenant = tenant();
        $user = $request->user();
        $items = collect($data['items'])
            ->map(fn (array $item) => [
                'key' => $item['key'],
                'title' => $item['title'],
                'price' => (int) $item['price'],
                'billing_period' => $item['billing_period'],
            ])
            ->values()
            ->all();

        $acceptance = tenancy()->central(fn () => CentralStoreTermAcceptance::create([
            'central_store_term_id' => $term->id,
            'tenant_id' => $tenant?->getTenantKey(),
            'tenant_name' => $tenant?->name,
            'user_id' => $user?->id,
            'buyer_name' => $user?->name,
            'buyer_email' => $user?->email,
            'items' => $items,
            'subtotal' => $data['subtotal'],
            'discount_amount' => $data['discount_amount'] ?? 0,
            'payable_total' => $data['payable_total'],
            'accepted_at' => $now,
            'paid_at' => $now,
            'status' => 'paid',
        ]));

        $enabledFeatures = $this->activateModules($tenant, $items, $now);

        return response()->json([
            'message' => 'تایید قوانین ثبت شد و امکانات خریداری‌شده فعال شدند.',
            'acceptance' => [
                'id' => $acceptance->id,
                'accepted_at' => optional($acceptance->accepted_at)->toDateTimeString(),
                'paid_at' => optional($acceptance->paid_at)->toDateTimeString(),
                'status' => $acceptance->status,
            ],
            'enabled_features' => $enabledFeatures,
        ]);
    }

    private function activeTermsData(): array
    {
        $term = tenancy()->central(function () {
            $term = CentralStoreTerm::query()
                ->where('is_active', true)
                ->latest()
                ->first();

            if (! $term) {
                $term = CentralStoreTerm::create([
                    'content' => "با خرید از فروشگاه، خریدار تایید می‌کند که مشخصات اقلام، قیمت، شرایط فعال‌سازی و مسئولیت استفاده از امکانات را مطالعه کرده و پذیرفته است.\nپس از پرداخت موفق، امکانات انتخاب‌شده برای همین سایت فعال می‌شوند.",
                    'is_active' => true,
                ]);
            }

            return $term;
        });

        return [
            'id' => $term->id,
            'content' => $term->content,
            'updated_at' => optional($term->updated_at)->toDateTimeString(),
        ];
    }

    private function activateModules(?Tenant $tenant, array $items, \Illuminate\Support\Carbon $paidAt): array
    {
        if (! $tenant) {
            return [];
        }

        return tenancy()->central(function () use ($tenant, $items, $paidAt) {
            $centralTenant = Tenant::query()->find($tenant->getTenantKey());
            if (! $centralTenant) {
                return [];
            }

            foreach ($items as $item) {
                $this->subscriptions->purchaseOrRenew($centralTenant->getTenantKey(), $item, $paidAt);
            }

            return $this->subscriptions->syncTenantModuleIds($centralTenant->getTenantKey());
        });
    }
}
