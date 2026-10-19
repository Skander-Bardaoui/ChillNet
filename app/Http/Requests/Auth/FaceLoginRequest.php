<?php

namespace App\Http\Requests\Auth;

use App\Rules\FaceCaptureImage;
use App\Support\FaceImage;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par reconnaissance faciale : email + capture webcam.
 *
 * Le limiteur est distinct de celui du mot de passe (préfixe `face-login|`)
 * pour qu'une série d'échecs biométriques ne verrouille pas la connexion
 * classique, qui reste le moyen de secours.
 */
class FaceLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'image' => FaceImage::normalize((string) $this->input('image')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'image' => ['required', 'string', 'max:4000000', new FaceCaptureImage],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function hit(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    public function clear(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return 'face-login|'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
