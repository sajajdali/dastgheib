<?php

namespace App\Services\Assistant\Billing;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/** اطلاعات مشترک همهٔ فراخوانی‌های API یک پیام کاربر (یا یک رونویسی). */
final class AiUsageContext
{
    public int $round = 0;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?int $userId,
        public readonly ?string $userName,
        public readonly string $type,
        public ?int $conversationId = null,
        public readonly string $requestGroup = '',
    ) {}

    public static function forCurrentTenant(User $user, string $type, ?int $conversationId = null): self
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        if (! $tenant) throw new RuntimeException('دستیار فقط داخل سایت کلینیک قابل استفاده است.');

        return new self((string) $tenant->getTenantKey(), $user->id, $user->name, $type, $conversationId, (string) Str::uuid());
    }
}
