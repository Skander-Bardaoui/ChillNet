<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crée un utilisateur dont la 2FA est active.
     *
     * @param  array<int, string>|null  $codes
     * @return array{0: User, 1: string}
     */
    private function utilisateurAvec2fa(?array $codes = null): array
    {
        $secret = Totp::generateSecret();
        $codes ??= ['AAAAA-BBBBB', 'CCCCC-DDDDD'];

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    public function test_la_2fa_peut_etre_activee(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('two-factor.enable'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
    }

    public function test_la_2fa_est_confirmee_avec_un_code_valide(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.enable'));

        $secret = $user->refresh()->two_factor_secret;

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => Totp::codeAt($secret)])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->refresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_la_confirmation_refuse_un_code_invalide(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.enable'));

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => 'abc'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_la_2fa_peut_etre_desactivee(): void
    {
        [$user] = $this->utilisateurAvec2fa();

        $this->actingAs($user)
            ->delete(route('two-factor.disable'))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_les_codes_de_recuperation_peuvent_etre_regenerees(): void
    {
        [$user] = $this->utilisateurAvec2fa(['AAAAA-BBBBB']);

        $this->actingAs($user)->post(route('two-factor.regenerate-recovery-codes'));

        $codes = $user->refresh()->recoveryCodes();

        $this->assertCount(8, $codes);
        $this->assertNotContains('AAAAA-BBBBB', $codes);
    }

    public function test_les_codes_de_recuperation_sont_renvoyes_en_json(): void
    {
        [$user] = $this->utilisateurAvec2fa();

        $this->actingAs($user)
            ->get(route('two-factor.recovery-codes'))
            ->assertOk()
            ->assertJsonPath('recovery_codes', $user->recoveryCodes());
    }

    public function test_la_connexion_redirige_vers_le_challenge_quand_la_2fa_est_active(): void
    {
        [$user] = $this->utilisateurAvec2fa();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_le_challenge_connecte_l_utilisateur_avec_un_code_valide(): void
    {
        [$user, $secret] = $this->utilisateurAvec2fa();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post('/two-factor-challenge', ['code' => Totp::codeAt($secret)])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('login.id'));
    }

    public function test_le_challenge_accepte_un_code_de_recuperation_et_le_consomme(): void
    {
        [$user] = $this->utilisateurAvec2fa(['AAAAA-BBBBB', 'CCCCC-DDDDD']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaa-bbbbb'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['CCCCC-DDDDD'], $user->refresh()->recoveryCodes());
    }

    public function test_le_challenge_refuse_un_code_invalide(): void
    {
        [$user] = $this->utilisateurAvec2fa();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post('/two-factor-challenge', ['code' => 'abc'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_le_challenge_redirige_vers_login_sans_connexion_en_attente(): void
    {
        $this->get('/two-factor-challenge')
            ->assertRedirect(route('login'));
    }

    public function test_la_page_du_challenge_s_affiche_avec_une_connexion_en_attente(): void
    {
        [$user] = $this->utilisateurAvec2fa();

        $this->withSession(['login.id' => $user->id])
            ->get('/two-factor-challenge')
            ->assertOk()
            ->assertSee('Vérification en deux étapes');
    }
}