<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:150'],
            'adresse' => ['required', 'string', 'min:5', 'max:255'],
            'quartier_id' => ['required', 'integer', 'exists:quartiers,id'],
            'nombre_logements' => ['required', 'integer', 'min:0', 'max:5000'],
            'salle_climatisee' => ['sometimes', 'boolean'],
            'point_fraicheur' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la résidence est obligatoire.',
            'adresse.required' => 'L\'adresse est obligatoire.',
            'quartier_id.required' => 'Veuillez sélectionner un quartier.',
            'quartier_id.exists' => 'Le quartier sélectionné est invalide.',
            'nombre_logements.integer' => 'Le nombre de logements doit être un nombre entier.',
        ];
    }
}
