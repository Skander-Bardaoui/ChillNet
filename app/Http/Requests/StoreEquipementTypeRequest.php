<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation pour créer / modifier un type d'équipement (back office admin).
 * Module 4 — Ghazi.
 */
class StoreEquipementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('equipement_type')?->id;

        return [
            'nom'         => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('equipement_types', 'slug')->ignore($id)],
            'icone'       => ['nullable', 'string', 'max:100'],
            'medical'     => ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'actif'       => ['boolean'],
            'ordre'       => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'   => 'Le nom du type est obligatoire.',
            'slug.required'  => 'Le slug est obligatoire.',
            'slug.unique'    => 'Ce slug est déjà utilisé par un autre type.',
            'slug.regex'     => 'Le slug ne peut contenir que des lettres minuscules, chiffres et underscores.',
        ];
    }
}
