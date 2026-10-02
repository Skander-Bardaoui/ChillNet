<?php

namespace Database\Factories;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Coupure;
use App\Models\Quartier;
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
            'type' => $this->faker->randomElement([TypeCoupure::Maintenance, TypeCoupure::Delestage])->value,
            'debut' => now()->addDay(),
            'fin' => now()->addDay()->addHours(4),
        ]);
    }
}
