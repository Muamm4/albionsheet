<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Executar o dump SQL diretamente
        $dumpPath = database_path('dump.sql');
        
        if (File::exists($dumpPath)) {
            $sql = File::get($dumpPath);
            
            // Executar o dump SQL diretamente
            DB::unprepared($sql);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover as tabelas criadas pelo dump SQL
        Schema::dropIfExists('craft');
        Schema::dropIfExists('materials');
    }
};
