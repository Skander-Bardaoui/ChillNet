<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\CoupureAiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contrat du client HTTP « coupures » (`/coupures/*`).
 *
 * Règle d'or : AUCUNE méthode ne lève jamais et AUCUNE ne bloque au-delà du
 * timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide → `null`.
 */
class CoupureAiClientTest extends TestCase
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

    private function client(): CoupureAiClient
    {
        return app(CoupureAiClient::class);
    }

    public function test_enabled_reflete_la_configuration(): void
    {
        $this->assertTrue($this->client()->enabled());

        config(['ai.enabled' => false]);
        $this->assertFalse($this->client()->enabled());

        config(['ai.enabled' => true, 'ai.service_url' => '']);
        $this->assertFalse($this->client()->enabled());
    }

    public function test_risk_renvoie_les_resultats_et_envoie_le_jeton(): void
    {
        Http::fake([
            'ai.test/coupures/risk' => Http::response([
                'results' => [
                    ['quartier_id' => 1, 'p24' => 0.42, 'score_final' => 42, 'source' => 'modele'],
                ],
            ]),
        ]);

        $resultats = $this->client()->risk([['quartier_id' => 1, 'temperature' => 40.0]]);

        $this->assertIsArray($resultats);
        $this->assertSame(1, $resultats[0]['quartier_id']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-AI-TOKEN', 'test-token')
            && str_contains($request->url(), '/coupures/risk'));
    }

    public function test_risk_renvoie_null_sur_500(): void
    {
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);

        $this->assertNull($this->client()->risk([['quartier_id' => 1]]));
    }

    public function test_risk_renvoie_null_sur_json_invalide(): void
    {
        Http::fake(['ai.test/*' => Http::response('pas du json', 200)]);

        $this->assertNull($this->client()->risk([['quartier_id' => 1]]));
    }

    public function test_risk_renvoie_null_sur_timeout(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Operation timed out');
        });

        $this->assertNull($this->client()->risk([['quartier_id' => 1]]));
    }

    public function test_risk_renvoie_null_quand_desactive(): void
    {
        config(['ai.enabled' => false]);

        $this->assertNull($this->client()->risk([['quartier_id' => 1]]));
    }

    public function test_anomalies_renvoie_les_incidents(): void
    {
        Http::fake([
            'ai.test/coupures/anomalies' => Http::response([
                'incidents' => [['id' => 'cluster-0', 'n_reports' => 4, 'radius_m' => 320.0]],
            ]),
        ]);

        $incidents = $this->client()->anomalies(['signalements' => []]);

        $this->assertIsArray($incidents);
        $this->assertSame(4, $incidents[0]['n_reports']);
    }

    public function test_etr_renvoie_les_resultats(): void
    {
        Http::fake([
            'ai.test/coupures/etr' => Http::response([
                'results' => [['id' => 7, 'p10_min' => 60.0, 'p50_min' => 120.0, 'p90_min' => 180.0]],
            ]),
        ]);

        $resultats = $this->client()->etr([['id' => 7, 'type' => 'panne']]);

        $this->assertIsArray($resultats);
        $this->assertSame(7, $resultats[0]['id']);
    }

    public function test_insights_et_retrain(): void
    {
        Http::fake([
            'ai.test/coupures/insights' => Http::response(['available' => true, 'version' => '1.0.0']),
            'ai.test/retrain-coupures' => Http::response(['status' => 'running']),
        ]);

        $this->assertTrue($this->client()->insights()['available']);
        $this->assertSame('running', $this->client()->retrain()['status']);
    }

    public function test_insights_renvoie_null_sur_erreur(): void
    {
        Http::fake(['ai.test/*' => Http::response('boom', 500)]);

        $this->assertNull($this->client()->insights());
    }
}
