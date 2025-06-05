<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemMaterial;
use App\Models\ItemStat;
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
            DB::beginTransaction();
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
                    $items[$uniqueNameKey]["uniquename"] = $item['UniqueName'];
                    $items[$uniqueNameKey]["nicename"] = $item['LocalizedNames']['EN-US'] ?? null;
                    $items[$uniqueNameKey]["description"] = $item['LocalizedDescriptions']['EN-US'] ?? null;
                } catch (\Exception $e) {
                    $this->error("Erro ao processar item: {$item['UniqueName']}");
                    $this->error($e->getMessage());
                    dd($item);
                }

                $itemStats = $this->findItemStats($uniqueNameKey);
                
                $items[$uniqueNameKey]["shop_category"] = isset($itemStats['@shopcategory']) ? $itemStats['@shopcategory'] : "sem categoria";
                $items[$uniqueNameKey]["shop_subcategory1"] = isset($itemStats['@shopsubcategory1']) ? $itemStats['@shopsubcategory1'] : "sem subcategoria";
                $items[$uniqueNameKey]["tier"] = isset($itemStats['@tier']) ? $itemStats['@tier'] : null;
                $items[$uniqueNameKey]["item_power"] = isset($itemStats['@itempower']) ? $itemStats['@itempower'] : null;
                $items[$uniqueNameKey]["slot_type"] = isset($itemStats['@slottype']) ? $itemStats['@slottype'] : null;
                $items[$uniqueNameKey]["crafting_category"] = isset($itemStats['@craftingcategory']) ? $itemStats['@craftingcategory'] : null;
                $items[$uniqueNameKey]["enchantment_level"] = isset($itemStats['enchantment']['@enchantmentlevel']) ? $itemStats['enchantment']['@enchantmentlevel'] : (isset($itemStats['@enchantmentlevel']) ? $itemStats['@enchantmentlevel'] : 0);
                $items[$uniqueNameKey]["craftingrequirements"] = isset($itemStats['craftingrequirements']) ? $itemStats['craftingrequirements'] : null;
                $items[$uniqueNameKey]["enchantment"] = isset($itemStats['enchantment']) ? $itemStats['enchantment'] : null;
                $items[$uniqueNameKey]["upgraderequirements"] = isset($itemStats['enchantment']['upgraderequirements']) ? $itemStats['enchantment']['upgraderequirements'] : null;

                // Remove campos que não pertencem ao modelo Item
                $itemData = collect($items[$uniqueNameKey])
                    ->except("craftingrequirements", "upgraderequirements")
                    ->toArray();
                
                    if (Item::where('uniquename', $uniqueNameKey)->exists()) {
                        $item = Item::where('uniquename', $uniqueNameKey)->first();
                        $item->update($itemData);
                    } else {
                        $item = Item::create([
                            'uniquename' => $uniqueNameKey,
                            'nicename' => $items[$uniqueNameKey]['nicename'],
                            'description' => $items[$uniqueNameKey]['description'],
                            'tier' => $items[$uniqueNameKey]['tier'],
                            'enchantment_level' => $items[$uniqueNameKey]['enchantment_level'],
                            'item_power' => $items[$uniqueNameKey]['item_power'],
                            'shop_category' => $items[$uniqueNameKey]['shop_category'],
                            'shop_subcategory1' => $items[$uniqueNameKey]['shop_subcategory1'],
                            'slot_type' => $items[$uniqueNameKey]['slot_type'],
                            'crafting_category' => $items[$uniqueNameKey]['crafting_category'],
                        ]);
                    }
              
                if (ItemStat::where('item_id', $item->id)->exists()) {
                    $item->stats()->update([
                        'stats_data' => $itemStats,
                        'enchantment' => $items[$uniqueNameKey]['enchantment'],
                        'craftingrequirements' => $items[$uniqueNameKey]['craftingrequirements'],
                        'upgraderequirements' => isset($items[$uniqueNameKey]['enchantment']['upgraderequirements']) ? $items[$uniqueNameKey]['enchantment']['upgraderequirements'] : null
                    ]);
                } else {
                    $item->stats()->create([
                        'stats_data' => $itemStats,
                        'enchantment' => $items[$uniqueNameKey]['enchantment'],
                        'craftingrequirements' => $items[$uniqueNameKey]['craftingrequirements'],
                        'upgraderequirements' => isset($items[$uniqueNameKey]['enchantment']['upgraderequirements']) ? $items[$uniqueNameKey]['enchantment']['upgraderequirements'] : null
                    ]);
                }

                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
            $this->info("Itens processados com sucesso.");

            $bar->finish();
            $this->newLine(2);

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
        }else{
            $itemStats['enchantment'] = isset($itemStats['enchantments']['enchantment']) ? $itemStats['enchantments']['enchantment'] : [];
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
