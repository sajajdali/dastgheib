<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::get('/', function () {
    $frontend = public_path('app.html');

    return is_file($frontend)
        ? response()->file($frontend)
        : view('welcome');
});
