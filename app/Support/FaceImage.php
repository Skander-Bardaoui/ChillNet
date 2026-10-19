<?php

namespace App\Support;

/**
 * Manipulation des images de visage échangées avec Face++.
 *
 * Les captures arrivent du navigateur sous forme de data URL (`data:image/jpeg;base64,…`)
 * ou de base64 brut. Tout est ré-encodé côté serveur avant stockage : l'image de
 * référence est ainsi réduite (640×480 max) et débarrassée de ses métadonnées
 * EXIF/GPS avant chiffrement en base.
 */
final class FaceImage
{
    private const MAX_WIDTH = 640;

    private const MAX_HEIGHT = 480;

    private const JPEG_QUALITY = 85;

    /**
     * Supprime l'en-tête éventuel d'une data URL et les espaces du base64.
     */
    public static function normalize(string $image): string
    {
        $image = trim($image);

        if (str_starts_with(strtolower($image), 'data:')) {
            $comma = strpos($image, ',');

            if ($comma !== false) {
                $image = substr($image, $comma + 1);
            }
        }

        return preg_replace('/\s+/', '', $image) ?? '';
    }

    /**
     * Décode le base64 (strict) en binaire, ou `null` si invalide.
     */
    public static function decode(string $image): ?string
    {
        $normalized = self::normalize($image);

        if ($normalized === '') {
            return null;
        }

        $binary = base64_decode($normalized, true);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * Dimensions et type MIME réels de l'image binaire.
     *
     * @return array{width: int, height: int, mime: string|null}|null
     */
    public static function dimensions(string $binary): ?array
    {
        $infos = @getimagesizefromstring($binary);

        if (! is_array($infos) || (int) $infos[0] < 1 || (int) $infos[1] < 1) {
            return null;
        }

        return [
            'width' => (int) $infos[0],
            'height' => (int) $infos[1],
            'mime' => is_string($infos['mime'] ?? null) ? $infos['mime'] : null,
        ];
    }

    /**
     * Ré-encode l'image en JPEG 640×480 maximum (qualité 85) et renvoie le
     * base64 canonique à stocker. `null` si l'image est illisible.
     */
    public static function prepareForStorage(string $image): ?string
    {
        $binary = self::decode($image);

        if ($binary === null) {
            return null;
        }

        $dimensions = self::dimensions($binary);

        if ($dimensions === null) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        try {
            $ratio = min(
                1.0,
                self::MAX_WIDTH / $dimensions['width'],
                self::MAX_HEIGHT / $dimensions['height'],
            );

            $width = max(1, (int) round($dimensions['width'] * $ratio));
            $height = max(1, (int) round($dimensions['height'] * $ratio));

            $canvas = imagecreatetruecolor($width, $height);

            if ($canvas === false) {
                return null;
            }

            // Fond blanc : les PNG transparents ne deviennent pas noirs en JPEG.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $dimensions['width'], $dimensions['height']);

            ob_start();
            imagejpeg($canvas, null, self::JPEG_QUALITY);
            $jpeg = ob_get_clean();

            imagedestroy($canvas);
        } finally {
            imagedestroy($source);
        }

        if (! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        return base64_encode($jpeg);
    }
}
