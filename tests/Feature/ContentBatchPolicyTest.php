<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\ContentBatch;
use App\Models\User;
use App\Policies\ContentBatchPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentBatchPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_enforces_role_tenant_and_lifecycle_matrix(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $administrator = User::factory()->systemAdministrator()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $editor = User::factory()->editor()->for($ownAgency)->create();
        $ownBatch = ContentBatch::factory()->for($ownAgency)->create();
        $otherBatch = ContentBatch::factory()->for($otherAgency)->create();
        $completedBatch = ContentBatch::factory()->for($ownAgency)->completed()->create();
        $policy = new ContentBatchPolicy;

        $this->assertTrue($policy->viewAny($administrator));
        $this->assertTrue($policy->viewAny($owner));
        $this->assertTrue($policy->viewAny($editor));
        $this->assertTrue($policy->create($administrator));
        $this->assertTrue($policy->create($owner));
        $this->assertTrue($policy->create($editor));
        $this->assertTrue($policy->view($administrator, $otherBatch));
        $this->assertTrue($policy->view($owner, $ownBatch));
        $this->assertTrue($policy->view($editor, $ownBatch));
        $this->assertFalse($policy->view($owner, $otherBatch));
        $this->assertFalse($policy->view($editor, $otherBatch));
        $this->assertTrue($policy->update($administrator, $otherBatch));
        $this->assertTrue($policy->update($owner, $ownBatch));
        $this->assertTrue($policy->update($editor, $ownBatch));
        $this->assertFalse($policy->update($owner, $completedBatch));
        $this->assertFalse($policy->delete($administrator, $ownBatch));
        $this->assertFalse($policy->restore($administrator, $ownBatch));
        $this->assertFalse($policy->forceDelete($administrator, $ownBatch));
    }
}
