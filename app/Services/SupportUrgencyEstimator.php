<?php

namespace App\Services;

use Illuminate\Support\Str;

class SupportUrgencyEstimator
{
    /**
     * @var array<string, array{label: string, score: int, pattern: string}>
     */
    private const INDICATEURS = [
        'incendie ou feu' => [
            'label' => 'incendie ou feu',
            'score' => 95,
            'pattern' => '/\b(?:incendie|feu|flammes?)\b/',
        ],
        'gaz' => [
            'label' => 'fuite ou odeur de gaz',
            'score' => 95,
            'pattern' => '/\b(?:fuite de gaz|odeur de gaz|gaz qui fuit)\b/',
        ],
        'urgence vitale' => [
            'label' => 'urgence médicale potentielle',
            'score' => 95,
            'pattern' => '/\b(?:ne respire plus|inconscient(?:e)?|perte de connaissance|arret cardiaque|urgence vitale)\b/',
        ],
        'personne bloquee' => [
            'label' => 'personne bloquée ou coincée',
            'score' => 90,
            'pattern' => '/\bpersonne\s+(?:(?:est|reste)\s+)?(?:bloque(?:e|es|s)?|coince(?:e|es|s)?)\b/',
        ],
        'danger' => [
            'label' => 'danger signalé',
            'score' => 75,
            'pattern' => '/\b(?:danger|dangereux|dangereuse|menace|violence)\b/',
        ],
        'malaise' => [
            'label' => 'malaise ou blessure',
            'score' => 80,
            'pattern' => '/\b(?:malaise|blesse(?:e|es|s)?|saigne|saignement)\b/',
        ],
        'personne vulnerable' => [
            'label' => 'personne vulnérable sans aide',
            'score' => 75,
            'pattern' => '/\b(?:personne agee seule|personne vulnerable seule|personne isolee|besoin d aide immediate)\b/',
        ],
        'chaleur dangereuse' => [
            'label' => 'forte chaleur avec symptôme',
            'score' => 85,
            'pattern' => '/\b(?:coup de chaleur|malaise a cause de la chaleur|deshydrate|deshydratation)\b/',
        ],
        'panne ascenseur' => [
            'label' => 'panne d’ascenseur',
            'score' => 60,
            'pattern' => '/\b(?:ascenseur en panne|panne d ascenseur)\b/',
        ],
        'coupure electrique' => [
            'label' => 'coupure d’électricité',
            'score' => 50,
            'pattern' => '/\b(?:coupure(?: de courant| electrique)?|plus d electricite|panne de courant)\b/',
        ],
        'fuite eau' => [
            'label' => 'fuite d’eau',
            'score' => 50,
            'pattern' => '/\b(?:fuite d eau|fuite importante|canalisation fuit|eau coule)\b/',
        ],
        'panne' => [
            'label' => 'panne ou équipement en panne',
            'score' => 40,
            'pattern' => '/\b(?:panne|ne fonctionne plus|casse|defectueux)\b/',
        ],
    ];

    /**
     * @return array{pourcentage: int, niveau: string, indicateurs: list<string>, urgence_immediate: bool}
     */
    public function evaluer(string $message): array
    {
        $texte = Str::ascii(mb_strtolower($message));
        $texte = preg_replace(
            '/\b(?:pas de|aucun|aucune|sans)\s+(?:danger|fuite|panne|malaise|menace|violence|blessure|coupure|incendie)\b/',
            ' ',
            $texte,
        ) ?? $texte;

        $scores = [];
        $indicateurs = [];
        foreach (self::INDICATEURS as $indicateur) {
            if (preg_match($indicateur['pattern'], $texte) === 1) {
                $scores[] = $indicateur['score'];
                $indicateurs[] = $indicateur['label'];
            }
        }

        $pourcentage = $scores === []
            ? 10
            : min(100, max($scores) + (count($scores) - 1) * 3);
        $niveau = match (true) {
            $pourcentage >= 80 => 'Élevée',
            $pourcentage >= 50 => 'Modérée',
            default => 'Faible',
        };

        return [
            'pourcentage' => $pourcentage,
            'niveau' => $niveau,
            'indicateurs' => $indicateurs,
            'urgence_immediate' => $pourcentage >= 90,
        ];
    }
}
