<?php

namespace Tests\Feature\Front;

use App\Enums\LieuType;
use App\Enums\Role;
use App\Enums\StatutCoupure;
use App\Models\Coupure;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signalement d'une coupure par un habitant (module 2) : le ciblage passe
 * par l'un de SES lieux, le quartier interne étant déduit du lieu.
 */
class CoupureSignalementTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(): Quartier
    {
        return Quartier::create([
            'nom' => 'Centre-Ville',
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
        ]);
    }

    /**
     * @return array{0: User, 1: Lieu}
     */
    private function habitantAvecLieu(Quartier $quartier): array
    {
        $user = User::factory()->create(['role' => Role::Habitant]);

        $lieu = $user->lieux()->create([
            'nom' => 'Domicile',
            'type' => LieuType::Domicile,
            'adresse' => '12 Avenue Habib Bourguiba',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
            'quartier_id' => $quartier->id,
            'est_principal' => true,
        ]);

        return [$user, $lieu];
    }

    public function test_the_signalement_form_lists_the_household_lieux(): void
    {
        [$habitant, $lieu] = $this->habitantAvecLieu($this->quartier());

        $this->actingAs($habitant)
            ->get(route('coupures.create'))
            ->assertOk()
            ->assertSee('Domicile');
    }

    public function test_a_habitant_signals_a_coupure_from_his_lieu(): void
    {
        $quartier = $this->quartier();
        [$habitant, $lieu] = $this->habitantAvecLieu($quartier);

        $this->actingAs($habitant)
            ->post(route('coupures.store'), [
                'lieu_id' => $lieu->id,
                'lieu' => 'rue des Lilas',
                'type' => 'panne',
                'debut' => now()->format('Y-m-d\TH:i'),
                'description' => 'Coupure depuis 18h05.',
            ])
            ->assertRedirect(route('coupures.index'));

        $coupure = Coupure::firstOrFail();

        $this->assertSame(StatutCoupure::EnCours, $coupure->statut);
        $this->assertSame($quartier->id, $coupure->quartier_id);
        $this->assertSame($habitant->id, $coupure->user_id);
        $this->assertSame('rue des Lilas', $coupure->lieu);
    }

    public function test_a_habitant_cannot_signal_from_another_households_lieu(): void
    {
        $quartier = $this->quartier();
        [$habitant] = $this->habitantAvecLieu($quartier);
        [, $lieuAutre] = $this->habitantAvecLieu($quartier);

        $this->actingAs($habitant)
            ->post(route('coupures.store'), [
                'lieu_id' => $lieuAutre->id,
                'type' => 'panne',
                'debut' => now()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('lieu_id');

        $this->assertSame(0, Coupure::count());
    }

    public function test_the_form_invites_to_create_a_lieu_when_there_is_none(): void
    {
        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $this->actingAs($habitant)
            ->get(route('coupures.create'))
            ->assertOk()
            ->assertSee('aucun lieu enregistré');
    }
}
