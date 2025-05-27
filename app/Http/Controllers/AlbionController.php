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
        return Inertia::render('albion/Index');
    }

    public function itemDetail(Request $request, $itemId)
    {
        if (!$itemId) {
            return redirect()->route('albion.index');
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

    public function getCraftingInfo($itemId)
    {
        if (!$itemId) {
            return response()->json(['error' => 'No item ID provided'], 400);
        }
        
        // Fallback para o método de simulação se não encontrar no banco
        return response()->json($this->generateCraftingInfo($itemId));
    }

    private function generateCraftingInfo($itemId)
    {
        $parts = explode('_', $itemId);
        
        if (count($parts) < 2) {
            return [
                'materials' => [],
                'totalCost' => 0
            ];
        }
        
        $tier = substr($parts[0], 1); // T4 -> 4
        $itemType = $parts[1]; // SWORD, AXE, etc.
        
        // Verificar se é um item craftável
        $craftableTypes = [
            'SWORD', 'AXE', 'MACE', 'HAMMER', 'SPEAR', 'DAGGER', 'QUARTERSTAFF',
            'BOW', 'CROSSBOW', 'CURSEDSTAFF', 'FIRESTAFF', 'FROSTSTAFF', 'ARCANESTAFF', 'HOLYSTAFF',
            'NATURESTAFF', 'PLATE', 'LEATHER', 'CLOTH', 'BAG', 'CAPE'
        ];
        
        if (!in_array($itemType, $craftableTypes)) {
            return [
                'materials' => [],
                'totalCost' => 0
            ];
        }
        
        // Determinar o tipo de material principal baseado no tipo de item
        $materialType = '';
        $materialAmount = 0;
        
        if (in_array($itemType, ['SWORD', 'AXE', 'MACE', 'HAMMER', 'SPEAR', 'DAGGER', 'QUARTERSTAFF'])) {
            $materialType = 'METALBAR'; // Armas corpo-a-corpo usam metal
            $materialAmount = 8;
        } elseif (in_array($itemType, ['BOW', 'CROSSBOW'])) {
            $materialType = 'PLANKS'; // Armas de madeira
            $materialAmount = 8;
        } elseif (in_array($itemType, ['CURSEDSTAFF', 'FIRESTAFF', 'FROSTSTAFF', 'ARCANESTAFF', 'HOLYSTAFF', 'NATURESTAFF'])) {
            $materialType = 'PLANKS'; // Cajados usam madeira
            $materialAmount = 8;
        } elseif ($itemType === 'PLATE') {
            $materialType = 'METALBAR'; // Armadura de placa usa metal
            $materialAmount = 12;
        } elseif ($itemType === 'LEATHER') {
            $materialType = 'LEATHER'; // Armadura de couro
            $materialAmount = 12;
        } elseif ($itemType === 'CLOTH') {
            $materialType = 'CLOTH'; // Armadura de tecido
            $materialAmount = 12;
        } elseif ($itemType === 'BAG') {
            $materialType = 'CLOTH'; // Bolsas usam tecido
            $materialAmount = 6;
        } elseif ($itemType === 'CAPE') {
            $materialType = 'CLOTH'; // Capas usam tecido
            $materialAmount = 6;
        }
        
        // Criar a lista de materiais
        $materials = [];
        
        // Material principal
        if ($materialType) {
            $materials[] = [
                'itemId' => "T{$tier}_{$materialType}",
                'name' => $this->getResourceName("T{$tier}_{$materialType}"),
                'quantity' => $materialAmount,
                'price' => 1000 * $tier // Preço simulado
            ];
        }
        
        // Verificar se é um item de artefato (contém @1, @2, @3)
        if (count($parts) > 2 && strpos($parts[2], '@') === 0) {
            $artifactLevel = substr($parts[2], 1); // @1 -> 1
            $artifactType = '';
            
            // Determinar o tipo de artefato
            if (count($parts) > 3) {
                $artifactType = "{$itemType}_{$parts[3]}";
            } else {
                $artifactType = $itemType;
            }
            
            // Adicionar artefato aos materiais
            $materials[] = [
                'itemId' => "ARTEFACT_{$artifactType}",
                'name' => $this->getArtifactName($artifactType, $artifactLevel),
                'quantity' => 1,
                'price' => 5000 * $tier * $artifactLevel // Preço simulado
            ];
        }
        
        // Calcular custo total
        $totalCost = 0;
        foreach ($materials as $material) {
            $totalCost += $material['price'] * $material['quantity'];
        }
        
        return [
            'materials' => $materials,
            'totalCost' => $totalCost
        ];
    }

    private function getResourceName($resourceId)
    {
        $resources = [
            'T2_METALBAR' => 'Lingote de Cobre',
            'T3_METALBAR' => 'Lingote de Bronze',
            'T4_METALBAR' => 'Lingote de Aço',
            'T5_METALBAR' => 'Lingote de Titânio',
            'T6_METALBAR' => 'Lingote de Runita',
            'T7_METALBAR' => 'Lingote Meteórico',
            'T8_METALBAR' => 'Lingote Adamantino',
            
            'T2_PLANKS' => 'Tábuas de Madeira Bruta',
            'T3_PLANKS' => 'Tábuas de Carvalho',
            'T4_PLANKS' => 'Tábuas de Pinho',
            'T5_PLANKS' => 'Tábuas de Cedro',
            'T6_PLANKS' => 'Tábuas de Mogno',
            'T7_PLANKS' => 'Tábuas de Ébano',
            'T8_PLANKS' => 'Tábuas de Jacarandá',
            
            'T2_LEATHER' => 'Couro Rústico',
            'T3_LEATHER' => 'Couro Grosso',
            'T4_LEATHER' => 'Couro Trabalhado',
            'T5_LEATHER' => 'Couro Resistente',
            'T6_LEATHER' => 'Couro Endurecido',
            'T7_LEATHER' => 'Couro Reforçado',
            'T8_LEATHER' => 'Couro Fortificado',
            
            'T2_CLOTH' => 'Tecido Simples',
            'T3_CLOTH' => 'Tecido de Linho',
            'T4_CLOTH' => 'Tecido de Algodão',
            'T5_CLOTH' => 'Tecido Resistente',
            'T6_CLOTH' => 'Tecido Luxuoso',
            'T7_CLOTH' => 'Tecido Sublime',
            'T8_CLOTH' => 'Tecido Real'
        ];
        
        return $resources[$resourceId] ?? $resourceId;
    }

    private function getArtifactName($itemType, $level)
    {
        $artifacts = [
            'SWORD_HELL' => 'Fragmento Demoníaco',
            'SWORD_UNDEAD' => 'Fragmento Amaldiçoado',
            'SWORD_KEEPER' => 'Fragmento Sagrado',
            'SWORD_AVALON' => 'Fragmento Avalônico',
            
            'AXE_HELL' => 'Chifre Demoníaco',
            'AXE_UNDEAD' => 'Crânio Amaldiçoado',
            'AXE_KEEPER' => 'Machado Sagrado',
            'AXE_AVALON' => 'Lâmina Avalônica',
            
            // Adicionar mais artefatos conforme necessário
        ];
        
        $key = "{$itemType}";
        return $artifacts[$key] ?? "Artefato Nível {$level}";
    }

    /**
     * Busca preços de itens na API do Albion Online e salva no banco de dados
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
    
    /**
     * Filtra os dados de preços conforme os parâmetros da requisição
     * 
     * @param array $prices Dados de preços organizados
     * @param array|string|null $locations Localizações para filtrar
     * @param array|string|null $qualities Qualidades para filtrar
     * @return array Dados filtrados no formato esperado pelo frontend
     */
    /**
     * Filtra os dados de preços conforme os parâmetros da requisição
     * 
     * @param array $prices Dados de preços organizados
     * @param array|string|null $locations Localizações para filtrar
     * @param array|string|null $qualities Qualidades para filtrar
     * @return array Dados filtrados no formato esperado pelo frontend
     */
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
