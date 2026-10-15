<?php

namespace Tests\Feature\Back;

use App\Enums\Role;
use App\Models\Quartier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * UI explicable du module 2 : panneau de risque enrichi (probabilités 24/48 h,
 * badge de désaccord, état « mode règles »), carte des incidents et carte
 * « Performance IA — Coupures » du tableau de bord.
 */
class CoupureAiUiTest extends TestCase
{
    use RefreshDatabase;

    private function quartier(string $nom = 'Centre-Ville'): Quartier
    {
        return Quartier::create([
            'nom' => $nom,
            'ville' => 'Tunis',
            'code_postal' => '1000',
            'latitude' => 36.8,
            'longitude' => 10.18,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    private function serviceIaActive(): void
    {
        config([
            'ai.enabled' => true,
            'ai.service_url' => 'http://ai.test',
            'ai.token' => 'test-token',
            'ai.timeout' => 2.0,
        ]);
    }

    public function test_mode_regles_quand_le_service_est_indisponible(): void
    {
        $this->quartier();

        $this->actingAs($this->admin())
            ->get(route('back.coupures.index'))
            ->assertOk()
            ->assertSee('Risque de coupure par zone')
            ->assertSee('mode règles');
    }

    public function test_panneau_enrichi_quand_le_service_repond(): void
    {
        $this->serviceIaActive();
        $this->quartier();

        Http::fake([
            'ai.test/coupures/risk' => Http::response([
                'results' => [[
                    'quartier_id' => 1,
                    'p24' => 0.4,
                    'p48' => 0.55,
                    'score_modele' => 40,
                    'score_regles' => 30,
                    'score_final' => 40,
                    'niveau_modele' => 'modere',
                    'niveau_regles' => 'modere',
                    'niveau_final' => 'modere',
                    'desaccord' => true,
                    'facteurs' => ['Pic de température 39,5°C'],
                    'raison' => 'Règles : modere — Modèle : modere',
                    'source' => 'modele',
                    'model_version' => '1.0.6',
                ]],
            ]),
            'ai.test/coupures/anomalies' => Http::response(['incidents' => []]),
            'ai.test/coupures/insights' => Http::response([
                'available' => true,
                'model_available' => true,
                'version' => '1.0.6',
                'trained_at' => '2026-10-15',
                'partial_dependence' => [
                    ['temp' => 20, 'p24' => 0.02, 'p48' => 0.04],
                    ['temp' => 30, 'p24' => 0.05, 'p48' => 0.09],
                    ['temp' => 40, 'p24' => 0.12, 'p48' => 0.20],
                ],
                'importances' => [],
                'metrics' => ['model24' => ['roc_auc' => 0.69, 'brier' => 0.056]],
                'evaluation' => [
                    'modele_p24' => ['roc_auc' => 0.69, 'brier' => 0.056],
                    'regles' => ['roc_auc' => 0.68],
                    'modele_niveaux' => ['recall_eleve' => 0.12],
                ],
                'n_snapshots_labelises' => 0,
            ]),
        ]);

        $this->actingAs($this->admin())
            ->get(route('back.coupures.index'))
            ->assertOk()
            ->assertSee('Coupure sous 24 h')
            ->assertSee('IA et règles en désaccord')
            ->assertSee('Corrélation chaleur ↔ coupures');
    }

    public function test_carte_performance_ia_coupures_sur_le_tableau_de_bord(): void
    {
        $this->serviceIaActive();

        Http::fake([
            'ai.test/model-info' => Http::response(['version' => '1.0.0']),
            'ai.test/coupures/insights' => Http::response([
                'available' => true,
                'model_available' => true,
                'version' => '1.0.6',
                'trained_at' => '2026-10-15',
                'partial_dependence' => [],
                'importances' => [],
                'metrics' => ['model24' => ['roc_auc' => 0.69, 'brier' => 0.056]],
                'evaluation' => ['regles' => ['roc_auc' => 0.68]],
                'n_snapshots_labelises' => 3,
            ]),
        ]);

        $this->actingAs($this->admin())
            ->get(route('back.dashboard'))
            ->assertOk()
            ->assertSee('Performance IA — Coupures')
            ->assertSee('1.0.6');
    }
}
