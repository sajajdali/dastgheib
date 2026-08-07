<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CentralAuthController;
use App\Http\Controllers\CentralBillingController;
use App\Http\Controllers\CentralServiceTicketController;
use App\Http\Controllers\CentralTenantController;

foreach (config('tenancy.central_domains', ['localhost']) as $centralDomain) {
    Route::domain($centralDomain)->group(function () {
        Route::prefix('central-api')->group(function () {
            Route::post('/login', [CentralAuthController::class, 'login'])->middleware('guest:central');
            Route::post('/logout', [CentralAuthController::class, 'logout'])->middleware('central.admin');
            Route::get('/me', [CentralAuthController::class, 'me'])->middleware('central.admin');

            Route::middleware('central.admin')->group(function () {
                Route::get('/billing', [CentralBillingController::class, 'index']);
                Route::post('/billing/plans', [CentralBillingController::class, 'storePlan']);
                Route::patch('/billing/plans/{plan}', [CentralBillingController::class, 'updatePlan']);
                Route::delete('/billing/plans/{plan}', [CentralBillingController::class, 'destroyPlan']);
                Route::patch('/billing/user-pricing', [CentralBillingController::class, 'updateUserPricing']);
                Route::post('/billing/discounts', [CentralBillingController::class, 'storeDiscount']);
                Route::patch('/billing/discounts/{discount}', [CentralBillingController::class, 'updateDiscount']);
                Route::delete('/billing/discounts/{discount}', [CentralBillingController::class, 'destroyDiscount']);
                Route::get('/store-terms', [CentralBillingController::class, 'storeTerms']);
                Route::patch('/store-terms', [CentralBillingController::class, 'updateStoreTerms']);

                Route::get('/tenants', [CentralTenantController::class, 'index']);
                Route::post('/tenants', [CentralTenantController::class, 'store']);
                Route::patch('/tenants/{tenant}', [CentralTenantController::class, 'update']);
                Route::post('/tenants/{tenant}/domains', [CentralTenantController::class, 'storeDomain']);
                Route::delete('/tenants/{tenant}/domains/{domain}', [CentralTenantController::class, 'destroyDomain']);
                Route::delete('/tenants/{tenant}', [CentralTenantController::class, 'destroy']);

                Route::get('/service-tickets', [CentralServiceTicketController::class, 'centralIndex']);
                Route::post('/service-tickets/{ticket}', [CentralServiceTicketController::class, 'centralUpdate']);
                Route::patch('/service-tickets/{ticket}', [CentralServiceTicketController::class, 'centralUpdate']);
            });
        });

        Route::get('/', function () {
            $frontend = public_path('app.html');

            return is_file($frontend)
                ? response()->file($frontend)
                : view('welcome');
        });
    });
}
