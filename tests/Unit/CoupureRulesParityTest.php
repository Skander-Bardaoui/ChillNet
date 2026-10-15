<?php

namespace Tests\Unit;

use App\Enums\NiveauRisque;
use App\Services\CoupureRiskAiService;
use Tests\TestCase;

/**
 * Parité stricte des règles PHP ↔ Python (module 2 — coupures).
 *
 * La fixture partagée `tests/fixtures/coupure_rules_cases.json` est la SOURCE DE
 * VÉRITÉ UNIQUE : le test Python (ai-service/tests/test_coupures_rules.py) la
 * lit aussi. Toute divergence rompt la chaîne de sécurité (plancher Python +
 * repli Laravel).
 */
class CoupureRulesParityTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function cas(): array
    {
        $chemin = base_path('tests/fixtures/coupure_rules_cases.json');

        $this->assertFileExists($chemin, 'Fixture de parité absente.');
        $data = json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        return $data['cases'] ?? [];
    }

    public function test_les_regles_php_concordent_avec_la_fixture_partagee(): void
    {
        $cases = $this->cas();
        $this->assertGreaterThanOrEqual(15, count($cases), 'La fixture doit contenir au moins 15 cas.');

        $ia = app(CoupureRiskAiService::class);

        foreach ($cases as $case) {
            $resultat = $ia->scoreDepuisContexte([
                'frequence' => $case['frequence'],
                'en_cours' => $case['en_cours'],
                'chaudes' => $case['chaudes'],
                'confirmations' => $case['confirmations'],
                'niveau_chaleur' => $case['niveau_chaleur'],
                'temperature' => $case['temperature'],
            ]);

            $contexte = json_encode($case, JSON_UNESCAPED_UNICODE);

            $this->assertSame($case['expected_score'], $resultat['score'], 'score : '.$contexte);
            $this->assertSame($case['expected_niveau'], $resultat['niveau']->value, 'niveau : '.$contexte);
            $this->assertSame($case['expected_facteurs'], $resultat['facteurs'], 'facteurs : '.$contexte);
            $this->assertSame($case['expected_raison'], $resultat['raison'], 'raison : '.$contexte);
        }
    }

    public function test_la_fixture_couvre_les_quatre_niveaux(): void
    {
        $attendus = array_values(array_unique(array_column($this->cas(), 'expected_niveau')));
        sort($attendus);

        $this->assertSame(['critique', 'eleve', 'faible', 'modere'], $attendus);
    }

    public function test_les_paliers_de_risque_sont_inchanges(): void
    {
        $this->assertSame(NiveauRisque::Faible, NiveauRisque::fromScore(0));
        $this->assertSame(NiveauRisque::Modere, NiveauRisque::fromScore(25));
        $this->assertSame(NiveauRisque::Eleve, NiveauRisque::fromScore(50));
        $this->assertSame(NiveauRisque::Critique, NiveauRisque::fromScore(75));
    }
}
