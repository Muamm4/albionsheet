<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AlbionMaterial;
use Illuminate\Support\Facades\File;

class AlbionCraft extends Model
{

    protected $table = 'craft';

    public $timestamps = false;

    protected $fillable = [
        'uniquename',
        'nicename',
        'craftitem1',
        'craftitem1_amount',
        'craftitem1_maxreturnamount',
        'craftitem2',
        'craftitem2_amount',
        'craftitem2_maxreturnamount',
        'craftitem3',
        'craftitem3_amount',
        'craftitem3_maxreturnamount',
        'craftitem4',
        'craftitem4_amount',
        'craftitem4_maxreturnamount',
        'craftitem5',
        'craftitem5_amount',
        'craftitem5_maxreturnamount',
        'craftitem6',
        'craftitem6_amount',
        'craftitem6_maxreturnamount',
        'fame',
        'focus',
        'shopcategory',
        'shopsubcategory1',
        'slottype',
        'craftingcategory'
    ];

    public static function findByUniqueName(string $uniqueName): ?self
    {
        $item = static::where('uniquename', $uniqueName)->first();
        
        if ($item) {
            // Buscar os preços do item na API
            $prices = self::getItemPrices([$uniqueName]);
            
            // Se encontrou preços, adicionar ao objeto
            if (!empty($prices) && isset($prices[0])) {
                // Adicionar as informações de qualities ao objeto
                $item->qualities = $prices[0]['qualities'] ?? [];
            } else {
                $item->qualities = [];
            }
            
            // Adicionar os materiais ao objeto
            $item->materials = $item->getMaterials();
        }
        
        return $item;
    }

    public function getMaterials(): array
    {
        $materials = [];
        $materialItemIds = [];
        
        // Coletar todos os IDs de materiais válidos
        for ($i = 1; $i <= 6; $i++) {
            $materialId = $this->{"craftitem{$i}"} ?? null;
            $amount = $this->{"craftitem{$i}_amount"} ?? null;
            
            if ($materialId && $amount) {
                $materialItemIds[] = $materialId;
                $materials[$materialId] = [
                    'uniquename' => $materialId,
                    'amount' => (int)$amount,
                    'max_return_amount' => (int)($this->{"craftitem{$i}_maxreturnamount"} ?? 0)
                ];
            }
        }
        
        // Se tiver materiais, buscar informações detalhadas de cada um
        if (!empty($materialItemIds)) {
            // Buscar os objetos dos materiais
            $materialObjects = self::whereIn('uniquename', $materialItemIds)->get();
            
            // Buscar os preços dos materiais
            $materialPrices = self::getItemPrices($materialItemIds);
            
            // Mapear preços por uniquename para fácil acesso
            $pricesByItem = [];
            foreach ($materialPrices as $price) {
                $pricesByItem[$price['item_id']] = $price['qualities'] ?? [];
            }
            
            // Adicionar informações detalhadas a cada material
            foreach ($materialObjects as $materialObject) {
                $uniquename = $materialObject->uniquename;
                if (isset($materials[$uniquename])) {
                    // Adicionar atributos do objeto
                    $materials[$uniquename]['nicename'] = $materialObject->nicename;
                    $materials[$uniquename]['shopcategory'] = $materialObject->shopcategory;
                    $materials[$uniquename]['shopsubcategory1'] = $materialObject->shopsubcategory1;
                    $materials[$uniquename]['slottype'] = $materialObject->slottype;
                    
                    // Adicionar preços
                    $materials[$uniquename]['qualities'] = $pricesByItem[$uniquename] ?? [];
                }
            }
        }
        
        return array_values($materials);
    }

    public function getCraftingInfo(): array
    {
        $materials = $this->getMaterials();
        return [
            'materials' => $this->getMaterials(),
            'totalCost' => 0, // Será calculado pelo frontend
            'fame' => (int)($this->fame ?? 0),
            'focus' => (int)($this->focus ?? 0),
            'category' => $this->craftingcategory,
            'shopCategory' => $this->shopcategory,
            'shopSubCategory' => $this->shopsubcategory1,
            'slotType' => $this->slottype
        ];
    }

    public function getFormattedName(): string
    {
        return $this->nicename ?: $this->uniquename;
    }

    public static function getItemPrices(Array $itemIds)
    {
        try {
            
            if (!$itemIds) {
                return response()->json(['error' => 'No items provided'], 400);
            }
            
            // Convert array to comma-separated string if needed
            if (is_array($itemIds)) {
                $itemIds = implode(',', $itemIds);
            }
            
            // Albion Online Data API endpoint
            $apiUrl = "https://west.albion-online-data.com/api/v2/stats/prices/{$itemIds}";
            
            // Use o cliente HTTP do Laravel para fazer a requisição
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->request('GET', $apiUrl, [
                'http_errors' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                return [];
            }
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            // Reorganizar os dados para uma estrutura mais adequada ao frontend
            $organizedData = [];
            
            foreach ($data as $item) {
                $itemId = $item['item_id'];
                $quality = $item['quality'];
                $city = $item['city'];
                
                // Inicializar a estrutura se não existir
                if (!isset($organizedData[$itemId])) {
                    $organizedData[$itemId] = [
                        'item_id' => $itemId,
                        'qualities' => []
                    ];
                }
                
                // Inicializar a qualidade se não existir
                if (!isset($organizedData[$itemId]['qualities'][$quality])) {
                    $organizedData[$itemId]['qualities'][$quality] = [
                        'quality' => $quality,
                        'cities' => []
                    ];
                }
                
                // Adicionar dados da cidade
                $organizedData[$itemId]['qualities'][$quality]['cities'][$city] = [
                    'sell_price_min' => $item['sell_price_min'],
                    'sell_price_min_date' => $item['sell_price_min_date'],
                    'sell_price_max' => $item['sell_price_max'],
                    'sell_price_max_date' => $item['sell_price_max_date'],
                    'buy_price_min' => $item['buy_price_min'],
                    'buy_price_min_date' => $item['buy_price_min_date'],
                    'buy_price_max' => $item['buy_price_max'],
                    'buy_price_max_date' => $item['buy_price_max_date']
                ];
            }
            
            // Converter arrays associativos para arrays indexados para melhor compatibilidade com JSON
            foreach ($organizedData as &$item) {
                $item['qualities'] = array_values($item['qualities']);
                
                foreach ($item['qualities'] as &$quality) {
                    $quality['cities'] = array_values(array_map(
                        function($cityName, $cityData) {
                            return array_merge(['city' => $cityName], $cityData);
                        },
                        array_keys($quality['cities']),
                        array_values($quality['cities'])
                    ));
                }
            }
            
            return array_values($organizedData);
        } catch (\Exception $e) {
            return [];
        }
    }
        
}
