<?php

namespace App\Models;

use App\Enums\LieuType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « Lieu » personnel d'un habitant (modèle Eloquent, table `lieux`).
 *
 * Un endroit géolocalisé que le foyer gère lui-même (Domicile, Travail…).
 * Il remplace le quartier dans TOUTE l'interface habitant : le quartier
 * reste purement interne et est **auto-assigné** (le plus proche du point)
 * pour que coupures / périmètre / pages publiques continuent de marcher.
 */
class Lieu extends Model
{
    use HasFactory;

    protected $table = 'lieux';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nom',
        'type',
        'adresse',
        'latitude',
        'longitude',
        'quartier_id',
        'est_principal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'est_principal' => 'boolean',
            'type' => LieuType::class,
        ];
    }

    protected static function booted(): void
    {
        // Rattachement interne automatique : dès qu'un point est posé, on
        // mémorise le quartier le plus proche (aucune saisie côté habitant).
        static::saving(function (self $lieu): void {
            if ($lieu->latitude === null || $lieu->longitude === null) {
                return;
            }

            if (! $lieu->exists || $lieu->isDirty(['latitude', 'longitude'])) {
                $lieu->quartier_id = Quartier::plusProche(
                    (float) $lieu->latitude,
                    (float) $lieu->longitude,
                )?->id;
            }
        });
    }

    /**
     * Le lieu a-t-il une position pour la carte Leaflet ?
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Zone interne associée (jamais montrée à l'habitant).
     *
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'quartier_id');
    }

    public function scopePrincipal($query)
    {
        return $query->where('est_principal', true);
    }
}
