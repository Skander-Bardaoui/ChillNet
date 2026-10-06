<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpConseilsRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_conseils_pages_render(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)->get(route('back.conseils.index'))->assertOk()->assertSee('total');
        $this->actingAs($admin)->get(route('back.conseils.create'))->assertOk();
    }
}
