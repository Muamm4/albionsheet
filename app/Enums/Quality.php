<?php

declare(strict_types=1);

namespace App\Enums;

enum Quality: int
{
    case Normal = 1;
    case Good = 2;
    case Outstanding = 3;
    case Excellent = 4;
    case Masterpiece = 5;

    public function label(): string
    {
        return match($this) {
            self::Normal => 'Normal',
            self::Good => 'Good',
            self::Outstanding => 'Outstanding',
            self::Excellent => 'Excellent',
            self::Masterpiece => 'Masterpiece',
        };
    }
}
