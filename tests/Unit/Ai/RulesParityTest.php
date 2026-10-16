<?php

namespace Tests\Unit\Ai;

use App\Services\VigilanceAiService;
use Tests\TestCase;

/**
 * Parité stricte des règles PHP ↔ Python.
 *
 * La fixture partagée `tests/fixtures/rules_cases.json` est la SOURCE DE VÉRITÉ
 * UNIQUE : le test Python (ai-service/tests/test_rules_parity.py) la lit aussi.
 * Toute divergence casse la chaîne de sécurité (plancher Python + repli Laravel).
 */
class RulesParityTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function cas(): array
    {
        $chemin = base_path('tests/fixtures/rules_cases.json');

        $this->assertFileExists($chemin, 'Fixture de parité absente.');
        $data = json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        return $data['cases'] ?? [];
    }

    public function test_les_regles_php_concordent_avec_la_fixture_partagee(): void
    {
        $cases = $this->cas();
        $this->assertGreaterThanOrEqual(15, count($cases), 'La fixture doit contenir au moins 15 cas.');

        $ia = app(VigilanceAiService::class);

        foreach ($cases as $case) {
            $niveau = $ia->niveauPour(
                (float) $case['temperature'],
                $case['humidite'] === null ? null : (int) $case['humidite'],
                ['jours_surchauffe' => (int) ($case['jours_surchauffe'] ?? 0)],
                (float) ($case['seuil'] ?? 35.0),
            );

            $this->assertSame(
                $case['expected'],
                $niveau->value,
                'Parité rompue pour '.json_encode($case, JSON_UNESCAPED_UNICODE),
            );
        }
    }

    public function test_la_fixture_couvre_les_trois_niveaux(): void
    {
        $attendus = array_values(array_unique(array_column($this->cas(), 'expected')));
        sort($attendus);

        $this->assertSame(['jaune', 'orange', 'rouge'], $attendus);
    }
}
