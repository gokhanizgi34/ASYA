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

            return HoroscopeForecast::query()->create([
                'agency_id' => $agencyId,
                'forecast_date' => $date->toDateString(),
                'sign' => $sign,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'status' => HoroscopeStatus::Draft,
                ...$generated[$sign->value],
            ]);
        })->all(), 3);
    }
}
