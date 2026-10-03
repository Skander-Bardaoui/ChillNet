<?php

namespace App\Http\Requests\Auth;

use App\Enums\ProfilVulnerabilite;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

/**
 * Inscription d'un foyer.
 *
 * Le rôle se choisit entre `habitant` et `gestionnaire` (l'`admin` est créé
 * uniquement par seeder / back-office, jamais depuis le formulaire public).
 *
 * Deux parcours distincts :
 *  - **habitant** : il pose un point sur la carte (latitude/longitude) qui
 *    devient son premier « lieu » personnel. Aucun quartier, aucune résidence
 *    à choisir — le quartier reste interne et sera déduit de la position.
 *  - **gestionnaire** : il rattache obligatoirement la résidence qu'il gère,
 *    soit `existante` (déjà référencée), soit `nouvelle` (déclarée par le
 *    gestionnaire, avec son quartier existant ou créé à la volée).
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
     * Normalise la requête avant validation : le rôle a une valeur par défaut,
     * et les champs du parcours non retenu sont neutralisés (un formulaire HTML
     * peut envoyer des champs masqués).
     */
    protected function prepareForValidation(): void
    {
        // Rôle par défaut (compatibilité avec les anciens payloads de test) :
        // un habitant simple.
        if (! $this->filled('role')) {
            $this->merge(['role' => Role::Habitant->value]);
        }

        // L'habitant décrit son foyer par un point géolocalisé, pas par une
        // résidence : on neutralise tout le bloc résidence/quartier.
        if ($this->input('role') === Role::Habitant->value) {
            $this->merge([
                'residence_mode' => null,
                'residence_id' => null,
                'nouvelle_residence_nom' => null,
                'nouvelle_residence_adresse' => null,
                'quartier_id' => null,
                'nouveau_quartier_nom' => null,
                'nouveau_quartier_ville' => null,
                'nouveau_quartier_code_postal' => null,
            ]);

            return;
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

        // Le point géolocalisé du lieu est exigé pour un habitant.
        $habitant = fn (): bool => $this->input('role') === Role::Habitant->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // Inscription publique : habitant ou gestionnaire uniquement.
            // L'admin est créé via seeder / back-office, jamais ici.
            'role' => ['required', Rule::in([Role::Habitant->value, Role::Gestionnaire->value])],

            // Habitant : premier lieu géolocalisé (point sur la carte).
            'lieu_nom' => ['nullable', 'string', 'min:2', 'max:120'],
            'lieu_adresse' => ['nullable', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', Rule::requiredIf($habitant)],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', Rule::requiredIf($habitant)],

            // Gestionnaire : rattachement obligatoire à une résidence.
            'residence_mode' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('role') === Role::Gestionnaire->value),
                Rule::in($this->input('role') === Role::Gestionnaire->value
                    ? [self::MODE_EXISTANTE, self::MODE_NOUVELLE]
                    : self::MODES),
            ],

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

            // Profil de vulnérabilité du foyer (facultatif) : personnalise les
            // messages de vigilance canicule.
            'profil_vulnerabilites' => ['nullable', 'array'],
            'profil_vulnerabilites.*' => ['string', Rule::in(ProfilVulnerabilite::persistables())],
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

            'role.required' => 'Veuillez choisir votre profil (habitant ou gestionnaire).',
            'role.in' => 'Le profil choisi est invalide. L\'administrateur est créé par l\'équipe ChillNet.',

            'residence_mode.required' => 'Veuillez indiquer votre résidence (ou choisir « plus tard »).',
            'residence_mode.in' => 'Le choix de résidence est invalide.',

            'lieu_nom.min' => 'Le nom du lieu est trop court.',
            'lieu_nom.max' => 'Le nom du lieu est trop long.',
            'latitude.required' => 'Posez votre point sur la carte (ou utilisez « Me localiser »).',
            'latitude.numeric' => 'La latitude doit être un nombre.',
            'latitude.between' => 'La latitude doit être comprise entre -90 et 90.',
            'longitude.required' => 'Posez votre point sur la carte (ou utilisez « Me localiser »).',
            'longitude.numeric' => 'La longitude doit être un nombre.',
            'longitude.between' => 'La longitude doit être comprise entre -180 et 180.',

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
