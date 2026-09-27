<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Résidence rattachée à un quartier (modèle Eloquent, table `residences`).
 */
class Residence extends Model
{
    protected $table = 'residences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'adresse',
        'nombre_logements',
        'salle_climatisee',
        'point_fraicheur',
        'quartier_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nombre_logements' => 'integer',
            'salle_climatisee' => 'boolean',
            'point_fraicheur' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'quartier_id');
    }

    /**
     * Foyers rattachés à cette résidence.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'residence_id');
    }

    /**
     * Champ de recherche scopé : résidences qui sont des points de fraîcheur.
     */
    public function scopePointFraicheur($query)
    {
        return $query->where('point_fraicheur', true);
    }
}
