<?php

namespace App\Services;

use App\HoroscopeStatus;
use App\Models\HoroscopeForecast;
use App\Models\User;
use App\ZodiacSign;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class HoroscopeDayBuilder
{
    public function __construct(
        private readonly HoroscopeAiWriter $writer,
        private readonly GeneratedContentPublicationService $publisher,
    ) {}

    /** @return array<int, HoroscopeForecast> */
    public function build(int $agencyId, CarbonInterface $date, User $user): array
    {
        $existing = HoroscopeForecast::query()->where('agency_id', $agencyId)->whereDate('forecast_date', $date->toDateString())->get()
            ->keyBy(fn (HoroscopeForecast $forecast): string => $forecast->sign->value);

        if ($existing->count() === count(ZodiacSign::cases())) {
            $forecasts = $existing->values()->all();
            $this->sendToPublicationCenter($agencyId, $date, $user, $forecasts);

            return $forecasts;
        }

        $generated = $this->writer->write($agencyId, $date);
        $forecasts = DB::transaction(fn (): array => collect(ZodiacSign::cases())->map(function (ZodiacSign $sign) use ($agencyId, $date, $user, $existing, $generated): HoroscopeForecast {
            if ($existing->has($sign->value)) {
                return $existing->get($sign->value);
            }
            $title = $sign->label().' burcu '.$date->format('d.m.Y').' '.$date->translatedFormat('l').' yorumu ve burç özellikleri';

            return HoroscopeForecast::query()->create([
                'agency_id' => $agencyId, 'forecast_date' => $date->toDateString(), 'sign' => $sign, 'symbol' => $sign->symbol(),
                'created_by' => $user->id, 'updated_by' => $user->id, 'status' => HoroscopeStatus::Published,
                'traits' => $generated[$sign->value]['traits'] ?? $sign->label().' burcunun temel özellikleri günlük koşullara göre farklı biçimde öne çıkabilir.',
                'rising' => $generated[$sign->value]['rising'] ?? 'Yükselen burcun etkisi kişisel doğum haritasına göre değişebilir.',
                'seo_title' => $title, 'seo_description' => $sign->label().' burcu için '.$date->format('d.m.Y').' tarihli günlük yorum, burç özellikleri, yükselen etkileri ve şanslı detaylar.',
                'seo_keywords' => [$sign->label().' burcu', 'günlük '.$sign->label().' burç yorumu', $sign->label().' burcu özellikleri', $sign->label().' yükseleni'],
                ...$generated[$sign->value], 'published_at' => now(),
            ]);
        })->all(), 5);

        $this->sendToPublicationCenter($agencyId, $date, $user, $forecasts);

        return $forecasts;
    }

    /** @param array<int, HoroscopeForecast> $forecasts */
    private function sendToPublicationCenter(int $agencyId, CarbonInterface $date, User $user, array $forecasts): void
    {
        $body = collect($forecasts)->map(fn (HoroscopeForecast $forecast): string => '## '.$forecast->symbol.' '.$forecast->sign->label()." Burcu\n\n".$forecast->general."\n\nAşk: ".$forecast->love."\n\nKariyer ve para: ".$forecast->career.' '.$forecast->money."\n\nSağlık: ".$forecast->health)->implode("\n\n");
        $this->publisher->send($agencyId, $user, [
            'title' => $date->format('d.m.Y').' Günlük Burç Yorumları',
            'summary' => $date->format('d.m.Y').' tarihli Koç’tan Balık’a tüm burçların aşk, kariyer, para ve sağlık yorumları.',
            'body' => $body,
            'keywords' => ['günlük burç yorumları', $date->format('d.m.Y').' burç yorumları', 'bugünün burç yorumu'],
            'hashtags' => ['#GünlükBurç', '#BurçYorumları'],
            'category' => 'Burçlar',
            'source_type' => 'horoscope_day',
            'source_id' => $date->toDateString(),
            'slug' => 'gunluk-burc-yorumlari-'.$date->format('Y-m-d'),
            'destination' => 'publish',
        ]);
    }
}
