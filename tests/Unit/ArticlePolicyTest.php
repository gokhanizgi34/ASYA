<?php

namespace Tests\Unit;

use App\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\UserRole;
use PHPUnit\Framework\TestCase;

class ArticlePolicyTest extends TestCase
{
    public function test_system_administrator_can_manage_any_article(): void
    {
        $administrator = new User(['role' => UserRole::SystemAdministrator]);
        $article = new Article(['agency_id' => 20, 'status' => ArticleStatus::Published]);
        $policy = new ArticlePolicy;

        $this->assertTrue($policy->view($administrator, $article));
        $this->assertTrue($policy->update($administrator, $article));
        $this->assertTrue($policy->delete($administrator, $article));
    }

    public function test_agency_user_cannot_access_another_agencys_article(): void
    {
        $editor = new User(['role' => UserRole::Editor, 'agency_id' => 10]);
        $article = new Article(['agency_id' => 20, 'status' => ArticleStatus::Draft]);
        $policy = new ArticlePolicy;

        $this->assertFalse($policy->view($editor, $article));
        $this->assertFalse($policy->update($editor, $article));
        $this->assertFalse($policy->delete($editor, $article));
    }

    public function test_editor_can_delete_draft_but_not_published_article(): void
    {
        $editor = new User(['role' => UserRole::Editor, 'agency_id' => 10]);
        $draft = new Article(['agency_id' => 10, 'status' => ArticleStatus::Draft]);
        $published = new Article(['agency_id' => 10, 'status' => ArticleStatus::Published]);
        $policy = new ArticlePolicy;

        $this->assertTrue($policy->delete($editor, $draft));
        $this->assertFalse($policy->delete($editor, $published));
    }
}
