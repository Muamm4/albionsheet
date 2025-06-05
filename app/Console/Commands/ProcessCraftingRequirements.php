<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemMaterial;
use App\Models\ItemStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCraftingRequirements extends Command
{
    protected $signature = 'albion:process-crafting';
    protected $description = 'Processa os requisitos de crafting dos itens e atualiza a tabela item_materials';

    // Categorias de itens que queremos processar
    private array $targetCategories = [
        'accessories', 'offhand', 'armor', 'magic', 'melee'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando processamento de requisitos de crafting...');

        try {
            DB::beginTransaction();

            // Buscar itens das categorias alvo
            $items = Item::whereIn('shop_category', $this->targetCategories)
                ->orWhereIn('shop_subcategory1', $this->targetCategories)
                ->get();

            $this->info("Encontrados {$items->count()} itens para processamento.");

            $bar = $this->output->createProgressBar($items->count());
            $bar->start();

            $processedItems = 0;
            $skippedItems = 0;

            foreach ($items as $item) {
                // Buscar estatísticas do item
                $itemStats = $item->stats()->first();
                
                if (!$itemStats || !isset($itemStats->craftingrequirements) || empty($itemStats->craftingrequirements)) {
                    $skippedItems++;
                    $bar->advance();
                    continue;
                }

                // Processar os requisitos de crafting
                $this->processCraftingRequirements($item, $itemStats);
                $processedItems++;
                
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            DB::commit();
            $this->info("Processamento concluído com sucesso!");
            $this->info("Itens processados: {$processedItems}");
            $this->info("Itens ignorados (sem requisitos de crafting): {$skippedItems}");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erro durante o processamento: {$e->getMessage()}");
            Log::error('Erro durante o processamento de requisitos de crafting', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Processa os requisitos de crafting de um item e cria os relacionamentos na tabela item_materials
     */
    private function processCraftingRequirements(Item $item, ItemStat $itemStat): void
    {
        $craftingRequirements = $itemStat->craftingrequirements;
    
        // Verificar se há múltiplas receitas
        if (isset($craftingRequirements['craftresource'])) {
            // Caso de uma única receita
            $this->processSingleRecipe($item, $craftingRequirements);
        } elseif (isset($craftingRequirements['recipes']) && is_array($craftingRequirements['recipes'])) {
            // Caso de múltiplas receitas
            $this->processMultipleRecipes($item, $craftingRequirements['recipes']);
        }
    }

    /**
     * Processa uma única receita de crafting
     */
    private function processSingleRecipe(Item $item, array $craftingRequirements): void
    {
        // Limpar materiais existentes para evitar duplicação
        $item->materials()->detach();
        
        if (!isset($craftingRequirements['craftresource'])) {
            return;
        }
        
        $resources = $craftingRequirements['craftresource'];
        
        // Garantir que estamos trabalhando com um array de recursos
        if (!isset($resources[0])) {
            $resources = [$resources];
        }
        
        foreach ($resources as $resource) {
            $this->addMaterialToItem($item, $resource);
        }
    }

    /**
     * Processa múltiplas receitas de crafting
     */
    private function processMultipleRecipes(Item $item, array $recipes): void
    {
        // Limpar materiais existentes para evitar duplicação
        $item->materials()->detach();
        
        // Por padrão, usamos a primeira receita
        // Em uma implementação mais avançada, poderíamos armazenar todas as receitas
        if (empty($recipes) || !isset($recipes[0])) {
            return;
        }
        
        $firstRecipe = $recipes[0];
        
        if (!isset($firstRecipe['craftresource'])) {
            return;
        }
        
        $resources = $firstRecipe['craftresource'];
        
        // Garantir que estamos trabalhando com um array de recursos
        if (!isset($resources[0])) {
            $resources = [$resources];
        }
        
        foreach ($resources as $resource) {
            $this->addMaterialToItem($item, $resource);
        }
    }

    /**
     * Adiciona um material ao item
     */
    private function addMaterialToItem(Item $item, array $resource): void
    {
        if (!isset($resource['@uniquename'])) {
            return;
        }
        
        $uniquename = $resource['@uniquename'];
        $amount = $resource['@count'] ?? 1;
        $returnAmount = $resource['@maxreturnamount'] ?? 0;
        
        // Buscar o material no banco de dados
        $material = Item::where('uniquename', $uniquename)->first();
        
        if (!$material) {
            // Se o material não existir, podemos criar um item básico
            $material = Item::create([
                'uniquename' => $uniquename,
                'nicename' => $uniquename, // Poderia ser melhorado com uma busca de nome amigável
                'tier' => $this->extractTier($uniquename),
                'enchantment_level' => $this->extractEnchantment($uniquename),
            ]);
        }
        
        // Criar o relacionamento entre o item e o material
        $item->materials()->attach($material->id, [
            'amount' => $amount,
            'max_return_amount' => $returnAmount,
        ]);
    }

    /**
     * Extrai o tier do uniquename do item
     */
    private function extractTier(string $uniquename): int
    {
        if (preg_match('/^T(\d+)_/', $uniquename, $matches)) {
            return (int)$matches[1];
        }
        
        return 1; // Tier padrão
    }

    /**
     * Extrai o nível de encantamento do uniquename do item
     */
    private function extractEnchantment(string $uniquename): int
    {
        if (preg_match('/@(\d+)$/', $uniquename, $matches)) {
            return (int)$matches[1];
        }
        
        return 0; // Sem encantamento
    }
}
