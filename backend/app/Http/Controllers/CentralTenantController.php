<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ModuleSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Stancl\Tenancy\Database\Models\Domain;

class CentralTenantController extends Controller
{
    public function __construct(private readonly ModuleSubscriptionService $subscriptions)
    {
    }

    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->with('domains')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tenant $tenant) => $this->tenantData($tenant));

        return response()->json(['tenants' => $tenants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('tenants', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', Rule::unique('domains', 'domain')],
            'seed' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'plan_id' => ['nullable', 'integer', 'exists:central_billing_plans,id'],
            'user_count' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'module_ids' => ['sometimes', 'array'],
            'module_ids.*' => ['string', 'max:120'],
        ]);

        $tenant = Tenant::create([
            'id' => Str::lower($data['id']),
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
            'plan_id' => $data['plan_id'] ?? null,
            'user_count' => $data['user_count'] ?? 1,
            'module_ids' => $data['module_ids'] ?? [],
        ]);

        $tenant->domains()->create(['domain' => Str::lower($data['domain'])]);

        if ($data['seed'] ?? true) {
            Artisan::call('tenants:seed', [
                '--tenants' => [$tenant->getTenantKey()],
                '--force' => true,
            ]);
        }

        return response()->json([
            'message' => 'سیستم جدید ساخته شد.',
            'tenant' => $this->tenantData($tenant->fresh('domains')),
        ], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
            'plan_id' => ['sometimes', 'nullable', 'integer', 'exists:central_billing_plans,id'],
            'user_count' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'module_ids' => ['sometimes', 'array'],
            'module_ids.*' => ['string', 'max:120'],
        ]);

        $tenant->update($data);

        return response()->json([
            'message' => 'سیستم به‌روزرسانی شد.',
            'tenant' => $this->tenantData($tenant->fresh('domains')),
        ]);
    }

    public function storeDomain(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', Rule::unique('domains', 'domain')],
        ]);

        $tenant->domains()->create(['domain' => Str::lower($data['domain'])]);

        return response()->json([
            'message' => 'دامنه اضافه شد.',
            'tenant' => $this->tenantData($tenant->fresh('domains')),
        ], 201);
    }

    public function destroyDomain(Tenant $tenant, Domain $domain): JsonResponse
    {
        abort_unless($domain->tenant_id === $tenant->getTenantKey(), 404);

        $domain->delete();

        return response()->json([
            'message' => 'دامنه حذف شد.',
            'tenant' => $this->tenantData($tenant->fresh('domains')),
        ]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json(['message' => 'سیستم و دیتابیس tenant حذف شد.']);
    }

    private function tenantData(Tenant $tenant): array
    {
        $legacyModules = is_array($tenant->module_ids ?? null) ? $tenant->module_ids : [];
        $subscriptionModules = $this->subscriptions->activeModuleIds($tenant->getTenantKey());

        return [
            'id' => $tenant->getTenantKey(),
            'name' => $tenant->name ?? $tenant->getTenantKey(),
            'status' => $tenant->status ?? 'active',
            'database' => $tenant->database()->getName(),
            'plan_id' => $tenant->plan_id ?? null,
            'user_count' => $tenant->user_count ?? 1,
            'module_ids' => array_values(array_unique([...$legacyModules, ...$subscriptionModules])),
            'module_subscriptions' => $this->moduleSubscriptions($tenant),
            'domains' => $tenant->domains
                ->map(fn (Domain $domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'url' => $this->domainUrl($domain->domain),
                ])
                ->values(),
            'created_at' => optional($tenant->created_at)->toDateTimeString(),
        ];
    }

    private function domainUrl(string $domain): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$domain.'/';
    }

    private function moduleSubscriptions(Tenant $tenant): array
    {
        return \App\Models\CentralModuleSubscription::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('status', \App\Models\CentralModuleSubscription::STATUS_ACTIVE)
            ->orderByRaw('expires_at is null desc')
            ->orderBy('expires_at')
            ->get()
            ->map(fn ($subscription) => [
                'id' => $subscription->id,
                'module_key' => $subscription->module_key,
                'module_title' => $subscription->module_title,
                'billing_period' => $subscription->billing_period,
                'duration_days' => $subscription->duration_days,
                'price_paid' => $subscription->price_paid,
                'starts_at' => optional($subscription->starts_at)->toDateTimeString(),
                'expires_at' => optional($subscription->expires_at)->toDateTimeString(),
                'status' => $subscription->status,
            ])
            ->values()
            ->all();
    }
}
