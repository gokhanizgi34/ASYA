<?php

namespace App\Services;

use Illuminate\Support\Str;

class RecipeTurkishCorrector
{
    /** @var array<string, string> */
    private const WORDS = [
        'visneli' => 'vişneli',
        'visne' => 'vişne',
        'visneleri' => 'vişneleri',
        'yapragi' => 'yaprağı',
        'yapraklarin' => 'yaprakların',
        'bardagi' => 'bardağı',
        'pirinc' => 'pirinç',
        'sogan' => 'soğan',
        'soganlari' => 'soğanları',
        'yarim' => 'yarım',
        'cay' => 'çay',
        'zeytinyagi' => 'zeytinyağı',
        'zeytinyaginda' => 'zeytinyağında',
        'cekirdeksiz' => 'çekirdeksiz',
        'tatli' => 'tatlı',
        'kasigi' => 'kaşığı',
        'sicak' => 'sıcak',
        'haslayin' => 'haşlayın',
        'haşlayin' => 'haşlayın',
        'dograyip' => 'doğrayıp',
        'yikanmis' => 'yıkanmış',
        'alip' => 'alıp',
        'ortasina' => 'ortasına',
        'harctan' => 'harçtan',
        'boregi' => 'böreği',
        'sarin' => 'sarın',
        'dizdiginiz' => 'dizdiğiniz',
        'uzerine' => 'üzerine',
        'ateste' => 'ateşte',
        'pisirin' => 'pişirin',
        'baharatlari' => 'baharatları',
        'sarmalarin' => 'sarmaların',
        'hazirlayin' => 'hazırlayın',
        'karistirin' => 'karıştırın',
        'karistirip' => 'karıştırıp',
        'cirpin' => 'çırpın',
        'dograyin' => 'doğrayın',
        'kizartin' => 'kızartın',
        'kizgin' => 'kızgın',
        'firin' => 'fırın',
        'firinda' => 'fırında',
        'firinlayin' => 'fırınlayın',
        'yag' => 'yağ',
        'yagi' => 'yağı',
        'tereyagi' => 'tereyağı',
        'yogurt' => 'yoğurt',
        'sarimsak' => 'sarımsak',
        'soguk' => 'soğuk',
        'corba' => 'çorba',
        'corbasi' => 'çorbası',
        'sut' => 'süt',
        'seker' => 'şeker',
        'sekeri' => 'şekeri',
        'cilek' => 'çilek',
        'kirmizi' => 'kırmızı',
        'yesil' => 'yeşil',
        'kagit' => 'kağıt',
        'kagidi' => 'kağıdı',
        'kagitlari' => 'kağıtları',
        'gogus' => 'göğüs',
        'gogsu' => 'göğsü',
        'uzeri' => 'üzeri',
        'uzerini' => 'üzerini',
        'koydugunuz' => 'koyduğunuz',
        'eklediginiz' => 'eklediğiniz',
        'pisene' => 'pişene',
        'yumusayana' => 'yumuşayana',
        'kaynamis' => 'kaynamış',
        'kaynatin' => 'kaynatın',
        'cevirin' => 'çevirin',
        'firca' => 'fırça',
        'surun' => 'sürün',
        'dogranmis' => 'doğranmış',
        'kavrulmus' => 'kavrulmuş',
        'haslanmis' => 'haşlanmış',
        'haslayip' => 'haşlayıp',
        'suzun' => 'süzün',
        'sogumaya' => 'soğumaya',
        'sogutun' => 'soğutun',
    ];

    public function correct(string $text): string
    {
        return preg_replace_callback('/[\p{L}]+/u', function (array $match): string {
            $word = $match[0];
            $corrected = self::WORDS[Str::lower($word)] ?? $word;

            if ($word === Str::upper($word)) {
                return Str::upper($corrected);
            }

            return preg_match('/^\p{Lu}/u', $word) === 1
                ? Str::ucfirst($corrected)
                : $corrected;
        }, $text) ?? $text;
    }
}
