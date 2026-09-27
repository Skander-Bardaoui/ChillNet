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
        ];
    }
}
