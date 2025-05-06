<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AlbionMaterial;
use Illuminate\Support\Facades\File;

class AlbionCraft extends Model
{
    /**
     * A tabela associada ao model.
     *
     * @var string
     */
    protected $table = 'craft';

    /**
     * Indica se o model deve ser timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Os atributos que são atribuíveis em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uniquename',
        'nicename',
        'craftitem1',
        'craftitem1_amount',
        'craftitem1_maxreturnamount',
        'craftitem2',
        'craftitem2_amount',
        'craftitem2_maxreturnamount',
        'craftitem3',
        'craftitem3_amount',
        'craftitem3_maxreturnamount',
        'craftitem4',
        'craftitem4_amount',
        'craftitem4_maxreturnamount',
        'craftitem5',
        'craftitem5_amount',
        'craftitem5_maxreturnamount',
        'craftitem6',
        'craftitem6_amount',
        'craftitem6_maxreturnamount',
        'fame',
        'focus',
        'shopcategory',
        'shopsubcategory1',
        'slottype',
        'craftingcategory'
    ];

    /**
     * Encontrar uma receita de crafting pelo uniquename do item.
     *
     * @param string $uniqueName
     * @return self|null
     */
    public static function findByUniqueName(string $uniqueName): ?self
    {
        return static::where('uniquename', $uniqueName)->first();
    }

    /**
     * Obter os materiais necessários para o craft.
     *
     * @return array
     */
    public function getMaterials(): array
    {
        $materials = [];
        
        for ($i = 1; $i <= 6; $i++) {
            $materialId = $this->{"craftitem{$i}"};
            $amount = $this->{"craftitem{$i}_amount"};
            
            if ($materialId && $amount) {
                $material = AlbionMaterial::where('uniquename', $materialId)->first();
                
                if (!$material) {
                    // Buscar no arquivo items.json se não encontrar no banco
                    $itemsPath = public_path('items.json');
                    $componentName = $materialId;
                    
                    if (File::exists($itemsPath)) {
                        $items = json_decode(File::get($itemsPath), true);
                        
                        foreach ($items as $item) {
                            if ($item['UniqueName'] === $materialId) {
                                $componentName = $item['LocalizedNames']['PT-BR'] ?? 
                                                $item['LocalizedNames']['EN-US'] ?? 
                                                $materialId;
                                break;
                            }
                        }
                    }
                    
                    $materials[] = [
                        'itemId' => $materialId,
                        'name' => $componentName,
                        'quantity' => (int)$amount,
                        'price' => 0, // Preço será preenchido pelo frontend
                        'maxReturn' => (int)($this->{"craftitem{$i}_maxreturnamount"} ?? 0)
                    ];
                } else {
                    $materials[] = [
                        'itemId' => $materialId,
                        'name' => $material->nicename,
                        'quantity' => (int)$amount,
                        'price' => 0, // Preço será preenchido pelo frontend
                        'maxReturn' => (int)($this->{"craftitem{$i}_maxreturnamount"} ?? 0)
                    ];
                }
            }
        }
        
        return $materials;
    }

    /**
     * Obter informações de crafting formatadas.
     *
     * @return array
     */
    public function getCraftingInfo(): array
    {
        return [
            'materials' => $this->getMaterials(),
            'totalCost' => 0, // Será calculado pelo frontend
            'fame' => (int)($this->fame ?? 0),
            'focus' => (int)($this->focus ?? 0),
            'category' => $this->craftingcategory,
            'shopCategory' => $this->shopcategory,
            'shopSubCategory' => $this->shopsubcategory1,
            'slotType' => $this->slottype
        ];
    }

    /**
     * Obter o nome formatado do item.
     * 
     * @return string
     */
    public function getFormattedName(): string
    {
        return $this->nicename ?: $this->uniquename;
    }
}
