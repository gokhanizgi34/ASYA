<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\NewsSource;
use Illuminate\Database\Seeder;

class InitialAgencySeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->updateOrCreate(
            ['slug' => 'asya-haber'],
            [
                'name' => 'ASYA Haber',
                'contact_email' => 'gokhanizgi@gmail.com',
                'subscription_ends_at' => now()->addYears(10),
                'is_active' => true,
            ],
        );

        NewsSource::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'domain' => 'trthaber.com'],
            [
                'created_by' => null,
                'name' => 'TRT Haber - Gündem',
                'feed_url' => 'https://www.trthaber.com/gundem_articles.rss',
                'feed_format' => 'rss',
                'source_type' => 'official',
                'notes' => 'İlk bağlantı testi için resmî TRT Haber RSS örneği.',
                'is_active' => true,
            ],
        );
    }
}
