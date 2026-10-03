<?php

namespace Tests\Feature\Auth;

use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // Un habitant pose un point sur la carte : c'est son premier lieu.
        $response = $this->post('/register', $this->payload([
            'lieu_nom' => 'Domicile',
            'latitude' => 36.8008,
            'longitude' => 10.1800,
        ]));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertNotNull($user);
        $this->assertSame(1, $user->lieux()->count());
        $this->assertSame('Domicile', $user->lieux()->first()->nom);
        $this->assertTrue($user->lieux()->first()->est_principal);
    }

    public function test_registration_screen_lists_referenced_residences(): void
    {
        $quartier = Quartier::create(['nom' => 'Sainte-Anne', 'ville' => 'Marseille', 'code_postal' => '13008']);
        Residence::create(['nom' => 'Résidence Les Oliviers', 'adresse' => '12 rue des Tilleuls', 'quartier_id' => $quartier->id]);

        $this->get('/register')
            ->assertOk()
            ->assertSee('Sainte-Anne')
            ->assertSee('Résidence Les Oliviers');
    }

    public function test_habitant_must_provide_a_location_point(): void
    {
        $this->post('/register', $this->payload())->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertGuest();
        $this->assertNull(User::firstWhere('email', 'test@example.com'));
    }

    public function test_habitant_location_is_assigned_to_the_nearest_quartier(): void
    {
        $quartier = Quartier::create([
            'nom' => 'Centre-Ville', 'ville' => 'Tunis',
            'latitude' => 36.8008, 'longitude' => 10.1800,
        ]);

        $this->post('/register', $this->payload([
            'latitude' => 36.8010,
            'longitude' => 10.1805,
        ]));

        $lieu = Lieu::firstWhere('user_id', User::firstWhere('email', 'test@example.com')->id);
        $this->assertNotNull($lieu);
        $this->assertSame($quartier->id, $lieu->quartier_id);
    }

    public function test_user_can_join_an_existing_residence(): void
    {
        $quartier = Quartier::create(['nom' => 'Sainte-Anne', 'ville' => 'Marseille', 'code_postal' => '13008']);
        $residence = Residence::create(['nom' => 'Les Oliviers', 'adresse' => '12 rue des Tilleuls', 'quartier_id' => $quartier->id]);

        $this->post('/register', $this->payload([
            'role' => 'gestionnaire',
            'residence_mode' => 'existante',
            'residence_id' => $residence->id,
        ]));

        $this->assertAuthenticated();
        $this->assertSame($residence->id, User::firstWhere('email', 'test@example.com')->residence_id);
    }

    public function test_existing_mode_requires_a_residence(): void
    {
        $this->post('/register', $this->payload([
            'role' => 'gestionnaire',
            'residence_mode' => 'existante',
            'residence_id' => '',
        ]))->assertSessionHasErrors('residence_id');

        $this->assertGuest();
        $this->assertNull(User::firstWhere('email', 'test@example.com'));
    }

    public function test_user_can_declare_a_residence_in_an_existing_quartier(): void
    {
        $quartier = Quartier::create(['nom' => 'Sainte-Anne', 'ville' => 'Marseille', 'code_postal' => '13008']);

        $this->post('/register', $this->payload([
            'role' => 'gestionnaire',
            'residence_mode' => 'nouvelle',
            'nouvelle_residence_nom' => 'Résidence du Parc',
            'nouvelle_residence_adresse' => '4 avenue du Parc',
            'quartier_id' => $quartier->id,
        ]));

        $this->assertAuthenticated();

        $residence = Residence::firstWhere('nom', 'Résidence du Parc');
        $this->assertNotNull($residence);
        $this->assertSame($quartier->id, $residence->quartier_id);
        $this->assertSame('4 avenue du Parc', $residence->adresse);
        $this->assertSame($residence->id, User::firstWhere('email', 'test@example.com')->residence_id);
    }

    public function test_user_can_declare_a_residence_and_its_quartier(): void
    {
        $this->post('/register', $this->payload([
            'role' => 'gestionnaire',
            'residence_mode' => 'nouvelle',
            'nouvelle_residence_nom' => 'Résidence des Vignes',
            'nouveau_quartier_nom' => 'Les Hauts de Vignes',
            'nouveau_quartier_ville' => 'Aix-en-Provence',
            'nouveau_quartier_code_postal' => '13100',
        ]));

        $this->assertAuthenticated();

        $quartier = Quartier::firstWhere('nom', 'Les Hauts de Vignes');
        $this->assertNotNull($quartier);
        $this->assertSame('Aix-en-Provence', $quartier->ville);

        $residence = Residence::firstWhere('nom', 'Résidence des Vignes');
        $this->assertNotNull($residence);
        $this->assertSame($quartier->id, $residence->quartier_id);
    }

    public function test_declaring_a_residence_reuses_an_already_declared_one(): void
    {
        // Résidence déjà déclarée par un voisin : le nouveau foyer doit y être
        // rattaché, sans créer de doublon.
        $quartier = Quartier::create(['nom' => 'Les Hauts de Vignes', 'ville' => 'Aix-en-Provence']);
        $existante = Residence::create(['nom' => 'Résidence des Vignes', 'quartier_id' => $quartier->id]);

        $this->post('/register', $this->payload([
            'role' => 'gestionnaire',
            'residence_mode' => 'nouvelle',
            'nouvelle_residence_nom' => 'Résidence des Vignes',
            'nouveau_quartier_nom' => 'Les Hauts de Vignes',
            'nouveau_quartier_ville' => 'Aix-en-Provence',
        ]));

        $this->assertSame(1, Quartier::count());
        $this->assertSame(1, Residence::count());
        $this->assertSame($existante->id, User::firstWhere('email', 'test@example.com')->residence_id);
    }

    public function test_declaring_a_residence_requires_a_quartier(): void
    {
        $this->post('/register', $this->payload([
            'role' => 'gestionnaire',
            'residence_mode' => 'nouvelle',
            'nouvelle_residence_nom' => 'Résidence des Vignes',
        ]))->assertSessionHasErrors(['nouveau_quartier_nom', 'nouveau_quartier_ville']);

        $this->assertGuest();
    }

    public function test_habitant_registration_does_not_create_a_residence(): void
    {
        $this->post('/register', $this->payload([
            'latitude' => 36.8008,
            'longitude' => 10.1800,
        ]));

        $this->assertAuthenticated();
        $this->assertNull(User::firstWhere('email', 'test@example.com')->residence_id);
        $this->assertSame(0, Residence::count());
    }

    /**
     * Payload de base d'une inscription, surchargé selon le scénario.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Famille Dupont',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }
}
