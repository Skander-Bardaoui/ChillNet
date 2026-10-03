<?php

namespace Tests\Unit;

use App\Enums\LieuType;
use App\Enums\Role;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LieuTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(string $nom, float $lat, float $lng): Quartier
    {
        return Quartier::create([
            'nom' => $nom,
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    public function test_lieu_type_exposes_labels_icons_and_values(): void
    {
        $this->assertSame('Domicile', LieuType::Domicile->label());
        $this->assertSame('Travail', LieuType::Travail->label());
        $this->assertSame('home', LieuType::Domicile->icone());
        $this->assertSame('work', LieuType::Travail->icone());
        $this->assertSame(['domicile', 'travail', 'autre'], LieuType::valeurs());
    }

    public function test_plus_proche_returns_the_nearest_geolocated_quartier(): void
    {
        $lointain = $this->quartier('Les Berges du Lac', 36.8325, 10.2800);
        $proche = $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $trouve = Quartier::plusProche(36.8035, 10.1760); // ~0,5 km de Centre-Ville

        $this->assertNotNull($trouve);
        $this->assertSame($proche->id, $trouve->id);
        $this->assertNotSame($lointain->id, $trouve->id);
    }

    public function test_plus_proche_ignores_quartiers_without_coordinates(): void
    {
        Quartier::create(['nom' => 'Sans coordonnées', 'ville' => 'Tunis', 'code_postal' => '1000']);
        $geolocalise = $this->quartier('Centre-Ville', 36.8008, 10.1800);

        $trouve = Quartier::plusProche(36.8008, 10.1800);

        $this->assertNotNull($trouve);
        $this->assertSame($geolocalise->id, $trouve->id);
    }

    public function test_plus_proche_returns_null_without_coordinates_or_geolocated_quartiers(): void
    {
        $this->assertNull(Quartier::plusProche(null, null));
        $this->assertNull(Quartier::plusProche(36.8008, 10.1800)); // aucun quartier géolocalisé
    }

    public function test_a_lieu_auto_assigns_the_nearest_quartier_from_its_point(): void
    {
        $proche = $this->quartier('Centre-Ville', 36.8008, 10.1800);
        $this->quartier('Les Berges du Lac', 36.8325, 10.2800);

        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $lieu = $habitant->lieux()->create([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'latitude' => 36.8035,
            'longitude' => 10.1760,
        ]);

        $this->assertSame($proche->id, $lieu->fresh()->quartier_id);
    }

    public function test_lieu_principal_prefers_the_flagged_lieu_then_the_first(): void
    {
        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $premier = $habitant->lieux()->create([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'est_principal' => false,
        ]);

        $second = $habitant->lieux()->create([
            'nom' => 'Travail',
            'type' => LieuType::Travail,
            'est_principal' => true,
        ]);

        // Le drapeau est prioritaire, même s'il ne s'agit pas du premier lieu.
        $this->assertSame($second->id, $habitant->lieuPrincipal()->id);

        // Sans aucun principal, on retombe sur le premier lieu créé.
        $second->update(['est_principal' => false]);
        $this->assertSame($premier->id, $habitant->lieuPrincipal()->id);
    }

    public function test_lieu_principal_returns_null_when_the_household_has_no_lieu(): void
    {
        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $this->assertNull($habitant->lieuPrincipal());
    }

    public function test_a_lieu_scopes_its_owner(): void
    {
        $habitant = User::factory()->create(['role' => Role::Habitant]);
        $autre = User::factory()->create(['role' => Role::Habitant]);

        $habitant->lieux()->create(['nom' => 'Domicile', 'type' => LieuType::Domicile]);

        $this->assertSame(1, $habitant->lieux()->count());
        $this->assertSame(0, $autre->lieux()->count());
        $this->assertInstanceOf(Lieu::class, $habitant->lieux()->first());
    }
}
