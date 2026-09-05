<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class NewsContentExtractor
{
    private const MAX_BODY_BYTES = 5_000_000;

    private const MAX_CRAWL_PAGES = 12;

    public function __construct(
        private readonly ExternalUrlGuard $urlGuard,
        private readonly NativeTlsHttpFetcher $nativeTlsFetcher,
    ) {}

    /**
     * @return array{
     *     items: array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>,
     *     method: string,
     *     url: string,
     *     status: int,
     *     fingerprint: string,
     *     crawled_pages: int
     * }
     */
    public function extract(string $url, int $agencyId): array
    {
        $response = $this->fetch($url);
        $body = $this->validBody($response);

        if ($this->looksLikeJson($response, $body)) {
            $items = $this->hydrateLinkedArticles($this->parseJson($body, $url), $url);

            return $this->result($items, 'json_api', $url, $response, $body);
        }

        if ($this->looksLikeXml($response, $body)) {
            $items = $this->hydrateLinkedArticles($this->parseXml($body, $url), $url);

            return $this->result($items, 'rss_atom_xml', $url, $response, $body);
        }

        if (! $this->looksLikeHtml($response, $body)) {
            throw new RuntimeException('Bağlantı desteklenen bir haber akışı, API veya HTML sayfası döndürmedi.');
        }

        foreach ($this->feedCandidates($url, $body) as $candidate) {
            $candidateResponse = $this->tryFetch($candidate);

            if (! $candidateResponse || ! $candidateResponse->successful()) {
                continue;
            }

            $candidateBody = $candidateResponse->body();

            if ($this->validSize($candidateBody) && $this->looksLikeXml($candidateResponse, $candidateBody)) {
                $items = $this->hydrateLinkedArticles($this->parseXml($candidateBody, $candidate), $candidate);

                return $this->result($items, 'rss_atom_xml', $candidate, $candidateResponse, $candidateBody);
            }
        }

        foreach ($this->apiCandidates($url, $body) as $candidate) {
            $candidateResponse = $this->tryFetch($candidate);

            if (! $candidateResponse || ! $candidateResponse->successful()) {
                continue;
            }

            $candidateBody = $candidateResponse->body();

            if ($this->validSize($candidateBody) && $this->looksLikeJson($candidateResponse, $candidateBody)) {
                try {
                    $items = $this->hydrateLinkedArticles($this->parseJson($candidateBody, $candidate), $candidate);

                    return $this->result($items, 'wordpress_json_api', $candidate, $candidateResponse, $candidateBody);
                } catch (RuntimeException) {
                    continue;
                }
            }
        }

        $body = $this->expandDynamicFragments($url, $body);
        $items = $this->crawlHtml($url, $body);

        if ($items === []) {
            return [
                'items' => [],
                'method' => 'html_dom_crawl_empty',
                'url' => $url,
                'status' => $response->status(),
                'fingerprint' => $this->fingerprint([], $body),
                'crawled_pages' => 1,
            ];
        }

        return [
            'items' => $this->filterRecentItems($items),
            'method' => 'html_dom_crawl',
            'url' => $url,
            'status' => $response->status(),
            'fingerprint' => $this->fingerprint($items),
            'crawled_pages' => count($items),
        ];
    }

    private function fetch(string $url): Response
    {
        $this->urlGuard->assertSafe($url);
        $accept = 'application/rss+xml, application/atom+xml, application/xml, text/xml, application/json, text/html';
        $userAgent = 'ASYA-News-Importer/2.0 (+authorized-public-content)';

        try {
            $request = Http::accept($accept)
                ->withUserAgent($userAgent)
                ->connectTimeout(8)
                ->timeout(20);
            $caBundle = $this->nativeTlsFetcher->caBundlePath();

            if ($caBundle !== null) {
                $request = $request->withOptions(['verify' => $caBundle]);
            }

            $response = $request->get($url);
        } catch (ConnectionException $exception) {
            if (! str_contains($exception->getMessage(), 'cURL error 60')) {
                throw $exception;
            }

            $response = $this->nativeTlsFetcher->fetch($url, $accept, $userAgent);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Kaynak HTTP '.$response->status().' yanıtı verdi.');
        }

        return $response;
    }

    private function tryFetch(string $url): ?Response
    {
        try {
            return $this->fetch($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function validBody(Response $response): string
    {
        $body = $response->body();

        if (! $this->validSize($body)) {
            throw new RuntimeException('Kaynak yanıtı boş veya izin verilen boyuttan büyük.');
        }

        return $body;
    }

    private function validSize(string $body): bool
    {
        return $body !== '' && strlen($body) <= self::MAX_BODY_BYTES;
    }

    private function looksLikeJson(Response $response, string $body): bool
    {
        $trimmed = ltrim($body);

        return Str::contains(Str::lower((string) $response->header('Content-Type')), ['application/json', '+json'])
            || str_starts_with($trimmed, '{')
            || str_starts_with($trimmed, '[');
    }

    private function looksLikeXml(Response $response, string $body): bool
    {
        $start = substr(ltrim($body, "\xEF\xBB\xBF \t\n\r\0\x0B"), 0, 3000);

        return Str::contains(Str::lower((string) $response->header('Content-Type')), ['application/rss+xml', 'application/atom+xml'])
            || preg_match('/<(rss|feed|rdf:RDF|channel)\b/i', $start) === 1;
    }

    private function looksLikeHtml(Response $response, string $body): bool
    {
        return Str::contains(Str::lower((string) $response->header('Content-Type')), 'text/html')
            || preg_match('/<(html|article|main)\b/i', substr($body, 0, 3000)) === 1;
    }

    /** @return array<int, string> */
    private function feedCandidates(string $pageUrl, string $html): array
    {
        $candidates = [rtrim($pageUrl, '/').'/feed/'];
        $xpath = $this->xpath($html);

        if ($xpath) {
            foreach ($xpath->query('//link[@href]') ?: [] as $link) {
                if (! $link instanceof DOMElement) {
                    continue;
                }

                $rel = Str::lower($link->getAttribute('rel'));
                $type = Str::lower($link->getAttribute('type'));

                if (Str::contains($rel, 'alternate') && Str::contains($type, ['rss', 'atom', 'xml'])) {
                    $candidates[] = $this->resolveUrl($pageUrl, $link->getAttribute('href'));
                }
            }
        }

        return $this->safeUniqueUrls($candidates, $pageUrl);
    }

    /** @return array<int, string> */
    private function apiCandidates(string $pageUrl, string $html): array
    {
        $candidates = [];
        $xpath = $this->xpath($html);

        if ($xpath) {
            foreach ($xpath->query('//link[@href]') ?: [] as $link) {
                if ($link instanceof DOMElement && Str::contains(Str::lower($link->getAttribute('rel')), 'https://api.w.org/')) {
                    $apiRoot = rtrim($this->resolveUrl($pageUrl, $link->getAttribute('href')), '/');
                    $candidates[] = $apiRoot.'/wp/v2/posts?per_page=20&_embed=1';
                }
            }
        }

        $parts = parse_url($pageUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        $candidates[] = $origin.'/wp-json/wp/v2/posts?per_page=20&_embed=1';

        return $this->safeUniqueUrls($candidates, $pageUrl);
    }

    /**
     * @return array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>
     */
    private function parseJson(string $body, string $baseUrl): array
    {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $records = $this->findRecords($decoded);
        $items = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $title = $this->plainText($this->firstValue($record, ['title.rendered', 'title', 'headline', 'name']));
            $content = $this->plainText($this->firstValue($record, ['content.rendered', 'content', 'body', 'description', 'excerpt.rendered', 'summary']));
            $url = $this->nullableUrl($baseUrl, $this->firstValue($record, ['link', 'url', 'permalink', 'canonical_url']));
            $image = $this->nullableUrl($baseUrl, $this->firstValue($record, ['_embedded.wp:featuredmedia.0.source_url', 'image.url', 'image', 'featured_image', 'thumbnail']));
            $date = $this->firstValue($record, ['date_gmt', 'date', 'published_at', 'publishedAt', 'pubDate', 'created_at']);

            if ($title === '' || mb_strlen($content) < 20) {
                continue;
            }

            $items[] = $this->item(
                $this->firstValue($record, ['id', 'guid.rendered', 'guid', 'uuid']),
                $title,
                $content,
                $url,
                $image,
                $date,
            );
        }

        if ($items === []) {
            throw new RuntimeException('JSON API yanıtında alınabilecek geçerli haber bulunamadı.');
        }

        return array_slice($items, 0, 50);
    }

    /** @return array<int, mixed> */
    private function findRecords(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        foreach (['items', 'articles', 'posts', 'news', 'results', 'data'] as $key) {
            $value = $decoded[$key] ?? null;

            if (is_array($value)) {
                if (array_is_list($value)) {
                    return $value;
                }

                $nested = $this->findRecords($value);

                if ($nested !== []) {
                    return $nested;
                }
            }
        }

        return [];
    }

    /**
     * @return array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>
     */
    private function parseXml(string $body, string $baseUrl): array
    {
        $body = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Bağlantı geçerli bir XML haber akışı döndürmedi.');
        }

        $nodes = isset($xml->channel->item) ? $xml->channel->item : ($xml->entry ?? $xml->item ?? $xml->article);
        $items = [];

        foreach ($nodes as $node) {
            $namespaces = $node->getNamespaces(true);
            $content = isset($namespaces['content']) ? (string) $node->children($namespaces['content'])->encoded : '';
            $media = isset($namespaces['media']) ? $node->children($namespaces['media']) : null;
            $link = (string) $node->link;

            if ($link === '' && isset($node->link['href'])) {
                $link = (string) $node->link['href'];
            }

            $title = $this->plainText((string) ($node->title ?: $node->headline ?: $node->name));
            $description = $content !== '' ? $content : (string) ($node->description ?: $node->summary ?: $node->content);
            $text = $this->plainText($description);

            if ($title === '' || mb_strlen($text) < 20) {
                continue;
            }

            $items[] = $this->item(
                (string) ($node->guid ?: $node->id),
                $title,
                $text,
                $this->nullableUrl($baseUrl, $link),
                filled((string) $node->enclosure['url']) ? (string) $node->enclosure['url'] : (filled((string) $media?->content['url']) ? (string) $media?->content['url'] : null),
                (string) ($node->pubDate ?: $node->published ?: $node->updated ?: $node->date),
            );
        }

        if ($items === []) {
            throw new RuntimeException('XML akışında alınabilecek geçerli haber bulunamadı.');
        }

        return array_slice($items, 0, 50);
    }

    /**
     * @param  array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>  $items
     * @return array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>
     */
    private function hydrateLinkedArticles(array $items, string $sourceUrl): array
    {
        $sourceHost = Str::lower((string) parse_url($sourceUrl, PHP_URL_HOST));

        return collect($items)->map(function (array $item) use ($sourceHost): array {
            $articleUrl = $item['url'];
            $articleHost = Str::lower((string) parse_url((string) $articleUrl, PHP_URL_HOST));

            if (mb_strlen($item['body']) >= 600 || ! $articleUrl || $articleHost === '' || $articleHost !== $sourceHost) {
                return $item;
            }

            $response = $this->tryFetch($articleUrl);
            if (! $response || ! $response->successful() || ! $this->looksLikeHtml($response, $response->body())) {
                return $item;
            }

            $fullArticle = $this->parseArticlePage($articleUrl, $response->body());
            if (! $fullArticle || mb_strlen($fullArticle['body']) < max(350, (int) (mb_strlen($item['body']) * 1.5))) {
                return $item;
            }

            return [
                ...$item,
                'title' => $fullArticle['title'] ?: $item['title'],
                'body' => $fullArticle['body'],
                'image_url' => $fullArticle['image_url'] ?: $item['image_url'],
            ];
        })->all();
    }

    /**
     * @return array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>
     */
    private function expandDynamicFragments(string $pageUrl, string $html): string
    {
        $xpath = $this->xpath($html);

        if (! $xpath) {
            return $html;
        }

        $candidates = [];

        foreach ($xpath->query('//*[@hx-get]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $candidates[] = $this->resolveUrl($pageUrl, $node->getAttribute('hx-get'));
            }
        }

        $fragments = [];

        foreach (array_slice($this->safeUniqueUrls($candidates, $pageUrl), 0, 20) as $candidate) {
            $response = $this->tryFetch($candidate);

            if ($response && $response->successful() && $this->looksLikeHtml($response, $response->body())) {
                $fragments[] = $response->body();
            }
        }

        return $fragments === [] ? $html : $html."\n".implode("\n", $fragments);
    }

    private function crawlHtml(string $listingUrl, string $html): array
    {
        $candidates = $this->articleLinks($listingUrl, $html);
        $items = [];

        foreach (array_slice($candidates, 0, self::MAX_CRAWL_PAGES) as $candidate) {
            $response = $this->tryFetch($candidate);

            if (! $response || ! $response->successful() || ! $this->looksLikeHtml($response, $response->body())) {
                continue;
            }

            $item = $this->parseArticlePage($candidate, $response->body());

            if ($item !== null) {
                $items[] = $item;
            }
        }

        if ($items === []) {
            $currentPage = $this->parseArticlePage($listingUrl, $html);

            if ($currentPage !== null) {
                $items[] = $currentPage;
            }
        }

        return array_slice($items, 0, 50);
    }

    /** @return array<int, string> */
    private function articleLinks(string $baseUrl, string $html): array
    {
        $xpath = $this->xpath($html);

        if (! $xpath) {
            return [];
        }

        $queries = [
            "//a[contains(translate(@href, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '/haber/')][@href] | //a[contains(translate(@href, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '/haberler/')][@href]",
            "//article//a[@href] | //h1/a[@href] | //h2/a[@href] | //h3/a[@href] | //a[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'post-title')][@href] | //*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'haber') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'news') or contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'post')]//a[@href]",
        ];
        $urls = [];

        foreach ($queries as $query) {
            foreach ($xpath->query($query) ?: [] as $link) {
                if (! $link instanceof DOMElement || mb_strlen($this->plainText($link->textContent)) < 10) {
                    continue;
                }

                $urls[] = $this->resolveUrl($baseUrl, $link->getAttribute('href'));
            }
        }

        return $this->safeUniqueUrls($urls, $baseUrl);
    }

    /**
     * @return array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}|null
     */
    private function parseArticlePage(string $url, string $html): ?array
    {
        $xpath = $this->xpath($html);

        if (! $xpath) {
            return null;
        }

        $title = $this->meta($xpath, 'property', 'og:title')
            ?: $this->nodeText($xpath, '//h1[1]')
            ?: $this->nodeText($xpath, '//h2[string-length(normalize-space(.)) > 20][1]')
            ?: $this->nodeText($xpath, '//title[1]');
        $body = $this->jsonLdArticleBody($xpath);
        $bodyNode = $this->headingContentNode($xpath) ?: $this->firstNode($xpath, [
            "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'article-content')][1]",
            "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'news-content')][1]",
            "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'entry-content')][1]",
            "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'post-content')][1]",
            "//*[contains(translate(@class, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'haber-detay')][1]",
            '//article[1]',
            '//main[1]',
        ]);
        $domBody = $bodyNode ? $this->paragraphText($bodyNode) : '';
        if (mb_strlen($domBody) > mb_strlen($body)) {
            $body = $domBody;
        }

        if ($title === '' || mb_strlen($body) < 180) {
            return null;
        }

        $image = $this->meta($xpath, 'property', 'og:image')
            ?: $this->meta($xpath, 'name', 'twitter:image')
            ?: $this->attribute($xpath, '//article[1]//img[@src][1]', 'src')
            ?: $this->attribute($xpath, '//article[1]//img[@data-src][1]', 'data-src')
            ?: $this->attribute($xpath, '//article[1]//img[@data-lazy-src][1]', 'data-lazy-src')
            ?: $this->attribute($xpath, '//main[1]//img[@src][1]', 'src')
            ?: $this->attribute($xpath, '//main[1]//img[@data-src][1]', 'data-src');
        $date = $this->meta($xpath, 'property', 'article:published_time')
            ?: $this->meta($xpath, 'name', 'date')
            ?: $this->attribute($xpath, '//time[@datetime][1]', 'datetime');

        return $this->item($url, $title, $body, $url, $this->nullableUrl($url, $image), $date);
    }

    private function xpath(string $html): ?DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($document) : null;
    }

    /** @param array<int, string> $queries */
    private function firstNode(DOMXPath $xpath, array $queries): ?DOMNode
    {
        foreach ($queries as $query) {
            $node = $xpath->query($query)?->item(0);

            if ($node instanceof DOMNode) {
                return $node;
            }
        }

        return null;
    }

    private function paragraphText(DOMNode $node): string
    {
        $document = $node instanceof DOMDocument ? $node : $node->ownerDocument;

        if (! $document) {
            return '';
        }

        $paragraphs = (new DOMXPath($document))->query('.//p[not(ancestor::nav) and not(ancestor::header) and not(ancestor::footer) and not(ancestor::aside)]', $node);
        $parts = [];

        foreach ($paragraphs ?: [] as $paragraph) {
            $text = $this->plainText($paragraph->textContent);

            if (mb_strlen($text) >= 20) {
                $parts[] = $text;
            }
        }

        return $this->plainText(implode("\n\n", $parts ?: [$node->textContent]));
    }

    private function headingContentNode(DOMXPath $xpath): ?DOMNode
    {
        $heading = $xpath->query('(//h1 | //h2[string-length(normalize-space(.)) > 20])[1]')?->item(0);

        for ($node = $heading?->parentNode; $node instanceof DOMNode; $node = $node->parentNode) {
            $paragraphCount = $xpath->query('.//p[not(ancestor::nav) and not(ancestor::header) and not(ancestor::footer) and not(ancestor::aside)]', $node)?->length ?? 0;

            if ($paragraphCount >= 2 && mb_strlen($this->paragraphText($node)) >= 180) {
                return $node;
            }

            if ($node instanceof DOMElement && in_array(Str::lower($node->tagName), ['main', 'body'], true)) {
                break;
            }
        }

        return null;
    }

    private function jsonLdArticleBody(DOMXPath $xpath): string
    {
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $decoded = json_decode(trim((string) $script->textContent), true);

            foreach ($this->jsonLdRecords($decoded) as $record) {
                $types = array_map('strtolower', (array) ($record['@type'] ?? []));

                if (array_intersect($types, ['article', 'newsarticle', 'reportagenewsarticle']) === []) {
                    continue;
                }

                $body = $this->plainText((string) ($record['articleBody'] ?? ''));
                if (mb_strlen($body) >= 180) {
                    return $body;
                }
            }
        }

        return '';
    }

    /** @return array<int, array<string, mixed>> */
    private function jsonLdRecords(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $records = [];
        if (isset($value['@type'])) {
            $records[] = $value;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $records = [...$records, ...$this->jsonLdRecords($child)];
            }
        }

        return $records;
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): string
    {
        return $this->attribute($xpath, "//meta[translate(@{$attribute}, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='".Str::lower($value)."'][1]", 'content');
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): string
    {
        $node = $xpath->query($query)?->item(0);

        return $node instanceof DOMElement ? trim($node->getAttribute($attribute)) : '';
    }

    private function nodeText(DOMXPath $xpath, string $query): string
    {
        return $this->plainText((string) $xpath->query($query)?->item(0)?->textContent);
    }

    /** @param array<int, mixed> $urls
     * @return array<int, string>
     */
    private function safeUniqueUrls(array $urls, string $baseUrl): array
    {
        $baseHost = Str::lower((string) parse_url($baseUrl, PHP_URL_HOST));
        $safe = [];

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '' || Str::lower((string) parse_url($url, PHP_URL_HOST)) !== $baseHost) {
                continue;
            }

            try {
                $this->urlGuard->assertSafe($url);
                $safe[] = $url;
            } catch (Throwable) {
                continue;
            }
        }

        return array_values(array_unique($safe));
    }

    private function resolveUrl(string $baseUrl, string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (preg_match('~^https?://~i', $href) === 1) {
            return $href;
        }

        $scheme = (string) parse_url($baseUrl, PHP_URL_SCHEME);
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);

        if ($scheme === '' || $host === '') {
            return '';
        }

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        $port = parse_url($baseUrl, PHP_URL_PORT);
        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = (string) parse_url($baseUrl, PHP_URL_PATH);
        $directory = str_ends_with($path, '/') ? $path : Str::beforeLast($path, '/').'/';

        return $origin.$directory.$href;
    }

    private function nullableUrl(string $baseUrl, mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $url = $this->resolveUrl($baseUrl, (string) $value);

        if (preg_match('~(?:^|[/_-])(?:logo|favicon|avatar|placeholder|default[-_]?image|no[-_]?image)(?:[/_.-]|$)~iu', (string) parse_url($url, PHP_URL_PATH)) === 1) {
            return null;
        }

        return $url !== '' ? Str::limit($url, 2000, '') : null;
    }

    private function firstValue(array $record, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($record, $path);

            if (filled($value) && is_scalar($value)) {
                return $value;
            }
        }

        return null;
    }

    private function plainText(mixed $value): string
    {
        return Str::squish(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}
     */
    private function item(mixed $externalId, string $title, string $body, ?string $url, ?string $image, mixed $date): array
    {
        $publishedAt = filled($date) ? rescue(fn (): Carbon => Carbon::parse((string) $date), now(), false) : now();

        return [
            'external_id' => filled($externalId) ? Str::limit((string) $externalId, 255, '') : ($url ? Str::limit($url, 255, '') : null),
            'title' => Str::limit($this->plainText($title), 500, ''),
            'body' => $this->plainText($body),
            'url' => $url,
            'image_url' => $image,
            'published_at' => $publishedAt->isFuture() ? now() : $publishedAt,
        ];
    }

    /**
     * @param  array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>  $items
     * @return array{items: array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>, method: string, url: string, status: int, fingerprint: string, crawled_pages: int}
     */
    private function result(array $items, string $method, string $url, Response $response, string $body): array
    {
        $items = $this->filterRecentItems($items);

        return [
            'items' => $items,
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'fingerprint' => $this->fingerprint($items, $body),
            'crawled_pages' => 1,
        ];
    }

    /** @param array<int, array{published_at: Carbon}> $items */
    private function filterRecentItems(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => $item['published_at']->greaterThanOrEqualTo(now()->subDays(2)),
        ));
    }

    /** @param array<int, array{title: string, url: ?string}> $items */
    private function fingerprint(array $items, string $fallback = ''): string
    {
        $content = collect($items)->map(fn (array $item): string => $item['title'].'|'.($item['url'] ?? ''))->implode("\n");

        return hash('sha256', $content !== '' ? $content : $fallback);
    }
}
