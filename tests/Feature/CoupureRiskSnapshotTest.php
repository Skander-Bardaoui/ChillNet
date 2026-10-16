<?php

namespace Tests\Feature;

use App\Models\Coupure;
use App\Models\CoupureRiskSnapshot;
use App\Models\Quartier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Boucle de retour d'expérience du module 2 (coupures) : capture, labellisation
 * a posteriori (fenêtre demi-ouverte) et export CSV des exemples réels.
 */
class CoupureRiskSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(string $nom = 'Zone'): Quartier
    {
        return Quartier::create([
            'nom' => $nom,
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => 36.8,
            'longitude' => 10.18,
        ]);
    }

    public function test_la_capture_cree_un_snapshot_par_zone_geolocalisee(): void
    {
        $this->quartier('Centre');
        $this->quartier('Nord');

        $this->artisan('ai:snapshot-risk')->assertSuccessful();

        $this->assertDatabaseCount('coupure_risk_snapshots', 2);
        $this->assertNotNull(CoupureRiskSnapshot::first()->features);
    }

    public function test_le_labeling_passe_a_vrai_quand_une_coupure_demarre_dans_la_fenetre(): void
    {
        $quartier = $this->quartier();

        $capture = now()->subHours(25);

        CoupureRiskSnapshot::create([
            'quartier_id' => $quartier->id,
            'captured_at' => $capture,
            'features' => ['frequence' => 0],
            'p24' => 0.1,
            'p48' => 0.2,
            'score' => 10,
        ]);

        // Coupure démarrée 2 h après la capture → dans (captured_at, +24 h].
        Coupure::factory()->create([
            'quartier_id' => $quartier->id,
            'debut' => $capture->copy()->addHours(2),
            'statut' => 'en_cours',
        ]);

        $this->artisan('ai:label-snapshots')->assertSuccessful();

        $snapshot = CoupureRiskSnapshot::first();
        $this->assertTrue($snapshot->outcome_24h);
        $this->assertNull($snapshot->outcome_48h);
        $this->assertNull($snapshot->labeled_at);
    }

    public function test_le_labeling_est_faux_sans_coupure_dans_la_fenetre(): void
    {
        $quartier = $this->quartier();

        CoupureRiskSnapshot::create([
            'quartier_id' => $quartier->id,
            'captured_at' => now()->subHours(25),
            'features' => [],
            'score' => 0,
        ]);

        $this->artisan('ai:label-snapshots')->assertSuccessful();

        $this->assertFalse(CoupureRiskSnapshot::first()->outcome_24h);
    }

    public function test_une_coupure_au_timestamp_exact_de_la_capture_ne_compte_pas(): void
    {
        $quartier = $this->quartier();

        $capture = now()->subHours(25);

        CoupureRiskSnapshot::create([
            'quartier_id' => $quartier->id,
            'captured_at' => $capture,
            'features' => [],
            'score' => 0,
        ]);

        // Borne demi-ouverte à GAUCHE : une coupure démarrée EXACTEMENT au
        // timestamp de la capture ne compte pas.
        Coupure::factory()->create([
            'quartier_id' => $quartier->id,
            'debut' => $capture,
            'statut' => 'en_cours',
        ]);

        $this->artisan('ai:label-snapshots')->assertSuccessful();

        $this->assertFalse(CoupureRiskSnapshot::first()->outcome_24h);
    }

    public function test_le_snapshot_est_labelise_une_fois_les_deux_horizons_ecoules(): void
    {
        $quartier = $this->quartier();

        CoupureRiskSnapshot::create([
            'quartier_id' => $quartier->id,
            'captured_at' => now()->subHours(50),
            'features' => [],
            'score' => 0,
        ]);

        $this->artisan('ai:label-snapshots')->assertSuccessful();

        $snapshot = CoupureRiskSnapshot::first();
        $this->assertNotNull($snapshot->outcome_24h);
        $this->assertNotNull($snapshot->outcome_48h);
        $this->assertNotNull($snapshot->labeled_at);
    }

    public function test_l_export_ecrit_les_exemples_labellises(): void
    {
        $quartier = $this->quartier();

        CoupureRiskSnapshot::create([
            'quartier_id' => $quartier->id,
            'captured_at' => now()->subHours(50),
            'features' => [
                'coupures_7j' => 2,
                'temperature' => 39.5,
                'canicule_niveau' => 2,
            ],
            'p24' => 0.3,
            'p48' => 0.4,
            'score' => 40,
            'outcome_24h' => true,
            'outcome_48h' => false,
            'labeled_at' => now(),
        ]);

        $dossier = storage_path('app/test_export_'.uniqid());
        File::ensureDirectoryExists($dossier);

        try {
            $this->artisan('ai:export-coupures', ['--dir' => $dossier])->assertSuccessful();

            $chemin = $dossier.'/coupures_real.csv';
            $this->assertFileExists($chemin);

            $lignes = array_map('str_getcsv', file($chemin));
            $this->assertSame('zone_id', $lignes[0][0]);
            $this->assertCount(2, $lignes); // en-tête + 1 exemple
            $entete = $lignes[0];
            $exemple = $lignes[1];

            $index = array_flip($entete);
            $this->assertSame((string) $quartier->id, $exemple[$index['zone_id']]);
            $this->assertSame('1', $exemple[$index['label_24h']]);
            $this->assertSame('0', $exemple[$index['label_48h']]);
            $this->assertSame('reel', $exemple[$index['source']]);
            $this->assertEqualsWithDelta(39.5, (float) $exemple[$index['temp']], 0.001);
        } finally {
            File::deleteDirectory($dossier);
        }
    }
}
