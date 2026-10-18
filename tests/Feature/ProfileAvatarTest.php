<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1×1 valide (évite la dépendance GD de `fake()->image()`). */
    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    public function test_la_photo_de_profil_peut_etre_importee(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $fichier = UploadedFile::fake()->createWithContent(
            'avatar.png',
            base64_decode(self::PNG_1PX),
        );

        $this->actingAs($user)
            ->patch('/profile/avatar', ['avatar' => $fichier])
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_le_fichier_doit_etre_une_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $fichier = UploadedFile::fake()->createWithContent('note.txt', 'pas une image');

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile/avatar', ['avatar' => $fichier])
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('avatarUpdate', 'avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_remplacer_la_photo_supprime_l_ancien_fichier(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG_1PX)),
        ]);

        $ancien = $user->refresh()->avatar_path;

        $this->actingAs($user)->patch('/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('b.png', base64_decode(self::PNG_1PX)),
        ]);

        $nouveau = $user->refresh()->avatar_path;

        $this->assertNotSame($ancien, $nouveau);
        Storage::disk('public')->assertMissing($ancien);
        Storage::disk('public')->assertExists($nouveau);
    }

    public function test_la_photo_de_profil_peut_etre_supprimee(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Storage::disk('public')->put('avatars/ancienne.png', 'contenu');
        $user->forceFill(['avatar_path' => 'avatars/ancienne.png'])->save();

        $this->actingAs($user)
            ->delete('/profile/avatar')
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing('avatars/ancienne.png');
    }

    public function test_la_page_profil_affiche_la_photo_et_la_section_2fa(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Photo de profil')
            ->assertSee('Double authentification (2FA)')
            ->assertSee('Activer la double authentification');
    }

    public function test_l_url_de_l_avatar_suit_l_hote_et_le_port_courants(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => 'avatars/photo.png'])->save();

        // L'ancienne implémentation (`Storage::url`) renvoyait APP_URL (port 80),
        // ce qui cassait l'affichage quand l'app tourne sur un autre port.
        $this->actingAs($user)
            ->get('http://127.0.0.1:8000/profile')
            ->assertOk()
            ->assertSee('http://127.0.0.1:8000/storage/avatars/photo.png', false);
    }
}