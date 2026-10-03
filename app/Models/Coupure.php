<?php

namespace App\Models;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coupure de courant rattachée à un quartier (modèle Eloquent, table `coupures`).
 *
 * Jointure : une coupure touche UNE zone précise = un quartier
 * (on réutilise la table `quartiers` comme zone, pas de table `zones` en double).
 *   Coupure N --- 1 Quartier
 *   Quartier 1 --- N Coupure
 */
class Coupure extends Model
{
    use HasFactory;

    protected $table = 'coupures';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quartier_id',
        'latitude',
        'longitude',
        'lieu',
        'type',
        'statut',
        'debut',
        'fin',
        'description',
        'confirmations',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TypeCoupure::class,
            'statut' => StatutCoupure::class,
            'confirmations' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'debut' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    /**
     * Le point posé sur la carte (ciblage libre, indépendant du quartier).
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Zone touchée par la coupure.
     *
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'quartier_id');
    }

    /**
     * Habitant / gestionnaire qui a signalé ou publié la coupure.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Coupures actives = en cours (pour la carte front).
     */
    public function scopeActives($query)
    {
        return $query->where('statut', StatutCoupure::EnCours);
    }

    /**
     * Coupures prévues (maintenance / délestage programmé).
     */
    public function scopePrevues($query)
    {
        return $query->where('statut', StatutCoupure::Prevue);
    }

    /**
     * Filtre par zone (quartier).
     */
    public function scopeParZone($query, int $quartierId)
    {
        return $query->where('quartier_id', $quartierId);
    }

    /**
     * Y a-t-il déjà une coupure NON résolue qui chevauche
     * [debut, fin] au MÊME endroit (même quartier + même rue) ?
     * Sert à la validation anti-chevauchement (voir StoreCoupureRequest).
     *
     * Un grand quartier peut avoir 2 coupures simultanées dans 2 rues
     * différentes : on ne bloque que si les rues correspondent (ou si
     * l'une d'elles est inconnue — on ne peut pas prouver que c'est
     * différent, on reste prudent). Comparaison insensible à la casse.
     *
     * Le test de fin se fait en PHP (pas de fonction SQL) pour rester
     * portable MySQL / SQLite : `datetime(...)` ne marche que sur SQLite.
     */
    public static function chevauche(int $quartierId, string $debut, ?string $fin, ?int $ignoreId = null, ?string $lieu = null): bool
    {
        // Une coupure sans fin = ponctuelle : on lui donne +2h pour le test.
        $finEffective = $fin ?: date('Y-m-d H:i:s', strtotime($debut.' +2 hours'));
        $lieuNormalise = $lieu ? mb_strtolower(trim($lieu)) : null;

        // Candidats : même zone, non résolues, qui commencent avant notre fin.
        $candidats = self::where('quartier_id', $quartierId)
            ->where('statut', '!=', StatutCoupure::Resolue->value)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('debut', '<', $finEffective)
            ->get(['debut', 'fin', 'lieu']);

        foreach ($candidats as $coupure) {
            // Rues différentes renseignées des deux côtés = incidents distincts.
            $lieuExistant = $coupure->lieu ? mb_strtolower(trim($coupure->lieu)) : null;
            if ($lieuNormalise && $lieuExistant && $lieuNormalise !== $lieuExistant) {
                continue;
            }

            $finExistante = $coupure->fin?->format('Y-m-d H:i:s')
                ?? date('Y-m-d H:i:s', strtotime($coupure->debut->format('Y-m-d H:i:s').' +2 hours'));

            if ($finExistante > $debut) {
                return true;
            }
        }

        return false;
    }
}
