<?php

namespace Tests\Unit\Ai;

use App\Enums\NiveauAlerte;
use App\Services\VigilanceAiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Orchestration Laravel ↔ service Python : repli sur les règles et plancher.
 */
class VigilanceAiServicePredictTest extends TestCase
{
    private function service(): VigilanceAiService
    {
        return app(VigilanceAiService::class);
    }

    public function test_repli_sur_les_regles_quand_le_service_est_indisponible(): void
    {
        config(['ai.enabled' => false]);

        $prediction = $this->service()->predict(['temperature' => 42.0, 'seuil' => 35.0]);

        $this->assertSame('regles', $prediction['source']);
        $this->assertFalse($prediction['modele_disponible']);
        $this->assertSame('rouge', $prediction['niveau_final']);
        $this->assertSame(NiveauAlerte::Rouge, $prediction['niveau']);
        $this->assertNull($prediction['niveau_modele']);
        $this->assertSame([], $prediction['probabilites']);
        $this->assertStringContainsString('Règles seules', $prediction['raison']);
    }

    public function test_repli_sur_les_regles_sur_erreur_du_service(): void
    {
        config(['ai.enabled' => true, 'ai.service_url' => 'http://ai.test', 'ai.token' => 't']);
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);

        $prediction = $this->service()->predict(['temperature' => 38.0, 'seuil' => 35.0]);

        $this->assertSame('regles', $prediction['source']);
        $this->assertSame('orange', $prediction['niveau_final']);
    }

    public function test_reponse_du_service_est_utilisee_et_mappee(): void
    {
        config(['ai.enabled' => true, 'ai.service_url' => 'http://ai.test', 'ai.token' => 't']);
        Http::fake([
            'ai.test/analyze' => Http::response([
                'niveau_final' => 'rouge',
                'niveau_modele' => 'orange',
                'niveau_regles' => 'orange',
                'desaccord' => false,
                'probabilites' => ['orange' => 0.7, 'rouge' => 0.3],
                'confiance' => 0.7,
                'facteurs' => [['feature' => 'temp', 'label' => 'Température']],
                'raison' => 'Règles : orange — Modèle : orange',
                'source' => 'modele',
                'model_version' => '1.0.0',
            ]),
        ]);

        $prediction = $this->service()->predict(['temperature' => 40.0, 'seuil' => 35.0]);

        $this->assertSame('modele', $prediction['source']);
        $this->assertTrue($prediction['modele_disponible']);
        $this->assertSame('rouge', $prediction['niveau_final']);
        $this->assertSame(NiveauAlerte::Orange, $prediction['niveau_modele']);
        $this->assertSame('1.0.0', $prediction['model_version']);
        $this->assertSame(0.7, $prediction['confiance']);
        $this->assertArrayHasKey('orange', $prediction['probabilites']);
    }

    public function test_plancher_de_securite_php_applique_si_le_service_descend_trop(): void
    {
        config(['ai.enabled' => true, 'ai.service_url' => 'http://ai.test', 'ai.token' => 't']);
        // Le service prétend « jaune » alors que les règles PHP donnent « rouge ».
        Http::fake([
            'ai.test/analyze' => Http::response([
                'niveau_final' => 'jaune',
                'niveau_modele' => 'jaune',
                'niveau_regles' => 'jaune',
                'source' => 'modele',
            ]),
        ]);

        $prediction = $this->service()->predict(['temperature' => 42.0, 'seuil' => 35.0]);

        $this->assertSame('rouge', $prediction['niveau_final']);
        $this->assertSame(NiveauAlerte::Rouge, $prediction['niveau']);
    }

    public function test_niveau_final_normal_du_service_est_ignore(): void
    {
        config(['ai.enabled' => true, 'ai.service_url' => 'http://ai.test', 'ai.token' => 't']);
        Http::fake([
            'ai.test/analyze' => Http::response([
                'niveau_final' => 'normal',
                'niveau_modele' => 'normal',
                'niveau_regles' => 'normal',
                'source' => 'modele',
            ]),
        ]);

        $prediction = $this->service()->predict(['temperature' => 37.0, 'seuil' => 35.0]);

        // « normal » n'existe pas côté règles : le plancher jaune est conservé.
        $this->assertSame('jaune', $prediction['niveau_final']);
        $this->assertNull($prediction['niveau_modele']);
    }
}
