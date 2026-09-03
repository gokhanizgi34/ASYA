<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_user_list(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_editor_cannot_manage_users(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('users.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('users.create'))->assertForbidden();
    }

    public function test_agency_owner_sees_only_users_in_own_agency(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        User::factory()->editor()->for($ownAgency)->create(['name' => 'Kendi Editörü']);
        $otherEditor = User::factory()->editor()->for($otherAgency)->create(['name' => 'Başka Editör']);

        $this->actingAs($owner)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Kendi Editörü')
            ->assertDontSee('Başka Editör');
        $this->actingAs($owner)->get(route('users.edit', $otherEditor))->assertForbidden();
    }

    public function test_system_administrator_can_view_user_list_safely(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($administrator)->get(route('users.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_user_create_screen_has_one_unrestricted_password_field(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->get(route('users.create'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false)
            ->assertSee('İstediğiniz uzunluk ve biçimde parola girebilirsiniz.');
    }

    public function test_empty_user_password_has_a_clear_turkish_message(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create();

        $this->actingAs($administrator)->post(route('users.store'), [
            'agency_id' => $agency->id,
            'name' => 'Yeni Kullanıcı',
            'email' => 'yeni-kullanici@asya.local',
            'password' => '',
            'role' => UserRole::Editor->value,
            'is_active' => '1',
        ])->assertSessionHasErrors(['password' => 'Parola alanı zorunludur.']);

        $this->assertDatabaseMissing('users', ['email' => 'yeni-kullanici@asya.local']);
    }

    public function test_system_administrator_can_create_user_for_agency(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create();

        $this->actingAs($administrator)->post(route('users.store'), [
            'agency_id' => $agency->id,
            'name' => '  Yeni Editör  ',
            'email' => 'EDITOR@ASYA.LOCAL',
            'password' => '1',
            'role' => UserRole::Editor->value,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'editor@asya.local')->firstOrFail();
        $this->assertSame('Yeni Editör', $user->name);
        $this->assertTrue($user->agency->is($agency));
        $this->assertSame(UserRole::Editor, $user->role);
        $this->assertTrue(Hash::check('1', $user->password));
    }

    public function test_non_administrator_role_requires_active_agency(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $inactiveAgency = Agency::factory()->inactive()->create();

        $this->actingAs($administrator)->post(route('users.store'), [
            'agency_id' => $inactiveAgency->id,
            'name' => 'Yeni Kullanıcı',
            'email' => 'yeni@asya.local',
            'password' => 'GuvenliParola123',
            'password_confirmation' => 'GuvenliParola123',
            'role' => UserRole::Editor->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('agency_id');
    }

    public function test_agency_owner_can_only_create_editor_in_own_agency(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();

        $this->actingAs($owner)->post(route('users.store'), [
            'agency_id' => $otherAgency->id,
            'name' => 'Ajans Editörü',
            'email' => 'ajans-editoru@asya.local',
            'password' => 'GuvenliParola123',
            'password_confirmation' => 'GuvenliParola123',
            'role' => UserRole::AgencyOwner->value,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $created = User::query()->where('email', 'ajans-editoru@asya.local')->firstOrFail();
        $this->assertSame($ownAgency->id, $created->agency_id);
        $this->assertSame(UserRole::Editor, $created->role);
    }

    public function test_system_administrator_can_update_user_without_changing_password(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create();
        $user = User::factory()->editor()->for($agency)->create(['password' => 'EskiGuvenli123']);
        $oldPasswordHash = $user->password;

        $this->actingAs($administrator)->put(route('users.update', $user), [
            'agency_id' => $agency->id,
            'name' => 'Güncel Kullanıcı',
            'email' => 'guncel@asya.local',
            'password' => '',
            'password_confirmation' => '',
            'role' => UserRole::AgencyOwner->value,
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame(UserRole::AgencyOwner, $user->role);
        $this->assertSame($oldPasswordHash, $user->password);
    }

    public function test_administrator_cannot_change_own_role(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->put(route('users.update', $administrator), [
            'agency_id' => '',
            'name' => $administrator->name,
            'email' => $administrator->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => UserRole::Editor->value,
        ])->assertSessionHasErrors(['role', 'agency_id']);

        $this->assertSame(UserRole::SystemAdministrator, $administrator->fresh()->role);
    }
}
