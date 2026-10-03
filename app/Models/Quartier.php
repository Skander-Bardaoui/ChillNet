<?php

namespace App\Models;

use App\Support\Geo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quartier du réseau ChillNet (modèle Eloquent, table `quartiers`).
 */
class Quartier extends Model
{
    protected $table = 'quartiers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'ville',
        'code_postal',
        'latitude',
        'longitude',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Le quartier a-t-il une position pour la carte Leaflet ?
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Quartier géolocalisé le plus proche d'un point (Haversine, en PHP).
     * Sert à rattacher automatiquement un « lieu » habitant à une zone
     * interne, sans que l'habitant ait à choisir un quartier.
     */
    public static function plusProche(?float $latitude, ?float $longitude): ?self
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return static::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->sortBy(fn (self $quartier): float => Geo::distanceKm(
                $latitude,
                $longitude,
                (float) $quartier->latitude,
                (float) $quartier->longitude,
            ))
            ->first();
    }

    /**
     * @return HasMany<Residence, $this>
     */
    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class, 'quartier_id');
    }

    /**
     * Coupures qui touchent ce quartier (la zone = le quartier).
     *
     * @return HasMany<Coupure, $this>
     */
    public function coupures(): HasMany
    {
        return $this->hasMany(Coupure::class, 'quartier_id');
    }

    /**
     * Alertes canicule qui couvrent ce quartier (relation N---N).
     *
     * @return BelongsToMany<Alerte, $this>
     */
    public function alertes(): BelongsToMany
    {
        return $this->belongsToMany(Alerte::class, 'alerte_quartier', 'quartier_id', 'alerte_id');
    }

    /**
     * Libellé complet du quartier (nom + ville + code postal).
     * Le code postal est facultatif pour un quartier déclaré par un habitant.
     */
    public function libelle(): string
    {
        $localisation = $this->code_postal
            ? $this->ville.' · '.$this->code_postal
            : $this->ville;

        return trim(sprintf('%s (%s)', $this->nom, $localisation));
    }
}
