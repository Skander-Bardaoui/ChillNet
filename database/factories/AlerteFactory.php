<?php

namespace Database\Factories;

use App\Enums\NiveauAlerte;
use App\Models\Alerte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alerte>
 */
class AlerteFactory extends Factory
{
    protected $model = Alerte::class;

    public function definition(): array
    {
        $debut = $this->faker->dateTimeBetween('-1 day', '+2 days');
        $fin = (clone $debut)->modify('+'.rand(6, 48).' hours');

        return [
            'titre' => 'Vigilance canicule — '.$this->faker->city(),
            'niveau' => $this->faker->randomElement(NiveauAlerte::cases())->value,
            'seuil_temperature' => $this->faker->randomFloat(1, 34, 38),
            'temperature_actuelle' => $this->faker->randomFloat(1, 30, 44),
            'temperature_ressentie' => $this->faker->randomFloat(1, 32, 47),
            'humidite' => $this->faker->numberBetween(30, 85),
            'source_meteo' => 'manuel',
            'debut' => $debut,
            'fin' => $fin,
            // Ciblage géographique non défini par défaut : les tests attachent
            // les quartiers via ->hasAttached($quartier) ou utilisent geo().
            'latitude' => null,
            'longitude' => null,
            'rayon_metres' => null,
            'message' => $this->faker->sentence(14),
            'validee' => false,
            // La jointure N---N n'est PAS créée ici : en test, attachez les
            // quartiers via ->hasAttached($quartier).
        ];
    }

    /**
     * Ciblage géographique : cercle (point + rayon, défaut 1 km).
     */
    public function geo(float $latitude, float $longitude, int $rayon = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'rayon_metres' => $rayon,
        ]);
    }

    public function validee(): static
    {
        return $this->state(fn (array $attributes) => [
            'validee' => true,
            'validee_le' => now(),
        ]);
    }

    public function brouillon(): static
    {
        return $this->state(fn (array $attributes) => ['validee' => false]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'debut' => now()->subHour(),
            'fin' => now()->addHours(6),
        ]);
    }

    public function programmee(): static
    {
        return $this->state(fn (array $attributes) => [
            'debut' => now()->addDay(),
            'fin' => now()->addDay()->addHours(8),
        ]);
    }

    public function terminee(): static
    {
        return $this->state(fn (array $attributes) => [
            'debut' => now()->subDays(2),
            'fin' => now()->subDay(),
        ]);
    }

    public function jaune(): static
    {
        return $this->state(fn (array $attributes) => ['niveau' => NiveauAlerte::Jaune->value]);
    }

    public function orange(): static
    {
        return $this->state(fn (array $attributes) => ['niveau' => NiveauAlerte::Orange->value]);
    }

    public function rouge(): static
    {
        return $this->state(fn (array $attributes) => ['niveau' => NiveauAlerte::Rouge->value]);
    }
}
