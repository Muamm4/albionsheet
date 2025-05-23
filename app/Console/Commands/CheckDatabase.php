<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDatabase extends Command
{
    protected $signature = 'db:check';
    protected $description = 'Check database connection and tables';

    public function handle(): int
    {
        $this->info('Checking database connection...');
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection successful');
        } catch (\Exception $e) {
            $this->error('❌ Could not connect to the database: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        // Check if tables exist
        $tables = ['items', 'item_materials', 'item_prices'];
        
        $this->info('\nChecking tables...');
        $headers = ['Table', 'Exists', 'Row Count'];
        $rows = [];
        
        foreach ($tables as $table) {
            $exists = Schema::hasTable($table) ? '✅' : '❌';
            $count = 'N/A';
            
            if ($exists === '✅') {
                try {
                    $count = DB::table($table)->count();
                } catch (\Exception $e) {
                    $count = 'Error: ' . $e->getMessage();
                }
            }
            
            $rows[] = [$table, $exists, $count];
        }
        
        $this->table($headers, $rows);
        
        // Check migrations
        $this->info('\nChecking migrations...');
        try {
            $migrations = DB::table('migrations')->get();
            $this->info('Migrations table exists with ' . $migrations->count() . ' entries');
            
            $this->info('\nLatest migrations:');
            $latestMigrations = DB::table('migrations')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->pluck('migration')
                ->toArray();
                
            foreach ($latestMigrations as $migration) {
                $this->line("- $migration");
            }
        } catch (\Exception $e) {
            $this->error('Error checking migrations: ' . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
}
