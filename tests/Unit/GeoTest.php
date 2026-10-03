<?php

namespace Tests\Unit;

use App\Support\Geo;
use Tests\TestCase;

class GeoTest extends TestCase
{
    public function test_distance_is_zero_for_the_same_point(): void
    {
        $this->assertSame(0.0, Geo::distanceKm(36.8008, 10.1800, 36.8008, 10.1800));
    }

    public function test_distance_matches_a_known_pair_within_tolerance(): void
    {
        // Paris → Londres ≈ 343,5 km (référence Haversine).
        $distance = Geo::distanceKm(48.8566, 2.3522, 51.5074, -0.1278);

        $this->assertEqualsWithDelta(343.5, $distance, 1.0);
    }

    public function test_distance_is_symmetric(): void
    {
        $aller = Geo::distanceKm(36.8008, 10.1800, 36.8035, 10.1760);
        $retour = Geo::distanceKm(36.8035, 10.1760, 36.8008, 10.1800);

        $this->assertEqualsWithDelta($aller, $retour, 0.0001);
    }

    public function test_a_nearby_point_stays_under_the_eight_hundred_metre_radius(): void
    {
        // ~470 m du foyer (point de fraîcheur seedé dans le périmètre).
        $distanceM = Geo::distanceKm(36.8008, 10.1800, 36.8035, 10.1760) * 1000;

        $this->assertLessThan(Geo::RAYON_REFUGES_M, $distanceM);
    }

    public function test_the_refuge_radius_constant_is_eight_hundred_metres(): void
    {
        $this->assertSame(800, Geo::RAYON_REFUGES_M);
    }
}
