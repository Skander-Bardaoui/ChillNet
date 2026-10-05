<?php

namespace App\Enums;

/**
 * Cycle de modération d'un point de fraîcheur :
 * proposé par un habitant (en attente) → validé ou refusé par l'admin.
 * Seuls les points validés sont visibles côté habitants.
 */
enum StatutPointFraicheur: string
{
    case EnAttente = 'en_attente';
    case Valide = 'valide';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::EnAttente => 'bg-amber-100 text-amber-800',
            self::Valide => 'bg-green-100 text-green-800',
            self::Refuse => 'bg-red-100 text-red-800',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::EnAttente => 'hourglass_top',
            self::Valide => 'verified',
            self::Refuse => 'block',
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
