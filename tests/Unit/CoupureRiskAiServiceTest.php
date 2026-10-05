<?php

namespace Tests\Unit;

use App\Enums\NiveauRisque;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Services\CoupureRiskAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
