<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlbionMaterial extends Model
{
    /**
     * A tabela associada ao model.
     *
     * @var string
     */
    protected $table = 'materials';

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
        'itemvalue'
    ];

    /**
     * Encontrar um material pelo seu uniquename.
     *
     * @param string $uniqueName
     * @return self|null
     */
    public static function findByUniqueName(string $uniqueName): ?self
    {
        return static::where('uniquename', $uniqueName)->first();
    }

    /**
     * Obter o valor do item.
     *
     * @return int
     */
    public function getValue(): int
    {
        return (int) $this->itemvalue;
    }

    /**
     * Obter o nome formatado do material.
     *
     * @return string
     */
    public function getFormattedName(): string
    {
        return $this->nicename ?: $this->uniquename;
    }
}
