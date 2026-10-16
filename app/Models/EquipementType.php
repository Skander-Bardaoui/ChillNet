<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Type d'équipement sensible — catalogue géré par l'admin.
 * Ex. : « Respirateur médical », « Climatiseur »…
 *
 * Module 4 — Ghazi.
 */
class EquipementType extends Model
{
    protected $table = 'equipement_types';

    /** @var list<string> */
    protected $fillable = [
        'nom',
        'slug',
        'icone',
        'medical',
        'description',
        'actif',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'medical' => 'boolean',
            'actif'   => 'boolean',
            'ordre'   => 'integer',
        ];
    }

    /**
     * Équipements déclarés par des habitants de ce type.
     *
     * @return HasMany<Equipement, $this>
     */
    public function equipements(): HasMany
    {
        return $this->hasMany(Equipement::class, 'equipement_type_id');
    }

    /**
     * Conseils liés à ce type d'équipement.
     *
     * @return BelongsToMany<Conseil, $this>
     */
    public function conseils(): BelongsToMany
    {
        return $this->belongsToMany(
            Conseil::class,
            'conseil_equipement_type',
            'equipement_type_id',
            'conseil_id',
        );
    }

    /**
     * Uniquement les types actifs, triés pour l'affichage.
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }
}
