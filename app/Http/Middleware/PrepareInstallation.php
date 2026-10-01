<?php

namespace App\Http\Middleware;

use App\Setup\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 初期設定が済むまで、すべての画面を初回設定画面（/-/setup）へ誘導する。
 *
 * セッションやクッキーの暗号化には APP_KEY が要るため、web ミドルウェアより前（グローバル）で動き、
 * APP_KEY の生成・SQLite ファイルの作成・マイグレーションを自動で行う。
 * .env に書き込めない・DB を用意できないときは、セッションを使わない素の HTML で手順を示す。
 */
class PrepareInstallation
{
    public function __construct(private Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('-/up') || $this->installer->isInstalled()) {
            return $next($request);
        }

        $manualEnv = $this->installer->ensureAppKey($request->root());

        if ($manualEnv !== null) {
            return response()->view('setup.manual-env', ['env' => $manualEnv], 503);
        }

        try {
            $this->installer->prepareDatabase();
        } catch (Throwable $e) {
            report($e);

            return response()->view('setup.error', ['message' => $e->getMessage()], 503);
        }

        if (! $request->is('-/setup')) {
            return redirect('/-/setup');
        }

        return $next($request);
    }
}
