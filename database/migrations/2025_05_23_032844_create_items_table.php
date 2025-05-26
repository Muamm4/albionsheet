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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('uniquename')->unique()->index();
            $table->string('nicename')->nullable();
            $table->string('description')->nullable();
            $table->unsignedTinyInteger('tier')->nullable();
            $table->unsignedTinyInteger('enchantment_level')->default(0);
            $table->string('shop_category')->nullable();
            $table->string('shop_subcategory1')->nullable();
            $table->string('slot_type')->nullable();
            $table->string('crafting_category')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
