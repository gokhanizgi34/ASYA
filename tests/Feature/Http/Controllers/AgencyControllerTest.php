<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Agency;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_agency_list(): void
    {
        $this->get(route('agencies.index'))->assertRedirect(route('login'));
    }

    public function test_editor_cannot_view_agencies(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('agencies.index'))->assertForbidden();
    }

    public function test_system_administrator_can_view_all_agencies_safely(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        Agency::factory()->create(['name' => '<script>alert(1)</script>', 'slug' => 'guvenli-ajans']);

        $this->actingAs($administrator)->get(route('agencies.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_agency_owner_sees_only_own_agency(): void
    {
        $ownAgency = Agency::factory()->create(['name' => 'Kendi Ajansı']);
        $otherAgency = Agency::factory()->create(['name' => 'Başka Ajans']);
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();

        $this->actingAs($owner)->get(route('agencies.index'))
            ->assertOk()
            ->assertSee('Kendi Ajansı')
            ->assertDontSee('Başka Ajans');

        $this->actingAs($owner)->get(route('agencies.edit', $otherAgency))->assertForbidden();
    }

    public function test_system_administrator_can_create_agency(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->post(route('agencies.store'), [
            'name' => '  Anadolu Haber Ajansı  ',
            'contact_email' => 'ILETISIM@ASYA.LOCAL',
            'phone' => '0555 111 22 33',
            'province' => 'İstanbul',
            'district' => 'Başakşehir',
            'category_name' => 'Başakşehir Haberleri',
            'subscription_ends_at' => '2027-12-31',
            'is_active' => '1',
        ])->assertRedirect(route('agencies.index'));

        $this->assertDatabaseHas('agencies', [
            'name' => 'Anadolu Haber Ajansı',
            'slug' => 'anadolu-haber-ajansi',
            'contact_email' => 'iletisim@asya.local',
            'is_active' => true,
        ]);
    }

    public function test_new_agency_gets_trial_dates_and_unexpired_pending_news(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $sourceAgency = Agency::factory()->create();
        RawNewsItem::factory()->for($sourceAgency)->create([
            'status' => RawNewsStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($administrator)->post(route('agencies.store'), [
            'name' => 'Yeni Ajans',
            'province' => 'Ankara',
            'district' => 'Çankaya',
            'category_name' => 'Çankaya Haberleri',
            'is_active' => '1',
        ])->assertRedirect(route('agencies.index'));

        $agency = Agency::query()->where('name', 'Yeni Ajans')->firstOrFail();
        $this->assertSame(today()->toDateString(), $agency->subscription_starts_at->toDateString());
        $this->assertSame(today()->addDays(2)->toDateString(), $agency->trial_ends_at->toDateString());
        $this->assertDatabaseHas('raw_news_items', [
            'agency_id' => $agency->id,
            'status' => RawNewsStatus::Pending->value,
            'original_title' => RawNewsItem::query()->where('agency_id', $sourceAgency->id)->value('original_title'),
        ]);
    }

    public function test_agency_owner_cannot_create_agency_but_can_update_own(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->get(route('agencies.create'))->assertForbidden();
        $this->actingAs($owner)->post(route('agencies.store'), [
            'name' => 'Yetkisiz Ajans',
            'is_active' => '1',
        ])->assertForbidden();

        $this->actingAs($owner)->put(route('agencies.update', $agency), [
            'name' => 'Güncellenen Ajans',
            'contact_email' => 'yeni@asya.local',
            'phone' => '',
            'subscription_ends_at' => '',
        ])->assertRedirect(route('agencies.index'));

        $this->assertSame('Güncellenen Ajans', $agency->fresh()->name);
    }
}
