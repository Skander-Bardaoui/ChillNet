<?php

namespace App\Http\Requests\Profile;

use App\Rules\FaceCaptureImage;
use App\Support\FaceImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Activation ou remplacement de la connexion par visage.
 *
 * Exige le consentement explicite (donnée biométrique) et une confirmation
 * du mot de passe : un appareil laissé déverrouillé ne suffit pas à enrôler
 * un visage.
 */
class UpdateFaceEnrollmentRequest extends FormRequest
{
    protected $errorBag = 'faceEnrollment';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'image' => FaceImage::normalize((string) $this->input('image')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'string', 'max:4000000', new FaceCaptureImage],
            'consent' => ['accepted'],
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
