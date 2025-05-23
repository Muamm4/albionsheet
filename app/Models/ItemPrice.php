<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\City;
use App\Enums\Quality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPrice extends Model
{
    protected $fillable = [
        'item_id',
        'quality',
        'city',
        'sell_price_min',
        'sell_price_min_date',
        'sell_price_max',
        'sell_price_max_date',
        'buy_price_min',
        'buy_price_min_date',
        'buy_price_max',
        'buy_price_max_date',
    ];

    protected $dates = [
        'sell_price_min_date',
        'sell_price_max_date',
        'buy_price_min_date',
        'buy_price_max_date',
    ];

    protected $casts = [
        'quality' => Quality::class,
        'city' => City::class,
        'sell_price_min' => 'integer',
        'sell_price_max' => 'integer',
        'buy_price_min' => 'integer',
        'buy_price_max' => 'integer',
    ];

    /**
     * Set the sell_price_min_date attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setSellPriceMinDateAttribute($value)
    {
        $this->attributes['sell_price_min_date'] = $this->parseDate($value);
    }

    /**
     * Set the sell_price_max_date attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setSellPriceMaxDateAttribute($value)
    {
        $this->attributes['sell_price_max_date'] = $this->parseDate($value);
    }

    /**
     * Set the buy_price_min_date attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setBuyPriceMinDateAttribute($value)
    {
        $this->attributes['buy_price_min_date'] = $this->parseDate($value);
    }

    /**
     * Set the buy_price_max_date attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setBuyPriceMaxDateAttribute($value)
    {
        $this->attributes['buy_price_max_date'] = $this->parseDate($value);
    }

    /**
     * Parse the given date value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function parseDate($value)
    {
        if (empty($value) || $value === '0001-01-01 00:00:00' || $value === '0001-01-01T00:00:00') {
            return null;
        }

        return $value;
    }

    /**
     * Relacionamento com o item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
