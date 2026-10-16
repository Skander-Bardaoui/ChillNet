<?php

namespace Tests\Feature;

use App\Enums\NiveauAlerte;
use App\Enums\Role;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpAlertesRender2Test extends TestCase
{
    use RefreshDatabase;

    public function test_dump_alertes_pages(): void
    {
        config(['services.groq.key' => null, 'services.weather.key' => null]);

        $quartier = Quartier::create([
            'nom' => 'Centre-Ville', 'ville' => 'Tunis', 'code_postal' => '1000',
            'latitude' => 36.8008, 'longitude' => 10.1800,
        ]);
        $residence = Residence::create(['nom' => 'R', 'adresse' => '1', 'quartier_id' => $quartier->id]);
        $gest = User::factory()->create(['role' => Role::Gestionnaire, 'residence_id' => $residence->id]);
        $admin = User::factory()->create(['role' => Role::Admin]);

        // Alerte géolocalisée AVEC quartier, non validée (brouillon) → tous les badges.
        $geo = Alerte::factory()->geo(36.8008, 10.1800, 1200)->create([
            'titre' => 'Alerte geo centre', 'niveau' => NiveauAlerte::Rouge->value, 'validee' => false,
            'temperature_actuelle' => 41.5, 'temperature_ressentie' => 45.0, 'humidite' => 60,
            'source_meteo' => 'weatherapi',
        ]);
        $geo->quartiers()->sync([$quartier->id]);

        // Alerte sans coordonnées, non validée, avec quartiers multiples.
        $multi = Alerte::factory()->create(['titre' => 'Alerte multi', 'niveau' => NiveauAlerte::Orange->value, 'validee' => false]);
        $multi->quartiers()->sync([$quartier->id]);

        $report = [];

        // Admin voit tout.
        foreach ([
            'admin_index_filter' => route('back.alertes.index', ['quartier_id' => $quartier->id, 'niveau' => 'rouge', 'tri' => 'zone']),
            'admin_show_geo' => route('back.alertes.show', $geo->id),
            'admin_show_multi' => route('back.alertes.show', $multi->id),
            'admin_edit_geo' => route('back.alertes.edit', $geo->id),
        ] as $nom => $url) {
            $res = $this->actingAs($admin)->get($url);
            $report[$nom] = $res->getStatusCode();
            file_put_contents(base_path("storage/tmp2_{$nom}.html"), $res->getContent());
        }

        // Gestionnaire sur SA zone.
        foreach ([
            'gest_show_own' => route('back.alertes.show', $geo->id),
            'gest_edit_own' => route('back.alertes.edit', $geo->id),
            'gest_index' => route('back.alertes.index'),
        ] as $nom => $url) {
            $res = $this->actingAs($gest)->get($url);
            $report[$nom] = $res->getStatusCode();
            file_put_contents(base_path("storage/tmp2_{$nom}.html"), $res->getContent());
        }

        fwrite(STDERR, "\n==== STATUS ====\n".print_r($report, true)."\n");
        $this->assertTrue(true);
    }
}
