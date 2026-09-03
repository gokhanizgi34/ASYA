<?php

namespace Tests\Feature;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Services\AiVisionNewsExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiVisionNewsExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_compatible_provider_extracts_verified_news_json_from_image(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"articles":[{"title":"Görüntüdeki haber","body":"Görüntü üzerinde açıkça bulunan yeterli uzunluktaki haber özeti.","published_at":"2026-08-30T12:00:00+03:00"}]}',
                    ],
                ]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create([
            'name' => 'OpenAI Görsel',
            'provider' => IntegrationProvider::OpenAi,
            'model' => 'gpt-5',
            'base_url' => 'https://93.184.216.34/v1/models',
            'auth_type' => IntegrationAuthType::Bearer,
            'credential' => 'secret-vision-key',
            'is_active' => true,
            'is_default' => true,
            'priority' => 1,
        ]);
        $imagePath = storage_path('framework/testing-vision.png');
        file_put_contents($imagePath, 'fake-png-content');

        try {
            $records = app(AiVisionNewsExtractor::class)->extract($agency->id, $imagePath);
        } finally {
            @unlink($imagePath);
        }

        $this->assertSame('Görüntüdeki haber', $records[0]['title']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-vision-key')
            && ! array_key_exists('temperature', $request->data())
            && str_contains((string) data_get($request->data(), 'messages.0.content.1.image_url.url'), 'data:image/png;base64,'));
    }
}
