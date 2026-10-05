<?php

namespace App\Services;

use App\Enums\NiveauAlerte;
use App\Enums\NiveauRisque;
use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Quartier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Module IA du module 2 (coupures) — approche déterministe et explicable,
 * dans le même esprit que la vigilance canicule du module 1 :
 *
 *  1. SCORE DE RISQUE par zone (0–100) : corrèle l'historique des coupures
 *     récentes avec la canicule (niveau d'alerte couvrant la zone + pic de
 *     température enregistré). Plus il fait chaud, plus le réseau est en
 *     surcharge → coupures par délestage / surcharge.
 *  2. DÉTECTION D'ANOMALIES : un afflux de signalements sur une même zone en
 *     peu de temps peut trahir un incident majeur resté non déclaré.
 *
 * Aucun appel réseau : tout est calculé depuis la base (portable MySQL/SQLite).
 */
class CoupureRiskAiService
{
    /** Fenêtre d'observation de l'historique des coupures. */
    public const FENETRE_HISTORIQUE_JOURS = 14;

    /** Fenêtre glissante de détection d'un afflux de signalements. */
    public const FENETRE_ANOMALIE_MINUTES = 120;

    /** Nombre de signalements, dans la fenêtre, à partir duquel on alerte. */
    public const SEUIL_ANOMALIE = 3;

    /**
     * Score de risque d'une zone, avec sa justification détaillée.
     *
     * @return array{
     *     quartier: Quartier,
     *     score: int,
     *     niveau: NiveauRisque,
     *     raison: string,
     *     facteurs: list<string>,
     *     frequence: int,
     *     en_cours: int,
     *     temperature: float|null
     * }
     */
    public function score(Quartier $quartier, ?float $temperature = null): array
    {
        $depuis = Carbon::now()->subDays(self::FENETRE_HISTORIQUE_JOURS);

        $coupures = Coupure::query()
            ->where('quartier_id', $quartier->id)
            ->where('debut', '>=', $depuis)
            ->get(['type', 'statut', 'confirmations']);

        $frequence = $coupures->count();
        $enCours = $coupures->where('statut', StatutCoupure::EnCours)->count();
        $chaudes = $coupures->whereIn('type', [TypeCoupure::Surcharge, TypeCoupure::Delestage])->count();
        $confirmations = (int) $coupures->sum('confirmations');

        // 1) Fréquence récente (0–35).
        $scoreFrequence = min(35, $frequence * 7);

        // 2) Part des coupures « liées à la chaleur » : surcharge / délestage (0–20).
        $scoreType = $frequence > 0 ? (int) round(min(20, ($chaudes / $frequence) * 20)) : 0;

        // 3) Canicule : alerte couvrant la zone + pic de température (0–35).
        $alertes = Alerte::query()
            ->validees()
            ->where('fin', '>=', Carbon::now())
            ->parQuartier($quartier->id)
            ->get(['niveau', 'temperature_actuelle']);

        $niveauChaleur = NiveauAlerte::plusGrave($alertes->pluck('niveau')->filter());
        $picTemperature = $alertes->max('temperature_actuelle');
        $picTemperature = $picTemperature !== null ? (float) $picTemperature : $temperature;

        $scoreChaleur = match ($niveauChaleur) {
            NiveauAlerte::Rouge => 35,
            NiveauAlerte::Orange => 22,
            NiveauAlerte::Jaune => 10,
            default => ($picTemperature !== null && $picTemperature >= 35) ? 12 : 0,
        };

        // 4) Activité en cours + confirmations des voisins (0–10).
        $scoreActif = min(10, $enCours * 4 + ($confirmations >= 3 ? 2 : 0));

        $score = max(0, min(100, $scoreFrequence + $scoreType + $scoreChaleur + $scoreActif));

        return [
            'quartier' => $quartier,
            'score' => $score,
            'niveau' => NiveauRisque::fromScore($score),
            'raison' => sprintf(
                '%d coupure(s) en %d j · alerte %s · %d en cours',
                $frequence,
                self::FENETRE_HISTORIQUE_JOURS,
                $niveauChaleur?->label() ?? 'aucune',
                $enCours,
            ),
            'facteurs' => $this->facteurs($frequence, $chaudes, $niveauChaleur, $picTemperature, $enCours),
            'frequence' => $frequence,
            'en_cours' => $enCours,
            'temperature' => $picTemperature,
        ];
    }

    /**
     * Scores de toutes les zones, triés du plus risqué au moins risqué.
     *
     * @param  iterable<Quartier>  $quartiers
     * @return Collection<int, array<string, mixed>>
     */
    public function scoresPourQuartiers(iterable $quartiers): Collection
    {
        return collect($quartiers)
            ->map(fn (Quartier $quartier): array => $this->score($quartier))
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Zones où un afflux de signalements récents suggère un incident majeur
     * non déclaré (seuil atteint dans la fenêtre glissante).
     *
     * @return Collection<int, array{quartier: Quartier, nombre: int, fenetre_minutes: int, message: string}>
     */
    public function detecterAnomalies(?int $fenetreMinutes = null, ?int $seuil = null): Collection
    {
        $fenetre = $fenetreMinutes ?? self::FENETRE_ANOMALIE_MINUTES;
        $seuil = $seuil ?? self::SEUIL_ANOMALIE;
        $depuis = Carbon::now()->subMinutes($fenetre);

        $parZone = Coupure::query()
            ->where('created_at', '>=', $depuis)
            ->where('statut', '!=', StatutCoupure::Resolue->value)
            ->whereNotNull('quartier_id')
            ->pluck('quartier_id')
            ->countBy()
            ->filter(fn (int $nombre): bool => $nombre >= $seuil)
            ->sortDesc();

        if ($parZone->isEmpty()) {
            return collect();
        }

        $quartiers = Quartier::whereKey($parZone->keys())->get()->keyBy('id');

        return $parZone
            ->map(function (int $nombre, $quartierId) use ($quartiers, $fenetre): ?array {
                $quartier = $quartiers->get((int) $quartierId);

                if (! $quartier) {
                    return null;
                }

                return [
                    'quartier' => $quartier,
                    'nombre' => $nombre,
                    'fenetre_minutes' => $fenetre,
                    'message' => sprintf(
                        '%d signalements en %d min sur %s — possible incident majeur non déclaré.',
                        $nombre,
                        $fenetre,
                        $quartier->nom,
                    ),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function facteurs(int $frequence, int $chaudes, ?NiveauAlerte $niveau, ?float $temperature, int $enCours): array
    {
        $facteurs = [];

        if ($frequence > 0) {
            $facteurs[] = sprintf('%d coupure(s) ces %d derniers jours', $frequence, self::FENETRE_HISTORIQUE_JOURS);
        }
        if ($chaudes > 0) {
            $facteurs[] = sprintf('%d coupure(s) de type surcharge/délestage', $chaudes);
        }
        if ($niveau !== null) {
            $facteurs[] = 'Vigilance '.mb_strtolower($niveau->label()).' en cours';
        }
        if ($temperature !== null) {
            $facteurs[] = sprintf('Pic de température %s°C', number_format($temperature, 1, ',', ''));
        }
        if ($enCours > 0) {
            $facteurs[] = sprintf('%d coupure(s) en cours', $enCours);
        }

        return $facteurs;
    }
}
