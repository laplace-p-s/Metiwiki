<?php

namespace Tests\Feature\Wiki;

use App\Enums\VersionKind;
use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\ArticleUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ページの作成・編集・改名・削除・版の復元
 */
class ArticleEditingTest extends TestCase
{
    use RefreshDatabase;

    private ArticleService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleService::class);
        $this->user = User::factory()->create();
    }

    private function createArticle(string $title, string $body = '本文'): Article
    {
        return $this->service->create(['title' => $title, 'body' => $body], $this->user);
    }

    public function test_edit_screen_for_missing_page_is_creation_form()
    {
        $this->actingAs($this->user)
            ->get('/'.rawurlencode('新しいページ').'/-/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Edit')
                ->where('isNew', true)
                ->where('title', '新しいページ')
                ->where('version', 0),
            );
    }

    public function test_edit_screen_for_existing_page_has_latest_version()
    {
        $article = $this->createArticle('Laravel', 'v1');
        $this->service->update($article, ['title' => 'Laravel', 'body' => 'v2'], $this->user, 1);
        $this->createArticle('Vue');

        $this->actingAs($this->user)
            ->get('/Laravel/-/edit')
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Edit')
                ->where('isNew', false)
                ->where('body', 'v2')
                ->where('version', 2)
                ->where('pageTitles', ['Vue']),
            );
    }

    public function test_page_can_be_created()
    {
        $this->actingAs($this->user)
            ->post(route('wiki.store'), [
                'title' => '　Getting_Started ',
                'body' => '[[Laravel]]',
                'summary' => '新規作成',
            ])
            ->assertRedirect('/Getting_Started');

        $article = Article::sole();
        $this->assertSame('Getting Started', $article->title);
        $this->assertSame('新規作成', $article->versions()->first()->summary);
    }

    /** @return array<string, array{string}> */
    public static function invalidTitles(): array
    {
        return [
            'slash' => ['a/b'],
            'question' => ['a?b'],
            'hash' => ['a#b'],
            'bracket' => ['[a]'],
            'pipe' => ['a|b'],
            'hyphen only' => ['-'],
            'dot' => ['.'],
            'dot dot' => ['..'],
            'leading dot' => ['.env'],
            'well-known' => ['.well-known'],
            'htaccess' => ['.htaccess'],
            'php suffix' => ['test.php'],
            'php suffix uppercase' => ['Test.PHP'],
            'index.php' => ['index.php'],
            'home in English' => ['Home'],
            'public directory' => ['Build'],
            'public file' => ['favicon.ico'],
            'control character' => ["a\u{0007}b"],
            'too long' => [str_repeat('あ', 256)],
            'blank after normalization' => [' _ '],
        ];
    }

    #[DataProvider('invalidTitles')]
    public function test_invalid_titles_are_rejected(string $title)
    {
        $this->actingAs($this->user)
            ->post(route('wiki.store'), ['title' => $title, 'body' => '本文'])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Article::count());
    }

    /** @return array<string, array{string}> */
    public static function titlesWithDots(): array
    {
        return [
            'dot in the middle' => ['Node.js'],
            'version number' => ['PHP 8.3'],
            'trailing dot' => ['Ph.D.'],
            'php in the middle' => ['php.ini の設定'],
            'php without dot' => ['Laravel と PHP'],
        ];
    }

    #[DataProvider('titlesWithDots')]
    public function test_titles_with_dots_not_at_the_start_are_allowed(string $title)
    {
        $this->actingAs($this->user)
            ->post(route('wiki.store'), ['title' => $title, 'body' => '本文'])
            ->assertSessionHasNoErrors();

        $this->assertSame($title, Article::sole()->title);
        $this->get(ArticleUrl::show($title))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('title', $title));
    }

    public function test_duplicate_title_ignoring_case_is_rejected()
    {
        $this->createArticle('Laravel');

        $this->actingAs($this->user)
            ->post(route('wiki.store'), ['title' => 'ＬＡＲＡＶＥＬ', 'body' => '本文'])
            ->assertSessionHasErrors('title');
    }

    public function test_body_is_limited_in_bytes()
    {
        $this->actingAs($this->user)
            ->post(route('wiki.store'), [
                'title' => '長いページ',
                'body' => str_repeat('あ', intdiv(ArticleService::MAX_BODY_BYTES, 3) + 1),
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_page_can_be_updated_and_renamed()
    {
        $article = $this->createArticle('Old', 'v1');

        $this->actingAs($this->user)
            ->patch(route('wiki.update', $article), [
                'title' => 'New',
                'body' => 'v2',
                'summary' => '改名',
                'version_number' => 1,
            ])
            ->assertRedirect('/New');

        $this->assertSame('v2', $article->fresh()->body);
        $this->get('/Old')->assertRedirect('/New');
    }

    public function test_renaming_to_existing_title_is_rejected()
    {
        $article = $this->createArticle('A');
        $this->createArticle('B');

        $this->actingAs($this->user)
            ->patch(route('wiki.update', $article), ['title' => 'b', 'body' => '本文', 'version_number' => 1])
            ->assertSessionHasErrors('title');
    }

    public function test_home_page_cannot_be_renamed()
    {
        $home = $this->createArticle(ArticleService::HOME_TITLE);

        $this->actingAs($this->user)
            ->patch(route('wiki.update', $home), ['title' => '別名', 'body' => '本文', 'version_number' => 1])
            ->assertSessionHasErrors('title');

        $this->actingAs($this->user)
            ->patch(route('wiki.update', $home), [
                'title' => ArticleService::HOME_TITLE,
                'body' => '更新',
                'version_number' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');
    }

    public function test_stale_version_is_rejected_as_conflict()
    {
        $article = $this->createArticle('Laravel', 'v1');
        $other = User::factory()->create(['name' => '他の編集者']);
        $this->service->update($article, ['title' => 'Laravel', 'body' => '他の人の編集'], $other, 1);

        $this->actingAs($this->user)
            ->from('/Laravel/-/edit')
            ->patch(route('wiki.update', $article), [
                'title' => 'Laravel',
                'body' => '自分の編集',
                'version_number' => 1,
            ])
            ->assertRedirect('/Laravel/-/edit')
            ->assertSessionHasErrors('version_number')
            ->assertInertiaFlash('conflict.latestBody', '他の人の編集')
            ->assertInertiaFlash('conflict.latestVersion', 2)
            ->assertInertiaFlash('conflict.updatedBy', '他の編集者');

        $this->assertSame('他の人の編集', $article->fresh()->body);
    }

    public function test_page_can_be_deleted()
    {
        $article = $this->createArticle('Laravel');
        $updatedAt = $article->fresh()->updated_at;

        $this->travel(1)->hour();
        $this->actingAs($this->user)
            ->delete(route('wiki.destroy', $article), ['summary' => '重複のため'])
            ->assertRedirect('/');

        $this->assertSoftDeleted($article);

        $article = Article::withTrashed()->find($article->id);
        $this->assertSame($this->user->id, $article->deleted_by);
        // 最終編集の日時は削除で変わらない
        $this->assertEquals($updatedAt, $article->updated_at);

        // 削除は本文を変えない版として記録する
        $version = $article->versions()->first();
        $this->assertSame(2, $version->version_number);
        $this->assertSame(VersionKind::Delete, $version->kind);
        $this->assertSame('重複のため', $version->summary);
        $this->assertSame('本文', $version->body);
        $this->assertSame($this->user->id, $version->user_id);
    }

    public function test_deletion_reason_is_optional_and_limited()
    {
        $article = $this->createArticle('Laravel');

        $this->actingAs($this->user)
            ->delete(route('wiki.destroy', $article), ['summary' => str_repeat('あ', 256)])
            ->assertSessionHasErrors('summary');

        $this->assertNotSoftDeleted($article);

        $this->actingAs($this->user)
            ->delete(route('wiki.destroy', $article))
            ->assertRedirect('/');

        $this->assertNull(Article::withTrashed()->find($article->id)->versions()->first()->summary);
    }

    public function test_history_marks_delete_and_restore_versions()
    {
        $admin = User::factory()->admin()->create();
        $article = $this->createArticle('Laravel', 'v1');
        $this->service->delete($article, $this->user);
        $this->service->restoreDeleted($article, $admin);

        $this->get(ArticleUrl::history('Laravel'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('latestVersion', 3)
                ->where('versions.data.0.kind', 'restore')
                ->where('versions.data.0.diffUrl', null)
                ->where('versions.data.1.kind', 'delete')
                ->where('versions.data.1.diffUrl', null)
                ->where('versions.data.2.kind', 'edit')
            );
    }

    public function test_recent_changes_show_only_deletion_records_of_deleted_pages()
    {
        $admin = User::factory()->admin()->create();
        $deleted = $this->createArticle('消したページ');
        $this->service->delete($deleted, $this->user, '不要');
        $restored = $this->createArticle('戻したページ');
        $this->service->delete($restored, $this->user);
        $this->service->restoreDeleted($restored, $admin);

        $rows = $this->get(route('wiki.recent-changes'))->viewData('page')['props']['versions']['data'];

        $of = fn (string $title) => array_values(array_map(
            fn (array $row) => [$row['kind'], $row['isDeleted']],
            array_filter($rows, fn (array $row) => $row['article']['title'] === $title),
        ));

        // 削除済みページは編集の版を隠し、削除の版だけを出す
        $this->assertSame([['delete', true]], $of('消したページ'));
        // 復元したページは作成・削除・復元のすべて
        $this->assertSame([['restore', false], ['delete', false], ['edit', false]], $of('戻したページ'));
    }

    public function test_home_page_cannot_be_deleted()
    {
        $home = $this->createArticle(ArticleService::HOME_TITLE);

        $this->actingAs($this->user)
            ->delete(route('wiki.destroy', $home))
            ->assertForbidden();

        $this->assertNotSoftDeleted($home);
    }

    public function test_version_can_be_restored()
    {
        $article = $this->createArticle('Laravel', 'v1');
        $this->service->update($article, ['title' => 'Laravel', 'body' => 'v2'], $this->user, 1);

        $this->actingAs($this->user)
            ->post(route('wiki.restore-version', [$article, 1]))
            ->assertRedirect('/Laravel');

        $this->assertSame('v1', $article->fresh()->body);
        $this->assertSame(3, $this->service->latestVersionNumber($article));
    }

    public function test_restoring_missing_version_is_404()
    {
        $article = $this->createArticle('Laravel');

        $this->actingAs($this->user)
            ->post(route('wiki.restore-version', [$article, 9]))
            ->assertNotFound();
    }
}
