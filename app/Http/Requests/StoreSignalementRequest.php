<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHabitant() === true;
    }

    public function rules(): array
    {
        return [
            'categorie' => ['required', Rule::in(['fuite', 'panne_locale', 'personne_vulnerable', 'autre'])],
            'categorie_autre' => ['required_if:categorie,autre', 'nullable', 'string', 'max:100'],
            'urgence' => ['required', Rule::in(['normale', 'prioritaire', 'vitale'])],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}