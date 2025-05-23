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
            $table->unsignedTinyInteger('tier')->nullable();
            $table->unsignedTinyInteger('enchantment')->default(0);
            $table->integer('fame')->default(0);
            $table->integer('focus')->default(0);
            $table->string('shopcategory')->nullable();
            $table->string('shopsubcategory1')->nullable();
            $table->string('slottype')->nullable();
            $table->string('craftingcategory')->nullable();
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
