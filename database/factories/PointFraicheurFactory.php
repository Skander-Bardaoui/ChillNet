<?php

namespace Database\Factories;

use App\Enums\StatutPointFraicheur;
use App\Enums\TypePointFraicheur;
use App\Models\PointFraicheur;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointFraicheur>
 */
class PointFraicheurFactory extends Factory
{
    protected $model = PointFraicheur::class;

    /**
     * Quartier le plus proche fixé dès la fabrication : sous WithoutModelEvents
     * (appel depuis DatabaseSeeder), le hook `saving` du modèle est muet.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PointFraicheur $point): void {
            $point->quartier_id ??= Quartier::plusProche((float) $point->latitude, (float) $point->longitude)?->id;
        });
    }

    public function definition(): array
    {
        $type = $this->faker->randomElement(TypePointFraicheur::cases());
        $h24 = $type === TypePointFraicheur::Fontaine || $this->faker->boolean(20);

        return [
            'nom' => match ($type) {
                TypePointFraicheur::Parc => 'Parc '.$this->faker->lastName(),
                TypePointFraicheur::SalleClimatisee => 'Salle climatisée '.$this->faker->lastName(),
                TypePointFraicheur::Fontaine => 'Fontaine '.$this->faker->lastName(),
            },
            'type' => $type->value,
            'description' => $this->faker->optional()->sentence(14),
            'adresse' => $this->faker->streetAddress().', Tunis',
            // Autour du centre de Tunis (≈ ±3 km).
            'latitude' => $this->faker->randomFloat(7, 36.78, 36.83),
            'longitude' => $this->faker->randomFloat(7, 10.15, 10.21),
            'capacite' => $type->aUneCapacite() ? $this->faker->numberBetween(20, 300) : null,
            'ouvert_24h' => $h24,
            'heure_ouverture' => $h24 ? null : $this->faker->randomElement(['07:00', '08:00', '09:00']),
            'heure_fermeture' => $h24 ? null : $this->faker->randomElement(['19:00', '20:00', '22:00']),
            'accessible_pmr' => $this->faker->boolean(60),
            'climatise' => $type === TypePointFraicheur::SalleClimatisee,
            'ombrage' => $type === TypePointFraicheur::Parc || $this->faker->boolean(30),
            'eau_potable' => $type === TypePointFraicheur::Fontaine || $this->faker->boolean(50),
            'statut' => StatutPointFraicheur::Valide->value,
            'valide_le' => now(),
        ];
    }

    public function valide(): static
    {
        return $this->state(fn () => ['statut' => StatutPointFraicheur::Valide->value, 'valide_le' => now()]);
    }

    public function enAttente(): static
    {
        return $this->state(fn () => ['statut' => StatutPointFraicheur::EnAttente->value, 'valide_le' => null, 'valide_par' => null]);
    }

    public function refuse(string $motif = 'Doublon d\'un point déjà référencé à proximité.'): static
    {
        return $this->state(fn () => ['statut' => StatutPointFraicheur::Refuse->value, 'motif_refus' => $motif, 'valide_le' => now()]);
    }

    public function parc(): static
    {
        return $this->state(fn () => ['type' => TypePointFraicheur::Parc->value, 'ombrage' => true, 'climatise' => false]);
    }

    public function salleClimatisee(): static
    {
        return $this->state(fn () => ['type' => TypePointFraicheur::SalleClimatisee->value, 'climatise' => true, 'ouvert_24h' => false, 'heure_ouverture' => '09:00', 'heure_fermeture' => '21:00']);
    }

    public function fontaine(): static
    {
        return $this->state(fn () => ['type' => TypePointFraicheur::Fontaine->value, 'eau_potable' => true, 'capacite' => null, 'ouvert_24h' => true, 'heure_ouverture' => null, 'heure_fermeture' => null]);
    }

    public function proposePar(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function a(float $latitude, float $longitude): static
    {
        return $this->state(fn () => ['latitude' => $latitude, 'longitude' => $longitude]);
    }
}
