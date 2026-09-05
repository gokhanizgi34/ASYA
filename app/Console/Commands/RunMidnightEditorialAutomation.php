<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\User;
use App\Services\HoroscopeDayBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

#[Signature('automation:midnight')]
#[Description('Gece yarısı haber, burç ve özel gün içerik otomasyonunu başlatır')]
class RunMidnightEditorialAutomation extends Command
{
    public function handle(HoroscopeDayBuilder $horoscopeBuilder): int
    {
        Artisan::call('news:import');
        $this->line(Artisan::output());

        $today = CarbonImmutable::today();
        Agency::query()->where('is_active', true)->orderBy('id')->each(function (Agency $agency) use ($today, $horoscopeBuilder): void {
            $user = User::query()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('id')->first();
            if (! $user) {
                return;
            }

            try {
                $horoscopeBuilder->build($agency->id, $today, $user);
                $this->info($agency->name.': günlük burçlar üretildi ve yayın kuyruğuna gönderildi.');
            } catch (Throwable $exception) {
                $this->error($agency->name.': burç üretimi başarısız: '.Str::limit($exception->getMessage(), 240));
            }
        });

        return self::SUCCESS;
    }
}
