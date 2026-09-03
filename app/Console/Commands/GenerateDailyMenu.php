<?php

namespace App\Console\Commands;

use App\ArticleStatus;
use App\DailyMenuBuilder;
use App\Models\Agency;
use App\Models\Article;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:generate-daily-menu')]
#[Description('Günün dört tarifinden ajans menü haberi oluşturur')]
class GenerateDailyMenu extends Command
{
    public function handle(DailyMenuBuilder $builder): int
    {
        $agencies = Agency::query()->where('is_active', true)->orderBy('id')->get();
        $slug = 'bugun-aksam-ne-yapsam-'.today()->format('Y-m-d');

        if ($agencies->isNotEmpty() && $agencies->every(fn (Agency $agency): bool => Article::query()->where('agency_id', $agency->id)->where('slug', $slug)->exists())) {
            $this->info('Bugünün menü haberleri zaten oluşturulmuş.');

            return self::SUCCESS;
        }

        $menu = $builder->build();

        if ($menu->count() < 4) {
            $this->error('Günlük menü için dört kategorinin tamamında aktif tarif bulunamadı.');

            return self::FAILURE;
        }

        $created = 0;

        $agencies->each(function (Agency $agency) use ($menu, $slug, &$created): void {
            if (Article::query()->where('agency_id', $agency->id)->where('slug', $slug)->exists()) {
                return;
            }

            $author = User::query()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('id')->first();
            if (! $author) {
                return;
            }

            $sections = $menu->map(fn ($recipe, string $category): string => "## {$this->label($category)}\n\n**{$recipe->title}**\n\nMalzemeler: {$recipe->ingredients}\n\nYapılışı: {$recipe->instructions}")->implode("\n\n");
            Article::query()->create([
                'agency_id' => $agency->id,
                'author_id' => $author->id,
                'title' => 'Bugün akşam ne yapsam? '.today()->translatedFormat('d F Y'),
                'slug' => 'bugun-aksam-ne-yapsam-'.today()->format('Y-m-d'),
                'summary' => 'Bugünün ana yemek, soğuk yemek, salata ve tatlı menüsü.',
                'body' => "Bugün akşam ne yapsam diye düşünenler için dört lezzetli tariften oluşan günlük menü.\n\n{$sections}",
                'status' => ArticleStatus::Draft,
                'source_trust_status' => 'verified',
                'source_name' => 'ASYA Tarif Havuzu',
                'source_url' => null,
                'editorial_metadata' => ['content_type' => 'daily_menu', 'menu_date' => today()->toDateString()],
            ]);
            $created++;
        });

        if ($created === 0) {
            $this->warn('Bugünün menü haberi daha önce oluşturulmuş veya aktif ajans bulunamadı.');
        } else {
            $this->info($created.' ajans için günlük menü haberi oluşturuldu.');
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
