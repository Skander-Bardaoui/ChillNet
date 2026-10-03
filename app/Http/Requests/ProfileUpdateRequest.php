<?php

namespace App\Http\Requests;

use App\Enums\ProfilVulnerabilite;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Aucune case cochée = aucune clé envoyée : on force un tableau vide
     * pour que l'utilisateur puisse aussi *effacer* son profil de vulnérabilité.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('profil_vulnerabilites')) {
            $this->merge(['profil_vulnerabilites' => []]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'profil_vulnerabilites' => ['nullable', 'array'],
            'profil_vulnerabilites.*' => ['string', Rule::in(ProfilVulnerabilite::persistables())],
        ];
    }
}
