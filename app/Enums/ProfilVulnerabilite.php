<?php

namespace App\Enums;

/**
 * Profil de vulnérabilité d'un foyer face à la chaleur.
 * Un foyer peut cumuler plusieurs profils (colonne JSON users.profil_vulnerabilites).
 * `Standard` n'est jamais persisté : il ne sert que d'affichage quand la
 * liste est vide.
 */
enum ProfilVulnerabilite: string
{
    case Standard = 'standard';
    case PersonneAgee = 'personne_agee';
    case Enfant = 'enfant';
    case EquipementMedical = 'equipement_medical';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Foyer standard',
            self::PersonneAgee => 'Personne âgée',
            self::Enfant => 'Enfant en bas âge',
            self::EquipementMedical => 'Équipement médical à domicile',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Standard => 'home',
            self::PersonneAgee => 'elderly',
            self::Enfant => 'child_care',
            self::EquipementMedical => 'medical_services',
        };
    }

    /**
     * Valeurs réellement persistables (= tout sauf `standard`).
     *
     * @return list<string>
     */
    public static function persistables(): array
    {
        return array_values(array_map(
            fn (self $profil): string => $profil->value,
            array_filter(self::cases(), fn (self $profil): bool => $profil !== self::Standard),
        ));
    }
}
