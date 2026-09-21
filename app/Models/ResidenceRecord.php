<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Eloquent mirror of the residences table (owned by the
 * App\Entities\Residence Doctrine entity) so Eloquent relations (User::residence)
 * can join/eager-load it without writing through this model.
 */
class ResidenceRecord extends Model
{
    protected $table = 'residences';

    public $timestamps = true;

    protected $guarded = ['*'];

    public function quartier()
    {
        return $this->belongsTo(QuartierRecord::class, 'quartier_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'residence_id');
    }
}
