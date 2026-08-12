<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Support\ActivityLogger;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'permission_groups' => config('roles.groups', []),
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->with('permissions:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role) => $this->roleData($role)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateRole($request);

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($data['permissions'] ?? []);
            ActivityLogger::manual('created', 'نقش‌ها و دسترسی‌ها', $role, [], [
                'name' => $role->name,
                'permissions' => $data['permissions'] ?? [],
            ]);

            return $role;
        });

        return response()->json($this->roleData($role->load('permissions')), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validateRole($request, $role);

        if ($role->name === 'مدیر سیستم' && $data['name'] !== $role->name) {
            return response()->json(['message' => 'نام نقش مدیر سیستم قابل تغییر نیست.'], 422);
        }

        $before = [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->values()->all(),
        ];

        DB::transaction(function () use ($role, $data, $before) {
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);
            ActivityLogger::manual('role_permissions_updated', 'نقش‌ها و دسترسی‌ها', $role, $before, [
                'name' => $role->name,
                'permissions' => $data['permissions'] ?? [],
            ]);
        });

        return response()->json($this->roleData($role->load('permissions')));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'مدیر سیستم') {
            return response()->json(['message' => 'نقش مدیر سیستم قابل حذف نیست.'], 422);
        }

        if ($this->roleUsersCount($role) > 0) {
            return response()->json(['message' => 'این نقش به کاربر متصل است؛ ابتدا تخصیص کاربران را تغییر دهید.'], 422);
        }

        ActivityLogger::manual('deleted', 'نقش‌ها و دسترسی‌ها', $role, [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->values()->all(),
        ]);
        $role->delete();

        return response()->json(['message' => 'نقش حذف شد.']);
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:125',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web'))
                    ->ignore($role?->id),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ]);
    }

    private function roleData(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'users_count' => $this->roleUsersCount($role),
            'protected' => $role->name === 'مدیر سیستم',
        ];
    }

    private function roleUsersCount(Role $role): int
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where(app(\Spatie\Permission\PermissionRegistrar::class)->pivotRole, $role->id)
            ->count();
    }
}
