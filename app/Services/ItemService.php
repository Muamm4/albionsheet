<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\City;
use App\Enums\Quality;
use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ItemService
{
    private AlbionPriceService $priceService;
    
    public function __construct(AlbionPriceService $priceService)
    {
        $this->priceService = $priceService;
    }
    
    /**
     * Busca um item pelo uniquename e adiciona informações de preços e materiais
     * 
     * @param string $uniqueName
     * @param bool $forceRefresh Força atualização dos preços na API
     * @return array|null
     */
    public function findByUniqueName(string $uniqueName, bool $forceRefresh = false): ?array
    {
        $item = Item::where('uniquename', $uniqueName)->first();
        
        if (!$item) {
            return null;
        }
        
        // Buscar os preços do item
        if ($forceRefresh) {
            // Forçar atualização dos preços na API
            $this->priceService->updateItemPrices($item);
        }
        
        // Montar o array com as informações do item
        $result = $this->formatItemData($item);
        
        return $result;
    }
    
    /**
     * Formata os dados do item incluindo preços e materiais
     * 
     * @param Item $item
     * @return array
     */
    private function formatItemData(Item $item): array
    {
        // Dados básicos do item
        $result = [
            'id' => $item->id,
            'uniquename' => $item->uniquename,
            'nicename' => $item->nicename,
            'tier' => $item->tier,
            'enchantment' => $item->enchantment,
            'fame' => $item->fame,
            'focus' => $item->focus,
            'shopcategory' => $item->shopcategory,
            'shopsubcategory1' => $item->shopsubcategory1,
            'slottype' => $item->slottype,
            'craftingcategory' => $item->craftingcategory,
            'qualities' => $this->getItemQualities($item),
            'materials' => $this->getItemMaterials($item),
        ];
        
        return $result;
    }
    
    /**
     * Obtém as qualidades e preços do item por cidade
     * 
     * @param Item $item
     * @return array
     */
    private function getItemQualities(Item $item): array
    {
        $qualities = [];
        
        // Agrupar preços por qualidade
        $prices = $item->prices()
            ->orderBy('quality')
            ->orderBy('city')
            ->get()
            ->groupBy('quality');
        
        foreach ($prices as $quality => $qualityPrices) {
            $qualityData = [
                'quality' => $quality,
                'cities' => []
            ];
            
            // Agrupar por cidade
            foreach ($qualityPrices as $price) {
                $qualityData['cities'][] = [
                    'city' => $price->city->value,
                    'sell_price_min' => $price->sell_price_min,
                    'sell_price_min_date' => $price->sell_price_min_date?->toIso8601String(),
                    'sell_price_max' => $price->sell_price_max,
                    'sell_price_max_date' => $price->sell_price_max_date?->toIso8601String(),
                    'buy_price_min' => $price->buy_price_min,
                    'buy_price_min_date' => $price->buy_price_min_date?->toIso8601String(),
                    'buy_price_max' => $price->buy_price_max,
                    'buy_price_max_date' => $price->buy_price_max_date?->toIso8601String(),
                ];
            }
            
            $qualities[] = $qualityData;
        }
        
        return $qualities;
    }
    
    /**
     * Obtém os materiais necessários para o craft do item
     * 
     * @param Item $item
     * @return array
     */
    private function getItemMaterials(Item $item): array
    {
        $materials = [];
        
        // Carregar materiais com seus preços
        $itemMaterials = $item->materials()->with('prices')->get();
        
        foreach ($itemMaterials as $material) {
            $pivot = $material->pivot;
            
            $materialData = [
                'uniquename' => $material->uniquename,
                'nicename' => $material->nicename,
                'amount' => $pivot->amount,
                'max_return_amount' => $pivot->max_return_amount,
                'tier' => $material->tier,
                'enchantment' => $material->enchantment,
                'shopcategory' => $material->shopcategory,
                'shopsubcategory1' => $material->shopsubcategory1,
                'slottype' => $material->slottype,
                'qualities' => $this->getItemQualities($material),
            ];
            
            $materials[] = $materialData;
        }
        
        return $materials;
    }
    
    /**
     * Calcula informações de crafting para um item
     * 
     * @param Item $item
     * @param Quality $quality
     * @param City $city
     * @return array
     */
    public function getCraftingInfo(Item $item, Quality $quality, City $city): array
    {
        // Buscar o preço do item na qualidade e cidade especificadas
        $itemPrice = $item->prices()
            ->where('quality', $quality)
            ->where('city', $city)
            ->first();
            
        $sellPrice = $itemPrice?->sell_price_min ?? 0;
        
        // Calcular o custo total dos materiais
        $materialsCost = 0;
        $materials = [];
        
        foreach ($item->materials as $material) {
            $pivot = $material->pivot;
            
            // Buscar o preço do material na cidade especificada (usando qualidade normal)
            $materialPrice = $material->prices()
                ->where('quality', Quality::Normal)
                ->where('city', $city)
                ->first();
                
            $materialCost = ($materialPrice?->buy_price_max ?? 0) * $pivot->amount;
            $materialsCost += $materialCost;
            
            $materials[] = [
                'uniquename' => $material->uniquename,
                'nicename' => $material->nicename,
                'amount' => $pivot->amount,
                'price' => $materialPrice?->buy_price_max ?? 0,
                'total_cost' => $materialCost,
            ];
        }
        
        // Calcular o lucro potencial
        $profit = $sellPrice - $materialsCost;
        $profitPercentage = $materialsCost > 0 ? ($profit / $materialsCost) * 100 : 0;
        
        return [
            'item_price' => $sellPrice,
            'materials_cost' => $materialsCost,
            'profit' => $profit,
            'profit_percentage' => $profitPercentage,
            'materials' => $materials,
            'fame' => $item->fame,
            'focus' => $item->focus,
        ];
    }
    
    /**
     * Busca itens por categoria
     * 
     * @param string $category
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getItemsByCategory(string $category, int $page = 1, int $perPage = 20): array
    {
        $items = Item::where('shopcategory', $category)
            ->orWhere('craftingcategory', $category)
            ->orderBy('tier')
            ->orderBy('nicename')
            ->paginate($perPage, ['*'], 'page', $page);
            
        $formattedItems = [];
        
        foreach ($items as $item) {
            $formattedItems[] = [
                'id' => $item->id,
                'uniquename' => $item->uniquename,
                'nicename' => $item->nicename,
                'tier' => $item->tier,
                'enchantment' => $item->enchantment,
            ];
        }
        
        return [
            'items' => $formattedItems,
            'total' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ];
    }
}
