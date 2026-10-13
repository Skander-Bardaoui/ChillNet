<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'une prédiction IA canicule (voir migration ai_predictions).
 *
 * Sert de boucle de retour d'expérience : « le gestionnaire a-t-il suivi le
 * moteur ? » et alimente l'entraînement suivant (corrections pondérées).
 *
 * Les niveaux sont stockés en clair (chaîne) : le moteur Python distingue
 * quatre classes (normal, jaune, orange, rouge), alors que l'enum NiveauAlerte
 * de l'application n'en expose que trois (jaune/orange/rouge).
 */
class AiPrediction extends Model
{
    protected $table = 'ai_predictions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alerte_id',
        'quartier_ids',
        'context',
        'niveau_modele',
        'probabilites',
        'niveau_regles',
        'niveau_final',
        'model_version',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quartier_ids' => 'array',
            'context' => 'array',
            'probabilites' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Alerte, $this>
     */
    public function alerte(): BelongsTo
    {
        return $this->belongsTo(Alerte::class, 'alerte_id');
    }

    /**
     * Prédiction non encore reliée à une alerte (candidat pour un rattachement).
     */
    public function scopeLibres($query)
    {
        return $query->whereNull('alerte_id');
    }

    /**
     * Prédictions corrigées par un gestionnaire (niveau final renseigné).
     */
    public function scopeCorrigees($query)
    {
        return $query->whereNotNull('niveau_final');
    }
}
