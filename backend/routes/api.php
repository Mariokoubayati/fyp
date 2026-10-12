<?php

use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\RefreshTokenController;
use App\Http\Controllers\Api\V1\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');

        Route::middleware('auth:api')->group(function (): void {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('refresh', RefreshTokenController::class)->name('refresh');
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', CurrentUserController::class)->name('me');
    });
});
