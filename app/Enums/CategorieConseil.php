<?php

namespace App\Enums;

/**
 * Catégories des conseils (module 4 — Ghazi).
 */
enum CategorieConseil: string
{
    case Hydratation = 'hydratation';
    case Energie     = 'energie';
    case Equipements = 'equipements';
    case Medical     = 'medical';
    case General     = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Hydratation => 'Hydratation',
            self::Energie     => 'Énergie',
            self::Equipements => 'Équipements',
            self::Medical     => 'Médical',
            self::General     => 'Général',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Hydratation => 'water_drop',
            self::Energie     => 'bolt',
            self::Equipements => 'devices',
            self::Medical     => 'health_and_safety',
            self::General     => 'tips_and_updates',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Hydratation => 'bg-blue-100 text-blue-800',
            self::Energie     => 'bg-yellow-100 text-yellow-800',
            self::Equipements => 'bg-purple-100 text-purple-800',
            self::Medical     => 'bg-red-100 text-red-800',
            self::General     => 'bg-gray-100 text-gray-700',
        };
    }
}
