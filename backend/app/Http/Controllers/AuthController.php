<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $data['login'] = $this->normalizeDigits($data['login']);
        $data['password'] = $this->normalizeDigits($data['password']);

        $throttleKey = Str::transliterate(Str::lower($data['login'])).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'تعداد تلاش‌ها بیش از حد مجاز است. کمی بعد دوباره امتحان کنید.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $field = match (true) {
            filter_var($data['login'], FILTER_VALIDATE_EMAIL) !== false => 'email',
            preg_match('/^09\d{9}$/', $data['login']) === 1 => 'mobile',
            default => 'name',
        };

        if (! Auth::attempt([$field => $data['login'], 'password' => $data['password']], $data['remember'] ?? false)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'login' => ['نام کاربری یا رمز عبور صحیح نیست.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'ورود با موفقیت انجام شد.',
            'user' => $this->userData($request),
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'از حساب کاربری خارج شدید.']);
    }

    private function userData(Request $request): array
    {
        $user = $request->user()->load('roles.permissions');
        $tenant = tenant();
        $tenantData = null;

        if ($tenant) {
            $planId = $tenant->plan_id ?? null;
            $plan = $planId
                ? DB::connection(config('tenancy.database.central_connection'))
                    ->table('central_billing_plans')
                    ->where('id', $planId)
                    ->first()
                : null;
            $startedAt = $tenant->created_at;
            $expiresAt = ($plan && $startedAt)
                ? $startedAt->copy()->addDays((int) $plan->duration_days)
                : null;
            $smsBalance = DB::table('app_settings')->where('key', 'sms_credit_balance')->value('value');

            $subscriptionModules = DB::connection(config('tenancy.database.central_connection'))
                ->table('central_module_subscriptions')
                ->where('tenant_id', tenant('id'))
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->pluck('module_key')
                ->unique()
                ->values()
                ->all();
            $legacyModules = is_array(tenant('module_ids')) ? tenant('module_ids') : [];

            $tenantData = [
                'id' => tenant('id'),
                'name' => $tenant->name ?? tenant('id'),
                'status' => $tenant->status ?? 'active',
                'module_ids' => array_values(array_unique([...$legacyModules, ...$subscriptionModules])),
                'plan_id' => $planId,
                'plan' => $plan ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'duration_days' => (int) $plan->duration_days,
                    'expires_at' => optional($expiresAt)->toDateString(),
                    'days_remaining' => $expiresAt ? max(0, now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)) : null,
                ] : null,
                'sms_balance' => $smsBalance,
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'tenant' => $tenantData,
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }
}
