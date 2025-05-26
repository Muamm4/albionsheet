<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemMaterial;
use App\Services\AlbionPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ImportAlbionDataFromJson extends Command
{
    protected $signature = 'albion:import-json';
    protected $description = 'Importa dados do Albion Online a partir de um arquivo JSON';

    private string $jsonFile;
    private string $jsonFileStats;
    private $mergeStats;

    private $categories = ["trackingitem","farmableitem","simpleitem","consumableitem","equipmentitem","weapon","mount","furnitureitem","consumablefrominventoryitem","mountskin","journalitem","labourercontract","transformationweapon","crystalleagueitem","siegebanner","killtrophy"];
    private $ignoreWords = ["NONTRADABLE", "SKIN", "TRASH", "UNIQUE_HIDEOUT","QUESTITEM_TOKEN_SMUGGLER"];

    private AlbionPriceService $priceService;

    public function __construct(AlbionPriceService $priceService)
    {
        parent::__construct();
        $this->priceService = $priceService;
    }

    public function handle(): int
    {
        $this->jsonFile = 'database/item_data.json';
        $this->jsonFileStats = 'database/items_stats.json';


        if (!File::exists($this->jsonFile) || !File::exists($this->jsonFileStats)) {
            $this->error("Arquivo não encontrado: {$this->jsonFile}");
            return Command::FAILURE;
        }

        $this->info('Iniciando importação dos dados do Albion Online a partir do JSON...');

        try {
            // Carregar o conteúdo do arquivo JSON
            $jsonContent = File::get($this->jsonFile);
            $jsonData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Erro ao decodificar o JSON: ' . json_last_error_msg());
                return Command::FAILURE;
            }

            $this->info('JSON carregado com sucesso. Iniciando processamento...');
            $items = [];

            $this->mergeStats();
            $this->info('Stats mesclados com sucesso. Iniciando processamento de itens...');
            $bar = $this->output->createProgressBar(count($jsonData));
            $bar->start();
            foreach ($jsonData as $item) {
                try {
                    foreach ($this->ignoreWords as $word) {
                        if (stripos($item['UniqueName'], $word) !== false) {
                            continue 2;
                        }
                    }
                    $uniqueNameKey = $item['UniqueName'];
                    $items[$uniqueNameKey]["uniqueName"] = $item['UniqueName'];
                    $items[$uniqueNameKey]["name"] = $item['LocalizedNames']['EN-US'] ?? null;
                    $items[$uniqueNameKey]["description"] = $item['LocalizedDescriptions']['EN-US'] ?? null;
                } catch (\Exception $e) {
                    $this->error("Erro ao processar item: {$item['UniqueName']}");
                    $this->error($e->getMessage());
                    dd($item);
                }
                    $itemStats = $this->findItemStats($uniqueNameKey);
                if($itemStats === []){
                    // $this->error("Item sem stats: {$uniqueNameKey}");
                }
                
                $items[$uniqueNameKey]["shopcategory"] = isset($itemStats['@shopcategory']) ? $itemStats['@shopcategory'] : "sem categoria";
                $items[$uniqueNameKey]["shopsubcategory1"] = isset($itemStats['@shopsubcategory1']) ? $itemStats['@shopsubcategory1'] : "sem subcategoria";
                $items[$uniqueNameKey]["itempower"] = isset($itemStats['@itempower']) ? $itemStats['@itempower'] : null;
                $items[$uniqueNameKey]["tier"] = isset($itemStats['@tier']) ? $itemStats['@tier'] : null;
                $items[$uniqueNameKey]["craftingrequirements"] = isset($itemStats['craftingrequirements']) ? $itemStats['craftingrequirements'] : null;
                $items[$uniqueNameKey]["slottype"] = isset($itemStats['@slottype']) ? $itemStats['@slottype'] : null;
                $items[$uniqueNameKey]["craftingcategory"] = isset($itemStats['@craftingcategory']) ? $itemStats['@craftingcategory'] : null;
                $items[$uniqueNameKey]["enchantment"] = isset($itemStats['enchantment']) ? $itemStats['enchantment'] : 0;
                $items[$uniqueNameKey]["enchantmentLevel"] = isset($itemStats['enchantment']['@enchantmentlevel']) ? $itemStats['enchantment']['@enchantmentlevel'] : "0";
                $items[$uniqueNameKey]["upgraderequirements"] = isset($itemStats['upgraderequirements']) ? $itemStats['upgraderequirements'] : null;

                // Remove campos que não pertencem ao modelo Item
                $itemData = collect($items[$uniqueNameKey])
                    ->except(['itempower', 'craftingrequirements', 'upgraderequirements'])
                    ->toArray();

                // Cria ou atualiza o item
                $item = Item::updateOrCreate([
                    'uniquename' => $uniqueNameKey
                ], $itemData);

                // Cria ou atualiza as estatísticas do item
                $item->stats()->updateOrCreate(
                    ['item_id' => $item->id],
                    [
                        'stats_data' => $itemStats,
                        'itempower' => $items[$uniqueNameKey]['itempower'],
                        'craftingrequirements' => $items[$uniqueNameKey]['craftingrequirements'],
                        'upgraderequirements' => $items[$uniqueNameKey]['upgraderequirements']
                    ]
                );

                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
            
            
           $itemsObject = collect($items);
           $this->info("Total de itens processados: " . $itemsObject->count());

            $categories = $itemsObject->pluck('shopcategory')->unique()->values()->all();
            foreach($categories as $category){
                $this->info("Categoria: " . $category);
            }

            $this->newLine(2);
            $this->info("Itens sem categoria:");
            $itemsWithoutCategory = $itemsObject->where('shopcategory', 'sem categoria')->pluck('uniqueName')->values()->all();
            foreach($itemsWithoutCategory as $item){
                $this->info("Item sem categoria: " . $item);
            }

            $this->newLine(2);
            $this->info("Categorias de itens processados: " . implode(', ', $categories));


            $this->newLine(2);
            $this->info("Itens sem tier:");
            $itemsWithoutTier = $itemsObject->where('tier', 'sem tier')->pluck('uniqueName')->values()->all();
            foreach($itemsWithoutTier as $item){
                $this->info("Item sem tier: " . $item);
            }

            $this->newLine(2);
            $this->info("Itens aleatórios por categoria:");
            $categories = $itemsObject->pluck('shopcategory')->unique()->values()->all();
            foreach($categories as $category){
                $item = $itemsObject->where('shopcategory', $category)->random();
                $this->info("Categoria: " . $category);
                $this->info("Item: " . $item['uniqueName']);
                $this->info("  - Tier: " . $item['tier']);
                $this->info("  - Requisitos de fabricação: " . json_encode($item['craftingrequirements']));
                $this->info("  - Subcategoria 1: " . $item['shopsubcategory1']);
                $this->newLine(2);
            }



            // // Terceiro passo: atualizar preços
            // $this->info('Atualizando preços dos itens...');

            // // Obter todos os uniquenames dos itens processados
            // $uniqueNames = array_keys($processedItems);

            // // Atualizar preços em lotes para evitar sobrecarga da API
            // $batchSize = 100;
            // $batches = array_chunk($uniqueNames, $batchSize);

            // $bar = $this->output->createProgressBar(count($batches));
            // $bar->start();

            // foreach ($batches as $batch) {
            //     $this->priceService->updateItemsPrices($batch);
            //     $bar->advance();

            //     // Pequena pausa para não sobrecarregar a API
            //     sleep(1);
            // }

            // $bar->finish();
            // $this->newLine(2);

            DB::commit();
            $this->info('Importação concluída com sucesso!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erro durante a importação: {$e->getMessage()}");
            Log::error('Erro durante a importação de dados do Albion Online', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Extrai o tier e o enchantment do uniquename do item
     * 
     * @param string $uniquename
     * @return array [tier, enchantment]
     */
    private function extractTierAndEnchantment(string $uniquename): array
    {
        // Padrão comum: T4_ITEM_NAME@1
        $tier = 0;
        $enchantment = 0;

        // Extrair tier
        if (preg_match('/^T(\d+)_/', $uniquename, $matches)) {
            $tier = (int) $matches[1];
        }

        // Extrair enchantment
        if (preg_match('/@(\d+)$/', $uniquename, $matches)) {
            $enchantment = (int) $matches[1];
        }

        return [$tier, $enchantment];
    }

    

    private function findItemStats(string $uniquename, bool $verifyEnchant = false): array
    {
        if (strpos($uniquename, '@') !== false) {
            $uniquenameArray = explode('@', $uniquename);
            $tier = $uniquenameArray[1];
            $uniquename = $uniquenameArray[0];
            $verifyEnchant = true;
        }

        if(strpos($uniquename, '_EMPTY') !== false || strpos($uniquename, '_FULL') !== false){
            $uniquename = str_replace('_EMPTY', '', $uniquename);
            $uniquename = str_replace('_FULL', '', $uniquename);
        }

        $itemStats = $this->recursiveFindKeyValue('@uniquename', $uniquename, $this->mergeStats);

        if($verifyEnchant && isset($itemStats['enchantments'])){
            $enchantStats = $this->recursiveFindKeyValue('@enchantmentlevel', $tier, $itemStats['enchantments']);
            $itemStats['enchantment'] = $enchantStats;
        }
        if ($itemStats) {
            return $itemStats;
        }
        return [];
    }

    private function recursiveFindKeyValue(string $key, string $value, $array, int $maxDepth = 2): array
    {
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] == $value) {
                return $item;
            }
            if ($maxDepth <= 0) {
                return [];
            }
            if (is_array($item) ) {
                $result = $this->recursiveFindKeyValue($key, $value, $item, $maxDepth - 1);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        return [];
    }

    private function mergeStats()
    {

        $jsonContentStats = File::get($this->jsonFileStats);
        $jsonStats = collect(json_decode($jsonContentStats, true));

        $mergedStats = collect();

        $this->info("Total de categorias: " . count($jsonStats['items']));
        $this->info(join(', ', $this->categories));

        $bar = $this->output->createProgressBar(count($jsonStats['items']));
        $bar->start();
       

        // Itera sobre cada categoria e adiciona seus itens à Collection mesclada
        foreach ($jsonStats['items'] as $categoryName => $itemsArray) {
            
            if(!in_array($categoryName, $this->categories)){
                continue;
            }
            $bar->advance();
            // Garante que $itemsArray é um array antes de merge
            if (is_array($itemsArray) && $categoryName !== '@xmlns:xsi' && $categoryName !== '@xsi:noNamespaceSchemaLocation') {
                $mergedStats = $mergedStats->merge([$categoryName => $itemsArray]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->mergeStats = $mergedStats;

        return COMMAND::SUCCESS;
    }
}
