<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Services\AiVisionNewsExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AiVisionNewsExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_ai_extraction_is_disabled(): void
    {
        $agency = Agency::factory()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Görsel AI kullanımı kapalıdır');

        app(AiVisionNewsExtractor::class)->extract($agency->id, 'unused.png');
    }
}
