<?php

namespace Tests\Feature\Front;

use App\Enums\CriticiteEquipement;
use App\Enums\Role;
use App\Models\Equipement;
use App\Models\EquipementType;
use App\Models\Lieu;
use App\Models\User;
use App\Services\ConseilRiskService;
use App\Services\MeteoActuelle;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests feature du Module 4 — Conseils & équipements sensibles.
 *
 * Vérifie :
 *  - la page /conseils renvoie 200 (avec et sans équipements),
 *  - le service ML simulé (Http::fake) : succès, échec, timeout,
 *  - la RÈGLE DE SÉCURITÉ ABSOLUE : criticité vitale → alerte vitale
 *    même si le ML prédit « faible »,
 *  - le repli déterministe quand le service ML est indisponible.
 */
class ConseilRiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'ai.enabled'     => true,
            'ai.service_url' => 'http://ai.test',
            'ai.token'       => 'test-token',
            'ai.timeout'     => 3.0,
        ]);
    }

    private function habitantAvecEquipement(
        string $typeSlug = 'respirateur',
        string $criticite = 'vitale',
    ): User {
        $user = User::factory()->create([
            'role'               => Role::Habitant,
            'email_verified_at'  => now(),
        ]);

        $type = EquipementType::where('slug', $typeSlug)->first()
            ?? EquipementType::create([
                'nom'    => 'Test Type',
                'slug'   => $typeSlug,
                'icone'  => 'devices',
                'medical' => $typeSlug === 'respirateur',
                'actif'   => true,
                'ordre'   => 1,
            ]);

        Equipement::create([
            'user_id'            => $user->id,
            'equipement_type_id' => $type->id,
            'nom'                => 'Equipement test',
            'criticite'          => $criticite,
            'contact_urgence'    => $criticite === 'vitale' ? 'Fille 0612345678' : null,
        ]);

        return $user;
    }

    private function habitantSansEquipement(): User
    {
        return User::factory()->create([
            'role'              => Role::Habitant,
            'email_verified_at' => now(),
        ]);
    }

    private function fakeMlSucces(string $niveau = 'critique', bool $alerteVitale = true): void
    {
        Http::fake(function ($request) use ($niveau, $alerteVitale) {
            if (str_contains($request->url(), 'ai.test/conseils/risk')) {
                return Http::response([
                    'results' => [[
                        'foyer_id'       => 1,
                        'niveau_final'   => $niveau,
                        'niveau_modele'  => $niveau,
                        'niveau_regles'  => $niveau,
                        'desaccord'      => false,
                        'confiance'      => 0.95,
                        'probabilites'   => ['faible' => 0.05, 'critique' => 0.95],
                        'facteurs'        => ['Coupure en cours : oui', 'Respirateur présent : oui'],
                        'alerte_vitale'  => $alerteVitale,
                        'raison'         => 'Modèle et règles alignés : critique.',
                        'source'         => 'modele',
                        'model_version'  => '1.0.0',
                    ]],
                ]);
            }

            return Http::response(['status' => 'ok'], 200);
        });
    }

    private function fakeMlIndisponible(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'ai.test/conseils/risk')) {
                return Http::response('Service indisponible', 500);
            }

            return Http::response(['status' => 'ok'], 200);
        });
    }

    private function fakeMlTimeout(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'ai.test/conseils/risk')) {
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            }

            return Http::response(['status' => 'ok'], 200);
        });
    }

    // ----------------------------------------------------------------------- //
    // Tests
    // ----------------------------------------------------------------------- //

    public function test_page_conseils_renvoie_200_avec_equipements(): void
    {
        $this->fakeMlSucces('critique', true);

        $user = $this->habitantAvecEquipement('respirateur', 'vitale');

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
        $response->assertSee('Mes conseils personnalisés');
    }

    public function test_page_conseils_renvoie_200_sans_equipements(): void
    {
        $this->fakeMlSucces('faible', false);

        $user = $this->habitantSansEquipement();

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
        $response->assertSee('Mes conseils personnalisés');
    }

    public function test_regle_vitale_passe_meme_si_ml_predit_faible(): void
    {
        // Le ML prédit « faible » mais l'équipement est vital → la règle de
        // sécurité absolue côté PHP doit forcer alerte_vitale et niveau critique.
        $this->fakeMlSucces('faible', false);

        $user = $this->habitantAvecEquipement('respirateur', 'vitale');

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
        // Le bandeau vital doit s'afficher
        $response->assertSee('protocole d');
    }

    public function test_repli_deterministe_quand_ml_indisponible(): void
    {
        $this->fakeMlIndisponible();

        $user = $this->habitantAvecEquipement('respirateur', 'vitale');

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
        // La page doit s'afficher normalement (pas de 500)
        $response->assertSee('Mes conseils personnalisés');
    }

    public function test_repli_deterministe_quand_ml_timeout(): void
    {
        $this->fakeMlTimeout();

        $user = $this->habitantAvecEquipement('refrigerateur', 'elevee');

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
    }

    public function test_le_repli_n_est_pas_cache_et_le_modele_revient_au_chargement_suivant(): void
    {
        $user = $this->habitantAvecEquipement('refrigerateur', 'elevee');
        $disponible = false;

        Http::fake(function ($request) use (&$disponible) {
            if (str_contains($request->url(), 'ai.test/conseils/risk')) {
                if (! $disponible) {
                    return Http::response('Service indisponible', 500);
                }

                return Http::response([
                    'results' => [[
                        'foyer_id'      => 1,
                        'niveau_final'  => 'eleve',
                        'niveau_modele' => 'eleve',
                        'niveau_regles' => 'eleve',
                        'desaccord'     => false,
                        'confiance'     => 0.82,
                        'probabilites'  => ['faible' => 0.05, 'modere' => 0.13, 'eleve' => 0.82],
                        'facteurs'      => ['Coupure en cours : oui'],
                        'alerte_vitale' => false,
                        'raison'        => 'Modèle et règles alignés : élevé.',
                        'source'        => 'modele',
                        'model_version' => '1.0.0',
                    ]],
                ]);
            }

            return Http::response(['status' => 'ok'], 200);
        });

        $this->actingAs($user)->get(route('conseils'))
            ->assertOk()
            ->assertDontSee('Distribution des probabilités');

        // Le service ML revient : le repli ne doit pas avoir figé la page.
        $disponible = true;

        $this->actingAs($user)->get(route('conseils'))
            ->assertOk()
            ->assertSee('Distribution des probabilités')
            ->assertSee('Modèle IA');
    }

    public function test_page_conseils_affiche_niveau_risque_ml(): void
    {
        $this->fakeMlSucces('critique', true);

        $user = $this->habitantAvecEquipement('respirateur', 'vitale');

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
        $response->assertSee('Niveau de risque');
        $response->assertSee('Critique');
    }

    public function test_payload_ml_contient_la_meteo_reelle_du_lieu(): void
    {
        $this->fakeMlSucces('eleve', true);

        $this->mock(WeatherService::class, function ($mock): void {
            $mock->shouldReceive('actuelCache')
                ->andReturn(new MeteoActuelle(temperature: 39.5, ressentie: 43.0, humidite: 55));
        });

        $user = $this->habitantAvecEquipement('refrigerateur', 'elevee');
        Lieu::factory()->principal()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('conseils'))->assertOk();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/conseils/risk')) {
                return false;
            }

            $foyer = $request['foyers'][0];

            return $foyer['temperature'] === 39.5
                && $foyer['ressentie'] === 43.0
                && $foyer['humidite'] === 55
                && $foyer['canicule_niveau'] === 2;
        });
    }

    public function test_niveau_canicule_depuis_temperature(): void
    {
        $this->assertSame(0, ConseilRiskService::niveauCaniculeDepuisTemperature(null));
        $this->assertSame(0, ConseilRiskService::niveauCaniculeDepuisTemperature(34.9));
        $this->assertSame(1, ConseilRiskService::niveauCaniculeDepuisTemperature(35.0));
        $this->assertSame(2, ConseilRiskService::niveauCaniculeDepuisTemperature(38.0));
        $this->assertSame(3, ConseilRiskService::niveauCaniculeDepuisTemperature(41.0));
    }

    public function test_equipement_medical_non_vital_declenche_alerte_vitale(): void
    {
        // Même définition que le service Python : médical OU criticité vitale.
        $this->fakeMlSucces('faible', false);

        $user = $this->habitantAvecEquipement('respirateur', 'elevee');

        $risque = app(ConseilRiskService::class)->evaluerFoyer($user, null);

        $this->assertTrue($risque['alerte_vitale']);
        $this->assertSame('critique', $risque['niveau_final']);
    }

    public function test_page_conseils_affiche_facteurs(): void
    {
        $this->fakeMlSucces('eleve', true);

        $user = $this->habitantAvecEquipement('respirateur', 'vitale');

        $response = $this->actingAs($user)->get(route('conseils'));

        $response->assertOk();
        $response->assertSee('Facteurs');
    }
}
