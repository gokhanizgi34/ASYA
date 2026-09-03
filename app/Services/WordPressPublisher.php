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
    ) {}

    /** @return array{post_id: string, media_id: int|null, url: string|null, response_meta: array<string, mixed>} */
    public function publish(Publication $publication): array
    {
        $publication->loadMissing('publishingTarget');
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
            return [
                'post_id' => (string) $existing[0]['id'],
                'media_id' => $publication->remote_media_id,
                'url' => $existing[0]['link'] ?? null,
                'response_meta' => ['driver' => 'rest', 'reused_existing_post' => true],
            ];
        }

        $categories = $this->resolveRestTerms($publication, $apiUrl, 'categories', (array) ($payload['categories'] ?? []), (array) data_get($payload, 'taxonomy_names.categories', []));
        $tags = $this->resolveRestTerms($publication, $apiUrl, 'tags', (array) ($payload['tags'] ?? []), (array) data_get($payload, 'taxonomy_names.tags', []));
        $mediaId = $publication->remote_media_id ?: $this->uploadRestMedia($publication, $request, $apiUrl, $payload['media']);
        if (! $publication->remote_media_id) {
            $publication->forceFill(['remote_media_id' => $mediaId])->save();
        }

        $postPayload = array_filter([
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'content' => $this->formatContent($payload['content']),
            'excerpt' => $payload['excerpt'],
            'status' => $publication->remote_status->value,
            'featured_media' => $mediaId,
            'author' => $payload['author'],
            'categories' => $categories,
            'tags' => $tags,
            'meta' => $payload['meta'],
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
        $post = $this->sendObserved(
            $publication,
            $apiUrl.'/posts',
            HttpMethod::Post,
            'WordPress yazısı oluşturma',
            fn (): Response => $this->request($target->username, $target->credential)->post($apiUrl.'/posts', $postPayload),
        )->throw()->json();

        if (! isset($post['id'])) {
            throw new RuntimeException('WordPress geçerli bir yazı kimliği döndürmedi.');
        }

        return [
            'post_id' => (string) $post['id'],
            'media_id' => $mediaId,
            'url' => $post['link'] ?? null,
            'response_meta' => ['driver' => 'rest', 'reused_existing_post' => false],
        ];
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
        $response = $this->sendObserved(
            $publication,
            $apiUrl.'/media',
            HttpMethod::Post,
            'WordPress medya yükleme',
            fn (): Response => $request->attach('file', Storage::disk($media['disk'])->get($media['path']), basename($media['path']))
                ->post($apiUrl.'/media', array_filter(['title' => $media['title'], 'alt_text' => $media['alt_text']])),
        )->throw()->json();

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

        if (! $mediaId) {
            $media = $payload['media'];
            $upload = $this->xmlRpcCall($publication, $endpoint, 'wp.uploadFile', [
                0,
                $target->username,
                $target->credential,
                [
                    'name' => basename($media['path']),
                    'type' => Storage::disk($media['disk'])->mimeType($media['path']) ?: 'application/octet-stream',
                    'bits' => base64_encode(Storage::disk($media['disk'])->get($media['path'])),
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
                'post_content' => $this->formatContent($payload['content']),
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

    private function request(string $username, string $credential): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(30)->acceptJson()->withBasicAuth($username, $credential);
    }

    private function formatContent(string $content): string
    {
        $content = preg_replace('~(?:https?://|www\\.)\\S+~iu', '', $content) ?? $content;

        return collect(preg_split('/\R{2,}/u', trim($content)) ?: [])
            ->filter(fn (string $block): bool => filled(trim($block)))
            ->map(function (string $block): string {
                $block = trim($block);

                if (preg_match('/^#{2,3}\s+(.+)$/us', $block, $matches) === 1) {
                    $level = str_starts_with($block, '### ') ? 3 : 2;

                    return '<h'.$level.'>'.e(trim($matches[1])).'</h'.$level.'>';
                }

                return '<p>'.nl2br(e($block), false).'</p>';
            })
            ->implode("\n");
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
