<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestDatabaseInsert extends Command
{
    protected $signature = 'db:test-insert';
    protected $description = 'Test database insertion';

    public function handle(): int
    {
        $this->info('Testing database insertion...');
        
        // Test 1: Check database connection
        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection is working');
        } catch (\Exception $e) {
            $this->error('❌ Database connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        // Test 2: Direct DB insert
        $this->info('\nTest 1: Direct DB insert...');
        try {
            $id = DB::table('items')->insertGetId([
                'uniquename' => 'TEST_ITEM_1',
                'nicename' => 'Test Item 1',
                'tier' => 1,
                'enchantment' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->info('✅ Direct insert successful. ID: ' . $id);
            
            // Verify the insert
            $count = DB::table('items')->where('uniquename', 'TEST_ITEM_1')->count();
            $this->info('✅ Verification: Found ' . $count . ' test items in database');
            
            // Clean up
            DB::table('items')->where('uniquename', 'TEST_ITEM_1')->delete();
            
        } catch (\Exception $e) {
            $this->error('❌ Direct insert failed: ' . $e->getMessage());
            Log::error('Direct insert failed', ['error' => $e->getMessage()]);
        }
        
        // Test 3: Model create
        $this->info('\nTest 2: Model create...');
        try {
            $item = new Item([
                'uniquename' => 'TEST_ITEM_2',
                'nicename' => 'Test Item 2',
                'tier' => 2,
                'enchantment' => 0,
            ]);
            
            $saved = $item->save();
            
            if ($saved) {
                $this->info('✅ Model save successful. ID: ' . $item->id);
                
                // Verify the model save
                $count = Item::where('uniquename', 'TEST_ITEM_2')->count();
                $this->info('✅ Verification: Found ' . $count . ' test items in database');
                
                // Check for validation errors
                $errors = $item->getErrors();
                if (!empty($errors)) {
                    $this->warn('Validation errors: ' . json_encode($errors));
                }
                
                // Clean up
                $item->delete();
            } else {
                $this->error('❌ Model save returned false');
                $this->error('Validation errors: ' . json_encode($item->getErrors()));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Model save failed: ' . $e->getMessage());
            Log::error('Model save failed', ['error' => $e->getMessage()]);
        }
        
        // Test 4: Check table structure
        $this->info('\nTest 3: Table structure...');
        try {
            $columns = DB::getSchemaBuilder()->getColumnListing('items');
            $this->info('✅ Items table columns: ' . implode(', ', $columns));
            
            // Check for required columns
            $required = ['id', 'uniquename', 'tier', 'enchantment', 'created_at', 'updated_at'];
            $missing = array_diff($required, $columns);
            
            if (!empty($missing)) {
                $this->error('❌ Missing columns: ' . implode(', ', $missing));
            } else {
                $this->info('✅ All required columns exist');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to check table structure: ' . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
}
