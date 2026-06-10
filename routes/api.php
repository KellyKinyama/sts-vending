<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\SupplyGroupController;
use App\Http\Controllers\Api\TariffController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\VendingKeyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());

    Route::apiResource('supply-groups', SupplyGroupController::class);
    Route::apiResource('vending-keys',  VendingKeyController::class);
    Route::apiResource('tariffs',       TariffController::class);
    Route::apiResource('customers',     CustomerController::class);
    Route::apiResource('meters',        MeterController::class);

    Route::get('tokens',                 [TokenController::class, 'index']);
    Route::get('tokens/{token}',         [TokenController::class, 'show']);
    Route::post('tokens',                [TokenController::class, 'issue']);
    Route::post('tokens/{tokenNo}/decode', [TokenController::class, 'decode']);
});

