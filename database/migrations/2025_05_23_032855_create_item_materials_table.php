<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('items')->onDelete('cascade');
            $table->integer('amount')->default(0);
            $table->integer('max_return_amount')->default(0);
            $table->timestamps();
            
            // Índice composto para evitar duplicatas
            $table->unique(['item_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_materials');
    }
};
