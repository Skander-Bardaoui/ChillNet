<?php

namespace App\Http\Requests;

use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Coupure;
use App\Models\Lieu;
use App\Models\Quartier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Signalement / édition d'une coupure.
 *
 * Deux parcours dans le même formulaire :
 *  - **habitant (front)** : il signale depuis l'un de ses **lieux** (`lieu_id`).
 *    Le quartier interne est déduit du lieu, jamais saisi. Le statut est forcé
 *    à « en cours » par le contrôleur.
 *  - **gestionnaire / admin (back-office)** : il pose **librement un point sur
 *    la carte** ; le quartier est facultatif (déduit du point quand c'est
 *    possible). Au moins une cible — quartier OU point — est exigée.
 */
class StoreCoupureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Les rôles sont déjà vérifiés par le middleware `role:...`
        // sur les routes (habitant en front, admin/gestionnaire en back).
        return true;
    }

    /**
     * Parcours habitant : le ciblage passe par un lieu personnel.
     */
    private function viaLieu(): bool
    {
        return (bool) $this->user()?->isHabitant();
    }

    /**
     * Le front habitant ne cible pas de quartier ni de point : on neutralise
     * ces champs pour qu'ils ne perturbent pas la validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->viaLieu()) {
            $this->merge([
                'quartier_id' => null,
                'latitude' => null,
                'longitude' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Habitant : le signalement porte sur l'un de SES lieux.
        if ($this->viaLieu()) {
            return [
                // Le lieu doit appartenir au foyer (pas de fuite vers un autre).
                'lieu_id' => [
                    'required',
                    'integer',
                    Rule::exists('lieux', 'id')->where('user_id', $this->user()?->id),
                ],
                'lieu' => ['nullable', 'string', 'max:255'],
                'type' => ['required', Rule::in(array_column(TypeCoupure::cases(), 'value'))],
                'debut' => ['required', 'date'],
                'fin' => ['nullable', 'date', 'after:debut'],
                'description' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            // Cible : un quartier existant (facultatif) et/ou un point sur la carte.
            'quartier_id' => ['nullable', 'integer', 'exists:quartiers,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

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
     * Validation métier : ciblage obligatoire (quartier OU point) et
     * anti-chevauchement sur le même endroit (même zone, même rue).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->viaLieu()) {
                // Le chevauchement se juge sur le quartier interne du lieu choisi.
                if ($validator->errors()->hasAny(['lieu_id', 'debut', 'fin'])) {
                    return;
                }

                $lieu = Lieu::where('user_id', $this->user()?->id)->find($this->input('lieu_id'));

                if (! $lieu || ! $lieu->quartier_id) {
                    // Aucune zone interne associée : rien à comparer.
                    return;
                }

                $existe = Coupure::chevauche(
                    (int) $lieu->quartier_id,
                    (string) $this->input('debut'),
                    $this->input('fin'),
                    null,
                    $this->input('lieu'),
                );

                if ($existe) {
                    $validator->errors()->add(
                        'debut',
                        'Une autre coupure (en cours ou prévue) occupe déjà cet endroit (même zone, même rue) sur ce créneau.'
                    );
                }

                return;
            }

            // Ciblage obligatoire : un quartier coché OU un point sur la carte.
            $aGeo = $this->filled('latitude') && $this->filled('longitude');

            if (! $this->filled('quartier_id') && ! $aGeo) {
                $validator->errors()->add(
                    'quartier_id',
                    'Ciblez la coupure : choisissez un quartier ou posez un point sur la carte.'
                );
            }

            // Si les champs de base sont déjà invalides, on ne teste pas le chevauchement.
            if ($validator->errors()->hasAny(['quartier_id', 'debut', 'fin', 'statut', 'latitude', 'longitude'])) {
                return;
            }

            // Les coupures résolues sont de l'historique : elles peuvent chevaucher.
            if ($this->input('statut') === StatutCoupure::Resolue->value) {
                return;
            }

            // Zone de comparaison : le quartier choisi, sinon le plus proche du point.
            $zoneId = $this->filled('quartier_id')
                ? (int) $this->input('quartier_id')
                : Quartier::plusProche(
                    $this->filled('latitude') ? (float) $this->input('latitude') : null,
                    $this->filled('longitude') ? (float) $this->input('longitude') : null,
                )?->id;

            if (! $zoneId) {
                // Aucune zone comparable (aucun quartier géolocalisé) : on laisse passer.
                return;
            }

            // En modification, on ignore la coupure elle-même (route model binding `{coupure}`).
            $ignoreId = $this->route('coupure') ?? $this->route('id');

            $existe = Coupure::chevauche(
                $zoneId,
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
            'lieu_id.required' => 'Choisissez le lieu concerné par la coupure.',
            'lieu_id.exists' => 'Ce lieu ne fait pas partie de votre foyer.',
            'quartier_id.exists' => 'La zone sélectionnée est invalide.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
            'lieu.max' => 'La rue / le lieu ne doit pas dépasser 255 caractères.',
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
