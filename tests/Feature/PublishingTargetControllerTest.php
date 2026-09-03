<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublishingProtocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishingTargetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_manage_publishing_targets(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->get(route('publishing-targets.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('publishing-targets.store'), $this->payload($agency->id))->assertForbidden();
    }

    public function test_owner_creates_only_own_target_and_credential_is_encrypted_and_hidden(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('publishing-targets.store'), $this->payload($otherAgency->id))->assertRedirect(route('publishing-targets.index'));

        $target = PublishingTarget::query()->firstOrFail();
        $this->assertSame($agency->id, $target->agency_id);
        $this->assertSame('secret-application-password', $target->credential);
        $this->assertArrayNotHasKey('credential', $target->toArray());
        $this->assertNotSame('secret-application-password', (string) $target->getRawOriginal('credential'));
    }

    public function test_blank_credential_on_update_preserves_existing_secret(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create(['credential' => 'existing-secret']);

        $this->actingAs($owner)->put(route('publishing-targets.update', $target), $this->payload($agency->id, ['name' => 'Yeni Ad', 'credential' => '']))->assertRedirect();

        $this->assertSame('existing-secret', $target->fresh()->credential);
        $this->assertSame('Yeni Ad', $target->fresh()->name);
    }

    public function test_private_and_local_addresses_are_rejected(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('publishing-targets.store'), $this->payload($agency->id, ['base_url' => 'http://127.0.0.1']))->assertSessionHasErrors('base_url');
        $this->actingAs($owner)->post(route('publishing-targets.store'), $this->payload($agency->id, ['base_url' => 'http://wordpress.local']))->assertSessionHasErrors('base_url');
        $this->assertDatabaseCount('publishing_targets', 0);
    }

    public function test_same_site_cannot_be_registered_for_another_agency(): void
    {
        $firstAgency = Agency::factory()->create();
        $secondAgency = Agency::factory()->create();
        $firstOwner = User::factory()->agencyOwner()->for($firstAgency)->create();
        $secondOwner = User::factory()->agencyOwner()->for($secondAgency)->create();

        $this->actingAs($firstOwner)->post(route('publishing-targets.store'), $this->payload($firstAgency->id));
        $this->actingAs($secondOwner)->post(route('publishing-targets.store'), $this->payload($secondAgency->id, [
            'name' => 'İkinci hedef',
            'base_url' => 'https://news.example.com/',
        ]))->assertSessionHasErrors('base_url');

        $this->assertDatabaseCount('publishing_targets', 1);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(int $agencyId, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agencyId,
            'name' => 'Ana WordPress',
            'base_url' => 'https://news.example.com',
            'protocol' => PublishingProtocol::WordPressRest->value,
            'username' => 'publisher',
            'credential' => 'secret-application-password',
            'default_author_id' => 1,
            'default_category_ids' => '2,5',
            'default_tag_ids' => '8,11',
            'is_active' => '1',
        ], $overrides);
    }
}
