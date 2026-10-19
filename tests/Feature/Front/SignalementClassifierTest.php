<?php

namespace Tests\Feature\Front;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SignalementClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_uses_python_category_model_when_available(): void
    {
        config([
            'ai.enabled' => true,
            'ai.service_url' => 'http://ai.test',
            'ai.token' => 'test-token',
        ]);
        Http::fake([
            'ai.test/signalements/classify' => Http::response([
                'categorie' => 'panne_locale',
                'confiance' => 88,
                'probabilites' => ['panne_locale' => 0.88],
            ]),
        ]);
        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $this->actingAs($habitant)
            ->postJson(route('front.signalements.analyze'), [
                'description' => 'La porte électrique ne fonctionne plus.',
            ])
            ->assertOk()
            ->assertJsonPath('categorie', 'panne_locale')
            ->assertJsonPath('confiance', 88);

        Http::assertSent(fn ($request) => str_ends_with(
            $request->url(),
            '/signalements/classify',
        ));
    }

    public function test_analysis_falls_back_to_local_model_for_invalid_remote_category(): void
    {
        config([
            'ai.enabled' => true,
            'ai.service_url' => 'http://ai.test',
        ]);
        Http::fake([
            'ai.test/signalements/classify' => Http::response([
                'categorie' => 'categorie_inconnue',
                'confiance' => 99,
            ]),
        ]);
        $habitant = User::factory()->create(['role' => Role::Habitant]);

        $this->actingAs($habitant)
            ->postJson(route('front.signalements.analyze'), [
                'description' => 'De l’eau coule du plafond et mouille le sol.',
            ])
            ->assertOk()
            ->assertJsonPath('categorie', 'fuite');
    }
}
