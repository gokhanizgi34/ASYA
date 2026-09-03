<?php

namespace Tests\Feature;

use App\HoroscopeStatus;
use App\Models\Agency;
use App\Models\HoroscopeForecast;
use App\Models\User;
use App\Services\HoroscopeAiWriter;
use App\ZodiacSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoroscopeForecastControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_creates_day_only_for_own_agency_and_sees_tenant_data(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $this->mock(HoroscopeAiWriter::class)
            ->shouldReceive('write')->once()->andReturn($this->aiForecasts());
        $other = HoroscopeForecast::factory()->for($otherAgency)->create(['sign' => ZodiacSign::Aries, 'forecast_date' => '2026-09-01', 'general' => 'OTHER-TENANT']);

        $this->actingAs($editor)->post(route('horoscopes.day'), ['agency_id' => $otherAgency->id, 'forecast_date' => '2026-09-01'])->assertRedirect();
        $this->assertSame(12, HoroscopeForecast::query()->where('agency_id', $agency->id)->count());

        $this->actingAs($editor)->get(route('horoscopes.index', ['date' => '2026-09-01']))
            ->assertOk()->assertDontSee($other->general);
        $this->actingAs($editor)->get(route('horoscopes.edit', $other))->assertForbidden();
    }

    public function test_missing_ai_integration_returns_a_form_error_instead_of_server_error(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('horoscopes.day'), [
            'agency_id' => $agency->id,
            'forecast_date' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHasErrors('agency_id');
    }

    public function test_editor_can_publish_complete_forecast(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $forecast = HoroscopeForecast::factory()->for($agency)->create(['status' => HoroscopeStatus::Draft]);

        $this->actingAs($editor)->put(route('horoscopes.update', $forecast), $this->payload([
            'status' => HoroscopeStatus::Published->value,
        ]))->assertRedirect();

        $this->assertSame(HoroscopeStatus::Published, $forecast->fresh()->status);
        $this->assertSame($editor->id, $forecast->fresh()->updated_by);
        $this->assertNotNull($forecast->fresh()->published_at);
    }

    public function test_incomplete_forecast_cannot_be_published(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $forecast = HoroscopeForecast::factory()->for($agency)->create();

        $this->actingAs($editor)->put(route('horoscopes.update', $forecast), $this->payload(['general' => 'Kısa']))
            ->assertSessionHasErrors('general');
    }

    public function test_horoscope_output_is_escaped(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $forecast = HoroscopeForecast::factory()->create(['forecast_date' => today(), 'general' => '<script>alert(1)</script>']);

        $this->actingAs($administrator)->get(route('horoscopes.index'))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @return array<string, array<string, int|string>> */
    private function aiForecasts(): array
    {
        return collect(ZodiacSign::cases())->mapWithKeys(fn (ZodiacSign $sign): array => [$sign->value => [
            'general' => $sign->label().' burcu için dengeli kararlar ve sakin iletişim bugün öne çıkabilir.',
            'love' => 'Duyguları açıkça paylaşmak ve dikkatle dinlemek ilişkilerde anlayışı güçlendirebilir.',
            'career' => 'Planlı ilerlemek ve öncelikleri belirlemek iş yaşamındaki adımları kolaylaştırabilir.',
            'money' => 'Günlük harcamaları gözden geçirmek bütçe dengesini korumaya yardımcı olabilir.',
            'health' => 'Dinlenmeye zaman ayırmak ve hafif hareket etmek günlük ritmi destekleyebilir.',
            'lucky_color' => 'Mavi',
            'lucky_number' => 7,
        ]])->all();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'status' => HoroscopeStatus::Draft->value,
            'general' => str_repeat('Bugün dengeli kararlar almak ve iletişimde açık olmak size yeni fırsatlar sunabilir. ', 2),
            'love' => 'Duygularınızı açıkça paylaşmak bağlarınızı güçlendirebilir.',
            'career' => 'Planlı ilerlemek iş hayatında görünür sonuçlar getirebilir.',
            'money' => 'Harcamaları gözden geçirmek bütçenizi dengelemenize yardımcı olabilir.',
            'health' => 'Dinlenme ve düzenli hareket için zaman ayırmanız yararlı olabilir.',
            'lucky_color' => 'Lacivert',
            'lucky_number' => 7,
        ], $overrides);
    }
}
