<?php

namespace App\Http\Middleware;

use App\Support\AuthFeatures;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            // Wiki 名（サイト設定。未設定なら APP_NAME）
            'name' => app(SiteSettings::class)->siteName(),
            // テーマ色（サイト設定）。最初の表示は app.blade.php の data-theme、画面遷移後はこの値で切り替える
            'themeColor' => app(SiteSettings::class)->themeColor()->value,
            'auth' => [
                // 全ページに載るため、画面で使う項目だけにする（ログイン中の本人の情報のみ）
                'user' => $user?->only(['id', 'name', 'login_id', 'email', 'is_admin']),
            ],
            // ヘッダーの「アカウント登録」リンク用。ログイン中は不要なので DB を引かない
            'canRegister' => fn () => $user === null && AuthFeatures::registrationEnabled(),
            // Metiwiki のバージョン。更新の要否を判断する管理者にだけ渡す（外部から版を推測されないように）
            'appVersion' => $user?->is_admin ? config('app.version') : null,
        ];
    }
}
