<?php

namespace Tests\Unit\Face;

use App\Services\Face\FacePlusPlusClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contrat du client Face++ : jamais d'exception, `null` en cas d'échec,
 * identifiants transmis en formulaire, `error_message` traité comme un échec
 * même avec un HTTP 200.
 */
class FacePlusPlusClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.faceplusplus.enabled' => true,
            'services.faceplusplus.key' => 'test-key',
            'services.faceplusplus.secret' => 'test-secret',
            'services.faceplusplus.base_url' => 'https://api-us.faceplusplus.test',
            'services.faceplusplus.timeout' => 2.0,
        ]);
    }

    private function client(): FacePlusPlusClient
    {
        return app(FacePlusPlusClient::class);
    }

    public function test_enabled_reflete_la_configuration(): void
    {
        $this->assertTrue($this->client()->enabled());

        config(['services.faceplusplus.enabled' => false]);
        $this->assertFalse($this->client()->enabled());

        config(['services.faceplusplus.enabled' => true, 'services.faceplusplus.key' => '']);
        $this->assertFalse($this->client()->enabled());

        config(['services.faceplusplus.key' => 'test-key', 'services.faceplusplus.secret' => '']);
        $this->assertFalse($this->client()->enabled());
    }

    public function test_detect_renvoie_les_visages_et_envoie_les_identifiants_en_formulaire(): void
    {
        Http::fake([
            'api-us.faceplusplus.test/facepp/v3/detect' => Http::response([
                'faces' => [['face_token' => 'abc123']],
            ]),
        ]);

        $faces = $this->client()->detect('data:image/jpeg;base64,'.base64_encode('probe'));

        $this->assertIsArray($faces);
        $this->assertCount(1, $faces);
        $this->assertSame('abc123', $faces[0]['face_token']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/facepp/v3/detect')
            && $request['api_key'] === 'test-key'
            && $request['api_secret'] === 'test-secret'
            && $request['image_base64'] === base64_encode('probe'));
    }

    public function test_detect_renvoie_un_tableau_vide_sans_visage(): void
    {
        Http::fake([
            'api-us.faceplusplus.test/*' => Http::response(['faces' => []]),
        ]);

        $this->assertSame([], $this->client()->detect(base64_encode('probe')));
    }

    public function test_un_error_message_avec_http_200_est_un_echec(): void
    {
        Http::fake([
            'api-us.faceplusplus.test/*' => Http::response([
                'error_message' => 'AUTHENTICATION_ERROR',
            ]),
        ]);

        $this->assertNull($this->client()->detect(base64_encode('probe')));
    }

    public function test_detect_renvoie_null_sur_statut_500(): void
    {
        Http::fake([
            'api-us.faceplusplus.test/*' => Http::response('boom', 500),
        ]);

        $this->assertNull($this->client()->detect(base64_encode('probe')));
    }

    public function test_detect_renvoie_null_sur_connexion_impossible(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Operation timed out');
        });

        $this->assertNull($this->client()->detect(base64_encode('probe')));
    }

    public function test_compare_renvoie_la_confiance_et_les_seuils(): void
    {
        Http::fake([
            'api-us.faceplusplus.test/facepp/v3/compare' => Http::response([
                'confidence' => 88.4,
                'thresholds' => ['1e-3' => 70.0, '1e-5' => 82.0],
            ]),
        ]);

        $resultat = $this->client()->compare(base64_encode('probe'), base64_encode('reference'));

        $this->assertNotNull($resultat);
        $this->assertSame(88.4, $resultat['confidence']);
        $this->assertEquals(82.0, $resultat['thresholds']['1e-5']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/facepp/v3/compare')
            && $request['image_base64_1'] === base64_encode('probe')
            && $request['image_base64_2'] === base64_encode('reference'));
    }

    public function test_compare_renvoie_null_sans_confiance(): void
    {
        Http::fake([
            'api-us.faceplusplus.test/*' => Http::response(['thresholds' => ['1e-5' => 80.0]]),
        ]);

        $this->assertNull($this->client()->compare(base64_encode('a'), base64_encode('b')));
    }

    public function test_les_methodes_ne_font_aucun_appel_quand_desactive(): void
    {
        config(['services.faceplusplus.enabled' => false]);

        $this->assertNull($this->client()->detect(base64_encode('probe')));
        $this->assertNull($this->client()->compare(base64_encode('a'), base64_encode('b')));

        Http::assertNothingSent();
    }
}
