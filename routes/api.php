<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Http\Middleware\SetCurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('reservations')->group(function () {

    Route::post('/', [ReservationController::class,'store',])->middleware(RequireIdempotencyKey::class);

    Route::post('/{reservation}/confirm', [ReservationController::class,'confirm',])->middleware(RequireIdempotencyKey::class);

    Route::post('/{reservation}/cancel', [ReservationController::class,'cancel',])->middleware(RequireIdempotencyKey::class);

    Route::put('/{reservation}', [ReservationController::class,'update',])->middleware(RequireIdempotencyKey::class);

    Route::get('/{reservation}', [ReservationController::class,'show',]);
});

Route::prefix('resources')->group(function () 
{ 
    // Check resource availability. 
    Route::get( '/{resource}/availability', [AvailabilityController::class, 'show'] ); 
    // Admin updates resource capacity.
     Route::put( '/{resource}/capacity', [ResourceController::class, 'updateCapacity'] )->middleware(SetCurrentUser::class); 
    });