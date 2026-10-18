<?php

namespace App\Services;

use App\Services\Ai\SignalementAiClient;

class SignalementClassifier
{
    private const CATEGORIES = [
        'autre',
        'fuite',
        'panne_locale',
        'personne_vulnerable',
    ];

    public function __construct(
        private SignalementAiClient $aiClient,
        private SignalementLocalClassifier $localClassifier,
    ) {}

    /**
     * @return array{categorie: string, confiance: int}
     */
    public function classer(string $description): array
    {
        $prediction = $this->aiClient->classify($description);
        $category = $prediction['categorie'] ?? null;
        $confidence = $prediction['confiance'] ?? null;

        if (is_string($category)
            && in_array($category, self::CATEGORIES, true)
            && is_numeric($confidence)) {
            return [
                'categorie' => $category,
                'confiance' => max(0, min(100, (int) round((float) $confidence))),
            ];
        }

        return $this->localClassifier->classer($description);
    }
}
