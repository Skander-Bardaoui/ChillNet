<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Signalement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'residence_id',
        'categorie',
        'categorie_autre',
        'urgence',
        'description',
        'photo_path',
        'statut',
        'date_signalement',
    ];

    protected function casts(): array
    {
        return ['date_signalement' => 'date'];
    }

    public function habitant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }
}