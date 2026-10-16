<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalementAnalyse extends Model
{
    protected $fillable = [
        'signalement_id',
        'score_urgence',
        'urgence_suggeree',
        'indicateurs_urgence',
        'termes_urgents',
        'signalements_lies',
        'empreinte_description',
        'version_modele',
        'analysee_le',
    ];

    protected function casts(): array
    {
        return [
            'indicateurs_urgence' => 'array',
            'termes_urgents' => 'array',
            'signalements_lies' => 'array',
            'analysee_le' => 'datetime',
        ];
    }

    public function signalement(): BelongsTo
    {
        return $this->belongsTo(Signalement::class);
    }
}
