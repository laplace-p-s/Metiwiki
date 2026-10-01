<?php

namespace App\Support;

/**
 * 実行時に ON/OFF が変わる認証機能の判定。
 *
 * Fortify の features は起動時にルート登録を固定するため、サイト設定やメール設定で
 * 切り替える機能はルートを登録したうえで、ここでの判定により利用可否を決める。
 */
class AuthFeatures
{
    /** 自己登録の可否を持つサイト設定のキー */
    public const REGISTRATION_SETTING = SiteSettings::REGISTRATION_ENABLED;

    /** 自己登録が有効か（サイト設定。既定は OFF） */
    public static function registrationEnabled(): bool
    {
        return app(SiteSettings::class)->registrationEnabled();
    }

    /**
     * メールによるパスワードリセットが有効か。
     *
     * メールを実際に送れるメーラーが設定されているときだけ有効にする。
     * 既定の log（ログに書くだけ）と array（テスト用）では送れないため無効。
     */
    public static function passwordResetEnabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
