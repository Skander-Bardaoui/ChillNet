<?php

namespace App\Models;

use App\Enums\NiveauAlerte;
use App\Enums\StatutAlerte;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Alerte météo / vigilance canicule (modèle Eloquent, table `alertes`).
 *
 * Jointure : une alerte couvre UN OU PLUSIEURS quartiers
 *   Alerte N --- N Quartier   (table pivot `alerte_quartier`)
 *
 * Le niveau de vigilance (jaune/orange/rouge) et le message sont produits
 * par le module IA (règles de seuils + génération LLM), mais restent
 * modifiables par le gestionnaire.
 */
class Alerte extends Model
{
    use HasFactory;

    protected $table = 'alertes';

    /**
     * Rayon par défaut (mètres) du cercle de ciblage géographique.
     */
    public const RAYON_DEFAUT_M = 1000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'titre',
        'niveau',
        'seuil_temperature',
        'temperature_actuelle',
        'temperature_ressentie',
        'humidite',
        'source_meteo',
        'debut',
        'fin',
        'message',
        'latitude',
        'longitude',
        'rayon_metres',
        'validee',
        'validee_le',
        'validee_par',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'niveau' => NiveauAlerte::class,
            'seuil_temperature' => 'float',
            'temperature_actuelle' => 'float',
            'temperature_ressentie' => 'float',
            'humidite' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'rayon_metres' => 'integer',
            'debut' => 'datetime',
            'fin' => 'datetime',
            'validee' => 'boolean',
            'validee_le' => 'datetime',
        ];
    }

    /**
     * Quartiers couverts par l'alerte (relation N---N).
     *
     * @return BelongsToMany<Quartier, $this>
     */
    public function quartiers(): BelongsToMany
    {
        return $this->belongsToMany(Quartier::class, 'alerte_quartier', 'alerte_id', 'quartier_id');
    }

    /**
     * Premier quartier couvert — raccourci d'affichage (badges, marqueurs).
     */
    protected function quartier(): Attribute
    {
        return Attribute::get(fn (): ?Quartier => $this->quartiers->first());
    }

    /**
     * Agent (habitant/gestionnaire/admin) qui a créé l'alerte.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Manager qui a validé l'alerte (gate d'affichage front).
     *
     * @return BelongsTo<User, $this>
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    /**
     * Statut dérivé du créneau [debut, fin] — jamais stocké en base.
     */
    protected function statut(): Attribute
    {
        return Attribute::get(function (): StatutAlerte {
            $now = Carbon::now();

            if ($this->debut && $this->debut->greaterThan($now)) {
                return StatutAlerte::Programmee;
            }

            if ($this->fin && $this->fin->lessThan($now)) {
                return StatutAlerte::Terminee;
            }

            return StatutAlerte::Active;
        });
    }

    public function estActive(): bool
    {
        return $this->statut === StatutAlerte::Active;
    }

    public function estProgrammee(): bool
    {
        return $this->statut === StatutAlerte::Programmee;
    }

    public function estTerminee(): bool
    {
        return $this->statut === StatutAlerte::Terminee;
    }

    /**
     * L'alerte couvre-t-elle ce quartier ?
     */
    public function couvreQuartier(int $quartierId): bool
    {
        return $this->relationLoaded('quartiers')
            ? $this->quartiers->contains('id', $quartierId)
            : $this->quartiers()->whereKey($quartierId)->exists();
    }

    /**
     * L'alerte porte-t-elle un centre de ciblage géographique ?
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Rayon du cercle en mètres (défaut 1 km si non renseigné).
     */
    public function rayonMetres(): int
    {
        return $this->rayon_metres ? (int) $this->rayon_metres : self::RAYON_DEFAUT_M;
    }

    /**
     * Le point (lat, lng) tombe-t-il dans le cercle de l'alerte ?
     */
    public function couvrePoint(float $latitude, float $longitude): bool
    {
        if (! $this->hasCoordinates()) {
            return false;
        }

        $distanceM = Geo::distanceKm((float) $this->latitude, (float) $this->longitude, $latitude, $longitude) * 1000;

        return $distanceM <= $this->rayonMetres();
    }

    /**
     * Alertes visibles pour un lieu : quartier couvert via le pivot (alertes
     * héritées) OU point dans le cercle (ciblage géographique). Filtrage en
     * PHP : pas de fonction SQL de distance (portabilité MySQL / SQLite).
     *
     * @param  iterable<Alerte>  $alertes
     * @return Collection<int, Alerte>
     */
    public static function filtrerPourLieu(iterable $alertes, ?int $quartierId, ?float $latitude, ?float $longitude): Collection
    {
        return collect($alertes)->filter(function (Alerte $alerte) use ($quartierId, $latitude, $longitude): bool {
            if ($quartierId !== null && $alerte->couvreQuartier($quartierId)) {
                return true;
            }

            return $latitude !== null && $longitude !== null && $alerte->couvrePoint($latitude, $longitude);
        })->values();
    }

    /**
     * Anti-doublon géographique : existe-t-il déjà une alerte de même niveau,
     * NON terminée, temps qui se chevauche, et dont le cercle recoupe celui-ci
     * (distance des centres <= somme des rayons) ?
     */
    public static function chevaucheGeo(float $latitude, float $longitude, int $rayon, string $debut, string $fin, ?int $ignoreId = null, ?NiveauAlerte $niveau = null): bool
    {
        $debutCarbon = Carbon::parse($debut);
        $finCarbon = Carbon::parse($fin);

        $candidats = self::query()
            ->where('fin', '>=', Carbon::now())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($niveau, fn ($q) => $q->where('niveau', $niveau->value))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('debut', '<', $finCarbon)
            ->where('fin', '>', $debutCarbon)
            ->get();

        foreach ($candidats as $candidat) {
            $distanceM = Geo::distanceKm((float) $candidat->latitude, (float) $candidat->longitude, $latitude, $longitude) * 1000;

            if ($distanceM <= $rayon + $candidat->rayonMetres()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alertes validées (visibles côté habitant).
     */
    public function scopeValidees($query)
    {
        return $query->where('validee', true);
    }

    /**
     * Alertes validées en cours (debut <= maintenant <= fin).
     */
    public function scopeActives($query)
    {
        $now = Carbon::now();

        return $query->where('validee', true)
            ->where('debut', '<=', $now)
            ->where('fin', '>=', $now);
    }

    /**
     * Alertes validées à venir.
     */
    public function scopeProgrammees($query)
    {
        return $query->where('validee', true)->where('debut', '>', Carbon::now());
    }

    /**
     * Alertes terminées (fin dépassée).
     */
    public function scopeTerminees($query)
    {
        return $query->where('fin', '<', Carbon::now());
    }

    /**
     * Filtre par quartier(s) — analogue N---N de Coupure::scopeParZone().
     *
     * @param  int|array<int>  $quartierIds
     */
    public function scopeParQuartier($query, int|array $quartierIds)
    {
        return $query->whereHas('quartiers', fn ($q) => $q->whereKey($quartierIds));
    }

    /**
     * Filtre par ville(s).
     *
     * @param  string|array<string>  $villes
     */
    public function scopeByZone($query, string|array $villes)
    {
        return $query->whereHas('quartiers', fn ($q) => $q->whereIn('ville', (array) $villes));
    }

    public function scopeNiveau($query, NiveauAlerte $niveau)
    {
        return $query->where('niveau', $niveau->value);
    }

    public function scopeRecentes($query)
    {
        return $query->orderByDesc('debut');
    }

    /**
     * Y a-t-il déjà une alerte de même niveau, NON terminée, qui chevauche
     * [debut, fin] sur au moins un des quartiers visés ?
     * Sert à la validation anti-doublon (voir StoreAlerteRequest).
     *
     * Comparaison en PHP/Carbon (portable MySQL / SQLite : pas de fonction
     * SQL de date, même approche que Coupure::chevauche()).
     *
     * @param  array<int>  $quartierIds
     */
    public static function chevauche(array $quartierIds, string $debut, string $fin, ?int $ignoreId = null, ?NiveauAlerte $niveau = null): bool
    {
        if (empty($quartierIds)) {
            return false;
        }

        $debutCarbon = Carbon::parse($debut);
        $finCarbon = Carbon::parse($fin);

        return self::query()
            // L'historique terminé ne bloque pas une nouvelle alerte.
            ->where('fin', '>=', Carbon::now())
            ->when($niveau, fn ($q) => $q->where('niveau', $niveau->value))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('debut', '<', $finCarbon)
            ->where('fin', '>', $debutCarbon)
            ->whereHas('quartiers', fn ($q) => $q->whereKey($quartierIds))
            ->exists();
    }
}
