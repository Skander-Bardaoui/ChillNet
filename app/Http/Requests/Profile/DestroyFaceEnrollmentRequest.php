<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Suppression de l'image de référence et désactivation de la connexion
 * par visage, confirmées par le mot de passe.
 */
class DestroyFaceEnrollmentRequest extends FormRequest
{
    protected $errorBag = 'faceEnrollment';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
