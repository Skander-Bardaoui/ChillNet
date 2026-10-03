<?php

namespace App\Http\Requests;

use App\Enums\LieuType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création / mise à jour d'un lieu personnel de l'habitant (Domicile, Travail…).
 *
 * Un lieu sans point est inutile : latitude et longitude sont obligatoires
 * (l'indicateur carte de l'espace habitant en dépend). Le quartier, lui, n'est
 * jamais saisi : il est déduit automatiquement (le plus proche) par le modèle.
 */
class StoreLieuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge(['type' => LieuType::Domicile->value]);
        }

        if (! $this->filled('nom')) {
            $this->merge(['nom' => 'Domicile']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', Rule::in(LieuType::valeurs())],
            'adresse' => ['nullable', 'string', 'min:3', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Donnez un nom à ce lieu (ex. : Domicile, Travail).',
            'nom.min' => 'Le nom du lieu est trop court.',
            'type.in' => 'Le type de lieu est invalide.',
            'latitude.required' => 'Posez le point de ce lieu sur la carte.',
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.required' => 'Posez le point de ce lieu sur la carte.',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',
        ];
    }
}
