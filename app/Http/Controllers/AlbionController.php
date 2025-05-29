<?php
/**
 * Albion Online Price Checker Controller
 *
 * Este controlador gerencia as funcionalidades relacionadas à consulta
 * de preços de itens do Albion Online através da API pública.
 *
 * @package App\Http\Controllers
 */

namespace App\Http\Controllers;

use App\Enums\City;
use App\Enums\Quality;
use App\Models\AlbionCraft;
use App\Models\AlbionMaterial;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Services\AlbionPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para gerenciar consultas de preços de itens do Albion Online
 */
class AlbionController extends Controller
{
    protected AlbionPriceService $priceService;
    
    public function __construct(AlbionPriceService $priceService)
    {
        $this->priceService = $priceService;
    }

    public function index()
    {
        return Inertia::render('Albion/Index');
    }

    public function itemDetail(Request $request, $itemId)
    {
        if (!$itemId) {
            return redirect()->route('Albion.index');
        }
        
        return Inertia::render('Albion/ItemDetail');
    }

    public function favorites()
    {
        return Inertia::render('Albion/Favorites');
    }


    public function calculator()
    {
        return Inertia::render('Albion/Calculator');
    }


    public function blackMarket()
    {
        return Inertia::render('Albion/BlackMarket');
    }

    public function getItemListDataJson()
    {
        $items = Item::all();
        return response()->json($items);
    }


    public function getItemDetails(Request $request, $itemId = null)
    {
        // Se não vier da rota, tenta pegar do request
        if (!$itemId) {
            $itemId = $request->input('itemId');
        }
        
        if (!$itemId) {
            return response()->json(['error' => 'No item ID provided'], 400);
        }
        
        // Carregar o arquivo items.json
        $itemsPath = public_path('items.json');
        
        if (!File::exists($itemsPath)) {
            return response()->json(['error' => 'Items data not found'], 404);
        }
        
        $items = json_decode(File::get($itemsPath), true);
        
        // Procurar o item pelo UniqueName
        $item = null;
        foreach ($items as $itemData) {
            if ($itemData['UniqueName'] === $itemId) {
                $item = [
                    'id' => $itemData['Index'] ?? '',
                    'name' => $itemData['LocalizedNames']['EN-US'] ?? $itemData['UniqueName'],
                    'localizedNames' => $itemData['LocalizedNames'] ?? [],
                    'uniqueName' => $itemData['UniqueName']
                ];
                break;
            }
        }
        
        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }
        
        // Verificar se o item está no cache
        $cacheKey = "item_details_{$itemId}";
        $cachedItem = Cache::get($cacheKey);
        
        if ($cachedItem) {
            return response()->json($cachedItem);
        }
        
        // Se não estiver no cache, salvar no cache por 1 hora
        Cache::put($cacheKey, $item, now()->addHour());
        
        return response()->json($item);
    }

    public function getItemPrices(Request $request)
    {
        try {
            $itemIds = $request->input('items');
            
            if (!$itemIds || !is_array($itemIds)) {
                return response()->json(['error' => 'No items provided'], 400);
            }
            
            $locations = $request->input('locations');
            $qualities = $request->input('qualities');
            $forceRefresh = $request->boolean('forceRefresh', false);
            
            // Verificar se os itens existem no banco de dados
            $items = Item::whereIn('uniquename', $itemIds)->get();
            
            // Se não encontrar todos os itens solicitados no banco, buscar apenas os que existem
            if ($items->count() < count($itemIds)) {
                $foundItemIds = $items->pluck('uniquename')->toArray();
                $missingItemIds = array_diff($itemIds, $foundItemIds);
                
                if (!empty($missingItemIds)) {
                    Log::warning('Alguns itens solicitados não foram encontrados no banco de dados', [
                        'missing_items' => $missingItemIds
                    ]);
                }
                
                // Se não encontrar nenhum item, retornar array vazio
                if ($items->isEmpty()) {
                    return response()->json([]);
                }
            }
            
            // Buscar preços usando o serviço com cache (ou forçar atualização se solicitado)
            $prices = $this->priceService->fetchPrices($items, !$forceRefresh);
            
            if (empty($prices)) {
                return response()->json([]);
            }
            
            // Salvar os preços no banco de dados
            $this->priceService->savePricesToDatabase($prices);
            
            // Filtrar os resultados conforme os parâmetros da requisição
            $filteredData = $this->filterPriceData($prices, $locations, $qualities);
            
            return response()->json($filteredData);
        } catch (\Exception $e) {
            Log::error("Erro ao buscar preços da API Albion: " . $e->getMessage(), [
                'items' => $request->input('items'),
                'locations' => $request->input('locations')
            ]);
            
            // Retornar array vazio em vez de erro para não quebrar o frontend
            return response()->json([]);
        }
    }
    
    private function filterPriceData(array $prices, $locations = null, $qualities = null): array
    {
        $result = [];
        
        // Converter para arrays se forem strings
        $locationArray = is_string($locations) ? explode(',', $locations) : $locations;
        $qualityArray = is_string($qualities) ? explode(',', $qualities) : $qualities;
        
        foreach ($prices as $itemData) {
            $itemId = $itemData['item_id'];
            
            foreach ($itemData['qualities'] as $qualityData) {
                $quality = $qualityData['quality'];
                
                // Filtrar por qualidade se especificado
                if ($qualityArray && !in_array($quality, $qualityArray)) {
                    continue;
                }
                
                foreach ($qualityData['cities'] as $cityData) {
                    $city = $cityData['city'];
                    
                    // Filtrar por localização se especificado
                    if ($locationArray && !in_array($city, $locationArray)) {
                        continue;
                    }
                    
                    // Formatar os dados no formato esperado pelo frontend
                    $result[] = [
                        'item_id' => $itemId,
                        'city' => $city,
                        'quality' => $quality,
                        'sell_price_min' => $cityData['sell_price_min'],
                        'sell_price_min_date' => $cityData['sell_price_min_date'],
                        'sell_price_max' => $cityData['sell_price_max'],
                        'sell_price_max_date' => $cityData['sell_price_max_date'],
                        'buy_price_min' => $cityData['buy_price_min'],
                        'buy_price_min_date' => $cityData['buy_price_min_date'],
                        'buy_price_max' => $cityData['buy_price_max'],
                        'buy_price_max_date' => $cityData['buy_price_max_date']
                    ];
                }
            }
        }
        
        return $result;
    }
}
