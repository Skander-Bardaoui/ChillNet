<?php

namespace Tests\Unit;

use App\Console\Commands\AiExportFraicheur;
use App\Enums\SantePoint;
use App\Services\SantePointService;
use Tests\TestCase;

/**
 * Parité stricte des règles PHP ↔ Python (module 3 — santé des points de fraîcheur).
 *
 * La fixture partagée `tests/fixtures/fraicheur_rules_cases.json` est la SOURCE
 * DE VÉRITÉ UNIQUE : le test Python (ai-service/tests/test_fraicheur_rules.py)
 * la lit aussi. Toute divergence rompt la chaîne de sécurité (plancher Python +
 * repli Laravel). Elle fige aussi l'ordre des colonnes exportées pour
 * l'entraînement.
 */
class FraicheurRulesParityTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        $chemin = base_path('tests/fixtures/fraicheur_rules_cases.json');

        $this->assertFileExists($chemin, 'Fixture de parité absente.');

        return json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_les_regles_php_concordent_avec_la_fixture_partagee(): void
    {
        $cases = $this->fixture()['cases'];
        $this->assertGreaterThanOrEqual(15, count($cases), 'La fixture doit contenir au moins 15 cas.');

        $sante = app(SantePointService::class);

        foreach ($cases as $case) {
            $resultat = $sante->scoreDepuisContexte($case);
            $contexte = json_encode($case, JSON_UNESCAPED_UNICODE);

            $this->assertSame($case['expected_score'], $resultat['score'], 'score : '.$contexte);
            $this->assertSame($case['expected_niveau'], $resultat['niveau']->value, 'niveau : '.$contexte);
            $this->assertSame($case['expected_facteurs'], $resultat['facteurs'], 'facteurs : '.$contexte);
            $this->assertSame($case['expected_actions'], $resultat['actions'], 'actions : '.$contexte);
            $this->assertSame($case['expected_raison'], $resultat['raison'], 'raison : '.$contexte);
        }
    }

    public function test_la_fixture_couvre_les_quatre_niveaux(): void
    {
        $attendus = array_values(array_unique(array_column($this->fixture()['cases'], 'expected_niveau')));
        sort($attendus);

        $this->assertSame(['a_surveiller', 'bon', 'critique', 'degrade'], $attendus);
    }

    public function test_l_export_suit_l_ordre_des_features_python(): void
    {
        $this->assertSame($this->fixture()['_colonnes'], AiExportFraicheur::FEATURES);
    }

    public function test_les_paliers_de_sante(): void
    {
        $this->assertSame(SantePoint::Bon, SantePoint::fromScore(24));
        $this->assertSame(SantePoint::ASurveiller, SantePoint::fromScore(25));
        $this->assertSame(SantePoint::Degrade, SantePoint::fromScore(50));
        $this->assertSame(SantePoint::Critique, SantePoint::fromScore(75));
    }
}
