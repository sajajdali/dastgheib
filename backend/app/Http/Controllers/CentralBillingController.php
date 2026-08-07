<?php

namespace App\Http\Controllers;

use App\Models\CentralBillingPlan;
use App\Models\CentralDiscountCode;
use App\Models\CentralStoreTerm;
use App\Models\CentralUserPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CentralBillingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'plans' => CentralBillingPlan::query()
                ->orderBy('sort_order')
                ->orderBy('duration_days')
                ->get(),
            'user_pricing' => $this->userPricing(),
            'discount_codes' => CentralDiscountCode::query()
                ->with(['redemptions' => fn ($query) => $query->latest('used_at')->latest()])
                ->withCount('redemptions')
                ->latest()
                ->get()
                ->map(fn (CentralDiscountCode $discount) => $this->discountData($discount)),
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);
        $plan = CentralBillingPlan::create($data);

        return response()->json([
            'message' => 'بسته زمانی ساخته شد.',
            'plan' => $plan,
        ], 201);
    }

    public function updatePlan(Request $request, CentralBillingPlan $plan): JsonResponse
    {
        $plan->update($this->validatePlan($request, partial: true));

        return response()->json([
            'message' => 'بسته زمانی به‌روزرسانی شد.',
            'plan' => $plan->fresh(),
        ]);
    }

    public function destroyPlan(CentralBillingPlan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json(['message' => 'بسته زمانی حذف شد.']);
    }

    public function updateUserPricing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'included_users' => ['required', 'integer', 'min:1', 'max:100000'],
            'extra_user_price' => ['required', 'integer', 'min:0'],
        ]);

        $pricing = $this->userPricing();
        $pricing->update($data);

        return response()->json([
            'message' => 'تعرفه کاربران به‌روزرسانی شد.',
            'user_pricing' => $pricing->fresh(),
        ]);
    }

    public function storeDiscount(Request $request): JsonResponse
    {
        $discount = CentralDiscountCode::create($this->validateDiscount($request));

        return response()->json([
            'message' => 'کد تخفیف ساخته شد.',
            'discount_code' => $this->discountData($discount->load('redemptions')->loadCount('redemptions')),
        ], 201);
    }

    public function updateDiscount(Request $request, CentralDiscountCode $discount): JsonResponse
    {
        $discount->update($this->validateDiscount($request, $discount));

        return response()->json([
            'message' => 'کد تخفیف به‌روزرسانی شد.',
            'discount_code' => $this->discountData($discount->fresh(['redemptions'])->loadCount('redemptions')),
        ]);
    }

    public function destroyDiscount(CentralDiscountCode $discount): JsonResponse
    {
        $discount->delete();

        return response()->json(['message' => 'کد تخفیف حذف شد.']);
    }

    public function storeTerms(): JsonResponse
    {
        return response()->json([
            'terms' => $this->storeTermsData(),
        ]);
    }

    public function updateStoreTerms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'min:10'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $term = CentralStoreTerm::query()->latest()->first();
        if ($term) {
            $term->update([
                'content' => $data['content'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        } else {
            $term = CentralStoreTerm::create([
                'content' => $data['content'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        }

        return response()->json([
            'message' => 'قوانین و مقررات فروشگاه به‌روزرسانی شد.',
            'terms' => $this->storeTermsData($term->fresh()),
        ]);
    }

    private function validatePlan(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'duration_days' => [$required, 'integer', 'min:1', 'max:3650'],
            'base_price' => [$required, 'integer', 'min:0'],
            'is_trial' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function validateDiscount(Request $request, ?CentralDiscountCode $discount = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('central_discount_codes', 'code')->ignore($discount?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    private function discountData(CentralDiscountCode $discount): array
    {
        return [
            'id' => $discount->id,
            'code' => $discount->code,
            'title' => $discount->title,
            'type' => $discount->type,
            'value' => $discount->value,
            'starts_at' => optional($discount->starts_at)->toDateTimeString(),
            'ends_at' => optional($discount->ends_at)->toDateTimeString(),
            'usage_limit' => $discount->usage_limit,
            'is_active' => (bool) $discount->is_active,
            'redemptions_count' => $discount->redemptions_count ?? $discount->redemptions->count(),
            'redemptions' => $discount->redemptions
                ->map(fn ($redemption) => [
                    'id' => $redemption->id,
                    'tenant_id' => $redemption->tenant_id,
                    'tenant_name' => $redemption->tenant_name,
                    'buyer_name' => $redemption->buyer_name,
                    'buyer_email' => $redemption->buyer_email,
                    'subtotal' => $redemption->subtotal,
                    'discount_amount' => $redemption->discount_amount,
                    'payable_total' => $redemption->payable_total,
                    'used_at' => optional($redemption->used_at ?? $redemption->created_at)->toDateTimeString(),
                ])
                ->values(),
        ];
    }

    private function userPricing(): CentralUserPricing
    {
        return CentralUserPricing::query()->firstOrCreate([], [
            'included_users' => 1,
            'extra_user_price' => 0,
        ]);
    }

    private function storeTermsData(?CentralStoreTerm $term = null): array
    {
        $term ??= CentralStoreTerm::query()->latest()->first();
        if (! $term) {
            $term = CentralStoreTerm::create([
                'content' => "با خرید از فروشگاه، خریدار تایید می‌کند که مشخصات اقلام، قیمت، شرایط فعال‌سازی و مسئولیت استفاده از امکانات را مطالعه کرده و پذیرفته است.\nپس از پرداخت موفق، امکانات انتخاب‌شده برای همین سایت فعال می‌شوند.",
                'is_active' => true,
            ]);
        }

        return [
            'id' => $term->id,
            'content' => $term->content,
            'is_active' => (bool) $term->is_active,
            'updated_at' => optional($term->updated_at)->toDateTimeString(),
        ];
    }
}
