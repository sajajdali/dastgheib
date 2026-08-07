<?php

namespace App\Services;

use App\Models\CentralModuleSubscription;
use Illuminate\Support\Carbon;

class ModuleSubscriptionService
{
    public function activeModuleIds(?string $tenantId): array
    {
        if (! $tenantId) {
            return [];
        }

        $now = now();

        return CentralModuleSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CentralModuleSubscription::STATUS_ACTIVE)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->pluck('module_key')
            ->unique()
            ->values()
            ->all();
    }

    public function syncTenantModuleIds(string $tenantId): array
    {
        return $this->activeModuleIds($tenantId);
    }

    public function purchaseOrRenew(string $tenantId, array $item, Carbon $paidAt): CentralModuleSubscription
    {
        $period = $item['billing_period'] ?? 'one_time';
        $durationDays = $this->durationDays($period);
        $current = $this->currentSubscription($tenantId, $item['key']);
        $startsAt = $current?->expires_at && $current->expires_at->isFuture()
            ? $current->expires_at->copy()
            : $paidAt->copy();
        $expiresAt = $durationDays ? $startsAt->copy()->addDays($durationDays) : null;

        if ($current) {
            $current->update(['status' => CentralModuleSubscription::STATUS_EXPIRED]);
        }

        return CentralModuleSubscription::create([
            'tenant_id' => $tenantId,
            'module_key' => $item['key'],
            'module_title' => $item['title'] ?? $item['key'],
            'billing_period' => $period,
            'duration_days' => $durationDays,
            'price_paid' => (int) ($item['price'] ?? 0),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'last_paid_at' => $paidAt,
            'status' => CentralModuleSubscription::STATUS_ACTIVE,
            'renewed_from_id' => $current?->id,
        ]);
    }

    public function expireDue(?Carbon $now = null): int
    {
        $now ??= now();
        $expired = CentralModuleSubscription::query()
            ->where('status', CentralModuleSubscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        $expired->each->update(['status' => CentralModuleSubscription::STATUS_EXPIRED]);
        $expired->pluck('tenant_id')->unique()->each(fn ($tenantId) => $this->syncTenantModuleIds($tenantId));

        return $expired->count();
    }

    public function durationDays(string $period): ?int
    {
        return match ($period) {
            'monthly' => 30,
            'quarterly' => 90,
            'semiannual' => 180,
            'annual' => 365,
            default => null,
        };
    }

    private function currentSubscription(string $tenantId, string $moduleKey): ?CentralModuleSubscription
    {
        return CentralModuleSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('module_key', $moduleKey)
            ->where('status', CentralModuleSubscription::STATUS_ACTIVE)
            ->latest('expires_at')
            ->latest()
            ->first();
    }
}
