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
    
    /**
     * Renderiza a página de recursos do Albion Online
     */
    public function resources()
    {
        return Inertia::render('Albion/Resources');
    }

    /**
     * Retorna a lista de itens do Albion Online em formato JSON com cache
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemListDataJson()
    {
        return Cache::remember('albion_item_list', 86400, function () {
            $items = Item::whereNot('uniquename', 'like', '%UNIQUE%')
                      ->whereNot('uniquename', 'like', '%SKIN%')
                      ->get();
            return response()->json($items);
        });
    }
    
    /**
     * Retorna a lista de recursos do Albion Online
     */
    public function getResourcesData(Request $request)
    {
        try {
            // Buscar todos os itens da categoria 'resources'
            $resources = Item::where('shop_category', 'resources')
                ->whereNot('uniquename', 'like', '%UNIQUE%')
                ->whereNot('uniquename', 'like', '%SKIN%')
                ->get();
            
            if ($resources->isEmpty()) {
                return response()->json([]);
            }
            
            // Obter os uniquenames dos recursos
            $resourceIds = $resources->pluck('uniquename')->toArray();
            
            // Buscar preços para todos os recursos em todas as cidades
            $prices = $this->priceService->fetchPrices($resources, false);
            
            // Processar os dados para o formato necessário para a tabela
            $processedData = [];
            
            foreach ($resources as $resource) {
                $resourceData = [
                    'id' => $resource->id,
                    'uniquename' => $resource->uniquename,
                    'nicename' => $resource->nicename,
                    'tier' => $resource->tier,
                    'enchantment_level' => $resource->enchantment_level,
                    'shop_category' => $resource->shop_category,
                    'shop_subcategory1' => $resource->shop_subcategory1,
                    'prices' => []
                ];
                
                // Encontrar os preços deste recurso
                $resourcePrices = collect($prices)->where('item_id', $resource->uniquename)->first();
                
                if ($resourcePrices) {
                    // Pegar a qualidade normal (1)
                    $normalQuality = collect($resourcePrices['qualities'])->where('quality', 1)->first();
                    
                    if ($normalQuality) {
                        $cityPrices = [];
                        $cityPriceDates = [];
                        $minPrice = PHP_INT_MAX;
                        $maxPrice = 0;
                        $minCity = '';
                        $maxCity = '';
                        
                        foreach ($normalQuality['cities'] as $cityData) {
                            if ($cityData['city'] === 'Brecilien') {
                                continue;
                            }
                            $sellPrice = $cityData['sell_price_min'] > 0 ? $cityData['sell_price_min'] : null;
                            
                            if ($sellPrice) {
                                $cityPrices[$cityData['city']] = $sellPrice;
                                $cityPriceDates[$cityData['city']] = $cityData['sell_price_min_date'];
                                
                                // Atualizar preço mínimo e máximo
                                if ($sellPrice < $minPrice) {
                                    $minPrice = $sellPrice;
                                    $minCity = $cityData['city'];
                                }
                                
                                if ($sellPrice > $maxPrice) {
                                    $maxPrice = $sellPrice;
                                    $maxCity = $cityData['city'];
                                }
                            } else {
                                $cityPrices[$cityData['city']] = null;
                                $cityPriceDates[$cityData['city']] = null;
                            }
                        }
                        
                        // Calcular oportunidade de flipping
                        $flippingData = null;
                        if ($minPrice < PHP_INT_MAX && $maxPrice > 0 && $minPrice < $maxPrice) {
                            $profit = $maxPrice - $minPrice;
                            $profitPercentage = ($profit / $minPrice) * 100;
                            
                            $flippingData = [
                                'buy_city' => $minCity,
                                'buy_price' => $minPrice,
                                'sell_city' => $maxCity,
                                'sell_price' => $maxPrice,
                                'profit' => $profit,
                                'profit_percentage' => round($profitPercentage, 2)
                            ];
                        }
                        
                        $resourceData['prices'] = $cityPrices;
                        $resourceData['price_dates'] = $cityPriceDates;
                        $resourceData['min_price'] = $minPrice < PHP_INT_MAX ? $minPrice : null;
                        $resourceData['max_price'] = $maxPrice > 0 ? $maxPrice : null;
                        $resourceData['min_city'] = $minCity;
                        $resourceData['max_city'] = $maxCity;
                        $resourceData['flipping'] = $flippingData;
                    }
                }
                
                $processedData[] = $resourceData;
            }
            
            return response()->json($processedData);
            
        } catch (\Exception $e) {
            Log::error("Erro ao buscar dados de recursos: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
