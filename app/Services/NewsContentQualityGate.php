<?php

namespace App\Services;

use App\Models\Article;
use App\Models\RawNewsItem;
use DomainException;
use Illuminate\Support\Str;

class NewsContentQualityGate
{
    public function assertRawNews(RawNewsItem $rawNewsItem): void
    {
        $body = $this->plainText($rawNewsItem->original_body);
        $title = $this->plainText($rawNewsItem->original_title);
        $isSocialSource = $rawNewsItem->newsSource?->source_type === 'social';

        if (preg_match('/^(?:t\\.?c\\.?\\s*)?.{2,80}\\s+(?:belediyesi|valiliği|kaymakamlığı)$/iu', $title) === 1) {
            throw new DomainException('Sayfa başlığı bir haber başlığı değil; kurumsal liste veya ana sayfa içeriğidir.');
        }

        if ((! $isSocialSource && Str::length($body) < 350) || count($this->sentences($body)) < ($isSocialSource ? 1 : 4)) {
            throw new DomainException('Ham haber özeti değil, en az dört anlamlı cümleden oluşan tam haber gövdesi gereklidir.');
        }

        if (preg_match('/Google Trends verilerine göre|arama hacmi|trendle ilişkilendirilen|yalnızca bağlantılı kaynak|detaylı bilgi için lütfen tıklayınız/iu', $body) === 1) {
            throw new DomainException('Trend sinyali tam haber içeriği değildir; bağlantılı haber gövdesi alınmalıdır.');
        }

        $newsText = $this->plainText($rawNewsItem->original_title.' '.$body);
        if (preg_match('/gizlilik (?:ve |)çerez|çerez (?:politikası|ilkeleri|tercihleri)|gizlilik politikası|kişisel verilerin korunması|kvkk|aydınlatma metni|kullanım koşulları|site haritası|üyelik sözleşmesi|mesafeli satış|iade politikası/iu', $newsText) === 1) {
            throw new DomainException('İçerik haber değil; politika, çerez, sözleşme veya kurumsal yardımcı sayfadır.');
        }
        if (preg_match('/sepete ekle|hemen satın al|kupon kodu|indirim kodu|sponsorlu içerik|reklamdır|üyelik fırsatı|ücretsiz dene|kampanya fırsatları|fiyat karşılaştır/iu', $newsText) === 1) {
            throw new DomainException('İçerik haber yerine reklam, satış veya spam metni içeriyor.');
        }

        if (preg_match('/başladı|açıldı|tamamlandı|düzenlendi|gerçekleştirildi|duyurdu|açıkladı|bildirildi|sürüyor|devam ediyor|buluştu|katıldı|ziyaret etti|toplantı|karar|proje|çalışma|etkinlik|festival|operasyon|kaza|yangın|gözaltı|hayatını kaybetti|kazandı|imzalandı|hizmete|başlayacak/iu', $newsText) !== 1) {
            throw new DomainException('Metinde doğrulanabilir bir olay, karar, açıklama veya gelişme bulunamadı.');
        }
    }

    /** @param array{title: string, summary: string, body: string} $content */
    public function assertGenerated(RawNewsItem $rawNewsItem, array $content): void
    {
        $this->assertRawNews($rawNewsItem);
        $body = $this->plainText($content['body']);
        $sentences = $this->sentences($body);

        if (Str::length($body) < 700 || count(preg_split('/\s+/u', $body) ?: []) < 100 || count($sentences) < 6) {
            throw new DomainException('AI çıktısı eksiksiz bir haber için yeterli uzunluk ve paragraf bütünlüğü taşımıyor.');
        }

        $normalizedSentences = collect($sentences)->map(fn (string $sentence): string => Str::lower(Str::squish($sentence)));
        if ($normalizedSentences->unique()->count() / max(1, $normalizedSentences->count()) < 0.75) {
            throw new DomainException('AI çıktısında tekrarlanan veya dolgu cümleler tespit edildi.');
        }

        $sourceTokens = $this->significantTokens($rawNewsItem->original_title.' '.$rawNewsItem->original_body);
        $outputTokens = $this->significantTokens($content['title'].' '.$content['summary'].' '.$body);
        if (count(array_intersect($sourceTokens, $outputTokens)) < 5) {
            throw new DomainException('AI çıktısı ham haberdeki kişi, kurum, yer ve olay ayrıntılarıyla yeterince örtüşmüyor.');
        }

        if (preg_match('/kaynak metindeki doğrulanmış bilgiler|bu haber notunda|yeterli ayrıntı bulunmadığı|okurların .* başvur/iu', $body) === 1) {
            throw new DomainException('AI çıktısında haber yerine sistem açıklaması veya dolgu metni tespit edildi.');
        }

        if (preg_match('~https?://|www\.|\b[a-z0-9-]+\.(?:com|net|org|com\.tr|bel\.tr)\b~iu', $content['title'].' '.$content['summary'].' '.$body) === 1) {
            throw new DomainException('AI çıktısında başka bir siteye ait bağlantı veya alan adı tespit edildi.');
        }

        if (preg_match('/Belediyenin yayımladığı duyuruda|kurumsal mecralarında|referans teşkil|resmî internet sitesindeki|resmi internet sitesindeki|bilgisi kamuoyuyla paylaşıldı|duyuru .* ortaya koyuyor/iu', $body) === 1) {
            throw new DomainException('AI çıktısında robotik kaynak açıklaması tespit edildi.');
        }
    }

    public function assertPublishable(Article $article, RawNewsItem $rawNewsItem): void
    {
        $this->assertGenerated($rawNewsItem, [
            'title' => $article->title,
            'summary' => $article->summary,
            'body' => $article->body,
        ]);
    }

    private function plainText(string $value): string
    {
        return Str::of(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    /** @return array<int, string> */
    private function sentences(string $body): array
    {
        return collect(preg_split('/(?<=[.!?])\s+/u', $body) ?: [])
            ->map(fn (string $sentence): string => trim($sentence))
            ->filter(fn (string $sentence): bool => Str::length($sentence) >= 25)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function significantTokens(string $text): array
    {
        $stopWords = ['olarak', 'olduğu', 'olacak', 'için', 'ile', 'daha', 'haber', 'açıklama', 'yapılan', 'edilen', 'ancak', 'sonra', 'kadar', 'göre'];

        return collect(preg_split('/[^\pL\pN]+/u', Str::lower($this->plainText($text))) ?: [])
            ->filter(fn (string $token): bool => Str::length($token) >= 5 && ! in_array($token, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }
}
