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
        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->unsignedTinyInteger('quality')->comment('1=Normal, 2=Good, 3=Outstanding, 4=Excellent, 5=Masterpiece');
            $table->string('city', 50);
            $table->integer('sell_price_min')->default(0);
            $table->timestamp('sell_price_min_date')->nullable();
            $table->integer('sell_price_max')->default(0);
            $table->timestamp('sell_price_max_date')->nullable();
            $table->integer('buy_price_min')->default(0);
            $table->timestamp('buy_price_min_date')->nullable();
            $table->integer('buy_price_max')->default(0);
            $table->timestamp('buy_price_max_date')->nullable();
            $table->timestamps();
            
            // Índice composto para evitar duplicatas e melhorar performance de consultas
            $table->unique(['item_id', 'quality', 'city']);
            $table->index(['item_id', 'quality']);
            $table->index(['city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_prices');
    }
};
