<?php

namespace Tests\Feature\Wiki;

use App\Models\Article;
use App\Models\Upload;
use App\Models\User;
use App\Services\ArticleSearchService;
use App\Services\ArticleService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * ヘルプ（システムページ）。通常のページと同じ見た目で表示し、編集・履歴は管理者だけ。
 * 通常のページのタイトルの名前空間には属さない
 */
class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    private ArticleService $service;

    private User $admin;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleService::class);
        $this->admin = User::factory()->admin()->create();
        $this->editor = User::factory()->create();
    }

    public function test_help_is_created_on_first_view_with_default_content()
    {
        $this->assertSame(0, Article::system(Article::SYSTEM_HELP)->count());

        $this->get(route('wiki.help'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Show')
                ->where('title', 'ヘルプ')
                ->where('article.url', '/-/help')
                ->where('showTabs', false)
                ->where('canEdit', false)
                ->where('backlinks', [])
                ->where('html', fn (string $html) => str_contains($html, 'Markdown 記法'))
            );

        $this->get(route('wiki.help'))->assertOk();

        $help = Article::system(Article::SYSTEM_HELP)->sole();
        $this->assertSame(1, $help->versions()->count());
        $this->assertNull($help->created_by);
    }

    public function test_non_admins_see_no_tabs_and_cannot_edit_or_view_history()
    {
        $help = $this->service->help();
        $this->service->update($help, ['title' => 'ヘルプ', 'body' => '二版'], $this->admin, 1);

        // 最終更新は日付だけ（更新者・版数は渡さない）
        $this->actingAs($this->editor)
            ->get(route('wiki.help'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('showTabs', false)
                ->where('canEdit', false)
                ->where('detailedMeta', false)
                ->where('article.updater', null)
                ->where('versionCount', null)
            );

        $this->actingAs($this->editor)->get(route('wiki.help.edit'))->assertForbidden();
        $this->actingAs($this->editor)->get(route('wiki.help.history'))->assertForbidden();
        $this->actingAs($this->editor)->get(route('wiki.help.version', 1))->assertForbidden();
        $this->actingAs($this->editor)->get(route('wiki.help.diff'))->assertForbidden();
        $this->actingAs($this->editor)
            ->patch(route('wiki.update', $help), ['title' => 'ヘルプ', 'body' => '書き換え', 'version_number' => 2])
            ->assertForbidden();
        $this->actingAs($this->editor)
            ->post(route('wiki.restore-version', [$help, 1]))
            ->assertForbidden();

        $this->assertSame('二版', $help->fresh()->body);
    }

    public function test_guests_cannot_edit_or_view_history()
    {
        $this->service->help();

        $this->get(route('wiki.help.edit'))->assertRedirect(route('login'));
        $this->get(route('wiki.help.history'))->assertForbidden();
        $this->get(route('wiki.help.version', 1))->assertForbidden();
        $this->get(route('wiki.help.diff'))->assertForbidden();
    }

    public function test_admin_can_edit_help_and_view_history()
    {
        $this->actingAs($this->admin)
            ->get(route('wiki.help'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('showTabs', true)
                ->where('canEdit', true)
                ->where('detailedMeta', true)
                ->where('versionCount', 1)
                ->where('urls.edit', '/-/help/edit')
                ->where('urls.history', '/-/help/history')
            );

        $help = Article::system(Article::SYSTEM_HELP)->sole();

        $this->actingAs($this->admin)
            ->get(route('wiki.help.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Edit')
                ->where('titleLocked', true)
                ->where('canDelete', false)
                ->where('urls.show', '/-/help')
            );

        $this->actingAs($this->admin)
            ->patch(route('wiki.update', $help), ['title' => 'ヘルプ', 'body' => '新しいヘルプ', 'version_number' => 1])
            ->assertRedirect('/-/help');

        $this->assertSame('新しいヘルプ', $help->fresh()->body);

        $this->actingAs($this->admin)
            ->get(route('wiki.help.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/History')
                ->where('versions.data.0.url', '/-/help/history/2')
                ->where('versions.data.0.diffUrl', '/-/help/diff?from=1&to=2')
                ->where('urls.view', '/-/help')
            );

        $this->actingAs($this->admin)->get(route('wiki.help.version', 1))->assertOk();
        $this->actingAs($this->admin)->get('/-/help/diff?from=1&to=2')->assertOk();

        $this->actingAs($this->admin)
            ->post(route('wiki.restore-version', [$help, 1]))
            ->assertRedirect('/-/help');
        $this->assertSame(3, $this->service->latestVersionNumber($help));
    }

    public function test_help_cannot_be_renamed_or_deleted()
    {
        $help = $this->service->help();

        $this->actingAs($this->admin)
            ->patch(route('wiki.update', $help), ['title' => '使い方', 'body' => '本文', 'version_number' => 1])
            ->assertSessionHasErrors('title');

        $this->actingAs($this->admin)
            ->delete(route('wiki.destroy', $help))
            ->assertForbidden();

        $this->assertSame('ヘルプ', $help->fresh()->title);
        $this->assertNotSoftDeleted($help);
    }

    public function test_help_is_outside_the_page_title_namespace()
    {
        $this->service->help();

        // [[ヘルプ]] は通常のページを指し、ヘルプとは別物
        $this->get('/ヘルプ')->assertOk()->assertInertia(fn (Assert $page) => $page->where('article', null));

        $page = $this->service->create(['title' => 'ヘルプ', 'body' => '通常のページ'], $this->editor);

        $this->get('/ヘルプ')->assertInertia(fn (Assert $p) => $p->where('article.id', $page->id));
        $this->get(route('wiki.help'))->assertInertia(fn (Assert $p) => $p->whereNot('article.id', $page->id));

        // 通常のページ「ヘルプ」があっても、ヘルプを保存できる
        $help = Article::system(Article::SYSTEM_HELP)->sole();
        $this->actingAs($this->admin)
            ->patch(route('wiki.update', $help), ['title' => 'ヘルプ', 'body' => '更新', 'version_number' => 1])
            ->assertRedirect('/-/help');
    }

    public function test_help_is_not_listed_searched_or_linked()
    {
        $help = $this->service->help();
        $this->service->update($help, ['title' => 'ヘルプ', 'body' => '固有の語ほげふが'], $this->admin, 1);

        $this->get(route('wiki.all-pages'))
            ->assertInertia(fn (Assert $page) => $page->where('pages', []));

        $this->assertSame([], app(ArticleSearchService::class)->search('ほげふが'));

        $this->get(route('wiki.recent-changes'))
            ->assertInertia(fn (Assert $page) => $page->where('versions.total', 0));

        $this->service->create(['title' => '別', 'body' => '本文'], $this->editor);
        $this->actingAs($this->editor)
            ->get('/別/-/edit')
            ->assertInertia(fn (Assert $page) => $page->where('pageTitles', []));
    }

    public function test_upload_usage_counts_help()
    {
        $upload = Upload::factory()->create();
        $help = $this->service->help();
        $this->service->update($help, ['title' => 'ヘルプ', 'body' => "![図]({$upload->url()})"], $this->admin, 1);

        $pages = app(UploadService::class)->pagesUsing($upload);

        $this->assertSame([$help->id], $pages->pluck('id')->all());
    }
}
