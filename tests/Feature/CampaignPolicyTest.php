<?php

namespace Tests\Feature;

use App\CampaignStatus;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\User;
use App\Policies\CampaignPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_tenant_matrix_protects_campaign_mutations(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $administrator = User::factory()->systemAdministrator()->create();
        $otherOwner = User::factory()->agencyOwner()->for($otherAgency)->create();
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create();
        $policy = new CampaignPolicy;

        $this->assertTrue($policy->view($editor, $campaign));
        $this->assertTrue($policy->update($editor, $campaign));
        $this->assertFalse($policy->changeStatus($editor, $campaign));
        $this->assertTrue($policy->changeStatus($owner, $campaign));
        $this->assertTrue($policy->delete($owner, $campaign));
        $this->assertFalse($policy->view($otherOwner, $campaign));
        $this->assertTrue($policy->view($administrator, $campaign));

        $campaign->update(['status' => CampaignStatus::Active]);
        $this->assertFalse($policy->update($owner, $campaign));

        $campaign->update(['status' => CampaignStatus::Completed]);
        $this->assertFalse($policy->update($owner, $campaign));
        $this->assertFalse($policy->delete($owner, $campaign));
        $this->assertFalse($policy->forceDelete($administrator, $campaign));
    }
}
