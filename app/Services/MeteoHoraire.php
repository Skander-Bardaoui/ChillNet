<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Une heure de prévision météo, indépendante du fournisseur.
 * Alimente la « Chronologie 24h » de l'espace habitant.
 */
final readonly class MeteoHoraire
{
    public function __construct(
        public CarbonInterface $heure,
        public float $temperature,
        public ?float $ressentie = null,
        public ?int $humidite = null,
        public ?float $vent = null,
        public ?string $condition = null,
    ) {}

    /**
     * Construit une heure depuis un élément `forecast.forecastday[].hour[]`
     * de WeatherAPI.com (objet `hour`).
     *
     * @param  array<string, mixed>  $hour
     */
    public static function fromWeatherApi(array $hour, CarbonInterface $heure): self
    {
        return new self(
            heure: $heure,
            temperature: (float) ($hour['temp_c'] ?? 0),
            ressentie: isset($hour['feelslike_c']) ? (float) $hour['feelslike_c'] : null,
            humidite: isset($hour['humidity']) ? (int) $hour['humidity'] : null,
            vent: isset($hour['wind_kph']) ? (float) $hour['wind_kph'] : null,
            condition: $hour['condition']['text'] ?? null,
        );
    }

    /**
     * Icône Material Symbols cohérente avec les maquettes du dashboard.
     * Déduite de l'heure (nuit) puis de la température et de la condition.
     */
    public function iconeMaterial(): string
    {
        $h = (int) $this->heure->format('G');

        if ($h < 6 || $h >= 20) {
            return 'nightlight';
        }

        if ($this->temperature >= 38) {
            return 'local_fire_department';
        }

        $condition = mb_strtolower($this->condition ?? '');

        return match (true) {
            str_contains($condition, 'soleil') || str_contains($condition, 'clair') || str_contains($condition, 'sunny') => 'wb_sunny',
            str_contains($condition, 'nuage') || str_contains($condition, 'couvert') || str_contains($condition, 'cloud') => 'partly_cloudy_day',
            str_contains($condition, 'pluie') || str_contains($condition, 'averse') || str_contains($condition, 'rain') => 'rainy',
            default => 'thermostat',
        };
    }
}
