<?php

namespace App\Http\Controllers;

use App\Models\CentralBillingPlan;
use App\Models\CentralStoreTerm;
use App\Models\CentralStoreTermAcceptance;
use App\Models\Tenant;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSubscriptionController extends Controller
{
    public function __construct(private readonly TenantSubscriptionService $subscriptions)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'subscription' => $this->subscriptions->statusData(tenant(), withCatalog: true),
        ]);
    }

    public function renew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'licensed_users' => ['required', 'integer', 'min:1', 'max:100000'],
            'term_id' => ['required', 'integer'],
            'accepted' => ['accepted'],
            'payment_reference' => ['required', 'string', 'max:120'],
        ]);

        [$plan, $term] = tenancy()->central(fn () => [
            CentralBillingPlan::query()->whereKey($data['plan_id'])->where('is_active', true)->first(),
            CentralStoreTerm::query()->whereKey($data['term_id'])->where('is_active', true)->first(),
        ]);
        abort_unless($plan, 422, 'بسته انتخاب‌شده فعال نیست.');
        abort_unless($term, 422, 'متن قوانین فعال یافت نشد.');

        /** @var Tenant $tenant */
        $tenant = tenant();
        $result = $this->subscriptions->renew(
            $tenant,
            $plan,
            (int) $data['licensed_users'],
            $data['payment_reference'],
        );
        $subscription = $result['subscription'];

        if (! $result['duplicate']) {
            tenancy()->central(fn () => CentralStoreTermAcceptance::create([
                'central_store_term_id' => $term->id,
                'tenant_id' => $tenant->getTenantKey(),
                'tenant_name' => $tenant->name,
                'user_id' => $request->user()?->id,
                'buyer_name' => $request->user()?->name,
                'buyer_email' => $request->user()?->email,
                'items' => [[
                    'key' => 'tenant-subscription-'.$plan->id,
                    'title' => $plan->name,
                    'licensed_users' => (int) $subscription->licensed_users,
                    'duration_days' => (int) $subscription->duration_days,
                    'subscription_id' => $subscription->id,
                ]],
                'subtotal' => (int) $subscription->price_paid,
                'discount_amount' => 0,
                'payable_total' => (int) $subscription->price_paid,
                'accepted_at' => now(),
                'paid_at' => $result['activated'] ? now() : null,
                'status' => $result['activated'] ? 'paid' : 'pending',
            ]));
        }

        return response()->json([
            'message' => $result['activated']
                ? ($result['duplicate'] ? 'این تمدید قبلاً ثبت و فعال شده است.' : 'اشتراک با موفقیت تمدید شد.')
                : ($result['duplicate'] ? 'این درخواست تمدید قبلاً ثبت شده است.' : 'درخواست تمدید ثبت شد و پس از تأیید پرداخت فعال می‌شود.'),
            'activated' => $result['activated'],
            'subscription' => $this->subscriptions->statusData($tenant, withCatalog: true),
        ]);
    }
}
