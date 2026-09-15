<?php

use App\Models\User;
use App\Models\DynamicReport;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('clinic.online', function (User $user): array {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'roles' => $user->getRoleNames()->values()->all(),
    ];
});

Broadcast::channel('clinic.{tenantId}.appointments', function (User $user, string $tenantId): bool {
    return (string) tenant('id') === $tenantId;
});

Broadcast::channel('clinic.{tenantId}.reports.{reportId}', function (User $user, string $tenantId, string $reportId): bool {
    if ((string) tenant('id') !== $tenantId) return false;
    return DynamicReport::query()->whereKey($reportId)->where('user_id', $user->id)->exists();
});
