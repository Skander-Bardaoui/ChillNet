<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuartierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'min:2', 'max:120'],
            'ville' => ['required', 'string', 'min:2', 'max:120'],
            'code_postal' => ['required', 'string', 'regex:/^[0-9A-Za-z\- ]{3,10}$/'],
            // Coordonnées Leaflet : optionnelles (quartier déclaré par un habitant),
            // mais si renseignées elles doivent être dans les bornes valides.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du quartier est obligatoire.',
            'ville.required' => 'La ville est obligatoire.',
            'code_postal.required' => 'Le code postal est obligatoire.',
            'code_postal.regex' => 'Le code postal saisi n\'est pas valide.',
            'latitude.numeric' => 'La latitude doit être un nombre (ex. : 36.80).',
            'latitude.between' => 'La latitude doit être entre -90 et 90.',
            'longitude.numeric' => 'La longitude doit être un nombre (ex. : 10.18).',
            'longitude.between' => 'La longitude doit être entre -180 et 180.',
        ];
    }
}
