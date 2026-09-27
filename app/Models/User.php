<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        ];
    }
}
