<?php

namespace Tests\Feature\Back;

use App\Enums\Role;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(string $nom): Quartier
    {
        return Quartier::create(['nom' => $nom, 'ville' => 'Tunis', 'code_postal' => '1000']);
    }

    private function residence(Quartier $quartier, string $nom = 'Résidence Test'): Residence
    {
        return Residence::create([
            'nom' => $nom,
            'adresse' => '1 rue de la Chaleur',
            'quartier_id' => $quartier->id,
        ]);
    }

    public function test_admin_sees_global_sections_and_counts(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $a = $this->quartier('Centre-Ville');
        $b = $this->quartier('Les Berges du Lac');
        $r1 = $this->residence($a, 'Résidence A');

        // Alerte validée et active couvrant les deux quartiers.
        $alerte = Alerte::factory()->create([
            'titre' => 'Canicule niveau orange',
            'validee' => true,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(6),
        ]);
        $alerte->quartiers()->sync([$a->id, $b->id]);

        Coupure::factory()->enCours()->create(['quartier_id' => $a->id]);

        $habitant = User::factory()->create(['role' => Role::Habitant, 'residence_id' => $r1->id]);
        Signalement::create([
            'user_id' => $habitant->id,
            'residence_id' => $r1->id,
            'categorie' => 'fuite',
            'urgence' => 'prioritaire',
            'description' => 'Fuite au 3e étage.',
            'statut' => 'nouveau',
            'date_signalement' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->get(route('back.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Chiffres clés par module');
        $response->assertSeeText('Activité récente');
        $response->assertSeeText('Canicule niveau orange');

        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['quartiers']);
        $this->assertSame(1, $stats['residences']);
        $this->assertSame(1, $stats['habitants']);
        $this->assertSame(1, $stats['alertes_actives']);
        $this->assertSame(0, $stats['alertes_a_valider']);
        $this->assertSame(1, $stats['coupures_en_cours']);
        $this->assertSame(0, $stats['coupures_prevues']);
        $this->assertSame(1, $stats['signalements_nouveaux']);
    }

    public function test_gestionnaire_only_sees_their_zone(): void
    {
        $a = $this->quartier('Centre-Ville');
        $b = $this->quartier('Les Berges du Lac');
        $ra = $this->residence($a, 'Résidence A');
        $rb = $this->residence($b, 'Résidence B');

        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => $ra->id,
        ]);

        $alerteZone = Alerte::factory()->create([
            'titre' => 'Alerte de ma zone',
            'validee' => true,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(6),
        ]);
        $alerteZone->quartiers()->sync([$a->id]);

        $alerteAilleurs = Alerte::factory()->create([
            'titre' => 'Alerte hors zone',
            'validee' => true,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(6),
        ]);
        $alerteAilleurs->quartiers()->sync([$b->id]);

        // Coupures : une dans sa zone, une ailleurs.
        Coupure::factory()->enCours()->create(['quartier_id' => $a->id]);
        Coupure::factory()->enCours()->create(['quartier_id' => $b->id]);

        // Signalement rattaché à une autre résidence : ne doit pas remonter.
        $autreHabitant = User::factory()->create(['role' => Role::Habitant, 'residence_id' => $rb->id]);
        Signalement::create([
            'user_id' => $autreHabitant->id,
            'residence_id' => $rb->id,
            'categorie' => 'panne_locale',
            'urgence' => 'vitale',
            'description' => 'Panne générale.',
            'statut' => 'nouveau',
            'date_signalement' => now()->toDateString(),
        ]);

        $response = $this->actingAs($gestionnaire)->get(route('back.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Chiffres clés par module');
        $response->assertSeeText('Activité récente');
        $response->assertSeeText('Alerte de ma zone');
        $response->assertDontSeeText('Alerte hors zone');

        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['residences']);
        $this->assertSame(1, $stats['alertes_actives']);
        $this->assertSame(1, $stats['coupures_en_cours']);
        $this->assertSame(0, $stats['signalements_nouveaux']);

        $dernieresAlertes = $response->viewData('dernieresAlertes');
        $this->assertSame(['Alerte de ma zone'], $dernieresAlertes->pluck('titre')->all());
    }

    public function test_gestionnaire_without_residence_sees_zeroed_scope(): void
    {
        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => null,
        ]);

        // Données hors périmètre : ne doivent pas apparaître.
        $a = $this->quartier('Centre-Ville');
        $alerte = Alerte::factory()->create(['validee' => true, 'debut' => now()->subHour(), 'fin' => now()->addHours(6)]);
        $alerte->quartiers()->sync([$a->id]);

        $response = $this->actingAs($gestionnaire)->get(route('back.dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(0, $stats['residences']);
        $this->assertSame(0, $stats['alertes_actives']);
        $this->assertSame(0, $stats['coupures_en_cours']);
        $this->assertSame(0, $stats['signalements_nouveaux']);
    }
}
