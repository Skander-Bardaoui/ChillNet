<?php

namespace App\Services;

use App\Models\Signalement;
use App\Models\SignalementAnalyse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignalementTriageService
{
    private const VERSION_MODELE = 'regles-v1';

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

        $analyses = [];
        foreach ($signalements as $signalement) {
            $analyse = $this->urgencyEstimator->evaluer($signalement->description);
            $urgenceSuggeree = match (true) {
                $analyse['pourcentage'] >= 80 => 'vitale',
                $analyse['pourcentage'] >= 50 => 'prioritaire',
                default => 'normale',
            };

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
                    ];
                }
            }

            $signalementsLies = array_values(array_unique(array_column($lies, 'id')));
            sort($signalementsLies);
            $analyses[$signalement->id] = [
                'score_urgence' => $analyse['pourcentage'],
                'urgence_suggeree' => $urgenceSuggeree,
                'indicateurs_urgence' => $analyse['indicateurs'],
                'termes_urgents' => $this->urgencyDetector->detecter($signalement->description),
                'signalements_lies' => $signalementsLies,
                'empreinte_description' => hash('sha256', $signalement->description),
                'version_modele' => self::VERSION_MODELE,
            ];
        }

        DB::transaction(function () use ($signalements, $analyses): void {
            foreach ($signalements as $signalement) {
                $analyse = SignalementAnalyse::firstOrNew(['signalement_id' => $signalement->id]);
                $analyse->fill($analyses[$signalement->id]);

                if ($analyse->isDirty()) {
                    $analyse->analysee_le = now();
                    $analyse->save();
                }
            }
        });

        foreach ($signalements as $signalement) {
            $analyse = SignalementAnalyse::where('signalement_id', $signalement->id)->firstOrFail();
            $signalement->setRelation('analyseIa', $analyse);
            $scoreEnregistre = self::URGENCES[$signalement->urgence] ?? 0;
            $signalement->setAttribute('urgence_suggeree', $analyse->urgence_suggeree);
            $signalement->setAttribute('score_urgence_texte', $analyse->score_urgence);
            $signalement->setAttribute('indicateurs_urgence_texte', $analyse->indicateurs_urgence ?? []);
            $signalement->setAttribute('score_priorite_tri', max($scoreEnregistre, $analyse->score_urgence));
            $signalement->setAttribute('termes_urgents_detectes', $analyse->termes_urgents ?? []);
            $signalement->setAttribute('signalements_lies', array_map(
                fn (int $id): array => ['id' => $id],
                $analyse->signalements_lies ?? [],
            ));
        }

        return $signalements
            ->sortByDesc(fn (Signalement $signalement): int => $signalement->score_priorite_tri)
            ->values();
    }

    /**
     * Recomputes and stores the analysis for every report.
     *
     * @return Collection<int, Signalement>
     */
    public function actualiser(): Collection
    {
        return $this->trier(Signalement::with('habitant')->get());
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
