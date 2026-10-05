<?php

namespace App\Models;

use App\Enums\StatutPointFraicheur;
use App\Enums\TypePointFraicheur;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Point de fraîcheur (module 3, table `points_fraicheur`) : parc ombragé,
 * salle climatisée ou fontaine accessible aux habitants en cas de canicule.
 *
 * Jointure principale :
 *   PointFraicheur 1 --- N Avis  (notes / commentaires des habitants)
 * Jointures secondaires :
 *   PointFraicheur N --- 1 Quartier (auto-assigné : le plus proche du point)
 *   PointFraicheur N --- 1 User     (habitant qui l'a proposé)
 */
class PointFraicheur extends Model
{
    use HasFactory;

    protected $table = 'points_fraicheur';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'type',
        'description',
        'adresse',
        'latitude',
        'longitude',
        'capacite',
        'ouvert_24h',
        'heure_ouverture',
        'heure_fermeture',
        'photo',
        'accessible_pmr',
        'climatise',
        'ombrage',
        'eau_potable',
        'statut',
        'motif_refus',
        'valide_par',
        'valide_le',
        'quartier_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypePointFraicheur::class,
            'statut' => StatutPointFraicheur::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'capacite' => 'integer',
            'ouvert_24h' => 'boolean',
            'accessible_pmr' => 'boolean',
            'climatise' => 'boolean',
            'ombrage' => 'boolean',
            'eau_potable' => 'boolean',
            'valide_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Rattachement interne automatique au quartier le plus proche, comme
        // pour les lieux habitants : aucune saisie de quartier n'est demandée.
        static::saving(function (self $point): void {
            if (! $point->exists || $point->isDirty(['latitude', 'longitude'])) {
                $point->quartier_id = Quartier::plusProche(
                    (float) $point->latitude,
                    (float) $point->longitude,
                )?->id;
            }
        });
    }

    /**
     * Avis déposés par les habitants.
     *
     * @return HasMany<Avis, $this>
     */
    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class, 'point_fraicheur_id');
    }

    /**
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'quartier_id');
    }

    /**
     * Habitant (ou gestionnaire) à l'origine de la proposition.
     *
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Admin qui a validé / refusé le point.
     *
     * @return BelongsTo<User, $this>
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function scopeValides($query)
    {
        return $query->where('statut', StatutPointFraicheur::Valide->value);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', StatutPointFraicheur::EnAttente->value);
    }

    public function scopeDeType($query, ?string $type)
    {
        return $query->when($type, fn ($q) => $q->where('type', $type));
    }

    /**
     * Ajoute `avis_count` et `avis_avg_note` en une seule requête.
     */
    public function scopeAvecNotes($query)
    {
        return $query->withCount('avis')->withAvg('avis', 'note');
    }

    public function estValide(): bool
    {
        return $this->statut === StatutPointFraicheur::Valide;
    }

    public function estEnAttente(): bool
    {
        return $this->statut === StatutPointFraicheur::EnAttente;
    }

    /**
     * Note moyenne arrondie à 0,1 (null si aucun avis). Utilise l'agrégat
     * `withAvg` s'il est chargé, sinon la relation.
     */
    public function noteMoyenne(): ?float
    {
        $moyenne = $this->avis_avg_note ?? ($this->relationLoaded('avis')
            ? $this->avis->avg('note')
            : $this->avis()->avg('note'));

        return $moyenne !== null ? round((float) $moyenne, 1) : null;
    }

    /**
     * URL publique de la photo (disque `public`), ou null.
     * `asset()` plutôt que `Storage::url()` : suit l'hôte courant (artisan serve).
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo ? asset('storage/'.$this->photo) : null);
    }

    /**
     * Horaires lisibles : « 24h/24 » ou « 09:00 – 22:00 ».
     */
    public function horaires(): string
    {
        if ($this->ouvert_24h) {
            return '24h/24';
        }

        if (! $this->heure_ouverture || ! $this->heure_fermeture) {
            return 'Horaires non renseignés';
        }

        return substr($this->heure_ouverture, 0, 5).' – '.substr($this->heure_fermeture, 0, 5);
    }

    /**
     * Le point est-il ouvert à l'instant donné ? Gère les horaires de nuit
     * (fermeture après minuit, ex. 18:00 – 02:00).
     */
    public function estOuvert(?CarbonInterface $moment = null): bool
    {
        if ($this->ouvert_24h) {
            return true;
        }

        if (! $this->heure_ouverture || ! $this->heure_fermeture) {
            return false;
        }

        $heure = ($moment ?? now())->format('H:i');
        $ouverture = substr($this->heure_ouverture, 0, 5);
        $fermeture = substr($this->heure_fermeture, 0, 5);

        return $ouverture <= $fermeture
            ? $heure >= $ouverture && $heure < $fermeture
            : $heure >= $ouverture || $heure < $fermeture;
    }

    /**
     * Équipements cochés, pour les badges (clé => [libellé, icône]).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function equipements(): array
    {
        return array_filter([
            'accessible_pmr' => $this->accessible_pmr ? ['Accessible PMR', 'accessible'] : null,
            'climatise' => $this->climatise ? ['Climatisé', 'ac_unit'] : null,
            'ombrage' => $this->ombrage ? ['Ombragé', 'park'] : null,
            'eau_potable' => $this->eau_potable ? ['Eau potable', 'water_drop'] : null,
        ]);
    }
}
