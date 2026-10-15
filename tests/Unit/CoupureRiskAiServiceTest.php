<?php

namespace Tests\Unit;

use App\Enums\NiveauRisque;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Services\CoupureRiskAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Brique IA du module 2 : score de risque par zone (historique + canicule)
 * et détection d'anomalies (afflux de signalements).
 */
class CoupureRiskAiServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CoupureRiskAiService
    {
        return new CoupureRiskAiService;
    }

    private function quartier(string $nom): Quartier
    {
        return Quartier::create([
            'nom' => $nom,
            'ville' => 'Tunis',
            'code_postal' => '1000',
        ]);
    }

    public function test_a_zone_without_history_or_heat_has_no_risk(): void
    {
        $resultat = $this->service()->score($this->quartier('Calme'));

        $this->assertSame(0, $resultat['score']);
        $this->assertSame(NiveauRisque::Faible, $resultat['niveau']);
    }

    public function test_history_raises_the_score(): void
    {
        $quartier = $this->quartier('Chargé');

        Coupure::factory()->count(4)->create([
            'quartier_id' => $quartier->id,
            'type' => 'panne',
            'statut' => 'en_cours',
            'debut' => now()->subDays(2),
        ]);

        $resultat = $this->service()->score($quartier);

        $this->assertSame(4, $resultat['frequence']);
        $this->assertGreaterThan(0, $resultat['score']);
    }

    public function test_a_heat_alert_raises_the_score(): void
    {
        $quartier = $this->quartier('Surchauffé');

        Coupure::factory()->count(2)->create([
            'quartier_id' => $quartier->id,
            'type' => 'surcharge',
            'statut' => 'en_cours',
            'debut' => now()->subDay(),
        ]);

        $sansAlerte = $this->service()->score($quartier)['score'];

        Alerte::factory()->validee()->active()->rouge()->hasAttached($quartier)->create();

        $avecAlerte = $this->service()->score($quartier->fresh())['score'];

        $this->assertGreaterThan($sansAlerte, $avecAlerte);
    }

    public function test_scores_are_sorted_from_the_riskiest_zone(): void
    {
        $calme = $this->quartier('Calme');
        $charge = $this->quartier('Chargé');

        Coupure::factory()->count(5)->create([
            'quartier_id' => $charge->id,
            'type' => 'delestage',
            'statut' => 'en_cours',
            'debut' => now()->subDay(),
        ]);

        $scores = $this->service()->scoresPourQuartiers([$calme, $charge]);

        $this->assertSame($charge->id, $scores->first()['quartier']->id);
        $this->assertGreaterThan($scores->last()['score'], $scores->first()['score']);
    }

    public function test_a_burst_of_signalements_is_flagged_as_an_anomaly(): void
    {
        $quartier = $this->quartier('Incident');

        Coupure::factory()->count(3)->create([
            'quartier_id' => $quartier->id,
            'statut' => 'en_cours',
            'debut' => now()->subMinutes(10),
        ]);

        $anomalies = $this->service()->detecterAnomalies();

        $this->assertCount(1, $anomalies);
        $this->assertSame($quartier->id, $anomalies->first()['quartier']->id);
        $this->assertSame(3, $anomalies->first()['nombre']);
    }

    public function test_no_anomaly_below_the_threshold(): void
    {
        $quartier = $this->quartier('Normal');

        Coupure::factory()->count(2)->create([
            'quartier_id' => $quartier->id,
            'statut' => 'en_cours',
            'debut' => now()->subMinutes(10),
        ]);

        $this->assertCount(0, $this->service()->detecterAnomalies());
    }

    public function test_resolved_coupures_do_not_trigger_an_anomaly(): void
    {
        $quartier = $this->quartier('Historique');

        Coupure::factory()->count(4)->create([
            'quartier_id' => $quartier->id,
            'statut' => 'resolue',
            'debut' => now()->subHour(),
        ]);

        $this->assertCount(0, $this->service()->detecterAnomalies());
    }

    public function test_niveau_risque_paliers(): void
    {
        $this->assertSame(NiveauRisque::Faible, NiveauRisque::fromScore(0));
        $this->assertSame(NiveauRisque::Faible, NiveauRisque::fromScore(24));
        $this->assertSame(NiveauRisque::Modere, NiveauRisque::fromScore(25));
        $this->assertSame(NiveauRisque::Eleve, NiveauRisque::fromScore(50));
        $this->assertSame(NiveauRisque::Critique, NiveauRisque::fromScore(75));
        $this->assertSame(NiveauRisque::Critique, NiveauRisque::fromScore(100));
    }

    /**
     * Le service IA est appelé quand il est activé : le résultat est enrichi
     * (probabilités, niveau modèle, version) et le plancher est conservé.
     */
    public function test_le_modele_est_appele_et_le_plancher_respecte(): void
    {
        $this->serviceIaActive();

        Http::fake([
            'ai.test/coupures/risk' => Http::response([
                'results' => [[
                    'quartier_id' => 1,
                    'p24' => 0.42,
                    'p48' => 0.55,
                    'score_modele' => 42,
                    'score_regles' => 0,
                    'score_final' => 42,
                    'niveau_modele' => 'modere',
                    'niveau_regles' => 'faible',
                    'niveau_final' => 'modere',
                    'desaccord' => true,
                    'facteurs' => ['Canicule rouge en cours'],
                    'raison' => 'Règles : faible — Modèle : modere',
                    'source' => 'modele',
                    'model_version' => '1.0.5',
                ]],
            ]),
        ]);

        $resultat = $this->service()->score($this->quartier('Piloté par le modèle'));

        $this->assertSame('modele', $resultat['source']);
        $this->assertSame(42, $resultat['score']);
        $this->assertSame(NiveauRisque::Modere, $resultat['niveau']);
        $this->assertSame(0.42, $resultat['p24']);
        $this->assertSame(0.55, $resultat['p48']);
        $this->assertTrue($resultat['desaccord']);
        $this->assertSame('1.0.5', $resultat['model_version']);
        $this->assertSame(['Canicule rouge en cours'], $resultat['facteurs']);
    }

    /**
     * PLANCHER DE SÉCURITÉ : un modèle qui propose MOINS que les règles ne peut
     * jamais faire baisser le score final (``max(modele, regles)``).
     */
    public function test_le_modele_ne_peut_pas_faire_baisser_le_score(): void
    {
        $this->serviceIaActive();

        $quartier = $this->quartier('Chargé');

        Coupure::factory()->count(5)->create([
            'quartier_id' => $quartier->id,
            'type' => 'delestage',
            'statut' => 'en_cours',
            'debut' => now()->subDay(),
        ]);

        Http::fake([
            'ai.test/coupures/risk' => Http::response([
                'results' => [[
                    'quartier_id' => $quartier->id,
                    'p24' => 0.02,
                    'p48' => 0.03,
                    'score_modele' => 5,
                    'score_regles' => 90,
                    'score_final' => 90,
                    'niveau_modele' => 'faible',
                    'niveau_regles' => 'critique',
                    'niveau_final' => 'critique',
                    'desaccord' => true,
                    'facteurs' => [],
                    'raison' => 'Le modèle suggère un niveau moindre ; le plancher des règles est conservé.',
                    'source' => 'modele',
                    'model_version' => '1.0.5',
                ]],
            ]),
        ]);

        $resultat = $this->service()->score($quartier);

        $this->assertSame($resultat['score_regles'], $resultat['score']);
        $this->assertGreaterThan(5, $resultat['score']);
        $this->assertSame('modele', $resultat['source']);
    }

    /**
     * Service indisponible (connexion refusée) → repli intégral sur les règles
     * PHP, sans probabilité ni version modèle.
     */
    public function test_repli_sur_les_regles_quand_le_service_est_indisponible(): void
    {
        $this->serviceIaActive();

        Http::fake(function (): void {
            throw new ConnectionException('Connection refused');
        });

        $quartier = $this->quartier('Repli');

        Coupure::factory()->count(3)->create([
            'quartier_id' => $quartier->id,
            'type' => 'surcharge',
            'statut' => 'en_cours',
            'debut' => now()->subDay(),
        ]);

        $resultat = $this->service()->score($quartier);
        $attendu = $this->service()->scoreDepuisContexte([
            'frequence' => 3,
            'en_cours' => 3,
            'chaudes' => 3,
        ]);

        $this->assertSame('regles', $resultat['source']);
        $this->assertNull($resultat['p24']);
        $this->assertNull($resultat['score_modele']);
        $this->assertNull($resultat['model_version']);
        $this->assertSame($attendu['score'], $resultat['score']);
    }

    /**
     * Incidents enrichis par le service IA : centroïde, rayon, logements estimés,
     * confiance et sévérité remontés tels quels.
     */
    public function test_anomalies_enrichies_par_le_service_ia(): void
    {
        $this->serviceIaActive();

        $quartier = $this->quartier('Incident IA');

        Http::fake([
            'ai.test/coupures/anomalies' => Http::response([
                'incidents' => [[
                    'id' => 'inc-1',
                    'centroid' => [36.8, 10.18],
                    'radius_m' => 620.0,
                    'n_reports' => 3,
                    'n_confirmations' => 2,
                    'quartier_ids' => [$quartier->id],
                    'logements_estimes' => 480,
                    'confidence' => 0.77,
                    'severity' => 'eleve',
                    'message' => '3 signalements en 120 min près de Incident IA : possible incident majeur non déclaré (~480 logements concernés)',
                    'source' => 'modele',
                ]],
            ]),
        ]);

        $anomalies = $this->service()->detecterAnomalies();

        $this->assertCount(1, $anomalies);
        $incident = $anomalies->first();
        $this->assertSame($quartier->id, $incident['quartier']->id);
        $this->assertSame(3, $incident['nombre']);
        $this->assertSame(480, $incident['logements_estimes']);
        $this->assertEqualsWithDelta(620.0, $incident['radius_m'], 0.001);
        $this->assertEqualsWithDelta(0.77, $incident['confidence'], 0.0001);
        $this->assertSame('eleve', $incident['severity']);
        $this->assertSame('modele', $incident['source']);
        $this->assertStringContainsString('possible incident majeur non déclaré', $incident['message']);
    }

    /**
     * Active le service IA vers un hôte faussé par ``Http::fake``.
     */
    private function serviceIaActive(): void
    {
        config([
            'ai.enabled' => true,
            'ai.service_url' => 'http://ai.test',
            'ai.token' => 'test-token',
            'ai.timeout' => 2.0,
        ]);
    }
}
