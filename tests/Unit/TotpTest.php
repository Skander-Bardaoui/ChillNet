<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /**
     * Vecteurs de test RFC 6238 (SHA-1), tronqués à 6 chiffres.
     *
     * @return array<string, array{int, string}>
     */
    public static function vecteursRfc6238(): array
    {
        return [
            'T=59' => [59, '287082'],
            'T=1111111109' => [1111111109, '081804'],
            'T=1111111111' => [1111111111, '050471'],
            'T=1234567890' => [1234567890, '005924'],
            'T=2000000000' => [2000000000, '279037'],
            'T=20000000000' => [20000000000, '353130'],
        ];
    }

    #[DataProvider('vecteursRfc6238')]
    public function test_code_totp_conforme_au_rfc_6238(int $timestamp, string $attendu): void
    {
        // Secret RFC : "12345678901234567890" encodé en Base32.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame($attendu, Totp::codeAt($secret, $timestamp));
    }

    public function test_verify_accepte_le_code_courant_et_rejette_un_mauvais_code(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::codeAt($secret);

        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, 'abc'));
        $this->assertFalse(Totp::verify($secret, '12345'));
    }

    public function test_le_secret_genere_est_en_base32_et_a_la_bonne_taille(): void
    {
        $secret = Totp::generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }
}