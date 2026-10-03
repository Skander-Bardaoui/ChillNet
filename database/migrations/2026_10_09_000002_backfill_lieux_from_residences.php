<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill : chaque habitant existant rattaché à une résidence reçoit un
     * lieu « Domicile » construit depuis sa résidence (coordonnées de la
     * résidence, sinon centroïde de son quartier). Idempotent — rejouable
     * sans créer de doublon.
     */
    public function up(): void
    {
        $habitants = DB::table('users')
            ->join('residences', 'residences.id', '=', 'users.residence_id')
            ->leftJoin('quartiers', 'quartiers.id', '=', 'residences.quartier_id')
            ->where('users.role', 'habitant')
            ->select([
                'users.id as user_id',
                'residences.quartier_id as quartier_id',
                'residences.latitude as residence_lat',
                'residences.longitude as residence_lng',
                'quartiers.latitude as quartier_lat',
                'quartiers.longitude as quartier_lng',
            ])
            ->get();

        foreach ($habitants as $habitent) {
            $existe = DB::table('lieux')
                ->where('user_id', $habitent->user_id)
                ->where('nom', 'Domicile')
                ->exists();

            if ($existe) {
                continue;
            }

            $latitude = $habitent->residence_lat ?? $habitent->quartier_lat;
            $longitude = $habitent->residence_lng ?? $habitent->quartier_lng;

            DB::table('lieux')->insert([
                'user_id' => $habitent->user_id,
                'nom' => 'Domicile',
                'type' => 'domicile',
                'adresse' => null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'quartier_id' => $habitent->quartier_id,
                'est_principal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Irréversible : on ne supprime pas les lieux issus du backfill (ils
     * deviennent des données utilisateur légitimes).
     */
    public function down(): void
    {
        //
    }
};
