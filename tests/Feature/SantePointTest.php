<?php

namespace Tests\Feature;

use App\Console\Commands\AiExportFraicheur;
use App\Enums\Affluence;
use App\Enums\Role;
use App\Enums\SantePoint;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Models\PointSanteSnapshot;
use App\Models\Quartier;
use App\Models\User;
use App\Services\RecommandationFraicheurService;
use App\Services\SantePointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Module 3 : santé PRÉDITE des points de fraîcheur à partir de leurs avis
 * (contexte, repli règles, appel au modèle Python, boucle snapshots → labels →
 * export, affichage back-office). Le service Python est simulé (Http::fake).
 */
class SantePointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.weather.key' => null]);
        Quartier::create(['nom' => 'Centre-Ville', 'ville' => 'Tunis', 'latitude' => 36.8008, 'longitude' => 10.1800]);
        Carbon::setTestNow('2026-07-20 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function avis(PointFraicheur $point, int $note, string $commentaire, int $ilYaJours, ?Affluence $affluence = null): Avis
    {
        return Avis::factory()->create([
            'point_fraicheur_id' => $point->id,
            'note' => $note,
            'commentaire' => $commentaire,
            'affluence' => $affluence?->value,
            'created_at' => now()->subDays($ilYaJours),
        ]);
    }

    /**
     * Salle climatisée qui se dégrade : avis récents négatifs (propreté, clim)
     * après une période bien notée.
     */
    private function pointDegrade(): PointFraicheur
    {
        $point = PointFraicheur::factory()->salleClimatisee()->create([
            'nom' => 'Salle Ibn Khaldoun',
            'capacite' => 40,
            'heure_ouverture' => '09:00',
            'heure_fermeture' => '21:00',
        ]);

        $this->avis($point, 5, 'Excellent accueil, très frais et propre.', 200);
        $this->avis($point, 4, 'Bien climatisé et calme.', 60);
        $this->avis($point, 5, 'Parfait, personnel accueillant.', 45);
        $this->avis($point, 1, 'Toilettes sales et dechets partout.', 10, Affluence::Forte);
        $this->avis($point, 2, 'La clim est en panne, etouffant.', 5, Affluence::Forte);
        $this->avis($point, 1, 'Sale, odeur horrible, a eviter.', 2, Affluence::Moyenne);
        $this->avis($point, 4, 'Correct malgre le monde.', 1);

        return $point;
    }

    public function test_le_contexte_relie_le_point_et_ses_avis(): void
    {
        $point = $this->pointDegrade();

        $contexte = app(SantePointService::class)->contextes(collect([$point->id => $point]))[$point->id];

        // Le point.
        $this->assertSame(1, $contexte['type_code']);
        $this->assertSame(40, $contexte['capacite']);
        $this->assertTrue($contexte['climatise']);
        $this->assertEquals(12.0, $contexte['heures_ouverture_jour']);

        // Ses avis : 4 sur 30 j dont 3 négatifs, après 2 avis à 4,5/5.
        $this->assertSame(4, $contexte['nb_avis_30j']);
        $this->assertEquals(0.75, $contexte['part_negatifs_30j']);
        $this->assertEquals(2.0, $contexte['note_moyenne_30j']);
        $this->assertEquals(-2.5, $contexte['evolution_note_30j']);
        $this->assertSame(2, $contexte['jours_depuis_dernier_negatif']);
        $this->assertEquals(round(2 / 3, 4), $contexte['part_affluence_forte_30j']);
        $this->assertGreaterThanOrEqual(2, $contexte['plaintes_proprete_30j']);
        $this->assertGreaterThanOrEqual(1, $contexte['plaintes_chaleur_30j']);
        // Note bayésienne sur les 7 avis : (22 + 3 × 3,5) / 10.
        $this->assertEquals(3.25, $contexte['note_moyenne']);
        $this->assertSame(7, $contexte['mois']);
    }

    public function test_repli_sur_les_regles_quand_le_service_ia_est_indisponible(): void
    {
        $point = $this->pointDegrade();

        $sante = app(SantePointService::class)->evaluer($point);

        $this->assertSame('regles', $sante['source']);
        $this->assertNull($sante['p14']);
        $this->assertSame(SantePoint::Critique, $sante['niveau']);
        $this->assertContains('Programmer un nettoyage du site', $sante['actions']);
        $this->assertContains('Note moyenne sur 30 j : 2,0/5', $sante['facteurs']);
    }

    public function test_un_point_sans_avis_est_en_bon_etat(): void
    {
        $point = PointFraicheur::factory()->fontaine()->create();

        $sante = app(SantePointService::class)->evaluer($point);

        $this->assertSame(SantePoint::Bon, $sante['niveau']);
        $this->assertSame([], $sante['actions']);
    }

    public function test_le_modele_ia_est_utilise_avec_le_plancher_des_regles(): void
    {
        config(['ai.enabled' => true, 'ai.service_url' => 'http://ai.test']);

        $sain = PointFraicheur::factory()->parc()->create(['nom' => 'Parc sain']);
        $degrade = $this->pointDegrade();

        Http::fake([
            'ai.test/fraicheur/sante' => Http::response(['results' => [
                [
                    'point_id' => $sain->id, 'p14' => 0.62, 'score_modele' => 62, 'score_regles' => 0, 'score_final' => 62,
                    'niveau_regles' => 'bon', 'niveau_final' => 'degrade', 'facteurs' => ['Température : 39,0 °C'],
                    'actions' => [], 'raison' => 'Risque estimé par le modèle.', 'source' => 'modele', 'model_version' => '1.0.0',
                ],
                [
                    // Le modèle sous-estime : le plancher des règles PHP doit l'emporter.
                    'point_id' => $degrade->id, 'p14' => 0.05, 'score_modele' => 5, 'score_regles' => 5, 'score_final' => 5,
                    'niveau_regles' => 'bon', 'niveau_final' => 'bon', 'facteurs' => [], 'actions' => [],
                    'raison' => '', 'source' => 'modele', 'model_version' => '1.0.0',
                ],
            ]]),
        ]);

        $santes = app(SantePointService::class)->evaluerPlusieurs([$sain, $degrade]);

        $this->assertSame('modele', $santes[$sain->id]['source']);
        $this->assertSame(62, $santes[$sain->id]['score']);
        $this->assertSame(SantePoint::Degrade, $santes[$sain->id]['niveau']);
        $this->assertSame(['Température : 39,0 °C'], $santes[$sain->id]['facteurs']);
        $this->assertSame(['Surveiller les prochains avis'], $santes[$sain->id]['actions']);
        $this->assertSame('1.0.0', $santes[$sain->id]['model_version']);

        $this->assertSame($santes[$degrade->id]['score_regles'], $santes[$degrade->id]['score']);
        $this->assertSame(SantePoint::Critique, $santes[$degrade->id]['niveau']);

        // Un seul appel groupé, avec le contexte complet de chaque point.
        Http::assertSentCount(1);
        Http::assertSent(fn ($requete): bool => count($requete['points']) === 2
            && $requete['points'][1]['nb_avis_30j'] === 4
            && $requete->hasHeader('X-AI-TOKEN'));
    }

    public function test_une_erreur_du_service_ia_declenche_le_repli(): void
    {
        config(['ai.enabled' => true, 'ai.service_url' => 'http://ai.test']);
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);

        $sante = app(SantePointService::class)->evaluer($this->pointDegrade());

        $this->assertSame('regles', $sante['source']);
        $this->assertSame(SantePoint::Critique, $sante['niveau']);
    }

    public function test_boucle_snapshot_labellisation_export(): void
    {
        $point = PointFraicheur::factory()->parc()->create();
        PointFraicheur::factory()->enAttente()->create();

        $this->artisan('ai:snapshot-fraicheur')->assertSuccessful();
        $this->artisan('ai:snapshot-fraicheur')->assertSuccessful(); // pas de doublon le même jour

        $this->assertSame(1, PointSanteSnapshot::count());
        $snapshot = PointSanteSnapshot::first();
        $this->assertSame($point->id, $snapshot->point_fraicheur_id);
        $this->assertSame(SantePoint::Bon, $snapshot->niveau);
        $this->assertArrayHasKey('part_negatifs_30j', $snapshot->features);

        // Deux avis négatifs arrivent dans les 14 jours suivant la capture.
        $this->avis($point, 1, 'Sale et abandonne.', -3);
        $this->avis($point, 2, 'Bancs casses, decevant.', -6);

        // Avant l'horizon : rien n'est labellisé.
        $this->artisan('ai:label-fraicheur')->assertSuccessful();
        $this->assertNull($snapshot->fresh()->labeled_at);

        Carbon::setTestNow(now()->addDays(15));
        $this->artisan('ai:label-fraicheur')->assertSuccessful();

        $snapshot->refresh();
        $this->assertTrue($snapshot->outcome_14j);
        $this->assertNotNull($snapshot->labeled_at);

        $dossier = storage_path('framework/testing/export-fraicheur');
        $this->artisan('ai:export-fraicheur', ['--dir' => $dossier])->assertSuccessful();

        $lignes = array_map('str_getcsv', file($dossier.'/fraicheur_real.csv', FILE_IGNORE_NEW_LINES));
        $this->assertSame(['point_id', 'date', ...AiExportFraicheur::FEATURES, 'label_14j', 'source'], $lignes[0]);
        $this->assertCount(2, $lignes);
        $this->assertSame('1', $lignes[1][count($lignes[1]) - 2]);
        $this->assertSame('reel', end($lignes[1]));

        @unlink($dossier.'/fraicheur_real.csv');
        @rmdir($dossier);
    }

    public function test_le_classement_penalise_les_points_a_risque(): void
    {
        $sain = PointFraicheur::factory()->parc()->create(['nom' => 'Parc sain']);
        $degrade = PointFraicheur::factory()->parc()->create(['nom' => 'Parc degrade']);

        $recommandations = collect([
            ['point' => $degrade, 'score' => 80, 'raisons' => []],
            ['point' => $sain, 'score' => 70, 'raisons' => []],
        ]);
        $santes = collect([
            $degrade->id => ['niveau' => SantePoint::Critique],
            $sain->id => ['niveau' => SantePoint::Bon],
        ]);

        $classement = app(RecommandationFraicheurService::class)->appliquerSante($recommandations, $santes);

        // 80 × 0,7 = 56 < 70 : le point sain passe devant.
        $this->assertSame([$sain->id, $degrade->id], $classement->pluck('point.id')->all());
        $this->assertSame(56, $classement[1]['score']);
        $this->assertSame(['Prévision IA : problèmes probables dans les 14 prochains jours'], $classement[1]['raisons']);
        $this->assertSame([], $classement[0]['raisons']);
    }

    public function test_le_front_affiche_la_prevision_et_une_alternative(): void
    {
        $degrade = $this->pointDegrade();
        $sain = PointFraicheur::factory()->parc()->create(['nom' => 'Parc des Oliviers', 'ouvert_24h' => true]);

        $this->get(route('points.show', $degrade))
            ->assertOk()
            ->assertSee('Prévision IA · 14 jours')
            ->assertSee('Problèmes probables')
            ->assertSee('Alternative en bon état')
            ->assertSee('Parc des Oliviers')
            ->assertDontSee('Programmer un nettoyage du site'); // actions réservées à l'admin

        $this->get(route('points.show', $sain))
            ->assertOk()
            ->assertSee('Bon état prévu')
            ->assertDontSee('Alternative en bon état');

        $this->get(route('refuges.index'))
            ->assertOk()
            ->assertSee('Prévision IA : problèmes probables');
    }

    public function test_le_back_office_affiche_la_sante_predite(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $point = $this->pointDegrade();

        $this->actingAs($admin)->get(route('back.points.index'))
            ->assertOk()
            ->assertSee('Points à risque dans les 14 prochains jours')
            ->assertSee('Salle Ibn Khaldoun')
            ->assertSee('Critique')
            ->assertSee('Programmer un nettoyage du site');

        $this->actingAs($admin)->get(route('back.points.show', $point))
            ->assertOk()
            ->assertSee('Santé prédite (IA)')
            ->assertSee('Actions conseillées')
            ->assertSee('Règles de sécurité (service IA indisponible)');
    }
}
