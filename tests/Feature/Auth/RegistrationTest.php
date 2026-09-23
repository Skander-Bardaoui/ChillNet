<?php

namespace Tests\Feature\Auth;

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
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
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

    public function test_user_can_join_an_existing_residence(): void
    {
        $quartier = Quartier::create(['nom' => 'Sainte-Anne', 'ville' => 'Marseille', 'code_postal' => '13008']);
        $residence = Residence::create(['nom' => 'Les Oliviers', 'adresse' => '12 rue des Tilleuls', 'quartier_id' => $quartier->id]);

        $this->post('/register', $this->payload([
            'residence_mode' => 'existante',
            'residence_id' => $residence->id,
        ]));

        $this->assertAuthenticated();
        $this->assertSame($residence->id, User::firstWhere('email', 'test@example.com')->residence_id);
    }

    public function test_existing_mode_requires_a_residence(): void
    {
        $this->post('/register', $this->payload([
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
            'residence_mode' => 'nouvelle',
            'nouvelle_residence_nom' => 'Résidence des Vignes',
        ]))->assertSessionHasErrors(['nouveau_quartier_nom', 'nouveau_quartier_ville']);

        $this->assertGuest();
    }

    public function test_user_can_register_without_a_residence(): void
    {
        $this->post('/register', $this->payload(['residence_mode' => 'aucune']));

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
