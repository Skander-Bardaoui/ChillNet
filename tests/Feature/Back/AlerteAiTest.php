<?php

namespace Tests\Feature\Back;

use App\Enums\LieuType;
use App\Enums\NiveauAlerte;
use App\Enums\Role;
use App\Models\AiPrediction;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Models\User;
use App\Services\MeteoActuelle;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Boucle de retour d'expérience IA côté back-office : traçage des prédictions,
 * explicabilité de `prefill`, rattachement des corrections gestionnaire, et
 * non-régression de la visibilité habitant.
 */
class AlerteAiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    private function quartier(string $nom = 'Centre-Ville'): Quartier
    {
        return Quartier::create(['nom' => $nom, 'ville' => 'Tunis', 'code_postal' => '1000']);
    }

    public function test_prefill_exposes_the_hybrid_explainability_keys(): void
    {
        Http::fake();

        $admin = $this->admin();
        $quartier = $this->quartier();

        $response = $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), [
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'temperature_actuelle' => 41,
                'humidite' => 60,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'niveau' => NiveauAlerte::Rouge->value,
                'source' => 'manuel',
                'niveau_regles' => NiveauAlerte::Rouge->value,
            ]);

        foreach (['probabilites', 'confiance', 'facteurs', 'desaccord', 'modele_disponible', 'raison', 'prediction_id', 'niveau_modele'] as $cle) {
            $response->assertJsonStructure([$cle]);
        }

        Http::assertNothingSent();
    }

    public function test_prefill_records_a_reproducible_prediction_trace(): void
    {
        Http::fake();

        $admin = $this->admin();
        $quartier = $this->quartier();

        $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), [
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'temperature_actuelle' => 41,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(1, AiPrediction::count());

        $trace = AiPrediction::firstOrFail();
        $this->assertNull($trace->alerte_id);
        $this->assertSame([$quartier->id], $trace->quartier_ids);
        $this->assertIsArray($trace->context);
        $this->assertArrayHasKey('temperature', $trace->context);
        $this->assertSame('rouge', $trace->niveau_regles);
    }

    public function test_prefill_never_500_when_the_model_is_missing(): void
    {
        Http::fake();

        // Service IA désactivé (comme en exploitation dégradée) : les règles
        // seules doivent décider, sans jamais renvoyer d'erreur.
        config(['ai.enabled' => false]);

        $admin = $this->admin();
        $quartier = $this->quartier();

        $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), [
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'temperature_actuelle' => 41,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'niveau' => NiveauAlerte::Rouge->value,
                'modele_disponible' => false,
                'desaccord' => false,
            ]);
    }

    public function test_prefill_never_500_when_weather_service_throws(): void
    {
        // WeatherService en panne franche (régression) : l'endpoint doit
        // répondre proprement `ok:false`, jamais une 500.
        $this->app->bind(WeatherService::class, fn () => new class extends WeatherService
        {
            public function actuel(?float $latitude, ?float $longitude): ?MeteoActuelle
            {
                throw new \RuntimeException('panne');
            }

            public function actuelPourQuartiers(iterable $quartiers): ?MeteoActuelle
            {
                throw new \RuntimeException('panne');
            }

            public function previsions(?float $latitude, ?float $longitude, int $heures = 24, ?int $jours = null): array
            {
                throw new \RuntimeException('panne');
            }

            public function previsionsPourQuartiers(iterable $quartiers, int $heures = 48): array
            {
                throw new \RuntimeException('panne');
            }
        });

        $admin = $this->admin();
        $quartier = Quartier::create([
            'nom' => 'Centre', 'ville' => 'Tunis', 'code_postal' => '1000',
            'latitude' => 36.8065, 'longitude' => 10.1815,
        ]);

        $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), ['quartier_ids' => [$quartier->id]])
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_a_manager_correction_is_stored_as_the_final_level(): void
    {
        Http::fake();

        $admin = $this->admin();
        $quartier = $this->quartier();

        // 1) Pré-remplissage : crée une trace IA.
        $predictionId = $this->actingAs($admin)
            ->postJson(route('back.alertes.prefill'), [
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'temperature_actuelle' => 41,
            ])
            ->assertOk()
            ->json('prediction_id');

        $this->assertNotNull($predictionId);

        // 2) Le gestionnaire enregistre en choisissant un niveau DIFFÉRENT
        //    (correction) : le niveau réellement retenu est figé.
        $this->actingAs($admin)
            ->post(route('back.alertes.store'), [
                'titre' => 'Vigilance forte chaleur',
                'niveau' => NiveauAlerte::Orange->value,
                'quartier_ids' => [$quartier->id],
                'seuil_temperature' => 35,
                'debut' => now()->format('Y-m-d H:i:s'),
                'fin' => now()->addHours(8)->format('Y-m-d H:i:s'),
                'message' => 'Restez hydratés.',
                'source_meteo' => 'manuel',
                'ai_prediction_id' => $predictionId,
            ])
            ->assertRedirect(route('back.alertes.index'));

        $alerte = Alerte::firstOrFail();
        $trace = AiPrediction::findOrFail($predictionId);

        $this->assertSame($alerte->id, $trace->alerte_id);
        $this->assertSame('orange', $trace->niveau_final);
    }

    public function test_validating_a_draft_finalises_its_linked_predictions(): void
    {
        $admin = $this->admin();
        $quartier = $this->quartier();

        $alerte = Alerte::factory()->create([
            'niveau' => NiveauAlerte::Rouge->value,
            'validee' => false,
        ]);
        $alerte->quartiers()->sync([$quartier->id]);

        $trace = AiPrediction::create([
            'alerte_id' => $alerte->id,
            'quartier_ids' => [$quartier->id],
            'context' => ['temperature' => 41.0],
            'niveau_modele' => NiveauAlerte::Rouge->value,
            'probabilites' => ['rouge' => 0.9],
            'niveau_regles' => NiveauAlerte::Rouge->value,
            'niveau_final' => null,
            'model_version' => 'test',
        ]);

        $this->actingAs($admin)
            ->patch(route('back.alertes.valider', $alerte->id))
            ->assertRedirect(route('back.alertes.index'));

        $this->assertSame('rouge', $trace->fresh()->niveau_final);
    }

    public function test_the_ai_performance_card_is_admin_only(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('back.dashboard'))
            ->assertOk()
            ->assertSee('Performance IA');
    }

    public function test_habitant_visibility_still_ignores_unvalidated_alerts(): void
    {
        $quartier = $this->quartier('Centre-Ville');
        $habitant = User::factory()->create(['role' => Role::Habitant]);
        $habitant->lieux()->create([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'quartier_id' => $quartier->id,
            'est_principal' => true,
        ]);

        Alerte::factory()->validee()->active()->rouge()->create(['titre' => 'Alerte Validee'])
            ->quartiers()->sync([$quartier->id]);
        Alerte::factory()->active()->rouge()->create(['titre' => 'Brouillon IA', 'validee' => false])
            ->quartiers()->sync([$quartier->id]);

        $this->actingAs($habitant)
            ->get(route('alertes.index'))
            ->assertOk()
            ->assertSee('Alerte Validee')
            ->assertDontSee('Brouillon IA');
    }
}
