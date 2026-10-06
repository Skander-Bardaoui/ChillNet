<?php

namespace Tests\Feature\Back;

use App\Enums\Role;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\SignalementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignalementManagementTest extends TestCase
{
    use RefreshDatabase;

    private function residence(string $name = 'Résidence Les Oliviers'): Residence
    {
        $quartier = Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
        ]);

        return Residence::create([
            'nom' => $name,
            'adresse' => '12 Avenue Habib Bourguiba',
            'quartier_id' => $quartier->id,
        ]);
    }

    public function test_manager_can_update_a_signalement_and_notify_its_habitant(): void
    {
        Notification::fake();

        $residence = $this->residence();
        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => $residence->id,
        ]);
        $habitant = User::factory()->create(['role' => Role::Habitant]);
        $signalement = Signalement::create([
            'user_id' => $habitant->id,
            'residence_id' => $residence->id,
            'categorie' => 'fuite',
            'urgence' => 'normale',
            'description' => 'Une fuite importante est visible dans le hall.',
            'date_signalement' => now()->toDateString(),
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.signalements.treatment', $signalement), [
                'statut' => 'en_traitement',
                'urgence' => 'prioritaire',
            ])
            ->assertRedirect(route('back.signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'statut' => 'en_traitement',
            'urgence' => 'prioritaire',
        ]);
        Notification::assertSentTo(
            $habitant,
            SignalementNotification::class,
            fn (SignalementNotification $notification): bool => $notification->toArray($habitant)['event'] === 'status_changed',
        );
    }

    public function test_manager_can_create_a_signalement_for_an_unattached_habitant_in_his_residence(): void
    {
        Notification::fake();
        $residence = $this->residence();
        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => $residence->id,
        ]);
        $habitant = User::factory()->create([
            'role' => Role::Habitant,
            'residence_id' => null,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.signalements.create'))
            ->assertOk()
            ->assertSee('Habitant')
            ->assertSee('Résidence Les Oliviers');

        $this->actingAs($gestionnaire)
            ->post(route('back.signalements.store'), [
                'user_id' => $habitant->id,
                'residence_id' => $residence->id,
                'categorie' => 'fuite',
                'urgence' => 'prioritaire',
                'statut' => 'nouveau',
                'date_signalement' => now()->toDateString(),
                'description' => 'Une fuite d’eau est signalée dans le hall.',
            ])
            ->assertRedirect(route('back.signalements.index'))
            ->assertSessionHas('success');

        $signalement = Signalement::firstOrFail();
        $this->assertSame($habitant->id, $signalement->user_id);
        $this->assertSame($residence->id, $signalement->residence_id);
        Notification::assertSentTo($habitant, SignalementNotification::class);
    }

    public function test_manager_can_edit_and_delete_a_signalement_in_his_residence(): void
    {
        $residence = $this->residence();
        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => $residence->id,
        ]);
        $habitant = User::factory()->create(['role' => Role::Habitant]);
        $signalement = Signalement::create([
            'user_id' => $habitant->id,
            'residence_id' => $residence->id,
            'categorie' => 'fuite',
            'urgence' => 'normale',
            'description' => 'Une fuite importante est visible dans le hall.',
            'date_signalement' => now()->toDateString(),
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.signalements.edit', $signalement))
            ->assertOk()
            ->assertSee('Modifier un signalement')
            ->assertSee('id="residence_id" name="residence_id"', false);

        $this->actingAs($gestionnaire)
            ->put(route('back.signalements.update', $signalement), [
                'user_id' => $habitant->id,
                'residence_id' => $residence->id,
                'categorie' => 'panne_locale',
                'urgence' => 'vitale',
                'statut' => 'en_traitement',
                'date_signalement' => now()->toDateString(),
                'description' => 'La lumière du hall est en panne depuis ce matin.',
            ])
            ->assertRedirect(route('back.signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'categorie' => 'panne_locale',
            'urgence' => 'vitale',
            'statut' => 'en_traitement',
        ]);

        $this->actingAs($gestionnaire)
            ->delete(route('back.signalements.destroy', $signalement))
            ->assertRedirect(route('back.signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('signalements', ['id' => $signalement->id]);
    }

    public function test_manager_can_see_edit_and_delete_reports_from_all_residences(): void
    {
        $residence = $this->residence();
        $autreResidence = $this->residence('Résidence El Manar');
        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => $residence->id,
        ]);
        $habitant = User::factory()->create(['role' => Role::Habitant]);
        $signalement = Signalement::create([
            'user_id' => $habitant->id,
            'residence_id' => $autreResidence->id,
            'categorie' => 'fuite',
            'urgence' => 'normale',
            'description' => 'Une fuite importante est visible dans le hall.',
            'date_signalement' => now()->toDateString(),
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.signalements.index'))
            ->assertOk()
            ->assertSee('Résidence El Manar')
            ->assertSee('Modifier')
            ->assertSee('Supprimer');

        $this->actingAs($gestionnaire)
            ->get(route('back.signalements.edit', $signalement))
            ->assertOk()
            ->assertSee('Modifier un signalement');

        $this->actingAs($gestionnaire)
            ->patch(route('back.signalements.treatment', $signalement), [
                'statut' => 'resolu',
                'urgence' => 'vitale',
            ])->assertRedirect(route('back.signalements.index'));

        $this->actingAs($gestionnaire)
            ->put(route('back.signalements.update', $signalement), [
                'user_id' => $habitant->id,
                'residence_id' => $autreResidence->id,
                'categorie' => 'panne_locale',
                'urgence' => 'prioritaire',
                'statut' => 'en_traitement',
                'date_signalement' => now()->subDay()->toDateString(),
                'description' => 'La lumière du couloir de cette résidence est en panne.',
            ])
            ->assertRedirect(route('back.signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'residence_id' => $autreResidence->id,
            'categorie' => 'panne_locale',
            'statut' => 'en_traitement',
        ]);

        $this->actingAs($gestionnaire)
            ->delete(route('back.signalements.destroy', $signalement))
            ->assertRedirect(route('back.signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('signalements', ['id' => $signalement->id]);
    }

    public function test_back_office_displays_report_statistics_independently_of_filters(): void
    {
        $residence = $this->residence();
        $gestionnaire = User::factory()->create([
            'role' => Role::Gestionnaire,
            'residence_id' => $residence->id,
        ]);

        foreach ([
            ['fuite', 'nouveau', 'vitale'],
            ['panne_locale', 'en_traitement', 'prioritaire'],
            ['personne_vulnerable', 'resolu', 'vitale'],
        ] as [$categorie, $statut, $urgence]) {
            $habitant = User::factory()->create(['role' => Role::Habitant]);
            Signalement::create([
                'user_id' => $habitant->id,
                'residence_id' => $residence->id,
                'categorie' => $categorie,
                'urgence' => $urgence,
                'statut' => $statut,
                'description' => 'Description suffisamment longue pour le signalement.',
                'date_signalement' => now()->toDateString(),
            ]);
        }

        $response = $this->actingAs($gestionnaire)
            ->get(route('back.signalements.index', ['statut' => 'nouveau']))
            ->assertOk()
            ->assertSee('Urgences vitales')
            ->assertSee('En traitement');

        $stats = $response->viewData('stats');
        $this->assertSame(3, (int) $stats->total);
        $this->assertSame(1, (int) $stats->nouveaux);
        $this->assertSame(1, (int) $stats->en_traitement);
        $this->assertSame(1, (int) $stats->resolus);
        $this->assertSame(2, (int) $stats->urgences_vitales);
    }
}
