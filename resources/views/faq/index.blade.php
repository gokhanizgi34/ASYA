<x-layouts.app title="S.S.S. ve Kullanım Rehberi">
@php
$sections = [
['Genel Bakış','Sistemin güncel durumunu, özet sayaçları ve son hareketleri tek ekranda izleyin.','Sol menüden Genel Bakış’ı açın; kartlardan ilgili iş akışına geçin.'],
['Haberler','Haber içeriklerini oluşturur, düzenler ve yayın öncesi yönetir.','Yeni haber ekleyin, başlık ve metni tamamlayın, durumunu kaydedin.'],
['SEO Analizi','Bir haberin arama motoru uygunluğunu ölçer ve iyileştirme önerileri verir.','Haber ayrıntısından SEO ekranını açıp analizi çalıştırın.'],
['Ham Haber Havuzu','Kaynaklardan gelen işlenmemiş haberleri toplar ve toplu işlem uygular.','Kayıtları filtreleyin; uygun olanları seçip işleme veya habere dönüştürün.'],
['Burçlar','Günlük burç içeriklerini hazırlar ve düzenler.','Günü hazırlayın, burç metinlerini gözden geçirip güncelleyin.'],
['AI Yazarlar','Yapay zekâ destekli köşe yazarı profilleri ve yazı taslakları oluşturur.','Yazar kişiliğini tanımlayın; konu verip taslak üretin ve editör kontrolünden geçirin.'],
['Promptlar','Yapay zekâ talimatlarını merkezi ve tekrar kullanılabilir biçimde saklar.','Prompt ekleyin, simülasyonda sınayın ve ilgili iş akışında kullanın.'],
['AI Haber Üretimi','Çok sayıda içeriği toplu görevler halinde üretir.','İçerik grubunu ve adetleri belirleyin; görevi kuyruğa gönderip sonucu izleyin.'],
['Yazım Dili Hafızası','Ajansın kelime tercihlerini öğrenerek metinleri mümkün olduğunda AI tokenı harcamadan özgünleştirir.','Örnek metinleri ve kelime dönüşümlerini kaydedin; günlük kotayı ve başarılı içeriğin yayın mı taslak mı olacağını seçin.'],
['AI Haber Görseli','Haber görsellerini üretir, değerlendirir ve kapak görseli seçer.','İstek oluşturun, adayları değerlendirin ve kapak olarak birini seçin.'],
['Yayın Merkezi','Hazır içerikleri hedef sitelere gönderir ve gönderim sonucunu takip eder.','İçeriği ve hedefi seçin; yayını başlatıp durum kaydını inceleyin.'],
['Yayın Hedefleri','WordPress veya diğer yayın noktalarının bağlantılarını tanımlar.','Hedef adresini ve erişim bilgisini girip etkinleştirin.'],
['Çoklu Site Dağıtım','Tek içeriği birden fazla siteye kontrollü biçimde dağıtır.','Haber ile hedef siteleri seçin ve toplu gönderimi başlatın.'],
['Haber Kaynağı Girişi','Haber kaynaklarının güvenilirlik puanlarını ve değerlendirmelerini tutar.','Bağlantıyı ekleyin; otomatik alımı ve tek güven puanını izleyin.'],
['Sosyal Yayıncı','Sosyal medya hesapları için gönderi hazırlar ve yayınlar.','Hesabı bağlayın, gönderiyi oluşturun ve yayın komutunu verin.'],
['Sosyal Akış','Sosyal kaynaklardan içerik akışlarını içe aktarır.','Kaynak adresini ekleyin ve içe aktarma işlemini çalıştırın.'],
['Sosyal Dinleme','Marka, kişi veya kelimeler hakkındaki sosyal bahisleri izler.','İzleme kuralı oluşturun; bulunan bahisleri inceleyip durumlandırın.'],
['Trend Motoru','Gündemde yükselen konu ve kelimeleri analiz eder.','Analizi çalıştırın; trend ayrıntısından içerik fırsatlarını değerlendirin.'],
['Kampanyalar','Belirli hedef ve tarih aralığına bağlı içerik çalışmalarını yönetir.','Kampanya oluşturun, içerik ekleyin ve her parçanın durumunu izleyin.'],
['Takvim','Yayın ve operasyon işlerini zaman planına yerleştirir.','Yeni plan ekleyin, tarih-saat belirleyin ve tamamlandıkça durumunu değiştirin.'],
['Analitik','İçerik ve operasyon performansını raporlar.','Verileri yenileyin, dönem sonuçlarını inceleyin veya dışarı aktarın.'],
['Bildirimler','Sistem olaylarını ve kullanıcıya düşen uyarıları tek yerde gösterir.','Kaydı açın; okundu işaretleyin veya tümünü topluca okuyun.'],
['Hata Kayıtları','Teknik hataları güvenli biçimde kaydeder, tekrarlarını birleştirir ve çözüm sürecini izler.','Yetkili kullanıcı ayrıntıyı açıp çözüm notu ve durum girer. İlk hata güvenli özet e-postası üretir.'],
['API Entegrasyonları','Yapay zekâ ve dış servis bağlantılarını ajans bazında yönetir.','Sağlayıcıyı seçin, model ile API anahtarını girin ve bağlantıyı test edin. Bir ajansa birden çok AI eklenebilir.'],
['Kara Liste','Yasaklı kelime, alan adı veya içerik kurallarını uygular.','Kural ekleyin, kapsamı belirleyin ve gerektiğinde devre dışı bırakın.'],
['Taksonomi','Kategori ve etiketleri farklı sistemler arasında eşleştirir.','Kaynak değer ile hedef değeri eşleştirip kaydedin.'],
['Rota Öğrenici','Dış servis uç noktalarının çalışma bilgisini öğrenir ve durumunu izler.','Öğrenilen rotaları inceleyin; güvenli olanları etkinleştirin.'],
['Yetki Matrisi','Rollerin hangi ekran ve işlemlere erişebildiğini gösterir.','Rol sütunları üzerinden izinleri kontrol edin; değişiklikleri kullanıcı yönetiminden uygulayın.'],
['Veritabanı Yedekleri','Sistem verisinin yedeğini oluşturur, doğrular ve indirir.','Yedek oluşturun, bütünlüğünü doğrulayın ve güvenli yerde saklayın.'],
['Sistem Ayarları','Uygulamanın genel davranış ve görünüm seçeneklerini yönetir.','Ayarı değiştirip kaydedin; etkisini ilgili ekranda doğrulayın.'],
['Ajanslar ve Kullanıcılar','Ajans hesaplarını, kullanıcıları, rolleri ve aktiflik durumlarını yönetir.','Önce ajansı, sonra kullanıcıyı oluşturun; doğru rolü atayıp erişimi etkinleştirin.'],
];
@endphp
<section class="space-y-8">
<header><p class="text-sm font-bold tracking-[.18em] text-cyan-300">S.S.S. VE KULLANIM REHBERİ</p><h1 class="mt-3 text-4xl font-black">ASYA nasıl kullanılır?</h1><p class="mt-3 max-w-3xl text-slate-400">Programdaki 31 ana bölümün amacı ve temel kullanımı. Aradığınız başlığı tarayıcının sayfada bul özelliğiyle de arayabilirsiniz.</p></header>
<div class="grid gap-4 md:grid-cols-2">@foreach($sections as $index => [$name,$purpose,$usage])<details class="group rounded-2xl border border-white/10 bg-white/[.04] p-5" @if($index===0) open @endif><summary class="flex cursor-pointer list-none items-center justify-between gap-3"><span><small class="mr-2 text-cyan-300">{{ str_pad($index+1,2,'0',STR_PAD_LEFT) }}</small><strong>{{ $name }}</strong></span><span class="text-slate-500 group-open:rotate-45">+</span></summary><div class="mt-4 space-y-3 border-t border-white/10 pt-4 text-sm"><p><strong class="text-cyan-300">Amacı:</strong> <span class="text-slate-300">{{ $purpose }}</span></p><p><strong class="text-violet-300">Kullanımı:</strong> <span class="text-slate-300">{{ $usage }}</span></p></div></details>@endforeach</div>
<div class="rounded-2xl border border-cyan-400/20 bg-cyan-400/5 p-6"><h2 class="text-xl font-bold">Yanıt bulamadınız mı?</h2><p class="mt-2 text-slate-400">Destek talebi oluşturduğunuzda kayıt panelde tutulur ve ayarlı bildirim adresine e-posta gönderilir.</p><a href="{{ route('support-tickets.create') }}" class="mt-4 inline-block rounded-xl bg-cyan-400 px-4 py-2 font-bold text-slate-950">Destek talebi oluştur</a></div>
</section>
</x-layouts.app>
