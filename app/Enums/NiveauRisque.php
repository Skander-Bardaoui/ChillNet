<?php

namespace App\Enums;

/**
 * Niveau de risque de coupure d'une zone, dérivé d'un score IA (0–100).
 * Ordre croissant : faible < modéré < élevé < critique.
 */
enum NiveauRisque: string
{
    case Faible = 'faible';
    case Modere = 'modere';
    case Eleve = 'eleve';
    case Critique = 'critique';

    public function label(): string
    {
        return match ($this) {
            self::Faible => 'Risque faible',
            self::Modere => 'Risque modéré',
            self::Eleve => 'Risque élevé',
            self::Critique => 'Risque critique',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Faible => 'bg-green-100 text-green-800',
            self::Modere => 'bg-amber-100 text-amber-800',
            self::Eleve => 'bg-orange-100 text-orange-800',
            self::Critique => 'bg-red-100 text-red-800',
        };
    }

    public function couleurHex(): string
    {
        return match ($this) {
            self::Faible => '#16a34a',
            self::Modere => '#f59e0b',
            self::Eleve => '#ea580c',
            self::Critique => '#dc2626',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Faible => 'shield',
            self::Modere => 'warning',
            self::Eleve => 'error',
            self::Critique => 'crisis_alert',
        };
    }

    /**
     * Paliers : 0–24 faible, 25–49 modéré, 50–74 élevé, 75–100 critique.
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Critique,
            $score >= 50 => self::Eleve,
            $score >= 25 => self::Modere,
            default => self::Faible,
        };
    }
}
