<?php

namespace App\Http\Requests;

use App\Enums\Affluence;
use App\Models\Avis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dépôt / modification d'un avis sur un point de fraîcheur.
 *
 *  - note entière de 1 à 5 ;
 *  - commentaire OBLIGATOIRE (10 caractères min.) si la note est ≤ 2 :
 *    une mauvaise note doit être justifiée pour être utile à la modération ;
 *  - un seul avis par habitant et par point (il modifie le sien sinon).
 */
class StoreAvisRequest extends FormRequest
{
    /** Note à partir de laquelle (incluse) le commentaire devient obligatoire. */
    public const NOTE_COMMENTAIRE_OBLIGATOIRE = 2;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => [
                Rule::requiredIf(fn (): bool => is_numeric($this->input('note'))
                    && (int) $this->input('note') <= self::NOTE_COMMENTAIRE_OBLIGATOIRE),
                'nullable', 'string', 'min:10', 'max:1000',
            ],
            'affluence' => ['nullable', Rule::enum(Affluence::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // En création uniquement : un avis par habitant et par point.
            $point = $this->route('point');

            if ($point && Avis::where('point_fraicheur_id', $point->id)->where('user_id', $this->user()?->id)->exists()) {
                $validator->errors()->add('note', 'Vous avez déjà donné votre avis sur ce point : modifiez-le plutôt.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Choisissez une note de 1 à 5.',
            'note.integer' => 'La note doit être un nombre entier.',
            'note.between' => 'La note doit être comprise entre 1 et 5.',
            'commentaire.required' => 'Un commentaire est obligatoire pour une note de 1 ou 2 : expliquez ce qui n\'allait pas.',
            'commentaire.min' => 'Le commentaire doit contenir au moins 10 caractères.',
            'commentaire.max' => 'Le commentaire ne doit pas dépasser 1000 caractères.',
            'affluence.enum' => 'L\'affluence choisie est invalide.',
        ];
    }
}
