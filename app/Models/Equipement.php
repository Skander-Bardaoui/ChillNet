<?php

namespace App\Models;

use App\Enums\CriticiteEquipement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Équipement sensible déclaré par un habitant.
 * Scopé à l'utilisateur ; le type est issu du catalogue EquipementType.
 *
 * Module 4 — Ghazi.
 */
class Equipement extends Model
{
    protected $table = 'equipements';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'equipement_type_id',
        'nom',
        'criticite',
        'notes',
        'contact_urgence',
    ];

    protected function casts(): array
    {
        return [
            'criticite' => CriticiteEquipement::class,
        ];
    }

    /**
     * Habitant propriétaire de l'équipement.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Type catalogué de l'équipement.
     *
     * @return BelongsTo<EquipementType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EquipementType::class, 'equipement_type_id');
    }

    /**
     * Un contact d'urgence est-il obligatoire pour cet équipement ?
     * Oui si : le type est médical OU la criticité est vitale.
     */
    public function requiertContact(): bool
    {
        return $this->type?->medical || $this->criticite === CriticiteEquipement::Vitale;
    }

    /**
     * Équipements triés par criticité décroissante pour l'affichage prioritaire.
     */
    public function scopeParCriticite($query)
    {
        return $query->orderByRaw("CASE criticite WHEN 'vitale' THEN 1 WHEN 'elevee' THEN 2 ELSE 3 END");
    }
}
