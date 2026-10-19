<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\SignalementAiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignalementAiClientTest extends TestCase
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

    public function test_classification_envoie_la_description_et_le_jeton(): void
    {
        Http::fake([
            'ai.test/signalements/classify' => Http::response([
                'categorie' => 'fuite',
                'confiance' => 91,
                'probabilites' => ['fuite' => 0.91],
            ]),
        ]);

        $result = app(SignalementAiClient::class)->classify('Une fuite au plafond.');

        $this->assertSame('fuite', $result['categorie']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-AI-TOKEN', 'test-token')
            && $request->url() === 'http://ai.test/signalements/classify'
            && $request['description'] === 'Une fuite au plafond.');
    }

    public function test_classification_retourne_null_sur_erreur_http_ou_transport(): void
    {
        Http::fake(['ai.test/*' => Http::response('indisponible', 503)]);
        $this->assertNull(app(SignalementAiClient::class)->classify('Fuite'));

        Http::fake(function (): void {
            throw new ConnectionException('Connection refused');
        });
        $this->assertNull(app(SignalementAiClient::class)->classify('Fuite'));
    }

    public function test_classification_ne_fait_pas_appel_quand_le_service_est_desactive(): void
    {
        config(['ai.enabled' => false]);
        Http::fake();

        $this->assertNull(app(SignalementAiClient::class)->classify('Fuite'));
        Http::assertNothingSent();
    }

    public function test_retrain_demande_un_entrainement_dedie(): void
    {
        Http::fake([
            'ai.test/retrain-signalements' => Http::response([
                'status' => 'running',
                'target' => 'signalements',
            ]),
        ]);

        $result = app(SignalementAiClient::class)->retrain();

        $this->assertSame('signalements', $result['target']);
        Http::assertSent(fn ($request) => $request->hasHeader('X-AI-TOKEN', 'test-token')
            && str_ends_with($request->url(), '/retrain-signalements'));
    }
}
