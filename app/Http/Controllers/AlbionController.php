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

use App\Models\AlbionCraft;
use App\Models\AlbionMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para gerenciar consultas de preços de itens do Albion Online
 */
class AlbionController extends Controller
{

    public function index()
    {
        // Passando um objeto de usuário vazio para evitar erros no layout
        return Inertia::render('Albion/Index');
    }

    public function itemDetail(Request $request, $itemId)
    {
        if (!$itemId) {
            return redirect()->route('albion.index');
        }
        
        return Inertia::render('Albion/ItemDetail', [
            'itemId' => $itemId
        ]);
    }

    /**
     * Display the favorites page.
     *
     * @return \Inertia\Response
     */
    public function favorites()
    {
        return Inertia::render('Albion/Favorites');
    }

    /**
     * Display the profit calculator page.
     *
     * @return \Inertia\Response
     */
    public function calculator()
    {
        return Inertia::render('Albion/Calculator');
    }

    /**
     * Display the black market comparison page.
     *
     * @return \Inertia\Response
     */
    public function blackMarket()
    {
        return Inertia::render('Albion/BlackMarket');
    }

    /**
     * Get item details from the items.json file.
     *
     * @param  Request  $request  A requisição contendo o ID do item
     * @param  string|null  $itemId  ID do item (opcional, pode vir da rota)
     * @return \Illuminate\Http\JsonResponse
     */
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
        
