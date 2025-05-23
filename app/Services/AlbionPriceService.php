<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\City;
use App\Enums\Quality;
use App\Models\Item;
use App\Models\ItemPrice;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AlbionPriceService
{
    private Client $httpClient;
    private string $apiBaseUrl = 'https://west.albion-online-data.com/api/v2/stats/prices/';
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 10,
            'http_errors' => false
        ]);
    }
    
    /**
     * Busca preços para uma lista de itens na API do Albion Online
     * 
     * @param array|Collection $items Lista de uniquenames ou objetos Item
     * @return array Dados organizados por item_id, quality e city
     */
    public function fetchPrices(array|Collection $items): array
    {
        try {
            // Extrair uniquenames se for uma coleção de objetos Item
            $itemIds = $items instanceof Collection
                ? $items->pluck('uniquename')->toArray()
                : $items;
                
            if (empty($itemIds)) {
                return [];
            }
            
            // Converter array para string separada por vírgulas
            $itemIdsString = implode(',', $itemIds);
            
            // Fazer requisição à API
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . $itemIdsString);
            
            if ($response->getStatusCode() !== 200) {
                Log::error('Erro ao buscar preços na API do Albion Online', [
                    'status_code' => $response->getStatusCode(),
                    'items' => $itemIdsString
                ]);
                return [];
            }
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            return $this->organizeData($data);
        } catch (\Exception $e) {
            Log::error('Exceção ao buscar preços na API do Albion Online', [
                'message' => $e->getMessage(),
                'items' => $items instanceof Collection ? $items->pluck('uniquename')->toArray() : $items
            ]);
            return [];
        }
    }
    
    /**
     * Organiza os dados da API em uma estrutura mais adequada
     * 
     * @param array $data Dados brutos da API
     * @return array Dados organizados
     */
    private function organizeData(array $data): array
    {
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
        
        // Converter arrays associativos para arrays indexados
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
    }
    
    /**
     * Salva os preços no banco de dados
     * 
     * @param array $organizedData Dados organizados retornados pelo método fetchPrices
     * @return void
     */
    public function savePricesToDatabase(array $organizedData): void
    {
        foreach ($organizedData as $itemData) {
            $uniquename = $itemData['item_id'];
            $item = Item::where('uniquename', $uniquename)->first();
            
            if (!$item) {
                continue;
            }
            
            foreach ($itemData['qualities'] as $qualityData) {
                $quality = $qualityData['quality'];
                
                foreach ($qualityData['cities'] as $cityData) {
                    $city = $cityData['city'];
                    
                    ItemPrice::updateOrCreate(
                        [
                            'item_id' => $item->id,
                            'quality' => $quality,
                            'city' => $city,
                        ],
                        [
                            'sell_price_min' => $cityData['sell_price_min'],
                            'sell_price_min_date' => $cityData['sell_price_min_date'],
                            'sell_price_max' => $cityData['sell_price_max'],
                            'sell_price_max_date' => $cityData['sell_price_max_date'],
                            'buy_price_min' => $cityData['buy_price_min'],
                            'buy_price_min_date' => $cityData['buy_price_min_date'],
                            'buy_price_max' => $cityData['buy_price_max'],
                            'buy_price_max_date' => $cityData['buy_price_max_date'],
                        ]
                    );
                }
            }
        }
    }
    
    /**
     * Atualiza os preços para um item específico
     * 
     * @param Item $item Item para atualizar preços
     * @return array Dados de preços organizados
     */
    public function updateItemPrices(Item $item): array
    {
        $prices = $this->fetchPrices([$item->uniquename]);
        
        if (!empty($prices)) {
            $this->savePricesToDatabase($prices);
        }
        
        return $prices;
    }
    
    /**
     * Atualiza os preços para uma lista de itens
     * 
     * @param array|Collection $items Lista de itens para atualizar preços
     * @return array Dados de preços organizados
     */
    public function updateItemsPrices(array|Collection $items): array
    {
        $prices = $this->fetchPrices($items);
        
        if (!empty($prices)) {
            $this->savePricesToDatabase($prices);
        }
        
        return $prices;
    }
}
