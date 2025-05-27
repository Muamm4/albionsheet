<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Quality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;

class Item extends Model
{
    protected $fillable = [
        'id',
        'uniquename',
        'nicename',
        'description',
        'tier',
        'enchantment_level',
        'item_power',
        'shop_category',
        'shop_subcategory1',
        'slot_type',
        'crafting_category',
        'created_at',
        'updated_at',
    ];

    public static function rules(): array
    {
        return [
            'uniquename' => 'required|string|max:255|unique:items,uniquename',
            'description' => 'nullable|string|max:255',
            'nicename' => 'nullable|string|max:255',
            'enchantment_level' => 'required|integer|min:0|max:4',
            'tier' => 'required|integer|min:1|max:8',
            'shopcategory' => 'nullable|string|max:100',
            'shopsubcategory1' => 'nullable|string|max:100',
            'slottype' => 'nullable|string|max:50',
            'craftingcategory' => 'nullable|string|max:100',
        ];
    }

    public static function messages(): array
    {
        return [
            'uniquename.required' => 'O campo uniquename é obrigatório.',
            'uniquename.unique' => 'Já existe um item com este uniquename.',
            'tier.required' => 'O campo tier é obrigatório.',
            'tier.integer' => 'O campo tier deve ser um número inteiro.',
            'tier.min' => 'O campo tier deve ser no mínimo :min.',
            'tier.max' => 'O campo tier não pode ser maior que :max.',
            'enchantment.required' => 'O campo enchantment é obrigatório.',
            'enchantment.integer' => 'O campo enchantment deve ser um número inteiro.',
            'enchantment.min' => 'O campo enchantment deve ser no mínimo :min.',
            'enchantment.max' => 'O campo enchantment não pode ser maior que :max.',
        ];
    }
    
    public function getErrors(): array
    {
        $validator = Validator::make($this->attributesToArray(), static::rules(), static::messages());
        
        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }
        
        return [];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_materials', 'item_id', 'material_id')
            ->withPivot(['amount', 'max_return_amount'])
            ->withTimestamps();
    }
    public function stats(): HasMany
    {
        return $this->hasMany(ItemStat::class);
    }

    public function getFormattedName(): string
    {
        return $this->nicename ?: $this->uniquename;
    }

    public function getCraftingInfo(): array
    {
        return [
            'fame' => $this->fame,
            'focus' => $this->focus,
            'category' => $this->craftingcategory,
            'shopCategory' => $this->shopcategory,
            'shopSubCategory' => $this->shopsubcategory1,
            'slotType' => $this->slottype
        ];
    }
}
