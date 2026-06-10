<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\SupplyGroups\SupplyGroupManager;
use App\Livewire\VendingKeys\VendingKeyManager;
use App\Livewire\Tariffs\TariffManager;
use App\Livewire\Customers\CustomerManager;
use App\Livewire\Meters\MeterManager;
use App\Livewire\Tokens\TokenManager;

Route::get('/', fn () => view('dashboard'))->name('dashboard');

// Auth middleware will be added once Fortify login views are wired up.
Route::get('/supply-groups', SupplyGroupManager::class)->name('supply-groups.index');
Route::get('/vending-keys',  VendingKeyManager::class)->name('vending-keys.index');
Route::get('/tariffs',       TariffManager::class)->name('tariffs.index');
Route::get('/customers',     CustomerManager::class)->name('customers.index');
Route::get('/meters',        MeterManager::class)->name('meters.index');
Route::get('/tokens',        TokenManager::class)->name('tokens.index');
