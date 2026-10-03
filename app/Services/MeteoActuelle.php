<?php

namespace App\Services;

/**
 * Relevé météo courant, indépendant du fournisseur.
 * Valeur immuable passée du WeatherService vers l'IA et le formulaire.
 */
final readonly class MeteoActuelle
{
    public function __construct(
        public float $temperature,
        public ?float $ressentie = null,
        public ?int $humidite = null,
        public ?float $vent = null,
        public ?string $condition = null,
        public ?string $ville = null,
        public string $source = 'weatherapi',
    ) {}

    /**
     * Construit le relevé depuis la réponse JSON de WeatherAPI.com
     * (`/current.json` : objets `current` et `location`).
     *
     * @param  array<string, mixed>  $json
     */
    public static function fromWeatherApi(array $json): self
    {
        $current = $json['current'] ?? [];
        $location = $json['location'] ?? [];

        return new self(
            temperature: (float) ($current['temp_c'] ?? 0),
            ressentie: isset($current['feelslike_c']) ? (float) $current['feelslike_c'] : null,
            humidite: isset($current['humidity']) ? (int) $current['humidity'] : null,
            vent: isset($current['wind_kph']) ? (float) $current['wind_kph'] : null,
            condition: $current['condition']['text'] ?? null,
            ville: $location['name'] ?? null,
            source: 'weatherapi',
        );
    }

    /**
     * Champs prêts à injecter dans le formulaire d'alerte.
     *
     * @return array<string, mixed>
     */
    public function toFormFields(): array
    {
        return [
            'temperature_actuelle' => round($this->temperature, 1),
            'temperature_ressentie' => $this->ressentie !== null ? round($this->ressentie, 1) : null,
            'humidite' => $this->humidite,
            'source_meteo' => $this->source,
        ];
    }
}
