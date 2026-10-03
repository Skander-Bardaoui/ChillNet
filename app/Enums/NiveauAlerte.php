<?php

namespace App\Enums;

/**
 * Niveau de vigilance canicule d'une alerte.
 * Ordre de gravité croissant : jaune < orange < rouge.
 */
enum NiveauAlerte: string
{
    case Jaune = 'jaune';
    case Orange = 'orange';
    case Rouge = 'rouge';

    public function label(): string
    {
        return match ($this) {
            self::Jaune => 'Vigilance jaune',
            self::Orange => 'Vigilance orange',
            self::Rouge => 'Vigilance rouge',
        };
    }

    /**
     * Classes Tailwind du badge (identiques aux maquettes front/back existantes).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Jaune => 'bg-amber-100 text-amber-800',
            self::Orange => 'bg-orange-100 text-orange-800',
            self::Rouge => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Couleur (marqueurs Leaflet, accents de bandeau).
     */
    public function couleurHex(): string
    {
        return match ($this) {
            self::Jaune => '#f59e0b',
            self::Orange => '#ea580c',
            self::Rouge => '#dc2626',
        };
    }

    /**
     * Icône Material Symbols (même jeu que les vues statiques).
     */
    public function icone(): string
    {
        return match ($this) {
            self::Jaune => 'thermostat',
            self::Orange => 'wb_sunny',
            self::Rouge => 'warning',
        };
    }

    public function gravite(): int
    {
        return match ($this) {
            self::Jaune => 1,
            self::Orange => 2,
            self::Rouge => 3,
        };
    }

    /**
     * Le niveau le plus grave d'une collection de niveaux (ou null si vide).
     *
     * @param  iterable<NiveauAlerte>  $niveaux
     */
    public static function plusGrave(iterable $niveaux): ?self
    {
        $max = null;

        foreach ($niveaux as $niveau) {
            if ($max === null || $niveau->gravite() > $max->gravite()) {
                $max = $niveau;
            }
        }

        return $max;
    }
}
