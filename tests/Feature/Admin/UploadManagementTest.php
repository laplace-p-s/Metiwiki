<?php

namespace Tests\Feature\Admin;

use App\Models\Upload;
use App\Models\User;
use App\Services\ArticleService;
use App\Services\UploadService;
use App\Support\ArticleUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UploadManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ArticleService $articles;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(UploadService::DISK);
        $this->admin = User::factory()->admin()->create();
        $this->articles = app(ArticleService::class);
    }

    private function storedUpload(array $attributes = []): Upload
    {
        $upload = Upload::factory()->create(['user_id' => $this->admin->id, ...$attributes]);
        Storage::disk(UploadService::DISK)->put($upload->path, 'image');

        return $upload;
    }

    private function markdown(Upload $upload): string
    {
        return "![画像]({$upload->url()})";
    }

    public function test_upload_list_requires_admin()
    {
        $upload = $this->storedUpload();
        $editor = User::factory()->create();

        $this->get(route('admin.uploads.index'))->assertRedirect(route('login'));
        $this->delete(route('admin.uploads.destroy', $upload))->assertRedirect(route('login'));

        $this->actingAs($editor)->get(route('admin.uploads.index'))->assertForbidden();
        $this->actingAs($editor)->delete(route('admin.uploads.destroy', $upload))->assertForbidden();

        $this->assertModelExists($upload);
        Storage::disk(UploadService::DISK)->assertExists($upload->path);
    }

    public function test_uploads_are_listed_with_usage_in_latest_bodies()
    {
        $used = $this->storedUpload(['original_name' => '使用中.png', 'size' => 2048]);
        $unused = $this->storedUpload(['original_name' => '未使用.png', 'size' => 1024]);
        $oldOnly = $this->storedUpload(['original_name' => '古い版だけ.png', 'size' => 512]);
        $deletedOnly = $this->storedUpload(['original_name' => '削除済みページだけ.png', 'size' => 256]);

        $this->articles->create(['title' => 'ページ1', 'body' => $this->markdown($used)], $this->admin);
        $this->articles->create(['title' => 'ページ2', 'body' => "前\n".$this->markdown($used)], $this->admin);

        // 古い版でだけ使っている画像は数えない
        $page = $this->articles->create(['title' => 'ページ3', 'body' => $this->markdown($oldOnly)], $this->admin);
        $this->articles->update($page, ['title' => 'ページ3', 'body' => '画像を外した'], $this->admin, 1);

        // 削除済みページでだけ使っている画像も数えない
        $this->articles->create(['title' => 'ページ4', 'body' => $this->markdown($deletedOnly)], $this->admin)->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.uploads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Uploads')
                ->where('uploads.total', 4)
                ->where('totalSize', 2048 + 1024 + 512 + 256)
                // 新しい順
                ->where('uploads.data.0.id', $deletedOnly->id)
                ->where('uploads.data.0.usageCount', 0)
                ->where('uploads.data.1.id', $oldOnly->id)
                ->where('uploads.data.1.usageCount', 0)
                ->where('uploads.data.2.id', $unused->id)
                ->where('uploads.data.2.usageCount', 0)
                ->where('uploads.data.3.id', $used->id)
                ->where('uploads.data.3.usageCount', 2)
                ->where('uploads.data.3.url', $used->url())
                ->where('uploads.data.3.user', $this->admin->name)
                ->where('uploads.data.3.usedIn', [
                    ['title' => 'ページ1', 'url' => ArticleUrl::show('ページ1')],
                    ['title' => 'ページ2', 'url' => ArticleUrl::show('ページ2')],
                ])
            );
    }

    public function test_usage_is_matched_by_token()
    {
        $used = $this->storedUpload();
        $unused = $this->storedUpload();

        $this->articles->create(['title' => 'ページ', 'body' => $this->markdown($used)], $this->admin);
        // 連番の id を使った URL の形は数えない
        $this->articles->create(['title' => '別', 'body' => "![図](/-/uploads/{$unused->id}/a.png)"], $this->admin);

        $service = app(UploadService::class);
        $this->assertCount(1, $service->pagesUsing($used));
        $this->assertCount(0, $service->pagesUsing($unused));
    }

    public function test_admin_can_delete_upload()
    {
        $upload = $this->storedUpload(['original_name' => '消す画像.png']);
        $this->articles->create(['title' => 'ページ', 'body' => $this->markdown($upload)], $this->admin);

        $this->actingAs($this->admin)
            ->from(route('admin.uploads.index'))
            ->delete(route('admin.uploads.destroy', $upload))
            ->assertRedirect(route('admin.uploads.index'))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertModelMissing($upload);
        Storage::disk(UploadService::DISK)->assertMissing($upload->path);

        // 本文の記法は残るが、画像は配信されなくなる
        $this->get($upload->url())->assertNotFound();
    }
}
