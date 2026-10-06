<?php

namespace App\Http\Requests;

use App\Enums\StatutPointFraicheur;
use App\Enums\TypePointFraicheur;
use App\Models\PointFraicheur;
use App\Support\Geo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ajout / modification d'un point de fraîcheur (module 3).
 *
 * Même formulaire pour deux parcours :
 *  - habitant (front)          : propose un point → statut forcé « en attente » ;
 *  - admin / gestionnaire (back) : saisie directe, l'admin peut fixer le statut.
 *
 * Règles avancées :
 *  - capacité obligatoire sauf pour une fontaine ;
 *  - horaires obligatoires sauf « ouvert 24h/24 », fermeture ≠ ouverture
 *    (une fermeture après minuit est admise : 18:00 – 02:00) ;
 *  - coordonnées GPS : bornes, précision, territoire tunisien, et anti-doublon
 *    (même type à moins de 50 m) ;
 *  - photo : image jpg/png/webp ≤ 2 Mo, au moins 300 × 200 px.
 */
class StorePointFraicheurRequest extends FormRequest
{
    /** Emprise de la Tunisie (contrôle de vraisemblance des coordonnées). */
    private const LAT_MIN = 30.2;

    private const LAT_MAX = 37.6;

    private const LNG_MIN = 7.5;

    private const LNG_MAX = 11.7;

    /** Rayon (m) de l'anti-doublon : même type de point trop proche. */
    public const RAYON_DOUBLON_M = 50;

    public function authorize(): bool
    {
        // Les rôles sont vérifiés par le middleware `role:...` des routes.
        return true;
    }

