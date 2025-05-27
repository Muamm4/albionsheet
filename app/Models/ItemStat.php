<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStat extends Model
{
    protected $fillable = [
        'item_id',
        'stats_data',
        'enchantment',
        'craftingrequirements',
        'upgraderequirements',
    ];

    protected $casts = [
        'stats_data' => 'array',
        'enchantment' => 'array',
        'craftingrequirements' => 'array',
        'upgraderequirements' => 'array',
    ];

    /**
     * Relacionamento com o item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
