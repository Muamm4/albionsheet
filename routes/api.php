<?php

use App\Http\Controllers\AlbionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rotas para o Albion Online
Route::get('/albion/item/{itemId}', [AlbionController::class, 'getItemDetails']);
Route::post('/albion/prices', [AlbionController::class, 'getItemPrices']);
Route::get('/albion/crafting/{itemId}', [AlbionController::class, 'getCraftingInfo']);
Route::get('/albion/items-to-craft/{itemId}', [AlbionController::class, 'getItemsToCraft']);

// Rota de teste para o cache de preços
Route::get('/albion/test-price-cache/{itemId}', function ($itemId) {
    $priceService = app(\App\Services\AlbionPriceService::class);
    $item = \App\Models\Item::where('uniquename', $itemId)->first();
    
    if (!$item) {
        return response()->json(['error' => 'Item não encontrado'], 404);
    }
    
    // Testar busca com cache
    $start = microtime(true);
    $pricesWithCache = $priceService->fetchPrices([$item], true);
    $timeWithCache = microtime(true) - $start;
    
    // Testar busca sem cache
    $start = microtime(true);
    $pricesWithoutCache = $priceService->fetchPrices([$item], false);
    $timeWithoutCache = microtime(true) - $start;
    
    return response()->json([
        'item' => $item->uniquename,
        'with_cache' => [
            'time' => $timeWithCache,
            'data' => $pricesWithCache
        ],
        'without_cache' => [
            'time' => $timeWithoutCache,
            'data' => $pricesWithoutCache
        ],
        'cache_improvement' => $timeWithoutCache > 0 ? ($timeWithoutCache - $timeWithCache) / $timeWithoutCache * 100 : 0
    ]);
});
