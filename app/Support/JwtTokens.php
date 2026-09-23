<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Émission et cycle de vie de la paire de jetons JWT.
 *
 * Les deux jetons sont signés avec le même secret mais se distinguent par leur
 * claim `token_type` :
 *
 *  - `access`  : jeton court (jwt.ttl), seul accepté par le guard `web` ;
 *  - `refresh` : jeton long (jwt.refresh_ttl, ou chillnet.remember_refresh_ttl
 *                si « Se souvenir de moi » était coché), utilisé uniquement pour
 *                régénérer une paire de jetons.
 *
 * Le refresh token est également porteur d'un claim `remember` afin que la
 * rotation conserve la durée de vie choisie à la connexion.
 */
final class JwtTokens
{
    public const TYPE_ACCESS = 'access';

    public const TYPE_REFRESH = 'refresh';

    /**
     * Émet une nouvelle paire de jetons pour un utilisateur.
     *
     * @return array{access_token: string, refresh_token: string, refresh_ttl: int}
     */
    public static function forUser(User $user, bool $remember = false): array
    {
        $refreshTtl = AuthCookie::refreshTtl($remember);

        $accessToken = Auth::guard('web')
            ->claims(['token_type' => self::TYPE_ACCESS])
            ->login($user);

        JWTAuth::factory()->setTTL($refreshTtl);
        $refreshToken = JWTAuth::claims([
            'token_type' => self::TYPE_REFRESH,
            'remember' => $remember,
        ])->fromUser($user);

        // On rétablit la durée de vie courte pour les jetons d'accès suivants.
        JWTAuth::factory()->setTTL(AuthCookie::accessTtl());

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'refresh_ttl' => $refreshTtl,
        ];
    }

    /**
     * L'access token du cookie est-il encore exploitable par le guard ?
     */
    public static function isUsableAccessToken(string $token): bool
    {
        try {
            $payload = JWTAuth::setToken($token)->check(true);

            return $payload !== false && $payload->get('token_type') !== self::TYPE_REFRESH;
        } catch (JWTException) {
            return false;
        }
    }

    /**
     * Rotation : valide un refresh token, le révoque, puis émet une nouvelle paire.
     *
     * @return array{access_token: string, refresh_token: string, refresh_ttl: int}|null
     */
    public static function rotate(string $refreshToken): ?array
    {
        try {
            $payload = JWTAuth::setToken($refreshToken)->check(true);

            if ($payload === false || $payload->get('token_type') !== self::TYPE_REFRESH) {
                return null;
            }

            $user = JWTAuth::authenticate();

            if (! $user instanceof User) {
                return null;
            }

            // Anti-rejeu : un refresh token ne sert qu'une fois.
            JWTAuth::invalidate();

            return self::forUser($user, (bool) $payload->get('remember', false));
        } catch (JWTException) {
            return null;
        }
    }

    /**
     * Révoque des jetons (déconnexion). Les jetons invalides sont ignorés.
     */
    public static function invalidate(?string ...$tokens): void
    {
        foreach ($tokens as $token) {
            if (! $token) {
                continue;
            }

            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (\Throwable) {
                // jeton déjà expiré ou révoqué : rien à faire
            }
        }
    }
}
