<?php

namespace App\Services;

use App\Models\Signalement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SignalementTriageService
{
    private const URGENCES = [
        'normale' => 20,
        'prioritaire' => 60,
        'vitale' => 100,
    ];

    private const MOTS_VIDES = [
        'ainsi', 'alors', 'apres', 'avec', 'dans', 'depuis', 'des', 'donc',
        'batiment', 'elle', 'elles', 'encore', 'est', 'et', 'eux', 'habitant',
        'il', 'ils', 'jour', 'la', 'le', 'les', 'leur', 'lui', 'mais',
        'matin', 'meme', 'mon', 'notre', 'nous', 'par', 'pas', 'panne',
        'plus', 'pour', 'probleme', 'quand', 'que', 'qui', 'residence',
        'sans', 'son', 'sont', 'sur', 'une', 'un', 'vos', 'votre',
    ];

    private const EQUIPEMENTS_SPECIFIQUES = [
        'ascenseur', 'chaudiere', 'canalisation', 'chauffage', 'eclairage',
        'electricite', 'escalier', 'fenetre', 'interphone', 'lampe',
        'lumiere', 'portail', 'robinet', 'serrure', 'toiture', 'tuyau',
    ];

    public function __construct(
        private SupportUrgencyEstimator $urgencyEstimator,
        private SignalementUrgencyDetector $urgencyDetector,
    ) {}

    /**
     * Annotates reports with advisory urgency and related active reports.
     *
     * @param  Collection<int, Signalement>  $signalements
     * @return Collection<int, Signalement>
     */
    public function trier(Collection $signalements): Collection
    {
        $descriptions = [];
        foreach ($signalements as $signalement) {
            $descriptions[$signalement->id] = $this->motsCles($signalement->description);
        }

        foreach ($signalements as $signalement) {
            $analyse = $this->urgencyEstimator->evaluer($signalement->description);
            $scoreEnregistre = self::URGENCES[$signalement->urgence] ?? 0;
            $scorePriorite = max($scoreEnregistre, $analyse['pourcentage']);

            $signalement->setAttribute('urgence_suggeree', match (true) {
                $analyse['pourcentage'] >= 80 => 'vitale',
                $analyse['pourcentage'] >= 50 => 'prioritaire',
                default => 'normale',
            });
            $signalement->setAttribute('score_urgence_texte', $analyse['pourcentage']);
            $signalement->setAttribute('indicateurs_urgence_texte', $analyse['indicateurs']);
            $signalement->setAttribute('score_priorite_tri', $scorePriorite);
            $signalement->setAttribute(
                'termes_urgents_detectes',
                $this->urgencyDetector->detecter($signalement->description),
            );

            $lies = [];
            if ($signalement->statut !== 'resolu') {
                foreach ($signalements as $candidat) {
                    if ($candidat->id === $signalement->id
                        || $candidat->user_id === $signalement->user_id
                        || $candidat->residence_id !== $signalement->residence_id
                        || $candidat->categorie !== $signalement->categorie
                        || $candidat->statut === 'resolu'
                        || ! $this->dansLaFenetre($signalement, $candidat)) {
                        continue;
                    }

                    $communs = array_values(array_intersect(
                        $descriptions[$signalement->id],
                        $descriptions[$candidat->id],
                    ));
                    $aUnEquipementCommun = array_intersect($communs, self::EQUIPEMENTS_SPECIFIQUES) !== [];

                    if (count($communs) < 2 && ! $aUnEquipementCommun) {
                        continue;
                    }

                    $lies[] = [
                        'id' => $candidat->id,
                        'habitant' => $candidat->habitant?->name ?? 'Habitant',
                    ];
                }
            }

            $signalement->setAttribute('signalements_lies', $lies);
        }

        return $signalements
            ->sortByDesc(fn (Signalement $signalement): int => $signalement->score_priorite_tri)
            ->values();
    }

    private function dansLaFenetre(Signalement $premier, Signalement $second): bool
    {
        return $premier->date_signalement !== null
            && $second->date_signalement !== null
            && $premier->date_signalement->diffInDays($second->date_signalement) <= 14;
    }

    /**
     * @return list<string>
     */
    private function motsCles(string $description): array
    {
        $texte = Str::ascii(mb_strtolower($description));
        preg_match_all('/[a-z0-9]+/', $texte, $matches);

        return array_values(array_unique(array_map(
            fn (string $mot): string => strlen($mot) > 4 && str_ends_with($mot, 's')
                ? substr($mot, 0, -1)
                : $mot,
            array_filter(
                $matches[0],
                fn (string $mot): bool => strlen($mot) > 2 && ! in_array($mot, self::MOTS_VIDES, true),
            ),
        )));
    }
}
