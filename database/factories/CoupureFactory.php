<?php

namespace Database\Factories;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupure>
 */
class CoupureFactory extends Factory
{
    protected $model = Coupure::class;

    public function definition(): array
    {
        $debut = $this->faker->dateTimeBetween('-3 days', '+3 days');
        $fin = (clone $debut)->modify('+'.rand(1, 5).' hours');

        return [
            // Jointure : la factory prend un quartier existant (seedé).
            // En test, créez d'abord un Quartier puis surchargez `quartier_id`.
            'quartier_id' => Quartier::inRandomOrder()->first()?->id ?? Quartier::create([
                'nom' => 'Quartier test '.$this->faker->unique()->word(),
                'ville' => $this->faker->city(),
                'code_postal' => $this->faker->postcode(),
            ])->id,
            'type' => $this->faker->randomElement(TypeCoupure::cases())->value,
            'statut' => $this->faker->randomElement(StatutCoupure::cases())->value,
            'debut' => $debut,
            'fin' => $fin,
            'description' => $this->faker->sentence(12),
            'lieu' => $this->faker->optional()->streetName(),
            'confirmations' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function enCours(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutCoupure::EnCours->value,
            'debut' => now()->subHour(),
            'fin' => now()->addHours(2),
        ]);
    }

    public function prevue(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutCoupure::Prevue->value,
            'debut' => now()->addDay(),
            'fin' => now()->addDay()->addHours(4),
        ]);
    }

    public function resolue(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutCoupure::Resolue->value,
            'debut' => now()->subDays(2),
            'fin' => now()->subDays(2)->addHours(2),
        ]);
    }

    public function delestage(): static
    {
        return $this->type(TypeCoupure::Delestage);
    }

    public function surcharge(): static
    {
        return $this->type(TypeCoupure::Surcharge);
    }

    public function panne(): static
    {
        return $this->type(TypeCoupure::Panne);
    }

    public function maintenance(): static
    {
        return $this->type(TypeCoupure::Maintenance);
    }

    /**
     * Ciblage libre : point posé sur la carte (carte front/back plus précise).
     */
    public function geo(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Coupure sans zone interne : la carte s'appuie alors sur le seul point.
     */
    public function sansQuartier(): static
    {
        return $this->state(fn (array $attributes) => ['quartier_id' => null]);
    }

    public function dansQuartier(Quartier|int $quartier): static
    {
        $id = $quartier instanceof Quartier ? $quartier->id : $quartier;

        return $this->state(fn (array $attributes) => ['quartier_id' => $id]);
    }

    public function rue(string $lieu): static
    {
        return $this->state(fn (array $attributes) => ['lieu' => $lieu]);
    }

    public function confirmee(int $nombre = 3): static
    {
        return $this->state(fn (array $attributes) => ['confirmations' => $nombre]);
    }

    public function signaleePar(?User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user?->id]);
    }

    private function type(TypeCoupure $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type->value]);
    }
}
