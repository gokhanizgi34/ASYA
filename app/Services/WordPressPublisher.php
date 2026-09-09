<?php

namespace App\Services;

use App\HttpMethod;
use App\Models\Publication;
use App\PublishingProtocol;
use Closure;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WordPressPublisher
{
    public function __construct(
        private readonly RouteMethodLearner $routeMethodLearner,
        private readonly DistrictCategoryResolver $districtCategoryResolver,
        private readonly XVideoFeaturedImageBadge $xVideoFeaturedImageBadge,
        private readonly ArticleBodyFormatter $bodyFormatter,
        private readonly HeadlineImageFormatter $headlineImageFormatter,
    ) {}

    /** @return array{post_id: string, media_id: int|null, url: string|null, response_meta: array<string, mixed>} */
    public function publish(Publication $publication): array
    {
        $publication->loadMissing('publishingTarget', 'article');
        $this->guardTargetUrl($publication->publishingTarget->base_url);

        return match ($publication->publishingTarget->protocol) {
            PublishingProtocol::WordPressRest => $this->publishWithRest($publication),
            PublishingProtocol::WordPressXmlRpc => $this->publishWithXmlRpc($publication),
        };
    }

    /** @return array{post_id: string, media_id: int|null, url: string|null, response_meta: array<string, mixed>} */
    private function publishWithRest(Publication $publication): array
    {
        $target = $publication->publishingTarget;
        $request = $this->request($target->username, $target->credential);
        $apiUrl = rtrim($target->base_url, '/').'/wp-json/wp/v2';
        $payload = $publication->payload;
        $searchContext = $publication->remote_status->value === 'publish' ? 'view' : 'edit';
        $existing = $this->sendObserved(
            $publication,
            $apiUrl.'/posts',
            HttpMethod::Get,
            'WordPress yazısı arama',
            fn (): Response => $request->get($apiUrl.'/posts', [
                'slug' => $payload['slug'],
                'status' => $publication->remote_status->value,
                'context' => $searchContext,
                'per_page' => 1,
            ]),
        )->throw()->json();

        if (is_array($existing) && isset($existing[0]['id'])) {
            $formattedContent = $this->formatContent($payload['content'], $publication);
            $post = $this->sendObserved(
                $publication,
                $apiUrl.'/posts/'.(int) $existing[0]['id'],
                HttpMethod::Post,
                'WordPress yazısı güncelleme',
                fn (): Response => $this->request($target->username, $target->credential)->post($apiUrl.'/posts/'.(int) $existing[0]['id'], [
                    'title' => $payload['title'],
                    'slug' => $payload['slug'],
                    'content' => $formattedContent,
                    'excerpt' => $payload['excerpt'],
                    'status' => $publication->remote_status->value,
                    'meta' => $payload['meta'],
                ]),
            )->throw()->json();

            $rankMathSynced = $this->syncRankMathMetadata(
                $publication,
                (int) $existing[0]['id'],
                $formattedContent,
            );

            return [
                'post_id' => (string) $existing[0]['id'],
                'media_id' => $publication->remote_media_id,
                'url' => $post['link'] ?? $existing[0]['link'] ?? null,
                'response_meta' => ['driver' => 'rest', 'reused_existing_post' => true, 'updated_existing_post' => true, 'rank_math_synced' => $rankMathSynced],
            ];
        }

        $categories = $this->resolveRestTerms($publication, $apiUrl, 'categories', (array) ($payload['categories'] ?? []), (array) data_get($payload, 'taxonomy_names.categories', []));
        $tags = $this->resolveRestTerms($publication, $apiUrl, 'tags', (array) ($payload['tags'] ?? []), (array) data_get($payload, 'taxonomy_names.tags', []));
        $media = data_get($payload, 'media');
        $mediaId = $publication->remote_media_id ?: (is_array($media) ? $this->uploadRestMedia($publication, $request, $apiUrl, $media) : null);
        if (! $publication->remote_media_id) {
            $publication->forceFill(['remote_media_id' => $mediaId])->save();
        }

        $formattedContent = $this->formatContent($payload['content'], $publication);
        $postPayload = array_filter([
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'content' => $formattedContent,
            'excerpt' => $payload['excerpt'],
            'status' => $publication->remote_status->value,
            'featured_media' => $mediaId,
            'categories' => $categories,
            'tags' => $tags,
            'meta' => $payload['meta'],
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
        $postResponse = $this->sendObserved(
            $publication,
            $apiUrl.'/posts',
            HttpMethod::Post,
            'WordPress yazısı oluşturma',
            fn (): Response => $this->request($target->username, $target->credential)->post($apiUrl.'/posts', $postPayload),
        );

        if (in_array($postResponse->status(), [401, 403], true) && $postResponse->json('code') === 'rest_cannot_create') {
            throw new RuntimeException('WordPress kullanıcısı yazı oluşturmaya yetkili değil. Kullanıcı rolünü Editör/Yönetici yapın veya doğru kullanıcı uygulama parolası kullanın.');
        }

        $post = $postResponse->throw()->json();

        if (! isset($post['id'])) {
            throw new RuntimeException('WordPress geçerli bir yazı kimliği döndürmedi.');
        }

        $rankMathSynced = $this->syncRankMathMetadata($publication, (int) $post['id'], $formattedContent);

        return [
            'post_id' => (string) $post['id'],
            'media_id' => $mediaId,
            'url' => $post['link'] ?? null,
            'response_meta' => ['driver' => 'rest', 'reused_existing_post' => false, 'rank_math_synced' => $rankMathSynced],
        ];
    }

    private function syncRankMathMetadata(Publication $publication, int $postId, string $content): bool
    {
        $metadata = collect((array) data_get($publication->payload, 'meta', []))
            ->filter(fn (mixed $value, string $key): bool => Str::startsWith($key, 'rank_math_') && filled($value))
            ->map(fn (mixed $value): mixed => is_array($value) ? implode(',', $value) : $value)
            ->all();

        if ($metadata === []) {
            return false;
        }

        $target = $publication->publishingTarget;
        $endpoint = rtrim($target->base_url, '/').'/wp-json/rankmath/v1/updateMeta';
        $response = $this->sendObserved(
            $publication,
            $endpoint,
            HttpMethod::Post,
            'Rank Math SEO alanlarını güncelleme',
            fn (): Response => $this->request($target->username, $target->credential)->post($endpoint, [
                'objectType' => 'post',
                'objectID' => $postId,
                'meta' => $metadata,
                'content' => $content,
            ]),
        );

        if ($response->status() === 404) {
            Log::notice('WordPress hedefinde Rank Math updateMeta servisi bulunamadı.', [
                'publication_id' => $publication->id,
                'publishing_target_id' => $publication->publishing_target_id,
            ]);

            return false;
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('Rank Math SEO alanları kaydedilemedi. WordPress uygulama parolası Editör veya Yönetici yetkisine sahip olmalıdır.');
        }

        $response->throw();

        return true;
    }

    /**
     * @param  array<int, mixed>  $existingIds
     * @param  array<int, mixed>  $names
     * @return array<int, int>
     */
    private function resolveRestTerms(Publication $publication, string $apiUrl, string $taxonomy, array $existingIds, array $names): array
    {
        $ids = collect($existingIds)->map(fn (mixed $id): int => (int) $id)->filter()->values();
        $target = $publication->publishingTarget;

        foreach (collect($names)->filter(fn (mixed $name): bool => is_string($name) && filled($name))->take(12) as $name) {
            $cleanName = Str::of((string) $name)->replaceStart('#', '')->squish()->limit(100, '')->toString();
            $slug = Str::slug($cleanName);

            if ($cleanName === '' || $slug === '') {
                continue;
            }

            $lookup = $this->sendObserved(
                $publication,
                $apiUrl.'/'.$taxonomy,
                HttpMethod::Get,
                'WordPress '.$taxonomy.' arama',
                fn (): Response => $this->request($target->username, $target->credential)->get($apiUrl.'/'.$taxonomy, [
                    'slug' => $slug,
                    'per_page' => 1,
                ]),
            )->throw()->json();

            if (is_array($lookup) && isset($lookup[0]['id'])) {
                $ids->push((int) $lookup[0]['id']);

                continue;
            }

            $created = $this->sendObserved(
                $publication,
                $apiUrl.'/'.$taxonomy,
                HttpMethod::Post,
                'WordPress '.$taxonomy.' oluşturma',
                fn (): Response => $this->request($target->username, $target->credential)->post($apiUrl.'/'.$taxonomy, [
                    'name' => $cleanName,
                    'slug' => $slug,
                ]),
            );
            $termId = (int) (data_get($created->json(), 'id') ?: data_get($created->json(), 'data.term_id'));

            if (! $created->successful() && $termId === 0) {
                if (in_array($created->status(), [401, 403], true) && $created->json('code') === 'rest_cannot_create') {
                    Log::warning('WordPress hesabının sınıflandırma oluşturma yetkisi yok; yayın mevcut terimlerle sürdürülecek.', [
                        'publication_id' => $publication->id,
                        'publishing_target_id' => $publication->publishing_target_id,
                        'taxonomy' => $taxonomy,
                        'term_name' => $cleanName,
                    ]);

                    continue;
                }

                $created->throw();
            }

            if ($termId > 0) {
                $ids->push($termId);
            }
        }

        return $ids->unique()->values()->all();
    }

    /** @param array{disk: string, path: string, title: string|null, alt_text: string|null} $media */
    private function uploadRestMedia(Publication $publication, PendingRequest $request, string $apiUrl, array $media): int
    {
        $mediaResponse = $this->sendObserved(
            $publication,
            $apiUrl.'/media',
            HttpMethod::Post,
            'WordPress medya yükleme',
            fn (): Response => $request->attach('file', $this->mediaBytes($publication, $media), basename($media['path']))
                ->post($apiUrl.'/media', array_filter(['title' => $media['title'], 'alt_text' => $media['alt_text']])),
        );

        if (in_array($mediaResponse->status(), [401, 403], true) && $mediaResponse->json('code') === 'rest_cannot_create') {
            throw new RuntimeException('WordPress kullanıcısı medya yüklemeye yetkili değil. Kullanıcı rolünü Editör/Yönetici yapın veya doğru kullanıcı uygulama parolası kullanın.');
        }

        $response = $mediaResponse->throw()->json();

        if (! isset($response['id'])) {
            throw new RuntimeException('WordPress geçerli bir medya kimliği döndürmedi.');
        }

        return (int) $response['id'];
    }

    /** @return array{post_id: string, media_id: int|null, url: string|null, response_meta: array<string, mixed>} */
    private function publishWithXmlRpc(Publication $publication): array
    {
        $target = $publication->publishingTarget;
        $endpoint = rtrim($target->base_url, '/').'/xmlrpc.php';
        $payload = $publication->payload;
        $mediaId = $publication->remote_media_id;

        if (! $mediaId && is_array($payload['media'] ?? null)) {
            $media = $payload['media'];
            $upload = $this->xmlRpcCall($publication, $endpoint, 'wp.uploadFile', [
                0,
                $target->username,
                $target->credential,
                [
                    'name' => basename($media['path']),
                    'type' => Storage::disk($media['disk'])->mimeType($media['path']) ?: 'application/octet-stream',
                    'bits' => base64_encode($this->mediaBytes($publication, $media)),
                    'overwrite' => true,
                ],
            ]);
            $mediaId = isset($upload['id']) ? (int) $upload['id'] : null;
            if (! $mediaId) {
                throw new RuntimeException('WordPress XML-RPC geçerli bir medya kimliği döndürmedi.');
            }
            $publication->forceFill(['remote_media_id' => $mediaId])->save();
        }

        $postId = $this->xmlRpcCall($publication, $endpoint, 'wp.newPost', [
            0,
            $target->username,
            $target->credential,
            array_filter([
                'post_type' => 'post',
                'post_status' => $publication->remote_status->value,
                'post_title' => $payload['title'],
                'post_name' => $payload['slug'],
                'post_content' => $this->formatContent($payload['content'], $publication),
                'post_excerpt' => $payload['excerpt'],
                'post_author' => $payload['author'],
                'post_thumbnail' => $mediaId,
                'terms' => array_filter(['category' => $payload['categories'], 'post_tag' => $payload['tags']]),
                'terms_names' => array_filter(['category' => data_get($payload, 'taxonomy_names.categories', []), 'post_tag' => data_get($payload, 'taxonomy_names.tags', [])]),
                'custom_fields' => collect($payload['meta'])->map(fn (mixed $value, string $key): array => [
                    'key' => $key,
                    'value' => is_array($value) ? implode(',', $value) : $value,
                ])->values()->all(),
            ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
        ]);

        if (! is_scalar($postId) || blank((string) $postId)) {
            throw new RuntimeException('WordPress XML-RPC geçerli bir yazı kimliği döndürmedi.');
        }

        return ['post_id' => (string) $postId, 'media_id' => $mediaId, 'url' => null, 'response_meta' => ['driver' => 'xmlrpc']];
    }

    /** @param array<int, mixed> $parameters */
    private function xmlRpcCall(Publication $publication, string $endpoint, string $method, array $parameters): mixed
    {
        $response = $this->sendObserved(
            $publication,
            $endpoint,
            HttpMethod::Post,
            'WordPress XML-RPC '.$method,
            fn (): Response => Http::connectTimeout(5)->timeout(30)
                ->withBody($this->buildXmlRpcRequest($method, $parameters), 'text/xml')
                ->post($endpoint),
        )->throw();

        return $this->parseXmlRpcResponse($response->body());
    }

    /** @param array<int, mixed> $parameters */
    private function buildXmlRpcRequest(string $method, array $parameters): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $call = $document->appendChild($document->createElement('methodCall'));
        $call->appendChild($document->createElement('methodName'))->appendChild($document->createTextNode($method));
        $params = $call->appendChild($document->createElement('params'));
        foreach ($parameters as $parameter) {
            $value = $params->appendChild($document->createElement('param'))->appendChild($document->createElement('value'));
            $this->appendXmlRpcValue($document, $value, $parameter);
        }

        return (string) $document->saveXML();
    }

    private function appendXmlRpcValue(DOMDocument $document, DOMElement $value, mixed $data, ?string $key = null): void
    {
        if ($key === 'bits') {
            $value->appendChild($document->createElement('base64', (string) $data));
        } elseif (is_bool($data)) {
            $value->appendChild($document->createElement('boolean', $data ? '1' : '0'));
        } elseif (is_int($data)) {
            $value->appendChild($document->createElement('int', (string) $data));
        } elseif (is_array($data) && array_is_list($data)) {
            $array = $value->appendChild($document->createElement('array'))->appendChild($document->createElement('data'));
            foreach ($data as $item) {
                $child = $array->appendChild($document->createElement('value'));
                $this->appendXmlRpcValue($document, $child, $item);
            }
        } elseif (is_array($data)) {
            $struct = $value->appendChild($document->createElement('struct'));
            foreach ($data as $memberKey => $item) {
                $member = $struct->appendChild($document->createElement('member'));
                $member->appendChild($document->createElement('name'))->appendChild($document->createTextNode((string) $memberKey));
                $child = $member->appendChild($document->createElement('value'));
                $this->appendXmlRpcValue($document, $child, $item, (string) $memberKey);
            }
        } else {
            $value->appendChild($document->createElement('string'))->appendChild($document->createTextNode((string) $data));
        }
    }

    private function parseXmlRpcResponse(string $xml): mixed
    {
        $document = new DOMDocument;
        if (! @$document->loadXML($xml)) {
            throw new RuntimeException('WordPress XML-RPC yanıtı okunamadı.');
        }
        if ($document->getElementsByTagName('fault')->length > 0) {
            throw new RuntimeException('WordPress XML-RPC isteği başarısız oldu.');
        }
        $params = $document->getElementsByTagName('params')->item(0);
        $value = $params instanceof DOMElement ? $params->getElementsByTagName('value')->item(0) : null;

        return $value instanceof DOMElement ? $this->decodeXmlRpcValue($value) : null;
    }

    private function decodeXmlRpcValue(DOMElement $value): mixed
    {
        $typed = null;
        foreach ($value->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $typed = $child;
                break;
            }
        }
        if (! $typed) {
            return $value->textContent;
        }
        if (in_array($typed->tagName, ['int', 'i4'], true)) {
            return (int) $typed->textContent;
        }
        if ($typed->tagName === 'boolean') {
            return $typed->textContent === '1';
        }
        if ($typed->tagName === 'struct') {
            $result = [];
            foreach ($typed->childNodes as $member) {
                if (! $member instanceof DOMElement || $member->tagName !== 'member') {
                    continue;
                }
                $name = $member->getElementsByTagName('name')->item(0)?->textContent;
                $memberValue = $member->getElementsByTagName('value')->item(0);
                if ($name !== null && $memberValue instanceof DOMElement) {
                    $result[$name] = $this->decodeXmlRpcValue($memberValue);
                }
            }

            return $result;
        }

        return $typed->textContent;
    }

    /**
     * @param  Closure(): Response  $send
     */
    private function sendObserved(
        Publication $publication,
        string $url,
        HttpMethod $method,
        string $purpose,
        Closure $send,
    ): Response {
        try {
            $response = $send();
            $this->routeMethodLearner->observe(
                $publication->agency_id,
                $url,
                $method,
                $response->status(),
                $purpose,
                $publication->publishing_target_id,
            );

            return $response;
        } catch (ConnectionException $exception) {
            $this->routeMethodLearner->observe(
                $publication->agency_id,
                $url,
                $method,
                null,
                $purpose,
                $publication->publishing_target_id,
            );

            throw $exception;
        }
    }

    /** @param array{disk: string, path: string} $media */
    private function mediaBytes(Publication $publication, array $media): string
    {
        $bytes = (string) Storage::disk($media['disk'])->get($media['path']);
        $bytes = $this->headlineImageFormatter->format($bytes);

        return $this->xVideoFeaturedImageBadge->apply($bytes, (string) $publication->article?->source_url);
    }

    private function request(string $username, string $credential): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(30)->acceptJson()->withBasicAuth($username, $credential);
    }

    private function formatContent(string $content, Publication $publication): string
    {
        $content = preg_replace('~(?:https?://|www\\.)\\S+~iu', '', $content) ?? $content;
        $publication->loadMissing('article.agency', 'publishingTarget');
        $searchTerm = $this->districtCategoryResolver->resolve($publication->article)
            ?? $publication->article->agency?->category_name
            ?? data_get($publication->article->editorial_metadata, 'category')
            ?? data_get($publication->payload, 'taxonomy_names.categories.0')
            ?? 'Haber';
        $query = Str::of((string) $searchTerm)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->replace(' ', '+')->toString();
        $searchUrl = rtrim($publication->publishingTarget->base_url, '/').'/?s='.($query ?: 'haber');

        $formatted = $this->addTableOfContents($this->bodyFormatter->toHtml($content));
        $xVideoEmbed = $this->xVideoEmbed($publication);
        $sourceReference = $this->sourceReference($publication);

        return $formatted
            .($xVideoEmbed !== '' ? "\n".$xVideoEmbed : '')
            .($sourceReference !== '' ? "\n".$sourceReference : '')
            ."\n".'<p><a href="'.e($searchUrl).'">'.e((string) $searchTerm).' haberleri</a></p>';
    }

    private function addTableOfContents(string $html): string
    {
        $headings = [];
        $usedIds = [];
        $html = preg_replace_callback('/<h([2-4])([^>]*)>(.*?)<\/h\1>/isu', function (array $match) use (&$headings, &$usedIds): string {
            $label = Str::of(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->toString();
            $baseId = Str::slug($label) ?: 'haber-bolumu';
            $id = $baseId;
            $suffix = 2;

            while (isset($usedIds[$id])) {
                $id = $baseId.'-'.$suffix;
                $suffix++;
            }

            $usedIds[$id] = true;
            $headings[] = ['level' => (int) $match[1], 'id' => $id, 'label' => $label];
            $attributes = preg_replace('/\s+id=(?:"[^"]*"|\'[^\']*\')/iu', '', $match[2]) ?? $match[2];

            return '<h'.$match[1].$attributes.' id="'.e($id).'">'.$match[3].'</h'.$match[1].'>';
        }, $html) ?? $html;

        if (count($headings) < 2) {
            return $html;
        }

        $items = collect($headings)->map(fn (array $heading): string => '<li class="asya-toc-level-'.$heading['level'].'"><a href="#'.e($heading['id']).'">'.e($heading['label']).'</a></li>')->implode('');
        $tableOfContents = '<nav class="asya-table-of-contents" aria-label="İçindekiler" style="margin:1.5rem 0;padding:1rem 1.25rem;border:1px solid #e5e7eb;border-radius:8px">'
            .'<p style="margin:0 0 .75rem"><strong>İçindekiler</strong></p><ul style="margin:0;padding-left:1.25rem">'.$items.'</ul></nav>';

        return $tableOfContents."\n".$html;
    }

    private function sourceReference(Publication $publication): string
    {
        $sourceUrl = trim((string) $publication->article?->source_url);
        $sourceHost = Str::lower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $targetHost = Str::lower((string) parse_url($publication->publishingTarget->base_url, PHP_URL_HOST));

        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL)
            || ! in_array(Str::lower((string) parse_url($sourceUrl, PHP_URL_SCHEME)), ['http', 'https'], true)
            || $sourceHost === ''
            || $sourceHost === $targetHost
            || in_array($sourceHost, ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'], true)) {
            return '';
        }

        $sourceName = Str::of((string) $publication->article?->source_name)->squish()->toString() ?: $sourceHost;

        return '<p class="asya-source-reference"><strong>Kaynak:</strong> <a href="'.e($sourceUrl).'" target="_blank" rel="noopener">'.e($sourceName).'</a></p>';
    }

    private function xVideoEmbed(Publication $publication): string
    {
        $sourceUrl = (string) $publication->article?->source_url;
        $host = Str::lower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $path = (string) parse_url($sourceUrl, PHP_URL_PATH);

        if (! in_array($host, ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'], true)
            || preg_match('~^/([A-Za-z0-9_]+)/status/(\d+)/video/\d+/?$~', $path, $matches) !== 1) {
            return '';
        }

        $embedUrl = 'https://twitter.com/i/videos/tweet/'.$matches[2];

        return '<div class="asya-x-video" style="margin:24px auto;max-width:900px">'
            .'<iframe src="'.e($embedUrl).'" title="Haber videosu" loading="lazy" scrolling="no" frameborder="0" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" style="display:block;width:100%;aspect-ratio:16/9;border:0" allowfullscreen></iframe>'
            .'</div>';
    }

    private function guardTargetUrl(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw new RuntimeException('Yayın hedefi genel ağa açık ve güvenli bir adres olmalıdır.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Özel veya ayrılmış ağ adreslerine yayın yapılamaz.');
        }
    }
}
