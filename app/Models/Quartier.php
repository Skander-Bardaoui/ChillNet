<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quartier du réseau ChillNet (modèle Eloquent, table `quartiers`).
 */
class Quartier extends Model
{
    protected $table = 'quartiers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'ville',
        'code_postal',
        'description',
    ];

    /**
     * @return HasMany<Residence, $this>
     */
    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class, 'quartier_id');
    }

    /**
     * Libellé complet du quartier (nom + ville + code postal).
     * Le code postal est facultatif pour un quartier déclaré par un habitant.
     */
    public function libelle(): string
    {
        $localisation = $this->code_postal
            ? $this->ville.' · '.$this->code_postal
            : $this->ville;

        return trim(sprintf('%s (%s)', $this->nom, $localisation));
    }
}
