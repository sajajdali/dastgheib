<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CentralAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! Auth::guard('central')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $credentials['remember'] ?? false)) {
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور صحیح نیست.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'ورود با موفقیت انجام شد.',
            'admin' => $this->adminData($request),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['admin' => $this->adminData($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'از پنل مدیریت خارج شدید.']);
    }

    private function adminData(Request $request): array
    {
        $admin = $request->user('central');

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ];
    }
}
