<?php

declare(strict_types=1);

namespace App\Enums;

enum City: string
{
    case Bridgewatch = 'Bridgewatch';
    case Caerleon = 'Caerleon';
    case FortSterling = 'Fort Sterling';
    case Lymhurst = 'Lymhurst';
    case Martlock = 'Martlock';
    case Thetford = 'Thetford';
    case BlackMarket = 'Black Market';
    case Brecilien = 'Brecilien';
    
    public function label(): string
    {
        return match($this) {
            self::Bridgewatch => 'Bridgewatch',
            self::Caerleon => 'Caerleon',
            self::FortSterling => 'Fort Sterling',
            self::Lymhurst => 'Lymhurst',
            self::Martlock => 'Martlock',
            self::Thetford => 'Thetford',
            self::BlackMarket => 'Black Market',
            self::Brecilien => 'Brecilien',
        };
    }
}
