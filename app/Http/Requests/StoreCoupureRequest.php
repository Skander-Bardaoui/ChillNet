<?php

namespace App\Http\Requests;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Coupure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCoupureRequest extends FormRequest
{
    public const MODE_EXISTANTE = 'existante';

    public const MODE_NOUVELLE = 'nouvelle';

    public function authorize(): bool
    {
        // Les rôles sont déjà vérifiés par le middleware `role:...`
        // sur les routes (habitant en front, admin/gestionnaire en back).
        return true;
    }

    /**
     * Mode par défaut + neutralisation des champs du mode non retenu
     * (le back-office n'envoie jamais zone_mode : il retombe sur existante).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('zone_mode')) {
            $this->merge(['zone_mode' => self::MODE_EXISTANTE]);
        }

        if ($this->input('zone_mode') !== self::MODE_EXISTANTE) {
            $this->merge(['quartier_id' => null]);
        }

        if ($this->input('zone_mode') !== self::MODE_NOUVELLE) {
            $this->merge([
                'nouveau_quartier_nom' => null,
                'nouveau_quartier_ville' => null,
                'nouveau_quartier_code_postal' => null,
                'nouveau_latitude' => null,
                'nouveau_longitude' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modeNouvelle = fn (): bool => $this->input('zone_mode') === self::MODE_NOUVELLE;

        return [
            // Choix du mode : zone existante OU déclarée à la volée.
            'zone_mode' => ['required', Rule::in([self::MODE_EXISTANTE, self::MODE_NOUVELLE])],

            // Zone touchée = un quartier existant (jointure coupures.quartier_id).
            'quartier_id' => [
                'nullable',
                'integer',
                'exists:quartiers,id',
                Rule::requiredIf(fn (): bool => ! $modeNouvelle()),
            ],

            // Zone déclarée par l'habitant (comme l'inscription qui déclare
            // sa résidence) : nom + ville obligatoires, reste optionnel.
            // La position est pré-remplie depuis le clic carte / le GPS.
            'nouveau_quartier_nom' => ['nullable', 'string', 'min:2', 'max:120', Rule::requiredIf($modeNouvelle)],
            'nouveau_quartier_ville' => ['nullable', 'string', 'min:2', 'max:120', Rule::requiredIf($modeNouvelle)],
            'nouveau_quartier_code_postal' => ['nullable', 'string', 'regex:/^[0-9A-Za-z\- ]{3,10}$/'],
            'nouveau_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'nouveau_longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Rue / lieu précis : permet 2 coupures simultanées dans 2 rues
            // différentes du même quartier (l'anti-doublon compare la rue).
            'lieu' => ['nullable', 'string', 'max:255'],

            'type' => ['required', Rule::in(array_column(TypeCoupure::cases(), 'value'))],
            'statut' => ['required', Rule::in(array_column(StatutCoupure::cases(), 'value'))],
            // Horaires estimés : début obligatoire, fin facultative mais après début.
            'debut' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after:debut'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Validation métier : pas deux coupures non résolues qui se chevauchent
     * sur la même zone (même quartier) sur le même créneau.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Si les champs de base sont déjà invalides, on ne teste pas le chevauchement.
            if ($validator->errors()->hasAny(['quartier_id', 'debut', 'fin', 'statut'])) {
                return;
            }

            // Zone déclarée à la volée : elle vient d'être créée, aucune
            // coupure ne peut déjà l'occuper — rien à vérifier.
            if ($this->input('zone_mode') === self::MODE_NOUVELLE) {
                return;
            }

            // Les coupures résolues sont de l'historique : elles peuvent chevaucher.
            if ($this->input('statut') === StatutCoupure::Resolue->value) {
                return;
            }

            // En modification, on ignore la coupure elle-même (route model binding `{coupure}`).
            $ignoreId = $this->route('coupure') ?? $this->route('id');

            $existe = Coupure::chevauche(
                (int) $this->input('quartier_id'),
                (string) $this->input('debut'),
                $this->input('fin'),
                $ignoreId ? (int) $ignoreId : null,
                $this->input('lieu'),
            );

            if ($existe) {
                $validator->errors()->add(
                    'debut',
                    'Une autre coupure (en cours ou prévue) occupe déjà cet endroit (même zone, même rue) sur ce créneau.'
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
            'zone_mode.required' => 'Veuillez indiquer si la zone existe déjà ou doit être déclarée.',
            'zone_mode.in' => 'Le mode de zone choisi est invalide.',
            'quartier_id.required' => 'Veuillez sélectionner la zone (quartier) touchée, ou déclarez-la.',
            'quartier_id.exists' => 'La zone sélectionnée est invalide.',
            'lieu.max' => 'La rue / le lieu ne doit pas dépasser 255 caractères.',
            'nouveau_quartier_nom.required' => 'Indiquez le nom du nouveau quartier.',
            'nouveau_quartier_nom.min' => 'Le nom du quartier est trop court.',
            'nouveau_quartier_ville.required' => 'Indiquez la ville du nouveau quartier.',
            'nouveau_quartier_code_postal.regex' => 'Le code postal saisi n\'est pas valide.',
            'nouveau_latitude.numeric' => 'La latitude doit être un nombre.',
            'nouveau_longitude.numeric' => 'La longitude doit être un nombre.',
            'type.required' => 'Veuillez choisir un type (délestage, surcharge, panne, maintenance).',
            'type.in' => 'Le type choisi est invalide.',
            'statut.required' => 'Veuillez choisir un statut (en cours, prévue, résolue).',
            'statut.in' => 'Le statut choisi est invalide.',
            'debut.required' => 'La date de début est obligatoire.',
            'debut.date' => 'La date de début n\'est pas valide.',
            'fin.date' => 'La date de fin n\'est pas valide.',
            'fin.after' => 'La fin doit être après le début.',
            'description.max' => 'La description ne doit pas dépasser 2000 caractères.',
        ];
    }
}
