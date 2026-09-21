<?php

namespace App\Enums;

enum Role: string
{
    case Habitant = 'habitant';
    case Gestionnaire = 'gestionnaire';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Habitant => 'Habitant',
            self::Gestionnaire => 'Gestionnaire de résidence',
            self::Admin => 'Administrateur',
        };
    }
}
