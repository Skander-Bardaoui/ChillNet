<?php

namespace App\Enums;

/**
 * Polarité d'un avis, calculée automatiquement par SentimentAvisService.
 */
enum Sentiment: string
{
    case Positif = 'positif';
    case Neutre = 'neutre';
    case Negatif = 'negatif';

    public function label(): string
    {
        return match ($this) {
            self::Positif => 'Positif',
            self::Neutre => 'Neutre',
            self::Negatif => 'Négatif',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Positif => 'bg-green-100 text-green-800',
            self::Neutre => 'bg-slate-100 text-slate-700',
            self::Negatif => 'bg-red-100 text-red-800',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Positif => 'sentiment_satisfied',
            self::Neutre => 'sentiment_neutral',
            self::Negatif => 'sentiment_dissatisfied',
        };
    }

    /**
     * Seuils sur le score [-1, 1] : > 0.15 positif, < -0.15 négatif.
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score > 0.15 => self::Positif,
            $score < -0.15 => self::Negatif,
            default => self::Neutre,
        };
    }
}
