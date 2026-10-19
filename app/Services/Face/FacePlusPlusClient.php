<?php

namespace App\Services\Face;

use App\Support\FaceImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP vers l'API Face++ (Megvii), utilisé uniquement côté serveur.
 *
 * Contrat strict : aucune méthode ne lève d'exception et aucune ne bloque
 * au-delà du timeout configuré. En cas de timeout, connexion refusée, statut
 * non-2xx, `error_message` renvoyé avec un HTTP 200 ou JSON invalide, la
 * méthode renvoie `null` et l'appelant applique sa politique d'échec.
 *
 * Les identifiants (api_key/api_secret) et les images ne sont JAMAIS journalisés.
 */
class FacePlusPlusClient
{
    /**
     * L'intégration est-elle activée et configurée ?
     */
    public function enabled(): bool
    {
        return (bool) config('services.faceplusplus.enabled', true)
            && trim((string) config('services.faceplusplus.key', '')) !== ''
            && trim((string) config('services.faceplusplus.secret', '')) !== '';
    }

    /**
     * POST /facepp/v3/detect — détecte les visages d'une image.
     *
     * @return array<int, array<string, mixed>>|null liste des visages (vide si aucun), `null` en cas d'échec
     */
    public function detect(string $imageBase64): ?array
    {
        $response = $this->post('/facepp/v3/detect', [
            'image_base64' => FaceImage::normalize($imageBase64),
        ]);

        if ($response === null) {
            return null;
        }

        $faces = $response['faces'] ?? [];

        return is_array($faces) ? $faces : null;
    }

    /**
     * POST /facepp/v3/compare — similarité entre deux visages.
     *
     * @return array{confidence: float, thresholds: array<string, mixed>}|null
     */
    public function compare(string $imageBase64_1, string $imageBase64_2): ?array
    {
        $response = $this->post('/facepp/v3/compare', [
            'image_base64_1' => FaceImage::normalize($imageBase64_1),
            'image_base64_2' => FaceImage::normalize($imageBase64_2),
        ]);

        if ($response === null) {
            return null;
        }

        $confidence = $response['confidence'] ?? null;

        if (! is_numeric($confidence)) {
            Log::warning('Reconnaissance faciale : confiance absente de la réponse Face++');

            return null;
        }

        $thresholds = is_array($response['thresholds'] ?? null) ? $response['thresholds'] : [];

        return [
            'confidence' => (float) $confidence,
            'thresholds' => $thresholds,
        ];
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, mixed>|null
     */
    private function post(string $path, array $payload): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout((float) config('services.faceplusplus.timeout', 8.0))
                ->acceptJson()
                ->post($this->url($path), array_merge($payload, [
                    'api_key' => (string) config('services.faceplusplus.key', ''),
                    'api_secret' => (string) config('services.faceplusplus.secret', ''),
                ]));
        } catch (\Throwable $e) {
            Log::warning('Reconnaissance faciale : appel Face++ impossible', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Reconnaissance faciale : statut Face++ inattendu', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            Log::warning('Reconnaissance faciale : réponse JSON invalide', ['path' => $path]);

            return null;
        }

        $errorMessage = $json['error_message'] ?? null;

        if (is_string($errorMessage) && trim($errorMessage) !== '') {
            Log::warning('Reconnaissance faciale : erreur renvoyée par Face++', [
                'path' => $path,
                'error' => $errorMessage,
            ]);

            return null;
        }

        return $json;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.faceplusplus.base_url', 'https://api-us.faceplusplus.com'), '/').$path;
    }
}
