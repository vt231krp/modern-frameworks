<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\GymClassController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public authentication
|--------------------------------------------------------------------------
*/

Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Authenticated routes (JWT)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Admin-only: user provisioning and role changes.
    Route::middleware('role:admin')->group(function () {
        Route::patch('users/{user}/role', [UserController::class, 'changeRole']);
        Route::apiResource('users', UserController::class);
    });

    // Fine-grained CRUD access is decided by the model policies.
    Route::apiResource('coaches', CoachController::class);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::apiResource('classes', GymClassController::class);
    Route::apiResource('members', MemberController::class);

    Route::get('registrations', [RegistrationController::class, 'index']);
    Route::post('registrations', [RegistrationController::class, 'store']);
    Route::get('registrations/{memberId}/{classId}', [RegistrationController::class, 'show']);
    Route::delete('registrations/{memberId?}/{classId?}', [RegistrationController::class, 'destroy']);
});
