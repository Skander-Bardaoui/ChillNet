<?php

namespace Database\Factories;

use App\Enums\LieuType;
use App\Models\Lieu;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lieu>
 */
class LieuFactory extends Factory
{
    protected $model = Lieu::class;

    public function definition(): array
    {
        // Autour de Tunis : quartier_id est auto-assigné par le modèle
        // (Quartier::plusProche) dès qu'un point est posé.
        return [
            'user_id' => User::factory(),
            'nom' => $this->faker->randomElement(['Domicile', 'Travail', 'Chez mes parents', 'Studio']),
            'type' => $this->faker->randomElement(LieuType::cases())->value,
            'adresse' => $this->faker->streetAddress(),
            'latitude' => $this->faker->latitude(36.75, 36.90),
            'longitude' => $this->faker->longitude(10.10, 10.35),
            'est_principal' => false,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn (array $attributes) => [
            'est_principal' => true,
        ]);
    }

    public function domicile(): static
    {
        return $this->state(fn (array $attributes) => [
            'nom' => 'Domicile',
            'type' => LieuType::Domicile->value,
        ]);
    }
}
