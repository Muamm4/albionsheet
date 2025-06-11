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
use App\Models\ItemMaterial;
use App\Models\ItemPrice;
use App\Models\ItemStat;
use App\Services\AlbionPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

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
            return redirect()->route('albion.index');
        }
        
        try {
            // Buscar o item pelo ID ou uniquename
            $item = is_numeric($itemId) 
                ? Item::with(['prices', 'materials.prices', 'stats'])->find($itemId)
                : Item::with(['prices', 'materials.prices', 'stats'])->where('uniquename', $itemId)->first();
            
            if (!$item) {
                return redirect()->route('albion.index')->with('error', 'Item não encontrado');
            }
            
            // Buscar os dados detalhados do item usando o método getItemDetails
            $response = $this->getItemDetails($request, $itemId);
            $itemData = json_decode($response->getContent(), true);
            
            // Renderizar a página com os dados do item
            return Inertia::render('Albion/ItemDetail', [
                'item' => $itemData
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao buscar detalhes do item para a página: " . $e->getMessage(), [
                'item_id' => $itemId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('albion.index')->with('error', 'Erro ao carregar detalhes do item');
        }
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
     * Retorna oportunidades de flipping entre cidades e Black Market
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getBlackMarketOpportunities(Request $request): JsonResponse
    {
        try {
            // Obter todos os preços do Black Market
            $blackMarketPrices = ItemPrice::where('city', City::BlackMarket)
                ->whereNotNull('buy_price_min')
                ->where('buy_price_min', '>', 0)
                ->with('item')
                ->get();
                
            if ($blackMarketPrices->isEmpty()) {
                return response()->json([], 200);
            }
            
            $opportunities = [];
            
            foreach ($blackMarketPrices as $blackMarketPrice) {
                $item = $blackMarketPrice->item;
                if (!$item) continue;
                
                // Buscar os preços deste item em todas as cidades (exceto Black Market)
                $cityPrices = ItemPrice::where('item_id', $item->id)
                    ->where('quality', $blackMarketPrice->quality)
                    ->where('city', '!=', City::BlackMarket)
                    ->whereNotNull('sell_price_min')
                    ->where('sell_price_min', '>', 0)
                    ->get();
                    
                foreach ($cityPrices as $cityPrice) {
                    // Calcular o lucro potencial
                    $profit = $blackMarketPrice->buy_price_min - $cityPrice->sell_price_min;
                    $profitPercentage = $cityPrice->sell_price_min > 0 
                        ? ($profit / $cityPrice->sell_price_min) * 100 
                        : 0;
                        
                    // Apenas incluir se houver lucro
                    if ($profit > 0) {
                        $opportunities[] = [
                            'id' => $cityPrice->id,
                            'item_id' => $item->id,
                            'uniquename' => $item->uniquename,
                            'nicename' => $item->nicename,
                            'tier' => $item->tier,
                            'enchantment_level' => $item->enchantment_level,
                            'quality' => $cityPrice->quality->value,
                            'city' => $cityPrice->city->value,
                            'sell_price_min' => $cityPrice->sell_price_min,
                            'buy_price_min' => $cityPrice->buy_price_min,
                            'black_market_price' => $blackMarketPrice->buy_price_min,
                            'profit' => $profit,
                            'profit_percentage' => $profitPercentage,
                            'updated_at' => $cityPrice->updated_at->format('Y-m-d H:i:s')
                        ];
                    }
                }
            }
            
            // Ordenar por margem de lucro (percentual) em ordem decrescente
            usort($opportunities, function($a, $b) {
                return $b['profit_percentage'] <=> $a['profit_percentage'];
            });
            
            return response()->json($opportunities, 200);
        } catch (\Exception $e) {
            Log::error("Erro ao buscar oportunidades do Black Market: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erro ao processar dados do Black Market'], 500);
        }
    }
    
    /**
 * Renderiza a página de recursos do Albion Online
 */
public function resources()
{
    return Inertia::render('Albion/Resources');
}

/**
 * Renderiza a página de equipamentos do Albion Online (armaduras, armas e acessórios)
 */
public function equipment()
{
    return Inertia::render('Albion/Equipment');
}

/**
 * Retorna dados de equipamentos (armaduras, armas e acessórios) com filtros
 * 
 * @param Request $request
 * @return JsonResponse
 */
public function getEquipmentData(Request $request): JsonResponse
{
    try {
        $categories = ['accessories', 'armor', 'magic', 'melee', 'ranged', 'offhand'];
        $tier = $request->input('tier', 0);
        $enchantment = $request->input('enchantment', -1);
        $category = $request->input('category');
        $subcategory = $request->input('subcategory');
        
        $query = Item::query();
        
        // Filtrar por categoria
        if ($category && in_array($category, $categories)) {
            $query->where('shop_category', $category);
        } else {
            $query->whereIn('shop_category', $categories);
        }
        
        // Filtrar por subcategoria
        if ($subcategory) {
            $query->where('shop_subcategory1', $subcategory);
        }
        
        // Filtrar por tier
        if ($tier > 0) {
            $query->where('tier', $tier);
        }
        
        // Filtrar por enchantment
        if ($enchantment >= 0) {
            $query->where('enchantment', $enchantment);
        }
        
        // Buscar itens com preços e stats
        $items = $query->with(['prices', 'stats'])->get();
        
        // Agrupar por subcategoria
        $groupedItems = $items->groupBy('shop_subcategory1');
        $result = [];
        
        foreach ($groupedItems as $subcategory => $subcategoryItems) {
            $result[] = [
                'subcategory' => $subcategory,
                'items' => $subcategoryItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'uniquename' => $item->uniquename,
                        'nicename' => $item->nicename,
                        'tier' => $item->tier,
                        'enchantment' => $item->enchantment,
                        'shop_category' => $item->shop_category,
                        'shop_subcategory1' => $item->shop_subcategory1,
                        'item_power' => $item->stats->isNotEmpty() ? $item->stats[0]->itempower : 0
                    ];
                })
            ];
        }
        
        return response()->json($result);
    } catch (\Exception $e) {
        Log::error('Erro ao buscar dados de equipamentos: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json(['error' => 'Erro ao buscar dados de equipamentos'], 500);
    }
}

    /**
     * Retorna a lista de itens do Albion Online em formato JSON com cache
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemListDataJson()
    {

        $item = Cache::remember('albion_item_list1', 86400, function () {
            return Item::whereNot('uniquename', 'like', '%UNIQUE%')
            ->whereNot('uniquename', 'like', '%SKIN%')
            ->select('id', 'uniquename', 'nicename', 'tier', 'enchantment_level')
            ->get();
        });

        return response()->json($item);
       

    }
    
    /**
     * Retorna a lista de recursos do Albion Online
     */
    public function getResourcesData(Request $request)
    {
        try {

            if(Cache::has('albion_resources_api')) {
                $resources = Cache::get('albion_resources_api');
                return response()->json($resources);
            }
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
            
            Cache::put('albion_resources_api', $processedData, now()->addMinutes(5));
            
            return response()->json($processedData);
            
        } catch (\Exception $e) {
            Log::error("Erro ao buscar dados de recursos: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getItemDetails(Request $request, $itemId = null)
    {
        try {
            // Se não for fornecido um ID, usar o da requisição
            if (!$itemId) {
                $itemId = $request->input('item_id') ?? $request->input('itemId');
            }
            
            if (!$itemId) {
                return response()->json(['error' => 'ID do item não fornecido'], 400);
            }
            
            // // Verificar se o item está no cache
            // $cacheKey = "item_details_{$itemId}";
            // $cachedItem = Cache::get($cacheKey);
            
            // if ($cachedItem && !$request->boolean('refresh')) {
            //     return response()->json($cachedItem);
            // }

            
            // Buscar o item pelo ID ou uniquename
            $item = is_numeric($itemId) 
                ? Item::with(['prices', 'materials.prices', 'stats'])->find($itemId)
                : Item::with(['prices', 'materials.prices', 'stats'])->where('uniquename', $itemId)->first();
            
            // Atualizar preços do item e dos seus materiais
            $this->priceService->fetchPrices([$item->uniquename], false);
            foreach ($item->materials as $material) {
                $this->priceService->fetchPrices([$material->uniquename], false);
            }
            
            if (!$item) {
                return response()->json(['error' => 'Item não encontrado'], 404);
            }
            
            // Formatar os dados para o frontend
            $formattedItem = [
                'id' => $item->id,
                'uniquename' => $item->uniquename,
                'nicename' => $item->nicename,
                'description' => $item->description,
                'tier' => $item->tier,
                'enchantment_level' => $item->enchantment_level,
                'shopcategory' => $item->shop_category,
                'shopsubcategory1' => $item->shop_subcategory1,
                'slottype' => $item->slot_type,
                'qualities' => [],
                'materials' => [],
                'crafting_analysis' => []
            ];
            
            // Verificar se o item é da categoria resources
            $isResource = strtolower($item->shop_category) === 'resources';
            
            // Formatar preços por qualidade e cidade
            $qualities = [];
            foreach ($item->prices as $price) {
                // Se for um recurso, forçar qualidade 1
                $qualityValue = $isResource ? 1 : $price->quality->value;
                $cityName = $price->city->name;
                
                if (!isset($qualities[$qualityValue])) {
                    $qualities[$qualityValue] = [
                        'quality' => $qualityValue,
                        'cities' => []
                    ];
                }
                
                $qualities[$qualityValue]['cities'][] = [
                    'city' => $cityName,
                    'sell_price_min' => $price->sell_price_min,
                    'sell_price_min_date' => $price->sell_price_min_date,
                    'sell_price_max' => $price->sell_price_max,
                    'sell_price_max_date' => $price->sell_price_max_date,
                    'buy_price_min' => $price->buy_price_min,
                    'buy_price_min_date' => $price->buy_price_min_date,
                    'buy_price_max' => $price->buy_price_max,
                    'buy_price_max_date' => $price->buy_price_max_date
                ];
            }
            
            $formattedItem['qualities'] = array_values($qualities);
            
            // Formatar materiais
            foreach ($item->materials as $material) {
                $materialQualities = [];
                
                // Verificar se o material é da categoria resources
                $isMaterialResource = strtolower($material->shop_category) === 'resources';
                
                foreach ($material->prices as $price) {
                    // Se for um recurso, forçar qualidade 1
                    $qualityValue = $isMaterialResource ? 1 : $price->quality->value;
                    $cityName = $price->city->name;
                    
                    if (!isset($materialQualities[$qualityValue])) {
                        $materialQualities[$qualityValue] = [
                            'quality' => $qualityValue,
                            'cities' => []
                        ];
                    }
                    
                    $materialQualities[$qualityValue]['cities'][] = [
                        'city' => $cityName,
                        'sell_price_min' => $price->sell_price_min,
                        'sell_price_min_date' => $price->sell_price_min_date,
                        'sell_price_max' => $price->sell_price_max,
                        'sell_price_max_date' => $price->sell_price_max_date,
                        'buy_price_min' => $price->buy_price_min,
                        'buy_price_min_date' => $price->buy_price_min_date,
                        'buy_price_max' => $price->buy_price_max,
                        'buy_price_max_date' => $price->buy_price_max_date
                    ];
                }
                
                $formattedItem['materials'][] = [
                    'uniquename' => $material->uniquename,
                    'nicename' => $material->nicename,
                    'amount' => $material->pivot->amount,
                    'max_return_amount' => $material->pivot->max_return_amount,
                    'shopcategory' => $material->shop_category,
                    'shopsubcategory1' => $material->shop_subcategory1,
                    'slottype' => $material->slot_type,
                    'qualities' => array_values($materialQualities)
                ];
            }
            
            // Calcular a análise de lucratividade do crafting para todas as cidades e qualidades
            if (!$item->materials->isEmpty()) {
                $craftingAnalysis = [];
                
                // Verificar se o item é da categoria resources
                $isResource = strtolower($item->shop_category) === 'resources';
                
                // Calcular para todas as cidades
                foreach (City::cases() as $city) {
                    // Se for um recurso, calcular apenas para qualidade 1
                    if ($isResource) {
                        $analysis = $this->calculateCraftingProfitability($item, $city->name, 1);
                        
                        if (!empty($analysis)) {
                            $analysis['city'] = $city->name;
                            $craftingAnalysis[] = $analysis;
                        }
                    } else {
                        // Calcular para todas as qualidades
                        foreach (Quality::cases() as $quality) {
                            $analysis = $this->calculateCraftingProfitability($item, $city->name, $quality->value);
                            
                            if (!empty($analysis)) {
                                $analysis['city'] = $city->name;
                                $craftingAnalysis[] = $analysis;
                            }
                        }
                    }
                }
                
                $formattedItem['crafting_analysis'] = $craftingAnalysis;
            }
            
            // Adicionar informações de crafting dos stats, se existirem
            if ($item->stats->isNotEmpty() && isset($item->stats[0]->craftingrequirements)) {
                $formattedItem['crafting_requirements'] = $item->stats[0]->craftingrequirements;
            }
            
            return response()->json($formattedItem);
        } catch (\Exception $e) {
            Log::error("Erro ao buscar detalhes do item: " . $e->getMessage(), [
                'item_id' => $itemId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Erro ao buscar detalhes do item'], 500);
        }
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
    
    /**
     * Calcula a lucratividade de crafting de um item em uma cidade específica
     * 
     * @param Item $item O item a ser analisado
     * @param string $cityName O nome da cidade para análise
     * @param int $quality A qualidade do item (padrão: 1 - Normal)
     * @return array Dados de lucratividade do crafting
     */
    private function calculateCraftingProfitability(Item $item, string $cityName, int $quality = 1): array
    {
        // Verificar se o item tem materiais
        if ($item->materials->isEmpty()) {
            return [];
        }
        
        // Verificar se o item é da categoria resources
        $isResource = strtolower($item->shop_category) === 'resources';
        
        // Se for um recurso, forçar qualidade 1
        $itemQuality = $isResource ? 1 : $quality;
        
        // Calcular o custo total dos materiais
        $totalMaterialCost = 0;
        $materialDetails = [];
        
        foreach ($item->materials as $material) {
            // Verificar se o material é da categoria resources
            $isMaterialResource = strtolower($material->shop_category) === 'resources';
            
            // Para recursos, sempre usamos qualidade 1 (normal)
            $materialQuality = $isMaterialResource ? 1 : 1; // Por enquanto, sempre usar qualidade 1 para materiais
            
            // Buscar o preço do material na cidade e qualidade especificadas
            $materialPrice = $material->prices
                ->where('city.name', $cityName)
                ->where('quality.value', $materialQuality)
                ->first();
            
            if (!$materialPrice) {
                continue;
            }
            
            // Usar o preço de venda mínimo como referência para o custo
            $materialCost = $materialPrice->sell_price_min * $material->pivot->amount;
            $totalMaterialCost += $materialCost;
            
            $materialDetails[] = [
                'id' => $material->id,
                'uniquename' => $material->uniquename,
                'nicename' => $material->nicename,
                'amount' => $material->pivot->amount,
                'unit_price' => $materialPrice->sell_price_min,
                'total_cost' => $materialCost
            ];
        }
        
        // Buscar o preço de venda do item na cidade e qualidade especificadas
        // Se for um recurso, usar sempre qualidade 1
        $itemPrice = $item->prices
            ->where('city.name', $cityName)
            ->where('quality.value', $itemQuality) // Usar $itemQuality que já considera se é resource
            ->first();
        
        if (!$itemPrice || !$itemPrice->sell_price_min) {
            return [];
        }
        
        // Calcular lucro e margem
        $sellPrice = $itemPrice->sell_price_min;
        $profit = $sellPrice - $totalMaterialCost;
        $profitMargin = $totalMaterialCost > 0 ? ($profit / $totalMaterialCost) * 100 : 0;
        
        // Determinar se é lucrativo
        $isProfitable = $profit > 0;
        
        return [
            'material_cost' => $totalMaterialCost,
            'material_details' => $materialDetails,
            'sell_price' => $sellPrice,
            'profit' => $profit,
            'profit_margin' => $profitMargin,
            'is_profitable' => $isProfitable,
            'quality' => $quality,
            'updated_at' => $itemPrice->sell_price_min_date
        ];
    }
}
