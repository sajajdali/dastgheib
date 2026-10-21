<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CentralStorageController extends Controller
{
    public function __construct(private readonly TenantStorageService $storage)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'packages' => DB::table('central_storage_packages')->orderBy('sort_order')->orderBy('id')->get(),
            'orders' => DB::table('central_storage_orders')->orderByDesc('id')->limit(200)->get(),
        ]);
    }

    public function savePackage(Request $request, ?int $package = null): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size_gb' => ['required', 'integer', 'min:1', 'max:10000'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $data['updated_at'] = now();
        if ($package) {
            DB::table('central_storage_packages')->where('id', $package)->update($data);
            $id = $package;
        } else {
            $data['created_at'] = now();
            $id = DB::table('central_storage_packages')->insertGetId($data);
        }

        return response()->json(['package' => DB::table('central_storage_packages')->find($id)]);
    }

    public function decideOrder(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'rejected'])]]);
        $connection = DB::connection();
        $result = $connection->transaction(function () use ($connection, $order, $data) {
            $row = $connection->table('central_storage_orders')->where('id', $order)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'pending', 422, 'این درخواست قبلاً بررسی شده است.');
            $update = ['status' => $data['status'], 'decided_at' => now(), 'updated_at' => now()];
            if ($data['status'] === 'active') {
                $update['starts_at'] = now();
                $update['expires_at'] = now()->addMonthNoOverflow();
            }
            $connection->table('central_storage_orders')->where('id', $order)->update($update);
            return $connection->table('central_storage_orders')->find($order);
        });

        return response()->json(['order' => $result]);
    }

    public function renewOrder(int $order): JsonResponse
    {
        $result = DB::transaction(function () use ($order) {
            $row = DB::table('central_storage_orders')->where('id', $order)->lockForUpdate()->first();
            abort_unless($row && $row->status === 'active', 422, 'بسته فعال یافت نشد.');
            $start = max(now()->timestamp, strtotime($row->expires_at));
            $expiresAt = \Illuminate\Support\Carbon::createFromTimestamp($start)->addMonthNoOverflow();
            DB::table('central_storage_orders')->where('id', $order)->update([
                'expires_at' => $expiresAt, 'updated_at' => now(),
            ]);
            return DB::table('central_storage_orders')->find($order);
        });

        return response()->json(['order' => $result]);
    }

    public function reconcile(Tenant $tenant): JsonResponse
    {
        return response()->json(['storage' => $this->storage->reconcileTenant($tenant)]);
    }
}
