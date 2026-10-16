<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantané de risque IA d'une zone (voir migration coupure_risk_snapshots).
 *
 * Sert de boucle de retour d'expérience du module 2 : à la capture, on fige le
 * contexte et la prédiction ; une fois la fenêtre d'observation écoulée,
 * `ai:label-snapshots` renseigne le résultat réel (`outcome_24h` / `outcome_48h`).
 * `ai:export-coupures` réinjecte ensuite ces exemples dans l'entraînement.
 */
class CoupureRiskSnapshot extends Model
{
    protected $table = 'coupure_risk_snapshots';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quartier_id',
        'captured_at',
        'features',
        'p24',
        'p48',
        'score',
        'model_version',
        'outcome_24h',
        'outcome_48h',
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
            'p24' => 'float',
            'p48' => 'float',
            'score' => 'integer',
            'outcome_24h' => 'boolean',
            'outcome_48h' => 'boolean',
            'labeled_at' => 'datetime',
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
     * Snapshots dont un outcome reste à déterminer.
     *
     * @param  Builder<CoupureRiskSnapshot>  $query
     * @return Builder<CoupureRiskSnapshot>
     */
    public function scopeNonLabelises(Builder $query): Builder
    {
        return $query->whereNull('labeled_at');
    }

    /**
     * Snapshots entièrement labellisés (exploitables pour l'entraînement).
     *
     * @param  Builder<CoupureRiskSnapshot>  $query
     * @return Builder<CoupureRiskSnapshot>
     */
    public function scopeLabelises(Builder $query): Builder
    {
        return $query->whereNotNull('labeled_at');
    }
}
