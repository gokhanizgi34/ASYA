<?php

namespace Tests\Unit;

use App\Models\Agency;
use App\Models\NewsSource;
use App\Models\RawNewsItem;
use App\Services\NewsContentQualityGate;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsContentQualityGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_feed_summary_is_rejected_as_incomplete_news(): void
    {
        $rawNewsItem = RawNewsItem::factory()->for(Agency::factory())->create([
            'original_body' => 'Belediye yeni park çalışmasını duyurdu. Ayrıntılar daha sonra açıklanacak.',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tam haber gövdesi');

        app(NewsContentQualityGate::class)->assertRawNews($rawNewsItem);
    }

    public function test_repetitive_ai_filler_is_rejected(): void
    {
        $rawNewsItem = $this->rawNewsItem();
        $sentence = 'Pendik Belediyesi sahil düzenleme projesindeki doğrulanmış çalışmalar hakkında ayrıntılı bilgi verdi. ';
        $content = [
            'title' => 'Pendik sahilinde düzenleme çalışmaları sürüyor',
            'summary' => 'Pendik sahilindeki çalışmaların kapsamı ve uygulama takvimi açıklandı.',
            'body' => str_repeat($sentence, 14),
        ];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tekrarlanan');

        app(NewsContentQualityGate::class)->assertGenerated($rawNewsItem, $content);
    }

    public function test_social_source_short_event_caption_passes_the_raw_news_gate(): void
    {
        $agency = Agency::factory()->create();
        $source = NewsSource::factory()->for($agency)->create(['source_type' => 'social']);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->for($source, 'newsSource')->create([
            'original_title' => 'Aydos Kalesi tadilat nedeniyle kapanıyor',
            'original_body' => 'Aydos Kalesi tadilat çalışmaları nedeniyle 7-11 Eylül tarihleri arasında ziyarete kapalı olacaktır. 12 Eylül itibarıyla yeniden açılacaktır.',
        ]);

        app(NewsContentQualityGate::class)->assertRawNews($rawNewsItem);
        $this->addToAssertionCount(1);
    }

    public function test_coherent_grounded_agency_story_passes_quality_gate(): void
    {
        $rawNewsItem = $this->rawNewsItem();
        $content = [
            'title' => 'Pendik sahilinde yenileme çalışmaları başladı',
            'summary' => 'Pendik Belediyesi, Doğu Mahallesi sahilindeki yürüyüş yolu ve çocuk parkı yenileme çalışmalarını başlattı.',
            'body' => $this->generatedBody(),
        ];

        app(NewsContentQualityGate::class)->assertGenerated($rawNewsItem, $content);

        $this->addToAssertionCount(1);
    }

    private function rawNewsItem(): RawNewsItem
    {
        return RawNewsItem::factory()->for(Agency::factory())->create([
            'original_title' => 'Pendik sahilinde yürüyüş yolu ve çocuk parkı yenileniyor',
            'original_body' => implode(' ', [
                'Pendik Belediyesi, Doğu Mahallesi sahilinde yenileme çalışması başlattı.',
                'Proje kapsamında yürüyüş yolunun zemini değiştirilecek ve aydınlatma direkleri yenilenecek.',
                'Çocuk parkındaki oyun grupları güvenlik standartlarına uygun yeni ekipmanlarla değiştirilecek.',
                'Çalışmaların ekim ayının ikinci haftasında tamamlanması planlanıyor.',
                'Belediye ekipleri çalışma süresince sahil yolunun belirli bölümlerini yaya kullanımına kapatacak.',
                'Vatandaşların yönlendirme levhalarını takip etmesi ve alternatif geçiş güzergâhlarını kullanması istendi.',
                'Düzenlemenin tamamlanmasının ardından kıyı hattında dinlenme alanları ve yeni banklar hizmete açılacak.',
            ]),
        ]);
    }

    private function generatedBody(): string
    {
        return implode("\n\n", [
            'Pendik Belediyesi, Doğu Mahallesi sahilindeki yürüyüş yolu ile çocuk parkını kapsayan yenileme çalışmalarını başlattı. Belediye ekipleri proje alanında zemin sökümü ve güvenlik çevirmesi yaptı.',
            'Çalışma kapsamında yürüyüş yolunun yıpranan zemini tamamen değiştirilecek. Sahil hattındaki aydınlatma direkleri de enerji verimliliği yüksek yeni sistemlerle yenilenecek.',
            'Çocuk parkında bulunan eski oyun grupları kaldırılacak ve güvenlik standartlarına uygun ekipmanlar yerleştirilecek. Park zemininin darbe emici malzemeyle kaplanması planlanıyor.',
            'Belediyenin açıkladığı takvime göre çalışmaların ekim ayının ikinci haftasında tamamlanması hedefleniyor. Programın hava koşullarına bağlı olarak sahadaki ilerlemeye göre uygulanacağı belirtildi.',
            'Uygulama süresince sahil yolunun belirli bölümleri yaya kullanımına geçici olarak kapatılacak. Vatandaşlardan yönlendirme levhalarını izlemeleri ve gösterilen alternatif geçişleri kullanmaları istendi.',
            'Yenileme tamamlandığında kıyı hattına yeni dinlenme alanları ile banklar eklenecek. Düzenlemenin sahilde yürüyüş yapanlar ve çocuklu aileler için daha güvenli bir kullanım sağlaması amaçlanıyor.',
            'Ekipler, çalışma alanında günlük saha kontrolü yaparak açık bölümlerde yaya güvenliğini sağlayacak. Projenin tamamlanan kısımları kontrollerin ardından aşamalı biçimde kullanıma açılacak.',
        ]);
    }
}
