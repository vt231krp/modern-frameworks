<?php

use App\Http\Controllers\CoachController;
use App\Http\Controllers\GymClassController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('coaches', CoachController::class);
Route::apiResource('members', MemberController::class);
Route::apiResource('subscriptions', SubscriptionController::class);
Route::apiResource('classes', GymClassController::class);

Route::get('registrations', [RegistrationController::class, 'index']);
Route::post('registrations', [RegistrationController::class, 'store']);
Route::get('registrations/{memberId}/{classId}', [RegistrationController::class, 'show']);
Route::delete('registrations/{memberId?}/{classId?}', [RegistrationController::class, 'destroy']);