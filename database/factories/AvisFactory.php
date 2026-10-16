<?php

namespace Database\Factories;

use App\Enums\Affluence;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Models\User;
use App\Services\SentimentAvisService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Avis d'un habitant. Le sentiment est calculé par le modèle (analyse
 * lexicale locale) à l'enregistrement : aucun appel Groq pendant le seed.
 *
 * @extends Factory<Avis>
 */
class AvisFactory extends Factory
{
    protected $model = Avis::class;

    private const POSITIFS = [
        'Très agréable et bien frais, parfait pour les après-midi de canicule.',
        'Endroit propre, calme et accueillant. Je recommande avec les enfants.',
        'Super ombragé, des bancs partout et une fontaine d\'eau potable.',
        'Personnel gentil, salle climatisée vraiment confortable.',
        'Idéal pour se reposer à l\'abri du soleil, merci pour ce lieu !',
    ];

    private const NEUTRES = [
        'Correct pour une pause, sans plus.',
        'Lieu pratique mais un peu d\'attente en fin de journée.',
        'Bien situé, mais l\'ombre est limitée vers midi.',
    ];

    private const NEGATIFS = [
        'Très sale, des déchets partout et une mauvaise odeur. Décevant.',
        'La climatisation était en panne, il faisait trop chaud à l\'intérieur.',
        'Bondé, impossible de trouver une place, attente interminable.',
        'Fontaine cassée, pas d\'eau potable depuis des jours. Inacceptable.',
        'Fermé alors que les horaires indiquaient ouvert, vraiment décevant.',
    ];

    /**
     * Sentiment calculé dès la fabrication : sous WithoutModelEvents (appel
     * depuis DatabaseSeeder), le hook `saving` du modèle ne se déclenche pas.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Avis $avis): void {
            if ($avis->sentiment === null) {
                $analyse = app(SentimentAvisService::class)->analyser($avis->commentaire, (int) $avis->note);
                $avis->sentiment = $analyse['sentiment'];
                $avis->score_sentiment = $analyse['score'];
            }
        });
    }

    public function definition(): array
    {
        $note = $this->faker->numberBetween(3, 5);

        return [
            'point_fraicheur_id' => PointFraicheur::factory(),
            'user_id' => User::factory(),
            'note' => $note,
            'commentaire' => $this->faker->randomElement($note >= 4 ? self::POSITIFS : self::NEUTRES),
            'affluence' => $this->faker->optional(0.7)->randomElement(Affluence::valeurs()),
        ];
    }

    public function positif(): static
    {
        return $this->state(fn () => [
            'note' => $this->faker->numberBetween(4, 5),
            'commentaire' => $this->faker->randomElement(self::POSITIFS),
        ]);
    }

    public function neutre(): static
    {
        return $this->state(fn () => [
            'note' => 3,
            'commentaire' => $this->faker->randomElement(self::NEUTRES),
        ]);
    }

    /**
     * Note ≤ 2 : commentaire obligatoire (règle métier respectée).
     */
    public function negatif(): static
    {
        return $this->state(fn () => [
            'note' => $this->faker->numberBetween(1, 2),
            'commentaire' => $this->faker->randomElement(self::NEGATIFS),
            'affluence' => $this->faker->randomElement([Affluence::Moyenne->value, Affluence::Forte->value]),
        ]);
    }

    public function par(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
