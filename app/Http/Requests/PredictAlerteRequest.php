<?php

namespace App\Http\Requests;

use App\Enums\ProfilVulnerabilite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Requête du bouton « Pré-remplir météo + IA » : ciblage par quartier(s)
 * et/ou coordonnées (cercle), éventuel override manuel de la température, et
 * demande facultative de message personnalisé (Groq).
 */
class PredictAlerteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('quartier_ids');

        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), fn ($v): bool => $v !== '');
        }

        if (! is_array($ids)) {
            $ids = $ids === null ? [] : [$ids];
        }

        $this->merge([
            'quartier_ids' => array_values(array_map('intval', $ids)),
            'avec_message' => filter_var($this->input('avec_message'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quartier_ids' => ['nullable', 'array'],
            'quartier_ids.*' => ['integer', 'distinct', 'exists:quartiers,id'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rayon_metres' => ['nullable', 'integer', 'between:100,50000'],

            'seuil_temperature' => ['nullable', 'numeric', 'between:30,55'],
            // Présence = override manuel : aucun appel WeatherAPI n'est fait.
            'temperature_actuelle' => ['nullable', 'numeric', 'between:-10,60'],
            'humidite' => ['nullable', 'integer', 'between:0,100'],

            'avec_message' => ['nullable', 'boolean'],
            'profil' => ['nullable', Rule::in(ProfilVulnerabilite::persistables())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quartier_ids.array' => 'Sélection de quartier invalide.',
            'quartier_ids.*.exists' => 'Un des quartiers sélectionnés n\'existe plus.',
            'seuil_temperature.between' => 'Le seuil de température doit être compris entre 30 et 55 °C.',
            'temperature_actuelle.between' => 'La température doit être comprise entre -10 et 60 °C.',
            'humidite.between' => 'L\'humidité doit être comprise entre 0 et 100 %.',
            'profil.in' => 'Profil de vulnérabilité invalide.',
        ];
    }
}
