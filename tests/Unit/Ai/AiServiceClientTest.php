<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contrat du client HTTP vers le microservice IA.
 *
 * Règle d'or : AUCUNE méthode ne lève jamais et AUCUNE ne bloque au-delà du
 * timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide → `null`.
 */
class AiServiceClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.enabled' => true,
            'ai.service_url' => 'http://ai.test',
            'ai.token' => 'test-token',
            'ai.timeout' => 2.0,
        ]);
    }

    private function client(): AiServiceClient
    {
        return app(AiServiceClient::class);
    }

    public function test_enabled_reflete_la_configuration(): void
    {
        $this->assertTrue($this->client()->enabled());

        config(['ai.enabled' => false]);
        $this->assertFalse($this->client()->enabled());

        config(['ai.enabled' => true, 'ai.service_url' => '']);
        $this->assertFalse($this->client()->enabled());
    }

    public function test_analyze_renvoie_la_reponse_et_envoie_le_jeton(): void
    {
        Http::fake([
            'ai.test/analyze' => Http::response([
                'niveau_final' => 'rouge',
                'niveau_modele' => 'rouge',
                'niveau_regles' => 'orange',
                'source' => 'modele',
            ]),
        ]);

        $reponse = $this->client()->analyze(['temperature' => 42.0]);

        $this->assertIsArray($reponse);
        $this->assertSame('rouge', $reponse['niveau_final']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-AI-TOKEN', 'test-token')
            && str_contains($request->url(), '/analyze'));
    }

    public function test_analyze_renvoie_null_sur_statut_500(): void
    {
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);

        $this->assertNull($this->client()->analyze(['temperature' => 42.0]));
    }

    public function test_analyze_renvoie_null_sur_json_invalide(): void
    {
        Http::fake(['ai.test/*' => Http::response('pas du json', 200)]);

        $this->assertNull($this->client()->analyze(['temperature' => 42.0]));
    }

    public function test_analyze_renvoie_null_sur_timeout_ou_connexion_impossible(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Operation timed out');
        });

        $this->assertNull($this->client()->analyze(['temperature' => 42.0]));
    }

    public function test_analyze_renvoie_null_quand_le_service_est_desactive(): void
    {
        config(['ai.enabled' => false]);

        $this->assertNull($this->client()->analyze(['temperature' => 42.0]));
    }

    public function test_analyze_batch_renvoie_la_liste_results(): void
    {
        Http::fake([
            'ai.test/analyze/batch' => Http::response([
                'results' => [
                    ['niveau_final' => 'jaune'],
                    ['niveau_final' => 'rouge'],
                ],
            ]),
        ]);

        $resultats = $this->client()->analyzeBatch([['temperature' => 36.0], ['temperature' => 43.0]]);

        $this->assertIsArray($resultats);
        $this->assertCount(2, $resultats);
        $this->assertSame('rouge', $resultats[1]['niveau_final']);
    }

    public function test_analyze_batch_renvoie_null_sur_reponse_sans_resultats(): void
    {
        Http::fake(['ai.test/*' => Http::response(['ok' => true])]);

        $this->assertNull($this->client()->analyzeBatch([['temperature' => 36.0]]));
    }

    public function test_message_renvoie_le_texte_et_transmet_les_profils(): void
    {
        Http::fake([
            'ai.test/message' => Http::response(['message' => '  Bois de l\'eau.  ']),
        ]);

        $message = $this->client()->message(
            ['niveau' => 'orange'],
            ['personne_agee'],
            'alerte:1',
        );

        $this->assertSame('Bois de l\'eau.', $message);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://ai.test/message'
                && ($payload['profils'] ?? null) === ['personne_agee']
                && ($payload['seed'] ?? null) === 'alerte:1';
        });
    }

    public function test_message_renvoie_null_sur_message_vide(): void
    {
        Http::fake(['ai.test/*' => Http::response(['message' => '   '])]);

        $this->assertNull($this->client()->message(['niveau' => 'jaune']));
    }

    public function test_model_info_et_health(): void
    {
        Http::fake([
            'ai.test/model-info' => Http::response(['version' => '1.0.0', 'model_available' => true]),
            'ai.test/health' => Http::response(['status' => 'ok']),
        ]);

        $info = $this->client()->modelInfo();

        $this->assertIsArray($info);
        $this->assertSame('1.0.0', $info['version']);
        $this->assertTrue($this->client()->health());
    }

    public function test_health_est_faux_quand_le_service_ne_repond_pas(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('refused');
        });

        $this->assertFalse($this->client()->health());
    }

    public function test_reload_renvoie_vrai_sur_succes(): void
    {
        Http::fake(['ai.test/reload' => Http::response(['status' => 'reloaded'])]);

        $this->assertTrue($this->client()->reload());
    }

    public function test_reload_renvoie_faux_sur_erreur(): void
    {
        Http::fake(['ai.test/reload' => Http::response('boom', 500)]);

        $this->assertFalse($this->client()->reload());
    }

    public function test_retrain_renvoie_la_reponse(): void
    {
        Http::fake(['ai.test/retrain' => Http::response(['status' => 'running'])]);

        $reponse = $this->client()->retrain();

        $this->assertIsArray($reponse);
        $this->assertSame('running', $reponse['status']);
    }
}
