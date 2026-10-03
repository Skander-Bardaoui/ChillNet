<?php

namespace App\Enums;

/**
 * Statut d'affichage d'une alerte, DÉRIVÉ de son créneau [debut, fin]
 * (il n'est jamais stocké en base) :
 *   - Programmée : debut dans le futur ;
 *   - Active     : debut <= maintenant <= fin ;
 *   - Terminée   : fin dépassée.
 */
enum StatutAlerte: string
{
    case Programmee = 'programmee';
    case Active = 'active';
    case Terminee = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::Programmee => 'Programmée',
            self::Active => 'Active',
            self::Terminee => 'Terminée',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Programmee => 'bg-sky-100 text-sky-800',
            self::Active => 'bg-red-100 text-red-800',
            self::Terminee => 'bg-surface-variant text-on-surface-variant',
        };
    }
}