        return response()->json($item);
    }

    /**
     * Get crafting information for an item.
     *
     * @param  string  $itemId  ID do item
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCraftingInfo($itemId)
    {
        if (!$itemId) {
            return response()->json(['error' => 'No item ID provided'], 400);
        }
        
        // Buscar informações de crafting do banco de dados usando o model
        $craftingData = AlbionCraft::findByUniqueName($itemId);
        
        if ($craftingData) {
            return response()->json($craftingData->getCraftingInfo());
        }
        
        // Fallback para o método de simulação se não encontrar no banco
        return response()->json($this->generateCraftingInfo($itemId));
    }

    private function generateCraftingInfo($itemId)
    {
        // Extrair informações do ID do item
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

    /**
     * Get resource name based on resource ID.
     *
     * @param  string  $resourceId  ID do recurso
     * @return string  Nome do recurso
     */
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

    /**
     * Get artifact name based on item type and level.
     *
     * @param  string  $itemType  Tipo do item
     * @param  int  $level  Nível do artefato
     * @return string  Nome do artefato
     */
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
     * Get item prices from Albion Online Data API.
     *
     * @param  Request  $request  A requisição contendo os IDs dos itens
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemPrices(Request $request)
    {
        try {
            $itemIds = $request->input('items');
            
            if (!$itemIds) {
                return response()->json(['error' => 'No items provided'], 400);
            }
            
            // Convert array to comma-separated string if needed
            if (is_array($itemIds)) {
                $itemIds = implode(',', $itemIds);
            }
            
            // Albion Online Data API endpoint
            $apiUrl = "https://www.albion-online-data.com/api/v2/stats/prices/{$itemIds}";
            
            // Add optional parameters
            $locations = $request->input('locations');
            $qualities = $request->input('qualities');
            
            $queryParams = [];
            
            if ($locations) {
                $queryParams['locations'] = is_array($locations) 
                    ? implode(',', $locations) 
                    : $locations;
            }
            
            if ($qualities) {
                $queryParams['qualities'] = is_array($qualities) 
                    ? implode(',', $qualities) 
                    : $qualities;
            }
            
            // Use o cliente HTTP do Laravel para fazer a requisição
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->request('GET', $apiUrl, [
                'query' => $queryParams,
                'http_errors' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                Log::warning("API Albion retornou status {$statusCode} para {$apiUrl}", [
                    'items' => $itemIds,
                    'locations' => $locations,
                    'qualities' => $qualities
                ]);
                
                // Retornar array vazio em vez de erro para não quebrar o frontend
                return response()->json([]);
            }
            
            $data = json_decode($response->getBody(), true);
            
            // Verificar se os dados são válidos
            if (!is_array($data)) {
                Log::warning("API Albion retornou dados inválidos para {$apiUrl}");
                return response()->json([]);
            }
            
            return response()->json($data);
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
     * Insere dados básicos de receitas de crafting no banco de dados.
     * Esta função é apenas para demonstração.
     */
    private function insertBasicCraftingRecipes()
    {
        // Exemplos de receitas básicas
        $recipes = [
            [
                'uniquename' => 'T4_BAG',
                'nicename' => 'Adept\'s Bag',
                'craftitem1' => 'T4_LEATHER',
                'craftitem1_amount' => '16',
                'craftitem2' => 'T4_CLOTH',
                'craftitem2_amount' => '8',
            ],
            [
                'uniquename' => 'T5_BAG',
                'nicename' => 'Expert\'s Bag',
                'craftitem1' => 'T5_LEATHER',
                'craftitem1_amount' => '32',
                'craftitem2' => 'T5_CLOTH',
                'craftitem2_amount' => '16',
            ],
        ];

        // Inserir as receitas no banco de dados
        foreach ($recipes as $recipe) {
            AlbionCraft::updateOrCreate(
                ['uniquename' => $recipe['uniquename']],
                $recipe
            );
        }
    }

    /**
     * Insere dados básicos de materiais no banco de dados.
     * Esta função é apenas para demonstração.
     */
    private function insertBasicMaterials()
    {
        // Exemplos de materiais básicos
        $materials = [
            [
                'uniquename' => 'T4_LEATHER',
                'tier' => '4',
                'nice_name' => 'Adept\'s Leather',
                'shopcategory' => 'resources',
            ],
            [
                'uniquename' => 'T4_CLOTH',
                'tier' => '4',
                'nice_name' => 'Adept\'s Cloth',
                'shopcategory' => 'resources',
            ],
            [
                'uniquename' => 'T5_LEATHER',
                'tier' => '5',
                'nice_name' => 'Expert\'s Leather',
                'shopcategory' => 'resources',
            ],
            [
                'uniquename' => 'T5_CLOTH',
                'tier' => '5',
                'nice_name' => 'Expert\'s Cloth',
                'shopcategory' => 'resources',
            ],
        ];

        // Inserir os materiais no banco de dados
        foreach ($materials as $material) {
            AlbionMaterial::updateOrCreate(
                ['uniquename' => $material['uniquename']],
                $material
            );
        }
    }

    /**
     * Get items that can be crafted using the given item as material.
     *
     * @param  string  $itemId  ID do item
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemsToCraft($itemId)
    {
        if (!$itemId) {
            return response()->json(['error' => 'No item ID provided'], 400);
        }
        
        try {
            // Verificar se a tabela existe
            if (!Schema::hasTable('craft')) {
                return response()->json([]);
            }
            
            // Buscar itens que usam este material
            $craftableItems = AlbionCraft::where('craftitem1', $itemId)
                ->orWhere('craftitem2', $itemId)
                ->orWhere('craftitem3', $itemId)
                ->orWhere('craftitem4', $itemId)
                ->orWhere('craftitem5', $itemId)
                ->orWhere('craftitem6', $itemId)
                ->get();
                
            if ($craftableItems->isEmpty()) {
                return response()->json([]);
            }
            
            $result = [];
            
            foreach ($craftableItems as $item) {
                try {
                    $materials = $item->getMaterials();
                    $craftingInfo = $item->getCraftingInfo();
                    
                    $result[] = [
                        'id' => $item->uniquename,
                        'name' => $item->nicename ?: $item->uniquename,
                        'materials' => $materials,
                        'craftingInfo' => $craftingInfo
                    ];
                } catch (\Exception $e) {
                    Log::error("Erro ao processar item craftável {$item->uniquename}: " . $e->getMessage());
                    // Continuar com o próximo item
                    continue;
                }
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Erro ao buscar itens craftáveis para {$itemId}: " . $e->getMessage());
            return response()->json([]);
        }
    }
}
