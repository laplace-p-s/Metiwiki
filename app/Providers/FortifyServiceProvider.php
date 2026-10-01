<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\AuthFeatures;
use App\Support\PasswordRequirements;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * 名前付きレート制限 auth-forms の対象（自己登録・パスワードリセットのメール送信・パスワードの再設定）
     */
    private const THROTTLED_FORM_ROUTES = ['register.store', 'password.email', 'password.update'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $this->rememberReturnPath($request);

            return Inertia::render('auth/Login', [
                'canResetPassword' => AuthFeatures::passwordResetEnabled(),
                'canRegister' => AuthFeatures::registrationEnabled(),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordRequirements' => PasswordRequirements::describe(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * ログイン画面の `?redirect=`（ログインボタンを押したページのパス）を、ログイン後の戻り先にする。
     * Fortify のログイン応答（2 段階認証を含む）は redirect()->intended() なので、url.intended に入れる。
     * 外部サイトへ飛ばされないよう、サイト内の絶対パスだけを受け付ける
     */
    private function rememberReturnPath(Request $request): void
    {
        $path = $request->query('redirect');

        if (! is_string($path)
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path)
        ) {
            return;
        }

        $request->session()->put('url.intended', url($path));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Fortify は登録・パスワードリセットのルートに throttle を付けないため、
        // config('fortify.middleware') で全 Fortify ルートに付け、対象外のルートは制限なしにする
        RateLimiter::for('auth-forms', function (Request $request) {
            $routeName = $request->route()?->getName();

            if (! in_array($routeName, self::THROTTLED_FORM_ROUTES, true)) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by($routeName.'|'.$request->ip());
        });
    }
}
