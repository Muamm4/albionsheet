<?php

/**
 * Rotas da aplicação web Albion Sheet
 *
 * @package AlbionSheet
 */

use App\Http\Controllers\AlbionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Albion Online routes
Route::get('/albion', [AlbionController::class, 'index'])->name('albion.index');
Route::post('/albion/prices', [AlbionController::class, 'getItemPrices'])->name('albion.prices');

// Novas rotas para detalhes do item e informações de crafting
Route::get('/albion/item/{itemId}', [AlbionController::class, 'itemDetail'])->name('albion.item.detail');
Route::get('/albion/crafting', [AlbionController::class, 'getCraftingInfo'])->name('albion.crafting.info');

// API routes para o novo formato
Route::get('/api/albion/item/{itemId}', [AlbionController::class, 'getItemDetails'])->name('api.albion.item');
Route::get('/api/albion/crafting/{itemId}', [AlbionController::class, 'getCraftingInfo'])->name('api.albion.crafting');
Route::get('/api/albion/craftable/{itemId}', [AlbionController::class, 'getItemsToCraft'])->name('api.albion.craftable');

// Novas páginas com barra lateral
Route::get('/albion/favorites', [AlbionController::class, 'favorites'])->name('albion.favorites');
Route::get('/albion/calculator', [AlbionController::class, 'calculator'])->name('albion.calculator');
Route::get('/albion/black-market', [AlbionController::class, 'blackMarket'])->name('albion.black-market');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
