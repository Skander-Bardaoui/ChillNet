<?php

namespace App\Models;

use App\Enums\Affluence;
use App\Enums\Sentiment;
use App\Services\SentimentAvisService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis d'un habitant sur un point de fraîcheur (table `avis`).
 *
 *   Avis N --- 1 PointFraicheur
 *   Avis N --- 1 User (auteur)
 *
 * Le sentiment est recalculé automatiquement (IA) à chaque enregistrement
 * dont la note ou le commentaire change.
 */
class Avis extends Model
{
    use HasFactory;

    protected $table = 'avis';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'point_fraicheur_id',
        'user_id',
        'note',
        'commentaire',
        'affluence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note' => 'integer',
            'affluence' => Affluence::class,
            'sentiment' => Sentiment::class,
            'score_sentiment' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Filet de sécurité : si le sentiment n'a pas été fourni par l'IA
        // (contrôleur → Groq), on applique l'analyse lexicale locale.
        static::saving(function (self $avis): void {
            if ($avis->isDirty('sentiment')) {
                return;
            }

            if (! $avis->exists || $avis->isDirty(['note', 'commentaire']) || $avis->sentiment === null) {
                $analyse = app(SentimentAvisService::class)->analyser($avis->commentaire, (int) $avis->note);
                $avis->score_sentiment = $analyse['score'];
                $avis->sentiment = $analyse['sentiment'];
            }
        });
    }

    /**
     * @return BelongsTo<PointFraicheur, $this>
     */
    public function pointFraicheur(): BelongsTo
    {
        return $this->belongsTo(PointFraicheur::class, 'point_fraicheur_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeNegatifs($query)
    {
        return $query->where('sentiment', Sentiment::Negatif->value);
    }

    /**
     * Initiale + nom abrégé de l'auteur (« Salma B. »), pour l'affichage public.
     */
    public function auteurAbrege(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];
        $prenom = $parts[0] ?? 'Habitant';
        $nom = isset($parts[1]) ? ' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '';

        return $prenom.$nom;
    }
}
