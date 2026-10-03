<?php

namespace App\Models;

use App\Enums\ProfilVulnerabilite;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Utilisateur ChillNet — modèle Laravel par défaut (Illuminate\Foundation\Auth\User)
 * conservé et étendu pour le projet :
 *  - authentification JWT (access token + refresh token) via JWTSubject,
 *  - rôle applicatif (Habitant / Gestionnaire / Admin),
 *  - rattachement optionnel à une résidence.
 */
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Identifiant utilisé comme sujet (`sub`) dans les claims JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Claims personnalisés ajoutés aux jetons émis pour cet utilisateur.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role?->value,
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'residence_id',
        'profil_vulnerabilites',
    ];

    /**
     * Résidence à laquelle le foyer est rattaché.
     *
     * @return BelongsTo<Residence, $this>
     */
    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class, 'residence_id');
    }

    /**
     * Lieux personnels du foyer (Domicile, Travail…). Remplacent le quartier
     * dans l'espace habitant : chaque lieu est géolocalisé et se voit
     * auto-assigner le quartier le plus proche (usage interne).
     *
     * @return HasMany<Lieu, $this>
     */
    public function lieux(): HasMany
    {
        return $this->hasMany(Lieu::class, 'user_id');
    }

    /**
     * Lieu principal du foyer : celui marqué `est_principal`, sinon le premier
     * déclaré. `null` tant que l'habitant n'a pas posé de lieu.
     */
    public function lieuPrincipal(): ?Lieu
    {
        return $this->lieux()->where('est_principal', true)->first()
            ?? $this->lieux()->orderBy('id')->first();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isGestionnaire(): bool
    {
        return $this->role === Role::Gestionnaire;
    }

    public function isHabitant(): bool
    {
        return $this->role === Role::Habitant;
    }

    /**
     * Le foyer peut-il gérer les quartiers / résidences ?
     */
    public function canManageResidences(): bool
    {
        return $this->isAdmin() || $this->isGestionnaire();
    }

    /**
     * Profils de vulnérabilité du foyer (personne âgée, enfant, équipement
     * médical…). Sert à personnaliser le message d'alerte canicule.
     *
     * @return array<int, ProfilVulnerabilite>
     */
    public function profilsVulnerabilite(): array
    {
        $valeurs = $this->profil_vulnerabilites ?? [];

        return array_values(array_filter(array_map(
            fn (string $valeur): ?ProfilVulnerabilite => ProfilVulnerabilite::tryFrom($valeur),
            is_array($valeurs) ? $valeurs : [],
        )));
    }

    public function aUnProfilVulnerable(): bool
    {
        return $this->profilsVulnerabilite() !== [];
    }

    /**
     * Profil dominant (le premier renseigné), ou `Standard` si aucun.
     */
    public function profilPrincipal(): ProfilVulnerabilite
    {
        return $this->profilsVulnerabilite()[0] ?? ProfilVulnerabilite::Standard;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'profil_vulnerabilites' => 'array',
        ];
    }
}
