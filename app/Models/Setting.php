<?php

namespace App\Models;

use App\Support\SiteSettings;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * サイト設定（Wiki 名、自己登録の可否など）のキーと値。
 *
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        // リクエスト内でまとめて読み込んだ設定値を古いまま使わないようにする
        static::saved(fn () => app(SiteSettings::class)->flush());
        static::deleted(fn () => app(SiteSettings::class)->flush());
    }
}
