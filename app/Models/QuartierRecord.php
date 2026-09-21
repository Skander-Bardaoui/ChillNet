<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Eloquent mirror of the quartiers table (owned by the
 * App\Entities\Quartier Doctrine entity) for relations/joins on the Eloquent side.
 */
class QuartierRecord extends Model
{
    protected $table = 'quartiers';

    public $timestamps = true;

    protected $guarded = ['*'];

    public function residences()
    {
        return $this->hasMany(ResidenceRecord::class, 'quartier_id');
    }
}
