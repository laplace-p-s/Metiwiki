<?php

namespace App\Support;

use App\Enums\ThemeColor;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use PDOException;

/**
 * サイト設定（settings テーブル）の読み書き。
 *
 * 全ページで Wiki 名などを参照するため、1 リクエストにつき 1 回だけまとめて読み込む。
 * コンテナの scoped シングルトンとして使い、Setting の保存・削除時にはキャッシュを捨てる。
 * 初期設定の前（テーブルが無い）でも例外にせず、未設定として扱う。
 */
class SiteSettings
{
    public const SITE_NAME = 'site_name';

    public const REGISTRATION_ENABLED = 'registration_enabled';

    public const INSTALLED_AT = 'installed_at';

    public const THEME_COLOR = 'theme_color';

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function flush(): void
    {
        $this->values = null;
    }

    /** Wiki 名（未設定なら APP_NAME） */
    public function siteName(): string
    {
        return $this->get(self::SITE_NAME) ?? (string) config('app.name');
    }

    /** 自己登録が有効か（既定は OFF） */
    public function registrationEnabled(): bool
    {
        return $this->get(self::REGISTRATION_ENABLED) === '1';
    }

    /** テーマ色（未設定・不明な値なら既定の色） */
    public function themeColor(): ThemeColor
    {
        return ThemeColor::tryFrom((string) $this->get(self::THEME_COLOR)) ?? ThemeColor::DEFAULT;
    }

    /** 初期設定（管理者の作成）が済んでいるか */
    public function isInstalled(): bool
    {
        return $this->get(self::INSTALLED_AT) !== null;
    }

    /**
     * @return array<string, string|null>
     */
    private function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            $values = [];
            foreach (Setting::query()->get(['key', 'value']) as $setting) {
                $values[$setting->key] = $setting->value;
            }

            return $this->values = $values;
        } catch (QueryException|PDOException|SQLiteDatabaseDoesNotExistException) {
            // DB やテーブルがまだ無い（初期設定の前）。次の呼び出しで読み直せるようキャッシュしない
            return [];
        }
    }
}
