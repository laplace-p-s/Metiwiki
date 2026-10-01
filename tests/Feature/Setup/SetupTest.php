<?php

namespace Tests\Feature\Setup;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;
use App\Setup\Installer;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    private string $envDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markNotInstalled();

        // .env の書き込みを確かめるテスト用の置き場（本物の .env には触れない）
        $this->envDir = sys_get_temp_dir().'/metiwiki-setup-test-'.uniqid();
        File::makeDirectory($this->envDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->envDir);

        parent::tearDown();
    }

    /** @return array<string, string> */
    private function validInput(array $overrides = []): array
    {
        return [
            'site_name' => 'サンプル Wiki',
            'name' => '管理者',
            'login_id' => 'Admin',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
            'sample_pages' => 'on',
            ...$overrides,
        ];
    }

    public function test_every_page_redirects_to_setup_before_installation()
    {
        $this->get('/')->assertRedirect('/-/setup');
        $this->get('/-/login')->assertRedirect('/-/setup');
        $this->get('/Laravel')->assertRedirect('/-/setup');
    }

    public function test_health_check_is_not_redirected()
    {
        $this->get('/-/up')->assertOk();
    }

    public function test_setup_screen_is_shown()
    {
        $this->get('/-/setup')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('setup/Index')
                ->where('passwordRequirements', '8文字以上'));
    }

    public function test_setup_creates_admin_site_name_and_home_pages()
    {
        $this->post('/-/setup', $this->validInput())->assertRedirect('/');

        $admin = User::sole();
        $this->assertSame('admin', $admin->login_id);
        $this->assertTrue($admin->is_admin);
        $this->assertNull($admin->email);
        $this->assertAuthenticatedAs($admin);

        $settings = app(SiteSettings::class);
        $settings->flush();
        $this->assertTrue($settings->isInstalled());
        $this->assertSame('サンプル Wiki', $settings->siteName());
        $this->assertFalse($settings->registrationEnabled());

        $home = app(ArticleService::class)->resolve(ArticleService::HOME_TITLE)['article'];
        $this->assertNotNull($home);
        $this->assertStringContainsString('# サンプル Wiki', $home->body);
        $this->assertSame(4, Article::count());

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('name', 'サンプル Wiki'));
    }

    public function test_setup_without_sample_pages_creates_only_home()
    {
        $input = $this->validInput();
        unset($input['sample_pages']);

        $this->post('/-/setup', $input)->assertRedirect('/');

        $home = Article::sole();
        $this->assertTrue(app(ArticleService::class)->isHome($home));
        $this->assertStringContainsString('# サンプル Wiki', $home->body);
        $this->assertStringNotContainsString('[[', $home->body);
    }

    public function test_setup_validates_input()
    {
        $this->post('/-/setup', $this->validInput([
            'site_name' => '',
            'login_id' => 'with space',
            'password_confirmation' => 'different',
        ]))->assertSessionHasErrors(['site_name', 'login_id', 'password']);

        $this->assertSame(0, User::count());
    }

    public function test_setup_is_not_available_after_installation()
    {
        $this->post('/-/setup', $this->validInput());
        auth()->logout();

        $this->get('/-/setup')->assertNotFound();
        $this->post('/-/setup', $this->validInput(['login_id' => 'intruder']))->assertNotFound();

        $this->assertSame(1, User::count());
    }

    public function test_installer_refuses_second_installation()
    {
        $installer = app(Installer::class);
        $installer->install(['site_name' => 'A', 'name' => 'A', 'login_id' => 'a', 'email' => null, 'password' => 'password']);

        $this->expectException(RuntimeException::class);

        $installer->install(['site_name' => 'B', 'name' => 'B', 'login_id' => 'b', 'email' => null, 'password' => 'password']);
    }

    public function test_app_key_is_generated_into_new_env_file()
    {
        $this->app->useEnvironmentPath($this->envDir);
        config(['app.key' => null]);

        $manual = app(Installer::class)->ensureAppKey('https://wiki.example.com');

        $this->assertNull($manual);
        $env = (string) file_get_contents($this->envDir.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:\S{44}$/m', $env);
        $this->assertMatchesRegularExpression('/^APP_URL=https:\/\/wiki.example.com$/m', $env);
        $this->assertStringStartsWith('base64:', (string) config('app.key'));
    }

    public function test_app_key_is_added_to_existing_env_file_without_touching_other_values()
    {
        $this->app->useEnvironmentPath($this->envDir);
        file_put_contents($this->envDir.'/.env', "APP_NAME=Mine\nAPP_KEY=\nAPP_URL=http://keep.example\n");
        config(['app.key' => null]);

        app(Installer::class)->ensureAppKey('https://ignored.example');

        $env = (string) file_get_contents($this->envDir.'/.env');
        $this->assertStringContainsString('APP_NAME=Mine', $env);
        $this->assertStringContainsString('APP_URL=http://keep.example', $env);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', $env);
    }

    public function test_existing_app_key_is_kept()
    {
        $key = config('app.key');

        $this->assertNull(app(Installer::class)->ensureAppKey());
        $this->assertSame($key, config('app.key'));
    }

    public function test_manual_instructions_are_shown_when_env_is_not_writable()
    {
        $this->app->useEnvironmentPath($this->envDir.'/missing-directory');
        config(['app.key' => null]);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('設定ファイル（.env）を配置してください')
            ->assertSee('APP_KEY=base64:', false);
    }

    public function test_database_error_is_shown()
    {
        $this->mock(Installer::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturnFalse();
            $mock->shouldReceive('ensureAppKey')->andReturnNull();
            $mock->shouldReceive('prepareDatabase')->andThrow(new RuntimeException('書き込めません'));
        });

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('データベースを準備できませんでした')
            ->assertSee('書き込めません');
    }

    public function test_install_command_sets_up_wiki()
    {
        $this->artisan('wiki:install', [
            '--site-name' => 'CLI Wiki',
            '--name' => '管理者',
            '--login-id' => 'Root',
            '--password' => 'password',
        ])
            ->expectsQuestion('管理者のメールアドレス（任意）', '')
            ->expectsConfirmation('使い方のサンプルページを作成しますか？', 'yes')
            ->expectsOutputToContain('初期設定が完了しました')
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('root', $admin->login_id);
        $this->assertTrue($admin->is_admin);
        $this->assertSame('CLI Wiki', app(SiteSettings::class)->siteName());
        $this->assertSame(4, Article::count());
    }

    public function test_install_command_can_skip_sample_pages()
    {
        $this->artisan('wiki:install', [
            '--site-name' => 'CLI Wiki',
            '--name' => '管理者',
            '--login-id' => 'root',
            '--email' => 'root@example.com',
            '--password' => 'password',
            '--no-samples' => true,
        ])->assertSuccessful();

        $this->assertTrue(app(ArticleService::class)->isHome(Article::sole()));
    }

    public function test_install_command_answers_no_to_sample_pages()
    {
        $this->artisan('wiki:install', [
            '--site-name' => 'CLI Wiki',
            '--name' => '管理者',
            '--login-id' => 'root',
            '--email' => 'root@example.com',
            '--password' => 'password',
        ])
            ->expectsConfirmation('使い方のサンプルページを作成しますか？', 'no')
            ->assertSuccessful();

        $this->assertSame(1, Article::count());
    }

    public function test_install_command_reports_validation_errors()
    {
        $this->artisan('wiki:install', [
            '--site-name' => 'CLI Wiki',
            '--name' => '管理者',
            '--login-id' => 'bad id',
            '--email' => 'not-an-email',
            '--password' => 'password',
            '--samples' => true,
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_install_command_does_nothing_when_already_installed()
    {
        app(Installer::class)->install(['site_name' => 'A', 'name' => 'A', 'login_id' => 'a', 'email' => null, 'password' => 'password']);

        $this->artisan('wiki:install')
            ->expectsOutputToContain('既に完了しています')
            ->assertSuccessful();

        $this->assertSame(1, User::count());
    }
}
