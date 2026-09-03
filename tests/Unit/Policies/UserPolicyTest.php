<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use App\UserRole;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    public function test_system_administrator_can_manage_every_user(): void
    {
        $administrator = new User(['role' => UserRole::SystemAdministrator]);
        $target = new User(['role' => UserRole::AgencyOwner, 'agency_id' => 20]);
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewAny($administrator));
        $this->assertTrue($policy->create($administrator));
        $this->assertTrue($policy->update($administrator, $target));
        $this->assertTrue($policy->updateStatus($administrator, $target));
        $this->assertFalse($policy->delete($administrator, $target));
    }

    public function test_agency_owner_can_manage_only_editors_in_same_agency(): void
    {
        $owner = new User(['role' => UserRole::AgencyOwner, 'agency_id' => 10]);
        $ownEditor = new User(['role' => UserRole::Editor, 'agency_id' => 10]);
        $otherEditor = new User(['role' => UserRole::Editor, 'agency_id' => 20]);
        $otherOwner = new User(['role' => UserRole::AgencyOwner, 'agency_id' => 10]);
        $policy = new UserPolicy;

        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->update($owner, $ownEditor));
        $this->assertFalse($policy->update($owner, $otherEditor));
        $this->assertFalse($policy->update($owner, $otherOwner));
    }

    public function test_editor_cannot_manage_users(): void
    {
        $editor = new User(['role' => UserRole::Editor, 'agency_id' => 10]);
        $target = new User(['role' => UserRole::Editor, 'agency_id' => 10]);
        $policy = new UserPolicy;

        $this->assertFalse($policy->viewAny($editor));
        $this->assertFalse($policy->create($editor));
        $this->assertFalse($policy->update($editor, $target));
        $this->assertFalse($policy->updateStatus($editor, $target));
    }
}
