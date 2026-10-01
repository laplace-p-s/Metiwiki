<?php

namespace Tests\Feature\Wiki;

use App\Markdown\WikiMarkdown;
use App\Models\Upload;
use App\Models\User;
use App\Services\ArticleService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use Tests\Unit\JpegMetadataStripperTest;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('テスト用の画像を作るのに GD が要る');
        }

        Storage::fake(UploadService::DISK);
        $this->user = User::factory()->create();
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAs($this->user)->postJson(route('wiki.uploads.store'), ['file' => $file]);
    }

    /** GPS 入りの Exif を差し込んだ、実際に読める JPEG */
    private function jpegWithGps(): UploadedFile
    {
        $image = imagecreatetruecolor(8, 6);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $app0Length = unpack('n', substr($jpeg, 4, 2))[1];
        $bytes = substr($jpeg, 0, 4 + $app0Length)
            .JpegMetadataStripperTest::exifWithGps(6)
            .substr($jpeg, 4 + $app0Length);

        return UploadedFile::fake()->createWithContent('旅行の写真.JPG', $bytes);
    }

    public function test_guest_cannot_upload()
    {
        $this->postJson(route('wiki.uploads.store'), ['file' => UploadedFile::fake()->image('a.png')])
            ->assertUnauthorized();

        $this->assertSame(0, Upload::count());
    }

    public function test_png_is_stored_and_markdown_is_returned()
    {
        $response = $this->upload(UploadedFile::fake()->image('図 1.png', 20, 10))
            ->assertCreated();

        $upload = Upload::sole();
        $this->assertSame('image/png', $upload->mime);
        $this->assertSame('図 1.png', $upload->original_name);
        $this->assertSame($this->user->id, $upload->user_id);
        $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/[A-Za-z0-9]{40}\.png$#', $upload->path);
        Storage::disk(UploadService::DISK)->assertExists($upload->path);

        // URL は連番の id ではなく、推測できない token で指す
        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $upload->token);
        $url = '/-/uploads/'.$upload->token.'/'.rawurlencode('図 1.png');
        $response->assertExactJson([
            'id' => $upload->id,
            'url' => $url,
            'markdown' => "![図 1]({$url})",
        ]);
    }

    public function test_jpeg_location_is_removed_before_saving()
    {
        $this->upload($this->jpegWithGps())->assertCreated();

        $upload = Upload::sole();
        $stored = Storage::disk(UploadService::DISK)->get($upload->path);

        $this->assertSame('旅行の写真.jpg', $upload->original_name);
        $this->assertStringNotContainsString('SECRET-GPS', (string) $stored);
        $this->assertSame(strlen((string) $stored), $upload->size);
        $this->assertNotFalse(getimagesizefromstring((string) $stored));
    }

    public function test_svg_is_rejected()
    {
        $svg = UploadedFile::fake()->createWithContent(
            'evil.png',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->upload($svg)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, Upload::count());
    }

    public function test_file_with_fake_image_header_is_rejected()
    {
        // 先頭だけ JPEG に見せかけた HTML
        $fake = UploadedFile::fake()->createWithContent('fake.jpg', "\xFF\xD8\xFF\xE0<html><script>alert(1)</script></html>");

        $this->upload($fake)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, Upload::count());
    }

    public function test_size_limit_is_enforced()
    {
        config(['wiki.upload_max_kb' => 1]);

        $this->upload(UploadedFile::fake()->image('big.png', 300, 300)->size(2))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_limit_is_the_smaller_of_setting_and_php()
    {
        config(['wiki.upload_max_kb' => 1]);
        $this->assertSame(1024, UploadService::maxBytes());

        config(['wiki.upload_max_kb' => 10_000_000]);
        $this->assertSame((int) UploadedFile::getMaxFilesize(), UploadService::maxBytes());
    }

    public function test_edit_screen_receives_upload_limit()
    {
        $this->actingAs($this->user)
            ->get('/'.rawurlencode('新しいページ').'/-/edit')
            ->assertInertia(fn (Assert $page) => $page->where('uploadMaxBytes', UploadService::maxBytes()));
    }

    public function test_image_is_served_to_guests_with_safe_headers()
    {
        $this->upload(UploadedFile::fake()->image('a.png'));
        $upload = Upload::sole();
        auth()->logout();

        $response = $this->get($upload->url())->assertOk();

        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_wrong_filename_redirects_to_canonical_url()
    {
        $this->upload(UploadedFile::fake()->image('a.png'));
        $upload = Upload::sole();

        $this->get('/-/uploads/'.$upload->token.'/other.png')->assertRedirect($upload->url());
    }

    public function test_image_cannot_be_reached_by_sequential_id()
    {
        $this->upload(UploadedFile::fake()->image('a.png'));
        $upload = Upload::sole();

        $this->get('/-/uploads/'.$upload->id.'/a.png')->assertNotFound();
        $this->get('/-/uploads/'.$upload->id.'/x')->assertNotFound();
    }

    public function test_storage_serving_route_is_not_registered()
    {
        $this->upload(UploadedFile::fake()->image('a.png'));

        $this->get('/storage/'.Upload::sole()->path)->assertNotFound();
    }

    public function test_missing_image_is_404()
    {
        $this->get('/-/uploads/'.str_repeat('0', 24).'/a.png')->assertNotFound();

        $this->upload(UploadedFile::fake()->image('a.png'));
        $upload = Upload::sole();
        Storage::disk(UploadService::DISK)->delete($upload->path);

        $this->get($upload->url())->assertNotFound();
    }

    public function test_display_name_is_sanitized()
    {
        $this->assertSame('a b.png', UploadService::displayName("../a/[b]/../a (b)\n?.PNG", 'png'));
        $this->assertSame('image.jpg', UploadService::displayName('???.jpeg', 'jpg'));
        $this->assertSame('写真.webp', UploadService::displayName('写真.jpg', 'webp'));
    }

    public function test_uploaded_image_is_rendered_in_page()
    {
        $markdown = $this->upload(UploadedFile::fake()->image('図.png'))->json('markdown');

        $html = app(WikiMarkdown::class)->render("本文\n\n".$markdown)['html'];

        $this->assertStringContainsString('<img src="/-/uploads/', $html);
        $this->assertStringContainsString('alt="図"', $html);

        app(ArticleService::class)->create(['title' => '画像のページ', 'body' => $markdown], $this->user);
        $this->get('/'.rawurlencode('画像のページ'))
            ->assertInertia(fn (Assert $page) => $page->where('html', fn (string $h) => str_contains($h, '<img src="/-/uploads/')));
    }
}
