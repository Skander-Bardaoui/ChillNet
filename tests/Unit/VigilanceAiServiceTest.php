<?php

namespace Tests\Unit;

use App\Enums\NiveauAlerte;
use App\Enums\ProfilVulnerabilite;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Services\VigilanceAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VigilanceAiServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): VigilanceAiService
    {
        return app(VigilanceAiService::class);
    }

    public function test_seuil_defaut_comes_from_config(): void
    {
        config(['services.weather.seuil_defaut' => 33]);

        $this->assertSame(33.0, $this->service()->seuilDefaut());
    }

    public function test_thresholds_are_seuil_plus_three_and_plus_six(): void
    {
        $ia = $this->service();

        // Seuil explicite pour ne pas dépendre de la config.
        $this->assertSame(NiveauAlerte::Jaune, $ia->niveauPour(37.0, null, [], 35.0));
        $this->assertSame(NiveauAlerte::Orange, $ia->niveauPour(38.0, null, [], 35.0));
        $this->assertSame(NiveauAlerte::Rouge, $ia->niveauPour(41.0, null, [], 35.0));
    }

    public function test_humidity_adds_a_penalty_to_the_effective_temperature(): void
    {
        $ia = $this->service();

        // Pénalité nulle à 60 % ; un peu d'air humide (65 %) suffit à franchir
        // le palier orange pour une température juste en dessous.
        $this->assertSame(NiveauAlerte::Jaune, $ia->niveauPour(37.0, 60, [], 35.0));
        $this->assertSame(NiveauAlerte::Jaune, $ia->niveauPour(37.6, 60, [], 35.0));
        $this->assertSame(NiveauAlerte::Orange, $ia->niveauPour(37.6, 65, [], 35.0));
    }

    public function test_high_humidity_escalates_one_level(): void
    {
        $ia = $this->service();

        // 33°C + pénalité humidité → reste jaune, mais >= 70 % escalade à orange.
        $this->assertSame(NiveauAlerte::Orange, $ia->niveauPour(33.0, 85, [], 35.0));

        // 37°C + pénalité saturée (40°C effectif → orange), humidité extrême → rouge.
        $this->assertSame(NiveauAlerte::Rouge, $ia->niveauPour(37.0, 100, [], 35.0));
    }

    public function test_an_installed_heatwave_escalates_the_level(): void
    {
        $ia = $this->service();

        // 2 jours de surchauffe : pas d'aggravation.
        $this->assertSame(NiveauAlerte::Jaune, $ia->niveauPour(37.0, null, ['jours_surchauffe' => 2], 35.0));

        // 3 jours : +2°C (35 → 37 → jaune) puis escalade → orange.
        $this->assertSame(NiveauAlerte::Orange, $ia->niveauPour(35.0, null, ['jours_surchauffe' => 3], 35.0));
    }

    public function test_rouge_is_the_maximum_level(): void
    {
        $this->assertSame(NiveauAlerte::Rouge, $this->service()->niveauPour(60.0, 100, ['jours_surchauffe' => 5], 35.0));
    }

    public function test_analyse_returns_a_readable_reason(): void
    {
        $analyse = $this->service()->analyse(41.0, 72, [], 35.0);

        $this->assertSame(NiveauAlerte::Rouge, $analyse['niveau']);
        $this->assertSame(35.0, $analyse['seuil']);
        $this->assertStringContainsString('41', $analyse['raison']);
        $this->assertStringContainsString('rouge', $analyse['raison']);
    }

    public function test_message_falls_back_to_deterministic_template_without_api_key(): void
    {
        config(['services.groq.key' => null]);

        $message = $this->service()->messagePourContexte(
            ['niveau_label' => 'Vigilance rouge', 'quartiers' => ['Centre-Ville'], 'temperature_actuelle' => 41.0],
            [ProfilVulnerabilite::PersonneAgee],
        );

        $this->assertStringContainsString('0800 06 66 66', $message);
        $this->assertStringContainsString('Personne âgée', $message);
    }

    public function test_message_uses_groq_when_available(): void
    {
        config(['services.groq.key' => 'fake-key']);
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Pense à boire de l\'eau régulièrement.']]],
            ]),
        ]);

        $message = $this->service()->messagePourContexte(['niveau_label' => 'Vigilance rouge'], []);

        $this->assertSame('Pense à boire de l\'eau régulièrement.', $message);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions'));
    }

    public function test_message_falls_back_when_groq_fails(): void
    {
        config(['services.groq.key' => 'fake-key']);
        Http::fake(['api.groq.com/*' => Http::response('boom', 500)]);

        $message = $this->service()->messagePourContexte(['niveau_label' => 'Vigilance rouge'], []);

        $this->assertStringContainsString('0800 06 66 66', $message);
    }

    public function test_historique_counts_distinct_overheating_days_for_a_quartier(): void
    {
        $quartier = Quartier::create(['nom' => 'Centre-Ville', 'ville' => 'Tunis', 'code_postal' => '1000']);
        $autre = Quartier::create(['nom' => 'Ailleurs', 'ville' => 'Tunis', 'code_postal' => '1001']);

        // Deux jours de surchauffe sur le quartier suivi.
        Alerte::factory()->hasAttached($quartier)->create([
            'temperature_actuelle' => 39.0,
            'seuil_temperature' => 35.0,
            'debut' => now()->subDay(),
        ]);
        Alerte::factory()->hasAttached($quartier)->create([
            'temperature_actuelle' => 40.0,
            'seuil_temperature' => 35.0,
            'debut' => now()->subDays(2),
        ]);
        // Sous le seuil : ne compte pas.
        Alerte::factory()->hasAttached($quartier)->create([
            'temperature_actuelle' => 30.0,
            'seuil_temperature' => 35.0,
            'debut' => now()->subDays(3),
        ]);
        // Autre quartier : ignoré.
        Alerte::factory()->hasAttached($autre)->create([
            'temperature_actuelle' => 42.0,
            'seuil_temperature' => 35.0,
            'debut' => now()->subDay(),
        ]);

        $historique = $this->service()->historiquePourQuartier($quartier->id, 7);

        $this->assertSame(2, $historique['jours_surchauffe']);
        $this->assertSame(40.0, $historique['temp_max']);
    }
}
