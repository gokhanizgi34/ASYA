<?php

namespace Tests\Feature;

use App\AdviceLetterStatus;
use App\AdviceRiskLevel;
use App\Models\AdviceLetter;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdviceLetterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_sees_and_submits_only_within_own_agency_but_cannot_answer(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $own = AdviceLetter::factory()->for($agency)->create(['pseudonym' => 'Kendi Mektubu']);
        $other = AdviceLetter::factory()->for($otherAgency)->create(['pseudonym' => 'Başka Mektup']);

        $this->actingAs($editor)->get(route('advice-letters.index'))
            ->assertOk()->assertSee($own->pseudonym)->assertDontSee($other->pseudonym);
        $this->actingAs($editor)->get(route('advice-letters.show', $other))->assertForbidden();
        $this->actingAs($editor)->get(route('advice-letters.edit', $own))->assertForbidden();

        $this->actingAs($editor)->post(route('advice-letters.store'), $this->payload($otherAgency, ['pseudonym' => 'Yeni Takma Ad']))->assertRedirect();
        $this->assertDatabaseHas('advice_letters', ['agency_id' => $agency->id, 'pseudonym' => 'Yeni Takma Ad']);
    }

    public function test_letter_content_is_encrypted_and_personal_data_is_flagged(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $question = 'Benim telefonum 0532 123 45 67 ve bu bilgiyi paylaşarak ilişkim hakkında ne yapacağımı sormak istiyorum.';

        $this->actingAs($owner)->post(route('advice-letters.store'), $this->payload($agency, ['question' => $question]))->assertRedirect();

        $letter = AdviceLetter::query()->sole();
        $this->assertSame($question, $letter->question);
        $this->assertSame(AdviceRiskLevel::High, $letter->risk_level);
        $this->assertContains('phone', $letter->risk_flags);
        $this->assertStringNotContainsString('0532 123 45 67', (string) DB::table('advice_letters')->value('question'));
    }

    public function test_owner_can_answer_and_publish_consented_letter(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $letter = AdviceLetter::factory()->for($agency)->create(['publication_consent' => true]);

        $this->actingAs($owner)->put(route('advice-letters.update', $letter), [
            'status' => AdviceLetterStatus::Published->value,
            'response_title' => 'Sınırlarınızı açıkça konuşun',
            'response_body' => str_repeat('Karşılıklı sınırlarınızı sakin ve açık bir dille konuşmanız, beklentilerinizi netleştirmeniz yararlı olacaktır. ', 2),
        ])->assertRedirect(route('advice-letters.show', $letter));

        $letter->refresh();
        $this->assertSame(AdviceLetterStatus::Published, $letter->status);
        $this->assertSame($owner->id, $letter->answered_by);
        $this->assertNotNull($letter->answered_at);
        $this->assertNotNull($letter->published_at);
    }

    public function test_critical_letter_cannot_be_answered_or_published(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $letter = AdviceLetter::factory()->for($agency)->create(['risk_level' => AdviceRiskLevel::Critical]);

        $this->actingAs($owner)->put(route('advice-letters.update', $letter), [
            'status' => AdviceLetterStatus::Published->value,
            'response_title' => 'Editoryal yanıt başlığı',
            'response_body' => str_repeat('Bu metin doğrulama sınırını karşılayacak kadar uzun bir editoryal yanıt örneğidir. ', 2),
        ])->assertSessionHasErrors('status');

        $this->assertSame(AdviceLetterStatus::Pending, $letter->fresh()->status);
    }

    public function test_unconsented_letter_cannot_be_published(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $letter = AdviceLetter::factory()->answered()->for($agency)->create(['publication_consent' => false]);

        $this->actingAs($owner)->put(route('advice-letters.update', $letter), [
            'status' => AdviceLetterStatus::Published->value,
            'response_title' => $letter->response_title,
            'response_body' => $letter->response_body,
        ])->assertSessionHasErrors('status');
    }

    public function test_letter_and_response_are_escaped(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $letter = AdviceLetter::factory()->answered()->create([
            'pseudonym' => '<script>alert(1)</script>',
            'response_body' => '<img src=x onerror=alert(1)>'.str_repeat(' güvenli metin', 10),
        ]);

        $this->actingAs($administrator)->get(route('advice-letters.show', $letter))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agency->id,
            'pseudonym' => 'Yolunu Arayan',
            'category' => 'personal',
            'question' => 'Son zamanlarda hayatımdaki değişikliklerle başa çıkmakta zorlanıyorum ve daha sağlıklı bir yol haritası oluşturmak istiyorum.',
            'publication_consent' => '1',
        ], $overrides);
    }
}
