<?php

namespace App\Enums;

/**
 * Santé PRÉDITE d'un point de fraîcheur (module 3), dérivée du score IA 0–100 :
 * risque que le point reçoive au moins deux avis négatifs dans les 14 jours.
 * Ordre croissant : bon < à surveiller < dégradé < critique.
 */
enum SantePoint: string
{
    case Bon = 'bon';
    case ASurveiller = 'a_surveiller';
    case Degrade = 'degrade';
    case Critique = 'critique';

    public function label(): string
    {
        return match ($this) {
            self::Bon => 'Bon état',
            self::ASurveiller => 'À surveiller',
            self::Degrade => 'Dégradé',
            self::Critique => 'Critique',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Bon => 'bg-green-100 text-green-800',
            self::ASurveiller => 'bg-amber-100 text-amber-800',
            self::Degrade => 'bg-orange-100 text-orange-800',
            self::Critique => 'bg-red-100 text-red-800',
        };
    }

    public function couleurHex(): string
    {
        return match ($this) {
            self::Bon => '#16a34a',
            self::ASurveiller => '#f59e0b',
            self::Degrade => '#ea580c',
            self::Critique => '#dc2626',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Bon => 'health_and_safety',
            self::ASurveiller => 'visibility',
            self::Degrade => 'trending_down',
            self::Critique => 'crisis_alert',
        };
    }

    /**
     * Un point dont l'administrateur doit s'occuper (niveau >= à surveiller).
     */
    public function estARisque(): bool
    {
        return $this !== self::Bon;
    }

    /**
     * Paliers : 0–24 bon, 25–49 à surveiller, 50–74 dégradé, 75–100 critique
     * (identiques à `fraicheur.rules.niveau_sante` côté Python).
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Critique,
            $score >= 50 => self::Degrade,
            $score >= 25 => self::ASurveiller,
            default => self::Bon,
        };
    }
}
