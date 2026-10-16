<?php

namespace App\Models;

use App\Enums\CategorieConseil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Conseil de prévention canicule / coupure géré par l'admin.
 * Un conseil peut être lié à 0-N types d'équipements sensibles.
 *
 * Module 4 — Ghazi.
 */
class Conseil extends Model
{
    protected $table = 'conseils';

    /** @var list<string> */
    protected $fillable = [
        'titre',
        'categorie',
        'contenu',
        'icone',
        'actif',
        'ordre',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieConseil::class,
            'actif'     => 'boolean',
            'ordre'     => 'integer',
        ];
    }

    /**
     * Admin qui a créé le conseil.
     *
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Types d'équipements auxquels ce conseil s'applique (N-N).
     *
     * @return BelongsToMany<EquipementType, $this>
     */
    public function equipementTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            EquipementType::class,
            'conseil_equipement_type',
            'conseil_id',
            'equipement_type_id',
        );
    }

    /** Conseils publiés (actif = true). */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /** Filtre par catégorie. */
    public function scopeParCategorie($query, CategorieConseil $categorie)
    {
        return $query->where('categorie', $categorie->value);
    }

    /**
     * Conseils pertinents pour un ensemble de slugs d'équipements.
     * Retourne les conseils liés à AU MOINS l'un de ces types, plus les
     * conseils généraux (sans type lié).
     *
     * @param  array<int>  $typeIds   IDs des EquipementType déclarés par l'habitant
     */
    public function scopePourEquipements($query, array $typeIds)
    {
        return $query->where(function ($q) use ($typeIds) {
            // Conseils généraux (non rattachés à un type).
            $q->whereDoesntHave('equipementTypes');

            if ($typeIds !== []) {
                // Ou conseils liés à l'un de ces types.
                $q->orWhereHas('equipementTypes', fn ($sub) => $sub->whereIn('equipement_types.id', $typeIds));
            }
        });
    }
}
