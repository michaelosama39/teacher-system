<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::post('register', [RegistrationController::class, 'store'])
    ->middleware(['feature:self_registration', 'throttle:registration'])
    ->name('register');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
|
| "active" revokes tokens of deactivated users; "password.changed" blocks
| everything except me/logout/change-password while the password is temporary.
|
*/

Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function (): void {
    Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function (): void {
        Route::get('me', 'me')->name('me');
        Route::post('logout', 'logout')->name('logout');
        Route::post('change-password', 'changePassword')->name('change-password');
    });

    Route::prefix('assistants')
        ->name('assistants.')
        ->middleware('can:manage-assistants')
        ->controller(AssistantController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('permissions', 'permissions')->name('permissions');
            Route::post('/', 'store')->name('store');
            Route::put('{assistant}/permissions', 'updatePermissions')->name('permissions.update');
            Route::post('{assistant}/reset-password', 'resetPassword')->name('reset-password');
            Route::post('{assistant}/deactivate', 'deactivate')->name('deactivate');
            Route::post('{assistant}/activate', 'activate')->name('activate');
        });

    Route::prefix('students')->name('students.')->controller(StudentController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{student}', 'show')->name('show');
        Route::patch('{student}', 'update')->name('update');
        Route::post('{student}/regenerate-qr', 'regenerateQr')->name('regenerate-qr');
        Route::post('{student}/approve', 'approve')->name('approve');
        Route::post('{student}/reject', 'reject')->name('reject');
    });
});
