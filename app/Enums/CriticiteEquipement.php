<?php

namespace App\Enums;

/**
 * Criticité d'un équipement sensible déclaré par un habitant.
 * Module 4 — Ghazi.
 */
enum CriticiteEquipement: string
{
    case Normale   = 'normale';
    case Elevee    = 'elevee';
    case Vitale    = 'vitale';

    public function label(): string
    {
        return match ($this) {
            self::Normale => 'Normale',
            self::Elevee  => 'Élevée',
            self::Vitale  => 'Vitale (médical)',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Normale => 'bg-surface-container-high text-on-surface-variant',
            self::Elevee  => 'bg-orange-100 text-orange-800',
            self::Vitale  => 'bg-red-100 text-red-800',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Normale => 'info',
            self::Elevee  => 'warning',
            self::Vitale  => 'emergency',
        };
    }

    /**
     * Une criticité vitale ou un type médical impose le champ contact_urgence.
     */
    public function requiertContact(): bool
    {
        return $this === self::Vitale;
    }

    public function priorite(): int
    {
        return match ($this) {
            self::Normale => 1,
            self::Elevee  => 2,
            self::Vitale  => 3,
        };
    }
}
