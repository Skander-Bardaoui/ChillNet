<?php

namespace App\Models;

use App\Enums\SantePoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantané de santé prédite d'un point de fraîcheur (module 3).
 *
 * À la capture, on fige le contexte (features) et la prédiction ; une fois les
 * 14 jours écoulés, `ai:label-fraicheur` renseigne le résultat réel
 * (`outcome_14j`) et `ai:export-fraicheur` réinjecte l'exemple dans
 * l'entraînement du modèle.
 */
class PointSanteSnapshot extends Model
{
    protected $table = 'point_sante_snapshots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'point_fraicheur_id',
        'captured_at',
        'features',
        'p14',
        'score',
        'niveau',
        'source',
        'model_version',
        'outcome_14j',
        'labeled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'features' => 'array',
            'p14' => 'float',
            'score' => 'integer',
            'niveau' => SantePoint::class,
            'outcome_14j' => 'boolean',
            'labeled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PointFraicheur, $this>
     */
    public function pointFraicheur(): BelongsTo
    {
        return $this->belongsTo(PointFraicheur::class, 'point_fraicheur_id');
    }

    /**
     * @param  Builder<PointSanteSnapshot>  $query
     * @return Builder<PointSanteSnapshot>
     */
    public function scopeNonLabelises(Builder $query): Builder
    {
        return $query->whereNull('labeled_at');
    }

    /**
     * @param  Builder<PointSanteSnapshot>  $query
     * @return Builder<PointSanteSnapshot>
     */
    public function scopeLabelises(Builder $query): Builder
    {
        return $query->whereNotNull('labeled_at');
    }
}
