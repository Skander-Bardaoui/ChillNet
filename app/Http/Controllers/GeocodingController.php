<?php

namespace App\Http\Controllers;

use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Géocodage inverse (coordonnées → adresse), partagé front office et back
 * office : « Mes lieux » et le formulaire de point de fraîcheur l'utilisent
 * pour pré-remplir automatiquement le champ adresse des cartes Leaflet.
 *
 * Ouvert à tout utilisateur connecté (le formulaire point est commun aux
 * habitants et aux gestionnaires). Ne renvoie jamais d'erreur dure : `adresse`
 * vaut `null` si le service est indisponible, le champ restant saisissable.
 */
class GeocodingController extends Controller
{
    public function __construct(private GeocodingService $geocodage) {}

    public function reverse(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'adresse' => $this->geocodage->adresseInverse(
                (float) $donnees['latitude'],
                (float) $donnees['longitude'],
            ),
        ]);
    }
}
