<?php

namespace App\Providers;

use App\Markdown\WikiMarkdown;
use App\Models\User;
use App\Support\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // commonmark の環境構築は重いので、リクエスト内で使い回す
        $this->app->singleton(WikiMarkdown::class, fn () => new WikiMarkdown(config('app.url')));

        // サイト設定は 1 リクエストにつき 1 回だけ読み込む
        $this->app->scoped(SiteSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // 管理画面（ユーザー管理・招待・サイト設定）は管理者だけ
        Gate::define('admin', fn (User $user) => $user->is_admin);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // uncompromised()（流出パスワードの照合）は外部 API へ問い合わせるため使わない。
        // 外部に送信せず、オフラインでも動くことを優先する
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
            : null,
        );
    }
}
