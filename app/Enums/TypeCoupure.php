<?php

namespace App\Enums;

enum TypeCoupure: string
{
    case Delestage = 'delestage';
    case Surcharge = 'surcharge';
    case Panne = 'panne';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Delestage => 'Délestage',
            self::Surcharge => 'Surcharge',
            self::Panne => 'Panne',
            self::Maintenance => 'Maintenance',
        };
    }
}
