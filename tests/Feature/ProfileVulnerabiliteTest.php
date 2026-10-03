<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileVulnerabiliteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_habitant_can_save_his_vulnerability_profiles(): void
    {
        $user = User::factory()->create(['profil_vulnerabilites' => []]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'profil_vulnerabilites' => ['personne_agee', 'equipement_medical'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame(
            ['personne_agee', 'equipement_medical'],
            $user->fresh()->profil_vulnerabilites,
        );
    }

    public function test_an_unknown_profile_is_rejected(): void
    {
        $user = User::factory()->create(['profil_vulnerabilites' => ['enfant']]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'profil_vulnerabilites' => ['martien'],
            ])
            ->assertSessionHasErrors('profil_vulnerabilites.0');

        // La valeur précédente est conservée.
        $this->assertSame(['enfant'], $user->fresh()->profil_vulnerabilites);
    }

    public function test_submitting_no_profile_clears_it(): void
    {
        $user = User::factory()->create(['profil_vulnerabilites' => ['personne_agee']]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $user->fresh()->profil_vulnerabilites);
    }
}
