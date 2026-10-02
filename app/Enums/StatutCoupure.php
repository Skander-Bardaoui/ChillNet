<?php

namespace App\Enums;

enum StatutCoupure: string
{
    case EnCours = 'en_cours';
    case Prevue = 'prevue';
    case Resolue = 'resolue';

    public function label(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Prevue => 'Prévue',
            self::Resolue => 'Résolue',
        };
    }
}
