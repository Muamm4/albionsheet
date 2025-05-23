<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemMaterial extends Pivot
{
    protected $table = 'item_materials';
    
    protected $fillable = [
        'item_id',
        'material_id',
        'amount',
        'max_return_amount',
    ];
    
    protected $casts = [
        'amount' => 'integer',
        'max_return_amount' => 'integer',
    ];
}
