<?php

namespace Tests\Feature\Admin;

use App\Enums\ThemeColor;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_settings_screen_shows_current_values()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Settings')
                ->where('siteName', config('app.name'))
                ->where('registrationEnabled', false)
                ->where('passwordResetEnabled', false),
            );
    }

    public function test_admin_can_change_site_name_and_open_registration()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.settings.update'), [
                'site_name' => '  みんなの Wiki  ',
                'registration_enabled' => true,
                'theme_color' => 'blue',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('name', 'みんなの Wiki'));

        auth()->logout();

        $this->get(route('register'))->assertOk();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
    }

    public function test_admin_can_close_registration()
    {
        app(SiteSettings::class)->set(SiteSettings::REGISTRATION_ENABLED, '1');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.settings.update'), [
                'site_name' => 'Wiki',
                'registration_enabled' => false,
                'theme_color' => 'blue',
            ]);

        auth()->logout();

        $this->get(route('register'))->assertNotFound();
    }

    public function test_site_settings_are_validated()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.settings.update'), [
                'site_name' => '',
                'registration_enabled' => 'maybe',
                'theme_color' => 'gold',
            ])
            ->assertSessionHasErrors(['site_name', 'registration_enabled', 'theme_color']);

        $this->assertSame(ThemeColor::DEFAULT, app(SiteSettings::class)->themeColor());
    }

    public function test_theme_color_defaults_to_blue()
    {
        $this->get('/')
            ->assertSee('data-theme="blue"', false)
            ->assertInertia(fn (Assert $page) => $page->where('themeColor', 'blue'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('themeColor', 'blue')
                ->has('themeColors', count(ThemeColor::cases()))
                ->where('themeColors.0', ['value' => 'blue', 'label' => '青'])
            );
    }

    public function test_admin_can_change_theme_color()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.settings.update'), [
                'site_name' => 'Wiki',
                'registration_enabled' => false,
                'theme_color' => 'teal',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        auth()->logout();

        // ゲストの画面にも反映される（最初の表示は html の data-theme、画面遷移は共有 props）
        $this->get('/')
            ->assertSee('data-theme="teal"', false)
            ->assertInertia(fn (Assert $page) => $page->where('themeColor', 'teal'));
    }

    public function test_unknown_stored_theme_color_falls_back_to_default()
    {
        app(SiteSettings::class)->set(SiteSettings::THEME_COLOR, 'removed-color');

        $this->get('/')->assertSee('data-theme="blue"', false);
    }

    public function test_page_title_uses_site_name()
    {
        app(SiteSettings::class)->set(SiteSettings::SITE_NAME, 'タイトル確認 Wiki');

        $this->get('/')
            ->assertSee('<meta name="application-name" content="タイトル確認 Wiki">', false)
            ->assertSee('<title>タイトル確認 Wiki</title>', false);
    }
}
