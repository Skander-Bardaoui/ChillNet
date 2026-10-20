<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\FaceImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProfileFaceEnrollmentTest extends TestCase
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
            'services.faceplusplus.image_max_bytes' => 2097152,
            'chillnet.face_consent_version' => '2026-10-01',
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

    private function fakeDetect(int $faceCount = 1): void
    {
        Http::fake([
            'api-us.faceplusplus.test/facepp/v3/detect' => Http::response([
                'faces' => array_fill(0, $faceCount, ['face_token' => 'token']),
            ]),
        ]);
    }

    private function enrolement(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->patch(route('profile.face.update'), array_merge([
            'image' => $this->imageBase64(),
            'consent' => '1',
            'password' => 'password',
        ], $overrides));
    }

    public function test_un_invite_ne_peut_pas_enroler_de_visage(): void
    {
        $this->patch(route('profile.face.update'))->assertRedirect(route('login'));
    }

    public function test_l_enrolement_enregistre_une_image_chiffree_et_les_traces_de_consentement(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();

        $response = $this->enrolement($user);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'face-enrolled');

        $user->refresh();

        $this->assertTrue($user->hasFaceEnrollment());
        $this->assertNotNull($user->face_enrolled_at);
        $this->assertNotNull($user->face_consent_at);
        $this->assertSame('2026-10-01', $user->face_consent_version);

        $raw = DB::table('users')->where('id', $user->id)->value('face_reference_image');

        $this->assertIsString($raw);
        $this->assertNotSame($this->imageBase64(), $raw);
        $this->assertStringNotContainsString('image/jpeg', $raw);

        $decrypted = (string) $user->face_reference_image;
        $dimensions = FaceImage::dimensions((string) base64_decode($decrypted, true));

        $this->assertSame('image/jpeg', $dimensions['mime']);
        $this->assertSame(320, $dimensions['width']);
        $this->assertSame(240, $dimensions['height']);
    }

    public function test_l_image_de_reference_est_masquee_dans_la_serialisation(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();
        $this->enrolement($user);

        $this->assertArrayNotHasKey('face_reference_image', $user->refresh()->toArray());
    }

    public function test_le_consentement_est_obligatoire(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();

        $response = $this->enrolement($user, ['consent' => null]);

        $response->assertSessionHasErrors(['consent'], null, 'faceEnrollment');
        $this->assertNull($user->refresh()->face_reference_image);
    }

    public function test_le_mot_de_passe_est_obligatoire_et_verifie(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();

        $this->enrolement($user, ['password' => null])
            ->assertSessionHasErrors(['password'], null, 'faceEnrollment');

        $this->enrolement($user, ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors(['password'], null, 'faceEnrollment');

        $this->assertNull($user->refresh()->face_reference_image);
    }

    public function test_un_service_indisponible_empeche_l_enrolement(): void
    {
        $user = User::factory()->create();
        Http::fake(['api-us.faceplusplus.test/*' => Http::response('boom', 503)]);

        $response = $this->enrolement($user);

        $response->assertSessionHasErrors(['image'], null, 'faceEnrollment');
        $this->assertNull($user->refresh()->face_reference_image);
    }

    public function test_une_photo_sans_visage_ou_avec_plusieurs_visages_est_refusee(): void
    {
        $user = User::factory()->create();

        $this->fakeDetect(faceCount: 0);
        $this->enrolement($user)->assertSessionHasErrors(['image'], null, 'faceEnrollment');
        $this->assertNull($user->refresh()->face_reference_image);

        Http::fake();
        $this->fakeDetect(faceCount: 2);
        $this->enrolement($user)->assertSessionHasErrors(['image'], null, 'faceEnrollment');
        $this->assertNull($user->refresh()->face_reference_image);
    }

    public function test_un_nouvel_enrolement_remplace_l_image_existante(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();

        $this->enrolement($user);
        $premiere = (string) $user->refresh()->face_reference_image;

        $this->enrolement($user, ['image' => $this->imageBase64(320, 240, 200)]);
        $seconde = (string) $user->refresh()->face_reference_image;

        $this->assertNotSame($premiere, $seconde);
    }

    public function test_la_suppression_avec_un_mauvais_mot_de_passe_est_refusee(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();
        $this->enrolement($user);

        $this->actingAs($user)
            ->delete(route('profile.face.destroy'), ['password' => 'mauvais'])
            ->assertSessionHasErrors(['password'], null, 'faceEnrollment');

        $this->assertTrue($user->refresh()->hasFaceEnrollment());
    }

    public function test_la_suppression_efface_l_image_le_consentement_et_l_activation(): void
    {
        $user = User::factory()->create();
        $this->fakeDetect();
        $this->enrolement($user);

        $response = $this->actingAs($user)
            ->delete(route('profile.face.destroy'), ['password' => 'password']);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'face-removed');

        $user->refresh();

        $this->assertFalse($user->hasFaceEnrollment());
        $this->assertNull($user->face_enrolled_at);
        $this->assertNull($user->face_consent_at);
        $this->assertNull($user->face_consent_version);
    }

    public function test_les_deux_vues_de_profil_affichent_la_carte_visage(): void
    {
        $habitant = User::factory()->create();

        $this->actingAs($habitant)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Connexion par visage (Face ID)');

        $gestionnaire = User::factory()->create(['role' => Role::Gestionnaire]);

        $this->actingAs($gestionnaire)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Connexion par visage (Face ID)');
    }
}
