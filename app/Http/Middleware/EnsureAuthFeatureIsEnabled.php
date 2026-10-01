<?php

namespace App\Http\Middleware;

use App\Support\AuthFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 自己登録・パスワードリセットが無効なときに、Fortify の該当ルートを 404 にする。
 *
 * Fortify のルートは個別にミドルウェアを指定できないため、web グループに入れてルート名で判定する。
 */
class EnsureAuthFeatureIsEnabled
{
    private const REGISTRATION_ROUTES = ['register', 'register.store'];

    private const PASSWORD_RESET_ROUTES = ['password.request', 'password.email', 'password.reset', 'password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs(...self::REGISTRATION_ROUTES) && ! AuthFeatures::registrationEnabled()) {
            abort(404);
        }

        if ($request->routeIs(...self::PASSWORD_RESET_ROUTES) && ! AuthFeatures::passwordResetEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
