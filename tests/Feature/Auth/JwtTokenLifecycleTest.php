<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\JwtTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JwtTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_both_auth_cookies_and_redirects_to_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookie('access_token');
        $response->assertCookie('refresh_token');
        $this->assertAuthenticated();
    }

    public function test_the_default_refresh_cookie_lasts_fourteen_days(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertEqualsWithDelta(
            now()->addMinutes((int) config('jwt.refresh_ttl'))->getTimestamp(),
            // Cookies JWT exclus du chiffrement Laravel (voir bootstrap/app.php) :
            // lecture brute, sans tentative de déchiffrement.
            $response->getCookie('refresh_token', false)->getExpiresTime(),
            10,
        );
    }

    public function test_remember_me_lengthens_the_refresh_cookie_to_thirty_days(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertEqualsWithDelta(
            now()->addMinutes((int) config('chillnet.remember_refresh_ttl'))->getTimestamp(),
            $response->getCookie('refresh_token', false)->getExpiresTime(),
            10,
        );
    }

    public function test_a_refresh_token_is_rotated_and_cannot_be_replayed_after_the_grace_period(): void
    {
        $user = User::factory()->create();

        $initial = JwtTokens::forUser($user);
        $rotated = JwtTokens::rotate($initial['refresh_token']);

        $this->assertNotNull($rotated);
        $this->assertNotSame($initial['refresh_token'], $rotated['refresh_token']);

        // Pendant la fenêtre de tolérance, une requête concurrente reste tolérée…
        $this->assertNotNull(JwtTokens::rotate($initial['refresh_token']));

        // …mais une fois la tolérance écoulée, le jeton rejoué est refusé.
        $this->travel(31)->seconds();

        $this->assertNull(JwtTokens::rotate($initial['refresh_token']));
    }

    public function test_a_refresh_token_keeps_its_remember_claim_after_rotation(): void
    {
        $user = User::factory()->create();

        $initial = JwtTokens::forUser($user, remember: true);
        $rotated = JwtTokens::rotate($initial['refresh_token']);

        $this->assertNotNull($rotated);
        $this->assertSame((int) config('chillnet.remember_refresh_ttl'), $rotated['refresh_ttl']);
    }

    public function test_an_access_token_cannot_be_used_to_rotate(): void
    {
        $user = User::factory()->create();

        $tokens = JwtTokens::forUser($user);

        $this->assertNull(JwtTokens::rotate($tokens['access_token']));
    }
}
