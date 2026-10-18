<?php

namespace App\Support;

/**
 * Implémentation TOTP (RFC 6238) autonome — HMAC-SHA1, période 30 s, 6 chiffres.
 *
 * Compatible Google Authenticator, Authy, FreeOTP, etc. Aucune dépendance
 * externe (pas de Fortify/Jetstream) afin de rester aligné sur l'authentification
 * JWT maison du projet.
 */
final class Totp
{
    /** Alphabet Base32 (sans I, L, O, 0 — norme RFC 4648). */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Période d'un code, en secondes. */
    private const PERIOD = 30;

    /** Nombre de chiffres du code. */
    private const DIGITS = 6;

    /**
     * Génère un nouveau secret aléatoire encodé en Base32 (160 bits par défaut).
     */
    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Code TOTP attendu pour un secret à un instant donné.
     */
    public static function codeAt(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);

        $key = self::base32Decode($secret);
        $binCounter = pack('J', $counter); // 64 bits, gros-boutiste

        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[19]) & 0x0f;
        $part = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $part, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie un code saisi par l'utilisateur, en tolérant `$window` pas de temps
     * de décalage de part et d'autre (dérive d'horloge). Comparaison en temps constant.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }

        $now = time();

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::codeAt($secret, $now + ($i * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URI `otpauth://` à encoder en QR code pour l'application d'authentification.
     */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$account);

        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$params;
    }

    /**
     * Décodage Base32 → binaire brut.
     */
    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        $bits = '';
        $binary = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);

            if ($index === false) {
                continue;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}