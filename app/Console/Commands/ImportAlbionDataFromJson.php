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

    private AlbionPriceService $priceService;

    public function __construct(AlbionPriceService $priceService)
    {
        parent::__construct();
        $this->priceService = $priceService;
    }

    public function handle(): int
    {
        $jsonFile = 'database/items.json';
        
        if (!File::exists($jsonFile)) {
            $this->error("Arquivo não encontrado: {$jsonFile}");
            return Command::FAILURE;
        }
        
        $this->info('Iniciando importação dos dados do Albion Online a partir do JSON...');
        
        try {
            // Carregar o conteúdo do arquivo JSON
            $jsonContent = File::get($jsonFile);
            $jsonData = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Erro ao decodificar o JSON: ' . json_last_error_msg());
                return Command::FAILURE;
            }
            
            $this->info('JSON carregado com sucesso. Iniciando processamento...');
            
            // Array para armazenar todos os itens encontrados
            $items = [];
            
            // Lista de chaves que contêm arrays de itens
            $itemKeys = [
                'trackingitem',
                'weapon',
                'armor',
                'consumable',
                'farmableitem',
                'simpleitem',
                'consumablefrominventory',
                'equipmentitem',
                'furnitureitem',
                'labourercontract',
                'mount',
                'product',
                'resource',
                'simple',
                'tool'
            ];
            
            // Procurar por arrays de itens nas chaves conhecidas
            foreach ($itemKeys as $key) {
                if (isset($jsonData['items'][$key])) {
                    $itemArray = $jsonData['items'][$key];
                    
                    // Se não for um array, pular
                    if (!is_array($itemArray)) {
                        continue;
                    }
                    
                    // Se for um array associativo (apenas um item), converter para array
                    if (isset($itemArray['@uniquename'])) {
                        $itemArray = [$itemArray];
                    }
                    
                    $count = count($itemArray);
                    $this->info("Encontrados $count itens na chave '$key'");
                    
                    // Adicionar itens encontrados à lista
                    foreach ($itemArray as $item) {
                        if (isset($item['@uniquename'])) {
                            $items[] = $item;
                        }
                    }
                }
            }
            
            // Também verificar itens no nível raiz (como hideoutitem)
            foreach ($jsonData as $key => $value) {
                if (str_ends_with($key, 'item') && is_array($value) && isset($value['@uniquename'])) {
                    $this->info("Encontrado item na chave raiz: $key");
                    $items[] = $value;
                }
            }
            
            $this->info("Total de itens encontrados: " . count($items));
            
            // Filtrar apenas itens que são craftables (possuem craftingrequirements)
            $craftableItems = array_filter($items, function($item) {
                return isset($item['craftingrequirements']);
            });
            
            $this->info('Total de itens encontrados: ' . count($items));
            $this->info('Total de itens craftáveis: ' . count($craftableItems));
            
            if (empty($craftableItems)) {
                $this->error('Nenhum item craftável encontrado. Verifique a estrutura do JSON.');
                return Command::FAILURE;
            }
            
            // Usar apenas itens craftáveis para importação
            $items = $craftableItems;
            
            $this->info('Iniciando importação de ' . count($items) . ' itens craftáveis...');
            
            $this->info('Arquivo JSON carregado com sucesso.');
            $this->info('Total de itens encontrados: ' . count($items));
            
            // Verificar conexão com o banco de dados
            try {
                DB::connection()->getPdo();
                $this->info('Conexão com o banco de dados estabelecida com sucesso.');
            } catch (\Exception $e) {
                $this->error('Não foi possível conectar ao banco de dados: ' . $e->getMessage());
                return Command::FAILURE;
            }
            
            $this->info('Iniciando transação de banco de dados...');
            DB::beginTransaction();
            
            $totalItems = count($items);
            $bar = $this->output->createProgressBar($totalItems);
            $bar->start();
            
            $processedItems = [];
            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            
            $this->info('\nCriando itens no banco de dados...');
            
            $this->info("\nIniciando processamento de $totalItems itens...");
            
            // Primeiro passo: criar todos os itens
            foreach ($items as $index => $itemData) {
                if ($index > 0 && $index % 20 === 0) {
                    $this->info("\nProcessados $index de $totalItems itens...");
                    $this->info("Itens criados: $createdCount, Pulados: $skippedCount, Erros: $errorCount");
                    
                    // Verificar se já salvamos algo no banco
                    try {
                        $count = DB::table('items')->count();
                        $this->info("Total de itens no banco: $count");
                    } catch (\Exception $e) {
                        $this->error("Erro ao verificar contagem de itens: " . $e->getMessage());
                    }
                }
                // Verificar se o item tem um uniquename
                $uniquename = $itemData['@uniquename'] ?? $itemData['@id'] ?? null;
                if (!$uniquename) {
                    $this->warn('Item sem identificador único, pulando...');
                    $skippedCount++;
                    
                    // Log detalhado para os primeiros itens pulados
                    if ($skippedCount <= 5) {
                        $this->warn("Dados do item sem ID: " . json_encode($itemData, JSON_PRETTY_PRINT));
                    }
                    continue;
                }
                
                // Extrair tier e enchantment do uniquename
                try {
                    [$tier, $enchantment] = $this->extractTierAndEnchantment($uniquename);
                } catch (\Exception $e) {
                    $this->error("Erro ao extrair tier/enchantment para $uniquename: " . $e->getMessage());
                    $skippedCount++;
                    $errorCount++;
                    continue;
                }
                
                // Obter o nome amigável do item
                $nicename = $itemData['@name'] ?? $itemData['@localizedname'] ?? null;
                
                // Obter outros atributos
                $fame = (int)($itemData['@fame'] ?? 0);
                $focus = (int)($itemData['@focuspoints'] ?? 0);
                $shopcategory = $itemData['@shopcategory'] ?? null;
                $shopsubcategory = $itemData['@shopsubcategory'] ?? null;
                $slottype = $itemData['@slottype'] ?? null;
                $craftingcategory = $itemData['@craftingcategory'] ?? null;
                
                try {
                    // Verificar se o item já existe e atualizar ou criar
                    $item = Item::updateOrCreate(
                        ['uniquename' => $uniquename],
                        [
                            'nicename' => $nicename,
                            'tier' => $tier,
                            'enchantment' => $enchantment,
                            'fame' => $fame,
                            'focus' => $focus,
                            'shopcategory' => $shopcategory,
                            'shopsubcategory1' => $shopsubcategory,
                            'slottype' => $slottype,
                            'craftingcategory' => $craftingcategory,
                        ]
                    );
                    
                    if ($item->wasRecentlyCreated) {
                        $this->info("✅ Novo item criado: $uniquename (ID: $item->id)");
                        $createdCount++;
                    } else {
                        $this->warn("🔄 Item atualizado: $uniquename (ID: $item->id)");
                        $updatedCount++;
                    }
                    
                    $this->info("Dados: " . json_encode([
                        'uniquename' => $uniquename,
                        'tier' => $tier,
                        'enchantment' => $enchantment
                    ]));
                    
                    $processedItems[$uniquename] = $item;
                    
                } catch (\Exception $e) {
                    $errorMessage = "❌ Erro ao processar o item $uniquename: " . $e->getMessage();
                    $this->error($errorMessage);
                    $this->error("Stack trace: " . $e->getTraceAsString());
                    $errorCount++;
                    $skippedCount++;
                    
                    // Se houver muitos erros, interromper a execução
                    if ($errorCount > 10) {
                        $this->error('Muitos erros encontrados. Interrompendo a importação.');
                        DB::rollBack();
                        return Command::FAILURE;
                    }
                    
                    continue;
                }
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            // Verificar itens no banco novamente
            try {
                $finalCount = DB::table('items')->count();
                $this->info("\n=== RESUMO DA IMPORTAÇÃO ===");
                $this->info("Itens processados: $totalItems");
                $this->info("Itens criados com sucesso: $createdCount");
                $this->info("Itens pulados: $skippedCount");
                $this->info("Erros encontrados: $errorCount");
                $this->info("Total de itens no banco: $finalCount");
                
                if ($finalCount === 0 && $createdCount > 0) {
                    $this->error("ATENÇÃO: Itens foram marcados como criados, mas não foram salvos no banco de dados!");
                    $this->error("Verifique as permissões do banco de dados e logs de erro.");
                }
            } catch (\Exception $e) {
                $this->error("Erro ao verificar contagem final de itens: " . $e->getMessage());
            }
            
            if (empty($processedItems)) {
                $this->error('Nenhum item foi processado com sucesso. Verifique os logs para mais detalhes.');
                
                // Tentar confirmar se há algum item na tabela
                try {
                    $count = DB::table('items')->count();
                    $this->info("Total de itens na tabela: $count");
                } catch (\Exception $e) {
                    $this->error("Erro ao verificar a tabela de itens: " . $e->getMessage());
                }
                
                DB::rollBack();
                return Command::FAILURE;
            }
            
            // Segundo passo: processar os materiais
            $this->info('\nProcessando materiais...');
            $bar = $this->output->createProgressBar(count($items));
            $bar->start();
            
            $materialsProcessed = 0;
            $materialsSkipped = 0;
            
            foreach ($items as $index => $itemData) {
                if ($index > 0 && $index % 100 === 0) {
                    $this->info("Processados $index itens para materiais...");
                }
                $uniquename = $itemData['@uniquename'] ?? $itemData['@id'] ?? null;
                if (!$uniquename) {
                    $this->warn('Item sem identificador único ao processar materiais, pulando...');
                    $materialsSkipped++;
                    continue;
                }
                
                $item = $processedItems[$uniquename] ?? null;
                
                if (!$item) {
                    $this->warn("Item $uniquename não encontrado nos itens processados, pulando...");
                    $materialsSkipped++;
                    continue;
                }
                
                // Processar materiais de crafting
                if (isset($itemData['craftresource'])) {
                    // Se for um único material, converter para array
                    $craftResources = isset($itemData['craftresource']['@uniquename']) 
                        ? [$itemData['craftresource']] 
                        : $itemData['craftresource'];
                    
                    foreach ($craftResources as $resourceData) {
                        $materialUniqueName = $resourceData['@uniquename'] ?? null;
                        if (!$materialUniqueName) {
                            continue;
                        }
                        
                        // Obter quantidade e retorno máximo
                        $amount = (int)($resourceData['@count'] ?? 0);
                        $maxReturnAmount = (int)($resourceData['@maxreturnamount'] ?? 0);
                        
                        // Verificar se o material já existe
                        $material = $processedItems[$materialUniqueName] ?? null;
                        
                        // Se o material não existir, criar um novo
                        if (!$material) {
                            [$tier, $enchantment] = $this->extractTierAndEnchantment($materialUniqueName);
                            
                            $material = Item::create([
                                'uniquename' => $materialUniqueName,
                                'nicename' => null, // Não temos o nome amigável no recurso
                                'tier' => $tier,
                                'enchantment' => $enchantment,
                            ]);
                            
                            $processedItems[$materialUniqueName] = $material;
                        }
                        
                        // Criar a relação entre o item e o material
                        ItemMaterial::create([
                            'item_id' => $item->id,
                            'material_id' => $material->id,
                            'amount' => $amount,
                            'max_return_amount' => $maxReturnAmount,
                        ]);
                    }
                }
                
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            // Terceiro passo: atualizar preços
            $this->info('Atualizando preços dos itens...');
            
            // Obter todos os uniquenames dos itens processados
            $uniqueNames = array_keys($processedItems);
            
            // Atualizar preços em lotes para evitar sobrecarga da API
            $batchSize = 100;
            $batches = array_chunk($uniqueNames, $batchSize);
            
            $bar = $this->output->createProgressBar(count($batches));
            $bar->start();
            
            foreach ($batches as $batch) {
                $this->priceService->updateItemsPrices($batch);
                $bar->advance();
                
                // Pequena pausa para não sobrecarregar a API
                sleep(1);
            }
            
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
}
