<?php

namespace App\Http\Requests;

use App\Enums\CriticiteEquipement;
use App\Models\EquipementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation pour déclarer / modifier un équipement sensible (front habitant).
 *
 * Règle conditionnelle : le contact d'urgence est obligatoire si le type
 * d'équipement est médical OU si la criticité est "vitale".
 *
 * Module 4 — Ghazi.
 */
class StoreEquipementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHabitant() ?? false;
    }

    public function rules(): array
    {
        return [
            'equipement_type_id' => ['required', 'integer', 'exists:equipement_types,id'],
            'nom'                => ['required', 'string', 'max:255'],
            'criticite'          => ['required', Rule::enum(CriticiteEquipement::class)],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'contact_urgence'    => [
                $this->contactObligatoire() ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'equipement_type_id.required' => 'Choisissez un type d\'équipement.',
            'equipement_type_id.exists'   => 'Type d\'équipement invalide.',
            'nom.required'                => 'Donnez un nom à votre équipement.',
            'criticite.required'          => 'La criticité est obligatoire.',
            'contact_urgence.required'    => 'Le contact d\'urgence est obligatoire pour un équipement médical ou vital.',
        ];
    }

    /**
     * Le contact d'urgence est requis si le type est médical ou criticité vitale.
     */
    private function contactObligatoire(): bool
    {
        $criticite = CriticiteEquipement::tryFrom((string) $this->input('criticite'));

        if ($criticite === CriticiteEquipement::Vitale) {
            return true;
        }

        $typeId = (int) $this->input('equipement_type_id');

        if ($typeId > 0) {
            return (bool) EquipementType::find($typeId)?->medical;
        }

        return false;
    }
}
