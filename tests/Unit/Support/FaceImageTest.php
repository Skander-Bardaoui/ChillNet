<?php

namespace Tests\Unit\Support;

use App\Support\FaceImage;
use Tests\TestCase;

class FaceImageTest extends TestCase
{
    private function jpeg(int $width, int $height, int $red = 120): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, 160, 200));

        ob_start();
        imagejpeg($image, null, 90);
        $binary = (string) ob_get_clean();

        imagedestroy($image);

        return $binary;
    }

    private function base64Jpeg(int $width, int $height, int $red = 120): string
    {
        return base64_encode($this->jpeg($width, $height, $red));
    }

    public function test_normalize_retire_l_entete_data_url_et_les_espaces(): void
    {
        $base64 = $this->base64Jpeg(200, 200);

        $this->assertSame($base64, FaceImage::normalize('  data:image/jpeg;base64, '.$base64."\n"));
        $this->assertSame($base64, FaceImage::normalize($base64));
    }

    public function test_decode_accepte_une_data_url_et_rejette_le_base64_invalide(): void
    {
        $base64 = $this->base64Jpeg(200, 200);

        $this->assertSame(base64_decode($base64, true), FaceImage::decode('data:image/png;base64,'.$base64));
        $this->assertNull(FaceImage::decode('%%%pas-du-base64%%%'));
        $this->assertNull(FaceImage::decode(''));
    }

    public function test_prepare_for_storage_reencode_en_jpeg_640x480_max(): void
    {
        $prepared = FaceImage::prepareForStorage($this->base64Jpeg(1200, 900));

        $this->assertNotNull($prepared);

        $binary = base64_decode($prepared, true);
        $dimensions = FaceImage::dimensions((string) $binary);

        $this->assertNotNull($dimensions);
        $this->assertSame('image/jpeg', $dimensions['mime']);
        $this->assertSame(640, $dimensions['width']);
        $this->assertSame(480, $dimensions['height']);
    }

    public function test_prepare_for_storage_ne_redimensionne_pas_une_petite_image(): void
    {
        $prepared = FaceImage::prepareForStorage($this->base64Jpeg(320, 240));

        $this->assertNotNull($prepared);

        $dimensions = FaceImage::dimensions((string) base64_decode($prepared, true));

        $this->assertSame(320, $dimensions['width']);
        $this->assertSame(240, $dimensions['height']);
    }

    public function test_prepare_for_storage_refuse_une_image_illisible(): void
    {
        $this->assertNull(FaceImage::prepareForStorage(base64_encode('pas une image')));
        $this->assertNull(FaceImage::prepareForStorage('%%%pas-du-base64%%%'));
    }
}
