<?php

namespace App\Enums;

/**
 * Affluence ressentie par un visiteur lors de son passage (saisie dans
 * l'avis). Alimente l'estimation d'affluence du moteur de recommandation.
 */
enum Affluence: string
{
    case Faible = 'faible';
    case Moyenne = 'moyenne';
    case Forte = 'forte';

    public function label(): string
    {
        return match ($this) {
            self::Faible => 'Peu de monde',
            self::Moyenne => 'Affluence moyenne',
            self::Forte => 'Bondé',
        };
    }

    /**
     * Taux d'occupation représentatif (0–1) utilisé par l'IA.
     */
    public function taux(): float
    {
        return match ($this) {
            self::Faible => 0.25,
            self::Moyenne => 0.6,
            self::Forte => 0.95,
        };
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
