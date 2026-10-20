<?php

namespace App\Rules;

use App\Support\FaceImage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valide une capture de visage transmise en base64 / data URL :
 *  - base64 décodable,
 *  - taille binaire ≤ FACEPP_IMAGE_MAX_BYTES (2 Mo par défaut),
 *  - JPEG ou PNG réellement détecté par le serveur,
 *  - côtés entre 160 et 2048 px (qualité suffisante pour Face++).
 */
class FaceCaptureImage implements ValidationRule
{
    private const MIN_EDGE = 160;

    private const MAX_EDGE = 2048;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('La photo du visage est manquante.');

            return;
        }

        $binary = FaceImage::decode($value);

        if ($binary === null) {
            $fail('La photo du visage est invalide ou illisible.');

            return;
        }

        $maxBytes = (int) config('services.faceplusplus.image_max_bytes', 2097152);

        if (strlen($binary) > $maxBytes) {
            $fail('La photo du visage ne doit pas dépasser '.(int) round($maxBytes / 1048576).' Mo.');

            return;
        }

        $dimensions = FaceImage::dimensions($binary);

        if ($dimensions === null) {
            $fail('La photo du visage est invalide ou illisible.');

            return;
        }

        if (! in_array($dimensions['mime'], ['image/jpeg', 'image/png'], true)) {
            $fail('La photo du visage doit être au format JPEG ou PNG.');

            return;
        }

        if (
            $dimensions['width'] < self::MIN_EDGE
            || $dimensions['height'] < self::MIN_EDGE
            || $dimensions['width'] > self::MAX_EDGE
            || $dimensions['height'] > self::MAX_EDGE
        ) {
            $fail('La photo du visage doit mesurer entre '.self::MIN_EDGE.' et '.self::MAX_EDGE.' pixels de côté.');
        }
    }
}
