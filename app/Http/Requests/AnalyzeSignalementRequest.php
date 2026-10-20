<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeSignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHabitant() === true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
