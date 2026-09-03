<?php

namespace Tests\Feature;

use App\BlacklistRuleType;
use App\Models\Agency;
use App\Models\BlacklistRule;
use App\Services\BlacklistMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlacklistMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_supported_rule_types_and_records_hits(): void
    {
        $agency = Agency::factory()->create();
        $rules = collect([
            [BlacklistRuleType::Keyword, 'yasak içerik', 'yasak içerik'],
            [BlacklistRuleType::Domain, 'blocked.example', 'blocked.example'],
            [BlacklistRuleType::UrlPrefix, 'https://sub.blocked.example/news', 'https://sub.blocked.example/news'],
            [BlacklistRuleType::Source, 'Şüpheli Ajans', 'şüpheli ajans'],
        ])->map(fn (array $data) => BlacklistRule::factory()->for($agency)->create([
            'type' => $data[0],
            'pattern' => $data[1],
            'normalized_pattern' => $data[2],
        ]));

        $result = app(BlacklistMatcher::class)->evaluate($agency->id, [
            'title' => '<b>YASAK</b>    içerik bulundu',
            'body' => 'Haber gövdesi',
            'source_name' => 'ŞÜPHELİ AJANS',
            'source_url' => 'https://sub.blocked.example/news/42',
        ]);

        $this->assertTrue($result['blocked']);
        $this->assertCount(4, $result['matches']);
        $rules->each(function (BlacklistRule $rule): void {
            $rule->refresh();
            $this->assertSame(1, $rule->hit_count);
            $this->assertNotNull($rule->last_hit_at);
        });
    }

    public function test_review_rule_requires_review_without_blocking(): void
    {
        $agency = Agency::factory()->create();
        BlacklistRule::factory()->review()->for($agency)->create([
            'pattern' => 'kontrol',
            'normalized_pattern' => 'kontrol',
        ]);

        $result = app(BlacklistMatcher::class)->evaluate($agency->id, ['title' => 'Kontrol edilmeli'], false);

        $this->assertFalse($result['blocked']);
        $this->assertTrue($result['requires_review']);
    }

    public function test_inactive_and_other_agency_rules_are_ignored(): void
    {
        $agency = Agency::factory()->create();
        BlacklistRule::factory()->inactive()->for($agency)->create(['pattern' => 'engel', 'normalized_pattern' => 'engel']);
        BlacklistRule::factory()->create(['pattern' => 'engel', 'normalized_pattern' => 'engel']);

        $result = app(BlacklistMatcher::class)->evaluate($agency->id, ['body' => 'Bu metin engel içerir']);

        $this->assertFalse($result['blocked']);
        $this->assertTrue($result['matches']->isEmpty());
    }

    public function test_domain_normalization_removes_scheme_and_path(): void
    {
        $normalized = app(BlacklistMatcher::class)->normalize(BlacklistRuleType::Domain, 'HTTPS://Example.COM/news');

        $this->assertSame('example.com', $normalized);
    }
}
