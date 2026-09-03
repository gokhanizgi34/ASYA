<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\HoroscopeForecast;
use App\Models\User;
use App\Services\HoroscopeDayBuilder;
use App\ZodiacSign;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HoroscopeDayBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_uses_ai_to_create_exactly_twelve_idempotent_daily_drafts(): void
    {
        Http::preventStrayRequests();
        $forecasts = collect(ZodiacSign::cases())->map(fn (ZodiacSign $sign): array => [
            'sign' => $sign->value,
            'general' => $sign->label().' burcu için bugün dengeli kararlar ve sakin iletişim öne çıkıyor.',
            'love' => 'Duyguları açık ve nazik biçimde paylaşmak ilişkilerde karşılıklı anlayışı güçlendirebilir.',
            'career' => 'Planlı ilerlemek ve öncelikleri netleştirmek iş yaşamındaki küçük engelleri aşmayı kolaylaştırabilir.',
            'money' => 'Günlük harcamaları gözden geçirmek ve acele kararlardan kaçınmak bütçe dengesini koruyabilir.',
            'health' => 'Dinlenmeye zaman ayırmak, yeterli su içmek ve hafif hareket etmek günün ritmini destekleyebilir.',
            'lucky_color' => 'Mavi',
            'lucky_number' => 7,
        ])->all();
        Http::fake([
            'https://93.184.216.34/v1beta/models/gemini-flash-latest:generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['forecasts' => $forecasts], JSON_UNESCAPED_UNICODE)]]]]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create([
            'base_url' => 'https://93.184.216.34/v1beta/models',
            'model' => 'gemini-flash-latest',
            'credential' => 'gemini-key',
            'is_active' => true,
        ]);
        $date = CarbonImmutable::parse('2026-09-01');

        $first = app(HoroscopeDayBuilder::class)->build($agency->id, $date, $editor);
        $second = app(HoroscopeDayBuilder::class)->build($agency->id, $date, $editor);

        $this->assertCount(12, $first);
        $this->assertCount(12, $second);
        $this->assertDatabaseCount('horoscope_forecasts', 12);
        $this->assertSame(12, HoroscopeForecast::query()->distinct()->count('sign'));
        $this->assertNotNull(HoroscopeForecast::query()->firstOrFail()->general);
        $forecast = HoroscopeForecast::query()->where('sign', ZodiacSign::Gemini)->firstOrFail();
        $this->assertSame('♊', $forecast->symbol);
        $this->assertStringContainsString('İkizler burcu', $forecast->seo_title);
        $this->assertContains('İkizler yükseleni', $forecast->seo_keywords);
        $this->assertNotEmpty($forecast->traits);
        $this->assertNotEmpty($forecast->rising);
        Http::assertSentCount(1);
    }
}
