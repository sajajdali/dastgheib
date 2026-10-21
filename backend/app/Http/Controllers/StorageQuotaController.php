<?php

namespace App\Http\Controllers;

use App\Services\TenantStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StorageQuotaController extends Controller
{
    public function __construct(private readonly TenantStorageService $storage)
    {
    }

    private function central()
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }

    public function status(): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $summary = $this->storage->summary($tenantId);
        if (! $summary['scanned_at']) {
            $summary = $this->storage->reconcileCurrentTenant();
        }

        return response()->json(['storage' => $summary]);
    }

    public function show(): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $summary = $this->storage->summary($tenantId);
        if (! $summary['scanned_at']) {
            $summary = $this->storage->reconcileCurrentTenant();
        }

        return response()->json([
            'storage' => $summary,
            'packages' => $this->central()->table('central_storage_packages')->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get(),
            'orders' => $this->central()->table('central_storage_orders')->where('tenant_id', $tenantId)
                ->orderByDesc('id')->get(),
        ]);
    }

    public function database(): JsonResponse
    {
        return response()->json([
            'database_bytes' => $this->storage->databaseBytes(),
            'measured_at' => now()->toDateTimeString(),
        ]);
    }

    public function order(Request $request): JsonResponse
    {
        $data = $request->validate(['package_id' => ['required', 'integer']]);
        $package = $this->central()->table('central_storage_packages')
            ->where('id', $data['package_id'])->where('is_active', true)->first();
        if (! $package) {
            throw ValidationException::withMessages(['package_id' => 'این بسته در حال حاضر قابل خرید نیست.']);
        }

        $orderId = $this->central()->table('central_storage_orders')->insertGetId([
            'tenant_id' => tenant('id'),
            'package_id' => $package->id,
            'package_name' => $package->name,
            'size_bytes' => (int) $package->size_gb * TenantStorageService::GIB,
            'monthly_price' => $package->monthly_price,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'درخواست ثبت شد. پس از تأیید پرداخت توسط مدیریت، فضای بسته فعال می‌شود.',
            'order' => $this->central()->table('central_storage_orders')->where('id', $orderId)->first(),
        ], 201);
    }
}
