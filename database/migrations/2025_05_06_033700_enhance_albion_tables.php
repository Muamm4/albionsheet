<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar se as tabelas existem antes de adicionar índices
        if (Schema::hasTable('craft')) {
            // Verificar se a coluna uniquename existe
            if (Schema::hasColumn('craft', 'uniquename')) {
                Schema::table('craft', function (Blueprint $table) {
                    // Adicionar índice para uniquename se não existir
                    if (!$this->hasIndex('craft', 'craft_uniquename_index')) {
                        $table->index('uniquename');
                    }
                });
            }
            
            // Verificar se a coluna shopcategory existe
            if (Schema::hasColumn('craft', 'shopcategory')) {
                Schema::table('craft', function (Blueprint $table) {
                    // Adicionar índice para shopcategory se não existir
                    if (!$this->hasIndex('craft', 'craft_shopcategory_index')) {
                        $table->index('shopcategory');
                    }
                });
            }
            
            // Verificar se a coluna craftingcategory existe
            if (Schema::hasColumn('craft', 'craftingcategory')) {
                Schema::table('craft', function (Blueprint $table) {
                    // Adicionar índice para craftingcategory se não existir
                    if (!$this->hasIndex('craft', 'craft_craftingcategory_index')) {
                        $table->index('craftingcategory');
                    }
                });
            }
        }

        // Verificar se a tabela materials existe
        if (Schema::hasTable('materials')) {
            // Verificar se a coluna uniquename existe
            if (Schema::hasColumn('materials', 'uniquename')) {
                Schema::table('materials', function (Blueprint $table) {
                    // Adicionar índice para uniquename se não existir
                    if (!$this->hasIndex('materials', 'materials_uniquename_index')) {
                        $table->index('uniquename');
                    }
                });
            }
            
            // Verificar se a coluna shopcategory existe
            if (Schema::hasColumn('materials', 'shopcategory')) {
                Schema::table('materials', function (Blueprint $table) {
                    // Adicionar índice para shopcategory se não existir
                    if (!$this->hasIndex('materials', 'materials_shopcategory_index')) {
                        $table->index('shopcategory');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover índices apenas se as tabelas existirem
        if (Schema::hasTable('craft')) {
            Schema::table('craft', function (Blueprint $table) {
                if ($this->hasIndex('craft', 'craft_uniquename_index')) {
                    $table->dropIndex(['uniquename']);
                }
                
                if ($this->hasIndex('craft', 'craft_shopcategory_index')) {
                    $table->dropIndex(['shopcategory']);
                }
                
                if ($this->hasIndex('craft', 'craft_craftingcategory_index')) {
                    $table->dropIndex(['craftingcategory']);
                }
            });
        }

        if (Schema::hasTable('materials')) {
            Schema::table('materials', function (Blueprint $table) {
                if ($this->hasIndex('materials', 'materials_uniquename_index')) {
                    $table->dropIndex(['uniquename']);
                }
                
                if ($this->hasIndex('materials', 'materials_shopcategory_index')) {
                    $table->dropIndex(['shopcategory']);
                }
            });
        }
    }

    /**
     * Verifica se um índice existe na tabela.
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function hasIndex($table, $index)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        
        foreach ($indexes as $idx) {
            if ($idx->Key_name === $index) {
                return true;
            }
        }
        
        return false;
    }
};
