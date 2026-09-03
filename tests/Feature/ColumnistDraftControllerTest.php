<?php

namespace Tests\Feature;

use App\ColumnistDraftStatus;
use App\Models\Agency;
use App\Models\AiColumnist;
use App\Models\ColumnistDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ColumnistDraftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_creates_local_draft_for_own_active_columnist(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $columnist = AiColumnist::factory()->for($agency)->create();
        $sourceNotes = str_repeat('Doğrulanmış kaynak notu ve editoryal bağlam. ', 3);

        $this->actingAs($editor)->post(route('columnist-drafts.store'), [
            'ai_columnist_id' => $columnist->id,
            'topic' => 'Kent yaşamında yeni dayanışma modelleri',
            'source_notes' => $sourceNotes,
        ])->assertRedirect();

        $draft = ColumnistDraft::query()->sole();
        $this->assertSame($agency->id, $draft->agency_id);
        $this->assertSame($editor->id, $draft->created_by);
        $this->assertSame(trim($sourceNotes), $draft->source_notes);
        $this->assertStringContainsString($columnist->disclosure, $draft->body);
        $this->assertSame('local_editorial_preview', $draft->prompt_snapshot['mode']);
        $this->assertStringNotContainsString($sourceNotes, (string) DB::table('columnist_drafts')->value('source_notes'));
    }

    public function test_editor_cannot_create_draft_for_foreign_columnist(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $foreignColumnist = AiColumnist::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->post(route('columnist-drafts.store'), [
            'ai_columnist_id' => $foreignColumnist->id,
            'topic' => 'Başka ajansa ait konu',
            'source_notes' => str_repeat('Yeterli uzunlukta kaynak ve doğrulama notu. ', 3),
        ])->assertSessionHasErrors('ai_columnist_id');

        $this->assertDatabaseCount('columnist_drafts', 0);
    }

    public function test_editor_cannot_approve_but_owner_can_review_draft(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $columnist = AiColumnist::factory()->for($agency)->create();
        $draft = ColumnistDraft::factory()->for($agency)->for($columnist, 'columnist')->create();

        $payload = [
            'headline' => 'Editoryal olarak gözden geçirilmiş köşe başlığı',
            'body' => str_repeat('Kaynaklarla desteklenen, görüş ve olguyu ayıran editoryal köşe metni. ', 4),
            'status' => ColumnistDraftStatus::Approved->value,
        ];

        $this->actingAs($editor)->put(route('columnist-drafts.update', $draft), $payload)
            ->assertSessionHasErrors('status');

        $this->actingAs($owner)->put(route('columnist-drafts.update', $draft), $payload)
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(ColumnistDraftStatus::Approved, $draft->status);
        $this->assertSame($owner->id, $draft->reviewed_by);
        $this->assertNotNull($draft->reviewed_at);
    }

    public function test_draft_is_tenant_isolated_and_output_is_escaped(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();
        $columnist = AiColumnist::factory()->for($agency)->create();
        $draft = ColumnistDraft::factory()->for($agency)->for($columnist, 'columnist')->create([
            'headline' => '<script>alert(1)</script>',
        ]);

        $this->actingAs($otherEditor)->get(route('columnist-drafts.show', $draft))->assertForbidden();

        $this->actingAs($editor)->get(route('columnist-drafts.show', $draft))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
