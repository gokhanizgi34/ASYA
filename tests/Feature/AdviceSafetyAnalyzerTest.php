<?php

namespace Tests\Feature;

use App\AdviceRiskLevel;
use App\Services\AdviceSafetyAnalyzer;
use Tests\TestCase;

class AdviceSafetyAnalyzerTest extends TestCase
{
    public function test_safe_text_is_low_risk(): void
    {
        $result = app(AdviceSafetyAnalyzer::class)->analyze('İş yerinde iletişim konusunda daha dengeli davranmak istiyorum.');

        $this->assertSame(AdviceRiskLevel::Low, $result['risk_level']);
        $this->assertSame([], $result['flags']);
    }

    public function test_personal_contact_and_bank_data_are_high_risk(): void
    {
        $result = app(AdviceSafetyAnalyzer::class)->analyze('Bana test@example.com veya 0532 123 45 67 üzerinden ulaşın. TR330006100519786457841326 hesabım.');

        $this->assertSame(AdviceRiskLevel::High, $result['risk_level']);
        $this->assertEqualsCanonicalizing(['email', 'phone', 'iban'], $result['flags']);
    }

    public function test_immediate_safety_language_is_critical(): void
    {
        $result = app(AdviceSafetyAnalyzer::class)->analyze('Artık dayanamıyorum ve intihar etmeyi düşünüyorum.');

        $this->assertSame(AdviceRiskLevel::Critical, $result['risk_level']);
        $this->assertContains('immediate_safety', $result['flags']);
    }
}
