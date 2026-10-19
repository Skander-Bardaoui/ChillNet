<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FaceLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.faceplusplus.enabled' => true,
            'services.faceplusplus.key' => 'test-key',
            'services.faceplusplus.secret' => 'test-secret',
            'services.faceplusplus.base_url' => 'https://api-us.faceplusplus.test',
            'services.faceplusplus.timeout' => 2.0,
            'services.faceplusplus.threshold' => 80.0,
            'services.faceplusplus.far' => '1e-5',
            'services.faceplusplus.image_max_bytes' => 2097152,
        ]);
    }

    private function imageBase64(int $width = 320, int $height = 240, int $red = 120): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, 160, 200));

        ob_start();
        imagejpeg($image, null, 90);
        $binary = (string) ob_get_clean();

        imagedestroy($image);

        return base64_encode($binary);
    }

    private function utilisateurEnrole(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'face_reference_image' => $this->imageBase64(320, 240, 90),
            'face_enrolled_at' => now(),
            'face_consent_at' => now(),
            'face_consent_version' => '2026-10-01',
        ])->save();

        return $user;
    }

    private function fakeFacePlusPlus(float $confidence = 92.0, int $faceCount = 1, float $farThreshold = 82.0): void
    {
        Http::fake([
            'api-us.faceplusplus.test/facepp/v3/detect' => Http::response([
                'faces' => array_fill(0, $faceCount, ['face_token' => 'token']),
            ]),
            'api-us.faceplusplus.test/facepp/v3/compare' => Http::response([
                'confidence' => $confidence,
                'thresholds' => ['1e-3' => 70.0, '1e-5' => $farThreshold],
            ]),
        ]);
    }

    private function tentative(User $user, array $overrides = []): TestResponse
    {
        return $this->post(route('login.face.store'), array_merge([
            'email' => $user->email,
            'image' => $this->imageBase64(),
        ], $overrides));
    }

    private function assertEchecGenerique(TestResponse $response): void
    {
        $response->assertSessionHasErrors(['email' => trans('auth.face_failed')]);
        $this->assertGuest();
    }

    public function test_la_page_de_connexion_par_visage_s_affiche(): void
    {
        $this->get(route('login.face'))
            ->assertOk()
            ->assertSee('Connexion par visage')
            ->assertSee('Activer la caméra');
    }

    public function test_une_connexion_par_visage_reussie_pose_les_cookies_jwt(): void
    {
        $user = $this->utilisateurEnrole();
        $this->fakeFacePlusPlus();

        $response = $this->tentative($user);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookie('access_token');
        $response->assertCookie('refresh_token');
        $this->assertAuthenticatedAs($user);
    }

    public function test_la_2fa_n_est_pas_contournee_par_le_visage(): void
    {
        $user = $this->utilisateurEnrole();
        $user->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->fakeFacePlusPlus();

        $response = $this->tentative($user);

        $response->assertRedirect(route('two-factor.challenge'));
        $response->assertCookieMissing('access_token');
        $response->assertCookieMissing('refresh_token');
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_un_email_inconnu_renvoie_le_message_generique_sans_appel_facepp(): void
    {
        Http::fake();

        $response = $this->post(route('login.face.store'), [
            'email' => 'inconnu@example.test',
            'image' => $this->imageBase64(),
        ]);

        $this->assertEchecGenerique($response);
        Http::assertNothingSent();
    }

    public function test_un_compte_sans_enrolement_renvoie_le_message_generique_sans_appel_facepp(): void
    {
        $user = User::factory()->create();
        Http::fake();

        $response = $this->tentative($user);

        $this->assertEchecGenerique($response);
        Http::assertNothingSent();
    }

    public function test_un_visage_absent_est_refuse(): void
    {
        $user = $this->utilisateurEnrole();
        $this->fakeFacePlusPlus(faceCount: 0);

        $this->assertEchecGenerique($this->tentative($user));
    }

    public function test_plusieurs_visages_sont_refuses(): void
    {
        $user = $this->utilisateurEnrole();
        $this->fakeFacePlusPlus(faceCount: 2);

        $this->assertEchecGenerique($this->tentative($user));
    }

    public function test_une_confiance_insuffisante_est_refusee(): void
    {
        $user = $this->utilisateurEnrole();
        $this->fakeFacePlusPlus(confidence: 60.0);

        $this->assertEchecGenerique($this->tentative($user));
    }

    public function test_le_seuil_far_de_facepp_prime_sur_le_plancher(): void
    {
        $user = $this->utilisateurEnrole();
        // 81 > plancher configuré (80) mais < seuil FAR (82) : refus attendu.
        $this->fakeFacePlusPlus(confidence: 81.0, farThreshold: 82.0);

        $this->assertEchecGenerique($this->tentative($user));
    }

    public function test_le_plancher_de_configuration_prime_sur_un_seuil_far_plus_bas(): void
    {
        config(['services.faceplusplus.threshold' => 90.0]);

        $user = $this->utilisateurEnrole();
        // 85 > seuil FAR (70) mais < plancher configuré (90) : refus attendu.
        $this->fakeFacePlusPlus(confidence: 85.0, farThreshold: 70.0);

        $this->assertEchecGenerique($this->tentative($user));
    }

    public function test_une_api_indisponible_est_refusee_generiquement(): void
    {
        $user = $this->utilisateurEnrole();
        Http::fake(['api-us.faceplusplus.test/*' => Http::response('boom', 503)]);

        $this->assertEchecGenerique($this->tentative($user));
    }

    public function test_une_reference_indechiffrable_est_refusee_sans_appel(): void
    {
        $user = $this->utilisateurEnrole();

        DB::table('users')->where('id', $user->id)->update([
            'face_reference_image' => 'pas-un-chiffre-valide',
        ]);

        Http::fake();

        $this->assertEchecGenerique($this->tentative($user));
        Http::assertNothingSent();
    }

    public function test_cinq_echecs_declenchent_le_blocage_du_limiteur_visage(): void
    {
        app()->setLocale('fr');

        $user = $this->utilisateurEnrole();
        $this->fakeFacePlusPlus(confidence: 10.0);

        for ($i = 0; $i < 5; $i++) {
            $this->tentative($user)->assertSessionHasErrors('email');
        }

        $response = $this->tentative($user);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', (string) session('errors')->first('email'));
    }

    public function test_un_echec_ne_flashe_pas_l_image_dans_la_session(): void
    {
        $user = $this->utilisateurEnrole();
        $this->fakeFacePlusPlus(confidence: 10.0);

        $this->tentative($user);

        $this->assertFalse(session()->hasOldInput('image'));
    }

    public function test_une_image_invalide_ne_flashe_pas_la_capture(): void
    {
        $user = $this->utilisateurEnrole();

        $this->from(route('login.face'))
            ->post(route('login.face.store'), [
                'email' => $user->email,
                'image' => 'pas-une-image',
            ])
            ->assertSessionHasErrors('image');

        $this->assertFalse(session()->hasOldInput('image'));
    }
}
