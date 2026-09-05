<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_complete_usage_guide(): void
    {
        $user = User::factory()->editor()->create();
        $response = $this->actingAs($user)->get(route('faq.index'));
        $response->assertOk()->assertSee('ASYA nasıl kullanılır?')->assertSee('31 ana bölüm')->assertSee('API Entegrasyonları')->assertSee('Ajanslar ve Kullanıcılar');
        $this->assertSame(31, substr_count($response->getContent(), 'group rounded-2xl'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('faq.index'))->assertRedirect(route('login'));
    }
}
