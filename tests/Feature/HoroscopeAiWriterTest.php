<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Services\HoroscopeAiWriter;
use App\ZodiacSign;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HoroscopeAiWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_invalid_json_is_retried_once_with_strict_schema(): void
    {
        Http::preventStrayRequests();
        $validJson = json_encode(['forecasts' => $this->forecasts()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        Http::fake([
            'https://93.184.216.34/v1beta/models/gemini-flash-latest:generateContent*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [['text' => '{"forecasts":[']]]]]])
                ->push(['candidates' => [['content' => ['parts' => [
                    ['text' => "Yanıt:\n```json\n"],
                    ['text' => $validJson],
                    ['text' => "\n```"],
                ]]]]]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create([
            'base_url' => 'https://93.184.216.34/v1beta/models',
            'model' => 'gemini-flash-latest',
            'credential' => 'gemini-key',
            'is_active' => true,
        ]);

        $result = app(HoroscopeAiWriter::class)->write($agency->id, CarbonImmutable::parse('2026-09-08'));

        $this->assertCount(12, $result);
        $requests = Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'gemini-flash-latest:generateContent'));
        $this->assertCount(2, $requests);
        $this->assertSame(12, data_get($requests[0][0]->data(), 'generationConfig.responseJsonSchema.properties.forecasts.minItems'));
        $this->assertSame(5000, data_get($requests[0][0]->data(), 'generationConfig.maxOutputTokens'));
        $this->assertStringContainsString('Önceki yanıt', (string) data_get($requests[1][0]->data(), 'contents.0.parts.0.text'));
    }

    public function test_http_failure_moves_from_gemini_to_next_ai_provider(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/v1beta/models/gemini-flash-latest:generateContent*' => Http::response(['error' => ['message' => 'quota']], 429),
            'https://93.184.216.35/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['forecasts' => $this->forecasts()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create([
            'base_url' => 'https://93.184.216.34/v1beta/models',
            'model' => 'gemini-flash-latest',
            'credential' => 'gemini-key',
            'is_active' => true,
        ]);
        ApiIntegration::factory()->ai(IntegrationProvider::OpenAi)->for($agency)->create([
            'base_url' => 'https://93.184.216.35/v1/models',
            'model' => 'gpt-test',
            'credential' => 'openai-key',
            'is_active' => true,
            'priority' => 20,
        ]);

        $result = app(HoroscopeAiWriter::class)->write($agency->id, CarbonImmutable::parse('2026-09-08'));

        $this->assertCount(12, $result);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://93.184.216.35/v1/chat/completions');
    }

    /** @return array<int, array<string, mixed>> */
    private function forecasts(): array
    {
        return collect(ZodiacSign::cases())->map(fn (ZodiacSign $sign): array => [
            'sign' => $sign->value,
            'general' => $sign->label().' burcu bugün sakin kararlarla günün olanaklarını daha verimli değerlendirebilir.',
            'traits' => $sign->label().' burcunun güçlü yönleri günün koşullarında belirginleşebilir.',
            'rising' => 'Yükselen burcun etkisi kişisel doğum haritasına göre farklılık gösterebilir.',
            'love' => 'Duyguları açık ve nazik biçimde paylaşmak ilişkilerde karşılıklı anlayışı güçlendirebilir.',
            'career' => 'Planlı ilerlemek ve öncelikleri netleştirmek iş yaşamındaki engelleri aşmayı kolaylaştırabilir.',
            'money' => 'Harcamaları gözden geçirmek ve acele kararlardan kaçınmak bütçe dengesini koruyabilir.',
            'health' => 'Dinlenmeye zaman ayırmak ve hafif hareket etmek günün ritmini destekleyebilir.',
            'lucky_color' => 'Mavi',
            'lucky_number' => 7,
        ])->all();
    }
}
