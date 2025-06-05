<?php

use App\Http\Controllers\AlbionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\IndexController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', [IndexController::class,'index'])->name('home');


Route::get('/auth/google', [GoogleAuthController::class, 'login'])->name('auth.google.login');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
    


Route::get('/api/albion/items/list', [AlbionController::class, 'getItemListDataJson'])->name('api.albion.items.list');
Route::get('/api/albion/item/{itemId}', [AlbionController::class, 'getItemDetails'])->name('api.albion.item');
Route::get('/api/albion/crafting/{itemId}', [AlbionController::class, 'getCraftingInfo'])->name('api.albion.crafting');
Route::get('/api/albion/craftable/{itemId}', [AlbionController::class, 'getItemsToCraft'])->name('api.albion.craftable');
Route::get('/api/albion/resources', [AlbionController::class, 'getResourcesData'])->name('api.albion.resources');
Route::get('/api/albion/equipment', [AlbionController::class, 'getEquipmentData'])->name('api.albion.equipment');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::controller(AlbionController::class)->prefix('albion')->name('albion.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/prices', 'getItemPrices')->name('prices');
        Route::get('/item/{itemId}', 'itemDetail')->name('item.detail');
        Route::get('/crafting', 'getCraftingInfo')->name('crafting.info');
        Route::get('/favorites', 'favorites')->name('favorites');
        Route::get('/calculator', 'calculator')->name('calculator');
        Route::get('/black-market', 'blackMarket')->name('black-market');
        Route::get('/resources', 'resources')->name('resources');
        Route::get('/equipment', 'equipment')->name('equipment');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
