<?php

namespace App\Enums;

/**
 * Nature d'un « lieu » personnel d'un habitant (Domicile, Travail…).
 * Sert à l'affichage (libellé + icône) ; le nom du lieu reste libre.
 */
enum LieuType: string
{
    case Domicile = 'domicile';
    case Travail = 'travail';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Domicile => 'Domicile',
            self::Travail => 'Travail',
            self::Autre => 'Autre',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Domicile => 'home',
            self::Travail => 'work',
            self::Autre => 'place',
        };
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_values(array_map(fn (self $type): string => $type->value, self::cases()));
    }
}
