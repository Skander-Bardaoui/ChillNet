<?php

namespace Tests\Feature\Front;

use App\Enums\Role;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignalementTest extends TestCase
{
    use RefreshDatabase;

    private function residence(array $attributes = []): Residence
    {
        $quartier = Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
        ]);

        return Residence::create($attributes + [
            'nom' => 'Résidence Les Oliviers',
            'adresse' => '12 Avenue Habib Bourguiba',
            'quartier_id' => $quartier->id,
        ]);
    }

    public function test_the_form_lists_residences_for_an_unattached_habitant(): void
    {
        $residence = $this->residence(['point_fraicheur' => true]);
        $habitant = User::factory()->create([
            'role' => Role::Habitant,
            'residence_id' => null,
        ]);

        $this->actingAs($habitant)
            ->get(route('signalements.index'))
            ->assertOk()
            ->assertSee('Résidence concernée')
            ->assertSee($residence->nom);
    }

    public function test_an_unattached_habitant_can_create_a_signalement_for_a_selected_residence(): void
    {
        Notification::fake();
        $residence = $this->residence();
        $habitant = User::factory()->create([
            'role' => Role::Habitant,
            'residence_id' => null,
        ]);

        $this->actingAs($habitant)
            ->post(route('front.signalements.store'), [
                'residence_id' => $residence->id,
                'categorie' => 'fuite',
                'urgence' => 'normale',
                'description' => 'Une fuite importante est visible dans le hall.',
            ])
            ->assertRedirect(route('signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signalements', [
            'user_id' => $habitant->id,
            'residence_id' => $residence->id,
            'categorie' => 'fuite',
        ]);
        $this->assertSame(1, Signalement::count());
    }

    public function test_a_habitant_cannot_create_a_signalement_without_a_valid_residence(): void
    {
        Notification::fake();
        $habitant = User::factory()->create([
            'role' => Role::Habitant,
            'residence_id' => null,
        ]);

        $this->actingAs($habitant)
            ->post(route('front.signalements.store'), [
                'residence_id' => 999999,
                'categorie' => 'fuite',
                'urgence' => 'normale',
                'description' => 'Une fuite importante est visible dans le hall.',
            ])
            ->assertSessionHasErrors('residence_id');

        $this->assertSame(0, Signalement::count());
    }

    public function test_invalid_submission_preserves_form_selections(): void
    {
        $residence = $this->residence();
        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $this->actingAs($habitant)
            ->from(route('signalements.index'))
            ->post(route('front.signalements.store'), [
                'residence_id' => $residence->id,
                'categorie' => 'autre',
                'categorie_autre' => 'Fuite de canalisation',
                'urgence' => 'vitale',
                'description' => 'Trop court',
            ])
            ->assertSessionHasErrors('description')
            ->assertSessionHas('_old_input.categorie', 'autre')
            ->assertSessionHas('_old_input.categorie_autre', 'Fuite de canalisation')
            ->assertSessionHas('_old_input.urgence', 'vitale')
            ->assertSessionHas('_old_input.residence_id', $residence->id);

        $this->assertSame(0, Signalement::count());
    }

    public function test_a_habitant_can_edit_his_signalement_without_resending_its_residence(): void
    {
        $residence = $this->residence();
        $habitant = User::factory()->create(['role' => Role::Habitant]);
        $signalement = Signalement::create([
            'user_id' => $habitant->id,
            'residence_id' => $residence->id,
            'categorie' => 'fuite',
            'urgence' => 'normale',
            'description' => 'Une fuite importante est visible dans le hall.',
            'date_signalement' => now()->toDateString(),
        ]);

        $this->actingAs($habitant)
            ->patch(route('front.signalements.update', $signalement), [
                'categorie' => 'panne_locale',
                'urgence' => 'prioritaire',
                'description' => 'La lumière du hall est en panne depuis ce matin.',
            ])
            ->assertRedirect(route('signalements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('signalements', [
            'id' => $signalement->id,
            'residence_id' => $residence->id,
            'categorie' => 'panne_locale',
            'urgence' => 'prioritaire',
        ]);
    }
}
