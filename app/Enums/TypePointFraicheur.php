<?php

namespace App\Enums;

/**
 * Nature d'un point de fraîcheur (module 3) : parc ombragé, salle climatisée
 * ou fontaine publique. Sert au formulaire, aux badges et à la carte.
 */
enum TypePointFraicheur: string
{
    case Parc = 'parc';
    case SalleClimatisee = 'salle_climatisee';
    case Fontaine = 'fontaine';

    public function label(): string
    {
        return match ($this) {
            self::Parc => 'Parc / espace ombragé',
            self::SalleClimatisee => 'Salle climatisée',
            self::Fontaine => 'Fontaine',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Parc => 'park',
            self::SalleClimatisee => 'ac_unit',
            self::Fontaine => 'water_drop',
        };
    }

    /**
     * Couleur des marqueurs Leaflet.
     */
    public function couleurHex(): string
    {
        return match ($this) {
            self::Parc => '#16a34a',
            self::SalleClimatisee => '#1b77ba',
            self::Fontaine => '#0891b2',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Parc => 'bg-green-100 text-green-800',
            self::SalleClimatisee => 'bg-sky-100 text-sky-800',
            self::Fontaine => 'bg-cyan-100 text-cyan-800',
        };
    }

    /**
     * Une fontaine n'accueille pas de public « installé » : pas de capacité.
     */
    public function aUneCapacite(): bool
    {
        return $this !== self::Fontaine;
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
