<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\BookingController;
use App\Http\Controllers\V1\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::get('events', [EventController::class, 'index']);
    Route::post('events', [EventController::class, 'store']);
    Route::get('events/{event}', [EventController::class, 'show']);
    Route::put('events/{event}', [EventController::class, 'update']);
    Route::delete('events/{event}', [EventController::class, 'destroy']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::delete('bookings/{booking}', [BookingController::class, 'destroy']);
        Route::post('events/{event}/bookings', [BookingController::class, 'store']);
    });
});
