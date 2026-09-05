<?php

namespace App\Console\Commands;

use App\DailyMenuBuilder;
use App\Models\Agency;
use App\Models\Article;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:generate-daily-menu')]
#[Description('Günün dört tarifinden ajans menü haberi oluşturur')]
class GenerateDailyMenu extends Command
{
    public function handle(DailyMenuBuilder $builder, GeneratedContentPublicationService $publisher): int
    {
        $agencies = Agency::query()->where('is_active', true)->orderBy('id')->get();
        $slug = 'bugun-aksam-ne-yapsam-'.today()->format('Y-m-d');

        if ($agencies->isNotEmpty() && $agencies->every(fn (Agency $agency): bool => Article::query()->where('agency_id', $agency->id)->where('slug', $slug)->exists())) {
            $this->info('Bugünün menü yayınları zaten oluşturulmuş.');

            return self::SUCCESS;
        }
        $menu = $builder->build();

        if ($menu->count() < 4) {
            $this->error('Günlük menü için dört kategorinin tamamında aktif tarif bulunamadı.');

            return self::FAILURE;
        }

        $published = 0;

        $agencies->each(function (Agency $agency) use ($menu, $publisher, &$published): void {
            $author = User::query()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('id')->first();
            if (! $author) {
                return;
            }

            $sections = $menu->map(fn ($recipe, string $category): string => "## {$this->label($category)}\n\n**{$recipe->title}**\n\nMalzemeler: {$recipe->ingredients}\n\nYapılışı: {$recipe->instructions}")->implode("\n\n");
            $publisher->send($agency->id, $author, [
                'title' => 'Bugün akşam ne yapsam? '.today()->translatedFormat('d F Y'),
                'summary' => 'Bugünün ana yemek, soğuk yemek, salata ve tatlı menüsü.',
                'body' => "Bugün akşam ne yapsam diye düşünenler için dört lezzetli tariften oluşan günlük menü.\n\n{$sections}",
                'keywords' => ['günlük menü', 'akşam yemeği tarifleri', 'bugün ne pişirsem'],
                'hashtags' => ['#GünlükMenü', '#YemekTarifleri'],
                'category' => 'Yemek Tarifleri',
                'source_type' => 'daily_menu',
                'source_id' => today()->toDateString(),
                'slug' => 'bugun-aksam-ne-yapsam-'.today()->format('Y-m-d'),
                'destination' => 'publish',
            ]);
            $published++;
        });

        if ($published === 0) {
            $this->warn('Günlük menü için aktif ajans veya aktif kullanıcı bulunamadı.');
        } else {
            $this->info($published.' ajans için günlük menü Yayın Merkezi’ne gönderildi.');
        }

        return self::SUCCESS;
    }

    private function label(string $category): string
    {
        return match ($category) {
            'main' => 'Ana yemek',
            'cold' => 'Soğuk yemek',
            'salad' => 'Salata',
            'dessert' => 'Tatlı',
            default => Str::headline($category),
        };
    }
}