    /**
     * Cases à cocher → booléens ; virgule décimale → point ; horaires
     * neutralisés quand le point est ouvert 24h/24.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['ouvert_24h', 'accessible_pmr', 'climatise', 'ombrage', 'eau_potable', 'supprimer_photo'] as $case) {
            $data[$case] = $this->boolean($case);
        }

        foreach (['latitude', 'longitude'] as $coord) {
            if (is_string($this->input($coord))) {
                $data[$coord] = str_replace(',', '.', trim($this->input($coord)));
            }
        }

        if ($this->boolean('ouvert_24h')) {
            $data['heure_ouverture'] = null;
            $data['heure_fermeture'] = null;
        }

        if ($this->input('type') === TypePointFraicheur::Fontaine->value) {
            $data['capacite'] = null;
        }

        $this->merge($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $coordonnee = ['required', 'numeric', 'regex:/^-?\d{1,3}(\.\d{1,7})?$/'];

        return [
            'nom' => ['required', 'string', 'min:3', 'max:150'],
            'type' => ['required', Rule::enum(TypePointFraicheur::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'adresse' => ['required', 'string', 'min:5', 'max:255'],
            'latitude' => [...$coordonnee, 'between:-90,90'],
            'longitude' => [...$coordonnee, 'between:-180,180'],
            'capacite' => [
                Rule::requiredIf(fn (): bool => $this->input('type') !== TypePointFraicheur::Fontaine->value),
                'nullable', 'integer', 'min:1', 'max:5000',
            ],
            'ouvert_24h' => ['boolean'],
            'heure_ouverture' => ['required_if_declined:ouvert_24h', 'nullable', 'date_format:H:i'],
            'heure_fermeture' => ['required_if_declined:ouvert_24h', 'nullable', 'date_format:H:i', 'different:heure_ouverture'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=300,min_height=200'],
            'supprimer_photo' => ['boolean'],
            'accessible_pmr' => ['boolean'],
            'climatise' => ['boolean'],
            'ombrage' => ['boolean'],
            'eau_potable' => ['boolean'],
            // Seul l'admin choisit le statut (back-office).
            'statut' => [
                Rule::excludeIf(fn (): bool => ! $this->user()?->isAdmin()),
                'required', Rule::enum(StatutPointFraicheur::class),
            ],
            'motif_refus' => [
                Rule::excludeIf(fn (): bool => ! $this->user()?->isAdmin()),
                'required_if:statut,'.StatutPointFraicheur::Refuse->value, 'nullable', 'string', 'min:10', 'max:500',
            ],
        ];
    }

    /**
     * Contrôles métier sur les coordonnées GPS.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['latitude', 'longitude', 'type'])) {
                return;
            }

            $lat = (float) $this->input('latitude');
            $lng = (float) $this->input('longitude');

            if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                $validator->errors()->add('latitude', 'Les coordonnées GPS doivent être situées en Tunisie (posez le point sur la carte).');

                return;
            }

            // Anti-doublon : un point du même type déjà référencé à moins de 50 m.
            $ignoreId = $this->route('point')?->id;
            $proche = PointFraicheur::query()
                ->where('type', $this->input('type'))
                ->where('statut', '!=', StatutPointFraicheur::Refuse->value)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                // Pré-filtre grossier (~0,001° ≈ 110 m) puis distance exacte en PHP.
                ->whereBetween('latitude', [$lat - 0.001, $lat + 0.001])
                ->whereBetween('longitude', [$lng - 0.0013, $lng + 0.0013])
                ->get()
                ->first(fn (PointFraicheur $p): bool => Geo::distanceKm($lat, $lng, $p->latitude, $p->longitude) * 1000 < self::RAYON_DOUBLON_M);

            if ($proche) {
                $validator->errors()->add(
                    'latitude',
                    'Un point du même type existe déjà à moins de '.self::RAYON_DOUBLON_M." m : « {$proche->nom} »."
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
            'nom.required' => 'Le nom du point de fraîcheur est obligatoire.',
            'nom.min' => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max' => 'Le nom ne doit pas dépasser 150 caractères.',
            'type.required' => 'Choisissez un type (parc, salle climatisée, fontaine).',
            'type.enum' => 'Le type choisi est invalide.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'adresse.required' => 'L\'adresse est obligatoire.',
            'adresse.min' => 'L\'adresse doit contenir au moins 5 caractères.',
            'latitude.required' => 'Posez le point sur la carte (latitude manquante).',
            'longitude.required' => 'Posez le point sur la carte (longitude manquante).',
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'latitude.regex' => 'La latitude doit avoir au plus 7 décimales.',
            'longitude.regex' => 'La longitude doit avoir au plus 7 décimales.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'capacite.required' => 'La capacité est obligatoire pour un parc ou une salle climatisée.',
            'capacite.integer' => 'La capacité doit être un nombre entier.',
            'capacite.min' => 'La capacité doit être d\'au moins 1 personne.',
            'capacite.max' => 'La capacité ne peut pas dépasser 5000 personnes.',
            'heure_ouverture.required_if_declined' => 'L\'heure d\'ouverture est obligatoire (ou cochez « ouvert 24h/24 »).',
            'heure_fermeture.required_if_declined' => 'L\'heure de fermeture est obligatoire (ou cochez « ouvert 24h/24 »).',
            'heure_ouverture.date_format' => 'L\'heure d\'ouverture doit être au format HH:MM.',
            'heure_fermeture.date_format' => 'L\'heure de fermeture doit être au format HH:MM.',
            'heure_fermeture.different' => 'La fermeture doit être différente de l\'ouverture.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'La photo doit être au format JPG, PNG ou WEBP.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
            'photo.dimensions' => 'La photo doit mesurer au moins 300 × 200 pixels.',
            'statut.required' => 'Choisissez un statut.',
            'statut.enum' => 'Le statut choisi est invalide.',
            'motif_refus.required_if' => 'Indiquez le motif du refus (il sera visible par l\'habitant).',
            'motif_refus.min' => 'Le motif doit contenir au moins 10 caractères.',
            'motif_refus.max' => 'Le motif ne doit pas dépasser 500 caractères.',
        ];
    }
}
