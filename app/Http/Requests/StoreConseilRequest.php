<?php

namespace App\Http\Requests;

use App\Enums\CategorieConseil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation pour créer / modifier un conseil (back office admin).
 * Module 4 — Ghazi.
 */
class StoreConseilRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isAdmin() || $user?->isGestionnaire();
    }

    public function rules(): array
    {
        return [
            'titre'               => ['required', 'string', 'max:255'],
            'categorie'           => ['required', Rule::enum(CategorieConseil::class)],
            'contenu'             => ['required', 'string', 'max:5000'],
            'icone'               => ['nullable', 'string', 'max:100'],
            'actif'               => ['boolean'],
            'ordre'               => ['nullable', 'integer', 'min:0'],
            'equipement_type_ids' => ['nullable', 'array'],
            'equipement_type_ids.*' => ['integer', 'exists:equipement_types,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required'    => 'Le titre est obligatoire.',
            'categorie.required'=> 'La catégorie est obligatoire.',
            'contenu.required'  => 'Le contenu est obligatoire.',
        ];
    }
}
