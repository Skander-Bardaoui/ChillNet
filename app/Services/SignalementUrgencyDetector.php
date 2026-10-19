<?php

namespace App\Services;

use Illuminate\Support\Str;

class SignalementUrgencyDetector
{
    /**
     * @return list<string>
     */
    public function detecter(string $description): array
    {
        $texte = Str::ascii(mb_strtolower($description));
        $termesUrgents = [
            'danger' => '/\bdanger\b|\bdangereux(?:se)?s?\b/',
            'personne bloquée' => '/\bpersonne\s+(?:est\s+)?bloque(?:e|es|s)?\b/',
            'personne coincée' => '/\bpersonne\s+(?:est\s+)?coince(?:e|es|s)?\b/',
        ];

        $termesDetectes = [];
        foreach ($termesUrgents as $terme => $motif) {
            if (preg_match($motif, $texte) === 1) {
                $termesDetectes[] = $terme;
            }
        }

        return $termesDetectes;
    }
}
