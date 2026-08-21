<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
])->group(function () {
    Route::middleware('web')->group(function () {
        Route::get('/csrf-cookie', fn () => response()->noContent())->name('tenant.csrf-cookie');
        Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

        Route::get('/', function () {
            $frontend = public_path('app.html');

            return is_file($frontend)
                ? response()->file($frontend, [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ])
                : view('welcome');
        });
    });

    Route::middleware(['web', 'api'])
        ->prefix('api')
        ->group(base_path('routes/api.php'));
});
