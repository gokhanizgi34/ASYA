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
    public function __construct(private readonly HoroscopeAiWriter $writer) {}

    /** @return array<int, HoroscopeForecast> */
    public function build(int $agencyId, CarbonInterface $date, User $user): array
    {
        $existing = HoroscopeForecast::query()
            ->where('agency_id', $agencyId)
            ->whereDate('forecast_date', $date->toDateString())
            ->get()
            ->keyBy(fn (HoroscopeForecast $forecast): string => $forecast->sign->value);

        if ($existing->count() === count(ZodiacSign::cases())) {
            return $existing->values()->all();
        }

        $generated = $this->writer->write($agencyId, $date);

        return DB::transaction(fn (): array => collect(ZodiacSign::cases())->map(function (ZodiacSign $sign) use ($agencyId, $date, $user, $existing, $generated): HoroscopeForecast {
            if ($existing->has($sign->value)) {
                return $existing->get($sign->value);
            }

            $title = $sign->label().' burcu '.$date->format('d.m.Y').' '.$date->translatedFormat('l').' yorumu ve burç özellikleri';

            return HoroscopeForecast::query()->create([
                'agency_id' => $agencyId,
                'forecast_date' => $date->toDateString(),
                'sign' => $sign,
                'symbol' => $sign->symbol(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'status' => HoroscopeStatus::Draft,
                'traits' => $generated[$sign->value]['traits'] ?? $sign->label().' burcunun temel özellikleri günlük koşullara göre farklı biçimde öne çıkabilir.',
                'rising' => $generated[$sign->value]['rising'] ?? 'Yükselen burcun etkisi kişisel doğum haritasına göre değişebilir.',
                'seo_title' => $title,
                'seo_description' => $sign->label().' burcu için '.$date->format('d.m.Y').' tarihli günlük yorum, burç özellikleri, yükselen etkileri ve şanslı detaylar.',
                'seo_keywords' => [$sign->label().' burcu', 'günlük '.$sign->label().' burç yorumu', $sign->label().' burcu özellikleri', $sign->label().' yükseleni'],
                ...$generated[$sign->value],
            ]);
        })->all(), 3);
    }
}
