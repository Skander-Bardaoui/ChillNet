<?php

namespace App\Support;

/**
 * Calculs géographiques partagés (périmètre des refuges de l'espace habitant).
 */
final class Geo
{
    /**
     * Rayon (en mètres) du périmètre « refuges & zones d'ombre ».
     */
    public const RAYON_REFUGES_M = 800;

    /**
     * Distance orthodromique (Haversine) entre deux points, en kilomètres.
     * Même formule que le calcul JavaScript de `front/coupures-create.blade.php`.
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rayon = 6371.0; // rayon terrestre moyen, en km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $rayon * asin(min(1.0, sqrt($a)));
    }
}
