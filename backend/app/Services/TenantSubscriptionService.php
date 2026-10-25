<?php

namespace App\Services;

use App\Models\CentralBillingPlan;
use App\Models\CentralTenantSubscription;
use App\Models\CentralUserPricing;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSubscriptionService
{
    public function isExpired(Tenant $tenant): bool
    {
        return $this->central(function () use ($tenant): bool {
            $subscription = $this->latestSubscription((string) $tenant->getTenantKey());
            if ($subscription) return $subscription->expires_at->isPast();
            if (! ($tenant->plan_id ?? null)) return false;

            $plan = CentralBillingPlan::query()->find($tenant->plan_id);
            return (bool) ($plan && $tenant->created_at
                && $tenant->created_at->copy()->addDays((int) $plan->duration_days)->endOfDay()->isPast());
        });
    }

    public function statusData(Tenant $tenant, bool $withCatalog = false): array
    {
        $actualUsers = $this->actualActiveUsers($tenant);

        return $this->central(function () use ($tenant, $withCatalog, $actualUsers): array {
            $subscription = $this->latestSubscription((string) $tenant->getTenantKey());
            $legacyPlan = ! $subscription && ($tenant->plan_id ?? null)
                ? CentralBillingPlan::query()->find($tenant->plan_id)
                : null;
            $expiresAt = $subscription?->expires_at
                ?? ($legacyPlan && $tenant->created_at
                    ? $tenant->created_at->copy()->addDays((int) $legacyPlan->duration_days)->endOfDay()
                    : null);
            $managed = (bool) ($subscription || $legacyPlan);
            $expired = $managed && $expiresAt?->isPast();
            // Historical tenants may already have more users than their old JSON
            // counter. Once a subscription exists its purchased capacity is the
            // source of truth and must never silently grow with the users table.
            $licensedUsers = $subscription
                ? max(1, (int) $subscription->licensed_users)
                : max(1, (int) ($tenant->user_count ?? 1), $actualUsers);
            $daysRemaining = $expiresAt
                ? max(0, now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false))
                : null;

            $data = [
                'managed' => $managed,
                'status' => $expired ? 'expired' : ($managed ? 'active' : 'unmanaged'),
                'is_active' => ! $expired,
                'is_expired' => (bool) $expired,
                'plan_id' => $subscription?->plan_id ?? $legacyPlan?->id,
                'plan_name' => $subscription?->plan_name ?? $legacyPlan?->name ?? 'بدون اشتراک زمان‌دار',
                'duration_days' => (int) ($subscription?->duration_days ?? $legacyPlan?->duration_days ?? 0),
                'starts_at' => optional($subscription?->starts_at ?? $tenant->created_at)->toDateTimeString(),
                'expires_at' => optional($expiresAt)->toDateTimeString(),
                'days_remaining' => $daysRemaining,
                'licensed_users' => $licensedUsers,
                'actual_users' => $actualUsers,
                'available_user_slots' => max(0, $licensedUsers - $actualUsers),
                'price_paid' => (int) ($subscription?->price_paid ?? 0),
                'pending_renewal' => $this->pendingRenewalData((string) $tenant->getTenantKey()),
            ];

            if ($withCatalog) {
                $pricing = $this->pricing();
                $data['user_pricing'] = $pricing->only(['included_users', 'extra_user_price']);
                $data['plans'] = CentralBillingPlan::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('duration_days')
                    ->get()
                    ->map(fn (CentralBillingPlan $plan) => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'duration_days' => (int) $plan->duration_days,
                        'base_price' => (int) $plan->base_price,
                        'is_trial' => (bool) $plan->is_trial,
                        'quoted_price' => $this->quote($plan, $licensedUsers, $pricing),
                    ])->values();
            }

            return $data;
        });
    }

    public function quote(CentralBillingPlan $plan, int $licensedUsers, ?CentralUserPricing $pricing = null): int
    {
        $pricing ??= $this->central(fn () => $this->pricing());
        $extraUsers = max(0, $licensedUsers - max(1, (int) $pricing->included_users));

        return (int) $plan->base_price + ($extraUsers * (int) $pricing->extra_user_price);
    }

    public function renew(Tenant $tenant, CentralBillingPlan $plan, int $licensedUsers, string $paymentReference, ?Carbon $paidAt = null): array
    {
        $paidAt ??= now();
        $actualUsers = $this->actualActiveUsers($tenant);
        abort_if($licensedUsers < $actualUsers, 422,
            "ظرفیت اشتراک نمی‌تواند کمتر از {$actualUsers} کاربر فعال فعلی باشد.");

        return $this->central(function () use ($tenant, $plan, $licensedUsers, $paymentReference, $paidAt): array {
            return DB::transaction(function () use ($tenant, $plan, $licensedUsers, $paymentReference, $paidAt): array {
                $duplicate = CentralTenantSubscription::query()
                    ->where('payment_reference', $paymentReference)
                    ->lockForUpdate()
                    ->first();
                if ($duplicate) {
                    abort_unless((string) $duplicate->tenant_id === (string) $tenant->getTenantKey(), 409,
                        'شناسه پرداخت قبلاً استفاده شده است.');
                    return [
                        'subscription' => $duplicate,
                        'duplicate' => true,
                        'activated' => $duplicate->status === CentralTenantSubscription::STATUS_ACTIVE,
                    ];
                }

                $existingPending = CentralTenantSubscription::query()
                    ->where('tenant_id', $tenant->getTenantKey())
                    ->where('status', CentralTenantSubscription::STATUS_PENDING)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();
                if ($existingPending) {
                    return [
                        'subscription' => $existingPending,
                        'duplicate' => true,
                        'activated' => false,
                    ];
                }

                $current = $this->latestSubscription((string) $tenant->getTenantKey(), lock: true);
                $pricing = $this->pricing(lock: true);
                $startsAt = $current?->expires_at?->isFuture()
                    ? $current->expires_at->copy()
                    : $paidAt->copy();
                $expiresAt = $startsAt->copy()->addDays((int) $plan->duration_days)->endOfDay();
                $price = $this->quote($plan, $licensedUsers, $pricing);
                $activated = $price === 0;

                $subscription = CentralTenantSubscription::create([
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'duration_days' => (int) $plan->duration_days,
                    'licensed_users' => $licensedUsers,
                    'included_users' => (int) $pricing->included_users,
                    'base_price' => (int) $plan->base_price,
                    'extra_user_price' => (int) $pricing->extra_user_price,
                    'price_paid' => $price,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'paid_at' => $activated ? $paidAt : null,
                    'status' => $activated
                        ? CentralTenantSubscription::STATUS_ACTIVE
                        : CentralTenantSubscription::STATUS_PENDING,
                    'payment_reference' => $paymentReference,
                    'renewed_from_id' => $current?->id,
                ]);

                if ($activated) {
                    $tenant->update([
                        'plan_id' => $plan->id,
                        'user_count' => $licensedUsers,
                    ]);
                }

                return ['subscription' => $subscription, 'duplicate' => false, 'activated' => $activated];
            });
        });
    }

    public function approvePending(CentralTenantSubscription $pending): CentralTenantSubscription
    {
        $tenant = $this->central(fn () => Tenant::query()->findOrFail($pending->tenant_id));
        $actualUsers = $this->actualActiveUsers($tenant);
        abort_if((int) $pending->licensed_users < $actualUsers, 422,
            "این درخواست برای {$pending->licensed_users} کاربر ثبت شده، اما اکنون {$actualUsers} کاربر فعال وجود دارد.");

        return $this->central(function () use ($pending): CentralTenantSubscription {
            return DB::transaction(function () use ($pending): CentralTenantSubscription {
                $pending = CentralTenantSubscription::query()->lockForUpdate()->findOrFail($pending->id);
                abort_unless($pending->status === CentralTenantSubscription::STATUS_PENDING, 422,
                    'این درخواست قبلاً بررسی شده است.');

                $tenant = Tenant::query()->findOrFail($pending->tenant_id);
                $current = $this->latestSubscription((string) $tenant->getTenantKey(), lock: true);
                $startsAt = $current?->expires_at?->isFuture() ? $current->expires_at->copy() : now();
                $pending->update([
                    'starts_at' => $startsAt,
                    'expires_at' => $startsAt->copy()->addDays($pending->duration_days)->endOfDay(),
                    'paid_at' => now(),
                    'status' => CentralTenantSubscription::STATUS_ACTIVE,
                    'renewed_from_id' => $current?->id,
                ]);
                CentralTenantSubscription::query()
                    ->where('tenant_id', $tenant->getTenantKey())
                    ->where('status', CentralTenantSubscription::STATUS_PENDING)
                    ->where('id', '!=', $pending->id)
                    ->update(['status' => CentralTenantSubscription::STATUS_CANCELLED, 'updated_at' => now()]);
                $tenant->update([
                    'plan_id' => $pending->plan_id,
                    'user_count' => $pending->licensed_users,
                ]);

                return $pending->fresh();
            });
        });
    }

    public function rejectPending(CentralTenantSubscription $pending): CentralTenantSubscription
    {
        return $this->central(function () use ($pending): CentralTenantSubscription {
            $pending = CentralTenantSubscription::query()->findOrFail($pending->id);
            abort_unless($pending->status === CentralTenantSubscription::STATUS_PENDING, 422,
                'این درخواست قبلاً بررسی شده است.');
            $pending->update(['status' => CentralTenantSubscription::STATUS_CANCELLED]);

            return $pending->fresh();
        });
    }

    public function assignFromCentral(Tenant $tenant, ?int $planId, int $licensedUsers): void
    {
        if (! $planId) {
            $this->central(fn () => CentralTenantSubscription::query()
                ->where('tenant_id', $tenant->getTenantKey())
                ->whereIn('status', [
                    CentralTenantSubscription::STATUS_ACTIVE,
                    CentralTenantSubscription::STATUS_PENDING,
                    CentralTenantSubscription::STATUS_EXPIRED,
                ])
                ->update(['status' => CentralTenantSubscription::STATUS_CANCELLED, 'updated_at' => now()]));
            return;
        }

        $this->central(function () use ($tenant, $planId, $licensedUsers): void {
            $plan = CentralBillingPlan::query()->find($planId);
            if (! $plan) return;
            CentralTenantSubscription::query()
                ->where('tenant_id', $tenant->getTenantKey())
                ->where('status', CentralTenantSubscription::STATUS_PENDING)
                ->update(['status' => CentralTenantSubscription::STATUS_CANCELLED, 'updated_at' => now()]);
            $current = $this->latestSubscription((string) $tenant->getTenantKey());
            if ($current
                && (int) $current->plan_id === (int) $plan->id
                && $current->expires_at->isFuture()) {
                $current->update(['licensed_users' => max(1, $licensedUsers)]);
                return;
            }

            $pricing = $this->pricing();
            CentralTenantSubscription::create([
                'tenant_id' => (string) $tenant->getTenantKey(),
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'duration_days' => (int) $plan->duration_days,
                'licensed_users' => max(1, $licensedUsers),
                'included_users' => (int) $pricing->included_users,
                'base_price' => (int) $plan->base_price,
                'extra_user_price' => (int) $pricing->extra_user_price,
                'price_paid' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addDays((int) $plan->duration_days)->endOfDay(),
                'paid_at' => now(),
                'status' => CentralTenantSubscription::STATUS_ACTIVE,
                'payment_reference' => 'admin-'.(string) $tenant->getTenantKey().'-'.now()->format('YmdHisv'),
                'renewed_from_id' => $current?->id,
            ]);
        });
    }

    public function actualActiveUsers(Tenant $tenant): int
    {
        $count = function (): int {
            if (! Schema::hasTable('users')) return 0;
            $query = User::query();
            if (Schema::hasColumn('users', 'access_blocked')) {
                $query->where(fn ($q) => $q->whereNull('access_blocked')->orWhere('access_blocked', false));
            }
            return $query->count();
        };

        if ((string) tenant('id') === (string) $tenant->getTenantKey()) {
            return $count();
        }

        $previous = tenant();
        try {
            tenancy()->initialize($tenant);
            return $count();
        } finally {
            tenancy()->end();
            if ($previous && (string) $previous->getTenantKey() !== (string) $tenant->getTenantKey()) {
                tenancy()->initialize($previous);
            }
        }
    }

    public function expireDue(): int
    {
        return $this->central(fn () => CentralTenantSubscription::query()
            ->where('status', CentralTenantSubscription::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update(['status' => CentralTenantSubscription::STATUS_EXPIRED, 'updated_at' => now()]));
    }

    private function latestSubscription(string $tenantId, bool $lock = false): ?CentralTenantSubscription
    {
        if (! Schema::hasTable('central_tenant_subscriptions')) return null;
        $query = CentralTenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [CentralTenantSubscription::STATUS_ACTIVE, CentralTenantSubscription::STATUS_EXPIRED])
            ->latest('expires_at')
            ->latest('id');
        if ($lock) $query->lockForUpdate();

        return $query->first();
    }

    private function pendingRenewalData(string $tenantId): ?array
    {
        if (! Schema::hasTable('central_tenant_subscriptions')) return null;
        $pending = CentralTenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CentralTenantSubscription::STATUS_PENDING)
            ->latest('id')
            ->first();
        if (! $pending) return null;

        return [
            'id' => $pending->id,
            'plan_id' => $pending->plan_id,
            'plan_name' => $pending->plan_name,
            'duration_days' => $pending->duration_days,
            'licensed_users' => $pending->licensed_users,
            'price' => $pending->price_paid,
            'requested_at' => optional($pending->created_at)->toDateTimeString(),
        ];
    }

    private function pricing(bool $lock = false): CentralUserPricing
    {
        $query = CentralUserPricing::query();
        if ($lock) $query->lockForUpdate();

        return $query->firstOrCreate([], ['included_users' => 1, 'extra_user_price' => 0]);
    }

    private function central(callable $callback): mixed
    {
        return tenancy()->initialized ? tenancy()->central($callback) : $callback();
    }
}
