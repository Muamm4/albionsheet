<?php

use App\Http\Controllers\AlbionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/api/albion/item/{itemId}', [AlbionController::class, 'getItemDetails'])->name('api.albion.item');
Route::get('/api/albion/crafting/{itemId}', [AlbionController::class, 'getCraftingInfo'])->name('api.albion.crafting');
Route::get('/api/albion/craftable/{itemId}', [AlbionController::class, 'getItemsToCraft'])->name('api.albion.craftable');

Route::get('/albion/favorites', [AlbionController::class, 'favorites'])->name('albion.favorites');
Route::get('/albion/calculator', [AlbionController::class, 'calculator'])->name('albion.calculator');
Route::get('/albion/black-market', [AlbionController::class, 'blackMarket'])->name('albion.black-market');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::controller(AlbionController::class)->prefix('albion')->name('albion.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/prices', 'getItemPrices')->name('prices');
        Route::get('/item/{itemId}', 'itemDetail')->name('item.detail');
        Route::get('/crafting', 'getCraftingInfo')->name('crafting.info');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
