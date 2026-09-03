<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthorizationMatrix;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationMatrixControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_system_administrator_can_view_authorization_matrix(): void
    {
        $owner = User::factory()->agencyOwner()->create();
        $editor = User::factory()->editor()->create();

        $this->get(route('authorization-matrix'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('authorization-matrix'))->assertForbidden();
        $this->actingAs($editor)->get(route('authorization-matrix'))->assertForbidden();
    }

    public function test_administrator_sees_roles_modules_and_live_permissions(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->get(route('authorization-matrix'))
            ->assertOk()
            ->assertSee('Yetki Matrisi')
            ->assertSee('Sistem Yöneticisi')
            ->assertSee('Ajans Sahibi')
            ->assertSee('Editör')
            ->assertSee('Veritabanı Yedekleri')
            ->assertSee('Kara Liste');
    }

    public function test_matrix_uses_actual_policy_decisions_for_sensitive_modules(): void
    {
        $rows = collect(app(AuthorizationMatrix::class)->rows())->keyBy('key');
        $backups = $rows->get('backups');
        $users = $rows->get('users');
        $articles = $rows->get('articles');

        $this->assertTrue($backups['permissions'][UserRole::SystemAdministrator->value]['viewAny']);
        $this->assertFalse($backups['permissions'][UserRole::AgencyOwner->value]['viewAny']);
        $this->assertFalse($backups['permissions'][UserRole::Editor->value]['create']);
        $this->assertTrue($users['permissions'][UserRole::AgencyOwner->value]['create']);
        $this->assertFalse($users['permissions'][UserRole::Editor->value]['create']);
        $this->assertTrue($articles['permissions'][UserRole::Editor->value]['update']);
    }
}
