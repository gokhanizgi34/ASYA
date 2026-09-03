<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\Recipe;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RecipeAiGenerator
{
    /** @return array<int, Recipe> */
    public function generate(int $agencyId, int $quota): array
    {
        $used = Recipe::query()->where('generated_for_agency_id', $agencyId)->whereDate('generated_at', today())->count();
        if ($used >= $quota) {
            throw new RuntimeException('Günlük tarif üretim kotanız doldu.');
        }

        $integration = app(AiIntegrationRegistry::class)->forAgency($agencyId)->first();
        if (! $integration) {
            throw new RuntimeException('Tarif üretimi için aktif bir metin AI entegrasyonu bulunamadı.');
        }

        $content = $this->request($integration);
        $rows = collect($content['recipes'] ?? []);
        $categories = ['main', 'cold', 'salad', 'dessert'];
        $created = [];

        foreach ($categories as $category) {
            if (count($created) + $used >= $quota) {
                break;
            }

            $row = $rows->first(fn (mixed $value): bool => is_array($value) && ($value['category'] ?? null) === $category);
            if (! is_array($row)) {
                continue;
            }

            $title = Str::squish((string) ($row['title'] ?? ''));
            $ingredients = $this->textValue($row['ingredients'] ?? '');
            $instructions = $this->textValue($row['instructions'] ?? '');
            if ($title === '' || Str::length($ingredients) < 10 || Str::length($instructions) < 20) {
                continue;
            }

            $created[] = Recipe::query()->create([
                'category' => $category,
                'title' => Str::limit($title, 180, ''),
                'ingredients' => Str::limit($ingredients, 5000, ''),
                'instructions' => Str::limit($instructions, 10000, ''),
                'origin' => 'ai',
                'generated_for_agency_id' => $agencyId,
                'generated_at' => now(),
                'is_active' => true,
            ]);
        }

        if ($created === []) {
            throw new RuntimeException('AI geçerli tarif verisi döndürmedi.');
        }

        return $created;
    }

    /** @return array<string, mixed> */
    private function request(ApiIntegration $integration): array
    {
        $prompt = 'Türkçe yemek tarifleri üret. Yalnızca saf JSON döndür. Her kategoriden bir tarif ver: main ana yemek, cold soğuk yemek, salad salata, dessert tatlı. Her tarifte category, title, ingredients ve instructions alanları olsun. Ölçüleri anlaşılır, yapılışı uygulanabilir ve özgün olsun.';
        $url = rtrim($integration->base_url, '/');
        if ($integration->provider === IntegrationProvider::GoogleGemini) {
            $root = preg_replace('~/models(?:\?.*)?$~', '', $url) ?: '';
            $url = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
        } elseif (preg_match('~/models(?:\?.*)?$~', $url) === 1) {
            $url = (string) preg_replace('~/models(?:\?.*)?$~', '/chat/completions', $url);
        } elseif (str_ends_with($url, '/v1')) {
            $url .= '/chat/completions';
        } elseif (! str_ends_with($url, '/chat/completions')) {
            $url .= '/chat/completions';
        }

        $this->assertUrl($url);
        $request = Http::acceptJson()->asJson()->connectTimeout(10)->timeout(max(60, $integration->timeout_seconds));
        $response = $integration->provider === IntegrationProvider::GoogleGemini
            ? $request->withQueryParameters(['key' => (string) $integration->credential])->post($url, ['generationConfig' => ['responseMimeType' => 'application/json'], 'contents' => [['parts' => [['text' => $prompt]]]]])
            : $request->withToken((string) $integration->credential)->post($url, ['model' => $integration->model, 'response_format' => ['type' => 'json_object'], 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $response->throw();
        $text = $integration->provider === IntegrationProvider::GoogleGemini ? data_get($response->json(), 'candidates.0.content.parts.0.text') : data_get($response->json(), 'choices.0.message.content');
        $decoded = json_decode((string) $text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI tarif yanıtı geçerli JSON değil.');
        }

        return $decoded;
    }

    private function assertUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('AI tarif endpointi geçersiz.');
        }
    }

    private function textValue(mixed $value): string
    {
        if (is_array($value)) {
            return Str::squish(collect($value)->map(fn (mixed $item): string => $this->textValue($item))->filter()->implode(' '));
        }

        return Str::squish((string) $value);
    }
}
