<?php

namespace Tests\Feature\Models;

use App\Models\Article;
use App\Models\ArticleLink;
use App\Models\ArticleRedirect;
use App\Models\ArticleVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_title_also_sets_title_key()
    {
        $article = Article::factory()->create(['title' => 'Getting Started']);

        $this->assertSame('getting started', $article->title_key);
        $this->assertSame('getting started', $article->fresh()->active_title_key);
    }

    public function test_titles_differing_only_in_case_conflict()
    {
        Article::factory()->create(['title' => 'Laravel']);

        $this->expectException(QueryException::class);

        Article::factory()->create(['title' => 'laravel']);
    }

    public function test_voiced_and_unvoiced_kana_do_not_conflict()
    {
        // MySQL の utf8mb4_unicode_ci では同一視されるが、照合キーの完全一致では別ページになる
        Article::factory()->create(['title' => 'ハ']);
        Article::factory()->create(['title' => 'バ']);

        $this->assertSame(2, Article::count());
    }

    public function test_deleted_article_title_can_be_reused()
    {
        $old = Article::factory()->create(['title' => 'Laravel']);
        $old->delete();

        $this->assertNull($old->fresh()->active_title_key);

        $new = Article::factory()->create(['title' => 'Laravel']);

        $this->assertSame('laravel', $new->fresh()->active_title_key);
    }

    public function test_restoring_article_sets_active_title_key_again()
    {
        $article = Article::factory()->create(['title' => 'Laravel']);
        $article->delete();
        $article->restore();

        $this->assertSame('laravel', $article->fresh()->active_title_key);
    }

    public function test_force_deleting_article_cascades_to_related_rows()
    {
        $article = Article::factory()->create();
        ArticleVersion::factory()->for($article)->create();
        ArticleLink::factory()->create(['from_article_id' => $article->id]);
        ArticleRedirect::factory()->for($article)->create();

        $article->forceDelete();

        $this->assertSame(0, ArticleVersion::count());
        $this->assertSame(0, ArticleLink::count());
        $this->assertSame(0, ArticleRedirect::count());
    }

    public function test_deleting_user_keeps_articles_and_versions()
    {
        $user = User::factory()->create();
        $article = Article::factory()->create(['created_by' => $user->id]);
        $version = ArticleVersion::factory()->for($article)->create(['user_id' => $user->id]);

        $user->delete();

        $article->refresh();
        $this->assertNull($article->created_by);
        $this->assertNull($article->updated_by);
        $this->assertNull($version->fresh()->user_id);
    }

    public function test_version_numbers_are_unique_per_article()
    {
        $article = Article::factory()->create();
        ArticleVersion::factory()->for($article)->create(['version_number' => 1]);
        ArticleVersion::factory()->create(['version_number' => 1]);

        $this->expectException(QueryException::class);

        ArticleVersion::factory()->for($article)->create(['version_number' => 1]);
    }

    public function test_versions_are_ordered_newest_first()
    {
        $article = Article::factory()->create();
        foreach ([1, 3, 2] as $number) {
            ArticleVersion::factory()->for($article)->create(['version_number' => $number]);
        }

        $this->assertSame([3, 2, 1], $article->versions->pluck('version_number')->all());
    }

    public function test_redirect_old_title_keys_are_unique()
    {
        ArticleRedirect::factory()->create(['old_title_key' => 'old title']);

        $this->expectException(QueryException::class);

        ArticleRedirect::factory()->create(['old_title_key' => 'old title']);
    }
}
