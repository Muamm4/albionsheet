<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDatabaseStructure extends Command
{
    protected $signature = 'db:check-structure';
    protected $description = 'Check the database structure';

    public function handle(): int
    {
        $this->info('Checking database structure...');
        
        // Check if items table exists
        if (!Schema::hasTable('items')) {
            $this->error('Items table does not exist!');
            return Command::FAILURE;
        }
        
        $this->info('Items table exists.');
        
        // Get columns in items table
        $columns = Schema::getColumnListing('items');
        $this->info('\nColumns in items table:');
        foreach ($columns as $column) {
            $this->line("- $column");
        }
        
        // Check if we can query the table
        try {
            $count = DB::table('items')->count();
            $this->info("\nTotal items in database: $count");
            
            if ($count > 0) {
                $firstItem = DB::table('items')->first();
                $this->info('\nFirst item in database:');
                $this->line(json_encode($firstItem, JSON_PRETTY_PRINT));
            }
        } catch (\Exception $e) {
            $this->error('Error querying items table: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
