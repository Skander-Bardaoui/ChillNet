<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstimateSupportUrgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHabitant() === true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }
}
