<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\Recipe;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RecipeAiGenerator
{
    public function __construct(
        private readonly AiIntegrationRegistry $registry,
        private readonly ExternalUrlGuard $urlGuard,
        private readonly SystemSettings $settings,
        private readonly RecipeTurkishCorrector $turkishCorrector,
    ) {}

    /** @return array<int, Recipe> */
    public function generate(int $agencyId, int $quota): array
    {
        $used = Recipe::query()->where('generated_for_agency_id', $agencyId)->whereDate('generated_at', today())->count();
        if ($used >= $quota) {
            throw new RuntimeException('Günlük tarif üretim kotanız doldu.');
        }

        $content = null;
        $lastError = 'Aktif bir metin AI entegrasyonu bulunamadı.';

        foreach ($this->registry->forAgency($agencyId) as $integration) {
            try {
                $candidate = $this->request($integration);
                if (! is_array($candidate['recipes'] ?? null) || $candidate['recipes'] === []) {
                    throw new RuntimeException('Sağlayıcı geçerli tarif listesi döndürmedi.');
                }
                $content = $candidate;

                break;
            } catch (Throwable $exception) {
                $lastError = $integration->name.': '.$exception->getMessage();
            }
        }

        if ($content === null) {
            throw new RuntimeException('Tarif üretimi tamamlanamadı. '.$lastError);
        }
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

            $title = $this->turkishCorrector->correct(Str::squish((string) ($row['title'] ?? '')));
            $ingredients = $this->turkishCorrector->correct($this->textValue($row['ingredients'] ?? ''));
            $instructions = $this->turkishCorrector->correct($this->textValue($row['instructions'] ?? ''));
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
        $prompt = 'Türkçe ve özgün dört yemek tarifi üret. main, cold, salad ve dessert kategorilerinin her birinden tam bir tarif ver. Malzemeleri ölçülü, yapılışı kısa ve uygulanabilir yaz. Yazım ve ekleri Türk Dil Kurumu ölçülerine uygun kullan. Türkçe karakterleri (ç, ğ, ı, İ, ö, ş, ü) hiçbir kelimede ASCII harflere dönüştürme. Örnek: yapragi değil yaprağı; visneli değil vişneli; sogan değil soğan; zeytinyagi değil zeytinyağı; pisirin değil pişirin. ASCII Türkçe yazılmış yanıt geçersiz sayılacaktır. Yalnızca istenen JSON yapısını döndür.';
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

        $this->urlGuard->assertSafe($url);
        $request = Http::acceptJson()->asJson()->connectTimeout(10)->timeout(max(60, $integration->timeout_seconds));
        $response = $integration->provider === IntegrationProvider::GoogleGemini
            ? $request->withQueryParameters(['key' => (string) $integration->credential])->post($url, ['generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $this->recipeSchema(), 'maxOutputTokens' => max(2400, (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id))], 'contents' => [['parts' => [['text' => $prompt]]]]])
            : $request->withToken((string) $integration->credential)->post($url, ['model' => $integration->model, 'response_format' => ['type' => 'json_object'], 'max_tokens' => (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id), 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $response->throw();
        $text = $integration->provider === IntegrationProvider::GoogleGemini
            ? collect(data_get($response->json(), 'candidates.0.content.parts', []))->pluck('text')->filter(fn (mixed $part): bool => is_string($part))->implode("\n")
            : data_get($response->json(), 'choices.0.message.content');
        $decoded = $this->decodeJson((string) $text);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI tarif yanıtı geçerli JSON değil.');
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function recipeSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['recipes'],
            'properties' => [
                'recipes' => [
                    'type' => 'array',
                    'minItems' => 4,
                    'maxItems' => 4,
                    'items' => [
                        'type' => 'object',
                        'required' => ['category', 'title', 'ingredients', 'instructions'],
                        'properties' => [
                            'category' => ['type' => 'string', 'enum' => ['main', 'cold', 'salad', 'dessert']],
                            'title' => ['type' => 'string'],
                            'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'instructions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $text): ?array
    {
        $text = trim($text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        return $start === false || $end === false
            ? null
            : json_decode(substr($text, $start, $end - $start + 1), true);
    }

    private function textValue(mixed $value): string
    {
        if (is_array($value)) {
            return Str::squish(collect($value)->map(fn (mixed $item): string => $this->textValue($item))->filter()->implode(' '));
        }

        return Str::squish((string) $value);
    }
}
