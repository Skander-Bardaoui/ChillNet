<?php

namespace Tests\Unit;

use App\Enums\NiveauAlerte;
use App\Models\Alerte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlerteGeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_coordinates_requires_both_latitude_and_longitude(): void
    {
        $this->assertFalse((new Alerte)->hasCoordinates());
        $this->assertFalse((new Alerte(['latitude' => 36.8]))->hasCoordinates());
        $this->assertTrue((new Alerte(['latitude' => 36.8, 'longitude' => 10.18]))->hasCoordinates());
    }

    public function test_default_radius_is_one_kilometre(): void
    {
        $this->assertSame(Alerte::RAYON_DEFAUT_M, (new Alerte)->rayonMetres());
        $this->assertSame(2000, (new Alerte(['rayon_metres' => 2000]))->rayonMetres());
    }

    public function test_couvre_point_within_and_outside_the_circle(): void
    {
        $alerte = new Alerte(['latitude' => 36.8008, 'longitude' => 10.1800, 'rayon_metres' => 1000]);

        // ~620 m à l'est → dans le cercle de 1 km.
        $this->assertTrue($alerte->couvrePoint(36.8008, 10.1870));

        // ~1,9 km au nord → hors du cercle.
        $this->assertFalse($alerte->couvrePoint(36.8180, 10.1800));
    }

    public function test_an_alerte_without_coordinates_covers_no_point(): void
    {
        $this->assertFalse((new Alerte)->couvrePoint(36.8008, 10.1800));
    }

    public function test_chevauche_geo_detects_overlapping_circles_in_time(): void
    {
        Alerte::factory()->geo(36.8008, 10.1800, 1000)->create([
            'niveau' => NiveauAlerte::Orange->value,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(5),
        ]);

        $debut = now()->format('Y-m-d H:i:s');
        $fin = now()->addHours(3)->format('Y-m-d H:i:s');

        // Même centre, temps qui se chevauche → doublon.
        $this->assertTrue(Alerte::chevaucheGeo(36.8008, 10.1800, 1000, $debut, $fin, null, NiveauAlerte::Orange));

        // Cercle lointain (> 2 km) → pas de doublon.
        $this->assertFalse(Alerte::chevaucheGeo(36.9000, 10.3000, 1000, $debut, $fin, null, NiveauAlerte::Orange));

        // Créneau disjoint → pas de doublon.
        $this->assertFalse(Alerte::chevaucheGeo(
            36.8008,
            10.1800,
            1000,
            now()->addHours(10)->format('Y-m-d H:i:s'),
            now()->addHours(12)->format('Y-m-d H:i:s'),
            null,
            NiveauAlerte::Orange,
        ));
    }
}
