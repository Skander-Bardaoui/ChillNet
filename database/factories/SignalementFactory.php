<?php

namespace Database\Factories;

use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signalement>
 */
class SignalementFactory extends Factory
{
    protected $model = Signalement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'residence_id' => function (): int {
                $residence = Residence::query()->inRandomOrder()->first();

                if ($residence) {
                    return $residence->id;
                }

                $quartier = Quartier::query()->first() ?? Quartier::create([
                    'nom' => 'Quartier test '.$this->faker->unique()->word(),
                    'ville' => $this->faker->city(),
                    'code_postal' => $this->faker->postcode(),
                ]);

                return Residence::create([
                    'nom' => 'Résidence test '.$this->faker->unique()->word(),
                    'adresse' => $this->faker->address(),
                    'quartier_id' => $quartier->id,
                ])->id;
            },
            'categorie' => $this->faker->randomElement(['fuite', 'panne_locale', 'personne_vulnerable', 'autre']),
            'categorie_autre' => null,
            'urgence' => $this->faker->randomElement(['normale', 'prioritaire', 'vitale']),
            'description' => $this->faker->paragraph(),
            'photo_path' => null,
            'statut' => $this->faker->randomElement(['nouveau', 'en_traitement', 'resolu']),
            'date_signalement' => $this->faker->date(),
        ];
    }

    public function autre(): static
    {
        return $this->state(fn (): array => [
            'categorie' => 'autre',
            'categorie_autre' => $this->faker->words(2, true),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (): array => ['urgence' => 'vitale']);
    }

    public function nouveau(): static
    {
        return $this->state(fn (): array => ['statut' => 'nouveau']);
    }
}
