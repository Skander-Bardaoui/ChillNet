<?php

namespace App\Http\Requests;

use App\Enums\NiveauAlerte;
use App\Models\Alerte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAlerteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Rôles déjà vérifiés par le middleware `role:admin,gestionnaire`.
        return true;
    }

    /**
     * Normalise les quartiers (cases à cocher tableau OU liste séparée par
     * des virgules) et la source météo avant la validation.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        // Un gestionnaire est TOUJOURS ramené à sa zone : on impose son
        // quartier ; à défaut de coordonnées fournies, on centre le cercle
        // sur le quartier de sa résidence.
        if ($user?->isGestionnaire() && $user->residence?->quartier_id) {
            $quartier = $user->residence->quartier;

            $merge = [
                'quartier_ids' => [(int) $user->residence->quartier_id],
                'source_meteo' => $this->input('source_meteo') ?: 'manuel',
            ];

            if (! $this->filled('latitude') && ! $this->filled('longitude') && $quartier?->hasCoordinates()) {
                $merge['latitude'] = $quartier->latitude;
                $merge['longitude'] = $quartier->longitude;
            }

            $this->merge($merge);

            return;
        }

        $ids = $this->input('quartier_ids');

        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), fn ($v): bool => $v !== '');
        }

        if (! is_array($ids)) {
            $ids = $ids === null ? [] : [$ids];
        }

        $this->merge([
            'quartier_ids' => array_values(array_map('intval', $ids)),
            'source_meteo' => $this->input('source_meteo') ?: 'manuel',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'min:3', 'max:150'],
            'niveau' => ['required', Rule::in(array_column(NiveauAlerte::cases(), 'value'))],

            // Ciblage : un ou plusieurs quartiers (facultatif) et/ou un cercle.
            'quartier_ids' => ['nullable', 'array'],
            'quartier_ids.*' => ['integer', 'distinct', 'exists:quartiers,id'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rayon_metres' => ['nullable', 'integer', 'between:100,50000'],

            'debut' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:debut'],
            'seuil_temperature' => ['required', 'numeric', 'between:30,55'],

            'temperature_actuelle' => ['nullable', 'numeric', 'between:-10,60'],
            'temperature_ressentie' => ['nullable', 'numeric', 'between:-10,70'],
            'humidite' => ['nullable', 'integer', 'between:0,100'],
            'source_meteo' => ['nullable', Rule::in(['weatherapi', 'manuel'])],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Validations métier : cohérence fin > début (message dédié), ciblage
     * obligatoire (quartier OU point), et anti-doublon (quartier et cercle).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('debut') && $this->filled('fin')) {
                try {
                    $debut = Carbon::parse((string) $this->input('debut'));
                    $fin = Carbon::parse((string) $this->input('fin'));

                    if ($fin->lessThanOrEqualTo($debut)) {
                        $validator->errors()->add('fin', 'La date de fin doit être postérieure à la date de début.');
                    }
                } catch (\Throwable) {
                    // Format déjà signalé par la règle `date`.
                }
            }

            $quartiers = array_values(array_filter((array) $this->input('quartier_ids', [])));
            $aGeo = $this->filled('latitude') && $this->filled('longitude');

            if ($quartiers === [] && ! $aGeo) {
                $validator->errors()->add(
                    'quartier_ids',
                    'Ciblez l\'alerte : cochez au moins un quartier ou posez un point sur la carte.'
                );
            }

            if ($validator->errors()->hasAny(['quartier_ids', 'debut', 'fin', 'niveau', 'latitude', 'longitude'])) {
                return;
            }

            $ignoreId = $this->route('alerte') ?? $this->route('id');
            $niveau = NiveauAlerte::tryFrom((string) $this->input('niveau'));
            $ignore = $ignoreId ? (int) $ignoreId : null;

            $doublonQuartier = $quartiers !== [] && Alerte::chevauche(
                $quartiers,
                (string) $this->input('debut'),
                (string) $this->input('fin'),
                $ignore,
                $niveau,
            );

            $doublonGeo = $aGeo && Alerte::chevaucheGeo(
                (float) $this->input('latitude'),
                (float) $this->input('longitude'),
                (int) ($this->input('rayon_metres') ?: Alerte::RAYON_DEFAUT_M),
                (string) $this->input('debut'),
                (string) $this->input('fin'),
                $ignore,
                $niveau,
            );

            if ($doublonQuartier || $doublonGeo) {
                $validator->errors()->add(
                    'quartier_ids',
                    'Une alerte de même niveau couvre déjà cette zone sur ce créneau.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre de l\'alerte est obligatoire.',
            'titre.min' => 'Le titre est trop court.',
            'titre.max' => 'Le titre ne doit pas dépasser 150 caractères.',
            'niveau.required' => 'Veuillez choisir un niveau de vigilance.',
            'niveau.in' => 'Le niveau choisi est invalide (jaune, orange ou rouge).',
            'quartier_ids.array' => 'Sélection de quartier invalide.',
            'quartier_ids.*.exists' => 'Un des quartiers sélectionnés n\'existe plus.',
            'quartier_ids.*.integer' => 'Sélection de quartier invalide.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'rayon_metres.between' => 'Le rayon doit être compris entre 100 m et 50 km.',
            'debut.required' => 'La date de début est obligatoire.',
            'debut.date' => 'La date de début n\'est pas valide.',
            'fin.required' => 'La date de fin est obligatoire.',
            'fin.date' => 'La date de fin n\'est pas valide.',
            'fin.after' => 'La date de fin doit être postérieure à la date de début.',
            'seuil_temperature.required' => 'Le seuil de température est obligatoire.',
            'seuil_temperature.numeric' => 'Le seuil de température doit être un nombre.',
            'seuil_temperature.between' => 'Le seuil de température doit être compris entre 30 et 55 °C.',
            'temperature_actuelle.between' => 'La température doit être comprise entre -10 et 60 °C.',
            'temperature_ressentie.between' => 'La température ressentie doit être comprise entre -10 et 70 °C.',
            'humidite.between' => 'L\'humidité doit être comprise entre 0 et 100 %.',
            'message.max' => 'Le message ne doit pas dépasser 2000 caractères.',
        ];
    }
}
