<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

/**
 * Inscription d'un foyer.
 *
 * La résidence se choisit selon trois modes :
 *  - `existante` : le foyer sélectionne une résidence déjà référencée ;
 *  - `nouvelle`  : le foyer déclare sa résidence, et éventuellement son quartier
 *                  s'il n'apparaît pas dans la liste ;
 *  - `aucune`    : le foyer renseignera sa résidence plus tard depuis son profil.
 */
class RegisterRequest extends FormRequest
{
    public const MODE_EXISTANTE = 'existante';

    public const MODE_NOUVELLE = 'nouvelle';

    public const MODE_AUCUNE = 'aucune';

    /**
     * Tous les modes acceptés.
     *
     * @var list<string>
     */
    public const MODES = [self::MODE_EXISTANTE, self::MODE_NOUVELLE, self::MODE_AUCUNE];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise la requête avant validation : le mode a une valeur par défaut et
     * les champs du mode non retenu sont neutralisés (un formulaire HTML peut
     * envoyer des champs masqués).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('residence_mode')) {
            $this->merge(['residence_mode' => self::MODE_AUCUNE]);
        }

        $mode = $this->input('residence_mode');

        if ($mode !== self::MODE_EXISTANTE) {
            $this->merge(['residence_id' => null]);
        }

        if ($mode !== self::MODE_NOUVELLE) {
            $this->merge([
                'nouvelle_residence_nom' => null,
                'nouvelle_residence_adresse' => null,
                'nouveau_quartier_nom' => null,
                'nouveau_quartier_ville' => null,
                'nouveau_quartier_code_postal' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modeNouvelle = fn (): bool => $this->input('residence_mode') === self::MODE_NOUVELLE;

        // Un nouveau quartier est exigé uniquement si aucun quartier existant
        // n'a été sélectionné.
        $quartierAAjouter = fn (): bool => $modeNouvelle() && ! $this->filled('quartier_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            'residence_mode' => ['required', Rule::in(self::MODES)],

            // Mode « résidence existante ».
            'residence_id' => [
                'nullable',
                'integer',
                Rule::exists('residences', 'id'),
                Rule::requiredIf(fn (): bool => $this->input('residence_mode') === self::MODE_EXISTANTE),
            ],

            // Mode « nouvelle résidence ».
            'nouvelle_residence_nom' => ['nullable', 'string', 'min:2', 'max:150', Rule::requiredIf($modeNouvelle)],
            'nouvelle_residence_adresse' => ['nullable', 'string', 'min:3', 'max:255'],

            // Rattachement du nouveau domicile : quartier existant...
            'quartier_id' => ['nullable', 'integer', Rule::exists('quartiers', 'id')],

            // ...ou quartier créé à la volée par le foyer.
            'nouveau_quartier_nom' => ['nullable', 'string', 'min:2', 'max:120', Rule::requiredIf($quartierAAjouter)],
            'nouveau_quartier_ville' => ['nullable', 'string', 'min:2', 'max:120', Rule::requiredIf($quartierAAjouter)],
            'nouveau_quartier_code_postal' => ['nullable', 'string', 'regex:/^[0-9A-Za-z\- ]{3,10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du foyer est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail saisie n\'est pas valide.',
            'email.unique' => 'Un compte utilise déjà cette adresse e-mail.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'password.min' => 'Le mot de passe doit contenir au moins :min caractères.',

            'residence_mode.required' => 'Veuillez indiquer votre résidence (ou choisir « plus tard »).',
            'residence_mode.in' => 'Le choix de résidence est invalide.',

            'residence_id.required' => 'Veuillez sélectionner votre résidence dans la liste.',
            'residence_id.exists' => 'La résidence sélectionnée n\'existe plus, choisissez-en une autre.',

            'nouvelle_residence_nom.required' => 'Le nom de votre résidence est obligatoire.',
            'nouvelle_residence_nom.min' => 'Le nom de la résidence est trop court.',

            'quartier_id.exists' => 'Le quartier sélectionné n\'existe plus, choisissez-en un autre.',
            'nouveau_quartier_nom.required' => 'Indiquez le nom de votre quartier.',
            'nouveau_quartier_ville.required' => 'Indiquez la ville de votre quartier.',
            'nouveau_quartier_code_postal.regex' => 'Le code postal saisi n\'est pas valide.',
        ];
    }
}
