<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\AlbionPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateItemPrices extends Command
{
    /**
     * O nome e a assinatura do comando do console.
     *
     * @var string
     */
    protected $signature = 'albion:update-prices 
                            {--batch-size=100 : Número de itens por lote}
                            {--no-cache : Força a atualização ignorando o cache}
                            {--category= : Filtrar por categoria (resources, accessories, armor, etc.)}
                            {--tier= : Filtrar por tier específico (1-8)}';

    /**
     * A descrição do comando do console.
     *
     * @var string
     */
    protected $description = 'Atualiza os preços de todos os itens utilizando a API do Albion Online';

    /**
     * Serviço para buscar e atualizar preços do Albion Online.
     *
     * @var AlbionPriceService
     */
    protected AlbionPriceService $priceService;

    /**
     * Cria uma nova instância do comando.
     *
     * @param AlbionPriceService $priceService
     * @return void
     */
    public function __construct(AlbionPriceService $priceService)
    {
        parent::__construct();
        $this->priceService = $priceService;
    }

    /**
     * Executa o comando do console.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Iniciando atualização de preços dos itens do Albion Online...');
        
        try {
            // Configurações
            $batchSize = (int) $this->option('batch-size');
            $useCache = !$this->option('no-cache');
            $category = $this->option('category');
            $tier = $this->option('tier');
            
            // Construir a query com base nos filtros
            $query = Item::query();
            
            if ($category) {
                $query->where('shop_category', $category);
                $this->info("Filtrando por categoria: {$category}");
            }
            
            if ($tier) {
                $query->where('tier', (int) $tier);
                $this->info("Filtrando por tier: {$tier}");
            }
            
            // Contar total de itens
            $totalItems = $query->count();
            
            if ($totalItems === 0) {
                $this->warn('Nenhum item encontrado com os filtros especificados.');
                return Command::SUCCESS;
            }
            
            $this->info("Total de itens a serem atualizados: {$totalItems}");
            $this->info("Tamanho do lote: {$batchSize}");
            $this->info("Usando cache: " . ($useCache ? 'Sim' : 'Não'));
            
            // Confirmar com o usuário
            if (!$this->confirm('Deseja continuar com a atualização?', true)) {
                $this->info('Operação cancelada pelo usuário.');
                return Command::SUCCESS;
            }
            
            // Processar em lotes
            $bar = $this->output->createProgressBar($totalItems);
            $bar->start();
            
            $totalBatches = ceil($totalItems / $batchSize);
            $updatedItems = 0;
            $failedItems = 0;
            
            for ($batch = 0; $batch < $totalBatches; $batch++) {
                // Buscar lote de itens
                $items = $query->skip($batch * $batchSize)
                               ->take($batchSize)
                               ->get();
                
                if ($items->isEmpty()) {
                    continue;
                }
                
                // Atualizar preços do lote
                try {
                    $this->priceService->updateItemsPrices($items, $useCache);
                    $updatedItems += $items->count();
                } catch (\Exception $e) {
                    $this->error("Erro ao atualizar lote #{$batch}: {$e->getMessage()}");
                    Log::error("Erro ao atualizar preços do lote #{$batch}", [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failedItems += $items->count();
                }
                
                // Atualizar barra de progresso
                $bar->advance($items->count());
                
                // Pequena pausa para não sobrecarregar a API
                if ($batch < $totalBatches - 1) {
                    usleep(500000); // 500ms
                }
            }
            
            $bar->finish();
            $this->newLine(2);
            
            // Resumo final
            $this->info('Atualização de preços concluída!');
            $this->info("Itens atualizados com sucesso: {$updatedItems}");
            
            if ($failedItems > 0) {
                $this->warn("Itens com falha na atualização: {$failedItems}");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Erro durante a atualização de preços: {$e->getMessage()}");
            Log::error('Erro durante a atualização de preços do Albion Online', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
